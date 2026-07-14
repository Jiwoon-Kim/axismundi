<?php
/**
 * Phase 2 - Follow/Block materialized relationship state.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** Relation table name. */
function axismundi_act_relations_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_activity_relations';
}

/** Whether the verified DB v2 repository is available for relation reads. */
function axismundi_act_relations_ready() : bool {
	return defined( 'AXISMUNDI_ACT_DB_VERSION' )
		&& AXISMUNDI_ACT_DB_VERSION === (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' );
}

/** Install and verify the relation table. Called by the repository DB gate. */
function axismundi_act_install_relations() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = axismundi_act_relations_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			relation_type varchar(16) NOT NULL,
			subject_actor_uri text NOT NULL,
			subject_actor_uri_hash char(64) NOT NULL,
			object_actor_uri text NOT NULL,
			object_actor_uri_hash char(64) NOT NULL,
			direction varchar(8) NOT NULL,
			state varchar(10) NOT NULL,
			initiating_activity_uri text NOT NULL,
			state_activity_uri text DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY relation_identity (relation_type, subject_actor_uri_hash, object_actor_uri_hash),
			KEY object_state (object_actor_uri_hash, relation_type, state),
			KEY subject_state (subject_actor_uri_hash, relation_type, state),
			KEY direction_state (direction, state)
		) ENGINE=InnoDB {$charset};"
	);
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed schema verification.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed index verification.
	$index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'relation_identity'", ARRAY_A );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed engine verification.
	$engine = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
	return in_array( 'subject_actor_uri', $columns, true )
		&& in_array( 'object_actor_uri', $columns, true )
		&& ! in_array( 'blog_id', $columns, true )
		&& ! empty( $index )
		&& 0 === (int) $index[0]['Non_unique']
		&& 'InnoDB' === $engine;
}

/** Exact materialized relation lookup. */
function axismundi_act_get_relation( string $type, string $subject_uri, string $object_uri ) : ?array {
	global $wpdb;
	$type = sanitize_key( $type );
	if ( ! axismundi_act_relations_ready() || ! in_array( $type, array( 'follow', 'block' ), true ) || '' === axismundi_act_uri( $subject_uri ) || '' === axismundi_act_uri( $object_uri ) ) {
		return null;
	}
	$table = axismundi_act_relations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact custom relation lookup.
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE relation_type = %s AND subject_actor_uri_hash = %s AND object_actor_uri_hash = %s",
			$type,
			hash( 'sha256', $subject_uri ),
			hash( 'sha256', $object_uri )
		),
		ARRAY_A
	);
	return is_array( $row )
		&& hash_equals( (string) $row['subject_actor_uri'], $subject_uri )
		&& hash_equals( (string) $row['object_actor_uri'], $object_uri )
		? $row
		: null;
}

/** Direction relative to this site, or WP_Error for unknown/remote-remote Actors. */
function axismundi_act_relation_direction( string $subject_uri, string $object_uri ) {
	$subject = axismundi_actors_get_by_uri( $subject_uri );
	$object  = axismundi_actors_get_by_uri( $object_uri );
	if ( ! $subject instanceof Axismundi_Actor || ! $object instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_act_relation_actor', __( 'A social relation requires two known Actors.', 'axismundi-activities' ) );
	}
	if ( $subject->is_local() && $object->is_local() ) {
		return 'local';
	}
	if ( $subject->is_local() ) {
		return 'outbound';
	}
	if ( $object->is_local() ) {
		return 'inbound';
	}
	return new WP_Error( 'ax_act_relation_scope', __( 'Remote-to-remote social relations are outside this site ledger.', 'axismundi-activities' ) );
}

