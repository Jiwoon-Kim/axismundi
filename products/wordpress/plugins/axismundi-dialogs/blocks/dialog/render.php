<?php
/**
 * axismundi/dialog - server render.
 *
 * The native <dialog> host at the root of a dialog-surface template part
 * (includes/surface.php). It owns the surface and its lifecycle attributes;
 * the part's inner blocks own the anatomy - header, body, actions - which is
 * why nothing here assumes one.
 *
 * Opening, the page-end render and the stable id arrive with the trigger step.
 * Until then the dialog renders closed, which is what a <dialog> without
 * `open` is.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

$axismundi_dialogs_host_presentation = in_array( $attributes['presentation'] ?? '', array( 'dialog-basic', 'dialog-full-screen', 'sheet-bottom', 'sheet-side' ), true )
	? $attributes['presentation']
	: 'dialog-basic';
$axismundi_dialogs_host_is_sheet = str_starts_with( $axismundi_dialogs_host_presentation, 'sheet-' );

// A dialog is always modal; only a sheet may be standard.
$axismundi_dialogs_host_modality = $axismundi_dialogs_host_is_sheet && 'standard' === ( $attributes['modality'] ?? '' )
	? 'standard'
	: 'modal';

/*
 * Render mode, derived rather than stored. A modal surface opens with
 * showModal(): top layer, scrim, inert page. A standard sheet opens with
 * show() and shares the page, pushing its content aside. The two differ in how
 * they open, not in where they render.
 */
$axismundi_dialogs_host_render_mode = 'standard' === $axismundi_dialogs_host_modality ? 'standard-sheet' : 'modal-dialog';

/*
 * Dismissal is the HTML closedby attribute. A standard sheet never
 * light-dismisses: the page beside it stays usable, so a click on the page is
 * not a request to close the sheet.
 */
$axismundi_dialogs_host_dismissal = in_array( $attributes['dismissal'] ?? '', array( 'any', 'closerequest', 'none' ), true )
	? $attributes['dismissal']
	: 'any';
if ( 'standard-sheet' === $axismundi_dialogs_host_render_mode && 'any' === $axismundi_dialogs_host_dismissal ) {
	$axismundi_dialogs_host_dismissal = 'closerequest';
}

$axismundi_dialogs_host_attrs = array(
	'data-presentation' => $axismundi_dialogs_host_presentation,
	'data-render-mode'  => $axismundi_dialogs_host_render_mode,
	'closedby'          => $axismundi_dialogs_host_dismissal,
);
if ( $axismundi_dialogs_host_is_sheet ) {
	$axismundi_dialogs_host_attrs['data-modality'] = $axismundi_dialogs_host_modality;
}
if ( 'sheet-side' === $axismundi_dialogs_host_presentation ) {
	$axismundi_dialogs_host_attrs['data-attachment'] = 'detached' === ( $attributes['attachment'] ?? '' ) ? 'detached' : 'docked';
	$axismundi_dialogs_host_attrs['data-edge']       = 'start' === ( $attributes['edge'] ?? '' ) ? 'start' : 'end';
}

// The name is the first heading that carries an id - the headline, in M3's
// anatomy. Without one, the author's label stands in. <dialog> already has the
// dialog role, so no role attribute is written.
$axismundi_dialogs_host_tags = new WP_HTML_Tag_Processor( (string) $content );
$axismundi_dialogs_host_name = '';
while ( $axismundi_dialogs_host_tags->next_tag() ) {
	if ( in_array( $axismundi_dialogs_host_tags->get_tag(), array( 'H1', 'H2', 'H3', 'H4', 'H5', 'H6' ), true ) ) {
		$axismundi_dialogs_host_id = $axismundi_dialogs_host_tags->get_attribute( 'id' );
		if ( is_string( $axismundi_dialogs_host_id ) && '' !== $axismundi_dialogs_host_id ) {
			$axismundi_dialogs_host_name = $axismundi_dialogs_host_id;
		}
		break;
	}
}
if ( '' !== $axismundi_dialogs_host_name ) {
	$axismundi_dialogs_host_attrs['aria-labelledby'] = $axismundi_dialogs_host_name;
} elseif ( '' !== trim( (string) ( $attributes['label'] ?? '' ) ) ) {
	$axismundi_dialogs_host_attrs['aria-label'] = trim( (string) $attributes['label'] );
}

printf(
	'<dialog %1$s><div class="wp-block-axismundi-dialog__container">%2$s</div></dialog>',
	get_block_wrapper_attributes( $axismundi_dialogs_host_attrs ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core.
	$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered inner blocks.
);
