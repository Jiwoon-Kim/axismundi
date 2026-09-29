import clsx from 'clsx';
import {
	__experimentalHStack as HStack,
	__experimentalHeading as Heading,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { isRTL } from '@wordpress/i18n';
import SidebarButton from '../sidebar-button';
import { chevronLeft, chevronRight } from '../sidebar-icons';

/*
 * Local port of packages/edit-site/src/components/sidebar-navigation-screen.
 * The edit-site store/router determines root and back state upstream; this
 * plugin supplies those integration values as explicit props.
 */
export default function SidebarNavigationScreen( {
	actions,
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
				className={ clsx( 'edit-site-sidebar-navigation-screen__main', {
					'has-footer': !! footer,
				} ) }
				spacing={ 0 }
				justify="flex-start"
			>
				<HStack
					spacing={ 3 }
					alignment="flex-start"
					className="edit-site-sidebar-navigation-screen__title-icon"
				>
					{ ! isRoot && onBack && (
						<SidebarButton
							onClick={ onBack }
							icon={ icon }
							label="Back"
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
						className="edit-site-sidebar-navigation-screen__title"
						level={ 1 }
						size={ 20 }
					>
						{ title }
					</Heading>
					{ actions && (
						<div className="edit-site-sidebar-navigation-screen__actions">
							{ actions }
						</div>
					) }
				</HStack>
				<div className="edit-site-sidebar-navigation-screen__content">
					{ description && (
						<div className="edit-site-sidebar-navigation-screen__description">
							{ description }
						</div>
					) }
					{ content }
				</div>
			</VStack>
			{ footer && (
				<footer className="edit-site-sidebar-navigation-screen__footer">
					{ footer }
				</footer>
			) }
		</>
	);
}
