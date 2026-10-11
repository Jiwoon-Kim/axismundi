/**
 * Surface -- the non-visual core every dialog and sheet row shares.
 *
 * M3 publishes Dialog, Bottom sheet and Side sheet as three components, and
 * this app renders them as three components too. What they share is not an
 * anatomy, it is a lifecycle: a trigger opens a surface, it takes focus, it
 * holds the page out or shares space with it, and it animates back out. That
 * lifecycle lives here once.
 *
 * `products/wordpress/plugins/axismundi/docs/DECISION-FRONTEND-OVERLAY-ESCAPE-AND-PUSH.md`
 * is the record. Section 8 is why the split is three files rather than one
 * component with a `presentation` prop: the block unified its host because
 * saved content and Site Editor transforms required one block name, and
 * neither exists here.
 *
 * HOST. A native `<dialog>` in every row. The top layer, `::backdrop`, Escape,
 * an inert background and focus restoration are all the platform's, and
 * re-implementing them in JS is what every hand-rolled modal gets wrong.
 *
 * NO PORTAL. Top-layer placement is independent of DOM position, so a modal
 * opens where it is declared. A portal is the tool for escaping an ancestor's
 * clipping, and section 2 of the record establishes there is none to escape.
 * Not using one is also what lets a standard sheet stay in the flow and take a
 * grid column (section 4).
 *
 * MODALITY DECIDES ESCAPE, NOT PRESENTATION. `showModal()` goes to the top
 * layer; `show()` stays in the flow. That is the whole difference, which is why
 * a standard side sheet becoming modal on a compact window moves no DOM -- the
 * same element is opened the other way (section 5).
 *
 * CLOSING IS ONE PATH. The `cancel` event (Escape, light dismiss) is held with
 * `preventDefault()` and turned into an `onClose` call; the caller lowers
 * `open`; the effect plays the exit and only then calls `close()`. So the
 * animation cannot be skipped by one of the closing routes, and a caller that
 * must not be dismissed simply does not lower `open`.
 */

import { useEffect, useRef, useState } from '@wordpress/element';
import warning from '@wordpress/warning';

const MODALITIES = [ 'modal', 'standard' ];

/*
 * M3's layout pages name three ways a pane can be shown, and they are not the
 * same question as modality. Modality decides the top layer, the scrim, the
 * scroll lock and whether the page behind is inert; display decides where the
 * box sits.
 *
 * Conflating them was a real defect: this file used to place every standard
 * surface in the flow, which is right for a side sheet and wrong for a bottom
 * sheet. M3 names the bottom sheet as its example of a docked pane -- "pinned
 * to the edge of a window", with the page still usable above it -- so a
 * standard bottom sheet stays fixed to the window edge and shares no space.
 * The bottom sheet had to override the shared layer to get that, which is the
 * shape of a contract that is wrong rather than a row that is special.
 *
 * `products/styleguide/_data/surface.yml` `react_surface.pane_display` holds
 * the mapping.
 */
const DISPLAYS = [ 'floating', 'docked', 'co-planar' ];

/*
 * HTML's `closedby`, which maps one-to-one onto M3's dismissal rule. See
 * `products/styleguide/_components/surface.md` decision 4 for the table.
 */
const CLOSED_BY = [ 'any', 'closerequest', 'none' ];

/*
 * Sequential-focus candidates, used to answer "does this region hold anything
 * a keyboard can reach" -- not to build a focus trap. A modal dialog's trap is
 * the browser's.
 */
export const FOCUSABLE_SELECTOR = [
	'a[href]',
	'button:not( [disabled] )',
	'input:not( [disabled] ):not( [type="hidden"] )',
	'select:not( [disabled] )',
	'textarea:not( [disabled] )',
	'details > summary',
	'audio[controls]',
	'video[controls]',
	'[contenteditable]:not( [contenteditable="false"] )',
	'[tabindex]:not( [tabindex^="-"] )',
].join( ', ' );

function oneOf( value, allowed, fallback, name ) {
	if ( allowed.includes( value ) ) {
		return value;
	}

	warning( `Surface: unsupported ${ name } "${ value }". Using "${ fallback }".` );

	return fallback;
}

