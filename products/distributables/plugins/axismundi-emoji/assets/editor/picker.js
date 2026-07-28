/**
 * Custom emoji picker.
 *
 * Inserts `:shortcode:` as plain text and nothing else. The image a reader eventually
 * sees is a render of a declaration the document carries in its `tag[]`, assembled
 * server-side from whatever shortcodes the text contains at publish time — so there is
 * no state here to keep in sync, and deleting the text deletes the declaration.
 *
 * Unicode emoji are deliberately absent. The operating system already has a good picker
 * for those (`Win + .`, the macOS palette, every mobile keyboard), and Unicode never
 * enters `tag[]` because a character needs no declaration to be understood.
 *
 * No JSX, no build — plain wp.element.createElement, matching the other editor assets.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var C = wp.components;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;

	var RECENT_KEY = 'axismundiEmojiRecent';
	var RECENT_MAX = 32;

	// Assigned at the bottom, where the component it references exists; `useAnchor` needs
	// the same object the format was registered with.
	var FORMAT_SETTINGS;

	/**
	 * Characters that may not sit next to a shortcode.
	 *
	 * This is the server tokenizer's set, not an approximation of it. `_` is legal inside
	 * a shortcode and therefore cannot also be a boundary; a picker using a simplified
	 * `[A-Za-z0-9:]` would decline to add a space after `foo_`, and the tokenizer would
	 * then decline to declare what the picker had just inserted — no error, no image,
	 * nothing to see. A line ending, a bracket, a full stop, and a Korean syllable are all
	 * boundaries, so no separator is added for those.
	 */
	var ADJACENT = /[A-Za-z0-9_:]/;

	/**
	 * The text to insert, padded only where the neighbours would break the boundary rule.
	 *
	 * Exported so every surface shares one answer. The block editor inserts through
	 * `wp.richText.insert()` and a profile field through `selectionStart`; the mechanics
	 * differ, the decision must not.
	 *
	 * @param {string} shortcode `:name:`.
	 * @param {string} before    Character immediately before the caret, '' at the start.
	 * @param {string} after     Character immediately after the caret, '' at the end.
	 * @return {string} Text to insert verbatim.
	 */
	function spacedInsertion( shortcode, before, after ) {
		var lead = before && ADJACENT.test( before ) ? ' ' : '';
		var tail = after && ADJACENT.test( after ) ? ' ' : '';
		return lead + shortcode + tail;
	}

	/** @return {string[]} Recently inserted shortcodes, newest first. */
	function readRecent() {
		try {
			var stored = window.localStorage.getItem( RECENT_KEY );
			var parsed = stored ? JSON.parse( stored ) : [];
			return Array.isArray( parsed ) ? parsed.filter( function ( s ) {
				return 'string' === typeof s;
			} ).slice( 0, RECENT_MAX ) : [];
		} catch ( e ) {
			// Private browsing, a full quota, or a corrupt value. Recents are a convenience;
			// losing them must never stop the picker from opening.
			return [];
		}
	}

	/** @param {string} shortcode Just-inserted shortcode. */
	function rememberRecent( shortcode ) {
		try {
			var next = [ shortcode ].concat( readRecent().filter( function ( s ) {
				return s !== shortcode;
			} ) ).slice( 0, RECENT_MAX );
			window.localStorage.setItem( RECENT_KEY, JSON.stringify( next ) );
		} catch ( e ) {
			// Ignored for the same reason.
		}
	}

	/**
	 * One emoji tile.
	 *
	 * The accessible name is the shortcode, because that is what the button inserts and
	 * what the author will see in their text. The image is decorative here.
	 */
	function Tile( props ) {
		var emoji = props.emoji;
		return el(
			'button',
			{
				type: 'button',
				className: 'axismundi-emoji-picker__tile',
				'aria-label': emoji.shortcode,
				title: emoji.aliases && emoji.aliases.length
					? emoji.shortcode + ' — ' + emoji.aliases.join( ', ' )
					: emoji.shortcode,
				onClick: function () {
					props.onSelect( emoji );
				}
			},
			el( 'img', { src: emoji.url, alt: '', width: 28, height: 28, draggable: false } )
		);
	}

	/**
	 * The picker body.
	 *
	 * The catalogue is searched on the server and never localized into the page: a site
	 * with a few hundred emoji would otherwise ship all of them into every editor load
	 * for a list the author filters down to three.
	 */
	function PickerBody( props ) {
		var search = useState( '' );
		var term = search[ 0 ];
		var setTerm = search[ 1 ];
		var results = useState( [] );
		var items = results[ 0 ];
		var setItems = results[ 1 ];
		var busy = useState( true );
		var loading = busy[ 0 ];
		var setLoading = busy[ 1 ];
		var recent = useState( readRecent() );
		var recentList = recent[ 0 ];
		var setRecentList = recent[ 1 ];
		var searchRef = useRef( null );

		useEffect( function () {
			if ( searchRef.current ) {
				searchRef.current.focus();
			}
		}, [] );

		useEffect( function () {
			var cancelled = false;
			// Debounced: a fetch per keystroke would queue requests that arrive out of order,
			// and the last response to land — not the last one asked for — would win.
			var timer = window.setTimeout( function () {
				setLoading( true );
				wp.apiFetch( {
					path: wp.url.addQueryArgs( '/axismundi/v1/emoji/local', {
						search: term,
						// Every surface this picker serves publishes its text, so a local-only
						// emoji here would produce a message that reads correctly at home and
						// as a bare word everywhere else.
						federated: true,
						per_page: 100
					} )
				} ).then( function ( response ) {
					if ( ! cancelled ) {
						setItems( Array.isArray( response ) ? response : [] );
						setLoading( false );
					}
				} ).catch( function () {
					if ( ! cancelled ) {
						setItems( [] );
						setLoading( false );
					}
				} );
			}, term ? 200 : 0 );
			return function () {
				cancelled = true;
				window.clearTimeout( timer );
			};
		}, [ term ] );

		function choose( emoji ) {
			rememberRecent( emoji.shortcode );
			setRecentList( readRecent() );
			props.onSelect( emoji.shortcode );
			// Deliberately stays open. Writing a line with three emoji in it should not mean
			// opening the picker three times; Misskey works this way and it reads better.
		}

		var byCategory = {};
		items.forEach( function ( emoji ) {
			var key = emoji.category || __( 'Uncategorized', 'axismundi-emoji' );
			byCategory[ key ] = byCategory[ key ] || [];
			byCategory[ key ].push( emoji );
		} );
		var categories = Object.keys( byCategory ).sort();

		var recentTiles = recentList
			.map( function ( shortcode ) {
				var match = null;
				items.forEach( function ( emoji ) {
					if ( emoji.shortcode === shortcode ) {
						match = emoji;
					}
				} );
				return match;
			} )
			.filter( Boolean )
			.slice( 0, 16 );

		return el(
			'div',
			{ className: 'axismundi-emoji-picker' },
			el( C.SearchControl, {
				ref: searchRef,
				value: term,
				onChange: setTerm,
				label: __( 'Search custom emoji', 'axismundi-emoji' ),
				placeholder: __( 'Search by name or alias', 'axismundi-emoji' ),
				__nextHasNoMarginBottom: true
			} ),
			loading
				? el( 'p', { className: 'axismundi-emoji-picker__status' }, el( C.Spinner ) )
				: null,
			! loading && ! items.length
				? el(
					'p',
					{ className: 'axismundi-emoji-picker__status' },
					term
						? __( 'No custom emoji match that search.', 'axismundi-emoji' )
						: __( 'This site has no custom emoji yet.', 'axismundi-emoji' )
				)
				: null,
			! loading && ! term && recentTiles.length
				? el(
					'div',
					{ className: 'axismundi-emoji-picker__group' },
					el( 'h3', { className: 'axismundi-emoji-picker__heading' }, __( 'Recent', 'axismundi-emoji' ) ),
					el(
						'div',
						{ className: 'axismundi-emoji-picker__grid' },
						recentTiles.map( function ( emoji ) {
							return el( Tile, { key: 'recent-' + emoji.id, emoji: emoji, onSelect: choose } );
						} )
					)
				)
				: null,
			categories.map( function ( category ) {
				return el(
					'div',
					{ className: 'axismundi-emoji-picker__group', key: category },
					el( 'h3', { className: 'axismundi-emoji-picker__heading' }, category ),
					el(
						'div',
						{ className: 'axismundi-emoji-picker__grid' },
						byCategory[ category ].map( function ( emoji ) {
							return el( Tile, { key: emoji.id, emoji: emoji, onSelect: choose } );
						} )
					)
				);
			} )
		);
	}

	/**
	 * The toolbar button.
	 *
	 * Registered as a format type because that is the public seam core exposes for the
	 * inline selection toolbar — the same row as bold, italic, and link. The format is
	 * never applied: selecting an emoji inserts text and adds no markup, so nothing of
	 * this registration reaches the saved document.
	 */
	function EmojiToolbarButton( props ) {
		var open = useState( false );
		var isOpen = open[ 0 ];
		var setOpen = open[ 1 ];

		/*
		 * Anchored to the text being edited, the way Highlight, Language, and Math are.
		 *
		 * A `Popover` with no anchor falls back to the document and lands in a corner of the
		 * viewport, far from the toolbar that opened it. `useAnchor` is the seam core uses
		 * for exactly this: it returns a virtual element tracking the current range inside
		 * `contentRef`, so the popover follows the caret and the block, including while the
		 * editor scrolls. Called unconditionally — it is a hook, and the popover's open state
		 * must not change how many hooks run.
		 */
		var anchor = wp.richText.useAnchor( {
			editableContentElement: props.contentRef ? props.contentRef.current : null,
			settings: FORMAT_SETTINGS
		} );

		function insert( shortcode ) {
			var value = props.value;
			// `end` is where a collapsed caret sits; with a selection, replacing it is what
			// the author asked for, so the boundary is judged against what will remain.
			var before = value.text.slice( 0, value.start ).slice( -1 );
			var after = value.text.slice( value.end ).charAt( 0 );
			props.onChange(
				wp.richText.insert( value, spacedInsertion( shortcode, before, after ) )
			);
		}

		return el(
			wp.element.Fragment,
			null,
			el( wp.blockEditor.RichTextToolbarButton, {
				icon: 'smiley',
				title: __( 'Custom emoji', 'axismundi-emoji' ),
				isActive: isOpen,
				onClick: function () {
					setOpen( ! isOpen );
				}
			} ),
			// Rendered only while open. `edit()` runs for every RichText instance on the
			// screen, so building the picker eagerly would mount it once per paragraph and
			// fetch the catalogue that many times.
			isOpen
				? el(
					C.Popover,
					{
						anchor: anchor,
						placement: 'bottom-start',
						// Flips above the selection when there is no room below, so a caret near
						// the foot of the window does not open a picker off-screen.
						flip: true,
						shift: true,
						focusOnMount: 'firstElement',
						className: 'axismundi-emoji-picker__popover',
						onClose: function () {
							setOpen( false );
						},
						onFocusOutside: function () {
							setOpen( false );
						}
					},
					el( PickerBody, { onSelect: insert } )
				)
				: null
		);
	}

	/*
	 * One settings object, shared by the registration and by `useAnchor`.
	 *
	 * `useAnchor` reads `tagName` and `className` from it to find the format's own range
	 * when the format is applied. Ours never is — selection inserts text and adds no
	 * markup — so it resolves to the current selection instead, which is the behaviour we
	 * want. Passing a second, differently-shaped object here would be a silent way for the
	 * two to disagree later.
	 */
	FORMAT_SETTINGS = {
		title: __( 'Custom emoji', 'axismundi-emoji' ),
		tagName: 'span',
		className: 'axismundi-emoji-insert',
		edit: EmojiToolbarButton
	};

	wp.richText.registerFormatType( 'axismundi-emoji/insert', FORMAT_SETTINGS );

	// Exposed for the profile fields, which insert into a plain textarea and must reach the
	// same conclusion about spacing as the block editor does.
	window.axismundiEmojiPicker = {
		spacedInsertion: spacedInsertion,
		readRecent: readRecent,
		rememberRecent: rememberRecent
	};
} )( window.wp );
