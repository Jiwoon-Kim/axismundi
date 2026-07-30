<?php
/**
 * Forum binding repository (docs/AXISMUNDI-FORUM-ARCHITECTURE.md §2.1, §3.1, §6-F0).
 *
 * A Forum (`ax_forum` CPT) is associated with exactly one local managed Group Actor,
 * and a managed Group is bound by at most one Forum. That 1:1-both-ways constraint is
 * the whole point of this file, and it is enforced at the DB layer by a dedicated
 * join table with a UNIQUE key on each side — post meta cannot express uniqueness, so
 * it cannot make a second bind fail atomically.
 *
 * This file owns the binding relation and nothing else. It never creates, renames,
 * publishes, or tombstones an Actor: identity, authority, and Group lifecycle belong
 * to Axismundi Actors, whose APIs Forum consumes. Deleting a Forum or its binding
 * removes only the binding row; the Group Actor is untouched.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_FORUM_DB_VERSION = '4.0';

/** @return string Fully-qualified binding table name. */
function axismundi_forum_bindings_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_forum_bindings';
}

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

/**
 * Move one Group's community policies out of `ax_forum` post meta and into Forum's own table.
 *
 * The `ax_forum` post was a stand-in for a community, and it was the wrong one: a Group Actor
 * already *is* the community, so the post only existed to hold policies and to be the thing
 * other rows pointed at. Keeping policy in its post meta meant a community's admission rules
 * lived in a WordPress post that had no reason to exist, and every projection was keyed to that
 * post rather than to the identity it stood for.
 *
 * Policy stays Forum-owned rather than moving into Actor meta. Actors owns identity; who may
 * post and who may join is a community function, and mixing it into the identity record would
 * put Forum's concerns inside the plugin that must not know about them.
 *
 * Idempotent: a Group already carrying settings is left alone, so a partial run resumes.
 *
 * @return int Groups migrated on this pass.
 */
