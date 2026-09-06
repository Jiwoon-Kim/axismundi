/**
 * Axismundi Theme Controls — scheme picker.
 *
 * The whole runtime is one attribute on <html>. assets/schemes.css carries
 * every scheme behind `:root[data-ax-scheme="<slug>"]`, which outranks the
 * theme's bare `:root` on specificity, so switching is setting or removing an
 * attribute and nothing else has to be loaded, adopted or reordered.
 *
 * The attribute is set for the first time by an inline script at wp_head
 * priority 0, before first paint. This file only needs to handle changes.
 */

( function () {
	'use strict';

	var config = window.axismundiThemeControls;

	if ( ! config || ! config.schemes ) {
		return;
	}

	var mount = document.querySelector( '.axismundi-theme-controls' );

	if ( ! mount ) {
		return;
	}

	var ROOT = document.documentElement;
	var DEFAULT = 'baseline';

	function current() {
		return ROOT.getAttribute( 'data-ax-scheme' ) || DEFAULT;
	}

	function apply( slug ) {
		if ( slug === DEFAULT ) {
			// Baseline is the absence of the attribute, so the theme's own
			// scheme shows through rather than being overridden with a copy of
			// itself that would then need maintaining.
			ROOT.removeAttribute( 'data-ax-scheme' );
		} else {
			ROOT.setAttribute( 'data-ax-scheme', slug );
		}

		try {
			// A year, path-wide, Lax. No personal data: the value is one of a
			// fixed set of slugs the plugin itself published.
			document.cookie =
				config.cookie +
				'=' +
				encodeURIComponent( slug ) +
				';path=/;max-age=31536000;samesite=lax' +
				( location.protocol === 'https:' ? ';secure' : '' );
		} catch ( e ) {
			// A blocked cookie costs the choice on the next page, not this one.
		}

		update();
	}

	var buttons = {};

	function update() {
		var active = current();

		Object.keys( buttons ).forEach( function ( slug ) {
			var pressed = slug === active;
			buttons[ slug ].setAttribute( 'aria-pressed', pressed ? 'true' : 'false' );
		} );
	}

	var group = document.createElement( 'div' );
	group.className = 'axismundi-theme-controls__group';
	group.setAttribute( 'role', 'group' );
	group.setAttribute( 'aria-label', config.label || 'Colour scheme' );

	Object.keys( config.schemes ).forEach( function ( slug ) {
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'axismundi-theme-controls__button';
		button.dataset.scheme = slug;
		button.textContent = config.schemes[ slug ];

		/*
		 * aria-pressed rather than aria-current or a radio group: these are
		 * toggles that change the page, not navigation and not a form value,
		 * which is the same reading the Theme Switcher block settled on.
		 */
		button.setAttribute( 'aria-pressed', 'false' );

		button.addEventListener( 'click', function () {
			apply( slug );
		} );

		buttons[ slug ] = button;
		group.appendChild( button );
	} );

	mount.appendChild( group );
	mount.hidden = false;
	update();
} )();
