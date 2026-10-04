/**
 * Material 3 Navigation rail.
 *
 * The rail host for `NavigationItem`, sibling to `NavigationBar`. Published
 * values are in `products/styleguide/_data/navigation_rail.yml`, checked by
 * `tools/validators/validate_styleguide_navigation_rail.py`; what the two hosts
 * share is in `navigation_item.yml`.
 *
 * Named `nav-rail` after `md.comp.nav-rail.*`, the namespace this implements.
 * `md.comp.navigation-rail.*` is the deprecated baseline, whose container is 80dp
 * wide -- which is also this rail's *narrow* width, so the two are easy to
 * confuse and the names are kept apart deliberately.
 *
 * WHAT THIS IMPLEMENTS, AND WHAT IT DOES NOT, is recorded in the yml under
 * `scope`. Shipped: collapsed and expanded standard, item alignment, the optional
 * fill and divider, menu and FAB slots, the full-width target area, arrow keys.
 * Deferred with reasons: the modal expanded layout, hiding when collapsed and the
 * transition between variants, predictive back, a FAB component, and the badge
 * moving beside the label when expanded.
 *
 * THE VARIANT CHOOSES THE ITEM ORIENTATION, and that is an inference. No sentence
 * binds them, but the active indicator "hugs the label text in the expanded nav
 * rail" and the expanded container starts at 220dp, which only makes sense with
 * the label beside the icon. So collapsed uses vertical items and expanded
 * horizontal ones, and `itemOrientation` can override it.
 *
 * THE TARGET AREA IS THE FULL WIDTH; THE INDICATOR IS NOT. Published twice, as a
 * rule rather than a measurement: "The navigation item's target area always spans
 * the full width of the nav rail, even if the item container hugs its contents."
 * The item is therefore stretched by this host and its indicator left
 * content-sized -- the arrangement is the host's job, which is why the item
 * publishes no width of its own.
 *
 * ARROWS ARE ADDED TO THE TAB ORDER, NOT SUBSTITUTED FOR IT. The accessibility
 * table publishes "Tab / Arrows -- Navigate between interactive elements" and
 * "Space / Enter -- Selects an interactive element". Both rows are true at once,
 * so there is no roving `tabindex` here: every item stays a tab stop and the
 * arrow keys only move focus. A roving `tabindex` would implement the second row
 * by deleting the first, which is the mistake this repository already made once
 * on the button group.
 *
 * NO WINDOW OBSERVATION, same as the bar. "NEVER USE THE NAVIGATION RAIL AND
 * NAVIGATION BAR SIMULTANEOUSLY" is a constraint on the two together, which
 * neither can enforce alone; `scaffold.css` satisfies it by swapping at 600px.
 *
 * @param {Object} props Component props.
 * @param {Array<{id: string, label: string, icon: import('@wordpress/element').ReactNode, href: string, badge?: import('@wordpress/element').ReactNode}>} props.destinations Three to seven destinations, in fixed order.
 * @param {string} props.activeId `id` of the current destination. One is always active.
 * @param {string} props.label Accessible name for the navigation landmark.
 * @param {'collapsed'|'expanded'} [props.variant='collapsed'] Published variant.
 * @param {'vertical'|'horizontal'} [props.itemOrientation] Overrides the variant's item layout.
 * @param {'top'|'center'} [props.alignment='top'] Alignment of the items as a group.
 * @param {boolean} [props.narrow=false] Collapsed only: the published 80dp width.
 * @param {boolean} [props.filled=false] Turn the container fill on.
 * @param {boolean} [props.divider=false] Vertical divider on the content-facing edge.
 * @param {import('@wordpress/element').ReactNode} [props.menu] Menu button. Top-aligned.
 * @param {import('@wordpress/element').ReactNode} [props.fab] FAB. Top-aligned, resting elevation 0.
 * @param {string} [props.className] Additional component class name.
 * @return {import('@wordpress/element').ReactNode} Navigation rail.
 */

import { Divider } from '../dividers/divider';
import { NavigationItem } from './navigation-item';
import { useRef } from '@wordpress/element';
import warning from '@wordpress/warning';

const VARIANTS = [ 'collapsed', 'expanded' ];
const ORIENTATIONS = [ 'vertical', 'horizontal' ];
const ALIGNMENTS = [ 'top', 'center' ];

/* See the header: an inference, overridable by `itemOrientation`. */
const ORIENTATION_FOR_VARIANT = { collapsed: 'vertical', expanded: 'horizontal' };

/* "Can contain 3-7 destinations plus an optional FAB" */
const DESTINATIONS_MINIMUM = 3;
const DESTINATIONS_MAXIMUM = 7;
/* "If there are more than five destinations, consider using a modal expanded nav rail instead." */
const MODAL_ADVISED_ABOVE = 5;

