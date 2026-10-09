import {
	CAROUSEL_MAX_SMALL_ITEM_SIZE,
	CAROUSEL_MIN_SMALL_ITEM_SIZE,
	centeredHeroStrategy,
	createCarouselKeylines,
	createCarouselStrategy,
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
