<?php
/**
 * Delivered, held, or never made (dev-only; dist-excluded).
 *
 * Three outcomes, and the middle one is why this is a stage of its own. `filter` is not a polite
 * word for discarding: somebody with a policy against strangers still has to be able to find the one
 * legitimate stranger who wrote to them, and a quarantine they can look through is what makes the
 * policy safe to turn on at all.
 *
 * The other thing pinned here is what this plugin does not own. Blocking is read from the Activity
 * ledger, because a second copy of a federated relation is a second truth about it. Muting is
 * notification-only and says so.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_ac_results = array();
$ax_ac_users   = array();

/** @param bool[] $results Results. */
function ax_ac_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with an activated, published Person Actor. */
function ax_ac_user( array &$users ) : int {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axac' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'editor' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), 'axac' . strtolower( wp_generate_password( 8, false, false ) ) );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return $id;
}

/** One Activity, recorded the way a domain records one. */
function ax_ac_record( string $actor_uri, string $object_uri, string $key ) : ?Axismundi_Activity {
	$activity = axismundi_act_record_source_activity(
		array( 'type' => 'Invite', 'actor' => $actor_uri, 'object' => $object_uri ),
		'local',
		$key
	);
	return $activity instanceof Axismundi_Activity ? $activity : null;
}

/** One intent, offered to the recorder. */
function ax_ac_offer( Axismundi_Activity $activity, string $recipient_uri, string $kind = 'axismundi-calendar/event-invited' ) {
	return axismundi_ntf_record_event(
		array(
			'kind'                => $kind,
			'recipient_actor_uri' => $recipient_uri,
			'actor_uri'           => (string) $activity->get_actor_uri(),
			'snapshot'            => array( 'title' => 'Rehearsal' ),
		),
		$activity
	);
}

add_action(
	'axismundi_notification_register_kinds',
	static function () : void {
		axismundi_ntf_register_kind( 'axismundi-calendar/event-invited', array( 'category' => 'calendar', 'urgency' => 'immediate' ) );
		axismundi_ntf_register_kind( 'axismundi-notifications/security-alert', array( 'category' => 'security', 'urgency' => 'immediate' ) );
	}
);
axismundi_ntf_register_kinds();

