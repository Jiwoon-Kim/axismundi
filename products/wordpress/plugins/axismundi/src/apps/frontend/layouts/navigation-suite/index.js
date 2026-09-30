/**
 * An adaptive layout scaffold that places navigation components by window size.
 * Route-level canonical layouts own their content panes.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children Route content.
 * @param {import('@wordpress/element').ReactNode} [props.navigationBar] Compact navigation component.
 * @param {import('@wordpress/element').ReactNode} [props.navigationRail] Medium-and-up navigation component.
 * @return {import('@wordpress/element').ReactNode} Navigation suite layout.
 */
export function NavigationSuite( { children, navigationBar, navigationRail } ) {
	return (
		<div className="axismundi-social ax-navigation-suite">
			<div className="ax-navigation-suite__content">{ children }</div>
			{ navigationBar ? (
				<div className="ax-navigation-suite__bar">{ navigationBar }</div>
			) : null }
			{ navigationRail ? (
				<div className="ax-navigation-suite__rail">{ navigationRail }</div>
			) : null }
		</div>
	);
}
