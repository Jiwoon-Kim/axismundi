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
	var scriptSrc = ( document.currentScript && document.currentScript.src ) || '';
	var themeRootUrl = scriptSrc.replace( /assets\/scripts\/editor-theme-scheme\.js(?:\?.*)?$/, '' );
	var styleBookStyles = [
		'assets/styles/tokens.ref.css',
		'assets/styles/tokens.sys.light.css',
		'assets/styles/tokens.sys.core.css',
		'assets/styles/tokens.comp.css',
		'assets/styles/tokens.sys.dark.css',
		'assets/styles/foundation.css',
		'assets/styles/prose.css',
		'assets/styles/blocks.css',
		'assets/styles/icons.css',
		'blocks/theme-switcher/style.css',
	];

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
		syncThemeSwitcherButtons( doc, currentScheme || readScheme() );
	}

	function syncThemeSwitcherButtons( doc, scheme ) {
		if ( ! doc || ! doc.querySelectorAll ) {
			return;
		}
		doc.querySelectorAll( '.wp-block-omphalos-theme-switcher [data-theme-mode]' ).forEach( function ( button ) {
			button.setAttribute( 'aria-pressed', button.getAttribute( 'data-theme-mode' ) === scheme ? 'true' : 'false' );
		} );
	}

	function setHtmlThemeAttribute( html, scheme ) {
		html = setThemeSwitcherActiveState( html, scheme );
		if ( /<html\b/i.test( html ) ) {
			if ( /\sdata-theme=(["'])(auto|light|dark)\1/i.test( html ) ) {
				return injectStyleBookAssets(
					html.replace( /\sdata-theme=(["'])(auto|light|dark)\1/i, ' data-theme="' + scheme + '"' )
				);
			}
			return injectStyleBookAssets( html.replace( /<html\b/i, '<html data-theme="' + scheme + '"' ) );
		}
		return html;
	}

	function setThemeSwitcherActiveState( html, scheme ) {
		return html.replace( /<button\b[^>]*>/gi, function ( tag ) {
			var modeMatch = tag.match( /\bdata-theme-mode=(["'])(auto|light|dark)\1/i );
			var mode = modeMatch && modeMatch[ 2 ];
			var pressed = mode === scheme ? 'true' : 'false';

			if ( ! mode ) {
				return tag;
			}

			if ( /\saria-pressed=(["'])(true|false)\1/i.test( tag ) ) {
				return tag.replace( /\saria-pressed=(["'])(true|false)\1/i, ' aria-pressed="' + pressed + '"' );
			}

			return tag.replace( />$/, ' aria-pressed="' + pressed + '">' );
		} );
	}

	function injectStyleBookAssets( html ) {
		if ( ! themeRootUrl || html.indexOf( 'omphalos-stylebook-assets' ) !== -1 ) {
			return html;
		}

		var links = styleBookStyles.map( function ( path ) {
			return '<link rel="stylesheet" href="' + themeRootUrl + path + '" data-omphalos-stylebook-assets="true">';
		} ).join( '' );
		var materialSymbolsFont =
			'<style data-omphalos-stylebook-assets="true">' +
			'@font-face{font-family:"Material Symbols Outlined";font-style:normal;font-weight:400;font-display:block;src:url("' +
			themeRootUrl +
			'assets/icons/material-symbols-outlined/material-symbols-outlined.woff2") format("woff2");}' +
			'</style>';
		var assets = materialSymbolsFont + links;

		if ( /<\/head>/i.test( html ) ) {
			return html.replace( /<\/head>/i, assets + '</head>' );
		}
		return assets + html;
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
			! isEditorBlobPreviewIframe( iframe ) ||
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

	function isEditorBlobPreviewIframe( iframe ) {
		return !! (
			iframe &&
			iframe.src &&
			iframe.src.indexOf( 'blob:' ) === 0 &&
			(
				iframe.classList.contains( 'editor-style-book__iframe' ) ||
				iframe.getAttribute( 'title' ) === 'Editor canvas'
			)
		);
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
