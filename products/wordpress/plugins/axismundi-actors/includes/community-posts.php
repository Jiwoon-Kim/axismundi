<?php
/** Community publication decisions, independent of source Objects and Forum CPTs. */
defined( 'ABSPATH' ) || exit;

function axismundi_actors_community_posts_table() : string { global $wpdb; return $wpdb->prefix . 'ax_community_posts'; }

function axismundi_actors_install_community_posts() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = axismundi_actors_community_posts_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		group_id bigint(20) unsigned NOT NULL,
		object_uri text NOT NULL,
		object_hash char(64) NOT NULL,
		create_uri text NOT NULL,
		author_uri text NOT NULL,
		admission_state varchar(16) NOT NULL DEFAULT 'pending',
		created_at datetime NOT NULL,
		reviewed_at datetime DEFAULT NULL,
		review_reason text DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY group_object (group_id,object_hash)
	) ENGINE=InnoDB {$charset};" );
	$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
	$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
	if ( array_diff( array( 'id', 'group_id', 'object_uri', 'create_uri', 'author_uri', 'admission_state', 'reviewed_at', 'review_reason' ), $columns ) || 'InnoDB' !== $engine ) { return false; }
	update_option( 'ax_community_posts_schema', '2', false );
	return true;
}
add_action( 'plugins_loaded', static function () : void {
	if ( '2' !== get_option( 'ax_community_posts_schema' ) ) { axismundi_actors_install_community_posts(); }
} );

function axismundi_actors_get_community_post( int $group_id, string $uri ) : ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE group_id=%d AND object_hash=%s', axismundi_actors_community_posts_table(), $group_id, hash( 'sha256', $uri ) ), ARRAY_A );
	return is_array( $row ) && $row['object_uri'] === $uri ? $row : null;
}

/** An idempotent pending admission is not permission to publish the Object. */
function axismundi_actors_admit_community_post( Axismundi_Actor $group, string $uri, string $create_uri, string $author_uri ) {
	global $wpdb;
	if ( ! axismundi_actors_is_community( $group ) || 'public' !== $group->get_status() ) {
		return new WP_Error( 'ax_community_profile', 'An active local Community is required.', array( 'status' => 403 ) );
	}
	$ok = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (group_id,object_uri,object_hash,create_uri,author_uri,created_at) VALUES (%d,%s,%s,%s,%s,%s)', axismundi_actors_community_posts_table(), $group->get_identity_id(), $uri, hash( 'sha256', $uri ), $create_uri, $author_uri, current_time( 'mysql', true ) ) );
	$row = axismundi_actors_get_community_post( $group->get_identity_id(), $uri );
	if ( false === $ok || ! $row ) { return new WP_Error( 'ax_community_write', 'The Community submission could not be stored.' ); }
	return $row['create_uri'] === $create_uri && $row['author_uri'] === $author_uri ? $row : new WP_Error( 'ax_community_conflict', 'The Object already has different submission evidence.', array( 'status' => 409 ) );
}

/** SQL participant: the caller owns the transaction with any Group Activity. */
function axismundi_actors_decide_community_post( Axismundi_Actor $group, Axismundi_Actor $moderator, string $uri, bool $approve, string $reason = '' ) {
	global $wpdb;
	$reason = trim( sanitize_textarea_field( $reason ) );
	if ( strlen( $reason ) > 4096 || ( ! $approve && '' === $reason ) ) {
		return new WP_Error( 'ax_community_reason', 'A rejection reason is required (maximum 4096 bytes).', array( 'status' => 400 ) );
	}
	if ( ! axismundi_actors_is_community( $group ) || 'public' !== $group->get_status() || 'Person' !== $moderator->get_type() || 'public' !== $moderator->get_status() ) {
		return new WP_Error( 'ax_community_profile', 'A Community and active Person moderator are required.', array( 'status' => 403 ) );
	}
	// Serialize with moderator membership changes, so a concurrent removal cannot authorize review.
	if ( ! $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE group_id=%d FOR UPDATE', axismundi_actors_moderators_state_table(), $group->get_identity_id() ) ) ) {
		return new WP_Error( 'ax_community_forbidden', 'No initialized moderator authority is available.', array( 'status' => 403 ) );
	}
	$members = axismundi_actors_read_moderators( $group );
	if ( is_wp_error( $members ) || ! in_array( $moderator->get_uri(), $members['items'], true ) ) {
		return new WP_Error( 'ax_community_forbidden', 'An existing Community moderator is required.', array( 'status' => 403 ) );
	}
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE group_id=%d AND object_hash=%s FOR UPDATE', axismundi_actors_community_posts_table(), $group->get_identity_id(), hash( 'sha256', $uri ) ), ARRAY_A );
	if ( ! $row || $row['object_uri'] !== $uri ) { return new WP_Error( 'ax_community_missing', 'The submission was not found.' ); }
	$state = $approve ? 'approved' : 'rejected';
	$operation = hash( 'sha256', 'community-review:' . $row['id'] );
	$log = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE operation_key=%s', axismundi_actors_moderators_log_table(), $operation ), ARRAY_A );
	if ( 'pending' !== $row['admission_state'] ) {
		return $row['admission_state'] === $state && $log && $log['actor_uri'] === $moderator->get_uri() && (string) ( $row['review_reason'] ?? '' ) === $reason ? true : new WP_Error( 'ax_community_decided', 'The submission already has a different decision.', array( 'status' => 409 ) );
	}
	$now = current_time( 'mysql', true );
	if ( false === $wpdb->update( axismundi_actors_community_posts_table(), array( 'admission_state' => $state, 'reviewed_at' => $now, 'review_reason' => $reason ), array( 'id' => $row['id'] ) )
		|| false === $wpdb->insert( axismundi_actors_moderators_log_table(), array( 'group_id' => $group->get_identity_id(), 'actor_uri' => $moderator->get_uri(), 'person_uri' => $row['author_uri'], 'verb' => $approve ? 'ApprovePost' : 'RejectPost', 'operation_key' => $operation, 'activity_uri' => $row['create_uri'], 'created_at' => $now ) ) ) {
		return new WP_Error( 'ax_community_write', 'The submission decision and audit log could not be written.' );
	}
	return true;
}
