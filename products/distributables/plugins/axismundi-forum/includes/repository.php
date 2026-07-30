<?php
/**
 * Forum community repository (docs/AXISMUNDI-FORUM-ARCHITECTURE.md §2.1, §3.1).
 *
 * A community *is* a managed Group Actor. Forum does not create a second record to stand for
 * one — it attaches discussion capability to the identity that already exists.
 *
 * This replaces the `ax_forum` CPT and its binding table. That design gave every community two
 * identities: a Group Actor that federation and people used, and a WordPress post that existed
 * only to hold policy and be the thing projections pointed at. It also produced two public
 * pages for one community, and no answer to which was canonical. The Group is canonical; there
 * is nothing to bind it to.
 *
 * This file owns the community settings record and nothing else. It never creates, renames,
 * publishes, or tombstones an Actor: identity, authority, and Group lifecycle belong to
 * Axismundi Actors, whose APIs Forum consumes. Every public managed Group is a community;
 * its settings row is created automatically and only stores Forum policy.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_FORUM_DB_VERSION = '5.2';

/** @return string Fully-qualified Forum-entry projection table name. */
function axismundi_forum_entries_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_forum_entries';
}

/** @return string Fully-qualified Forum-membership projection table name. */
function axismundi_forum_memberships_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_forum_memberships';
}

/** @return string Fully-qualified per-Group community settings table name. */
function axismundi_forum_settings_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_forum_settings';
}

/** @return string[] Every table this plugin owns, for install and reset. */
function axismundi_forum_owned_tables() : array {
	return array(
		axismundi_forum_settings_table(),
		axismundi_forum_entries_table(),
		axismundi_forum_memberships_table(),
	);
}

/**
 * Create the Forum schema and record the version only once the constraints are verified.
 *
 * Every table is keyed by `group_identity_id`, because the Group identity is the community.
 *
 * @return void
 */
function axismundi_forum_install() : void {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();

	// A row stores policy for a public Group. Publishing the Group, not this row, makes it a
	// community.
	$settings = axismundi_forum_settings_table();
	dbDelta(
		"CREATE TABLE {$settings} (
			group_identity_id bigint(20) unsigned NOT NULL,
			posting_policy varchar(12) NOT NULL DEFAULT 'open',
			membership_policy varchar(12) NOT NULL DEFAULT 'open',
			topic_approval_policy varchar(12) NOT NULL DEFAULT 'open',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (group_identity_id)
		) ENGINE=InnoDB {$charset};"
	);

	// UNIQUE (group, object) is the admission invariant: one object enters one community once.
	$entries = axismundi_forum_entries_table();
	dbDelta(
		"CREATE TABLE {$entries} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			group_identity_id bigint(20) unsigned NOT NULL,
			object_uri text NOT NULL,
			object_uri_hash char(64) NOT NULL,
			entry_type varchar(12) NOT NULL,
			source_post_id bigint(20) unsigned DEFAULT NULL,
			submission_actor_identity_id bigint(20) unsigned DEFAULT NULL,
			admission_state varchar(12) NOT NULL DEFAULT 'visible',
			moderation_state varchar(12) NOT NULL DEFAULT 'visible',
			locked_at datetime DEFAULT NULL,
			sticky_position int(10) unsigned DEFAULT NULL,
			accepted_activity_uri text DEFAULT NULL,
			announced_activity_uri text DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY group_object (group_identity_id, object_uri_hash),
			UNIQUE KEY local_topic_post (source_post_id),
			KEY group_state_order (group_identity_id, admission_state, moderation_state, sticky_position)
		) ENGINE=InnoDB {$charset};"
	);

	// (group, actor) is the natural key of a membership and now the primary one.
	$memberships = axismundi_forum_memberships_table();
	dbDelta(
		"CREATE TABLE {$memberships} (
			group_identity_id bigint(20) unsigned NOT NULL,
			actor_identity_id bigint(20) unsigned NOT NULL,
			membership_evidence_activity_uri text DEFAULT NULL,
			membership_state varchar(12) NOT NULL DEFAULT 'pending',
			membership_role varchar(12) NOT NULL DEFAULT 'member',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (group_identity_id, actor_identity_id),
			KEY actor_state (actor_identity_id, membership_state),
			KEY group_state (group_identity_id, membership_state),
			KEY group_role (group_identity_id, membership_role, membership_state)
		) ENGINE=InnoDB {$charset};"
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check on a custom table.
	$entry_unique = (array) $wpdb->get_col( "SHOW INDEX FROM {$entries} WHERE Key_name = 'group_object' AND Non_unique = 0" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check on a custom table.
	$entry_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$entries}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check on a custom table.
	$membership_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$memberships}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check on a custom table.
	$settings_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$settings}" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- storage-engine verification.
	$engine = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s', DB_NAME, $entries ) );

	/*
	 * The legacy columns are a hard stop rather than something to repair. This plugin has never
	 * shipped, so a table still keyed to `forum_post_id` is a development database that predates
	 * the schema, not a user's data — writing an upgrade path for it would be inventing history.
	 * The version stays unrecorded, which surfaces the reset action instead.
	 */
	$legacy = in_array( 'forum_post_id', $entry_columns, true ) || in_array( 'forum_post_id', $membership_columns, true );

	if ( ! $legacy
		&& ! empty( $entry_unique )
		&& in_array( 'entry_type', $entry_columns, true )
		&& in_array( 'membership_evidence_activity_uri', $membership_columns, true )
		&& in_array( 'membership_role', $membership_columns, true )
		&& in_array( 'membership_policy', $settings_columns, true )
		&& in_array( 'topic_approval_policy', $settings_columns, true )
		&& 'InnoDB' === $engine ) {
		update_option( 'ax_forum_db_version', AXISMUNDI_FORUM_DB_VERSION, false );
	}
}

