import { NavigableRegion } from '@wordpress/admin-ui';
import { ThemeProvider } from '@wordpress/theme';
import { SidebarContent } from '../sidebar/navigation';

const CONTENT_COLOR = { background: '#fcfcfc' };

/**
 * A route-neutral shell. Routes describe the areas they need; this component
 * only assigns those areas to the application layout.
 */
export default function AdminLayout( { areas, layout, mobileView, sidebarScreen } ) {
	return (
		<div
			className="axismundi-admin-app"
			data-ax-admin-workspace={ layout.workspace }
			data-ax-admin-mobile-view={ mobileView }
		>
			<div className="ax-admin-layout__content">
				<div className="ax-admin-layout__sidebar-region">
					<NavigableRegion
						className="ax-admin-sidebar__content"
						aria-label={ layout.navigationLabel }
					>
						<SidebarContent screenKey={ sidebarScreen } shouldAnimate={ layout.sidebarShouldAnimate }>
							{ areas.sidebar }
						</SidebarContent>
					</NavigableRegion>
				</div>
				<ThemeProvider color={ CONTENT_COLOR }>
					<main className="ax-admin-layout__main">
						<section className="ax-admin-layout__area" aria-label={ layout.contentLabel }>
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
