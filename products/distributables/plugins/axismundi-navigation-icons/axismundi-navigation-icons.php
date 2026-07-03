<?php
/**
 * Plugin Name:       Axismundi Navigation Icons
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-navigation-icons
 * Description:       Authoring plugin: add a Material Symbols leading icon to navigation items (link, submenu, home) for Axismundi.
 * Version:           0.1.2
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-navigation-icons
 *
 * @package AxismundiNavigationIcons
 *
 * Boundary: this plugin owns navigation-item icon authoring (the icon data + the
 * li-level restructure that inserts it), the `item-vertical` core/navigation
 * style variation, the front-end icon-click delegation (assets/view.js), and the
 * submenu disclosure arrow (the restructure moves the trigger button into the item
 * body, out of the theme's direct-child arrow selector). The Axismundi theme keeps
 * the M3 Navigation Bar / Rail / Menu spec and the item baseline — pill, state
 * layer and active indicator, made style-aware so it yields to the icon slot under
 * item-vertical — plus the Material Symbols @font-face (theme.json) and the
 * `.material-symbols-outlined` box contract (assets/styles/icons.css). The inserted
 * glyph therefore renders only when an Axismundi-family theme is active; otherwise
 * the ligature name degrades gracefully to plain text.
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_NAVIGATION_ICONS_VERSION = '0.1.2';

/**
 * Navigation blocks that accept a free-text Material Symbols icon name.
 *
 * core/navigation-link absorbs the custom/page/post/category/tag link variants
 * (kind/type/id), so it is the single block behind all of those.
 *
 * @return array<int,string>
 */
function axismundi_navigation_icons_text_blocks() : array {
	return array( 'core/navigation-link', 'core/navigation-submenu' );
}

/**
 * Default icon for navigation items whose target semantics are known.
 *
 * An explicitly authored axismundiNavIcon always wins. These defaults keep the
 * page-oriented core variants useful without forcing authors to type the same
 * ligature repeatedly.
 *
 * @param string              $name  Block name.
 * @param array<string,mixed> $attrs Block attributes.
 * @return string Sanitised default ligature token, or empty string.
 */
function axismundi_navigation_icons_default_icon( string $name, array $attrs ) : string {
	// A submenu groups links; default it to the page-list glyph regardless of kind.
	if ( 'core/navigation-submenu' === $name ) {
		return 'pages';
	}

	if ( 'core/navigation-link' !== $name ) {
		return '';
	}

	$kind = $attrs['kind'] ?? '';
	$type = $attrs['type'] ?? '';

	if ( 'post-type' === $kind ) {
		if ( 'page' === $type ) {
			return 'pages';
		}
		if ( 'post' === $type ) {
			return 'article';
		}
	}

	if ( 'taxonomy' === $kind ) {
		if ( 'category' === $type ) {
			return 'category';
		}
		if ( 'post_tag' === $type ) {
			return 'label';
		}
	}

	// Custom URL link. `link_2` (underscore) is the Material Symbols ligature; the
	// hyphenated form some authors type does not resolve.
	if ( 'custom' === $kind ) {
		return 'link_2';
	}

	return '';
}

/**
 * Cache-busting version for a plugin asset (file mtime, like the theme).
 *
 * @param string $relative_path Plugin-relative path.
 * @return string
 */
function axismundi_navigation_icons_asset_version( string $relative_path ) : string {
	$mtime = @filemtime( plugin_dir_path( __FILE__ ) . ltrim( $relative_path, '/' ) );
	return $mtime ? (string) $mtime : AXISMUNDI_NAVIGATION_ICONS_VERSION;
}

/**
 * Teach the server about the icon attributes on the target core blocks.
 *
 * The matching client-side declaration lives in assets/editor.js
 * (blocks.registerBlockType). Both are required: the JS filter lets the editor
 * store/serialise the attribute in the block delimiter; this PHP filter makes the
 * value reach render_block() as $block['attrs'].
 *
 * @param array<string,mixed> $args Block type registration args.
 * @return array<string,mixed>
 */
function axismundi_navigation_icons_register_attributes( array $args ) : array {
	$name = $args['name'] ?? '';
	if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
		$args['attributes'] = array();
	}

	if ( in_array( $name, axismundi_navigation_icons_text_blocks(), true ) ) {
		// No 'default' on purpose: an unset attribute means "use the semantic
		// default icon", while an explicit empty string means "no icon" (opt-out).
		$args['attributes']['axismundiNavIcon'] = array(
			'type' => 'string',
		);
	} elseif ( 'core/home-link' === $name ) {
		$args['attributes']['axismundiHomeIcon'] = array(
			'type'    => 'boolean',
			'default' => true,
		);
	} elseif ( 'core/page-list' === $name ) {
		// page-list is dynamic with no per-item authoring, so icons are an
		// all-or-nothing control on the block. It defaults on so a ref-less
		// Navigation block (home-link + page-list) has icons after a header reset;
		// authors can still turn it off explicitly.
		$args['attributes']['axismundiPageListIcons'] = array(
			'type'    => 'boolean',
			'default' => true,
		);
	}

	return $args;
}
add_filter( 'register_block_type_args', 'axismundi_navigation_icons_register_attributes' );

