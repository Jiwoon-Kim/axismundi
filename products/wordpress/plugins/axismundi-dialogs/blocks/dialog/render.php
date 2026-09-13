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

/*
 * Saved content from the retired trigger block. It had this block's name, so
 * its comments now resolve to this host; it stored templatePart, variant and
 * trigger attributes and never inner blocks. Rendered as the host it would be
 * a blank native <dialog> with nothing to open it, so it renders nothing - and
 * so does a host with no content, which has nothing to show.
 */
foreach ( array( 'templatePart', 'variant', 'triggerLabel', 'triggerIcon', 'closeOnBackdrop', 'scrollMode' ) as $axismundi_dialogs_host_legacy_key ) {
	if ( array_key_exists( $axismundi_dialogs_host_legacy_key, $attributes ) ) {
		return;
	}
}
if ( '' === trim( (string) $content ) ) {
	return;
}

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
 * show(): no scrim, and the page behind it stays usable. The two differ in how
 * they open, not in where they render. Pushing the page's content aside for a
 * standard sheet is not implemented yet; it is decided with the admin bar.
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

/*
 * Initial focus. Opening a <dialog> focuses its first focusable descendant -
 * the Cancel button, the drag handle, or a scrolling Content group - and a
 * button focused that way wears its focus state layer as if it had been chosen.
 * The dialog itself takes focus instead (blocks/dialog/view.js, on `toggle`):
 * assistive technology announces it by its name, and Tab reaches the first
 * control from there. tabindex -1 makes it focusable without putting it in the
 * tab order. `autofocus` on the dialog would say the same in HTML, but measured
 * in Chrome it did not: focus still went to the first focusable descendant.
 */
$axismundi_dialogs_host_attrs = array(
	'data-presentation' => $axismundi_dialogs_host_presentation,
	'data-render-mode'  => $axismundi_dialogs_host_render_mode,
	'closedby'          => $axismundi_dialogs_host_dismissal,
	'tabindex'          => '-1',
);
if ( $axismundi_dialogs_host_is_sheet ) {
	$axismundi_dialogs_host_attrs['data-modality'] = $axismundi_dialogs_host_modality;
}
if ( 'sheet-side' === $axismundi_dialogs_host_presentation ) {
	$axismundi_dialogs_host_attrs['data-attachment'] = 'detached' === ( $attributes['attachment'] ?? '' ) ? 'detached' : 'docked';
	$axismundi_dialogs_host_attrs['data-edge']       = 'start' === ( $attributes['edge'] ?? '' ) ? 'start' : 'end';
}

// How a docked standard side sheet shares the page (blocks/dialog/view.js):
// resizing the content is the default and writes nothing; moving the whole page
// aside is the Page setting's other choice.
if ( 'standard-sheet' === $axismundi_dialogs_host_render_mode
	&& 'sheet-side' === $axismundi_dialogs_host_presentation
	&& 'move' === ( $attributes['pageShare'] ?? '' ) ) {
	$axismundi_dialogs_host_attrs['data-page-share'] = 'move';
}

/*
 * The name. An author's aria-label (the ariaLabel block support, which core
 * writes into the wrapper attributes) is a deliberate choice and wins.
 * Otherwise the first heading names the dialog - the headline, in M3's
 * anatomy. A heading without an id gets a unique one here: a starter pattern
 * cannot carry a fixed anchor, because two surfaces made from one pattern
 * would then share it on a page. <dialog> already has the dialog role, so no
 * role attribute is written.
 */
