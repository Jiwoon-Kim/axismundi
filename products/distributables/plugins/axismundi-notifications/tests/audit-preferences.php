<?php
/**
 * What one person wants to be told about (dev-only; dist-excluded).
 *
 * A different question from acceptance, asked of a different party. Acceptance is about the sender
 * and belongs to the Actor written to; this is about attention and belongs to each person who reads
 * for that Actor -- so two managers of one Group can want different things without either overruling
 * the other.
 *
 * The order, most binding first, and every step of it is pinned below:
 *
 *   security and moderation      always allowed
 *   block and mute               already gone, dropped by acceptance
 *   an explicit setting per kind
 *   a setting per category
 *   a default for that Actor
 *   a global default
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_pf_results = array();
$ax_pf_users   = array();
$ax_pf_groups  = array();

/** @param bool[] $results Results. */
function ax_pf_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with an activated, published Person Actor. */
function ax_pf_user( array &$users ) : int {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axpf' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'editor' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), 'axpf' . strtolower( wp_generate_password( 8, false, false ) ) );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return $id;
}

/*
 * One run's own keys. A source key is an idempotency key, so a fixture reusing a literal one hands
 * back the Activity a previous run recorded -- whose Actor has since been deleted with its user, and
 * which is not the act this run is describing.
 */
$GLOBALS['ax_pf_run'] = wp_generate_uuid4();

/** One event addressed to an Actor, handed out to whoever wants it. */
function ax_pf_deliver( string $recipient_uri, string $sender_uri, string $kind, string $key ) : int {
	$key      = (string) $GLOBALS['ax_pf_run'] . ':' . $key;
	$activity = axismundi_act_record_source_activity(
		array( 'type' => 'Invite', 'actor' => $sender_uri, 'object' => home_url( '/?ax-pf=' . rawurlencode( $key ) ) ),
		'local',
		'ax-pf:' . $key
	);
	if ( ! $activity instanceof Axismundi_Activity ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
		printf( "  (fixture could not record the Activity: %s)\n", is_wp_error( $activity ) ? $activity->get_error_message() : 'unknown' );
		return 0;
	}
	$event = axismundi_ntf_record_event(
		array( 'kind' => $kind, 'recipient_actor_uri' => $recipient_uri, 'actor_uri' => $sender_uri, 'snapshot' => array( 'title' => 'Rehearsal' ) ),
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
		axismundi_ntf_register_kind( 'axismundi-calendar/event-joined', array( 'category' => 'calendar', 'urgency' => 'bundled' ) );
		axismundi_ntf_register_kind( 'axismundi-notifications/security-alert', array( 'category' => 'security', 'urgency' => 'immediate' ) );
	}
);
axismundi_ntf_register_kinds();

