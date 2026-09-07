<?php
/**
 * A Calendar as a collection somebody else can subscribe to (dev-only; dist-excluded).
 *
 * The document a peer fetches, and the one FEP-400e will name as `target`. Three things have to hold
 * or the separations built in the previous slices come apart on the wire: the collection belongs to
 * the calendar's authority while each Event belongs to whoever wrote it; it publishes what is filed
 * here rather than everything that shows here; and a calendar nobody made public is not served at
 * all, however much of it the person asking could read.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_cp_results = array();
$ax_cp_users   = array();
$ax_cp_posts   = array();

/** @param bool[] $results Results. */
function ax_cp_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with an activated Person Actor. */
function ax_cp_user( array &$users ) : int {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axcp' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	if ( $actor instanceof Axismundi_Actor ) {
		axismundi_actors_register_handle( $actor->get_identity_id(), 'axcp' . strtolower( wp_generate_password( 8, false, false ) ) );
	}
	return $id;
}

/** One published Event, filed on a named calendar. */
function ax_cp_event( array &$posts, int $author, int $calendar_id, string $title, string $start, array $extra = array() ) : int {
	$post_id = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_title' => $title, 'post_status' => 'draft', 'post_author' => $author )
	);
	$posts[] = $post_id;
	axismundi_cal_event_save(
		$post_id,
		array_merge( array( 'calendar_id' => $calendar_id, 'starts_at' => $start, 'ends_at' => gmdate( 'Y-m-d H:i:s', strtotime( $start ) + HOUR_IN_SECONDS ), 'timezone' => 'Asia/Seoul' ), $extra )
	);
	$GLOBALS['axismundi_cal_rest_write'] = true;
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$GLOBALS['axismundi_cal_rest_write'] = false;
	return $post_id;
}

