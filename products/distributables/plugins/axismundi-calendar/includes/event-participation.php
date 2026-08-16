<?php
/**
 * Who is coming, and how they said so.
 *
 * FEP-8a8e puts this on `Join`: an Actor sends one at an Event, and the Event's owner answers with
 * `Accept` or `Reject`. `attendees` is then the accepted ones -- a derived collection rather than an
 * array somebody edits, because an editable list beside the activities would be a second answer to
 * the question the activities already settle.
 *
 * The policy is `join_mode`, which this plugin already stores and which FEP-8a8e already names. A
 * second "rsvp policy" field would be the same question asked twice, and the two would disagree the
 * first time one of them was changed.
 *
 * Keyed on the Event's Object URI, not on a local post. Half of what belongs here has no post: a
 * reply somebody on this site sent to an Event on another one is theirs to remember and that site's
 * to own, and the two questions -- who asked to come to mine, what have I asked to come to -- read
 * from the same rows. The post id stays as a shortcut for the local half.
 *
 * A remote Event does not have to be cached for any of that. The cache makes a card richer; the
 * relationship is the URI, and deleting the cache does not delete what somebody said.
 *
 * Local Actors only, for now. Nothing here receives a `Join` over the wire or sends an `Accept` back;
 * what it does is settle the states and the permission they carry, so that the federation adapter
 * has something to project rather than a model invented alongside it.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * What one Actor's participation can be.
 *
 * ActivityStreams has four answers, not two: `Accept`, `Reject`, and a tentative form of each. Folding
 * the tentative pair into one word loses which way somebody was leaning, and that is the half of a
 * maybe anybody cares about -- an organizer reads "probably coming" and "probably not" very
 * differently.
 *
 * `tentative` is one state and not two. `TentativeAccept` and iCalendar's `PARTSTAT=TENTATIVE` lean
 * the same way -- both mean probably coming -- so keeping them apart would have been recording where
 * an answer arrived from in the shape of what it said. Where it arrived from is what
 * `current_response_activity_uri` answers, and a reply that came by email simply has none.
 *
 * `tentative_rejected` stays on its own because nothing else means it. iCalendar has no word for
 * probably-not, which is why it is the one state whose email projection has to lose something.
 *
 * `withdrawn` belongs to `Undo(Join)` alone: taking back a request you sent. Somebody who was invited
 * does not withdraw, they answer again -- a later `Reject(Invite)` replaces an earlier `Accept`, and
 * the activities stay in the ledger as the history of that.
 *
 * `removed` is the organizer ending somebody's attendance, and it is nobody's answer. `rejected` is
 * the organizer refusing a request that was never granted and `withdrawn` is the guest taking their
 * own back; this is the one that ends something that was already agreed, against the wish of the
 * person it belongs to. Writing it as any of the other three would put words in somebody's mouth --
 * a guest shown as having declined an Event they were thrown out of.
 *
 * Only `accepted` counts. Attendees, capacity and the locations kept for the people coming all read
 * that one word, because a maybe is not a place taken.
 */
const AXISMUNDI_CAL_PARTICIPATION_STATES = array(
	'pending',
	'accepted',
	'tentative',
	'tentative_rejected',
	'rejected',
	'withdrawn',
	'removed',
);

/**
 * The `PARTSTAT` one state becomes in an iCalendar reply.
 *
 * Lossy in one direction on purpose. iCalendar's `TENTATIVE` means tentatively *accepted*, so sending
 * a tentative refusal as `TENTATIVE` would tell an organizer the opposite of what was said. `DECLINED`
 * keeps the direction and loses the hesitancy, which is the safer half to lose.
 *
 * Nothing consumes this yet: the public feed carries no `ATTENDEE` at all, so the only surface that
 * could is an iTIP reply, which does not exist. It is written down here because the mapping is a
 * decision rather than a lookup, and deciding it beside the states is where it stays true.
 *
 * @param string $state Participation state.
 * @return string
 */
function axismundi_cal_participation_partstat( string $state ) : string {
	$map = array(
		'pending'            => 'NEEDS-ACTION',
		'accepted'           => 'ACCEPTED',
		'tentative'          => 'TENTATIVE',
		'tentative_rejected' => 'DECLINED',
		'rejected'           => 'DECLINED',
		'withdrawn'          => 'DECLINED',
		// Lossy in the same direction and for the same reason: iCalendar has no word for having been
		// taken off the list, and `DECLINED` at least does not claim they are coming.
		'removed'            => 'DECLINED',
	);
	return $map[ $state ] ?? 'NEEDS-ACTION';
}

/**
 * Who started it.
 *
 * `Join` is sent by the person coming and answered by the organizer. `Invite` is sent by the
 * organizer and answered by the person -- ActivityStreams makes it a kind of `Offer`, and the answer
 * is an `Accept` of that activity either way.
 *
 * The states are the same and the roles are mirrored, which is the whole reason this is stored:
 * `rejected` is the responder declining and `withdrawn` is whoever started it taking it back, and
 * only this says which of the two each of those names.
 */
const AXISMUNDI_CAL_PARTICIPATION_SOURCES = array( 'join', 'invite' );

/** The join modes under which an Actor may ask to come at all. */
const AXISMUNDI_CAL_JOINABLE_MODES = array( 'free', 'restricted', 'invite' );

