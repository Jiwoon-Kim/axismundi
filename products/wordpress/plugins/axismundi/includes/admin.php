<?php
/**
 * Axismundi administration surface.
 *
 * @package Axismundi
 */

defined( 'ABSPATH' ) || exit;

/** @return void */
function axismundi_capstone_register_admin_page() : void {
	add_menu_page(
		__( 'Axismundi', 'axismundi' ),
		__( 'Axismundi', 'axismundi' ),
		AXISMUNDI_CAPSTONE_ADMIN_CAPABILITY,
		AXISMUNDI_CAPSTONE_ADMIN_PAGE,
		'axismundi_capstone_render_admin_page',
		'dashicons-rss',
		58
	);
}
add_action( 'admin_menu', 'axismundi_capstone_register_admin_page' );

/**
 * Scope the Axismundi console chrome changes to its own admin screen.
 *
 * The WordPress toolbar and notices deliberately remain available.
 *
 * @param string $classes Existing body classes.
 * @return string
 */
function axismundi_capstone_admin_body_class( string $classes ) : string {
	$screen = get_current_screen();
	if ( ! $screen || 'toplevel_page_' . AXISMUNDI_CAPSTONE_ADMIN_PAGE !== $screen->id ) {
		return $classes;
	}

	return $classes . ' axismundi-admin';
}
add_filter( 'admin_body_class', 'axismundi_capstone_admin_body_class' );

/** @return void */
function axismundi_capstone_render_admin_page() : void {
	if ( ! current_user_can( AXISMUNDI_CAPSTONE_ADMIN_CAPABILITY ) ) {
		wp_die( esc_html__( 'You cannot manage Axismundi.', 'axismundi' ), '', array( 'response' => 403 ) );
	}
	?>
	<div id="axismundi-root" data-axismundi-application="admin"></div>
	<?php
}

/**
 * @param string $hook Current admin screen hook.
 * @return void
 */
function axismundi_capstone_enqueue_admin_app( string $hook ) : void {
	if ( 'toplevel_page_' . AXISMUNDI_CAPSTONE_ADMIN_PAGE !== $hook ) {
		return;
	}
	axismundi_capstone_enqueue_app( 'admin' );
}
add_action( 'admin_enqueue_scripts', 'axismundi_capstone_enqueue_admin_app' );
