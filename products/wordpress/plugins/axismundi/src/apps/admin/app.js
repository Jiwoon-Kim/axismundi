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

export function AdminApp() {
	const [ path, setPath ] = useState( getAdminPath );
	const [ mobileView, setMobileView ] = useState( 'content' );
	const adminThemeColors = useMemo( getAdminThemeColors, [] );
	const route = useMemo( () => resolveAdminRoute( path ), [ path ] );

	useEffect( () => {
		const handlePopState = () => setPath( getAdminPath() );
		window.addEventListener( 'popstate', handlePopState );
		return () => window.removeEventListener( 'popstate', handlePopState );
	}, [] );

	const navigate = useCallback( ( nextPath ) => {
		window.history.pushState( {}, '', getAdminRouteUrl( nextPath ) );
		setPath( nextPath );
		setMobileView( 'content' );
	}, [] );

	const showNavigation = useCallback( () => setMobileView( 'navigation' ), [] );
	const areas = useMemo(
		() => resolveAdminRouteAreas( route, { navigate, showNavigation } ),
		[ navigate, route, showNavigation ]
	);

	return (
		<ThemeProvider isRoot color={ { primary: adminThemeColors.primary, background: '#fcfcfc' } }>
			<ThemeProvider color={ adminThemeColors }>
				<AdminLayout areas={ areas } mobileView={ mobileView } route={ route } />
			</ThemeProvider>
		</ThemeProvider>
	);
}
