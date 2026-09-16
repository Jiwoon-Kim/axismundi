<?php
/**
 * Custom emoji picker assets (dev-only; dist-excluded).
 *
 * The load-bearing claim is that the picker's spacing rule and the server's tokenizer
 * agree. They are written in different languages and cannot share code, so the only
 * thing keeping them together is a check that reads the boundary set out of the
 * JavaScript and exercises the PHP with it. A picker that disagrees inserts a shortcode
 * that renders nowhere and reports nothing.
 *
 * No network, no browser: this asserts the contract, not the interaction.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-emoji.php';

$ax_pick_results = array();

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_pick_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

try {
	$ax_pick_js  = (string) file_get_contents( dirname( __DIR__ ) . '/assets/editor/picker.js' );
	$ax_pick_css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/editor/picker.css' );

	// -- The two rules must be the same rule ---------------------------------------------

	ax_pick_assert( $ax_pick_results, 'the picker declares a boundary set', 1 === preg_match( '/var ADJACENT = \/\[([^\]]+)\]\//', $ax_pick_js, $ax_pick_set ) );
	$ax_pick_chars = isset( $ax_pick_set[1] ) ? $ax_pick_set[1] : '';
	ax_pick_assert( $ax_pick_results, 'and it is the tokenizer\'s set, underscore included', 'A-Za-z0-9_:' === $ax_pick_chars );

	/*
	 * Read from the JavaScript rather than restated here, so a future edit to the picker
	 * that drops a character is caught by this file instead of by a user wondering why
	 * their emoji is a word.
	 */
	$ax_pick_adjacent = array();
	foreach ( array( 'a', 'M', 'z', '0', '9', '_', ':' ) as $ax_pick_char ) {
		if ( 1 === preg_match( '/[' . $ax_pick_chars . ']/', $ax_pick_char ) ) {
			$ax_pick_adjacent[] = $ax_pick_char;
		}
	}
	$ax_pick_missed = array();
	foreach ( $ax_pick_adjacent as $ax_pick_char ) {
		// The picker would add a space here; confirm the tokenizer really needs one.
		if ( array() !== axismundi_emoji_tokenize( $ax_pick_char . ':spaced: x' ) ) {
			$ax_pick_missed[] = $ax_pick_char;
		}
	}
	ax_pick_assert( $ax_pick_results, 'every character the picker pads is one the tokenizer would otherwise reject', array() === $ax_pick_missed );

	$ax_pick_wasted = array();
	foreach ( array( '-', '(', '.', ' ', '가', '/' ) as $ax_pick_char ) {
		$pads     = 1 === preg_match( '/[' . $ax_pick_chars . ']/u', $ax_pick_char );
		$declared = array() !== axismundi_emoji_tokenize( $ax_pick_char . ':spaced:' . $ax_pick_char );
		if ( $pads || ! $declared ) {
			$ax_pick_wasted[] = $ax_pick_char;
		}
	}
	ax_pick_assert( $ax_pick_results, 'and it pads nothing the tokenizer already accepts, so Korean text gains no stray spaces', array() === $ax_pick_wasted );

	// -- What the picker inserts -----------------------------------------------------------

	ax_pick_assert( $ax_pick_results, 'selection inserts the shortcode as text, never image markup', ! str_contains( $ax_pick_js, '<img' ) || ! preg_match( '/insert\(\s*value,\s*[\'"]<img/', $ax_pick_js ) );
	ax_pick_assert( $ax_pick_results, 'the popover is built only while open, not once per RichText instance', str_contains( $ax_pick_js, 'isOpen' ) && str_contains( $ax_pick_js, '? el(' ) );
	ax_pick_assert( $ax_pick_results, 'the catalogue is searched over REST rather than localized into the page', str_contains( $ax_pick_js, '/axismundi/v1/emoji/local' ) && ! str_contains( $ax_pick_js, 'wp_localize_script' ) );

	/*
	 * Every surface this picker serves publishes its text, so a local-only emoji offered
	 * here would produce a message that reads correctly at home and as a bare word
	 * everywhere else.
	 */
	ax_pick_assert( $ax_pick_results, 'it asks only for emoji this site may publish', str_contains( $ax_pick_js, 'federated: true' ) );

	/*
	 * Changed deliberately (docs/AXISMUNDI-EMOJI-UNICODE.md §12.5). Until v0.2 this asserted
	 * that no Unicode data existed here at all, on the grounds that the OS picker covers it;
	 * it does not cover flags on Windows. What still holds: no third-party picker, no
	 * catalogue inlined into the script, and a grapheme is inserted as plain text.
	 */
	ax_pick_assert( $ax_pick_results, 'Unicode data is fetched from this plugin\'s catalogue, not bundled into the script or a third-party picker', ! str_contains( $ax_pick_js, 'emoji-mart' ) && str_contains( $ax_pick_js, 'window.axismundiEmojiUnicodeSource' ) && str_contains( $ax_pick_js, "'/axismundi/v1/emoji/unicode'" ) && strlen( $ax_pick_js ) < 60000 );
	ax_pick_assert( $ax_pick_results, 'a Unicode choice inserts the grapheme as text, unpadded and without markup', str_contains( $ax_pick_js, 'props.onChange( wp.richText.insert( props.value, text ) );' ) );
	ax_pick_assert( $ax_pick_results, 'the picker never rewraps editor text itself; it only classes its own tiles', ! str_contains( $ax_pick_js, 'protect(' ) && str_contains( $ax_pick_js, "attributes.className += ' ax-unicode-emoji';" ) );

	/*
	 * Laid out like the front-end reaction picker (Activities): one scrolling page, a jump
	 * strip that navigates rather than filters, and collapsible sections. Tabs were tried and
	 * rejected — they hide what an author is comparing against.
	 */
	ax_pick_assert( $ax_pick_results, 'one scrolling page, not tabs', ! str_contains( $ax_pick_js, 'TabPanel' ) && str_contains( $ax_pick_js, "'axismundi-emoji-picker__scroll'" ) );
	ax_pick_assert( $ax_pick_results, 'the category strip is a toolbar of jumps that marks the current section with aria-current', str_contains( $ax_pick_js, "role: 'toolbar'" ) && str_contains( $ax_pick_js, "'aria-current': isActive ? 'true' : undefined" ) );
	ax_pick_assert( $ax_pick_results, 'sections collapse, and a closed section mounts no tiles', str_contains( $ax_pick_js, "'aria-expanded': props.expanded ? 'true' : 'false'" ) && str_contains( $ax_pick_js, '! props.collapsible || props.expanded ? props.children : null' ) );
	ax_pick_assert( $ax_pick_results, 'Recent and Custom come before the Unicode groups', (int) strpos( $ax_pick_js, "jumpButton( 'recent'" ) < (int) strpos( $ax_pick_js, "jumpButton( 'custom'" ) && (int) strpos( $ax_pick_js, "jumpButton( 'custom'" ) < (int) strpos( $ax_pick_js, "jumpButton( 'uni:'" ) );
	ax_pick_assert( $ax_pick_results, 'a Unicode group fetches its file only when it first opens', str_contains( $ax_pick_js, "loadGroup( source.groups[ id.slice( 4 ) ] )" ) && ! str_contains( $ax_pick_js, 'rgi-17.0.json' ) );
	ax_pick_assert( $ax_pick_results, 'icons are Dashicons, which wp-admin always has, not the theme\'s Material Symbols', str_contains( $ax_pick_js, 'C.Dashicon' ) && ! str_contains( $ax_pick_js, 'material-symbols' ) );
	ax_pick_assert( $ax_pick_results, 'and the geometry matches the reaction picker: 24rem panel, 48px strip, 2.5rem tiles', str_contains( $ax_pick_css, 'inline-size: min( 24rem' ) && str_contains( $ax_pick_css, 'block-size: 48px;' ) && str_contains( $ax_pick_css, 'inline-size: 2.5rem;' ) );

	/*
	 * Pixel grid (measured at devicePixelRatio 1.5, 2026-09-16). Every rule here replaced one
	 * that divided space into fractions; a return of any of them brings the fractions back.
	 */
	ax_pick_assert( $ax_pick_results, 'pixel grid: tiles sit in fixed 40px columns, not stretched 1fr ones', str_contains( $ax_pick_css, 'grid-template-columns: repeat( auto-fill, 40px );' ) && ! str_contains( $ax_pick_css, 'minmax(' ) );
	ax_pick_assert( $ax_pick_results, 'pixel grid: jumps are a fixed 32px, centred, and the divider is not a 1px border', str_contains( $ax_pick_css, 'flex: 0 0 32px;' ) && str_contains( $ax_pick_css, 'justify-content: center;' ) && ! str_contains( $ax_pick_css, 'border-block-end: 1px' ) );
	ax_pick_assert( $ax_pick_results, 'pixel grid: headings, search and empty rows have pixel line-heights, sections a rounded width', str_contains( $ax_pick_css, 'line-height: 16px;' ) && str_contains( $ax_pick_css, 'line-height: 20px;' ) && str_contains( $ax_pick_css, 'block-size: 40px;' ) && str_contains( $ax_pick_css, 'inline-size: round( down, 100%, 1px );' ) );
	ax_pick_assert( $ax_pick_results, 'pixel grid: the popover\'s measured max-height is rounded down', str_contains( $ax_pick_js, "closest( '.components-popover__content' )" ) && str_contains( $ax_pick_js, "Math.floor( value ) + 'px'" ) );

	// -- Registration ownership --------------------------------------------------------------

	/*
	 * Registered by `init`, and that is the assertion — not merely "registered eventually".
	 *
	 * The block editor fires `enqueue_block_editor_assets` from `edit-form-blocks.php`
	 * before `admin-header.php` reaches `admin_enqueue_scripts`. Registering on the latter
	 * therefore left every consumer's guard declining silently: no error, no button. The
	 * first version of this check fired `admin_enqueue_scripts` by hand and then enqueued,
	 * which reproduced the author's assumption rather than the editor's order, and passed.
	 */
	set_current_screen( 'post' );
	ax_pick_assert( $ax_pick_results, 'Emoji registers the picker before any editor hook runs', wp_script_is( AXISMUNDI_EMOJI_PICKER_HANDLE, 'registered' ) );
	ax_pick_assert(
		$ax_pick_results,
		'and it registers on init, so the block editor finds it when it enqueues before admin_enqueue_scripts',
		false !== has_action( 'init', 'axismundi_emoji_register_editor_assets' )
	);
	/*
	 * Registered, not enqueued. This plugin has no business knowing which screens belong to
	 * Note, Object Projections, or a later profile editor; a list of other people's screens
	 * kept here would need editing every time one of them grew a surface.
	 */
	ax_pick_assert( $ax_pick_results, 'but does not enqueue it, leaving that to whoever owns the screen', ! wp_script_is( AXISMUNDI_EMOJI_PICKER_HANDLE, 'enqueued' ) );

	/*
	 * Driven through a real consumer on the real hook, rather than by calling the enqueue
	 * helper directly. The bug this replaces was entirely an ordering one — the helper
	 * worked perfectly when called by hand — so a check that calls it by hand cannot see it.
	 *
	 * The Article editor is used because Note loads its editor file behind `is_admin()`,
	 * which is false under WP-CLI; the two consumers share the hook, so the ordering this
	 * proves covers both.
	 */
	$ax_pick_screen = get_current_screen();
	if ( $ax_pick_screen instanceof WP_Screen ) {
		$ax_pick_screen->is_block_editor( true );
	}
	do_action( 'enqueue_block_editor_assets' );
	ax_pick_assert( $ax_pick_results, 'a consumer enqueuing on enqueue_block_editor_assets actually gets it', wp_script_is( AXISMUNDI_EMOJI_PICKER_HANDLE, 'enqueued' ) );
	ax_pick_assert( $ax_pick_results, 'its stylesheet travels with it', wp_style_is( AXISMUNDI_EMOJI_PICKER_HANDLE, 'enqueued' ) );

	$ax_pick_note = (string) file_get_contents( WP_PLUGIN_DIR . '/axismundi-note/includes/editor.php' );
	$ax_pick_art  = (string) file_get_contents( WP_PLUGIN_DIR . '/axismundi-object-projections/includes/post-settings.php' );
	ax_pick_assert( $ax_pick_results, 'the Note editor asks for it', str_contains( $ax_pick_note, 'axismundi_emoji_enqueue_picker' ) );
	ax_pick_assert( $ax_pick_results, 'the Article editor asks for it', str_contains( $ax_pick_art, 'axismundi_emoji_enqueue_picker' ) );
	ax_pick_assert(
		$ax_pick_results,
		'both guard the call, so neither editor breaks with Emoji deactivated',
		str_contains( $ax_pick_note, "function_exists( 'axismundi_emoji_enqueue_picker' )" ) && str_contains( $ax_pick_art, "function_exists( 'axismundi_emoji_enqueue_picker' )" )
	);

	// The format is registered for the toolbar seam, never applied, so no markup is saved.
	ax_pick_assert( $ax_pick_results, 'the toolbar button rides core\'s format seam under our own namespace', str_contains( $ax_pick_js, "registerFormatType( 'axismundi-emoji/insert'" ) );
	ax_pick_assert( $ax_pick_results, 'the picker styles both colour schemes, since the editor follows the admin theme', str_contains( $ax_pick_css, 'prefers-color-scheme: dark' ) );
} catch ( Throwable $ax_pick_error ) {
	ax_pick_assert( $ax_pick_results, 'the picker suite ran to completion: ' . $ax_pick_error->getMessage(), false );
}

$ax_pick_failures = count( array_filter( $ax_pick_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_pick_results ), $ax_pick_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_pick_failures > 0 ? 1 : 0 );
}
exit( $ax_pick_failures > 0 ? 1 : 0 );