function axismundi_forum_migrate_bindings_to_settings() : int {
	global $wpdb;
	$bindings = axismundi_forum_bindings_table();
	$settings = axismundi_forum_settings_table();
	// The policy meta keys live with the features that own them, so a partial include order
	// must not silently migrate every community to a default policy it never chose.
	if ( ! defined( 'AXISMUNDI_FORUM_POSTING_POLICY_META' ) || ! defined( 'AXISMUNDI_FORUM_MEMBERSHIP_POLICY_META' ) ) {
		return 0;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration over a fixed custom table.
	if ( (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bindings ) ) !== $bindings ) {
		return 0;
	}
	$now     = current_time( 'mysql', true );
	$moved   = 0;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded one-off migration read.
	foreach ( (array) $wpdb->get_results( "SELECT forum_post_id, group_identity_id, created_at FROM {$bindings}", ARRAY_A ) as $binding ) {
		$group_identity_id = (int) $binding['group_identity_id'];
		$forum_post_id     = (int) $binding['forum_post_id'];
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- existence check before insert.
		if ( $group_identity_id <= 0 || (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$settings} WHERE group_identity_id = %d", $group_identity_id ) ) > 0 ) {
			continue;
		}
		$posting    = (string) get_post_meta( $forum_post_id, AXISMUNDI_FORUM_POSTING_POLICY_META, true );
		$membership = (string) get_post_meta( $forum_post_id, AXISMUNDI_FORUM_MEMBERSHIP_POLICY_META, true );
		$inserted   = $wpdb->insert(
			$settings,
			array(
				'group_identity_id' => $group_identity_id,
				'posting_policy'    => in_array( $posting, array( 'open', 'managers' ), true ) ? $posting : 'open',
				'membership_policy' => in_array( $membership, array( 'open', 'approval' ), true ) ? $membership : 'open',
				// The community is as old as its binding was, not as old as this migration.
				'created_at'        => (string) ( $binding['created_at'] ?? $now ),
				'updated_at'        => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration insert.
		if ( false !== $inserted ) {
			++$moved;
		}
	}
	return $moved;
}

/**
 * Rekey the entry and membership projections from the `ax_forum` post to the Group identity.
 *
 * Entries already carried `group_identity_id` alongside `forum_post_id`, so for them this is
 * only an index change. Memberships did not, and are backfilled through the binding table
 * while it still exists — which is why this must run before the binding table is dropped, and
 * why the drop is a later, separately verified step rather than part of the same pass.
 *
 * The legacy `forum_post_id` columns are deliberately left in place. A migration that deletes
 * its own evidence cannot be checked afterwards, and there is no way to prove the rekey was
 * correct once the old key is gone.
 *
 * @return array{entries:int,memberships:int} Rows given a Group identity on this pass.
 */
function axismundi_forum_migrate_projections_to_group() : array {
	global $wpdb;
	$bindings    = axismundi_forum_bindings_table();
	$memberships = axismundi_forum_memberships_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- migration guard.
	if ( (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bindings ) ) !== $bindings ) {
		return array( 'entries' => 0, 'memberships' => 0 );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- backfill from the still-present binding table.
	$members = (int) $wpdb->query( "UPDATE {$memberships} m JOIN {$bindings} b ON b.forum_post_id = m.forum_post_id SET m.group_identity_id = b.group_identity_id WHERE m.group_identity_id = 0 OR m.group_identity_id IS NULL" );
	$entries = axismundi_forum_entries_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- entries already carry the Group; repair only rows that do not.
	$rows = (int) $wpdb->query( "UPDATE {$entries} e JOIN {$bindings} b ON b.forum_post_id = e.forum_post_id SET e.group_identity_id = b.group_identity_id WHERE e.group_identity_id = 0 OR e.group_identity_id IS NULL" );
	return array( 'entries' => max( 0, $rows ), 'memberships' => max( 0, $members ) );
}

/**
 * Create/upgrade the binding table (dbDelta) and record the schema version only after
 * the UNIQUE constraint that carries the 1:1 invariant is verified present.
 *
 * @return void
 */
function axismundi_forum_install() : void {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset  = $wpdb->get_charset_collate();
	$bindings = axismundi_forum_bindings_table();

	// PRIMARY KEY (forum_post_id) gives the forum side its UNIQUE; the named UNIQUE KEY
	// gives the group side its own. Both directions fail a second bind atomically.
	dbDelta(
		"CREATE TABLE {$bindings} (
			forum_post_id bigint(20) unsigned NOT NULL,
			group_identity_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (forum_post_id),
			UNIQUE KEY group_identity_id (group_identity_id)
		) ENGINE=InnoDB {$charset};"
	);

	$entries = axismundi_forum_entries_table();
	dbDelta(
		"CREATE TABLE {$entries} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			forum_post_id bigint(20) unsigned NOT NULL,
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
			UNIQUE KEY forum_object (forum_post_id, object_uri_hash),
			UNIQUE KEY local_topic_post (source_post_id),
			KEY forum_state_order (forum_post_id, admission_state, moderation_state, sticky_position),
			KEY group_object (group_identity_id, object_uri_hash)
		) ENGINE=InnoDB {$charset};"
	);

	$memberships = axismundi_forum_memberships_table();
	dbDelta(
		"CREATE TABLE {$memberships} (
			forum_post_id bigint(20) unsigned NOT NULL,
			group_identity_id bigint(20) unsigned NOT NULL DEFAULT 0,
			actor_identity_id bigint(20) unsigned NOT NULL,
			membership_evidence_activity_uri text DEFAULT NULL,
			membership_state varchar(12) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (forum_post_id, actor_identity_id),
			UNIQUE KEY group_member (group_identity_id, actor_identity_id),
			KEY actor_state (actor_identity_id, membership_state),
			KEY forum_state (forum_post_id, membership_state),
			KEY group_state (group_identity_id, membership_state)
		) ENGINE=InnoDB {$charset};"
	);

	/*
	 * Community policy, keyed by the Group identity that is the community. The `ax_forum` post
	 * held this in post meta while it stood in for a community it was never needed for; the
	 * Group Actor already is one, so the policy belongs to the identity, not to a post that
	 * exists only to be pointed at.
	 */
	$settings = axismundi_forum_settings_table();
	dbDelta(
		"CREATE TABLE {$settings} (
			group_identity_id bigint(20) unsigned NOT NULL,
			posting_policy varchar(12) NOT NULL DEFAULT 'open',
			membership_policy varchar(12) NOT NULL DEFAULT 'open',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (group_identity_id)
		) ENGINE=InnoDB {$charset};"
	);

	/*
	 * `follow_activity_uri` was named for the only evidence F1 first handled. Lemmy has no Join
	 * and Mobilizon sends one, so the column had to stop naming a single Activity type. dbDelta
	 * has just added the new column beside the old one and cannot rename or copy: left alone it
	 * would leave every existing membership pointing at evidence nothing reads, so the data is
	 * carried across here and the old column dropped to complete the rename.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check on a custom table.
	if ( in_array( 'follow_activity_uri', (array) $wpdb->get_col( "SHOW COLUMNS FROM {$memberships}" ), true ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed-name column backfill.
		$wpdb->query( "UPDATE {$memberships} SET membership_evidence_activity_uri = follow_activity_uri WHERE membership_evidence_activity_uri IS NULL" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- completing the rename.
		$wpdb->query( "ALTER TABLE {$memberships} DROP COLUMN follow_activity_uri" );
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check on a custom table.
	$group_unique = (array) $wpdb->get_col( "SHOW INDEX FROM {$bindings} WHERE Key_name = 'group_identity_id' AND Non_unique = 0" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check on a custom table.
	$engine = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s', DB_NAME, $bindings ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check on a custom table.
	$entry_indexes = (array) $wpdb->get_col( "SHOW INDEX FROM {$entries} WHERE Key_name = 'forum_object'" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check on a custom table.
	$entry_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$entries}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check on a custom table.
	$membership_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$memberships}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check on a custom table.
	$membership_index = (array) $wpdb->get_col( "SHOW INDEX FROM {$memberships} WHERE Key_name = 'forum_state'" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check on a custom table.
	$settings_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$settings}" );

	/*
	 * Carry the community across before anything reads the new key. The binding table and the
	 * `ax_forum` posts are still the only place the old association exists, so this runs while
	 * they are present and is verified before either is removed.
	 */
	if ( in_array( 'group_identity_id', $membership_columns, true ) && in_array( 'group_identity_id', $settings_columns, true ) ) {
		axismundi_forum_migrate_bindings_to_settings();
		axismundi_forum_migrate_projections_to_group();
	}

	if ( ! empty( $group_unique ) && ! empty( $entry_indexes ) && in_array( 'entry_type', $entry_columns, true ) && in_array( 'membership_evidence_activity_uri', $membership_columns, true ) && ! in_array( 'follow_activity_uri', $membership_columns, true ) && in_array( 'group_identity_id', $membership_columns, true ) && in_array( 'membership_policy', $settings_columns, true ) && ! empty( $membership_index ) && 'InnoDB' === $engine ) {
		update_option( 'ax_forum_db_version', AXISMUNDI_FORUM_DB_VERSION, false );
	}
}

