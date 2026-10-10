import { Icon } from '../../../../components/material/icon';
import {
	PrimaryTabBar,
	SecondaryTabBar,
	TabPanels,
	Tabs,
} from '../../../../components/tabs';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import { useEffect, useRef, useState } from '@wordpress/element';
import '../../stylebook-page.css';
import './tabs.css';

const PRIMARY_TABS = [
	{ id: 'overview', label: 'Overview', icon: <Icon name="dashboard" />, panel: 'Primary overview panel.' },
	{ id: 'activity', label: 'Activity', icon: <Icon name="notifications" />, panel: 'Primary activity panel.' },
	{ id: 'members', label: 'Members', icon: <Icon name="group" />, panel: 'Primary members panel.' },
];

const SECONDARY_TABS = [
	{ id: 'details', label: 'Details', panel: 'Secondary details panel.' },
	{ id: 'history', label: 'History', panel: 'Secondary history panel.' },
	{ id: 'settings', label: 'Settings', panel: 'Secondary settings panel.' },
];

// Long enough to actually overflow. A bar whose scrollWidth equals its
// clientWidth demonstrates nothing about scrolling, wheel movement, or
// scrolling a focused tab back into view.
const SCROLLABLE_TABS = [
	{ id: 'today', label: 'Today', panel: 'Today panel.' },
	{ id: 'following', label: 'Following', panel: 'Following panel.' },
	{ id: 'bookmarks', label: 'Saved bookmarks', panel: 'Bookmarks panel.' },
	{ id: 'mentions', label: 'Mentions and replies', panel: 'Mentions panel.' },
	{ id: 'collections', label: 'Shared collections', disabled: true, panel: 'Collections panel.' },
	{ id: 'preferences', label: 'Reading preferences', panel: 'Preferences panel.' },
	{ id: 'people', label: 'People you follow', panel: 'People panel.' },
	{ id: 'groups', label: 'Groups and forums', panel: 'Groups panel.' },
	{ id: 'calendar', label: 'Calendar and events', panel: 'Calendar panel.' },
	{ id: 'archive', label: 'Archive', panel: 'Archive panel.' },
];

// A label is optional; a name is not. The kit publishes an icon-only layout for
// the primary style, and nothing in the component branches on the variant to
// allow it, so both styles accept it.
const ICON_ONLY_TABS = [
	{ id: 'home', name: 'Home', icon: <Icon name="home" />, panel: 'Home panel.' },
	{ id: 'search', name: 'Search', icon: <Icon name="search" />, panel: 'Search panel.' },
	{ id: 'alerts', name: 'Alerts', icon: <Icon name="notifications" />, panel: 'Alerts panel.' },
];

// The nested set owns its own state root, which is why the bar and the panel
// view are siblings rather than one wrapper: a secondary set inside a primary
// panel shares nothing with the set above it.
const NESTED_TABS = [
	{ id: 'recent', label: 'Recent', panel: 'Nested recent panel.' },
	{ id: 'popular', label: 'Popular', panel: 'Nested popular panel.' },
];

function NestedPanel() {
	return (
		<Tabs id="tabs-nested-inner" label="Nested secondary specimen" tabs={ NESTED_TABS }>
			<SecondaryTabBar />
			<TabPanels />
		</Tabs>
	);
}

const NESTING_TABS = [
	{ id: 'feed', label: 'Feed', panel: <NestedPanel /> },
	{ id: 'about', label: 'About', panel: 'Outer about panel.' },
];

function Group( { children, kicker, title } ) {
	return (
		<section className="ax-stylebook-page__group" aria-label={ title }>
			<header className="ax-stylebook-page__group-header">
				<p className="ax-stylebook-page__group-kicker">{ kicker }</p>
				<h2>{ title }</h2>
			</header>
			{ children }
		</section>
	);
}

