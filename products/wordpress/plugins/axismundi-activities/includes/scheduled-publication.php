<?php
/** Private scheduling intent, never an Activity or an attribution authority. @package AxismundiActivities */
defined( 'ABSPATH' ) || exit;

function axismundi_act_submissions_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_activity_submission';
}

/** Source-neutral identity; this slice supplies only the WordPress Post Create adapter/clock. */
function axismundi_act_install_submissions( bool $migrate_legacy = false ) : bool {
	global $wpdb;
	$table = axismundi_act_submissions_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		intent_uuid char(36) NOT NULL,
		source_owner varchar(64) NOT NULL,
		source_kind varchar(64) NOT NULL,
		source_ref bigint(20) unsigned NOT NULL,
		activity_type varchar(32) NOT NULL,
		principal_user_id bigint(20) unsigned NOT NULL,
		actor_identity_id bigint(20) unsigned NOT NULL,
		actor_uri text NOT NULL,
		object_uri text NOT NULL,
		expected_version datetime NOT NULL,
		source_hash char(64) NOT NULL,
		audience_json longtext NOT NULL,
		scheduled_for datetime NOT NULL,
		state varchar(16) NOT NULL,
		error_code varchar(64) NOT NULL DEFAULT '',
		updated_at datetime NOT NULL,
		PRIMARY KEY  (intent_uuid),
		UNIQUE KEY source (source_owner, source_kind, source_ref)
	) ENGINE=InnoDB {$charset};" );
	$columns = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
	$primary = (array) $wpdb->get_col( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'PRIMARY'", $table ), 4 );
	$source = (array) $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'source'", $table ), ARRAY_A );
	usort( $source, static fn( array $a, array $b ) : int => (int) $a['Seq_in_index'] <=> (int) $b['Seq_in_index'] );
	$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
	$valid = ! array_diff( array( 'source_owner', 'source_kind', 'source_ref', 'activity_type', 'intent_uuid', 'principal_user_id', 'actor_identity_id', 'actor_uri', 'object_uri', 'expected_version', 'source_hash', 'audience_json', 'scheduled_for', 'state', 'error_code', 'updated_at' ), $columns )
		&& ! in_array( 'post_id', $columns, true ) && array( 'intent_uuid' ) === $primary
		&& array( 'source_owner', 'source_kind', 'source_ref' ) === array_column( $source, 'Column_name' )
		&& 0 === (int) ( $source[0]['Non_unique'] ?? 1 ) && 'InnoDB' === $engine;
	return $valid && ( ! $migrate_legacy || axismundi_act_migrate_pending_submissions( $wpdb->prefix . 'ax_activity_pending', $table ) );
}

/** Replay-safe v12 copy. Keep the verified old table as an archive, never a second read path. */
function axismundi_act_migrate_pending_submissions( string $legacy, string $target ) : bool {
	global $wpdb;
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $legacy ) ) );
	if ( null === $exists ) { return '' === $wpdb->last_error; }
	$columns = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $legacy ) );
	$fields = array( 'intent_uuid', 'principal_user_id', 'actor_identity_id', 'actor_uri', 'object_uri', 'expected_version', 'source_hash', 'audience_json', 'scheduled_for', 'state', 'error_code', 'updated_at' );
	if ( array_diff( array_merge( array( 'post_id' ), $fields ), $columns ) ) { return false; }
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return false; }
	$list = implode( ', ', $fields );
	$ok = $wpdb->query( $wpdb->prepare(
		"INSERT INTO %i (source_owner, source_kind, source_ref, activity_type, {$list})
		 SELECT 'wordpress-core', 'post', post_id, 'Create', {$list} FROM %i
		 ON DUPLICATE KEY UPDATE source_owner = source_owner", $target, $legacy
	) );
	$equal = implode( ' AND ', array_map( static fn( string $field ) : string => "(BINARY n.{$field} <=> BINARY o.{$field})", $fields ) );
	$bad = false !== $ok ? $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM %i o LEFT JOIN %i n ON n.source_owner = 'wordpress-core'
		 AND n.source_kind = 'post' AND n.source_ref = o.post_id
		 WHERE n.intent_uuid IS NULL OR n.activity_type <> 'Create' OR NOT ({$equal})", $legacy, $target
	) ) : null;
	if ( null === $bad || 0 !== (int) $bad || false === $wpdb->query( 'COMMIT' ) ) {
		$wpdb->query( 'ROLLBACK' );
		return false;
	}
	return true;
}

