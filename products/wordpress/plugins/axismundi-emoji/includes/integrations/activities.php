<?php
/**
 * Axismundi Activities integration.
 *
 * A custom reaction carries its FEP-9098 declaration on the Activity, not on the
 * Object it reacts to. Observe that declaration after the Activity commit so an
 * inbound reaction chip can use the same review and cache path as Object content.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit;

/**
 * Observe custom emoji declared by an inbound reaction Activity.
 *
 * References remain attached to the reacted Object because the registry's existing
 * reference model is Object-or-Actor scoped. That keeps retention aligned with the
 * visible reaction surface and avoids inventing a third reference lifecycle solely
 * for Activity envelopes.
 *
 * @param mixed $activity Newly committed Activity.
 * @return void
 */
function axismundi_emoji_observe_inbound_reaction( $activity ) : void {
	if ( ! class_exists( 'Axismundi_Activity' ) || ! ( $activity instanceof Axismundi_Activity ) || 'inbound' !== $activity->get_direction() ) {
		return;
	}
	if ( ! in_array( $activity->get_type(), array( 'Like', 'EmojiReact' ), true ) || ! axismundi_emoji_ready() ) {
		return;
	}
	$object_uri       = $activity->get_object_uri();
	$payload          = $activity->get_payload();
	$declaration_uri  = $activity->get_actor_uri();
	if ( null === $object_uri || '' === $object_uri || ! is_array( $payload ) || empty( $payload['tag'] ) ) {
		return;
	}
	axismundi_emoji_observe_payload( $payload, $object_uri, 'object', $declaration_uri );
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_emoji_observe_inbound_reaction', 40 );