function MetricsSample() {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const host = hostRef.current;
		const primary = host?.querySelector( '[data-variant="primary"] .ax-tab-bar__tab' );
		const secondary = host?.querySelector( '[data-variant="secondary"] .ax-tab-bar__tab' );
		const tabs = [ ...( host?.querySelectorAll( '[role="tab"]' ) ?? [] ) ];
		if ( ! primary || ! secondary ) {
			return;
		}
		setMetrics( {
			primaryHeight: Math.round( primary.getBoundingClientRect().height ),
			secondaryHeight: Math.round( secondary.getBoundingClientRect().height ),
			rovingStops: tabs.filter( ( tab ) => 0 === tab.tabIndex ).length,
		} );
	}, [] );

	return (
		<div ref={ hostRef } className="ax-stylebook-tabs__samples">
			<Tabs id="tabs-primary-metrics" label="Primary tab specimen" tabs={ PRIMARY_TABS }>
				<PrimaryTabBar />
				<TabPanels />
			</Tabs>
			<Tabs id="tabs-secondary-metrics" label="Secondary tab specimen" tabs={ SECONDARY_TABS }>
				<SecondaryTabBar />
				<TabPanels />
			</Tabs>
			<p className="ax-stylebook-page__note">
				{ metrics
					? `primary ${ metrics.primaryHeight }px · secondary ${ metrics.secondaryHeight }px · roving tab stops ${ metrics.rovingStops }`
					: 'Measuring...' }
			</p>
		</div>
	);
}

export function StylebookTabsPage() {
	return (
		<Scaffold className="axismundi-social ax-stylebook ax-stylebook--tabs">
			<div className="ax-stylebook-page">
				<nav className="ax-stylebook-page__navigation" aria-label="Stylebook">
					<a href="/social/stylebook">Stylebook</a>
					<a href="/social/stylebook/components/lists">Lists</a>
					<a href="/social/stylebook/components/navigations">Navigation</a>
				</nav>

				<section className="ax-stylebook-page__section" id="tabs" aria-labelledby="ax-stylebook-tabs-title">
					<header className="ax-stylebook-page__section-header">
						<p className="ax-stylebook-page__eyebrow">Material component</p>
						<h1 id="ax-stylebook-tabs-title">Tabs</h1>
					</header>

					<Group kicker="Primary and secondary stay distinct token families" title="Variants and panels">
						<MetricsSample />
					</Group>

					<Group kicker="Long labels retain their natural width and scroll" title="Scrollable tabs">
						<div className="ax-stylebook-tabs__samples ax-stylebook-tabs__samples--scrollable">
							<Tabs id="tabs-scrollable" label="Scrollable tab specimen" tabs={ SCROLLABLE_TABS }>
								<PrimaryTabBar scrollable />
								<TabPanels />
							</Tabs>
						</div>
						<p className="ax-stylebook-page__note">
							A vertical wheel moves this bar sideways while it still has somewhere to go,
							and releases the gesture to the page at either end. There is no drag here the
							way the Carousel has one, so on the desktop the wheel is the pointer&rsquo;s
							only way through.
						</p>
					</Group>

					<Group kicker="A label is optional; a name is not" title="Icon only">
						<div className="ax-stylebook-tabs__samples">
							<Tabs id="tabs-icon-only" label="Icon-only tab specimen" tabs={ ICON_ONLY_TABS }>
								<PrimaryTabBar />
								<TabPanels />
							</Tabs>
						</div>
						<p className="ax-stylebook-page__note">
							The kit publishes this layout for the primary style only. It is unspecified for
							secondary rather than forbidden, and allowing it costs no branch, so both
							accept it. An icon-only tab carries its name on the tab itself, because M3
							warns that an icon&rsquo;s meaning may not be clear.
						</p>
					</Group>

					<Group kicker="Two state roots, no shared controller" title="Nested tab sets">
						<div className="ax-stylebook-tabs__samples">
							<Tabs id="tabs-nesting" label="Outer primary specimen" tabs={ NESTING_TABS }>
								<PrimaryTabBar />
								<TabPanels />
							</Tabs>
						</div>
						<p className="ax-stylebook-page__note">
							The secondary set lives inside the outer panel with its own state root. This is
							what the bar/panel split buys: a wrapper owning both would have forced the
							inner set to share the outer selection.
						</p>
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
