<?php
/**
 * Managed-actor authority kernel (DATA-MODEL §9.6.1).
 *
 * A managed actor -- a `Group` (forum / Lemmy community), or a `Service` /
 * `Organization` publisher -- is neither the site actor nor tied 1:1 to a WP
 * user. It is administered through `wp_ax_actor_managers`: a small, active-only
 * relation of *who may operate this actor*. This file owns creation of managed
 * actors and every read/write of that authority relation, and nothing else.
 *
 * What this file is NOT: it does not model Follow, membership, moderation, votes,
 * or any object/activity state. Those belong to the Activity / social store and,
 * for a forum, to the Forum plugin (DATA-MODEL §9.7). `can_manage()` answers only
 * "may this WP user operate this managed actor," never "is this user a follower or
 * member." The one authority invariant enforced here is that a managed actor
 * always keeps at least one `owner`.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manager roles, highest authority first. Rank is index-derived so a comparison
 * ("at least manager") never hard-codes a second copy of the ordering.
 *
 * @return array<string,int> role => rank (higher = more authority).
 */
function axismundi_actors_manager_roles() : array {
	return array(
		'owner'   => 3,
		'manager' => 2,
		'editor'  => 1,
	);
}

/** Rank of one role, or 0 for an unknown role. */
function axismundi_actors_manager_role_rank( string $role ) : int {
	return (int) ( axismundi_actors_manager_roles()[ $role ] ?? 0 );
}

/**
 * Create a managed actor and seed its creator as the first `owner`.
 *
 * `Group`, `Service` and `Organization` are the same kind of thing to administer -- an identity
 * nobody logs in as, run through the manager relation, publishing under a handle of its own. They
 * differ in what they mean to a peer, which is why the type is a parameter and not three functions.
 *
 * The actor is created through the same local-actor path Person/Site actors use
 * (`actor_scope='managed'`, `actor_type='Group'`, `local_user_id=NULL`), then the
 * owner relation is written. If the owner write fails the just-created actor is
 * removed, because a managed actor with no owner would violate the kernel's one
 * invariant; there is never an observable ownerless managed actor.
 *
 * @param array<string,mixed> $args owner_user_id (required), preferred_username, actor_type (default Group), status.
 * @return Axismundi_Actor|WP_Error
 */
function axismundi_actors_create_managed_actor( array $args ) {
	global $wpdb;
	$owner_user_id = (int) ( $args['owner_user_id'] ?? 0 );
	if ( $owner_user_id <= 0 || ! get_userdata( $owner_user_id ) ) {
		return new WP_Error( 'ax_actors_managed_owner', __( 'A managed actor requires a valid owner user.', 'axismundi-actors' ) );
	}
	$type = (string) ( $args['actor_type'] ?? 'Group' );
	if ( ! in_array( $type, array( 'Group', 'Service', 'Organization' ), true ) ) {
		return new WP_Error( 'ax_actors_managed_type', __( 'A managed actor must be a Group, Service, or Organization.', 'axismundi-actors' ) );
	}

	$actor = axismundi_actors_create_local(
		array(
			'actor_type'         => $type,
			'actor_scope'        => 'managed',
			'preferred_username' => (string) ( $args['preferred_username'] ?? '' ),
			'status'             => (string) ( $args['status'] ?? 'internal' ),
			// local_user_id stays NULL: a managed actor is not a WP-user Person.
		)
	);
	if ( is_wp_error( $actor ) ) {
		return $actor;
	}

	$seeded = axismundi_actors_write_manager_row( $actor->get_identity_id(), $owner_user_id, 'owner' );
	if ( is_wp_error( $seeded ) ) {
		// Compensate: an ownerless managed actor must never be observable.
		$identity_id = $actor->get_identity_id();
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- compensating rollback of a just-created actor.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- compensating rollback of a just-created identity.
		return $seeded;
	}
	return $actor;
}