/**
 * Reduce an authored icon name to a safe Material Symbols ligature token.
 *
 * Ligature names are lowercase, underscore-separated (e.g. arrow_drop_down). To
 * be forgiving, spaces and hyphens are normalised to underscores ("arrow drop
 * down" -> "arrow_drop_down"); everything else is stripped so the value can be
 * spliced into markup without escaping concerns. Kept in sync with the JS
 * sanitize() in assets/editor.js.
 *
 * @param mixed $raw Authored value.
 * @return string Sanitised token, possibly empty.
 */
function axismundi_navigation_icons_sanitize( $raw ) : string {
	$value = strtolower( trim( (string) $raw ) );
	$value = preg_replace( '/[\s-]+/', '_', $value );   // spaces / hyphens -> underscore.
	$value = preg_replace( '/[^a-z0-9_]/', '', $value ); // strip anything else.
	$value = preg_replace( '/_+/', '_', $value );        // collapse repeats.
	return trim( $value, '_' );
}

/**
 * Build the icon box: an `.ax-nav-item-icon` slot wrapping the glyph span.
 *
 * The slot and the glyph are distinct elements, mirroring the lab structure
 * (`.nav-bar__icon` slot > glyph). The slot is what the item-vertical CSS grows
 * into the 56x32 M3 active-indicator pill; the inner `.material-symbols-outlined`
 * keeps the theme's 1em box / ligature / fallback from icons.css.
 *
 * @param string $name Sanitised ligature token.
 * @return string
 */
function axismundi_navigation_icons_icon_box( string $name ) : string {
	return sprintf(
		'<span class="ax-nav-item-icon"><span class="material-symbols-outlined axismundi-navigation-icon" aria-hidden="true" translate="no">%s</span></span>',
		$name
	);
}

/**
 * Restructure a navigation item into an icon box + body box at the <li> level.
 *
 *   <li class="…">
 *     <span class="ax-nav-item-trigger">
 *       <span class="ax-nav-item-icon"><span class="…axismundi-navigation-icon">…</span></span>
 *       <span class="ax-nav-item-body">
 *         <a class="…__content">label (+ description)</a>
 *         <button class="…__submenu-icon">…</button>   (submenu only)
 *       </span>
 *     </span>
 *     <ul class="…__submenu-container">…</ul>         (submenu only)
 *   </li>
 *
 * This is the visual-first structure: the icon and body are grouped in a trigger
 * wrapper, while the child submenu <ul> stays outside it. That lets open-always
 * items paint hover/focus/current state on the trigger only, without swallowing
 * the inline child list. The icon is still outside the <a>, so a small front-end
 * script (assets/view.js) forwards an icon click to the item's link, and the
 * plugin owns the disclosure arrow (the move breaks the theme's direct-child
 * arrow selector).
 *
 * Only the first row is rewritten (the item's own row), never the nested submenu
 * popover rows inside the block content (limit 1 on each pass).
 *
 * @param string $html    Rendered block HTML.
 * @param string $name    Sanitised ligature token.
 * @param bool   $is_home Whether this is core/home-link (raw-text label to wrap).
 * @return string
 */
