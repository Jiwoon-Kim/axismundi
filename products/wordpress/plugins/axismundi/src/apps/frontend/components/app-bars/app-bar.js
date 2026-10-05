/**
 * Material 3 Expressive app bar.
 *
 * Scroll position belongs to the surface that owns the scroll container, so
 * this component receives a state value rather than observing the window.
 * Search app bars compose Search and are deferred until that component exists.
 *
 * @param {Object} props Component props.
 * @param {'small'|'medium'|'large'} [props.variant='small'] Expressive size.
 * @param {'leading'|'centered'} [props.alignment='leading'] Headline alignment.
 * @param {import('@wordpress/element').ReactNode} props.title Page-specific title node.
 * @param {import('@wordpress/element').ReactNode} [props.subtitle] Optional context node.
 * @param {import('@wordpress/element').ReactNode} [props.leading] Navigation control.
 * @param {import('@wordpress/element').ReactNode} [props.actions] One or two essential actions.
 * @param {boolean} [props.scrolled=false] Applies the published on-scroll surface.
 * @param {string} [props.className] Additional component class name.
 * @return {import('@wordpress/element').ReactNode} App bar.
 */

import { Children, Fragment } from '@wordpress/element';
import { Elevation } from '../material/elevation';
import warning from '@wordpress/warning';

const VARIANTS = [ 'small', 'medium', 'large' ];
const ALIGNMENTS = [ 'leading', 'centered' ];

function oneOf( value, allowed, fallback, name ) {
	if ( allowed.includes( value ) ) {
		return value;
	}
	warning( 'AppBar: unknown ' + name + ' "' + value + '"; using ' + fallback + '.' );
	return fallback;
}

export function AppBar( {
	variant = 'small',
	alignment = 'leading',
	title,
	subtitle,
	leading,
	actions,
	scrolled = false,
	className,
	...props
} ) {
	const size = oneOf( variant, VARIANTS, 'small', 'variant' );
	const textAlignment = oneOf( alignment, ALIGNMENTS, 'leading', 'alignment' );
	/*
	 * A direct Fragment is the ergonomic form for two sibling IconButtons.
	 * Count its children, while intentionally treating an arbitrary component as
	 * one opaque action slot: inspecting another component's render tree would
	 * make this API lie.
	 */
	const actionNodes = actions?.type === Fragment ? actions.props.children : actions;
	const actionCount = Children.count( actionNodes );

	if ( ! title ) {
		warning( 'AppBar: title is required. Pass a page-owned text, image, or logo node.' );
	}
	if ( 2 < actionCount ) {
		warning( 'AppBar: ' + actionCount + ' trailing actions. M3 allows one essential action, two if necessary; use a Toolbar for more.' );
	}

	return (
		<header
			{ ...props }
			className={ [ 'ax-app-bar', className ].filter( Boolean ).join( ' ' ) }
			data-alignment={ textAlignment }
			data-has-subtitle={ subtitle ? '' : undefined }
			data-scrolled={ scrolled ? '' : undefined }
			data-variant={ size }
		>
			<Elevation level={ scrolled ? 2 : 0 } />
			<div className="ax-app-bar__leading">{ leading }</div>
			<div className="ax-app-bar__text">
				<div className="ax-app-bar__title">{ title }</div>
				{ subtitle ? <div className="ax-app-bar__subtitle">{ subtitle }</div> : null }
			</div>
			{ actions ? <div className="ax-app-bar__actions">{ actions }</div> : null }
		</header>
	);
}
