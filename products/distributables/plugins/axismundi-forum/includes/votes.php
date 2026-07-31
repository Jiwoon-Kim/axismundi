<?php
/**
 * Community voting: one vote per Actor per object, and the score that follows from it.
 *
 * Activities records `Like` and `Dislike` as independent facts, because ActivityStreams does
 * not make them opposites — an Actor may genuinely hold both there. "One vote per person" is a
 * forum rule, so it lives here, with the community that imposes it.
 *
 * Casting a vote therefore has two halves: withdraw whatever the Actor held before, then record
 * the new one. The order matters. Recording first would leave a window where the ledger says an
 * Actor both likes and dislikes the same object, and peers that received both would each pick
 * their own winner. Withdrawing first means a failure leaves the Actor with *no* vote — visible,
 * honest, and retryable — rather than two contradictory ones federated to other servers.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

/** @return string[] The vote directions a community accepts. */
function axismundi_forum_vote_directions() : array {
	return array( 'up', 'down', 'none' );
}

/** The Activity verb one direction records, or '' for withdrawal. */
function axismundi_forum_vote_verb( string $direction ) : string {
	if ( 'up' === $direction ) {
		return 'Like';
	}
	return 'down' === $direction ? 'Dislike' : '';
}

/**
 * The vote one Actor currently holds on an object.
 *
 * Both verbs are read rather than assumed mutually exclusive: an inbound vote from a peer that
 * does not enforce exclusivity, or a withdrawal that failed midway, can leave both standing.
 * The most recent one is reported, so the answer is deterministic instead of depending on which
 * verb happened to be queried first.
 *
 * @param string $actor_uri  Voting Actor.
 * @param string $object_uri Canonical object URI.
 * @return string One of `up`, `down`, `none`.
 */
function axismundi_forum_actor_vote( string $actor_uri, string $object_uri ) : string {
	if ( ! function_exists( 'axismundi_act_get_actor_vote' ) ) {
		return 'none';
	}
	$like    = axismundi_act_get_actor_vote( 'Like', $actor_uri, $object_uri, true );
	$dislike = axismundi_act_get_actor_vote( 'Dislike', $actor_uri, $object_uri, true );
	if ( $like instanceof Axismundi_Activity && $dislike instanceof Axismundi_Activity ) {
		return strcmp( (string) $like->get_uri(), (string) $dislike->get_uri() ) === 0
			? 'up'
			: ( axismundi_forum_vote_is_newer( $like, $dislike ) ? 'up' : 'down' );
	}
	if ( $like instanceof Axismundi_Activity ) {
		return 'up';
	}
	return $dislike instanceof Axismundi_Activity ? 'down' : 'none';
}

/** Whether the first vote Activity is the later of two, by recorded time then identity. */
function axismundi_forum_vote_is_newer( Axismundi_Activity $left, Axismundi_Activity $right ) : bool {
	$left_time  = (int) strtotime( (string) ( $left->get_published_at() ?? '' ) );
	$right_time = (int) strtotime( (string) ( $right->get_published_at() ?? '' ) );
	if ( $left_time !== $right_time ) {
		return $left_time > $right_time;
	}
	// Equal timestamps still need one stable answer, or the same page would disagree with itself.
	return strcmp( (string) $left->get_uri(), (string) $right->get_uri() ) > 0;
}

/**
 * The community score for one object.
 *
 * Counts come from the Activities ledger rather than a Forum tally table, so a rebuild is never
 * needed and a vote that arrived before this feature existed still counts.
 *
 * @param string $object_uri Canonical object URI.
 * @param string $actor_uri  Optional viewer, for their own current vote.
 * @return array{up:int,down:int,score:int,viewer:string}
 */
