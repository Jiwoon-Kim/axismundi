import { Button as UiButton } from '@wordpress/ui';
import { NavigableRegion, getAdminThemeColors } from '@wordpress/admin-ui';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { ThemeProvider } from '@wordpress/theme';
import { __experimentalItemGroup as ItemGroup } from '@wordpress/components';
import {
	blockDefault,
	Icon,
	layout,
	styles,
	symbol,
	chevronLeft,
} from '@wordpress/icons';
import { getAxismundiConfig } from '../../shared/runtime/config';
import SidebarNavigationItem from './components/site-editor/sidebar-navigation-item';
import SidebarNavigationScreen from './components/site-editor/sidebar-navigation-screen';

const CONTENT_COLOR = { background: '#fcfcfc' };

const OPERATION_SECTIONS = [
	{ id: 'overview', label: 'Overview' },
	{ id: 'settings', label: 'Settings' },
	{ id: 'diagnostics', label: 'Diagnostics' },
];

const DESIGN_SECTIONS = [
	{ id: 'styles', label: 'Styles', icon: styles },
	{ id: 'templates', label: 'Templates', icon: layout, withChevron: true },
	{ id: 'template-parts', label: 'Template Parts', icon: layout, withChevron: true },
	{ id: 'patterns', label: 'Patterns', icon: symbol, withChevron: true },
	{ id: 'components', label: 'Components', icon: blockDefault, withChevron: true },
];

function getAdminPath() {
	return new URLSearchParams( window.location.search ).get( 'p' ) || getAxismundiConfig().path || '/';
}

function getInitialRoute() {
	const path = `/${ getAdminPath().replace( /^\/+|\/+$/g, '' ) }`;
	const designMatch = path.match( /^\/design(?:\/([a-z-]+))?$/ );
	if ( designMatch ) {
		const designSection = DESIGN_SECTIONS.find( ( item ) => item.id === designMatch[ 1 ] );
		return { workspace: 'design', section: designSection?.id || 'styles' };
	}

	const operationSection = OPERATION_SECTIONS.find( ( item ) => `/${ item.id }` === path );
	return { workspace: 'operations', section: operationSection?.id || 'overview' };
}

function getPathForRoute( workspace, section ) {
	return workspace === 'design' ? `/design/${ section }` : section === 'overview' ? '/' : `/${ section }`;
}

function FrontendPreview( { label } ) {
	return (
		<section className="ax-admin-frontend-preview" aria-label="Frontend preview">
			<p className="ax-admin-frontend-preview__label">{ label } preview</p>
			<iframe
				className="ax-admin-frontend-preview__frame"
				title={ `Axismundi Social ${ label } preview` }
				src={ getAxismundiConfig().route || '/social/' }
			/>
		</section>
	);
}

function DesignSidebar( { section, onSelect, onBack } ) {
	return (
		<SidebarNavigationScreen
			title="Design"
			description="Manage the Social application's presentation system."
			onBack={ onBack }
			footer={ <span className="ax-admin-layout__save-status">Saved</span> }
			content={
				<nav aria-label="Design sections">
					<ItemGroup className="edit-site-sidebar-navigation-screen-main">
						{ DESIGN_SECTIONS.map( ( item ) => (
							<SidebarNavigationItem
								key={ item.id }
								active={ item.id === section }
								icon={ item.icon }
								onClick={ () => onSelect( item.id ) }
								withChevron={ item.withChevron }
							>
								{ item.label }
							</SidebarNavigationItem>
						) ) }
					</ItemGroup>
				</nav>
			}
		/>
	);
}

function OperationsSidebar( { section, onSelect, onDesign } ) {
	return (
		<SidebarNavigationScreen
			title="Admin"
			description="Manage Axismundi applications and their operating environment."
			dashboardHref="/wp-admin/"
			footer={ <span className="ax-admin-layout__save-status">Saved</span> }
			content={
				<nav aria-label="Axismundi sections">
					<ItemGroup className="edit-site-sidebar-navigation-screen-main">
						{ OPERATION_SECTIONS.map( ( item ) => (
							<SidebarNavigationItem
								key={ item.id }
								active={ item.id === section }
								onClick={ () => onSelect( item.id ) }
							>
								{ item.label }
							</SidebarNavigationItem>
						) ) }
						<SidebarNavigationItem withChevron onClick={ onDesign }>Design</SidebarNavigationItem>
					</ItemGroup>
				</nav>
			}
		/>
	);
}

