/**
 * axismundi/dialog - runtime.
 *
 * The <dialog> opens and closes natively; this module adds what the platform
 * does not, and nothing it does:
 *
 *   invoker fallback  `commandfor` / `command` carried out where a browser does
 *                     not know them yet
 *   trigger state     `aria-expanded` on every trigger that opens a dialog
 *   initial focus     the dialog itself, not its first control
 *   motion            every presentation opens and closes with motion, through
 *                     the Web Animations API
 *   drag handle       the bottom sheet's two heights
 *
 * Delegated from the document, because the page-end render may add a surface
 * after this module has run.
 */

const HANDLE = '.wp-block-axismundi-dialog__drag-handle';
const HOST = 'dialog.wp-block-axismundi-dialog';

/**
 * Whether a value is one of this block's dialogs.
 *
 * @param {*} dialog An event target.
 * @return {boolean} Whether it is a Dialog block's <dialog>.
 */
function isHost( dialog ) {
	return typeof dialog?.matches === 'function' && dialog.matches( HOST );
}

/* ---- Motion -------------------------------------------------------------- */

/*
 * Dialogs take the Material Web dialog's choreography
 * (material-web dialog/internal/animations.ts):
 *
 *   open    dialog      translate -50px -> 0      enter curve and duration
 *           container   clipped from 35% of its height to all of it
 *           scrim       fades in, linear, the same duration
 *           content     held for the first 20%, fades in over half the duration
 *           actions     held for the first 50%, fades in over 0.6 of it
 *   close   dialog      translate 0 -> -50px      exit curve and duration
 *           container   clipped back to 35%
 *           scrim       fades out
 *           slots       fade out over two thirds of it
 *
 * The fractions are Material Web's own ratios (250 and 300 of 500ms), so the
 * slots keep pace when a surface's speed changes. Material Web animates an inner
 * container's height; this dialog has none - the inner blocks are its own
 * children - so the container is a clip-path, which reveals the content rather
 * than squashing it. The open clip ends outside the box, so the elevation shadow
 * is not cut off and then snapped back.
 *
 * Sheets slide in from the edge they sit on, and back out to it; a modal sheet's
 * scrim fades with them. M3 publishes no sheet choreography to copy, so this is
 * the plainest motion that reads as a sheet.
 *
 * The curves and durations are the surface's four motion properties
 * (--axismundi-dialog-motion-*, blocks/dialog/style.css), which point at the
 * theme's motion tokens and differ by presentation.
 *
 * WAAPI rather than CSS transitions, because the pieces start at different
 * offsets and closing has to finish before the dialog does: a native close would
 * hide it at once. So a close request - the `close` command, Escape, a scrim
 * click - is held (`preventDefault()`), the motion runs, and `close()` follows.
 * Measured in Chrome: the `command` and `cancel` events are both cancelable, a
 * `::backdrop` takes a WAAPI animation, and the tokens' cubic-bezier values are
 * accepted as `easing`. With reduced motion, or without WAAPI, every surface
 * opens and closes at once.
 */
const reducedMotion =
	typeof window.matchMedia === 'function'
		? window.matchMedia( '(prefers-reduced-motion: reduce)' )
		: { matches: false };

// A dialog's running motion: its animations, and whether they are closing it.
const motions = new WeakMap();

// Dialogs opened here, whose motion already started with the opening. Their
// `toggle` event comes a task later and must not start it a second time - it
// can arrive after a short motion has already finished.
const openedHere = new WeakSet();

/* ---- Standard side sheet: the page it shares ----------------------------- */

/*
 * M3's standard side sheet sits beside the page's content rather than over it,
 * on medium windows and wider; on a compact window it is a modal
 * (products/styleguide/_data/surface.yml, adaptive). So a docked standard side
 * sheet opened on a medium or wider window pushes the page: its measured width
 * becomes --axismundi-dialog-push on <html>, and the class for its edge gives
 * the block theme's root that much padding (blocks/dialog/style.css). The
 * padding moves with the sheet's own motion. Opened on a compact window, the
 * same sheet opens as a modal instead, and is restored when it closes. If the
 * window crosses that boundary while either is open, the sheet closes.
 *
 * A sheet whose Page setting is "move" (data-page-share="move") moves the whole
 * page aside instead, keeping its width, as a mobile off-canvas drawer does - on
 * every window, compact included, since that is what the mode is for. The page
 * is translated by the sheet's width, signed for the side the sheet is on.
 *
 * 599px is M3's compact window (products/styleguide/_data/layout.yml).
 */
