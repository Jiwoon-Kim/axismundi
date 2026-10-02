<?php
/**
 * Axismundi application assets.
 *
 * @package Axismundi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return build metadata for an application entry point.
 *
 * @param 'frontend'|'admin' $application Application entry point.
 * @return array<string,mixed>
 */
function axismundi_capstone_asset_metadata( string $application ) : array {
	$path = dirname( __DIR__ ) . '/build/' . $application . '.asset.php';
	if ( ! is_readable( $path ) ) {
		return array(
			'dependencies' => array( 'wp-element', 'wp-i18n' ),
			'version'      => AXISMUNDI_CAPSTONE_VERSION,
		);
	}

	$metadata = require $path;
	return is_array( $metadata ) ? $metadata : array();
}

/**
 * Enqueue one isolated Axismundi application bundle.
 *
 * The two applications consume two different design systems, and neither receives the
 * other's bundle or styles.
 *
 * Admin consumes the WordPress design system directly: `--wpds-*`, from the `wp-theme`
 * stylesheet, declared below as a dependency. Social consumes Material 3 through the
 * Axismundi theme's foundation asset contract, which the theme itself enqueues -- this
 * plugin does not name theme handles here, because asset location, version and order
 * belong to the theme (DECISION-FRONTEND-THEME-ASSET-CONTRACT.md). What `theme.json`
 * cannot express, the Social bundle carries itself.
 *
 * Neither application ships token fallbacks. Axismundi is an integration plugin for the
 * Axismundi ecosystem, not a general-purpose plugin, so the WordPress design system and
 * the Axismundi theme are premises rather than possibilities.
 *
 * @param 'frontend'|'admin' $application Application entry point.
 * @return void
 */
function axismundi_capstone_enqueue_app( string $application ) : void {
	if ( ! in_array( $application, array( 'frontend', 'admin' ), true ) ) {
		return;
	}

	$script = dirname( __DIR__ ) . '/build/' . $application . '.js';
	$style  = dirname( __DIR__ ) . '/build/' . $application . '.css';
	if ( ! is_readable( $script ) || ! is_readable( $style ) ) {
		return;
	}

	$asset  = axismundi_capstone_asset_metadata( $application );
	$deps   = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array();
	$ver    = isset( $asset['version'] ) && is_scalar( $asset['version'] ) ? (string) $asset['version'] : AXISMUNDI_CAPSTONE_VERSION;
	$handle = 'axismundi-' . $application;

	/*
	 * `wp-theme` defines every `--wpds-*` property the Admin stylesheet reads. Core does
	 * reach it on most admin screens as a transitive dependency of `wp-commands`, so
	 * leaving this empty appears to work -- which is the problem. The token contract would
	 * then hold by coincidence, and lose silently if that chain ever changed.
	 */
	wp_enqueue_style(
		$handle,
		plugins_url( 'build/' . $application . '.css', dirname( __DIR__ ) . '/axismundi.php' ),
		'admin' === $application ? array( 'wp-theme' ) : array(),
		$ver
	);
	wp_enqueue_script(
		$handle,
		plugins_url( 'build/' . $application . '.js', dirname( __DIR__ ) . '/axismundi.php' ),
		$deps,
		$ver,
		true
	);
	$config = array(
		'application' => $application,
		'adminUrl'    => admin_url(),
		'route'       => '/' . AXISMUNDI_CAPSTONE_ROUTE . '/',
		'path'        => 'admin' === $application ? axismundi_capstone_admin_path() : '/',
	);

	if ( 'frontend' === $application ) {
		$config['viewer'] = array(
			'authenticated' => is_user_logged_in(),
		);
		$config['loginUrl'] = wp_login_url( home_url( '/' . AXISMUNDI_CAPSTONE_ROUTE . '/' ) );
	}

	wp_add_inline_script(
		$handle,
		'window.axismundiCapstone = ' . wp_json_encode( $config ) . ';',
		'before'
	);
}

/**
 * Return the internal admin app path without accepting arbitrary query data.
 *
 * @return string
 */
function axismundi_capstone_admin_path() : string {
	$path = isset( $_GET['p'] ) && is_scalar( $_GET['p'] ) ? (string) wp_unslash( $_GET['p'] ) : '/';
	$path = '/' . trim( sanitize_text_field( $path ), '/' );
	return in_array(
		$path,
		array(
			'/',
			'/settings',
			'/diagnostics',
			'/design',
			'/design/styles',
			'/design/templates',
			'/design/template-parts',
			'/design/patterns',
			'/design/components',
			'/design/assets',
			'/design/assets/fonts',
			'/design/assets/icons',
			'/design/assets/emojis',
		),
		true
	) ? $path : '/';
}
