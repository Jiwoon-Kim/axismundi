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
const compactSheet = window.matchMedia( '(max-width: 839px)' );
const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
const closeTimers = new WeakMap();

const dialogFromTrigger = ( ref ) => {
	const id = ref.getAttribute( 'aria-controls' );
	return ( id && document.getElementById( id ) ) || null;
};

const closeOthers = ( keep ) =>
	document
		.querySelectorAll( 'dialog.ax-dialog[open]' )
		.forEach( ( d ) => d !== keep && d.close() );

const closeDialog = ( dialog ) => {
	if ( ! dialog?.open || dialog.classList.contains( 'is-closing' ) ) {
		return;
	}
	if ( reducedMotion.matches ) {
		dialog.close();
		return;
	}
	if (
		dialog.getAttribute( 'data-ax-push' ) === 'true' &&
		! compactSheet.matches
	) {
		html.style.setProperty( '--ax-dialog-push-size', '0px' );
	}
	dialog.classList.add( 'is-closing' );
	closeTimers.set( dialog, window.setTimeout( () => {
		closeTimers.delete( dialog );
		if ( dialog.open ) {
			dialog.close();
		}
	}, 250 ) );
};

const clearPush = () => {
	html.classList.remove( 'ax-dialog-pushed-start', 'ax-dialog-pushed-end' );
	html.style.removeProperty( '--ax-dialog-push-size' );
};

const applyPush = ( dialog ) => {
	clearPush();
	if ( dialog.getAttribute( 'data-ax-push' ) !== 'true' ) {
		return;
	}
	const edge = dialog.getAttribute( 'data-ax-edge' ) === 'start' ? 'start' : 'end';
	html.style.setProperty( '--ax-dialog-push-size', `${ dialog.getBoundingClientRect().width }px` );
	html.classList.add( `ax-dialog-pushed-${ edge }` );
};

const usesModalPresentation = ( dialog ) =>
	dialog.getAttribute( 'data-ax-modal' ) !== 'false' ||
	( dialog.getAttribute( 'data-ax-push' ) === 'true' && compactSheet.matches );

const bindResponsiveClose = ( dialog ) => {
	if ( dialog.dataset.axDialogResponsiveBound ) {
		return;
	}
	dialog.dataset.axDialogResponsiveBound = 'true';
	compactSheet.addEventListener( 'change', () => {
		if ( dialog.open && dialog.getAttribute( 'data-ax-push' ) === 'true' ) {
			dialog.close();
		}
	} );
};

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
		!! document.querySelector( 'dialog.ax-dialog[open]:not( [data-ax-modal="false"] ), dialog.ax-dialog[open].is-compact-modal' )
	);

store( 'axismundi/dialog', {
	actions: {
		open() {
			const dialog = dialogFromTrigger( getElement().ref );
			if ( ! dialog || dialog.open ) {
				return;
			}
			clearPush();
			closeOthers( dialog );
			const modalPresentation = usesModalPresentation( dialog );
			dialog.classList.toggle( 'is-compact-modal', modalPresentation && dialog.getAttribute( 'data-ax-modal' ) === 'false' );
			// Modal: top layer, ::backdrop scrim, focus containment, Escape close.
			// Wide Standard: no scrim; reserve matching space in the site root.
			if ( modalPresentation ) {
				dialog.showModal();
			} else {
				dialog.show();
				applyPush( dialog );
			}
			getContext().isOpen = true;
			bindScrollState( dialog );
			bindResponsiveClose( dialog );
			syncScrollLock();
		},

		close() {
			closeDialog( getElement().ref.closest( 'dialog.ax-dialog' ) );
		},

		onCancel( event ) {
			event.preventDefault();
			closeDialog( getElement().ref );
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
			closeDialog( dialog );
		},

		onDialogClose() {
			const dialog = getElement().ref;
			const timer = closeTimers.get( dialog );
			if ( timer ) {
				window.clearTimeout( timer );
				closeTimers.delete( dialog );
			}
			dialog.classList.remove( 'is-scrolled', 'is-compact-modal', 'is-closing' );
			clearPush();
			getContext().isOpen = false;
			syncScrollLock();
		},
	},
} );
