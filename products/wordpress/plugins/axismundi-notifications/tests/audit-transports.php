<?php
/**
 * Whether a switch actually sends anything (dev-only; dist-excluded).
 *
 * Written against a specific failure seen elsewhere: a settings screen that saves email preferences
 * whose send functions are still commented out. A preference that exists is not a delivery that
 * happens, so every check here ends at `wp_mail` -- captured, and asserted on its recipient and its
 * contents rather than on the switch being on.
 *
 * The other half is what must not be sent: to an address nobody confirmed, to somebody who is
 * sitting in the app, or about something they have already read.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_tr_results = array();
$ax_tr_users   = array();
$ax_tr_groups  = array();
$GLOBALS['ax_tr_mail'] = array();
$GLOBALS['ax_tr_run']  = wp_generate_uuid4();

/** @param bool[] $results Results. */
function ax_tr_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

// Every message this run would send, caught at the boundary rather than actually posted.
add_filter(
	'pre_wp_mail',
	static function ( $short_circuit, array $args ) {
		$GLOBALS['ax_tr_mail'][] = $args;
		return true;
	},
	10,
	2
);

/** An account with an activated, published Person Actor. */
function ax_tr_user( array &$users ) : int {
	$login = 'axtr' . strtolower( wp_generate_password( 8, false, false ) );
	// With an address, because a real account has one -- WordPress requires it, and it is the default
	// notification destination this file is about.
	$id = (int) wp_insert_user(
		array( 'user_login' => $login, 'user_email' => $login . '@example.test', 'user_pass' => wp_generate_password(), 'role' => 'editor' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), 'axtr' . strtolower( wp_generate_password( 8, false, false ) ) );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return $id;
}

/** One delivered notification, and the delivery it produced. */
function ax_tr_deliver( string $recipient_uri, string $sender_uri, string $key ) : int {
	$key      = (string) $GLOBALS['ax_tr_run'] . ':' . $key;
	$activity = axismundi_act_record_source_activity(
		array( 'type' => 'Invite', 'actor' => $sender_uri, 'object' => home_url( '/?ax-tr=' . rawurlencode( $key ) ) ),
		'local',
		'ax-tr:' . $key
	);
	if ( ! $activity instanceof Axismundi_Activity ) {
		return 0;
	}
	$event = axismundi_ntf_record_event(
		array( 'kind' => 'axismundi-calendar/event-invited', 'recipient_actor_uri' => $recipient_uri, 'actor_uri' => $sender_uri, 'snapshot' => array( 'title' => 'Dress rehearsal' ) ),
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

/**
 * Let the wait elapse.
 *
 * An attempt is scheduled a few minutes out on purpose, so that "are they still here" can be asked
 * later than the moment the notification was made. A fixture running in one second has to stand in
 * for that passing time; nothing else about the worker is changed.
 *
 * Just past due, not years past. The worker gives up on an attempt that has waited a whole day for
 * somebody to stop reading, so a fixture clock set to 2020 would make every wait look abandoned and
 * quietly test the wrong branch.
 */
function ax_tr_time_passes() : void {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture clock.
	$wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from this plugin.
			'UPDATE ' . axismundi_ntf_attempts_table() . " SET scheduled_at = %s WHERE state IN ( 'queued', 'retryable' )",
			gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS )
		)
	);
}

/** Messages sent to one address in this run. */
function ax_tr_mail_to( string $address ) : array {
	return array_values(
		array_filter(
			(array) $GLOBALS['ax_tr_mail'],
			static fn( array $mail ) : bool => in_array( $address, (array) $mail['to'], true ) || $address === $mail['to']
		)
	);
}

add_action(
	'axismundi_notification_register_kinds',
	static function () : void {
		axismundi_ntf_register_kind( 'axismundi-calendar/event-invited', array( 'category' => 'calendar', 'urgency' => 'immediate' ) );
	}
);
axismundi_ntf_register_kinds();

