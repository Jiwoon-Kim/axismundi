/**
 * Material 3 Side sheet.
 *
 * Its own component rather than a row of Dialog, because the swap M3 publishes
 * between a bottom sheet and a side sheet is "can be swapped ... not must" --
 * the caller's composition, not the component's obligation. Section 8 of
 * `docs/DECISION-FRONTEND-OVERLAY-ESCAPE-AND-PUSH.md`.
 *
 * MODALITY IS THE WHOLE DIFFERENCE. A modal sheet goes to the top layer, draws
 * a scrim and locks the page; a standard one stays in the flow, takes a column
 * of the Scaffold's body and leaves the page usable. Both are the same element
 * opened two ways, which is why a standard sheet becoming modal on a compact
 * window moves no DOM (section 5).
 *
 * THAT SWAP IS THE ONE THING CSS CANNOT DO. Every width, colour and corner here
 * is a media query or an attribute selector. Which method opens the element is
 * not, so this is one of the decisions `useWindowSizeClass` exists for -- its
 * own documentation draws that line, and this stays on the right side of it.
 *
 * THE CLOSE AFFORDANCE IS NOT OPTIONAL. M3's anatomy calls it "highly
 * recommended" and its accessibility page says it is always present; the
 * stricter page is the one this project follows (`surface.yml` `conflicts`,
 * subject `side sheet close button`). A standard sheet fires no `cancel` event
 * at all -- Escape does not close a non-modal dialog -- so without the button
 * it would have no keyboard way out whatsoever.
 *
 * PLACEMENT IS THE REGION'S, NOT THE WINDOW'S. M3 says a side sheet is usually
 * the full window height and then qualifies it: "set by how the layout is
 * divided into regions". Inside a Scaffold the region is the body, which
 * already sits below the app bar and above the navigation bar, so a standard
 * sheet needs no offsets of its own.
 */

import { useEffect, useRef } from '@wordpress/element';
import warning from '@wordpress/warning';
import { IconButton } from '../buttons/icon-button';
import { Elevation } from '../material/elevation';
import { Icon } from '../material/icon';
import { useWindowSizeClass } from '../../foundations/layout/breakpoints/use-window-size-class';
import { Surface, useScrollAffordance } from './surface';

const MODALITIES = [ 'modal', 'standard' ];
const EDGES = [ 'start', 'end' ];
const ATTACHMENTS = [ 'docked', 'detached' ];

/* `surface.yml` `presentations[sheet-side].measurements.width`. */
const MINIMUM_WIDTH = 256;
const MAXIMUM_WIDTH = 400;

/*
 * `surface.yml` `adaptive`: a standard side sheet is mostly medium and
 * expanded, and a modal one is preferred on compact.
 */
const MODAL_ONLY_CLASS = 'compact';

function oneOf( value, allowed, fallback, name ) {
	if ( allowed.includes( value ) ) {
		return value;
	}

	warning( `SideSheet: unsupported ${ name } "${ value }". Using "${ fallback }".` );

	return fallback;
}

/**
 * A sheet enters from the edge it sits on and leaves back to it.
 *
 * Only a modal sheet is animated. A standard one is not an overlay arriving
 * over the page, it is the body being divided differently, and sliding it
 * across a column that has already appeared shows a gap where the sheet is not
 * yet. Animating the division itself is the open question; see
 * `surface.yml` `react_surface.not_implemented`.
 *
 * The curve does not overshoot, which is the reason `surface.yml` records for
 * this row: a spring would pull the sheet off its own edge and show the page
 * through the gap.
 *
 * @param {HTMLDialogElement} dialog Surface host.
 * @param {Object}            timing Easing and duration.
 * @return {Array} Animations.
 */
function slide( dialog, timing, away ) {
	if ( 'modal' !== dialog.dataset.modality ) {
		return [];
	}

	const offset = 'start' === dialog.dataset.edge ? '-100%' : '100%';
	const frames = away ? [ 'translateX( 0 )', `translateX( ${ offset } )` ] : [ `translateX( ${ offset } )`, 'translateX( 0 )' ];

	return [
		dialog.animate( { transform: frames }, timing ),
		dialog.animate( { opacity: away ? [ 1, 0 ] : [ 0, 1 ] }, { ...timing, pseudoElement: '::backdrop' } ),
	];
}

const enterSheet = ( dialog, timing ) => slide( dialog, timing, false );
const exitSheet = ( dialog, timing ) => slide( dialog, timing, true );

/**
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children Sheet content.
 * @param {boolean} props.open Whether the sheet is open. Controlled.
 * @param {Function} props.onClose Called when the sheet asks to be closed.
 * @param {'modal'|'standard'} [props.modality='modal'] Top layer, or a column of the page.
 * @param {'start'|'end'} [props.edge='end'] Which window edge it sits on. Logical.
 * @param {'docked'|'detached'} [props.attachment='docked'] Against the edge, or inset by 16dp.
 * @param {number} [props.width=256] Sheet width, clamped to the published 256..400.
 * @param {import('@wordpress/element').ReactNode} [props.headline] Headline text.
 * @param {Function} [props.onBack] Renders a back icon button when given.
 * @param {import('@wordpress/element').ReactNode} [props.actions] Bottom action bar.
 * @param {boolean} [props.divider=false] Rule between the content and the actions.
 * @param {string} [props.closeLabel='Close'] Name for the close button.
 * @param {string} [props.backLabel='Back'] Name for the back button.
 * @param {string} [props.className] Additional class name.
 * @return {import('@wordpress/element').ReactNode} Side sheet surface.
 */
