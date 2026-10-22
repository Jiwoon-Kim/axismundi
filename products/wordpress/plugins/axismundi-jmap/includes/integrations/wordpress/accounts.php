<?php
/** Local Actor to Account identity binding. @package AxismundiJmap */

namespace Axismundi\Jmap;

defined( 'ABSPATH' ) || exit;

/** Account identity lookup only; data-type rights still belong to each provider. */
function resolve_account_actor( string $account_id, int $user_id ) : ?\Axismundi_Actor {
	if ( $user_id <= 0 || ! function_exists( 'axismundi_actors_get_by_identity' )
		|| ! function_exists( 'axismundi_actors_managed_actor_can_manage' )
		|| ! preg_match( '/\Aa([1-9][0-9]*)\z/D', $account_id, $match ) ) {
		return null;
	}
	$id = (int) $match[1];
	if ( (string) $id !== $match[1] ) {
		return null;
	}
	$actor = axismundi_actors_get_by_identity( $id );
	if ( ! $actor instanceof \Axismundi_Actor || ! $actor->is_local()
		|| in_array( $actor->get_status(), array( 'disabled', 'tombstone' ), true ) ) {
		return null;
	}
	if ( $actor->is_managed() ) {
		return axismundi_actors_managed_actor_can_manage( $id, $user_id ) ? $actor : null;
	}
	return 'Person' === $actor->get_type() && 'user' === $actor->get_scope()
		&& $user_id === $actor->get_local_user_id() ? $actor : null;
}

/** No handle-lock requirement: reading account data is not publishing an activity. */
function account_actors( int $user_id ) : array {
	if ( $user_id <= 0 || ! function_exists( 'axismundi_actors_get_for_user' )
		|| ! function_exists( 'axismundi_actors_list_manageable_actors' ) ) {
		return array();
	}
	$candidates = array_merge( array( axismundi_actors_get_for_user( $user_id ) ), axismundi_actors_list_manageable_actors( $user_id ) );
	$result = array();
	foreach ( $candidates as $actor ) {
		if ( ! $actor instanceof \Axismundi_Actor ) {
			continue;
		}
		$id = 'a' . $actor->get_identity_id();
		if ( null !== resolve_account_actor( $id, $user_id ) ) {
			$result[ $id ] = $actor;
		}
	}
	return $result;
}
