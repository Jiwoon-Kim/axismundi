import { CanonicalLayoutFixture } from './canonical-layout-fixtures';
import { StylebookButtonsPage } from './components/buttons';
import { StylebookCardsPage } from './components/cards';
import { StylebookSplitButtonsPage } from './components/split-buttons';
import { StylebookButtonGroupsPage } from './components/button-groups';
import { StylebookIconButtonsPage } from './components/icon-buttons';
import { StylebookStylesPage } from './styles';
import { StylebookMotionPage } from './styles/motion';

/**
 * Social runtime verification surface.
 *
 * Production components are added here as fixtures as they are implemented.
 * This page deliberately owns no demo component implementations.
 *
 * @return {import('@wordpress/element').ReactNode} Stylebook route content.
 */
export function StylebookPage( { component, layout, style, styles } ) {
	if ( 'buttons' === component ) {
		return <StylebookButtonsPage />;
	}

	if ( 'icon-buttons' === component ) {
		return <StylebookIconButtonsPage />;
	}

	if ( 'button-groups' === component ) {
		return <StylebookButtonGroupsPage />;
	}

	if ( 'cards' === component ) {
		return <StylebookCardsPage />;
	}

	if ( 'split-buttons' === component ) {
		return <StylebookSplitButtonsPage />;
	}

	if ( 'motion' === style ) {
		return <StylebookMotionPage />;
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
				<a href="/social/stylebook/components/button-groups">Button groups</a>
				<a href="/social/stylebook/components/split-buttons">Split buttons</a>
				<a href="/social/stylebook/layout/feed">Feed</a>
				<a href="/social/stylebook/layout/list-detail">List-detail</a>
				<a href="/social/stylebook/layout/supporting_pane">Supporting pane</a>
			</nav>
		</main>
	);
}
