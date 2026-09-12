/**
 * The plain tooltip, in the block editor.
 *
 * assets/tooltip.js attaches itself to the document it loads in. In the editor
 * that document is the admin page, and the blocks are in the canvas iframe -
 * another document, which the runtime never sees. So this bridges the two: it
 * hands each canvas it finds to `attach`, which is idempotent and takes a
 * document for exactly this reason.
 *
 * Why bother showing it while authoring: Show tooltips is a setting with no
 * other visible effect. Without this, turning it off and on looks identical and
 * the author has to publish to find out what it does.
 *
 * The canvas is replaced when the editor re-mounts - switching to the site
 * editor, opening a template part - so the iframe is looked for again on every
 * DOM change rather than once on load.
 */
( function () {
	function bridge() {
		var tooltip = window.axismundiDialogs && window.axismundiDialogs.tooltip;
		if ( ! tooltip ) {
			return;
		}
		var frames = document.querySelectorAll( 'iframe[name="editor-canvas"]' );
		for ( var i = 0; i < frames.length; i++ ) {
			// Same-origin, and only once the document exists: a canvas that is
			// still loading has no body for the tooltip to live in. The next
			// mutation brings it back here.
			try {
				if ( frames[ i ].contentDocument && frames[ i ].contentDocument.body ) {
					tooltip.attach( frames[ i ].contentDocument );
				}
			} catch ( e ) {}
		}
		// The canvas is not always an iframe - the editor renders it inline when
		// the theme opts out of iframing, and the widget and post editors have
		// done both. Attaching to the admin document too costs nothing: `attach`
		// marks what it has bound and the triggers are the same either way.
		tooltip.attach( document );
	}

	function start() {
		bridge();
		new window.MutationObserver( bridge ).observe( document.body, {
			childList: true,
			subtree: true,
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