function axismundi_navigation_icons_restructure( string $html, string $name, bool $is_home ) : string {
	$icon_box = axismundi_navigation_icons_icon_box( $name );
	$parts = preg_split( '/(<ul\b[^>]*\bwp-block-navigation__submenu-container\b[^>]*>)/', $html, 2, PREG_SPLIT_DELIM_CAPTURE );
	$head = $parts[0] ?? $html;
	$tail = '';
	if ( is_array( $parts ) && count( $parts ) > 1 ) {
		$tail = implode( '', array_slice( $parts, 1 ) );
	}

	// home-link renders a raw-text label; wrap it so it styles like the
	// navigation-link label span.
	if ( $is_home ) {
		$head = preg_replace_callback(
			'/(<a\b[^>]*\bwp-block-navigation-item__content\b[^>]*>)(.*?)(<\/a>)/s',
			static function ( array $m ) {
				return $m[1] . '<span class="wp-block-navigation-item__label ax-nav-item-label">' . trim( $m[2] ) . '</span>' . $m[3];
			},
			$head,
			1
		) ?? $head;
	}

	$wrap = static function ( string $row ) use ( $icon_box ) : string {
		return '<span class="ax-nav-item-trigger">' . $icon_box . '<span class="ax-nav-item-body">' . $row . '</span></span>';
	};

	// Rewrite only the row before the item's own submenu <ul>. Nested submenu
	// rows live in $tail and are handled by their own render_block pass.
	$count = 0;
	$head = preg_replace_callback(
		'/(<a\b[^>]*\bwp-block-navigation-item__content\b[^>]*>.*?<\/a>)(\s*<button\b(?=[^>]*\bwp-block-navigation-submenu__toggle\b).*?<\/button>)?/s',
		static function ( array $m ) use ( $wrap ) {
			return $wrap( $m[1] . ( $m[2] ?? '' ) );
		},
		$head,
		1,
		$count
	);

	if ( null === $head ) {
		return $html;
	}

	if ( ! $count ) {
		$head = preg_replace_callback(
			'/(<button\b(?=[^>]*\bwp-block-navigation-item__content\b)(?=[^>]*\bwp-block-navigation-submenu__toggle\b).*?<\/button>)(\s*<span\b(?=[^>]*\bwp-block-navigation__submenu-icon\b).*?<\/span>)?/s',
			static function ( array $m ) use ( $wrap ) {
				return $wrap( $m[1] . ( $m[2] ?? '' ) );
			},
			$head,
			1,
			$count
		);
	}

	return ( $count && null !== $head ) ? $head . $tail : $html;
}

/**
 * Add the page-list default icon to every generated page item.
 *
 * core/page-list is a dynamic block that expands into plain page anchors, not
 * core/navigation-link child blocks, so it needs a small renderer pass of its
 * own. The markup mirrors axismundi_navigation_icons_restructure(): icon slot
 * first, then a body slot containing the page link.
 *
 * @param string $html Rendered core/page-list HTML.
 * @return string
 */
function axismundi_navigation_icons_restructure_page_list( string $html ) : string {
	// Act on any page list whose items carry `wp-block-pages-list__item`, whether
	// inside a navigation block or standalone (widget context). The icon box base
	// layout (.ax-nav-item-trigger/-icon) is nav-agnostic, and navigation.css adds
	// a standalone row + list reset for items outside a <nav>. View.js click
	// delegation is nav-only, but a page-list icon is decorative anyway (the link
	// label is the click target both in nav and standalone), so nothing regresses.
	if (
		! str_contains( $html, 'wp-block-pages-list__item' )
		|| str_contains( $html, 'ax-nav-item-icon' )
	) {
		return $html;
	}

	$icon_box = axismundi_navigation_icons_icon_box( 'pages' );

	$result = preg_replace_callback(
		'/(<li\b[^>]*\bwp-block-pages-list__item\b[^>]*>\s*)(<a\b[^>]*\bwp-block-pages-list__item__link\b[^>]*>)(.*?)(<\/a>)/s',
		static function ( array $m ) use ( $icon_box ) {
			return $m[1]
				. '<span class="ax-nav-item-trigger">'
				. $icon_box
				. '<span class="ax-nav-item-body">'
				. $m[2]
				. '<span class="wp-block-navigation-item__label ax-nav-item-label">'
				. trim( $m[3] )
				. '</span>'
				. $m[4]
				. '</span>'
				. '</span>';
		},
		$html
	);

	return null === $result ? $html : $result;
}

/**
 * Front-end render: restructure the authored navigation items into icon + body.
 *
 * @param string              $block_content Rendered block HTML.
 * @param array<string,mixed> $block         Parsed block.
 * @return string
 */
function axismundi_navigation_icons_render_block( string $block_content, array $block ) : string {
	$name = $block['blockName'] ?? '';
	$attrs = $block['attrs'] ?? array();

	if ( in_array( $name, axismundi_navigation_icons_text_blocks(), true ) ) {
		$raw = $attrs['axismundiNavIcon'] ?? null;
		if ( null === $raw ) {
			// Unset → semantic default (page / category / tag links).
			$icon = axismundi_navigation_icons_default_icon( $name, $attrs );
		} else {
			// Explicit value; an empty string is an explicit "no icon" opt-out.
			$icon = axismundi_navigation_icons_sanitize( $raw );
		}
		if ( '' === $icon ) {
			return $block_content;
		}
		return axismundi_navigation_icons_restructure( $block_content, $icon, false );
	}

	if ( 'core/page-list' === $name ) {
		if ( array_key_exists( 'axismundiPageListIcons', $attrs ) && false === $attrs['axismundiPageListIcons'] ) {
			return $block_content;
		}
		return axismundi_navigation_icons_restructure_page_list( $block_content );
	}

	if ( 'core/home-link' === $name ) {
		if ( array_key_exists( 'axismundiHomeIcon', $attrs ) && false === $attrs['axismundiHomeIcon'] ) {
			return $block_content;
		}
		return axismundi_navigation_icons_restructure( $block_content, 'home', true );
	}

	return $block_content;
}
add_filter( 'render_block', 'axismundi_navigation_icons_render_block', 10, 2 );

