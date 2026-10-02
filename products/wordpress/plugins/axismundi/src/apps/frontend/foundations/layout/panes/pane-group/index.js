/**
 * A neutral CSS Grid container for panes. Canonical layouts own their own
 * track definitions and adaptive rules through additional class names.
 *
 * @param {Object} props Component props.
 * @param {string} [props.className] Additional topology class name.
 * @param {import('@wordpress/element').ReactNode} props.children Pane items.
 * @return {import('@wordpress/element').ReactNode} Pane grid wrapper.
 */
export function PaneGroup( { className, children, ...props } ) {
	return (
		<div
			{ ...props }
			className={ [ 'ax-pane-group', className ]
				.filter( Boolean )
				.join( ' ' ) }
		>
			{ children }
		</div>
	);
}
