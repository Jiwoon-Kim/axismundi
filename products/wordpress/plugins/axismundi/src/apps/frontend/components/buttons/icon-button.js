/**
 * Material 3 Icon button.
 *
 * Not a Button with the label taken out. The anatomy is Container and Icon, and
 * four consequences follow from that one missing part, each of which would be
 * wrong if this were copied from Button:
 *
 *   the name       has to be text nobody sees, and is required rather than optional
 *   Small's icon   24dp, where Button's Small takes 20dp
 *   width          a third axis, and the only component in M3 that has one
 *   colour         filled, tonal, outlined, standard -- no text, no elevated
 *
 * Published values live in `products/styleguide/_data/icon_button.yml`, beside
 * the page that works through those differences
 * (`products/styleguide/_components/icon-buttons.md`).
 *
 * `href` chooses the host: with one it renders an anchor, without one a button.
 * An earlier note here said an icon that navigates belongs to a separate
 * `IconLink`; that is withdrawn. The two differ in what they do, and the element
 * is where that difference belongs, but it is the same control to look at and
 * splitting the component would have duplicated every size, width and colour
 * rule to say so. No general `as`: a caller should not be able to put this on an
 * element with no interaction semantics.
 *
 * A disabled link needs its own answer, since an anchor has no `disabled`
 * attribute. The href is dropped instead -- an anchor without one is not a link,
 * is not focusable and is not announced as one -- and `aria-disabled` says why.
 *
 * Toggle follows the fixed-label contract from the Dialogs plugin's
 * `docs/BUTTON-STATE.md`, and the selected icon is the Material Symbols `FILL`
 * axis rather than a second glyph. A second name is only needed where nothing
 * can move an axis -- a static face, or the WordPress icon registry where one
 * icon is one SVG -- and neither is a Social source yet.
 *
 * Do not reach for `toggle` to build a control that cycles. `aria-pressed`
 * holds two values, so a three-state control misreports one of them whatever it
 * answers; the theme switcher's cycle button is the worked example and
 * deliberately has no `aria-pressed` at all.
 *
 * @param {Object} props Component props.
 * @param {string} props.label Accessible name. Required: the anatomy has no label.
 * @param {import('@wordpress/element').ReactNode} props.icon Icon element.
 * @param {'filled'|'tonal'|'outlined'|'standard'} [props.variant='filled'] M3 colour style.
 * @param {'xsmall'|'small'|'medium'|'large'|'xlarge'} [props.size='small'] M3 size.
 * @param {'narrow'|'default'|'wide'} [props.width='default'] M3 width.
 * @param {'round'|'square'} [props.shape='round'] M3 shape.
 * @param {boolean} [props.toggle=false] Opt into the fixed-label toggle contract.
 * @param {boolean} [props.selected] Selected state, for a controlled toggle.
 * @param {boolean} [props.defaultSelected=false] Initial state, for an uncontrolled toggle.
 * @param {Function} [props.onSelectedChange] Called with the next selected state.
 * @param {boolean} [props.disabled=false] Disabled state.
 * @param {string} [props.href] Renders an anchor instead of a button.
 * @param {string} [props.className] Additional component class name.
 * @return {import('@wordpress/element').ReactNode} Icon button control.
 */

import { forwardRef, useState } from '@wordpress/element';
import warning from '@wordpress/warning';

const VARIANTS = [ 'filled', 'tonal', 'outlined', 'standard' ];
const SIZES = [ 'xsmall', 'small', 'medium', 'large', 'xlarge' ];
const WIDTHS = [ 'narrow', 'default', 'wide' ];
const SHAPES = [ 'round', 'square' ];

function oneOf( value, allowed, fallback ) {
	return allowed.includes( value ) ? value : fallback;
}

export const IconButton = forwardRef( function IconButton( {
	label,
	icon,
	variant = 'filled',
	size = 'small',
	width = 'default',
	shape = 'round',
	toggle = false,
	selected,
	defaultSelected = false,
	onSelectedChange,
	disabled = false,
	href,
	className,
	type = 'button',
	onClick,
	...props
}, ref ) {
	const colorStyle = oneOf( variant, VARIANTS, 'filled' );
	const buttonSize = oneOf( size, SIZES, 'small' );
	const buttonWidth = oneOf( width, WIDTHS, 'default' );
	const buttonShape = oneOf( shape, SHAPES, 'round' );

	if ( ! label ) {
		warning(
			'IconButton: `label` is required. An icon button has no label in its anatomy, so without one it has no accessible name at all.'
		);
	}

	const [ uncontrolled, setUncontrolled ] = useState( Boolean( defaultSelected ) );
	const isControlled = undefined !== selected;
	const isSelected = toggle && ( isControlled ? Boolean( selected ) : uncontrolled );

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

	const isLink = undefined !== href;

	if ( isLink && toggle ) {
		warning(
			'IconButton: `toggle` and `href` cannot both apply. A link goes somewhere; it has no pressed state.'
		);
	}

	const Host = isLink ? 'a' : 'button';
	const hostProps = isLink
		? { href: disabled ? undefined : href, 'aria-disabled': disabled || undefined }
		: { disabled, type };

	return (
		<Host
			{ ...props }
			{ ...hostProps }
			ref={ ref }
			aria-pressed={ toggle && ! isLink ? isSelected : undefined }
			className={ [ 'ax-icon-button', className ].filter( Boolean ).join( ' ' ) }
			data-shape={ buttonShape }
			data-size={ buttonSize }
			data-variant={ colorStyle }
			data-width={ buttonWidth }
			onClick={ handleClick }
		>
			<span className="ax-icon-button__icon">{ icon }</span>
			<span className="ax-sr-only">{ label }</span>
		</Host>
	);
} );
