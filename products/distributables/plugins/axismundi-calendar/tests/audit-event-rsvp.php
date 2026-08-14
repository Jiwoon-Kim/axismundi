<?php
/**
 * Saying you are coming (dev-only; dist-excluded).
 *
 * FEP-8a8e puts this on `Join`, answered with `Accept` or `Reject`, and `attendees` is the accepted
 * ones. Derived rather than kept: an editable list beside the replies would be a second answer to
 * the question the replies already settle, and the two would part company the first time somebody
 * withdrew.
 *
 * The rule this file exists for is the one that connects to the previous slice. A joining link kept
 * for attendees has to open when somebody is accepted -- being told "you are coming" and not where
 * to go makes acceptance mean nothing -- and must stay shut while the answer is pending, because
 * being told is what acceptance grants. The public document never carries it either way: it has no
 * Actor to be, and is read by everybody who has the calendar.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_rs_results   = array();
$ax_rs_posts     = array();
$ax_rs_calendars = array();
$ax_rs_users     = array();

/** @param bool[] $results Results. */
function ax_rs_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** A user with a public Person Actor. */
function ax_rs_user( array &$users ) : array {
	$login   = 'ax_rs_' . strtolower( wp_generate_password( 8, false, false ) );
	$id      = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$users[] = $id;
	$uri     = '';
	if ( function_exists( 'axismundi_actors_ensure_for_user' ) ) {
		$actor = axismundi_actors_ensure_for_user( $id );
		if ( $actor instanceof Axismundi_Actor ) {
			axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
			$uri = (string) $actor->get_uri();
		}
	}
	return array( 'user_id' => $id, 'actor_uri' => $uri );
}

