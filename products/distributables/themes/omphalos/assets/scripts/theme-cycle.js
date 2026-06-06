/**
 * Omphalos — theme-cycle switcher behaviour (custom-HTML, no block, no plugin).
 *
 * Drives every [data-theme-cycle] button in the theme. Clicking advances the
 * colour scheme auto -> light -> dark, sets <html data-theme>, persists the
 * choice to the omphalos_theme cookie, and updates the Material Symbol glyph +
 * accessible label. The blocking head script (inc/theme-switcher.php) applies the
 * persisted value before paint; this only handles toggles + the live glyph.
 *
 * Vanilla (no @wordpress/interactivity) so the theme stays self-contained — the
 * companion plugin owns the Interactivity-API block version of the same control.
 */
( function () {
	var MODES = {
		auto: { icon: 'contrast', label: 'Auto' },
		light: { icon: 'light_mode', label: 'Light' },
		dark: { icon: 'dark_mode', label: 'Dark' },
	};
	var ORDER = [ 'auto', 'light', 'dark' ];
	var COOKIE = 'omphalos_theme';

	function normalize( value ) {
		return ORDER.indexOf( value ) >= 0 ? value : 'auto';
	}

	function writeCookie( mode ) {
		document.cookie = COOKIE + '=' + mode + '; path=/; max-age=31536000; SameSite=Lax';
	}

	function render( button, mode ) {
		var m = MODES[ mode ];
		var icon = button.querySelector( '.material-symbols-outlined' );
		var label = button.querySelector( '.screen-reader-text' );
		if ( icon ) {
			icon.textContent = m.icon;
		}
		if ( label ) {
			label.textContent = m.label;
		}
		button.setAttribute( 'aria-label', 'Color scheme: ' + m.label + '. Activate to cycle.' );
	}

	function apply( button, mode ) {
		var next = normalize( mode );
		document.documentElement.dataset.theme = next;
		writeCookie( next );
		render( button, next );
	}

	function init() {
		var buttons = document.querySelectorAll( '[data-theme-cycle]' );
		Array.prototype.forEach.call( buttons, function ( button ) {
			// Seed glyph/label from the scheme the head script already applied
			// (also corrects a cache-stale server-rendered glyph on load).
			render( button, normalize( document.documentElement.dataset.theme ) );
			button.addEventListener( 'click', function () {
				var current = normalize( document.documentElement.dataset.theme );
				var index = ORDER.indexOf( current );
				apply( button, ORDER[ ( index + 1 ) % ORDER.length ] );
			} );
		} );
	}

	if ( document.readyState !== 'loading' ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}
}() );
