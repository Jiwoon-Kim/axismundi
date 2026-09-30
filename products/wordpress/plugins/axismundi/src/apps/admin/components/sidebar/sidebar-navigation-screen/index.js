import clsx from 'clsx';
import {
	__experimentalHStack as HStack,
	__experimentalHeading as Heading,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { __, isRTL } from '@wordpress/i18n';
import SidebarButton from '../sidebar-button';
import { chevronLeft, chevronRight } from '../sidebar-icons';

/*
 * Axismundi-owned screen composition. Route/history and local sidebar
 * navigation state are supplied through explicit props.
 */
export default function SidebarNavigationScreen( {
	actions,
	backLabel = __( 'Back', 'axismundi' ),
	content,
	dashboardHref,
	dashboardLabel = 'Go to the Dashboard',
	description,
	footer,
	onBack,
	title,
} ) {
	const icon = isRTL() ? chevronRight : chevronLeft;
	const isRoot = Boolean( dashboardHref );

	return (
		<>
			<VStack
				className={ clsx( 'ax-admin-sidebar-screen__main', {
					'has-footer': !! footer,
				} ) }
				spacing={ 0 }
				justify="flex-start"
			>
				<HStack
					spacing={ 3 }
					alignment="flex-start"
					className="ax-admin-sidebar-screen__title-icon"
				>
					{ ! isRoot && onBack && (
						<SidebarButton
							onClick={ onBack }
							icon={ icon }
							label={ backLabel }
							showTooltip={ false }
						/>
					) }
					{ isRoot && (
						<SidebarButton
							icon={ icon }
							label={ dashboardLabel }
							href={ dashboardHref }
						/>
					) }
					<Heading
						className="ax-admin-sidebar-screen__title"
						level={ 1 }
						size={ 20 }
					>
						{ title }
					</Heading>
					{ actions && (
						<div className="ax-admin-sidebar-screen__actions">
							{ actions }
						</div>
					) }
				</HStack>
				<div className="ax-admin-sidebar-screen__content">
					{ description && (
						<div className="ax-admin-sidebar-screen__description">
							{ description }
						</div>
					) }
					{ content }
				</div>
			</VStack>
			{ footer && (
				<footer className="ax-admin-sidebar-screen__footer">
					{ footer }
				</footer>
			) }
		</>
	);
}
