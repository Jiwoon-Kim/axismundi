<?php
/**
 * Local Actor profile changes → outbound Update Activities.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Record the current public Actor representation as an Update when it changed.
 *
 * Actor documents are not Objects with `attributedTo`, so they deliberately do
 * not use the generic Object lifecycle helper. The immutable Actor URI is both
 * the activity author and the updated object.
 *
 * @return Axismundi_Activity|WP_Error|null Null means the profile is not public
 *                                          or Object Projections is unavailable.
 */
function axismundi_act_record_actor_update( Axismundi_Actor $actor ) {
	if ( ! $actor->is_local() || 'public' !== $actor->get_status() || ! function_exists( 'axismundi_op_actor_transform' ) || ! function_exists( 'axismundi_act_resolve_audience' ) ) {
		return null;
	}

	$object = axismundi_op_actor_transform( $actor );
	if ( ! is_array( $object ) || ! hash_equals( $actor->get_uri(), axismundi_act_member_uri( $object['id'] ?? '' ) ) ) {
		return new WP_Error( 'ax_act_actor_projection', __( 'The Actor profile could not be projected for federation.', 'axismundi-activities' ) );
	}

	$fingerprint = axismundi_act_lifecycle_fingerprint( $object );
	if ( '' === $fingerprint ) {
		return new WP_Error( 'ax_act_actor_projection', __( 'The Actor profile could not be normalized for federation.', 'axismundi-activities' ) );
	}

	$latest = axismundi_act_get_object_lifecycle( $actor->get_uri() );
	if ( $latest instanceof Axismundi_Activity && 'Delete' !== $latest->get_type() ) {
		$previous = $latest->get_payload()['object'] ?? null;
		if ( is_array( $previous )
			&& hash_equals( $latest->get_actor_uri(), $actor->get_uri() )
			&& hash_equals( axismundi_act_lifecycle_fingerprint( $previous ), $fingerprint ) ) {
			return $latest;
		}
	}

	$audience = axismundi_act_resolve_audience( $actor, 'public' );
	if ( is_wp_error( $audience ) ) {
		return $audience;
	}

	$generation = $latest instanceof Axismundi_Activity ? $latest->get_uri() : 'initial';
	return axismundi_act_record_source_activity(
		array(
			'type'   => 'Update',
			'actor'  => $actor->get_uri(),
			'object' => $object,
			'to'     => $audience['to'],
			'cc'     => $audience['cc'],
		),
		'outbound',
		'local-actor-update:' . $actor->get_uri() . ':after:' . $generation . ':snapshot:' . $fingerprint
	);
}

/** Record a durable local Actor profile edit without blocking the settings request. */
function axismundi_act_on_actor_profile_updated( $actor ) : void {
	if ( ! $actor instanceof Axismundi_Actor ) {
		return;
	}
	$result = axismundi_act_record_actor_update( $actor );
	if ( is_wp_error( $result ) ) {
		/** @param WP_Error $result @param Axismundi_Actor $actor Failed public Actor Update. */
		do_action( 'axismundi_act_actor_update_failed', $result, $actor );
	}
}
add_action( 'axismundi_actors_local_actor_profile_updated', 'axismundi_act_on_actor_profile_updated' );
