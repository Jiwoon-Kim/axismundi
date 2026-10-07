/**
 * Optional visual building block for a CarouselItem.
 *
 * The five aspect ratios mirror the Material 3 Community Kit building blocks.
 * They describe source media, not a Carousel layout algorithm. In particular,
 * using several ratios does not silently opt a caller into Multi-aspect ratio
 * geometry; that remains an Uncontained configuration to implement separately.
 *
 * @param {Object}                                 props                     Component props.
 * @param {import('@wordpress/element').ReactNode} props.children            Visual content.
 * @param {'16:9'|'4:3'|'1:1'|'3:4'|'9:16'}        [props.aspectRatio='1:1'] Published Figma building-block ratio.
 * @param {string}                                 [props.className]         Additional class name.
 * @return {import('@wordpress/element').ReactNode} Carousel item media.
 */

import warning from '@wordpress/warning';

const ASPECT_RATIOS = [ '16:9', '4:3', '1:1', '3:4', '9:16' ];

export function CarouselItemMedia( {
	children,
	aspectRatio = '1:1',
	className,
	...props
} ) {
	const ratio = ASPECT_RATIOS.includes( aspectRatio ) ? aspectRatio : '1:1';
	if ( ratio !== aspectRatio ) {
		warning(
			`CarouselItemMedia: unsupported aspectRatio "${ aspectRatio }"; using 1:1.`
		);
	}

	return (
		<span
			{ ...props }
			className={ [ 'ax-carousel-item-media', className ]
				.filter( Boolean )
				.join( ' ' ) }
			data-aspect-ratio={ ratio }
		>
			{ children }
		</span>
	);
}
