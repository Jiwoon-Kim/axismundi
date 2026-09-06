<?php
/**
 * Plugin Name:       Axismundi Theme Controls
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-theme-controls
 * Description:       Switch the site between Material Design 3 colour schemes, and remember what the reader picked.
 * Version:           0.1.0
 * Requires at least: 7.1
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-theme-controls
 *
 * @package AxismundiThemeControls
 */

/*
 * WHAT THIS IS, AND WHAT IT DELIBERATELY IS NOT
 *
 * A proof that a colour scheme can be swapped at runtime over a theme whose
 * scheme lives entirely in CSS custom properties. Everything below is the
 * smallest thing that actually works, and the notes are here because the
 * reasoning cost more than the code did.
 *
 * The mechanism. Axismundi keeps every colour value in CSS: its theme.json
 * palette is 32 entries of var(--md-sys-color-*) and its settings.custom is
 * empty, so changing the scheme means redefining --md-ref-palette-* on :root
 * and winning against the theme's own :root rules. assets/schemes.css does it
 * by specificity -- :root[data-ax-scheme="blue"] is (0,2,0) against a bare
 * :root at (0,1,0) -- so this plugin's stylesheet beats the theme's wherever
 * it lands in the cascade. Setting one attribute is the entire runtime.
 *
 * NOT style variations. They cannot carry this. Selecting a variation stores it
 * as user global styles, and styles.css does not survive that round trip:
 * written to the global styles post and read back, it is gone, and
 * settings.custom goes the same way, as administrator with unfiltered_html and
 * edit_css just as without. The ordering would have blocked it regardless --
 * global-styles-inline-css prints far ahead of the theme's own stylesheet link.
 *
 * NOT adoptedStyleSheets, though it works. Constructable stylesheets do sit
 * after the document's own sheets in the cascade, which is the position an
 * override needs when it cannot win on specificity. Measured precedence is
 * inline style on <html> > adoptedStyleSheets > <link>. It is the right tool
 * for a scheme computed in the browser from an arbitrary colour. It is the
 * wrong tool here, because it can only run after first paint, and an attribute
 * can be set before it.
 *
 * NOT material-color-utilities on the page. Computing a scheme in the browser
 * needs Google's HCT implementation, which is 7 files, 67,533 bytes raw and
 * 16,925 gzipped -- affordable, but only needed if a visitor picks a colour
 * this plugin has not seen. Pre-generated schemes need none of it. The
 * generator lives in the Omphalos theme, which also holds M3's published
 * palette tables; assets/schemes.css is its output and is not edited here.
 * Two copies of that formula would drift.
 *
 * NOT a block. The Theme Switcher plugin is the block-shaped one and took
 * several releases to get there. This is a proof; one control in the footer
 * shows the mechanism works, and a block can come later if it earns it.
 *
 * The error palette is untouched by every scheme. M3 holds error at a fixed
 * hue across schemes.
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_THEME_CONTROLS_COOKIE = 'axismundi_scheme';

/**
 * The schemes this plugin ships, in the order the control offers them.
 *
 * Slugs match the attribute values in assets/schemes.css. `baseline` is not in
 * that file and does not need to be: it is the absence of the attribute, which
 * leaves the active theme's own scheme showing.
 *
 * @return array<string, string> Slug => label.
 */
function axismundi_theme_controls_schemes() : array {
	return array(
		'baseline' => __( 'Baseline', 'axismundi-theme-controls' ),
		'blue'     => __( 'Blue', 'axismundi-theme-controls' ),
		'cyan'     => __( 'Cyan', 'axismundi-theme-controls' ),
		'green'    => __( 'Green', 'axismundi-theme-controls' ),
		'orange'   => __( 'Orange', 'axismundi-theme-controls' ),
	);
}

/**
 * Set the scheme attribute before the page paints.
 *
 * Same shape as the Theme Switcher's scheme bridge, and for the same reason:
 * rendering the attribute in PHP would make every page uncacheable per reader,
 * so the cookie is read client-side at wp_head priority 0, before any stylesheet
 * has been applied to a rendered box. The regex is the whitelist -- an
 * unexpected cookie value sets nothing and the theme's own scheme shows.
 *
 * @return void
 */
function axismundi_theme_controls_head_script() : void {
	$slugs = implode( '|', array_diff( array_keys( axismundi_theme_controls_schemes() ), array( 'baseline' ) ) );

	printf(
		'<script>(function(){try{var m=document.cookie.match(/(?:^|;\s*)%1$s=(%2$s)/);if(m){document.documentElement.setAttribute("data-ax-scheme",m[1]);}}catch(e){}})();</script>' . "\n",
		esc_js( AXISMUNDI_THEME_CONTROLS_COOKIE ),
		esc_js( $slugs )
	);
}
add_action( 'wp_head', 'axismundi_theme_controls_head_script', 0 );

/**
 * Enqueue the schemes and the control.
 *
 * The schemes stylesheet has no dependency on the theme's. It does not need one:
 * it wins by specificity, so it works wherever it lands.
 *
 * @return void
 */
function axismundi_theme_controls_enqueue() : void {
	foreach ( array( 'schemes', 'theme-controls' ) as $name ) {
		$path = __DIR__ . '/assets/' . $name . '.css';

		if ( ! file_exists( $path ) ) {
			continue;
		}

		wp_enqueue_style(
			'axismundi-theme-controls-' . $name,
			plugins_url( 'assets/' . $name . '.css', __FILE__ ),
			array(),
			(string) filemtime( $path )
		);
	}

	$script = __DIR__ . '/assets/theme-controls.js';

	if ( ! file_exists( $script ) ) {
		return;
	}

	wp_enqueue_script(
		'axismundi-theme-controls',
		plugins_url( 'assets/theme-controls.js', __FILE__ ),
		array(),
		(string) filemtime( $script ),
		true
	);

	wp_localize_script(
		'axismundi-theme-controls',
		'axismundiThemeControls',
		array(
			'cookie'  => AXISMUNDI_THEME_CONTROLS_COOKIE,
			'schemes' => axismundi_theme_controls_schemes(),
			'label'   => __( 'Colour scheme', 'axismundi-theme-controls' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'axismundi_theme_controls_enqueue' );

/**
 * Render the control's mount point.
 *
 * A mount point rather than the control itself, because the control is only
 * useful with JavaScript: without it there is nothing to switch, and a set of
 * buttons that do nothing is worse than no buttons. The script fills this in.
 *
 * @return void
 */
function axismundi_theme_controls_mount() : void {
	echo '<div class="axismundi-theme-controls" hidden></div>' . "\n";
}
add_action( 'wp_footer', 'axismundi_theme_controls_mount' );