/** Insert or update one relation row inside the caller's transaction. */
function axismundi_act_write_relation( string $type, string $subject_uri, string $object_uri, string $direction, string $state, string $initiating_uri, ?string $state_uri ) {
	global $wpdb;
	$table = axismundi_act_relations_table();
	$now   = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- relation row lock inside Activity transaction.
	$existing = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE relation_type = %s AND subject_actor_uri_hash = %s AND object_actor_uri_hash = %s FOR UPDATE",
			$type,
			hash( 'sha256', $subject_uri ),
			hash( 'sha256', $object_uri )
		),
		ARRAY_A
	);
	if ( is_array( $existing ) && ( ! hash_equals( (string) $existing['subject_actor_uri'], $subject_uri ) || ! hash_equals( (string) $existing['object_actor_uri'], $object_uri ) ) ) {
		return new WP_Error( 'ax_act_relation_hash_collision', __( 'A relation URI hash collision was detected.', 'axismundi-activities' ) );
	}
	$fields = array(
		'relation_type'          => $type,
		'subject_actor_uri'      => $subject_uri,
		'subject_actor_uri_hash' => hash( 'sha256', $subject_uri ),
		'object_actor_uri'       => $object_uri,
		'object_actor_uri_hash'  => hash( 'sha256', $object_uri ),
		'direction'              => $direction,
		'state'                  => $state,
		'initiating_activity_uri' => $initiating_uri,
		'state_activity_uri'     => $state_uri,
		'updated_at'             => $now,
	);
	$ok = is_array( $existing )
		? false !== $wpdb->update( $table, $fields, array( 'id' => (int) $existing['id'] ) )
		: false !== $wpdb->insert( $table, array_merge( $fields, array( 'created_at' => $now ) ) );
	return $ok ? axismundi_act_get_relation( $type, $subject_uri, $object_uri ) : new WP_Error( 'ax_act_relation_write', __( 'The social relation could not be stored.', 'axismundi-activities' ) );
}

/** Start a Follow/Block without downgrading an already live relation. */
function axismundi_act_start_relation( string $type, string $subject_uri, string $object_uri, string $direction, string $activity_uri ) {
	$existing = axismundi_act_get_relation( $type, $subject_uri, $object_uri );
	$live     = 'follow' === $type ? array( 'pending', 'accepted' ) : array( 'active' );
	if ( is_array( $existing ) && in_array( (string) $existing['state'], $live, true ) ) {
		return $existing;
	}
	return axismundi_act_write_relation(
		$type,
		$subject_uri,
		$object_uri,
		$direction,
		'follow' === $type ? 'pending' : 'active',
		$activity_uri,
		null
	);
}

/** Transition a relation only when the referenced initiating Activity still owns it. */
function axismundi_act_transition_relation( string $type, string $subject_uri, string $object_uri, string $initiating_uri, string $state, string $state_uri ) {
	$existing = axismundi_act_get_relation( $type, $subject_uri, $object_uri );
	if ( ! is_array( $existing ) || ! hash_equals( (string) $existing['initiating_activity_uri'], $initiating_uri ) ) {
		return $existing;
	}
	return axismundi_act_write_relation( $type, $subject_uri, $object_uri, (string) $existing['direction'], $state, $initiating_uri, $state_uri );
}

/** Activity rows that point to one Activity URI, oldest to newest. */
function axismundi_act_relation_transitions( string $activity_uri ) : array {
	global $wpdb;
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- transition derivation inside repository transaction.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE object_uri_hash = %s AND effective_status = 'active' AND activity_type IN ('Accept','Reject') ORDER BY COALESCE(published_at, received_at, created_at) ASC, id ASC", hash( 'sha256', $activity_uri ) ), ARRAY_A );
	return array_values( array_filter( $rows, static fn( array $row ) : bool => hash_equals( (string) $row['object_uri'], $activity_uri ) ) );
}

