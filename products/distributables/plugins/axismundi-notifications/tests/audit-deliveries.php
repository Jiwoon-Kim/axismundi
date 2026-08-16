<?php
/**
 * Whose attention it is (dev-only; dist-excluded).
 *
 * The event says an Organization was told something. This layer says which people have to deal with
 * it, and holds each of their own read state -- two managers of one Group reading the same notice
 * are two acts of attention, and one of them clearing their badge must not clear the other's.
 *
 * Two rules pull opposite ways here, and both are pinned: fan-out is a snapshot of the managers at
 * that moment, while access is re-asked on every read. Somebody made a manager today can read the
 * Actor's history without inheriting its unread count, and somebody removed today cannot read
 * anything, rows or no rows.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_dv_results = array();
$ax_dv_users   = array();
$ax_dv_groups  = array();

/** @param bool[] $results Results. */
function ax_dv_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with an activated, published Person Actor. */
function ax_dv_user( array &$users ) : int {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axdv' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'editor' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), 'axdv' . strtolower( wp_generate_password( 8, false, false ) ) );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return $id;
}

/** One Activity, recorded the way a domain records one. */
function ax_dv_record( string $type, string $actor_uri, string $object_uri, string $key ) : ?Axismundi_Activity {
	$activity = axismundi_act_record_source_activity(
		array( 'type' => $type, 'actor' => $actor_uri, 'object' => $object_uri ),
		'local',
		$key
	);
	return $activity instanceof Axismundi_Activity ? $activity : null;
}

