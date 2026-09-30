import { NavigationSuite } from './layouts/navigation-suite';

export function FrontendApp() {
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
