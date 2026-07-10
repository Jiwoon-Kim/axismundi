/**
 * axismundi/post-quick-view — singleton hub runtime.
 *
 * Extends the shared axismundi/dialog store (store() merges by namespace) with
 * the quick-view state and the openPostQuickView action. A feed trigger sets its
 * postId/fetchUrl in context and points aria-controls at this hub; on click the
 * action opens the hub (native showModal → top layer, scrim, focus containment,
 * Escape, focus restoration to the trigger) and fetches the fragment into
 * state.quickViewHtml, which data-wp-html injects into the body region.
 *
 * If no hub is on the page this module never loads, so the trigger's action is
 * undefined and its anchor navigates to #comments (the v0.1 fallback).
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

const html = document.documentElement;
const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
const closeTimers = new WeakMap();

const closeOthers = ( keep ) =>
	document
		.querySelectorAll( 'dialog.ax-dialog[open]' )
		.forEach( ( d ) => d !== keep && d.close() );

// Lock document scroll only while a modal dialog is open.
const syncScrollLock = () =>
	html.classList.toggle(
		'ax-dialog-scroll-locked',
		!! document.querySelector(
			'dialog.ax-dialog[open]:not( [data-ax-modal="false"] ), dialog.ax-dialog[open].is-compact-modal'
		)
	);

// Clean up after a close. Driven from closeDialog — the single path the button,
// backdrop, and Escape all funnel through — rather than a data-wp-on--close
// handler, because the <dialog> "close" event does not reliably reach the
// Interactivity listener here.
const finalizeClose = ( dialog ) => {
	dialog.classList.remove( 'is-closing' );
	const body = dialog.querySelector( '.ax-post-quick-view__body' );
	if ( body ) {
		body.innerHTML = '';
	}
	const input = dialog.querySelector( '.ax-composer__input' );
	if ( input ) {
		input.value = '';
	}
	state.quickViewBusy = false;
	state.composerBusy = false;
	state.composerError = false;
	state.composerHeld = false;
	syncScrollLock();
};

const closeDialog = ( dialog ) => {
	if ( ! dialog?.open || dialog.classList.contains( 'is-closing' ) ) {
		return;
	}
	if ( reducedMotion.matches ) {
		dialog.close();
		finalizeClose( dialog );
		return;
	}
	dialog.classList.add( 'is-closing' );
	closeTimers.set(
		dialog,
		window.setTimeout( () => {
			closeTimers.delete( dialog );
			if ( dialog.open ) {
				dialog.close();
			}
			finalizeClose( dialog );
		}, 250 )
	);
};

const fetchFragment = async ( url ) => {
	const response = await fetch( url, { headers: { Accept: 'application/json' } } );
	if ( ! response.ok ) {
		throw new Error( 'HTTP ' + response.status );
	}
	const data = await response.json();
	return data && data.html ? data.html : '';
};

// The fetched fragment is injected straight into the body element. WordPress'
// Interactivity API has no raw-HTML binding directive (only data-wp-text, which
// escapes), so state drives the loading/error chrome via data-wp-bind while the
// server-rendered, same-origin fragment is set with innerHTML here.
const { state } = store( 'axismundi/dialog', {
	state: {
		quickViewHref: '',
		quickViewPermalink: '',
		quickViewPostId: 0,
		quickViewFetchUrl: '',
		quickViewBusy: false,
		quickViewError: false,
		composerBusy: false,
		composerError: false,
		composerHeld: false,
	},
	actions: {
		*openPostQuickView( event ) {
			const trigger = getElement().ref;
			const hubId = trigger.getAttribute( 'aria-controls' );
			const hub = hubId && document.getElementById( hubId );
			// No hub on this page → let the native anchor navigate to #comments.
			if ( ! hub ) {
				return;
			}
			event.preventDefault();

			// If the hub is mid-close (is-closing timer pending), cancel it so a
			// rapid reopen isn't shut again by the stale timer and left stranded
			// open-with-is-closing.
			const pending = closeTimers.get( hub );
			if ( pending ) {
				window.clearTimeout( pending );
				closeTimers.delete( hub );
				hub.classList.remove( 'is-closing' );
			}

			const context = getContext();
			const body = hub.querySelector( '.ax-post-quick-view__body' );
			state.quickViewError = false;
			state.quickViewBusy = true;
			state.quickViewHref =
				context.href || trigger.getAttribute( 'href' ) || '';
			state.quickViewPermalink = context.permalink || '';
			state.quickViewPostId = context.postId || 0;
			state.quickViewFetchUrl = context.fetchUrl || '';
			state.composerBusy = false;
			state.composerError = false;
			state.composerHeld = false;
			if ( body ) {
				body.innerHTML = '';
			}

			if ( ! hub.open ) {
				closeOthers( hub );
				hub.showModal();
				syncScrollLock();
			}

			try {
				const html = yield fetchFragment( context.fetchUrl );
				if ( body ) {
					body.innerHTML = html;
					body.scrollTop = 0;
				}
				state.quickViewBusy = false;
			} catch ( error ) {
				state.quickViewBusy = false;
				state.quickViewError = true;
			}
		},

		// Post a top-level comment as the logged-in user via the core REST
		// endpoint (login-only by default), then refetch the thread. Reply
		// targeting is Phase 3b-2.
		*submitComment( event ) {
			event.preventDefault();
			const form = getElement().ref;
			const input = form.querySelector( '.ax-composer__input' );
			const content = input ? input.value.trim() : '';
			if ( ! content || state.composerBusy ) {
				return;
			}
			const host = form.closest( '.ax-post-quick-view-host' );
			const hub = host && host.querySelector( 'dialog.ax-post-quick-view' );
			const body = hub && hub.querySelector( '.ax-post-quick-view__body' );

			state.composerError = false;
			state.composerHeld = false;
			state.composerBusy = true;

			try {
				const response = yield fetch( form.getAttribute( 'data-ax-comments-url' ), {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': form.getAttribute( 'data-ax-nonce' ),
					},
					body: JSON.stringify( {
						post: state.quickViewPostId,
						content,
					} ),
				} );
				const data = yield response.json();
				if ( ! response.ok ) {
					throw new Error( ( data && data.message ) || 'error' );
				}
				input.value = '';
				state.composerHeld = !! ( data && data.status && data.status !== 'approved' );
				const html = yield fetchFragment( state.quickViewFetchUrl );
				if ( body ) {
					body.innerHTML = html;
				}
				state.composerBusy = false;
			} catch ( error ) {
				state.composerBusy = false;
				state.composerError = true;
			}
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
			if ( event.target !== dialog ) {
				return;
			}
			if ( dialog.getAttribute( 'data-ax-close-on-backdrop' ) !== 'true' ) {
				return;
			}
			closeDialog( dialog );
		},

		// Backup for a native close that bypasses closeDialog (e.g. dialog.close()
		// called directly); closeDialog already finalizes the common paths.
		onQuickViewClose() {
			const dialog = getElement().ref;
			const timer = closeTimers.get( dialog );
			if ( timer ) {
				window.clearTimeout( timer );
				closeTimers.delete( dialog );
			}
			finalizeClose( dialog );
		},
	},
} );

// Reddit-style reply fold. The fragment is injected with innerHTML, so its toggle
// buttons are never hydrated by the Interactivity runtime — a single delegated
// listener handles them instead. Pure DOM: toggle is-collapsed + aria-expanded,
// no fetch (all replies are already in the fetched fragment).
document.addEventListener( 'click', ( event ) => {
	const toggle = event.target.closest(
		'.ax-post-quick-view__body .ax-comment__toggle'
	);
	if ( ! toggle ) {
		return;
	}
	const comment = toggle.closest( '.ax-comment' );
	if ( ! comment ) {
		return;
	}
	const nowCollapsed = comment.classList.toggle( 'is-collapsed' );
	toggle.setAttribute( 'aria-expanded', nowCollapsed ? 'false' : 'true' );
} );