/** Reconcile a Follow from its effective state and any out-of-order Accept/Reject rows. */
function axismundi_act_reconcile_follow( array $follow ) {
	$subject_uri = (string) $follow['actor_uri'];
	$object_uri  = (string) $follow['object_uri'];
	$direction   = axismundi_act_relation_direction( $subject_uri, $object_uri );
	if ( is_wp_error( $direction ) ) {
		return $direction;
	}
	$relation = axismundi_act_start_relation( 'follow', $subject_uri, $object_uri, $direction, (string) $follow['activity_uri'] );
	if ( is_wp_error( $relation ) || ! is_array( $relation ) ) {
		return $relation;
	}
	if ( ! hash_equals( (string) $relation['initiating_activity_uri'], (string) $follow['activity_uri'] ) ) {
		return $relation;
	}
	if ( 'undone' === (string) $follow['effective_status'] ) {
		return axismundi_act_transition_relation( 'follow', $subject_uri, $object_uri, (string) $follow['activity_uri'], 'undone', (string) $follow['activity_uri'] );
	}
	foreach ( axismundi_act_relation_transitions( (string) $follow['activity_uri'] ) as $transition ) {
		if ( ! hash_equals( (string) $transition['actor_uri'], $object_uri ) ) {
			continue;
		}
		$state    = 'Accept' === (string) $transition['activity_type'] ? 'accepted' : 'rejected';
		$relation = axismundi_act_transition_relation( 'follow', $subject_uri, $object_uri, (string) $follow['activity_uri'], $state, (string) $transition['activity_uri'] );
	}
	return $relation;
}

/** Reconcile a Block from its effective state. */
function axismundi_act_reconcile_block( array $block ) {
	$subject_uri = (string) $block['actor_uri'];
	$object_uri  = (string) $block['object_uri'];
	$direction   = axismundi_act_relation_direction( $subject_uri, $object_uri );
	if ( is_wp_error( $direction ) ) {
		return $direction;
	}
	$relation = axismundi_act_start_relation( 'block', $subject_uri, $object_uri, $direction, (string) $block['activity_uri'] );
	if ( is_wp_error( $relation ) || ! is_array( $relation ) || ! hash_equals( (string) $relation['initiating_activity_uri'], (string) $block['activity_uri'] ) ) {
		return $relation;
	}
	return 'undone' === (string) $block['effective_status']
		? axismundi_act_transition_relation( 'block', $subject_uri, $object_uri, (string) $block['activity_uri'], 'undone', (string) $block['activity_uri'] )
		: $relation;
}

/** Follow an Undo chain to the non-Undo root Activity. */
function axismundi_act_relation_root( array $activity ) : ?array {
	$seen = array();
	while ( 'Undo' === (string) $activity['activity_type'] && ! empty( $activity['object_uri'] ) && count( $seen ) < 16 ) {
		$uri = (string) $activity['object_uri'];
		if ( isset( $seen[ $uri ] ) ) {
			return null;
		}
		$seen[ $uri ] = true;
		$activity     = axismundi_act_transaction_row( $uri );
		if ( ! is_array( $activity ) ) {
			return null;
		}
	}
	return $activity;
}

/**
 * Apply one relation-bearing Activity inside the Activity repository transaction.
 *
 * @return array<int,array<string,mixed>>|WP_Error Changed relation snapshots.
 */
function axismundi_act_apply_relation_activity( array $normalized ) {
	$type = (string) $normalized['activity_type'];
	if ( ! in_array( $type, array( 'Follow', 'Accept', 'Reject', 'Undo', 'Block' ), true ) ) {
		return array();
	}
	$before = null;
	$root   = null;
	if ( in_array( $type, array( 'Follow', 'Block' ), true ) ) {
		$root = axismundi_act_transaction_row( (string) $normalized['activity_uri'] );
	} elseif ( 'Undo' === $type ) {
		$current = axismundi_act_transaction_row( (string) $normalized['activity_uri'] );
		$root    = is_array( $current ) ? axismundi_act_relation_root( $current ) : null;
	} else {
		$root = axismundi_act_transaction_row( (string) $normalized['object_uri'] );
		if ( is_array( $root ) && 'Follow' === (string) $root['activity_type'] && ! hash_equals( (string) $normalized['actor_uri'], (string) $root['object_uri'] ) ) {
			return new WP_Error( 'ax_act_follow_transition_actor', __( 'Only the followed Actor may Accept or Reject a Follow.', 'axismundi-activities' ) );
		}
	}
	if ( ! is_array( $root ) || ! in_array( (string) $root['activity_type'], array( 'Follow', 'Block' ), true ) ) {
		return array();
	}
	$relation_type = strtolower( (string) $root['activity_type'] );
	$before        = axismundi_act_get_relation( $relation_type, (string) $root['actor_uri'], (string) $root['object_uri'] );
	$after         = 'follow' === $relation_type ? axismundi_act_reconcile_follow( $root ) : axismundi_act_reconcile_block( $root );
	if ( is_wp_error( $after ) ) {
		return $after;
	}
	if ( ! is_array( $after ) || $before === $after ) {
		return array();
	}
	return array( $after );
}