/**
 * Whether the Forum schema on this site predates the Group-keyed one.
 *
 * @return bool
 */
function axismundi_forum_schema_is_stale() : bool {
	return (string) get_option( 'ax_forum_db_version', '' ) !== AXISMUNDI_FORUM_DB_VERSION;
}

/**
 * Drop and recreate every Forum-owned table.
 *
 * Forum is pre-release and its schema is still moving, so shipping upgrade migrations would
 * mean maintaining paths between shapes no site has ever run in production. The honest tool for
 * a development database is a reset, and WordPress.com staging has no WP-CLI to do it by hand.
 *
 * Strictly bounded to the three tables this plugin owns. Group Actors, Actor profiles, `ax_topic`
 * posts, the Activity ledger, and relations are all owned elsewhere and are not touched — a
 * community can be rebuilt from them, which is the whole reason the projections are projections.
 *
 * @return true|WP_Error
 */
function axismundi_forum_reset_development_data() {
	global $wpdb;
	$tables = array_merge( axismundi_forum_owned_tables(), array( $wpdb->prefix . 'ax_forum_bindings' ) );
	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- deliberate reset of Forum-owned tables only.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
	delete_option( 'ax_forum_db_version' );
	axismundi_forum_install();
	return axismundi_forum_schema_is_stale()
		? new WP_Error( 'ax_forum_reset_failed', __( 'The Forum tables could not be recreated.', 'axismundi-forum' ) )
		: true;
}

/**
 * Whether Forum can reach the Actors authority kernel. When Actors is inactive the
 * community API fails closed rather than guessing.
 *
 * @return bool
 */
function axismundi_forum_actors_available() : bool {
	return function_exists( 'axismundi_actors_get_by_identity' )
		&& function_exists( 'axismundi_actors_managed_actor_can_manage' );
}

/** @return bool Whether an Actor is a public managed Group and therefore a community. */
function axismundi_forum_public_community_group( $actor ) : bool {
	return $actor instanceof Axismundi_Actor
		&& $actor->is_local()
		&& $actor->is_managed()
		&& 'Group' === $actor->get_type()
		&& 'public' === $actor->get_status();
}

/** @return int The first local owner, used only for the initial explicit self-Follow. */
function axismundi_forum_community_owner_user_id( int $group_identity_id ) : int {
	if ( ! function_exists( 'axismundi_actors_group_managers' ) ) {
		return 0;
	}
	foreach ( (array) axismundi_actors_group_managers( $group_identity_id ) as $manager ) {
		if ( 'owner' === (string) ( $manager['role'] ?? '' ) ) {
			return (int) ( $manager['user_id'] ?? 0 );
		}
	}
	return 0;
}

/**
 * Initialize Forum side effects for an already-public managed Group.
 *
 * Community identity comes from the Actor, not from a policy row. This deliberately does not
 * write settings: default open policies are read virtually until a manager changes one.
 */
function axismundi_forum_ensure_public_community( Axismundi_Actor $actor ) : bool {
	if ( ! axismundi_forum_public_community_group( $actor ) ) {
		return false;
	}
	do_action( 'axismundi_forum_public_community_initialized', $actor, axismundi_forum_community_owner_user_id( $actor->get_identity_id() ) );
	return true;
}

/** Create Forum policy when Actors publishes or updates a managed Group profile. */
function axismundi_forum_sync_published_group( $actor ) : void {
	if ( $actor instanceof Axismundi_Actor ) {
		axismundi_forum_ensure_public_community( $actor );
	}
}
add_action( 'axismundi_actors_local_actor_profile_updated', 'axismundi_forum_sync_published_group', 20 );

/** Create Forum policy when an existing managed Group becomes public. */
function axismundi_forum_sync_group_status( int $identity_id, string $status ) : void {
	if ( 'public' === $status && function_exists( 'axismundi_actors_get_by_identity' ) ) {
		axismundi_forum_sync_published_group( axismundi_actors_get_by_identity( $identity_id ) );
	}
}
add_action( 'axismundi_actors_status_changed', 'axismundi_forum_sync_group_status', 20, 2 );

