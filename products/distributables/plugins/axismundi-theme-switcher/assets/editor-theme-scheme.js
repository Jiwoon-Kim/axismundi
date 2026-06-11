/**
 * Axismundi editor colour-scheme sync.
 *
 * Front-end pages receive an early head script from the plugin. The block/site
 * editor uses separate preview documents, so mirror the same axismundi_theme
 * cookie onto the editor document and same-origin preview iframes.
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
		syncButtons( doc, scheme );
	}

	function syncButtons( doc, scheme ) {
		if ( ! doc.querySelectorAll ) {
			return;
		}

		doc.querySelectorAll( '.wp-block-axismundi-theme-switcher [data-theme-mode]' ).forEach( function ( button ) {
			button.setAttribute( 'aria-pressed', button.getAttribute( 'data-theme-mode' ) === scheme ? 'true' : 'false' );
		} );

		doc.querySelectorAll( '.wp-block-axismundi-theme-switcher [data-theme-cycle]' ).forEach( function ( button ) {
			var icon = button.querySelector( '.material-symbols-outlined' );
			var label = button.querySelector( '.screen-reader-text' );
			var meta = {
				auto: { icon: 'contrast', label: 'Auto' },
				light: { icon: 'light_mode', label: 'Light' },
				dark: { icon: 'dark_mode', label: 'Dark' },
			}[ scheme ];

			button.setAttribute( 'aria-label', 'Color scheme: ' + meta.label + '. Activate to cycle.' );
			if ( icon ) {
				icon.textContent = meta.icon;
			}
			if ( label ) {
				label.textContent = meta.label;
			}
		} );
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

	function apply() {
		current = readScheme();
		syncButtons( document, current );
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
