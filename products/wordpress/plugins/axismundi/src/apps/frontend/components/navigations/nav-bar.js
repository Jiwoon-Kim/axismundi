/**
 * Material 3 Navigation bar.
 *
 * The host for `NavigationItem`. Published values are in
 * `products/styleguide/_data/navigation_bar.yml`, checked by
 * `tools/validators/validate_styleguide_navigation_bar.py`; everything the bar
 * and the rail share lives in `navigation_item.yml` instead, and the split is
 * the contract:
 *
 *   container, and the arithmetic arranging items in it   -> this component
 *   the horizontal figures the item will not default      -> this component
 *   colour, state, vertical geometry                      -> NavigationItem
 *
 * DESTINATIONS ARE AN EXPLICIT ARRAY, not derived from the router.
 * `DECISION-FRONTEND-NAVIGATION-COMPONENT-SLICING.md` §4 decided this and gave
 * the shape -- `{ id, label, icon, href }` -- for three recorded reasons, the
 * load-bearing one being that M3 caps the bar at five destinations while the
 * route table will hold dozens, so what goes here is a product decision and not
 * a derived value. `badge` is accepted as a fifth, optional key because the bar's
 * own guidelines publish it: "Navigation bars can display badges in the upper
 * right corners of the destination icon."
 *
 * THE BOUNDS ARE PUBLISHED AT BOTH ENDS, so both are warned about. "Navigation
 * bars provide access to three to five destinations", and twice more as
 * prohibitions: "Don't use a navigation bar for fewer than three destinations.
 * Instead, use tabs", and "Avoid putting more than five navigation items in a
 * navigation bar". Warned rather than refused or clamped -- dropping a
 * destination would hide a route from the product, and rendering nothing would
 * hide the mistake behind an empty bar.
 *
 * `itemLayout` IS A PROP AND THE BAR DOES NOT WATCH THE WINDOW. M3 publishes the
 * axis as a configuration with Vertical as the default, and separately publishes
 * a window rule: vertical in compact windows, horizontal in medium. The rule is
 * not implemented here, for two measured reasons recorded in the yml's
 * `discrepancies`. The two layouts differ in DOM and not only in CSS -- the label
 * is a child of the indicator when horizontal and a sibling when vertical -- so
 * no media query can switch them; it needs a JS window-size signal, and this app
 * has none outside the stylebook. And the only window that would trigger it is a
 * medium one, where `scaffold.css` hides the bar and shows the rail from 600px
 * up. Which component serves a medium window is a layout decision M3 leaves open
 * ("Medium: Use a navigation bar or navigation rail"), so it stays open here.
 *
 * ELEVATION LEVEL 2, WITH ITS SHADOW. The overview prose says "no drop shadow",
 * and that sentence compares M2 with the M3 *baseline* bar, whose shadow token
 * was then deprecated as a bug to match it. The flexible bar publishes both
 * `container.elevation -> md.sys.elevation.level2` and a live
 * `container.shadow-color` fresh, which reads as a deliberate Expressive change.
 * This reverses a claim this repository made once from the hand-transcribed
 * overview alone; the validator pins both rows as live so it is not reversed
 * again by someone reading only the prose.
 *
 * PLACEMENT IS NOT HERE. "The container should always be placed at the bottom of
 * the product and span the full length of the window." The span is this
 * component's (`inline-size: 100%`); the bottom is `Scaffold`'s grid area, and
 * `Scaffold` "owns window-level geometry only".
 *
 * @param {Object} props Component props.
 * @param {Array<{id: string, label: string, icon: import('@wordpress/element').ReactNode, href: string, badge?: import('@wordpress/element').ReactNode, badgeDescription?: string}>} props.destinations Three to five destinations, in fixed order.
 * @param {string} props.activeId `id` of the current destination. One is always active.
 * @param {string} props.label Accessible name for the navigation landmark.
 * @param {'vertical'|'horizontal'} [props.itemLayout='vertical'] Published item layout axis.
 * @param {string} [props.className] Additional component class name.
 * @return {import('@wordpress/element').ReactNode} Navigation bar.
 */

import { Elevation } from '../material/elevation';
import { NavigationItem } from './navigation-item';
import warning from '@wordpress/warning';

const ITEM_LAYOUTS = [ 'vertical', 'horizontal' ];

/* md.comp.nav-bar.container.elevation -> md.sys.elevation.level2 */
const CONTAINER_ELEVATION = 2;

/* "Navigation bars provide access to three to five destinations." */
const DESTINATIONS_MINIMUM = 3;
const DESTINATIONS_MAXIMUM = 5;

export function NavigationBar( {
	destinations = [],
	activeId,
	label,
	itemLayout = 'vertical',
	className,
	...props
} ) {
	let layout = itemLayout;
	if ( ! ITEM_LAYOUTS.includes( layout ) ) {
		warning( `NavigationBar: unknown itemLayout "${ itemLayout }"; using vertical.` );
		layout = 'vertical';
	}

	if ( DESTINATIONS_MINIMUM > destinations.length ) {
		warning(
			`NavigationBar: ${ destinations.length } destinations. M3 publishes "Don't use a navigation bar for fewer than three destinations. Instead, use tabs."`
		);
	}
	if ( DESTINATIONS_MAXIMUM < destinations.length ) {
		warning(
			`NavigationBar: ${ destinations.length } destinations. M3 publishes "Avoid putting more than five navigation items in a navigation bar" -- consider tabs, or a modal expanded navigation rail.`
		);
	}

	/*
	 * "One navigation destination is always active." Not a default we are filling
	 * in: the active indicator is the only thing telling someone where they are,
	 * so a bar with none is a bar that cannot answer that. Warned and left
	 * as-is rather than defaulting to the first destination, which would claim
	 * the person is somewhere they are not.
	 */
	if ( destinations.length && ! destinations.some( ( { id } ) => id === activeId ) ) {
		warning(
			`NavigationBar: activeId "${ activeId }" matches no destination; one destination is always active.`
		);
	}

	/*
	 * A navigation landmark with no name is indistinguishable from the next one,
	 * and Social will have more than one. The published line is permissive about
	 * where the name comes from -- "A navigation bar's accessibility label can
	 * incorporate its adjacent UI text" -- but not about having one.
	 */
	if ( ! label ) {
		warning( 'NavigationBar: `label` is required -- a navigation landmark needs a name.' );
	}

	return (
		<nav
			{ ...props }
			className={ [ 'ax-nav-bar', className ].filter( Boolean ).join( ' ' ) }
			data-item-layout={ layout }
			aria-label={ label || undefined }
		>
			<Elevation level={ CONTAINER_ELEVATION } />
			{ /*
			 * No list wrapper. M3's anatomy is the container and its items with
			 * nothing between them, and the published keyboard row is "Tab --
			 * Move between navigation items", which a flat landmark of links
			 * already gives.
			 *
			 * The item carries no width of its own -- `navigation_item.yml` puts
			 * "item width" under `not_published_here` because it is the host's
			 * arithmetic -- so the bar names each one and sizes it in CSS.
			 */ }
			{ destinations.map( ( { id, label: destination, icon, href, badge, badgeDescription } ) => (
				<NavigationItem
					key={ id }
					className="ax-nav-bar__item"
					icon={ icon }
					label={ destination }
					href={ href }
					badge={ badge }
					badgeDescription={ badgeDescription }
					orientation={ layout }
					active={ id === activeId }
				/>
			) ) }
		</nav>
	);
}
