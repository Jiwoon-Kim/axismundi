<?php
/**
 * The notification contract (dev-only; dist-excluded).
 *
 * Slice one is a contract and not a feature, so this is what pins it: every entry projects a
 * recorded Activity, an act resolves once however many times it arrives, resolution happens after
 * the transition rather than at record time, and the explicit flush is the path rather than the
 * shutdown pass.
 *
 * The resolver here stands in for Calendar's, which arrives in slice three. It is deliberately a
 * fixture: what is being audited is the seam, and a real domain would prove the seam and its own
 * decisions at once, leaving neither pinned.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_ct_results = array();
$ax_ct_users   = array();

/** @param bool[] $results Results. */
function ax_ct_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with an activated, published Person Actor. */
function ax_ct_actor( array &$users ) : Axismundi_Actor {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axct' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), 'axct' . strtolower( wp_generate_password( 8, false, false ) ) );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return axismundi_actors_get_for_user( $id );
}

// What a domain would register. The state it reads is deliberately mutable, so the fixture can put
// the resolver on both sides of a transition and see which answer it gives.
$GLOBALS['ax_ct_state'] = array( 'audience' => array(), 'kind' => 'axismundi-calendar/event-invited' );

add_action(
	'axismundi_notification_register_kinds',
	static function () : void {
		axismundi_ntf_register_kind( 'axismundi-calendar/event-invited', array( 'category' => 'calendar', 'urgency' => 'immediate' ) );
		axismundi_ntf_register_kind( 'axismundi-calendar/event-cancelled', array( 'category' => 'calendar', 'urgency' => 'immediate' ) );
	}
);
axismundi_ntf_register_kinds();

add_filter(
	'axismundi_notification_intents',
	static function ( array $intents, Axismundi_Activity $activity ) : array {
		if ( 'Invite' !== $activity->get_type() && 'Update' !== $activity->get_type() ) {
			return $intents;
		}
		foreach ( (array) $GLOBALS['ax_ct_state']['audience'] as $recipient ) {
			$intents[] = array(
				'kind'                => 'Invite' === $activity->get_type() ? 'axismundi-calendar/event-invited' : 'axismundi-calendar/event-cancelled',
				'recipient_actor_uri' => (string) $recipient,
				'grouping_key'        => 'fixture:' . (string) $activity->get_object_uri(),
				'snapshot'            => array( 'title' => 'Rehearsal', 'seen_audience' => count( (array) $GLOBALS['ax_ct_state']['audience'] ) ),
			);
		}
		return $intents;
	},
	10,
	2
);

/** One Activity, recorded the way a domain records one. */
function ax_ct_record( string $type, string $actor_uri, string $object_uri, string $key ) : ?Axismundi_Activity {
	$activity = axismundi_act_record_source_activity(
		array( 'type' => $type, 'actor' => $actor_uri, 'object' => $object_uri ),
		'local',
		$key
	);
	return $activity instanceof Axismundi_Activity ? $activity : null;
}

