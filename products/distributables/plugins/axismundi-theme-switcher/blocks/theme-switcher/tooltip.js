/**
 * axismundi/theme-switcher — plain tooltip runtime.
 *
 * M3's plain tooltip: a short visual label for a control that shows no text of
 * its own. Here that is the cycle button, which is always icon-only, and the
 * group's segments when their labels are turned off.
 *
 * WordPress ships two Tooltip components and neither fits. Both are React,
 * both assume the editor's chrome and its portal slots, and neither package is
 * loaded on the front end at all. What is worth borrowing is the newer one's
 * accessibility rule, which this follows: the popup is VISUAL ONLY. It carries
 * `aria-hidden`, has no `role="tooltip"`, and nothing points at it -- no
 * `aria-describedby`. The button's own accessible name already says what the
 * tooltip says, so announcing it again would only repeat. That is also why the
 * text is read from the trigger's `.screen-reader-text` span at open time
 * rather than stored: for the cycle button that span is bound to the live
 * scheme, so the tooltip follows it with no state of its own here.
 *
 * One element per document, moved rather than duplicated. `attach()` is
 * idempotent and takes a document, because the block editor's canvas is a
 * separate one: the front end attaches to its own on load, and the editor
 * bridge attaches to each preview iframe.
 */
( function () {
	var namespace = window.axismundiThemeSwitcher = window.axismundiThemeSwitcher || {};
	if ( namespace.tooltip ) {
		return;
	}

	var TRIGGER = '[data-ax-ts-tooltip]';
	var LABEL = '.screen-reader-text';
	var WRAPPER = '.wp-block-axismundi-theme-switcher';
	// The Navigation block's overlay, which core gives z-index 100000 to clear
	// the admin bar. A tooltip parked on <body> at 99999 would sit behind it.
	var OVERLAY = '.wp-block-navigation__responsive-container';
	var OVERLAY_OPEN = 'is-menu-open';

	// M3 places a plain tooltip above its target, 4dp away when the target has
	// a visual boundary -- every trigger here is a button, so always 4.
	var GAP = 4;
	// Keep it off the viewport edge when a trigger sits near one.
	var EDGE = 8;
	// First appearance waits; a second one straight after does not. Sweeping
	// Auto -> Light -> Dark should read as one gesture, not three waits.
	var DELAY = 700;
	var WARM = 400;
	// M3: a tooltip is transient, and goes 1.5s after the pointer leaves its
	// target. Long enough to glance back at, short enough not to linger.
	var LINGER = 1500;
	// M3 opens a tooltip on touch by tap and hold, which has to be told apart
	// from a tap that means to press the button.
	var HOLD = 500;

	var states = new WeakMap();

	function stateFor( doc ) {
		var state = states.get( doc );
		if ( ! state ) {
			state = {
				element: null,
				trigger: null,
				showTimer: 0,
				closeTimer: 0,
				holdTimer: 0,
				held: null,
				overlay: null,
				warmUntil: 0,
				warmWrapper: null,
				suppressed: null,
			};
			states.set( doc, state );
		}
		return state;
	}

	function stop( doc, state, name ) {
		if ( state[ name ] ) {
			doc.defaultView.clearTimeout( state[ name ] );
			state[ name ] = 0;
		}
	}

	function element( doc, state ) {
		if ( ! state.element ) {
			state.element = doc.createElement( 'div' );
			state.element.className = 'axismundi-theme-switcher__tooltip';
			state.element.setAttribute( 'aria-hidden', 'true' );
		}
		return state.element;
	}

	function labelFor( trigger ) {
		var label = trigger.querySelector( LABEL );
		return label ? ( label.textContent || '' ).trim() : '';
	}

	/*
	 * Where the tooltip has to live to be seen. <body> is enough almost
	 * everywhere -- it escapes any `overflow` or stacking context the header
	 * puts the switcher in. Inside an open Navigation overlay it is not, so the
	 * tooltip moves into the overlay and rides its layer instead of trying to
	 * out-number it. That keeps it under the dialog layer, which also sits at
	 * 100000 and should stay on top.
	 */
	function overlayFor( trigger ) {
		var overlay = trigger.closest( OVERLAY );
		return overlay && overlay.classList.contains( OVERLAY_OPEN ) ? overlay : null;
	}

	function place( element, trigger ) {
		var target = trigger.getBoundingClientRect();
		var box = element.getBoundingClientRect();
		var view = element.ownerDocument.defaultView;

		var top = target.top - box.height - GAP;
		if ( top < EDGE ) {
			// No room above. M3 does the same for a target in an app bar.
			top = target.bottom + GAP;
		}

		var left = target.left + ( target.width - box.width ) / 2;
		var limit = view.innerWidth - box.width - EDGE;
		if ( left > limit ) {
			left = limit;
		}
		if ( left < EDGE ) {
			left = EDGE;
		}

		element.style.top = Math.round( top ) + 'px';
		element.style.left = Math.round( left ) + 'px';
	}

	function close( doc ) {
		var state = stateFor( doc );

		stop( doc, state, 'showTimer' );
		stop( doc, state, 'closeTimer' );

		if ( state.overlay ) {
			state.overlay.disconnect();
			state.overlay = null;
		}

		if ( ! state.trigger ) {
			return;
		}

		state.warmUntil = Date.now() + WARM;
		state.warmWrapper = state.trigger.closest( WRAPPER );
		state.trigger = null;

		if ( state.element ) {
			state.element.classList.remove( 'is-open' );
			// Back to <body>, so a tooltip that opened inside a Navigation
			// overlay is never left parented in one that has since closed.
			if ( doc.body && state.element.parentElement !== doc.body ) {
				doc.body.appendChild( state.element );
			}
		}
	}

	// M3 keeps a tooltip up for 1.5s after the pointer leaves its target.
	function scheduleClose( doc ) {
		var state = stateFor( doc );

		// Nothing is showing, but something may be about to: drop that.
		if ( ! state.trigger ) {
			stop( doc, state, 'showTimer' );
			return;
		}

		stop( doc, state, 'closeTimer' );
		state.closeTimer = doc.defaultView.setTimeout( function () {
			close( doc );
		}, LINGER );
	}

	function open( doc, trigger ) {
		var text = labelFor( trigger );
		if ( ! text ) {
			return;
		}

		var state = stateFor( doc );

		// Whatever else was queued or showing loses: M3 says a new tooltip
		// closes any open one, and a pending one for a trigger the reader has
		// already left must not surface behind it.
		stop( doc, state, 'showTimer' );
		stop( doc, state, 'closeTimer' );
		if ( state.overlay ) {
			state.overlay.disconnect();
			state.overlay = null;
		}

		var el = element( doc, state );
		var overlay = overlayFor( trigger );

		el.textContent = text;
		( overlay || doc.body ).appendChild( el );
		state.trigger = trigger;
		place( el, trigger );
		el.classList.add( 'is-open' );

		/*
		 * An overlay can close while its tooltip is up -- the menu's own close
		 * button is a trigger's neighbour. Watch the class that opened it and
		 * go when it does, rather than leaving a tooltip inside something
		 * hidden.
		 */
		if ( overlay ) {
			state.overlay = new doc.defaultView.MutationObserver( function () {
				if ( ! overlay.classList.contains( OVERLAY_OPEN ) ) {
					close( doc );
				}
			} );
			state.overlay.observe( overlay, { attributes: true, attributeFilter: [ 'class' ] } );
		}
	}

	function show( doc, trigger ) {
		var state = stateFor( doc );

		// Back on a trigger whose tooltip is still lingering: keep it.
		stop( doc, state, 'closeTimer' );

		if ( state.trigger === trigger ) {
			return;
		}

		stop( doc, state, 'showTimer' );

		// Already showing, or still warm from the last one in the same
		// switcher: no wait.
		var warm = Date.now() < state.warmUntil &&
			state.warmWrapper &&
			state.warmWrapper === trigger.closest( WRAPPER );

		if ( warm || state.trigger ) {
			open( doc, trigger );
			return;
		}

		state.showTimer = doc.defaultView.setTimeout( function () {
			open( doc, trigger );
		}, DELAY );
	}

	/*
	 * A hold that showed a tooltip was not a tap, so the press it would
	 * otherwise be must not reach the button. One shot, in capture, and dropped
	 * on the next turn if no click follows.
	 */
	function swallowClick( doc ) {
		function once( event ) {
			event.preventDefault();
			event.stopPropagation();
			doc.removeEventListener( 'click', once, true );
		}
		doc.addEventListener( 'click', once, true );
		doc.defaultView.setTimeout( function () {
			doc.removeEventListener( 'click', once, true );
		}, 0 );
	}

	function attach( doc ) {
		if ( ! doc || ! doc.documentElement || ! doc.body ) {
			return;
		}
		if ( doc.documentElement.dataset.axTsTooltip ) {
			return;
		}
		doc.documentElement.dataset.axTsTooltip = 'bound';

		function triggerFrom( event ) {
			return event.target.closest ? event.target.closest( TRIGGER ) : null;
		}

		doc.addEventListener( 'pointerover', function ( event ) {
			// Touch has its own way in, below.
			if ( 'touch' === event.pointerType ) {
				return;
			}
			var trigger = triggerFrom( event );
			if ( ! trigger || stateFor( doc ).suppressed === trigger ) {
				return;
			}
			show( doc, trigger );
		} );

		doc.addEventListener( 'pointerout', function ( event ) {
			var trigger = triggerFrom( event );
			if ( ! trigger ) {
				return;
			}
			// Moving within the trigger -- onto its icon, say -- is not leaving.
			if ( event.relatedTarget && trigger.contains( event.relatedTarget ) ) {
				return;
			}
			var state = stateFor( doc );
			if ( state.suppressed === trigger ) {
				state.suppressed = null;
			}
			scheduleClose( doc );
		} );

		doc.addEventListener( 'pointerdown', function ( event ) {
			var trigger = triggerFrom( event );
			if ( ! trigger ) {
				return;
			}
			var state = stateFor( doc );

			/*
			 * Touch: tap and hold shows it, which is M3's gesture there. The
			 * hold has to outlast a tap, or every press would flash a label on
			 * its way to activating the button.
			 */
			if ( 'touch' === event.pointerType ) {
				stop( doc, state, 'holdTimer' );
				state.held = null;
				state.holdTimer = doc.defaultView.setTimeout( function () {
					state.held = trigger;
					open( doc, trigger );
				}, HOLD );
				return;
			}

			/*
			 * Pointer: a press dismisses it. The button is about to do
			 * something, and a label describing what is already under the
			 * pointer stops being useful. It stays dismissed until the pointer
			 * leaves, so it does not reappear over a button just clicked.
			 */
			state.suppressed = trigger;
			close( doc );
		}, true );

		function endHold( event ) {
			var state = stateFor( doc );
			stop( doc, state, 'holdTimer' );
			if ( ! state.held ) {
				return;
			}
			state.held = null;
			if ( 'pointerup' === event.type ) {
				swallowClick( doc );
			}
			scheduleClose( doc );
		}
		doc.addEventListener( 'pointerup', endHold, true );
		doc.addEventListener( 'pointercancel', endHold, true );

		/*
		 * Keyboard focus shows it at once. The delay exists so a pointer
		 * crossing a control does not flash a label; a reader who has tabbed
		 * here has already asked. `:focus-visible` is what keeps this from
		 * firing after a mouse click, which focuses the button too.
		 */
		doc.addEventListener( 'focusin', function ( event ) {
			var trigger = triggerFrom( event );
			if ( ! trigger || ! trigger.matches( ':focus-visible' ) ) {
				return;
			}
			open( doc, trigger );
		} );

		doc.addEventListener( 'focusout', function ( event ) {
			if ( triggerFrom( event ) ) {
				scheduleClose( doc );
			}
		} );

		doc.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				close( doc );
			}
		} );

		// Anything that moves the trigger out from under it closes it at once
		// rather than chasing it.
		doc.addEventListener( 'scroll', function () {
			close( doc );
		}, true );
		doc.defaultView.addEventListener( 'resize', function () {
			close( doc );
		} );
	}

	namespace.tooltip = { attach: attach };
	attach( document );
} )();
