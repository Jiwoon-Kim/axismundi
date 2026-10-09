/**
 * Optional visual building block for a CarouselItem.
 *
 * M3 gives the uncontained multi-aspect ratio layout a range, not a set:
 * "Item widths can range anywhere between 9:16 for min width size to 16:9 for
 * max width." An earlier version accepted five ratios, the ones the Community
 * Kit draws its building blocks at, and warned on anything else. Those five are
 * a sample of the range, so this takes any ratio and clamps it to the published
 * bounds.
 *
 * The width is not restated here. A ratio plus the carousel's height already
 * determines it, so the ratio is handed to CSS and the browser does the
 * arithmetic; writing `height * 16 / 9` beside `aspect-ratio: 16 / 9` would be
 * the same fact stored twice.
 *
 * @param {Object}                                 props                     Component props.
 * @param {import('@wordpress/element').ReactNode} props.children            Visual content.
 * @param {string|number}                          [props.aspectRatio='1:1'] Width to height, between 9:16 and 16:9.
 * @param {string}                                 [props.className]         Additional class name.
 * @return {import('@wordpress/element').ReactNode} Carousel item media.
 */

import warning from '@wordpress/warning';

/* The published bounds, as ratios of width to height. */
const MINIMUM_RATIO = 9 / 16;
const MAXIMUM_RATIO = 16 / 9;

export function parseAspectRatio( aspectRatio ) {
	const parts = String( aspectRatio ).split( ':' );
	const ratio =
		2 === parts.length
			? Number( parts[ 0 ] ) / Number( parts[ 1 ] )
			: Number( aspectRatio );
	if ( ! Number.isFinite( ratio ) || 0 >= ratio ) {
		return null;
	}
	return ratio;
}

export function CarouselItemMedia( {
	children,
	aspectRatio = '1:1',
	className,
	...props
} ) {
	let ratio = parseAspectRatio( aspectRatio );
	if ( null === ratio ) {
		warning(
			`CarouselItemMedia: aspectRatio "${ aspectRatio }" is not a ratio; using 1:1.`
		);
		ratio = 1;
	}
	const clamped = Math.min( Math.max( ratio, MINIMUM_RATIO ), MAXIMUM_RATIO );
	if ( clamped !== ratio ) {
		warning(
			`CarouselItemMedia: aspectRatio "${ aspectRatio }" is outside the published 9:16 to 16:9 range; clamping.`
		);
	}

	return (
		<span
			{ ...props }
			className={ [ 'ax-carousel-item-media', className ]
				.filter( Boolean )
				.join( ' ' ) }
			style={ {
				...props.style,
				'--ax-carousel-media-ratio': clamped,
			} }
		>
			{ children }
		</span>
	);
}
