import { PaneGroup } from '../../panes/pane-group';

/**
 * Canonical M3 feed topology. It arranges arbitrary feed items; it does not
 * prescribe cards, data loading, or item semantics.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children Feed items.
 * @return {import('@wordpress/element').ReactNode} Adaptive feed grid.
 */
export function FeedLayout( { children } ) {
	return <PaneGroup className="ax-feed-layout">{ children }</PaneGroup>;
}
