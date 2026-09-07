<?php
/**
 * Media Archive Title server render.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

$axismundi_media_title_route = (string) get_query_var( 'ax_media_archive' );
$axismundi_media_title = __( 'Media', 'axismundi-media-library' );
if ( 'folder' === $axismundi_media_title_route ) {
	$axismundi_media_title_term = get_term( (int) get_query_var( 'ax_media_folder' ), AXISMUNDI_MEDIA_FOLDER_TAX );
	if ( $axismundi_media_title_term instanceof WP_Term ) {
		$axismundi_media_title = $axismundi_media_title_term->name;
	}
} elseif ( 'owner' === $axismundi_media_title_route ) {
	$axismundi_media_title_raw = (string) get_query_var( 'ax_media_owner' );
	$axismundi_media_title_user = ctype_digit( $axismundi_media_title_raw )
		? get_user_by( 'id', (int) $axismundi_media_title_raw )
		: get_user_by( 'slug', $axismundi_media_title_raw );
	if ( $axismundi_media_title_user ) {
		$axismundi_media_title = $axismundi_media_title_user->display_name;
	}
}

$axismundi_media_title_level = max( 1, min( 6, (int) ( $attributes['level'] ?? 1 ) ) );
$axismundi_media_title_wrapper = get_block_wrapper_attributes( array( 'class' => 'wp-block-heading' ) );
printf(
	'<h%1$d %2$s>%3$s</h%1$d>',
	esc_attr( (string) $axismundi_media_title_level ),
	$axismundi_media_title_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated block wrapper attributes.
	esc_html( $axismundi_media_title )
);
