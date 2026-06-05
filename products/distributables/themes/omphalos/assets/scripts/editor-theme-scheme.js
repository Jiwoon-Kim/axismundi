/**
 * Omphalos editor canvas colour-scheme sync.
 *
 * Front-end pages get an early wp_head script that reads the omphalos_theme
 * cookie and sets <html data-theme>. The block/site editor canvas is an iframe,
 * so wp_head does not run inside it. This editor-only bridge copies the same
 * cookie value into the canvas document whenever the iframe is created/reloaded.
 */
( function () {
	var VALID = [ 'auto', 'light', 'dark' ];
	var COOKIE = 'omphalos_theme';

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
		doc.documentElement.dataset.theme = readScheme();
	}

	function applyToIframe( iframe ) {
		try {
			applyToDocument( iframe.contentDocument );
		} catch ( error ) {
			// The editor canvas is same-origin; ignore any transient inaccessible frame.
		}
	}

	function getCanvasIframes() {
		return document.querySelectorAll(
			'iframe[name="editor-canvas"], iframe.block-editor-iframe__html, iframe.edit-site-visual-editor__editor-canvas'
		);
	}

	function apply() {
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

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', apply );
	} else {
		apply();
	}

	new MutationObserver( apply ).observe( document.documentElement, {
		childList: true,
		subtree: true,
	} );
} )();
