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

/** Whether the verified repository schema is available for relation reads. */
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
			state varchar(16) NOT NULL,
			initiating_activity_uri text DEFAULT NULL,
			state_activity_uri text DEFAULT NULL,
			termination_activity_uri text DEFAULT NULL,
			evidence_type varchar(16) NOT NULL DEFAULT 'activity',
			evidence_ref text DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY relation_identity (relation_type, subject_actor_uri_hash, object_actor_uri_hash),
			KEY object_state (object_actor_uri_hash, relation_type, state),
			KEY subject_state (subject_actor_uri_hash, relation_type, state),
			KEY direction_state (direction, state)
		) ENGINE=InnoDB {$charset};"
	);
	// dbDelta does not reliably relax NOT NULL or widen existing varchar columns.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed schema inspection.
	$column_rows = (array) $wpdb->get_results( "SHOW COLUMNS FROM {$table}", ARRAY_A );
	$column_map  = array_column( $column_rows, null, 'Field' );
	if ( isset( $column_map['initiating_activity_uri'] ) && 'YES' !== (string) $column_map['initiating_activity_uri']['Null'] ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- verified v4 nullable migration.
		$wpdb->query( "ALTER TABLE {$table} MODIFY initiating_activity_uri text NULL" );
	}
	if ( isset( $column_map['state'] ) && 'varchar(16)' !== strtolower( (string) $column_map['state']['Type'] ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- verified v4 state-width migration.
		$wpdb->query( "ALTER TABLE {$table} MODIFY state varchar(16) NOT NULL" );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- v4 provenance backfill.
	$wpdb->query( "UPDATE {$table} SET evidence_type = 'activity', evidence_ref = initiating_activity_uri WHERE evidence_type IS NULL OR evidence_type = '' OR (evidence_type = 'activity' AND evidence_ref IS NULL)" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- final fixed schema verification.
	$column_rows = (array) $wpdb->get_results( "SHOW COLUMNS FROM {$table}", ARRAY_A );
	$column_map  = array_column( $column_rows, null, 'Field' );
	$columns     = array_keys( $column_map );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed index verification.
	$index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'relation_identity'", ARRAY_A );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed engine verification.
	$engine = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
	return in_array( 'subject_actor_uri', $columns, true )
		&& in_array( 'object_actor_uri', $columns, true )
		&& in_array( 'evidence_type', $columns, true )
		&& in_array( 'evidence_ref', $columns, true )
		&& in_array( 'termination_activity_uri', $columns, true )
		&& 'YES' === (string) ( $column_map['initiating_activity_uri']['Null'] ?? '' )
		&& 'varchar(16)' === strtolower( (string) ( $column_map['state']['Type'] ?? '' ) )
		&& ! in_array( 'blog_id', $columns, true )
		&& ! empty( $index )
		&& 0 === (int) $index[0]['Non_unique']
		&& 'InnoDB' === $engine;
}

/** Exact materialized relation lookup. */
function axismundi_act_get_relation( string $type, string $subject_uri, string $object_uri, bool $lock = false ) : ?array {
	global $wpdb;
	$type = sanitize_key( $type );
	if ( ! axismundi_act_relations_ready() || ! in_array( $type, array( 'follow', 'block' ), true ) || '' === axismundi_act_uri( $subject_uri ) || '' === axismundi_act_uri( $object_uri ) ) {
		return null;
	}
	$table = axismundi_act_relations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact custom relation lookup.
	$row = $wpdb->get_row(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Values are prepared; the boolean selects only a literal FOR UPDATE suffix.
			"SELECT * FROM {$table} WHERE relation_type = %s AND subject_actor_uri_hash = %s AND object_actor_uri_hash = %s" . ( $lock ? ' FOR UPDATE' : '' ),
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

/** Account-owned Block policy; repository failures must not be treated as an absent Block. */
function axismundi_act_actor_blocks( string $subject_uri, string $object_uri, bool $lock = false ) {
	if ( ! axismundi_act_relations_ready() || '' === axismundi_act_uri( $subject_uri ) || '' === axismundi_act_uri( $object_uri ) ) {
		return new WP_Error( 'ax_act_block_policy', 'The Block relation policy is unavailable.' );
	}
	global $wpdb;
	$relation = axismundi_act_get_relation( 'block', $subject_uri, $object_uri, $lock );
	if ( '' !== $wpdb->last_error ) {
		return new WP_Error( 'ax_act_block_read', 'The Block relation could not be read.' );
	}
	return is_array( $relation ) && 'active' === $relation['state'];
}

/** Direction relative to this site, or WP_Error for unknown/remote-remote Actors. */
function axismundi_act_relation_direction( string $subject_uri, string $object_uri ) {
	$subject = axismundi_actors_get_by_uri( $subject_uri );
	$object  = axismundi_actors_get_by_uri( $object_uri );
	if ( ! $subject instanceof Axismundi_Actor || ! $object instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_act_relation_actor', __( 'A social relation requires two known Actors.', 'axismundi-activities' ) );
	}
	if ( $subject->is_local() && $object->is_local() ) {
		return 'outbound';
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
function axismundi_act_write_relation( string $type, string $subject_uri, string $object_uri, string $direction, string $state, ?string $initiating_uri, ?string $state_uri, string $evidence_type = 'activity', ?string $evidence_ref = null, ?string $termination_uri = null ) {
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
		// Preserve the terminal cause for this cycle, independently of later response/Undo URIs.
		'termination_activity_uri' => 'follow' === $type ? ( $termination_uri ?? ( is_array( $existing ) && $existing['initiating_activity_uri'] === $initiating_uri ? ( $existing['termination_activity_uri'] ?? null ) : null ) ) : null,
		'evidence_type'          => $evidence_type,
		'evidence_ref'           => null !== $evidence_ref ? $evidence_ref : $initiating_uri,
		'updated_at'             => $now,
	);
	$ok = is_array( $existing )
		? false !== $wpdb->update( $table, $fields, array( 'id' => (int) $existing['id'] ) )
		: false !== $wpdb->insert( $table, array_merge( $fields, array( 'created_at' => $now ) ) );
	return $ok ? axismundi_act_get_relation( $type, $subject_uri, $object_uri ) : new WP_Error( 'ax_act_relation_write', __( 'The social relation could not be stored.', 'axismundi-activities' ) );
}

/** Start a Follow/Block without downgrading an already live relation. */
function axismundi_act_start_relation( string $type, string $subject_uri, string $object_uri, string $direction, string $activity_uri ) {
	$existing = axismundi_act_get_relation( $type, $subject_uri, $object_uri, true );
	// The same Follow never starts a fresh cycle merely because its state is terminal.
	if ( 'follow' === $type && is_array( $existing ) && hash_equals( (string) ( $existing['initiating_activity_uri'] ?? '' ), $activity_uri ) ) {
		return $existing;
	}
	$live     = 'follow' === $type ? array( 'pending', 'accepted' ) : array( 'active' );
	if ( is_array( $existing ) && 'activity' === (string) ( $existing['evidence_type'] ?? 'activity' ) && in_array( (string) $existing['state'], $live, true ) ) {
		if ( hash_equals( (string) ( $existing['initiating_activity_uri'] ?? '' ), $activity_uri ) ) {
			return $existing;
		}
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

/**
 * Import one current-state Follow assertion without inventing an Activity.
 *
 * Actual Follow/Accept/Reject/Undo Activities always take ownership later through
 * axismundi_act_start_relation(). Snapshot writes never overwrite Activity evidence.
 *
 * @return array<string,mixed>|WP_Error
 */
function axismundi_act_import_follow_snapshot( string $subject_uri, string $object_uri, string $state, string $evidence_ref ) {
	global $wpdb;
	if ( ! in_array( $state, array( 'accepted', 'legacy_pending' ), true ) || '' === trim( $evidence_ref ) || strlen( $evidence_ref ) > 2048 ) {
		return new WP_Error( 'ax_act_snapshot', __( 'The legacy Follow snapshot is invalid.', 'axismundi-activities' ) );
	}
	$direction = axismundi_act_relation_direction( $subject_uri, $object_uri );
	if ( is_wp_error( $direction ) ) {
		return $direction;
	}
	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic snapshot materialization.
	$existing = axismundi_act_get_relation( 'follow', $subject_uri, $object_uri );
	$blocked = axismundi_act_follow_pair_blocked( $subject_uri, $object_uri );
	if ( is_wp_error( $blocked ) || $blocked || ! empty( $existing['termination_activity_uri'] ) ) {
		$wpdb->query( 'ROLLBACK' );
		return is_wp_error( $blocked ) ? $blocked : ( $existing ?? new WP_Error( 'ax_act_follow_blocked', 'A blocked pair cannot import a live Follow.' ) );
	}
	if ( is_array( $existing ) && 'activity' === (string) ( $existing['evidence_type'] ?? 'activity' ) ) {
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Literal transaction control statement; no user input and nothing to cache.
		return $existing;
	}
	if ( is_array( $existing ) && 'accepted' === (string) $existing['state'] && 'legacy_pending' === $state ) {
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Literal transaction control statement; no user input and nothing to cache.
		return $existing;
	}
	$resolved_state = is_array( $existing ) && 'accepted' === (string) $existing['state'] ? 'accepted' : $state;
	$relation = axismundi_act_write_relation( 'follow', $subject_uri, $object_uri, $direction, $resolved_state, null, null, 'legacy_snapshot', $evidence_ref );
	if ( is_wp_error( $relation ) ) {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Literal transaction control statement; no user input and nothing to cache.
		return $relation;
	}
	$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Literal transaction control statement; no user input and nothing to cache.
	return $relation;
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

/** Fail closed on either directional Block when starting or accepting a Follow. */
function axismundi_act_follow_pair_blocked( string $subject_uri, string $object_uri ) {
	foreach ( array( array( $subject_uri, $object_uri ), array( $object_uri, $subject_uri ) ) as [ $subject, $object ] ) {
		$blocked = axismundi_act_actor_blocks( $subject, $object, true );
		if ( is_wp_error( $blocked ) || $blocked ) { return $blocked; }
	}
	return false;
}

/** A recorded Person Block permanently ends earlier Follow cycles, even after its Undo. */
function axismundi_act_follow_termination_block( array $follow ) {
	$relation = axismundi_act_get_relation( 'follow', $follow['actor_uri'], $follow['object_uri'] );
	if ( ! is_array( $relation ) || $follow['activity_uri'] !== $relation['initiating_activity_uri'] || empty( $relation['termination_activity_uri'] ) ) { return null; }
	$block = axismundi_act_transaction_row( $relation['termination_activity_uri'] );
	if ( is_array( $block ) && 'Block' === $block['activity_type'] && empty( $block['target_uri'] )
		&& ( ( $block['actor_uri'] === $follow['actor_uri'] && $block['object_uri'] === $follow['object_uri'] ) || ( $block['actor_uri'] === $follow['object_uri'] && $block['object_uri'] === $follow['actor_uri'] ) ) ) { return $block; }
	return new WP_Error( 'ax_act_block_cause', 'The terminal Follow evidence is unavailable.' );
}

/** Resolve an actual active same-author Undo rather than pointing back at its target. */
function axismundi_act_effective_undo_uri( array $root ) {
	global $wpdb;
	$uri = $wpdb->get_var( $wpdb->prepare( "SELECT activity_uri FROM %i WHERE activity_type='Undo' AND effective_status='active' AND object_uri_hash=%s AND object_uri=%s AND actor_uri_hash=%s AND actor_uri=%s ORDER BY id DESC LIMIT 1", axismundi_act_activities_table(), hash( 'sha256', $root['activity_uri'] ), $root['activity_uri'], hash( 'sha256', $root['actor_uri'] ), $root['actor_uri'] ) );
	return '' === $wpdb->last_error && is_string( $uri ) && '' !== $uri ? $uri : new WP_Error( 'ax_act_transition_evidence', 'The effective Undo evidence could not be read.' );
}

/** Reconcile a Follow from its effective state and any out-of-order Accept/Reject rows. */
function axismundi_act_reconcile_follow( array $follow ) {
	$subject_uri = (string) $follow['actor_uri'];
	$object_uri  = (string) $follow['object_uri'];
	$direction   = axismundi_act_relation_direction( $subject_uri, $object_uri );
	if ( is_wp_error( $direction ) ) {
		return $direction;
	}
	$current = axismundi_act_get_relation( 'follow', $subject_uri, $object_uri );
	if ( is_array( $current ) && ! empty( $current['initiating_activity_uri'] ) && $current['initiating_activity_uri'] !== $follow['activity_uri'] ) {
		$owner = axismundi_act_transaction_row( $current['initiating_activity_uri'] );
		if ( is_array( $owner ) && (int) $owner['id'] > (int) $follow['id'] ) { return $current; }
	}
	$relation = axismundi_act_start_relation( 'follow', $subject_uri, $object_uri, $direction, (string) $follow['activity_uri'] );
	if ( is_wp_error( $relation ) || ! is_array( $relation ) ) {
		return $relation;
	}
	if ( ! hash_equals( (string) $relation['initiating_activity_uri'], (string) $follow['activity_uri'] ) ) {
		return $relation;
	}
	if ( 'undone' === (string) $follow['effective_status'] ) {
		$undo = axismundi_act_effective_undo_uri( $follow );
		return is_wp_error( $undo ) ? $undo : axismundi_act_transition_relation( 'follow', $subject_uri, $object_uri, (string) $follow['activity_uri'], 'undone', $undo );
	}
	$block = axismundi_act_follow_termination_block( $follow );
	if ( is_wp_error( $block ) ) { return $block; }
	if ( is_array( $block ) ) {
		$state = $block['actor_uri'] === $subject_uri ? 'undone' : 'rejected';
		$state_uri = $block['activity_uri'];
		foreach ( axismundi_act_relation_transitions( $follow['activity_uri'] ) as $transition ) {
			if ( 'rejected' === $state && 'Reject' === $transition['activity_type'] && $object_uri === $transition['actor_uri'] && (int) $transition['id'] > (int) $block['id'] ) { $state_uri = $transition['activity_uri']; }
		}
		return axismundi_act_transition_relation( 'follow', $subject_uri, $object_uri, $follow['activity_uri'], $state, $state_uri );
	}
	// Derive from effective evidence, not the previous materialization (e.g. Undo(Accept)).
	$relation = axismundi_act_transition_relation( 'follow', $subject_uri, $object_uri, $follow['activity_uri'], 'pending', $follow['activity_uri'] );
	if ( is_wp_error( $relation ) ) { return $relation; }
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
	$current = axismundi_act_get_relation( 'block', $subject_uri, $object_uri );
	if ( is_array( $current ) && ! empty( $current['initiating_activity_uri'] ) && $current['initiating_activity_uri'] !== $block['activity_uri'] ) {
		$owner = axismundi_act_transaction_row( $current['initiating_activity_uri'] );
		if ( is_array( $owner ) && (int) $owner['id'] > (int) $block['id'] ) { return $current; }
	}
	$relation = axismundi_act_start_relation( 'block', $subject_uri, $object_uri, $direction, (string) $block['activity_uri'] );
	if ( is_wp_error( $relation ) || ! is_array( $relation ) || ! hash_equals( (string) $relation['initiating_activity_uri'], (string) $block['activity_uri'] ) ) {
		return $relation;
	}
	if ( 'undone' === (string) $block['effective_status'] ) {
		$undo = axismundi_act_effective_undo_uri( $block );
		return is_wp_error( $undo ) ? $undo : axismundi_act_transition_relation( 'block', $subject_uri, $object_uri, (string) $block['activity_uri'], 'undone', $undo );
	}
	return $relation;
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
	/*
	 * Only an `Undo` reaches its root through a response: `axismundi_act_relation_root()`
	 * unwraps `Undo` chains and stops at the `Accept` it names, so this hop is what gets
	 * `Undo(Accept)` to the `Follow`.
	 *
	 * `Accept` and `Reject` answer a request, so naming another `Accept` or `Reject` is not a
	 * transition we interpret. Rejecting a rejection has no defined meaning, and hopping it
	 * would silently turn it into rejecting the request itself. The hop also bypassed the
	 * actor guard above, which only fires when the named activity is the `Follow`. The state
	 * is derived from evidence addressed to the Follow rather than from this activity, so
	 * what the hop allowed was an undefined construct entering the state machine, not a
	 * transition it could choose. Resolving nothing leaves such an activity recorded and
	 * without effect, which is the right answer for an inbound one.
	 *
	 * Measured 2026-11-14: restricting this hop changes nothing observable -- same relation
	 * state, same ten change hooks in `audit-relations` either way. So this is a convention
	 * about which grammar the machine answers, not an enforced invariant; what the audit pins
	 * is the outcome, that such an activity does not transition the relation.
	 */
	if ( 'Undo' === $type && is_array( $root ) && in_array( $root['activity_type'], array( 'Accept', 'Reject' ), true ) ) {
		$root = axismundi_act_transaction_row( (string) $root['object_uri'] );
	}
	if ( ! is_array( $root ) || ! in_array( (string) $root['activity_type'], array( 'Follow', 'Block' ), true ) ) {
		return array();
	}
	// Conservatively leave target-scoped Block semantics to an explicit interoperability policy;
	// do not silently interpret them as personal Block relations.
	if ( 'Block' === $root['activity_type'] && ! empty( $root['target_uri'] ) ) { return array(); }
	if ( 'Follow' === $type ) {
		$blocked = axismundi_act_follow_pair_blocked( $root['actor_uri'], $root['object_uri'] );
		if ( is_wp_error( $blocked ) ) { return $blocked; }
		if ( $blocked ) { return 'inbound' === $normalized['direction'] ? array() : new WP_Error( 'ax_act_follow_blocked', 'A blocked Actor pair cannot start a Follow.' ); }
	}
	// A receipt for another Account must not mutate a blocked Account's Follow state.
	if ( 'inbound' === ( $normalized['direction'] ?? '' ) && 'Follow' === $root['activity_type'] ) {
		foreach ( array( $root['actor_uri'], $root['object_uri'] ) as $uri ) {
			$account = axismundi_actors_get_by_uri( (string) $uri );
			if ( ! $account instanceof Axismundi_Actor || ! $account->is_local() ) {
				continue;
			}
			$blocked = axismundi_act_actor_blocks( $account->get_uri(), (string) $normalized['actor_uri'] );
			if ( is_wp_error( $blocked ) ) {
				return $blocked;
			}
			if ( $blocked ) {
				return array();
			}
		}
	}
	$relation_type = strtolower( (string) $root['activity_type'] );
	$before        = axismundi_act_get_relation( $relation_type, (string) $root['actor_uri'], (string) $root['object_uri'] );
	$after         = 'follow' === $relation_type ? axismundi_act_reconcile_follow( $root ) : axismundi_act_reconcile_block( $root );
	if ( is_wp_error( $after ) ) {
		return $after;
	}
	$events = array();
	if ( 'block' === $relation_type && is_array( $after ) && $after['initiating_activity_uri'] === $root['activity_uri'] ) {
		$closed = axismundi_act_close_block_follows( $root );
		if ( is_wp_error( $closed ) ) { return $closed; }
		$events = $closed;
	}
	if ( ! is_array( $after ) || $before === $after ) {
		return $events;
	}
	$events[] = $after;
	return $events;
}

/** Atomically close both Person Follow edges, retaining the Block as causal evidence. */
function axismundi_act_close_block_follows( array $block ) {
	$events = array();
	if ( ! empty( $block['target_uri'] ) ) { return $events; }
	foreach ( array( array( $block['actor_uri'], $block['object_uri'] ), array( $block['object_uri'], $block['actor_uri'] ) ) as [ $subject, $object ] ) {
		$a = axismundi_actors_get_by_uri( $subject );
		$b = axismundi_actors_get_by_uri( $object );
		if ( ! $a instanceof Axismundi_Actor || ! $b instanceof Axismundi_Actor || 'Person' !== $a->get_type() || 'Person' !== $b->get_type() ) { continue; }
		$before = axismundi_act_get_relation( 'follow', $subject, $object, true );
		if ( ! is_array( $before ) || ! empty( $before['termination_activity_uri'] ) ) { continue; }
		$follow = ! empty( $before['initiating_activity_uri'] ) ? axismundi_act_transaction_row( $before['initiating_activity_uri'] ) : null;
		if ( is_array( $follow ) && (int) $follow['id'] > (int) $block['id'] && 'undone' === $block['effective_status'] ) { continue; }
		$live = in_array( $before['state'], array( 'accepted', 'pending', 'legacy_pending' ), true );
		$state = $live ? ( $subject === $block['actor_uri'] ? 'undone' : 'rejected' ) : $before['state'];
		$after = axismundi_act_write_relation( 'follow', $subject, $object, $before['direction'], $state, $before['initiating_activity_uri'], $live ? $block['activity_uri'] : $before['state_activity_uri'], $before['evidence_type'], $before['evidence_ref'], $block['activity_uri'] );
		if ( is_wp_error( $after ) ) { return $after; }
		$events[] = $after;
	}
	return $events;
}

/** Upgrade only active, Activity-backed Person Blocks; schema version is not yet advanced. */
function axismundi_act_backfill_active_person_blocks() : bool {
	global $wpdb;
	$table = axismundi_act_relations_table();
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return false; }
	$committed = false;
	try {
		$blocks = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE relation_type='block' AND state='active' FOR UPDATE", $table ), ARRAY_A );
		if ( '' !== $wpdb->last_error ) { return false; }
		foreach ( $blocks as $relation ) {
			$block = ! empty( $relation['initiating_activity_uri'] ) ? axismundi_act_transaction_row( $relation['initiating_activity_uri'] ) : null;
			if ( '' !== $wpdb->last_error ) { return false; }
			if ( ! is_array( $block ) || 'Block' !== $block['activity_type'] || 'active' !== $block['effective_status'] || ! empty( $block['target_uri'] )
				|| $block['actor_uri'] !== $relation['subject_actor_uri'] || $block['object_uri'] !== $relation['object_actor_uri'] ) { continue; }
			$a = axismundi_actors_get_by_uri( $block['actor_uri'] );
			$b = axismundi_actors_get_by_uri( $block['object_uri'] );
			if ( ! $a instanceof Axismundi_Actor || ! $b instanceof Axismundi_Actor || 'Person' !== $a->get_type() || 'Person' !== $b->get_type() || ( ! $a->is_local() && ! $b->is_local() ) ) { continue; }
			foreach ( array( array( $a->get_uri(), $b->get_uri() ), array( $b->get_uri(), $a->get_uri() ) ) as [ $subject, $object ] ) {
				$follow = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE relation_type='follow' AND subject_actor_uri_hash=%s AND object_actor_uri_hash=%s FOR UPDATE", $table, hash( 'sha256', $subject ), hash( 'sha256', $object ) ), ARRAY_A );
				if ( '' !== $wpdb->last_error ) { return false; }
				if ( ! is_array( $follow ) || $follow['subject_actor_uri'] !== $subject || $follow['object_actor_uri'] !== $object || ! empty( $follow['termination_activity_uri'] ) ) { continue; }
				$live = in_array( $follow['state'], array( 'accepted', 'pending', 'legacy_pending' ), true );
				$state = $live ? ( $subject === $a->get_uri() ? 'undone' : 'rejected' ) : $follow['state'];
				if ( false === $wpdb->update( $table, array( 'state' => $state, 'state_activity_uri' => $live ? $block['activity_uri'] : $follow['state_activity_uri'], 'termination_activity_uri' => $block['activity_uri'], 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $follow['id'] ) ) ) { return false; }
			}
		}
		$committed = false !== $wpdb->query( 'COMMIT' );
		return $committed;
	} finally { if ( ! $committed ) { $wpdb->query( 'ROLLBACK' ); } }
}

/** Accepted follower Actor URIs for one Actor. */
function axismundi_act_get_followers( string $actor_uri, int $limit = 100 ) : array {
	return axismundi_act_relation_actor_list( 'object', $actor_uri, $limit );
}

/** One stable page of accepted followers or following Actors. */
function axismundi_act_get_follow_collection_page( string $side, string $actor_uri, int $page = 1, int $per_page = 20 ) : array {
	global $wpdb;
	$actor_uri = axismundi_act_uri( $actor_uri );
	if ( ! axismundi_act_relations_ready() || ! in_array( $side, array( 'subject', 'object' ), true ) || '' === $actor_uri ) {
		return array( 'items' => array(), 'total' => 0, 'has_more' => false );
	}
	$page          = max( 1, $page );
	$per_page      = max( 1, min( 100, $per_page ) );
	$offset        = ( $page - 1 ) * $per_page;
	$table         = axismundi_act_relations_table();
	$match_column  = $side . '_actor_uri';
	$hash_column   = $side . '_actor_uri_hash';
	$return_column = 'subject' === $side ? 'object_actor_uri' : 'subject_actor_uri';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Allowlisted column names and repository-owned relation table.
	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE relation_type = 'follow' AND state = 'accepted' AND {$hash_column} = %s AND {$match_column} = %s", hash( 'sha256', $actor_uri ), $actor_uri ) );
	// Fetch one extra row so callers never need a second count query to discover next.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Allowlisted column names and repository-owned relation table.
	$rows = (array) $wpdb->get_col( $wpdb->prepare( "SELECT {$return_column} FROM {$table} WHERE relation_type = 'follow' AND state = 'accepted' AND {$hash_column} = %s AND {$match_column} = %s ORDER BY id DESC LIMIT %d OFFSET %d", hash( 'sha256', $actor_uri ), $actor_uri, $per_page + 1, $offset ) );
	$items = array_values( array_unique( array_map( 'strval', array_slice( $rows, 0, $per_page ) ) ) );
	return array( 'items' => $items, 'total' => $total, 'has_more' => count( $rows ) > $per_page );
}

/** Count accepted follower edges without exposing their Actor URIs. */
function axismundi_act_get_follower_count( string $actor_uri ) : int {
	global $wpdb;
	$actor_uri = axismundi_act_uri( $actor_uri );
	if ( ! axismundi_act_relations_ready() || '' === $actor_uri ) {
		return 0;
	}
	$table = axismundi_act_relations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table; values prepared and exact URI checked alongside its hash.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE relation_type = 'follow' AND state = 'accepted' AND object_actor_uri_hash = %s AND object_actor_uri = %s", hash( 'sha256', $actor_uri ), $actor_uri ) );
}

/** Count accepted Actors followed by one Actor. */
function axismundi_act_get_following_count( string $actor_uri ) : int {
	global $wpdb;
	$actor_uri = axismundi_act_uri( $actor_uri );
	if ( ! axismundi_act_relations_ready() || '' === $actor_uri ) {
		return 0;
	}
	$table = axismundi_act_relations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table; values prepared and exact URI checked alongside its hash.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE relation_type = 'follow' AND state = 'accepted' AND subject_actor_uri_hash = %s AND subject_actor_uri = %s", hash( 'sha256', $actor_uri ), $actor_uri ) );
}

/** Accepted followed Actor URIs for one Actor. */
function axismundi_act_get_following( string $actor_uri, int $limit = 100 ) : array {
	return axismundi_act_relation_actor_list( 'subject', $actor_uri, $limit );
}

/**
 * One page of relation rows pointing at an Actor, whatever their state.
 *
 * The existing readers each answer one question — who follows me, who is waiting — which is
 * what a page needs. A consumer rebuilding a projection needs the opposite: the whole
 * relation set for an Actor, including the rejected and undone rows, because a projection
 * that only replayed live states would leave a withdrawn member looking like one who never
 * asked.
 *
 * Paged by ascending id rather than capped, because a cap here is a silent truncation the
 * caller cannot detect: a rebuild that stopped at row 500 would delete every member past it
 * and report success. Callers page with `$after_id` until a short page comes back. Both the
 * type filter and the exact URI comparison are done in SQL so that a full page always means
 * a full page — filtering in PHP after LIMIT would make the page boundary a lie.
 *
 * Read-only and state-blind on purpose. The caller decides what each state means to it; this
 * only reports what the ledger holds, which is the boundary that keeps site policy out of the
 * federation record.
 *
 * @param string   $object_uri Actor being followed or joined.
 * @param string[] $types      Relation types to include; empty means all.
 * @param int      $limit      Page size.
 * @param int      $after_id   Exclusive lower bound on `id`; 0 starts at the beginning.
 * @return array<int,array<string,mixed>>
 */
function axismundi_act_get_relations_for_object( string $object_uri, array $types = array(), int $limit = 200, int $after_id = 0 ) : array {
	global $wpdb;
	if ( ! axismundi_act_relations_ready() || '' === axismundi_act_uri( $object_uri ) ) {
		return array();
	}
	$table  = axismundi_act_relations_table();
	$types  = array_values( array_unique( array_filter( array_map( 'sanitize_key', $types ) ) ) );
	$where  = 'object_actor_uri_hash = %s AND object_actor_uri = %s AND id > %d';
	$values = array( hash( 'sha256', $object_uri ), $object_uri, max( 0, $after_id ) );
	if ( array() !== $types ) {
		$where   .= ' AND relation_type IN (' . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ')';
		$values   = array_merge( $values, $types );
	}
	$values[] = max( 1, min( 1000, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table; every value is prepared.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id ASC LIMIT %d", $values ), ARRAY_A );
	return array_map( 'axismundi_act_relation_read_model', $rows );
}

/**
 * Stable relation read contract, independent of repository column names.
 *
 * @param array<string,mixed> $row Repository row or an existing read model.
 * @return array{id:int,relation_type:string,subject_uri:string,object_uri:string,direction:string,state:string,initiating_activity_uri:string,state_activity_uri:string,evidence_type:string,evidence_ref:string}
 */
function axismundi_act_relation_read_model( array $row ) : array {
	return array(
		'id' => (int) ( $row['id'] ?? 0 ),
		'relation_type' => (string) ( $row['relation_type'] ?? '' ),
		'subject_uri' => (string) ( $row['subject_uri'] ?? $row['subject_actor_uri'] ?? '' ),
		'object_uri' => (string) ( $row['object_uri'] ?? $row['object_actor_uri'] ?? '' ),
		'direction' => (string) ( $row['direction'] ?? '' ),
		'state' => (string) ( $row['state'] ?? '' ),
		'initiating_activity_uri' => (string) ( $row['initiating_activity_uri'] ?? '' ),
		'state_activity_uri' => (string) ( $row['state_activity_uri'] ?? '' ),
		'evidence_type' => (string) ( $row['evidence_type'] ?? '' ),
		'evidence_ref' => (string) ( $row['evidence_ref'] ?? '' ),
	);
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
