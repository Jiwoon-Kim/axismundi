<?php
/**
 * Omphalos — an Axismundi child theme used as a layout laboratory.
 *
 * Deliberately small. The parent registers the fonts, the token layers, the core
 * block styles and the block style variations, and it addresses all of them
 * through get_template_directory(), so they keep resolving to the parent while
 * this child is active. Nothing here re-enqueues any of it.
 *
 * What this file does own is the layout CSS the block editor cannot express.
 * Its responsive model is a single desktop/mobile stack; a scaffold that turns a
 * vertical rail into a top bar as the window narrows is a reverse transform
 * across more breakpoints than that, so the shell is a CSS contract and the
 * template markup carries only semantic structure.
 *
 * WordPress loads this file before the parent's functions.php, so a hook added
 * here fires against a parent that has not registered anything yet. Anything
 * that needs to see the parent's registrations belongs on `after_setup_theme`
 * or later, not at the top level.
 *
 * @package Omphalos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Layout stylesheets this theme owns, in cascade order.
 *
 * Handle => theme-relative path. Each depends on the parent's last stylesheet so
 * it lands after everything the parent registered, whatever that set becomes.
 */
function omphalos_layout_styles() : array {
	return array(
		'omphalos-color'    => 'assets/styles/color.css',
		'omphalos-scaffold' => 'assets/styles/scaffold.css',
	);
}

/**
 * Mirror the layout CSS into the editor canvas.
 *
 * The canvas is a separate document: without this the shell exists on the front
 * end and nowhere in the editor, which reads as the layout being broken rather
 * than absent.
 */
function omphalos_setup() : void {
	add_editor_style( array_values( array_filter( omphalos_layout_styles(), static function ( string $path ) : bool {
		return file_exists( get_stylesheet_directory() . '/' . $path );
	} ) ) );
}
add_action( 'after_setup_theme', 'omphalos_setup' );

/**
 * Enqueue the layout CSS on the front end.
 *
 * The file mtime is the version, matching the parent's reasoning: a static
 * constant serves a stale file to the front while the editor reloads it, which
 * reads as "the editor updated but the page did not".
 */
function omphalos_enqueue_layout_styles() : void {
	foreach ( omphalos_layout_styles() as $handle => $path ) {
		$absolute = get_stylesheet_directory() . '/' . $path;

		if ( ! file_exists( $absolute ) ) {
			continue;
		}

		$mtime = @filemtime( $absolute );

		wp_enqueue_style(
			$handle,
			get_stylesheet_directory_uri() . '/' . $path,
			array(),
			$mtime ? (string) $mtime : null
		);
	}
}
add_action( 'wp_enqueue_scripts', 'omphalos_enqueue_layout_styles', 20 );
