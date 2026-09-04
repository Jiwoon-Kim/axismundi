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
	var OVERLAY = '.wp-block-navigation__responsive-container.is-menu-open';

	// M3 places a plain tooltip above its target, 4dp away when the target has
	// a visual boundary -- every trigger here is a button, so always 4.
	var GAP = 4;
	// Keep it off the viewport edge when a trigger sits near one.
	var EDGE = 8;
	// First appearance waits; a second one straight after does not. Sweeping
	// Auto -> Light -> Dark should read as one gesture, not three waits.
	var DELAY = 700;
	var WARM = 400;

	var states = new WeakMap();

	function stateFor( doc ) {
		var state = states.get( doc );
		if ( ! state ) {
			state = {
				element: null,
				trigger: null,
				timer: 0,
				warmUntil: 0,
				warmWrapper: null,
				suppressed: null,
			};
			states.set( doc, state );
		}
		return state;
	}

	function element( doc, state ) {
		if ( ! state.element || ! state.element.ownerDocument ) {
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
	function hostFor( doc, trigger ) {
		return trigger.closest( OVERLAY ) || doc.body;
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

	function hide( doc ) {
		var state = stateFor( doc );
		doc.defaultView.clearTimeout( state.timer );
		state.timer = 0;

		if ( ! state.trigger ) {
			return;
		}

		state.warmUntil = Date.now() + WARM;
		state.warmWrapper = state.trigger.closest( WRAPPER );
		state.trigger = null;

		if ( state.element ) {
			state.element.classList.remove( 'is-open' );
		}
	}

	function open( doc, trigger ) {
		var text = labelFor( trigger );
		if ( ! text ) {
			return;
		}

		var state = stateFor( doc );
		var el = element( doc, state );

		el.textContent = text;
		hostFor( doc, trigger ).appendChild( el );
		state.trigger = trigger;
		place( el, trigger );
		el.classList.add( 'is-open' );
	}

	function show( doc, trigger ) {
		var state = stateFor( doc );
		if ( state.trigger === trigger ) {
			return;
		}

		doc.defaultView.clearTimeout( state.timer );

		// Still warm from the last one, and in the same switcher: no wait.
		var warm = Date.now() < state.warmUntil &&
			state.warmWrapper &&
			state.warmWrapper === trigger.closest( WRAPPER );

		if ( warm || state.trigger ) {
			open( doc, trigger );
			return;
		}

		state.timer = doc.defaultView.setTimeout( function () {
			open( doc, trigger );
		}, DELAY );
	}

	function attach( doc ) {
		if ( ! doc || ! doc.documentElement || ! doc.body ) {
			return;
		}
		if ( doc.documentElement.dataset.axTsTooltip ) {
			return;
		}
		doc.documentElement.dataset.axTsTooltip = 'bound';

		doc.addEventListener( 'pointerover', function ( event ) {
			// Not on touch. Material says not to expect a tooltip there, and a
			// tap would only flash it on the way to activating the button.
			if ( 'touch' === event.pointerType ) {
				return;
			}
			var trigger = event.target.closest && event.target.closest( TRIGGER );
			if ( ! trigger ) {
				return;
			}
			if ( stateFor( doc ).suppressed === trigger ) {
				return;
			}
			show( doc, trigger );
		} );

		doc.addEventListener( 'pointerout', function ( event ) {
			var trigger = event.target.closest && event.target.closest( TRIGGER );
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
			hide( doc );
		} );

		/*
		 * A press dismisses it. The button is about to do something, and a
		 * label describing what is already under the pointer stops being
		 * useful. It stays dismissed until the pointer leaves, so it does not
		 * reappear over a button that was just clicked.
		 */
		doc.addEventListener( 'pointerdown', function ( event ) {
			var trigger = event.target.closest && event.target.closest( TRIGGER );
			if ( ! trigger ) {
				return;
			}
			stateFor( doc ).suppressed = trigger;
			hide( doc );
		}, true );

		/*
		 * Keyboard focus shows it at once. The delay exists so a pointer
		 * crossing a control does not flash a label; a reader who has tabbed
		 * here has already asked. `:focus-visible` is what keeps this from
		 * firing after a mouse click, which focuses the button too.
		 */
		doc.addEventListener( 'focusin', function ( event ) {
			var trigger = event.target.closest && event.target.closest( TRIGGER );
			if ( ! trigger || ! trigger.matches( ':focus-visible' ) ) {
				return;
			}
			open( doc, trigger );
		} );

		doc.addEventListener( 'focusout', function ( event ) {
			if ( event.target.closest && event.target.closest( TRIGGER ) ) {
				hide( doc );
			}
		} );

		doc.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				hide( doc );
			}
		} );

		// Anything that moves the trigger out from under it closes it rather
		// than chasing it.
		doc.addEventListener( 'scroll', function () {
			hide( doc );
		}, true );
		doc.defaultView.addEventListener( 'resize', function () {
			hide( doc );
		} );
	}

	namespace.tooltip = { attach: attach };
	attach( document );
} )();