/**
 * Whether Forum can reach the Actors authority kernel. When Actors is inactive the
 * binding API fails closed rather than guessing.
 *
 * @return bool
 */
function axismundi_forum_actors_available() : bool {
	return function_exists( 'axismundi_actors_get_by_identity' )
		&& function_exists( 'axismundi_actors_managed_actor_can_manage' );
}

/**
 * Validate that an identity is a Forum-eligible target: a local, managed Group Actor.
 * A Person, the site actor, a Service/Organization, or a remote Group is rejected —
 * F0 binds forums to Group communities only.
 *
 * @param int $group_identity_id Candidate Group identity.
 * @return Axismundi_Actor|WP_Error The resolved actor, or a typed rejection.
 */
function axismundi_forum_eligible_group( int $group_identity_id ) {
	if ( ! axismundi_forum_actors_available() ) {
		return new WP_Error( 'ax_forum_no_actors', __( 'Axismundi Actors is not available.', 'axismundi-forum' ) );
	}
	$actor = axismundi_actors_get_by_identity( $group_identity_id );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_forum_group_missing', __( 'The selected Group Actor does not exist.', 'axismundi-forum' ) );
	}
	if ( ! $actor->is_local() ) {
		return new WP_Error( 'ax_forum_group_remote', __( 'A Forum can only bind a local Group Actor.', 'axismundi-forum' ) );
	}
	if ( ! $actor->is_managed() || 'Group' !== $actor->get_type() ) {
		return new WP_Error( 'ax_forum_group_type', __( 'A Forum can only bind a managed Group Actor.', 'axismundi-forum' ) );
	}
	return $actor;
}

/**
 * Bind a Forum to a managed Group, checking manager authority through Actors and
 * enforcing the 1:1 invariant on both sides.
 *
 * @param int $forum_post_id     The ax_forum post.
 * @param int $group_identity_id The managed Group identity to bind.
 * @param int $user_id           The WP user performing the bind (authority is theirs).
 * @return true|WP_Error
 */
