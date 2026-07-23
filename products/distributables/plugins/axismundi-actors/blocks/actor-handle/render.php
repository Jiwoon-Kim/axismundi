<?php
/**
 * Actor Handle server render.
 *
 * The federated address is its own block so it can take a different font family
 * and size from the display name. It is identity, not decoration: the full
 * `@user@host` form stays the default because the same username exists on many
 * hosts.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

$axismundi_actor_handle_subject = axismundi_actors_resolve_block_subject( (string) ( $block->context['axismundi/actorId'] ?? '' ) );
if ( ! is_array( $axismundi_actor_handle_subject ) ) {
	return;
}
$axismundi_actor_handle_value = trim( (string) $axismundi_actor_handle_subject['handle'] );
if ( '' === $axismundi_actor_handle_value && ! empty( $axismundi_actor_handle_subject['preferred_username'] ) ) {
	$axismundi_actor_handle_value = '@' . ltrim( trim( (string) $axismundi_actor_handle_subject['preferred_username'] ), '@' );
}
if ( '' === $axismundi_actor_handle_value ) {
	return;
}
if ( ! empty( $attributes['shortForm'] ) ) {
	$axismundi_actor_handle_parts = explode( '@', ltrim( $axismundi_actor_handle_value, '@' ) );
	$axismundi_actor_handle_value = '@' . $axismundi_actor_handle_parts[0];
}
$axismundi_actor_handle_url   = (string) $axismundi_actor_handle_subject['url'];
$axismundi_actor_handle_inner = esc_html( $axismundi_actor_handle_value );
if ( ! empty( $attributes['isLink'] ) && '' !== $axismundi_actor_handle_url ) {
	$axismundi_actor_handle_inner = '<a href="' . esc_url( $axismundi_actor_handle_url ) . '" rel="author">' . $axismundi_actor_handle_inner . '</a>';
}
printf(
	'<span %1$s>%2$s</span>',
	get_block_wrapper_attributes( array( 'class' => 'ax-actor-handle' ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated block wrapper attributes.
	$axismundi_actor_handle_inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
);
