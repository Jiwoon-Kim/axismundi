import {
	CAROUSEL_MAX_SMALL_ITEM_SIZE,
	CAROUSEL_MIN_SMALL_ITEM_SIZE,
	centeredHeroStrategy,
	createCarouselKeylines,
	createCarouselStrategy,
	heroStrategy,
	keylinesForScrollOffset,
	maximumScrollOffset,
	multiBrowseStrategy,
	scrollOffsetForItem,
	uncontainedStrategy,
} from '../carousel-strategy';

function occupiedSpace( strategy, itemSpacing ) {
	return (
		strategy.sizes.reduce( ( total, size ) => total + size, 0 ) +
		Math.max( 0, strategy.sizes.length - 1 ) * itemSpacing
	);
}

describe( 'Material carousel strategies', () => {
	test( 'moves centered keylines to each edge one position per step', () => {
		const availableSpace = 768;
		const itemSpacing = 8;
		const defaultKeylines = createCarouselKeylines( {
			alignment: 'center',
			availableSpace,
			itemSpacing,
			leftAnchorSize: 10,
			rightAnchorSize: 10,
			sizes: [ 56, 122, 186, 186, 122, 56 ],
		} );
		const strategy = createCarouselStrategy( {
			availableSpace,
			defaultKeylines,
			itemSpacing,
		} );

		expect( strategy.startKeylineSteps ).toHaveLength( 3 );
		expect( strategy.endKeylineSteps ).toHaveLength( 3 );
		expect(
			strategy.startKeylineSteps
				.at( -1 )
				.map( ( keyline ) => keyline.offset )
		).toEqual( [ -13, 93, 287, 449, 579, 676, 740, 781 ] );
		expect(
			strategy.endKeylineSteps
				.at( -1 )
				.map( ( keyline ) => keyline.offset )
		).toEqual( [ -13, 28, 92, 189, 319, 481, 675, 781 ] );
	} );

	test( 'makes the first centered-hero item focal at scroll start', () => {
		const result = centeredHeroStrategy( {
			availableSpace: 380,
			itemCount: 10,
			itemSpacing: 8,
		} );
		const keylines = keylinesForScrollOffset( result, 0, 3000 );
		const firstVisible = keylines.find( ( keyline ) => ! keyline.isAnchor );

		expect( firstVisible.isFocal ).toBe( true );
		expect( firstVisible.size ).toBeCloseTo(
			result.arrangement.largeSize,
			5
		);
		expect( firstVisible.offset - firstVisible.size / 2 ).toBeCloseTo(
			0,
			5
		);
	} );

	test( 'snaps centered-hero items to the focal keyline for each edge state', () => {
		const result = centeredHeroStrategy( {
			availableSpace: 380,
			itemCount: 10,
			itemSpacing: 8,
		} );
		const maximum = maximumScrollOffset( {
			availableSpace: 380,
			itemCount: 10,
			itemMainAxisSize: result.itemMainAxisSize,
			itemSpacing: 8,
		} );

		expect( scrollOffsetForItem( result, 0, 10 ) ).toBe( 0 );
		expect( scrollOffsetForItem( result, 1, 10 ) ).toBe( 244 );
		expect( scrollOffsetForItem( result, 9, 10 ) ).toBe( maximum );
	} );

	test( 'matches the AndroidX maximum scroll formula', () => {
		expect(
			maximumScrollOffset( {
				availableSpace: 380,
				itemCount: 10,
				itemMainAxisSize: 186,
				itemSpacing: 8,
			} )
		).toBe( 1552 );
	} );

	test( 'matches the AndroidX multi-browse reference arrangement', () => {
		const strategy = multiBrowseStrategy( {
			availableSpace: 380,
			itemCount: 10,
			itemSpacing: 8,
			preferredItemWidth: 186,
		} );

		expect( strategy.arrangement.largeSize ).toBeCloseTo( 186, 5 );
		expect( strategy.sizes.at( -1 ) ).toBeCloseTo( 56, 5 );
		expect( strategy.keylines.at( -2 ).offset ).toBeCloseTo( 352, 5 );
		expect(
			strategy.keylines.map( ( keyline ) => keyline.unadjustedOffset )
		).toEqual( [ -101, 93, 287, 481, 675 ] );
	} );

	test.each( [ 412, 600, 713 ] )(
		'multi-browse fills %dpx from a preferred width',
		( availableSpace ) => {
			const strategy = multiBrowseStrategy( {
				availableSpace,
				itemCount: 10,
				itemSpacing: 8,
				preferredItemWidth: 186,
			} );

			expect( occupiedSpace( strategy, 8 ) ).toBeCloseTo(
				availableSpace,
				5
			);
			expect( strategy.arrangement.smallSize ).toBeGreaterThanOrEqual(
				CAROUSEL_MIN_SMALL_ITEM_SIZE
			);
			expect( strategy.arrangement.smallSize ).toBeLessThanOrEqual(
				CAROUSEL_MAX_SMALL_ITEM_SIZE
			);
			expect(
				strategy.keylines.some( ( keyline ) => keyline.isFocal )
			).toBe( true );
		}
	);

	test.each( [ 1, 2, 5, 10 ] )(
		'multi-browse does not require more non-large positions than %d items provide',
		( itemCount ) => {
			const strategy = multiBrowseStrategy( {
				availableSpace: 412,
				itemCount,
				itemSpacing: 8,
				preferredItemWidth: 186,
			} );
			const nonLargeCount =
				strategy.arrangement.mediumCount +
				strategy.arrangement.smallCount;

			expect( nonLargeCount ).toBeLessThanOrEqual( itemCount );
		}
	);

	test( 'uncontained preserves exact item width as the large stride input', () => {
		const strategy = uncontainedStrategy( {
			availableSpace: 600,
			itemSpacing: 8,
			itemWidth: 186,
		} );

		expect( strategy.itemMainAxisSize ).toBe( 194 );
		expect( strategy.arrangement.largeCount ).toBe( 3 );
		expect( strategy.arrangement.mediumCount ).toBe( 1 );
		expect( strategy.keylines[ 0 ].isAnchor ).toBe( true );
		expect( strategy.keylines.at( -1 ).isAnchor ).toBe( true );
	} );

	test.each( [
		[ 125, 3, 37.5, 18.75 ],
		[ 105, 3, 102, 51 ],
	] )(
		'matches AndroidX uncontained geometry for %dpx items',
		( itemWidth, largeCount, mediumSize, leftAnchorSize ) => {
			const strategy = uncontainedStrategy( {
				availableSpace: 400,
				itemSpacing: 0,
				itemWidth,
			} );

			expect( strategy.arrangement.largeCount ).toBe( largeCount );
			expect( strategy.arrangement.mediumSize ).toBeCloseTo(
				mediumSize,
				5
			);
			expect( strategy.keylines[ 0 ].size ).toBeCloseTo(
				leftAnchorSize,
				5
			);
			expect( strategy.keylines.at( -1 ).size ).toBe( 10 );
		}
	);

	test( 'reproduces the Figma reference profiles from their own large width', () => {
		/*
		 * The kit and the ported algorithm were never in disagreement. Upstream
		 * treats a missing preferred width as "one large item fills the viewport",
		 * which pushes the small item to its 40dp floor; given the width the frame
		 * was drawn at, the same code returns the frame.
		 */
		expect(
			heroStrategy( {
				availableSpace: 380,
				itemCount: 10,
				itemSpacing: 8,
				preferredItemWidth: 316,
			} ).sizes
		).toEqual( [ 316, 56 ] );

		expect(
			centeredHeroStrategy( {
				availableSpace: 380,
				itemCount: 10,
				itemSpacing: 8,
				preferredItemWidth: 252,
			} ).sizes
		).toEqual( [ 56, 252, 56 ] );

		/* And without one, the small item sits on its floor. */
		expect(
			heroStrategy( {
				availableSpace: 380,
				itemCount: 10,
				itemSpacing: 8,
			} ).sizes
		).toEqual( [ 332, 40 ] );
	} );

	test( 'the large item takes what the small items leave', () => {
		/*
		 * How the compact preferred widths were chosen, and the check that they
		 * return the Figma Mobile frames. It is not a rule the specimens apply
		 * per container: the scaling test below is why.
		 */
		const spacing = 8;
		const preferred = ( availableSpace, smallCount ) =>
			availableSpace - smallCount * ( 56 + spacing );

		for ( const availableSpace of [ 380, 568 ] ) {
			expect(
				heroStrategy( {
					availableSpace,
					itemCount: 10,
					itemSpacing: spacing,
					preferredItemWidth: preferred( availableSpace, 1 ),
				} ).sizes
			).toEqual( [ availableSpace - 64, 56 ] );

			expect(
				centeredHeroStrategy( {
					availableSpace,
					itemCount: 10,
					itemSpacing: spacing,
					preferredItemWidth: preferred( availableSpace, 2 ),
				} ).sizes
			).toEqual( [ 56, availableSpace - 128, 56 ] );
		}
	} );

	test( 'a wider container shows more items, not a wider one', () => {
		/*
		 * "As the carousel container size increases, so do the number of carousel
		 * items visible at a time", and "on larger screens, more large items are
		 * visible". The adaptation belongs to the algorithm, so the caller's
		 * preferred width stays put and the container decides how many fit. A
		 * width derived per container does the opposite: it held Hero at two
		 * items and grew the large one to 1504dp.
		 */
		const counts = [ 380, 568, 808, 1168, 1568 ].map(
			( availableSpace ) =>
				heroStrategy( {
					availableSpace,
					itemCount: 10,
					itemSpacing: 8,
					preferredItemWidth: 316,
				} ).sizes.length
		);

		expect( counts ).toEqual( [ 2, 3, 4, 5, 6 ] );
	} );

	test( 'AndroidX centred hero does not use the shared Figma Tablet profile', () => {
		/*
		 * Figma's Tablet Hero, Multi-browse and Center-aligned Hero frames are
		 * all 184, 184, 120 and 56. Hero cannot produce a medium at all, and a
		 * centred hero is symmetric. The kit therefore converges its Tablet
		 * variants to Multi-browse; that is not AndroidX Hero runtime geometry.
		 */
		for ( const preferredItemWidth of [ 186, 252, 440 ] ) {
			const sizes = centeredHeroStrategy( {
				availableSpace: 568,
				itemCount: 10,
				itemSpacing: 8,
				preferredItemWidth,
			} ).sizes;

			expect( sizes[ 0 ] ).toBe( sizes[ sizes.length - 1 ] );
			expect( sizes ).not.toEqual( [ 184, 184, 120, 56 ] );
		}
	} );

	test( 'AndroidX hero has no medium item at any width', () => {
		/*
		 * The Figma Tablet Hero frame is 184, 184, 120 and 56, and the 120 is a
		 * medium. Hero's arrangement is searched with mediumCounts of [0], so the
		 * shared Figma Tablet profile cannot be produced by the AndroidX Hero
		 * strategy, regardless of why the kit converges those variants.
		 */
		for ( const preferredItemWidth of [ undefined, 120, 186, 316 ] ) {
			const strategy = heroStrategy( {
				availableSpace: 568,
				itemCount: 10,
				itemSpacing: 8,
				preferredItemWidth,
			} );

			expect( strategy.arrangement.mediumCount ).toBe( 0 );
		}
	} );

	test( 'uncontained reports a stride while the advanced layouts report a box', () => {
		const itemSpacing = 8;
		const itemWidth = 186;

		/*
		 * Uncontained folds the spacing into largeSize, so itemMainAxisSize is
		 * the distance from one item to the next and the DOM box is one gap
		 * smaller. A renderer that writes itemMainAxisSize as the item width
		 * widens every uncontained item by a gap, which is why the two layouts
		 * cannot share one line here.
		 */
		const uncontained = uncontainedStrategy( {
			availableSpace: 600,
			itemSpacing,
			itemWidth,
		} );

		expect( uncontained.itemMainAxisSize ).toBe( itemWidth + itemSpacing );
		expect( uncontained.itemMainAxisSize - itemSpacing ).toBe( itemWidth );

		/* The advanced strategies size the box itself: nothing is folded in. */
		const multiBrowse = multiBrowseStrategy( {
			availableSpace: 600,
			itemCount: 10,
			itemSpacing,
			preferredItemWidth: itemWidth,
		} );
		const focal = multiBrowse.defaultKeylines.find(
			( keyline ) => keyline.isFocal
		);

		expect( focal.size ).toBe( multiBrowse.itemMainAxisSize );
	} );

	test( 'places the AndroidX 125px uncontained cutoff item at 393.75px', () => {
		const strategy = uncontainedStrategy( {
			availableSpace: 400,
			itemSpacing: 0,
			itemWidth: 125,
		} );

		expect( strategy.keylines.at( -2 ).offset ).toBeCloseTo( 393.75, 5 );
	} );

	test.each( [
		[ 180, 6, 100, 'center' ],
		[ 120, 1, 120, 'start' ],
	] )(
		'matches AndroidX centered-hero geometry at %dpx with %d items',
		( availableSpace, itemCount, largeSize, alignment ) => {
			const strategy = centeredHeroStrategy( {
				availableSpace,
				itemCount,
				itemSpacing: 0,
			} );

			expect( strategy.arrangement.largeSize ).toBeCloseTo(
				largeSize,
				5
			);
			expect( strategy.alignment ).toBe( alignment );
		}
	);

	test( 'start-aligns a two-item centered hero with a large focal item', () => {
		const strategy = centeredHeroStrategy( {
			availableSpace: 180,
			itemCount: 2,
			itemSpacing: 0,
		} );

		expect( strategy.arrangement.largeSize ).toBeGreaterThan( 100 );
		expect( strategy.alignment ).toBe( 'start' );
	} );

	test( 'centered hero centers only when at least three items can fill its arrangement', () => {
		const one = centeredHeroStrategy( {
			availableSpace: 412,
			itemCount: 1,
			itemSpacing: 8,
		} );
		const two = centeredHeroStrategy( {
			availableSpace: 412,
			itemCount: 2,
			itemSpacing: 8,
		} );
		const ten = centeredHeroStrategy( {
			availableSpace: 412,
			itemCount: 10,
			itemSpacing: 8,
		} );

		expect( one.alignment ).toBe( 'start' );
		expect( one.sizes ).toHaveLength( 1 );
		expect( two.alignment ).toBe( 'start' );
		expect( ten.alignment ).toBe( 'center' );
		expect(
			ten.keylines.find( ( keyline ) => keyline.isFocal ).offset
		).toBeCloseTo( 206, 5 );
		expect( ten.sizes[ 0 ] ).toBeCloseTo(
			ten.sizes[ ten.sizes.length - 1 ],
			5
		);
	} );
} );
