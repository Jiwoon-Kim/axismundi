import { Pane } from '../../layouts/pane';
import { PaneGroup } from '../../layouts/pane-group';

function FixturePane( { children } ) {
	return <Pane className="ax-layout-fixture__pane">{ children }</Pane>;
}

/**
 * Developer-only layout fixtures. They use the production Pane and PaneGroup
 * primitives while intentionally avoiding production Material components.
 *
 * @return {import('@wordpress/element').ReactNode} Grid topology fixtures.
 */
export function LayoutTopologyFixtures() {
	return (
		<section className="ax-layout-fixture" aria-labelledby="ax-layout-fixture-title">
			<header className="ax-layout-fixture__header">
				<h2 id="ax-layout-fixture-title">Layout topology</h2>
				<p>Pane and PaneGroup grid verification fixtures.</p>
			</header>

			<section className="ax-layout-fixture__case" aria-labelledby="ax-one-pane-title">
				<h3 id="ax-one-pane-title">One pane</h3>
				<PaneGroup className="ax-layout-fixture__group ax-layout-fixture__group--one">
					<FixturePane>Primary</FixturePane>
				</PaneGroup>
			</section>

			<section className="ax-layout-fixture__case" aria-labelledby="ax-two-pane-title">
				<h3 id="ax-two-pane-title">Two panes</h3>
				<PaneGroup className="ax-layout-fixture__group ax-layout-fixture__group--two">
					<FixturePane>Primary</FixturePane>
					<FixturePane>Supporting</FixturePane>
				</PaneGroup>
			</section>

			<section className="ax-layout-fixture__case" aria-labelledby="ax-three-pane-title">
				<h3 id="ax-three-pane-title">Three panes</h3>
				<PaneGroup className="ax-layout-fixture__group ax-layout-fixture__group--three">
					<FixturePane>List</FixturePane>
					<FixturePane>Detail</FixturePane>
					<FixturePane>Supporting</FixturePane>
				</PaneGroup>
			</section>
		</section>
	);
}