const compactWindow =
	typeof window.matchMedia === 'function'
		? window.matchMedia( '(max-width: 599px)' )
		: { matches: false, addEventListener() {} };

// Sheets pushing the page: dialog -> its root, edge and size observer.
const pushes = new Map();

// Standard sheets opened as modals on a compact window: dialog -> what to restore.
const compactModals = new Map();

/**
 * Whether a surface is a standard side sheet.
 *
 * @param {Object} dialog The dialog element.
 * @return {boolean} Whether it is one.
 */
function isStandardSideSheet( dialog ) {
	return (
		dialog.dataset.presentation === 'sheet-side' &&
		dialog.dataset.renderMode === 'standard-sheet'
	);
}

/**
 * How a standard side sheet shares the page: resizing the content, or moving
 * the whole page aside.
 *
 * @param {Object} dialog The dialog element.
 * @return {string} `resize` or `move`.
 */
function pageShareOf( dialog ) {
	return dialog.dataset.pageShare === 'move' ? 'move' : 'resize';
}

/**
 * Whether a side sheet sits on the window's right. The inline end is the right
 * in a left-to-right page and the left in a right-to-left one.
 *
 * @param {Object} dialog The dialog element.
 * @return {boolean} Whether it is on the right.
 */
function sitsOnRight( dialog ) {
	const rtl =
		dialog.ownerDocument.defaultView.getComputedStyle( dialog )
			.direction === 'rtl';
	return ( dialog.dataset.edge !== 'start' ) !== rtl;
}

/**
 * Start sharing the page with an open standard side sheet.
 *
 * @param {Object} dialog The dialog element.
 */
function startPush( dialog ) {
	const doc = dialog.ownerDocument;
	const root = doc.querySelector( '.wp-site-blocks' );
	const mode = pageShareOf( dialog );
	if (
		! root ||
		pushes.has( dialog ) ||
		dialog.dataset.attachment === 'detached' ||
		( mode === 'resize' && compactWindow.matches )
	) {
		return;
	}
	const html = doc.documentElement;
	const edge = dialog.dataset.edge === 'start' ? 'start' : 'end';
	const toRight = sitsOnRight( dialog );
	const measure = () => {
		const width = dialog.getBoundingClientRect().width;
		if ( mode === 'move' ) {
			html.style.setProperty(
				'--axismundi-dialog-move',
				`${ toRight ? -width : width }px`
			);
		} else {
			html.style.setProperty( '--axismundi-dialog-push', `${ width }px` );
		}
	};
	measure();
	html.classList.add(
		mode === 'move'
			? 'axismundi-dialog-moved'
			: `axismundi-dialog-pushed-${ edge }`
	);
	const { ResizeObserver: Observer } = doc.defaultView;
	const observer =
		typeof Observer === 'function' ? new Observer( measure ) : null;
	observer?.observe( dialog );
	pushes.set( dialog, { root, edge, mode, toRight, observer } );
}

/**
 * Stop sharing the page with a sheet.
 *
 * @param {Object} dialog The dialog element.
 */
function endPush( dialog ) {
	const push = pushes.get( dialog );
	if ( ! push ) {
		return;
	}
	pushes.delete( dialog );
	push.observer?.disconnect();
	const html = dialog.ownerDocument.documentElement;
	if ( push.mode === 'move' ) {
		html.classList.remove( 'axismundi-dialog-moved' );
		html.style.removeProperty( '--axismundi-dialog-move' );
		return;
	}
	html.classList.remove( `axismundi-dialog-pushed-${ push.edge }` );
	if (
		! [ ...pushes.values() ].some( ( other ) => other.mode === 'resize' )
	) {
		html.style.removeProperty( '--axismundi-dialog-push' );
	}
}

/**
 * The page moving with a sheet that shares it: its padding when resizing, its
 * position when moving.
 *
 * @param {Object}  dialog  The dialog element.
 * @param {boolean} opening Whether the sheet is opening.
 * @param {Object}  timing  The sheet's own timing.
 * @return {Array} The animation, or nothing when the sheet does not share.
 */
