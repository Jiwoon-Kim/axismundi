/**
 * axismundi/object-attachments — carousel view engine (no build step).
 *
 * Owns slide position only: track transform, previous/next, dots, keyboard, and touch
 * swipe. It deliberately never reads or writes `location`, `history`, or a per-image
 * hash. Jetpack's carousel records `#jp-carousel-{id}` and rewrites the URL on every
 * slide change, which turns one gallery visit into a stack of history entries and makes
 * Back walk backwards through slides instead of leaving the page. Slide position is
 * view state, not a document address, so it stays in memory.
 *
 * Progressive enhancement: the server already emitted every slide in document order, so
 * with this script absent the media is all still reachable — the track simply does not
 * translate.
 */
( function () {
	var REDUCED = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function Carousel( root ) {
		this.root = root;
		this.track = root.querySelector( '.axismundi-object__carousel-track' );
		if ( ! this.track ) {
			return;
		}
		this.slides = Array.prototype.slice.call(
			this.track.querySelectorAll( '.axismundi-object__carousel-slide' )
		);
		this.total = this.slides.length;
		this.dots = Array.prototype.slice.call(
			root.querySelectorAll( '.axismundi-object__carousel-dot' )
		);
		this.prev = root.querySelector( '.axismundi-object__carousel-nav--prev' );
		this.next = root.querySelector( '.axismundi-object__carousel-nav--next' );
		this.index = 0;

		if ( this.total > 1 ) {
			this.bind();
		}
		this.update();
	}

	Carousel.prototype.bind = function () {
		var self = this;

		if ( this.prev ) {
			this.prev.addEventListener( 'click', function () { self.goTo( self.index - 1 ); } );
		}
		if ( this.next ) {
			this.next.addEventListener( 'click', function () { self.goTo( self.index + 1 ); } );
		}
		this.dots.forEach( function ( dot, i ) {
			dot.addEventListener( 'click', function () { self.goTo( i ); } );
		} );

		// Arrow keys act only while focus is inside this carousel, so a page with two
		// of them (or a carousel beside other content) never steals the arrow keys.
		this.root.addEventListener( 'keydown', function ( event ) {
			if ( 'ArrowLeft' === event.key ) {
				event.preventDefault();
				self.goTo( self.index - 1 );
			} else if ( 'ArrowRight' === event.key ) {
				event.preventDefault();
				self.goTo( self.index + 1 );
			}
		} );

		// Swipe. The 40px threshold keeps a tap from moving the track, which matters
		// because a slide can contain the sensitive-media reveal button: that button
		// must stay clickable, and it is never bound here.
		var startX = 0;
		var startY = 0;
		this.root.addEventListener( 'touchstart', function ( event ) {
			startX = event.touches[ 0 ].clientX;
			startY = event.touches[ 0 ].clientY;
		}, { passive: true } );
		this.root.addEventListener( 'touchend', function ( event ) {
			var dx = event.changedTouches[ 0 ].clientX - startX;
			var dy = event.changedTouches[ 0 ].clientY - startY;
			// Ignore a mostly-vertical drag so swiping does not fight page scrolling.
			if ( Math.abs( dx ) > 40 && Math.abs( dx ) > Math.abs( dy ) ) {
				self.goTo( self.index + ( dx < 0 ? 1 : -1 ) );
			}
		}, { passive: true } );
	};

	Carousel.prototype.goTo = function ( n ) {
		if ( this.total <= 1 ) {
			return;
		}
		this.index = ( ( n % this.total ) + this.total ) % this.total;
		this.update();
	};

	Carousel.prototype.update = function () {
		this.track.style.transition = REDUCED ? 'none' : '';
		this.track.style.transform = 'translateX(-' + ( this.index * 100 ) + '%)';

		var self = this;
		this.slides.forEach( function ( slide, i ) {
			var current = i === self.index;
			slide.classList.toggle( 'is-active', current );
			// Keep off-screen slides out of the tab order and the accessibility tree,
			// so keyboard and screen-reader users are not walked through hidden media.
			slide.setAttribute( 'aria-hidden', current ? 'false' : 'true' );
			slide.inert = ! current;
		} );
		this.dots.forEach( function ( dot, i ) {
			var current = i === self.index;
			dot.classList.toggle( 'is-active', current );
			if ( current ) {
				dot.setAttribute( 'aria-current', 'true' );
			} else {
				dot.removeAttribute( 'aria-current' );
			}
		} );
	};

	function init( scope ) {
		( scope || document ).querySelectorAll( '[data-ax-carousel]' ).forEach( function ( root ) {
			if ( ! root.axCarousel ) {
				root.axCarousel = new Carousel( root );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () { init(); } );
	} else {
		init();
	}

	// Exposed so a later surface (the media dialog) can initialise a carousel it built.
	window.axismundiObjectCarousel = { init: init, Carousel: Carousel };
}() );
