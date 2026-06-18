/**
 * axismundi/toc — front-end scroll-spy + smooth anchor scroll.
 *
 * Marks the TOC link for the current reading section with `is-current`
 * (+ aria-current) — the lab TOC rail's active hook. The current section is the
 * last heading scrolled past a line 20% down the viewport; one decision drives
 * both triggers so they never disagree:
 *   - IntersectionObserver: the cheap main trigger.
 *   - scroll fallback (rAF-throttled): corrects fast scrolls / short sections / IO misses.
 *
 * Active is CLEARED when the reading line is outside the content: above the first
 * heading (intro / featured image / Home key) or past the post-content bottom
 * (comments / footer / End key).
 *
 * Clicking a TOC link lights the link immediately; the (smooth) hash navigation
 * itself is left to the browser — the theme owns `scroll-behavior: smooth` (with a
 * reduced-motion override), so the plugin does not preventDefault or scroll. The
 * literal hash is matched byte-for-byte against the heading id — sanitize_title()
 * percent-encodes non-ASCII (e.g. Korean) headings into the same value on both the
 * id and the href, so the href must NOT be decoded (decoding would turn "%ea%b0%95…"
 * back into "강조…" and miss getElementById on the literal id). See linkId.
 *
 * Read-only observation; supports multiple TOC navs on one page.
 */
( function () {
	const linkId = ( link ) => ( link.getAttribute( 'href' ) || '' ).replace( /^#/, '' );

	function initNav( nav ) {
		const links = Array.prototype.slice.call( nav.querySelectorAll( '.ax-toc__link' ) );
		if ( ! links.length ) {
			return;
		}

		const byId = {};
		const targets = [];
		links.forEach( function ( link ) {
			const id = linkId( link );
			if ( ! id ) {
				return;
			}
			const heading = document.getElementById( id );
			if ( ! heading ) {
				return;
			}
			byId[ id ] = link;
			targets.push( heading );
		} );

		if ( ! targets.length ) {
			return;
		}

		// The content box bounds the "past the content" clear. Core renders
		// post-content with .wp-block-post-content; fall back to .entry-content,
		// else skip that edge (the above-content clear still applies).
		const contentEl = targets[ 0 ].closest( '.wp-block-post-content, .entry-content' );
		// Disclosure variant: the <summary> can echo the current section. No aria-live
		// — narrating every section while scrolling would be noise.
		const summaryCurrent = nav.querySelector( '.ax-toc__summary-current' );

		let currentId = '';
		// id === '' clears every link.
		function apply( id ) {
			if ( id === currentId ) {
				return; // guard: skip redundant DOM writes
			}
			currentId = id;
			links.forEach( function ( link ) {
				const active = id !== '' && link === byId[ id ];
				link.classList.toggle( 'is-current', active );
				if ( active ) {
					link.setAttribute( 'aria-current', 'true' );
				} else {
					link.removeAttribute( 'aria-current' );
				}
			} );
			if ( summaryCurrent ) {
				if ( id && byId[ id ] ) {
					summaryCurrent.textContent = byId[ id ].textContent;
					nav.classList.add( 'is-tracking' );
				} else {
					summaryCurrent.textContent = '';
					nav.classList.remove( 'is-tracking' );
				}
			}
		}

		function updateActive() {
			// The active line sits 15% down the viewport. It MUST match the theme's
			// in-content heading scroll-margin-top (style.css, 15vh) so a clicked TOC
			// link lands the heading exactly where is-current is decided.
			const line = window.innerHeight * 0.15;

			// Above the content: the first heading has not reached the line yet.
			if ( targets[ 0 ].getBoundingClientRect().top > line ) {
				apply( '' );
				return;
			}
			// Past the content: the post-content box has scrolled above the line.
			if ( contentEl && contentEl.getBoundingClientRect().bottom < line ) {
				apply( '' );
				return;
			}
			// Otherwise the current section is the last heading at/above the line.
			let active = targets[ 0 ];
			for ( let i = 0; i < targets.length; i++ ) {
				if ( targets[ i ].getBoundingClientRect().top <= line ) {
					active = targets[ i ];
				} else {
					break;
				}
			}
			apply( active.id );
		}

		const observer = new IntersectionObserver( updateActive, {
			rootMargin: '-20% 0px -70% 0px',
			threshold: 0,
		} );
		targets.forEach( function ( heading ) {
			observer.observe( heading );
		} );

		let ticking = false;
		window.addEventListener(
			'scroll',
			function () {
				if ( ticking ) {
					return;
				}
				ticking = true;
				requestAnimationFrame( function () {
					ticking = false;
					updateActive();
				} );
			},
			{ passive: true }
		);

		// Click → light the active link immediately. The browser handles the (smooth)
		// hash navigation natively; the theme owns scroll-behavior, so we neither
		// preventDefault nor scroll here.
		nav.addEventListener( 'click', function ( event ) {
			const link = event.target.closest( '.ax-toc__link' );
			if ( link ) {
				apply( linkId( link ) );
			}
		} );

		updateActive();
	}

	function init() {
		Array.prototype.slice
			.call( document.querySelectorAll( '.ax-toc' ) )
			.forEach( initNav );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
