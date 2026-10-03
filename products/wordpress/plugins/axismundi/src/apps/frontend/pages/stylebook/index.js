import { CanonicalLayoutFixture } from './canonical-layout-fixtures';
import { StylebookButtonsPage } from './components/buttons';
import { StylebookIconButtonsPage } from './components/icon-buttons';
import { StylebookStylesPage } from './styles';

/**
 * Social runtime verification surface.
 *
 * Production components are added here as fixtures as they are implemented.
 * This page deliberately owns no demo component implementations.
 *
 * @return {import('@wordpress/element').ReactNode} Stylebook route content.
 */
export function StylebookPage( { component, layout, styles } ) {
	if ( 'buttons' === component ) {
		return <StylebookButtonsPage />;
	}

	if ( 'icon-buttons' === component ) {
		return <StylebookIconButtonsPage />;
	}

	if ( styles ) {
		return <StylebookStylesPage />;
	}

	if ( layout ) {
		return <CanonicalLayoutFixture layout={ layout } />;
	}

	return (
		<main className="axismundi-social ax-stylebook" aria-labelledby="ax-stylebook-title">
			<header className="ax-stylebook__header">
				<p className="ax-stylebook__eyebrow">Axismundi Social</p>
				<h1 id="ax-stylebook-title" className="ax-stylebook__title">Stylebook</h1>
			</header>
			<nav className="ax-stylebook__navigation" aria-label="Layout fixtures">
				<a href="/social/stylebook/styles">Styles</a>
				<a href="/social/stylebook/components/buttons">Buttons</a>
				<a href="/social/stylebook/components/icon-buttons">Icon buttons</a>
				<a href="/social/stylebook/layout/feed">Feed</a>
				<a href="/social/stylebook/layout/list-detail">List-detail</a>
				<a href="/social/stylebook/layout/supporting_pane">Supporting pane</a>
			</nav>
		</main>
	);
}
