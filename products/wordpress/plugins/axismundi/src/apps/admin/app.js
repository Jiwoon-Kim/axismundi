import { getAdminThemeColors } from '@wordpress/admin-ui';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { ThemeProvider } from '@wordpress/theme';
import AdminLayout from './layout/admin-layout';
import {
	getAdminPath,
	getAdminRouteUrl,
	resolveAdminRoute,
	resolveAdminRouteAreas,
} from './routes';
import { useSidebarNavigation } from './sidebar/navigation';

export function AdminApp() {
	const [ path, setPath ] = useState( getAdminPath );
	const [ mobileView, setMobileView ] = useState( 'content' );
	const adminThemeColors = useMemo( getAdminThemeColors, [] );
	const route = useMemo( () => resolveAdminRoute( path ), [ path ] );
	const [ sidebarScreen, setSidebarScreen ] = useState( route.layout.sidebarScreen );
	const sidebarNavigation = useSidebarNavigation();

	useEffect( () => {
		const handlePopState = () => {
			const nextRoute = resolveAdminRoute( getAdminPath() );
			sidebarNavigation.reset();
			setPath( nextRoute.path );
			setSidebarScreen( nextRoute.layout.sidebarScreen );
		};
		window.addEventListener( 'popstate', handlePopState );
		return () => window.removeEventListener( 'popstate', handlePopState );
	}, [ sidebarNavigation ] );

	const navigate = useCallback( ( nextPath, sidebarTransition ) => {
		const nextRoute = resolveAdminRoute( nextPath );

		if ( sidebarTransition ) {
			sidebarNavigation.navigate(
				sidebarTransition.direction,
				sidebarTransition.focusSelector
			);
			setSidebarScreen( sidebarTransition.screen );
		} else {
			sidebarNavigation.reset();
			setSidebarScreen( nextRoute.layout.sidebarScreen );
		}

		if ( sidebarTransition?.history !== false ) {
			window.history.pushState( {}, '', getAdminRouteUrl( nextPath ) );
		}
		setPath( nextRoute.path );
		setMobileView( 'content' );
	}, [ sidebarNavigation ] );

	const showNavigation = useCallback( () => setMobileView( 'navigation' ), [] );
	const areas = useMemo(
		() => resolveAdminRouteAreas( route, {
			navigate,
			sidebarScreen,
			showNavigation,
		} ),
		[ navigate, route, showNavigation, sidebarScreen ]
	);

	return (
		<ThemeProvider isRoot color={ { primary: adminThemeColors.primary, background: '#fcfcfc' } }>
			<ThemeProvider color={ adminThemeColors }>
				<AdminLayout
					areas={ areas }
					layout={ route.layout }
					mobileView={ mobileView }
					sidebarScreen={ sidebarScreen }
				/>
			</ThemeProvider>
		</ThemeProvider>
	);
}