/**
 * Reads a duration custom property as milliseconds.
 *
 * The theme publishes durations in both units -- `150ms` on the duration
 * scale, `500ms` on the curve scale -- and a token that is missing resolves to
 * an empty string rather than a number, so this returns 0 and the surface
 * opens at once instead of hanging on an animation that never ran.
 *
 * @param {CSSStyleDeclaration} styles Computed style of the surface.
 * @param {string}              name   Custom property name.
 * @return {number} Milliseconds.
 */
function readDuration( styles, name ) {
	const raw = styles.getPropertyValue( name ).trim();

	if ( raw.endsWith( 'ms' ) ) {
		return parseFloat( raw ) || 0;
	}

	if ( raw.endsWith( 's' ) ) {
		return ( parseFloat( raw ) || 0 ) * 1000;
	}

	return 0;
}

/**
 * The timing each row declares in CSS, so a theme changes a surface's speed in
 * one place and this file never names a motion token.
 *
 * @param {HTMLDialogElement} dialog Surface host.
 * @return {{enter: Object, exit: Object}} Easing and duration for both directions.
 */
function readMotion( dialog ) {
	const styles = getComputedStyle( dialog );

	return {
		enter: {
			easing: styles.getPropertyValue( '--ax-surface-motion-enter' ).trim() || 'linear',
			duration: readDuration( styles, '--ax-surface-motion-enter-duration' ),
		},
		exit: {
			easing: styles.getPropertyValue( '--ax-surface-motion-exit' ).trim() || 'linear',
			duration: readDuration( styles, '--ax-surface-motion-exit-duration' ),
		},
	};
}

/**
 * Puts focus on the first interactive element, which is M3's rule and this
 * project's decision (`surface.yml` `conflicts`, subject `initial focus`).
 *
 * This was expected to need no code: HTML's dialog focusing steps already say
 * autofocus, then the first focusable descendant, then the dialog. Measured in
 * Chrome 152, they do not, as soon as the body scrolls -- focus lands on the
 * scrolling region even though it has `tabIndex === -1`, carries no `tabindex`
 * attribute and matches no focusable selector, so it is not in the sequential
 * focus order the steps describe. A dialog whose body fits still focuses the
 * first button, which is why this only shows up on long content.
 *
 * So the rule is implemented rather than inherited. A region marked as the
 * surface's scroller is skipped even when it has been made reachable, because
 * being reachable by Tab and being the place focus starts are different things.
 *
 * An author's `autofocus` still wins: the platform has already honoured it by
 * the time this runs, and the APG exception for a destructive first action is
 * expressed that way rather than guessed at here.
 *
 * @param {HTMLDialogElement} dialog Surface host.
 */
function applyInitialFocus( dialog ) {
	if ( dialog.querySelector( '[autofocus]' ) ) {
		return;
	}

	const first = Array.from( dialog.querySelectorAll( FOCUSABLE_SELECTOR ) ).find(
		( candidate ) => ! candidate.hasAttribute( 'data-surface-scroll' )
	);

	// Nothing interactive: the platform's choice stands, because there is no
	// first interactive element for this rule to prefer.
	if ( first && first !== document.activeElement ) {
		first.focus();
	}
}

function prefersReducedMotion() {
	return (
		'function' === typeof window.matchMedia &&
		window.matchMedia( '( prefers-reduced-motion: reduce )' ).matches
	);
}

/**
 * Whether a scrolling region needs to be made keyboard-reachable.
 *
 * Section 7 of the record. M3 pins a dialog's headline and actions and scrolls
 * what is between them, so the scrolling box is a `<div>`; WCAG 2.1.1 wants it
 * reachable by keyboard.
 *
 * Both conditions have to hold: the region actually overflows, and nothing
 * inside it can take focus. When something inside it can, focus is already in
 * there and the nearest scrollable ancestor answers the arrow keys, so a
 * tabindex would add a tab stop and no accessibility.
 *
 * Those two conditions are Chrome's own rule for keyboard-focusable scroll
 * containers, which is deliberate: this is the same behaviour for the engines
 * that do not implement it. Chrome additionally focuses such a region when the
 * surface opens, which `applyInitialFocus` undoes -- being reachable by Tab and
 * being where focus starts are different questions, and M3 answers the second
 * with the first interactive element.
 *
 * Measured per open and on resize, because both conditions are runtime facts.
 *
 * @param {Object}  ref  Ref to the scrolling region.
 * @param {boolean} open Whether the surface is open.
 * @return {boolean} Whether to expose the region to the keyboard.
 */
