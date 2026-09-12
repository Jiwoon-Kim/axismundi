/**
 * Plain tooltip (M3 34.2) for a control that shows no text of its own.
 *
 * An icon button has no visible label - that is its anatomy - so M3 asks for a
 * tooltip on hover and focus. The text is the control's own accessible name,
 * read from its `.screen-reader-text` span at open time.
 *
 * The popup is VISUAL ONLY: `aria-hidden`, no `role="tooltip"`, nothing points
 * at it, no `aria-describedby`. The button's accessible name already says what
 * the tooltip says, so announcing it again would only repeat it. WordPress's
 * newer Tooltip component follows the same rule; neither of the two it ships is
 * usable here, both being React and neither loaded on the front end.
 *
 * INDEPENDENT OF THE THEME SWITCHER'S. The theme switcher has a tooltip of its
 * own and must keep working without this plugin, so the two are separate
 * implementations of the same M3 behaviour rather than one shared runtime. If a
 * tooltip ever lands in core, both collapse into it.
 *
 * The one place they cannot agree anyway is where the popup goes. This plugin's
 * buttons can sit inside an open `<dialog>`, which is in the top layer: a
 * tooltip parented on `<body>` would be painted underneath it, whatever its
 * z-index. So this one is a popover - also top layer, and above the dialog that
 * opened before it - which needs no reparenting and no watching of the box it
 * sits in. Where `popover` is missing it falls back to `<body>`, which is right
 * everywhere except inside a modal dialog.
 */
( function () {
	var TRIGGER = '[data-ax-tooltip]';
	var LABEL = '.screen-reader-text';
	// The nearest thing a "second tooltip straight after" can be measured
	// against: two icon buttons in the same group are one sweep of the eye.
	var GROUP = '.wp-block-buttons';

	// M3 places a plain tooltip above its target, 4dp away when the target has
	// a visual boundary. Every trigger here is a button, so always 4.
	var GAP = 4;
	// Keep it off the viewport edge when a trigger sits near one.
	var EDGE = 8;
	// First appearance waits; a second one straight after does not.
	var DELAY = 700;
	var WARM = 400;
	// M3: a tooltip is transient, and goes 1.5s after the pointer leaves.
	var LINGER = 1500;
	// M3 opens a tooltip on touch by tap and hold, which has to be told apart
	// from a tap that means to press the button.
	var HOLD = 500;

	var supportsPopover =
		typeof HTMLElement !== 'undefined' &&
		Object.prototype.hasOwnProperty.call( HTMLElement.prototype, 'popover' );

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
				warmUntil: 0,
				warmGroup: null,
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

	// One element per document, reused. Never more: only one tooltip is ever
	// open, which is M3's rule as well as this one's bookkeeping.
	function element( doc, state ) {
		if ( ! state.element ) {
			state.element = doc.createElement( 'div' );
			state.element.className = 'ax-tooltip';
			state.element.setAttribute( 'aria-hidden', 'true' );
			if ( supportsPopover ) {
				// Manual, not auto: an auto popover closes on any click
				// outside it, and this one is dismissed by leaving its
				// trigger. It also must not join the light-dismiss stack a
				// dialog is on.
				state.element.popover = 'manual';
			}
			doc.body.appendChild( state.element );
		}
		return state.element;
	}

	function labelFor( trigger ) {
		var label = trigger.querySelector( LABEL );
		return label ? ( label.textContent || '' ).trim() : '';
	}

	function place( el, trigger ) {
		var target = trigger.getBoundingClientRect();
		var box = el.getBoundingClientRect();
		var view = el.ownerDocument.defaultView;

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

		el.style.top = Math.round( top ) + 'px';
		el.style.left = Math.round( left ) + 'px';
	}

	function close( doc ) {
		var state = stateFor( doc );

		stop( doc, state, 'showTimer' );
		stop( doc, state, 'closeTimer' );

		if ( ! state.trigger ) {
			return;
		}

		state.warmUntil = Date.now() + WARM;
		state.warmGroup = state.trigger.closest( GROUP );
		state.trigger = null;

		if ( state.element ) {
			state.element.classList.remove( 'is-open' );
			if ( supportsPopover ) {
				state.element.hidePopover();
			}
		}
	}

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

		// Whatever else was queued or showing loses: a new tooltip closes any
		// open one, and a pending one for a trigger the reader has already left
		// must not surface behind it.
		stop( doc, state, 'showTimer' );
		stop( doc, state, 'closeTimer' );

		var el = element( doc, state );
		el.textContent = text;
		state.trigger = trigger;

		// Shown before measuring: a popover has no box until it is in the top
		// layer, and a hidden one would place at 0x0.
		//
		// Left and rejoined even when it is already open. The top layer is
		// ordered by when something joined it, so a tooltip that was up before
		// a <dialog> opened would be painted under it - measured, with the
		// dialog on top. Rejoining puts it above whatever is there now. The
		// fade is on `is-open`, which does not move, so this does not flicker.
		if ( supportsPopover ) {
			if ( el.matches( ':popover-open' ) ) {
				el.hidePopover();
			}
			el.showPopover();
		}
		place( el, trigger );
		el.classList.add( 'is-open' );
	}

	function show( doc, trigger ) {
		var state = stateFor( doc );

		// Back on a trigger whose tooltip is still lingering: keep it.
		stop( doc, state, 'closeTimer' );

		if ( state.trigger === trigger ) {
			return;
		}

		stop( doc, state, 'showTimer' );

		// Already showing, or still warm from the last one in the same group:
		// no wait.
		var warm =
			Date.now() < state.warmUntil &&
			state.warmGroup &&
			state.warmGroup === trigger.closest( GROUP );

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
		// NOT `axTooltip`: that writes data-ax-tooltip on <html>, which the
		// trigger selector then matches, so every closest() in the document
		// finds the root and the whole page becomes one trigger showing the
		// first .screen-reader-text on it - the skip link. Measured.
		if ( doc.documentElement.dataset.axTooltipBound ) {
			return;
		}
		doc.documentElement.dataset.axTooltipBound = 'bound';

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

		doc.addEventListener(
			'pointerdown',
			function ( event ) {
				var trigger = triggerFrom( event );
				if ( ! trigger ) {
					return;
				}
				var state = stateFor( doc );

				/*
				 * Touch: tap and hold shows it, which is M3's gesture there.
				 * The hold has to outlast a tap, or every press would flash a
				 * label on its way to activating the button.
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
				 * pointer stops being useful. It stays dismissed until the
				 * pointer leaves, so it does not reappear over a button just
				 * clicked - which for a toggle is exactly where the pointer is.
				 */
				state.suppressed = trigger;
				close( doc );
			},
			true
		);

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
		// rather than chasing it. A dialog closing takes its buttons with it,
		// and the focus it restores fires focusout here first.
		doc.addEventListener(
			'scroll',
			function () {
				close( doc );
			},
			true
		);
		doc.defaultView.addEventListener( 'resize', function () {
			close( doc );
		} );
	}

	/*
	 * The block editor draws the canvas in an iframe of its own, so the front
	 * end's one document is not the only one. `attach` is idempotent and takes a
	 * document for that reason: the editor bridge (assets/editor-tooltip.js)
	 * calls it for each canvas it finds, so an author sees the same tooltip the
	 * page will show.
	 */
	var namespace = ( window.axismundiDialogs = window.axismundiDialogs || {} );
	namespace.tooltip = { attach: attach };

	attach( document );
} )();