try {
	$ax_tr_reader_user = ax_tr_user( $ax_tr_users );
	$ax_tr_reader      = axismundi_actors_get_for_user( $ax_tr_reader_user );
	$ax_tr_sender      = axismundi_actors_get_for_user( ax_tr_user( $ax_tr_users ) );
	$ax_tr_address     = 'axtr' . strtolower( wp_generate_password( 8, false, false ) ) . '@example.test';

	// -- the account address is the default destination --------------------------------------------------------

	/*
	 * WordPress already holds this address, already verifies changes to it, and already writes to it
	 * privately. Asking somebody to confirm the address they sign in with would be ceremony over a
	 * permission they have given, and a second copy of something WordPress owns. What is *not* implied
	 * is publication: this is where the site writes to an account, never the Actor's public contact.
	 */
	$ax_tr_account = (string) get_userdata( $ax_tr_reader_user )->user_email;
	ax_tr_assert(
		$ax_tr_results,
		'the address on the account is where notifications would go, without asking again',
		is_array( axismundi_ntf_mailbox( $ax_tr_reader_user ) )
			&& $ax_tr_account === (string) axismundi_ntf_mailbox( $ax_tr_reader_user )['address']
			&& 'account' === (string) axismundi_ntf_mailbox( $ax_tr_reader_user )['source']
	);
	/*
	 * And nothing goes anywhere until somebody asks for email at all. The protection is the transport
	 * being off by default, not the absence of an address.
	 */
	$ax_tr_quiet = ax_tr_deliver( (string) $ax_tr_reader->get_uri(), (string) $ax_tr_sender->get_uri(), 'quiet' );
	update_user_meta( $ax_tr_reader_user, AXISMUNDI_NTF_LAST_ACTIVE_META, time() - HOUR_IN_SECONDS );
	ax_tr_time_passes();
	axismundi_ntf_process_transport_queue();
	ax_tr_assert(
		$ax_tr_results,
		'and nothing is sent until somebody asks for email, an account not being a subscription',
		$ax_tr_quiet > 0 && array() === ax_tr_mail_to( $ax_tr_account )
	);
	axismundi_ntf_set_preference( $ax_tr_reader_user, 0, 'category', 'calendar', 'email', true );

	// -- somewhere else, if they want ----------------------------------------------------------------------------

	axismundi_ntf_request_mailbox( $ax_tr_reader_user, $ax_tr_address );
	ax_tr_assert(
		$ax_tr_results,
		'a different address is confirmed first, since a form takes whatever anybody types into it',
		1 === count( ax_tr_mail_to( $ax_tr_address ) )
			&& $ax_tr_account === (string) axismundi_ntf_mailbox( $ax_tr_reader_user )['address']
	);
	ax_tr_assert(
		$ax_tr_results,
		'and a wrong token confirms nothing',
		false === axismundi_ntf_confirm_mailbox( $ax_tr_reader_user, 'not-the-token' )
			&& $ax_tr_account === (string) axismundi_ntf_mailbox( $ax_tr_reader_user )['address']
	);
	preg_match( '/ax_ntf_confirm=([A-Za-z0-9]+)/', (string) ax_tr_mail_to( $ax_tr_address )[0]['message'], $ax_tr_matches );
	ax_tr_assert(
		$ax_tr_results,
		'while the one that was mailed there does, and takes precedence from then on',
		isset( $ax_tr_matches[1] )
			&& true === axismundi_ntf_confirm_mailbox( $ax_tr_reader_user, (string) $ax_tr_matches[1] )
			&& $ax_tr_address === (string) axismundi_ntf_mailbox( $ax_tr_reader_user )['address']
			&& 'confirmed' === (string) axismundi_ntf_mailbox( $ax_tr_reader_user )['source']
	);

	// -- and then something actually arrives -------------------------------------------------------------------

	/*
	 * The check this file exists for. Not "the preference is on" and not "a row was queued" -- an
	 * actual message, to the address that was confirmed, saying what it is about.
	 */
	$ax_tr_second = ax_tr_deliver( (string) $ax_tr_reader->get_uri(), (string) $ax_tr_sender->get_uri(), 'two' );
	update_user_meta( $ax_tr_reader_user, AXISMUNDI_NTF_LAST_ACTIVE_META, time() - HOUR_IN_SECONDS );
	ax_tr_time_passes();
	$ax_tr_sent = axismundi_ntf_process_transport_queue();
	$ax_tr_mail = ax_tr_mail_to( $ax_tr_address );
	ax_tr_assert(
		$ax_tr_results,
		'a notification for somebody who is away actually reaches their mailbox',
		$ax_tr_second > 0
			&& 1 === (int) $ax_tr_sent['sent']
			&& 2 === count( $ax_tr_mail )
			&& str_contains( (string) end( $ax_tr_mail )['subject'], 'Dress rehearsal' )
	);
	// The message points at the site rather than carrying what somebody might not want in a mailbox.
	ax_tr_assert(
		$ax_tr_results,
		'and points them here rather than carrying the contents into a mailbox',
		str_contains( (string) end( $ax_tr_mail )['message'], 'axismundi-notifications' )
	);

	// -- sending is not delivering ------------------------------------------------------------------------------

	/*
	 * Two rows, deliberately. The delivery is the fact that this is one of theirs; the attempt is one
	 * try at carrying it. Collapsing them would make "you have this" and "the mail server took it"
	 * one column that cannot be both.
	 */
	global $wpdb;
	$ax_tr_states = (array) $wpdb->get_col( 'SELECT state FROM ' . axismundi_ntf_attempts_table() );
	ax_tr_assert(
		$ax_tr_results,
		'the send is recorded beside the delivery rather than inside it',
		in_array( 'sent', $ax_tr_states, true )
			&& 1 === axismundi_ntf_unread_count( $ax_tr_reader_user ) - 1
	);

	// -- what must not be sent ------------------------------------------------------------------------------------

	// Somebody sitting in the app is told by the app.
	$ax_tr_third = ax_tr_deliver( (string) $ax_tr_reader->get_uri(), (string) $ax_tr_sender->get_uri(), 'three' );
	update_user_meta( $ax_tr_reader_user, AXISMUNDI_NTF_LAST_ACTIVE_META, time() );
	$ax_tr_before = count( ax_tr_mail_to( $ax_tr_address ) );
	ax_tr_time_passes();
	$ax_tr_held   = axismundi_ntf_process_transport_queue();
	ax_tr_assert(
		$ax_tr_results,
		'somebody who is here right now is not emailed about it, judged when it would be sent',
		$ax_tr_third > 0
			&& 1 === (int) $ax_tr_held['waiting']
			&& $ax_tr_before === count( ax_tr_mail_to( $ax_tr_address ) )
	);
	// And having read it in the app cancels the message rather than delaying it.
	axismundi_ntf_mark_all_read( $ax_tr_reader_user );
	update_user_meta( $ax_tr_reader_user, AXISMUNDI_NTF_LAST_ACTIVE_META, time() - HOUR_IN_SECONDS );
	ax_tr_time_passes();
	$ax_tr_skipped = axismundi_ntf_process_transport_queue();
	ax_tr_assert(
		$ax_tr_results,
		'and something they already read never becomes an email at all',
		1 === (int) $ax_tr_skipped['skipped']
			&& $ax_tr_before === count( ax_tr_mail_to( $ax_tr_address ) )
	);
	// Giving the address up stops everything, and leaves nothing a later bug could read as consent.
	axismundi_ntf_forget_mailbox( $ax_tr_reader_user );
	ax_tr_assert(
		$ax_tr_results,
		'giving up the alternate address falls back to the account one rather than to nothing',
		null === axismundi_ntf_alternate_mailbox( $ax_tr_reader_user, false )
			&& $ax_tr_account === (string) axismundi_ntf_mailbox( $ax_tr_reader_user )['address']
	);
	// The default is off: somebody who never asked for email is not queued for any.
	$ax_tr_quiet_user = ax_tr_user( $ax_tr_users );
	ax_tr_assert(
		$ax_tr_results,
		'and somebody who never asked for email is never queued for any',
		! axismundi_ntf_wants( $ax_tr_quiet_user, 0, 'axismundi-calendar/event-invited', 'calendar', 'email' )
	);

	// -- an Organization has no mailbox --------------------------------------------------------------------------

	/*
	 * Nothing is written to an Organization, because it is not a person and has no attention. What
	 * happens is that each manager is written to at their own address -- which needs no rule of its
	 * own, being what per-person deliveries already do.
	 */
	$ax_tr_manager_one = ax_tr_user( $ax_tr_users );
	$ax_tr_manager_two = ax_tr_user( $ax_tr_users );
	$ax_tr_org         = axismundi_actors_create_managed_actor(
		array(
			'owner_user_id'      => $ax_tr_manager_one,
			'actor_type'         => 'Organization',
			'preferred_username' => 'axtr' . strtolower( wp_generate_password( 8, false, false ) ),
			'status'             => 'internal',
		)
	);
	$ax_tr_org_id = $ax_tr_org instanceof Axismundi_Actor ? (int) $ax_tr_org->get_identity_id() : 0;
	$ax_tr_groups[] = $ax_tr_org_id;
	axismundi_actors_add_manager( $ax_tr_org_id, $ax_tr_manager_two, 'manager' );
	foreach ( array( $ax_tr_manager_one, $ax_tr_manager_two ) as $ax_tr_manager ) {
		axismundi_ntf_set_preference( $ax_tr_manager, 0, 'category', 'calendar', 'email', true );
		update_user_meta( $ax_tr_manager, AXISMUNDI_NTF_LAST_ACTIVE_META, time() - HOUR_IN_SECONDS );
	}
	ax_tr_deliver( (string) axismundi_actors_get_by_identity( $ax_tr_org_id )->get_uri(), (string) $ax_tr_sender->get_uri(), 'org' );
	ax_tr_time_passes();
	axismundi_ntf_process_transport_queue();
	ax_tr_assert(
		$ax_tr_results,
		'a notice to an Organization is emailed to each of its managers, at their own addresses',
		1 === count( ax_tr_mail_to( (string) get_userdata( $ax_tr_manager_one )->user_email ) )
			&& 1 === count( ax_tr_mail_to( (string) get_userdata( $ax_tr_manager_two )->user_email ) )
	);
	// And there is nowhere to put an address on the Organization itself.
	ax_tr_assert(
		$ax_tr_results,
		'and the Organization itself has no mailbox to give, being nobody\'s attention',
		null === axismundi_ntf_mailbox( $ax_tr_org_id )
			&& ! function_exists( 'axismundi_ntf_actor_mailbox' )
	);
} finally {
	global $wpdb;
	/*
	 * Innermost last. An attempt is only orphaned once its delivery is gone, and a delivery only once
	 * its event is -- so cleaning attempts first leaves this run's rows behind to be processed by the
	 * next one, which is exactly the pollution that made this suite pass alone and fail in company.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . axismundi_ntf_events_table() . ' WHERE kind LIKE %s', 'axismundi-calendar/%' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( 'DELETE d FROM ' . axismundi_ntf_deliveries_table() . ' d LEFT JOIN ' . axismundi_ntf_events_table() . ' e ON e.id = d.notification_id WHERE e.id IS NULL' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( 'DELETE a FROM ' . axismundi_ntf_attempts_table() . ' a LEFT JOIN ' . axismundi_ntf_deliveries_table() . ' d ON d.id = a.delivery_id WHERE d.id IS NULL' );
	foreach ( array_unique( $ax_tr_groups ) as $ax_tr_group_id ) {
		if ( $ax_tr_group_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned identity cleanup.
			$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $ax_tr_group_id ), array( '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned identity cleanup.
			$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $ax_tr_group_id ), array( '%d' ) );
		}
	}
	foreach ( array_unique( $ax_tr_users ) as $ax_tr_user_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_ntf_preferences_table(), array( 'local_user_id' => (int) $ax_tr_user_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_ntf_mailboxes_table(), array( 'local_user_id' => (int) $ax_tr_user_id ), array( '%d' ) );
		wp_delete_user( (int) $ax_tr_user_id );
	}
}

$ax_tr_failures = count( array_filter( $ax_tr_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_tr_results ), $ax_tr_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_tr_failures > 0 ? 1 : 0 );
}
exit( $ax_tr_failures > 0 ? 1 : 0 );
