import { NavigableRegion } from '@wordpress/admin-ui';
import { ThemeProvider } from '@wordpress/theme';

const CONTENT_COLOR = { background: '#fcfcfc' };

/**
 * A route-neutral shell. Routes describe the areas they need; this component
 * only assigns those areas to the application layout.
 */
export default function AdminLayout( { areas, mobileView, route } ) {
	return (
		<div
			className="axismundi-admin-app"
			data-ax-admin-workspace={ route.workspace }
			data-ax-admin-mobile-view={ mobileView }
		>
			<div className="ax-admin-layout__content">
				<div className="ax-admin-layout__sidebar-region">
					<NavigableRegion
						className="edit-site-sidebar__content"
						aria-label={ route.workspace === 'design' ? 'Axismundi design' : 'Axismundi administration' }
					>
						<div className="edit-site-sidebar__screen-wrapper">{ areas.sidebar }</div>
					</NavigableRegion>
				</div>
				<ThemeProvider color={ CONTENT_COLOR }>
					<main className="ax-admin-layout__main">
						<section className="ax-admin-layout__area" aria-label={ route.label }>
							{ areas.content }
							{ areas.preview && (
								<div className="ax-admin-layout__preview">{ areas.preview }</div>
							) }
						</section>
					</main>
				</ThemeProvider>
			</div>
		</div>
	);
}
