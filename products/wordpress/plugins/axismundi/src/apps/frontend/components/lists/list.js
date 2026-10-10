/**
 * Material 3 List.
 *
 * This first slice is deliberately a native list: it owns the grouping
 * semantics (`ul > li`), not selection. Selection needs a List controller so
 * that a list can enforce one selection mode; it is deferred with multi-action
 * rows in `products/styleguide/_data/list.yml`.
 */

import warning from '@wordpress/warning';

const VARIANTS = [ 'standard', 'segmented' ];

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

	return (
		<ul
			{ ...props }
			className={ [ 'ax-list', className ].filter( Boolean ).join( ' ' ) }
			data-variant={ visualVariant }
		>
			{ children }
		</ul>
	);
}
