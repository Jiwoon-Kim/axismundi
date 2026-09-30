/**
 * M3 NavigationSuite scaffold. It owns adaptive navigation placement only;
 * route-level canonical layouts own their content panes.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children Route content.
 * @param {import('@wordpress/element').ReactNode} [props.navigationBar] Compact navigation.
 * @param {import('@wordpress/element').ReactNode} [props.navigationRail] Medium-and-up navigation.
 * @return {import('@wordpress/element').ReactNode} Navigation suite scaffold.
 */
export function NavigationSuite( { children, navigationBar, navigationRail } ) {
	return (
		<div className="axismundi-social md-navigation-suite">
			<div className="md-navigation-suite__content">{ children }</div>
			{ navigationBar ? (
				<div className="md-navigation-suite__bar">{ navigationBar }</div>
			) : null }
			{ navigationRail ? (
				<div className="md-navigation-suite__rail">{ navigationRail }</div>
			) : null }
		</div>
	);
}
