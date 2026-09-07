<?php
/**
 * Who is allowed to ask (dev-only; dist-excluded).
 *
 * `join_mode` was answering two questions. How a request is admitted -- at once, by somebody
 * deciding, elsewhere, or not at all -- is not the same as who is allowed to make one, and a single
 * field could offer "open to anyone" and "invitation only" but never "followers, admitted
 * immediately", which is one value from each.
 *
 * The rule this file exists for is that the entrance closes before the reply is recorded. An Event
 * admitting people on arrival has no later step at which anybody could be turned away, so a check
 * that ran after the row was written would be inspecting a door somebody had already walked through.
 * Every assertion about a refusal here therefore checks the absence of the row as well as the error,
 * because an error beside a stored acceptance is the failure that looks like a success.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_je_results   = array();
$ax_je_posts     = array();
$ax_je_calendars = array();
$ax_je_users     = array();

/**
 * Forget which Actor published an Event, which no ordinary path does.
 *
 * The fixtures need an Event nothing can answer for, and an Event that has been saved always records
 * a publisher. Written directly rather than through the writer, because refusing to produce this
 * state is exactly what the writer is for.
 *
 * @param int $post_id Event post ID.
 * @return void
 */
function ax_je_clear_acting_actor( int $post_id ) : void {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture reaching a state the writer will not produce.
	$wpdb->update( axismundi_cal_events_table(), array( 'acting_actor_identity_id' => 0 ), array( 'post_id' => $post_id ), array( '%d' ), array( '%d' ) );
}

/** @param bool[] $results Results. */
function ax_je_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/**
 * A user with a public Person Actor whose handle is claimed.
 *
 * The handle is not decoration here. A local Follow requires both Actors to be activated, and an
 * Actor without one is not -- so a fixture that skipped it would produce two people who cannot
 * follow each other, and every assertion below would pass for the wrong reason.
 */
