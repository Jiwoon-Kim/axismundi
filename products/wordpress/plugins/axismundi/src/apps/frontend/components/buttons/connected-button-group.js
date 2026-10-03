/**
 * Material 3 Connected button group.
 *
 * Connected is not a ButtonGroup with a different visual treatment. Standard
 * groups arrange independent Button and IconButton instances; connected groups
 * own Segments, and therefore own their size, shape and single colour system.
 * M3 calls that system a filled-toggle pair by default. It has no per-segment
 * colour API: mixing styles is the thing the component is meant to prevent.
 *
 * KEYBOARD. M3's accessibility table has three rows and all three hold at once:
 * Tab navigates between buttons, arrow keys navigate inside the component, and
 * Space or Enter activates the focused button.
 *
 * Tab and arrows together is not a radiogroup, and the reason is the number of
 * tab stops rather than the presence of arrows. A radiogroup is one tab stop
 * with arrows moving inside it; here every segment is a tab stop and the arrows
 * are additional. So each segment keeps its native tabbability -- no roving
 * `tabindex`, which would take the first row away to satisfy the second -- and
 * the arrows move focus on top of that.
 *
 * Arrows move focus only. Activation is published separately as Space or Enter,
 * which is what separates this from radio behaviour, where an arrow changes the
 * selection as it moves.
 *
 * `input[type="radio"]` with a shared `name` remains the better answer when a
 * required single choice really is a radiogroup: the browser owns mutual
 * exclusion, the single tab stop and form reset. That is a product alternative
 * beside this component, deliberately not hidden inside it.
 *
 * @param {Object} props Component props.
 * @param {Array<Object>} props.segments Ordered segment data.
 * @param {'single'|'multiple'} [props.selectionMode='single'] Selection model.
 * @param {boolean} [props.selectionRequired=false] Keep at least one selected segment.
 * @param {string|string[]} [props.value] Controlled selected value or values.
 * @param {string|string[]} [props.defaultValue] Initial uncontrolled value or values.
 * @param {Function} [props.onValueChange] Called with the next value or values.
 * @param {'xsmall'|'small'|'medium'|'large'|'xlarge'} [props.size='small'] M3 size.
 * @param {'round'|'square'} [props.shape='round'] M3 outer shape.
 * @param {string} [props.label] Accessible group name.
 * @param {Function} [props.onKeyDown] Runs before the arrow-key handler, which a `preventDefault` here suppresses.
 * @param {string} [props.className] Additional component class name.
 * @return {import('@wordpress/element').ReactNode} Connected selection control.
 */

import { forwardRef, useState } from '@wordpress/element';
import warning from '@wordpress/warning';

const SIZES = [ 'xsmall', 'small', 'medium', 'large', 'xlarge' ];
const SHAPES = [ 'round', 'square' ];
const SELECTION_MODES = [ 'single', 'multiple' ];

function oneOf( value, allowed, fallback ) {
	return allowed.includes( value ) ? value : fallback;
}

function normaliseValues( value, selectionMode, segments, selectionRequired ) {
	const requestedValues = 'multiple' === selectionMode
		? ( Array.isArray( value ) ? value : [] )
		: ( undefined === value || null === value ? [] : [ value ] );
	/* M3 publishes no disabled selected state for Connected segments. */
	const enabledValues = new Set(
		segments.filter( ( segment ) => ! segment.disabled ).map( ( segment ) => segment.value )
	);
	const values = [ ...new Set( requestedValues.filter( ( requestedValue ) => enabledValues.has( requestedValue ) ) ) ];

	if ( 'single' === selectionMode && values.length > 1 ) {
		values.splice( 1 );
	}

	if ( selectionRequired && ! values.length ) {
		const firstEnabled = segments.find( ( segment ) => ! segment.disabled );
		if ( firstEnabled ) {
			return [ firstEnabled.value ];
		}
	}

	return values;
}

function publicValue( values, selectionMode ) {
	return 'multiple' === selectionMode ? values : values[ 0 ] ?? null;
}

