<?php
/**
 * Actor Avatar server render.
 *
 * The outer element carries only the block's own identity -- selection outline,
 * layout supports -- and stays a plain, unrounded box, exactly as Core's Avatar
 * does. The round avatar is the inner `<img>` (or the `<a>` wrapping it when
 * linked); border, shadow, and radius from the block supports are read directly
 * and applied there ({@see axismundi_actors_avatar_border_attributes()},
 * {@see axismundi_actors_avatar_shadow_style()}). This is deliberate, not
 * incidental: WordPress draws a block's editor selection outline as a
 * pseudo-element sized to the block's own wrapper, so a wrapper that is itself
 * round and clipped (`overflow: hidden`) clips that outline into a circle too.
 * Skipping supports serialization on the wrapper (block.json
 * `__experimentalSkipSerialization`) keeps the outer box genuinely plain, and this
 * file re-applies each support to the inner element by hand -- the same split
 * Core's own Avatar block uses.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

$axismundi_actor_avatar_subject = axismundi_actors_resolve_block_subject( (string) ( $block->context['axismundi/actorId'] ?? '' ) );
if ( ! is_array( $axismundi_actor_avatar_subject ) ) {
	return;
}
$axismundi_actor_avatar_size    = isset( $attributes['size'] ) ? max( 24, min( 256, (int) $attributes['size'] ) ) : 128;
$axismundi_actor_avatar_variant = 'compact' === (string) ( $attributes['variant'] ?? '' ) ? 'compact' : 'profile';
$axismundi_actor_avatar_actor   = $axismundi_actor_avatar_subject['actor'] ?? null;
$axismundi_actor_avatar_html    = $axismundi_actor_avatar_actor instanceof Axismundi_Actor
	? axismundi_actors_avatar_html( $axismundi_actor_avatar_actor, $axismundi_actor_avatar_size )
	: ( '' !== (string) $axismundi_actor_avatar_subject['avatar_url']
		? '<img class="ax-actor-profile__avatar" src="' . esc_url( (string) $axismundi_actor_avatar_subject['avatar_url'] ) . '" alt="" width="' . (int) $axismundi_actor_avatar_size . '" height="' . (int) $axismundi_actor_avatar_size . '" loading="lazy" />'
		: '' );

// No resolvable image is nothing to render: like Core's Avatar, the block does not
// leave a decorated empty circle behind.
if ( '' === $axismundi_actor_avatar_html ) {
	return;
}

$axismundi_actor_avatar_border      = axismundi_actors_avatar_border_attributes( $attributes );
$axismundi_actor_avatar_shadow      = axismundi_actors_avatar_shadow_style( $attributes );
$axismundi_actor_avatar_image_style = trim(
	rtrim( $axismundi_actor_avatar_border['style'], ';' )
	. ( '' !== $axismundi_actor_avatar_shadow ? ';' . rtrim( $axismundi_actor_avatar_shadow, ';' ) : '' ),
	';'
);

// The size drives every variant through one custom property, so the size control
// is authoritative in the profile header, in a compact feed row, and in the editor
// preview alike. It lives on the outer wrapper; the inner image reads its own
// dimensions from it via CSS.
$axismundi_actor_avatar_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'ax-actor-avatar is-' . $axismundi_actor_avatar_variant,
		'style' => '--axismundi-actor-avatar-size:' . (int) $axismundi_actor_avatar_size . 'px',
	)
);

// Merge the border/shadow class and style onto the inner `<img>`.
$axismundi_actor_avatar_img = new WP_HTML_Tag_Processor( $axismundi_actor_avatar_html );
if ( $axismundi_actor_avatar_img->next_tag( 'img' ) ) {
	foreach ( array_filter( explode( ' ', $axismundi_actor_avatar_border['class'] ) ) as $axismundi_actor_avatar_c ) {
		$axismundi_actor_avatar_img->add_class( $axismundi_actor_avatar_c );
	}
	$axismundi_actor_avatar_img->add_class( 'ax-actor-avatar__image' );
	if ( '' !== $axismundi_actor_avatar_image_style ) {
		$axismundi_actor_avatar_existing = (string) $axismundi_actor_avatar_img->get_attribute( 'style' );
		$axismundi_actor_avatar_img->set_attribute(
			'style',
			trim( $axismundi_actor_avatar_image_style . ( '' !== $axismundi_actor_avatar_existing ? ';' . $axismundi_actor_avatar_existing : '' ), ';' )
		);
	}
}
$axismundi_actor_avatar_html = $axismundi_actor_avatar_img->get_updated_html();

// The avatar links to the Actor's profile only when asked and only when a URL
// exists: a remote Actor still being fetched, or a bare descriptor, has none. The
// `<a>` stays a bare link, matching Core's Avatar -- the image carries the shape.
$axismundi_actor_avatar_url     = (string) ( $axismundi_actor_avatar_subject['url'] ?? '' );
$axismundi_actor_avatar_is_link = ! empty( $attributes['isLink'] ) && '' !== $axismundi_actor_avatar_url;
$axismundi_actor_avatar_allowed = array(
	'img' => array( 'src' => true, 'srcset' => true, 'sizes' => true, 'class' => true, 'style' => true, 'alt' => true, 'width' => true, 'height' => true, 'loading' => true, 'decoding' => true ),
	'a'   => array( 'href' => true, 'class' => true, 'target' => true, 'rel' => true ),
);

if ( $axismundi_actor_avatar_is_link ) {
	$axismundi_actor_avatar_target = '_blank' === (string) ( $attributes['linkTarget'] ?? '_self' ) ? '_blank' : '_self';
	$axismundi_actor_avatar_rel    = trim( (string) ( $attributes['rel'] ?? '' ) );
	if ( '_blank' === $axismundi_actor_avatar_target && '' === $axismundi_actor_avatar_rel ) {
		$axismundi_actor_avatar_rel = 'noreferrer noopener';
	}
	$axismundi_actor_avatar_html = sprintf(
		'<a class="ax-actor-avatar__link" href="%1$s" target="%2$s"%3$s>%4$s</a>',
		esc_url( $axismundi_actor_avatar_url ),
		esc_attr( $axismundi_actor_avatar_target ),
		'' !== $axismundi_actor_avatar_rel ? ' rel="' . esc_attr( $axismundi_actor_avatar_rel ) . '"' : '',
		wp_kses( $axismundi_actor_avatar_html, $axismundi_actor_avatar_allowed )
	);
}
?>
<div <?php echo $axismundi_actor_avatar_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated block wrapper attributes. ?>>
	<?php echo wp_kses( $axismundi_actor_avatar_html, $axismundi_actor_avatar_allowed ); ?>
</div>
