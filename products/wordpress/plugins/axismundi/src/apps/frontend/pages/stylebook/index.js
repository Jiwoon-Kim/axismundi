import { LayoutTopologyFixtures } from './layout-topology-fixtures';

/**
 * Social runtime verification surface.
 *
 * Production components are added here as fixtures as they are implemented.
 * This page deliberately owns no demo component implementations.
 *
 * @return {import('@wordpress/element').ReactNode} Stylebook route content.
 */
export function StylebookPage() {
	return (
		<main className="axismundi-social ax-stylebook" aria-labelledby="ax-stylebook-title">
			<header className="ax-stylebook__header">
				<p className="ax-stylebook__eyebrow">Axismundi Social</p>
				<h1 id="ax-stylebook-title" className="ax-stylebook__title">Stylebook</h1>
			</header>
			<LayoutTopologyFixtures />
		</main>
	);
}
