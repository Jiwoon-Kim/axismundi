import { Pane } from '../../panes/pane';
import { PaneGroup } from '../../panes/pane-group';

/**
 * Canonical M3 supporting-pane topology. Compact and medium windows reflow the
 * supporting content below the primary pane; expanded windows place it beside
 * the primary pane. Presentation of the supporting content is caller-owned.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.primary Primary content.
 * @param {import('@wordpress/element').ReactNode} props.supporting Contextual content.
 * @return {import('@wordpress/element').ReactNode} Supporting-pane layout.
 */
export function SupportingPaneLayout( { primary, supporting } ) {
	return (
		<PaneGroup className="ax-supporting-pane-layout">
			<Pane className="ax-supporting-pane-layout__primary">{ primary }</Pane>
			<Pane className="ax-supporting-pane-layout__supporting">{ supporting }</Pane>
		</PaneGroup>
	);
}