function pushMotion( dialog, opening, timing ) {
	const push = pushes.get( dialog );
	if ( ! push ) {
		return [];
	}
	const width = dialog.getBoundingClientRect().width;
	let keyframes;
	if ( push.mode === 'move' ) {
		keyframes = [
			{ translate: '0px 0' },
			{ translate: `${ push.toRight ? -width : width }px 0` },
		];
	} else {
		const property =
			push.edge === 'start' ? 'paddingInlineStart' : 'paddingInlineEnd';
		keyframes = [
			{ [ property ]: '0px' },
			{ [ property ]: `${ width }px` },
		];
	}
	return [
		push.root.animate( opening ? keyframes : keyframes.reverse(), timing ),
	];
}

/**
 * Whether a dialog opens and closes with motion.
 *
 * @param {Object} dialog The dialog element.
 * @return {boolean} Whether to animate it.
 */
function hasMotion( dialog ) {
	return typeof dialog.animate === 'function' && ! reducedMotion.matches;
}

/**
 * A CSS time as milliseconds.
 *
 * @param {string} value `500ms` or `0.5s`.
 * @return {number} Milliseconds.
 */
function milliseconds( value ) {
	const number = parseFloat( value );
	return /[^m]s$/.test( value ) ? number * 1000 : number;
}

/**
 * The surface's motion: its four motion properties, resolved where it is, with
 * the basic dialog's values where the theme's tokens are missing.
 *
 * @param {Object} dialog The dialog element.
 * @return {{enter: string, enterDuration: number, exit: string, exitDuration: number}} The motion.
 */
function motionOf( dialog ) {
	const style = dialog.ownerDocument.defaultView.getComputedStyle( dialog );
	const read = ( name, fallback ) =>
		style.getPropertyValue( name ).trim() || fallback;
	return {
		enter: read(
			'--axismundi-dialog-motion-enter',
			'cubic-bezier(0.38, 1.21, 0.22, 1)'
		),
		enterDuration: milliseconds(
			read( '--axismundi-dialog-motion-enter-duration', '500ms' )
		),
		exit: read(
			'--axismundi-dialog-motion-exit',
			'cubic-bezier(0.3, 0, 0.8, 0.15)'
		),
		exitDuration: milliseconds(
			read( '--axismundi-dialog-motion-exit-duration', '150ms' )
		),
	};
}

/**
 * Stop a dialog's motion where it is.
 *
 * @param {Object} dialog The dialog element.
 */
function stopMotion( dialog ) {
	motions
		.get( dialog )
		?.animations.forEach( ( animation ) => animation.cancel() );
	motions.delete( dialog );
}

/**
 * Whether a surface is a sheet.
 *
 * @param {Object} dialog The dialog element.
 * @return {boolean} Whether it is a bottom or side sheet.
 */
function isSheet( dialog ) {
	return ( dialog.dataset.presentation || '' ).startsWith( 'sheet-' );
}

/**
 * Where a sheet sits when it is off-screen: past its own edge, by its own size.
 *
 * @param {Object} dialog The dialog element.
 * @return {string} A `translate` value.
 */
function offscreen( dialog ) {
	if ( dialog.dataset.presentation === 'sheet-bottom' ) {
		return '0 100%';
	}
	// A detached sheet also clears its 16px margin.
	const toRight = sitsOnRight( dialog );
	const distance =
		dialog.dataset.attachment === 'detached' ? '100% + 16px' : '100%';
	return toRight ? `calc(${ distance }) 0` : `calc(-1 * (${ distance })) 0`;
}

/**
 * The dialog container's clip at rest and folded, with its own corners.
 *
 * @param {Object} dialog The dialog element.
 * @return {{open: string, folded: string}} clip-path values.
 */
function clips( dialog ) {
	const radius =
		dialog.ownerDocument.defaultView.getComputedStyle( dialog )
			.borderTopLeftRadius || '0px';
	return {
		open: `inset(-64px -64px -64px -64px round ${ radius })`,
		folded: `inset(0px 0px 65% 0px round ${ radius })`,
	};
}

/**
 * The scrim's fade, for a modal surface.
 *
 * @param {Object} dialog  The dialog element.
 * @param {Array}  opacity From and to.
 * @param {Object} timing  Duration, and fill when closing.
 * @return {Array} The animation, or nothing.
 */
