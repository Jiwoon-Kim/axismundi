import { useSelect } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import { safeHTML } from '@wordpress/dom';
import { useMemo } from '@wordpress/element';

function getSvgMarkup( content, label ) {
	const document = new DOMParser().parseFromString( content, 'image/svg+xml' );
	const svg = document.querySelector( 'svg' );

	if ( ! svg || document.querySelector( 'parsererror' ) ) {
		return '';
	}

	svg.removeAttribute( 'height' );
	svg.removeAttribute( 'width' );

	if ( label ) {
		svg.setAttribute( 'aria-label', label );
		svg.setAttribute( 'role', 'img' );
		svg.removeAttribute( 'aria-hidden' );
		svg.removeAttribute( 'focusable' );
	} else {
		svg.setAttribute( 'aria-hidden', 'true' );
		svg.setAttribute( 'focusable', 'false' );
		svg.removeAttribute( 'aria-label' );
		svg.removeAttribute( 'role' );
	}

	return svg.outerHTML;
}

/**
 * Admin-owned rendering primitive for a trusted WordPress Icon Registry record.
 * The component deliberately resolves only the same-origin core-data entity.
 */
export default function RegistryIcon( {
	className = '',
	label = '',
	name,
	size = 24,
} ) {
	const { icon, hasResolvedIcon } = useSelect(
		( select ) => {
			if ( ! name ) {
				return { icon: null, hasResolvedIcon: true };
			}

			const { getEntityRecord, hasFinishedResolution } = select( coreDataStore );

			return {
				icon: getEntityRecord( 'root', 'icon', name ),
				hasResolvedIcon: hasFinishedResolution( 'getEntityRecord', [
					'root',
					'icon',
					name,
				] ),
			};
		},
		[ name ]
	);

	const svg = useMemo(
		() => ( icon?.content ? getSvgMarkup( icon.content, label ) : '' ),
		[ icon?.content, label ]
	);
	const normalizedSize = Number.isFinite( Number( size ) ) ? Number( size ) : 24;
	const classes = [ 'ax-admin-registry-icon', className ].filter( Boolean ).join( ' ' );
	const style = { '--ax-admin-registry-icon-size': `${ normalizedSize }px` };

	if ( ! hasResolvedIcon ) {
		return (
			<span
				aria-hidden="true"
				className={ classes }
				data-state="loading"
				style={ style }
			/>
		);
	}

	if ( ! svg ) {
		return null;
	}

	return (
		<span
			className={ classes }
			data-state="ready"
			// Core sanitizes registry records; safeHTML mirrors core/icon's second render pass.
			dangerouslySetInnerHTML={ { __html: safeHTML( svg ) } }
			style={ style }
		/>
	);
}