try {
	$ax_pf_alice_user = ax_pf_user( $ax_pf_users );
	$ax_pf_bob_user   = ax_pf_user( $ax_pf_users );
	$ax_pf_sender     = axismundi_actors_get_for_user( ax_pf_user( $ax_pf_users ) );
	$ax_pf_alice      = axismundi_actors_get_for_user( $ax_pf_alice_user );

	$ax_pf_org = axismundi_actors_create_managed_actor(
		array(
			'owner_user_id'      => $ax_pf_alice_user,
			'actor_type'         => 'Organization',
			'preferred_username' => 'axpf' . strtolower( wp_generate_password( 8, false, false ) ),
			'status'             => 'internal',
		)
	);
	$ax_pf_org_id   = $ax_pf_org instanceof Axismundi_Actor ? (int) $ax_pf_org->get_identity_id() : 0;
	$ax_pf_groups[] = $ax_pf_org_id;
	axismundi_actors_add_manager( $ax_pf_org_id, $ax_pf_bob_user, 'manager' );
	$ax_pf_org_uri = (string) axismundi_actors_get_by_identity( $ax_pf_org_id )->get_uri();

	// -- two people, one inbox, different wants ------------------------------------------------------------

	/*
	 * The reason preference belongs to the delivery rather than to the event. Alice does not want
	 * calendar notices for this Organization; Bob does, and the Organization's record is unaffected by
	 * either of them.
	 */
	axismundi_ntf_set_preference( $ax_pf_alice_user, 0, 'category', 'calendar', 'in_app', false );
	$ax_pf_event = ax_pf_deliver( $ax_pf_org_uri, (string) $ax_pf_sender->get_uri(), 'axismundi-calendar/event-invited', 'one' );
	ax_pf_assert(
		$ax_pf_results,
		'one manager turning a category off does not turn it off for the other',
		$ax_pf_event > 0
			&& 0 === axismundi_ntf_unread_count( $ax_pf_alice_user )
			&& 1 === axismundi_ntf_unread_count( $ax_pf_bob_user )
	);
	// The Actor's own record is untouched: what somebody wants to read is not what happened.
	ax_pf_assert(
		$ax_pf_results,
		'and the Organization\'s record of it stands whatever anybody wanted',
		1 === count( axismundi_ntf_events_for_actor( $ax_pf_org_id ) )
	);

	// -- the more specific answer wins -------------------------------------------------------------------

	// A kind said explicitly beats the category it belongs to.
	axismundi_ntf_set_preference( $ax_pf_alice_user, 0, 'kind', 'axismundi-calendar/event-invited', 'in_app', true );
	ax_pf_assert(
		$ax_pf_results,
		'a kind somebody named beats the category they switched off',
		axismundi_ntf_wants( $ax_pf_alice_user, $ax_pf_org_id, 'axismundi-calendar/event-invited', 'calendar' )
			&& ! axismundi_ntf_wants( $ax_pf_alice_user, $ax_pf_org_id, 'axismundi-calendar/event-joined', 'calendar' )
	);
	// And a setting made for one Actor beats the same setting made for all of them.
	axismundi_ntf_set_preference( $ax_pf_alice_user, $ax_pf_org_id, 'category', 'calendar', 'in_app', true );
	ax_pf_assert(
		$ax_pf_results,
		'and something said about one Actor beats what was said about all of them',
		axismundi_ntf_wants( $ax_pf_alice_user, $ax_pf_org_id, 'axismundi-calendar/event-joined', 'calendar' )
			&& ! axismundi_ntf_wants( $ax_pf_alice_user, (int) $ax_pf_alice->get_identity_id(), 'axismundi-calendar/event-joined', 'calendar' )
	);
	// Clearing it lets the one behind answer again, rather than meaning "no".
	axismundi_ntf_clear_preference( $ax_pf_alice_user, $ax_pf_org_id, 'category', 'calendar', 'in_app' );
	ax_pf_assert(
		$ax_pf_results,
		'clearing a setting hands the question back rather than answering it no',
		! axismundi_ntf_wants( $ax_pf_alice_user, $ax_pf_org_id, 'axismundi-calendar/event-joined', 'calendar' )
	);
	// Somebody who has said nothing at all still gets their notifications.
	ax_pf_assert(
		$ax_pf_results,
		'and somebody who has said nothing is shown everything, which is how they find out at all',
		axismundi_ntf_wants( $ax_pf_bob_user, $ax_pf_org_id, 'axismundi-calendar/event-joined', 'calendar' )
	);

	// -- what no preference reaches ------------------------------------------------------------------------

	/*
	 * A security warning nobody saw because a switch was off is the failure this layer exists to
	 * avoid producing, and a moderation notice somebody turned off is one they are not answering while
	 * believing there is nothing to answer.
	 */
	axismundi_ntf_set_preference( $ax_pf_alice_user, 0, 'category', 'security', 'in_app', false );
	$ax_pf_alert = ax_pf_deliver( (string) $ax_pf_alice->get_uri(), (string) $ax_pf_sender->get_uri(), 'axismundi-notifications/security-alert', 'two' );
	ax_pf_assert(
		$ax_pf_results,
		'a security notice arrives however emphatically it was switched off',
		$ax_pf_alert > 0
			&& axismundi_ntf_wants( $ax_pf_alice_user, (int) $ax_pf_alice->get_identity_id(), 'axismundi-notifications/security-alert', 'security' )
			&& 1 === axismundi_ntf_unread_count( $ax_pf_alice_user )
	);

	// -- settings are about what happens next ---------------------------------------------------------------

	/*
	 * The rule that keeps a filtered event where it is, applied to deliveries: turning something off
	 * does not take back what was already sent, and turning it on does not manufacture deliveries for
	 * the weeks it was off.
	 */
	$ax_pf_before = axismundi_ntf_unread_count( $ax_pf_bob_user );
	axismundi_ntf_set_preference( $ax_pf_bob_user, 0, 'category', 'calendar', 'in_app', false );
	ax_pf_assert(
		$ax_pf_results,
		'turning a category off leaves what was already delivered exactly where it is',
		$ax_pf_before === axismundi_ntf_unread_count( $ax_pf_bob_user )
	);
	$ax_pf_missed = ax_pf_deliver( $ax_pf_org_uri, (string) $ax_pf_sender->get_uri(), 'axismundi-calendar/event-joined', 'three' );
	ax_pf_assert(
		$ax_pf_results,
		'while what arrives after does not reach them',
		$ax_pf_missed > 0 && $ax_pf_before === axismundi_ntf_unread_count( $ax_pf_bob_user )
	);
	// Nor does it appear in their list as history: it was never theirs to be shown.
	ax_pf_assert(
		$ax_pf_results,
		'and does not appear in their list as something that happened while they were not looking',
		! in_array(
			$ax_pf_missed,
			array_map( static fn( array $row ) : int => (int) $row['id'], axismundi_ntf_inbox( $ax_pf_bob_user, 20 ) ),
			true
		)
	);
	axismundi_ntf_set_preference( $ax_pf_bob_user, 0, 'category', 'calendar', 'in_app', true );
	ax_pf_assert(
		$ax_pf_results,
		'and turning it back on does not manufacture the weeks it was off',
		$ax_pf_before === axismundi_ntf_unread_count( $ax_pf_bob_user )
	);
} finally {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . axismundi_ntf_events_table() . ' WHERE kind LIKE %s OR kind LIKE %s', 'axismundi-calendar/%', 'axismundi-notifications/%' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( 'DELETE d FROM ' . axismundi_ntf_deliveries_table() . ' d LEFT JOIN ' . axismundi_ntf_events_table() . ' e ON e.id = d.notification_id WHERE e.id IS NULL' );
	foreach ( array_unique( $ax_pf_users ) as $ax_pf_user_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_ntf_preferences_table(), array( 'local_user_id' => (int) $ax_pf_user_id ), array( '%d' ) );
		wp_delete_user( (int) $ax_pf_user_id );
	}
	foreach ( array_unique( $ax_pf_groups ) as $ax_pf_group_id ) {
		if ( $ax_pf_group_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned identity cleanup.
			$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $ax_pf_group_id ), array( '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned identity cleanup.
			$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $ax_pf_group_id ), array( '%d' ) );
		}
	}
}

$ax_pf_failures = count( array_filter( $ax_pf_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_pf_results ), $ax_pf_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_pf_failures > 0 ? 1 : 0 );
}
exit( $ax_pf_failures > 0 ? 1 : 0 );