/**
 * The policy settings for one public managed Group, or null otherwise.
 *
 * @param int $group_identity_id Group identity.
 * @return array{group_identity_id:int,posting_policy:string,membership_policy:string,topic_approval_policy:string,created_at:string,updated_at:string}|null
 */
function axismundi_forum_get_community( int $group_identity_id ) : ?array {
	if ( $group_identity_id <= 0 || ! function_exists( 'axismundi_actors_get_by_identity' ) ) {
		return null;
	}
	$actor = axismundi_actors_get_by_identity( $group_identity_id );
	if ( ! axismundi_forum_public_community_group( $actor ) ) {
		return null;
	}
	global $wpdb;
	$table = axismundi_forum_settings_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- PK lookup on a custom table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE group_identity_id = %d", $group_identity_id ), ARRAY_A );
	if ( null === $row ) {
		return array( 'group_identity_id' => $group_identity_id, 'posting_policy' => 'open', 'membership_policy' => 'open', 'topic_approval_policy' => 'open', 'created_at' => '', 'updated_at' => '' );
	}
	return array(
		'group_identity_id' => (int) $row['group_identity_id'],
		'posting_policy'    => (string) $row['posting_policy'],
		'membership_policy' => (string) $row['membership_policy'],
		'topic_approval_policy' => (string) $row['topic_approval_policy'],
		'created_at'        => (string) $row['created_at'],
		'updated_at'        => (string) $row['updated_at'],
	);
}

/** @return bool Whether this public managed Group is a community. */
function axismundi_forum_is_community( int $group_identity_id ) : bool {
	return null !== axismundi_forum_get_community( $group_identity_id );
}

/** Update one community policy after verifying Group-manager authority. */
function axismundi_forum_update_community_policy( int $group_identity_id, int $user_id, string $column, string $value ) {
	if ( ! in_array( $column, array( 'posting_policy', 'membership_policy', 'topic_approval_policy' ), true ) || ! axismundi_forum_is_community( $group_identity_id )
		|| ! axismundi_forum_actors_available() || ! axismundi_actors_managed_actor_can_manage( $group_identity_id, $user_id ) ) {
		return new WP_Error( 'ax_forum_forbidden', __( 'You do not manage this community.', 'axismundi-forum' ) );
	}
	global $wpdb;
	$now = current_time( 'mysql', true );
	$table = axismundi_forum_settings_table();
	$sql = 'INSERT INTO ' . $table . ' (group_identity_id, posting_policy, membership_policy, topic_approval_policy, created_at, updated_at) VALUES (%d, %s, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE ' . $column . ' = VALUES(' . $column . '), updated_at = VALUES(updated_at)';
	$existing = axismundi_forum_get_community( $group_identity_id );
	$posted = 'posting_policy' === $column ? $value : (string) $existing['posting_policy'];
	$membership = 'membership_policy' === $column ? $value : (string) $existing['membership_policy'];
	$topic_approval = 'topic_approval_policy' === $column ? $value : (string) $existing['topic_approval_policy'];
	$written = $wpdb->query( $wpdb->prepare( $sql, $group_identity_id, $posted, $membership, $topic_approval, $now, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- allowlisted policy column and Group-keyed upsert.
	return false === $written ? new WP_Error( 'ax_forum_settings_write', __( 'The community settings could not be saved.', 'axismundi-forum' ) ) : true;
}

/**
 * The Group Actor hosting one community, or null when it is not a community or unresolvable.
 *
 * @param int $group_identity_id Group identity.
 * @return Axismundi_Actor|null
 */
function axismundi_forum_get_community_group( int $group_identity_id ) : ?Axismundi_Actor {
	if ( ! axismundi_forum_is_community( $group_identity_id ) || ! axismundi_forum_actors_available() ) {
		return null;
	}
	$actor = axismundi_actors_get_by_identity( $group_identity_id );
	return $actor instanceof Axismundi_Actor ? $actor : null;
}

/**
 * Communities one user may operate, for selectors and admin screens.
 *
 * @param int $user_id WP user.
 * @return array<int,Axismundi_Actor> Group Actors with discussion enabled, keyed by identity.
 */
function axismundi_forum_manageable_communities( int $user_id ) : array {
	if ( ! function_exists( 'axismundi_actors_list_manageable_groups' ) ) {
		return array();
	}
	$communities = array();
	foreach ( (array) axismundi_actors_list_manageable_groups( $user_id ) as $actor ) {
		if ( $actor instanceof Axismundi_Actor && axismundi_forum_is_community( $actor->get_identity_id() ) ) {
			$communities[ $actor->get_identity_id() ] = $actor;
		}
	}
	return $communities;
}
