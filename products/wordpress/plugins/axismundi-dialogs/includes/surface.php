<?php
/**
 * Where a dialog surface lives: the `dialog-surface` template-part area.
 *
 * A surface is not a block placed in a post. It is a template part the Site
 * Editor manages, the way core keeps the Navigation block apart from its
 * Navigation Overlay part: a trigger in a post or a template names the part,
 * and the part's root block - axismundi/dialog - is the HTML <dialog> host.
 * M3's dialogs and sheets are presentations of that host
 * (products/styleguide, Surface page).
 *
 * This file gives the area the treatment core gives navigation-overlay by
 * name, since a plugin area gets none of it for free (WordPress 7.1,
 * blocks/template-part.php):
 *
 *   - the area exists, so the Site Editor can create parts in it;
 *   - its parts are not offered in the general inserter - a surface is opened
 *     by a trigger, never dropped inline into content;
 *   - the host block is not offered in post and page editors.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

/**
 * The area's slug.
 *
 * Why not `dialog`: it would hide that sheets live here too. Why not
 * `surface`: M3 already uses the word for a family of colour roles.
 *
 * @return string The template-part area slug.
 */
function axismundi_dialogs_surface_area(): string {
	return 'dialog-surface';
}

/**
 * Register the dialog-surface area.
 *
 * @param array $areas Template-part areas.
 * @return array The areas with dialog-surface added.
 */
function axismundi_dialogs_register_dialog_surface_area( $areas ): array {
	$areas   = is_array( $areas ) ? $areas : array();
	$areas[] = array(
		'area'        => axismundi_dialogs_surface_area(),
		'label'       => _x( 'Dialog Surface', 'template part area', 'axismundi-dialogs' ),
		'description' => __( 'Content shown in a dialog layer: dialogs, bottom sheets and side sheets. A button opens it; it is not placed inline in content.', 'axismundi-dialogs' ),
		'icon'        => 'layout',
		// The part's root block renders the <dialog> itself, so the part's own
		// wrapper stays a plain div, as navigation-overlay's does.
		'area_tag'    => 'div',
	);
	return $areas;
}
add_filter( 'default_wp_template_part_areas', 'axismundi_dialogs_register_dialog_surface_area' );

/**
 * Register the dialog-surface starter patterns.
 *
 * The design a new part starts from, as core's navigation-overlay patterns are
 * for that area: offered in the Site Editor's Design list when a part is
 * created in dialog-surface (blockTypes core/template-part/dialog-surface). A
 * pattern is code and is not edited; the part made from it is. No part is
 * created here - a site holds a dialog-surface part only once someone makes
 * one.
 *
 * Loaded in a fixed order, the order M3 introduces them, as core loads its
 * own. No `source` is set: the editor offers a pattern for an area unless its
 * source is one of core's or the pattern directory's.
 *
 * @return void
 */
function axismundi_dialogs_register_dialog_surface_patterns(): void {
	register_block_pattern_category(
		'dialog-surface',
		array(
			'label'       => _x( 'Dialog Surface', 'Block pattern category', 'axismundi-dialogs' ),
			'description' => __( 'Starting designs for dialogs, bottom sheets and side sheets.', 'axismundi-dialogs' ),
		)
	);

	$directory = dirname( __DIR__ ) . '/patterns/';
	foreach ( array( 'basic', 'basic-icon', 'list', 'full-screen', 'bottom-sheet', 'side-sheet-modal', 'side-sheet-standard' ) as $name ) {
		$file = $directory . 'dialog-surface-' . $name . '.php';
		if ( ! is_file( $file ) ) {
			continue;
		}
		$pattern = require $file;
		if ( is_array( $pattern ) ) {
			register_block_pattern( 'axismundi-dialogs/dialog-surface-' . $name, $pattern );
		}
	}
}
add_action( 'init', 'axismundi_dialogs_register_dialog_surface_patterns' );

/**
 * The dialog-surface parts this page's triggers name, each once, rendered.
 *
 * A part is rendered when its first trigger renders, not when it is printed.
 * Block supports - a group's flex direction, a container's gap - write their
 * CSS into a store that core prints once; measured: rendered in wp_footer, a
 * part's layout rules were generated after that store had been printed, and a
 * vertical group inside the dialog laid out as a row. Rendered here, while the
 * template renders, the rules join the store in time. Only the printing waits
 * for the end of the page (axismundi_dialogs_render_dialog_surfaces()).
 *
 * The part's close controls are wired here too, where the <dialog>'s id is
 * known. A part with no Dialog block at its root has nothing a trigger can
 * open, so it is stored as nothing to print.
 *
 * @param WP_Block_Template|null $template A part to add, or null to read.
 * @return array<string, string> Rendered surfaces, by template id.
 */
function axismundi_dialogs_queue_dialog_surface( ?WP_Block_Template $template = null ): array {
	static $queued = array();
	if ( ! $template instanceof WP_Block_Template || isset( $queued[ $template->id ] ) ) {
		return $queued;
	}

	// Claimed before rendering, so a trigger inside the part naming the same part
	// does not render it again.
	$queued[ $template->id ] = '';

	$id   = axismundi_dialogs_dialog_surface_id( $template );
	$tags = new WP_HTML_Tag_Processor( do_blocks( (string) $template->content ) );
	if ( ! $tags->next_tag(
		array(
			'tag_name'   => 'DIALOG',
			'class_name' => 'wp-block-axismundi-dialog',
		)
	) ) {
		return $queued;
	}
	$tags->set_attribute( 'id', $id );
	while ( $tags->next_tag( 'BUTTON' ) ) {
		if ( null !== $tags->get_attribute( 'data-axismundi-dialog-close' ) ) {
			$tags->set_attribute( 'commandfor', $id );
			$tags->set_attribute( 'command', 'close' );
		}
	}
	$queued[ $template->id ] = $tags->get_updated_html();
	return $queued;
}