export function useScrollAffordance( ref, open ) {
	const [ reachable, setReachable ] = useState( false );

	useEffect( () => {
		const region = ref.current;

		if ( ! open || ! region ) {
			setReachable( false );
			return;
		}

		function measure() {
			setReachable(
				region.scrollHeight > region.clientHeight &&
					null === region.querySelector( FOCUSABLE_SELECTOR )
			);
		}

		measure();

		/*
		 * Both conditions change without the region's own box changing, so one
		 * observer cannot see them.
		 *
		 * The region has a `max-block-size` and shrinks to fit inside it, so
		 * content arriving late -- a fetched fragment, a paragraph appended --
		 * grows `scrollHeight` while the border box stays exactly where it was,
		 * and a ResizeObserver on the region never fires. A link appearing or
		 * a button being disabled changes nothing about any box at all.
		 *
		 * So: a MutationObserver for what is in the region, and a
		 * ResizeObserver over the region and each of its element children for
		 * what has no mutation at all, such as an image finishing its load. The
		 * children are re-observed whenever the child list changes.
		 */
		const resize =
			'function' === typeof window.ResizeObserver ? new window.ResizeObserver( measure ) : null;

		function watchBoxes() {
			if ( ! resize ) {
				return;
			}

			resize.disconnect();
			resize.observe( region );
			Array.from( region.children ).forEach( ( child ) => resize.observe( child ) );
		}

		watchBoxes();

		if ( 'function' !== typeof window.MutationObserver ) {
			return () => resize?.disconnect();
		}

		/*
		 * `measure` writes `tabindex`, `role` and `aria-labelledby` back onto
		 * this region through React, which is itself a mutation. It converges
		 * rather than loops: the re-measurement reads the same two facts, sets
		 * the same state, and React bails out of an identical value. The
		 * attribute filter keeps the common case from even reaching that point
		 * by naming only the attributes that decide whether a descendant can
		 * take focus.
		 */
		const mutate = new window.MutationObserver( () => {
			watchBoxes();
			measure();
		} );

		mutate.observe( region, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: [ 'disabled', 'href', 'tabindex', 'contenteditable', 'type', 'hidden' ],
		} );

		return () => {
			resize?.disconnect();
			mutate.disconnect();
		};
	}, [ ref, open ] );

	return reachable;
}

/**
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children Surface content.
 * @param {boolean} props.open Whether the surface is open. Controlled.
 * @param {Function} [props.onClose] Called when the surface asks to be closed.
 * @param {'modal'|'standard'} [props.modality='modal'] Top layer, scrim and lock.
 * @param {'floating'|'docked'|'co-planar'} [props.display='floating'] Where the box sits.
 * @param {'any'|'closerequest'|'none'} [props.closedBy='any'] HTML dismissal policy.
 * @param {'dialog'|'alertdialog'} [props.role] Overrides the host's implicit role.
 * @param {string} [props.labelledBy] Id of the element naming the surface.
 * @param {string} [props.describedBy] Id of the element describing it.
 * @param {Function} [props.enter] Builds the enter animations.
 * @param {Function} [props.exit] Builds the exit animations.
 * @param {string} [props.className] Additional class name.
 * @return {import('@wordpress/element').ReactNode} Native dialog host.
 */
