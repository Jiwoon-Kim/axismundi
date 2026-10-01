/**
 * Places application regions without supplying navigation or supporting-pane
 * components. Those are independent Material or Axismundi components.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children Route template content.
 * @param {import('@wordpress/element').ReactNode} [props.navigationBar] Compact navigation.
 * @param {import('@wordpress/element').ReactNode} [props.navigationRail] Medium-and-up navigation.
 * @param {import('@wordpress/element').ReactNode} [props.supporting] Expanded supporting content.
 * @return {import('@wordpress/element').ReactNode} Application region layout.
 */
export function AppLayout( {
	children,
	navigationBar,
	navigationRail,
	supporting,
} ) {
	return (
		<div
			className="ax-app-layout"
			data-has-bar={ navigationBar ? '' : undefined }
			data-has-rail={ navigationRail ? '' : undefined }
			data-has-supporting={ supporting ? '' : undefined }
		>
			{ navigationBar ? (
				<div className="ax-app-layout__bar">{ navigationBar }</div>
			) : null }
			{ navigationRail ? (
				<div className="ax-app-layout__rail">{ navigationRail }</div>
			) : null }
			<main className="ax-app-layout__main">{ children }</main>
			{ supporting ? (
				<div className="ax-app-layout__supporting">{ supporting }</div>
			) : null }
		</div>
	);
}
