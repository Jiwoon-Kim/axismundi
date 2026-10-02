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
 * There is one icon slot and it is leading. M3 describes no trailing icon and
 * names two icons on one button as an explicit Don't, which the style guide
 * already recorded (`products/styleguide/_components/buttons.md`): a trailing
 * slot would be an Axismundi decision rather than a Material one, and would
 * have to be written down as such before it is built.
 *
 * Toggle follows the fixed-label contract from the Dialogs plugin's
 * `docs/BUTTON-STATE.md`: the accessible name never changes, and `aria-pressed`
 * carries the state. The second contract in that memo -- a command button whose
 * label is replaced -- is deliberately not implemented here, and the two must
 * not be mixed on one button.
 *
 * Text takes no toggle. M3 states it outright -- "toggle buttons don't use the
 * text style" -- and the reason is structural rather than stylistic: a toggle
 * tells selected from unselected by recolouring its container, and Text has
 * none. The combination is refused rather than rendered without a cue, and
 * `@wordpress/warning` says so once in development so a caller is not left
 * wondering why `onSelectedChange` never fires. Measured: it logs on this
 * runtime and the second identical call is swallowed. Which build of the
 * `wp-warning` handle core serves decides whether the gate is `SCRIPT_DEBUG`
 * or the production build step, so do not rely on one of them by name.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children Button label.
 * @param {'filled'|'elevated'|'tonal'|'outlined'|'text'} [props.variant='filled'] M3 colour style.
 * @param {'xsmall'|'small'|'medium'|'large'|'xlarge'} [props.size='small'] M3 size.
 * @param {'round'|'square'} [props.shape='round'] M3 shape.
 * @param {import('@wordpress/element').ReactNode} [props.icon] Leading icon element.
 * @param {boolean} [props.toggle=false] Opt into the fixed-label toggle contract.
 * @param {boolean} [props.selected] Selected state, for a controlled toggle.
 * @param {boolean} [props.defaultSelected=false] Initial state, for an uncontrolled toggle.
 * @param {Function} [props.onSelectedChange] Called with the next selected state.
 * @param {boolean} [props.disabled=false] Disabled state.
 * @param {string} [props.className] Additional component class name.
 * @return {import('@wordpress/element').ReactNode} Button control.
 */

import { useState } from '@wordpress/element';
import warning from '@wordpress/warning';
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

	/* M3: toggle buttons do not use the Text style, which has no container. */
	const supportsToggle = 'text' !== colorStyle;
	const isToggle = toggle && supportsToggle;

	if ( toggle && ! supportsToggle ) {
		warning(
			'Button: M3 publishes no Toggle Text button, so `toggle` is ignored on variant="text".'
		);
	}

	const [ uncontrolled, setUncontrolled ] = useState( Boolean( defaultSelected ) );
	const isControlled = undefined !== selected;
	const isSelected = isToggle && ( isControlled ? Boolean( selected ) : uncontrolled );

	const restingLevel = RESTING_ELEVATION[ colorStyle ];

	function handleClick( event ) {
		if ( isToggle ) {
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
			aria-pressed={ isToggle ? isSelected : undefined }
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
		</button>
	);
}
