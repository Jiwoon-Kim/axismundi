import { Carousel } from '../../../../components/carousels/carousel';
import { CenteredHeroCarousel } from '../../../../components/carousels/centered-hero-carousel';
import { CarouselItem } from '../../../../components/carousels/carousel-item';
import { CarouselItemMedia } from '../../../../components/carousels/carousel-item-media';
import { CarouselItemText } from '../../../../components/carousels/carousel-item-text';
import { MultiBrowseCarousel } from '../../../../components/carousels/multi-browse-carousel';
import { UncontainedCarousel } from '../../../../components/carousels/uncontained-carousel';
import { Icon } from '../../../../components/material/icon';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import { useEffect, useRef, useState } from '@wordpress/element';
import '../../stylebook-page.css';
import './carousels.css';

const ITEMS = [
	{
		id: 'archive',
		icon: 'inventory_2',
		title: 'Archive',
		supporting: 'Saved objects',
		image: 'https://picsum.photos/seed/ax-archive/900/900',
	},
	{
		id: 'groups',
		icon: 'groups',
		title: 'Groups',
		supporting: 'Community topics',
		image: 'https://picsum.photos/seed/ax-groups/900/900',
	},
	{
		id: 'articles',
		icon: 'article',
		title: 'Articles',
		supporting: 'Long-form reading',
		image: 'https://picsum.photos/seed/ax-articles/900/900',
	},
	{
		id: 'media',
		icon: 'perm_media',
		title: 'Media',
		supporting: 'Images and video',
		image: 'https://picsum.photos/seed/ax-media/900/900',
	},
	{
		id: 'events',
		icon: 'event',
		title: 'Events',
		supporting: 'Upcoming activity',
		image: 'https://picsum.photos/seed/ax-events/900/900',
	},
	{
		id: 'people',
		icon: 'person',
		title: 'People',
		supporting: 'Saved profiles',
		image: 'https://picsum.photos/seed/ax-people/900/900',
	},
	{
		id: 'notes',
		icon: 'sticky_note_2',
		title: 'Notes',
		supporting: 'Short updates',
		image: 'https://picsum.photos/seed/ax-notes/900/900',
	},
	{
		id: 'photos',
		icon: 'photo_library',
		title: 'Photos',
		supporting: 'Visual collections',
		image: 'https://picsum.photos/seed/ax-photos/900/900',
	},
	{
		id: 'bookmarks',
		icon: 'bookmark',
		title: 'Bookmarks',
		supporting: 'Reading list',
		image: 'https://picsum.photos/seed/ax-bookmarks/900/900',
	},
	{
		id: 'places',
		icon: 'place',
		title: 'Places',
		supporting: 'Shared locations',
		image: 'https://picsum.photos/seed/ax-places/900/900',
	},
];

const FIGMA_ASPECT_RATIOS = [ '16:9', '4:3', '1:1', '3:4', '9:16' ];

/*
 * Upstream treats a missing preferred width as "one large item fills the
 * viewport, minus the space the small items need", which squeezes the small
 * item to its 40dp floor: that is why Hero showed 332 and 40 while
 * Multi-browse, which was given 186, showed a 56dp small.
 *
 * Fed the large width the Figma frames were drawn at, the ported algorithm
 * reproduces those frames exactly -- 316 and 56 for Hero, 56, 252 and 56 for
 * the centred Hero -- so the kit and the implementation were never in
 * disagreement, only differently fed. At the Tablet width the same inputs put
 * more large items on screen, which is what the guidelines say happens.
 */
const PREFERRED_ITEM_WIDTH = {
	hero: 316,
	'center-hero': 252,
	'multi-browse': 186,
	uncontained: undefined,
};

