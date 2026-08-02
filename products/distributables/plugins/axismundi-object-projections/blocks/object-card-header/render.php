<?php
/**
 * Object Card Header server render.
 *
 * The shell only. Its children are ordinary Actor and Object blocks that an author can rearrange,
 * and they resolve their own subject — which is how one saved header names the Object's author on
 * a timeline and the Group on a community surface without the template saying either.
 *
 * The wrapper is the point of the block. `axismundi-object-card__header` was written out four
 * levels deep in every card template, so a template saved by the Site Editor froze a copy of that
 * structure and changing the header afterwards changed the file but not the page. It is also the
 * class the audits use to tell a rendered card from a bare-title fallback, so it is emitted here
 * rather than left to whichever group an author happens to leave around the children.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

$ax_op_header_content = trim( (string) ( $content ?? '' ) );
if ( '' === $ax_op_header_content ) {
	// Every child stood down — a header with nothing in it is a gap, not a row.
	return;
}
printf(
	'<div %s>%s</div>',
	get_block_wrapper_attributes( array( 'class' => 'axismundi-object-card__header' ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes wrapper attributes.
	$ax_op_header_content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks escape their own output.
);