try {
	$ax_ac_reader_user  = ax_ac_user( $ax_ac_users );
	$ax_ac_stranger_user = ax_ac_user( $ax_ac_users );
	$ax_ac_reader   = axismundi_actors_get_for_user( $ax_ac_reader_user );
	$ax_ac_stranger = axismundi_actors_get_for_user( $ax_ac_stranger_user );
	$ax_ac_id       = (int) $ax_ac_reader->get_identity_id();
	$ax_ac_object   = home_url( '/?ax-ac=' . wp_generate_uuid4() );

	// -- nothing is held until somebody asks for it -------------------------------------------------------

	/*
	 * All off by default. Quarantining strangers on a site nobody is harassing produces an empty inbox
	 * and a full requests list, which is a worse failure than the one it prevents.
	 */
	$ax_ac_first = ax_ac_offer(
		ax_ac_record( (string) $ax_ac_stranger->get_uri(), $ax_ac_object, 'ax-ac-1:' . $ax_ac_object ),
		(string) $ax_ac_reader->get_uri()
	);
	ax_ac_assert(
		$ax_ac_results,
		'a stranger reaches somebody who has not asked to be protected from strangers',
		! is_wp_error( $ax_ac_first )
			&& 1 === count( axismundi_ntf_inbox( $ax_ac_reader_user ) )
			&& array() === axismundi_ntf_requests( $ax_ac_reader_user )
	);

	// -- held, not discarded --------------------------------------------------------------------------------

	axismundi_ntf_set_policy( $ax_ac_id, array( 'filter_not_following' => true ) );
	$ax_ac_held_activity = ax_ac_record( (string) $ax_ac_stranger->get_uri(), $ax_ac_object . '&b=1', 'ax-ac-2:' . $ax_ac_object );
	$ax_ac_held = ax_ac_offer( $ax_ac_held_activity, (string) $ax_ac_reader->get_uri() );
	ax_ac_assert(
		$ax_ac_results,
		'once they ask, a stranger is held for review rather than delivered',
		! is_wp_error( $ax_ac_held )
			&& 1 === count( axismundi_ntf_requests( $ax_ac_reader_user ) )
			&& 1 === count( axismundi_ntf_inbox( $ax_ac_reader_user ) )
	);
	/*
	 * The row and its snapshot are the same as any other. What differs is which list it appears in --
	 * one table, two questions -- and that is what makes it reviewable.
	 */
	ax_ac_assert(
		$ax_ac_results,
		'and it is the same record, kept whole, differing only in where it is listed',
		'filtered' === (string) axismundi_ntf_requests( $ax_ac_reader_user )[0]['state']
			&& 'Rehearsal' === (string) json_decode( (string) axismundi_ntf_requests( $ax_ac_reader_user )[0]['snapshot'], true )['title']
			&& (string) $ax_ac_held_activity->get_uri() === (string) axismundi_ntf_requests( $ax_ac_reader_user )[0]['source_activity_uri']
	);
	// Accepting it makes it an ordinary notification from that moment.
	ax_ac_assert(
		$ax_ac_results,
		'accepting one lets it through, which is the whole point of holding rather than dropping',
		true === axismundi_ntf_accept_request( (int) axismundi_ntf_requests( $ax_ac_reader_user )[0]['id'], $ax_ac_reader_user )
			&& array() === axismundi_ntf_requests( $ax_ac_reader_user )
			&& 2 === count( axismundi_ntf_inbox( $ax_ac_reader_user ) )
	);
	// Somebody they follow is not a stranger, which is what keeps the condition usable.
	$ax_ac_friend_user = ax_ac_user( $ax_ac_users );
	$ax_ac_friend      = axismundi_actors_get_for_user( $ax_ac_friend_user );
	// A Follow the other Actor accepted. A pending one is somebody who asked and was not let in, and
	// the policy treats that as the stranger it is.
	$ax_ac_follow = axismundi_act_record_source_activity(
		array( 'type' => 'Follow', 'actor' => (string) $ax_ac_reader->get_uri(), 'object' => (string) $ax_ac_friend->get_uri() ),
		'local',
		'ax-ac-follow:' . (string) $ax_ac_friend->get_uri()
	);
	axismundi_act_record_source_activity(
		array( 'type' => 'Accept', 'actor' => (string) $ax_ac_friend->get_uri(), 'object' => (string) $ax_ac_follow->get_uri() ),
		'local',
		'ax-ac-follow-accept:' . (string) $ax_ac_follow->get_uri()
	);
	$ax_ac_from_friend = ax_ac_offer(
		ax_ac_record( (string) $ax_ac_friend->get_uri(), $ax_ac_object . '&c=1', 'ax-ac-3:' . $ax_ac_object ),
		(string) $ax_ac_reader->get_uri()
	);
	ax_ac_assert(
		$ax_ac_results,
		'while somebody they follow is not a stranger under the same policy',
		! is_wp_error( $ax_ac_from_friend ) && array() === axismundi_ntf_requests( $ax_ac_reader_user )
	);
	axismundi_ntf_set_policy( $ax_ac_id, array( 'filter_not_following' => false ) );

	// -- what is never held ------------------------------------------------------------------------------------

	/*
	 * A quarantined security warning is worse than any amount of noise, and a moderation notice a
	 * filter swallowed is one nobody is answering.
	 */
	axismundi_ntf_set_policy( $ax_ac_id, array( 'filter_not_following' => true ) );
	$ax_ac_security = ax_ac_offer(
		ax_ac_record( (string) $ax_ac_stranger->get_uri(), $ax_ac_object . '&d=1', 'ax-ac-4:' . $ax_ac_object ),
		(string) $ax_ac_reader->get_uri(),
		'axismundi-notifications/security-alert'
	);
	ax_ac_assert(
		$ax_ac_results,
		'a security notice is never held back, whatever policy is switched on',
		! is_wp_error( $ax_ac_security )
			&& array() === axismundi_ntf_requests( $ax_ac_reader_user )
	);
	axismundi_ntf_set_policy( $ax_ac_id, array( 'filter_not_following' => false ) );

	// -- decided once, when it is written -----------------------------------------------------------------------

	/*
	 * A policy evaluated at read time would answer today's question about yesterday's message, and
	 * somebody turning a filter on would watch their existing inbox rearrange itself behind them.
	 */
	$ax_ac_before = count( axismundi_ntf_inbox( $ax_ac_reader_user ) );
	axismundi_ntf_set_policy( $ax_ac_id, array( 'filter_not_following' => true ) );
	ax_ac_assert(
		$ax_ac_results,
		'turning a filter on does not reach back and hide what was already delivered',
		$ax_ac_before === count( axismundi_ntf_inbox( $ax_ac_reader_user ) )
	);
	axismundi_ntf_set_policy( $ax_ac_id, array( 'filter_not_following' => false ) );

	// -- muting, which is this plugin's, and blocking, which is not ---------------------------------------------

	/*
	 * A mute is a decision about that Actor in particular, so it ends the question rather than filing
	 * it for review: a requests list full of the people you muted is the mute not working.
	 */
	axismundi_ntf_mute( $ax_ac_id, (string) $ax_ac_stranger->get_uri() );
	$ax_ac_muted = ax_ac_offer(
		ax_ac_record( (string) $ax_ac_stranger->get_uri(), $ax_ac_object . '&e=1', 'ax-ac-5:' . $ax_ac_object ),
		(string) $ax_ac_reader->get_uri()
	);
	ax_ac_assert(
		$ax_ac_results,
		'a muted Actor produces no notice at all, and none held for review either',
		is_wp_error( $ax_ac_muted ) && 'ax_ntf_dropped' === $ax_ac_muted->get_error_code()
			&& array() === axismundi_ntf_requests( $ax_ac_reader_user )
	);
	axismundi_ntf_unmute( $ax_ac_id, (string) $ax_ac_stranger->get_uri() );
	ax_ac_assert(
		$ax_ac_results,
		'and unmuting them starts it again, the mute being about the future and not the past',
		! axismundi_ntf_is_muted( $ax_ac_id, (string) $ax_ac_stranger->get_uri() )
			&& ! is_wp_error( ax_ac_offer( ax_ac_record( (string) $ax_ac_stranger->get_uri(), $ax_ac_object . '&f=1', 'ax-ac-6:' . $ax_ac_object ), (string) $ax_ac_reader->get_uri() ) )
	);
	/*
	 * Blocking is read from the ledger and never stored here. A block is a federated social relation
	 * with its own Activity; a copy in this plugin would be a second truth about it, and the two
	 * would disagree the first time one of them changed.
	 */
	axismundi_act_record_source_activity(
		array( 'type' => 'Block', 'actor' => (string) $ax_ac_reader->get_uri(), 'object' => (string) $ax_ac_stranger->get_uri() ),
		'local',
		'ax-ac-block:' . (string) $ax_ac_stranger->get_uri()
	);
	$ax_ac_blocked = ax_ac_offer(
		ax_ac_record( (string) $ax_ac_stranger->get_uri(), $ax_ac_object . '&g=1', 'ax-ac-7:' . $ax_ac_object ),
		(string) $ax_ac_reader->get_uri()
	);
	ax_ac_assert(
		$ax_ac_results,
		'a blocked Actor is refused on the ledger\'s word, this plugin keeping no copy of the block',
		is_wp_error( $ax_ac_blocked ) && 'ax_ntf_dropped' === $ax_ac_blocked->get_error_code()
			&& axismundi_ntf_is_blocked( (string) $ax_ac_reader->get_uri(), (string) $ax_ac_stranger->get_uri() )
			&& ! function_exists( 'axismundi_ntf_block' )
	);
	// And the mute stays what it says it is.
	ax_ac_assert(
		$ax_ac_results,
		'muting is notification-only, and nothing here reaches a timeline or a search',
		function_exists( 'axismundi_ntf_mute' ) && ! function_exists( 'axismundi_ntf_hide_from_timeline' )
	);
} finally {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . axismundi_ntf_events_table() . ' WHERE kind LIKE %s OR kind LIKE %s', 'axismundi-calendar/%', 'axismundi-notifications/%' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( 'DELETE d FROM ' . axismundi_ntf_deliveries_table() . ' d LEFT JOIN ' . axismundi_ntf_events_table() . ' e ON e.id = d.notification_id WHERE e.id IS NULL' );
	foreach ( array_unique( $ax_ac_users ) as $ax_ac_user_id ) {
		wp_delete_user( (int) $ax_ac_user_id );
	}
}

$ax_ac_failures = count( array_filter( $ax_ac_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ac_results ), $ax_ac_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ac_failures > 0 ? 1 : 0 );
}
exit( $ax_ac_failures > 0 ? 1 : 0 );
