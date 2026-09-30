import { __ } from '@wordpress/i18n';
import { __experimentalItemGroup as ItemGroup } from '@wordpress/components';
import { layout } from '@wordpress/icons';
import { getAxismundiConfig } from '../../../shared/runtime/config';
import RouteContent from '../components/route-content';
import FrontendPreview from '../preview/frontend-preview';
import SidebarNavigationItem from '../components/sidebar/sidebar-navigation-item';
import SidebarNavigationScreen from '../components/sidebar/sidebar-navigation-screen';

const OPERATION_SECTIONS = [
	{ id: 'overview', label: __( 'Overview', 'axismundi' ) },
	{ id: 'settings', label: __( 'Settings', 'axismundi' ) },
	{ id: 'diagnostics', label: __( 'Diagnostics', 'axismundi' ) },
];

const DESIGN_SECTIONS = [
	{ id: 'styles', label: __( 'Styles', 'axismundi' ), registryIcon: 'axismundi/styles' },
	{ id: 'templates', label: __( 'Templates', 'axismundi' ), registryIcon: 'axismundi/templates', withChevron: true },
	{ id: 'template-parts', label: __( 'Template Parts', 'axismundi' ), icon: layout, withChevron: true },
	{ id: 'patterns', label: __( 'Patterns', 'axismundi' ), registryIcon: 'axismundi/patterns', withChevron: true },
	{ id: 'components', label: __( 'Components', 'axismundi' ), registryIcon: 'axismundi/components', withChevron: true },
];

function normalizePath( path ) {
	return `/${ String( path || '/' ).replace( /^\/+|\/+$/g, '' ) }`;
}

export function getAdminPath() {
	return normalizePath(
		new URLSearchParams( window.location.search ).get( 'p' ) || getAxismundiConfig().path || '/'
	);
}

export function getAdminRouteUrl( path ) {
	const url = new URL( window.location.href );
	url.searchParams.set( 'page', 'axismundi' );
	url.searchParams.set( 'p', path );
	return url.toString();
}

function createRouteLinkHandler( path, navigate, sidebarTransition ) {
	return ( event ) => {
		if (
			event.button !== 0 ||
			event.metaKey ||
			event.ctrlKey ||
			event.shiftKey ||
			event.altKey ||
			event.defaultPrevented
		) {
			return;
		}

		event.preventDefault();
		navigate( path, sidebarTransition );
	};
}

function NavigationItem( {
	active,
	children,
	icon,
	registryIcon,
	path,
	navigate,
	screen,
	withChevron = false,
} ) {
	const id = screen ? `ax-admin-sidebar-${ screen }` : undefined;
	const sidebarTransition = screen
		? {
			direction: 'forward',
			focusSelector: `#${ id }`,
			screen,
		}
		: undefined;

	return (
		<SidebarNavigationItem
			active={ active }
			as="a"
			href={ getAdminRouteUrl( path ) }
			id={ id }
			icon={ icon }
			registryIcon={ registryIcon }
			onClick={ createRouteLinkHandler( path, navigate, sidebarTransition ) }
			withChevron={ withChevron }
		>
			{ children }
		</SidebarNavigationItem>
	);
}

function OperationsSidebar( { path, navigate } ) {
	return (
		<SidebarNavigationScreen
			title={ __( 'Admin', 'axismundi' ) }
			description={ __( 'Manage Axismundi applications and their operating environment.', 'axismundi' ) }
			dashboardHref={ getAxismundiConfig().adminUrl || '/wp-admin/' }
			dashboardLabel={ __( 'Go to the Dashboard', 'axismundi' ) }
			content={
				<nav aria-label={ __( 'Axismundi sections', 'axismundi' ) }>
					<ItemGroup className="ax-admin-sidebar-screen-main">
						{ OPERATION_SECTIONS.map( ( item ) => {
							const itemPath = item.id === 'overview' ? '/' : `/${ item.id }`;
							return (
								<NavigationItem key={ item.id } active={ path === itemPath } path={ itemPath } navigate={ navigate }>
									{ item.label }
								</NavigationItem>
							);
						} ) }
						<NavigationItem
							active={ path === '/design' }
							path="/design"
							navigate={ navigate }
							screen="design-root"
							withChevron
						>
							{ __( 'Design', 'axismundi' ) }
						</NavigationItem>
					</ItemGroup>
				</nav>
			}
		/>
	);
}

