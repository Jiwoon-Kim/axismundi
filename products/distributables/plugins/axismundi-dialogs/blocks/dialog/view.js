/**
 * axismundi/sheet — front-end Interactivity store.
 *
 * The native <dialog> owns the hard parts: showModal() gives the top layer,
 * ::backdrop scrim, focus containment, Escape-to-close, and focus restoration to
 * the trigger. This store only:
 *   - opens the dialog from the trigger and closes it from the close block,
 *   - closes on a ::backdrop click (when enabled),
 *   - mirrors the open state onto the trigger's aria-expanded (context.isOpen),
 *   - locks document scroll while any sheet is open,
 *   - enforces a single open sheet at a time.
 *
 * The close block, the ::backdrop, and Escape all funnel through the dialog's
 * native `close` event (onDialogClose), so open state has exactly one exit path.
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

const html = document.documentElement;

const dialogFromTrigger = ( ref ) => {
	const id = ref.getAttribute( 'aria-controls' );
	return ( id && document.getElementById( id ) ) || null;
};

const closeOthers = ( keep ) =>
	document
		.querySelectorAll( 'dialog.ax-dialog[open]' )
		.forEach( ( d ) => d !== keep && d.close() );

const bindScrollState = ( dialog ) => {
	const region = dialog.querySelector(
		'.ax-dialog__surface > section > :not(header, footer)'
	);
	if ( ! region || region.dataset.axDialogScrollBound ) {
		return;
	}
	const sync = () => dialog.classList.toggle( 'is-scrolled', region.scrollTop > 0 );
	region.dataset.axDialogScrollBound = 'true';
	region.addEventListener( 'scroll', sync, { passive: true } );
	sync();
};

// Lock only while a MODAL sheet is open; a standard sheet (data-ax-modal="false")
// leaves the page interactive and scrollable. Recomputed on every open/close so
// nested or rapid toggles never strand the lock.
const syncScrollLock = () =>
	html.classList.toggle(
		'ax-dialog-scroll-locked',
		!! document.querySelector( 'dialog.ax-dialog[open]:not( [data-ax-modal="false"] )' )
	);

store( 'axismundi/dialog', {
	actions: {
		open() {
			const dialog = dialogFromTrigger( getElement().ref );
			if ( ! dialog || dialog.open ) {
				return;
			}
			closeOthers( dialog );
			// Modal: top layer, ::backdrop scrim, focus containment, Escape close.
			// Standard: no scrim, background stays interactive and scrollable.
			if ( 'false' === dialog.getAttribute( 'data-ax-modal' ) ) {
				dialog.show();
			} else {
				dialog.showModal();
			}
			getContext().isOpen = true;
			bindScrollState( dialog );
			syncScrollLock();
		},

		close() {
			getElement().ref.closest( 'dialog.ax-dialog' )?.close();
		},

		onBackdropClick( event ) {
			const dialog = getElement().ref;
			// A ::backdrop click reports the dialog itself as the target; a click on
			// the surface or its children does not.
			if ( event.target !== dialog ) {
				return;
			}
			if ( dialog.getAttribute( 'data-ax-close-on-backdrop' ) !== 'true' ) {
				return;
			}
			dialog.close();
		},

		onDialogClose() {
			getElement().ref.classList.remove( 'is-scrolled' );
			getContext().isOpen = false;
			syncScrollLock();
		},
	},
} );