/**
 * Who is allowed to ask, which is a different question from how the asking is answered.
 *
 * Two values and not four, because the other two are already said elsewhere. `nobody` is what
 * `join_mode` of `none` or `invite` means -- an Event admitting no requests, or only ones it started
 * -- and a second way to say it would be a pair of settings that can contradict each other. An
 * allowlist waits for delegation to exist: a list of Actors nobody can yet be added to is a policy
 * with no way to satisfy it.
 *
 * Measured against the Event's owning Actor alone. Several people can hold `manage_items` on the
 * Calendar, and "followers" resolved across all of them would be a different set depending on who
 * last edited the post -- the one thing an eligibility rule must not be. When co-hosting needs its
 * own answer, it arrives as an explicit eligibility principal rather than by widening this.
 */
const AXISMUNDI_CAL_JOIN_ELIGIBILITIES = array( 'public', 'followers' );

/** @return string Participation table name. */
function axismundi_cal_participation_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_cal_event_participation';
}

/**
 * The Object URI one local Event is known by.
 *
 * Object Projections owns this identity and answers when it is installed. The fallback is the same
 * expression rather than a different one, because an identity that changed with the plugin list
 * would be a different Object to every peer holding the old one -- and this plugin is required to
 * work with that one absent.
 *
 * @param int $post_id Event post ID.
 * @return string
 */
function axismundi_cal_event_uri( int $post_id ) : string {
	if ( $post_id <= 0 ) {
		return '';
	}
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post ) {
		return '';
	}
	if ( function_exists( 'axismundi_op_post_object_uri' ) ) {
		return (string) axismundi_op_post_object_uri( $post );
	}
	return (string) add_query_arg( 'p', $post_id, home_url( '/' ) );
}

/**
 * One Actor's participation in one Event, by the Event's own identity.
 *
 * The form the local one is built on, because half of what belongs in this table has no local post:
 * a reply somebody here sent to an Event on another site is theirs to remember and that site's to
 * own. Whether the Event is cached locally is a question about what can be displayed, not about
 * whether the relationship exists.
 *
 * @param string $event_uri Event Object URI.
 * @param string $actor_uri Actor URI.
 * @return array<string,mixed>|null
 */
function axismundi_cal_participation_by_uri( string $event_uri, string $actor_uri ) : ?array {
	global $wpdb;
	$event_uri = trim( $event_uri );
	$actor_uri = trim( $actor_uri );
	if ( '' === $event_uri || '' === $actor_uri || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_participation_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE event_uri_hash = %s AND actor_uri_hash = %s",
			hash( 'sha256', $event_uri ),
			hash( 'sha256', $actor_uri )
		),
		ARRAY_A
	);
	return is_array( $row ) ? $row : null;
}

/**
 * Everything one Actor has replied to, wherever it is.
 *
 * What a person's own list of replies reads from: their Events and other people's alike, since the
 * row names an Event rather than pointing at a post. A caller wanting to show more than the address
 * asks the remote object cache, and gets nothing when there is none -- which is a thinner card, not
 * a missing row.
 *
 * @param string $actor_uri Actor URI.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_actor_participations( string $actor_uri ) : array {
	global $wpdb;
	$actor_uri = trim( $actor_uri );
	if ( '' === $actor_uri || ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_participation_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (array) $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE actor_uri_hash = %s ORDER BY updated_at DESC, id DESC", hash( 'sha256', $actor_uri ) ),
		ARRAY_A
	);
}

/**
 * One Actor's participation in one Event, or null.
 *
 * @param int    $post_id   Event post ID.
 * @param string $actor_uri Actor URI.
 * @return array<string,mixed>|null
 */
function axismundi_cal_event_participation( int $post_id, string $actor_uri ) : ?array {
	// Resolved to the identity rather than queried on the shortcut, so a local Event and the same Event
	// named by URI cannot answer differently.
	return axismundi_cal_participation_by_uri( axismundi_cal_event_uri( $post_id ), $actor_uri );
}

/**
 * The Actor an Event belongs to, for the purposes of asking who follows it.
 *
 * The post author's Actor, deliberately -- not whoever can edit the Calendar. Editors come and go and
 * there can be several, and an eligibility rule that resolved to a different set of followers
 * depending on which of them touched the post last would be unanswerable to the person it turns away.
 *
 * Empty when the author has no Actor, and callers must read that as a closed door rather than an
 * open one: an Event whose owner cannot be named has no followers to check against, and treating
 * "unknown" as "everyone" is the failure that lets exactly the wrong person in.
 *
 * @param int $post_id Event post ID.
 * @return string
 */
function axismundi_cal_event_owner_actor_uri( int $post_id ) : string {
	/*
	 * The Actor the Event was published by, which is who a Join is addressed to and who an Invite
	 * comes from. It used to be derived from the WordPress account that wrote it -- correct only while
	 * everybody published as themselves, and wrong the moment somebody posts on an Organization's
	 * behalf: the guest would be asking a person to admit them to an event the organisation is
	 * running. The derivation survives inside the resolver as the answer for Events written before
	 * there was anywhere to record the choice.
	 */
	return axismundi_cal_event_acting_actor_uri( $post_id );
}

/**
 * Whether one Actor is allowed to ask to come at all.
 *
 * The entrance, which is a separate question from what happens to a request once it is made. An
 * Event that accepts on arrival has no later step at which somebody could be turned away, so this is
 * the only place an uninvited guest is stopped -- and it therefore has to answer before the reply is
 * recorded, not after.
 *
 * Fails closed in every direction it cannot answer. A missing Event, an unknown eligibility value, an
 * owner with no Actor, and a site without the Activities plugin all return false, because each of
 * them is a question about who follows whom that nothing here can answer -- and the safe reading of
 * an unanswerable restriction is that it restricts.
 *
 * The owner is eligible for their own Event. They are not among their own followers, and a rule that
 * kept the host out of the guest list would be arithmetic rather than policy.
 *
 * `pending` follows do not count. Somebody who has asked to follow and not been accepted is not a
 * follower yet, and reading the request as the relationship would let the follow approval an Actor
 * deliberately withheld be bypassed by a Join.
 *
 * @param int    $post_id   Event post ID.
 * @param string $actor_uri Asking Actor.
 * @return bool
 */
