import { getContext, getElement, store, withScope, withSyncEvent } from '@wordpress/interactivity';

/**
 * The timeline as a client-rendered island inside a server-rendered page.
 *
 * The profile header, the tabs, and the first page are ordinary HTML. Only the continuation is
 * fetched, and each request returns exactly one page which is appended once — constant work per
 * step, the same shape Jetpack's infinite scroll uses.
 *
 * Ownership of a card's clicks belongs to whichever surface the card is on.
 *
 * On a single object page a Like button is the interaction and owns itself. In a feed the same
 * control repeats across cards that are appended and replaced continuously, and DOM added after
 * load is never hydrated — so the server renders those controls as presentation only, without
 * interactive directives, and this region handles them. Both paths are uniform here: the first
 * server-rendered page and every appended page are handled the same way, because both are
 * presentation. Nothing distinguishes them, so nothing can fire twice.
 */
const send = async ( url, method, body, nonce ) => {
	const response = await fetch( url, {
		method,
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce || '' },
		body: JSON.stringify( body ),
	} );
	const result = await response.json();
	if ( ! response.ok ) {
		throw new Error( result.message || 'request_failed' );
	}
	return result;
};

/** Reflect a Like result back into the shared interaction control that was pressed. */
const paintLike = ( button, result ) => {
	button.setAttribute( 'aria-pressed', result.is_liked ? 'true' : 'false' );
	button.classList.toggle( 'is-selected', !! result.is_liked );
	const count = button.querySelector( '.axismundi-interaction__count' );
	if ( count ) {
		count.textContent = Number( result.like_count || 0 ).toLocaleString();
	}
};

/** Reflect a Repost result back into the shared interaction control that was pressed. */
const paintAnnounce = ( button, result ) => {
	button.setAttribute( 'aria-pressed', result.is_announced ? 'true' : 'false' );
	button.classList.toggle( 'is-selected', !! result.is_announced );
	const count = button.querySelector( '.axismundi-interaction__count' );
	if ( count ) {
		count.textContent = Number( result.announce_count || 0 ).toLocaleString();
	}
};

/** Reflect a community vote result back into the control group that was pressed. */
const paintVote = ( group, result ) => {
	group.dataset.axVoteViewer = result.viewer || 'none';
	const paint = ( button, active, on, off ) => {
		if ( ! button ) {
			return;
		}
		button.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		button.classList.toggle( 'is-selected', active );
		const icon = button.querySelector( '.material-symbols-outlined' );
		if ( icon ) {
			icon.textContent = active ? on : off;
		}
	};
	paint( group.querySelector( '[data-ax-direction="up"]' ), result.viewer === 'up', 'thumb_up', 'thumb_up_off_alt' );
	paint( group.querySelector( '[data-ax-direction="down"]' ), result.viewer === 'down', 'thumb_down', 'thumb_down_off_alt' );
	const score = group.querySelector( '.axismundi-interaction__value' );
	if ( score ) {
		score.textContent = Number( result.score || 0 ).toLocaleString();
	}
};

/**
 * Perform one card action.
 *
 * The semantics live with the feature they belong to, not with the feed: this only reads what the
 * server put on the element and calls the same endpoint the interactive block would have called.
 * A second definition of what a Like means is how the two surfaces would drift apart.
 */
const performAction = async ( button ) => {
	const action = button.dataset.axAction;
	if ( 'like' === action ) {
		const liked = button.getAttribute( 'aria-pressed' ) === 'true';
		const previousCount = Number( button.querySelector( '.axismundi-interaction__count' )?.textContent || 0 );
		paintLike( button, { is_liked: ! liked, like_count: Math.max( 0, previousCount + ( liked ? -1 : 1 ) ) } );
		try {
			paintLike( button, await send( button.dataset.axEndpoint, liked ? 'DELETE' : 'POST', { object_uri: button.dataset.axObjectUri }, button.dataset.axNonce ) );
		} catch ( error ) {
			paintLike( button, { is_liked: liked, like_count: previousCount } );
			throw error;
		}
		return;
	}
	if ( 'announce' === action ) {
		const announced = button.getAttribute( 'aria-pressed' ) === 'true';
		const previousCount = Number( button.querySelector( '.axismundi-interaction__count' )?.textContent || 0 );
		paintAnnounce( button, { is_announced: ! announced, announce_count: Math.max( 0, previousCount + ( announced ? -1 : 1 ) ) } );
		try {
			paintAnnounce( button, await send( button.dataset.axEndpoint, announced ? 'DELETE' : 'POST', { object_uri: button.dataset.axObjectUri }, button.dataset.axNonce ) );
		} catch ( error ) {
			paintAnnounce( button, { is_announced: announced, announce_count: previousCount } );
			throw error;
		}
		return;
	}
	if ( 'vote' === action ) {
		const group = button.closest( '[data-ax-vote-object-uri]' );
		if ( ! group ) {
			return;
		}
		// Pressing the side already held is a withdrawal, matching the server's rule.
		const wanted = button.dataset.axDirection;
		const direction = group.dataset.axVoteViewer === wanted ? 'none' : wanted;
		paintVote( group, await send( group.dataset.axVoteEndpoint, 'POST', { object_uri: group.dataset.axVoteObjectUri, direction }, group.dataset.axNonce ) );
	}
};

