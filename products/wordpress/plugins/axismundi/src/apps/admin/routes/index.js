import { __ } from '@wordpress/i18n';
import { __experimentalItemGroup as ItemGroup } from '@wordpress/components';
import { blockDefault, layout, styles, symbol } from '@wordpress/icons';
import { getAxismundiConfig } from '../../../shared/runtime/config';
import RouteContent from '../components/route-content';
import FrontendPreview from '../preview/frontend-preview';
import SidebarNavigationItem from '../components/site-editor/sidebar-navigation-item';
import SidebarNavigationScreen from '../components/site-editor/sidebar-navigation-screen';

const OPERATION_SECTIONS = [
	{ id: 'overview', label: __( 'Overview', 'axismundi' ) },
	{ id: 'settings', label: __( 'Settings', 'axismundi' ) },
	{ id: 'diagnostics', label: __( 'Diagnostics', 'axismundi' ) },
];

const DESIGN_SECTIONS = [
	{ id: 'styles', label: __( 'Styles', 'axismundi' ), icon: styles },
	{ id: 'templates', label: __( 'Templates', 'axismundi' ), icon: layout },
	{ id: 'template-parts', label: __( 'Template Parts', 'axismundi' ), icon: layout },
	{ id: 'patterns', label: __( 'Patterns', 'axismundi' ), icon: symbol },
	{ id: 'components', label: __( 'Components', 'axismundi' ), icon: blockDefault },
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

function createRouteLinkHandler( path, navigate ) {
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
		navigate( path );
	};
}

function NavigationItem( { active, children, icon, path, navigate } ) {
	return (
		<SidebarNavigationItem
			active={ active }
			as="a"
			href={ getAdminRouteUrl( path ) }
			icon={ icon }
			onClick={ createRouteLinkHandler( path, navigate ) }
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
			footer={ <span className="ax-admin-layout__save-status">{ __( 'Saved', 'axismundi' ) }</span> }
			content={
				<nav aria-label={ __( 'Axismundi sections', 'axismundi' ) }>
					<ItemGroup className="edit-site-sidebar-navigation-screen-main">
						{ OPERATION_SECTIONS.map( ( item ) => {
							const itemPath = item.id === 'overview' ? '/' : `/${ item.id }`;
							return (
								<NavigationItem key={ item.id } active={ path === itemPath } path={ itemPath } navigate={ navigate }>
									{ item.label }
								</NavigationItem>
							);
						} ) }
						<NavigationItem active={ path.startsWith( '/design' ) } path="/design/styles" navigate={ navigate }>
							{ __( 'Design', 'axismundi' ) }
						</NavigationItem>
					</ItemGroup>
				</nav>
			}
		/>
	);
}

function DesignSidebar( { path, navigate } ) {
	return (
		<SidebarNavigationScreen
			title={ __( 'Design', 'axismundi' ) }
			description={ __( "Manage the Social application's presentation system.", 'axismundi' ) }
			onBack={ () => navigate( '/' ) }
			footer={ <span className="ax-admin-layout__save-status">{ __( 'Saved', 'axismundi' ) }</span> }
			content={
				<nav aria-label={ __( 'Design sections', 'axismundi' ) }>
					<ItemGroup className="edit-site-sidebar-navigation-screen-main">
						{ DESIGN_SECTIONS.map( ( item ) => {
							const itemPath = `/design/${ item.id }`;
							return (
								<NavigationItem key={ item.id } active={ path === itemPath } icon={ item.icon } path={ itemPath } navigate={ navigate }>
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
		areas: {
			sidebar: ( context ) => <OperationsSidebar path={ path } navigate={ context.navigate } />,
			content: ( context ) => <OperationsContent route={ { path, ...section } } showNavigation={ context.showNavigation } />,
		},
	};
}

function createDesignRoute( section ) {
	const path = `/design/${ section.id }`;
	return {
		path,
		workspace: 'design',
		label: section.label,
		areas: {
			sidebar: ( context ) => <DesignSidebar path={ path } navigate={ context.navigate } />,
			content: ( context ) => <DesignContent route={ { path, ...section } } showNavigation={ context.showNavigation } />,
			preview: () => <FrontendPreview label={ section.label } />,
		},
	};
}

const ROUTES = [
	...OPERATION_SECTIONS.map( createOperationsRoute ),
	...DESIGN_SECTIONS.map( createDesignRoute ),
];

export function resolveAdminRoute( path = getAdminPath() ) {
	const normalizedPath = normalizePath( path );
	if ( normalizedPath === '/design' ) {
		return ROUTES.find( ( route ) => route.path === '/design/styles' );
	}
	return ROUTES.find( ( route ) => route.path === normalizedPath ) || ROUTES[ 0 ];
}

export function resolveAdminRouteAreas( route, context ) {
	return Object.fromEntries(
		Object.entries( route.areas ).map( ( [ name, area ] ) => [ name, typeof area === 'function' ? area( context ) : area ] )
	);
}
