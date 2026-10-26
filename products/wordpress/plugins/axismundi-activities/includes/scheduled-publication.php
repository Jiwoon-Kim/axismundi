<?php
/** Private scheduling intent, never an Activity or an attribution authority. @package AxismundiActivities */
defined( 'ABSPATH' ) || exit;

function axismundi_act_pending_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_activity_pending';
}

/** One replaceable intent per source; no Activity URI, outbox or delivery fields. */
function axismundi_act_install_pending() : bool {
	global $wpdb;
	$table = axismundi_act_pending_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		post_id bigint(20) unsigned NOT NULL,
		intent_uuid char(36) NOT NULL,
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
		PRIMARY KEY  (post_id),
		UNIQUE KEY intent_uuid (intent_uuid)
	) ENGINE=InnoDB {$charset};" );
	$columns = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
	$primary = (array) $wpdb->get_col( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'PRIMARY'", $table ), 4 );
	$generation = (array) $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'intent_uuid'", $table ), ARRAY_A );
	$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
	return ! array_diff( array( 'post_id', 'intent_uuid', 'principal_user_id', 'actor_identity_id', 'actor_uri', 'object_uri', 'expected_version', 'source_hash', 'audience_json', 'scheduled_for', 'state', 'error_code', 'updated_at' ), $columns )
		&& array( 'post_id' ) === $primary && array( 'intent_uuid' ) === array_column( $generation, 'Column_name' )
		&& 0 === (int) ( $generation[0]['Non_unique'] ?? 1 ) && 'InnoDB' === $engine;
}

/** Core changes modified/status on publication; compare the frozen source independently. */
function axismundi_act_scheduled_source_hash( WP_Post $post ) : string {
	return hash( 'sha256', wp_json_encode( array( $post->post_title, $post->post_content, $post->post_excerpt,
		$post->post_author, $post->post_password, $post->post_date_gmt ) ) );
}

function axismundi_act_pending_intent( int $post_id ) : ?array {
	global $wpdb;
	if ( AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION ) ) { return null; }
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE post_id = %d', axismundi_act_pending_table(), $post_id ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/** A named lock spans the ledger's own transaction without nesting SQL transactions. */
function axismundi_act_pending_lock( int $post_id, callable $work ) {
	global $wpdb;
	$key = 'ax-pending-' . substr( hash( 'sha256', DB_NAME . ':' . $wpdb->prefix . ':' . $post_id ), 0, 48 );
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $key ) ) ) {
		return new WP_Error( 'ax_act_pending_lock', 'Scheduled publication is busy.' );
	}
	try { return $work(); }
	finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) ); }
}

/** Consume exactly the frozen intent inside the Activity repository transaction. */
function axismundi_act_pending_commit_callback( array $intent ) : callable {
	return static function ( array $activity ) use ( $intent ) {
		global $wpdb;
		if ( $activity['actor_uri'] !== $intent['actor_uri'] || $activity['object_uri'] !== $intent['object_uri'] ) {
			return new WP_Error( 'ax_act_pending_identity', 'The scheduled Activity does not match the frozen intent.' );
		}
		$updated = $wpdb->update( axismundi_act_pending_table(), array( 'state' => 'completed', 'error_code' => '', 'updated_at' => current_time( 'mysql', true ) ),
			array( 'post_id' => $intent['post_id'], 'intent_uuid' => $intent['intent_uuid'], 'state' => 'pending' ) );
		return 1 === $updated ? true : new WP_Error( 'ax_act_pending_consume', 'The scheduled publication intent could not be consumed.' );
	};
}