function scrim( dialog, opacity, timing ) {
	if ( ! dialog.matches( ':modal' ) ) {
		return [];
	}
	return [
		dialog.animate(
			opacity.map( ( value ) => ( { opacity: value } ) ),
			{ ...timing, easing: 'linear', pseudoElement: '::backdrop' }
		),
	];
}

/**
 * A slot's fade in, held for part of it first.
 *
 * @param {Object} slot     The slot element.
 * @param {number} hold     The share of the fade spent at 0.
 * @param {number} duration Milliseconds.
 * @return {Object} The animation.
 */
function fadeIn( slot, hold, duration ) {
	return slot.animate(
		[
			{ opacity: 0, offset: 0 },
			{ opacity: 0, offset: hold },
			{ opacity: 1, offset: 1 },
		],
		{ duration, easing: 'linear' }
	);
}

/**
 * Keep a set of animations as a dialog's motion, and act when they finish.
 *
 * @param {Object}   dialog     The dialog element.
 * @param {Array}    animations The animations.
 * @param {boolean}  closing    Whether they close the dialog.
 * @param {Function} done       Called when they all finish, unless replaced.
 */
function track( dialog, animations, closing, done ) {
	motions.set( dialog, { animations, closing } );
	Promise.all( animations.map( ( animation ) => animation.finished ) )
		.then( () => {
			// Replaced on the way - reopened, or closed some other way.
			if ( motions.get( dialog )?.animations !== animations ) {
				return;
			}
			motions.delete( dialog );
			done();
		} )
		.catch( () => {} );
}

/**
 * Run the opening motion on a dialog that has just opened.
 *
 * @param {Object} dialog The dialog element.
 */
function animateOpen( dialog ) {
	stopMotion( dialog );
	const { enter, enterDuration } = motionOf( dialog );
	const animations = [];

	if ( isSheet( dialog ) ) {
		animations.push(
			dialog.animate(
				[ { translate: offscreen( dialog ) }, { translate: '0 0' } ],
				{ duration: enterDuration, easing: enter }
			),
			...pushMotion( dialog, true, {
				duration: enterDuration,
				easing: enter,
			} )
		);
	} else {
		const clip = clips( dialog );
		animations.push(
			dialog.animate(
				[ { translate: '0 -50px' }, { translate: '0 0' } ],
				{
					duration: enterDuration,
					easing: enter,
				}
			),
			dialog.animate(
				[ { clipPath: clip.folded }, { clipPath: clip.open } ],
				{ duration: enterDuration, easing: enter }
			)
		);
		Array.from( dialog.children ).forEach( ( slot ) =>
			animations.push(
				slot.matches( 'footer' )
					? fadeIn( slot, 0.5, enterDuration * 0.6 )
					: fadeIn( slot, 0.2, enterDuration * 0.5 )
			)
		);
	}
	animations.push(
		...scrim( dialog, [ 0, 1 ], { duration: enterDuration } )
	);

	track( dialog, animations, false, () => {} );
}

/**
 * Run the closing motion, then close the dialog.
 *
 * @param {Object}           dialog      The dialog element.
 * @param {string|undefined} returnValue The dialog's return value, if any.
 */
function animateClose( dialog, returnValue ) {
	if ( motions.get( dialog )?.closing ) {
		return;
	}
	stopMotion( dialog );
	const { exit, exitDuration } = motionOf( dialog );
	// `forwards`, so the surface stays gone between the last frame and close().
	const timing = { duration: exitDuration, easing: exit, fill: 'forwards' };
	const animations = [];

	if ( isSheet( dialog ) ) {
		animations.push(
			dialog.animate(
				[ { translate: '0 0' }, { translate: offscreen( dialog ) } ],
				timing
			),
			...pushMotion( dialog, false, timing )
		);
	} else {
		const clip = clips( dialog );
		animations.push(
			dialog.animate(
				[ { translate: '0 0' }, { translate: '0 -50px' } ],
				timing
			),
			dialog.animate(
				[ { clipPath: clip.open }, { clipPath: clip.folded } ],
				timing
			)
		);
		Array.from( dialog.children ).forEach( ( slot ) =>
			animations.push(
				slot.animate( [ { opacity: 1 }, { opacity: 0 } ], {
					duration: ( exitDuration * 2 ) / 3,
					easing: 'linear',
					fill: 'forwards',
				} )
			)
		);
	}
	animations.push(
		...scrim( dialog, [ 1, 0 ], {
			duration: exitDuration,
			fill: 'forwards',
		} )
	);

	track( dialog, animations, true, () => {
		dialog.close( returnValue );
		// Now, not in the `close` event a task later: the page's padding would
		// otherwise return to the sheet's width for a frame once its animation
		// is cancelled.
		endPush( dialog );
		// After close(), so the surface is already hidden when its styles return
		// to rest.
		animations.forEach( ( animation ) => animation.cancel() );
	} );
}