function axismundi_cal_event_join_eligible( int $post_id, string $actor_uri ) : bool {
	$actor_uri = trim( $actor_uri );
	$envelope  = axismundi_cal_event_get( $post_id );
	if ( '' === $actor_uri || ! is_array( $envelope ) ) {
		return false;
	}
	$eligibility = (string) ( $envelope['join_eligibility'] ?? 'public' );
	if ( 'public' === $eligibility ) {
		return true;
	}
	if ( 'followers' !== $eligibility ) {
		return false;
	}
	$owner = axismundi_cal_event_owner_actor_uri( $post_id );
	if ( '' === $owner ) {
		return false;
	}
	if ( $owner === $actor_uri ) {
		return true;
	}
	if ( ! function_exists( 'axismundi_act_get_relation' ) ) {
		return false;
	}
	// The asking Actor is the subject of a Follow whose object is the owner: they follow, not the
	// reverse. An Event's host following somebody is not that person's invitation to attend.
	$relation = axismundi_act_get_relation( 'follow', $actor_uri, $owner );
	return is_array( $relation ) && 'accepted' === (string) $relation['state'];
}

/**
 * Record one participation Activity and hand back the URI it is known by.
 *
 * Every state below moves by writing one of these first. The row is a projection kept so a screen
 * does not have to replay the ledger to draw a list; the Activity is what happened, and a state
 * changed without one would be a fact with no author, no time and nothing for a later `Undo` to
 * address.
 *
 * Written before the row on purpose. A failure here must leave nothing behind, and the opposite
 * order fails the other way: a row saying somebody is coming with no record of them saying so. An
 * Activity whose row then fails to write is a request that was made and not projected, which is
 * recoverable and visible; the reverse is neither.
 *
 * `$source_event_key` makes a repeated submission return the Activity the first one recorded rather
 * than minting a second. Local Activity URIs are freshly generated per call, so without it a double
 * click is two `Join`s in the ledger for one intention.
 *
 * @param array<string,mixed> $payload          ActivityStreams payload.
 * @param string              $source_event_key Stable identity for the thing that caused it.
 * @return string|WP_Error Activity URI.
 */
function axismundi_cal_participation_activity( array $payload, string $source_event_key = '' ) {
	if ( ! axismundi_cal_has_activities() ) {
		return new WP_Error( 'ax_event_ledger', __( 'Replies need the Activities plugin, which records what was said.', 'axismundi-calendar' ), array( 'status' => 503 ) );
	}
	$activity = '' !== $source_event_key
		? axismundi_act_record_source_activity( $payload, 'local', $source_event_key )
		: axismundi_act_record_activity( $payload, 'local' );
	if ( is_wp_error( $activity ) ) {
		return $activity;
	}
	return (string) $activity->get_uri();
}

/**
 * Ask to come.
 *
 * The answer depends on what the Event asked for, and both answers are the same activity: `free`
 * accepts on arrival, `restricted` leaves it pending for somebody to decide. Storing the difference
 * as two kinds of request would make the organizer's later `Accept` mean two things.
 *
 * @param int    $post_id   Event post ID.
 * @param string $actor_uri Actor URI, which must be the one asking.
 * @return string|WP_Error Resulting state.
 */
