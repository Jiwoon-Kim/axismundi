import { getContext, getElement, store, withScope } from '@wordpress/interactivity';

const menuFor = ( context ) => context.menuId ? document.getElementById( context.menuId ) : null;

const triggerFor = ( context, ref ) => context.anchorId
	? document.getElementById( context.anchorId )
	: ref.querySelector( '.axismundi-interaction__button' );

const paintFeedTrigger = ( context ) => {
	if ( ! context.anchorId ) {
		return;
	}
	const trigger = document.getElementById( context.anchorId );
	if ( ! trigger ) {
		return;
	}
	trigger.disabled = Boolean( context.isPending );
	trigger.setAttribute( 'aria-pressed', context.isAnnounced ? 'true' : 'false' );
	trigger.classList.toggle( 'is-selected', Boolean( context.isAnnounced ) );
	const count = trigger.querySelector( '.axismundi-interaction__count' );
	if ( count ) {
		count.textContent = Number( context.announces || 0 ).toLocaleString();
	}
};

function positionMenu( trigger, menu ) {
	const rect = trigger.getBoundingClientRect();
	const box = menu.getBoundingClientRect();
	const gap = 8;
	const edge = 16;
	const opensUp = rect.bottom + gap + box.height > window.innerHeight;
	menu.style.left = `${ Math.max( edge, Math.min( rect.right - box.width, window.innerWidth - box.width - edge ) ) }px`;
	menu.style.top = `${ opensUp ? Math.max( edge, rect.top - gap - box.height ) : Math.min( rect.bottom + gap, window.innerHeight - box.height - edge ) }px`;
	menu.dataset.placement = opensUp ? 'top' : 'bottom';
}

const { state, actions } = store( 'axismundi/announce-button', {
	state: {
		get isDisabled() {
			const context = getContext();
			return ! context.canAnnounce || context.isPending;
		},
		get announceLabel() {
			return getContext().isAnnounced ? 'Undo repost' : 'Repost';
		},
		get isMenuOpen() {
			return '' !== state.menuOpenFor && state.menuOpenFor === getContext().menuId;
		},
		get isMenuHidden() {
			return ! state.isMenuOpen;
		},
	},

	actions: {
		toggleMenu() {
			const context = getContext();
			state.menuOpenFor = state.menuOpenFor === context.menuId ? '' : context.menuId;
		},

		/** Open the one hydrated feed menu from any card, including cards appended later. */
		openFeedMenu( event ) {
			const target = event.target instanceof Element ? event.target : null;
			const trigger = target ? target.closest( '[data-ax-action="announce-menu"]' ) : null;
			if ( ! trigger || trigger.disabled || ! trigger.dataset.axObjectUri ) {
				return;
			}
			event.preventDefault();
			for ( const openTrigger of document.querySelectorAll( '[data-ax-action="announce-menu"][aria-expanded="true"]' ) ) {
				openTrigger.setAttribute( 'aria-expanded', 'false' );
			}
			trigger.setAttribute( 'aria-expanded', 'true' );
			const context = getContext();
			context.objectUri = trigger.dataset.axObjectUri;
			context.anchorId = trigger.id;
			context.quoteUrl = trigger.dataset.axQuoteUrl || '';
			context.endpoint = trigger.dataset.axEndpoint || context.endpoint;
			context.nonce = trigger.dataset.axNonce || context.nonce;
			context.canAnnounce = true;
			context.isDisabled = false;
			context.isPending = false;
			context.isAnnounced = trigger.getAttribute( 'aria-pressed' ) === 'true';
			context.announces = Number( trigger.querySelector( '.axismundi-interaction__count' )?.textContent || 0 );
			context.error = '';
			state.menuOpenFor = context.menuId;
		},

		*toggleAnnounce() {
			const context = getContext();
			if ( ! context.canAnnounce || context.isPending ) {
				return;
			}
			const previousState = context.isAnnounced;
			const previousCount = context.announces;
			context.isPending = true;
			context.isDisabled = true;
			context.error = '';
			context.isAnnounced = ! previousState;
			context.announces = Math.max( 0, previousCount + ( context.isAnnounced ? 1 : -1 ) );
			paintFeedTrigger( context );
			try {
				const response = yield fetch( context.endpoint, {
					method: context.isAnnounced ? 'POST' : 'DELETE',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': context.nonce },
					body: JSON.stringify( { object_uri: context.objectUri } ),
				} );
				const result = yield response.json();
				if ( ! response.ok ) {
					throw new Error( result.message || 'request_failed' );
				}
				context.isAnnounced = Boolean( result.is_announced );
				context.announces = Number( result.announce_count ) || 0;
				state.menuOpenFor = '';
			} catch ( error ) {
				context.isAnnounced = previousState;
				context.announces = previousCount;
				context.error = error instanceof Error && error.message !== 'request_failed' ? error.message : context.errorFallback;
			} finally {
				context.isPending = false;
				context.isDisabled = ! context.canAnnounce;
				paintFeedTrigger( context );
			}
		},
	},

	callbacks: {
		menuLifecycle() {
			const { ref } = getElement();
			const context = getContext();
			if ( state.menuOpenFor !== context.menuId ) {
				return;
			}
			const menu = menuFor( context );
			const trigger = triggerFor( context, ref );
			if ( ! menu || ! trigger ) {
				return;
			}
			const place = withScope( () => positionMenu( trigger, menu ) );
			place();
			window.requestAnimationFrame( () => menu.querySelector( '[role="menuitem"]:not([hidden]):not([disabled])' )?.focus() );
			const close = () => {
				state.menuOpenFor = '';
				if ( context.anchorId ) {
					trigger.setAttribute( 'aria-expanded', 'false' );
				}
			};
			const onDown = withScope( ( event ) => {
				if ( ! ref.contains( event.target ) ) {
					close();
				}
			} );
			const onKey = withScope( ( event ) => {
				const items = [ ...menu.querySelectorAll( '[role="menuitem"]:not([hidden]):not([disabled])' ) ];
				if ( 'Escape' === event.key ) {
					event.preventDefault();
					close();
					trigger.focus();
					return;
				}
				if ( ! items.length || ! [ 'ArrowDown', 'ArrowUp', 'Home', 'End' ].includes( event.key ) ) {
					return;
				}
				event.preventDefault();
				const current = Math.max( 0, items.indexOf( document.activeElement ) );
				const index = 'Home' === event.key ? 0 : 'End' === event.key ? items.length - 1 : ( current + ( 'ArrowDown' === event.key ? 1 : -1 ) + items.length ) % items.length;
				items[ index ].focus();
			} );
			let frame = window.requestAnimationFrame( () => document.addEventListener( 'pointerdown', onDown ) );
			document.addEventListener( 'keydown', onKey );
			window.addEventListener( 'resize', place );
			window.addEventListener( 'scroll', place, true );
			return () => {
				window.cancelAnimationFrame( frame );
				document.removeEventListener( 'pointerdown', onDown );
				document.removeEventListener( 'keydown', onKey );
				window.removeEventListener( 'resize', place );
				window.removeEventListener( 'scroll', place, true );
			};
		},
	},
} );