/**
 * Open a dialog, with motion where it has any.
 *
 * @param {Object}  dialog The dialog element.
 * @param {boolean} modal  Whether to open it as a modal.
 */
function openDialog( dialog, modal ) {
	if ( dialog.open ) {
		return;
	}
	openedHere.add( dialog );

	// A standard side sheet on a compact window is a modal one (M3), restored
	// when it closes.
	let asModal = modal;
	if (
		! modal &&
		isStandardSideSheet( dialog ) &&
		pageShareOf( dialog ) === 'resize' &&
		compactWindow.matches
	) {
		compactModals.set( dialog, {
			renderMode: dialog.dataset.renderMode,
			modality: dialog.dataset.modality,
		} );
		dialog.dataset.renderMode = 'modal-dialog';
		dialog.dataset.modality = 'modal';
		asModal = true;
	}

	if ( asModal ) {
		dialog.showModal();
	} else {
		dialog.show();
		if ( isStandardSideSheet( dialog ) ) {
			startPush( dialog );
		}
	}
	if ( hasMotion( dialog ) ) {
		animateOpen( dialog );
	}
}

/**
 * Close a dialog, with motion where it has any.
 *
 * @param {Object}           dialog      The dialog element.
 * @param {string|undefined} returnValue The dialog's return value, if any.
 */
function closeDialog( dialog, returnValue ) {
	if ( ! dialog.open ) {
		return;
	}
	if ( hasMotion( dialog ) ) {
		animateClose( dialog, returnValue );
	} else {
		dialog.close( returnValue );
	}
}

/**
 * A command button's value as the dialog's return value, as the native `close`
 * command passes it: only when the button has one.
 *
 * @param {Object|null} button The invoking button.
 * @return {string|undefined} The value.
 */
function returnValueOf( button ) {
	return button?.hasAttribute( 'value' ) ? button.value : undefined;
}

/*
 * Invoker commands arrive at the dialog as a `command` event before the browser
 * acts on them, and the event is cancelable. `command` does not bubble, so it is
 * heard in the capture phase.
 *
 *   --toggle    a standard sheet's trigger (includes/surface.php). A custom
 *               command the browser does nothing with: the sheet opens here,
 *               or closes when it is already open - a standard sheet leaves
 *               its trigger usable, so the trigger is its toggle.
 *   show-modal  built in. Taken over only for motion, so the motion starts in
 *   close       the same task as the opening - no frame of the finished surface
 *               first - and closing waits for it.
 */
document.addEventListener(
	'command',
	( event ) => {
		const dialog = event.target;
		if ( ! isHost( dialog ) ) {
			return;
		}
		if ( event.command === '--toggle' ) {
			if ( dialog.open ) {
				closeDialog( dialog );
			} else {
				openDialog( dialog, false );
			}
			return;
		}
		if ( ! hasMotion( dialog ) ) {
			return;
		}
		if ( event.command === 'show-modal' ) {
			event.preventDefault();
			openDialog( dialog, true );
		} else if ( event.command === 'close' ) {
			event.preventDefault();
			closeDialog( dialog, returnValueOf( event.source ) );
		}
	},
	true
);

/*
 * Escape, and a scrim click where `closedby` allows one, request a close with a
 * cancelable `cancel` event. Held while the motion runs. A second Escape arrives
 * uncancelable and closes at once, which is the browser's escape hatch and is
 * left alone.
 */
document.addEventListener(
	'cancel',
	( event ) => {
		const dialog = event.target;
		if (
			! isHost( dialog ) ||
			! hasMotion( dialog ) ||
			! event.cancelable
		) {
			return;
		}
		event.preventDefault();
		closeDialog( dialog );
	},
	true
);

/*
 * Invoker commands where a browser does not know them yet - a button without
 * `commandForElement` - carried out here, for this block's dialogs only.
 */
