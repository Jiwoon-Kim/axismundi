import { AppLayout } from './templates/app-layout';
import { StylebookPage } from './pages/stylebook';

function getFrontendRoute() {
	const route = window.axismundiCapstone?.route ?? '/social/';
	const basePath = route.replace( /\/+$/, '' );
	const pathname = window.location.pathname.replace( /\/+$/, '' );
	const relativePath = pathname.startsWith( basePath )
		? pathname.slice( basePath.length ).replace( /^\/+/, '' )
		: '';

	if ( 'stylebook' === relativePath ) {
		return { name: 'stylebook' };
	}

	if ( 'stylebook/styles' === relativePath ) {
		return { name: 'stylebook', styles: true };
	}

	const stylebookStyle = relativePath.match( /^stylebook\/styles\/(motion)$/ );
	if ( stylebookStyle ) {
		return { name: 'stylebook', style: stylebookStyle[ 1 ] };
	}

	const stylebookComponent = relativePath.match(
		/^stylebook\/components\/(buttons|icon-buttons|button-groups|split-buttons|cards)$/
	);

	if ( stylebookComponent ) {
		return { name: 'stylebook', component: stylebookComponent[ 1 ] };
	}

	const stylebookLayout = relativePath.match(
		/^stylebook\/layout\/(feed|list-detail|supporting_pane)$/
	);

	if ( stylebookLayout ) {
		return { name: 'stylebook', layout: stylebookLayout[ 1 ] };
	}

	if ( /^objects\/[^/]+$/.test( relativePath ) ) {
		return { name: 'object' };
	}

	return { name: 'home' };
}

function isAuthenticatedViewer() {
	return true === window.axismundiCapstone?.viewer?.authenticated;
}

function HomeTemplate() {
	const authenticated = isAuthenticatedViewer();

	return (
		<section className="ax-social-shell" aria-labelledby="ax-social-shell-title">
			<header className="ax-social-shell__header">
				<p className="ax-social-shell__eyebrow">Axismundi</p>
				<h1 id="ax-social-shell-title" className="ax-social-shell__title">
					{ authenticated ? 'Home' : 'Social' }
				</h1>
			</header>
			<p className="ax-social-shell__message">
				{ authenticated
					? 'Your home feed will render here.'
					: 'Sign in to view your home feed.' }
			</p>
			{ ! authenticated && window.axismundiCapstone?.loginUrl ? (
				<p>
					<a href={ window.axismundiCapstone.loginUrl }>Sign in</a>
				</p>
			) : null }
		</section>
	);
}

function PublicObjectTemplate() {
	return (
		<section className="ax-social-shell" aria-labelledby="ax-social-object-title">
			<header className="ax-social-shell__header">
				<p className="ax-social-shell__eyebrow">Axismundi</p>
				<h1 id="ax-social-object-title" className="ax-social-shell__title">Object</h1>
			</header>
			<p className="ax-social-shell__message">
				Public objects will render here without requiring a signed-in home session.
			</p>
		</section>
	);
}

export function FrontendApp() {
	const route = getFrontendRoute();

	if ( 'stylebook' === route.name ) {
		return <StylebookPage component={ route.component } layout={ route.layout } style={ route.style } styles={ route.styles } />;
	}

	return (
		<AppLayout>
			{ 'object' === route.name ? <PublicObjectTemplate /> : <HomeTemplate /> }
		</AppLayout>
	);
}