try {
	$ax_rs_host    = ax_rs_user( $ax_rs_users );
	$ax_rs_guest   = ax_rs_user( $ax_rs_users );
	$ax_rs_bystand = ax_rs_user( $ax_rs_users );
	wp_set_current_user( $ax_rs_host['user_id'] );

	$ax_rs_suffix   = strtolower( wp_generate_password( 6, false, false ) );
	$ax_rs_calendar = (int) axismundi_cal_calendar_save(
		array( 'name' => 'RSVP fixture', 'slug' => 'ax-rs-' . $ax_rs_suffix, 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_rs_host['actor_uri'] )
	);
	$ax_rs_calendars[] = $ax_rs_calendar;
	axismundi_cal_acl_grant( $ax_rs_calendar, '', 'reader', 'public' );

	$ax_rs_make = static function ( array &$posts, int $calendar, string $title, array $fields = array() ) : int {
		$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_title' => $title, 'post_author' => get_current_user_id() ) );
		$posts[] = $post_id;
		axismundi_cal_event_save(
			$post_id,
			array_merge( array( 'calendar_id' => $calendar, 'timezone' => 'Asia/Seoul', 'starts_at' => '2026-10-10 19:00:00', 'ends_at' => '2026-10-10 21:00:00' ), $fields )
		);
		$GLOBALS['axismundi_cal_rest_write'] = true;
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		$GLOBALS['axismundi_cal_rest_write'] = false;
		return $post_id;
	};

	// -- What a policy allows --------------------------------------------------------------------------

	/*
	 * The policy is `join_mode`, which FEP-8a8e already names and this plugin already stored. A second
	 * "rsvp policy" field would be the same question asked twice, and the two would disagree the first
	 * time one of them was changed.
	 */
	$ax_rs_closed = $ax_rs_make( $ax_rs_posts, $ax_rs_calendar, 'Nothing to answer', array( 'join_mode' => 'none' ) );
	ax_rs_assert(
		$ax_rs_results,
		'an event taking no replies refuses one rather than storing it quietly',
		is_wp_error( axismundi_cal_event_join( $ax_rs_closed, $ax_rs_guest['actor_uri'] ) )
	);

	/*
	 * A policy inviting replies needs somewhere they can come from. A private Event cannot be found or
	 * read by an ordinary Actor, so opening it to replies is a promise with nothing behind it -- and it
	 * would read on screen as an event accepting people nobody can reach.
	 */
	$ax_rs_private = $ax_rs_make( $ax_rs_posts, $ax_rs_calendar, 'Private and closed', array( 'visibility' => 'private' ) );
	ax_rs_assert(
		$ax_rs_results,
		'a private event cannot be set to take replies, since nobody outside can find it',
		is_wp_error( axismundi_cal_event_save( $ax_rs_private, array( 'join_mode' => 'free' ) ) )
			&& is_wp_error( axismundi_cal_event_save( $ax_rs_private, array( 'join_mode' => 'restricted' ) ) )
	);
	/*
	 * `invite` is in the vocabulary and cannot be satisfied yet. Offering it would be the most confusing
	 * state available: a policy saying "invitation only" with no way to invite anybody.
	 */
	$ax_rs_invite = $ax_rs_make( $ax_rs_posts, $ax_rs_calendar, 'Invitation only', array( 'join_mode' => 'invite' ) );
	ax_rs_assert(
		$ax_rs_results,
		'invitation only is refused a reply until there is a way to be invited',
		is_wp_error( axismundi_cal_event_join( $ax_rs_invite, $ax_rs_guest['actor_uri'] ) )
	);

	// -- Free and restricted are the same activity ------------------------------------------------------

	$ax_rs_open       = $ax_rs_make( $ax_rs_posts, $ax_rs_calendar, 'Anyone may come', array( 'join_mode' => 'free' ) );
	$ax_rs_moderated  = $ax_rs_make( $ax_rs_posts, $ax_rs_calendar, 'Ask first', array( 'join_mode' => 'restricted' ) );

	ax_rs_assert(
		$ax_rs_results,
		'an open event accepts on arrival',
		'accepted' === axismundi_cal_event_join( $ax_rs_open, $ax_rs_guest['actor_uri'] )
	);
	ax_rs_assert(
		$ax_rs_results,
		'while a moderated one leaves the answer to somebody, using the same request',
		'pending' === axismundi_cal_event_join( $ax_rs_moderated, $ax_rs_guest['actor_uri'] )
	);
	ax_rs_assert(
		$ax_rs_results,
		'asking twice changes an answer rather than adding one',
		'pending' === axismundi_cal_event_join( $ax_rs_moderated, $ax_rs_guest['actor_uri'] )
			&& 1 === (int) $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture inspection.
				$wpdb->prepare( 'SELECT COUNT(*) FROM ' . axismundi_cal_participation_table() . ' WHERE event_post_id = %d', $ax_rs_moderated )
			)
	);

	// -- Attendees are the accepted ones ----------------------------------------------------------------

	ax_rs_assert(
		$ax_rs_results,
		'the people coming are the accepted replies rather than a list somebody keeps',
		1 === count( axismundi_cal_event_attendees( $ax_rs_open ) )
			&& 0 === count( axismundi_cal_event_attendees( $ax_rs_moderated ) )
	);
	axismundi_cal_event_participation_set( $ax_rs_moderated, $ax_rs_guest['actor_uri'], 'accepted' );
	ax_rs_assert(
		$ax_rs_results,
		'and answering a pending one adds them without anything else being edited',
		1 === count( axismundi_cal_event_attendees( $ax_rs_moderated ) )
	);
	/*
	 * `withdrawn` rather than a deleted row. Somebody who came and then could not is a different fact
	 * from somebody who never answered, and an organizer counting heads is entitled to the difference.
	 */
	axismundi_cal_event_participation_set( $ax_rs_moderated, $ax_rs_guest['actor_uri'], 'withdrawn' );
	ax_rs_assert(
		$ax_rs_results,
		'withdrawing removes them from the count while leaving the fact that they answered',
		0 === count( axismundi_cal_event_attendees( $ax_rs_moderated ) )
			&& is_array( axismundi_cal_event_participation( $ax_rs_moderated, $ax_rs_guest['actor_uri'] ) )
	);
	ax_rs_assert(
		$ax_rs_results,
		'while somebody turned down cannot simply ask again',
		( static function () use ( $ax_rs_moderated, $ax_rs_guest ) : bool {
			axismundi_cal_event_participation_set( $ax_rs_moderated, $ax_rs_guest['actor_uri'], 'rejected' );
			return is_wp_error( axismundi_cal_event_join( $ax_rs_moderated, $ax_rs_guest['actor_uri'] ) );
		} )()
	);

	// -- Which is what opens the joining link -----------------------------------------------------------

	/*
	 * The rule that connects this slice to the last one. A link kept for attendees opens when somebody
	 * is accepted and not before: being told is what acceptance grants, so granting it while the answer
	 * is open would make the answer pointless.
	 */
	$ax_rs_hybrid = $ax_rs_make(
		$ax_rs_posts,
		$ax_rs_calendar,
		'Hybrid with a private room',
		array(
			'join_mode' => 'restricted',
			'locations' => array(
				array( 'kind' => 'physical', 'label' => 'Main hall' ),
				array( 'kind' => 'virtual', 'features' => array( 'VIDEO' ), 'access' => 'attendees', 'label' => 'Speakers room', 'url' => 'https://meet.example.com/speakers' ),
			),
		)
	);
	axismundi_cal_event_join( $ax_rs_hybrid, $ax_rs_guest['actor_uri'] );

	$ax_rs_sees = static function ( array $who, int $post_id ) : int {
		$before = get_current_user_id();
		wp_set_current_user( $who['user_id'] );
		$count = count( array_filter(
			axismundi_cal_event_visible_locations( $post_id, $who['actor_uri'] ),
			static fn( array $l ) : bool => 'attendees' === (string) $l['access']
		) );
		wp_set_current_user( $before );
		return $count;
	};

	ax_rs_assert( $ax_rs_results, 'a pending reply does not open the link yet', 0 === $ax_rs_sees( $ax_rs_guest, $ax_rs_hybrid ) );
	axismundi_cal_event_participation_set( $ax_rs_hybrid, $ax_rs_guest['actor_uri'], 'accepted' );
	ax_rs_assert( $ax_rs_results, 'and accepting opens it, or acceptance would mean nothing', 1 === $ax_rs_sees( $ax_rs_guest, $ax_rs_hybrid ) );
	ax_rs_assert( $ax_rs_results, 'while somebody who never replied is still shown nothing', 0 === $ax_rs_sees( $ax_rs_bystand, $ax_rs_hybrid ) );
	axismundi_cal_event_participation_set( $ax_rs_hybrid, $ax_rs_guest['actor_uri'], 'withdrawn' );
	ax_rs_assert( $ax_rs_results, 'and it closes again when they withdraw', 0 === $ax_rs_sees( $ax_rs_guest, $ax_rs_hybrid ) );

	/*
	 * Never in the public document, whoever is accepted. That document has no Actor to be and is read
	 * by everybody who has the calendar, so a link published there is public regardless of who was
	 * entitled to it. An authenticated per-Actor feed is a different surface with a different writer.
	 */
	axismundi_cal_event_participation_set( $ax_rs_hybrid, $ax_rs_guest['actor_uri'], 'accepted' );
	ax_rs_assert(
		$ax_rs_results,
		'and the public feed carries it for nobody, since it has no Actor to be',
		! str_contains( (string) axismundi_cal_site_feed( $ax_rs_calendar, 'RSVP fixture', 'Asia/Seoul' )['body'], 'meet.example.com/speakers' )
	);

	// -- How many is derived too -------------------------------------------------------------------------

	/*
	 * The one principle worth taking from the plugins that came before: the people coming are counted
	 * from the replies, and how many more can come follows from that count. A stored counter beside the
	 * capacity would be a third answer to a question the replies already settle -- and it would go
	 * wrong in the direction nobody notices, letting one more person in.
	 *
	 * `maximum_attendee_capacity` was stored and projected here before anything counted against it,
	 * which is a limit that reads as enforced and is not.
	 */
	$ax_rs_small = $ax_rs_make(
		$ax_rs_posts,
		$ax_rs_calendar,
		'Room for one',
		array( 'join_mode' => 'free', 'maximum_attendee_capacity' => 1 )
	);
	ax_rs_assert(
		$ax_rs_results,
		'an empty capped event has its whole capacity left',
		1 === axismundi_cal_event_remaining_capacity( $ax_rs_small )
	);
	ax_rs_assert(
		$ax_rs_results,
		'and an uncapped one counts nobody, since there is nothing to count against',
		null === axismundi_cal_event_remaining_capacity( $ax_rs_open )
	);

	ax_rs_assert(
		$ax_rs_results,
		'the first reply is accepted and uses the place up',
		'accepted' === axismundi_cal_event_join( $ax_rs_small, $ax_rs_guest['actor_uri'] )
			&& 0 === axismundi_cal_event_remaining_capacity( $ax_rs_small )
	);
	ax_rs_assert(
		$ax_rs_results,
		'the next is turned away rather than quietly making the limit wrong',
		is_wp_error( axismundi_cal_event_join( $ax_rs_small, $ax_rs_bystand['actor_uri'] ) )
	);
	/*
	 * And it frees up again, because the count is the replies rather than a tally that only ever rose.
	 */
	axismundi_cal_event_participation_set( $ax_rs_small, $ax_rs_guest['actor_uri'], 'withdrawn' );
	ax_rs_assert(
		$ax_rs_results,
		'somebody withdrawing frees the place, which a counter that only counted up would not',
		1 === axismundi_cal_event_remaining_capacity( $ax_rs_small )
			&& 'accepted' === axismundi_cal_event_join( $ax_rs_small, $ax_rs_bystand['actor_uri'] )
	);

	/*
	 * The other door into the same room. An organizer accepting a pending reply makes an attendee too,
	 * so the limit has to hold there or it holds nowhere.
	 */
	$ax_rs_queue = $ax_rs_make(
		$ax_rs_posts,
		$ax_rs_calendar,
		'One place, moderated',
		array( 'join_mode' => 'restricted', 'maximum_attendee_capacity' => 1 )
	);
	axismundi_cal_event_join( $ax_rs_queue, $ax_rs_guest['actor_uri'] );
	axismundi_cal_event_join( $ax_rs_queue, $ax_rs_bystand['actor_uri'] );
	ax_rs_assert(
		$ax_rs_results,
		'a full event still records that somebody asked, which is a waiting list rather than a refusal',
		2 === (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture inspection.
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . axismundi_cal_participation_table() . ' WHERE event_post_id = %d', $ax_rs_queue )
		)
	);
	ax_rs_assert(
		$ax_rs_results,
		'accepting the first works and accepting the second is refused, since that is where the limit bites',
		true === axismundi_cal_event_participation_set( $ax_rs_queue, $ax_rs_guest['actor_uri'], 'accepted' )
			&& is_wp_error( axismundi_cal_event_participation_set( $ax_rs_queue, $ax_rs_bystand['actor_uri'], 'accepted' ) )
	);
	/*
	 * Re-accepting somebody already accepted must not be refused by a capacity they are part of. That
	 * is the off-by-one a naive check produces, and it appears as an organizer unable to re-save.
	 */
	ax_rs_assert(
		$ax_rs_results,
		'while re-accepting somebody already in is not refused by a place they are themselves taking',
		true === axismundi_cal_event_participation_set( $ax_rs_queue, $ax_rs_guest['actor_uri'], 'accepted' )
	);

	// -- The Event is named, not pointed at -------------------------------------------------------------

	/*
	 * The identity is the Event's own URI. Half of what belongs in this table has no local post: a reply
	 * somebody here sent to an Event on another site is theirs to remember and that site's to own, and
	 * a row keyed on a post id could not name it.
	 */
	$ax_rs_row = axismundi_cal_event_participation( $ax_rs_open, $ax_rs_guest['actor_uri'] );
	ax_rs_assert(
		$ax_rs_results,
		'a reply names the Event by its own identity rather than by a local row number',
		is_array( $ax_rs_row ) && axismundi_cal_event_uri( $ax_rs_open ) === (string) $ax_rs_row['event_uri']
	);
	ax_rs_assert(
		$ax_rs_results,
		'while the post id stays as a shortcut for the local case',
		is_array( $ax_rs_row ) && $ax_rs_open === (int) $ax_rs_row['event_post_id']
	);
	/*
	 * And a reply to somewhere else entirely, which is what the second screen reads. Nothing about it
	 * needs the Event to be cached here -- a cache makes the card richer, and its absence leaves the
	 * address and the state, which is still an answer.
	 */
	$ax_rs_elsewhere = 'https://elsewhere.example/?p=88';
	$wpdb->insert(
		axismundi_cal_participation_table(),
		array(
			'event_uri'      => $ax_rs_elsewhere,
			'event_uri_hash' => hash( 'sha256', $ax_rs_elsewhere ),
			'event_post_id'  => null,
			'actor_uri'      => $ax_rs_guest['actor_uri'],
			'actor_uri_hash' => hash( 'sha256', $ax_rs_guest['actor_uri'] ),
			'initiating_activity_uri' => 'https://example.test/activities/1',
			'state'          => 'accepted',
			'created_at'     => current_time( 'mysql', true ),
			'updated_at'     => current_time( 'mysql', true ),
		)
	);
	ax_rs_assert(
		$ax_rs_results,
		'a reply to an Event on another site is storable, with no post to point at',
		is_array( axismundi_cal_participation_by_uri( $ax_rs_elsewhere, $ax_rs_guest['actor_uri'] ) )
	);
	ax_rs_assert(
		$ax_rs_results,
		'and one Actor own replies read as one list, wherever the Events are',
		( static function () use ( $ax_rs_guest, $ax_rs_elsewhere ) : bool {
			$mine = array_column( axismundi_cal_actor_participations( $ax_rs_guest['actor_uri'] ), 'event_uri' );
			return in_array( $ax_rs_elsewhere, $mine, true ) && count( $mine ) > 1;
		} )()
	);
	/*
	 * The identity is what makes a second reply the same reply. Keyed on the post id, an Event named by
	 * URI and the same Event named by post would have been two rows.
	 */
	/*
	 * The case that tells the identity from the shortcut. A reply arriving over the wire names a local
	 * Event by its URI and has no reason to carry a post id -- and it has to be the same row a local
	 * lookup finds, or the same person would be able to reply twice and the Event would count them
	 * both. Asserting only that a locally-written row is findable proves nothing: it has both columns.
	 */
	$ax_rs_wire_uri = axismundi_cal_event_uri( $ax_rs_moderated );
	$wpdb->insert(
		axismundi_cal_participation_table(),
		array(
			'event_uri'      => $ax_rs_wire_uri,
			'event_uri_hash' => hash( 'sha256', $ax_rs_wire_uri ),
			// No post id, the way a reply from another site would arrive.
			'event_post_id'  => null,
			'actor_uri'      => $ax_rs_bystand['actor_uri'],
			'actor_uri_hash' => hash( 'sha256', $ax_rs_bystand['actor_uri'] ),
			'initiating_activity_uri' => 'https://elsewhere.example/activities/9',
			'state'          => 'pending',
			'created_at'     => current_time( 'mysql', true ),
			'updated_at'     => current_time( 'mysql', true ),
		)
	);
	ax_rs_assert(
		$ax_rs_results,
		'a reply naming a local Event by URI is the row a local lookup finds, shortcut or no shortcut',
		is_array( axismundi_cal_event_participation( $ax_rs_moderated, $ax_rs_bystand['actor_uri'] ) )
	);
	ax_rs_assert(
		$ax_rs_results,
		'so accepting it counts them once rather than leaving a second row to answer differently',
		true === axismundi_cal_event_participation_set( $ax_rs_moderated, $ax_rs_bystand['actor_uri'], 'accepted' )
			&& 1 === count( array_filter(
				axismundi_cal_event_attendees( $ax_rs_moderated ),
				static fn( array $a ) : bool => (string) $a['actor_uri'] === $ax_rs_bystand['actor_uri']
			) )
	);

	// -- Who started it -----------------------------------------------------------------------------

	/*
	 * `Join` is sent by the person coming and answered by the organizer; `Invite` is sent by the
	 * organizer and answered by the person. The two produce the same states with the roles mirrored --
	 * `rejected` is the responder declining and `withdrawn` is whoever started it taking it back -- so
	 * the row has to say which, or an approval screen cannot tell whose turn it is.
	 */
	ax_rs_assert(
		$ax_rs_results,
		'a reply somebody sent themselves records that they started it',
		'join' === (string) axismundi_cal_event_participation( $ax_rs_open, $ax_rs_guest['actor_uri'] )['source']
	);

	/*
	 * The guest list does not care which way round it began. Somebody who asked and was accepted and
	 * somebody who was invited and accepted are both coming, so a collection counting one of those
	 * would be a room half described. Asserted now, before invitations exist, because the shape is
	 * what makes adding them a new writer rather than a new reader.
	 */
	$ax_rs_invited_uri = axismundi_cal_event_uri( $ax_rs_open );
	$wpdb->insert(
		axismundi_cal_participation_table(),
		array(
			'event_uri'      => $ax_rs_invited_uri,
			'event_uri_hash' => hash( 'sha256', $ax_rs_invited_uri ),
			'event_post_id'  => $ax_rs_open,
			'actor_uri'      => $ax_rs_bystand['actor_uri'],
			'actor_uri_hash' => hash( 'sha256', $ax_rs_bystand['actor_uri'] ),
			'initiating_activity_uri' => 'https://example.test/activities/invite-1',
			'source'         => 'invite',
			'state'          => 'accepted',
			'created_at'     => current_time( 'mysql', true ),
			'updated_at'     => current_time( 'mysql', true ),
		)
	);
	$ax_rs_guests = axismundi_cal_event_attendees( $ax_rs_open );
	ax_rs_assert(
		$ax_rs_results,
		'and the guest list counts an accepted invitation beside an accepted request',
		2 === count( $ax_rs_guests )
			&& array( 'invite', 'join' ) === ( static function () use ( $ax_rs_guests ) : array {
				$sources = array_unique( array_column( $ax_rs_guests, 'source' ) );
				sort( $sources );
				return $sources;
			} )()
	);
	/*
	 * And the same acceptance opens the same door. A joining link kept for attendees cannot ask how
	 * somebody came to be one.
	 */
	ax_rs_assert(
		$ax_rs_results,
		'so an invited attendee sees what an accepted one sees',
		axismundi_cal_can_view_attendee_location( $ax_rs_bystand['actor_uri'], $ax_rs_open )
	);
	ax_rs_assert(
		$ax_rs_results,
		'while a source nothing defines is not one of the two this records',
		array( 'invite', 'join' ) === ( static function () : array {
			$sources = AXISMUNDI_CAL_PARTICIPATION_SOURCES;
			sort( $sources );
			return $sources;
		} )()
	);

	/*
	 * The privileges belong to whoever holds them. This predicate takes an Actor and also reads the
	 * logged-in user, and the two can be different people -- asked about a guest while an organizer is
	 * signed in, it answered with the organizer's entitlement and reported the guest as able to see a
	 * link kept for attendees.
	 */
	// An Event nobody has replied to, so the only thing that could grant it is the confusion itself.
	$ax_rs_untouched = $ax_rs_make( $ax_rs_posts, $ax_rs_calendar, 'Nobody has replied', array( 'join_mode' => 'restricted' ) );
	ax_rs_assert(
		$ax_rs_results,
		'an organizer being signed in does not make somebody else an attendee',
		false === axismundi_cal_can_view_attendee_location( $ax_rs_bystand['actor_uri'], $ax_rs_untouched )
			&& true === axismundi_cal_can_view_attendee_location( $ax_rs_host['actor_uri'], $ax_rs_untouched )
	);

	// -- A maybe has a direction ------------------------------------------------------------------------

	/*
	 * ActivityStreams has four answers, not two: `Accept`, `Reject`, and a tentative form of each.
	 * Folding the pair into one word loses which way somebody was leaning, and that is the half of a
	 * maybe anybody reads -- "probably coming" and "probably not" are not the same message.
	 */
	ax_rs_assert(
		$ax_rs_results,
		'a tentative answer keeps the direction it leans',
		in_array( 'tentative', AXISMUNDI_CAL_PARTICIPATION_STATES, true )
			&& in_array( 'tentative_rejected', AXISMUNDI_CAL_PARTICIPATION_STATES, true )
	);
	/*
	 * One state, not two. `TentativeAccept` and `PARTSTAT=TENTATIVE` lean the same way, so separating
	 * them would have recorded where an answer arrived from in the shape of what it said -- and where
	 * it arrived from is what the response activity answers.
	 */
	ax_rs_assert(
		$ax_rs_results,
		'and a tentative yes is one state however it arrived',
		! in_array( 'tentative_accept', AXISMUNDI_CAL_PARTICIPATION_STATES, true )
			&& 'TENTATIVE' === axismundi_cal_participation_partstat( 'tentative' )
	);

	/*
	 * Only a yes is a place taken. Every other answer -- including both maybes -- leaves the room and
	 * the joining link alone, which is what makes acceptance mean something.
	 */
	$ax_rs_maybe = $ax_rs_make(
		$ax_rs_posts,
		$ax_rs_calendar,
		'Maybes do not count',
		array( 'join_mode' => 'restricted', 'maximum_attendee_capacity' => 1 )
	);
	axismundi_cal_event_join( $ax_rs_maybe, $ax_rs_guest['actor_uri'] );
	foreach ( array( 'pending', 'tentative', 'tentative_rejected', 'rejected', 'withdrawn' ) as $ax_rs_state ) {
		axismundi_cal_event_participation_set( $ax_rs_maybe, $ax_rs_guest['actor_uri'], $ax_rs_state );
		ax_rs_assert(
			$ax_rs_results,
			sprintf( 'a %s answer is not somebody coming', str_replace( '_', ' ', $ax_rs_state ) ),
			0 === count( axismundi_cal_event_attendees( $ax_rs_maybe ) )
				&& 1 === axismundi_cal_event_remaining_capacity( $ax_rs_maybe )
				&& false === axismundi_cal_can_view_attendee_location( $ax_rs_guest['actor_uri'], $ax_rs_maybe )
		);
	}

	/*
	 * The reply an organizer would be sent by email, which is a decision rather than a lookup. `TENTATIVE`
	 * in iCalendar means tentatively *accepted*, so sending a tentative refusal as `TENTATIVE` would
	 * report the opposite of what was said -- `DECLINED` keeps the direction and loses the hesitancy,
	 * which is the safer half to lose.
	 */
	ax_rs_assert(
		$ax_rs_results,
		'a tentative yes travels as TENTATIVE and a tentative no does not, since that word means yes',
		'TENTATIVE' === axismundi_cal_participation_partstat( 'tentative' )
			&& 'DECLINED' === axismundi_cal_participation_partstat( 'tentative_rejected' )
	);
	ax_rs_assert(
		$ax_rs_results,
		'and an unanswered invitation is the one iCalendar calls NEEDS-ACTION',
		'NEEDS-ACTION' === axismundi_cal_participation_partstat( 'pending' )
	);

	/*
	 * Where the provenance points. An invitation begins with `Invite`, so a column named for `Join`
	 * would be describing itself wrongly from the next slice onward -- renamed now, while the only
	 * rows in it are the ones this file wrote.
	 */
	ax_rs_assert(
		$ax_rs_results,
		'the activity a row came from is named for what it holds rather than for one kind of it',
		is_array( $ax_rs_row ) && array_key_exists( 'initiating_activity_uri', $ax_rs_row )
			&& ! array_key_exists( 'join_activity_uri', $ax_rs_row )
	);

	/*
	 * Two activities, because the pair does not move together. What began the relationship never
	 * changes; what the state is currently reading is replaced each time somebody answers again. One
	 * column would have to forget an `Invite` in order to remember the `Reject` that followed it.
	 */
	$ax_rs_pairs = $ax_rs_make( $ax_rs_posts, $ax_rs_calendar, 'Answered twice', array( 'join_mode' => 'restricted' ) );
	axismundi_cal_event_join( $ax_rs_pairs, $ax_rs_guest['actor_uri'] );
	ax_rs_assert(
		$ax_rs_results,
		'nothing has answered a pending request, which is what an empty response means',
		null === axismundi_cal_event_participation( $ax_rs_pairs, $ax_rs_guest['actor_uri'] )['current_response_activity_uri']
	);
	axismundi_cal_event_participation_set( $ax_rs_pairs, $ax_rs_guest['actor_uri'], 'accepted', 'https://example.test/activities/accept-1' );
	axismundi_cal_event_participation_set( $ax_rs_pairs, $ax_rs_guest['actor_uri'], 'rejected', 'https://example.test/activities/reject-1' );
	$ax_rs_pair_row = axismundi_cal_event_participation( $ax_rs_pairs, $ax_rs_guest['actor_uri'] );
	ax_rs_assert(
		$ax_rs_results,
		'answering again moves the response without disturbing what began it',
		'https://example.test/activities/reject-1' === (string) $ax_rs_pair_row['current_response_activity_uri']
			&& 'join' === (string) $ax_rs_pair_row['source']
	);
	/*
	 * And back to nothing when the state returns to waiting. Leaving the old answer there would have
	 * the row explaining itself with an activity it is no longer showing.
	 */
	axismundi_cal_event_participation_set( $ax_rs_pairs, $ax_rs_guest['actor_uri'], 'pending' );
	ax_rs_assert(
		$ax_rs_results,
		'and returning to waiting clears it rather than leaving an answer the state is not showing',
		null === axismundi_cal_event_participation( $ax_rs_pairs, $ax_rs_guest['actor_uri'] )['current_response_activity_uri']
	);
	/*
	 * A reply that arrived by email has no activity to point at. Inventing one would put a local
	 * fiction in the column whose whole job is saying where an answer came from.
	 */
	axismundi_cal_event_participation_set( $ax_rs_pairs, $ax_rs_guest['actor_uri'], 'tentative' );
	ax_rs_assert(
		$ax_rs_results,
		'while an answer from outside ActivityPub simply has none',
		'tentative' === (string) axismundi_cal_event_participation( $ax_rs_pairs, $ax_rs_guest['actor_uri'] )['state']
			&& null === axismundi_cal_event_participation( $ax_rs_pairs, $ax_rs_guest['actor_uri'] )['current_response_activity_uri']
	);

	// -- Changing the Event does not re-ask anybody ------------------------------------------------------

	/*
	 * An `Update(Event)` tells the people already invited that something moved. It is not a second
	 * invitation, and it must not reset what they answered: a new `Invite` to somebody who already
	 * replied reads on their side as a duplicate, or worse as their answer having been discarded.
	 *
	 * Nothing in the writer touches these rows today, which is the state this pins -- the rule is easy
	 * to keep by accident and easy to lose the day somebody adds a notification to the save path.
	 */
	$ax_rs_moved = $ax_rs_make( $ax_rs_posts, $ax_rs_calendar, 'Moved afterwards', array( 'join_mode' => 'free' ) );
	axismundi_cal_event_join( $ax_rs_moved, $ax_rs_guest['actor_uri'] );
	$ax_rs_before_update = axismundi_cal_event_participation( $ax_rs_moved, $ax_rs_guest['actor_uri'] );

	axismundi_cal_event_save(
		$ax_rs_moved,
		array(
			'starts_at' => '2026-10-11 19:00:00',
			'ends_at'   => '2026-10-11 21:00:00',
			'locations' => array( array( 'kind' => 'physical', 'label' => 'A different room' ) ),
		)
	);
	$ax_rs_after_update = axismundi_cal_event_participation( $ax_rs_moved, $ax_rs_guest['actor_uri'] );
	ax_rs_assert(
		$ax_rs_results,
		'moving an Event leaves what people answered exactly where it was',
		is_array( $ax_rs_after_update )
			&& (string) $ax_rs_before_update['state'] === (string) $ax_rs_after_update['state']
			&& (int) $ax_rs_before_update['id'] === (int) $ax_rs_after_update['id']
	);
	ax_rs_assert(
		$ax_rs_results,
		'and the guest list with it, since a change of room is not a change of mind',
		1 === count( axismundi_cal_event_attendees( $ax_rs_moved ) )
	);
	/*
	 * The sequence does rise, which is the part a subscriber is meant to notice. Telling them the Event
	 * changed and asking them to answer again are different messages.
	 */
	ax_rs_assert(
		$ax_rs_results,
		'though the Event itself is marked as changed, which is what a subscriber is told',
		(int) axismundi_cal_schedule_for_event( $ax_rs_moved )['sequence'] > 0
	);

	// -- Only for yourself ------------------------------------------------------------------------------

	ax_rs_assert(
		$ax_rs_results,
		'a reply needs an Actor to be from',
		is_wp_error( axismundi_cal_event_join( $ax_rs_open, '' ) )
	);
	ax_rs_assert(
		$ax_rs_results,
		'and a state nothing defines is refused rather than stored',
		is_wp_error( axismundi_cal_event_participation_set( $ax_rs_open, $ax_rs_guest['actor_uri'], 'maybe' ) )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( $ax_rs_posts as $ax_rs_post ) {
		wp_delete_post( (int) $ax_rs_post, true );
	}
	foreach ( $ax_rs_calendars as $ax_rs_cal ) {
		axismundi_cal_calendar_delete( (int) $ax_rs_cal );
	}
	foreach ( $ax_rs_users as $ax_rs_user_id ) {
		wp_delete_user( (int) $ax_rs_user_id );
	}
}

$ax_rs_failures = count( array_filter( $ax_rs_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_rs_results ), $ax_rs_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_rs_failures > 0 ? 1 : 0 );
}
exit( $ax_rs_failures > 0 ? 1 : 0 );
