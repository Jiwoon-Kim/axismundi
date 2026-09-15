/**
 * Emoji picker for editing surfaces: custom emoji and Unicode emoji on one scrolling page.
 *
 * Custom emoji insert `:shortcode:` as plain text and nothing else. The image a reader
 * eventually sees is a render of a declaration the document carries in its `tag[]`,
 * assembled server-side from whatever shortcodes the text contains at publish time — so
 * there is no state here to keep in sync, and deleting the text deletes the declaration.
 *
 * Unicode emoji insert the grapheme itself, also as plain text. They come from the RGI
 * catalogue this plugin owns: one static file per Unicode group, fetched when that group is
 * first opened, and the REST route for search. A character needs no declaration to be
 * understood, so Unicode never enters `tag[]`.
 *
 * The layout follows the reaction picker in Axismundi Activities, deliberately: a search
 * field, a strip of category jumps, and one scrolling page of collapsible sections. Every
 * emoji picker a reader already knows works this way, and an author who reacts on the front
 * end should find the same shape when writing. Only the icons differ: the front end uses the
 * theme's Material Symbols, which wp-admin does not load, so this uses Dashicons.
 *
 * Until v0.2 Unicode was deliberately absent, on the grounds that the operating system's
 * picker covers it. It does not cover flags on Windows; see docs/AXISMUNDI-EMOJI-UNICODE.md.
 * Where this browser cannot draw a profile, Unicode tiles use the plugin's emoji font: the
 * Unicode adapter runs here in passive mode (probe and classify only) and this file applies
 * the class to its own tiles, so nothing in the editor's React-managed DOM is rewrapped.
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
	var RECENT_ALL_KEY = 'axismundiEmojiRecentAll';
	var RECENT_MAX = 32;
	var RECENT_SHOWN = 16;

	// Assigned at the bottom, where the component it references exists; `useAnchor` needs
	// the same object the format was registered with.
	var FORMAT_SETTINGS;

	/*
	 * Dashicons standing in for the reaction picker's Material Symbols, one per Unicode group
	 * in `emoji-test.txt` order. A group this map does not know still gets a jump, with a
	 * generic icon, so a future Unicode group cannot silently lose its way in.
	 */
	var GROUP_ICONS = {
		'Smileys & Emotion': 'smiley',
		'People & Body': 'admin-users',
		'Animals & Nature': 'pets',
		'Food & Drink': 'food',
		'Travel & Places': 'airplane',
		'Activities': 'awards',
		'Objects': 'lightbulb',
		'Symbols': 'tag',
		'Flags': 'flag'
	};

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

	/**
	 * @param {string} key localStorage key.
	 * @return {string[]} Recently inserted values, newest first.
	 */
	function readList( key ) {
		try {
			var stored = window.localStorage.getItem( key );
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

	/**
	 * @param {string} key   localStorage key.
	 * @param {string} value Just-inserted value.
	 */
	function rememberIn( key, value ) {
		try {
			var next = [ value ].concat( readList( key ).filter( function ( s ) {
				return s !== value;
			} ) ).slice( 0, RECENT_MAX );
			window.localStorage.setItem( key, JSON.stringify( next ) );
		} catch ( e ) {
			// Ignored for the same reason.
		}
	}

	/** @return {string[]} Recently inserted shortcodes, newest first. */
	function readRecent() {
		return readList( RECENT_KEY );
	}

	/** @param {string} shortcode Just-inserted shortcode. */
	function rememberRecent( shortcode ) {
		rememberIn( RECENT_KEY, shortcode );
		rememberIn( RECENT_ALL_KEY, shortcode );
	}

	// -- Unicode data -------------------------------------------------------------------------

	/**
	 * Whether a sequence carries a Fitzpatrick skin tone modifier.
	 *
	 * Toned variants are left out, as in the reaction picker: they would multiply the People
	 * group several times over, and choosing a tone is a separate control that does not exist
	 * yet. Code points, not a regular expression, so no escape notation sits in this file.
	 */
	function hasSkinTone( text ) {
		return Array.from( text ).some( function ( ch ) {
			var cp = ch.codePointAt( 0 );
			return cp >= 0x1f3fb && cp <= 0x1f3ff;
		} );
	}

	function withoutSkinTones( items ) {
		return ( Array.isArray( items ) ? items : [] ).filter( function ( item ) {
			return item && 'string' === typeof item.emoji && ! hasSkinTone( item.emoji );
		} );
	}

	// One request per group per editor load, shared by every picker instance.
	var groupCache = {};

	function loadGroup( url ) {
		if ( ! groupCache[ url ] ) {
			groupCache[ url ] = window.fetch( url, { credentials: 'omit' } ).then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'unicode_group_failed' );
				}
				return response.json();
			} ).then( function ( data ) {
				return withoutSkinTones( data && data.items );
			} );
			// A failed load must not be cached as the answer.
			groupCache[ url ].catch( function () {
				delete groupCache[ url ];
			} );
		}
		return groupCache[ url ];
	}

	function unicodeSource() {
		var source = window.axismundiEmojiUnicodeSource;
		return source && source.groups && Object.keys( source.groups ).length ? source : null;
	}

	// -- Tiles --------------------------------------------------------------------------------

	/**
	 * One custom emoji tile.
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
	 * The glyph, drawn by the emoji font where the adapter found native support missing.
	 *
	 * No `wp-exclude-emoji` here: Core's emoji detection does not run on the block editor
	 * screen, so there is no image replacement to keep away.
	 */
	function UnicodeGlyph( props ) {
		var api = window.axismundiEmojiUnicode;
		var profile = api ? api.classify( props.emoji ) : null;
		var attributes = { className: 'axismundi-emoji-picker__glyph', 'aria-hidden': 'true' };
		if ( profile && api.decisions()[ profile ] ) {
			attributes.className += ' ax-unicode-emoji';
			attributes[ 'data-ax-emoji-profile' ] = profile;
		}
		return el( 'span', attributes, props.emoji );
	}

	/** One Unicode tile. Its accessible name is the Unicode name when known, the glyph otherwise. */
	function UnicodeTile( props ) {
		var item = props.item;
		var label = item.name || item.emoji;
		return el(
			'button',
			{
				type: 'button',
				className: 'axismundi-emoji-picker__tile',
				'aria-label': label,
				title: label,
				onClick: function () {
					props.onSelect( item );
				}
			},
			el( UnicodeGlyph, { emoji: item.emoji } )
		);
	}

	function Grid( props ) {
		return el( 'div', { className: 'axismundi-emoji-picker__grid' }, props.children );
	}

	/**
	 * A section of the page. Collapsible ones keep their tiles unmounted while closed, which is
	 * what keeps four thousand Unicode buttons out of the editor until someone wants them.
	 */
	function Section( props ) {
		var Heading = props.level || 'h3';
		var heading = props.collapsible
			? el(
				'button',
				{
					type: 'button',
					className: 'axismundi-emoji-picker__category-toggle',
					'aria-expanded': props.expanded ? 'true' : 'false',
					onClick: props.onToggle
				},
				el( 'span', null, props.title ),
				el( C.Dashicon, { icon: 'arrow-down-alt2', className: 'axismundi-emoji-picker__chevron' } )
			)
			: props.title;
		return el(
			'section',
			{ className: 'axismundi-emoji-picker__section', 'data-section': props.id },
			el( Heading, { className: 'h4' === Heading ? 'axismundi-emoji-picker__subcategory' : 'axismundi-emoji-picker__category' }, heading ),
			! props.collapsible || props.expanded ? props.children : null
		);
	}

	// -- The picker ---------------------------------------------------------------------------

	/**
	 * One scrolling page: Recent, Custom emoji by category, then each Unicode group.
	 *
	 * Recent and Custom are open; Unicode groups start closed and fetch their file the first
	 * time they open. Searching replaces the page with one list of matches, custom first.
	 */
	function PickerPanel( props ) {
		var source = unicodeSource();
		var groups = source ? Object.keys( source.groups ) : [];

		var termState = useState( '' );
		var term = termState[ 0 ];
		var setTerm = termState[ 1 ];
		var customState = useState( [] );
		var custom = customState[ 0 ];
		var setCustom = customState[ 1 ];
		var customLoadedState = useState( false );
		var customLoaded = customLoadedState[ 0 ];
		var setCustomLoaded = customLoadedState[ 1 ];
		var resultsState = useState( { custom: [], unicode: [] } );
		var results = resultsState[ 0 ];
		var setResults = resultsState[ 1 ];
		var searchingState = useState( false );
		var searching = searchingState[ 0 ];
		var setSearching = searchingState[ 1 ];
		var byGroupState = useState( {} );
		var byGroup = byGroupState[ 0 ];
		var setByGroup = byGroupState[ 1 ];
		var expandedState = useState( [] );
		var expanded = expandedState[ 0 ];
		var setExpanded = expandedState[ 1 ];
		var activeState = useState( 'recent' );
		var active = activeState[ 0 ];
		var setActive = activeState[ 1 ];
		var pendingState = useState( '' );
		var pending = pendingState[ 0 ];
		var setPending = pendingState[ 1 ];
		var errorState = useState( '' );
		var error = errorState[ 0 ];
		var setError = errorState[ 1 ];
		var recentState = useState( readList( RECENT_ALL_KEY ) );
		var recent = recentState[ 0 ];
		var setRecent = recentState[ 1 ];
		// Bumped when a font probe settles, so Unicode tiles re-read the adapter's decisions.
		var fontTickState = useState( 0 );
		var setFontTick = fontTickState[ 1 ];
		var searchRef = useRef( null );
		var scrollRef = useRef( null );

		useEffect( function () {
			if ( searchRef.current ) {
				searchRef.current.focus();
			}
		}, [] );

		// The site's publishable custom emoji, once. Every category starts open, as on the front end.
		useEffect( function () {
			var cancelled = false;
			wp.apiFetch( {
				path: wp.url.addQueryArgs( '/axismundi/v1/emoji/local', {
					// Every surface this picker serves publishes its text, so a local-only
					// emoji here would produce a message that reads correctly at home and
					// as a bare word everywhere else.
					federated: true,
					per_page: 100
				} )
			} ).then( function ( response ) {
				if ( cancelled ) {
					return;
				}
				var list = Array.isArray( response ) ? response : [];
				setCustom( list );
				setExpanded( function ( current ) {
					var next = current.slice();
					list.forEach( function ( emoji ) {
						var id = 'custom:' + ( emoji.category || '' );
						if ( next.indexOf( id ) === -1 ) {
							next.push( id );
						}
					} );
					return next;
				} );
				setCustomLoaded( true );
			} ).catch( function () {
				if ( ! cancelled ) {
					setCustomLoaded( true );
					setError( __( 'Custom emoji could not be loaded.', 'axismundi-emoji' ) );
				}
			} );
			return function () {
				cancelled = true;
			};
		}, [] );

		useEffect( function () {
			var api = window.axismundiEmojiUnicode;
			var cancelled = false;
			if ( api && api.verify ) {
				Object.keys( api.decisions() ).forEach( function ( id ) {
					if ( api.decisions()[ id ] ) {
						api.verify( id ).then( function () {
							if ( ! cancelled ) {
								setFontTick( function ( n ) {
									return n + 1;
								} );
							}
						} );
					}
				} );
			}
			return function () {
				cancelled = true;
			};
		}, [] );

		// Debounced: a fetch per keystroke would queue requests that arrive out of order, and
		// the last response to land — not the last one asked for — would win.
		useEffect( function () {
			if ( ! term.trim() ) {
				setResults( { custom: [], unicode: [] } );
				setSearching( false );
				return undefined;
			}
			var cancelled = false;
			var timer = window.setTimeout( function () {
				setSearching( true );
				Promise.all( [
					wp.apiFetch( {
						path: wp.url.addQueryArgs( '/axismundi/v1/emoji/local', { search: term, federated: true, per_page: 100 } )
					} ).catch( function () {
						return [];
					} ),
					source
						? wp.apiFetch( {
							path: wp.url.addQueryArgs( '/axismundi/v1/emoji/unicode', { search: term, per_page: 100 } )
						} ).catch( function () {
							return null;
						} )
						: Promise.resolve( null )
				] ).then( function ( responses ) {
					if ( ! cancelled ) {
						setResults( {
							custom: Array.isArray( responses[ 0 ] ) ? responses[ 0 ] : [],
							unicode: withoutSkinTones( responses[ 1 ] && responses[ 1 ].items )
						} );
						setSearching( false );
					}
				} );
			}, 200 );
			return function () {
				cancelled = true;
				window.clearTimeout( timer );
			};
		}, [ term ] );

		// A jump waits for its section to open, so it measures the grown section, not an empty heading.
		useEffect( function () {
			if ( ! pending || ! scrollRef.current ) {
				return;
			}
			var box = scrollRef.current;
			var target = box.querySelector( '[data-section="' + ( window.CSS && window.CSS.escape ? window.CSS.escape( pending ) : pending ) + '"]' );
			if ( target ) {
				/*
				 * Only the page of sections scrolls. `scrollIntoView` also scrolls every scrollable
				 * ancestor, and inside a popover near the foot of a short window that moved the
				 * whole panel, search field and strip with it, out of view.
				 *
				 * Set directly rather than `scrollTo( { behavior: 'smooth' } )`: inside the block
				 * editor's popover a smooth scroll never moved at all (Chrome, measured 2026-09-16:
				 * 0px after 1.3s, while the same offset set directly landed at once).
				 */
				box.scrollTop += target.getBoundingClientRect().top - box.getBoundingClientRect().top;
				setPending( '' );
			}
		}, [ pending, expanded, byGroup ] );

		function open( id ) {
			if ( expanded.indexOf( id ) !== -1 ) {
				return Promise.resolve();
			}
			setError( '' );
			var loading = 0 === id.indexOf( 'uni:' ) && source
				? loadGroup( source.groups[ id.slice( 4 ) ] ).then( function ( list ) {
					setByGroup( function ( current ) {
						var next = Object.assign( {}, current );
						next[ id.slice( 4 ) ] = list;
						return next;
					} );
				} )
				: Promise.resolve();
			return loading.then( function () {
				setExpanded( function ( current ) {
					return current.indexOf( id ) === -1 ? current.concat( [ id ] ) : current;
				} );
			} ).catch( function () {
				setError( __( 'Emoji could not be loaded.', 'axismundi-emoji' ) );
			} );
		}

		function toggle( id ) {
			if ( expanded.indexOf( id ) !== -1 ) {
				setExpanded( expanded.filter( function ( other ) {
					return other !== id;
				} ) );
				return;
			}
			open( id );
		}

		/** Navigation, never a filter: moves the scroll position and opens a folded destination first. */
		function jump( id ) {
			setActive( id );
			open( id ).then( function () {
				setPending( id );
			} );
		}

		// The heading nearest the top of the scroll box is where the author is. Scrolling never opens anything.
		function trackScroll() {
			var box = scrollRef.current;
			if ( ! box ) {
				return;
			}
			var top = box.getBoundingClientRect().top;
			var current = active;
			box.querySelectorAll( '[data-section]' ).forEach( function ( section ) {
				if ( section.getBoundingClientRect().top - top <= 8 ) {
					current = section.getAttribute( 'data-section' );
				}
			} );
			if ( current !== active ) {
				setActive( current );
			}
		}

		function pickCustom( emoji ) {
			rememberRecent( emoji.shortcode );
			setRecent( readList( RECENT_ALL_KEY ) );
			props.onInsertShortcode( emoji.shortcode );
			// Deliberately stays open. Writing a line with three emoji in it should not mean
			// opening the picker three times; Misskey works this way and it reads better.
		}

		function pickUnicode( item ) {
			rememberIn( RECENT_ALL_KEY, item.emoji );
			setRecent( readList( RECENT_ALL_KEY ) );
			props.onInsertText( item.emoji );
		}

		var customByShortcode = {};
		custom.forEach( function ( emoji ) {
			customByShortcode[ emoji.shortcode ] = emoji;
		} );

		function recentTiles() {
			return recent.map( function ( value ) {
				if ( ':' === value.charAt( 0 ) ) {
					// A custom emoji no longer published here simply drops out of Recent.
					return customByShortcode[ value ]
						? el( Tile, { key: 'recent-' + value, emoji: customByShortcode[ value ], onSelect: pickCustom } )
						: null;
				}
				return el( UnicodeTile, { key: 'recent-' + value, item: { emoji: value, name: '' }, onSelect: pickUnicode } );
			} ).filter( Boolean ).slice( 0, RECENT_SHOWN );
		}

		var categories = [];
		var byCategory = {};
		custom.forEach( function ( emoji ) {
			var category = emoji.category || '';
			if ( ! byCategory[ category ] ) {
				byCategory[ category ] = [];
				categories.push( category );
			}
			byCategory[ category ].push( emoji );
		} );

		function jumpButton( id, icon, label ) {
			var isActive = id === active;
			return el(
				'button',
				{
					key: id,
					type: 'button',
					className: 'axismundi-emoji-picker__jump' + ( isActive ? ' is-active' : '' ),
					'aria-current': isActive ? 'true' : undefined,
					title: label,
					onClick: function () {
						jump( id );
					}
				},
				el( C.Dashicon, { icon: icon } ),
				el( C.VisuallyHidden, null, label )
			);
		}

		var filtering = '' !== term.trim();
		var tiles = recentTiles();

		return el(
			'div',
			{ className: 'axismundi-emoji-picker', role: 'dialog', 'aria-label': __( 'Emoji', 'axismundi-emoji' ) },
			el(
				'div',
				{ className: 'axismundi-emoji-picker__search' },
				el( C.Dashicon, { icon: 'search' } ),
				el( 'input', {
					ref: searchRef,
					type: 'search',
					className: 'axismundi-emoji-picker__input',
					value: term,
					placeholder: __( 'Search emoji', 'axismundi-emoji' ),
					'aria-label': __( 'Search emoji', 'axismundi-emoji' ),
					onChange: function ( event ) {
						setTerm( event.target.value );
					}
				} ),
				searching ? el( 'span', { className: 'axismundi-emoji-picker__loading', role: 'progressbar', 'aria-label': __( 'Searching emoji', 'axismundi-emoji' ) } ) : null
			),
			! filtering
				? el(
					'div',
					{ className: 'axismundi-emoji-picker__strip', role: 'toolbar', 'aria-label': __( 'Jump to emoji category', 'axismundi-emoji' ) },
					jumpButton( 'recent', 'clock', __( 'Recent', 'axismundi-emoji' ) ),
					jumpButton( 'custom', 'star-filled', __( 'Custom emoji', 'axismundi-emoji' ) ),
					groups.map( function ( group ) {
						return jumpButton( 'uni:' + group, GROUP_ICONS[ group ] || 'screenoptions', group );
					} )
				)
				: null,
			error ? el( 'p', { className: 'axismundi-emoji-picker__empty', role: 'alert' }, error ) : null,
			el(
				'div',
				{ className: 'axismundi-emoji-picker__scroll', ref: scrollRef, onScroll: trackScroll },
				filtering
					? el(
						Section,
						{ id: 'results', title: __( 'Results', 'axismundi-emoji' ) },
						results.custom.length || results.unicode.length
							? el(
								Grid,
								null,
								results.custom.map( function ( emoji ) {
									return el( Tile, { key: 'c-' + emoji.id, emoji: emoji, onSelect: pickCustom } );
								} ),
								results.unicode.map( function ( item ) {
									return el( UnicodeTile, { key: 'u-' + ( item.key || item.emoji ), item: item, onSelect: pickUnicode } );
								} )
							)
							: ( searching ? null : el( 'p', { className: 'axismundi-emoji-picker__empty' }, __( 'No emoji found.', 'axismundi-emoji' ) ) )
					)
					: [
						/*
						 * Recent and Custom do not collapse. Collapsing exists to keep thousands of
						 * Unicode tiles unmounted until wanted; these two are small and are why
						 * the author opened the picker.
						 */
						el(
							Section,
							{ key: 'recent', id: 'recent', title: __( 'Recent', 'axismundi-emoji' ) },
							tiles.length
								? el( Grid, null, tiles )
								: el( 'p', { className: 'axismundi-emoji-picker__empty' }, __( 'Emoji you use will appear here.', 'axismundi-emoji' ) )
						),
						el(
							Section,
							{ key: 'custom', id: 'custom', title: __( 'Custom emoji', 'axismundi-emoji' ) },
							! customLoaded
								? el( 'p', { className: 'axismundi-emoji-picker__empty' }, el( C.Spinner ) )
								: ( categories.length
									? categories.map( function ( category ) {
										var id = 'custom:' + category;
										return el(
											Section,
											{
												key: id,
												id: id,
												level: 'h4',
												title: category || __( 'Uncategorized', 'axismundi-emoji' ),
												collapsible: true,
												expanded: expanded.indexOf( id ) !== -1,
												onToggle: function () {
													toggle( id );
												}
											},
											el( Grid, null, byCategory[ category ].map( function ( emoji ) {
												return el( Tile, { key: emoji.id, emoji: emoji, onSelect: pickCustom } );
											} ) )
										);
									} )
									: el( 'p', { className: 'axismundi-emoji-picker__empty' }, __( 'This site has no custom emoji yet.', 'axismundi-emoji' ) ) )
						),
						groups.map( function ( group ) {
							var id = 'uni:' + group;
							return el(
								Section,
								{
									key: id,
									id: id,
									title: group,
									collapsible: true,
									expanded: expanded.indexOf( id ) !== -1,
									onToggle: function () {
										toggle( id );
									}
								},
								el( Grid, null, ( byGroup[ group ] || [] ).map( function ( item ) {
									return el( UnicodeTile, { key: item.key || item.emoji, item: item, onSelect: pickUnicode } );
								} ) )
							);
						} )
					]
			)
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

		// A grapheme needs no boundary: it is never tokenized, so nothing is padded.
		function insertText( text ) {
			props.onChange( wp.richText.insert( props.value, text ) );
		}

		return el(
			wp.element.Fragment,
			null,
			el( wp.blockEditor.RichTextToolbarButton, {
				icon: 'smiley',
				title: __( 'Emoji', 'axismundi-emoji' ),
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
					el( PickerPanel, { onInsertShortcode: insert, onInsertText: insertText } )
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
		title: __( 'Emoji', 'axismundi-emoji' ),
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
