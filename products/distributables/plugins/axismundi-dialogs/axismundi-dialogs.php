<?php
/**
 * Plugin Name:       Axismundi Dialogs
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-dialogs
 * Description:       Accessible Material Design 3 side / bottom sheet host for Axismundi. The block owns the trigger and the native <dialog> host; a Sheet template part owns the content and layout.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-dialogs
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Sheet collection, host, and close blocks.
 *
 * The host block renders a trigger button plus a native <dialog>; the close block
 * is a small affordance a Sheet template part places wherever it wants a dismiss
 * control (mirrors core's navigation-overlay-close for the Navigation overlay).
 *
 * @return void
 */
function axismundi_dialogs_register_blocks() : void {
	foreach ( array( 'dialogs', 'sheet', 'dialog', 'dialog-close', 'dialog-title', 'dialog-icon' ) as $axismundi_dialogs_block ) {
		$axismundi_dialogs_dir = __DIR__ . '/blocks/' . $axismundi_dialogs_block;
		if ( file_exists( $axismundi_dialogs_dir . '/block.json' ) ) {
			register_block_type( $axismundi_dialogs_dir );
		}
	}

	// Shared runtime surface (open button, <dialog> box, template-part contract,
	// scrim, scroll lock) enqueued for both the Sheet and the Dialog host blocks.
	$axismundi_dialogs_shared = array(
		'handle' => 'axismundi-dialogs-shared',
		'src'    => plugins_url( 'assets/shared.css', __FILE__ ),
		'path'   => __DIR__ . '/assets/shared.css',
		'ver'    => (string) filemtime( __DIR__ . '/assets/shared.css' ),
	);
	wp_enqueue_block_style( 'axismundi/sheet', $axismundi_dialogs_shared );
	wp_enqueue_block_style( 'axismundi/dialog', $axismundi_dialogs_shared );
}
add_action( 'init', 'axismundi_dialogs_register_blocks' );

/**
 * Own the custom `sheet` template-part area.
 *
 * The theme ships the default Sheet parts (theme.json templateParts), but the
 * AREA itself is a plugin concern: it exists only while a Sheet host can consume
 * it. Registering it here gives the Site Editor a labelled, icon'd category for
 * Sheet parts instead of dropping them into "Uncategorized".
 *
 * @param array<int,array<string,mixed>> $areas Registered template-part areas.
 * @return array<int,array<string,mixed>>
 */
function axismundi_dialogs_register_part_area( array $areas ) : array {
	$areas[] = array(
		'area'        => 'sheet',
		'area_tag'    => 'div',
		'label'       => __( 'Sheet', 'axismundi-dialogs' ),
		'description' => __( 'Content shown inside an Axismundi side or bottom sheet.', 'axismundi-dialogs' ),
		'icon'        => 'layout',
	);
	$areas[] = array(
		'area'        => 'dialog',
		'area_tag'    => 'div',
		'label'       => __( 'Dialog', 'axismundi-dialogs' ),
		'description' => __( 'Content shown inside an Axismundi basic or full-screen dialog.', 'axismundi-dialogs' ),
		'icon'        => 'admin-page',
	);

	return $areas;
}
add_filter( 'default_wp_template_part_areas', 'axismundi_dialogs_register_part_area' );

/**
 * Keep the part-only Sheet blocks out of the post/page inserter.
 *
 * sheet-close and sheet-title only do anything inside a Sheet template part (they
 * dismiss / label the enclosing dialog), so they should only be insertable while
 * editing a template part in the Site Editor. The post editor passes a concrete
 * WP_Post context; if that post is not a wp_template_part, drop them from the
 * allowed list. The Site Editor passes no post here, so parts and templates are
 * unaffected.
 *
 * @param bool|array<int,string>        $allowed Allowed block names, or true for all.
 * @param WP_Block_Editor_Context|mixed $context Current editor context.
 * @return bool|array<int,string>
 */
function axismundi_dialogs_restrict_close_block( $allowed, $context ) {
	if ( ! ( isset( $context->post ) && $context->post instanceof WP_Post ) ) {
		return $allowed;
	}
	if ( 'wp_template_part' === $context->post->post_type ) {
		return $allowed;
	}

	if ( true === $allowed ) {
		$allowed = array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() );
	}

	return array_values( array_diff( (array) $allowed, array( 'axismundi/dialog-close', 'axismundi/dialog-title', 'axismundi/dialog-icon' ) ) );
}
add_filter( 'allowed_block_types_all', 'axismundi_dialogs_restrict_close_block', 10, 2 );
