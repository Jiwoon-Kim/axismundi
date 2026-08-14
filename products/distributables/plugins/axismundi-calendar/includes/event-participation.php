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
	$author = (int) get_post_field( 'post_author', $post_id );
	if ( $author <= 0 || ! function_exists( 'axismundi_actors_get_for_user' ) ) {
		return '';
	}
	$actor = axismundi_actors_get_for_user( $author );
	return $actor instanceof Axismundi_Actor ? (string) $actor->get_uri() : '';
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
	 * Only the acceptance is capped. A moderated Event that is full may still take requests -- that is
	 * a waiting list, and refusing to record one would lose the fact that somebody asked -- but an open
	 * Event accepts on arrival, so this is where it has to say no.
	 *
	 * The host is capped with everybody else. Letting them past a limit they set would make the number
	 * a decoration: a capacity of twenty that seats twenty-one is not a capacity, and every peer
	 * reading `remainingAttendeeCapacity` would be told something untrue. A full event is theirs to
	 * resize or to thin out, which are both decisions rather than an exception.
	 */
	$remaining = axismundi_cal_event_remaining_capacity( $post_id );
	if ( 'accepted' === $state && null !== $remaining && $remaining < 1 ) {
		return new WP_Error( 'ax_event_join_full', __( 'This event is full.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	$existing = axismundi_cal_event_participation( $post_id, $actor_uri );
	$now      = current_time( 'mysql', true );
	$table    = axismundi_cal_participation_table();

	if ( is_array( $existing ) ) {
		/*
		 * Asking again after being turned down does not undo the answer. Somebody who withdrew may
		 * change their mind, which is the one transition a second `Join` legitimately makes.
		 */
		if ( 'rejected' === (string) $existing['state'] ) {
			return new WP_Error( 'ax_event_join_rejected', __( 'That reply was already answered.', 'axismundi-calendar' ), array( 'status' => 403 ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( $table, array( 'state' => $state, 'updated_at' => $now ), array( 'id' => (int) $existing['id'] ) );
		return $state;
	}

	$event_uri = axismundi_cal_event_uri( $post_id );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->insert(
		$table,
		array(
			'event_uri'      => $event_uri,
			'event_uri_hash' => hash( 'sha256', $event_uri ),
			// The shortcut, not the identity. A row for somebody else's Event has none.
			'event_post_id'  => $post_id,
			'actor_uri'      => $actor_uri,
			'actor_uri_hash' => hash( 'sha256', $actor_uri ),
			'initiating_activity_uri' => '',
			/*
			 * An open Event answers on arrival, so the row has a response from the start; a moderated one
			 * is waiting for somebody, and `NULL` is what "nothing has answered yet" looks like.
			 */
			'current_response_activity_uri' => null,
			// Asked for by the person coming, which is what makes the organizer the one who answers.
			'source'         => 'join',
			'state'          => $state,
			'created_at'     => $now,
			'updated_at'     => $now,
		)
	);
	return $state;
}

/**
 * Answer somebody's request, or take one's own back.
 *
 * @param int    $post_id   Event post ID.
 * @param string $actor_uri Whose participation.
 * @param string $state     New state.
 * @return true|WP_Error
 */
function axismundi_cal_event_participation_set( int $post_id, string $actor_uri, string $state, string $response_activity_uri = '' ) {
	global $wpdb;
	if ( ! in_array( $state, AXISMUNDI_CAL_PARTICIPATION_STATES, true ) ) {
		return new WP_Error( 'ax_event_state', __( 'That is not something a reply can be.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$existing = axismundi_cal_event_participation( $post_id, $actor_uri );
	if ( ! is_array( $existing ) ) {
		return new WP_Error( 'ax_event_participation_missing', __( 'That person has not replied.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	/*
	 * The other way somebody becomes an attendee, and therefore the other place the count has to hold.
	 * Checked only when this is a change into `accepted`: re-accepting somebody already accepted must
	 * not be refused by a capacity they are themselves part of.
	 */
	if ( 'accepted' === $state && 'accepted' !== (string) $existing['state'] ) {
		$remaining = axismundi_cal_event_remaining_capacity( $post_id );
		if ( null !== $remaining && $remaining < 1 ) {
			return new WP_Error( 'ax_event_accept_full', __( 'This event is full, so nobody else can be accepted.', 'axismundi-calendar' ), array( 'status' => 409 ) );
		}
	}
	/*
	 * Back to nothing when the answer is withdrawn to `pending`: the activity that produced the old
	 * state is no longer what the row is reading, and leaving it there would have the projection
	 * explaining itself with an answer it is not showing.
	 */
	$response = 'pending' === $state ? null : ( '' !== $response_activity_uri ? $response_activity_uri : ( $existing['current_response_activity_uri'] ?? null ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update(
		axismundi_cal_participation_table(),
		array(
			'state' => $state,
			'current_response_activity_uri' => $response,
			'updated_at' => current_time( 'mysql', true ),
		),
		array( 'id' => (int) $existing['id'] )
	);
	return true;
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
