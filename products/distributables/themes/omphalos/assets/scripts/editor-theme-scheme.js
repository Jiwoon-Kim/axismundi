/**
 * Omphalos editor canvas colour-scheme sync.
 *
 * Front-end pages get an early wp_head script that reads the omphalos_theme
 * cookie and sets <html data-theme>. The block/site editor canvas is an iframe,
 * so wp_head does not run inside it. This editor-only bridge copies the same
 * cookie value into editor-owned preview documents whenever iframes are
 * created/reloaded or when the cookie changes while the editor is open. The
 * Site Editor has more than one preview surface: the main canvas iframe and the
 * Style Book's block preview frames, so this bridge intentionally scans
 * same-origin iframes instead of binding only to the canvas selector.
 */
( function () {
	var VALID = [ 'auto', 'light', 'dark' ];
	var COOKIE = 'omphalos_theme';
	var currentScheme = null;

	function normalize( value ) {
		return VALID.indexOf( value ) === -1 ? 'auto' : value;
	}

	function readScheme() {
		var match = document.cookie.match( new RegExp( '(?:^|;\\s*)' + COOKIE + '=(auto|light|dark)' ) );
		return normalize( match && match[ 1 ] );
	}

	function applyToDocument( doc ) {
		if ( ! doc || ! doc.documentElement ) {
			return;
		}
		doc.documentElement.dataset.theme = currentScheme || readScheme();
	}

	function setHtmlThemeAttribute( html, scheme ) {
		if ( /<html\b/i.test( html ) ) {
			if ( /\sdata-theme=(["'])(auto|light|dark)\1/i.test( html ) ) {
				return html.replace( /\sdata-theme=(["'])(auto|light|dark)\1/i, ' data-theme="' + scheme + '"' );
			}
			return html.replace( /<html\b/i, '<html data-theme="' + scheme + '"' );
		}
		return html;
	}

	function isPreviewDocument( doc ) {
		if ( ! doc || ! doc.documentElement ) {
			return false;
		}
		if ( doc === document ) {
			return true;
		}
		return !! (
			doc.body &&
			doc.querySelector(
				'.editor-styles-wrapper, .block-editor-block-preview__content, .is-root-container, .wp-site-blocks, .wp-block, [data-type]'
			)
		);
	}

	function applyToIframe( iframe ) {
		try {
			if ( isPreviewDocument( iframe.contentDocument ) ) {
				applyToDocument( iframe.contentDocument );
			}
		} catch ( error ) {
			// Ignore transient or cross-origin frames. Editor preview frames are same-origin.
		}
		rewriteStyleBookBlobIframe( iframe );
	}

	function rewriteStyleBookBlobIframe( iframe ) {
		var scheme = currentScheme || readScheme();
		if (
			! iframe ||
			! iframe.classList.contains( 'editor-style-book__iframe' ) ||
			! iframe.src ||
			iframe.src.indexOf( 'blob:' ) !== 0 ||
			iframe.dataset.omphalosThemeSchemeBlob === scheme ||
			iframe.dataset.omphalosThemeSchemeRewriting ||
			typeof window.fetch !== 'function' ||
			typeof window.Blob === 'undefined' ||
			! window.URL ||
			typeof window.URL.createObjectURL !== 'function'
		) {
			return;
		}

		iframe.dataset.omphalosThemeSchemeRewriting = 'true';
		window.fetch( iframe.src )
			.then( function ( response ) {
				return response.text();
			} )
			.then( function ( html ) {
				if ( ! /<html\b/i.test( html ) ) {
					return;
				}
				var previousGeneratedSrc = iframe.dataset.omphalosThemeSchemeBlobSrc;
				var nextHtml = setHtmlThemeAttribute( html, scheme );
				iframe.src = window.URL.createObjectURL(
					new window.Blob( [ nextHtml ], { type: 'text/html' } )
				);
				iframe.dataset.omphalosThemeSchemeBlob = scheme;
				iframe.dataset.omphalosThemeSchemeBlobSrc = iframe.src;
				if ( previousGeneratedSrc ) {
					window.setTimeout( function () {
						window.URL.revokeObjectURL( previousGeneratedSrc );
					}, 1000 );
				}
			} )
			.catch( function () {} )
			.finally( function () {
				delete iframe.dataset.omphalosThemeSchemeRewriting;
			} );
	}

	function getCanvasIframes() {
		return document.querySelectorAll( 'iframe' );
	}

	function apply() {
		currentScheme = readScheme();
		applyToDocument( document );
		getCanvasIframes().forEach( function ( iframe ) {
			applyToIframe( iframe );
			if ( ! iframe.dataset.omphalosThemeSchemeBound ) {
				iframe.dataset.omphalosThemeSchemeBound = 'true';
				iframe.addEventListener( 'load', function () {
					applyToIframe( iframe );
				} );
			}
		} );
	}

	function applyIfChanged() {
		var next = readScheme();
		if ( next === currentScheme ) {
			return;
		}
		currentScheme = next;
		applyToDocument( document );
		getCanvasIframes().forEach( applyToIframe );
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

	window.addEventListener( 'focus', applyIfChanged );
	window.addEventListener( 'pageshow', applyIfChanged );
	document.addEventListener( 'visibilitychange', applyIfChanged );
	window.addEventListener( 'omphalos-theme-scheme-change', applyIfChanged );
	window.setInterval( applyIfChanged, 1000 );
} )();