function DesignSidebarRoot( { path, navigate } ) {
	return (
		<SidebarNavigationScreen
			title={ __( 'Design', 'axismundi' ) }
			description={ __( "Manage the Social application's presentation system.", 'axismundi' ) }
			onBack={ () =>
				navigate( '/', {
					direction: 'back',
					screen: 'operations-root',
				} )
			}
			content={
				<nav aria-label={ __( 'Design sections', 'axismundi' ) }>
					<ItemGroup className="ax-admin-sidebar-screen-main">
						{ DESIGN_SECTIONS.map( ( item ) => {
							const itemPath = `/design/${ item.id }`;
							return (
								<NavigationItem
									key={ item.id }
									active={ path === itemPath }
									icon={ item.icon }
									registryIcon={ item.registryIcon }
									path={ itemPath }
									navigate={ navigate }
									screen={ item.id }
									withChevron={ item.withChevron }
								>
									{ item.label }
								</NavigationItem>
							);
						} ) }
					</ItemGroup>
				</nav>
			}
		/>
	);
}

function DesignSectionSidebar( { path, navigate, section } ) {
	return (
		<SidebarNavigationScreen
			title={ section.label }
			onBack={ () =>
				navigate( path, {
					direction: 'back',
					history: false,
					screen: 'design-root',
				} )
			}
		/>
	);
}

function DesignSidebar( { path, navigate, section, sidebarScreen } ) {
	if ( sidebarScreen === 'design-root' ) {
		return <DesignSidebarRoot path={ path } navigate={ navigate } />;
	}

	return <DesignSectionSidebar path={ path } navigate={ navigate } section={ section } />;
}

function OperationsContent( { route, showNavigation } ) {
	return (
		<RouteContent
			backLabel={ __( 'Back to navigation', 'axismundi' ) }
			eyebrow={ __( 'Axismundi', 'axismundi' ) }
			onShowNavigation={ showNavigation }
			title={ route.label }
		>
			<p>{ __( 'This workspace is ready for Axismundi operations.', 'axismundi' ) }</p>
		</RouteContent>
	);
}

function DesignContent( { route, showNavigation } ) {
	return (
		<RouteContent
			backLabel={ __( 'Back to navigation', 'axismundi' ) }
			eyebrow={ __( 'Design', 'axismundi' ) }
			onShowNavigation={ showNavigation }
			title={ route.label }
		/>
	);
}

function createOperationsRoute( section ) {
	const path = section.id === 'overview' ? '/' : `/${ section.id }`;
	return {
		path,
		workspace: 'operations',
		label: section.label,
		layout: {
			contentLabel: section.label,
			navigationLabel: __( 'Axismundi administration', 'axismundi' ),
			sidebarScreen: 'operations-root',
			sidebarShouldAnimate: false,
			workspace: 'operations',
		},
		areas: {
			sidebar: ( context ) => <OperationsSidebar path={ path } navigate={ context.navigate } />,
			content: ( context ) => <OperationsContent route={ { path, ...section } } showNavigation={ context.showNavigation } />,
		},
	};
}

const DESIGN_ROOT_ROUTE = {
	path: '/design',
	workspace: 'design',
	label: __( 'Design', 'axismundi' ),
	layout: {
		contentLabel: __( 'Design', 'axismundi' ),
		navigationLabel: __( 'Axismundi design', 'axismundi' ),
		sidebarScreen: 'design-root',
		sidebarShouldAnimate: true,
		workspace: 'design',
	},
	areas: {
		sidebar: ( context ) => <DesignSidebarRoot path="/design" navigate={ context.navigate } />,
		content: ( context ) => (
			<DesignContent
				route={ { label: __( 'Design', 'axismundi' ), path: '/design' } }
				showNavigation={ context.showNavigation }
			/>
		),
		preview: () => <FrontendPreview label={ __( 'Design', 'axismundi' ) } />,
	},
};

function createDesignRoute( section ) {
	const path = `/design/${ section.id }`;
	return {
		path,
		workspace: 'design',
		label: section.label,
		layout: {
			contentLabel: section.label,
			navigationLabel: __( 'Axismundi design', 'axismundi' ),
			sidebarScreen: section.id,
			sidebarShouldAnimate: true,
			workspace: 'design',
		},
		areas: {
			sidebar: ( context ) => <DesignSidebar path={ path } navigate={ context.navigate } section={ section } sidebarScreen={ context.sidebarScreen } />,
			content: ( context ) => <DesignContent route={ { path, ...section } } showNavigation={ context.showNavigation } />,
			preview: () => <FrontendPreview label={ section.label } />,
		},
	};
}

const ROUTES = [
	...OPERATION_SECTIONS.map( createOperationsRoute ),
	DESIGN_ROOT_ROUTE,
	...DESIGN_SECTIONS.map( createDesignRoute ),
];

export function resolveAdminRoute( path = getAdminPath() ) {
	const normalizedPath = normalizePath( path );
	return ROUTES.find( ( route ) => route.path === normalizedPath ) || ROUTES[ 0 ];
}

export function resolveAdminRouteAreas( route, context ) {
	return Object.fromEntries(
		Object.entries( route.areas ).map( ( [ name, area ] ) => [ name, typeof area === 'function' ? area( context ) : area ] )
	);
}
