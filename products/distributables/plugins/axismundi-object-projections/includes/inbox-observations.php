<?php
/**
 * Cache complete remote Objects observed in verified inbound Create activities.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** Store one complete, self-consistent Object after the inbound ledger commit. */
function axismundi_op_observe_inbound_create_object( Axismundi_Activity $activity ) : void {
	if ( 'Create' !== $activity->get_type() || 'inbound' !== $activity->get_direction() ) {
		return;
	}
	$payload = $activity->get_payload();
	$object  = $payload['object'] ?? null;
	if ( ! is_array( $object ) || array_is_list( $object ) ) {
		return;
	}
	$object_uri = axismundi_op_remote_member_uri( $object['id'] ?? '' );
	$author_uri = axismundi_op_remote_member_uri( $object['attributedTo'] ?? '' );
	if ( '' === $object_uri || '' === $author_uri
		|| ! hash_equals( (string) $activity->get_object_uri(), $object_uri )
		|| ! hash_equals( $activity->get_actor_uri(), $author_uri )
	) {
		return;
	}
	$stored = axismundi_op_remote_object_store( $object, array( 'fetched_at' => current_time( 'mysql', true ) ) );
	if ( is_array( $stored ) ) {
		do_action( 'axismundi_op_remote_object_observed', $stored, $activity );
	}
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_op_observe_inbound_create_object', 20 );