/** Persist intent after source metadata and mentions, not on autosaves or revisions. */
function axismundi_act_capture_scheduled_publication( int $post_id, WP_Post $post, bool $update, ?WP_Post $before = null, bool $rest_complete = false ) : void {
	unset( $update, $before );
	if ( 'post' !== $post->post_type || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id )
		|| ( wp_is_serving_rest_request() && ! $rest_complete ) || ! axismundi_act_post_lifecycle_applies( $post ) ) { return; }
	if ( 'future' !== $post->post_status ) {
		if ( 'publish' !== $post->post_status ) {
			axismundi_act_pending_lock( $post_id, static function () use ( $post_id ) {
				global $wpdb;
				return $wpdb->update( axismundi_act_pending_table(), array( 'state' => 'cancelled', 'updated_at' => current_time( 'mysql', true ) ), array( 'post_id' => $post_id, 'state' => 'pending' ) );
			} );
		}
		return;
	}
	if ( ! axismundi_actors_account_selection_enabled() ) { return; }
	$result = axismundi_act_pending_lock( $post_id, static function () use ( $post_id ) {
		global $wpdb;
		$post = get_post( $post_id );
		$user = get_current_user_id();
		$actor = $user > 0 && user_can( $user, 'edit_post', $post_id ) ? axismundi_act_request_publish_actor( $post, $user ) : new WP_Error( 'ax_act_pending_principal', 'A schedule requires an authenticated publisher.' );
		$audience = ! is_wp_error( $actor ) ? axismundi_op_post_article_audience( $post, null, $actor->get_uri() ) : $actor;
		$error = is_wp_error( $audience ) ? $audience : null;
		$row = array( 'post_id' => $post_id, 'intent_uuid' => wp_generate_uuid4(), 'principal_user_id' => $user,
			'actor_identity_id' => is_wp_error( $actor ) ? 0 : $actor->get_identity_id(),
			'actor_uri' => is_wp_error( $actor ) ? '' : $actor->get_uri(), 'object_uri' => axismundi_op_post_object_uri( $post ),
			'expected_version' => $post->post_modified_gmt, 'source_hash' => axismundi_act_scheduled_source_hash( $post ),
			'audience_json' => wp_json_encode( is_array( $audience ) ? $audience : array() ),
			'scheduled_for' => $post->post_date_gmt, 'state' => $error ? 'failed' : 'pending',
			'error_code' => $error ? $error->get_error_code() : '', 'updated_at' => current_time( 'mysql', true ) );
		if ( false === $wpdb->replace( axismundi_act_pending_table(), $row ) ) {
			// Never leave an older valid intent usable after an unsuccessful replacement.
			$wpdb->update( axismundi_act_pending_table(), array( 'state' => 'failed', 'error_code' => 'ax_act_pending_write' ), array( 'post_id' => $post_id ) );
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
	$wpdb->delete( axismundi_act_pending_table(), array( 'post_id' => $post_id ), array( '%d' ) );
} );

/** Return true for any recorded schedule, including failures: never fall back to a live cookie. */
function axismundi_act_execute_scheduled_publication( WP_Post $post, ?WP_Post $before = null ) : bool {
	$intent = axismundi_act_pending_intent( $post->ID );
	if ( ! $intent ) { return $before instanceof WP_Post && 'future' === $before->post_status; }
	if ( in_array( $intent['state'], array( 'completed', 'cancelled' ), true ) ) { return false; }
	$result = axismundi_act_pending_lock( $post->ID, static function () use ( $post, $before ) {
		global $wpdb;
		$intent = axismundi_act_pending_intent( $post->ID );
		if ( ! $intent || 'pending' !== $intent['state'] ) { return true; }
		$post = get_post( $post->ID );
		if ( 'publish' !== $post->post_status ) { return true; }
		$user = (int) $intent['principal_user_id'];
		$actor = axismundi_act_c2s_resolve_actor( $user, (int) $intent['actor_identity_id'] );
		$error = null;
		if ( ! user_can( $user, 'edit_post', $post->ID ) || is_wp_error( $actor ) || ! $actor instanceof Axismundi_Actor ) {
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
		if ( $error && false === $wpdb->update( axismundi_act_pending_table(), array( 'state' => 'failed',
			'error_code' => $error->get_error_code(), 'updated_at' => current_time( 'mysql', true ) ), array( 'post_id' => $post->ID, 'intent_uuid' => $intent['intent_uuid'], 'state' => 'pending' ) ) ) {
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
	$row = axismundi_act_pending_intent( $id );
	if ( $row && 'failed' === $row['state'] ) {
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Scheduled federation failed. Review the Account and schedule again.', 'axismundi-activities' ) . ' (' . esc_html( $row['error_code'] ) . ')</p></div>';
	}
} );