try {
	$ax_ct_host  = ax_ct_actor( $ax_ct_users );
	$ax_ct_guest = ax_ct_actor( $ax_ct_users );
	$ax_ct_other = ax_ct_actor( $ax_ct_users );
	$ax_ct_object = home_url( '/?ax-ct=' . wp_generate_uuid4() );

	// -- resolution waits for the transition -------------------------------------------------------------

	/*
	 * The trap this design exists around. The ledger announces the Activity the moment it commits, and
	 * a domain records the Activity before it writes the row -- so a resolver run there would answer
	 * from the state before the act. Here the audience is still empty at record time.
	 */
	$GLOBALS['ax_ct_state']['audience'] = array();
	$ax_ct_invite = ax_ct_record( 'Invite', (string) $ax_ct_host->get_uri(), $ax_ct_object, 'ax-ct-invite:' . $ax_ct_object );
	ax_ct_assert(
		$ax_ct_results,
		'recording an Activity notifies nobody yet, the transition it describes not having happened',
		null !== $ax_ct_invite
			&& array( (string) $ax_ct_invite->get_uri() ) === axismundi_ntf_pending()
			&& array() === axismundi_ntf_events_for_actor( (int) $ax_ct_guest->get_identity_id() )
	);
	// The domain finishes its work and says so, which is when the audience is what the act produced.
	$GLOBALS['ax_ct_state']['audience'] = array( (string) $ax_ct_guest->get_uri() );
	ax_ct_assert(
		$ax_ct_results,
		'and the explicit flush is what asks, so the answer is the state the act left behind',
		1 === axismundi_notification_flush()
			&& array() === axismundi_ntf_pending()
			&& 1 === count( axismundi_ntf_events_for_actor( (int) $ax_ct_guest->get_identity_id() ) )
	);
	/*
	 * The snapshot is stored because nothing will ever ask again. There is no path here that re-runs a
	 * resolver over history: the audience was a fact about a moment, and tomorrow's answer would be a
	 * different question -- a past cancellation sent to somebody removed since.
	 */
	$ax_ct_stored = axismundi_ntf_events_for_actor( (int) $ax_ct_guest->get_identity_id() )[0];
	ax_ct_assert(
		$ax_ct_results,
		'keeping what the resolver saw, since the audience of a past act is not recomputable',
		'Rehearsal' === (string) json_decode( (string) $ax_ct_stored['snapshot'], true )['title']
			&& 1 === (int) json_decode( (string) $ax_ct_stored['snapshot'], true )['seen_audience']
	);

	// -- every entry projects an Activity ------------------------------------------------------------------

	ax_ct_assert(
		$ax_ct_results,
		'and names the Activity it projects, with the category its kind was registered under',
		(string) $ax_ct_invite->get_uri() === (string) $ax_ct_stored['source_activity_uri']
			&& 'calendar' === (string) $ax_ct_stored['category']
			&& (string) $ax_ct_host->get_uri() === (string) $ax_ct_stored['actor_uri']
	);
	// A kind nothing registered is refused: an entry no settings screen can describe is one nobody
	// could ever turn off.
	$ax_ct_unknown = axismundi_ntf_record_event(
		array( 'kind' => 'axismundi-calendar/event-invented', 'recipient_actor_uri' => (string) $ax_ct_guest->get_uri() ),
		$ax_ct_invite
	);
	ax_ct_assert(
		$ax_ct_results,
		'a kind nobody registered is refused rather than stored under a name nothing describes',
		is_wp_error( $ax_ct_unknown ) && 'ax_ntf_kind' === $ax_ct_unknown->get_error_code()
	);

	// -- one act, one notice --------------------------------------------------------------------------------

	/*
	 * Everything that produces one of these can produce it twice: a redelivered inbox POST, a retried
	 * request, a double-clicked button. The constraint is what makes the second one land on the first.
	 */
	$ax_ct_again = ax_ct_record( 'Invite', (string) $ax_ct_host->get_uri(), $ax_ct_object, 'ax-ct-invite:' . $ax_ct_object );
	axismundi_notification_flush();
	ax_ct_assert(
		$ax_ct_results,
		'the same act arriving again is the same notice, not a second one',
		null !== $ax_ct_again
			&& 1 === count( axismundi_ntf_events_for_actor( (int) $ax_ct_guest->get_identity_id() ) )
	);
	// A different kind from the same Activity is a different notice, which is why the kind is in the key.
	$ax_ct_second_kind = axismundi_ntf_record_event(
		array( 'kind' => 'axismundi-calendar/event-cancelled', 'recipient_actor_uri' => (string) $ax_ct_guest->get_uri() ),
		$ax_ct_invite
	);
	ax_ct_assert(
		$ax_ct_results,
		'while one Activity meaning two different things to somebody is two notices',
		! is_wp_error( $ax_ct_second_kind )
			&& 2 === count( axismundi_ntf_events_for_actor( (int) $ax_ct_guest->get_identity_id() ) )
	);

	// -- who may be addressed ----------------------------------------------------------------------------------

	// Nobody hears about their own act, said once here rather than in every resolver.
	$ax_ct_self = axismundi_ntf_record_event(
		array( 'kind' => 'axismundi-calendar/event-invited', 'recipient_actor_uri' => (string) $ax_ct_host->get_uri() ),
		$ax_ct_invite
	);
	ax_ct_assert(
		$ax_ct_results,
		'an Actor is not told about something they did themselves',
		is_wp_error( $ax_ct_self ) && 'ax_ntf_self' === $ax_ct_self->get_error_code()
	);
	/*
	 * And a remote Actor has no inbox here. Their half of the same act is the Activity delivered to
	 * their server, which the ledger has already done; a row here would be an entry with no reader.
	 */
	$ax_ct_remote = axismundi_ntf_record_event(
		array( 'kind' => 'axismundi-calendar/event-invited', 'recipient_actor_uri' => 'https://remote.example/users/nobody' ),
		$ax_ct_invite
	);
	ax_ct_assert(
		$ax_ct_results,
		'and a remote Actor reads their notifications on their own server, not in this table',
		is_wp_error( $ax_ct_remote ) && 'ax_ntf_recipient' === $ax_ct_remote->get_error_code()
	);

	// -- the flush is the contract; shutdown is the net ------------------------------------------------------

	/*
	 * Both paths resolve, and they are not interchangeable. A fatal error, an `exit` in a redirect
	 * handler or a killed request all end without shutdown running the way anybody hoped, and what
	 * survives is the Activity with no notification beside it -- so a domain command that returns
	 * having recorded an Activity and changed its state, without flushing, is a bug in that command.
	 */
	$GLOBALS['ax_ct_state']['audience'] = array( (string) $ax_ct_other->get_uri() );
	$ax_ct_late = ax_ct_record( 'Update', (string) $ax_ct_host->get_uri(), $ax_ct_object, 'ax-ct-update:' . $ax_ct_object );
	ax_ct_assert(
		$ax_ct_results,
		'an unflushed Activity is still waiting rather than already resolved',
		null !== $ax_ct_late
			&& array( (string) $ax_ct_late->get_uri() ) === axismundi_ntf_pending()
			&& array() === axismundi_ntf_events_for_actor( (int) $ax_ct_other->get_identity_id() )
	);
	do_action( 'shutdown' );
	ax_ct_assert(
		$ax_ct_results,
		'and the shutdown pass catches it, because losing the notice as well as the bug helps nobody',
		array() === axismundi_ntf_pending()
			&& 1 === count( axismundi_ntf_events_for_actor( (int) $ax_ct_other->get_identity_id() ) )
	);
	// Flushing with nothing waiting is not an error, so a command may always call it.
	ax_ct_assert(
		$ax_ct_results,
		'flushing an empty queue does nothing at all, so a command can always end with one',
		0 === axismundi_notification_flush() && 0 === axismundi_notification_flush()
	);

	// -- and nothing here is a second record ---------------------------------------------------------------------

	ax_ct_assert(
		$ax_ct_results,
		'this plugin needs the ledger and the identity service, and imitates neither',
		axismundi_ntf_has_activities() && axismundi_ntf_has_actors()
			&& ! function_exists( 'axismundi_ntf_record_activity' )
	);
} finally {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . axismundi_ntf_events_table() . ' WHERE kind LIKE %s', 'axismundi-calendar/%' ) );
	foreach ( array_unique( $ax_ct_users ) as $ax_ct_user_id ) {
		wp_delete_user( (int) $ax_ct_user_id );
	}
}

$ax_ct_failures = count( array_filter( $ax_ct_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ct_results ), $ax_ct_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ct_failures > 0 ? 1 : 0 );
}
exit( $ax_ct_failures > 0 ? 1 : 0 );
