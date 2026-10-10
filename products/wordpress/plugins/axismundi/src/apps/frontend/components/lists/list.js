/**
 * Material 3 List.
 *
 * This first slice is deliberately a native list: it owns the grouping
 * semantics (`ul > li`) and the arrow-key movement M3 requires of a
 * single-action list, not selection. Selection needs a List controller so that
 * a list can enforce one selection mode; it is deferred with multi-action rows
 * in `products/styleguide/_data/list.yml`.
 *
 * Tab order is left alone. M3 asks for arrow keys in addition to tabbing, not
 * instead of it, so this list does not take a roving tabindex: every row stays
 * its own tab stop and the arrow keys are a second path through the same rows.
 */

import warning from '@wordpress/warning';

const VARIANTS = [ 'standard', 'segmented' ];
const ACTION_SELECTOR = '.ax-list-item__action:is( a[href], button:not( [disabled] ) )';
const BACKWARD_KEYS = [ 'ArrowUp' ];
const FORWARD_KEYS = [ 'ArrowDown' ];

/**
 * Resolves one arrow key into a step through the rows.
 *
 * Up and down are absolute. Left and right follow the writing direction, so
 * they mirror under RTL the way the rows themselves do.
 *
 * @param {string}  key Pressed key.
 * @param {boolean} rtl Whether the list renders right-to-left.
 * @return {number} -1, 1, or 0 when the key is not an arrow.
 */
function getStep( key, rtl ) {
	if ( FORWARD_KEYS.includes( key ) ) {
		return 1;
	}

	if ( BACKWARD_KEYS.includes( key ) ) {
		return -1;
	}

	if ( 'ArrowRight' === key ) {
		return rtl ? -1 : 1;
	}

	if ( 'ArrowLeft' === key ) {
		return rtl ? 1 : -1;
	}

	return 0;
}

/**
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children List rows.
 * @param {'standard'|'segmented'} [props.variant='standard'] Visual style.
 * @param {string} [props.className] Additional class name.
 * @return {import('@wordpress/element').ReactNode} Native list container.
 */
export function List( { children, className, variant = 'standard', ...props } ) {
	const visualVariant = VARIANTS.includes( variant ) ? variant : 'standard';

	if ( visualVariant !== variant ) {
		warning( `List: unsupported variant "${ variant }". Using "standard".` );
	}

	function moveFocus( event ) {
		const list = event.currentTarget;
		const from = event.target.closest( ACTION_SELECTOR );

		// Only the rows answer arrow keys. Anything a row happens to contain
		// keeps its own key handling.
		if ( ! from || ! list.contains( from ) ) {
			return;
		}

		const rtl = 'rtl' === getComputedStyle( list ).direction;
		const step = getStep( event.key, rtl );

		if ( 0 === step ) {
			return;
		}

		const actions = Array.from( list.querySelectorAll( ACTION_SELECTOR ) );
		const index = actions.indexOf( from );

		if ( 2 > actions.length || 0 > index ) {
			return;
		}

		// M3 wraps: past the last row the focus returns to the first.
		const to = ( index + step + actions.length ) % actions.length;

		// Only now. Every return above leaves the key to the page, so an arrow
		// this list does not answer still scrolls.
		event.preventDefault();
		actions[ to ].focus();
	}

	return (
		<ul
			{ ...props }
			className={ [ 'ax-list', className ].filter( Boolean ).join( ' ' ) }
			data-variant={ visualVariant }
			onKeyDown={ moveFocus }
		>
			{ children }
		</ul>
	);
}