document.addEventListener( 'click', ( event ) => {
	const button =
		typeof event.target?.closest === 'function'
			? event.target.closest( 'button[commandfor]' )
			: null;
	if ( ! button || 'commandForElement' in button ) {
		return;
	}
	const dialog = document.getElementById(
		button.getAttribute( 'commandfor' )
	);
	if ( ! isHost( dialog ) ) {
		return;
	}
	const command = button.getAttribute( 'command' );
	if ( command === '--toggle' && dialog.open ) {
		closeDialog( dialog );
	} else if ( command === 'show-modal' || command === '--toggle' ) {
		openDialog( dialog, command === 'show-modal' );
	} else if ( command === 'close' ) {
		closeDialog( dialog, returnValueOf( button ) );
	}
} );

/* ---- Trigger state and focus --------------------------------------------- */

/**
 * Mirror a dialog's open state onto every trigger that opens it.
 *
 * Invoker commands open and close the <dialog>, but do not report the state on
 * the button, so `aria-expanded` - which assistive technology reads, and which
 * gives the button its selected look (assets/button.css) - is kept here.
 *
 * @param {Object}  dialog The dialog element.
 * @param {boolean} open   Whether it is open.
 */
function syncTriggers( dialog, open ) {
	if ( ! dialog.id ) {
		return;
	}
	// Compared rather than put into a selector, so no id needs escaping.
	document
		.querySelectorAll( 'button[commandfor][aria-expanded]' )
		.forEach( ( button ) => {
			if ( button.getAttribute( 'commandfor' ) === dialog.id ) {
				button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			}
		} );
}

// `toggle` fires on a <dialog> when it opens and when it closes, whatever
// opened it. It does not bubble, so it is heard in the capture phase.
document.addEventListener(
	'toggle',
	( event ) => {
		const dialog = event.target;
		if ( ! isHost( dialog ) ) {
			return;
		}
		syncTriggers( dialog, dialog.open );

		if ( ! dialog.open ) {
			// Closed some other way while moving - a script, a form: stop there.
			stopMotion( dialog );
			afterClose( dialog );
			return;
		}

		// Opened by a script rather than a command: animate from here, a task
		// later than a command would. One opened here already has its motion.
		if ( openedHere.has( dialog ) ) {
			openedHere.delete( dialog );
		} else if ( hasMotion( dialog ) && ! motions.has( dialog ) ) {
			animateOpen( dialog );
		}

		// Initial focus on the dialog, not its first control (render.php). An
		// author's own `autofocus` inside - a field to type into - is kept.
		const active = dialog.ownerDocument.activeElement;
		if (
			dialog.contains( active ) &&
			active !== dialog &&
			! active.hasAttribute( 'autofocus' )
		) {
			dialog.focus( { preventScroll: true } );
		}
	},
	true
);

/* ---- Drag handle --------------------------------------------------------- */

/*
 * A bottom sheet opens no higher than half the window (M3 bottom sheet
 * guidelines); its drag handle raises it to the window's full height and
 * lowers it again, and dragging it down closes it.
 *
 *   fling     a quick flick decides by direction, however short: up raises the
 *             sheet to full height, down takes it one step down from where the
 *             drag began - full to initial, initial to closed. Quick is faster
 *             than 0.5px per millisecond over the last 100ms before release.
 *   drag      otherwise the release position decides. The sheet's height
 *             follows the pointer; released below 30% of the window it closes,
 *             above 70% it fills the window, in between it returns to its
 *             initial height - moving there from where the pointer left it.
 *   click     the handle is a <button>, so a click, Enter and Space all arrive
 *             as one click and toggle the two heights: the single-pointer and
 *             keyboard alternative to dragging that M3's accessibility page
 *             requires. A press that moves less than 4px is a click; a drag
 *             that just ended is not.
 *
 * The height is state on the <dialog> (data-sheet-height), read by
 * blocks/dialog/style.css; the handle mirrors it in aria-expanded. While
 * dragging, the height is written inline, with the sheet's limit and
 * transition set aside. Closing the sheet returns it to its initial height, so
 * it reopens the way M3 opens one.
 */
const DRAG_SLOP = 4;
const CLOSE_BELOW = 0.3;
const EXPAND_ABOVE = 0.7;
// Pixels per millisecond, and the time it is measured over before release.
const FLING_SPEED = 0.5;
const FLING_WINDOW = 100;

