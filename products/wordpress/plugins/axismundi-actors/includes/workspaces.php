<?php
/**
 * Protocol-neutral access to local operating identities.
 *
 * Resolving a workspace grants neither publication nor domain-specific data rights.
 * JMAP may project these identities as Accounts, but Actors never depends on JMAP.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/** May a WP user access this local operating identity? No administrator override. */
function axismundi_actors_can_access_workspace( Axismundi_Actor $actor, int $user_id ) : bool {
	if ( $user_id <= 0 || ! $actor->is_local()
		|| in_array( $actor->get_status(), array( 'disabled', 'tombstone' ), true ) ) {
		return false;
	}
	if ( $actor->is_managed() ) {
		return axismundi_actors_managed_actor_can_manage( $actor->get_identity_id(), $user_id );
	}
	return 'user' === $actor->get_scope() && (int) $actor->get_local_user_id() === $user_id;
}

/** Resolve an identity with current authority; callers must still check domain rights. */
function axismundi_actors_resolve_workspace( int $identity_id, int $user_id ) : ?Axismundi_Actor {
	if ( $identity_id <= 0 || $user_id <= 0 ) {
		return null;
	}
	$actor = axismundi_actors_get_by_identity( $identity_id );
	return $actor instanceof Axismundi_Actor && axismundi_actors_can_access_workspace( $actor, $user_id ) ? $actor : null;
}

/** Accessible identities, own user Actor first, keyed by internal identity ID. */
function axismundi_actors_workspace_options( int $user_id ) : array {
	if ( $user_id <= 0 ) {
		return array();
	}
	$candidates = array_merge( array( axismundi_actors_get_for_user( $user_id ) ), axismundi_actors_list_manageable_actors( $user_id ) );
	$options = array();
	foreach ( $candidates as $actor ) {
		if ( $actor instanceof Axismundi_Actor && axismundi_actors_can_access_workspace( $actor, $user_id ) ) {
			$options[ $actor->get_identity_id() ] = $actor;
		}
	}
	return $options;
}