export function SideSheet( {
	children,
	open,
	onClose,
	modality = 'modal',
	edge = 'end',
	attachment = 'docked',
	width = MINIMUM_WIDTH,
	headline,
	onBack,
	actions,
	divider = false,
	closeLabel = 'Close',
	backLabel = 'Back',
	className,
	...props
} ) {
	const declaredModality = oneOf( modality, MODALITIES, 'modal', 'modality' );
	const resolvedEdge = oneOf( edge, EDGES, 'end', 'edge' );
	const resolvedAttachment = oneOf( attachment, ATTACHMENTS, 'docked', 'attachment' );
	const sizeClass = useWindowSizeClass();
	const bodyRef = useRef( null );
	const bodyReachable = useScrollAffordance( bodyRef, open );

	/*
	 * The published range is 256 to 400. A number outside it is clamped rather
	 * than refused: a sheet that does not render is a worse answer to a typo
	 * than a sheet at its widest.
	 */
	const resolvedWidth = Math.min( MAXIMUM_WIDTH, Math.max( MINIMUM_WIDTH, Number( width ) || MINIMUM_WIDTH ) );

	if ( resolvedWidth !== Number( width ) && undefined !== width ) {
		warning(
			`SideSheet: width ${ width } is outside the published ${ MINIMUM_WIDTH }-${ MAXIMUM_WIDTH }dp range. Using ${ resolvedWidth }.`
		);
	}

	// A standard sheet opens as a modal on a compact window.
	const compact = MODAL_ONLY_CLASS === sizeClass;
	const effectiveModality = compact ? 'modal' : declaredModality;

	/*
	 * `surface.yml` `windows.page_share.resize`: the sheet closes if the window
	 * crosses that boundary while it is open. Reopening it the other way would
	 * be the kinder answer, but it is not the one that is published, and a
	 * standard sheet that silently became modal mid-task would take the page
	 * away from someone who was using it.
	 */
	const crossedRef = useRef( effectiveModality );

	useEffect( () => {
		if ( open && crossedRef.current !== effectiveModality ) {
			onClose?.();
		}

		crossedRef.current = effectiveModality;
	}, [ effectiveModality, open, onClose ] );

	const headlineId = `${ props.id || 'ax-side-sheet' }-headline`;

	return (
		<Surface
			{ ...props }
			open={ open }
			onClose={ onClose }
			modality={ effectiveModality }
			// A modal side sheet floats over the page; a standard one is
			// co-planar with it and takes a column of the Scaffold's body.
			display={ 'modal' === effectiveModality ? 'floating' : 'co-planar' }
			closedBy="any"
			labelledBy={ headline ? headlineId : undefined }
			enter={ enterSheet }
			exit={ exitSheet }
			className={ [ 'ax-side-sheet', className ].filter( Boolean ).join( ' ' ) }
			data-edge={ resolvedEdge }
			data-attachment={ resolvedAttachment }
			style={ { ...props.style, '--ax-side-sheet-width': `${ resolvedWidth }px` } }
		>
			<Elevation level={ 'modal' === effectiveModality ? 1 : 0 } />

			<header className="ax-side-sheet__header">
				{ onBack ? (
					<IconButton
						className="ax-side-sheet__back"
						label={ backLabel }
						icon={ <Icon name="arrow_back" /> }
						variant="standard"
						onClick={ onBack }
					/>
				) : null }
				{ headline ? (
					<h2 className="ax-side-sheet__headline" id={ headlineId }>
						{ headline }
					</h2>
				) : null }
				{ /*
				 * Always rendered. The anatomy calls it optional and the
				 * accessibility page calls it always present; this project
				 * follows the stricter page, and a standard sheet has no
				 * Escape to fall back on.
				 */ }
				<IconButton
					className="ax-side-sheet__close"
					label={ closeLabel }
					icon={ <Icon name="close" /> }
					variant="standard"
					onClick={ onClose }
				/>
			</header>

			<div
				className="ax-side-sheet__body"
				ref={ bodyRef }
				data-surface-scroll=""
				tabIndex={ bodyReachable ? 0 : undefined }
				role={ bodyReachable ? 'group' : undefined }
				aria-labelledby={ bodyReachable && headline ? headlineId : undefined }
			>
				{ children }
			</div>

			{ divider && actions ? <hr className="ax-side-sheet__divider" /> : null }
			{ actions ? <div className="ax-side-sheet__actions">{ actions }</div> : null }
		</Surface>
	);
}