try {
	$ax_cp_owner_user = ax_cp_user( $ax_cp_users );
	$ax_cp_guest_user = ax_cp_user( $ax_cp_users );
	wp_set_current_user( $ax_cp_owner_user );
	$ax_cp_owner = axismundi_actors_get_for_user( $ax_cp_owner_user );
	$ax_cp_guest = axismundi_actors_get_for_user( $ax_cp_guest_user );
	$ax_cp_cal   = axismundi_cal_primary_calendar( (string) $ax_cp_owner->get_uri() );
	$ax_cp_id    = (int) $ax_cp_cal['id'];

	// -- a calendar nobody published is not a collection ---------------------------------------------

	/*
	 * Asked before anything is made public, because "not served" is the state a mistake here would
	 * skip past silently: a collection is a document strangers fetch, and holding access is not the
	 * same as the calendar being published.
	 */
	ax_cp_assert(
		$ax_cp_results,
		'a calendar nobody made public is not served as a collection',
		! axismundi_cal_collection_visible( new Axismundi_Cal_Collection( $ax_cp_cal ) )
	);
	axismundi_cal_acl_grant( $ax_cp_id, (string) $ax_cp_guest->get_uri(), 'reader' );
	ax_cp_assert(
		$ax_cp_results,
		'and sharing it with somebody does not publish it either',
		! axismundi_cal_collection_visible( new Axismundi_Cal_Collection( axismundi_cal_calendar_get( $ax_cp_id ) ) )
	);
	axismundi_cal_acl_grant( $ax_cp_id, '', 'reader', 'public' );
	$ax_cp_cal = axismundi_cal_calendar_get( $ax_cp_id );
	ax_cp_assert(
		$ax_cp_results,
		'a public one is',
		axismundi_cal_collection_visible( new Axismundi_Cal_Collection( $ax_cp_cal ) )
	);

	// -- the address ----------------------------------------------------------------------------------

	ax_cp_assert(
		$ax_cp_results,
		'an Actor\'s own calendar is addressed by the handle its Actor already promises',
		home_url( '/calendar/@' . $ax_cp_owner->get_preferred_username() ) === axismundi_cal_collection_uri_for( $ax_cp_cal )
	);
	/*
	 * Anything else gets an opaque id. A calendar somebody made can be renamed, re-shared and handed
	 * over, and none of those may move an address a subscriber already holds.
	 */
	$ax_cp_made = axismundi_cal_calendar_save(
		array( 'name' => 'Team', 'slug' => 'axcp-' . strtolower( wp_generate_password( 8, false, false ) ), 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => (string) $ax_cp_owner->get_uri() )
	);
	$ax_cp_made_row = axismundi_cal_calendar_get( (int) $ax_cp_made );
	ax_cp_assert(
		$ax_cp_results,
		'a calendar somebody made is addressed by an opaque id that a rename cannot move',
		home_url( '/calendar/c/' . $ax_cp_made_row['uuid'] ) === axismundi_cal_collection_uri_for( $ax_cp_made_row )
	);

	// -- whose collection, and whose work ---------------------------------------------------------------

	$ax_cp_first  = ax_cp_event( $ax_cp_posts, $ax_cp_owner_user, $ax_cp_id, 'Second', '2026-12-02 10:00:00' );
	$ax_cp_second = ax_cp_event( $ax_cp_posts, $ax_cp_owner_user, $ax_cp_id, 'First', '2026-12-01 10:00:00' );
	$ax_cp_doc    = axismundi_cal_collection_transform( new Axismundi_Cal_Collection( $ax_cp_cal ) );
	ax_cp_assert(
		$ax_cp_results,
		'the collection belongs to the calendar\'s authority, not to whoever wrote what is in it',
		is_array( $ax_cp_doc )
			&& 'OrderedCollection' === (string) $ax_cp_doc['type']
			&& (string) $ax_cp_owner->get_uri() === (string) $ax_cp_doc['attributedTo']
			&& home_url( '/calendar/@' . $ax_cp_owner->get_preferred_username() ) === (string) $ax_cp_doc['id']
	);
	// Ordered by when they happen, so the document does not depend on the order rows were written.
	ax_cp_assert(
		$ax_cp_results,
		'and its items are ordered by when they start rather than by when they were saved',
		array( axismundi_cal_event_object_uri( get_post( $ax_cp_second ) ), axismundi_cal_event_object_uri( get_post( $ax_cp_first ) ) )
			=== (array) $ax_cp_doc['orderedItems']
	);

	// -- what it publishes is narrower than what it shows -------------------------------------------------

	/*
	 * A private Event on a public calendar is the two-axis rule, and the collection is one of the
	 * surfaces that must not forget to ask.
	 */
	$ax_cp_private = ax_cp_event( $ax_cp_posts, $ax_cp_owner_user, $ax_cp_id, 'Private', '2026-12-03 10:00:00', array( 'visibility' => 'private' ) );
	$ax_cp_doc     = axismundi_cal_collection_transform( new Axismundi_Cal_Collection( axismundi_cal_calendar_get( $ax_cp_id ) ) );
	ax_cp_assert(
		$ax_cp_results,
		'a private Event on a public calendar stays out of the collection',
		! in_array( axismundi_cal_event_object_uri( get_post( $ax_cp_private ) ), (array) $ax_cp_doc['orderedItems'], true )
			&& 2 === (int) $ax_cp_doc['totalItems']
	);
	/*
	 * And an Event that merely appears here. The guest's calendar shows what they were invited to; the
	 * host's collection publishes what is filed on it, and republishing another Actor's Event would
	 * tell a subscriber it lives at two addresses.
	 */
	$ax_cp_guest_cal = axismundi_cal_primary_calendar( (string) $ax_cp_guest->get_uri() );
	axismundi_cal_acl_grant( (int) $ax_cp_guest_cal['id'], '', 'reader', 'public' );
	$ax_cp_joinable = ax_cp_event( $ax_cp_posts, $ax_cp_owner_user, $ax_cp_id, 'Party', '2026-12-04 10:00:00', array( 'join_mode' => 'free', 'join_eligibility' => 'public' ) );
	axismundi_cal_event_join( $ax_cp_joinable, (string) $ax_cp_guest->get_uri() );
	$ax_cp_guest_doc = axismundi_cal_collection_transform( new Axismundi_Cal_Collection( axismundi_cal_calendar_get( (int) $ax_cp_guest_cal['id'] ) ) );
	ax_cp_assert(
		$ax_cp_results,
		'an Event somebody was invited to is published by the calendar it is filed on, not by theirs',
		array() === (array) $ax_cp_guest_doc['orderedItems']
			&& in_array( axismundi_cal_event_object_uri( get_post( $ax_cp_joinable ) ), (array) axismundi_cal_collection_transform( new Axismundi_Cal_Collection( axismundi_cal_calendar_get( $ax_cp_id ) ) )['orderedItems'], true )
	);
	// Their own calendar still shows it, which is the point of the two being different questions.
	ax_cp_assert(
		$ax_cp_results,
		'while their own calendar still shows it to them',
		in_array( $ax_cp_joinable, axismundi_cal_placed_event_ids( (int) $ax_cp_guest_cal['id'] ), true )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_cp_posts ) as $ax_cp_post_id ) {
		wp_delete_post( (int) $ax_cp_post_id, true );
	}
	if ( isset( $ax_cp_made ) && ! is_wp_error( $ax_cp_made ) ) {
		axismundi_cal_calendar_delete( (int) $ax_cp_made );
	}
	foreach ( array_unique( $ax_cp_users ) as $ax_cp_user_id ) {
		wp_delete_user( (int) $ax_cp_user_id );
	}
}

$ax_cp_failures = count( array_filter( $ax_cp_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_cp_results ), $ax_cp_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_cp_failures > 0 ? 1 : 0 );
}
exit( $ax_cp_failures > 0 ? 1 : 0 );