function ax_je_user( array &$users ) : array {
	$handle  = 'axje' . strtolower( wp_generate_password( 8, false, false ) );
	$id      = (int) wp_insert_user( array( 'user_login' => $handle, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$users[] = $id;
	$uri     = '';
	if ( function_exists( 'axismundi_actors_ensure_for_user' ) ) {
		$actor = axismundi_actors_ensure_for_user( $id );
		if ( $actor instanceof Axismundi_Actor ) {
			axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
			axismundi_actors_register_handle( $actor->get_identity_id(), $handle );
			$uri = (string) $actor->get_uri();
		}
	}
	return array( 'user_id' => $id, 'actor_uri' => $uri );
}

/** Whether one Actor has a reply of any kind stored for one Event. */
function ax_je_has_row( int $post_id, string $actor_uri ) : bool {
	return is_array( axismundi_cal_event_participation( $post_id, $actor_uri ) );
}

try {
	$ax_je_host      = ax_je_user( $ax_je_users );
	$ax_je_follower  = ax_je_user( $ax_je_users );
	$ax_je_stranger  = ax_je_user( $ax_je_users );
	$ax_je_applicant = ax_je_user( $ax_je_users );

	$ax_je_actor = static function ( string $uri ) : ?Axismundi_Actor {
		$actor = axismundi_actors_get_by_uri( $uri );
		return $actor instanceof Axismundi_Actor ? $actor : null;
	};

	// The relationship the whole file is about, made the ordinary way so that what is asserted is the
	// same relation an actual Follow produces rather than a row written to suit the test.
	axismundi_act_follow_local_actor( $ax_je_actor( $ax_je_follower['actor_uri'] ), $ax_je_actor( $ax_je_host['actor_uri'] ) );

	/*
	 * Asserted before anything depends on it. Every refusal below would also be produced by two people
	 * who simply cannot follow each other, so a fixture that failed silently would report a working
	 * door while proving nothing about it.
	 */
	$ax_je_established = axismundi_act_get_relation( 'follow', $ax_je_follower['actor_uri'], $ax_je_host['actor_uri'] );
	ax_je_assert(
		$ax_je_results,
		'the fixture really did establish a follow, so the refusals below mean what they say',
		is_array( $ax_je_established ) && 'accepted' === (string) $ax_je_established['state']
	);

	wp_set_current_user( $ax_je_host['user_id'] );
	$ax_je_suffix   = strtolower( wp_generate_password( 6, false, false ) );
	$ax_je_calendar = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Eligibility fixture', 'slug' => 'ax-je-' . $ax_je_suffix, 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_je_host['actor_uri'] )
	);
	$ax_je_calendars[] = $ax_je_calendar;
	axismundi_cal_acl_grant( $ax_je_calendar, '', 'reader', 'public' );

	$ax_je_make = static function ( array &$posts, int $calendar, string $title, array $fields = array() ) : int {
		$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_title' => $title, 'post_author' => get_current_user_id() ) );
		$posts[] = $post_id;
		axismundi_cal_event_save(
			$post_id,
			array_merge( array( 'calendar_id' => $calendar, 'timezone' => 'Asia/Seoul', 'starts_at' => '2026-11-14 19:00:00', 'ends_at' => '2026-11-14 21:00:00' ), $fields )
		);
		$GLOBALS['axismundi_cal_rest_write'] = true;
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		$GLOBALS['axismundi_cal_rest_write'] = false;
		return $post_id;
	};

	// -- The two questions, kept apart ----------------------------------------------------------------

	ax_je_assert(
		$ax_je_results,
		'an event written before this column existed is open to anyone, which is what it meant',
		'public' === (string) axismundi_cal_event_get( $ax_je_make( $ax_je_posts, $ax_je_calendar, 'Default', array( 'join_mode' => 'free' ) ) )['join_eligibility']
	);
	ax_je_assert(
		$ax_je_results,
		'and an eligibility nothing defines is refused rather than stored as a restriction nobody can read',
		is_wp_error( axismundi_cal_event_save( $ax_je_posts[0], array( 'join_eligibility' => 'nobody' ) ) )
	);

	// -- public --------------------------------------------------------------------------------------

	$ax_je_open = $ax_je_make( $ax_je_posts, $ax_je_calendar, 'Open house', array( 'join_mode' => 'free', 'join_eligibility' => 'public' ) );
	ax_je_assert(
		$ax_je_results,
		'anyone may ask an event open to anyone, and an immediate mode admits them on arrival',
		'accepted' === axismundi_cal_event_join( $ax_je_open, $ax_je_stranger['actor_uri'] )
	);

	$ax_je_moderated = $ax_je_make( $ax_je_posts, $ax_je_calendar, 'Open but moderated', array( 'join_mode' => 'restricted', 'join_eligibility' => 'public' ) );
	ax_je_assert(
		$ax_je_results,
		'and the same request waits when the mode is the one that waits',
		'pending' === axismundi_cal_event_join( $ax_je_moderated, $ax_je_stranger['actor_uri'] )
	);

	// -- followers -----------------------------------------------------------------------------------

	$ax_je_private_free = $ax_je_make(
		$ax_je_posts,
		$ax_je_calendar,
		'Followers, admitted at once',
		array( 'join_mode' => 'free', 'join_eligibility' => 'followers' )
	);
	ax_je_assert(
		$ax_je_results,
		'a follower may ask, and is admitted on arrival exactly as anyone would have been',
		'accepted' === axismundi_cal_event_join( $ax_je_private_free, $ax_je_follower['actor_uri'] )
	);

	/*
	 * The pair the split exists for. An immediate mode turns the next few lines into an attendee, so a
	 * refusal here has to leave nothing behind -- not a pending row somebody could later approve by
	 * mistake, and not a place taken in a count.
	 */
	$ax_je_refused = axismundi_cal_event_join( $ax_je_private_free, $ax_je_stranger['actor_uri'] );
	ax_je_assert(
		$ax_je_results,
		'somebody who does not follow the host is refused an event restricted to followers',
		is_wp_error( $ax_je_refused ) && 'ax_event_join_ineligible' === $ax_je_refused->get_error_code()
	);
	ax_je_assert(
		$ax_je_results,
		'and the refusal stores nothing, so there is no reply for anyone to accept afterwards',
		! ax_je_has_row( $ax_je_private_free, $ax_je_stranger['actor_uri'] )
	);
	ax_je_assert(
		$ax_je_results,
		'nor do they appear among the people coming',
		1 === count( axismundi_cal_event_attendees( $ax_je_private_free ) )
	);

	$ax_je_private_mod = $ax_je_make(
		$ax_je_posts,
		$ax_je_calendar,
		'Followers, after approval',
		array( 'join_mode' => 'restricted', 'join_eligibility' => 'followers' )
	);
	ax_je_assert(
		$ax_je_results,
		'a follower may ask a moderated event and waits, which is the mode answering rather than the door',
		'pending' === axismundi_cal_event_join( $ax_je_private_mod, $ax_je_follower['actor_uri'] )
	);
	ax_je_assert(
		$ax_je_results,
		'a stranger does not reach the queue at all, since waiting is something only the eligible do',
		is_wp_error( axismundi_cal_event_join( $ax_je_private_mod, $ax_je_stranger['actor_uri'] ) )
			&& ! ax_je_has_row( $ax_je_private_mod, $ax_je_stranger['actor_uri'] )
	);

	// -- What counts as following --------------------------------------------------------------------

	/*
	 * Asked for is not accepted. An Actor who approves their own followers has withheld something, and
	 * reading the request as the relationship would let a Join walk past a decision they had made.
	 */
	add_filter( 'axismundi_act_local_follow_requires_approval', '__return_true' );
	axismundi_act_follow_local_actor( $ax_je_actor( $ax_je_applicant['actor_uri'] ), $ax_je_actor( $ax_je_host['actor_uri'] ) );
	remove_filter( 'axismundi_act_local_follow_requires_approval', '__return_true' );
	ax_je_assert(
		$ax_je_results,
		'a follow still awaiting approval is not yet following, and does not open the event',
		'pending' === (string) ( (array) axismundi_act_get_relation( 'follow', $ax_je_applicant['actor_uri'], $ax_je_host['actor_uri'] ) )['state']
			&& ! axismundi_cal_event_join_eligible( $ax_je_private_free, $ax_je_applicant['actor_uri'] )
	);

	/*
	 * Direction. The host following somebody is the host's interest in them, not their invitation --
	 * and a predicate that read the relation either way round would open every event to whoever its
	 * organizer happened to follow back.
	 */
	axismundi_act_follow_local_actor( $ax_je_actor( $ax_je_host['actor_uri'] ), $ax_je_actor( $ax_je_stranger['actor_uri'] ) );
	ax_je_assert(
		$ax_je_results,
		'being followed by the host is not following the host',
		'accepted' === (string) ( (array) axismundi_act_get_relation( 'follow', $ax_je_host['actor_uri'], $ax_je_stranger['actor_uri'] ) )['state']
			&& ! axismundi_cal_event_join_eligible( $ax_je_private_free, $ax_je_stranger['actor_uri'] )
	);

	ax_je_assert(
		$ax_je_results,
		'the host is eligible for their own event, being no follower of themselves',
		axismundi_cal_event_join_eligible( $ax_je_private_free, $ax_je_host['actor_uri'] )
	);

	// -- The host counting themselves in -------------------------------------------------------------

	/*
	 * Not automatic. Somebody who runs an event and does not attend it is ordinary, so being the
	 * organizer cannot put them among the attendees by itself -- the list would then be answering a
	 * question nobody asked it.
	 */
	$ax_je_hosted = $ax_je_make( $ax_je_posts, $ax_je_calendar, 'Hosted, not attended', array( 'join_mode' => 'free', 'join_eligibility' => 'followers' ) );
	ax_je_assert(
		$ax_je_results,
		'running an event does not by itself put the host among the people coming',
		0 === count( axismundi_cal_event_attendees( $ax_je_hosted ) )
			&& ! ax_je_has_row( $ax_je_hosted, $ax_je_host['actor_uri'] )
	);
	/*
	 * And when they do say so, the answer is immediate whatever the mode. The person who would approve
	 * the request is the person making it, so leaving it pending would be waiting for oneself.
	 */
	ax_je_assert(
		$ax_je_results,
		'the host may join their own followers-only event, which they do not follow',
		'accepted' === axismundi_cal_event_join( $ax_je_hosted, $ax_je_host['actor_uri'] )
			&& 1 === count( axismundi_cal_event_attendees( $ax_je_hosted ) )
	);

	$ax_je_invite_only = $ax_je_make( $ax_je_posts, $ax_je_calendar, 'Invitation only', array( 'join_mode' => 'invite' ) );
	ax_je_assert(
		$ax_je_results,
		'and their own invitation-only event, which is not a rule about whether they may attend',
		'accepted' === axismundi_cal_event_join( $ax_je_invite_only, $ax_je_host['actor_uri'] )
	);
	ax_je_assert(
		$ax_je_results,
		'while everybody else is still waiting for an invitation to exist',
		is_wp_error( axismundi_cal_event_join( $ax_je_invite_only, $ax_je_follower['actor_uri'] ) )
	);
	$ax_je_moderated_own = $ax_je_make( $ax_je_posts, $ax_je_calendar, 'Own and moderated', array( 'join_mode' => 'restricted' ) );
	ax_je_assert(
		$ax_je_results,
		'a host does not queue for their own approval',
		'accepted' === axismundi_cal_event_join( $ax_je_moderated_own, $ax_je_host['actor_uri'] )
	);

	/*
	 * The one thing self-joining does not bypass. A capacity of one that seats two is not a capacity,
	 * and every peer reading `remainingAttendeeCapacity` would be told something untrue -- so a full
	 * event is the host's to resize or to thin out, which are decisions rather than an exception.
	 */
	$ax_je_full = $ax_je_make( $ax_je_posts, $ax_je_calendar, 'One seat', array( 'join_mode' => 'free', 'maximum_attendee_capacity' => 1 ) );
	axismundi_cal_event_join( $ax_je_full, $ax_je_stranger['actor_uri'] );
	ax_je_assert(
		$ax_je_results,
		'the host is turned away from a full event exactly as anybody else would be',
		0 === axismundi_cal_event_remaining_capacity( $ax_je_full )
			&& is_wp_error( axismundi_cal_event_join( $ax_je_full, $ax_je_host['actor_uri'] ) )
			&& 1 === count( axismundi_cal_event_attendees( $ax_je_full ) )
	);
	/*
	 * `none` is not a policy self-joining escapes either. An Event tracking no participation has no
	 * list for the host to be on, and admitting one person to it would make the setting mean nothing.
	 */
	$ax_je_untracked = $ax_je_make( $ax_je_posts, $ax_je_calendar, 'No participation', array( 'join_mode' => 'none' ) );
	ax_je_assert(
		$ax_je_results,
		'an event tracking nobody tracks its host no differently',
		is_wp_error( axismundi_cal_event_join( $ax_je_untracked, $ax_je_host['actor_uri'] ) )
			&& ! ax_je_has_row( $ax_je_untracked, $ax_je_host['actor_uri'] )
	);

	// -- Reachable to its host, whoever else can find it ----------------------------------------------

	/*
	 * Public listing is what makes an Event reachable by a stranger. It is not what makes it the
	 * host's to act on, and reading one gate as the other keeps somebody off the guest list of an
	 * event they are running because the calendar it is on is for members.
	 */
	$ax_je_members = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Members only', 'slug' => 'ax-je-shut-' . $ax_je_suffix, 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_je_host['actor_uri'] )
	);
	$ax_je_calendars[] = $ax_je_members;
	$ax_je_closed = $ax_je_make( $ax_je_posts, $ax_je_members, 'Behind the door', array( 'join_mode' => 'free', 'maximum_attendee_capacity' => 1 ) );
	ax_je_assert(
		$ax_je_results,
		'an event on a calendar nobody can list is still one its host may join',
		! axismundi_cal_event_listable( get_post( $ax_je_closed ) )
			&& 'accepted' === axismundi_cal_event_join( $ax_je_closed, $ax_je_host['actor_uri'] )
	);
	ax_je_assert(
		$ax_je_results,
		'and the host takes an ordinary seat there, counted like any other',
		0 === axismundi_cal_event_remaining_capacity( $ax_je_closed )
	);
	ax_je_assert(
		$ax_je_results,
		'while it stays unreachable to everybody else, which is what the listing gate is for',
		is_wp_error( axismundi_cal_event_join( $ax_je_closed, $ax_je_follower['actor_uri'] ) )
			&& ! ax_je_has_row( $ax_je_closed, $ax_je_follower['actor_uri'] )
	);

	// -- Actor to Actor, on both ends ------------------------------------------------------------------

	/*
	 * `Join`, `Invite`, `Accept` and `Reject` all take an Actor as their subject. A host without one
	 * cannot answer a request that arrives, so turning participation on for them would be offering a
	 * handshake with nobody on the other side -- and it is the point at which a participant identity
	 * that is a local user id would have to enter the model.
	 */
	$ax_je_plain = (int) wp_insert_user( array( 'user_login' => 'ax_je_plain_' . $ax_je_suffix, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_je_users[] = $ax_je_plain;
	$ax_je_hostless = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_title' => 'No actor', 'post_author' => $ax_je_plain )
	);
	$ax_je_posts[] = $ax_je_hostless;
	$ax_je_base = array( 'calendar_id' => $ax_je_calendar, 'timezone' => 'Asia/Seoul', 'starts_at' => '2026-11-14 19:00:00', 'ends_at' => '2026-11-14 21:00:00' );
	ax_je_assert(
		$ax_je_results,
		'an author with no actor profile cannot turn participation on at all',
		'' === axismundi_cal_event_owner_actor_uri( $ax_je_hostless )
			&& is_wp_error( axismundi_cal_event_save( $ax_je_hostless, array_merge( $ax_je_base, array( 'join_mode' => 'free' ) ) ) )
			&& is_wp_error( axismundi_cal_event_save( $ax_je_hostless, array_merge( $ax_je_base, array( 'join_mode' => 'restricted' ) ) ) )
	);
	ax_je_assert(
		$ax_je_results,
		'though the event itself saves perfectly well, participation being the part that needs one',
		! is_wp_error( axismundi_cal_event_save( $ax_je_hostless, array_merge( $ax_je_base, array( 'join_mode' => 'none' ) ) ) )
	);

	// -- Fail closed ---------------------------------------------------------------------------------

	/*
	 * An owner with no Actor cannot be followed by anybody, so there is no set for `followers` to name.
	 * Read as everyone, that unanswerable question would admit the whole internet to the one kind of
	 * event somebody deliberately narrowed.
	 */
	$ax_je_orphan = $ax_je_make( $ax_je_posts, $ax_je_calendar, 'Ownerless', array( 'join_mode' => 'free', 'join_eligibility' => 'followers' ) );
	/*
	 * Orphaned through both answers, because there are two now. An Event records the Actor it was
	 * published by, so clearing `post_author` alone no longer leaves the host unanswerable -- the
	 * recorded Actor would still be there, which is the point of recording it. This reaches the state
	 * the check is about: nothing published it and nobody wrote it.
	 */
	wp_update_post( array( 'ID' => $ax_je_orphan, 'post_author' => 0 ) );
	ax_je_clear_acting_actor( $ax_je_orphan );
	ax_je_assert(
		$ax_je_results,
		'an event whose owner has no Actor admits nobody rather than everybody',
		'' === axismundi_cal_event_owner_actor_uri( $ax_je_orphan )
			&& ! axismundi_cal_event_join_eligible( $ax_je_orphan, $ax_je_follower['actor_uri'] )
			&& is_wp_error( axismundi_cal_event_join( $ax_je_orphan, $ax_je_follower['actor_uri'] ) )
	);
	/*
	 * The same event with nothing else standing in the way. Opened to anyone while its author still
	 * had an Actor and orphaned afterwards, it is the one case the writer's guard cannot catch -- and
	 * with `public` eligibility there is no second rule to refuse it by accident, so this is what says
	 * the runtime check exists rather than the one above happening to cover for it.
	 */
	$ax_je_stranded = $ax_je_make( $ax_je_posts, $ax_je_calendar, 'Orphaned but open', array( 'join_mode' => 'free', 'join_eligibility' => 'public' ) );
	wp_update_post( array( 'ID' => $ax_je_stranded, 'post_author' => $ax_je_plain ) );
	ax_je_clear_acting_actor( $ax_je_stranded );
	$ax_je_stranded_error = axismundi_cal_event_join( $ax_je_stranded, $ax_je_follower['actor_uri'] );
	ax_je_assert(
		$ax_je_results,
		'an event open to anyone stops taking replies once its host has no Actor to answer with',
		axismundi_cal_event_join_eligible( $ax_je_stranded, $ax_je_follower['actor_uri'] )
			&& is_wp_error( $ax_je_stranded_error )
			&& 'ax_event_join_no_host' === $ax_je_stranded_error->get_error_code()
			&& ! ax_je_has_row( $ax_je_stranded, $ax_je_follower['actor_uri'] )
	);
	ax_je_assert(
		$ax_je_results,
		'and an event that does not exist is not a door standing open',
		! axismundi_cal_event_join_eligible( 0, $ax_je_follower['actor_uri'] )
	);

	// -- Narrowing later -----------------------------------------------------------------------------

	/*
	 * The stranger was admitted while the event was open to anyone, and narrowing it afterwards is not
	 * a reason to reach into what they were already told -- that is the organizer's decision to make.
	 * What it does stop is asking again, which is the transition the door is there to answer.
	 */
	axismundi_cal_event_save( $ax_je_open, array( 'join_eligibility' => 'followers' ) );
	ax_je_assert(
		$ax_je_results,
		'narrowing an open event afterwards does not revoke the answers already given',
		'accepted' === (string) axismundi_cal_event_participation( $ax_je_open, $ax_je_stranger['actor_uri'] )['state']
	);
	ax_je_assert(
		$ax_je_results,
		'but the same person can no longer renew that by asking again',
		is_wp_error( axismundi_cal_event_join( $ax_je_open, $ax_je_stranger['actor_uri'] ) )
	);

	// -- Kept while inert ----------------------------------------------------------------------------

	/*
	 * Closing an Event and opening it again must not widen it. Dropping the eligibility because the
	 * mode made it briefly meaningless would lose a restriction on the way back in, which is the one
	 * direction a forgotten setting is dangerous in.
	 */
	axismundi_cal_event_save( $ax_je_private_free, array( 'join_mode' => 'none' ) );
	axismundi_cal_event_save( $ax_je_private_free, array( 'join_mode' => 'free' ) );
	ax_je_assert(
		$ax_je_results,
		'an event closed and reopened is as narrow as it was, not as open as the default',
		'followers' === (string) axismundi_cal_event_get( $ax_je_private_free )['join_eligibility']
			&& is_wp_error( axismundi_cal_event_join( $ax_je_private_free, $ax_je_stranger['actor_uri'] ) )
	);

	// -- Reachable from the editor -------------------------------------------------------------------

	/*
	 * The recurring failure in this plugin has been a field that is stored, projected and impossible to
	 * set, so the surface a panel reads is asserted beside the rule rather than assumed from it.
	 */
	ax_je_assert(
		$ax_je_results,
		'the panel can read the eligibility and write it back',
		'followers' === (string) axismundi_cal_rest_envelope( $ax_je_private_free )['joinEligibility']
			&& array( 'join_eligibility' => 'followers' ) === axismundi_cal_rest_to_fields( array( 'joinEligibility' => 'followers' ) )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( $ax_je_posts as $ax_je_post ) {
		wp_delete_post( (int) $ax_je_post, true );
	}
	foreach ( $ax_je_calendars as $ax_je_cal ) {
		axismundi_cal_calendar_delete( (int) $ax_je_cal );
	}
	foreach ( $ax_je_users as $ax_je_user_id ) {
		wp_delete_user( (int) $ax_je_user_id );
	}
}

$ax_je_failures = count( array_filter( $ax_je_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_je_results ), $ax_je_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_je_failures > 0 ? 1 : 0 );
}
exit( $ax_je_failures > 0 ? 1 : 0 );
