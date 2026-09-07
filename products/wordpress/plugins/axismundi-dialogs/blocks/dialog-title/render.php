<?php
/**
 * axismundi/dialog-title — server render.
 *
 * Title priority: the block's own authored content, then the title the Sheet
 * block delivers through a scoped render_block_context filter (the part is
 * rendered outside the Sheet block's tree, so it arrives as $block->context),
 * then a generic label. Authored content wins so a part can carry a fixed
 * heading; leaving it empty falls back to the current sheet's dynamic title.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

$axismundi_dialogs_title_level = isset( $attributes['level'] ) ? (int) $attributes['level'] : 2;
if ( $axismundi_dialogs_title_level < 1 || $axismundi_dialogs_title_level > 6 ) {
	$axismundi_dialogs_title_level = 2;
}
$axismundi_dialogs_title_tag = 'h' . $axismundi_dialogs_title_level;

$axismundi_dialogs_title_content = wp_kses_post( (string) ( $attributes['content'] ?? '' ) );
$axismundi_dialogs_title_is_authored = '' !== trim( wp_strip_all_tags( $axismundi_dialogs_title_content ) );

if ( $axismundi_dialogs_title_is_authored ) {
	$axismundi_dialogs_title_html = $axismundi_dialogs_title_content;
} else {
	$axismundi_dialogs_title_text = '';
	if ( isset( $block->context['axismundi/dialogTitle'] ) ) {
		$axismundi_dialogs_title_text = trim( (string) $block->context['axismundi/dialogTitle'] );
	}
	if ( '' === $axismundi_dialogs_title_text ) {
		$axismundi_dialogs_title_text = __( 'Sheet', 'axismundi-dialogs' );
	}
	$axismundi_dialogs_title_html = esc_html( $axismundi_dialogs_title_text );
}

$axismundi_dialogs_title_classes = 'ax-dialog-title';
if ( ! empty( $attributes['textAlign'] ) ) {
	$axismundi_dialogs_title_classes .= ' has-text-align-' . sanitize_html_class( (string) $attributes['textAlign'] );
}

$axismundi_dialogs_title_wrapper = get_block_wrapper_attributes( array( 'class' => $axismundi_dialogs_title_classes ) );

printf(
	'<%1$s %2$s>%3$s</%1$s>',
	tag_escape( $axismundi_dialogs_title_tag ),
	$axismundi_dialogs_title_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() is escaped.
	$axismundi_dialogs_title_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- authored content is wp_kses_post()'d; fallback is esc_html()'d.
);
