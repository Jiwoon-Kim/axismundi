/**
 * axismundi/object-media-dialog — singleton hub runtime.
 *
 * Opens ONE dialog for whichever Object Attachments block was clicked. The slides are
 * cloned straight out of that block's DOM rather than re-fetched or re-rendered, which
 * is what makes sensitivity correct for free: a gated attachment brings its blur, its
 * warning, and its Show button along with it, so the dialog never has to re-derive a
 * decision Object Projections already made.
 *
 * Deliberately no URL involvement. Jetpack's carousel writes `#jp-carousel-{id}` and
 * pushes history on every slide change, so Back walks backwards through images instead
 * of leaving the page. Which slide is showing is view state; it lives in memory and
 * disappears with the dialog.
 *
 * The DOM contract with Object Projections is intentionally small:
 *   [data-ax-object-media]                    the attachments block
 *   [data-ax-media-open="{index}"]            an opener, one per viewable image
 *   figure[data-ax-media-index="{index}"]     the figure to clone as a slide
 *   template.axismundi-object__media-panel-data   the side panel
 *
 * This hub is imperative rather than an Interactivity store binding, unlike the Sheet
 * and Dialog blocks. Those bind a trigger that lives in the same markup and mirror its
 * aria-expanded; every opener here lives in another plugin's block and is discovered by
 * delegation, so a store would add a layer without anything to bind to.
 */

const HUB_ID = 'ax-object-media-dialog';

const hub = () => document.getElementById( HUB_ID );

const syncScrollLock = () =>
	document.documentElement.classList.toggle(
		'ax-dialog-scroll-locked',
		!! document.querySelector( 'dialog.ax-dialog[open]:not( [data-ax-modal="false"] )' )
	);

/** Empty the hub so a closed dialog holds no media and starts no downloads. */
const clearHub = ( dialog ) => {
	const track = dialog.querySelector( '.axismundi-object__carousel-track' );
	const panel = dialog.querySelector( '.ax-object-media-dialog__content' );
	if ( track ) {
		track.innerHTML = '';
		track.style.transform = '';
	}
	if ( panel ) {
		panel.innerHTML = '';
	}
	dialog.querySelectorAll( '.axismundi-object__carousel-dots, .axismundi-object__carousel-nav' )
		.forEach( ( node ) => node.remove() );
	const carousel = dialog.querySelector( '[data-ax-carousel]' );
	if ( carousel ) {
		delete carousel.axCarousel;
	}
};

/**
 * The single close path. Teardown runs here rather than from the dialog's `close`
 * event, because that event does not reliably reach a listener in this runtime — the
 * post quick view hub documents the same finding. Every exit (close button, backdrop,
 * Escape) funnels through this function so a closed dialog is never left holding media.
 */
const closeHub = ( dialog ) => {
	if ( ! dialog ) {
		return;
	}
	if ( dialog.open ) {
		dialog.close();
	}
	clearHub( dialog );
	syncScrollLock();
};

/** Build the controls the carousel engine expects, for as many slides as we cloned. */
const buildControls = ( carousel, total ) => {
	if ( total <= 1 ) {
		return;
	}
	const mk = ( cls, label ) => {
		const b = document.createElement( 'button' );
		b.type = 'button';
		b.className = cls;
		b.setAttribute( 'aria-label', label );
		return b;
	};
	carousel.appendChild(
		mk( 'axismundi-object__carousel-nav axismundi-object__carousel-nav--prev', 'Previous media' )
	);
	carousel.appendChild(
		mk( 'axismundi-object__carousel-nav axismundi-object__carousel-nav--next', 'Next media' )
	);
	const dots = document.createElement( 'div' );
	dots.className = 'axismundi-object__carousel-dots';
	for ( let i = 0; i < total; i++ ) {
		const dot = mk( 'axismundi-object__carousel-dot', 'Go to slide ' + ( i + 1 ) );
		dots.appendChild( dot );
	}
	carousel.appendChild( dots );
};

/**
 * Fill the hub from one attachments block and show it.
 *
 * @param {Element} block Attachments block that was clicked.
 * @param {number}  index Slide to open on.
 */
