<?php
/** Local Actor to Account identity binding. @package AxismundiJmap */

namespace Axismundi\Jmap;

defined( 'ABSPATH' ) || exit;

/** Account identity lookup only; data-type rights still belong to each provider. */
function resolve_account_actor( string $account_id, int $user_id ) : ?\Axismundi_Actor {
	if ( $user_id <= 0 || ! function_exists( 'axismundi_actors_resolve_workspace' )
		|| ! preg_match( '/\Aa([1-9][0-9]*)\z/D', $account_id, $match ) ) {
		return null;
	}
	$id = (int) $match[1];
	if ( (string) $id !== $match[1] ) {
		return null;
	}
	return axismundi_actors_resolve_workspace( $id, $user_id );
}

/** No handle-lock requirement: reading account data is not publishing an activity. */
function account_actors( int $user_id ) : array {
	if ( $user_id <= 0 || ! function_exists( 'axismundi_actors_workspace_options' ) ) {
		return array();
	}
	$result = array();
	foreach ( axismundi_actors_workspace_options( $user_id ) as $identity_id => $actor ) {
		$result[ 'a' . $identity_id ] = $actor;
	}
	return $result;
}
