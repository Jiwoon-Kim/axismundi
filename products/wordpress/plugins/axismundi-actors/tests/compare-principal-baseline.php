<?php
/** Read-only owner-baseline comparison. Pipe the baseline into wp eval-file. @package AxismundiActors */
defined( 'ABSPATH' ) || exit( 1 );

$pair_count = 0;
$list_count = 0;
$pair_deltas = 0;
$list_deltas = 0;
$unrecognized = 0;
while ( false !== ( $line = fgets( STDIN ) ) ) {
	$line = trim( $line );
	if ( preg_match( '/^(\d+)\|(\d+)\|role=/', $line, $match ) ) {
		++$pair_count;
		$account_id = (int) $match[1];
		$user_id = (int) $match[2];
		$actor = axismundi_actors_get_by_identity( $account_id );
		$valid = $actor instanceof Axismundi_Actor;
		$current = sprintf( '%d|%d|role=%s|access=%d|actas=%d|personal=%d|manage=%d',
			$account_id, $user_id, axismundi_actors_account_role( $account_id, $user_id ),
			$valid && axismundi_actors_can_access_workspace( $actor, $user_id ),
			$valid && axismundi_actors_can_act_as( $actor, $user_id ),
			$valid && axismundi_actors_account_is_personal( $actor, $user_id ),
			$valid && $actor->is_managed() && axismundi_actors_principal_can_manage( $account_id, 'wp_user', $user_id )
		);
		if ( $current !== $line ) {
			++$pair_deltas;
			echo "PAIR BEFORE={$line} AFTER={$current}\n";
		}
	} elseif ( preg_match( '/^user (\d+) accounts=(\[[^\]]*\]) manageable=(\[[^\]]*\])$/', $line, $match ) ) {
		++$list_count;
		$user_id = (int) $match[1];
		$accounts = array_keys( axismundi_actors_accounts_for_user( $user_id ) );
		// Reproduce the legacy UI projection without treating it as canonical principal policy.
		$manageable = array_values( array_map( static fn( Axismundi_Actor $a ) : int => $a->get_identity_id(),
			array_filter( axismundi_actors_accounts_for_principal( 'wp_user', $user_id ), static fn( Axismundi_Actor $a ) : bool => $a->is_managed() )
		) );
		$current = 'user ' . $user_id . ' accounts=' . wp_json_encode( $accounts ) . ' manageable=' . wp_json_encode( $manageable );
		if ( $current !== $line ) {
			++$list_deltas;
			echo "LIST BEFORE={$line} AFTER={$current}\n";
		}
	} elseif ( '' !== $line ) {
		++$unrecognized;
	}
}
printf( "Compared %d pair verdicts (%d deltas), %d User list lines (%d deltas); %d unrecognized nonempty lines.\n", $pair_count, $pair_deltas, $list_count, $list_deltas, $unrecognized );
if ( 0 === $pair_count || 0 === $list_count || $unrecognized > 0 || $pair_deltas > 0 ) {
	throw new RuntimeException( 'Incomplete baseline comparison or changed pair verdicts.' );
}
