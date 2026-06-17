/**
 * axismundi/toc — front-end scroll-spy.
 *
 * Marks the TOC link whose heading is currently the topmost one in view with
 * `is-current` (+ aria-current), the same active hook the lab TOC rail uses. No
 * Interactivity store needed: this is read-only observation of heading positions.
 * Respects multiple TOC navs on one page (e.g. a sidebar rail + an inline copy).
 */
( function () {
	function initNav( nav ) {
		var links = Array.prototype.slice.call( nav.querySelectorAll( '.ax-toc__link' ) );
		if ( ! links.length ) {
			return;
		}

		var byId = {};
		var targets = [];
		links.forEach( function ( link ) {
			var id = decodeURIComponent( ( link.getAttribute( 'href' ) || '' ).replace( /^#/, '' ) );
			if ( ! id ) {
				return;
			}
			var heading = document.getElementById( id );
			if ( ! heading ) {
				return;
			}
			byId[ id ] = link;
			targets.push( heading );
		} );

		if ( ! targets.length ) {
			return;
		}

		var visible = new Set();

		function setCurrent( id ) {
			links.forEach( function ( link ) {
				link.classList.remove( 'is-current' );
				link.removeAttribute( 'aria-current' );
			} );
			var current = byId[ id ];
			if ( current ) {
				current.classList.add( 'is-current' );
				current.setAttribute( 'aria-current', 'true' );
			}
		}

		var observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						visible.add( entry.target );
					} else {
						visible.delete( entry.target );
					}
				} );

				// Pick the topmost heading still in the viewport band.
				var top = null;
				var topY = Infinity;
				visible.forEach( function ( heading ) {
					var y = heading.getBoundingClientRect().top;
					if ( y < topY ) {
						topY = y;
						top = heading;
					}
				} );
				if ( top && top.id ) {
					setCurrent( top.id );
				}
			},
			{ rootMargin: '0px 0px -70% 0px', threshold: 0 }
		);

		targets.forEach( function ( heading ) {
			observer.observe( heading );
		} );
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
