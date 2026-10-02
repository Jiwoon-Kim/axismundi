import { Pane } from '../panes/pane';

/**
 * Places application chrome slots and the primary content pane. The scaffold
 * owns window-level geometry only; callers supply the actual bar, rail, and
 * supporting components.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children Primary content.
 * @param {import('@wordpress/element').ReactNode} [props.navigationBar] Compact navigation.
 * @param {import('@wordpress/element').ReactNode} [props.navigationRail] Medium-and-up navigation.
 * @param {import('@wordpress/element').ReactNode} [props.supporting] Expanded supporting content.
 * @param {string} [props.className] Product-level wrapper class.
 * @return {import('@wordpress/element').ReactNode} Application scaffold.
 */
export function Scaffold( {
	children,
	navigationBar,
	navigationRail,
	supporting,
	className,
} ) {
	return (
		<div
			className={ [ 'ax-scaffold', className ].filter( Boolean ).join( ' ' ) }
			data-has-bar={ navigationBar ? '' : undefined }
			data-has-rail={ navigationRail ? '' : undefined }
			data-has-supporting={ supporting ? '' : undefined }
		>
			{ navigationBar ? (
				<div className="ax-scaffold__bar">{ navigationBar }</div>
			) : null }
			{ navigationRail ? (
				<div className="ax-scaffold__rail">{ navigationRail }</div>
			) : null }
			<Pane as="main" className="ax-scaffold__main">
				{ children }
			</Pane>
			{ supporting ? (
				<Pane as="aside" className="ax-scaffold__supporting">
					{ supporting }
				</Pane>
			) : null }
		</div>
	);
}