/**
 * Whether one WP user may operate one managed actor, at or above an optional
 * minimum role. This is a pure relation predicate over `wp_ax_actor_managers`:
 * it deliberately does NOT grant a site admin authority here, so callers that
 * want an admin override (e.g. routing preview) OR it in explicitly and the
 * relation stays the single source of truth for "is a manager."
 *
 * @param int         $identity_id Managed actor identity.
 * @param int         $user_id     Viewer WP user.
 * @param string|null $min_role    Minimum role (owner|manager|editor), or null for any.
 * @return bool
 */
function axismundi_actors_managed_actor_can_manage( int $identity_id, int $user_id, ?string $min_role = null ) : bool {
	if ( $identity_id <= 0 || $user_id <= 0 ) {
		return false;
	}
	global $wpdb;
	$table = axismundi_actors_managers_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- composite-PK exact lookup on a custom table.
	$role = (string) $wpdb->get_var( $wpdb->prepare( "SELECT role FROM {$table} WHERE identity_id = %d AND user_id = %d", $identity_id, $user_id ) );
	if ( '' === $role ) {
		return false;
	}
	return null === $min_role
		|| axismundi_actors_manager_role_rank( $role ) >= axismundi_actors_manager_role_rank( $min_role );
}

/**
 * Managers of one managed actor, owners first.
 *
 * @param int $identity_id Managed actor identity.
 * @return array<int,array{user_id:int,role:string,created_at:string,updated_at:string}>
 */
function axismundi_actors_group_managers( int $identity_id ) : array {
	if ( $identity_id <= 0 ) {
		return array();
	}
	global $wpdb;
	$table = axismundi_actors_managers_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed lookup on a custom table.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT user_id, role, created_at, updated_at FROM {$table} WHERE identity_id = %d", $identity_id ), ARRAY_A );
	usort(
		$rows,
		static fn( array $a, array $b ) : int => axismundi_actors_manager_role_rank( (string) $b['role'] ) <=> axismundi_actors_manager_role_rank( (string) $a['role'] )
	);
	return array_map(
		static fn( array $r ) : array => array(
			'user_id'    => (int) $r['user_id'],
			'role'       => (string) $r['role'],
			'created_at' => (string) $r['created_at'],
			'updated_at' => (string) $r['updated_at'],
		),
		$rows
	);
}

/**
 * Managed actors one WP user may operate, at or above an optional minimum role.
 *
 * Named for what the relation holds rather than for the first caller's use of it: a
 * Forum asks this for bindable Groups, but an Organization and a Service are in the
 * same list, and the acting-Actor switcher offers all three.
 *
 * @param int         $user_id  Viewer WP user.
 * @param string|null $min_role Minimum role, or null for any.
 * @return Axismundi_Actor[]
 */
function axismundi_actors_list_manageable_actors( int $user_id, ?string $min_role = null ) : array {
	if ( $user_id <= 0 ) {
		return array();
	}
	global $wpdb;
	$table = axismundi_actors_managers_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- KEY(user_id, role) lookup on a custom table.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT identity_id, role FROM {$table} WHERE user_id = %d", $user_id ), ARRAY_A );
	$min_rank = null === $min_role ? 0 : axismundi_actors_manager_role_rank( $min_role );
	$actors   = array();
	foreach ( $rows as $row ) {
		if ( axismundi_actors_manager_role_rank( (string) $row['role'] ) < $min_rank ) {
			continue;
		}
		$actor = axismundi_actors_get_by_identity( (int) $row['identity_id'] );
		if ( $actor instanceof Axismundi_Actor ) {
			$actors[] = $actor;
		}
	}
	return $actors;
}

/** @return Axismundi_Actor[] Every local managed Group, for site-administrator recovery only. */
function axismundi_actors_list_all_managed_actors() : array {
	global $wpdb;
	$table = axismundi_actors_actors_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- site-admin recovery list of local managed identities.
	$ids = (array) $wpdb->get_col( "SELECT identity_id FROM {$table} WHERE actor_scope = 'managed' ORDER BY identity_id ASC" );
	$groups = array();
	foreach ( $ids as $identity_id ) {
		$actor = axismundi_actors_get_by_identity( (int) $identity_id );
		if ( $actor instanceof Axismundi_Actor && $actor->is_local() && $actor->is_managed() && 'Group' === $actor->get_type() ) {
			$groups[] = $actor;
		}
	}
	return $groups;
}

