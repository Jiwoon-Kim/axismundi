/**
 * A content-bearing layout item. Pane owns no grid topology or accessibility
 * navigation behavior; its parent layout determines both where it is placed
 * and whether it needs an additional navigable boundary.
 *
 * @param {Object} props Component props.
 * @param {string} [props.as] Element used for the pane wrapper.
 * @param {string} [props.className] Additional layout class name.
 * @param {import('@wordpress/element').ReactNode} props.children Pane content.
 * @return {import('@wordpress/element').ReactNode} Pane wrapper.
 */
export function Pane( { as: Tag = 'div', className, children } ) {
	return (
		<Tag className={ [ 'ax-pane', className ].filter( Boolean ).join( ' ' ) }>
			{ children }
		</Tag>
	);
}
