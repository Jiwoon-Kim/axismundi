<?php
/**
 * The command service every local submission goes through.
 *
 * A submission is a request, not a record. The caller says which post it wants published and
 * which identity it is asking to publish as; the server decides the Activity id, the actor,
 * the object and the audience. That is the same separation ActivityPub draws when it makes a
 * server ignore a client-supplied Activity `id`, and it is what stops a caller asserting an
 * Actor or an audience it was never granted.
 *
 * Design and the protocol quotations behind it: `docs/C2S.md`.
 *
 * This is the service, not a wire endpoint. The block and classic editors are local submission
 * adapters that call it directly. An Actor outbox `POST` endpoint would be one more adapter
 * over this same function rather than a second implementation.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** Operations this service accepts. */
function axismundi_act_c2s_operations() : array {
	return array( 'Create', 'Delete' );
}

/**
 * Resolve which Actor a principal is asking to publish as.
 *
 * Identity rather than URI, because the permission kernel is identity-keyed the whole way
 * down and an Actor has four public lookups -- a string argument would make this function
 * guess which one it had been handed before it could check anything.
 *
 * Passing `0` is a real request and not an error: a client with no way to choose an Actor,
 * such as the official mobile apps, submits without one. It resolves to the principal's own
 * Person and to nothing else. The server never substitutes an Organization the principal
 * happens to manage, nor the site Actor, because an unchosen identity that speaks for someone
 * else puts words in their mouth.
 *
 * @param int $principal_user_id Authenticated user.
 * @param int $actor_identity_id Chosen identity, or 0 for the principal's own Person.
 * @return Axismundi_Actor|WP_Error|null Null when the principal has no identity to publish under.
 */
function axismundi_act_c2s_resolve_actor( int $principal_user_id, int $actor_identity_id ) {
	if ( ! function_exists( 'axismundi_actors_can_act_as' ) ) {
		return new WP_Error( 'ax_act_c2s_actors', __( 'Actor identities are unavailable.', 'axismundi-activities' ) );
	}
	if ( $actor_identity_id <= 0 ) {
		$own = axismundi_actors_default_acting_actor( $principal_user_id );
		return $own instanceof Axismundi_Actor ? $own : null;
	}
	$actor = axismundi_actors_get_by_identity( $actor_identity_id );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_act_c2s_actor', __( 'The submitted identity does not exist.', 'axismundi-activities' ) );
	}
	// Re-checked here rather than trusted from a stored preference: a manager role is
	// revocable, so a choice made last week is not authority today.
	if ( ! axismundi_actors_can_act_as( $actor, $principal_user_id ) ) {
		return new WP_Error( 'ax_act_c2s_permission', __( 'This account may not publish as that identity.', 'axismundi-activities' ) );
	}
	return $actor;
}

/**
 * Submit one local command and record the Activity it earns.
 *
 * @param int   $principal_user_id Authenticated user making the request.
 * @param int   $actor_identity_id Identity they are asking to act as; 0 for their own Person.
 * @param array $command           operation, post_id, expected_version.
 * @param array $context           Reserved for the submitting surface and idempotency hints.
 * @return Axismundi_Activity|WP_Error|null Null when nothing was published and nothing is wrong.
 */
function axismundi_act_submit_c2s( int $principal_user_id, int $actor_identity_id, array $command, array $context = array() ) {
	$operation = (string) ( $command['operation'] ?? '' );
	$post_id   = (int) ( $command['post_id'] ?? 0 );
	$expected  = (string) ( $command['expected_version'] ?? '' );

	if ( ! in_array( $operation, axismundi_act_c2s_operations(), true ) ) {
		return new WP_Error( 'ax_act_c2s_operation', __( 'The submitted operation is not one this service performs.', 'axismundi-activities' ) );
	}
	if ( $principal_user_id <= 0 || ! get_userdata( $principal_user_id ) ) {
		return new WP_Error( 'ax_act_c2s_principal', __( 'A submission requires an authenticated account.', 'axismundi-activities' ) );
	}
	/*
	 * Checked before the lookup, because `get_post( 0 )` falls back to the global `$post`
	 * rather than returning nothing. A command that omits its subject would otherwise act on
	 * whatever post the surrounding request happened to be rendering.
	 */
	$post = $post_id > 0 ? get_post( $post_id ) : null;
	if ( ! $post instanceof WP_Post ) {
		return new WP_Error( 'ax_act_c2s_subject', __( 'The submitted post does not exist.', 'axismundi-activities' ) );
	}

	/*
	 * The version guard. Both editors write to `wp_posts` before this service hears about the
	 * save, so another edit can land in between, and without this the recorded Activity would
	 * describe a body nobody chose to publish. A Delete is exempt: it withdraws an identity,
	 * and which revision was current when it was asked for changes nothing about that.
	 */
	if ( 'Delete' !== $operation ) {
		if ( '' === $expected ) {
			return new WP_Error( 'ax_act_c2s_version', __( 'A submission must state the post version it publishes.', 'axismundi-activities' ) );
		}
		if ( ! hash_equals( (string) $post->post_modified_gmt, $expected ) ) {
			return new WP_Error( 'ax_act_c2s_stale', __( 'The post changed after this submission was prepared.', 'axismundi-activities' ) );
		}
	}

	// Authority over the local source, which is WordPress's question and stays WordPress's.
	if ( ! user_can( $principal_user_id, 'Delete' === $operation ? 'delete_post' : 'edit_post', $post->ID ) ) {
		return new WP_Error( 'ax_act_c2s_capability', __( 'This account may not act on that post.', 'axismundi-activities' ) );
	}

	if ( ! function_exists( 'axismundi_op_post_object_uri' ) ) {
		return new WP_Error( 'ax_act_c2s_projection', __( 'Object projection is unavailable.', 'axismundi-activities' ) );
	}
	$object_uri = axismundi_op_post_object_uri( $post );
	if ( '' === $object_uri ) {
		return new WP_Error( 'ax_act_c2s_projection', __( 'The post has no projected object identity.', 'axismundi-activities' ) );
	}

	$result = 'Delete' === $operation
		? axismundi_act_c2s_withdraw( $post, $object_uri, $principal_user_id, $actor_identity_id )
		: axismundi_act_c2s_publish( $post, $object_uri, $principal_user_id, $actor_identity_id );

	/**
	 * Fires after one local submission has been decided.
	 *
	 * @since 0.1.0
	 * @param Axismundi_Activity|WP_Error|null $result    What the submission earned.
	 * @param array                            $command   The submitted command.
	 * @param array                            $context   Submitting surface and hints.
	 * @param int                              $principal Authenticated user.
	 */
	do_action( 'axismundi_act_c2s_submitted', $result, $command, $context, $principal_user_id );
	return $result;
}

