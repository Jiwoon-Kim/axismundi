import { FeedLayout } from '../../foundations/layout/canonical-examples/feed';
import { ListDetailLayout } from '../../foundations/layout/canonical-examples/list-detail';
import { Pane } from '../../foundations/layout/panes/pane';
import { Scaffold } from '../../foundations/layout/scaffold';
import { useWindowSizeClass } from '../../foundations/layout/breakpoints/use-window-size-class';
import { SupportingPaneLayout } from '../../foundations/layout/canonical-examples/supporting-pane';
import { AppBar } from '../../components/app-bars/app-bar';
import { IconButton } from '../../components/buttons/icon-button';
import { Card } from '../../components/cards/card';
import { Icon } from '../../components/material/icon';
import { NavigationBar } from '../../components/navigations/nav-bar';
import { NavigationRail } from '../../components/navigations/nav-rail';
import { useEffect, useRef, useState } from '@wordpress/element';

const layoutDefinitions = {
	topology: {
		title: 'Scaffold topology',
		description: 'The chrome and content regions are measured separately from canonical layout composition.',
	},
	feed: {
		title: 'Feed',
		description: 'An activity feed keeps individual objects scannable while its card grid reflows.',
	},
	'list-detail': {
		title: 'List-detail',
		description: 'An object directory keeps its selected detail beside the collection when space permits.',
	},
	supporting_pane: {
		title: 'Supporting pane',
		description: 'A conversation keeps its contextual people and activity close without crowding the reading pane.',
	},
};

const destinations = [
	{
		id: 'home',
		label: 'Home',
		icon: <Icon name="home" />,
		href: '/social/stylebook/layout/feed',
	},
	{
		id: 'explore',
		label: 'Explore',
		icon: <Icon name="explore" />,
		href: '/social/stylebook/layout/list-detail',
	},
	{
		id: 'inbox',
		label: 'Inbox',
		icon: <Icon name="forum" />,
		href: '/social/stylebook/layout/supporting_pane',
	},
];

function DemoPane( { children, className } ) {
	return <Pane className={ [ 'ax-canonical-layout-demo__pane', className ].filter( Boolean ).join( ' ' ) }>{ children }</Pane>;
}

function DemoShell( { activeId, title, subtitle, children, className } ) {
	const appBar = (
		<AppBar
			title={ title }
			subtitle={ subtitle }
			leading={ <IconButton label="Open navigation" icon={ <Icon name="menu" /> } variant="standard" /> }
			actions={ (
				<>
					<IconButton label="Search" icon={ <Icon name="search" /> } variant="standard" />
					<IconButton label="Account" icon={ <Icon name="account_circle" /> } variant="standard" />
				</>
			) }
		/>
	);

	return (
		<Scaffold
			className={ [ 'axismundi-social', 'ax-stylebook', 'ax-canonical-layout-demo', className ].filter( Boolean ).join( ' ' ) }
			appBar={ appBar }
			navigationBar={ <NavigationBar destinations={ destinations } activeId={ activeId } label="Primary navigation" /> }
			navigationRail={ <NavigationRail destinations={ destinations } activeId={ activeId } label="Primary navigation" filled divider /> }
		>
			{ children }
		</Scaffold>
	);
}

function readTopology( root ) {
	const rail = root.querySelector( '.ax-scaffold__rail' );
	const content = root.querySelector( '.ax-scaffold__content' );
	const appBar = root.querySelector( '.ax-scaffold__app-bar' );

	if ( ! rail || ! content || ! appBar ) {
		return null;
	}

	const railBounds = rail.getBoundingClientRect();
	const contentBounds = content.getBoundingClientRect();
	const appBarBounds = appBar.getBoundingClientRect();
	const scrollContainers = [ ...root.querySelectorAll( '*' ) ].filter( ( element ) => {
		const { overflowY } = window.getComputedStyle( element );
		return ( 'auto' === overflowY || 'scroll' === overflowY ) && element.scrollHeight > element.clientHeight;
	} );

	return {
		railRight: Math.round( railBounds.right ),
		contentLeft: Math.round( contentBounds.left ),
		appBarLeft: Math.round( appBarBounds.left ),
		scaffoldScrollContainers: scrollContainers.length,
	};
}

