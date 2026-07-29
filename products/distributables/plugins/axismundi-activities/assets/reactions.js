/**
 * One store for two blocks.
 *
 * The picker button sits among the other actions and the chips sit below them, so they are
 * separate blocks with separate contexts — but a pick made in one has to appear in the
 * other. The authoritative summary therefore lives in shared state keyed by object URI,
 * and each bar copies its own object's entry into its context when it changes.
 *
 * Chips stay in context rather than being read straight from state so that the server can
 * render them: `data-wp-each` over a context array is processed server-side, which is what
 * makes the first paint correct without JavaScript having run.
 *
 * Every mutation replaces the whole summary. The endpoint returns it in full for that
 * reason: adding a reaction can create a chip, removing one can delete a chip, and a
 * client that tried to patch counts locally would have to reimplement the aggregate and
 * would drift from it.
 */
import { getContext, getElement, store, withScope } from '@wordpress/interactivity';

const NAMESPACE = 'axismundi/reactions';
/*
 * Skin-tone variants are left out of the grid, not out of the product.
 *
 * The RGI set spells every tone of every gesture, which is why `People & Body` is 2,418
 * entries against 388 base ones — two thirds of the whole catalogue is tone permutations.
 * Building them all is what froze the tab, and no picker shows them that way: they offer
 * the base emoji and a tone control beside it. Until that control exists the grid shows
 * the base, and a toned reaction that arrives from a peer still renders and still counts,
 * because nothing about receiving them changed.
 */
const SKIN_TONE = /[\u{1F3FB}-\u{1F3FF}]/u;
const RECENT_KEY = 'axismundi.reactions.recent';
const RECENT_MAX = 24;


/**
 * Recently used emoji live in the browser, not the ledger.
 *
 * What somebody reached for last is a fact about this person at this keyboard, and putting
 * it on the server would make a per-Actor history out of it and send it back on every page.
 * The list is small, disposable, and nobody else's business.
 */
function readRecent() {
	try {
		const stored = JSON.parse( window.localStorage.getItem( RECENT_KEY ) || '[]' );
		return Array.isArray( stored ) ? stored.slice( 0, RECENT_MAX ) : [];
	} catch ( error ) {
		return [];
	}
}

function writeRecent( entry ) {
	try {
		const kept = readRecent().filter( ( item ) => item.key !== entry.key );
		kept.unshift( entry );
		const next = kept.slice( 0, RECENT_MAX );
		window.localStorage.setItem( RECENT_KEY, JSON.stringify( next ) );
		return next;
	} catch ( error ) {
		return readRecent();
	}
}

/**
 * The reaction key the server will give this content, computed here so the optimistic chip
 * carries its final identity from the first frame.
 *
 * It has to agree with `axismundi_act_normalize_reaction()` exactly: variation selectors are
 * stripped before the code points are listed, and a shortcode is lower-cased and qualified
 * with this site's authority. A key that disagreed would show a second chip for one
 * reaction until the response arrived and merged them.
 */
function reactionKey( content ) {
	if ( content.startsWith( ':' ) && content.endsWith( ':' ) ) {
		return `custom:${ state.localAuthority }:${ content.slice( 1, -1 ).toLowerCase() }`;
	}
	const points = [ ...content.replace( /[︎️]/g, '' ) ]
		.map( ( ch ) => 'U+' + ch.codePointAt( 0 ).toString( 16 ).toUpperCase().padStart( 4, '0' ) );
	return `unicode:${ points.join( ' ' ) }`;
}

/**
 * The summary as it will look once this reaction lands, applied before the request goes out.
 *
 * Reactions are the one interaction where waiting for a round trip is felt: the reader has
 * already decided, and the server is only going to agree. Ordering is left alone — the
 * response re-sorts by count, and shuffling chips under the cursor mid-click would be worse
 * than a chip briefly out of order.
 */
function withReaction( summary, key, presentation ) {
	let found = false;
	const chips = summary.chips.map( ( chip ) => {
		if ( chip.key !== key ) {
			return chip;
		}
		found = true;
		return { ...chip, mine: true, count: chip.mine ? chip.count : chip.count + 1 };
	} );
	if ( ! found ) {
		chips.push( {
			key,
			kind: presentation.kind,
			label: presentation.label,
			image: null,
			imageUrl: presentation.imageUrl || '',
			hasImage: Boolean( presentation.imageUrl ),
			isGlyph: presentation.kind === 'unicode',
			count: 1,
			mine: true,
			joinable: true,
		} );
	}
	return { ...summary, chips, mine: summary.mine.includes( key ) ? summary.mine : [ ...summary.mine, key ] };
}