/**
 * Public Group Actors known to this server, for the human-facing directory.
 *
 * Local entries must be published managed Groups with locked handles. Remote entries
 * are limited to cached Actors that declared themselves public; this is a directory of
 * what this server knows, not a claim to enumerate every Group on the fediverse.
 *
 * @param int $limit  Maximum rows.
 * @param int $offset Result offset.
 * @return Axismundi_Actor[]
 */
function axismundi_actors_get_public_managed_actors( int $limit = 50, int $offset = 0 ) : array {
	global $wpdb;
	$limit      = max( 1, min( 100, $limit ) );
	$offset     = max( 0, $offset );
	$identities = axismundi_actors_identities_table();
	$actors     = axismundi_actors_actors_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed table names; pagination values prepared.
	$rows = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT i.*, a.* FROM {$identities} i INNER JOIN {$actors} a ON a.identity_id = i.id
			 WHERE a.actor_type = 'Group' AND i.status = 'public'
			 AND ( i.origin = 'remote' OR ( i.origin = 'local' AND a.actor_scope = 'managed' AND a.handle_locked_at IS NOT NULL ) )
			 ORDER BY COALESCE( NULLIF( a.display_name, '' ), a.preferred_username, i.canonical_uri ) ASC, i.id ASC LIMIT %d OFFSET %d",
			$limit,
			$offset
		),
		ARRAY_A
	);
	return array_map( static fn( array $row ) : Axismundi_Actor => Axismundi_Actor::from_row( $row ), $rows );
}

/**
 * Search public Group Actors already known to this server.
 *
 * This deliberately searches the local registry only. It never performs remote
 * discovery from an editor keystroke.
 *
 * @return Axismundi_Actor[]
 */
function axismundi_actors_search_public_groups( string $search, int $limit = 20 ) : array {
	global $wpdb;
	$search     = trim( $search );
	$limit      = max( 1, min( 20, $limit ) );
	$identities = axismundi_actors_identities_table();
	$actors     = axismundi_actors_actors_table();
	$addresses  = axismundi_actors_addresses_table();
	$where      = "a.actor_type = 'Group' AND i.status = 'public' AND ( i.origin = 'remote' OR ( i.origin = 'local' AND a.actor_scope = 'managed' AND a.handle_locked_at IS NOT NULL ) )";
	$args       = array();
	if ( '' !== $search ) {
		$like   = '%' . $wpdb->esc_like( ltrim( $search, '@' ) ) . '%';
		$where .= " AND ( a.preferred_username LIKE %s OR a.display_name LIKE %s OR i.canonical_uri LIKE %s OR EXISTS ( SELECT 1 FROM {$addresses} ad WHERE ad.identity_id = i.id AND ad.address LIKE %s ) )";
		$args   = array( $like, $like, $like, $like );
	}
	$sql    = "SELECT i.*, a.* FROM {$identities} i INNER JOIN {$actors} a ON a.identity_id = i.id WHERE {$where} ORDER BY CASE WHEN i.origin = 'local' THEN 0 ELSE 1 END, COALESCE( NULLIF( a.display_name, '' ), a.preferred_username, i.canonical_uri ) ASC, i.id ASC LIMIT %d";
	$args[] = $limit;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom tables and prepared search values.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );
	return array_map( static fn( array $row ) : Axismundi_Actor => Axismundi_Actor::from_row( $row ), $rows );
}

/** Count the bounded public Group directory. */
function axismundi_actors_count_public_groups() : int {
	global $wpdb;
	$identities = axismundi_actors_identities_table();
	$actors     = axismundi_actors_actors_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed table names and constant predicates.
	return (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$identities} i INNER JOIN {$actors} a ON a.identity_id = i.id
		 WHERE a.actor_type = 'Group' AND i.status = 'public'
		 AND ( i.origin = 'remote' OR ( i.origin = 'local' AND a.actor_scope = 'managed' AND a.handle_locked_at IS NOT NULL ) )"
	);
}

