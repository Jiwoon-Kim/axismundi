import { Carousel } from './carousel';

/**
 * Material uncontained carousel with a consumer-selected uniform item width.
 *
 * @param {Object} props             Component props.
 * @param {number} [props.itemWidth] Exact item width in CSS pixels. Leave it
 *                                   out for items of various sizes, which is
 *                                   M3's multi-aspect ratio carousel: the same
 *                                   layout, with each item as wide as its own
 *                                   media.
 * @param {Object} [props.style]     Additional root styles.
 * @return {import('@wordpress/element').ReactNode} Uncontained carousel.
 */
export function UncontainedCarousel( { itemWidth, style, ...props } ) {
	return (
		<Carousel
			{ ...props }
			layout="uncontained"
			scrollBehavior="default"
			style={
				0 < itemWidth
					? {
							...style,
							'--ax-carousel-item-width': `${ itemWidth }px`,
						}
					: style
			}
		/>
	);
}
