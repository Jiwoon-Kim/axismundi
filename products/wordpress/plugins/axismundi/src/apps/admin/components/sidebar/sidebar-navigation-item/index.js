import clsx from 'clsx';
import { __experimentalItem as Item, FlexBlock } from '@wordpress/components';
import { Stack } from '@wordpress/ui';
import { isRTL } from '@wordpress/i18n';
import { chevronRightSmall, chevronLeftSmall, Icon } from '@wordpress/icons';

/*
 * Axismundi-owned sidebar item using public WordPress component primitives.
 * Navigation behavior is supplied by the local sidebar provider.
 */
export default function SidebarNavigationItem( {
	isHidden = false,
	...props
} ) {
	if ( isHidden ) {
		return null;
	}

	return <SidebarNavigationItemContent { ...props } />;
}

function SidebarNavigationItemContent( {
	active = false,
	children,
	className,
	icon,
	suffix,
	withChevron = false,
	...props
} ) {
	return (
		<Item
			className={ clsx(
				'ax-admin-sidebar-navigation-item',
				{ 'with-suffix': ! withChevron && suffix },
				className
			) }
			aria-current={ active ? true : undefined }
			{ ...props }
		>
			<Stack direction="row" align="center" justify="start" gap="sm">
				{ icon && <Icon icon={ icon } size={ 24 } /> }
				<FlexBlock>{ children }</FlexBlock>
				{ withChevron && (
					<Icon
						icon={ isRTL() ? chevronLeftSmall : chevronRightSmall }
						className="ax-admin-sidebar-navigation-item__drilldown-indicator"
						size={ 24 }
					/>
				) }
				{ ! withChevron && suffix }
			</Stack>
		</Item>
	);
}