export function Surface( {
	children,
	open,
	onClose,
	modality = 'modal',
	display = 'floating',
	closedBy = 'any',
	role,
	labelledBy,
	describedBy,
	enter,
	exit,
	className,
	...props
} ) {
	const dialogRef = useRef( null );
	const animationsRef = useRef( [] );
	/*
	 * Marks the `close()` this component makes itself, at the end of the exit
	 * animation, so the `close` event it fires is not mistaken for someone else
	 * closing the surface. Without the mark the handler below would call
	 * `onClose` again after the caller had already lowered `open`.
	 */
	const selfClosingRef = useRef( false );
	const resolvedModality = oneOf( modality, MODALITIES, 'modal', 'modality' );
	const resolvedDisplay = oneOf( display, DISPLAYS, 'floating', 'display' );
	const resolvedClosedBy = oneOf( closedBy, CLOSED_BY, 'any', 'closedBy' );

	function selfClose( dialog ) {
		selfClosingRef.current = true;
		dialog.close();
	}

	useEffect( () => {
		const dialog = dialogRef.current;

		if ( ! dialog ) {
			return;
		}

		// An enter that is still running when the surface is asked to close
		// would otherwise fight the exit and win, because it was started first.
		function stopAnimations() {
			animationsRef.current.forEach( ( animation ) => animation.cancel() );
			animationsRef.current = [];
		}

		function play( build, timing ) {
			if ( ! build || prefersReducedMotion() || 0 === timing.duration ) {
				return [];
			}

			const animations = build( dialog, timing ) || [];
			animationsRef.current = animations;

			return animations;
		}

		if ( open && ! dialog.open ) {
			/*
			 * The open call comes first and everything that touches focus comes
			 * after it, because the platform runs its focusing steps inside it.
			 */
			if ( 'modal' === resolvedModality ) {
				dialog.showModal();
			} else {
				dialog.show();
			}

			applyInitialFocus( dialog );
			stopAnimations();
			play( enter, readMotion( dialog ).enter );

			return;
		}

		if ( ! open && dialog.open ) {
			stopAnimations();

			const animations = play( exit, readMotion( dialog ).exit );

			if ( 0 === animations.length ) {
				selfClose( dialog );
				return;
			}

			let cancelled = false;

			Promise.all( animations.map( ( animation ) => animation.finished ) )
				.then( () => {
					if ( ! cancelled && dialog.open ) {
						selfClose( dialog );
					}
				} )
				// A cancelled animation rejects. Reopening mid-exit is the
				// ordinary way that happens, and it must not close the surface.
				.catch( () => {} );

			return () => {
				cancelled = true;
			};
		}
	}, [ open, resolvedModality, enter, exit ] );

	// Unmounting while open leaves the top layer holding a detached element in
	// some engines, so close without motion -- there is nothing left to watch.
	useEffect( () => {
		const dialog = dialogRef.current;

		return () => {
			if ( dialog?.open ) {
				selfClose( dialog );
			}
		};
	}, [] );

	/*
	 * Escape and a light dismiss both arrive here, and neither closes the
	 * surface by itself: the caller lowers `open` and the effect above plays
	 * the exit. A non-modal surface fires no `cancel` at all, which is why a
	 * standard sheet's close affordance is required rather than recommended.
	 */
	function onCancel( event ) {
		event.preventDefault();
		onClose?.();
	}

	/*
	 * A surface can also be closed without asking: `<form method="dialog">`,
	 * which the contract records as the standard way to read a confirmation
	 * back, and a plain `dialog.close()` from anywhere else. The browser closes
	 * it and fires `close`, and nothing else happens -- the caller's `open` is
	 * still true, the effect's dependencies have not changed, so it does not
	 * re-run. The surface is shut and the app thinks it is open, and the next
	 * press of the trigger sets `open` to a value it already holds, which
	 * renders nothing. The surface can never be opened again.
	 *
	 * So a `close` this component did not make is reported like a cancel. The
	 * exit animation is not played, because by then there is nothing left to
	 * animate out.
	 */
	function onNativeClose() {
		if ( selfClosingRef.current ) {
			selfClosingRef.current = false;
			return;
		}

		onClose?.();
	}

	return (
		<dialog
			{ ...props }
			ref={ dialogRef }
			className={ [ 'ax-surface', className ].filter( Boolean ).join( ' ' ) }
			role={ role }
			aria-labelledby={ labelledBy }
			aria-describedby={ describedBy }
			closedby={ resolvedClosedBy }
			data-modality={ resolvedModality }
			data-display={ resolvedDisplay }
			onCancel={ onCancel }
			onClose={ onNativeClose }
		>
			{ children }
		</dialog>
	);
}
