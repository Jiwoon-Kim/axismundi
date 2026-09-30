import clsx from 'clsx';
import { Button } from '@wordpress/components';

/* Axismundi-owned composition using the public WordPress Button primitive. */
export default function SidebarButton( props ) {
	return (
		<Button
			size="compact"
			{ ...props }
			className={ clsx( 'ax-admin-sidebar-button', props.className ) }
		/>
	);
}
