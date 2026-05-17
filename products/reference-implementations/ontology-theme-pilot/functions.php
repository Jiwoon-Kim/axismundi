<?php
/**
 * Axismundi Pilot — functions.php
 *
 * Operationalizes v2.1a-P0 Tier 2 component bindings (Direct.CoreBlockStyle).
 * Each block style registration here corresponds to a row in
 *   _meta/v2_1/block_component_binding_rules.json
 * with binding_type = "Direct.CoreBlockStyle".
 *
 * @package Axismundi_Pilot
 * @version 0.1.0-pilot
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'AXISMUNDI_PILOT_VERSION' ) ) {
	define( 'AXISMUNDI_PILOT_VERSION', '0.1.0-pilot' );
}

/**
 * Theme setup.
 */
function axismundi_pilot_setup() : void {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption', 'style', 'script' ) );

	// Korean as first-class — 적절한 textdomain 로딩.
	load_theme_textdomain( 'axismundi-pilot', get_template_directory() . '/languages' );

	// editor-styles enqueue tokens + base + block-styles into the editor.
	add_editor_style( array(
		'assets/css/tokens.css',
		'assets/css/base.css',
		'assets/css/block-styles.css',
	) );
}
add_action( 'after_setup_theme', 'axismundi_pilot_setup' );

/**
 * Front-end asset enqueue.
 * Order matters: tokens.css must load before base.css and block-styles.css.
 */
function axismundi_pilot_enqueue_assets() : void {
	wp_enqueue_style(
		'axismundi-tokens',
		get_template_directory_uri() . '/assets/css/tokens.css',
		array(),
		AXISMUNDI_PILOT_VERSION
	);
	wp_enqueue_style(
		'axismundi-base',
		get_template_directory_uri() . '/assets/css/base.css',
		array( 'axismundi-tokens' ),
		AXISMUNDI_PILOT_VERSION
	);
	wp_enqueue_style(
		'axismundi-block-styles',
		get_template_directory_uri() . '/assets/css/block-styles.css',
		array( 'axismundi-tokens', 'axismundi-base' ),
		AXISMUNDI_PILOT_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'axismundi_pilot_enqueue_assets' );

/**
 * Register block styles per v2.1a-P0 binding rules.
 *
 * Each call here is a direct operationalization of a binding rule with
 * binding_type = "Direct.CoreBlockStyle". The (block_name, style_name) tuple
 * comes from `block_component_binding_rules.json -> rules[*].style_class_binding`.
 */
function axismundi_pilot_register_block_styles() : void {

	/* === Button (M3 component → core/button) — binding rule §1.1 === */
	// binding: Direct.CoreBlockStyle | confidence 0.9 | bucket A
	register_block_style( 'core/button', array(
		'name'  => 'filled',
		'label' => __( 'Filled', 'axismundi-pilot' ),
	) );
	register_block_style( 'core/button', array(
		'name'  => 'tonal',
		'label' => __( 'Tonal', 'axismundi-pilot' ),
	) );
	register_block_style( 'core/button', array(
		'name'  => 'elevated',
		'label' => __( 'Elevated', 'axismundi-pilot' ),
	) );
	register_block_style( 'core/button', array(
		'name'  => 'outlined',
		'label' => __( 'Outlined', 'axismundi-pilot' ),
	) );
	register_block_style( 'core/button', array(
		'name'  => 'text',
		'label' => __( 'Text', 'axismundi-pilot' ),
	) );

	/* === Card (M3 component → core/group) — binding rule §1.2 === */
	register_block_style( 'core/group', array(
		'name'  => 'card-filled',
		'label' => __( 'Card — Filled', 'axismundi-pilot' ),
	) );
	register_block_style( 'core/group', array(
		'name'  => 'card-elevated',
		'label' => __( 'Card — Elevated', 'axismundi-pilot' ),
	) );
	register_block_style( 'core/group', array(
		'name'  => 'card-outlined',
		'label' => __( 'Card — Outlined', 'axismundi-pilot' ),
	) );

	/* === List (M3 component → core/list) — binding rule §1.2 === */
	register_block_style( 'core/list', array(
		'name'  => 'list-segmented',
		'label' => __( 'Segmented', 'axismundi-pilot' ),
	) );

	/* === Divider (M3 component → core/separator) — binding rule §1.2 === */
	register_block_style( 'core/separator', array(
		'name'  => 'divider-inset',
		'label' => __( 'Inset', 'axismundi-pilot' ),
	) );
	register_block_style( 'core/separator', array(
		'name'  => 'divider-middle-inset',
		'label' => __( 'Middle inset', 'axismundi-pilot' ),
	) );

	/* === Search (M3 component → core/search) — binding rule §1.3 === */
	register_block_style( 'core/search', array(
		'name'  => 'filled-search',
		'label' => __( 'Filled', 'axismundi-pilot' ),
	) );
}
add_action( 'init', 'axismundi_pilot_register_block_styles' );

/**
 * Optional debug helper.
 * Reachable by appending `?axismundi_debug=1` in wp-admin (admin only).
 */
require_once get_template_directory() . '/inc/debug.php';
