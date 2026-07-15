<?php
/**
 * First-party Axismundi Actor projection.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** Public representation gate for one local Actor. */
function axismundi_op_actor_visible( Axismundi_Actor $actor ) : bool {
	return function_exists( 'axismundi_actors_is_public_profile' )
		&& axismundi_actors_is_public_profile( $actor );
}

/** Project one Axismundi Actor; transport fields are supplied by a bridge filter. */
function axismundi_op_actor_transform( Axismundi_Actor $actor ) : array {
	$profile = function_exists( 'axismundi_actors_profile_data' )
		? axismundi_actors_profile_data( $actor )
		: array();
	$object  = array(
		'id'                      => $actor->get_uri(),
		'type'                    => $actor->get_type(),
		'url'                     => $actor->get_profile_url(),
		'preferredUsername'       => $actor->get_preferred_username(),
		'name'                    => (string) ( $profile['name'] ?? $actor->get_display_name() ),
		'summary'                 => (string) ( $profile['summary'] ?? '' ),
		'manuallyApprovesFollowers' => true === $actor->get_policy_flag( 'manually_approves_followers' ),
	);
	if ( null !== $actor->get_policy_flag( 'discoverable' ) ) {
		$object['discoverable'] = $actor->get_policy_flag( 'discoverable' );
	}
	if ( null !== $actor->get_policy_flag( 'indexable' ) ) {
		$object['indexable'] = $actor->get_policy_flag( 'indexable' );
	}
	if ( '' !== $actor->get_published_at() ) {
		$object['published'] = mysql2date( DATE_RFC3339, $actor->get_published_at(), false );
	}
	$avatar_id = $actor->get_avatar_attachment_id();
	$header_id = $actor->get_header_attachment_id();
	$avatar    = $avatar_id > 0 ? wp_get_attachment_image_url( $avatar_id, 'medium' ) : (string) ( $profile['avatar'] ?? '' );
	$header    = $header_id > 0 ? wp_get_attachment_image_url( $header_id, 'large' ) : '';
	if ( '' !== $avatar ) {
		$object['icon'] = array( 'type' => 'Image', 'url' => esc_url_raw( $avatar ) );
	}
	if ( '' !== $header ) {
		$object['image'] = array( 'type' => 'Image', 'url' => esc_url_raw( $header ) );
	}

	/**
	 * Supply protocol transport properties without transferring representation ownership.
	 *
	 * @param array<string,mixed> $fields inbox/publicKey/endpoints/etc.
	 * @param Axismundi_Actor     $actor  Local Actor.
	 */
	$fields  = (array) apply_filters( 'axismundi_op_actor_transport_fields', array(), $actor );
	$allowed = array_intersect_key( $fields, array_flip( array( 'inbox', 'outbox', 'followers', 'following', 'featured', 'endpoints', 'publicKey' ) ) );
	return array_merge( $object, $allowed );
}

/** Register the Actor transformer when the Actors plugin is available. */
function axismundi_op_register_actor_transformers() : void {
	if ( ! class_exists( 'Axismundi_Actor' ) ) {
		return;
	}
	axismundi_op_register_object_transformer(
		'axismundi-actor',
		array(
			'supports'   => static fn( $source ) : bool => $source instanceof Axismundi_Actor,
			'object_uri' => static fn( Axismundi_Actor $actor ) : string => $actor->get_uri(),
			'transform'  => 'axismundi_op_actor_transform',
			'visible'    => 'axismundi_op_actor_visible',
			'priority'   => 5,
		)
	);
}
add_action( 'axismundi_op_register_transformers', 'axismundi_op_register_actor_transformers' );
