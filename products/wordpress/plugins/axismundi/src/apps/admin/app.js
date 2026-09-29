import { Button } from '@wordpress/ui';
import { NavigableRegion, getAdminThemeColors } from '@wordpress/admin-ui';
import { useMemo, useState } from '@wordpress/element';
import { ThemeProvider } from '@wordpress/theme';
import { getAxismundiConfig } from '../../shared/runtime/config';

const CONTENT_COLOR = { background: '#fcfcfc' };

const ADMIN_SECTIONS = [
	{ id: 'overview', label: 'Overview' },
	{ id: 'settings', label: 'Settings' },
	{ id: 'diagnostics', label: 'Diagnostics' },
	{ id: 'developer', label: 'Developer' },
];

function getInitialAdminSection() {
	const path = getAxismundiConfig().path || new URLSearchParams( window.location.search ).get( 'p' ) || '/';
	const section = path.replace( /^\//, '' );
	return ADMIN_SECTIONS.some( ( item ) => item.id === section ) ? section : 'overview';
}

function FrontendPreview() {
	return (
		<section className="ax-admin-frontend-preview" aria-label="Frontend preview">
			<p className="ax-admin-frontend-preview__label">Frontend preview</p>
			<iframe
				className="ax-admin-frontend-preview__frame"
				title="Axismundi Social preview"
				src={ getAxismundiConfig().route || '/social/' }
			/>
		</section>
	);
}

export function AdminApp() {
	const [ section, setSection ] = useState( getInitialAdminSection );
	const [ inspectorOpen, setInspectorOpen ] = useState( false );
	const [ mobileView, setMobileView ] = useState( 'content' );
	const adminThemeColors = useMemo( getAdminThemeColors, [] );
	const active = ADMIN_SECTIONS.find( ( item ) => item.id === section ) || ADMIN_SECTIONS[ 0 ];

	return (
		<ThemeProvider
			isRoot
			color={ { primary: adminThemeColors.primary, ...CONTENT_COLOR } }
		>
			<ThemeProvider color={ adminThemeColors }>
				<div
					className="axismundi-admin-app"
					data-ax-admin-section={ section }
					data-ax-admin-mobile-view={ mobileView }
				>
					<div className="ax-admin-layout__content">
						<NavigableRegion
							className="ax-admin-layout__sidebar-region"
							aria-label="Axismundi administration"
						>
							<div className="ax-admin-layout__sidebar">
								<div className="ax-admin-layout__sidebar-heading">
									<Button
										className="ax-admin-layout__back"
										variant="minimal"
										aria-label="Back to navigation"
										onClick={ () => setMobileView( 'navigation' ) }
									>
										&lt;
									</Button>
									<div>
										<p className="ax-admin-layout__brand">Axismundi</p>
										<h1 className="ax-admin-layout__title">Admin</h1>
									</div>
							</div>
								<p className="ax-admin-layout__description">Manage Axismundi applications and their operating environment.</p>
								<nav className="ax-admin-layout__nav" aria-label="Axismundi sections">
									{ ADMIN_SECTIONS.map( ( item ) => (
										<Button
											key={ item.id }
											className="ax-admin-layout__nav-item"
											variant="minimal"
											tone="neutral"
											aria-current={ item.id === section ? 'page' : undefined }
											onClick={ () => {
												setSection( item.id );
												setMobileView( 'content' );
											} }
										>
											{ item.label }
										</Button>
									) ) }
								</nav>
								<div className="ax-admin-layout__save-status">Saved</div>
							</div>
						</NavigableRegion>
						<ThemeProvider color={ CONTENT_COLOR }>
							<main className="ax-admin-layout__main">
								<section className="ax-admin-layout__area" aria-label={ active.label }>
									<header className="ax-admin-layout__header">
										<Button
											className="ax-admin-layout__content-back"
											variant="minimal"
											aria-label="Back to navigation"
											onClick={ () => setMobileView( 'navigation' ) }
										>
											&lt;
										</Button>
										<p className="ax-admin-layout__eyebrow">Axismundi</p>
										<h2>{ active.label }</h2>
										<Button variant="minimal" tone="neutral" aria-pressed={ inspectorOpen } onClick={ () => setInspectorOpen( ! inspectorOpen ) }>Inspector</Button>
									</header>
									<div className="ax-admin-layout__area-content">
										<h3>{ active.label }</h3>
										{ section === 'developer' ? <FrontendPreview /> : <p>This workspace is ready for Axismundi components and application scenarios.</p> }
									</div>
								</section>
							</main>
							{ inspectorOpen && (
								<aside className="ax-admin-layout__inspector" aria-label="Inspector">
									<header className="ax-admin-layout__inspector-header">
										<h2>Inspector</h2>
										<Button variant="minimal" tone="neutral" onClick={ () => setInspectorOpen( false ) }>Close</Button>
									</header>
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