function axismundi_forum_bind_group( int $forum_post_id, int $group_identity_id, int $user_id ) {
	if ( 'ax_forum' !== get_post_type( $forum_post_id ) ) {
		return new WP_Error( 'ax_forum_not_forum', __( 'Binding target is not a Forum.', 'axismundi-forum' ) );
	}
	$actor = axismundi_forum_eligible_group( $group_identity_id );
	if ( is_wp_error( $actor ) ) {
		return $actor;
	}
	// Authority comes from the Actors manager relation, never from copied post meta or
	// a WordPress role: only a manager of this Group may bind it to a Forum.
	if ( ! axismundi_actors_managed_actor_can_manage( $group_identity_id, $user_id ) ) {
		return new WP_Error( 'ax_forum_forbidden', __( 'You do not manage this Group Actor.', 'axismundi-forum' ) );
	}

	global $wpdb;
	$table = axismundi_forum_bindings_table();
	$now   = current_time( 'mysql', true );

	// Pre-check gives a specific error; the UNIQUE keys are the real guarantee below.
	$forum_bound = axismundi_forum_get_binding( $forum_post_id );
	if ( null !== $forum_bound ) {
		return (int) $forum_bound['group_identity_id'] === $group_identity_id
			? true
			: new WP_Error( 'ax_forum_forum_bound', __( 'This Forum is already bound to a different Group.', 'axismundi-forum' ) );
	}
	if ( axismundi_forum_get_forum_for_group( $group_identity_id ) > 0 ) {
		return new WP_Error( 'ax_forum_group_bound', __( 'This Group is already bound to another Forum.', 'axismundi-forum' ) );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- insert on a custom table; UNIQUE keys enforce the invariant.
	$ok = $wpdb->insert(
		$table,
		array(
			'forum_post_id'     => $forum_post_id,
			'group_identity_id' => $group_identity_id,
			'created_at'        => $now,
			'updated_at'        => $now,
		),
		array( '%d', '%d', '%s', '%s' )
	);
	if ( false === $ok ) {
		// A race lost to one of the UNIQUE keys lands here.
		return new WP_Error( 'ax_forum_bind_failed', __( 'Could not bind the Group; it or the Forum may already be bound.', 'axismundi-forum' ) );
	}
	return true;
}

/**
 * Remove a Forum's binding (revoke = row delete). This never touches the Group Actor:
 * unbinding or deleting a Forum must not tombstone or otherwise mutate the identity.
 *
 * @param int $forum_post_id The ax_forum post.
 * @param int $user_id       The WP user requesting the unbind.
 * @return true|WP_Error
 */
function axismundi_forum_unbind_group( int $forum_post_id, int $user_id ) {
	$binding = axismundi_forum_get_binding( $forum_post_id );
	if ( null === $binding ) {
		return true;
	}
	if ( ! axismundi_forum_actors_available()
		|| ! axismundi_actors_managed_actor_can_manage( $binding['group_identity_id'], $user_id ) ) {
		return new WP_Error( 'ax_forum_forbidden', __( 'You do not manage this Group Actor.', 'axismundi-forum' ) );
	}
	if ( function_exists( 'axismundi_forum_count_entries' ) && axismundi_forum_count_entries( $forum_post_id ) > 0 ) {
		return new WP_Error( 'ax_forum_has_entries', __( 'A Forum with Topic entries cannot be unbound.', 'axismundi-forum' ) );
	}
	if ( function_exists( 'axismundi_forum_count_memberships' ) && axismundi_forum_count_memberships( $forum_post_id ) > 0 ) {
		return new WP_Error( 'ax_forum_has_memberships', __( 'A Forum with membership records cannot be unbound.', 'axismundi-forum' ) );
	}
	return axismundi_forum_delete_binding( $forum_post_id );
}

/**
 * Delete a binding row without an authority decision.
 *
 * This is intentionally internal infrastructure for post deletion and fixture cleanup.
 * User-triggered unbinds must call axismundi_forum_unbind_group() instead.
 *
 * @param int $forum_post_id The ax_forum post.
 * @return true|WP_Error
 */
function axismundi_forum_delete_binding( int $forum_post_id ) {
	global $wpdb;
	$deleted = $wpdb->delete( axismundi_forum_bindings_table(), array( 'forum_post_id' => $forum_post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- active-only relation delete on a custom table.
	return false === $deleted
		? new WP_Error( 'ax_forum_unbind_failed', __( 'Could not remove the Forum binding.', 'axismundi-forum' ) )
		: true;
}

/**
 * The binding row for one Forum, or null.
 *
 * @param int $forum_post_id The ax_forum post.
 * @return array{forum_post_id:int,group_identity_id:int,created_at:string,updated_at:string}|null
 */
function axismundi_forum_get_binding( int $forum_post_id ) : ?array {
	if ( $forum_post_id <= 0 ) {
		return null;
	}
	global $wpdb;
	$table = axismundi_forum_bindings_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- PK lookup on a custom table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT forum_post_id, group_identity_id, created_at, updated_at FROM {$table} WHERE forum_post_id = %d", $forum_post_id ), ARRAY_A );
	if ( null === $row ) {
		return null;
	}
	return array(
		'forum_post_id'     => (int) $row['forum_post_id'],
		'group_identity_id' => (int) $row['group_identity_id'],
		'created_at'        => (string) $row['created_at'],
		'updated_at'        => (string) $row['updated_at'],
	);
}

/**
 * The Forum post id bound to a Group, or 0.
 *
 * @param int $group_identity_id The managed Group identity.
 * @return int
 */
function axismundi_forum_get_forum_for_group( int $group_identity_id ) : int {
	if ( $group_identity_id <= 0 ) {
		return 0;
	}
	global $wpdb;
	$table = axismundi_forum_bindings_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- UNIQUE key lookup on a custom table.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT forum_post_id FROM {$table} WHERE group_identity_id = %d", $group_identity_id ) );
}

/**
 * The managed Group Actor bound to a Forum, or null when unbound or unresolvable.
 *
 * @param int $forum_post_id The ax_forum post.
 * @return Axismundi_Actor|null
 */
function axismundi_forum_get_bound_group( int $forum_post_id ) : ?Axismundi_Actor {
	$binding = axismundi_forum_get_binding( $forum_post_id );
	if ( null === $binding || ! axismundi_forum_actors_available() ) {
		return null;
	}
	$actor = axismundi_actors_get_by_identity( $binding['group_identity_id'] );
	return $actor instanceof Axismundi_Actor ? $actor : null;
}

/**
 * When a Forum is deleted, drop its binding row only. The Group Actor's lifecycle is
 * Actors' concern; a Forum deletion must never cascade into the identity.
 *
 * @param int $post_id Deleted post id.
 * @return void
 */
function axismundi_forum_on_delete_post( int $post_id ) : void {
	if ( 'ax_forum' !== get_post_type( $post_id ) ) {
		return;
	}
	if ( function_exists( 'axismundi_forum_delete_entries_for_forum' ) ) {
		axismundi_forum_delete_entries_for_forum( $post_id );
	}
	if ( function_exists( 'axismundi_forum_delete_memberships_for_forum' ) ) {
		axismundi_forum_delete_memberships_for_forum( $post_id );
	}
	axismundi_forum_delete_binding( $post_id );
}
add_action( 'before_delete_post', 'axismundi_forum_on_delete_post' );

/**
 * A user who cannot manage the bound Group cannot remove its Forum binding by
 * deleting the Forum post. Direct programmatic deletion remains available for
 * controlled cleanup; normal WordPress deletion flows pass through this meta cap.
 *
 * @param string[] $caps    Required primitive capabilities.
 * @param string   $cap     Requested meta capability.
 * @param int      $user_id WP user id.
 * @param array    $args    Capability arguments.
 * @return string[]
 */
function axismundi_forum_bound_forum_delete_caps( array $caps, string $cap, int $user_id, array $args ) : array {
	if ( 'delete_post' !== $cap || empty( $args[0] ) || 'ax_forum' !== get_post_type( (int) $args[0] ) ) {
		return $caps;
	}
	$binding = axismundi_forum_get_binding( (int) $args[0] );
	if ( null === $binding ) {
		return $caps;
	}
	return axismundi_forum_actors_available()
		&& axismundi_actors_managed_actor_can_manage( $binding['group_identity_id'], $user_id )
		? $caps
		: array( 'do_not_allow' );
}
add_filter( 'map_meta_cap', 'axismundi_forum_bound_forum_delete_caps', 10, 4 );
