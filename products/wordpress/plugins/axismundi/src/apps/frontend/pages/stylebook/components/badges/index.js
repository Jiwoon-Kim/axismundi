import { Badge } from '../../../../components/badges';
import { Icon } from '../../../../components/material/icon';
import { NavigationBar } from '../../../../components/navigations/nav-bar';
import { NavigationRail } from '../../../../components/navigations/nav-rail';
import { PrimaryTabBar, SecondaryTabBar, TabPanels, Tabs } from '../../../../components/tabs';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import '../../stylebook-page.css';
import './badges.css';

const NAV_DESTINATIONS = [
	{ id: 'home', label: 'Home', icon: <Icon name="home" />, href: '#home' },
	{
		id: 'alerts',
		label: 'Alerts',
		icon: <Icon name="notifications" />,
		href: '#alerts',
		badge: <Badge label="9" />,
		badgeDescription: '9 notifications',
	},
	{
		id: 'messages',
		label: 'Messages',
		icon: <Icon name="mail" />,
		href: '#messages',
		badge: <Badge />,
		badgeDescription: 'New notification',
	},
];

// A maximum-count badge is 34dp against a 24dp icon, so this is the width at
// which the two published sections stop agreeing: the badge guidelines send it
// to the trailing edge, the navigation anatomy draws it on the icon.
const COLLISION_DESTINATIONS = [
	{
		id: 'photos',
		label: 'Photos',
		icon: <Icon name="image" />,
		href: '#photos',
		badge: <Badge label="999+" />,
		badgeDescription: '999 or more notifications',
	},
	{
		id: 'fonts',
		label: 'Fonts',
		icon: <Icon name="font_download" />,
		href: '#fonts',
		badge: <Badge />,
		badgeDescription: 'New notification',
	},
	{ id: 'documents', label: 'Documents', icon: <Icon name="description" />, href: '#documents' },
];

const TAB_ITEMS = [
	{ id: 'feed', label: 'Feed', icon: <Icon name="dynamic_feed" />, panel: 'Feed panel.' },
	{
		id: 'activity',
		label: 'Activity',
		icon: <Icon name="notifications" />,
		badge: <Badge label="999+" />,
		badgeDescription: '999+ notifications',
		panel: 'Activity panel.',
	},
	{ id: 'saved', label: 'Saved', icon: <Icon name="bookmark" />, panel: 'Saved panel.' },
];

const INLINE_TAB_ITEMS = [
	{ id: 'overview', label: 'Overview', panel: 'Overview panel.' },
	{
		id: 'updates',
		label: 'Updates',
		icon: <Icon name="update" />,
		badge: <Badge label="9" />,
		badgeDescription: '9 updates',
		panel: 'Updates panel.',
	},
	{ id: 'history', label: 'History', panel: 'History panel.' },
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

export function StylebookBadgesPage() {
	return (
		<Scaffold className="axismundi-social ax-stylebook ax-stylebook--badges">
			<div className="ax-stylebook-page">
				<nav className="ax-stylebook-page__navigation" aria-label="Stylebook">
					<a href="/social/stylebook">Stylebook</a>
					<a href="/social/stylebook/components/navigations">Navigation</a>
					<a href="/social/stylebook/components/tabs">Tabs</a>
				</nav>

				<section className="ax-stylebook-page__section" id="badges" aria-labelledby="ax-stylebook-badges-title">
					<header className="ax-stylebook-page__section-header">
						<p className="ax-stylebook-page__eyebrow">Material component</p>
						<h1 id="ax-stylebook-badges-title">Badges</h1>
					</header>

					<Group kicker="Badge owns its box, not an interaction target" title="Badge variants">
						<div className="ax-stylebook-badges__variants" aria-label="Badge variants">
							<div><Badge /><span>Small</span></div>
							<div><Badge label="8" /><span>Large</span></div>
							<div><Badge label="999+" /><span>Maximum label</span></div>
						</div>
						<p className="ax-stylebook-page__note">
							Small is 6dp; large is 16dp with label-small text. The 999+ specimen
							shows the expandable corner.full shape rather than a fixed 8dp radius.
						</p>
					</Group>

					<Group kicker="Published icon-corner placement" title="Navigation hosts">
						<div className="ax-stylebook-badges__navigation">
							<NavigationBar activeId="home" destinations={ NAV_DESTINATIONS } label="Badge navigation bar specimen" />
							<div className="ax-stylebook-badges__rail-frame">
								<NavigationRail activeId="alerts" destinations={ NAV_DESTINATIONS } label="Badge navigation rail specimen" />
							</div>
						</div>
						<p className="ax-stylebook-page__note">
							The actual NavigationItem slot anchors badges at the icon&rsquo;s upper trailing corner.
							The badge remains presentational; its count or status is appended to the destination name.
						</p>
					</Group>

					<Group kicker="Where the two published sections disagree" title="Horizontal item, maximum count">
						<div className="ax-stylebook-badges__navigation">
							<div className="ax-stylebook-badges__rail-frame">
								<NavigationRail
									activeId="photos"
									destinations={ COLLISION_DESTINATIONS }
									variant="expanded"
									label="Badge collision rail specimen"
								/>
							</div>
						</div>
						<p className="ax-stylebook-page__note">
							A vertical item carries its label underneath, so a badge on the icon collides with
							nothing and stays there at any width. A horizontal item is the case the badge
							guidelines name &mdash; an icon with a badge followed by text &mdash; and they
							answer it: place a large badge at the trailing edge, or use a small one. The
							navigation anatomy draws a large badge on a horizontal item&rsquo;s icon instead,
							which is the picture those same guidelines mark Don&rsquo;t. A small badge is too
							narrow to reach the label and stays on the icon.
						</p>
					</Group>

					<Group kicker="Tabs own their inline and stacked placement rules" title="Tab host">
						<div className="ax-stylebook-badges__tabs">
							<Tabs id="badge-tabs" label="Badge icon tab specimen" tabs={ TAB_ITEMS }>
								<PrimaryTabBar />
								<TabPanels />
							</Tabs>
							<Tabs id="badge-inline-tabs" label="Badge inline tab specimen" tabs={ INLINE_TAB_ITEMS }>
								<SecondaryTabBar />
								<TabPanels />
							</Tabs>
						</div>
						<p className="ax-stylebook-page__note">
							The actual tab bars preserve the tab&rsquo;s accessible name with the count appended:
							primary anchors on an icon, while secondary uses its published 4dp inline gap.
						</p>
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
