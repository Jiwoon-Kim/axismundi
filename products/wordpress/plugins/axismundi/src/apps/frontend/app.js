import { NavigationSuite } from './layouts/navigation-suite';
import { StylebookPage } from './pages/stylebook';

function getFrontendView() {
	const route = window.axismundiCapstone?.route ?? '/social/';
	const basePath = route.replace( /\/+$/, '' );
	const pathname = window.location.pathname.replace( /\/+$/, '' );

	return pathname === `${ basePath }/stylebook` ? 'stylebook' : 'social';
}

export function FrontendApp() {
	if ( 'stylebook' === getFrontendView() ) {
		return <StylebookPage />;
	}

	return (
		<NavigationSuite>
			<section className="ax-social-shell" aria-labelledby="ax-social-shell-title">
				<header className="ax-social-shell__header">
					<p className="ax-social-shell__eyebrow">Axismundi</p>
					<h1 id="ax-social-shell-title" className="ax-social-shell__title">Social</h1>
				</header>
				<p className="ax-social-shell__message">The Social application shell is ready. Reader data and interactions arrive in later milestones.</p>
			</section>
		</NavigationSuite>
	);
}