/** One event addressed to an Actor, handed out to whoever must deal with it. */
function ax_dv_deliver( Axismundi_Activity $activity, string $recipient_uri, int $initiator, string $kind = 'axismundi-calendar/event-invited' ) : int {
	$event = axismundi_ntf_record_event(
		array(
			'kind'                     => $kind,
			'recipient_actor_uri'      => $recipient_uri,
			'initiating_local_user_id' => $initiator,
			'snapshot'                 => array( 'title' => 'Rehearsal' ),
		),
		$activity
	);
	if ( is_wp_error( $event ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
		printf( "  (fixture refused: %s)\n", $event->get_error_message() );
		return 0;
	}
	axismundi_ntf_fan_out( (int) $event );
	return (int) $event;
}

add_action(
	'axismundi_notification_register_kinds',
	static function () : void {
		axismundi_ntf_register_kind( 'axismundi-calendar/event-invited', array( 'category' => 'calendar', 'urgency' => 'immediate' ) );
		axismundi_ntf_register_kind( 'axismundi-calendar/event-cancelled', array( 'category' => 'calendar', 'urgency' => 'immediate' ) );
	}
);
axismundi_ntf_register_kinds();

try {
	$ax_dv_alice = ax_dv_user( $ax_dv_users );
	$ax_dv_bob   = ax_dv_user( $ax_dv_users );
	$ax_dv_carol = ax_dv_user( $ax_dv_users );
	$ax_dv_outsider = ax_dv_user( $ax_dv_users );

	// An Organization the first two run together.
	$ax_dv_org = axismundi_actors_create_managed_actor(
		array(
			'owner_user_id'      => $ax_dv_alice,
			'actor_type'         => 'Organization',
			'preferred_username' => 'axdv' . strtolower( wp_generate_password( 8, false, false ) ),
			'status'             => 'internal',
		)
	);
	$ax_dv_org_id = $ax_dv_org instanceof Axismundi_Actor ? (int) $ax_dv_org->get_identity_id() : 0;
	$ax_dv_groups[] = $ax_dv_org_id;
	axismundi_actors_add_manager( $ax_dv_org_id, $ax_dv_bob, 'manager' );
	$ax_dv_org_uri = (string) axismundi_actors_get_by_identity( $ax_dv_org_id )->get_uri();
	$ax_dv_object  = home_url( '/?ax-dv=' . wp_generate_uuid4() );

	// -- an act performed as the Organization ------------------------------------------------------------

	/*
	 * The case the whole design turns on. Alice invites the Organization to something *as* the
	 * Organization, so the Activity's actor and its recipient are the same Actor -- and the notice is
	 * for Bob, who was not at the keyboard.
	 */
	$ax_dv_activity = ax_dv_record( 'Invite', $ax_dv_org_uri, $ax_dv_object, 'ax-dv-invite:' . $ax_dv_object );
	$ax_dv_event    = ax_dv_deliver( $ax_dv_activity, $ax_dv_org_uri, $ax_dv_alice );
	ax_dv_assert(
		$ax_dv_results,
		'a notice to an Organization reaches its other managers',
		$ax_dv_event > 0 && 1 === axismundi_ntf_unread_count( $ax_dv_bob )
	);
	ax_dv_assert(
		$ax_dv_results,
		'and not the manager who performed it, which is what "your own act" actually means',
		0 === axismundi_ntf_unread_count( $ax_dv_alice )
	);
	// Alice can still read it: the Organization was told, and she is one of the people responsible.
	ax_dv_assert(
		$ax_dv_results,
		'though she can still see it, since it is the Organization\'s news and she runs it too',
		1 === count( axismundi_ntf_inbox( $ax_dv_alice ) )
	);
	// Nobody outside the Organization sees it at all.
	ax_dv_assert(
		$ax_dv_results,
		'while somebody who does not run it sees nothing',
		array() === axismundi_ntf_inbox( $ax_dv_outsider )
	);

	// -- one person reading does not clear another's -----------------------------------------------------

	$ax_dv_second = ax_dv_deliver(
		ax_dv_record( 'Update', $ax_dv_org_uri, $ax_dv_object, 'ax-dv-update:' . $ax_dv_object ),
		$ax_dv_org_uri,
		0,
		'axismundi-calendar/event-cancelled'
	);
	ax_dv_assert(
		$ax_dv_results,
		'an act nobody local performed reaches every manager, including the one at the keyboard',
		$ax_dv_second > 0 && 1 === axismundi_ntf_unread_count( $ax_dv_alice ) && 2 === axismundi_ntf_unread_count( $ax_dv_bob )
	);
	axismundi_ntf_mark_read( $ax_dv_second, $ax_dv_alice );
	ax_dv_assert(
		$ax_dv_results,
		'and one of them reading it leaves the other\'s badge exactly where it was',
		0 === axismundi_ntf_unread_count( $ax_dv_alice ) && 2 === axismundi_ntf_unread_count( $ax_dv_bob )
	);

	// -- somebody who arrives later ------------------------------------------------------------------------

	/*
	 * Carol is made a manager now. She becomes responsible for the Organization, which includes what
	 * it was told before she arrived -- but she is not handed months of unread notices about a period
	 * she was not there for. That is what an inbox becomes something to clear rather than read.
	 */
	axismundi_actors_add_manager( $ax_dv_org_id, $ax_dv_carol, 'manager' );
	ax_dv_assert(
		$ax_dv_results,
		'a new manager can read what the Organization was told before they arrived',
		2 === count( axismundi_ntf_inbox( $ax_dv_carol ) )
	);
	ax_dv_assert(
		$ax_dv_results,
		'and inherits none of it as unread, having not been there',
		0 === axismundi_ntf_unread_count( $ax_dv_carol )
	);
	// Reading one of them is still hers to do, and creates the delivery that was never made.
	ax_dv_assert(
		$ax_dv_results,
		'though marking one read is hers to do, which is what makes it stop being new to her',
		true === axismundi_ntf_mark_read( $ax_dv_event, $ax_dv_carol )
			&& 0 === axismundi_ntf_unread_count( $ax_dv_carol )
	);
	// And what arrives after she joins is hers like anybody's.
	$ax_dv_third = ax_dv_deliver(
		ax_dv_record( 'Invite', $ax_dv_org_uri, $ax_dv_object . '&again=1', 'ax-dv-invite2:' . $ax_dv_object ),
		$ax_dv_org_uri,
		$ax_dv_alice
	);
	ax_dv_assert(
		$ax_dv_results,
		'while anything sent after she joined arrives for her like anybody else',
		$ax_dv_third > 0 && 1 === axismundi_ntf_unread_count( $ax_dv_carol ) && 0 === axismundi_ntf_unread_count( $ax_dv_alice )
	);

	// -- somebody who leaves --------------------------------------------------------------------------------

	/*
	 * Access is re-asked on every read rather than trusted from the rows, so removing a manager ends
	 * their reading immediately -- their delivery rows are still there and say what was delivered,
	 * which is not the same as what may now be read.
	 */
	axismundi_actors_remove_manager( $ax_dv_org_id, $ax_dv_bob );
	ax_dv_assert(
		$ax_dv_results,
		'somebody removed as a manager stops being able to read it, rows or no rows',
		array() === axismundi_ntf_inbox( $ax_dv_bob ) && 0 === axismundi_ntf_unread_count( $ax_dv_bob )
	);
	$ax_dv_denied = axismundi_ntf_mark_read( $ax_dv_third, $ax_dv_bob );
	ax_dv_assert(
		$ax_dv_results,
		'and cannot mark one read either, the check being asked of the model rather than the screen',
		is_wp_error( $ax_dv_denied ) && 'ax_ntf_forbidden' === $ax_dv_denied->get_error_code()
	);

	// -- a person's own Actor -------------------------------------------------------------------------------

	$ax_dv_alice_actor = axismundi_actors_get_for_user( $ax_dv_alice );
	$ax_dv_personal    = ax_dv_deliver(
		ax_dv_record( 'Invite', (string) axismundi_actors_get_for_user( $ax_dv_outsider )->get_uri(), $ax_dv_object . '&p=1', 'ax-dv-personal:' . $ax_dv_object ),
		(string) $ax_dv_alice_actor->get_uri(),
		$ax_dv_outsider
	);
	ax_dv_assert(
		$ax_dv_results,
		'a notice to a person reaches that person',
		$ax_dv_personal > 0 && 1 === axismundi_ntf_unread_count( $ax_dv_alice )
	);
	/*
	 * And nobody else, including an administrator. Being able to administer somebody's identity --
	 * rename it, fix its handle -- is not being entitled to read the invitations and mentions they
	 * received, so this gate is deliberately narrower than the one Actors uses for management.
	 */
	$ax_dv_admin = ax_dv_user( $ax_dv_users );
	( new WP_User( $ax_dv_admin ) )->set_role( 'administrator' );
	ax_dv_assert(
		$ax_dv_results,
		'and an administrator does not read it, administering an identity not being reading its post',
		! axismundi_ntf_can_read_inbox( (int) $ax_dv_alice_actor->get_identity_id(), $ax_dv_admin )
			&& array() === axismundi_ntf_inbox( $ax_dv_admin )
	);
} finally {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . axismundi_ntf_events_table() . ' WHERE kind LIKE %s', 'axismundi-calendar/%' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( 'DELETE d FROM ' . axismundi_ntf_deliveries_table() . ' d LEFT JOIN ' . axismundi_ntf_events_table() . ' e ON e.id = d.notification_id WHERE e.id IS NULL' );
	foreach ( array_unique( $ax_dv_groups ) as $ax_dv_group_id ) {
		if ( $ax_dv_group_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned identity cleanup.
			$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $ax_dv_group_id ), array( '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned identity cleanup.
			$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $ax_dv_group_id ), array( '%d' ) );
		}
	}
	foreach ( array_unique( $ax_dv_users ) as $ax_dv_user_id ) {
		wp_delete_user( (int) $ax_dv_user_id );
	}
}

$ax_dv_failures = count( array_filter( $ax_dv_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_dv_results ), $ax_dv_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_dv_failures > 0 ? 1 : 0 );
}
exit( $ax_dv_failures > 0 ? 1 : 0 );
