/**
 * Unicode emoji font fallback adapter.
 *
 * Design: docs/AXISMUNDI-EMOJI-UNICODE.md (§3, §4, §7).
 *
 * In `auto` mode a grapheme is wrapped for the emoji font only when all three hold:
 *   1. the browser cannot draw its profile natively (canvas probe, as Core does);
 *   2. a font on this site covers that profile (the manifest, via PHP);
 *   3. the browser really draws it with that font (a second probe, once the font loads).
 * In `font` mode the site has chosen its own emoji, so the first question is not asked:
 * every RGI emoji is wrapped for the complete font, and only 2 and 3 apply.
 *
 * If the third turns out false, every wrapper of that profile is replaced with plain text,
 * which Core's own MutationObserver then hands to Twemoji (when the site keeps WordPress's
 * image fallback on). A failure therefore falls back to exactly what WordPress would do.
 *
 * Nothing here reads or writes Core's emoji globals or its sessionStorage entry.
 *
 * No build, no dependencies. Requires Intl.Segmenter; without it the adapter does nothing,
 * because splitting a ZWJ or tag sequence would be worse than not wrapping at all.
 */
( function ( window, document ) {
	'use strict';

	var CONFIG_ID = 'axismundi-emoji-unicode-config';
	var CACHE_KEY = 'axismundiEmojiProbe';
	// One week in milliseconds. Core's loader adds seconds to a millisecond timestamp (Trac candidate C1).
	var CACHE_TTL = 7 * 24 * 60 * 60 * 1000;
	var WRAPPER_CLASS = 'ax-unicode-emoji';
	var CORE_EXCLUDE_CLASS = 'wp-exclude-emoji';
	var ZWSP = String.fromCharCode( 0x200b );
	var NATIVE_FONT = '600 32px Arial'; // Core's probe font, so both probes answer the same question.
	var SKIP_SELECTOR = [
		'.' + WRAPPER_CLASS,
		'.' + CORE_EXCLUDE_CLASS,
		'.ax-emoji-picture',
		'script', 'style', 'textarea', 'select', 'noscript', 'iframe', 'noframes',
		'[contenteditable]'
	].join( ', ' );

	/*
	 * Front end: inert JSON in the head, printed like Core's `wp-emoji-settings`. Editor: a
	 * global set by an inline script, because admin screens have no wp_head to print into.
	 */
	var configNode = document.getElementById( CONFIG_ID );
	var config = window.axismundiEmojiUnicodeConfig || null;
	if ( configNode ) {
		try {
			config = JSON.parse( configNode.textContent );
		} catch ( e ) {
			return;
		}
	}
	if (
		! config || ! config.profiles ||
		typeof Intl === 'undefined' || typeof Intl.Segmenter !== 'function' ||
		typeof MutationObserver === 'undefined' || ! document.body
	) {
		return;
	}

	// Only the profiles the site's rendering mode uses: flags in `auto`, every emoji in `font`.
	var mode = config.mode === 'font' ? 'font' : 'auto';
	var profiles = {};
	Object.keys( config.profiles ).forEach( function ( id ) {
		var modes = config.profiles[ id ].modes;
		if ( ! Array.isArray( modes ) || modes.indexOf( mode ) !== -1 ) {
			profiles[ id ] = config.profiles[ id ];
		}
	} );
	var family = config.family || 'Noto Color Emoji';
	// Only tests/unicode-harness sets this; PHP never prints it. It stands in for a font file
	// so wrapping and Core interplay can be measured before the font exists.
	var assumeFont = !! ( config.diagnostics && config.diagnostics.assumeFont === true );
	var segmenter = new Intl.Segmenter( undefined, { granularity: 'grapheme' } );
	var decisions = {};
	var verifying = {};
	var sequenceKeys = {};
	var cache = readCache();
	var context = null;

	Object.keys( profiles ).forEach( function ( id ) {
		if ( profiles[ id ].sequences ) {
			sequenceKeys[ id ] = {};
			profiles[ id ].sequences.forEach( function ( list ) {
				sequenceKeys[ id ][ list.join( '-' ) ] = true;
			} );
		}
	} );

	// -- Cache --------------------------------------------------------------------------------

	function readCache() {
		try {
			var item = JSON.parse( window.sessionStorage.getItem( CACHE_KEY ) );
			if ( item && item.v === config.version && typeof item.t === 'number' && Date.now() < item.t + CACHE_TTL && item.native && item.font ) {
				return item;
			}
		} catch ( e ) {}
		return { v: config.version, t: Date.now(), native: {}, font: {} };
	}

	function writeCache() {
		try {
			window.sessionStorage.setItem( CACHE_KEY, JSON.stringify( cache ) );
		} catch ( e ) {}
	}

	// -- Classification ---------------------------------------------------------------------

	function codePoints( text ) {
		return Array.from( text, function ( ch ) {
			return ch.codePointAt( 0 );
		} );
	}

	function fromCodePoints( list ) {
		return String.fromCodePoint.apply( String, list );
	}

	function isRegionalIndicator( cp ) {
		return cp >= 0x1f1e6 && cp <= 0x1f1ff;
	}

	/*
	 * Unicode's own RGI_Emoji property of strings where the browser has it (the `v` flag), a
	 * pictographic or regional-indicator test where it does not. Built with the RegExp
	 * constructor, so a browser without `v` does not fail to parse this whole file.
	 */
	var EMOJI_PATTERN = ( function () {
		try {
			return new RegExp( '^\\p{RGI_Emoji}$', 'v' );
		} catch ( e ) {
			try {
				return new RegExp( '^(?:\\p{Extended_Pictographic}|\\p{Regional_Indicator})', 'u' );
			} catch ( e2 ) {
				return null;
			}
		}
	}() );

	function matches( rule, cps, grapheme ) {
		switch ( rule ) {
			case 'rgi-emoji':
				return !! EMOJI_PATTERN && EMOJI_PATTERN.test( grapheme );
			case 'regional-indicator-pair':
				return cps.length === 2 && isRegionalIndicator( cps[ 0 ] ) && isRegionalIndicator( cps[ 1 ] );
			case 'tag-sequence':
				if ( cps.length < 3 || cps[ 0 ] !== 0x1f3f4 || cps[ cps.length - 1 ] !== 0xe007f ) {
					return false;
				}
				for ( var i = 1; i < cps.length - 1; i++ ) {
					if ( cps[ i ] < 0xe0020 || cps[ i ] > 0xe007e ) {
						return false;
					}
				}
				return true;
		}
		return false;
	}

	/**
	 * The profile a single grapheme belongs to, or null.
	 *
	 * @param {string} grapheme One extended grapheme cluster.
	 * @return {?string} Profile id.
	 */
	function classify( grapheme ) {
		var cps = codePoints( grapheme );
		for ( var id in profiles ) {
			if ( ! matches( profiles[ id ].match, cps, grapheme ) ) {
				continue;
			}
			if ( sequenceKeys[ id ] && ! sequenceKeys[ id ][ cps.join( '-' ) ] ) {
				continue;
			}
			return id;
		}
		return null;
	}

	/*
	 * A cheap pre-check before segmenting. Flags are astral, so in `auto` a text node with no
	 * surrogate pair holds no candidate. `font` also claims emoji below the astral planes —
	 * a watch, a heart, a keycap — so there the common BMP emoji blocks count too.
	 */
	function mayContainCandidate( text ) {
		for ( var i = 0; i < text.length; i++ ) {
			var unit = text.charCodeAt( i );
			if ( unit >= 0xd800 && unit <= 0xdbff ) {
				return true;
			}
			if ( 'font' === mode && ( 0xa9 === unit || 0xae === unit || ( unit >= 0x203c && unit <= 0x3299 ) ) ) {
				return true;
			}
		}
		return false;
	}

	// -- Probes -----------------------------------------------------------------------------

	function getContext() {
		if ( context === null ) {
			var canvas = document.createElement( 'canvas' );
			canvas.width = 160;
			canvas.height = 48;
			context = canvas.getContext( '2d', { willReadFrequently: true } ) || false;
			if ( context ) {
				context.textBaseline = 'top';
			}
		}
		return context;
	}

	function pixels( ctx, font, text ) {
		ctx.font = font;
		ctx.clearRect( 0, 0, ctx.canvas.width, ctx.canvas.height );
		ctx.fillText( text, 0, 0 );
		return ctx.getImageData( 0, 0, ctx.canvas.width, ctx.canvas.height ).data;
	}

	/**
	 * Whether a sequence draws differently from its code points drawn apart.
	 *
	 * Core's technique: an unsupported sequence renders as its separate parts, so it looks
	 * the same as the parts with a zero-width space between them.
	 */
	function drawsAsOne( ctx, font, list ) {
		var joined = pixels( ctx, font, fromCodePoints( list ) );
		var apart = pixels( ctx, font, list.map( function ( cp ) {
			return String.fromCodePoint( cp );
		} ).join( ZWSP ) );
		for ( var i = 0; i < joined.length; i++ ) {
			if ( joined[ i ] !== apart[ i ] ) {
				return true;
			}
		}
		return false;
	}

	function nativeSupported( id ) {
		if ( typeof cache.native[ id ] === 'boolean' ) {
			return cache.native[ id ];
		}
		var ctx = getContext();
		// No canvas means no evidence; claim support so nothing is wrapped and Core decides.
		var supported = ! ctx || profiles[ id ].probe.every( function ( list ) {
			return drawsAsOne( ctx, NATIVE_FONT, list );
		} );
		cache.native[ id ] = supported;
		return supported;
	}

	function fontSupported( id ) {
		if ( assumeFont ) {
			return Promise.resolve( true );
		}
		if ( typeof cache.font[ id ] === 'boolean' ) {
			return Promise.resolve( cache.font[ id ] );
		}
		var ctx = getContext();
		if ( ! ctx || ! document.fonts || typeof document.fonts.load !== 'function' ) {
			return Promise.resolve( false );
		}
		// Each file loads under its own alias, so the probe asks for exactly the face the wrapper will use.
		var font = '32px "' + ( profiles[ id ].fontFamily || family ) + '"';
		var sample = profiles[ id ].probe.map( fromCodePoints ).join( '' );
		return document.fonts.load( font, sample ).then( function () {
			return profiles[ id ].probe.every( function ( list ) {
				return drawsAsOne( ctx, font, list );
			} );
		}, function () {
			return false;
		} ).then( function ( supported ) {
			cache.font[ id ] = supported;
			writeCache();
			return supported;
		} );
	}

	// -- Wrapping ---------------------------------------------------------------------------

	/**
	 * Two nested spans, inserted together.
	 *
	 * WORKAROUND (Trac candidate C2): Core skips `.wp-exclude-emoji` only while walking
	 * children, never when the excluded element is itself the node it parses. An excluded
	 * span inserted directly, or one whose text changes, is replaced with an image
	 * (reproduced 2026-09-15). Nesting it inside our own wrapper keeps it a child in every
	 * parse Core starts. Once Core honours the class on the parse root, one span suffices.
	 */
	function makeWrapper( id, grapheme ) {
		var outer = document.createElement( 'span' );
		outer.className = WRAPPER_CLASS;
		outer.setAttribute( 'data-ax-emoji-profile', id );
		var inner = document.createElement( 'span' );
		inner.className = CORE_EXCLUDE_CLASS;
		inner.textContent = grapheme;
		outer.appendChild( inner );
		return outer;
	}

	function skipped( node ) {
		var element = node.nodeType === 1 ? node : node.parentElement;
		return ! element || !! element.closest( SKIP_SELECTOR );
	}

	function wrapTextNode( text ) {
		var data = text.data;
		var parent = text.parentNode;
		if ( ! data || ! parent || ! mayContainCandidate( data ) || skipped( text ) ) {
			return;
		}
		var fragment = null;
		var plain = '';
		for ( var part of segmenter.segment( data ) ) {
			var id = classify( part.segment );
			if ( id && decisions[ id ] ) {
				fragment = fragment || document.createDocumentFragment();
				if ( plain ) {
					fragment.appendChild( document.createTextNode( plain ) );
					plain = '';
				}
				fragment.appendChild( makeWrapper( id, part.segment ) );
				verify( id );
			} else {
				plain += part.segment;
			}
		}
		if ( ! fragment ) {
			return;
		}
		if ( plain ) {
			fragment.appendChild( document.createTextNode( plain ) );
		}
		parent.replaceChild( fragment, text );
	}

	/**
	 * Wrap every covered grapheme under a node.
	 *
	 * @param {Node} root Element or text node.
	 */
	function protect( root ) {
		if ( ! root ) {
			return;
		}
		if ( root.nodeType === 3 ) {
			wrapTextNode( root );
			return;
		}
		if ( root.nodeType !== 1 || skipped( root ) ) {
			return;
		}
		var walker = document.createTreeWalker( root, 4 /* NodeFilter.SHOW_TEXT */ );
		var texts = [];
		while ( walker.nextNode() ) {
			texts.push( walker.currentNode );
		}
		texts.forEach( wrapTextNode );
	}

	// Drop a profile's wrappers back to plain text; Core's observer then treats it as new text.
	function unwrap( id ) {
		decisions[ id ] = false;
		document.querySelectorAll( '.' + WRAPPER_CLASS + '[data-ax-emoji-profile="' + id + '"]' ).forEach( function ( outer ) {
			if ( outer.parentNode ) {
				outer.parentNode.replaceChild( document.createTextNode( outer.textContent ), outer );
			}
		} );
	}

	function verify( id ) {
		if ( verifying[ id ] ) {
			return;
		}
		verifying[ id ] = fontSupported( id ).then( function ( supported ) {
			if ( ! supported ) {
				unwrap( id );
			}
			return supported;
		} );
	}

	// -- Start ------------------------------------------------------------------------------

	var active = Object.keys( profiles ).filter( function ( id ) {
		// `font` does not ask what the browser can draw: the site chose its own emoji.
		decisions[ id ] = !! profiles[ id ].font && cache.font[ id ] !== false && ( 'font' === mode || ! nativeSupported( id ) );
		return decisions[ id ];
	} );
	writeCache();

	window.axismundiEmojiUnicode = {
		classify: classify,
		protect: protect,
		decisions: function () {
			return Object.assign( {}, decisions );
		},
		verified: function ( id ) {
			return verifying[ id ] || Promise.resolve( null );
		},
		// Start the font probe for a profile without wrapping anything; the editor picker
		// applies the class itself and needs to know when the answer is final.
		verify: function ( id ) {
			if ( decisions[ id ] ) {
				verify( id );
			}
			return verifying[ id ] || Promise.resolve( false );
		}
	};

	/*
	 * Passive mode (`observe: false`, the block editor): probe and classify only. The editor's
	 * DOM belongs to React, and replacing its text nodes behind React's back breaks
	 * reconciliation, so there the picker applies the wrapper class to its own tiles instead.
	 */
	if ( ! active.length || config.observe === false ) {
		return;
	}

	// Registered before Core's (which waits for Twemoji to load), so each batch reaches us first.
	new MutationObserver( function ( records ) {
		records.forEach( function ( record ) {
			if ( record.type === 'characterData' ) {
				wrapTextNode( record.target );
				return;
			}
			record.addedNodes.forEach( protect );
		} );
	} ).observe( document.body, { childList: true, subtree: true, characterData: true } );

	protect( document.body );
}( window, document ) );