const openFor = ( block, index ) => {
	const dialog = hub();
	if ( ! dialog ) {
		return false;
	}
	const figures = Array.prototype.slice.call(
		block.querySelectorAll( 'figure[data-ax-media-index]' )
	);
	if ( ! figures.length ) {
		return false;
	}

	clearHub( dialog );
	const track = dialog.querySelector( '.axismundi-object__carousel-track' );
	const carousel = dialog.querySelector( '[data-ax-carousel]' );

	figures.forEach( ( figure ) => {
		const slide = document.createElement( 'div' );
		slide.className = 'axismundi-object__carousel-slide';
		slide.setAttribute( 'role', 'group' );
		slide.setAttribute( 'aria-roledescription', 'slide' );
		const clone = figure.cloneNode( true );
		// The opener is an inline affordance; inside the dialog the media is already open.
		clone.querySelectorAll( '[data-ax-media-open]' ).forEach( ( b ) => b.remove() );
		// The gallery's preview limit does not exist here: the dialog carries every
		// attachment, so a "+2 more" badge would sit over the first picture counting
		// items the reader can already reach, and the overflow class marks slides the
		// gallery chose not to show. Both are inline-presentation state, not the media.
		clone.querySelectorAll( '.axismundi-object__attachment-more' ).forEach( ( b ) => b.remove() );
		clone.classList.remove( 'is-preview-overflow' );
		clone.querySelectorAll( '.is-preview-overflow' ).forEach( ( n ) => n.classList.remove( 'is-preview-overflow' ) );
		// Lazy loading is for a long page, not for the thing the reader just asked to see.
		clone.querySelectorAll( 'img[loading="lazy"]' ).forEach( ( img ) => {
			img.loading = 'eager';
		} );
		// The inline gallery uses a selected frame (for example 5:3) and may crop into
		// it. This surface is for inspecting the attachment, so discard only those
		// presentation overrides from the clone: intrinsic dimensions and responsive
		// sources stay intact, while the dialog CSS contains the original ratio.
		clone.querySelectorAll( 'img, video' ).forEach( ( media ) => {
			media.style.removeProperty( 'aspect-ratio' );
			media.style.removeProperty( 'object-fit' );
		} );
		slide.appendChild( clone );
		track.appendChild( slide );
	} );

	buildControls( carousel, figures.length );

	const template = block.querySelector( 'template.axismundi-object__media-panel-data' );
	const panel = dialog.querySelector( '.ax-object-media-dialog__content' );
	if ( template && panel ) {
		panel.appendChild( template.content.cloneNode( true ) );
	}
	dialog.classList.toggle( 'has-panel', !! ( template && panel && panel.childElementCount ) );

	if ( ! dialog.open ) {
		document.querySelectorAll( 'dialog.ax-dialog[open]' ).forEach( ( d ) => d !== dialog && d.close() );
		dialog.showModal();
		syncScrollLock();
	}

	// The engine is Object Projections' — the dialog reuses it rather than shipping a
	// second carousel implementation.
	if ( window.axismundiObjectCarousel && carousel ) {
		window.axismundiObjectCarousel.init( dialog );
		if ( carousel.axCarousel && index > 0 ) {
			carousel.axCarousel.goTo( index );
		}
	}
	return true;
};

// Delegated: attachments blocks may be added after load (a feed, an editor preview).
document.addEventListener( 'click', ( event ) => {
	const opener = event.target.closest( '[data-ax-media-open]' );
	if ( opener ) {
		// A gated attachment's warning overlay paints above this button and swallows the
		// click, so reaching here already means the viewer revealed the media.
		const block = opener.closest( '[data-ax-object-media]' );
		if ( block && openFor( block, parseInt( opener.getAttribute( 'data-ax-media-open' ), 10 ) || 0 ) ) {
			event.preventDefault();
		}
		return;
	}
	if ( event.target.closest( '[data-ax-media-dialog-close]' ) ) {
		closeHub( hub() );
		return;
	}
	// Backdrop: a click landing on the dialog element itself is outside the surface.
	const dialog = hub();
	if ( dialog && dialog.open && event.target === dialog ) {
		closeHub( dialog );
	}
} );

// Escape: the native dialog closes itself, but its `close`/`cancel` events are not
// dependable here, so the same teardown is invoked explicitly. Listening on the document
// (capture) catches the key whether or not focus sits inside the dialog.
document.addEventListener(
	'keydown',
	( event ) => {
		if ( 'Escape' !== event.key ) {
			return;
		}
		const dialog = hub();
		if ( dialog && dialog.open ) {
			closeHub( dialog );
		}
	},
	true
);

// A native close that happens without going through closeHub (browser-initiated) still
// gets cleaned up when the event does arrive; clearHub is idempotent.
const bindClose = () => {
	const dialog = hub();
	if ( ! dialog || dialog.axCloseBound ) {
		return;
	}
	dialog.axCloseBound = true;
	dialog.addEventListener( 'close', () => {
		clearHub( dialog );
		syncScrollLock();
	} );
};

// Bound now when the document is already parsed: this module is deferred, so by the time
// it evaluates DOMContentLoaded has usually fired and a listener registered for it would
// never run.
if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', bindClose );
} else {
	bindClose();
}