function TopologyFixture( { sizeClass } ) {
	const fixtureRef = useRef( null );
	const [ topology, setTopology ] = useState( null );

	useEffect( () => {
		const measure = () => setTopology( readTopology( fixtureRef.current ) );

		measure();
		window.addEventListener( 'resize', measure );
		return () => window.removeEventListener( 'resize', measure );
	}, [] );

	return (
		<section ref={ fixtureRef } className="ax-canonical-layout-fixture" aria-label="Scaffold topology">
			<p className="ax-canonical-layout-fixture__readout" data-size-class={ sizeClass }>
				window size class: <strong>{ sizeClass }</strong> &middot; { topology
					? `rail right ${ topology.railRight }px · content left ${ topology.contentLeft }px · app bar left ${ topology.appBarLeft }px · scaffold scroll containers ${ topology.scaffoldScrollContainers }`
					: 'measuring topology…' }
			</p>
			<DemoShell
				activeId="home"
				className="ax-canonical-layout-topology"
				title="Scaffold topology"
				subtitle="Navigation and content occupy separate regions"
			>
				<section className="ax-canonical-layout-topology__main" aria-labelledby="topology-heading">
					<h1 id="topology-heading">Content pane</h1>
					<p>The navigation rail is its sibling. The app bar is the first region inside this content pane.</p>
					<div className="ax-canonical-layout-topology__blocks" aria-hidden="true">
						<div />
						<div />
						<div />
						<div />
					</div>
				</section>
			</DemoShell>
		</section>
	);
}

function FeedFixture() {
	const posts = [
		[ 'Mina Park', 'Published a field note', 'A small set of carefully named layout primitives is easier to reuse than a shell that knows product data.' ],
		[ 'Open Commons', 'Boosted a discussion', 'The next reader should be able to tell whether a surface is a feed, a directory, or a contextual view.' ],
		[ 'J. Rivera', 'Shared an article', 'Reading geometry should stay stable while the page chooses the content and the URL chooses the selection.' ],
		[ 'Design systems', 'Started a topic', 'The compact view is a route decision. The layout only receives the pane it should show.' ],
		[ 'Community Lab', 'Added a resource', 'A supporting pane can remain useful without becoming a second primary task.' ],
	];

	return (
		<DemoShell activeId="home" title="Home" subtitle="Activity from people and groups you follow">
			<section className="ax-canonical-layout-demo__content" aria-labelledby="feed-heading">
				<div className="ax-canonical-layout-demo__heading">
					<div>
						<p className="ax-canonical-layout-demo__eyebrow">Following</p>
						<h1 id="feed-heading">Activity</h1>
					</div>
					<IconButton label="Feed options" icon={ <Icon name="tune" /> } variant="standard" />
				</div>
				<FeedLayout>
					{ posts.map( ( [ actor, action, body ] ) => (
						<Card key={ actor } as="article" variant="filled" className="ax-canonical-layout-demo__post">
							<header>
								<strong>{ actor }</strong>
								<span>{ action }</span>
							</header>
							<p>{ body }</p>
							<a href="#open-post">Open post</a>
						</Card>
					) ) }
				</FeedLayout>
			</section>
		</DemoShell>
	);
}

