<?php
/**
 * Historical regression adapters, out-of-scope Calendar boundary, and Group directory policy.
 *
 * A managed actor -- a `Group` (forum / Lemmy community), or a `Service` /
 * `Organization` publisher -- is neither the site actor nor tied 1:1 to a WP
 * user. It is administered through `wp_ax_actor_managers`: a small, active-only
 * relation of *who may operate this actor*. This file owns creation of managed
 * actors through explicit WP login adapters. Canonical principal policy, storage,
 * roles, live-owner counts and creation live in principals.php.
 *
 * What this file is NOT: it does not model Follow, membership, moderation, votes,
 * or any object/activity state. Those belong to the Activity / social store and,
 * for a forum, to the Forum plugin (DATA-MODEL §9.7). `can_manage()` answers only
 * "may this WP user operate this managed actor," never "is this user a follower or
 * member." Account access also reads this table through account-access.php,
 * while these legacy managed APIs keep their managed-only scope.
 * Actors/Forum/Notifications production consumers use the typed principal API.
 * The two query adapters still serve Calendar, whose migration is outside this task.
 * Other old signatures are retained for historical audits, not new production callers.
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
	return axismundi_actors_principal_roles();
}

/** Rank of one role, or 0 for an unknown role. */
function axismundi_actors_manager_role_rank( string $role ) : int {
	return axismundi_actors_principal_role_rank( $role );
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
 * owner relation is written in create_local's transaction. Failure rolls back
 * the identity and initial grant together.
 *
 * @param array<string,mixed> $args owner_user_id (required), preferred_username, actor_type (default Group), status.
 * @return Axismundi_Actor|WP_Error
 */
function axismundi_actors_create_managed_actor( array $args ) {
	$owner_user_id = (int) ( $args['owner_user_id'] ?? 0 );
	if ( $owner_user_id <= 0 || ! get_userdata( $owner_user_id ) ) {
		return new WP_Error( 'ax_actors_managed_owner', __( 'A managed actor requires a valid owner user.', 'axismundi-actors' ) );
	}
	$type = (string) ( $args['actor_type'] ?? 'Group' );
	if ( ! in_array( $type, array( 'Group', 'Service', 'Organization' ), true ) ) {
		return new WP_Error( 'ax_actors_managed_type', __( 'A managed actor must be a Group, Service, or Organization.', 'axismundi-actors' ) );
	}

	return axismundi_actors_create_principal_account(
		array(
			'actor_type'         => $type,
			'actor_scope'        => 'managed',
			'preferred_username' => (string) ( $args['preferred_username'] ?? '' ),
			'status'             => (string) ( $args['status'] ?? 'public' ),
			// local_user_id stays NULL: a managed actor is not a WP-user Person.
		),
		'wp_user',
		$owner_user_id
	);
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
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->has_principals() ) {
		return false;
	}
	// Legacy managed-screen boundary: a logged-in WP User, not an inferred Actor.
	return axismundi_actors_principal_can_manage( $identity_id, 'wp_user', $user_id, $min_role );
}

/**
 * Managers of one managed actor, owners first.
 *
 * @param int $identity_id Managed actor identity.
 * @return array<int,array{user_id:int,role:string,created_at:string,updated_at:string}>
 */
