import { FeedLayout } from '../../foundations/layout/canonical-examples/feed';
import { ListDetailLayout } from '../../foundations/layout/canonical-examples/list-detail';
import { Pane } from '../../foundations/layout/panes/pane';
import { Scaffold } from '../../foundations/layout/scaffold';
import { useWindowSizeClass } from '../../foundations/layout/breakpoints/use-window-size-class';
import { SupportingPaneLayout } from '../../foundations/layout/canonical-examples/supporting-pane';

const layoutDefinitions = {
	feed: {
		title: 'Feed',
		description: 'A single pane whose arbitrary items reflow into a grid as space grows.',
	},
	'list-detail': {
		title: 'List-detail',
		description: 'List and detail panes are shown together from the expanded breakpoint.',
	},
	supporting_pane: {
		title: 'Supporting pane',
		description: 'Supporting content reflows below the primary pane before becoming co-planar.',
	},
};

function FixturePane( { children } ) {
	return <Pane className="ax-canonical-layout-fixture__pane">{ children }</Pane>;
}

function FeedFixture() {
	return (
		<FeedLayout>
			<FixturePane>Feed item 1</FixturePane>
			<FixturePane>Feed item 2</FixturePane>
			<FixturePane>Feed item 3</FixturePane>
			<FixturePane>Feed item 4</FixturePane>
			<FixturePane>Feed item 5</FixturePane>
		</FeedLayout>
	);
}

function ListDetailFixture() {
	return (
		<ListDetailLayout
			list={ <FixturePane>List pane</FixturePane> }
			detail={ <FixturePane>Detail pane</FixturePane> }
			extra={ <FixturePane>Extra pane</FixturePane> }
		/>
	);
}

function SupportingPaneFixture() {
	return (
		<SupportingPaneLayout
			primary={ <FixturePane>Primary pane</FixturePane> }
			supporting={ <FixturePane>Supporting pane</FixturePane> }
		/>
	);
}

/**
 * Developer-only canonical layout verification. The placeholders deliberately
 * contain no Material components or product behavior.
 *
 * @param {Object} props Component props.
 * @param {'feed'|'list-detail'|'supporting_pane'} props.layout Canonical layout key.
 * @return {import('@wordpress/element').ReactNode} Canonical layout fixture.
 */
export function CanonicalLayoutFixture( { layout } ) {
	const definition = layoutDefinitions[ layout ];
	const fixture = {
		feed: <FeedFixture />,
		'list-detail': <ListDetailFixture />,
		supporting_pane: <SupportingPaneFixture />,
	}[ layout ];

	/*
	 * The first consumer of the window size class, and the only way to verify the
	 * hook at all: a signal with nobody reading it cannot be measured. It is read
	 * here and printed, not used to choose anything -- the fixtures' own geometry
	 * stays in their stylesheets, where a media query belongs.
	 */
	const sizeClass = useWindowSizeClass();

	return (
		<Scaffold className="axismundi-social ax-stylebook">
			<section className="ax-canonical-layout-fixture" aria-label={ definition.title }>
				<p className="ax-canonical-layout-fixture__readout" data-size-class={ sizeClass }>
					window size class: <strong>{ sizeClass }</strong> &middot; { window.innerWidth }px
				</p>
				{ fixture }
			</section>
		</Scaffold>
	);
}
