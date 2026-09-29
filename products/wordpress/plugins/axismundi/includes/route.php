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
		'^' . AXISMUNDI_CAPSTONE_ROUTE . '/?$',
		'index.php?' . AXISMUNDI_CAPSTONE_QUERY_VAR . '=1',
		'top'
	);
}
add_action( 'init', 'axismundi_capstone_add_rewrite_rule' );

/** @return void */
function axismundi_capstone_upgrade_rewrite_rules() : void {
	$option = 'axismundi_capstone_rewrite_version';
	if ( AXISMUNDI_CAPSTONE_VERSION === get_option( $option ) ) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( $option, AXISMUNDI_CAPSTONE_VERSION, false );
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
	return '1' === (string) get_query_var( AXISMUNDI_CAPSTONE_QUERY_VAR )
		&& isset( $wp->request )
		&& AXISMUNDI_CAPSTONE_ROUTE === trim( (string) $wp->request, '/' );
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
	<body <?php body_class( 'axismundi-app-document' ); ?>>
		<main id="axismundi-root" data-axismundi-application="frontend"></main>
		<?php wp_print_footer_scripts(); ?>
	</body>
	</html>
	<?php
	exit;
}
add_action( 'template_redirect', 'axismundi_capstone_render_public_route', 0 );
