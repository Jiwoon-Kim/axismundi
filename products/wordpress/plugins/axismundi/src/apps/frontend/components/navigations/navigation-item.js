/**
 * Material 3 Navigation item.
 *
 * M3 publishes no `navigation item` component. Its tokens live inside the two
 * hosts -- `md.comp.nav-bar.item.*` and `md.comp.nav-rail.item.*` -- and those
 * two sets are not the same set. Published values and the shape of the
 * difference are in `products/styleguide/_data/navigation_item.yml`, checked by
 * `tools/validators/validate_styleguide_navigation_item.py`:
 *
 *   colour and state      identical, role for role      -> this component owns
 *   vertical geometry     identical, figure for figure   -> this component owns
 *   horizontal geometry   diverges between the hosts     -> the host supplies
 *
 * That is why this exists before either host. An item that hardcoded an
 * indicator height or a label typescale would be right in the bar and wrong in
 * the rail.
 *
 * THE TWO ORIENTATIONS HAVE DIFFERENT DOM, not just different CSS. M3: items
 * "can be vertical, with the text below the icon and indicator, or horizontal,
 * with the icon and text beside each other inside the indicator." So the label
 * is a sibling of the indicator when vertical and a child of it when
 * horizontal. One structure cannot serve both -- a label inside the indicator
 * would sit on the pill's fill, which is the opposite of "below the indicator".
 *
 * NO TRUNCATION. Both hosts publish it as a prohibition -- "Don't wrap or
 * truncate text as it can make the label hard to understand" and "Don't shrink
 * longer text to fit on a single line" for the bar, "Don't truncate or display an
 * ellipsis in place of label text" and "Don't reduce the type size to fit more
 * characters" for the rail -- and both accessibility pages put the growth on the
 * host, which "should grow vertically to accommodate larger labels". So this
 * component neither ellipsises nor clamps, and a label that does not fit is the
 * host's problem to make room for. Note the contrast with Connected button group,
 * where ellipsis was our decision because M3 published nothing.
 *
 * The two hosts do differ on WRAPPING: the bar forbids it, the rail allows it as
 * a fallback ("If necessary, create a line break between words, or hyphenate
 * longer words"). Declaring no overflow behaviour here satisfies both, and the
 * difference belongs to whichever host is arranging the items.
 *
 * `href` IS REQUIRED, and the host is always an `<a>`. The usual rule in this
 * app is that `href` chooses between `<a>` and `<button>`, but a navigation
 * destination has already been decided elsewhere:
 * `DECISION-FRONTEND-NAVIGATION-MODEL.md` says the React app "preserves a real
 * anchor URL", that the router enhances an unmodified primary click, and that
 * "React SPA라는 이유로 inert element 또는 `onClick` only navigation을 사용하지
 * 않는다". A `<button>` here would take deep links, reload, bookmarking,
 * middle-click and open-in-new-tab away from every destination in the product.
 *
 * SPACE DOES NOT ACTIVATE, AND THAT IS DELIBERATE. M3's accessibility table
 * publishes "Space or Enter: Selects the focused navigation item", and
 * `navigation_item.yml` records it. On the web the element decides: an anchor is
 * activated by Enter, and Space scrolls the page. That table describes a
 * widget -- on Android, iOS and Flutter a navigation item is button-like -- and
 * the row is true there. Implementing it here would give a control that
 * announces as a link and behaves as a button, and would take page scrolling
 * away from a keyboard user whose focus is in the navigation. NON-STANDARD
 * against M3, standard for the platform; see `navigation_item.yml`
 * `discrepancies`, which the validator pins so this is not silently "fixed".
 *
 * The active item is marked `aria-current`, not `aria-pressed`. It is where you
 * are, not something held down -- and `aria-pressed` is binary, which is wrong
 * for one-of-many.
 *
 * Badge is a slot and nothing else. M3's anatomy lists a small and a large
 * badge, and neither host publishes a single live badge token -- all of them sit
 * in the deprecated baseline namespaces, and the page says "For badge color
 * roles, go to badge specs." So the position is honoured and none of the badge's
 * own figures are spent here.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.icon Icon node, not a name.
 * @param {string} props.label Visible label. M3 requires one on every item.
 * @param {string} props.href Destination. Required -- a navigation item is a link.
 * @param {'vertical'|'horizontal'} [props.orientation='vertical'] Item layout axis.
 * @param {boolean} [props.active=false] Whether this is the current destination.
 * @param {import('@wordpress/element').ReactNode} [props.badge] Badge node, placed on the icon.
 * @param {string} [props.badgeDescription] Badge information appended to the link name.
 * @param {string} [props.className] Additional component class name.
 * @return {import('@wordpress/element').ReactNode} Navigation item.
 */