// Handles whose drag has just ended: the click that follows is not a toggle.
const dragged = new WeakSet();

/**
 * Set a bottom sheet's height state, and its handle's.
 *
 * @param {Object}  dialog   The dialog element.
 * @param {Object}  handle   The drag handle.
 * @param {boolean} expanded Whether the sheet is raised to full height.
 */
function setSheetHeight( dialog, handle, expanded ) {
	if ( expanded ) {
		dialog.dataset.sheetHeight = 'expanded';
	} else {
		delete dialog.dataset.sheetHeight;
	}
	handle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
}

/**
 * Clear what a drag wrote on the sheet.
 *
 * @param {Object} dialog The dialog element.
 */
function clearDrag( dialog ) {
	dialog.style.removeProperty( 'block-size' );
	dialog.style.removeProperty( 'max-block-size' );
	dialog.style.removeProperty( 'transition' );
}

/**
 * Settle a sheet where a drag released it: close it, raise it, or return it.
 *
 * @param {Object}  dialog      The dialog element.
 * @param {Object}  handle      The drag handle.
 * @param {number}  velocity    Pixels per millisecond at release; down is positive.
 * @param {boolean} wasExpanded Whether the sheet was at full height when the drag began.
 */
function settle( dialog, handle, velocity, wasExpanded ) {
	const view = dialog.ownerDocument.defaultView;
	const from = dialog.getBoundingClientRect().height;
	const share = from / view.innerHeight;

	// A fling decides by direction, not by where it ended.
	if ( velocity <= -FLING_SPEED ) {
		moveSheet( dialog, handle, true, from );
		return;
	}
	if ( velocity >= FLING_SPEED ) {
		if ( wasExpanded ) {
			moveSheet( dialog, handle, false, from );
		} else {
			closeDialog( dialog );
		}
		return;
	}

	// Closed from where it is; afterClose() clears the inline height.
	if ( share < CLOSE_BELOW ) {
		closeDialog( dialog );
		return;
	}

	moveSheet( dialog, handle, share > EXPAND_ABOVE, from );
}

/**
 * Move a bottom sheet to a height state, from the height it has now.
 *
 * The state decides the height (blocks/dialog/style.css), so it is set and the
 * height it gives is measured; the sheet is then animated there from where it
 * was. Used by a click, and by a drag's release from wherever the pointer left
 * the sheet.
 *
 * @param {Object}  dialog   The dialog element.
 * @param {Object}  handle   The drag handle.
 * @param {boolean} expanded Whether to raise the sheet to full height.
 * @param {number}  from     The sheet's height now, in pixels.
 */
function moveSheet( dialog, handle, expanded, from ) {
	const view = dialog.ownerDocument.defaultView;
	dialog.style.transition = 'none';
	setSheetHeight( dialog, handle, expanded );
	dialog.style.removeProperty( 'block-size' );
	dialog.style.removeProperty( 'max-block-size' );
	const to = dialog.getBoundingClientRect().height;

	if ( ! hasMotion( dialog ) || Math.abs( to - from ) < 1 ) {
		clearDrag( dialog );
		return;
	}
	const style = view.getComputedStyle( dialog );
	const animation = dialog.animate(
		[
			{ blockSize: `${ from }px`, maxBlockSize: 'none' },
			{ blockSize: `${ to }px`, maxBlockSize: 'none' },
		],
		{
			duration: milliseconds(
				style
					.getPropertyValue( '--md-sys-motion-duration-medium1' )
					.trim() || '250ms'
			),
			easing:
				style
					.getPropertyValue( '--axismundi-dialog-motion-enter' )
					.trim() || 'cubic-bezier(0.05, 0.7, 0.1, 1)',
		}
	);
	animation.finished
		.then( () => clearDrag( dialog ) )
		.catch( () => clearDrag( dialog ) );
}

