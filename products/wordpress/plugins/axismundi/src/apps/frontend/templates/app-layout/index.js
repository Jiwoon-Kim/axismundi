import { Scaffold } from '../../foundations/layout/scaffold';

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
		<Scaffold
			className="ax-app-layout"
			navigationBar={ navigationBar }
			navigationRail={ navigationRail }
			supporting={ supporting }
		>
			{ children }
		</Scaffold>
	);
}
