import { Carousel } from './carousel';

/**
 * Material multi-browse carousel.
 *
 * @param {Object} props                    Component props.
 * @param {number} props.preferredItemWidth Target width for large items in CSS pixels.
 * @return {import('@wordpress/element').ReactNode} Multi-browse carousel.
 */
export function MultiBrowseCarousel( props ) {
	return (
		<Carousel { ...props } layout="multi-browse" scrollBehavior="snap" />
	);
}
