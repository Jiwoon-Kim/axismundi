import { forwardRef, useState } from '@wordpress/element';
import warning from '@wordpress/warning';
import { Elevation } from '../material/elevation';
import { Tooltip } from '../tooltip';

const COLOUR_STYLES = [
	'primary-container',
	'secondary-container',
	'tertiary-container',
	'primary',
	'secondary',
	'tertiary',
];
const SIZES = [ 'small', 'medium', 'large' ];

function oneOf( value, allowed, fallback ) {
	return allowed.includes( value ) ? value : fallback;
}

function firstWord( value ) {
	return String( value ?? '' ).trim().split( /\s+/, 1 )[ 0 ].toLocaleLowerCase();
}

function useFabElevation() {
	const [ hovered, setHovered ] = useState( false );
	const [ pressed, setPressed ] = useState( false );

	return {
		elevation: pressed ? 3 : hovered ? 4 : 3,
		handlers: {
			onMouseEnter: () => setHovered( true ),
			onMouseLeave: () => {
				setHovered( false );
				setPressed( false );
			},
			onMouseDown: () => setPressed( true ),
			onMouseUp: () => setPressed( false ),
		},
	};
}

/**
 * Material 3 expressive floating action button.
 *
 * A FAB is an icon-only screen action. It deliberately does not accept a
 * disabled state: M3 removes an unavailable FAB instead of dimming one.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.icon Meaningful icon.
 * @param {string} props.label Accessible name and visual tooltip text.
 * @param {'small'|'medium'|'large'} [props.size='small'] Expressive size.
 * @param {'primary-container'|'secondary-container'|'tertiary-container'|'primary'|'secondary'|'tertiary'} [props.colour='primary-container'] Colour style.
 * @return {import('@wordpress/element').ReactNode} FAB control.
 */
export const Fab = forwardRef( function Fab( {
	icon,
	label,
	size = 'small',
	colour = 'primary-container',
	disabled,
	className,
	onMouseEnter,
	onMouseLeave,
	onMouseDown,
	onMouseUp,
	...props
}, ref ) {
	const fabSize = oneOf( size, SIZES, 'small' );
	const colourStyle = oneOf( colour, COLOUR_STYLES, 'primary-container' );
	const { elevation, handlers } = useFabElevation();

	if ( ! label ) {
		warning( 'Fab: `label` is required because an icon-only FAB needs an accessible name and tooltip text.' );
	}
	if ( ! icon ) {
		warning( 'Fab: `icon` is required. Use ExtendedFab when the action needs a visible label.' );
	}
	if ( undefined !== disabled ) {
		warning( 'Fab: M3 publishes no disabled FAB. Remove an unavailable action instead.' );
	}

	return (
		<Tooltip accessibleName={ label } content={ label }>
		<button
			{ ...props }
			{ ...handlers }
			ref={ ref }
			aria-label={ label || undefined }
			className={ [ 'ax-fab', className ].filter( Boolean ).join( ' ' ) }
			data-colour={ colourStyle }
			data-size={ fabSize }
			onMouseDown={ ( event ) => {
				handlers.onMouseDown();
				onMouseDown?.( event );
			} }
			onMouseEnter={ ( event ) => {
				handlers.onMouseEnter();
				onMouseEnter?.( event );
			} }
			onMouseLeave={ ( event ) => {
				handlers.onMouseLeave();
				onMouseLeave?.( event );
			} }
			onMouseUp={ ( event ) => {
				handlers.onMouseUp();
				onMouseUp?.( event );
			} }
			type="button"
		>
			<Elevation level={ elevation } />
			<span className="ax-fab__icon">{ icon }</span>
		</button>
		</Tooltip>
	);
} );

/**
 * Material 3 expressive extended floating action button.
 *
 * Extended FABs retain a visible label, so their label supplies the accessible
 * name and they intentionally have no tooltip.
 *
 * @param {Object} props Component props.
 * @param {string} props.label Visible action label.
 * @param {import('@wordpress/element').ReactNode} [props.icon] Optional icon.
 * @param {'small'|'medium'|'large'} [props.size='small'] Expressive size.
 * @param {'primary-container'|'secondary-container'|'tertiary-container'|'primary'|'secondary'|'tertiary'} [props.colour='primary-container'] Colour style.
 * @return {import('@wordpress/element').ReactNode} Extended FAB control.
 */
export const ExtendedFab = forwardRef( function ExtendedFab( {
	icon,
	label,
	size = 'small',
	colour = 'primary-container',
	disabled,
	className,
	'aria-label': accessibleName,
	onMouseEnter,
	onMouseLeave,
	onMouseDown,
	onMouseUp,
	...props
}, ref ) {
	const fabSize = oneOf( size, SIZES, 'small' );
	const colourStyle = oneOf( colour, COLOUR_STYLES, 'primary-container' );
	const { elevation, handlers } = useFabElevation();

	if ( ! label ) {
		warning( 'ExtendedFab: `label` is required. An icon-only extended FAB is not published.' );
	}
	if ( accessibleName && firstWord( accessibleName ) !== firstWord( label ) ) {
		warning( 'ExtendedFab: the accessible name must begin with the same word as its visible label.' );
	}
	if ( undefined !== disabled ) {
		warning( 'ExtendedFab: M3 publishes no disabled FAB. Remove an unavailable action instead.' );
	}

	return (
		<button
			{ ...props }
			{ ...handlers }
			ref={ ref }
			aria-label={ accessibleName || undefined }
			className={ [ 'ax-extended-fab', className ].filter( Boolean ).join( ' ' ) }
			data-colour={ colourStyle }
			data-has-icon={ icon ? '' : undefined }
			data-size={ fabSize }
			onMouseDown={ ( event ) => {
				handlers.onMouseDown();
				onMouseDown?.( event );
			} }
			onMouseEnter={ ( event ) => {
				handlers.onMouseEnter();
				onMouseEnter?.( event );
			} }
			onMouseLeave={ ( event ) => {
				handlers.onMouseLeave();
				onMouseLeave?.( event );
			} }
			onMouseUp={ ( event ) => {
				handlers.onMouseUp();
				onMouseUp?.( event );
			} }
			type="button"
		>
			<Elevation level={ elevation } />
			{ icon ? <span className="ax-extended-fab__icon">{ icon }</span> : null }
			<span className="ax-extended-fab__label">{ label }</span>
		</button>
	);
} );
