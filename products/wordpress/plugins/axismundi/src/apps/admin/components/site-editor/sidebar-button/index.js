import clsx from 'clsx';
import { Button } from '@wordpress/components';

/* Local port of packages/edit-site/src/components/sidebar-button. */
export default function SidebarButton( props ) {
	return (
		<Button
			size="compact"
			{ ...props }
			className={ clsx( 'edit-site-sidebar-button', props.className ) }
		/>
	);
}
