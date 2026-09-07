/**
 * Axismundi editor colour-scheme sync.
 *
 * Front-end pages receive an early head script from the plugin. The block/site
 * editor uses separate preview documents, so mirror the same axismundi_theme
 * cookie onto the editor document and same-origin preview iframes.
 *
 * That mirroring is the whole job. This file used to reach into the rendered
 * switchers as well -- rewriting each cycle button's icon, label and
 * accessible name, and each segment's `aria-pressed` -- which is markup edit.js
 * owns and React re-renders from its own state. The two paths could not stay in
 * step: a scheme attribute added to edit.js was not added here, so a cycle
 * button ended up wearing the new icon this file had written and the colour of
 * the old scheme React still believed in. edit.js now listens for the event
 * below, so every switcher in the editor re-renders from one signal and nothing
 * needs patching behind it.
 */
( function () {
	var VALID = [ 'auto', 'light', 'dark' ];
	var COOKIE = 'axismundi_theme';
	var EVENT = 'axismundi-theme-scheme-change';
	var current = null;

	function normalize( value ) {
		return VALID.indexOf( value ) === -1 ? 'auto' : value;
	}

	function readScheme() {
		var match = document.cookie.match( new RegExp( '(?:^|;\\s*)' + COOKIE + '=(auto|light|dark)' ) );
		return normalize( match && match[ 1 ] );
	}

	function applyToDocument( doc, scheme ) {
		if ( ! doc || ! doc.documentElement ) {
			return;
		}

		doc.documentElement.dataset.theme = scheme;
	}

	function isPreviewDocument( doc ) {
		return !! (
			doc &&
			doc.documentElement &&
			doc.body &&
			doc.querySelector(
				'.editor-styles-wrapper, .block-editor-block-preview__content, .is-root-container, .wp-site-blocks, .wp-block, [data-type]'
			)
		);
	}

	function applyToIframe( iframe, scheme ) {
		try {
			if ( isPreviewDocument( iframe.contentDocument ) ) {
				applyToDocument( iframe.contentDocument, scheme );
			}
		} catch ( error ) {
			// Ignore transient or cross-origin frames. Editor preview frames are same-origin.
		}
	}

	/*
	 * The tooltip runtime, attached to each preview document.
	 *
	 * It has to happen there and not here: listeners on this document never see
	 * events inside the canvas, and the tooltip element belongs beside the
	 * block it labels rather than in the editor's chrome.
	 *
	 * Deliberately not folded into the scheme pass. That one runs when the
	 * scheme changes or the document mutates, and the canvas can finish loading
	 * after the last of those -- a page opened and left alone never got the
	 * runtime. This runs on the same poll that watches for a scheme changed in
	 * another tab, so a canvas is bound within a second of being ready.
	 * attach() is idempotent, so the cost of that is a flag read per frame.
	 */
	function attachTooltips() {
		if ( ! window.axismundiThemeSwitcher || ! window.axismundiThemeSwitcher.tooltip ) {
			return;
		}

		document.querySelectorAll( 'iframe' ).forEach( function ( iframe ) {
			try {
				if ( isPreviewDocument( iframe.contentDocument ) ) {
					window.axismundiThemeSwitcher.tooltip.attach( iframe.contentDocument );
				}
			} catch ( error ) {
				// Ignore transient or cross-origin frames.
			}
		} );
	}

	function apply() {
		current = readScheme();
		/*
		 * The editor document too, not only the preview frames. Global Styles renders the
		 * colour-palette swatches in this document, and the theme's palette entries are
		 * var(--md-sys-color-*) -- so without data-theme here the dark token layer can only
		 * match through `prefers-color-scheme`, and the swatches follow the operating system
		 * while the canvas beside them follows the switcher.
		 *
		 * The theme loads only token layers into this document, so the attribute changes
		 * custom property values and nothing else; `color-scheme` is separately pinned to
		 * normal there so the admin chrome keeps its own native controls.
		 */
		applyToDocument( document, current );
		attachTooltips();
		document.querySelectorAll( 'iframe' ).forEach( function ( iframe ) {
			applyToIframe( iframe, current );
			if ( ! iframe.dataset.axismundiThemeSchemeBound ) {
				iframe.dataset.axismundiThemeSchemeBound = 'true';
				iframe.addEventListener( 'load', function () {
					applyToIframe( iframe, readScheme() );
				} );
			}
		} );
	}

	function applyIfChanged() {
		attachTooltips();

		var next = readScheme();
		if ( next === current ) {
			return;
		}
		apply();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', apply );
	} else {
		apply();
	}

	new MutationObserver( apply ).observe( document.documentElement, {
		childList: true,
		subtree: true,
	} );

	window.addEventListener( EVENT, apply );
	window.addEventListener( 'focus', applyIfChanged );
	window.addEventListener( 'pageshow', applyIfChanged );
	document.addEventListener( 'visibilitychange', applyIfChanged );
	window.setInterval( applyIfChanged, 1000 );
} )();
