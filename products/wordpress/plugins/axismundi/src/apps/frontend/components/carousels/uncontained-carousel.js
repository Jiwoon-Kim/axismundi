import { Carousel } from './carousel';

/**
 * Material uncontained carousel with a consumer-selected uniform item width.
 *
 * @param {Object} props           Component props.
 * @param {number} props.itemWidth Item width in CSS pixels.
 * @param {Object} [props.style]   Additional root styles.
 * @return {import('@wordpress/element').ReactNode} Uncontained carousel.
 */
export function UncontainedCarousel( { itemWidth, style, ...props } ) {
	return (
		<Carousel
			{ ...props }
			layout="uncontained"
			scrollBehavior="default"
			style={ {
				...style,
				'--ax-carousel-item-width': `${ itemWidth }px`,
			} }
		/>
	);
}