export function AdminApp() {
	const [ route, setRoute ] = useState( getInitialRoute );
	const [ inspectorOpen, setInspectorOpen ] = useState( false );
	const [ mobileView, setMobileView ] = useState( 'content' );
	const adminThemeColors = useMemo( getAdminThemeColors, [] );
	const sections = route.workspace === 'design' ? DESIGN_SECTIONS : OPERATION_SECTIONS;
	const active = sections.find( ( item ) => item.id === route.section ) || sections[ 0 ];

	useEffect( () => {
		const handlePopState = () => setRoute( getInitialRoute() );
		window.addEventListener( 'popstate', handlePopState );
		return () => window.removeEventListener( 'popstate', handlePopState );
	}, [] );

	function navigate( workspace, section ) {
		const path = getPathForRoute( workspace, section );
		const url = new URL( window.location.href );
		url.searchParams.set( 'page', 'axismundi' );
		url.searchParams.set( 'p', path );
		window.history.pushState( {}, '', url );
		setRoute( { workspace, section } );
		setMobileView( 'content' );
	}

	return (
		<ThemeProvider isRoot color={ { primary: adminThemeColors.primary, ...CONTENT_COLOR } }>
			<ThemeProvider color={ adminThemeColors }>
				<div className="axismundi-admin-app" data-ax-admin-workspace={ route.workspace } data-ax-admin-mobile-view={ mobileView }>
					<div className="ax-admin-layout__content">
						<div className="ax-admin-layout__sidebar-region">
							<NavigableRegion className="edit-site-sidebar__content" aria-label={ route.workspace === 'design' ? 'Axismundi design' : 'Axismundi administration' }>
								<div className="edit-site-sidebar__screen-wrapper">
									{ route.workspace === 'design' ? (
										<DesignSidebar section={ route.section } onSelect={ ( section ) => navigate( 'design', section ) } onBack={ () => navigate( 'operations', 'overview' ) } />
									) : (
										<OperationsSidebar section={ route.section } onSelect={ ( section ) => navigate( 'operations', section ) } onDesign={ () => navigate( 'design', 'styles' ) } />
									) }
								</div>
							</NavigableRegion>
						</div>
						<ThemeProvider color={ CONTENT_COLOR }>
							<main className="ax-admin-layout__main">
								<section className="ax-admin-layout__area" aria-label={ active.label }>
									<header className="ax-admin-layout__header">
										<UiButton className="ax-admin-layout__content-back" variant="minimal" aria-label="Back to navigation" onClick={ () => setMobileView( 'navigation' ) }><Icon icon={ chevronLeft } size={ 24 } /></UiButton>
										<p className="ax-admin-layout__eyebrow">{ route.workspace === 'design' ? 'Design' : 'Axismundi' }</p>
										<h2>{ active.label }</h2>
										<UiButton variant="minimal" tone="neutral" aria-pressed={ inspectorOpen } onClick={ () => setInspectorOpen( ! inspectorOpen ) }>Inspector</UiButton>
									</header>
									<div className="ax-admin-layout__area-content">
										<h3>{ active.label }</h3>
										{ route.workspace === 'design' ? <FrontendPreview label={ active.label } /> : <p>This workspace is ready for Axismundi operations.</p> }
									</div>
								</section>
							</main>
							{ inspectorOpen && (
								<aside className="ax-admin-layout__inspector" aria-label="Inspector">
									<header className="ax-admin-layout__inspector-header"><h2>Inspector</h2><UiButton variant="minimal" tone="neutral" onClick={ () => setInspectorOpen( false ) }>Close</UiButton></header>
									<p>Component state and environment controls will appear here.</p>
								</aside>
							) }
						</ThemeProvider>
					</div>
				</div>
			</ThemeProvider>
		</ThemeProvider>
	);
}
