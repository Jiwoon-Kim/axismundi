/**
 * Material 3 Button.
 *
 * Size, colour style and shape are three independent configurations in M3, so
 * they are three props rather than one variant list. The published values live
 * in `products/styleguide/_data/button.yml`, which
 * `tools/validators/validate_styleguide_button.py` checks against the spec; the
 * stylesheet beside this file spends the same numbers.
 *
 * Icons arrive as nodes rather than names. A block has to flatten an icon into
 * attributes because it cannot compose, which is why `axismundi/dialog-button`
 * carries `showIcon`, `iconSource`, `icon`, `iconClass` and more. Here the
 * caller passes an element and CSS sizes it, so the icon keeps its own API.
 *
 * Toggle follows the fixed-label contract from the Dialogs plugin's
 * `docs/BUTTON-STATE.md`: the accessible name never changes, and `aria-pressed`
 * carries the state. The second contract in that memo -- a command button whose
 * label is replaced -- is deliberately not implemented here, and the two must
 * not be mixed on one button.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children Button label.
 * @param {'filled'|'elevated'|'tonal'|'outlined'|'text'} [props.variant='filled'] M3 colour style.
 * @param {'xsmall'|'small'|'medium'|'large'|'xlarge'} [props.size='small'] M3 size.
 * @param {'round'|'square'} [props.shape='round'] M3 shape.
 * @param {import('@wordpress/element').ReactNode} [props.icon] Leading icon element.
 * @param {import('@wordpress/element').ReactNode} [props.trailingIcon] Trailing icon element.
 * @param {boolean} [props.toggle=false] Opt into the fixed-label toggle contract.
 * @param {boolean} [props.selected] Selected state, for a controlled toggle.
 * @param {boolean} [props.defaultSelected=false] Initial state, for an uncontrolled toggle.
 * @param {Function} [props.onSelectedChange] Called with the next selected state.
 * @param {boolean} [props.disabled=false] Disabled state.
 * @param {string} [props.className] Additional component class name.
 * @return {import('@wordpress/element').ReactNode} Button control.
 */

import { useState } from '@wordpress/element';
import { Elevation } from '../material/elevation';

const VARIANTS = [ 'filled', 'elevated', 'tonal', 'outlined', 'text' ];
const SIZES = [ 'xsmall', 'small', 'medium', 'large', 'xlarge' ];
const SHAPES = [ 'round', 'square' ];

/**
 * M3 resting elevation by colour style. Only Elevated has a shadow at rest.
 *
 * @see products/styleguide/_data/button.yml
 */
const RESTING_ELEVATION = { elevated: 1 };

function oneOf( value, allowed, fallback ) {
	return allowed.includes( value ) ? value : fallback;
}

export function Button( {
	children,
	variant = 'filled',
	size = 'small',
	shape = 'round',
	icon,
	trailingIcon,
	toggle = false,
	selected,
	defaultSelected = false,
	onSelectedChange,
	disabled = false,
	className,
	type = 'button',
	onClick,
	...props
} ) {
	const colorStyle = oneOf( variant, VARIANTS, 'filled' );
	const buttonSize = oneOf( size, SIZES, 'small' );
	const buttonShape = oneOf( shape, SHAPES, 'round' );

	const [ uncontrolled, setUncontrolled ] = useState( Boolean( defaultSelected ) );
	const isControlled = undefined !== selected;
	const isSelected = toggle && ( isControlled ? Boolean( selected ) : uncontrolled );

	const restingLevel = RESTING_ELEVATION[ colorStyle ];

	function handleClick( event ) {
		if ( toggle ) {
			const next = ! isSelected;
			if ( ! isControlled ) {
				setUncontrolled( next );
			}
			onSelectedChange?.( next );
		}
		onClick?.( event );
	}

	return (
		<button
			{ ...props }
			aria-pressed={ toggle ? isSelected : undefined }
			className={ [ 'ax-button', className ].filter( Boolean ).join( ' ' ) }
			data-shape={ buttonShape }
			data-size={ buttonSize }
			data-variant={ colorStyle }
			disabled={ disabled }
			onClick={ handleClick }
			type={ type }
		>
			{ undefined !== restingLevel && <Elevation level={ restingLevel } /> }
			{ icon && <span className="ax-button__icon">{ icon }</span> }
			<span className="ax-button__label">{ children }</span>
			{ trailingIcon && <span className="ax-button__icon">{ trailingIcon }</span> }
		</button>
	);
}