/** OP's existing source namespace; post ID belongs only to this adapter. */
function axismundi_act_post_submission_source( int $post_id ) : array {
	return array( 'source_owner' => 'wordpress-core', 'source_kind' => 'post', 'source_ref' => $post_id );
}

/** Core changes modified/status on publication; compare the frozen source independently. */
function axismundi_act_scheduled_source_hash( WP_Post $post ) : string {
	return hash( 'sha256', wp_json_encode( array( $post->post_title, $post->post_content, $post->post_excerpt,
		$post->post_author, $post->post_password, $post->post_date_gmt ) ) );
}

function axismundi_act_get_activity_submission( string $owner, string $kind, int $ref ) : ?array {
	global $wpdb;
	if ( $ref <= 0 || '' === $owner || '' === $kind || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION ) ) { return null; }
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE source_owner = %s AND source_kind = %s AND source_ref = %d', axismundi_act_submissions_table(), $owner, $kind, $ref ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

function axismundi_act_scheduled_post_submission( int $post_id ) : ?array {
	return axismundi_act_get_activity_submission( 'wordpress-core', 'post', $post_id );
}

/** A named lock spans the ledger's own transaction without nesting SQL transactions. */
function axismundi_act_submission_lock( array $source, callable $work ) {
	global $wpdb;
	$key = 'ax-submission-' . substr( hash( 'sha256', wp_json_encode( array( DB_NAME, $wpdb->prefix, $source['source_owner'], $source['source_kind'], (string) $source['source_ref'] ) ) ), 0, 48 );
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $key ) ) ) {
		return new WP_Error( 'ax_act_pending_lock', 'Scheduled publication is busy.' );
	}
	try { return $work(); }
	finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) ); }
}

/** Consume exactly the frozen intent inside the Activity repository transaction. */
function axismundi_act_submission_commit_callback( array $intent ) : callable {
	return static function ( array $activity ) use ( $intent ) {
		global $wpdb;
		if ( $activity['activity_type'] !== $intent['activity_type'] || $activity['actor_uri'] !== $intent['actor_uri'] || $activity['object_uri'] !== $intent['object_uri'] ) {
			return new WP_Error( 'ax_act_pending_identity', 'The scheduled Activity does not match the frozen intent.' );
		}
		$updated = $wpdb->update( axismundi_act_submissions_table(), array( 'state' => 'completed', 'error_code' => '', 'updated_at' => current_time( 'mysql', true ) ),
			array( 'source_owner' => $intent['source_owner'], 'source_kind' => $intent['source_kind'], 'source_ref' => $intent['source_ref'], 'intent_uuid' => $intent['intent_uuid'], 'state' => 'pending' ) );
		return 1 === $updated ? true : new WP_Error( 'ax_act_pending_consume', 'The scheduled publication intent could not be consumed.' );
	};
}

