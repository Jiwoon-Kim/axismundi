<?php
/** Read-only deterministic direct User verdict snapshot: wp eval-file. @package AxismundiActors */
defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$rows = $wpdb->get_results( $wpdb->prepare(
	'SELECT identity_id, principal_id FROM %i WHERE principal_kind = %s',
	axismundi_actors_managers_table(), 'wp_user'
), ARRAY_A );
if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
	throw new RuntimeException( 'Could not snapshot direct User relations.' );
}
$lines = array();
foreach ( $rows as $row ) {
	$account_id = (int) $row['identity_id'];
	$user_id = (int) $row['principal_id'];
	$actor = axismundi_actors_get_by_identity( $account_id );
	$valid = $actor instanceof Axismundi_Actor;
	$lines[] = sprintf(
		'%d|%d|role=%s|access=%d|actas=%d|personal=%d|manage=%d',
		$account_id, $user_id, axismundi_actors_account_role( $account_id, $user_id ),
		$valid && axismundi_actors_can_access_workspace( $actor, $user_id ),
		$valid && axismundi_actors_can_act_as( $actor, $user_id ),
		$valid && axismundi_actors_account_is_personal( $actor, $user_id ),
		axismundi_actors_managed_actor_can_manage( $account_id, $user_id )
	);
}
sort( $lines, SORT_STRING );
echo implode( "\n", $lines ) . ( $lines ? "\n" : '' );