function axismundi_cal_event_join( int $post_id, string $actor_uri ) {
	global $wpdb;
	$actor_uri = trim( $actor_uri );
	if ( '' === $actor_uri ) {
		return new WP_Error( 'ax_event_join_actor', __( 'Only an Actor can say they are coming.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$envelope = axismundi_cal_event_get( $post_id );
	if ( ! is_array( $envelope ) ) {
		return new WP_Error( 'ax_event_join_missing', __( 'That event does not exist.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	// An Event that has been called off makes no arrangements, whatever its mode still says.
	$cancelled = axismundi_cal_event_cancellation_block( $post_id );
	if ( null !== $cancelled ) {
		return $cancelled;
	}
	$mode = (string) $envelope['join_mode'];
	if ( ! in_array( $mode, AXISMUNDI_CAL_JOINABLE_MODES, true ) ) {
		return new WP_Error( 'ax_event_join_closed', __( 'This event is not taking replies.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	/*
	 * The host counting themselves in.
	 *
	 * An `Invite` to oneself would be theatre -- a request and an answer between the same Actor,
	 * establishing nothing that was not already true -- so this is a `Join`, and the point of it is the
	 * list and the count rather than access. Somebody who runs an event and does not attend it is
	 * ordinary, which is why being the organizer does not put them among the attendees by itself.
	 *
	 * The eligibility question does not apply to them. `followers` measures against this Actor, and
	 * nobody follows themselves, so the host would be turned away from their own event by arithmetic.
	 * `invite` is the same shape of answer: an Event admitting only people it invited is not a rule
	 * about whether its organizer may attend.
	 */
	$owner = axismundi_cal_event_owner_actor_uri( $post_id );
	/*
	 * Participation is a relationship between Actors, on both ends. `Join`, `Invite`, `Accept` and
	 * `Reject` all have an Actor as their subject, so an Event whose host is not one has nobody to
	 * answer a request -- and admitting people to it would leave rows attributed to a party that
	 * cannot be addressed, which is not something a later Actor could be retrofitted into.
	 *
	 * The writer refuses this combination, so reaching it means the host's Actor went away after the
	 * fact. Closed rather than left open, because the alternative is an Event still taking replies on
	 * behalf of somebody who can no longer receive them.
	 */
	if ( '' === $owner ) {
		return new WP_Error( 'ax_event_join_no_host', __( 'This event has no host Actor to reply to.', 'axismundi-calendar' ), array( 'status' => 403 ) );
	}
	$is_self = $owner === $actor_uri;
	/*
	 * Somewhere it can be found. An Event nobody outside can read has no way for an Actor to discover
	 * it or to be told their reply was taken, so a policy inviting replies to one is a promise with
	 * nothing behind it.
	 *
	 * The host is asking about their own Event, which is a different question. Public listing is what
	 * makes an Event reachable by a stranger; it is not what makes it the host's to act on. Reading
	 * the listing gate as an authority gate would keep somebody off the guest list of an event they
	 * run, because the calendar it is on is for members -- a confusion between who can find a thing
	 * and who owns it.
	 */
	$reachable = $is_self
		? axismundi_cal_event_post_viewable( get_post( $post_id ) )
		: axismundi_cal_event_listable( get_post( $post_id ) );
	if ( ! $reachable ) {
		return new WP_Error( 'ax_event_join_unreachable', __( 'An event nobody can see cannot be replied to.', 'axismundi-calendar' ), array( 'status' => 403 ) );
	}
	// `invite` waits for the invitation to exist. Offering it now would be a policy nobody could satisfy.
	if ( 'invite' === $mode && ! $is_self ) {
		return new WP_Error( 'ax_event_join_invite', __( 'This event is invitation only, and invitations are not available yet.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	/*
	 * Before the row, and before the state is chosen. An Event that accepts on arrival turns the next
	 * few lines into an attendee, so an eligibility check placed after them would be reading a door
	 * somebody had already walked through. It also runs ahead of the existing-row branch, so an Event
	 * narrowed to followers stops somebody who joined while it was open from renewing that.
	 */
	if ( ! $is_self && ! axismundi_cal_event_join_eligible( $post_id, $actor_uri ) ) {
		return new WP_Error( 'ax_event_join_ineligible', __( 'This event is only open to people who follow the host.', 'axismundi-calendar' ), array( 'status' => 403 ) );
	}

	/*
	 * `free` is not a mode without a handshake -- it is one where the server answers immediately. FEP
	 * puts an Event's attendees behind an `Accept(Join)` either way, so an open Event accepts on
	 * arrival rather than skipping the step.
	 */
	$state = ( 'free' === $mode || $is_self ) ? 'accepted' : 'pending';
	/*
	 * Capacity is not consulted here. Deciding there is room and then writing the row are two steps
	 * with a gap between them, and two people arriving at an Event with one place left would both
	 * read the count before either wrote -- so the count is taken under a lock, in the same section
	 * that seats them. A moderated request takes no place at all: it is a waiting list, and letting
	 * requests hold seats would let a few dozen of them close an Event nobody had been admitted to.
	 */
	$existing  = axismundi_cal_event_participation( $post_id, $actor_uri );
	$event_uri = axismundi_cal_event_uri( $post_id );

	if ( is_array( $existing ) && 'rejected' === (string) $existing['state'] ) {
		/*
		 * Asking again after being turned down does not undo the answer. Somebody who withdrew may
		 * change their mind, which is the one transition a second `Join` legitimately makes.
		 */
		return new WP_Error( 'ax_event_join_rejected', __( 'That reply was already answered.', 'axismundi-calendar' ), array( 'status' => 403 ) );
	}
	if ( is_array( $existing ) && 'removed' === (string) $existing['state'] ) {
		/*
		 * And somebody the organizer took off the list cannot put themselves back on it. On an Event
		 * that admits people on arrival a second `Join` would otherwise reverse the removal within
		 * seconds, which would make removing anybody impossible rather than merely temporary. The way
		 * back is another invitation, which is the organizer's to send.
		 */
		return new WP_Error( 'ax_event_join_removed', __( 'You were taken off this event\'s list.', 'axismundi-calendar' ), array( 'status' => 403 ) );
	}

	/*
	 * A fresh `Join`, keyed so that submitting twice is one request.
	 *
	 * Somebody who withdrew and came back is making a new request, not reviving the old one -- their
	 * first `Join` has an `Undo` addressed to it, and reusing it would contradict that. Keying the
	 * second on the first gives it its own identity while keeping the double-click idempotent, which
	 * a bare event-and-actor key could not do without handing back the undone one.
	 *
	 * Only a retracted request earns a new one. Somebody whose request still stands and who asks
	 * again has not made a second request -- a reloaded page usually -- and minting a `Join` for that
	 * would put one Activity in the ledger per attempt rather than per intention.
	 */
	$previous = is_array( $existing ) && 'withdrawn' === (string) $existing['state']
		? (string) $existing['initiating_activity_uri']
		: '';
	$key      = '' !== $previous
		? 'ax-cal-rejoin:' . $previous
		: 'ax-cal-join:' . $event_uri . ':' . $actor_uri;
	$join_uri = axismundi_cal_participation_activity(
		array( 'type' => 'Join', 'actor' => $actor_uri, 'object' => $event_uri ),
		$key
	);
	if ( is_wp_error( $join_uri ) ) {
		return $join_uri;
	}

	$seated = axismundi_cal_participation_seat( $post_id, $actor_uri, $state, $join_uri );
	if ( is_wp_error( $seated ) ) {
		return $seated;
	}

	/*
	 * The server answering on arrival. `free` and a host counting themselves in are both an immediate
	 * `Accept`, recorded rather than implied: FEP-8a8e defines the `attendees` collection as the Joins
	 * that were answered with one, so a state of `accepted` with no `Accept` anywhere would be a
	 * conclusion with no premise and nothing for a peer to read. What separates it from a moderated
	 * Event is not whether the Accept exists but whether a person made it, which is why a screen says
	 * "accepted automatically" rather than naming somebody.
	 *
	 * After the seat, because an `Accept` published for a place that turned out not to exist is the
	 * one order of events with nothing to undo it.
	 */
	if ( 'accepted' === $state ) {
		$accepted = axismundi_cal_participation_activity(
			array( 'type' => 'Accept', 'actor' => $owner, 'object' => $join_uri ),
			'ax-cal-accept:' . $join_uri
		);
		if ( is_wp_error( $accepted ) ) {
			return $accepted;
		}
		axismundi_cal_participation_note_response( $post_id, $actor_uri, $accepted );
	}
	/*
	 * The organizer, either way, and the two are different news. A request is something they have to
	 * answer; an arrival on an open Event is something they only need to know about -- and a host
	 * counting themselves in is neither, which the emitter drops because it is their own act.
	 */
	axismundi_cal_notify(
		'accepted' === $state ? 'event_joined' : 'event_join_requested',
		$post_id,
		array( $owner ),
		$actor_uri,
		$join_uri
	);
	return $state;
}

/**
 * Write one participation row, taking a seat atomically if the state needs one.
 *
 * The seat and the state have to be decided together. Counting the accepted replies, deciding there
 * is room, and then writing a row is three steps with two gaps in them -- and two people arriving at
 * an Event with one place left will both read the count before either writes, so both are seated and
 * the capacity has been exceeded by exactly the number of people who asked at once. The count is
 * therefore taken under a lock held until the row is written.
 *
 * `pending` takes no seat. A moderated Event is a waiting list, and letting requests hold places
 * would let a few dozen of them close an Event nobody has been admitted to -- so capacity is checked
 * where somebody is actually seated, which is the acceptance rather than the asking.
 *
 * Somebody already accepted is not counted against themselves. Re-accepting them would otherwise be
 * refused by a capacity they are part of.
 *
 * No Activity is recorded in here. The ledger runs its own transaction, and a nested `COMMIT` would
 * end this one early -- which is the sort of bug that only appears under the concurrency this
 * function exists to survive.
 *
 * @param int         $post_id   Event post ID.
 * @param string      $actor_uri Whose participation.
 * @param string      $state     State to write.
 * @param string|null $join_uri  Initiating Activity URI, or null to keep what is there.
 * @return string|WP_Error State written.
 */
function axismundi_cal_participation_seat( int $post_id, string $actor_uri, string $state, ?string $join_uri = null, string $source = 'join' ) {
	global $wpdb;
	$envelope = axismundi_cal_event_get( $post_id );
	if ( ! is_array( $envelope ) ) {
		return new WP_Error( 'ax_event_seat_missing', __( 'That event does not exist.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	$table     = axismundi_cal_participation_table();
	$event_uri = axismundi_cal_event_uri( $post_id );
	$event_key = hash( 'sha256', $event_uri );
	$actor_key = hash( 'sha256', $actor_uri );
	$capacity  = null === $envelope['maximum_attendee_capacity'] ? null : (int) $envelope['maximum_attendee_capacity'];
	$now       = current_time( 'mysql', true );

	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the seat and the row are one decision.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
	$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE event_uri_hash = %s AND actor_uri_hash = %s FOR UPDATE", $event_key, $actor_key ), ARRAY_A );
	if ( 'accepted' === $state && null !== $capacity && ( ! is_array( $existing ) || 'accepted' !== (string) $existing['state'] ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
		$taken = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE event_uri_hash = %s AND state = 'accepted' FOR UPDATE", $event_key ) );
		if ( $taken >= $capacity ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return new WP_Error( 'ax_event_join_full', __( 'This event is full.', 'axismundi-calendar' ), array( 'status' => 409 ) );
		}
	}
	/*
	 * The response is cleared, not carried. Whatever answered the previous state is not what answers
	 * this one, and the Activity that does is recorded once the seat is real -- an `Accept` published
	 * for a place that turned out not to exist is the one order of events with nothing to undo it.
	 */
	$row = array(
		'state'                         => $state,
		'current_response_activity_uri' => null,
		'updated_at'                    => $now,
	);
	if ( null !== $join_uri ) {
		$row['initiating_activity_uri'] = $join_uri;
	}
	if ( is_array( $existing ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( $table, $row, array( 'id' => (int) $existing['id'] ) );
	} else {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->insert(
			$table,
			array_merge(
				$row,
				array(
					'event_uri'      => $event_uri,
					'event_uri_hash' => $event_key,
					// The shortcut, not the identity. A row for somebody else's Event has none.
					'event_post_id'  => $post_id,
					'actor_uri'      => $actor_uri,
					'actor_uri_hash' => $actor_key,
					'initiating_activity_uri' => (string) $join_uri,
					/*
					 * Which end this started from, and therefore who answers: a request the guest made is the
					 * organizer's to decide, an invitation the host made is the guest's. Written once, on the
					 * row that begins the relation, because the direction cannot change afterwards.
					 */
					'source'         => 'invite' === $source ? 'invite' : 'join',
					'created_at'     => $now,
				)
			)
		);
	}
	$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return $state;
}

/**
 * Note the Activity that produced the state a row is currently in.
 *
 * Separate from seating because the two cannot share a transaction: the ledger opens its own, and an
 * `Accept` can only honestly be written once the place it acknowledges has been secured.
 *
 * @param int    $post_id      Event post ID.
 * @param string $actor_uri    Whose participation.
 * @param string $activity_uri Response Activity URI.
 * @return void
 */
function axismundi_cal_participation_note_response( int $post_id, string $actor_uri, string $activity_uri ) : void {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update(
		axismundi_cal_participation_table(),
		array( 'current_response_activity_uri' => $activity_uri ),
		array(
			'event_uri_hash' => hash( 'sha256', axismundi_cal_event_uri( $post_id ) ),
			'actor_uri_hash' => hash( 'sha256', $actor_uri ),
		)
	);
}

/**
 * Move one existing participation to another state.
 *
 * The general setter the specific transitions are built from. It does not decide whether the move is
 * allowed -- who may reject, what may be withdrawn and when are questions the callers above answer,
 * because each has a different one.
 *
 * Capacity is not re-checked here. Seating is atomic and lives in one place, and a second check
 * beside it would be a second authority on the same number -- the kind that goes wrong in the
 * direction nobody notices.
 *
 * @param int    $post_id               Event post ID.
 * @param string $actor_uri             Whose participation.
 * @param string $state                 New state.
 * @param string $response_activity_uri Activity that produced it, if there is one.
 * @return true|WP_Error
 */
function axismundi_cal_event_participation_set( int $post_id, string $actor_uri, string $state, string $response_activity_uri = '' ) {
	if ( ! in_array( $state, AXISMUNDI_CAL_PARTICIPATION_STATES, true ) ) {
		return new WP_Error( 'ax_event_state', __( 'That is not something a reply can be.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( ! is_array( axismundi_cal_event_participation( $post_id, $actor_uri ) ) ) {
		return new WP_Error( 'ax_event_participation_missing', __( 'That person has not replied.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	$seated = axismundi_cal_participation_seat( $post_id, $actor_uri, $state );
	if ( is_wp_error( $seated ) ) {
		return $seated;
	}
	/*
	 * Back to nothing when there is no answer to name. `pending` is the state of having been asked
	 * and not replied to, so pointing it at the Activity that produced the previous one would have
	 * the row explaining itself with an answer it is no longer showing.
	 */
	if ( '' !== $response_activity_uri ) {
		axismundi_cal_participation_note_response( $post_id, $actor_uri, $response_activity_uri );
	}
	return true;
}

/**
 * Take back your own request, whether or not it was granted.
 *
 * `Undo(Join)` in both cases, and the reason it is the same act either way is who wrote what. The
 * handshake is Follow's: the guest sends `Join`, the host answers `Accept(Join)`, and leaving is the
 * guest undoing *their own* `Join` -- never the host's `Accept`, which is not theirs to retract. An
 * `Undo` addressed at somebody else's Activity is a ledger that lies about who changed their mind.
 *
 * That also settles `Leave`. It was held open while it was unclear whether an accepted `Join` is
 * undone or left, and reading the exchange as a handshake answers it: there is nothing here `Leave`
 * would say that `Undo(Join)` does not, and one verb is one meaning to keep true.
 *
 * The ledger enforces the rest: an `Undo` must be authored by whoever authored the Activity it
 * addresses, so this cannot become a way for anybody else to retract somebody's request.
 *
 * @param int    $post_id   Event post ID.
 * @param string $actor_uri Whose request.
 * @return true|WP_Error
 */
function axismundi_cal_event_withdraw_join( int $post_id, string $actor_uri ) {
	$actor_uri     = trim( $actor_uri );
	$participation = axismundi_cal_event_participation( $post_id, $actor_uri );
	if ( ! is_array( $participation ) ) {
		return new WP_Error( 'ax_event_withdraw_missing', __( 'There is no request to take back.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	// An invitation is not withdrawn by the person who received it: they undo their own answer to it.
	if ( 'join' !== (string) $participation['source'] ) {
		return new WP_Error( 'ax_event_withdraw_source', __( 'An invitation is answered rather than taken back.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	/*
	 * A request that is waiting, and one that was granted. What cannot be taken back is one that was
	 * refused or a place somebody was removed from -- there the `Join` is already spent, and undoing it
	 * would be retracting a request that is no longer what stands between them and the Event.
	 */
	if ( ! in_array( (string) $participation['state'], array( 'pending', 'accepted' ), true ) ) {
		return new WP_Error( 'ax_event_withdraw_answered', __( 'There is no standing request to take back.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	$join_uri = (string) $participation['initiating_activity_uri'];
	if ( '' === $join_uri ) {
		return new WP_Error( 'ax_event_withdraw_unaddressable', __( 'That request predates the record of it and cannot be taken back.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	$undo = axismundi_cal_participation_activity(
		array( 'type' => 'Undo', 'actor' => $actor_uri, 'object' => $join_uri ),
		'ax-cal-undo-join:' . $join_uri
	);
	if ( is_wp_error( $undo ) ) {
		return $undo;
	}
	/*
	 * Stored as the response the state is reading, which is what the column means, even though the
	 * person who wrote it is the one who asked rather than the one who answers. The alternative --
	 * leaving it empty because a withdrawal is not an answer -- would leave `withdrawn` as the one
	 * state whose cause cannot be looked up.
	 */
	$set = axismundi_cal_event_participation_set( $post_id, $actor_uri, 'withdrawn', $undo );
	if ( is_wp_error( $set ) ) {
		return $set;
	}
	// Somebody leaving is the organizer's business: a place opened up, and on a moderated Event a
	// request they were about to answer is no longer there to answer.
	axismundi_cal_notify( 'event_join_withdrawn', $post_id, array( axismundi_cal_event_owner_actor_uri( $post_id ) ), $actor_uri, $undo );
	return true;
}

/**
 * Answer somebody's request.
 *
 * The organizer's half of the handshake, for `restricted` Events. `Accept` and `Reject` are the two
 * activities FEP-8a8e names, and both address the `Join` rather than the Event -- which is what
 * makes a later answer replaceable without the Event having to change.
 *
 * Only a pending request. Re-answering a settled one is a different question with a different set of
 * rules -- disinviting somebody already accepted, or reversing a refusal -- and none of them are
 * this slice.
 *
 * @param int    $post_id   Event post ID.
 * @param string $actor_uri Whose request.
 * @param string $decision  accept|reject.
 * @return string|WP_Error Resulting state.
 */
function axismundi_cal_event_respond_to_join( int $post_id, string $actor_uri, string $decision ) {
	$actor_uri = trim( $actor_uri );
	if ( ! in_array( $decision, array( 'accept', 'reject' ), true ) ) {
		return new WP_Error( 'ax_event_respond_decision', __( 'A request is either accepted or rejected.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	// Admitting somebody to an Event that is off would be a place at something that is not happening.
	$cancelled = axismundi_cal_event_cancellation_block( $post_id );
	if ( null !== $cancelled ) {
		return $cancelled;
	}
	$participation = axismundi_cal_event_participation( $post_id, $actor_uri );
	if ( ! is_array( $participation ) ) {
		return new WP_Error( 'ax_event_respond_missing', __( 'That person has not asked to come.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	if ( 'invite' === (string) $participation['source'] ) {
		/*
		 * The row is an invitation this Event sent, and the answer to one belongs to the person invited.
		 * Without this guard the two paths meet on the same pending row and a host could accept on
		 * somebody's behalf -- an attendance nobody agreed to, recorded as though they had.
		 */
		return new WP_Error( 'ax_event_respond_invite', __( 'That is an invitation, and only the person invited can answer it.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	if ( 'pending' !== (string) $participation['state'] ) {
		return new WP_Error( 'ax_event_respond_answered', __( 'That request has already been answered.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	$owner = axismundi_cal_event_owner_actor_uri( $post_id );
	if ( '' === $owner ) {
		return new WP_Error( 'ax_event_respond_no_host', __( 'This event has no host Actor to answer with.', 'axismundi-calendar' ), array( 'status' => 403 ) );
	}
	$join_uri = (string) $participation['initiating_activity_uri'];
	if ( '' === $join_uri ) {
		return new WP_Error( 'ax_event_respond_unaddressable', __( 'That request predates the record of it and cannot be answered.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	/*
	 * The seat first, and the `Accept` once it is real. An acceptance recorded for a place that turned
	 * out not to exist is a published promise with nothing to retract it, so the order is the same one
	 * an immediate acceptance uses -- and the capacity is read inside the lock rather than before it,
	 * because two organizers approving the last place at once would otherwise both be told there was
	 * one.
	 */
	$state  = 'accept' === $decision ? 'accepted' : 'rejected';
	$seated = axismundi_cal_participation_seat( $post_id, $actor_uri, $state );
	if ( is_wp_error( $seated ) ) {
		return $seated;
	}
	$response = axismundi_cal_participation_activity(
		array( 'type' => 'accept' === $decision ? 'Accept' : 'Reject', 'actor' => $owner, 'object' => $join_uri ),
		'ax-cal-' . $decision . ':' . $join_uri
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	axismundi_cal_participation_note_response( $post_id, $actor_uri, $response );
	// The answer belongs to the person who asked, and it is the one thing they have been waiting for.
	axismundi_cal_notify( 'event_join_answered', $post_id, array( $actor_uri ), $owner, $response );
	return $state;
}

/**
 * Whether the signed-in user may answer requests for one Event.
 *
 * Named once because two surfaces ask it and a screen deciding for itself would be the wrong
 * authority: what a panel renders is a courtesy, and the endpoint has to reach the same answer
 * without having seen it.
 *
 * Not tied to being accepted. Attending an Event and running one are different things, and an
 * attendee who could approve the next person would be a permission granted by an RSVP.
 *
 * @param int $post_id Event post ID.
 * @return bool
 */
function axismundi_cal_can_manage_participation( int $post_id ) : bool {
	$schedule = axismundi_cal_schedule_for_event( $post_id );
	if ( is_array( $schedule ) && axismundi_cal_calendar_can( axismundi_cal_calendar_get( (int) $schedule['calendar_id'] ), 'manage_items' ) ) {
		return true;
	}
	$author = (int) get_post_field( 'post_author', $post_id );
	return $author > 0 && get_current_user_id() === $author;
}

/**
 * One participation row of one Event, by its own id.
 *
 * Scoped to the Event rather than looked up by id alone, so a request naming somebody else's row
 * cannot be answered by whoever manages this Event.
 *
 * @param int $post_id Event post ID.
 * @param int $id      Participation row ID.
 * @return array<string,mixed>|null
 */
function axismundi_cal_participation_by_id( int $post_id, int $id ) : ?array {
	global $wpdb;
	if ( $id <= 0 || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_participation_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d AND event_uri_hash = %s",
			$id,
			hash( 'sha256', axismundi_cal_event_uri( $post_id ) )
		),
		ARRAY_A
	);
	return is_array( $row ) ? $row : null;
}

/**
 * Everything said about one Event, whatever it was.
 *
 * The list a management screen draws its tabs from. The tabs are a projection of these states and
 * not a set of their own -- a filter that invented one would be a status the ledger cannot produce.
 *
 * @param int      $post_id Event post ID.
 * @param string[] $states  States to keep, or empty for all.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_event_participations( int $post_id, array $states = array() ) : array {
	global $wpdb;
	if ( $post_id <= 0 || ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_participation_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$rows = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE event_uri_hash = %s ORDER BY updated_at DESC, id DESC",
			hash( 'sha256', axismundi_cal_event_uri( $post_id ) )
		),
		ARRAY_A
	);
	if ( array() === $states ) {
		return $rows;
	}
	return array_values(
		array_filter(
			$rows,
			static fn( array $row ) : bool => in_array( (string) $row['state'], $states, true )
		)
	);
}

/**
 * The people coming.
 *
 * Derived from the accepted replies rather than kept as a list. An editable array beside the replies
 * would be a second answer to the question they already settle, and the two would part company the
 * first time somebody withdrew.
 *
 * Whoever started it. Somebody who asked and was accepted and somebody who was invited and accepted
 * are both coming, and a collection that counted only one of those would be a guest list missing half
 * the room -- which is why `source` is a column on the row and not a filter on this.
 *
 * @param int $post_id Event post ID.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_event_attendees( int $post_id ) : array {
	global $wpdb;
	if ( $post_id <= 0 || ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_participation_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE event_uri_hash = %s AND state = 'accepted' ORDER BY updated_at ASC, id ASC",
			hash( 'sha256', axismundi_cal_event_uri( $post_id ) )
		),
		ARRAY_A
	);
}

/**
 * How many more people can be accepted, or null when nobody counted.
 *
 * Derived from the accepted replies, the way the list of them is. A stored counter beside the
 * capacity would be a third answer to a question the replies already settle, and it would go wrong
 * in the direction nobody notices: too high, letting somebody in.
 *
 * FEP-8a8e calls this `remainingAttendeeCapacity`, and it is what a peer reads to decide whether to
 * offer a Join at all.
 *
 * @param int $post_id Event post ID.
 * @return int|null
 */
function axismundi_cal_event_remaining_capacity( int $post_id ) : ?int {
	$envelope = axismundi_cal_event_get( $post_id );
	if ( ! is_array( $envelope ) || null === $envelope['maximum_attendee_capacity'] ) {
		return null;
	}
	return max( 0, (int) $envelope['maximum_attendee_capacity'] - count( axismundi_cal_event_attendees( $post_id ) ) );
}

/**
 * The Actor URI of whoever is signed in, whatever their profile says.
 *
 * Not `current_actor_uri()`, which answers for federation and returns nothing unless the Actor is
 * publicly visible. Whether somebody's profile is published has no bearing on whether they are the
 * organizer of this Event, and using a resolver that cared would silently stop recognising them.
 *
 * @return string
 */
function axismundi_cal_signed_in_actor_uri() : string {
	$user_id = get_current_user_id();
	if ( $user_id <= 0 ) {
		return '';
	}
	if ( function_exists( 'axismundi_actors_get_for_user' ) ) {
		$actor = axismundi_actors_get_for_user( $user_id );
		if ( $actor instanceof Axismundi_Actor ) {
			return (string) $actor->get_uri();
		}
	}
	return (string) axismundi_cal_current_actor_uri();
}

/**
 * Whether one Actor may be shown the locations kept for the people attending.
 *
 * Three sources, and the third is the point of writing it as a predicate now: an invitation is a
 * fourth line here rather than a rethink of who counts. Somebody who was accepted is coming, and
 * withholding the joining link from them would make acceptance mean nothing.
 *
 * `pending` is not enough. Being told the meeting URL is what acceptance grants, so granting it while
 * the answer is still open would make the answer pointless.
 *
 * @param string $actor_uri Asking Actor, or '' for none.
 * @param int    $post_id   Event post ID.
 * @return bool
 */
function axismundi_cal_can_view_attendee_location( string $actor_uri, int $post_id ) : bool {
	$actor_uri = trim( $actor_uri );

	/*
	 * The organizer's privileges belong to the organizer. They are read from the logged-in user, so
	 * they may only answer for that user's own Actor -- asked about somebody else while an
	 * administrator happens to be logged in, they would report that person's entitlement as whatever
	 * the administrator's is, which is the one answer this must never give.
	 */
	if ( '' === $actor_uri || $actor_uri === axismundi_cal_signed_in_actor_uri() ) {
		$schedule = axismundi_cal_schedule_for_event( $post_id );
		if ( is_array( $schedule ) && axismundi_cal_calendar_can( axismundi_cal_calendar_get( (int) $schedule['calendar_id'] ), 'manage_items' ) ) {
			return true;
		}
		$author = (int) get_post_field( 'post_author', $post_id );
		if ( $author > 0 && get_current_user_id() === $author ) {
			return true;
		}
	}

	$participation = axismundi_cal_event_participation( $post_id, $actor_uri );
	return is_array( $participation ) && 'accepted' === (string) $participation['state'];
}

/**
 * Drop an Event's replies with the Event.
 *
 * Local rows only. A federated `Join` somebody else sent is theirs, and deleting an Event is not a
 * reason to reach into what another Actor said -- that is a `Delete` to publish, not a row to remove.
 *
 * @param int $post_id Event post ID.
 * @return void
 */
function axismundi_cal_event_participation_forget( int $post_id ) : void {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_cal_participation_table(), array( 'event_uri_hash' => hash( 'sha256', axismundi_cal_event_uri( $post_id ) ) ) );
}