/**
 * The id a part's <dialog> carries, so any trigger on the page can name it.
 *
 * Stable - built from the part's slug, not counted - so every trigger that
 * names the part names the same element.
 *
 * @param WP_Block_Template $template The part.
 * @return string The element id.
 */
function axismundi_dialogs_dialog_surface_id( WP_Block_Template $template ): string {
	return 'dialog-surface-' . sanitize_html_class( $template->slug );
}

/**
 * The invoker command that opens a part's <dialog>.
 *
 * Read from the part's host block: a standard sheet has no scrim and opens
 * with show(); every other surface opens with showModal(). The same derivation
 * as blocks/dialog/render.php's render mode.
 *
 * `show-modal` is a built-in invoker command. A non-modal show is not - the
 * built-in dialog commands are show-modal, close and request-close - and,
 * measured in Chrome, `command="show"` did nothing and dispatched no event. So a
 * standard sheet's trigger carries the custom command `--toggle`, which reaches
 * the dialog as a `command` event: blocks/dialog/view.js opens the sheet, and
 * closes it when it is already open. A standard sheet leaves the page usable,
 * its trigger included, so the trigger is its toggle. It needs that script; a
 * modal surface does not, and its scrim covers the trigger anyway.
 *
 * @param WP_Block_Template $template The part.
 * @return string `--toggle` or `show-modal`.
 */
function axismundi_dialogs_dialog_surface_command( WP_Block_Template $template ): string {
	foreach ( parse_blocks( (string) $template->content ) as $block ) {
		if ( 'axismundi/dialog' !== ( $block['blockName'] ?? '' ) ) {
			continue;
		}
		$presentation = (string) ( $block['attrs']['presentation'] ?? '' );
		return str_starts_with( $presentation, 'sheet-' ) && 'standard' === ( $block['attrs']['modality'] ?? '' )
			? '--toggle'
			: 'show-modal';
	}
	return 'show-modal';
}

/**
 * Print the queued dialog surfaces at the end of the page.
 *
 * Once per part, however many triggers name it, and at the end of <body> -
 * measured: inside a layout container WordPress layout rules move a surface
 * off its place (blocks/dialog/style.css). Already rendered when queued
 * (axismundi_dialogs_queue_dialog_surface()); only printed here.
 *
 * @return void
 */
function axismundi_dialogs_render_dialog_surfaces(): void {
	foreach ( axismundi_dialogs_queue_dialog_surface() as $html ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks() output, wired in axismundi_dialogs_queue_dialog_surface().
	}
}
add_action( 'wp_footer', 'axismundi_dialogs_render_dialog_surfaces', 0 );

/**
 * Keep dialog-surface parts out of the general inserter.
 *
 * Mirrors what core does for navigation-overlay in
 * build_template_part_block_area_variations() and
 * build_template_part_block_instance_variations(): no "add a part of this
 * area" variation, and instance variations kept with an empty scope, so the
 * part still resolves its title and icon but is never offered for insertion.
 *
 * @param array         $variations Registered variations.
 * @param WP_Block_Type $block_type The block type.
 * @return array The variations.
 */
function axismundi_dialogs_hide_dialog_surface_parts( $variations, $block_type ): array {
	$variations = is_array( $variations ) ? $variations : array();
	if ( ! $block_type instanceof WP_Block_Type || 'core/template-part' !== $block_type->name ) {
		return $variations;
	}

	$kept = array();
	foreach ( $variations as $variation ) {
		if ( axismundi_dialogs_surface_area() !== ( $variation['attributes']['area'] ?? '' ) ) {
			$kept[] = $variation;
			continue;
		}
		if ( str_starts_with( (string) ( $variation['name'] ?? '' ), 'area_' ) ) {
			continue;
		}
		$variation['scope'] = array();
		$kept[]             = $variation;
	}
	return $kept;
}
add_filter( 'get_block_type_variations', 'axismundi_dialogs_hide_dialog_surface_parts', 10, 2 );

/**
 * Keep the host block out of post and page editors.
 *
 * It belongs at the root of a dialog-surface part. The Site Editor (no post in
 * context) and a template part being edited keep it available.
 *
 * @param bool|array<int,string>        $allowed Allowed block names, or true for all.
 * @param WP_Block_Editor_Context|mixed $context Current editor context.
 * @return bool|array<int,string>
 */
function axismundi_dialogs_restrict_dialog_host( $allowed, $context ) {
	if ( ! ( isset( $context->post ) && $context->post instanceof WP_Post ) ) {
		return $allowed;
	}
	if ( 'wp_template_part' === $context->post->post_type ) {
		return $allowed;
	}
	if ( false === $allowed ) {
		return $allowed;
	}
	if ( true === $allowed ) {
		$allowed = array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() );
	}
	return array_values( array_diff( (array) $allowed, array( 'axismundi/dialog' ) ) );
}
add_filter( 'allowed_block_types_all', 'axismundi_dialogs_restrict_dialog_host', 10, 2 );