function UncontainedSample() {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const carousel = hostRef.current?.querySelector( '.ax-carousel' );
		const track = carousel?.querySelector( '.ax-carousel__items' );
		const items = [
			...( carousel?.querySelectorAll( '.ax-carousel-item' ) ?? [] ),
		];
		const action = items[ 0 ]?.querySelector( '.ax-carousel-item__action' );
		if ( ! carousel || ! track || ! action ) {
			return;
		}

		function measure() {
			const trackStyle = window.getComputedStyle( track );
			const actionStyle = window.getComputedStyle( action );
			setMetrics( {
				role: carousel.getAttribute( 'role' ),
				roleDescription: carousel.getAttribute(
					'aria-roledescription'
				),
				containerTabIndex: carousel.tabIndex,
				actions: carousel.querySelectorAll( '[data-carousel-action]' )
					.length,
				itemWidths: items
					.map( ( item ) =>
						Math.round( item.getBoundingClientRect().width )
					)
					.join( '/' ),
				padding: `${ Math.round( parseFloat( trackStyle.paddingInlineStart ) ) }/${ Math.round( parseFloat( trackStyle.paddingInlineEnd ) ) }`,
				blockPadding: Math.round(
					parseFloat( trackStyle.paddingBlockStart )
				),
				gap: Math.round( parseFloat( trackStyle.gap ) ),
				radius: Math.round(
					parseFloat( actionStyle.borderTopLeftRadius )
				),
				overflows: track.scrollWidth > track.clientWidth,
				scrollLeft: Math.round( track.scrollLeft ),
			} );
		}

		measure();
		track.addEventListener( 'scroll', measure, { passive: true } );
		return () => track.removeEventListener( 'scroll', measure );
	}, [] );

	return (
		<div className="ax-stylebook-carousels__sample" ref={ hostRef }>
			<a
				className="ax-stylebook-carousels__before"
				href="#uncontained-carousel"
			>
				Before carousel
			</a>
			<UncontainedCarousel
				className="ax-stylebook-carousels__uncontained"
				itemWidth={ 280 }
				label="Object collections"
			>
				{ ITEMS.map( ( item ) => (
					<CarouselItem
						href={ `#all-${ item.id }` }
						key={ item.id }
						label={ item.title }
						outlined
					>
						<CarouselItemMedia
							aspectRatio="4:3"
							className="ax-stylebook-carousels__visual"
						>
							<img alt="" draggable="false" src={ item.image } />
						</CarouselItemMedia>
						<CarouselItemText
							label={ item.title }
							supportingText={ item.supporting }
						/>
					</CarouselItem>
				) ) }
			</UncontainedCarousel>
			<a className="ax-stylebook-carousels__show-all" href="#all-items">
				Show all
			</a>
			<p className="ax-stylebook-page__note">
				{ metrics
					? `role ${ metrics.role }/${ metrics.roleDescription } · container tabindex ${ metrics.containerTabIndex } · item actions ${ metrics.actions } · widths ${ metrics.itemWidths }px (280 is this specimen's local policy, not M3) · inline padding ${ metrics.padding }px · block padding ${ metrics.blockPadding }px · gap ${ metrics.gap }px · radius ${ metrics.radius }px · horizontal overflow ${ metrics.overflows } · scrollLeft ${ metrics.scrollLeft }px`
					: 'Measuring...' }
			</p>
		</div>
	);
}

/*
 * `multiAspect` is a property of this specimen, not of the component: it picks
 * which ratios the items are drawn at and what the label says. M3 describes the
 * layout as "the same layout as the uncontained carousel but with items of
 * various sizes", so the carousel is told nothing -- an uncontained carousel
 * with no uniform width already lets each item be as wide as its own media.
 */