const handleFeedClick = async ( event ) => {
	const target = event.target instanceof Element ? event.target : null;
	const button = target ? target.closest( '[data-ax-action]' ) : null;
	if ( ! button || button.disabled || button.dataset.axPending === '1' ) {
		return;
	}
	// The one hydrated picker host owns reaction controls, including controls in appended cards.
	// It needs the original click to reach its document-level handler.
	if ( 'reaction' === button.dataset.axAction || 'announce-menu' === button.dataset.axAction ) {
		return;
	}
	event.preventDefault();
	button.dataset.axPending = '1';
	button.disabled = true;
	try {
		await performAction( button );
	} catch ( error ) {
		// The control keeps whatever the server last told it. Repainting a guess would be worse
		// than leaving it where it was.
	} finally {
		delete button.dataset.axPending;
		button.disabled = false;
	}
};

/*
 * The two timeline switches are a reading preference, not a destination, so they live in the
 * reader's browser rather than in the URL. That means the server always renders the default and
 * the runtime reconciles: if the stored preference differs, page one is fetched again with it.
 * A profile link therefore always points at the same thing for everyone, and one reader's choice
 * to hide boosts is not baked into every link they share.
 */
const STORAGE = 'axismundi:feed:';

const readStored = ( bit, fallback ) => {
	try {
		const value = window.localStorage.getItem( STORAGE + bit );
		return null === value ? fallback : '1' === value;
	} catch ( error ) {
		// Storage can be unavailable or full. The default is a perfectly good answer.
		return fallback;
	}
};

const writeStored = ( bit, on ) => {
	try {
		window.localStorage.setItem( STORAGE + bit, on ? '1' : '0' );
	} catch ( error ) {
		// A preference that cannot be remembered still applies to this page.
	}
};

const filterKey = ( replies, boosts ) => {
	if ( replies && boosts ) {
		return 'all';
	}
	if ( replies ) {
		return 'posts-and-replies';
	}
	return boosts ? 'posts-and-boosts' : 'posts';
};

const feedParts = ( ref ) => {
	const section = ref.closest( '.axismundi-activity-feed' );
	return {
		section,
		list: section?.querySelector( '.axismundi-activity-feed__list' ),
		more: section?.querySelector( '.axismundi-activity-feed__more-link' ),
	};
};

const requestPage = async ( context, after ) => {
	const url = new URL( context.endpoint, window.location.href );
	url.searchParams.set( 'actor_uri', context.actorUri );
	url.searchParams.set( 'surface', context.surface );
	url.searchParams.set( 'filter', context.filter );
	url.searchParams.set( 'density', context.density || 'card' );
	url.searchParams.set( 'after', after || '' );
	url.searchParams.set( 'per_page', String( context.perPage ) );
	/*
	 * The nonce is what makes the cookie count.
	 *
	 * WordPress accepts cookie authentication on a REST request only alongside a valid nonce;
	 * without one the request is anonymous even though the browser sent the session. This page is
	 * rendered server-side, so every control on it came back saying "log in to do this" — a feed
	 * whose first page knew who was reading and whose second page did not.
	 */
	const response = await fetch( url, {
		credentials: 'same-origin',
		headers: context.nonce ? { 'X-WP-Nonce': context.nonce } : {},
	} );
	const result = await response.json();
	if ( ! response.ok ) {
		throw new Error( result.message || 'request_failed' );
	}
	return result;
};

/** Parse card HTML in list context, so the parser keeps the rows instead of discarding them. */
const rowsFrom = ( list, html ) => {
	const range = document.createRange();
	range.selectNodeContents( list );
	return range.createContextualFragment( html || '' );
};