function ListDetailFixture() {
	return (
		<DemoShell activeId="explore" title="Explore" subtitle="Topics from the social web">
			<section className="ax-canonical-layout-demo__content" aria-labelledby="explore-heading">
				<h1 id="explore-heading" className="ax-sr-only">Explore topics</h1>
				<ListDetailLayout
					list={ (
						<DemoPane className="ax-canonical-layout-demo__list">
							<p className="ax-canonical-layout-demo__eyebrow">Topics</p>
							<nav aria-label="Topics">
								<a href="#open-source" aria-current="page"><strong>Open source publishing</strong><span>18 new posts</span></a>
								<a href="#civic"><strong>Civic technology</strong><span>7 new posts</span></a>
								<a href="#local"><strong>Local communities</strong><span>24 new posts</span></a>
								<a href="#research"><strong>Research notes</strong><span>4 new posts</span></a>
							</nav>
						</DemoPane>
					) }
					detail={ (
						<DemoPane className="ax-canonical-layout-demo__detail">
							<article>
								<p className="ax-canonical-layout-demo__eyebrow">Topic</p>
								<h2>Open source publishing</h2>
								<p>People are comparing ways to keep publication, discovery, and local ownership connected without making every reader carry the same data model.</p>
								<div className="ax-canonical-layout-demo__detail-actions">
									<IconButton label="Follow topic" icon={ <Icon name="notifications" /> } variant="tonal" />
									<IconButton label="More topic actions" icon={ <Icon name="more_vert" /> } variant="standard" />
								</div>
								<h3>Recent discussion</h3>
								<p>One source of truth for a resource does not mean one presentation. A context can add the framing a reader needs without changing the object itself.</p>
							</article>
						</DemoPane>
					) }
					extra={ (
						<DemoPane className="ax-canonical-layout-demo__context">
							<p className="ax-canonical-layout-demo__eyebrow">Context</p>
							<h2>Open publishing</h2>
							<dl>
								<div><dt>Members</dt><dd>2,814</dd></div>
								<div><dt>Active today</dt><dd>138</dd></div>
								<div><dt>Visibility</dt><dd>Public</dd></div>
							</dl>
						</DemoPane>
					) }
				/>
			</section>
		</DemoShell>
	);
}

function SupportingPaneFixture() {
	return (
		<DemoShell activeId="inbox" title="Inbox" subtitle="Replies and mentions that need your attention">
			<section className="ax-canonical-layout-demo__content" aria-labelledby="thread-heading">
				<h1 id="thread-heading" className="ax-sr-only">Conversation</h1>
				<SupportingPaneLayout
					primary={ (
						<DemoPane className="ax-canonical-layout-demo__thread">
							<article>
								<p className="ax-canonical-layout-demo__eyebrow">Conversation</p>
								<h2>A better reading surface for shared objects</h2>
								<p>Contextual links should help a reader understand why an object appears here while preserving the object’s own source and canonical identity.</p>
								<div className="ax-canonical-layout-demo__reply">
									<strong>Amara</strong>
									<p>That makes the group route useful without treating it as a replacement for the object route.</p>
								</div>
								<div className="ax-canonical-layout-demo__reply">
									<strong>Jun</strong>
									<p>And the supporting pane can carry the people and context rather than forcing those details into the reading column.</p>
								</div>
							</article>
						</DemoPane>
					) }
					supporting={ (
						<DemoPane className="ax-canonical-layout-demo__supporting">
							<p className="ax-canonical-layout-demo__eyebrow">In this conversation</p>
							<h2>3 participants</h2>
							<ul>
								<li><Icon name="account_circle" /> Amara</li>
								<li><Icon name="account_circle" /> Jun</li>
								<li><Icon name="account_circle" /> Mina</li>
							</ul>
							<a href="#open-context">Open group context</a>
						</DemoPane>
					) }
				/>
			</section>
		</DemoShell>
	);
}

/**
 * Developer-only canonical layout demonstrations. The content is static, but
 * each topology is composed with the application chrome it will eventually host.
 *
 * @param {Object} props Component props.
 * @param {'topology'|'feed'|'list-detail'|'supporting_pane'} props.layout Layout demonstration key.
 * @return {import('@wordpress/element').ReactNode} Canonical layout fixture.
 */
export function CanonicalLayoutFixture( { layout } ) {
	const definition = layoutDefinitions[ layout ];
	/*
	 * The first consumer of the window size class, and the only way to verify the
	 * hook at all: a signal with nobody reading it cannot be measured. It is read
	 * here and printed, not used to choose anything -- the fixtures' own geometry
	 * stays in their stylesheets, where a media query belongs.
	 */
	const sizeClass = useWindowSizeClass();
	const fixture = {
		topology: <TopologyFixture sizeClass={ sizeClass } />,
		feed: <FeedFixture />,
		'list-detail': <ListDetailFixture />,
		supporting_pane: <SupportingPaneFixture />,
	}[ layout ];

	if ( 'topology' === layout ) {
		return fixture;
	}

	return (
		<section className="ax-canonical-layout-fixture" aria-label={ definition.title }>
			<p className="ax-canonical-layout-fixture__readout" data-size-class={ sizeClass }>
				window size class: <strong>{ sizeClass }</strong> &middot; { window.innerWidth }px
			</p>
			{ fixture }
		</section>
	);
}
