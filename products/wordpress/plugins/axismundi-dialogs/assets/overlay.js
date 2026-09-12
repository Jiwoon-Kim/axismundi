/**
 * Navigation overlay - core's mechanism, re-implemented.
 *
 * This is NOT the plugin's dialog runtime and deliberately not a native
 * `<dialog>`. Core's Navigation overlay is a `div` carrying `role="dialog"` and
 * `aria-modal`, shown by class, and this reproduces that: the same element
 * shape, the same `html.has-modal-open`, the same focus behaviour. A surface
 * that behaves like core's should be built like core's, or the two drift in
 * ways nobody can see until something breaks.
 *
 * What is NOT copied is where the open state lives. Core keeps it in the
 * element context, which welds the overlay to its own button - nothing else on
 * the page can open it. Here the trigger and the surface are rendered together
 * by one block, so they share a context without anything being welded, and the
 * surface still carries an id that a second trigger could name later.
 *
 * Ported from packages/block-library/src/navigation/view.js (wp/7.1):
 * the focusable selector list, the Tab trap, the focusout close, and the rule
 * that focus only returns to the trigger if it is still inside the overlay.
 */
import { store, getContext, getElement, withSyncEvent } from '@wordpress/interactivity';

const focusableSelectors = [
	'a[href]',
	'input:not([disabled]):not([type="hidden"]):not([aria-hidden])',
	'select:not([disabled]):not([aria-hidden])',
	'textarea:not([disabled]):not([aria-hidden])',
	'button:not([disabled]):not([aria-hidden])',
	'[contenteditable]',
	'[tabindex]:not([tabindex^="-"])',
];

function getFocusableElements( ref ) {
	return Array.from( ref.querySelectorAll( focusableSelectors ) ).filter( ( element ) => {
		if ( typeof element.checkVisibility === 'function' ) {
			return element.checkVisibility( { checkOpacity: false, checkVisibilityCSS: true } );
		}
		return element.offsetParent !== null;
	} );
}

const { state, actions } = store( 'axismundi/overlay', {
	state: {
		// `role` and `aria-modal` only while open: a closed surface is not a
		// dialog, it is markup that is not there yet. Core does the same.
		get roleAttribute() {
			return getContext().isOpen ? 'dialog' : null;
		},
		get ariaModal() {
			return getContext().isOpen ? 'true' : null;
		},
	},
	actions: {
		open() {
			const ctx = getContext();
			if ( ctx.isOpen ) {
				return;
			}
			ctx.previousFocus = getElement().ref;
			ctx.isOpen = true;
			// The scroll lock is core's class, on the root, because the page
			// behind this surface is core's page.
			document.documentElement.classList.add( 'has-modal-open' );
		},

		close() {
			const ctx = getContext();
			if ( ! ctx.isOpen ) {
				return;
			}
			ctx.isOpen = false;
			document.documentElement.classList.remove( 'has-modal-open' );
			// Only take focus back if it is still inside the surface being
			// closed; if the reader has already moved on, leave them alone.
			if ( ctx.modal?.contains( document.activeElement ) ) {
				ctx.previousFocus?.focus();
			}
			ctx.modal = null;
			ctx.previousFocus = null;
		},

		handleKeydown: withSyncEvent( ( event ) => {
			const ctx = getContext();
			if ( ! ctx.isOpen ) {
				return;
			}
			if ( event.key === 'Escape' ) {
				actions.close();
				return;
			}
			if ( event.key !== 'Tab' ) {
				return;
			}
			// The trap. A native <dialog> would do this itself; a div has to be
			// told, which is the price of matching core's element.
			if ( event.shiftKey && document.activeElement === ctx.firstFocusableElement ) {
				event.preventDefault();
				ctx.lastFocusableElement?.focus();
			} else if ( ! event.shiftKey && document.activeElement === ctx.lastFocusableElement ) {
				event.preventDefault();
				ctx.firstFocusableElement?.focus();
			}
		} ),

		handleFocusout: withSyncEvent( ( event ) => {
			const ctx = getContext();
			if ( ! ctx.isOpen ) {
				return;
			}
			// Focus left the document entirely - another tab, the dev tools,
			// the address bar. Core closes on this and it is the behaviour an
			// overlay menu is expected to have.
			if ( event.relatedTarget === null || ! ctx.modal?.contains( event.relatedTarget ) ) {
				actions.close();
			}
		} ),
	},
	callbacks: {
		initOverlay() {
			const ctx = getContext();
			const { ref } = getElement();
			if ( ! ctx.isOpen ) {
				return;
			}
			const focusable = getFocusableElements( ref );
			ctx.modal = ref;
			ctx.firstFocusableElement = focusable[ 0 ];
			ctx.lastFocusableElement = focusable[ focusable.length - 1 ];
		},
		focusFirstElement() {
			const { ref } = getElement();
			if ( getContext().isOpen ) {
				getFocusableElements( ref )[ 0 ]?.focus();
			}
		},
	},
} );
