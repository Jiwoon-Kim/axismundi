/**
 * Material 3 Bottom sheet.
 *
 * A bottom sheet is its own anatomy, but not its own dialog lifecycle. The
 * shared Surface owns the native host, top layer, scrim, scroll lock and
 * initial focus. This component supplies the bottom-edge geometry and the two
 * heights M3 requires people to reach without a drag gesture.
 *
 * Pointer drag is deliberately absent. The handle remains a real button, so
 * click, Space and Enter cycle the same initial and expanded states. Drag,
 * fling and pointer capture are separately deferred in surface.yml rather
 * than being approximated with unpublished thresholds.
 */

import { useEffect, useId, useRef, useState } from '@wordpress/element';
import warning from '@wordpress/warning';
import { IconButton } from '../buttons/icon-button';
import { Elevation } from '../material/elevation';
import { Icon } from '../material/icon';
import { Surface, useScrollAffordance } from './surface';

const MODALITIES = [ 'modal', 'standard' ];
const HEIGHTS = [ 'initial', 'expanded' ];

function oneOf( value, allowed, fallback, name ) {
	if ( allowed.includes( value ) ) {
		return value;
	}

	warning( `BottomSheet: unsupported ${ name } "${ value }". Using "${ fallback }".` );
	return fallback;
}

function slide( dialog, timing, away ) {
	const frames = away
		? [ 'translateY( 0 )', 'translateY( 100% )' ]
		: [ 'translateY( 100% )', 'translateY( 0 )' ];

	return [
		dialog.animate( { transform: frames }, timing ),
		dialog.animate(
			{ opacity: away ? [ 1, 0 ] : [ 0, 1 ] },
			{ ...timing, pseudoElement: '::backdrop' }
		),
	];
}

const enterSheet = ( dialog, timing ) => slide( dialog, timing, false );
const exitSheet = ( dialog, timing ) => slide( dialog, timing, true );

/**
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children Sheet body.
 * @param {boolean} props.open Whether the sheet is open. Controlled.
 * @param {Function} props.onClose Called when the sheet asks to close.
 * @param {'modal'|'standard'} [props.modality='modal'] Top layer or page-sharing sheet.
 * @param {import('@wordpress/element').ReactNode} [props.headline] Accessible sheet name.
 * @param {string} [props.handleLabel='Resize sheet'] Accessible handle name.
 * @param {string} [props.closeLabel='Close'] Accessible full-height close name.
 * @param {string} [props.collapseLabel='Collapse sheet'] Accessible standard-sheet collapse name.
 * @param {string} [props.className] Additional class name.
 * @return {import('@wordpress/element').ReactNode} Bottom sheet surface.
 */
export function BottomSheet( {
	children,
	open,
	onClose,
	modality = 'modal',
	headline,
	handleLabel = 'Resize sheet',
	closeLabel = 'Close',
	collapseLabel = 'Collapse sheet',
	className,
	...props
} ) {
	const resolvedModality = oneOf( modality, MODALITIES, 'modal', 'modality' );
	const [ height, setHeight ] = useState( 'initial' );
	const bodyRef = useRef( null );
	const headlineId = `${ useId() }-headline`;
	const bodyReachable = useScrollAffordance( bodyRef, open );
	const expanded = 'expanded' === height;

	// Every fresh open starts at the published half-window cap. The expanded
	// state belongs to one visit to the transient surface, not its next one.
	useEffect( () => {
		if ( open ) {
			setHeight( 'initial' );
		}
	}, [ open ] );

	function cycleHeight() {
		setHeight( ( current ) => ( 'initial' === current ? 'expanded' : 'initial' ) );
	}

	function collapse() {
		setHeight( 'initial' );
	}

	return (
		<Surface
			{ ...props }
			open={ open }
			onClose={ onClose }
			modality={ resolvedModality }
			// Docked in both modalities: M3's own example of a docked
			// pane is the bottom sheet, pinned to the window's edge with the
			// page still usable above it.
			display="docked"
			closedBy="any"
			labelledBy={ headline ? headlineId : undefined }
			enter={ enterSheet }
			exit={ exitSheet }
			className={ [ 'ax-bottom-sheet', className ].filter( Boolean ).join( ' ' ) }
			data-sheet-height={ oneOf( height, HEIGHTS, 'initial', 'height' ) }
		>
			<Elevation level={ 1 } />
			<header className="ax-bottom-sheet__header">
				<button
					className="ax-bottom-sheet__drag-handle"
					type="button"
					aria-label={ handleLabel }
					onClick={ cycleHeight }
				/>
				{ expanded && 'modal' === resolvedModality ? (
					<IconButton
						className="ax-bottom-sheet__close"
						label={ closeLabel }
						icon={ <Icon name="close" /> }
						variant="standard"
						onClick={ onClose }
					/>
				) : null }
				{ expanded && 'standard' === resolvedModality ? (
					<IconButton
						className="ax-bottom-sheet__collapse"
						label={ collapseLabel }
						icon={ <Icon name="keyboard_arrow_down" /> }
						variant="standard"
						onClick={ collapse }
					/>
				) : null }
			</header>
			<div
				className="ax-bottom-sheet__body"
				ref={ bodyRef }
				data-surface-scroll=""
				tabIndex={ bodyReachable ? 0 : undefined }
				role={ bodyReachable ? 'group' : undefined }
				aria-labelledby={ bodyReachable && headline ? headlineId : undefined }
			>
				{ headline ? <h2 className="ax-bottom-sheet__headline" id={ headlineId }>{ headline }</h2> : null }
				{ children }
			</div>
		</Surface>
	);
}
