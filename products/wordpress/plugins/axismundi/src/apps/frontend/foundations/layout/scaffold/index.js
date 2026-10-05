import { Pane } from '../panes/pane';

/**
 * Places application chrome around a content region. The rail is a sibling of
 * that region; the app bar, primary pane, and optional supporting pane live
 * inside it. The scaffold owns window-level geometry only.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children Primary content.
 * @param {import('@wordpress/element').ReactNode} [props.appBar] Top application bar.
 * @param {import('@wordpress/element').ReactNode} [props.navigationBar] Compact navigation.
 * @param {import('@wordpress/element').ReactNode} [props.navigationRail] Medium-and-up navigation.
 * @param {import('@wordpress/element').ReactNode} [props.supporting] Expanded supporting content.
 * @param {string} [props.className] Product-level wrapper class.
 * @return {import('@wordpress/element').ReactNode} Application scaffold.
 */
export function Scaffold( {
	children,
	appBar,
	navigationBar,
	navigationRail,
	supporting,
	className,
} ) {
	return (
		<div
			className={ [ 'ax-scaffold', className ].filter( Boolean ).join( ' ' ) }
			data-has-app-bar={ appBar ? '' : undefined }
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
			<div className="ax-scaffold__content">
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
				</div>
			</div>
		</div>
	);
}