if ( '' === trim( (string) ( $attributes['ariaLabel'] ?? '' ) ) ) {
	$axismundi_dialogs_host_tags = new WP_HTML_Tag_Processor( (string) $content );
	while ( $axismundi_dialogs_host_tags->next_tag() ) {
		if ( in_array( $axismundi_dialogs_host_tags->get_tag(), array( 'H1', 'H2', 'H3', 'H4', 'H5', 'H6' ), true ) ) {
			$axismundi_dialogs_host_id = $axismundi_dialogs_host_tags->get_attribute( 'id' );
			if ( ! is_string( $axismundi_dialogs_host_id ) || '' === $axismundi_dialogs_host_id ) {
				$axismundi_dialogs_host_id = wp_unique_id( 'dialog-surface-headline-' );
				$axismundi_dialogs_host_tags->set_attribute( 'id', $axismundi_dialogs_host_id );
			}
			$axismundi_dialogs_host_attrs['aria-labelledby'] = $axismundi_dialogs_host_id;
			break;
		}
	}
	$content = $axismundi_dialogs_host_tags->get_updated_html();
}

// The inner blocks are the dialog's own children, as a Group's are its
// element's: block supports - background, padding, radius, shadow - land on
// the same element the content sits in, so there is no inner container for
// them to disagree with.
/*
 * Block spacing. Core's layout support would write the gap into the block's
 * container rule, and whether that beats the theme's global flex gap would be
 * a question of stylesheet order rather than intent - measured: block
 * stylesheets print first, global styles next, block supports last, all at the
 * same specificity. block.json skips that serialization and the value goes
 * inline here, where it beats every default; with no value, the contract's own
 * default (style.css) beats the theme's. The sanitizing and preset conversion
 * are core's (wp_sanitize_block_gap_value, wp_get_layout_style).
 */
$axismundi_dialogs_host_gap = $attributes['style']['spacing']['blockGap'] ?? null;
if ( is_array( $axismundi_dialogs_host_gap ) ) {
	$axismundi_dialogs_host_gap = $axismundi_dialogs_host_gap['top'] ?? null;
}
if ( is_string( $axismundi_dialogs_host_gap ) && '' !== $axismundi_dialogs_host_gap && ! preg_match( '%[\\\(&=}]|/\*%', $axismundi_dialogs_host_gap ) ) {
	if ( str_contains( $axismundi_dialogs_host_gap, 'var:preset|spacing|' ) ) {
		$axismundi_dialogs_host_gap = 'var(--wp--preset--spacing--' . _wp_to_kebab_case( substr( $axismundi_dialogs_host_gap, strrpos( $axismundi_dialogs_host_gap, '|' ) + 1 ) ) . ')';
	}
	$axismundi_dialogs_host_attrs['style'] = 'gap:' . $axismundi_dialogs_host_gap . ';';
}

/*
 * The drag handle, on bottom sheets. The Material 3 Design Kit layers a bottom
 * sheet as Header > Drag handle, then Content, so the switch renders the Header
 * too: without a handle there is no header, and the Content group's own padding
 * is the sheet's top edge. The handle is a 32x4 rounded rectangle, but it is
 * not decoration: the
 * bottom sheet accessibility page makes it a button in the tab order, labelled,
 * that cycles the sheet's heights. This sheet has two - the initial height,
 * capped at half the window, and expanded - so the handle toggles between them
 * (view.js) and reports which with aria-expanded. Dragging is not needed for
 * any of it: a press does what a drag would, which is the single-pointer
 * alternative M3 requires.
 */
$axismundi_dialogs_host_handle = '';
if ( 'sheet-bottom' === $axismundi_dialogs_host_presentation && ! empty( $attributes['showDragHandle'] ) ) {
	$axismundi_dialogs_host_handle = sprintf(
		'<header class="wp-block-axismundi-dialog__header"><button type="button" class="wp-block-axismundi-dialog__drag-handle" aria-expanded="false" aria-label="%s"></button></header>',
		esc_attr__( 'Resize sheet', 'axismundi-dialogs' )
	);
}

printf(
	'<dialog %1$s>%3$s%2$s</dialog>',
	get_block_wrapper_attributes( $axismundi_dialogs_host_attrs ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core.
	$content, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered inner blocks.
	$axismundi_dialogs_host_handle // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal markup, label escaped above.
);
