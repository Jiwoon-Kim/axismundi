/**
 * Material 3 Split button.
 *
 * A main action with a menu of related actions beside it. Published differences
 * from Button are in `docs/REFERENCE-M3-SPLIT-BUTTON.md`; everything not listed
 * there -- colour mapping, state layers, label type, disabled -- is Button's and
 * is not restated.
 *
 * It draws its own two buttons rather than taking Button and IconButton
 * instances, which is the opposite of ButtonGroup and is decided by the figures
 * rather than by preference: at Small the trailing icon is 22dp where
 * IconButton's is 24dp, and the trailing button is 48 wide where IconButton's
 * Small default is 40. Embedding one would put two published contracts against
 * each other. These two halves are parts of this component, not components.
 *
 * The trailing button is 48 wide at XS and S for a reason worth keeping in view:
 * 13 + 22 + 13. M3 gives icon buttons a 48dp target rule and gives split buttons
 * none, because the published width already is the target.
 *
 * No `aria-haspopup` and no `aria-expanded`. There is no menu yet, and an
 * element that announces a popup it does not have is lying. `trailingProps` is
 * where a later Menu supplies them; the stylesheet already answers
 * `[aria-expanded="true"]`, so the rotation and the corner change arrive with the
 * menu rather than needing this component changed.
 *
 * @param {Object} props Component props.
 * @param {string} props.label Leading button label.
 * @param {string} props.menuLabel Accessible name for the trailing button.
 * @param {import('@wordpress/element').ReactNode} [props.icon] Optional leading icon.
 * @param {import('@wordpress/element').ReactNode} props.menuIcon Trailing menu icon, which M3 asks not to substitute.
 * @param {'filled'|'tonal'|'elevated'|'outlined'} [props.variant='filled'] M3 colour style.
 * @param {'xsmall'|'small'|'medium'|'large'|'xlarge'} [props.size='small'] M3 size.
 * @param {string} [props.groupLabel] Accessible name for the pair, when it needs one.
 * @param {boolean} [props.disabled=false] Disables both halves.
 * @param {Function} [props.onAction] Leading button click.
 * @param {Object} [props.trailingProps] Extra props for the trailing button, including the ARIA a menu will supply.
 * @param {string} [props.className] Additional component class name.
 * @return {import('@wordpress/element').ReactNode} Split button.
 */

import warning from '@wordpress/warning';

/* Text is absent, as on IconButton; Standard is absent, unlike IconButton. */
const VARIANTS = [ 'filled', 'tonal', 'elevated', 'outlined' ];
const SIZES = [ 'xsmall', 'small', 'medium', 'large', 'xlarge' ];

export function SplitButton( {
	label,
	menuLabel,
	icon,
	menuIcon,
	variant = 'filled',
	size = 'small',
	groupLabel,
	disabled = false,
	onAction,
	trailingProps = {},
	className,
	...props
} ) {
	const colorStyle = VARIANTS.includes( variant ) ? variant : 'filled';
	const buttonSize = SIZES.includes( size ) ? size : 'small';

	if ( ! menuLabel ) {
		warning(
			'SplitButton: `menuLabel` is required. The trailing button shows only an icon, so without it the menu half has no accessible name.'
		);
	}

	return (
		<div
			{ ...props }
			aria-label={ groupLabel }
			className={ [ 'ax-split-button', className ].filter( Boolean ).join( ' ' ) }
			data-size={ buttonSize }
			data-variant={ colorStyle }
			role={ groupLabel ? 'group' : undefined }
		>
			<button
				className="ax-split-button__leading"
				disabled={ disabled }
				onClick={ onAction }
				type="button"
			>
				{ icon && <span className="ax-split-button__icon">{ icon }</span> }
				<span className="ax-split-button__label">{ label }</span>
			</button>
			<button
				{ ...trailingProps }
				className="ax-split-button__trailing"
				disabled={ disabled }
				type="button"
			>
				<span className="ax-split-button__menu-icon">{ menuIcon }</span>
				<span className="ax-sr-only">{ menuLabel }</span>
			</button>
		</div>
	);
}