/** Persist intent after source metadata and mentions, not on autosaves or revisions. */
function axismundi_act_capture_scheduled_publication( int $post_id, WP_Post $post, bool $update, ?WP_Post $before = null, bool $rest_complete = false ) : void {
	unset( $update, $before );
	if ( AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION ) ) { return; }
	if ( 'post' !== $post->post_type || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id )
		|| ( wp_is_serving_rest_request() && ! $rest_complete ) || ! axismundi_act_post_lifecycle_applies( $post ) ) { return; }
	if ( 'future' !== $post->post_status ) {
		if ( 'publish' !== $post->post_status ) {
			axismundi_act_submission_lock( axismundi_act_post_submission_source( $post_id ), static function () use ( $post_id ) {
				global $wpdb;
				return $wpdb->update( axismundi_act_submissions_table(), array( 'state' => 'cancelled', 'updated_at' => current_time( 'mysql', true ) ), array_merge( axismundi_act_post_submission_source( $post_id ), array( 'state' => 'pending' ) ) );
			} );
		}
		return;
	}
	if ( ! axismundi_actors_account_selection_enabled() ) { return; }
	$result = axismundi_act_submission_lock( axismundi_act_post_submission_source( $post_id ), static function () use ( $post_id ) {
		global $wpdb;
		$post = get_post( $post_id );
		$user = get_current_user_id();
		$actor = $user > 0 && user_can( $user, 'edit_post', $post_id ) ? axismundi_act_request_publish_actor( $post, $user ) : new WP_Error( 'ax_act_pending_principal', 'A schedule requires an authenticated publisher.' );
		$audience = ! is_wp_error( $actor ) ? axismundi_op_post_article_audience( $post, null, $actor->get_uri() ) : $actor;
		$error = is_wp_error( $audience ) ? $audience : null;
		$row = array_merge( axismundi_act_post_submission_source( $post_id ), array( 'activity_type' => 'Create', 'intent_uuid' => wp_generate_uuid4(), 'principal_user_id' => $user,
			'actor_identity_id' => is_wp_error( $actor ) ? 0 : $actor->get_identity_id(),
			'actor_uri' => is_wp_error( $actor ) ? '' : $actor->get_uri(), 'object_uri' => axismundi_op_post_object_uri( $post ),
			'expected_version' => $post->post_modified_gmt, 'source_hash' => axismundi_act_scheduled_source_hash( $post ),
			'audience_json' => wp_json_encode( is_array( $audience ) ? $audience : array() ),
			'scheduled_for' => $post->post_date_gmt, 'state' => $error ? 'failed' : 'pending',
			'error_code' => $error ? $error->get_error_code() : '', 'updated_at' => current_time( 'mysql', true ) ) );
		if ( false === $wpdb->replace( axismundi_act_submissions_table(), $row ) ) {
			// Never leave an older valid intent usable after an unsuccessful replacement.
			$wpdb->update( axismundi_act_submissions_table(), array( 'state' => 'failed', 'error_code' => 'ax_act_pending_write' ), axismundi_act_post_submission_source( $post_id ) );
			return new WP_Error( 'ax_act_pending_write', 'The scheduled publication intent could not be stored.' );
		}
		return $error ?: true;
	} );
	if ( is_wp_error( $result ) ) {
		set_transient( 'ax_act_publish_error_' . get_current_user_id() . '_' . $post_id, $result->get_error_message(), MINUTE_IN_SECONDS );
		do_action( 'axismundi_act_post_create_failed', $result, $post );
	}
}
add_action( 'wp_after_insert_post', 'axismundi_act_capture_scheduled_publication', 120, 4 );
add_action( 'rest_after_insert_post', static function ( WP_Post $post ) : void {
	axismundi_act_capture_scheduled_publication( $post->ID, $post, true, null, true );
}, 120 );

/** Intent is disposable scheduling data, not a graveyard or an Object identity record. */
add_action( 'deleted_post', static function ( int $post_id ) : void {
	global $wpdb;
	if ( AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION ) ) { return; }
	$wpdb->delete( axismundi_act_submissions_table(), axismundi_act_post_submission_source( $post_id ), array( '%s', '%s', '%d' ) );
} );