function KeylineSample( {
	layout,
	context,
	items,
	alignment = 'start',
	multiAspect = false,
} ) {
	let RuntimeCarousel = Carousel;
	if ( 'center' === alignment ) {
		RuntimeCarousel = CenteredHeroCarousel;
	} else if ( 'multi-browse' === layout ) {
		RuntimeCarousel = MultiBrowseCarousel;
	}
	return (
		<div
			className="ax-stylebook-carousels__keyline-stage"
			data-context={ context }
			data-multi-aspect={ multiAspect ? '' : undefined }
		>
			<p className="ax-stylebook-page__eyebrow">
				{ context } ·{ ' ' }
				{ alignment === 'center' ? 'center-aligned ' : '' }
				{ multiAspect ? 'multi-aspect ratio' : layout }
			</p>
			<RuntimeCarousel
				className="ax-stylebook-carousels__keyline-carousel"
				label={ `${ context } ${ layout } items` }
				layout={ layout }
				alignment={ alignment }
				preferredItemWidth={
					PREFERRED_ITEM_WIDTH[
						'center' === alignment ? 'center-hero' : layout
					]
				}
				scrollBehavior="snap"
			>
				{ items.map( ( item, index ) => (
					<CarouselItem
						href={ `#all-${ item.id }` }
						key={ item.id }
						label={ item.title }
					>
						<CarouselItemMedia
							aspectRatio={
								multiAspect
									? FIGMA_ASPECT_RATIOS[
											index % FIGMA_ASPECT_RATIOS.length
										]
									: '1:1'
							}
						>
							<img alt="" draggable="false" src={ item.image } />
						</CarouselItemMedia>
						{ multiAspect && 0 === index ? (
							<CarouselItemText
								appearance="overlay"
								label={ item.title }
								supportingText={ item.supporting }
							/>
						) : null }
					</CarouselItem>
				) ) }
			</RuntimeCarousel>
		</div>
	);
}

function StaticProfile( { context, label, items } ) {
	return (
		<div
			className="ax-stylebook-carousels__static-profile"
			data-context={ context }
		>
			<p className="ax-stylebook-page__eyebrow">
				{ context } · { label }
			</p>
			<div className="ax-stylebook-carousels__static-track">
				{ items.map( ( item, index ) => (
					<div
						className="ax-stylebook-carousels__static-item"
						data-role={ item.role }
						key={ `${ label }-${ index }` }
						style={ {
							inlineSize: `${ item.inlineSize }px`,
						} }
					>
						<img alt="" draggable="false" src={ item.image } />
					</div>
				) ) }
			</div>
		</div>
	);
}

const FIGMA_PROFILES = {
	'hero-mobile': [
		{ role: 'large', inlineSize: 316, image: ITEMS[ 0 ].image },
		{ role: 'small', inlineSize: 56, image: ITEMS[ 1 ].image },
	],
	'multi-mobile': [
		{ role: 'large', inlineSize: 188, image: ITEMS[ 0 ].image },
		{ role: 'medium', inlineSize: 120, image: ITEMS[ 1 ].image },
		{ role: 'small', inlineSize: 56, image: ITEMS[ 2 ].image },
	],
	'center-mobile': [
		{ role: 'small', inlineSize: 56, image: ITEMS[ 0 ].image },
		{ role: 'large', inlineSize: 252, image: ITEMS[ 1 ].image },
		{ role: 'small', inlineSize: 56, image: ITEMS[ 2 ].image },
	],
	'hero-tablet': [
		{ role: 'large', inlineSize: 184, image: ITEMS[ 0 ].image },
		{ role: 'large', inlineSize: 184, image: ITEMS[ 1 ].image },
		{ role: 'medium', inlineSize: 120, image: ITEMS[ 2 ].image },
		{ role: 'small', inlineSize: 56, image: ITEMS[ 3 ].image },
	],
	'multi-tablet': [
		{ role: 'large', inlineSize: 184, image: ITEMS[ 0 ].image },
		{ role: 'large', inlineSize: 184, image: ITEMS[ 1 ].image },
		{ role: 'medium', inlineSize: 120, image: ITEMS[ 2 ].image },
		{ role: 'small', inlineSize: 56, image: ITEMS[ 3 ].image },
	],
	'center-tablet': [
		{ role: 'large', inlineSize: 184, image: ITEMS[ 0 ].image },
		{ role: 'large', inlineSize: 184, image: ITEMS[ 1 ].image },
		{ role: 'medium', inlineSize: 120, image: ITEMS[ 2 ].image },
		{ role: 'small', inlineSize: 56, image: ITEMS[ 3 ].image },
	],
	'multi-aspect-mobile': [
		{ inlineSize: 362.22, image: ITEMS[ 0 ].image },
		{ inlineSize: 270.67, image: ITEMS[ 1 ].image },
		{ inlineSize: 204, image: ITEMS[ 2 ].image },
		{ inlineSize: 153.25, image: ITEMS[ 3 ].image },
		{ inlineSize: 116, image: ITEMS[ 4 ].image },
	],
};