function axismundi_forum_vote_score( string $object_uri, string $actor_uri = '' ) : array {
	$up   = 0;
	$down = 0;
	if ( function_exists( 'axismundi_act_get_latest_effective_votes' ) ) {
		foreach ( axismundi_act_get_latest_effective_votes( array( 'Like', 'Dislike' ), $object_uri ) as $vote ) {
			if ( 'Like' === $vote->get_type() ) {
				++$up;
			} elseif ( 'Dislike' === $vote->get_type() ) {
				++$down;
			}
		}
	} else {
		// Activities 0.0.39 supplies the exclusive-vote projection. Keep a degraded read for
		// an accidental mixed-version deploy instead of making the vote control disappear.
		$up   = function_exists( 'axismundi_act_get_like_count' ) ? axismundi_act_get_like_count( $object_uri ) : 0;
		$down = function_exists( 'axismundi_act_get_dislike_count' ) ? axismundi_act_get_dislike_count( $object_uri ) : 0;
	}
	return array(
		'up'     => $up,
		'down'   => $down,
		'score'  => $up - $down,
		'viewer' => '' === $actor_uri ? 'none' : axismundi_forum_actor_vote( $actor_uri, $object_uri ),
	);
}

/**
 * Cast, change, or withdraw one Actor's vote on a community object.
 *
 * @param Axismundi_Actor $actor      Local voting Actor.
 * @param string          $object_uri Canonical object URI.
 * @param string          $direction  `up`, `down`, or `none`.
 * @return array{up:int,down:int,score:int,viewer:string}|WP_Error Resulting score.
 */
function axismundi_forum_cast_vote( Axismundi_Actor $actor, string $object_uri, string $direction ) {
	if ( ! in_array( $direction, axismundi_forum_vote_directions(), true ) ) {
		return new WP_Error( 'ax_forum_vote_direction', __( 'That is not a valid vote.', 'axismundi-forum' ) );
	}
	if ( ! function_exists( 'axismundi_act_vote_on_object' ) || ! function_exists( 'axismundi_act_undo_vote_on_object' ) ) {
		return new WP_Error( 'ax_forum_vote_unavailable', __( 'The activity ledger is unavailable.', 'axismundi-forum' ) );
	}
	$keep = axismundi_forum_vote_verb( $direction );

	/*
	 * Withdraw first, including the verb being cast when it is already held: a repeated press of
	 * the same button is a toggle back to no vote, which is what every forum does and what makes
	 * the control reversible without a third button.
	 */
	$current = axismundi_forum_actor_vote( $actor->get_uri(), $object_uri );
	if ( 'none' === $direction ) {
		$keep = '';
	} elseif ( $current === $direction ) {
		$keep = '';
	}
	foreach ( array( 'Like', 'Dislike' ) as $verb ) {
		if ( $verb === $keep ) {
			continue;
		}
		$held = axismundi_act_get_actor_vote( $verb, $actor->get_uri(), $object_uri, true );
		if ( ! $held instanceof Axismundi_Activity ) {
			continue;
		}
		$undone = axismundi_act_undo_vote_on_object( $verb, $actor, $object_uri );
		if ( is_wp_error( $undone ) ) {
			return $undone;
		}
	}
	if ( '' !== $keep ) {
		$recipient = axismundi_forum_vote_recipient_uri( $object_uri );
		$cast      = axismundi_act_vote_on_object( $keep, $actor, $object_uri, $recipient );
		if ( is_wp_error( $cast ) ) {
			return $cast;
		}
	}
	return axismundi_forum_vote_score( $object_uri, $actor->get_uri() );
}

/**
 * Who a community vote is addressed to.
 *
 * A vote on a community object goes to the community, not only to the author: that is how the
 * Group learns of it, and on a remote threadiverse server it is the community — not the author —
 * that keeps the score. Falling back to the author preserves the plain object case.
 *
 * @param string $object_uri Canonical object URI.
 * @return string Recipient Actor URI, or '' when none can be resolved.
 */
function axismundi_forum_vote_recipient_uri( string $object_uri ) : string {
	$group = function_exists( 'axismundi_forum_object_community_group' ) ? axismundi_forum_object_community_group( $object_uri ) : null;
	if ( $group instanceof Axismundi_Actor ) {
		return $group->get_uri();
	}
	if ( function_exists( 'axismundi_act_resolve_like_target' ) ) {
		$target = axismundi_act_resolve_like_target( $object_uri );
		if ( ! is_wp_error( $target ) ) {
			return (string) ( $target['recipient_uri'] ?? '' );
		}
	}
	return '';
}