/** Return true for any recorded schedule, including failures: never fall back to a live cookie. */
function axismundi_act_execute_scheduled_publication( WP_Post $post, ?WP_Post $before = null ) : bool {
	$intent = axismundi_act_scheduled_post_submission( $post->ID );
	if ( ! $intent ) { return $before instanceof WP_Post && 'future' === $before->post_status; }
	if ( in_array( $intent['state'], array( 'completed', 'cancelled' ), true ) ) { return false; }
	$result = axismundi_act_submission_lock( axismundi_act_post_submission_source( $post->ID ), static function () use ( $post, $before ) {
		global $wpdb;
		$intent = axismundi_act_scheduled_post_submission( $post->ID );
		if ( ! $intent || 'pending' !== $intent['state'] ) { return true; }
		$post = get_post( $post->ID );
		if ( 'publish' !== $post->post_status ) { return true; }
		$user = (int) $intent['principal_user_id'];
		$actor = axismundi_act_c2s_resolve_actor( $user, (int) $intent['actor_identity_id'] );
		$error = null;
		if ( 'Create' !== $intent['activity_type'] ) {
			$error = new WP_Error( 'ax_act_submission_type', 'This source adapter supports only Create submissions.' );
		} elseif ( ! user_can( $user, 'edit_post', $post->ID ) || is_wp_error( $actor ) || ! $actor instanceof Axismundi_Actor ) {
			$error = new WP_Error( 'ax_act_pending_permission', 'Scheduled publication authority was revoked.' );
		} elseif ( ! hash_equals( $intent['actor_uri'], $actor->get_uri() )
			|| ( $before instanceof WP_Post && 'future' === $before->post_status && $before->post_modified_gmt !== $intent['expected_version'] )
			|| ! hash_equals( $intent['object_uri'], axismundi_op_post_object_uri( $post ) )
			|| ! hash_equals( $intent['source_hash'], axismundi_act_scheduled_source_hash( $post ) )
			|| $intent['scheduled_for'] !== $post->post_date_gmt ) {
			$error = new WP_Error( 'ax_act_pending_stale', 'The scheduled source changed without a replacement intent.' );
		} elseif ( strtotime( $intent['scheduled_for'] . ' UTC' ) > time() ) {
			$error = new WP_Error( 'ax_act_pending_early', 'The scheduled publication is not due.' );
		} else {
			$audience = axismundi_op_post_article_audience( $post, null, $actor->get_uri() );
			if ( is_wp_error( $audience ) || wp_json_encode( $audience ) !== $intent['audience_json'] ) {
				$error = new WP_Error( 'ax_act_pending_audience', 'The scheduled audience changed; choose it again.' );
			}
		}
		if ( ! $error ) {
			$result = axismundi_act_submit_c2s( $user, $actor->get_identity_id(), axismundi_act_post_command( $post, 'Create' ), array( 'surface' => 'scheduled', 'intent_uuid' => $intent['intent_uuid'] ) );
			$error = is_wp_error( $result ) ? $result : ( $result instanceof Axismundi_Activity ? null : new WP_Error( 'ax_act_pending_empty', 'No scheduled Activity was recorded.' ) );
		}
		if ( $error && false === $wpdb->update( axismundi_act_submissions_table(), array( 'state' => 'failed',
			'error_code' => $error->get_error_code(), 'updated_at' => current_time( 'mysql', true ) ), array_merge( axismundi_act_post_submission_source( $post->ID ), array( 'intent_uuid' => $intent['intent_uuid'], 'state' => 'pending' ) ) ) ) {
			return new WP_Error( 'ax_act_pending_write', 'The scheduled publication result could not be stored.' );
		}
		return $error ?: true;
	} );
	if ( is_wp_error( $result ) ) { do_action( 'axismundi_act_post_create_failed', $result, $post ); }
	return true;
}

/** Durable failure remains visible to source editors after a background cron execution. */
add_action( 'admin_notices', static function () : void {
	$id = isset( $_GET['post'] ) && is_scalar( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	if ( $id <= 0 || ! current_user_can( 'edit_post', $id ) ) { return; }
	$row = axismundi_act_scheduled_post_submission( $id );
	if ( $row && 'failed' === $row['state'] ) {
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Scheduled federation failed. Review the Account and schedule again.', 'axismundi-activities' ) . ' (' . esc_html( $row['error_code'] ) . ')</p></div>';
	}
} );