/** The same, for withdrawing: the chip goes when its last person leaves it. */
function withoutReaction( summary, key ) {
	const chips = summary.chips
		.map( ( chip ) => ( chip.key === key ? { ...chip, mine: false, count: Math.max( 0, chip.count - 1 ) } : chip ) )
		.filter( ( chip ) => chip.count > 0 );
	return { ...summary, chips, mine: summary.mine.filter( ( entry ) => entry !== key ) };
}

/**
 * Add the presentation-only fields a reaction-bar template needs.
 *
 * The REST aggregate intentionally carries the canonical `image` descriptor; the block
 * context additionally needs a boolean for the HTML `hidden` attribute and a flattened URL
 * for `data-wp-bind--src`. `syncFromStore()` runs during hydration too, so replacing the
 * server-decorated context with raw REST-shaped chips made a renderable custom emoji fall
 * back to its shortcode after JavaScript loaded. The same rule applies to a newly optimistic
 * chip: a response that has not decorated `image` yet must not erase its known local URL.
 *
 * Existing `joinable` answers are preserved. A custom chip newly observed from the server is
 * conservative by default: only an optimistic local chip may become joinable without the
 * server-rendered declaration check that established it.
 */
function chipsForView( summary, previous = [] ) {
	const priorByKey = new Map( previous.map( ( chip ) => [ chip.key, chip ] ) );
	return ( summary.chips || [] ).map( ( chip ) => {
		const prior = priorByKey.get( chip.key );
		/*
		 * Three sources, in order of authority: what the server decorated, what this chip is
		 * already carrying, and what the chip of the same key carried a moment ago.
		 *
		 * The middle one is the optimistic case and it was missing. A chip built by
		 * `withReaction()` has `image: null` and its URL in `imageUrl`, and being brand new it
		 * has no prior — so the first two rules both missed and it rendered as its shortcode
		 * until the server answered. That is the flash: not a slow image, an erased URL.
		 */
		const imageUrl = ( chip.image && chip.image.url ) || chip.imageUrl || prior?.imageUrl || '';
		return {
			...chip,
			imageUrl,
			hasImage: Boolean( imageUrl ),
			isGlyph: chip.kind === 'unicode',
			joinable: typeof prior?.joinable === 'boolean' ? prior.joinable : chip.kind !== 'custom',
		};
	} );
}

/**
 * One shape for everything the picker offers.
 *
 * A custom emoji and a Unicode one are chosen the same way and differ only in what is sent,
 * so the grid should not have to know which it is holding. `shortcode` is what goes on the
 * wire for a custom one; `glyph` is what goes on the wire for a Unicode one; whichever is
 * empty tells the template which half to draw.
 */
function pickerItemFromCustom( row ) {
	return {
		key: `custom:${ row.name }`,
		shortcode: row.shortcode,
		glyph: '',
		url: row.url,
		outbound: row.outbound,
	};
}

function pickerItemFromUnicode( entry ) {
	return {
		key: entry.key,
		shortcode: entry.name,
		glyph: entry.emoji,
		url: '',
		outbound: true,
	};
}

/**
 * Anchor the picker to the trigger's trailing corner, clamped inside the viewport.
 *
 * `position: fixed`, so it has to be placed every time it opens and again whenever the
 * page moves under it. Flips above the trigger when there is not enough room below, which
 * is the ordinary case for a card near the bottom of a feed.
 */
function positionPicker( trigger, picker ) {
	const rect = trigger.getBoundingClientRect();
	const box = picker.getBoundingClientRect();
	const gap = 8;
	const edge = 16;
	const opensUp = rect.bottom + gap + box.height > window.innerHeight;
	const left = Math.max( edge, Math.min( rect.right - box.width, window.innerWidth - box.width - edge ) );
	const top = opensUp
		? Math.max( edge, rect.top - gap - box.height )
		: Math.min( rect.bottom + gap, window.innerHeight - box.height - edge );
	picker.style.left = `${ left }px`;
	picker.style.top = `${ top }px`;
	picker.dataset.placement = opensUp ? 'top' : 'bottom';
}

