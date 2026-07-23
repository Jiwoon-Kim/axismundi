<?php
/**
 * Actor Name server render.
 *
 * Split out of `axismundi/actor-identity` so a display name and a federated
 * handle can carry different typography, font family, and spacing. Actors keeps
 * owning identity presentation for both local and cached remote Actors; the
 * composite block remains for simple profile layouts.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

$axismundi_actor_name_subject = axismundi_actors_resolve_block_subject( (string) ( $block->context['axismundi/actorId'] ?? '' ) );
if ( ! is_array( $axismundi_actor_name_subject ) ) {
	return;
}
$axismundi_actor_name_value = trim( (string) $axismundi_actor_name_subject['name'] );
if ( '' === $axismundi_actor_name_value ) {
	return;
}
$axismundi_actor_name_level = isset( $attributes['level'] ) ? (int) $attributes['level'] : 0;
$axismundi_actor_name_tag   = $axismundi_actor_name_level >= 1 && $axismundi_actor_name_level <= 6 ? 'h' . $axismundi_actor_name_level : 'span';
$axismundi_actor_name_url   = (string) $axismundi_actor_name_subject['url'];
$axismundi_actor_name_link  = ! isset( $attributes['isLink'] ) || (bool) $attributes['isLink'];
$axismundi_actor_name_class = 'ax-actor-name' . ( 'span' === $axismundi_actor_name_tag ? '' : ' wp-block-heading' );
$axismundi_actor_name_inner = esc_html( $axismundi_actor_name_value );
if ( $axismundi_actor_name_link && '' !== $axismundi_actor_name_url ) {
	$axismundi_actor_name_inner = '<a href="' . esc_url( $axismundi_actor_name_url ) . '" rel="author">' . $axismundi_actor_name_inner . '</a>';
}
printf(
	'<%1$s %2$s>%3$s</%1$s>',
	esc_attr( $axismundi_actor_name_tag ),
	get_block_wrapper_attributes( array( 'class' => $axismundi_actor_name_class ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated block wrapper attributes.
	$axismundi_actor_name_inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
);
