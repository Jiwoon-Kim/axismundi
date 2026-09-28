<?php
/**
 * The client-to-server publish command: which Actor a local save publishes as.
 *
 * ActivityPub has the Activity carry the actor and the object take its `attributedTo`
 * from it. This project had the order reversed: a projection derived an Actor from
 * `post_author` and handed it to the ledger, which then verified the ledger against the
 * projection. That is only ever right while every account has exactly one Person, and it
 * has no answer at all once someone publishes as an Organization they manage.
 *
 * So the decision moves here, to the boundary a save crosses on its way into the ledger:
 * Actors offers the acting Actor, this file turns a save into a command carrying it and
 * re-checks it with `can_act_as()`, the ledger records the Create or Update, and Object
 * Projections materialises the Object from that record. See the design note in
 * `axismundi-object-projections/docs/LOCAL-OBJECTS.md`.
 *
 * `post_author` keeps its own meaning throughout — who may edit the source in wp-admin.
 * It is not consulted as an identity, only to tell an author's own save apart from a save
 * made on their behalf.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/**
 * The principal a save is made on behalf of.
 *
 * Usually the signed-in account. A scheduled publication runs with no session at all, and the
 * author is the principal there by construction: they are the one who asked for it to happen.
 * Without this a post scheduled for tomorrow would reach the command service with no principal
 * and publish as nobody.
 *
 * @param WP_Post  $post    Source post.
 * @param int|null $user_id Saver; defaults to the current one.
 * @return int
 */
function axismundi_act_publish_principal( WP_Post $post, ?int $user_id = null ) : int {
	$user_id = null === $user_id ? get_current_user_id() : $user_id;
	return $user_id > 0 ? $user_id : (int) $post->post_author;
}

/**
 * The Actor a local save offers to publish as, before the service verifies it.
 *
 * This is the adapter's side of the contract: it chooses which identity to submit, and the
 * command service re-checks that the principal may use it. The two are deliberately separate
 * so that a client which chooses explicitly -- a REST caller, a later outbox POST -- bypasses
 * this helper entirely without bypassing the check.
 *
 * @param WP_Post  $post    Source post.
 * @param int|null $user_id Saver; defaults to the current one.
 * @return Axismundi_Actor|null
 */
function axismundi_act_publish_actor( WP_Post $post, ?int $user_id = null ) : ?Axismundi_Actor {
	$user_id = null === $user_id ? get_current_user_id() : $user_id;
	if ( ! function_exists( 'axismundi_actors_can_act_as' ) ) {
		return null;
	}
	if ( $user_id > 0 && $user_id === (int) $post->post_author ) {
		$acting = axismundi_actors_acting_actor( $user_id );
		if ( $acting instanceof Axismundi_Actor && axismundi_actors_can_act_as( $acting, $user_id ) ) {
			return $acting;
		}
	}
	return axismundi_actors_default_acting_actor( (int) $post->post_author );
}

/**
 * Resolve the publishing identity for one local source post.
 *
 * A committed Object keeps the Actor it is attributed to, which is deliberately read from
 * the ledger's attribution rather than from its latest Activity: the two are the same until
 * someone edits work that is not theirs, and after that only the first is the owner.
 *
 * The acting Actor applies only to an author saving their own post. An import, a scheduled
 * publication, or an administrator editing someone else's draft must not hand that post to
 * the saver's identity, so those fall back to the author's own Person -- the same guard the
 * Calendar's `acting_actor_identity_id` already uses, and the only precedent in the project
 * that works.
 *
 * Eligibility is re-checked here rather than trusted from the stored preference, because a
 * manager role is revocable and a choice made last week is not authority today.
 *
 * @param WP_Post     $post       Source post.
 * @param string      $object_uri Canonical Object URI; empty skips the committed lookup.
 * @param int|null    $user_id    User performing the save; defaults to the current one.
 * @return string Actor URI, or an empty string when there is no identity to publish under.
 */
function axismundi_act_publish_actor_uri( WP_Post $post, string $object_uri = '', ?int $user_id = null ) : string {
	$user_id = null === $user_id ? get_current_user_id() : $user_id;
	$uri     = '' === $object_uri ? '' : axismundi_act_get_object_attribution( $object_uri );

	if ( '' === $uri ) {
		$actor = axismundi_act_publish_actor( $post, $user_id );
		$uri   = $actor instanceof Axismundi_Actor ? $actor->get_uri() : '';
	}

	/**
	 * Filter the Actor URI one local save publishes as.
	 *
	 * The seam a compatibility adapter uses when another product owns the publishing
	 * identity. Returning an empty string leaves the post unattributed, which is a real
	 * state rather than an error: the Object may still be projected for reading, and no
	 * Activity is recorded for it.
	 *
	 * @since 0.1.0
	 * @param string  $uri        Resolved Actor URI or empty string.
	 * @param WP_Post $post       Source post.
	 * @param string  $object_uri Canonical Object URI.
	 * @param int     $user_id    User performing the save.
	 */
	return (string) apply_filters( 'axismundi_act_publish_actor_uri', $uri, $post, $object_uri, $user_id );
}

/**
 * Answer Object Projections when it asks who a local Object is attributed to.
 *
 * The direction that makes this plugin the authority: projection no longer derives an identity
 * and hands it over to be recorded; it asks what was recorded. Object Projections owns the
 * filter and never reads this ledger, so it keeps working with this plugin inactive -- an
 * Object simply has no attribution then, which is the correct answer rather than a degraded one.
 *
 * An Actor that later stops being public does not retract attribution. What went out under a
 * name stays under that name; withdrawing it is a Delete, not a silent change of author.
 *
 * @param string  $uri        Attribution another integration already settled.
 * @param WP_Post $post       Source post.
 * @param string  $object_uri Canonical Object URI.
 */
function axismundi_act_local_object_attribution( string $uri, WP_Post $post, string $object_uri ) : string {
	unset( $post );
	return '' !== $uri ? $uri : axismundi_act_get_object_attribution( $object_uri );
}
add_filter( 'axismundi_op_local_object_attribution', 'axismundi_act_local_object_attribution', 10, 3 );
