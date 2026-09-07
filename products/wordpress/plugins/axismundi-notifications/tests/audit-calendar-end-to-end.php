<?php
/**
 * A calendar act arriving in somebody's inbox (dev-only; dist-excluded).
 *
 * The two halves have been audited apart: Calendar answers what an Activity meant, and Notifications
 * stores and hands out what it is told. This is the seam between them, which neither audit can see
 * on its own -- and the failure it exists to catch is the quiet one, where both halves are correct
 * and nothing joins them.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_e2e_results = array();
$ax_e2e_users   = array();
$ax_e2e_posts   = array();

/** @param bool[] $results Results. */
function ax_e2e_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with an activated, published Person Actor. */
function ax_e2e_user( array &$users ) : int {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axe2e' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), 'axe2e' . strtolower( wp_generate_password( 8, false, false ) ) );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return $id;
}

try {
	$ax_e2e_host_user  = ax_e2e_user( $ax_e2e_users );
	$ax_e2e_guest_user = ax_e2e_user( $ax_e2e_users );
	wp_set_current_user( $ax_e2e_host_user );
	$ax_e2e_host  = axismundi_actors_get_for_user( $ax_e2e_host_user );
	$ax_e2e_guest = axismundi_actors_get_for_user( $ax_e2e_guest_user );
	$ax_e2e_cal   = (int) axismundi_cal_primary_calendar( (string) $ax_e2e_host->get_uri() )['id'];
	axismundi_cal_acl_grant( $ax_e2e_cal, '', 'reader', 'public' );

	$ax_e2e_event = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_title' => 'Dress rehearsal', 'post_status' => 'draft', 'post_author' => $ax_e2e_host_user )
	);
	$ax_e2e_posts[] = $ax_e2e_event;
	axismundi_cal_event_save(
		$ax_e2e_event,
		array(
			'calendar_id' => $ax_e2e_cal,
			'starts_at'   => gmdate( 'Y-m-d H:i:s', strtotime( '+12 days' ) ),
			'ends_at'     => gmdate( 'Y-m-d H:i:s', strtotime( '+12 days +2 hours' ) ),
			'timezone'    => 'Asia/Seoul',
			'join_mode'   => 'restricted',
		)
	);
	$GLOBALS['axismundi_cal_rest_write'] = true;
	wp_update_post( array( 'ID' => $ax_e2e_event, 'post_status' => 'publish' ) );
	$GLOBALS['axismundi_cal_rest_write'] = false;

	$ax_e2e_before = axismundi_ntf_unread_count( $ax_e2e_guest_user );
	axismundi_cal_event_invite( $ax_e2e_event, (string) $ax_e2e_guest->get_uri() );
	$ax_e2e_inbox = axismundi_ntf_inbox( $ax_e2e_guest_user, 5 );
	ax_e2e_assert(
		$ax_e2e_results,
		'inviting somebody puts it in their inbox, by the end of the command and not the request',
		$ax_e2e_before + 1 === axismundi_ntf_unread_count( $ax_e2e_guest_user )
			&& array() !== $ax_e2e_inbox
			&& 'axismundi-calendar/event-invited' === (string) $ax_e2e_inbox[0]['kind']
			&& 'Dress rehearsal' === (string) json_decode( (string) $ax_e2e_inbox[0]['snapshot'], true )['title']
	);
	// The host performed it, so it is not news to them however many Actors they hold.
	ax_e2e_assert(
		$ax_e2e_results,
		'and the organizer is not told about the invitation they just sent',
		0 === axismundi_ntf_unread_count( $ax_e2e_host_user )
	);
	// The answer travels the other way, and the guest performed that one.
	wp_set_current_user( $ax_e2e_guest_user );
	axismundi_cal_event_respond_to_invite( $ax_e2e_event, (string) $ax_e2e_guest->get_uri(), 'accept' );
	ax_e2e_assert(
		$ax_e2e_results,
		'answering it reaches the organizer and does not come back to the guest',
		1 === axismundi_ntf_unread_count( $ax_e2e_host_user )
			&& 1 === axismundi_ntf_unread_count( $ax_e2e_guest_user )
	);
	/*
	 * Two Activities from one command, and the flush between them is why both arrive. Resolving only
	 * at the end would have given the retraction the state of the answer that replaced it.
	 */
	axismundi_cal_event_respond_to_invite( $ax_e2e_event, (string) $ax_e2e_guest->get_uri(), 'reject' );
	$ax_e2e_host_kinds = array_map(
		static fn( array $row ) : string => (string) $row['kind'],
		axismundi_ntf_inbox( $ax_e2e_host_user, 10 )
	);
	ax_e2e_assert(
		$ax_e2e_results,
		'changing an answer delivers both the retraction and the new answer, each in its own right',
		in_array( 'axismundi-calendar/event-invite-answer-undone', $ax_e2e_host_kinds, true )
			&& 2 === count( array_filter( $ax_e2e_host_kinds, static fn( string $k ) : bool => 'axismundi-calendar/event-invite-answered' === $k ) )
	);
	// And the Event being called off reaches whoever still holds it.
	wp_set_current_user( $ax_e2e_host_user );
	axismundi_cal_event_cancel( $ax_e2e_event );
	ax_e2e_assert(
		$ax_e2e_results,
		'calling it off reaches the guest, who had said no and still kept the evening in view',
		in_array(
			'axismundi-calendar/event-cancelled',
			array_map( static fn( array $row ) : string => (string) $row['kind'], axismundi_ntf_inbox( $ax_e2e_guest_user, 10 ) ),
			true
		)
	);
} finally {
	wp_set_current_user( 0 );
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . axismundi_ntf_events_table() . ' WHERE kind LIKE %s', 'axismundi-calendar/%' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( 'DELETE d FROM ' . axismundi_ntf_deliveries_table() . ' d LEFT JOIN ' . axismundi_ntf_events_table() . ' e ON e.id = d.notification_id WHERE e.id IS NULL' );
	foreach ( array_unique( $ax_e2e_posts ) as $ax_e2e_post_id ) {
		wp_delete_post( (int) $ax_e2e_post_id, true );
	}
	foreach ( array_unique( $ax_e2e_users ) as $ax_e2e_user_id ) {
		wp_delete_user( (int) $ax_e2e_user_id );
	}
}

$ax_e2e_failures = count( array_filter( $ax_e2e_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_e2e_results ), $ax_e2e_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_e2e_failures > 0 ? 1 : 0 );
}
exit( $ax_e2e_failures > 0 ? 1 : 0 );
