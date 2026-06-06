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
	var editorTokenStyles = [
		'assets/styles/tokens.ref.css',
		'assets/styles/tokens.sys.light.css',
		'assets/styles/tokens.sys.core.css',
		'assets/styles/tokens.sys.dark.css',
	];
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
	var styleBookCoreStyles = [
		'wp-includes/blocks/image/style.min.css',
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
		if ( doc === document ) {
			injectEditorTokenAssets( doc );
		}
		doc.documentElement.dataset.theme = currentScheme || readScheme();
		syncThemeSwitcherButtons( doc, currentScheme || readScheme() );
	}

	function injectEditorTokenAssets( doc ) {
		if ( ! themeRootUrl || ! doc.head || doc.querySelector( '[data-omphalos-editor-token-assets]' ) ) {
			return;
		}

		editorTokenStyles.forEach( function ( path ) {
			var link = doc.createElement( 'link' );
			link.rel = 'stylesheet';
			link.href = themeRootUrl + path;
			link.dataset.omphalosEditorTokenAssets = 'true';
			doc.head.appendChild( link );
		} );
	}

	function injectStyleBookAssetsIntoDocument( doc ) {
		if ( ! themeRootUrl || ! doc.head || doc.querySelector( '[data-omphalos-stylebook-assets]' ) ) {
			return;
		}

		var fontStyle = doc.createElement( 'style' );
		fontStyle.dataset.omphalosStylebookAssets = 'true';
		fontStyle.textContent =
			'@font-face{font-family:"Material Symbols Outlined";font-style:normal;font-weight:400;font-display:block;src:url("' +
			themeRootUrl +
			'assets/icons/material-symbols-outlined/material-symbols-outlined.woff2") format("woff2");}' +
			'.editor-styles-wrapper,.block-editor-block-preview__content,body{word-break:keep-all;overflow-wrap:anywhere;}';
		doc.head.appendChild( fontStyle );

		styleBookCoreStyles.concat( styleBookStyles ).forEach( function ( path ) {
			var link = doc.createElement( 'link' );
			link.rel = 'stylesheet';
			link.href = makeSiteAssetUrl( path );
			link.dataset.omphalosStylebookAssets = 'true';
			doc.head.appendChild( link );
		} );
	}

	function makeSiteAssetUrl( path ) {
		if ( path.indexOf( 'wp-includes/' ) === 0 ) {
			return getSiteRootUrl() + path;
		}
		return themeRootUrl + path;
	}

	function getSiteRootUrl() {
		if ( typeof window.ajaxurl === 'string' && window.ajaxurl ) {
			return window.ajaxurl.replace( /wp-admin\/admin-ajax\.php(?:\?.*)?$/, '' );
		}
		return window.location.origin + '/';
	}

	function syncThemeSwitcherButtons( doc, scheme ) {
		if ( ! doc || ! doc.querySelectorAll ) {
			return;
		}
		doc.querySelectorAll( '.wp-block-omphalos-theme-switcher [data-theme-mode]' ).forEach( function ( button ) {
			button.setAttribute( 'aria-pressed', button.getAttribute( 'data-theme-mode' ) === scheme ? 'true' : 'false' );
		} );
	}

	function setHtmlThemeAttribute( html, scheme, resetScroll ) {
		html = setThemeSwitcherActiveState( html, scheme );
		if ( /<html\b/i.test( html ) ) {
			if ( /\sdata-theme=(["'])(auto|light|dark)\1/i.test( html ) ) {
				return injectStyleBookAssets(
					html.replace( /\sdata-theme=(["'])(auto|light|dark)\1/i, ' data-theme="' + scheme + '"' ),
					resetScroll
				);
			}
			return injectStyleBookAssets(
				html.replace( /<html\b/i, '<html data-theme="' + scheme + '"' ),
				resetScroll
			);
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

	function injectStyleBookAssets( html, resetScroll ) {
		if ( ! themeRootUrl || html.indexOf( 'omphalos-stylebook-assets' ) !== -1 ) {
			return html;
		}

		var links = styleBookCoreStyles.concat( styleBookStyles ).map( function ( path ) {
			return '<link rel="stylesheet" href="' + makeSiteAssetUrl( path ) + '" data-omphalos-stylebook-assets="true">';
		} ).join( '' );
		/* The Style Book typography specimen uses one long mixed-script sample
		 * without spaces. TT5's defaults fit by chance; M3 type can overflow the
		 * blob iframe unless the lab's mixed-script break policy is present. */
		var materialSymbolsFont =
			'<style data-omphalos-stylebook-assets="true">' +
			'@font-face{font-family:"Material Symbols Outlined";font-style:normal;font-weight:400;font-display:block;src:url("' +
			themeRootUrl +
			'assets/icons/material-symbols-outlined/material-symbols-outlined.woff2") format("woff2");}' +
			'.editor-styles-wrapper,.block-editor-block-preview__content,body{word-break:keep-all;overflow-wrap:anywhere;}' +
			'</style>';
		// New generated blob previews may not paint until the frame is activated.
		// Nudge focus/scroll inside the blob itself; parent access is unreliable.
		var scrollResetScript = resetScroll
			? '<script data-omphalos-stylebook-assets="true">(function(){var started=Date.now();function reset(){try{document.documentElement.dataset.omphalosStylebookScrollReset="true";window.focus();window.scrollTo(0,0);document.documentElement.scrollTop=0;if(document.body){document.body.scrollTop=0;}Array.prototype.forEach.call(document.querySelectorAll("*"),function(element){if(element.scrollTop){element.scrollTop=0;}});}catch(error){}if(Date.now()-started<4000){requestAnimationFrame(reset);}}reset();}());</script>'
			: '';
		var assets = materialSymbolsFont + links + scrollResetScript;

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
		var didApplyToLiveDocument = false;
		try {
			if ( isPreviewDocument( iframe.contentDocument ) ) {
				applyToDocument( iframe.contentDocument );
				if ( isEditorBlobPreviewIframe( iframe ) ) {
					injectStyleBookAssetsIntoDocument( iframe.contentDocument );
				}
				didApplyToLiveDocument = true;
			}
		} catch ( error ) {
			// Ignore transient or cross-origin frames. Editor preview frames are same-origin.
		}
		if ( didApplyToLiveDocument ) {
			return;
		}
		rewriteStyleBookBlobIframe( iframe );
	}

	function rewriteStyleBookBlobIframe( iframe ) {
		var scheme = currentScheme || readScheme();
		var sourceSrc = iframe && iframe.src;
		var generatedSrc = iframe && iframe.dataset.omphalosThemeSchemeBlobGeneratedSrc;
		if (
			! iframe ||
			! isEditorBlobPreviewIframe( iframe ) ||
			! sourceSrc ||
			sourceSrc.indexOf( 'blob:' ) !== 0 ||
			iframe.dataset.omphalosThemeSchemeRewriting ||
			typeof window.fetch !== 'function' ||
			typeof window.Blob === 'undefined' ||
			! window.URL ||
			typeof window.URL.createObjectURL !== 'function'
		) {
			return;
		}
		if (
			iframe.dataset.omphalosThemeSchemeBlob === scheme &&
			(
				sourceSrc === generatedSrc ||
				iframe.dataset.omphalosThemeSchemeBlobSourceSrc === sourceSrc
			)
		) {
			return;
		}

		// Style Book SPA routes replace the source blob without changing the
		// scheme. Track source/generated blobs separately so variations refresh.
		iframe.dataset.omphalosThemeSchemeRewriting = 'true';
		window.fetch( sourceSrc )
			.then( function ( response ) {
				return response.text();
			} )
			.then( function ( html ) {
				if ( ! /<html\b/i.test( html ) ) {
					return;
				}
				var previousGeneratedSrc = iframe.dataset.omphalosThemeSchemeBlobGeneratedSrc;
				var shouldResetScroll = iframe.dataset.omphalosThemeSchemeBlobSourceSrc !== sourceSrc;
				var nextHtml = setHtmlThemeAttribute( html, scheme, shouldResetScroll );
				var nextGeneratedSrc = window.URL.createObjectURL(
					new window.Blob( [ nextHtml ], { type: 'text/html' } )
				);
				if ( shouldResetScroll ) {
					iframe.addEventListener(
						'load',
						function () {
							resetStyleBookIframeScroll( iframe );
							window.setTimeout( function () {
								resetStyleBookIframeScroll( iframe );
							}, 50 );
							window.setTimeout( function () {
								resetStyleBookIframeScroll( iframe );
							}, 250 );
							window.setTimeout( function () {
								resetStyleBookIframeScroll( iframe );
							}, 750 );
						},
						{ once: true }
					);
				}
				iframe.src = nextGeneratedSrc;
				iframe.dataset.omphalosThemeSchemeBlob = scheme;
				iframe.dataset.omphalosThemeSchemeBlobSourceSrc = sourceSrc;
				iframe.dataset.omphalosThemeSchemeBlobGeneratedSrc = nextGeneratedSrc;
				if ( previousGeneratedSrc && previousGeneratedSrc !== nextGeneratedSrc ) {
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

	function resetStyleBookIframeScroll( iframe ) {
		try {
			iframe.focus();
			if ( iframe.contentWindow ) {
				iframe.contentWindow.focus();
				iframe.contentWindow.scrollTo( 0, 0 );
			}
			if ( iframe.contentDocument && iframe.contentDocument.documentElement ) {
				iframe.contentDocument.documentElement.scrollTop = 0;
			}
			if ( iframe.contentDocument && iframe.contentDocument.body ) {
				iframe.contentDocument.body.scrollTop = 0;
			}
		} catch ( error ) {}
	}

	function isEditorBlobPreviewIframe( iframe ) {
		return !! (
			iframe &&
			iframe.src &&
			iframe.src.indexOf( 'blob:' ) === 0 &&
			iframe.classList.contains( 'editor-style-book__iframe' )
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
