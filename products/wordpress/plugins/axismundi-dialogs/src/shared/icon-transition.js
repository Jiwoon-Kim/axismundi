/**
 * How a button's icon moves between its states.
 *
 * One setting of presets rather than separate switches for fade, rotation and
 * scale: combinations multiply, and some read as faults (a fade-out with no
 * fade-in). Each preset is a whole transition, drawn by assets/button.css from
 * the wrapper's data-icon-transition:
 *
 *   none    the icon changes at once
 *   fade    the unselected and selected icons cross-fade
 *   rotate  they cross-fade while turning; with no selected icon the one icon
 *           turns - a plus becoming a close, a chevron flipping
 *   scale   they cross-fade while shrinking and growing
 *
 * The turn is its own value because it depends on the glyph: 45deg turns a
 * plus into an x, 180deg flips a chevron.
 *
 * Preview on hover (data-icon-preview) shows the selected icon state while the
 * pointer is over the button or it has keyboard focus - a Like filling its
 * heart, a Repost turning its arrows - so the state is announced before it is
 * chosen. On a button with no state it is the only time that state shows,
 * which is how such a button gets a hover fill or a hover turn at all.
 */
import { __ } from '@wordpress/i18n';
import { SelectControl, ToggleControl } from '@wordpress/components';

const TRANSITION_OPTIONS = [
	{ label: __( 'None', 'axismundi-dialogs' ), value: '' },
	{ label: __( 'Fade', 'axismundi-dialogs' ), value: 'fade' },
	{ label: __( 'Rotate', 'axismundi-dialogs' ), value: 'rotate' },
	{ label: __( 'Scale', 'axismundi-dialogs' ), value: 'scale' },
];

const ROTATION_OPTIONS = [
	{
		label: __( '45° - a plus to a close', 'axismundi-dialogs' ),
		value: '45',
	},
	{ label: __( '90° - a quarter turn', 'axismundi-dialogs' ), value: '90' },
	{
		label: __( '180° - a chevron flipping', 'axismundi-dialogs' ),
		value: '180',
	},
];

/**
 * The wrapper attributes the stylesheet reads, as render.php writes them.
 *
 * @param {Object}  attributes                    Block attributes.
 * @param {string}  attributes.iconTransition     The stored preset, or undefined for none.
 * @param {number}  attributes.selectedRotation   The stored turn, or undefined for 180deg.
 * @param {boolean} attributes.iconPreviewOnHover Whether hover previews the selected state.
 * @return {Object} data-icon-transition, data-selected-rotation and data-icon-preview.
 */
export function iconTransitionProps( {
	iconTransition,
	selectedRotation,
	iconPreviewOnHover,
} ) {
	return {
		'data-icon-preview': iconPreviewOnHover ? 'hover' : undefined,
		'data-icon-transition': iconTransition || undefined,
		'data-selected-rotation':
			iconTransition === 'rotate' &&
			[ 45, 90 ].includes( selectedRotation )
				? String( selectedRotation )
				: undefined,
	};
}

/**
 * The Icon transition and Rotation controls.
 *
 * @param {Object}   props                    Component props.
 * @param {string}   props.iconTransition     The stored preset, or undefined for none.
 * @param {number}   props.selectedRotation   The stored turn, or undefined for 180deg.
 * @param {boolean}  props.iconPreviewOnHover Whether hover previews the selected state.
 * @param {boolean}  props.hasState           Whether the button can be selected.
 * @param {boolean}  props.hasSelectedIcon    Whether a selected icon is set.
 * @param {Function} props.setAttributes      The block's setter.
 * @return {Element} The controls.
 */
export function IconTransitionControls( {
	iconTransition,
	selectedRotation,
	iconPreviewOnHover,
	hasState,
	hasSelectedIcon,
	setAttributes,
} ) {
	// A button with no state has one icon, so only a turn can move it; a
	// stored Fade or Scale stays listed so the control shows what is saved.
	const options = hasState
		? TRANSITION_OPTIONS
		: TRANSITION_OPTIONS.filter(
				( option ) =>
					[ '', 'rotate' ].includes( option.value ) ||
					option.value === iconTransition
		  );
	let help = __(
		'How the icon moves between the unselected and selected icons.',
		'axismundi-dialogs'
	);
	if ( ! hasState ) {
		help = __(
			'This button has no selected state, so the icon only turns while Preview on hover shows it.',
			'axismundi-dialogs'
		);
	} else if ( ! hasSelectedIcon && iconTransition ) {
		help = __(
			'With no selected icon, only Rotate shows: the one icon turns while the button is selected.',
			'axismundi-dialogs'
		);
	}
	return (
		<>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Icon transition', 'axismundi-dialogs' ) }
				value={ iconTransition ?? '' }
				options={ options }
				help={ help }
				onChange={ ( value ) =>
					setAttributes( {
						iconTransition: value || undefined,
						// The turn means nothing to the other presets.
						selectedRotation:
							value === 'rotate' ? selectedRotation : undefined,
					} )
				}
			/>
			{ iconTransition === 'rotate' && (
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Rotation', 'axismundi-dialogs' ) }
					value={ String( selectedRotation ?? 180 ) }
					options={ ROTATION_OPTIONS }
					onChange={ ( value ) =>
						setAttributes( {
							selectedRotation:
								value === '180' ? undefined : Number( value ),
						} )
					}
				/>
			) }
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Preview on hover', 'axismundi-dialogs' ) }
				checked={ !! iconPreviewOnHover }
				help={
					hasState
						? __(
								'Hover and keyboard focus show the selected icon state - the fill, the turn or the selected icon - before the button is selected.',
								'axismundi-dialogs'
						  )
						: __(
								'Hover and keyboard focus fill or turn the icon, as set above.',
								'axismundi-dialogs'
						  )
				}
				onChange={ ( value ) =>
					setAttributes( { iconPreviewOnHover: value || undefined } )
				}
			/>
		</>
	);
}
