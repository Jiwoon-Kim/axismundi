import { CanonicalLayoutFixture } from './canonical-layout-fixtures';
import { StylebookButtonsPage } from './components/buttons';
import { StylebookCardsPage } from './components/cards';
import { StylebookCarouselsPage } from './components/carousels';
import { StylebookDividersPage } from './components/dividers';
import { StylebookSplitButtonsPage } from './components/split-buttons';
import { StylebookButtonGroupsPage } from './components/button-groups';
import { StylebookIconButtonsPage } from './components/icon-buttons';
import { StylebookAppBarsPage } from './components/app-bars';
import { StylebookNavigationsPage } from './components/navigations';
import { StylebookListsPage } from './components/lists';
import { StylebookTabsPage } from './components/tabs';
import { StylebookBadgesPage } from './components/badges';
import { StylebookSurfacesPage } from './components/surfaces';
import { StylebookMenusPage } from './components/menus';
import { StylebookFabsPage } from './components/fabs';
import { StylebookTooltipsPage } from './components/tooltips';
import { lazy, Suspense } from '@wordpress/element';
import { StylebookStylesPage } from './styles';
import { StylebookMotionPage } from './styles/motion';

const StylebookLoadingIndicatorsPage = lazy( () => import( './components/loading-indicators' )
	.then( ( module ) => ( { default: module.StylebookLoadingIndicatorsPage } ) ) );
const StylebookProgressIndicatorsPage = lazy( () => import( './components/progress-indicators' )
	.then( ( module ) => ( { default: module.StylebookProgressIndicatorsPage } ) ) );

/**
 * Social runtime verification surface.
 *
 * Production components are added here as fixtures as they are implemented.
 * This page deliberately owns no demo component implementations.
 *
 * @return {import('@wordpress/element').ReactNode} Stylebook route content.
 */
export function StylebookPage( { component, layout, style, styles } ) {
	if ( 'progress-indicators' === component ) {
		return <Suspense fallback={ <p role="status">Loading...</p> }><StylebookProgressIndicatorsPage /></Suspense>;
	}
	if ( 'loading-indicators' === component ) {
		return <Suspense fallback={ <p role="status">Loading…</p> }><StylebookLoadingIndicatorsPage /></Suspense>;
	}
	if ( 'buttons' === component ) {
		return <StylebookButtonsPage />;
	}

	if ( 'icon-buttons' === component ) {
		return <StylebookIconButtonsPage />;
	}

	if ( 'button-groups' === component ) {
		return <StylebookButtonGroupsPage />;
	}

	if ( 'dividers' === component ) {
		return <StylebookDividersPage />;
	}

	if ( 'cards' === component ) {
		return <StylebookCardsPage />;
	}

	if ( 'carousels' === component ) {
		return <StylebookCarouselsPage />;
	}

	if ( 'lists' === component ) {
		return <StylebookListsPage />;
	}

	if ( 'tabs' === component ) {
		return <StylebookTabsPage />;
	}

	if ( 'badges' === component ) {
		return <StylebookBadgesPage />;
	}

	if ( 'surfaces' === component ) {
		return <StylebookSurfacesPage />;
	}

	if ( 'menus' === component ) {
		return <StylebookMenusPage />;
	}

	if ( 'fabs' === component ) {
		return <StylebookFabsPage />;
	}

	if ( 'tooltips' === component ) {
		return <StylebookTooltipsPage />;
	}

	if ( 'split-buttons' === component ) {
		return <StylebookSplitButtonsPage />;
	}

	if ( 'navigations' === component ) {
		return <StylebookNavigationsPage />;
	}

	if ( 'app-bars' === component ) {
		return <StylebookAppBarsPage />;
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
				<a href="/stylebook/styles">Styles</a>
				<a href="/stylebook/components/buttons">Buttons</a>
				<a href="/stylebook/components/icon-buttons">Icon buttons</a>
				<a href="/stylebook/components/button-groups">Button groups</a>
				<a href="/stylebook/components/split-buttons">Split buttons</a>
				<a href="/stylebook/components/cards">Cards</a>
				<a href="/stylebook/components/carousels">Carousels</a>
				<a href="/stylebook/components/lists">Lists</a>
				<a href="/stylebook/components/tabs">Tabs</a>
				<a href="/stylebook/components/badges">Badges</a>
				<a href="/stylebook/components/surfaces">Surfaces</a>
				<a href="/stylebook/components/menus">Menus</a>
				<a href="/stylebook/components/fabs">FABs</a>
				<a href="/stylebook/components/tooltips">Tooltips</a>
				<a href="/stylebook/components/loading-indicators">Loading indicators</a>
				<a href="/stylebook/components/progress-indicators">Progress indicators</a>
				<a href="/stylebook/components/dividers">Dividers</a>
				<a href="/stylebook/components/navigations">Navigation</a>
				<a href="/stylebook/components/app-bars">App bars</a>
				<a href="/stylebook/layout/topology">Scaffold topology</a>
				<a href="/stylebook/layout/feed">Feed</a>
				<a href="/stylebook/layout/list-detail">List-detail</a>
				<a href="/stylebook/layout/supporting_pane">Supporting pane</a>
			</nav>
		</main>
	);
}