/** Record the Create one publishable post earns, under the identity the command chose. */
function axismundi_act_c2s_publish( WP_Post $post, string $object_uri, int $principal_user_id, int $actor_identity_id ) {
	$actor = axismundi_act_c2s_resolve_actor( $principal_user_id, $actor_identity_id );
	if ( is_wp_error( $actor ) ) {
		return $actor;
	}
	/*
	 * No identity to publish under. The post is saved and projectable for reading; it is
	 * simply published by nobody, which is a real state rather than a failure.
	 */
	if ( ! $actor instanceof Axismundi_Actor ) {
		return null;
	}

	/*
	 * A committed Object keeps the Actor it is attributed to. A second submission naming a
	 * different identity is a reassignment, which needs its own authorisation and is not a
	 * side effect of saving, so it is refused rather than quietly applied.
	 */
	$attributed = axismundi_act_get_object_attribution( $object_uri );
	if ( '' !== $attributed && ! hash_equals( $attributed, $actor->get_uri() ) ) {
		return new WP_Error( 'ax_act_c2s_attribution', __( 'This object is already attributed to another Actor.', 'axismundi-activities' ) );
	}

	return axismundi_act_record_post_create( $post, $object_uri, $actor->get_uri() );
}

/**
 * Record the Delete that withdraws one published post.
 *
 * A withdrawal has exactly one valid Actor -- the Object's attribution -- so `0` means
 * something different here than it does for a publication, where it means the principal's own
 * Person. What must not differ is the check. Deleting the local post is a WordPress
 * capability, but speaking as an Organization is not: without `can_act_as()` here, anyone who
 * could empty the trash could emit a Delete signed by an Organization they were never
 * appointed to, including one whose manager role they have already lost.
 *
 * Refusing is the honest outcome rather than borrowing the identity. The caller separates the
 * two consequences: `pre_delete_post` keeps the local post rather than federating a Delete
 * nobody was authorised to make.
 */
function axismundi_act_c2s_withdraw( WP_Post $post, string $object_uri, int $principal_user_id, int $actor_identity_id ) {
	unset( $post );
	$attributed = axismundi_act_get_object_attribution( $object_uri );
	if ( '' === $attributed ) {
		return null;
	}
	if ( ! function_exists( 'axismundi_actors_get_by_uri' ) || ! function_exists( 'axismundi_actors_can_act_as' ) ) {
		return new WP_Error( 'ax_act_c2s_actors', __( 'Actor identities are unavailable.', 'axismundi-activities' ) );
	}
	$actor = axismundi_actors_get_by_uri( $attributed );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_act_c2s_actor', __( 'The Actor this object is attributed to is unavailable.', 'axismundi-activities' ) );
	}
	if ( $actor_identity_id > 0 && $actor_identity_id !== $actor->get_identity_id() ) {
		return new WP_Error( 'ax_act_c2s_attribution', __( 'That identity is not the one this object is attributed to.', 'axismundi-activities' ) );
	}
	if ( ! axismundi_actors_can_act_as( $actor, $principal_user_id ) ) {
		return new WP_Error( 'ax_act_c2s_permission', __( 'This account may not withdraw an object published by that identity.', 'axismundi-activities' ) );
	}
	return axismundi_act_record_object_delete( $object_uri, $attributed );
}
