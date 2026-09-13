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
