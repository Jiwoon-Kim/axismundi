<?php
/**
 * Public application route.
 *
 * @package Axismundi
 */

defined( 'ABSPATH' ) || exit;

/** @return void */
function axismundi_capstone_add_rewrite_rule() : void {
	add_rewrite_rule(
		'^' . AXISMUNDI_CAPSTONE_ROUTE . '(?:/stylebook(?:/styles(?:/motion)?|/components/(?:buttons|icon-buttons|button-groups|split-buttons|cards)|/layout/(?:feed|list-detail|supporting_pane))?|/objects/[^/]+)?/?$',
		'index.php?' . AXISMUNDI_CAPSTONE_QUERY_VAR . '=1',
		'top'
	);
}
add_action( 'init', 'axismundi_capstone_add_rewrite_rule' );

/** @return void */
function axismundi_capstone_upgrade_rewrite_rules() : void {
	$option = 'axismundi_capstone_rewrite_version';
	if ( AXISMUNDI_CAPSTONE_REWRITE_VERSION === get_option( $option ) ) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( $option, AXISMUNDI_CAPSTONE_REWRITE_VERSION, false );
}
add_action( 'init', 'axismundi_capstone_upgrade_rewrite_rules', 20 );

/**
 * @param string[] $vars Public query variables.
 * @return string[]
 */
function axismundi_capstone_query_vars( array $vars ) : array {
	$vars[] = AXISMUNDI_CAPSTONE_QUERY_VAR;
	return $vars;
}
add_filter( 'query_vars', 'axismundi_capstone_query_vars' );

/** @return bool */
function axismundi_capstone_is_public_route() : bool {
	global $wp;

	// A public query var is how rewrite rules enter WordPress, but it must not make
	// `/?axismundi_capstone=1` a second, canonical-looking application URL.
	if ( '1' !== (string) get_query_var( AXISMUNDI_CAPSTONE_QUERY_VAR ) || ! isset( $wp->request ) ) {
		return false;
	}

	$request = trim( (string) $wp->request, '/' );
	return 1 === preg_match(
		'#^' . preg_quote( AXISMUNDI_CAPSTONE_ROUTE, '#' ) . '(?:/stylebook(?:/styles(?:/motion)?|/components/(?:buttons|icon-buttons|button-groups|split-buttons|cards)|/layout/(?:feed|list-detail|supporting_pane))?|/objects/[^/]+)?$#',
		$request
	);
}

/**
 * Request only the Axismundi theme foundation on the standalone app route.
 *
 * The theme retains responsibility for resolving, versioning, and enqueueing
 * its assets; the plugin only selects the public application asset mode.
 *
 * @param bool $foundation_only Current theme asset mode.
 * @return bool
 */
function axismundi_capstone_use_theme_foundation_only( bool $foundation_only ) : bool {
	return $foundation_only || axismundi_capstone_is_public_route();
}
add_filter( 'axismundi_theme_foundation_only', 'axismundi_capstone_use_theme_foundation_only' );

/**
 * Keep the standalone application document free of WordPress admin chrome.
 *
 * Disabling the bar at its source suppresses its markup, stylesheet, and inline
 * document-offset rules together. Admin and other WordPress surfaces retain the
 * normal logged-in experience.
 *
 * @param bool $show Whether the admin bar should be shown.
 * @return bool
 */
function axismundi_capstone_hide_public_admin_bar( bool $show ) : bool {
	return axismundi_capstone_is_public_route() ? false : $show;
}
add_filter( 'show_admin_bar', 'axismundi_capstone_hide_public_admin_bar' );

/**
 * Remove Core and Gutenberg presentation policy from the standalone app route.
 *
 * Theme font faces, image auto-sizing, emoji, and plugin integrations remain
 * outside this narrowly-scoped Core presentation boundary. The Admin Bar is
 * removed independently because it is WordPress chrome, not application UI.
 *
 * @return void
 */
function axismundi_capstone_isolate_public_core_presentation() : void {
	if ( ! axismundi_capstone_is_public_route() ) {
		return;
	}

	// Gutenberg replaces the Core callback when its compatibility layer is active.
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles', 10 );
	remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
	remove_action( 'wp_enqueue_scripts', 'gutenberg_enqueue_global_styles', 10 );
	remove_action( 'wp_footer', 'gutenberg_enqueue_global_styles', 1 );

}
add_action( 'wp_enqueue_scripts', 'axismundi_capstone_isolate_public_core_presentation', 0 );

/**
 * Remove any Core presentation handles enqueued after the route setup.
 *
 * @return void
 */
function axismundi_capstone_dequeue_public_core_presentation() : void {
	if ( ! axismundi_capstone_is_public_route() ) {
		return;
	}

	// Cover Core versions that enqueue these presentation styles differently.
	foreach ( array( 'global-styles', 'wp-block-library', 'wp-block-library-theme', 'core-block-supports' ) as $handle ) {
		wp_dequeue_style( $handle );
	}

	// This is a core/navigation block presentation extension, not a Social app
	// integration.
	wp_dequeue_style( 'axismundi-navigation-icons' );
	wp_dequeue_script( 'axismundi-navigation-icons-view' );

	// Regional font providers currently belong to the block-theme/editor asset
	// path. Social will opt into its own fallback policy in a later checkpoint.
	foreach ( array( 'axismundi-korean-font-provider', 'axismundi-japanese-font-provider', 'axismundi-traditional-chinese-font-provider' ) as $handle ) {
		wp_dequeue_style( $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'axismundi_capstone_dequeue_public_core_presentation', 100 );

/**
 * Render explicitly allowed footer integrations for the standalone application.
 *
 * Do not call wp_footer() here: that would reopen every active plugin's footer
 * hook. Each integration must be admitted deliberately while its Frontend
 * ownership is evaluated.
 *
 * @return void
 */
function axismundi_capstone_render_public_footer_allowlist() : void {
	if ( function_exists( 'axismundi_theme_controls_mount' ) ) {
		axismundi_theme_controls_mount();
	}
}

/** @return void */
function axismundi_capstone_render_public_route() : void {
	if ( ! axismundi_capstone_is_public_route() ) {
		return;
	}

	status_header( 200 );
	nocache_headers();
	axismundi_capstone_enqueue_app( 'frontend' );
	?>
	<!doctype html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex,follow">
		<?php wp_head(); ?>
	</head>
	<body class="axismundi-app-document axismundi-social">
		<div id="axismundi-root" data-axismundi-application="frontend"></div>
		<?php axismundi_capstone_render_public_footer_allowlist(); ?>
		<?php wp_print_footer_scripts(); ?>
	</body>
	</html>
	<?php
	exit;
}
add_action( 'template_redirect', 'axismundi_capstone_render_public_route', 0 );