/** Fetch one custom-emojis page. The unfiltered page is the local category source. */
function* loadCustomCatalogue( search = '' ) {
	state.isSearching = true;
	try {
		const url = state.catalogueEndpoint + ( search ? '&search=' + encodeURIComponent( search ) : '' );
		const response = yield fetch( url, { credentials: 'same-origin', headers: { 'X-WP-Nonce': state.nonce } } );
		if ( ! response.ok ) {
			throw new Error( 'catalogue_failed' );
		}
		// The PHP search helper has `{ items, total }` internally, but the REST boundary
		// deliberately sends the items array and pagination headers. The picker consumes
		// that public shape, not the helper's private envelope.
		const items = yield response.json();
		if ( '' === search ) {
			state.catalogue = Array.isArray( items ) ? items : [];
			state.catalogueLoaded = true;
		} else {
			state.customSearch = Array.isArray( items ) ? items : [];
		}
	} catch ( error ) {
		state.error = state.i18n.catalogueError;
	} finally {
		state.isSearching = false;
	}
}

/** Fetch the full index only for a text search; browsing uses one group file at a time. */
function* loadUnicodeIndex() {
	if ( state.unicodeIndexLoaded || ! state.unicodeIndexSource ) {
		return;
	}
	const response = yield fetch( state.unicodeIndexSource, { credentials: 'omit' } );
	if ( ! response.ok ) {
		throw new Error( 'unicode_failed' );
	}
	const data = yield response.json();
	state.unicode = Array.isArray( data.items ) ? data.items : [];
	state.unicodeIndexLoaded = true;
}

/** Fetch and normalize only the Unicode group the reader opened. */
function* loadUnicodeGroup( group ) {
	if ( state.unicodeLoadedGroups.includes( group ) ) {
		return;
	}
	const url = state.unicodeGroupSources[ group ];
	if ( ! url ) {
		throw new Error( 'unicode_failed' );
	}
	const response = yield fetch( url, { credentials: 'omit' } );
	if ( ! response.ok ) {
		throw new Error( 'unicode_failed' );
	}
	const data = yield response.json();
	const items = Array.isArray( data.items )
		? data.items.filter( ( entry ) => ! SKIN_TONE.test( entry.emoji ) ).map( pickerItemFromUnicode )
		: [];
	state.unicodeByGroup = { ...state.unicodeByGroup, [ group ]: items };
	state.unicodeLoadedGroups = [ ...state.unicodeLoadedGroups, group ];
}