/**
 * Register the parent-level item-layout style variation on core/navigation.
 *
 * orientation (core) decides the navigation family — horizontal = M3 Navigation
 * Bar, vertical = M3 Navigation Rail — and the Axismundi theme owns that baseline
 * item skin (pill, state layer, active indicator). With no is-style class, icons
 * use the default row layout (icon beside label). The single style variation adds
 * only the icon/label axis the core block has no concept of:
 *   - item-vertical: icon above the label (column)
 * Drawer is intentionally absent (M3 Expressive folds it into the expanded
 * navigation rail, which is orientation: vertical here).
 *
 * @return void
 */
function axismundi_navigation_icons_register_styles() : void {
	register_block_style(
		'core/navigation',
		array(
			'name'  => 'item-vertical',
			'label' => __( 'Vertical item', 'axismundi-navigation-icons' ),
		)
	);
}
add_action( 'init', 'axismundi_navigation_icons_register_styles' );

/**
 * Editor-frame script: the attribute declaration, the InspectorControls panel and
 * the canvas-preview custom property. These are block-editor JS filters, so they
 * run in the editor (parent) frame.
 *
 * @return void
 */
function axismundi_navigation_icons_enqueue_editor_script() : void {
	$js_path = plugin_dir_path( __FILE__ ) . 'assets/editor.js';
	if ( ! file_exists( $js_path ) ) {
		return;
	}

	$asset = file_exists( plugin_dir_path( __FILE__ ) . 'assets/editor.asset.php' )
		? require plugin_dir_path( __FILE__ ) . 'assets/editor.asset.php'
		: array( 'dependencies' => array(), 'version' => AXISMUNDI_NAVIGATION_ICONS_VERSION );

	wp_enqueue_script(
		'axismundi-navigation-icons-editor',
		plugins_url( 'assets/editor.js', __FILE__ ),
		$asset['dependencies'] ?? array(),
		axismundi_navigation_icons_asset_version( 'assets/editor.js' ),
		true
	);
	wp_set_script_translations( 'axismundi-navigation-icons-editor', 'axismundi-navigation-icons' );
}
add_action( 'enqueue_block_editor_assets', 'axismundi_navigation_icons_enqueue_editor_script' );

/**
 * Rendered styles for both the front end and the iframed editor canvas.
 *
 * enqueue_block_assets reaches the editor canvas iframe (where the navigation DOM
 * actually lives) as well as the front end, so the icon alignment + item-layout
 * geometry apply in both. The ::before preview glyph is canvas-only — on the
 * front the real spliced span renders instead — so it is gated to the editor
 * (admin) context.
 *
 * @return void
 */
function axismundi_navigation_icons_enqueue_block_styles() : void {
	$nav_path = plugin_dir_path( __FILE__ ) . 'assets/navigation.css';
	if ( file_exists( $nav_path ) ) {
		wp_enqueue_style(
			'axismundi-navigation-icons',
			plugins_url( 'assets/navigation.css', __FILE__ ),
			array(),
			axismundi_navigation_icons_asset_version( 'assets/navigation.css' )
		);
	}

	$preview_path = plugin_dir_path( __FILE__ ) . 'assets/editor.css';
	if ( is_admin() && file_exists( $preview_path ) ) {
		wp_enqueue_style(
			'axismundi-navigation-icons-preview',
			plugins_url( 'assets/editor.css', __FILE__ ),
			array( 'axismundi-navigation-icons' ),
			axismundi_navigation_icons_asset_version( 'assets/editor.css' )
		);
	}
}
add_action( 'enqueue_block_assets', 'axismundi_navigation_icons_enqueue_block_styles' );

/**
 * Front-end script: forward an icon click to the item's link.
 *
 * The icon box is a sibling of the link in the rendered li-level structure, so a
 * pointer click on the icon would do nothing without this. Front only — the editor
 * canvas shows a CSS-only preview glyph with no real icon box to click.
 *
 * @return void
 */
function axismundi_navigation_icons_enqueue_view_script() : void {
	$path = plugin_dir_path( __FILE__ ) . 'assets/view.js';
	if ( ! file_exists( $path ) ) {
		return;
	}
	wp_enqueue_script(
		'axismundi-navigation-icons-view',
		plugins_url( 'assets/view.js', __FILE__ ),
		array(),
		axismundi_navigation_icons_asset_version( 'assets/view.js' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'axismundi_navigation_icons_enqueue_view_script' );