/** Count the owners of one managed actor. */
function axismundi_actors_managed_owner_count( int $identity_id ) : int {
	global $wpdb;
	$table = axismundi_actors_managers_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed count on a custom table.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE identity_id = %d AND role = 'owner'", $identity_id ) );
}

/** Insert or update one manager row (no invariant checks; internal seam). */
function axismundi_actors_write_manager_row( int $identity_id, int $user_id, string $role ) {
	if ( ! array_key_exists( $role, axismundi_actors_manager_roles() ) ) {
		return new WP_Error( 'ax_actors_manager_role', __( 'Unknown manager role.', 'axismundi-actors' ) );
	}
	global $wpdb;
	$table = axismundi_actors_managers_table();
	$now   = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- upsert on a composite-PK custom table.
	$ok = $wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$table} (identity_id, user_id, role, created_at, updated_at)
			 VALUES (%d, %d, %s, %s, %s)
			 ON DUPLICATE KEY UPDATE role = VALUES(role), updated_at = VALUES(updated_at)",
			$identity_id,
			$user_id,
			$role,
			$now,
			$now
		)
	);
	return false === $ok
		? new WP_Error( 'ax_actors_manager_write', __( 'Could not write the manager relation.', 'axismundi-actors' ) )
		: true;
}

/**
 * Add or promote/demote a manager, enforcing the one-owner invariant.
 *
 * Only a managed actor can carry managers, and the last owner can never be
 * demoted -- demoting them would leave the actor ownerless.
 *
 * @return true|WP_Error
 */
function axismundi_actors_add_manager( int $identity_id, int $user_id, string $role ) {
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_managed() ) {
		return new WP_Error( 'ax_actors_manager_target', __( 'Managers can only be assigned to a managed actor.', 'axismundi-actors' ) );
	}
	if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
		return new WP_Error( 'ax_actors_manager_user', __( 'A manager must be a valid user.', 'axismundi-actors' ) );
	}
	if ( 'owner' !== $role && axismundi_actors_managed_actor_can_manage( $identity_id, $user_id, 'owner' ) && 1 === axismundi_actors_managed_owner_count( $identity_id ) ) {
		return new WP_Error( 'ax_actors_last_owner', __( 'The last owner cannot be demoted.', 'axismundi-actors' ) );
	}
	return axismundi_actors_write_manager_row( $identity_id, $user_id, $role );
}

/**
 * Remove a manager, enforcing the one-owner invariant (revoke = row delete).
 *
 * @return true|WP_Error
 */
function axismundi_actors_remove_manager( int $identity_id, int $user_id ) {
	if ( axismundi_actors_managed_actor_can_manage( $identity_id, $user_id, 'owner' ) && 1 === axismundi_actors_managed_owner_count( $identity_id ) ) {
		return new WP_Error( 'ax_actors_last_owner', __( 'The last owner cannot be removed.', 'axismundi-actors' ) );
	}
	global $wpdb;
	$deleted = $wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $identity_id, 'user_id' => $user_id ), array( '%d', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- active-only relation delete on a custom table.
	return false === $deleted
		? new WP_Error( 'ax_actors_manager_remove', __( 'Could not remove the manager relation.', 'axismundi-actors' ) )
		: true;
}

/**
 * Let a site administrator explicitly register themselves as a managed Group manager.
 *
 * This is recovery authority, not an implicit `can_manage()` exception. The relation remains
 * visible and auditable just like one granted by an existing manager.
 */
function axismundi_actors_claim_managed_group( int $identity_id, int $user_id ) {
	if ( $user_id <= 0 || ! user_can( $user_id, 'manage_options' ) ) {
		return new WP_Error( 'ax_actors_manager_forbidden', __( 'Only a site administrator may claim this managed Group.', 'axismundi-actors' ) );
	}
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || ! $actor->is_managed() || 'Group' !== $actor->get_type() ) {
		return new WP_Error( 'ax_actors_manager_target', __( 'Only a local managed Group may be claimed.', 'axismundi-actors' ) );
	}
	return axismundi_actors_add_manager( $identity_id, $user_id, 'manager' );
}
