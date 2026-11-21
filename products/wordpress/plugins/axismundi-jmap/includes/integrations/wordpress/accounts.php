<?php
/** Local Actor to Account identity binding. @package AxismundiJmap */

namespace Axismundi\Jmap;

defined( 'ABSPATH' ) || exit;

/** Stored Account identity only. Does not provision, select, or grant access. */
function account_actor( string $account_id ) : ?\Axismundi_Actor {
	if ( ! function_exists( 'axismundi_actors_get_by_identity' ) || '1' !== get_option( 'ax_jmap_db_version' )
		|| ! preg_match( '/\Aa([1-9][0-9]*)\z/D', $account_id, $match )
		|| (string) (int) $match[1] !== $match[1] ) { return null; }
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT actor_id FROM %i WHERE actor_id=%d', accounts_table(), (int) $match[1] ) );
	$actor = $id ? axismundi_actors_get_by_identity( $id ) : null;
	return $actor instanceof \Axismundi_Actor && $actor->is_local() ? $actor : null;
}

/**
 * Human label for an Account or its Principal: the Actor's verified primary acct
 * address (`alice@example.com`, no `acct:` scheme), else its display name.
 *
 * RFC 8620 §2 gives an email address as the example Account name, so a full handle
 * fits the field, and generic clients that ignore `actorUri` still see a label two
 * Actors with the same display name do not share. It stays a label: clients key on
 * accountId and link to the Actor through `actorUri`, never through this string.
 */
function account_label( \Axismundi_Actor $actor ) : string {
	$acct = function_exists( 'axismundi_actors_primary_acct_address' ) ? axismundi_actors_primary_acct_address( $actor ) : '';
	return '' !== $acct ? $acct : $actor->get_display_name();
}

function account_id_for_actor( int $actor_id ) : ?string {
	$id = 'a' . $actor_id;
	return account_actor( $id ) ? $id : null;
}

/** Account identity lookup only; data-type rights still belong to each provider. */
function resolve_account_actor( string $account_id, int $user_id ) : ?\Axismundi_Actor {
	if ( $user_id <= 0 || ! function_exists( 'axismundi_actors_resolve_workspace' ) || ! account_actor( $account_id )
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
		if ( account_actor( 'a' . $identity_id ) ) { $result[ 'a' . $identity_id ] = $actor; }
	}
	return $result;
}
