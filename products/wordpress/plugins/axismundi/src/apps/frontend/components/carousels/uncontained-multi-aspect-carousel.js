/**
 * Material uncontained multi-aspect ratio carousel.
 *
 * M3 describes it as "the same layout as the uncontained carousel but contains
 * items of various sizes", so it shares the Uncontained engine rather than
 * having one of its own. What it adds is a required input, and that is the
 * whole reason this wrapper exists.
 *
 * A uniform carousel can be laid out from one width. This one cannot: its total
 * width is the leading padding, plus the carousel's height times each item's
 * ratio, plus a gap between each pair, plus the trailing padding. One item with
 * no ratio makes that sum unknowable and leaves the renderer measuring whatever
 * the browser happened to give it, so every item is required to declare one and
 * is reported if it does not.
 *
 * Checking rather than defaulting is deliberate. A default ratio is a size the
 * caller did not choose, and M3 publishes a range for these items -- 9:16 to
 * 16:9 -- not a value to fall back on.
 *
 * `itemWidth` is not accepted at all. Passing one is what the uniform
 * UncontainedCarousel is for, and accepting both would leave two sources for
 * the same geometry.
 *
 * @param {Object}                                 props                            Component props.
 * @param {import('@wordpress/element').ReactNode} props.children                   Direct CarouselItem children, each with ratio-bearing media.
 * @param {string}                                 props.label                      Accessible name.
 * @param {'default'|'snap'}                       [props.scrollBehavior='default'] Both are published for Uncontained.
 * @return {import('@wordpress/element').ReactNode} Uncontained multi-aspect carousel.
 */

import { Children, isValidElement } from '@wordpress/element';
import warning from '@wordpress/warning';

import { Carousel } from './carousel';
import { CarouselItem } from './carousel-item';
import { CarouselItemMedia } from './carousel-item-media';

/**
 * Whether an item carries media with an explicitly declared ratio.
 *
 * CarouselItemMedia defaults its own `aspectRatio`, so the default never
 * reaches the element's props: a missing one is genuinely missing here.
 *
 * @param {import('@wordpress/element').ReactNode} item Candidate carousel item.
 * @return {boolean} True when the item declares a ratio.
 */
export function declaresRatio( item ) {
	return Children.toArray( item.props?.children ).some(
		( child ) =>
			isValidElement( child ) &&
			CarouselItemMedia === child.type &&
			undefined !== child.props.aspectRatio
	);
}

export function UncontainedMultiAspectCarousel( {
	children,
	scrollBehavior = 'default',
	...props
} ) {
	const items = Children.toArray( children ).filter(
		( child ) => isValidElement( child ) && CarouselItem === child.type
	);
	const without = items.filter( ( item ) => ! declaresRatio( item ) ).length;
	if ( without ) {
		warning(
			`UncontainedMultiAspectCarousel: ${ without } of ${ items.length } items declare no aspectRatio. Every item needs one, because the carousel's width is the sum of height times ratio across its items; this layout is only for items that genuinely vary.`
		);
	}

	return (
		<Carousel
			{ ...props }
			layout="uncontained"
			scrollBehavior={ scrollBehavior }
		>
			{ children }
		</Carousel>
	);
}