export function StylebookCarouselsPage() {
	return (
		<Scaffold className="axismundi-social ax-stylebook ax-stylebook--carousels">
			<div className="ax-stylebook-page">
				<nav
					className="ax-stylebook-page__navigation"
					aria-label="Stylebook"
				>
					<a href="/social/stylebook">Stylebook</a>
					<a href="/social/stylebook/components/cards">Cards</a>
					<a href="/social/stylebook/components/navigations">
						Navigation
					</a>
				</nav>

				<section
					className="ax-stylebook-page__section"
					id="carousels"
					aria-labelledby="ax-stylebook-carousels-title"
					style={ {
						gridTemplateColumns: 'minmax(0, 1fr)',
						maxInlineSize: '100%',
						minInlineSize: 0,
					} }
				>
					<header className="ax-stylebook-page__section-header">
						<p className="ax-stylebook-page__eyebrow">
							Material component
						</p>
						<h1 id="ax-stylebook-carousels-title">Carousels</h1>
					</header>

					<section
						className="ax-stylebook-page__group"
						id="uncontained-carousel"
						style={ {
							gridTemplateColumns: 'minmax(0, 1fr)',
							maxInlineSize: '100%',
							minInlineSize: 0,
						} }
					>
						<header className="ax-stylebook-page__group-header">
							<p className="ax-stylebook-page__group-kicker">
								Uniform geometry, free scrolling, and no
								controls over the content
							</p>
							<h2>Uncontained</h2>
						</header>
						<UncontainedSample />
						<p className="ax-stylebook-page__note">
							Left and Right move between item actions without
							removing any item from the Tab order. Up and Down
							leave the carousel. The named group itself never
							receives focus.
						</p>
					</section>

					<section
						className="ax-stylebook-page__group"
						id="carousel-keylines"
						style={ {
							gridTemplateColumns: 'minmax(0, 1fr)',
							maxInlineSize: '100%',
							minInlineSize: 0,
						} }
					>
						<header className="ax-stylebook-page__group-header">
							<p className="ax-stylebook-page__group-kicker">
								Runtime behavior with ten items per carousel
							</p>
							<h2>Hero and Multi-browse</h2>
						</header>
						<div
							className="ax-stylebook-carousels__keylines"
							style={ {
								maxInlineSize: '100%',
								minInlineSize: 0,
							} }
						>
							<KeylineSample
								context="Mobile"
								items={ ITEMS }
								layout="hero"
							/>
							<KeylineSample
								context="Mobile"
								items={ ITEMS }
								layout="multi-browse"
							/>
							<KeylineSample
								alignment="center"
								context="Mobile"
								items={ ITEMS }
								layout="hero"
							/>
							<KeylineSample
								context="Tablet"
								items={ ITEMS }
								layout="hero"
							/>
							<KeylineSample
								context="Tablet"
								items={ ITEMS }
								layout="multi-browse"
							/>
							<KeylineSample
								alignment="center"
								context="Tablet"
								items={ ITEMS }
								layout="hero"
							/>
							<KeylineSample
								context="Mobile"
								items={ ITEMS }
								layout="uncontained"
								multiAspect
							/>
							<KeylineSample
								context="Tablet"
								items={ ITEMS }
								layout="uncontained"
								multiAspect
							/>
						</div>
						<p className="ax-stylebook-page__note">
							These are runtime specimens with ten items. The
							Mobile and Tablet labels identify fixed reference
							container widths, not public breakpoint props.
						</p>
					</section>

					<section
						className="ax-stylebook-page__group"
						id="carousel-figma-profiles"
						style={ {
							gridTemplateColumns: 'minmax(0, 1fr)',
							maxInlineSize: '100%',
							minInlineSize: 0,
						} }
					>
						<header className="ax-stylebook-page__group-header">
							<p className="ax-stylebook-page__group-kicker">
								Static measurements, not runtime behavior
							</p>
							<h2>Figma reference profiles</h2>
						</header>
						<div className="ax-stylebook-carousels__static-profiles">
							<StaticProfile
								context="Mobile"
								label="Hero"
								items={ FIGMA_PROFILES[ 'hero-mobile' ] }
							/>
							<StaticProfile
								context="Mobile"
								label="Multi-browse"
								items={ FIGMA_PROFILES[ 'multi-mobile' ] }
							/>
							<StaticProfile
								context="Mobile"
								label="Center-aligned Hero"
								items={ FIGMA_PROFILES[ 'center-mobile' ] }
							/>
							<StaticProfile
								context="Tablet"
								label="Hero"
								items={ FIGMA_PROFILES[ 'hero-tablet' ] }
							/>
							<StaticProfile
								context="Tablet"
								label="Multi-browse"
								items={ FIGMA_PROFILES[ 'multi-tablet' ] }
							/>
							<StaticProfile
								context="Tablet"
								label="Center-aligned Hero"
								items={ FIGMA_PROFILES[ 'center-tablet' ] }
							/>
							<StaticProfile
								context="Mobile"
								label="Multi-aspect ratio"
								items={
									FIGMA_PROFILES[ 'multi-aspect-mobile' ]
								}
							/>
							<StaticProfile
								context="Tablet"
								label="Multi-aspect ratio"
								items={
									FIGMA_PROFILES[ 'multi-aspect-mobile' ]
								}
							/>
						</div>
						<p className="ax-stylebook-page__note">
							These profiles preserve the inspected Figma
							compositions as static measurement references. They
							are not the component&apos;s item-count or runtime
							breakpoint contract.
						</p>
					</section>

					<section
						className="ax-stylebook-page__group"
						id="carousel-building-blocks"
						style={ {
							gridTemplateColumns: 'minmax(0, 1fr)',
							maxInlineSize: '100%',
							minInlineSize: 0,
						} }
					>
						<header className="ax-stylebook-page__group-header">
							<p className="ax-stylebook-page__group-kicker">
								Figma building blocks, not layout strategies
							</p>
							<h2>Item media ratios</h2>
						</header>
						<div className="ax-stylebook-carousels__ratios">
							{ FIGMA_ASPECT_RATIOS.map( ( ratio ) => (
								<figure key={ ratio }>
									<CarouselItemMedia
										aspectRatio={ ratio }
										className="ax-stylebook-carousels__ratio"
									>
										<Icon name="image" />
									</CarouselItemMedia>
									<figcaption>{ ratio }</figcaption>
								</figure>
							) ) }
						</div>
						<p className="ax-stylebook-page__note">
							These five Community Kit ratios are implemented
							above as one Uncontained configuration. The images
							are stylebook-only Picsum fixtures, not a component
							data dependency.
						</p>
					</section>

					<section
						className="ax-stylebook-page__group"
						id="all-items"
						style={ {
							gridTemplateColumns: 'minmax(0, 1fr)',
							maxInlineSize: '100%',
							minInlineSize: 0,
						} }
					>
						<header className="ax-stylebook-page__group-header">
							<p className="ax-stylebook-page__group-kicker">
								Required non-horizontal path for a vertically
								scrolling page
							</p>
							<h2>All object collections</h2>
						</header>
						<ul className="ax-stylebook-carousels__all-items">
							{ ITEMS.map( ( item ) => (
								<li id={ `all-${ item.id }` } key={ item.id }>
									<strong>{ item.title }</strong>{ ' ' }
									{ item.supporting }
								</li>
							) ) }
						</ul>
					</section>
				</section>
			</div>
		</Scaffold>
	);
}
