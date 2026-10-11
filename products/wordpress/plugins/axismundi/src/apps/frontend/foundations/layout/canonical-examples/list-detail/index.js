import { Pane } from '../../panes/pane';
import { PaneGroup } from '../../panes/pane-group';
import { useWindowSizeClass } from '../../breakpoints/use-window-size-class';
import viewport from '../../breakpoints/viewport.json';

/*
 * How many panes this topology shows, by window size class. M3's list-detail
 * page publishes the table: compact 1, medium 1 recommended, expanded and
 * above 2, and a third pane only where `extra` is supplied at extra-large.
 */
const TWO_PANE_FROM = 'expanded';
const THREE_PANE_FROM = 'extraLarge';

const ORDER = Object.keys( viewport.windowSizeClasses );

function visiblePanes( sizeClass, extra ) {
	const at = ORDER.indexOf( sizeClass );

	if ( extra && at >= ORDER.indexOf( THREE_PANE_FROM ) ) {
		return 3;
	}

	return at >= ORDER.indexOf( TWO_PANE_FROM ) ? 2 : 1;
}

/**
 * Returns the pane count resolved by the list-detail layout at this viewport.
 * Consumers that need to render pane-dependent controls share this decision
 * instead of re-deriving its breakpoint.
 *
 * @param {boolean} [extra=false] Whether an optional third pane is supplied.
 * @return {number} Number of visible panes.
 */
export function useListDetailPaneCount( extra = false ) {
	return visiblePanes( useWindowSizeClass(), extra );
}

/**
 * Canonical M3 list-detail topology. Selection and navigation remain outside
 * this layout; compactPane only tells the layout which supplied pane is visible
 * in its single-pane presentation.
 *
 * WHAT IT PUBLISHES. The layout exposes its resolved pane count through
 * `useListDetailPaneCount` for callers, and through `data-panes` for observable
 * layout geometry. Two M3 rules are the caller's to render: "a back button
 * appears in detail view only for single-pane layouts", and "a selected state
 * appears only in list view for two-pane layouts". Measured before this existed
 * -- the fixture kept `aria-current="page"` on a list item at 420px, announcing
 * a current page while the detail it referred to was not on screen.
 *
 * The media queries stay. `use-window-size-class.js` draws the line itself:
 * anything a media query can express stays a media query, and the hook is for
 * the decisions CSS cannot make. Telling React how many panes are visible is
 * one of those. That does put the threshold in two places, which is why
 * `viewport.json` is read here rather than retyped, and why the validator holds
 * the stylesheet's own breakpoint to the same file.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.list Collection content.
 * @param {import('@wordpress/element').ReactNode} props.detail Selected-item content.
 * @param {import('@wordpress/element').ReactNode} [props.extra] Optional extra context.
 * @param {'list'|'detail'} [props.compactPane] Visible pane below expanded width.
 * @return {import('@wordpress/element').ReactNode} List-detail layout.
 */
export function ListDetailLayout( {
	list,
	detail,
	extra,
	compactPane = 'list',
} ) {
	const panes = useListDetailPaneCount( Boolean( extra ) );

	return (
		<PaneGroup
			className="ax-list-detail-layout"
			data-compact-pane={ compactPane }
			data-has-extra={ extra ? '' : undefined }
			data-panes={ panes }
		>
			<Pane className="ax-list-detail-layout__list">{ list }</Pane>
			<Pane className="ax-list-detail-layout__detail">{ detail }</Pane>
			{ extra ? <Pane className="ax-list-detail-layout__extra">{ extra }</Pane> : null }
		</PaneGroup>
	);
}