const { state, actions } = store( NAMESPACE, {
	state: {
		get isOpen() {
			return state.openFor === getContext().objectUri;
		},
		get isPickerHidden() {
			return ! state.isOpen;
		},
		get isBusy() {
			return state.pendingFor === getContext().objectUri;
		},
		/** A chip the reader cannot join and has not joined is a label, not a control. */
		get isChipDisabled() {
			const { item } = getContext();
			return state.isBusy || ( ! item.mine && ! item.joinable );
		},
		get chipLabel() {
			const { item } = getContext();
			return item.mine ? state.i18n.removeReaction : state.i18n.addSame;
		},

		// -- The jump strip ---------------------------------------------------------------

		/** Reflects where the scroll position is, which is why it is not a tab. */
		/*
		 * Three roles, three attributes, and that is the whole point.
		 *
		 * They all used to be `data-section`, which quietly broke the strip: a jump looked up
		 * `[data-section="custom"]`, and because the strip is above the sections in document
		 * order it found *the jump button itself* and scrolled an element already on screen
		 * into view — nothing moved. `data-jump` names a destination, `data-toggle` names
		 * something that folds, and `data-section` names the destination itself.
		 */
		get isSectionActive() {
			return getElement().attributes[ 'data-jump' ] === state.activeSection;
		},
		get isSectionExpanded() {
			return state.expandedSections.includes( getElement().attributes[ 'data-toggle' ] );
		},

		// -- Searching --------------------------------------------------------------------

		get isFiltering() {
			return state.search.trim() !== '';
		},
		/*
		 * One list, custom emoji first. A reader who typed a word wants matches, not the
		 * shelf they came from, and this site's own emoji are the ones they can only find
		 * here — a Unicode emoji they could also have typed.
		 */
		get searchResults() {
			const term = state.search.trim().toLowerCase();
			if ( '' === term ) {
				return [];
			}
			const custom = state.customSearch.map( pickerItemFromCustom );
			const unicode = state.unicode
				.filter( ( entry ) => {
					if ( SKIN_TONE.test( entry.emoji ) ) {
						return false;
					}
					// `emoji-test.txt` keeps the display name's case ("South Korea") while
					// the query is folded. Search the whole keyword phrase too, so a useful
					// multi-word query does not depend on it being one generated keyword.
					const index = `${ entry.name } ${ entry.keywords.join( ' ' ) }`.toLowerCase();
					return index.includes( term ) || entry.emoji === term;
				} )
				.map( pickerItemFromUnicode );
			// The custom endpoint already filtered server-side; Unicode is filtered here
			// because the whole set is in memory and a round trip would be slower than the
			// scan.
			return [ ...custom, ...unicode.slice( 0, 200 ) ];
		},
		get isSearchEmpty() {
			return state.isFiltering && state.searchResults.length === 0 && ! state.isSearching;
		},

		// -- Unicode, grouped as `emoji-test.txt` groups them --------------------------

		/*
			 * Every heading exists for category navigation, but a collapsed group contributes no
			 * tiles at all. This is a rendering boundary, not merely `display:none` over a
			 * fully-built grid.
			 */
		get unicodeSections() {
			return state.unicodeGroups.map( ( group ) => {
				const id = `uni:${ group }`;
				const entries = state.unicodeByGroup[ group ] || [];
				return {
					id,
					label: group,
					items: state.expandedSections.includes( id ) ? entries : [],
				};
			} );
		},

		// -- Custom emoji, grouped ------------------------------------------------------

		/*
		 * Grouped here rather than server-side because the catalogue arrives from a search
		 * endpoint that returns a flat page. Insertion order is preserved, and the endpoint
		 * already sorts by category then name, so the groups come out in its order.
		 */
		get customGroups() {
			// The outer section is always open; only its categories fold, and only because a
			// site with many custom emoji has the same DOM problem the Unicode set does.
			const groups = [];
			const index = new Map();
			for ( const item of state.catalogue ) {
				const category = item.category || '';
				if ( ! index.has( category ) ) {
					const group = { id: `custom:${ category }`, category, label: category || state.i18n.uncategorized, items: [] };
					index.set( category, group );
					groups.push( group );
				}
				if ( state.expandedSections.includes( `custom:${ category }` ) ) {
					index.get( category ).items.push( pickerItemFromCustom( item ) );
				}
			}
			return groups;
		},
		get isCatalogueEmpty() {
			return state.catalogueLoaded && state.catalogue.length === 0;
		},

		// -- Recent ---------------------------------------------------------------------

		get recentItems() {
			// Never folded: a short list the reader came for, and the first thing they look at.
			return state.recent;
		},
		get hasRecent() {
			return state.recent.length > 0;
		},

		/*
		 * An emoji this site withholds from publication is offered rather than hidden — it
		 * is usable here, and a reader has already seen it in local content. What it cannot
		 * do is travel, so on an object belonging to another server it is shown disabled
		 * with the reason, instead of being posted and refused.
		 */
		get isEmojiBlocked() {
			const { item, objectUri } = getContext();
			// A Unicode entry has no `outbound` flag and is never withheld.
			if ( ! item || typeof item.outbound === 'undefined' ) {
				return false;
			}
			return ! item.outbound && ! ( state.summaries[ objectUri ] || {} ).is_local;
		},
		get emojiLabel() {
			const { item } = getContext();
			const name = item.shortcode || item.glyph || '';
			return state.isEmojiBlocked ? `${ name } — ${ state.i18n.localOnlyBlocked }` : name;
		},
	},

	actions: {
		*togglePicker() {
			const { objectUri } = getContext();
			state.openFor = state.openFor === objectUri ? '' : objectUri;
			state.error = '';
			if ( ! state.openFor ) {
				return;
			}
			state.recent = readRecent();
			state.activeSection = 'recent';
			/*
			 * The site's own emoji load with the picker, because the Custom section no longer
			 * folds and there is nothing left to trigger the fetch. They are also the reason
			 * most readers opened this: a handful of images, not four thousand.
			 *
			 * Their categories start open for the same reason. Folding exists to keep the
			 * Unicode set out of the DOM; a site's own set is small enough to show, and a
			 * reader who has to expand a category to find the emoji they came for has been
			 * given a click for nothing.
			 */
			if ( ! state.catalogueLoaded ) {
				yield* loadCustomCatalogue();
				const categories = [ ...new Set( state.catalogue.map( ( item ) => `custom:${ item.category || '' }` ) ) ];
				state.expandedSections = [ ...new Set( [ ...state.expandedSections, ...categories ] ) ];
			}
		},

		/** Expand or collapse one category. Fetching is tied to first expansion. */
		*toggleSection() {
			const section = getElement().attributes[ 'data-toggle' ];
			if ( state.expandedSections.includes( section ) ) {
				state.expandedSections = state.expandedSections.filter( ( id ) => id !== section );
				return;
			}
			state.error = '';
			try {
				if ( 'custom' === section && ! state.catalogueLoaded ) {
					yield* loadCustomCatalogue();
				} else if ( section.startsWith( 'uni:' ) ) {
					yield* loadUnicodeGroup( section.slice( 4 ) );
				}
				state.expandedSections = [ ...state.expandedSections, section ];
			} catch ( error ) {
				state.error = state.i18n.catalogueError;
			}
		},

		/** Move the scroll position. The strip is navigation, so it never hides anything. */
		*jumpTo() {
			const section = getElement().attributes[ 'data-jump' ];
			const { ref } = getElement();
			const picker = ref.closest( '.axismundi-reaction-button__picker' );
			const scroll = picker.querySelector( '.axismundi-reaction-picker__scroll' );
			/*
			 * A folded destination is an empty heading, so jumping to one lands on a strip of
			 * nothing. Open it first, and wait for it: `scrollIntoView` before the grid exists
			 * measures a section that is about to grow and leaves the reader above it.
			 */
			if ( ! state.expandedSections.includes( section ) ) {
				state.error = '';
				try {
					if ( section.startsWith( 'uni:' ) ) {
						yield* loadUnicodeGroup( section.slice( 4 ) );
					}
					state.expandedSections = [ ...state.expandedSections, section ];
				} catch ( error ) {
					state.error = state.i18n.catalogueError;
				}
			}
			state.activeSection = section;
			// Scoped to the scroll region: the strip carries the same names, and searching it
			// first is what made every jump a no-op.
			const target = scroll.querySelector( `[data-section="${ CSS.escape( section ) }"]` );
			if ( target ) {
				target.scrollIntoView( { block: 'start', behavior: 'smooth' } );
			}
		},

		/**
		 * Follow the scroll and say where we are. It never expands a category: scrolling
		 * should not unexpectedly create a few hundred controls below the reader's pointer.
		 *
		 * The heading nearest the top of the scroll box is the section the reader is in,
		 * which is the same rule the Windows panel and Mastodon follow. Anything within one
		 * screen below is revealed early so it is already drawn when it arrives.
		 */
		trackScroll() {
			const { ref } = getElement();
			const top = ref.getBoundingClientRect().top;
			let current = state.activeSection;
			for ( const section of ref.querySelectorAll( '[data-section]' ) ) {
				const id = section.getAttribute( 'data-section' );
				const offset = section.getBoundingClientRect().top - top;
				if ( offset <= 8 ) {
					current = id;
				}
			}
			state.activeSection = current;
		},

		/*
		 * Debounced, because the catalogue is a network round trip and a search box fires on
		 * every keystroke. The timer lives on state so a second keystroke cancels the first
		 * rather than racing it. Searching also moves to the tab the results are in —
		 * typing while looking at Recent otherwise filters a list nobody can see.
		 */
		search( event ) {
			const term = event.target.value;
			state.search = term;
			// Searching replaces the page, so the strip's position is meaningless until it ends.
			state.activeSection = 'recent';
			clearTimeout( state.searchTimer );
			state.searchTimer = setTimeout(
				withScope( function* () {
					state.isSearching = true;
					try {
						yield* loadCustomCatalogue( term );
						yield* loadUnicodeIndex();
					} catch ( error ) {
						state.error = state.i18n.catalogueError;
					} finally {
						state.isSearching = false;
					}
				} ),
				250
			);
		},

		/**
		 * One handler for both kinds, because the grid holds one kind of thing.
		 *
		 * What differs is only the wire value: a custom emoji sends its `:shortcode:` and a
		 * Unicode one sends its grapheme. Everything after that — the key, the optimistic
		 * chip, the recent list — follows from which of the two the item carries.
		 */
		*pick() {
			const { item, objectUri } = getContext();
			if ( state.isEmojiBlocked ) {
				return;
			}
			const custom = item.glyph === '';
			const content = custom ? item.shortcode : item.glyph;
			const key = reactionKey( content );
			yield* actions.mutate(
				objectUri,
				'POST',
				{ content },
				withReaction( state.summaries[ objectUri ], key, {
					kind: custom ? 'custom' : 'unicode',
					label: content,
					imageUrl: item.url,
				} ),
				() => {
					state.recent = writeRecent( { ...item, key } );
					state.openFor = '';
				}
			);
		},

		/**
		 * An existing chip: leave it if it is mine, join it if I may.
		 *
		 * Deliberately does not close anything. Withdrawing a reaction happens in the bar,
		 * where no popover is open, and joining one is a single click a reader may want to
		 * repeat on the chip next to it.
		 */
		*toggleChip() {
			const { item, objectUri } = getContext();
			if ( state.isChipDisabled ) {
				return;
			}
			const summary = state.summaries[ objectUri ];
			yield* ( item.mine
				? actions.mutate( objectUri, 'DELETE', { reaction_key: item.key }, withoutReaction( summary, item.key ) )
				: actions.mutate( objectUri, 'POST', { content: item.label }, withReaction( summary, item.key, item ) ) );
		},

		/**
		 * Show it, send it, then let the server have the last word.
		 *
		 * The optimistic summary goes in before the request so the chip moves under the
		 * reader's finger. The response replaces it wholesale rather than being merged: only
		 * the ledger knows how many *other* people are on that chip, and a client that tried
		 * to keep its own count would drift the moment somebody else reacted.
		 *
		 * On failure the snapshot goes back. A reaction that silently stayed on screen after
		 * the server refused it would be a lie the reader acts on.
		 *
		 * `onApplied` runs the moment the optimistic state is in, not when the server answers.
		 * Closing the popover on the response meant it hung around for the whole round trip
		 * after the reader had visibly finished — the chip was already on screen. A refusal
		 * does not reopen it either: the reaction quietly rolls back and the action row's
		 * live region says why, which is proportionate for something that should not happen.
		 *
		 * It is a callback rather than a return value because the value a `yield*`-delegated
		 * generator returns is not handed back reliably here — the caller resumed with a
		 * falsy result while this generator was still completing, which is what left the
		 * popover open in the first place.
		 */
		*mutate( objectUri, method, body, optimistic, onApplied ) {
			if ( ! state.canReact || state.pendingFor ) {
				return;
			}
			const snapshot = state.summaries[ objectUri ];
			state.pendingFor = objectUri;
			state.error = '';
			if ( optimistic ) {
				state.summaries = { ...state.summaries, [ objectUri ]: optimistic };
			}
			if ( onApplied ) {
				onApplied();
			}
			try {
				const response = yield fetch( state.endpoint, {
					method,
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': state.nonce },
					body: JSON.stringify( { object_uri: objectUri, ...body } ),
				} );
				const result = yield response.json();
				if ( ! response.ok ) {
					throw new Error( result.message || 'request_failed' );
				}
				// Keyed by URI so every block showing this object updates, including the bar
				// that did not initiate the change.
				state.summaries = { ...state.summaries, [ objectUri ]: result };
			} catch ( error ) {
				state.summaries = { ...state.summaries, [ objectUri ]: snapshot };
				state.error = error instanceof Error && error.message !== 'request_failed' ? error.message : state.i18n.mutationError;
			} finally {
				state.pendingFor = '';
			}
		},
	},

	callbacks: {
		/** Adopt the authoritative summary for this bar's object once one arrives. */
		syncFromStore() {
			const context = getContext();
			const summary = state.summaries[ context.objectUri ];
			if ( summary ) {
				context.chips = chipsForView( summary, context.chips || [] );
			}
		},

		/**
		 * Everything the popover owns while it is open, and nothing while it is closed.
		 *
		 * Dismissal has to be here rather than on the trigger: a popover that can only be
		 * closed by its own button is a trap on touch, where there is no Escape key. The
		 * listeners are attached a frame late so the click that opened it does not
		 * immediately close it again, and are torn down with the popover so a page holding
		 * many cards is not carrying a document listener for each one.
		 */
		pickerLifecycle() {
			const { ref } = getElement();
			const { objectUri } = getContext();
			if ( state.openFor !== objectUri ) {
				return;
			}
			const trigger = ref.querySelector( '.axismundi-reaction-button__trigger' );
			const picker = ref.querySelector( '.axismundi-reaction-button__picker' );
			if ( ! trigger || ! picker ) {
				return;
			}
			const place = withScope( () => positionPicker( trigger, picker ) );
			place();

			const onDown = withScope( ( event ) => {
				if ( ! ref.contains( event.target ) ) {
					state.openFor = '';
				}
			} );
			const onKey = withScope( ( event ) => {
				if ( event.key === 'Escape' ) {
					state.openFor = '';
					trigger.focus();
				}
			} );
			let frame = 0;
			frame = window.requestAnimationFrame( () => {
				document.addEventListener( 'pointerdown', onDown );
			} );
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
