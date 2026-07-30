<?php
/**
 * Cache complete remote Objects observed in verified inbound Create and Update activities.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** Store one complete, self-consistent Object after the inbound ledger commit. */
function axismundi_op_observe_inbound_object( Axismundi_Activity $activity ) : void {
	if ( ! in_array( $activity->get_type(), array( 'Create', 'Update' ), true ) || 'inbound' !== $activity->get_direction() ) {
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
add_action( 'axismundi_act_activity_recorded', 'axismundi_op_observe_inbound_object', 20 );

/** Queue the URI-only target of a public inbound Announce for cache acquisition. */
function axismundi_op_observe_inbound_announce( Axismundi_Activity $activity ) : void {
	if ( 'Announce' !== $activity->get_type()
		|| 'inbound' !== $activity->get_direction()
		|| ! function_exists( 'axismundi_act_is_publicly_renderable' )
		|| ! axismundi_act_is_publicly_renderable( $activity )
		|| ! function_exists( 'axismundi_op_schedule_announced_object_fetch' )
	) {
		return;
	}
	$payload = $activity->get_payload();
	// An embedded Activity is handled by the Group-forwarding path below. Its id is an
	// Activity id, not an Object URL suitable for the object fetch queue.
	if ( ! is_scalar( $payload['object'] ?? null ) ) {
		return;
	}
	axismundi_op_schedule_announced_object_fetch( $activity->get_object_uri() );
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_op_observe_inbound_announce', 21 );

/** @return bool Whether an ActivityStreams member contains one exact URI. */
function axismundi_op_payload_member_contains_uri( $value, string $expected_uri ) : bool {
	if ( is_scalar( $value ) ) {
		return hash_equals( $expected_uri, axismundi_op_remote_member_uri( $value ) );
	}
	if ( ! is_array( $value ) ) {
		return false;
	}
	if ( array_is_list( $value ) ) {
		foreach ( $value as $member ) {
			if ( axismundi_op_payload_member_contains_uri( $member, $expected_uri ) ) {
				return true;
			}
		}
		return false;
	}
	return axismundi_op_payload_member_contains_uri( $value['id'] ?? $value['href'] ?? '', $expected_uri );
}

/**
 * Return the embedded Create endorsed by a remote Group Announce, or null.
 *
 * FEP-1b12 makes the Group Announce the approval boundary. We only unwrap a complete,
 * internally consistent Create addressed to that same Group; a bare object URI remains the
 * existing fetch case and a Person's boost never gains Group semantics.
 *
 * @return array<string,mixed>|null
 */
function axismundi_op_inbound_group_announce_create( Axismundi_Activity $announce ) : ?array {
	if ( 'inbound' !== $announce->get_direction() || 'Announce' !== $announce->get_type()
		|| ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
		return null;
	}
	$group = axismundi_actors_get_by_uri( $announce->get_actor_uri() );
	if ( ! $group instanceof Axismundi_Actor || $group->is_local() || 'Group' !== $group->get_type() ) {
		return null;
	}
	$payload = $announce->get_payload();
	$create  = $payload['object'] ?? null;
	if ( ! is_array( $create ) || array_is_list( $create ) || 'Create' !== (string) ( $create['type'] ?? '' )
		|| '' === axismundi_op_remote_member_uri( $create['id'] ?? '' ) ) {
		return null;
	}
	$author_uri = axismundi_op_remote_member_uri( $create['actor'] ?? '' );
	$object     = $create['object'] ?? null;
	if ( '' === $author_uri || ! is_array( $object ) || array_is_list( $object )
		|| ! hash_equals( $author_uri, axismundi_op_remote_member_uri( $object['attributedTo'] ?? '' ) ) ) {
		return null;
	}
	foreach ( array( 'audience', 'to', 'cc' ) as $property ) {
		if ( axismundi_op_payload_member_contains_uri( $create[ $property ] ?? null, $group->get_uri() )
			|| axismundi_op_payload_member_contains_uri( $object[ $property ] ?? null, $group->get_uri() ) ) {
			return $create;
		}
	}
	return null;
}

/**
 * Preserve the original Create endorsed by an inbound remote Group Announce.
 *
 * The nested activity is recorded unchanged, which lets the normal Create observer cache its
 * Object. It is not made public here: the outer Announce's followers-only audience remains the
 * authorization boundary for a future recipient-scoped timeline.
 */
function axismundi_op_unwrap_inbound_group_announce( Axismundi_Activity $announce ) : void {
	$create = axismundi_op_inbound_group_announce_create( $announce );
	if ( null === $create || ! function_exists( 'axismundi_act_record_activity' ) ) {
		return;
	}
	$recorded = axismundi_act_record_activity( $create, 'inbound' );
	if ( $recorded instanceof Axismundi_Activity ) {
		do_action( 'axismundi_op_inbound_group_announce_unwrapped', $announce, $recorded );
	}
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_op_unwrap_inbound_group_announce', 22 );