export const ConnectedButtonGroup = forwardRef( function ConnectedButtonGroup( {
	segments = [],
	selectionMode = 'single',
	selectionRequired = false,
	value,
	defaultValue,
	onValueChange,
	size = 'small',
	shape = 'round',
	label,
	className,
	onKeyDown,
	...props
}, ref ) {
	const groupSize = oneOf( size, SIZES, 'small' );
	const groupShape = oneOf( shape, SHAPES, 'round' );
	const mode = oneOf( selectionMode, SELECTION_MODES, 'single' );
	const isControlled = undefined !== value;
	const [ uncontrolledValues, setUncontrolledValues ] = useState( () =>
		normaliseValues( defaultValue, mode, segments, selectionRequired )
	);
	const selectedValues = isControlled
		? normaliseValues( value, mode, segments, selectionRequired )
		: normaliseValues( uncontrolledValues, mode, segments, selectionRequired );

	if ( ! segments.length ) {
		warning( 'ConnectedButtonGroup: `segments` must contain at least one selectable segment.' );
	}

	/*
	 * `value` is the identity of a segment, so two segments holding the same one
	 * are one segment drawn twice: selecting either presses both, and React has
	 * two children under one key.
	 */
	if ( segments.length !== new Set( segments.map( ( segment ) => segment.value ) ).size ) {
		warning(
			'ConnectedButtonGroup: every segment needs a distinct `value`. Duplicates select together and share a React key.'
		);
	}

	/*
	 * Disabled segments are skipped because they are not focusable to begin with;
	 * the filter keeps the arrow from landing on nothing rather than adding a
	 * rule. Movement stops at the ends: M3 publishes no wrap, and Tab is the
	 * published way out of the group, so an arrow that does nothing at the last
	 * segment is a dead end rather than a trap.
	 *
	 * Direction is read from the group's own computed `direction`, so ArrowRight
	 * moves right on screen in both writing directions.
	 */
	function moveFocus( event ) {
		onKeyDown?.( event );

		const step = { ArrowLeft: -1, ArrowRight: 1 }[ event.key ];
		if ( ! step || event.defaultPrevented ) {
			return;
		}

		const group = event.currentTarget;
		const focusable = [
			...group.querySelectorAll( '.ax-connected-button-group__segment:not( :disabled )' ),
		];
		const from = focusable.indexOf( event.target );
		if ( 0 > from ) {
			return;
		}

		const rtl = 'rtl' === window.getComputedStyle( group ).direction;
		const to = from + ( rtl ? -step : step );
		if ( 0 > to || to >= focusable.length ) {
			return;
		}

		event.preventDefault();
		focusable[ to ].focus();
	}

	function selectValue( segment, event ) {
		if ( segment.disabled ) {
			return;
		}

		const selected = selectedValues.includes( segment.value );
		let nextValues;

		if ( 'multiple' === mode ) {
			nextValues = selected
				? selectedValues.filter( ( selectedValue ) => selectedValue !== segment.value )
				: [ ...selectedValues, segment.value ];
		} else {
			nextValues = selected ? [] : [ segment.value ];
		}

		if ( selectionRequired && selected && 1 === selectedValues.length ) {
			return;
		}

		if ( ! isControlled ) {
			setUncontrolledValues( nextValues );
		}

		onValueChange?.( publicValue( nextValues, mode ), event );
	}

	return (
		<div
			{ ...props }
			ref={ ref }
			aria-label={ label }
			className={ [ 'ax-connected-button-group', className ].filter( Boolean ).join( ' ' ) }
			data-selection-mode={ mode }
			data-shape={ groupShape }
			data-size={ groupSize }
			data-selection-required={ selectionRequired || undefined }
			onKeyDown={ moveFocus }
			role={ label ? 'group' : undefined }
		>
			{ segments.map( ( segment ) => {
				const selected = selectedValues.includes( segment.value );
				const showLabel = false !== segment.showLabel;

				if ( ! segment.label ) {
					warning(
						'ConnectedButtonGroup: every segment needs `label`; an icon-only segment uses it as its accessible name.'
					);
				}

				return (
					<button
						key={ segment.value }
						aria-pressed={ selected }
						className="ax-connected-button-group__segment"
						disabled={ segment.disabled }
						onClick={ ( event ) => selectValue( segment, event ) }
						type="button"
					>
						{ segment.icon && (
							<span className="ax-connected-button-group__icon">{ segment.icon }</span>
						) }
						<span className={ showLabel ? 'ax-connected-button-group__label' : 'ax-sr-only' }>
							{ segment.label }
						</span>
					</button>
				);
			} ) }
		</div>
	);
} );
