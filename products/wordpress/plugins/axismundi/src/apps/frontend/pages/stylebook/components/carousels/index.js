import { Carousel } from '../../../../components/carousels/carousel';
import { CarouselItem } from '../../../../components/carousels/carousel-item';
import { CarouselItemMedia } from '../../../../components/carousels/carousel-item-media';
import { CarouselItemText } from '../../../../components/carousels/carousel-item-text';
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
	},
	{
		id: 'groups',
		icon: 'groups',
		title: 'Groups',
		supporting: 'Community topics',
	},
	{
		id: 'articles',
		icon: 'article',
		title: 'Articles',
		supporting: 'Long-form reading',
	},
	{
		id: 'media',
		icon: 'perm_media',
		title: 'Media',
		supporting: 'Images and video',
	},
	{
		id: 'events',
		icon: 'event',
		title: 'Events',
		supporting: 'Upcoming activity',
	},
];

const FIGMA_ASPECT_RATIOS = [ '16:9', '4:3', '1:1', '3:4', '9:16' ];

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
			<Carousel
				className="ax-stylebook-carousels__uncontained"
				label="Object collections"
				scrollBehavior="default"
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
							<Icon name={ item.icon } />
						</CarouselItemMedia>
						<CarouselItemText
							label={ item.title }
							supportingText={ item.supporting }
						/>
					</CarouselItem>
				) ) }
			</Carousel>
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
						id="carousel-building-blocks"
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
							These five ratios are Community Kit item building
							blocks. Combining them into Multi-aspect ratio
							geometry remains a later Uncontained configuration.
						</p>
					</section>

					<section
						className="ax-stylebook-page__group"
						id="all-items"
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