/**
 * The community Group a local Note was submitted to, when it is one of our own replies.
 *
 * The Group is read from the reply's immutable lifecycle evidence, not from the current parent
 * or membership state. A reply that was accepted yesterday stays in that conversation after its
 * author leaves; conversely, an ordinary reply must not acquire community context later just
 * because its author joins. The submission permission is enforced while Note creates the
 * lifecycle Activity, and this read path preserves that fact.
 *
 * @param string $object_uri Canonical object URI.
 * @return Axismundi_Actor|null
 */
function axismundi_forum_local_note_community_group( string $object_uri ) : ?Axismundi_Actor {
	if ( ! function_exists( 'axismundi_note_local_uuid_from_uri' ) || ! function_exists( 'axismundi_act_get_by_object' ) || ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
		return null;
	}
	if ( null === axismundi_note_local_uuid_from_uri( $object_uri ) ) {
		return null;
	}
	foreach ( axismundi_act_get_by_object( $object_uri, 50 ) as $submission ) {
		if ( ! $submission instanceof Axismundi_Activity || ! $submission->is_effective() || ! in_array( $submission->get_type(), array( 'Create', 'Update' ), true ) ) {
			continue;
		}
		$object = $submission->get_payload()['object'] ?? null;
		if ( ! is_array( $object ) || ! hash_equals( $object_uri, (string) ( $object['id'] ?? '' ) ) || (string) ( $object['attributedTo'] ?? '' ) !== $submission->get_actor_uri() ) {
			continue;
		}
		$group_uri = axismundi_act_member_uri( $object['audience'] ?? '' );
		$group     = '' !== $group_uri ? axismundi_actors_get_by_uri( $group_uri ) : null;
		if ( $group instanceof Axismundi_Actor && ! $group->is_local() && 'Group' === $group->get_type() && 'public' === $group->get_status() && axismundi_forum_activity_addresses_actor( $submission, $group_uri ) ) {
			return $group;
		}
	}
	return null;
}

/**
 * The community Group an object belongs to, local or remote.
 *
 * A local object's community is proved from the ledger, exactly as the inbound redistribution
 * gate proves it — an object may not claim a community it was never submitted to. A cached
 * remote object is taken at its own server's word, because its community is that server's fact
 * to state and we are only reading it back.
 *
 * @param string $object_uri Canonical object URI.
 * @return Axismundi_Actor|null
 */
function axismundi_forum_object_community_group( string $object_uri ) : ?Axismundi_Actor {
	$local = function_exists( 'axismundi_forum_local_community_for_submitted_object' )
		? axismundi_forum_local_community_for_submitted_object( $object_uri )
		: null;
	if ( $local instanceof Axismundi_Actor ) {
		return $local;
	}
	/*
	 * The ledger proof above only recognises a *local* Group, because that is the only case
	 * where the submission and the community live on the same server. Our own reply into a
	 * remote community is just as much a community post, and without this branch it would be
	 * treated as an ordinary Note — rendered without community context, and worse, voted on by
	 * addressing only the author, which a threadiverse peer does not count.
	 */
	$reply_group = axismundi_forum_local_note_community_group( $object_uri );
	if ( $reply_group instanceof Axismundi_Actor ) {
		return $reply_group;
	}
	if ( ! function_exists( 'axismundi_op_remote_object_get' ) || ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
		return null;
	}
	$remote = axismundi_op_remote_object_get( $object_uri, false );
	$payload = is_array( $remote ) ? (array) ( $remote['payload'] ?? array() ) : array();
	foreach ( array( 'audience', 'to', 'cc' ) as $property ) {
		foreach ( axismundi_forum_member_uris( $payload[ $property ] ?? array() ) as $candidate ) {
			$group = axismundi_actors_get_by_uri( $candidate );
			if ( $group instanceof Axismundi_Actor && 'Group' === $group->get_type() && 'tombstone' !== $group->get_status() ) {
				return $group;
			}
		}
	}
	return null;
}