/** Accepted follower Actor URIs for one Actor. */
function axismundi_act_get_followers( string $actor_uri, int $limit = 100 ) : array {
	return axismundi_act_relation_actor_list( 'object', $actor_uri, $limit );
}

/** Accepted followed Actor URIs for one Actor. */
function axismundi_act_get_following( string $actor_uri, int $limit = 100 ) : array {
	return axismundi_act_relation_actor_list( 'subject', $actor_uri, $limit );
}

/** Pending Follow relation rows addressed to one local Actor. */
function axismundi_act_get_pending_follow_requests( string $actor_uri, int $limit = 100 ) : array {
	global $wpdb;
	if ( ! axismundi_act_relations_ready() || '' === axismundi_act_uri( $actor_uri ) ) {
		return array();
	}
	$table = axismundi_act_relations_table();
	$limit = max( 1, min( 200, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table; values prepared.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE relation_type = 'follow' AND state = 'pending' AND object_actor_uri_hash = %s ORDER BY updated_at DESC, id DESC LIMIT %d", hash( 'sha256', $actor_uri ), $limit ), ARRAY_A );
	return array_values( array_filter( $rows, static fn( array $row ) : bool => hash_equals( (string) $row['object_actor_uri'], $actor_uri ) ) );
}

/** Pending Follow relation rows sent by one local Actor. */
function axismundi_act_get_pending_following_requests( string $actor_uri, int $limit = 100 ) : array {
	global $wpdb;
	if ( ! axismundi_act_relations_ready() || '' === axismundi_act_uri( $actor_uri ) ) {
		return array();
	}
	$table = axismundi_act_relations_table();
	$limit = max( 1, min( 200, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table; values prepared.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE relation_type = 'follow' AND state = 'pending' AND subject_actor_uri_hash = %s ORDER BY updated_at DESC, id DESC LIMIT %d", hash( 'sha256', $actor_uri ), $limit ), ARRAY_A );
	return array_values( array_filter( $rows, static fn( array $row ) : bool => hash_equals( (string) $row['subject_actor_uri'], $actor_uri ) ) );
}

/** Query accepted Follow edges from either side. */
function axismundi_act_relation_actor_list( string $side, string $actor_uri, int $limit ) : array {
	global $wpdb;
	if ( ! axismundi_act_relations_ready() || ! in_array( $side, array( 'subject', 'object' ), true ) || '' === axismundi_act_uri( $actor_uri ) ) {
		return array();
	}
	$table         = axismundi_act_relations_table();
	$match_column  = $side . '_actor_uri';
	$hash_column   = $side . '_actor_uri_hash';
	$return_column = 'subject' === $side ? 'object_actor_uri' : 'subject_actor_uri';
	$limit         = max( 1, min( 500, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- allowlisted columns and fixed table; values prepared.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT {$match_column}, {$return_column} FROM {$table} WHERE relation_type = 'follow' AND state = 'accepted' AND {$hash_column} = %s ORDER BY id DESC LIMIT %d", hash( 'sha256', $actor_uri ), $limit ), ARRAY_A );
	$out = array();
	foreach ( $rows as $row ) {
		if ( hash_equals( (string) $row[ $match_column ], $actor_uri ) ) {
			$out[] = (string) $row[ $return_column ];
		}
	}
	return array_values( array_unique( $out ) );
}

/** Recent relation rows for the administrator log. */
function axismundi_act_get_recent_relations( int $limit = 50 ) : array {
	global $wpdb;
	if ( ! axismundi_act_relations_ready() ) {
		return array();
	}
	$table = axismundi_act_relations_table();
	$limit = max( 1, min( 200, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table; numeric limit prepared.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY updated_at DESC, id DESC LIMIT %d", $limit ), ARRAY_A );
}
