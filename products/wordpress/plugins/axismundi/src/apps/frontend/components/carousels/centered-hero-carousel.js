import { Carousel } from './carousel';

/**
 * Material center-aligned hero carousel.
 *
 * @param {Object} props                      Component props.
 * @param {number} [props.preferredItemWidth] Optional target width for large items.
 * @return {import('@wordpress/element').ReactNode} Centered hero carousel.
 */
export function CenteredHeroCarousel( props ) {
	return (
		<Carousel
			{ ...props }
			alignment="center"
			layout="hero"
			scrollBehavior="snap"
		/>
	);
}