export function NavigationRail( {
	destinations = [],
	activeId,
	label,
	variant = 'collapsed',
	itemOrientation,
	alignment = 'top',
	narrow = false,
	filled = false,
	divider = false,
	menu,
	fab,
	className,
	...props
} ) {
	const itemsRef = useRef();

	let kind = variant;
	if ( ! VARIANTS.includes( kind ) ) {
		warning( `NavigationRail: unknown variant "${ variant }"; using collapsed.` );
		kind = 'collapsed';
	}

	let axis = itemOrientation ?? ORIENTATION_FOR_VARIANT[ kind ];
	if ( ! ORIENTATIONS.includes( axis ) ) {
		warning( `NavigationRail: unknown itemOrientation "${ itemOrientation }"; using the variant's own.` );
		axis = ORIENTATION_FOR_VARIANT[ kind ];
	}

	let group = alignment;
	if ( ! ALIGNMENTS.includes( group ) ) {
		warning( `NavigationRail: unknown alignment "${ alignment }"; using top.` );
		group = 'top';
	}

	if ( DESTINATIONS_MINIMUM > destinations.length ) {
		warning(
			`NavigationRail: ${ destinations.length } destinations. The collapsed rail "should contain 3-7 navigation items".`
		);
	}
	if ( DESTINATIONS_MAXIMUM < destinations.length ) {
		warning(
			`NavigationRail: ${ destinations.length } destinations. M3 publishes 3-7; above that the rail is the wrong component.`
		);
	} else if ( MODAL_ADVISED_ABOVE < destinations.length ) {
		/*
		 * Advice with a threshold rather than a limit, so it is said once and
		 * nothing is refused: "If there are more than five destinations, consider
		 * using a modal expanded nav rail instead."
		 *
		 * It applies to the expanded variant too. The advice names the MODAL
		 * expanded rail, and the expanded variant implemented here is the standard
		 * one -- modal is deferred -- so switching to `expanded` does not satisfy
		 * it. This condition was first written to skip the expanded variant, which
		 * silenced the advice for exactly the configuration that still needs it.
		 */
		warning(
			`NavigationRail: ${ destinations.length } destinations. M3 suggests a modal expanded rail above five, and the modal layout is not implemented yet.`
		);
	}

	if ( destinations.length && ! destinations.some( ( { id } ) => id === activeId ) ) {
		warning(
			`NavigationRail: activeId "${ activeId }" matches no destination; one destination is always active.`
		);
	}

	if ( ! label ) {
		warning( 'NavigationRail: `label` is required -- a navigation landmark needs a name.' );
	}

	/* `narrow` is a collapsed-only figure; there is no published narrow expanded rail. */
	if ( narrow && 'collapsed' !== kind ) {
		warning( 'NavigationRail: `narrow` is published for the collapsed rail only; ignoring it.' );
	}

	/*
	 * Arrow keys move focus between items and nothing else. Scoped to the items
	 * container rather than the whole rail, because the menu and the FAB are
	 * reached with `Tab` -- "From the FAB or menu, Tab brings the person to the
	 * navigation items. Tab or Arrows then navigate between items."
	 *
	 * Both axes are honoured regardless of the item orientation: the rail is
	 * vertical in both variants, and a person pressing Right on a horizontal item
	 * is asking for the next destination either way. Home and End are not
	 * published and are not added.
	 */
	const moveFocus = ( event ) => {
		const keys = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 };
		const step = keys[ event.key ];
		if ( ! step ) {
			return;
		}
		const items = [ ...( itemsRef.current?.querySelectorAll( '.ax-nav-rail__item' ) ?? [] ) ];
		const from = items.indexOf( document.activeElement );
		if ( 0 > from ) {
			return;
		}
		const to = items[ from + step ];
		if ( ! to ) {
			/*
			 * No wrap. Nothing publishes one, and stopping at the ends keeps the
			 * published `Tab` row usable -- a wrapping arrow key in a landmark
			 * traps a keyboard user who expected to leave it.
			 */
			return;
		}
		event.preventDefault();
		to.focus();
	};

	return (
		<nav
			{ ...props }
			className={ [ 'ax-nav-rail', className ].filter( Boolean ).join( ' ' ) }
			data-variant={ kind }
			data-item-orientation={ axis }
			data-alignment={ group }
			data-narrow={ narrow && 'collapsed' === kind ? '' : undefined }
			data-filled={ filled ? '' : undefined }
			aria-label={ label || undefined }
		>
			{ /*
			 * "The menu icon and FAB should always be top-aligned", which is why
			 * they sit outside the items group that `alignment` moves. Slots only:
			 * the menu's collapse/expand behaviour and the FAB component are
			 * deferred, and what goes in them is the caller's.
			 */ }
			{ menu ? <div className="ax-nav-rail__menu">{ menu }</div> : null }
			{ fab ? <div className="ax-nav-rail__fab">{ fab }</div> : null }

			<div className="ax-nav-rail__items" ref={ itemsRef } onKeyDown={ moveFocus }>
				{ destinations.map( ( { id, label: destination, icon, href, badge } ) => (
					<NavigationItem
						key={ id }
						className="ax-nav-rail__item"
						icon={ icon }
						label={ destination }
						href={ href }
						badge={ badge }
						orientation={ axis }
						active={ id === activeId }
					/>
				) ) }
			</div>

			{ /*
			 * "A vertical divider can help separate the rail from app content. The
			 * divider should be positioned on the edge of the rail container
			 * that's adjacent to the app's content area." Placement is the
			 * stylesheet's; this only decides whether there is one.
			 */ }
			{ divider ? (
				<Divider orientation="vertical" className="ax-nav-rail__divider" />
			) : null }
		</nav>
	);
}
