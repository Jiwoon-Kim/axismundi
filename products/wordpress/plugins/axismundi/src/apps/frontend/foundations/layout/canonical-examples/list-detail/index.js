import { Pane } from '../../panes/pane';
import { PaneGroup } from '../../panes/pane-group';

/**
 * Canonical M3 list-detail topology. Selection and navigation remain outside
 * this layout; compactPane only tells the layout which supplied pane is visible
 * in its single-pane presentation.
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
	return (
		<PaneGroup
			className="ax-list-detail-layout"
			data-compact-pane={ compactPane }
			data-has-extra={ extra ? '' : undefined }
		>
			<Pane className="ax-list-detail-layout__list">{ list }</Pane>
			<Pane className="ax-list-detail-layout__detail">{ detail }</Pane>
			{ extra ? <Pane className="ax-list-detail-layout__extra">{ extra }</Pane> : null }
		</PaneGroup>
	);
}
