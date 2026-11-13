<?php
/**
 * A refused Update names what it refused, and refuses exactly what it refused before. Dev-only.
 *
 * The decision is the contract this guards: adding reasons to the payload must not widen or
 * narrow what the gate accepts. The last check submits a well-formed patch for an Object that
 * does not exist, so a later error code proves the gate let the shape through.
 */
defined( 'ABSPATH' ) || exit( 1 );
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; };

$actor = null;
$user = 0;
foreach ( $GLOBALS['wpdb']->get_results( 'SELECT m.identity_id,m.principal_id FROM ' . axismundi_actors_managers_table() . " m JOIN {$GLOBALS['wpdb']->users} u ON u.ID=m.principal_id WHERE m.principal_kind='wp_user'", ARRAY_A ) as $row ) {
	$candidate = axismundi_actors_get_by_identity( (int) $row['identity_id'] );
	if ( $candidate instanceof Axismundi_Actor && true === axismundi_act_outbox_authorize( (int) $row['principal_id'], $candidate ) ) {
		$actor = $candidate;
		$user = (int) $row['principal_id'];
		break;
	}
}
$assert( $actor instanceof Axismundi_Actor, 'Existing Account operator available' );

$submit = static function ( array $object, array $extra = array() ) use ( $user, $actor ) {
	return axismundi_act_submit_local_object( $user, $actor, array( 'type' => 'Update', 'actor' => $actor->get_uri(), 'object' => $object ) + $extra );
};
$absent = axismundi_op_local_object_uri( wp_generate_uuid4() );

$assert( function_exists( 'axismundi_op_local_note_server_set' ), 'The projection declares its server-set properties' );
$owned = axismundi_op_local_note_server_set();
$assert( in_array( 'published', $owned, true ) && in_array( 'id', $owned, true ), 'Declared list covers the store-assigned properties' );
$assert( function_exists( 'axismundi_op_local_property_known' ), 'The projection uses the vocabulary registry' );
$assert( axismundi_op_local_property_known( 'duration' ) && ! axismundi_op_local_property_known( 'nonsenseField' ), 'Vocabulary is not limited to Note fields' );

// A property the author simply got wrong is not a property the server owns.
$error = $submit( array( 'id' => $absent, 'content' => '<p>x</p>', 'nonsenseField' => 1 ) );
$assert( is_wp_error( $error ) && 'ax_c2s_update_patch' === $error->get_error_code(), 'Unknown property is refused' );
$data = $error->get_error_data();
$assert( 422 === ( $data['status'] ?? 0 ), 'Refusal keeps its status' );
$assert( 'unsupported_property' === ( $data['properties']['object.nonsenseField'] ?? '' ), 'A property nobody has heard of is named as such' );
$assert( ! isset( $data['properties']['object.content'] ), 'A writable property is not named in the refusal' );

$error = $submit( array( 'id' => $absent, 'content' => '<p>x</p>', 'published' => gmdate( 'c' ) ) );
$assert( is_wp_error( $error ) && 'server_set' === ( $error->get_error_data()['properties']['object.published'] ?? '' ), 'Server-set property is named as server-set' );

// Recognised is not writable: attribution has its own policy, so this is not a typo.
$error = $submit( array( 'id' => $absent, 'content' => '<p>x</p>', 'attributedTo' => $actor->get_uri() ) );
$assert( is_wp_error( $error ) && 'not_updatable' === ( $error->get_error_data()['properties']['object.attributedTo'] ?? '' ), 'A recognised property this operation does not accept is not called unknown' );

$error = $submit( array( 'content' => '<p>x</p>' ) );
$assert( is_wp_error( $error ) && 'required' === ( $error->get_error_data()['properties']['object.id'] ?? '' ), 'A patch without a target names id' );

$error = $submit( array( 'id' => $absent ) );
$assert( is_wp_error( $error ) && in_array( 'no_editable_properties', $error->get_error_data()['reasons'] ?? array(), true ), 'A patch that edits nothing says so' );

// Addressing rides on the Object in this model; the Update activity may not carry its own.
$public = array( 'https://www.w3.org/ns/activitystreams#Public' );
$error = $submit( array( 'id' => $absent, 'content' => '<p>x</p>' ), array( 'to' => $public ) );
$assert( is_wp_error( $error ) && 'not_updatable' === ( $error->get_error_data()['properties']['activity.to'] ?? '' ), 'Activity addressing is named, not swallowed' );

// Both documents carry `to`. Neither refusal may overwrite the other.
$error = $submit( array( 'id' => $absent, 'content' => '<p>x</p>', 'to' => $public ), array( 'to' => $public ) );
$both = is_wp_error( $error ) ? $error->get_error_data()['properties'] : array();
$assert( 'not_updatable' === ( $both['object.to'] ?? '' ) && 'not_updatable' === ( $both['activity.to'] ?? '' ), 'Both to inputs are reported' );

$error = $submit( array( 'id' => $absent, 'type' => 'Article', 'content' => '<p>x</p>' ) );
$assert( is_wp_error( $error ) && 'unsupported' === ( $error->get_error_data()['properties']['object.type'] ?? '' ), 'A type this slice does not author is named' );

// The decision is unchanged: a well-formed patch still passes this gate and fails further on.
$error = $submit( array( 'id' => $absent, 'content' => '<p>Edited</p>' ) );
$assert( is_wp_error( $error ) && 'ax_c2s_update_patch' !== $error->get_error_code(), 'A well-formed patch is not refused by the shape gate' );
$assert( 'ax_c2s_update_actor' === $error->get_error_code(), 'It is refused later, for an Object that does not exist' );

echo "\n== {$checks} checks, 0 failed ==\n";