document.addEventListener( 'pointerdown', ( event ) => {
	const handle =
		typeof event.target?.closest === 'function'
			? event.target.closest( HANDLE )
			: null;
	const dialog = handle ? handle.closest( HOST ) : null;
	if ( ! dialog || ! dialog.open || event.button !== 0 ) {
		return;
	}
	const view = dialog.ownerDocument.defaultView;
	const startY = event.clientY;
	const startHeight = dialog.getBoundingClientRect().height;
	const wasExpanded = dialog.dataset.sheetHeight === 'expanded';
	// The pointer's recent positions, for the speed at release.
	const samples = [ { time: event.timeStamp, y: event.clientY } ];
	let dragging = false;

	// Captured, so the drag keeps arriving when the pointer leaves the handle.
	// setPointerCapture throws for a pointer the browser is not tracking - a
	// synthetic event - and the drag still works without it.
	try {
		handle.setPointerCapture( event.pointerId );
	} catch {}

	const move = ( moveEvent ) => {
		const distance = moveEvent.clientY - startY;
		samples.push( { time: moveEvent.timeStamp, y: moveEvent.clientY } );
		while (
			samples.length > 2 &&
			moveEvent.timeStamp - samples[ 0 ].time > FLING_WINDOW
		) {
			samples.shift();
		}
		if ( ! dragging ) {
			if ( Math.abs( distance ) < DRAG_SLOP ) {
				return;
			}
			dragging = true;
			dialog.style.transition = 'none';
			dialog.style.maxBlockSize = 'none';
		}
		const height = Math.min(
			Math.max( startHeight - distance, 0 ),
			view.innerHeight
		);
		dialog.style.blockSize = `${ height }px`;
	};
	const end = ( endEvent ) => {
		handle.removeEventListener( 'pointermove', move );
		handle.removeEventListener( 'pointerup', end );
		handle.removeEventListener( 'pointercancel', end );
		if ( ! dragging ) {
			return;
		}
		dragged.add( handle );
		// Speed over the recent samples up to the release. A cancelled pointer
		// was not released by the user, so it has no fling.
		let velocity = 0;
		if ( endEvent.type === 'pointerup' ) {
			const first = samples[ 0 ];
			velocity =
				( endEvent.clientY - first.y ) /
				Math.max( endEvent.timeStamp - first.time, 1 );
		}
		settle( dialog, handle, velocity, wasExpanded );
	};
	handle.addEventListener( 'pointermove', move );
	handle.addEventListener( 'pointerup', end );
	handle.addEventListener( 'pointercancel', end );
} );

document.addEventListener( 'click', ( event ) => {
	const handle =
		typeof event.target?.closest === 'function'
			? event.target.closest( HANDLE )
			: null;
	const dialog = handle ? handle.closest( HOST ) : null;
	if ( ! dialog ) {
		return;
	}
	if ( dragged.has( handle ) ) {
		dragged.delete( handle );
		return;
	}
	moveSheet(
		dialog,
		handle,
		dialog.dataset.sheetHeight !== 'expanded',
		dialog.getBoundingClientRect().height
	);
} );

/**
 * Put back what an open surface changed, once it has closed: the sheet's
 * height, its triggers, the page it pushed, and a compact modal's own
 * presentation. Safe to run twice.
 *
 * Called from `toggle` and from `close`: measured in Chrome, closing a dialog
 * fired `toggle` but no `close` event, and a browser without `toggle` for
 * dialogs still fires `close`.
 *
 * @param {Object} dialog The dialog element.
 */
function afterClose( dialog ) {
	delete dialog.dataset.sheetHeight;
	dialog.querySelector( HANDLE )?.setAttribute( 'aria-expanded', 'false' );
	clearDrag( dialog );
	syncTriggers( dialog, false );

	endPush( dialog );
	const standard = compactModals.get( dialog );
	if ( standard ) {
		compactModals.delete( dialog );
		dialog.dataset.renderMode = standard.renderMode;
		dialog.dataset.modality = standard.modality;
	}
}

// `close` does not bubble, so it is heard in the capture phase.
document.addEventListener(
	'close',
	( event ) => {
		if ( isHost( event.target ) ) {
			afterClose( event.target );
		}
	},
	true
);

// Crossing M3's compact boundary changes what a resizing standard side sheet
// is, so one that is open - resizing the page, or opened as a modal - closes. A
// sheet that moves the page behaves the same on both sides, and stays.
compactWindow.addEventListener( 'change', () => {
	[
		...[ ...pushes ]
			.filter( ( [ , push ] ) => push.mode === 'resize' )
			.map( ( [ dialog ] ) => dialog ),
		...compactModals.keys(),
	].forEach( ( dialog ) => closeDialog( dialog ) );
} );
