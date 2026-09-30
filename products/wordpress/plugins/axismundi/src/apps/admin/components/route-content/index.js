import { Button } from '@wordpress/ui';
import { Icon, chevronLeft } from '@wordpress/icons';

/**
 * Route-owned content composition. The shared layout places this area but
 * deliberately does not prescribe its heading or controls.
 */
export default function RouteContent( {
	backLabel,
	children,
	eyebrow,
	onShowNavigation,
	title,
} ) {
	return (
		<>
			<header className="ax-admin-route-content__header">
				<Button
					className="ax-admin-route-content__back"
					variant="minimal"
					aria-label={ backLabel }
					onClick={ onShowNavigation }
				>
					<Icon icon={ chevronLeft } size={ 24 } />
				</Button>
				<p className="ax-admin-route-content__eyebrow">{ eyebrow }</p>
				<h2>{ title }</h2>
			</header>
			<div className="ax-admin-route-content__body">
				<h3>{ title }</h3>
				{ children }
			</div>
		</>
	);
}
