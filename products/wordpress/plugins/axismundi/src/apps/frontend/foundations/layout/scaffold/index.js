import { Pane } from '../panes/pane';

/**
 * Places application chrome around a content pane. The rail is a sibling of
 * that pane; the app bar, primary pane, and optional supporting pane live
 * inside it. The scaffold owns window-level geometry only.
 *
 * The `sheet` slot is where a standard side sheet lives. It is a column of the
 * body rather than padding injected on the page root, which is what the block
 * runtime has to do for want of a scaffold: the body already sits below the app
 * bar and above the navigation bar, so a sheet placed here needs no offsets of
 * its own and nothing has to measure its width. Section 4 of
 * `docs/DECISION-FRONTEND-OVERLAY-ESCAPE-AND-PUSH.md`.
 *
 * It is a node, like `appBar` and `supporting`, so the sheet's open state stays
 * with whatever renders it. A sheet that is closed, or open as a modal, takes
 * no space: the column is `auto`, a closed dialog is `display: none`, and a
 * modal one is in the top layer.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children Primary content.
 * @param {import('@wordpress/element').ReactNode} [props.appBar] Top application bar.
 * @param {import('@wordpress/element').ReactNode} [props.navigationBar] Compact navigation.
 * @param {import('@wordpress/element').ReactNode} [props.navigationRail] Medium-and-up navigation.
 * @param {import('@wordpress/element').ReactNode} [props.supporting] Expanded supporting content.
 * @param {import('@wordpress/element').ReactNode} [props.sheet] Standard side sheet column.
 * @param {string} [props.className] Product-level wrapper class.
 * @return {import('@wordpress/element').ReactNode} Application scaffold.
 */
export function Scaffold( {
	children,
	appBar,
	navigationBar,
	navigationRail,
	supporting,
	sheet,
	className,
} ) {
	return (
		<div
			className={ [ 'ax-scaffold', className ].filter( Boolean ).join( ' ' ) }
			data-has-app-bar={ appBar ? '' : undefined }
			data-has-bar={ navigationBar ? '' : undefined }
			data-has-rail={ navigationRail ? '' : undefined }
			data-has-supporting={ supporting ? '' : undefined }
			data-has-sheet={ sheet ? '' : undefined }
		>
			{ navigationBar ? (
				<div className="ax-scaffold__bar">{ navigationBar }</div>
			) : null }
			{ navigationRail ? (
				<div className="ax-scaffold__rail">{ navigationRail }</div>
			) : null }
			<Pane className="ax-scaffold__content">
				{ appBar ? (
					<div className="ax-scaffold__app-bar">{ appBar }</div>
				) : null }
				<div className="ax-scaffold__body">
					<Pane as="main" className="ax-scaffold__main">
						{ children }
					</Pane>
					{ supporting ? (
						<Pane as="aside" className="ax-scaffold__supporting">
							{ supporting }
						</Pane>
					) : null }
					{ sheet }
				</div>
			</Pane>
		</div>
	);
}