function axismundi_actors_group_managers( int $identity_id ) : array {
	// Excluded management UI consumes WP logins; do not infer users from Account grants.
	$rows = array_values( array_filter( axismundi_actors_account_principals( $identity_id ), static fn( array $r ) : bool => 'wp_user' === $r['principal_kind'] ) );
	return array_map(
		static fn( array $r ) : array => array(
			'user_id'    => (int) $r['principal_id'],
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
	// Compatibility name; the relation, not a stored scope, determines the inventory.
	return array_values( array_filter(
		axismundi_actors_accounts_for_principal( 'wp_user', $user_id, $min_role ),
		static fn( Axismundi_Actor $actor ) : bool => $actor->has_principals()
	) );
}

/** @return Axismundi_Actor[] Local principal-operated Groups, for the recovery inventory. */
function axismundi_actors_list_all_managed_actors() : array {
	global $wpdb;
	$table = axismundi_actors_actors_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- site-admin recovery list of local managed identities.
	$ids = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT a.identity_id FROM %i a WHERE EXISTS (SELECT 1 FROM %i m WHERE m.identity_id = a.identity_id) ORDER BY a.identity_id ASC', $table, axismundi_actors_managers_table() ) );
	$groups = array();
	foreach ( $ids as $identity_id ) {
		$actor = axismundi_actors_get_by_identity( (int) $identity_id );
		if ( $actor instanceof Axismundi_Actor && $actor->is_local() && $actor->has_principals() && 'Group' === $actor->get_type() ) {
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
			"SELECT i.*, a.* FROM %i i INNER JOIN %i a ON a.identity_id = i.id
			 WHERE a.actor_type = 'Group' AND i.status = 'public'
			 AND ( i.origin = 'remote' OR ( i.origin = 'local' AND EXISTS (SELECT 1 FROM {$wpdb->prefix}ax_actor_managers m WHERE m.identity_id = a.identity_id) AND a.handle_locked_at IS NOT NULL ) )
			 ORDER BY COALESCE( NULLIF( a.display_name, '' ), a.preferred_username, i.canonical_uri ) ASC, i.id ASC LIMIT %d OFFSET %d", $identities, $actors,
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
	/*
	 * `$where` is assembled here and carries its own placeholders; everything it
	 * names is bound. `$args` is built in statement order because prepare()
	 * binds positionally -- the address table sits between the third and fourth
	 * LIKE, so its argument does too.
	 */
	$where = "a.actor_type = 'Group' AND i.status = 'public' AND ( i.origin = 'remote' OR ( i.origin = 'local' AND EXISTS (SELECT 1 FROM {$wpdb->prefix}ax_actor_managers m WHERE m.identity_id = a.identity_id) AND a.handle_locked_at IS NOT NULL ) )";
	$args  = array( $identities, $actors );
	if ( '' !== $search ) {
		$like   = '%' . $wpdb->esc_like( ltrim( $search, '@' ) ) . '%';
		$where .= ' AND ( a.preferred_username LIKE %s OR a.display_name LIKE %s OR i.canonical_uri LIKE %s OR EXISTS ( SELECT 1 FROM %i ad WHERE ad.identity_id = i.id AND ad.address LIKE %s ) )';
		array_push( $args, $like, $like, $like, $addresses, $like );
	}
	$sql    = "SELECT i.*, a.* FROM %i i INNER JOIN %i a ON a.identity_id = i.id WHERE {$where} ORDER BY CASE WHEN i.origin = 'local' THEN 0 ELSE 1 END, COALESCE( NULLIF( a.display_name, '' ), a.preferred_username, i.canonical_uri ) ASC, i.id ASC LIMIT %d";
	$args[] = $limit;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- assembled clause; every table and value is prepared.
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
		$wpdb->prepare( "SELECT COUNT(*) FROM %i i INNER JOIN %i a ON a.identity_id = i.id
		 WHERE a.actor_type = 'Group' AND i.status = 'public'
		 AND ( i.origin = 'remote' OR ( i.origin = 'local' AND EXISTS (SELECT 1 FROM {$wpdb->prefix}ax_actor_managers m WHERE m.identity_id = a.identity_id) AND a.handle_locked_at IS NOT NULL ) )", $identities, $actors )
	);
}

/** Legacy management UI adapter: count only live direct WP User recovery anchors. */
function axismundi_actors_managed_owner_count( int $identity_id ) : int {
	return axismundi_actors_principal_owner_count( $identity_id, 'wp_user' );
}

/** Legacy WP User adapter. Creation uses seed_account_owner instead of this writer. */
function axismundi_actors_write_manager_row( int $identity_id, int $user_id, string $role ) {
	return axismundi_actors_set_principal_role( $identity_id, 'wp_user', $user_id, $role );
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
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->has_principals() ) {
		return new WP_Error( 'ax_actors_manager_target', __( 'Managers can only be assigned to a managed actor.', 'axismundi-actors' ) );
	}
	// Explicit WP-login adapter for the management UI, which is not migrated yet.
	return axismundi_actors_set_principal_role( $identity_id, 'wp_user', $user_id, $role );
}

/**
 * Remove a manager, enforcing the one-owner invariant (revoke = row delete).
 *
 * @return true|WP_Error
 */
function axismundi_actors_remove_manager( int $identity_id, int $user_id ) {
	// Explicit WP-login adapter; local_account owners cannot replace this anchor.
	return axismundi_actors_set_principal_role( $identity_id, 'wp_user', $user_id, null );
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
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || ! $actor->has_principals() || 'Group' !== $actor->get_type() ) {
		return new WP_Error( 'ax_actors_manager_target', __( 'Only a local managed Group may be claimed.', 'axismundi-actors' ) );
	}
	return axismundi_actors_set_principal_role( $identity_id, 'wp_user', $user_id, 'manager' );
}