store( 'axismundi/actor-feed', {
	actions: {
		/** Open or close the filter popover. */
		toggleFilters() {
			const context = getContext();
			context.isFiltersOpen = ! context.isFiltersOpen;
		},

		/** Apply the reader's switches: persist them, then replace the list from page one. */
		setFilter: withSyncEvent( function* ( event ) {
			const context = getContext();
			const { ref } = getElement();
			const panel = ref.closest( '.axismundi-activity-feed__filters' );
			const replies = panel?.querySelector( 'input[name="replies"]' )?.checked === true;
			const boosts = panel?.querySelector( 'input[name="boosts"]' )?.checked === true;
			writeStored( 'replies', replies );
			writeStored( 'boosts', boosts );
			context.filter = filterKey( replies, boosts );
			context.filterLabel = context.filterLabels[ context.filter ] || context.filterLabel;
			const { list, more } = feedParts( ref );
			if ( ! list ) {
				return;
			}
			context.isPending = true;
			context.error = '';
			try {
				const result = yield requestPage( context, '' );
				// The list is replaced rather than appended to: this is a different list, not more
				// of the same one, and the cursor from the old filter names a position in an
				// ordering that no longer applies.
				list.replaceChildren( rowsFrom( list, result.html ) );
				context.cursor = result.has_more ? result.next_cursor : '';
				if ( more ) {
					more.hidden = ! context.cursor;
				}
			} catch ( error ) {
				context.error =
					error instanceof Error && error.message !== 'request_failed'
						? error.message
						: context.errorFallback;
			} finally {
				context.isPending = false;
			}
		} ),
		loadMore: withSyncEvent( function* ( event ) {
			event.preventDefault();
			const context = getContext();
			if ( context.isPending || ! context.cursor ) {
				return;
			}
			const { ref } = getElement();
			const { list } = feedParts( ref );
			if ( ! list ) {
				return;
			}
			context.isPending = true;
			context.error = '';
			try {
				const result = yield requestPage( context, context.cursor );
				list.appendChild( rowsFrom( list, result.html ) );
				context.cursor = result.has_more ? result.next_cursor : '';
				ref.hidden = ! context.cursor;
				if ( context.cursor ) {
					// Keep the no-script fallback honest: the link always points at the page it
					// would load next.
					const next = new URL( ref.href, window.location.href );
					next.searchParams.set( 'feed_after', context.cursor );
					ref.href = next.toString();
				}
			} catch ( error ) {
				context.error =
					error instanceof Error && error.message !== 'request_failed'
						? error.message
						: context.errorFallback;
			} finally {
				context.isPending = false;
			}
		} ),
	},
	callbacks: {
		/**
		 * Reveal the switches and reconcile them with what this reader chose last time.
		 *
		 * The server rendered the default, so a stored preference that differs means the visible
		 * list is not the one they asked for; page one is fetched again with theirs. That is the
		 * cost of keeping the preference out of the URL, and it is the same brief correction
		 * Mastodon makes.
		 */
		watchFilters() {
			const { ref } = getElement();
			const context = getContext();
			if ( ! ref || ! context.clientOwned ) {
				return;
			}
			ref.hidden = false;
			const defaults = context.defaultFilter;
			const replies = readStored( 'replies', defaults === 'all' || defaults === 'posts-and-replies' );
			const boosts = readStored( 'boosts', defaults === 'all' || defaults === 'posts-and-boosts' );
			const repliesInput = ref.querySelector( 'input[name="replies"]' );
			const boostsInput = ref.querySelector( 'input[name="boosts"]' );
			if ( repliesInput ) {
				repliesInput.checked = replies;
			}
			if ( boostsInput ) {
				boostsInput.checked = boosts;
			}
			const wanted = filterKey( replies, boosts );
			if ( wanted === context.filter ) {
				return;
			}
			// Reuse the one path that applies a filter, so a restored preference and a pressed
			// switch cannot end up meaning different things.
			( repliesInput || boostsInput )?.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		},
		/**
		 * While the popover is open, Escape and a click outside close it.
		 *
		 * This is the same lifecycle the reaction picker uses. A popover that can only be closed
		 * by pressing its own trigger again is a trap for anyone navigating by keyboard, and the
		 * listeners are bound only while it is open so a closed control costs nothing.
		 */
		filtersLifecycle() {
			const { ref } = getElement();
			const context = getContext();
			if ( ! ref || ! context.isFiltersOpen ) {
				return;
			}
			const trigger = ref.querySelector( '.axismundi-activity-feed__filters-trigger' );
			const onDown = withScope( ( event ) => {
				if ( ! ref.contains( event.target ) ) {
					context.isFiltersOpen = false;
				}
			} );
			const onKey = withScope( ( event ) => {
				if ( 'Escape' === event.key ) {
					context.isFiltersOpen = false;
					trigger?.focus();
				}
			} );
			/*
			 * Bound immediately. Deferring to the next frame is the usual guard against the very
			 * click that opened a popover closing it again, but it cannot happen here: the
			 * trigger opens on `click`, and `pointerdown` has already fired by then. The deferral
			 * only created a window in which the listener might never be bound at all — which is
			 * exactly what happened whenever the frame was cancelled first.
			 */
			document.addEventListener( 'pointerdown', onDown );
			document.addEventListener( 'keydown', onKey );
			return () => {
				document.removeEventListener( 'pointerdown', onDown );
				document.removeEventListener( 'keydown', onKey );
			};
		},

		watchFeed() {
			const { ref } = getElement();
			if ( ! ref ) {
				return;
			}
			// One listener on the list catches clicks from every card it holds, whether that card
			// arrived with the document or was appended later. The cards are presentation either
			// way, so there is no second handler for this one to race.
			const listener = ( event ) => {
				handleFeedClick( event );
			};
			ref.addEventListener( 'click', listener );
			return () => ref.removeEventListener( 'click', listener );
		},
	},
} );