import { forwardRef, isValidElement } from '@wordpress/element';
import warning from '@wordpress/warning';

const ORIENTATIONS = [ 'vertical', 'horizontal' ];

/**
 * Whether a badge node carries a label, which is what makes it the large one.
 *
 * @param {import('@wordpress/element').ReactNode} badge Badge node.
 * @return {boolean} True for a large badge.
 */
function isLargeBadge( badge ) {
	if ( ! isValidElement( badge ) ) {
		return false;
	}

	const label = badge.props?.label;

	return undefined !== label && null !== label && '' !== String( label ).trim();
}

export const NavigationItem = forwardRef( function NavigationItem(
	{ icon, label, href, orientation = 'vertical', active = false, badge, badgeDescription, className, ...props },
	ref
) {
	let axis = orientation;
	if ( ! ORIENTATIONS.includes( axis ) ) {
		warning( `NavigationItem: unknown orientation "${ orientation }"; using vertical.` );
		axis = 'vertical';
	}

	/*
	 * Both are published as required parts of the anatomy, and a label is named
	 * twice more in the guidelines ("All navigation items require a label text").
	 * Warned rather than refused: a missing label is a caller's bug, and
	 * rendering nothing would hide it behind an empty bar.
	 */
	if ( ! label ) {
		warning( 'NavigationItem: `label` is required -- M3 publishes no unlabelled item.' );
	}
	if ( ! icon ) {
		warning( 'NavigationItem: `icon` is required by the published anatomy.' );
	}
	if ( badge && ! badgeDescription ) {
		warning( 'NavigationItem: `badgeDescription` is required when a Badge is present so its information reaches assistive technology.' );
	}

	/*
	 * A destination with no URL is a caller's bug, not a second kind of item. It
	 * renders the way a disabled link already does in this app -- no `href`, and
	 * `aria-disabled` to say so -- because an anchor without a destination is not
	 * a link and takes no focus, and a silent one would simply vanish from the
	 * tab order.
	 */
	if ( ! href ) {
		warning( 'NavigationItem: `href` is required -- navigation is link-first; see DECISION-FRONTEND-NAVIGATION-MODEL.md.' );
	}

	/*
	 * A vertical item has its label underneath, so a badge on the icon has
	 * nothing to collide with and stays there whatever its width. A horizontal
	 * item is the case the badge guidelines name -- "an icon with a badge is
	 * followed by text" -- and they resolve it: put a large badge at the
	 * trailing edge, or use a small one. The navigation anatomy draws a large
	 * badge on a horizontal item's icon instead, which is the picture those same
	 * guidelines mark Don't; `badge.yml` records the two readings and why this
	 * one wins. A small badge is too narrow to reach the label and stays put.
	 */
	const trailingBadge = 'horizontal' === axis && isLargeBadge( badge );
	const iconSlot = (
		<span className="ax-navigation-item__icon">
			{ icon }
			{ badge && ! trailingBadge ? (
				<span className="ax-navigation-item__badge">{ badge }</span>
			) : null }
		</span>
	);
	const labelSlot = <span className="ax-navigation-item__label">{ label }</span>;
	// The label and its trailing badge are one group, so the item's own gap
	// applies to the pair and the published 4dp sits between the two of them.
	const labelGroup = trailingBadge ? (
		<span className="ax-navigation-item__label-with-badge">
			{ labelSlot }
			<span className="ax-navigation-item__trailing-badge">{ badge }</span>
		</span>
	) : (
		labelSlot
	);
	const accessibleName = badgeDescription ? [ label, badgeDescription ].filter( Boolean ).join( ', ' ) : undefined;

	return (
		<a
			ref={ ref }
			className={ [ 'ax-navigation-item', className ].filter( Boolean ).join( ' ' ) }
			data-orientation={ axis }
			href={ href || undefined }
			aria-disabled={ href ? undefined : 'true' }
			aria-label={ accessibleName }
			aria-current={ active ? 'page' : undefined }
			{ ...props }
		>
			{ /* Vertical puts the label below the indicator; horizontal puts it inside. */ }
			<span className="ax-navigation-item__indicator">
				{ iconSlot }
				{ 'horizontal' === axis ? labelGroup : null }
			</span>
			{ 'vertical' === axis ? labelSlot : null }
		</a>
	);
} );
