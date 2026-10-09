/*
 * Adapted from AndroidX Material 3 Carousel (Apache-2.0).
 * Source: androidx/androidx commit a0c645de83b353f2aa79c3b33a11e8ba853ca1e4,
 * Keylines.kt and Arrangement.kt.
 * Retrieved 2026-10-09. This module keeps only the platform-neutral sizing
 * strategy; DOM measurement, scrolling and rendering remain Axismundi code.
 */

export const CAROUSEL_MIN_SMALL_ITEM_SIZE = 40;
export const CAROUSEL_MAX_SMALL_ITEM_SIZE = 56;

const ANCHOR_SIZE = 10;
const MEDIUM_ITEM_FLEX_PERCENTAGE = 0.1;
const MEDIUM_LARGE_ITEM_DIFF_THRESHOLD = 0.85;

function clamp( value, minimum, maximum ) {
	return Math.min( maximum, Math.max( minimum, value ) );
}

function arrangementIsValid( arrangement ) {
	const {
		largeCount,
		largeSize,
		mediumCount,
		mediumSize,
		smallCount,
		smallSize,
	} = arrangement;
	if ( largeCount && mediumCount && smallCount ) {
		return largeSize > mediumSize && mediumSize > smallSize;
	}
	if ( largeCount && smallCount ) {
		return largeSize > smallSize;
	}
	return true;
}

function arrangementCost( arrangement, targetLargeSize ) {
	return arrangementIsValid( arrangement )
		? Math.abs( targetLargeSize - arrangement.largeSize ) *
				arrangement.priority
		: Number.POSITIVE_INFINITY;
}

function calculateLargeSize(
	availableSpace,
	smallCount,
	smallSize,
	mediumCount,
	largeCount
) {
	return (
		( availableSpace - ( smallCount + mediumCount / 2 ) * smallSize ) /
		( largeCount + mediumCount / 2 )
	);
}

function fitArrangement( {
	availableSpace,
	itemSpacing,
	largeCount,
	largeSize,
	mediumCount,
	mediumSize,
	minSmallSize,
	maxSmallSize,
	priority,
	smallCount,
	smallSize,
} ) {
	const itemCount = largeCount + mediumCount + smallCount;
	const spaceWithoutSpacing =
		availableSpace - Math.max( 0, itemCount - 1 ) * itemSpacing;
	let fittedSmallSize = clamp( smallSize, minSmallSize, maxSmallSize );
	let fittedLargeSize = largeSize;
	let fittedMediumSize = mediumSize;
	const occupied =
		fittedLargeSize * largeCount +
		fittedMediumSize * mediumCount +
		fittedSmallSize * smallCount;
	const delta = spaceWithoutSpacing - occupied;

	if ( smallCount && 0 < delta ) {
		fittedSmallSize += Math.min(
			delta / smallCount,
			maxSmallSize - fittedSmallSize
		);
	} else if ( smallCount && 0 > delta ) {
		fittedSmallSize += Math.max(
			delta / smallCount,
			minSmallSize - fittedSmallSize
		);
	}

	if ( ! smallCount ) {
		fittedSmallSize = 0;
	}
	fittedLargeSize = calculateLargeSize(
		spaceWithoutSpacing,
		smallCount,
		fittedSmallSize,
		mediumCount,
		largeCount
	);
	fittedMediumSize = ( fittedLargeSize + fittedSmallSize ) / 2;

	if ( mediumCount && fittedLargeSize !== largeSize ) {
		const targetAdjustment = ( largeSize - fittedLargeSize ) * largeCount;
		const availableMediumFlex =
			fittedMediumSize * MEDIUM_ITEM_FLEX_PERCENTAGE * mediumCount;
		const distributed = Math.min(
			Math.abs( targetAdjustment ),
			availableMediumFlex
		);
		if ( 0 < targetAdjustment ) {
			fittedMediumSize -= distributed / mediumCount;
			fittedLargeSize += distributed / largeCount;
		} else {
			fittedMediumSize += distributed / mediumCount;
			fittedLargeSize -= distributed / largeCount;
		}
	}

	return {
		priority,
		smallSize: fittedSmallSize,
		smallCount,
		mediumSize: fittedMediumSize,
		mediumCount,
		largeSize: fittedLargeSize,
		largeCount,
	};
}

function findLowestCostArrangement( {
	availableSpace,
	itemSpacing,
	largeCounts,
	maxSmallSize,
	mediumCounts,
	minSmallSize,
	smallCounts,
	targetLargeSize,
	targetMediumSize,
	targetSmallSize,
} ) {
	let best = null;
	let priority = 1;
	for ( const largeCount of largeCounts ) {
		for ( const mediumCount of mediumCounts ) {
			for ( const smallCount of smallCounts ) {
				const candidate = fitArrangement( {
					availableSpace,
					itemSpacing,
					largeCount,
					largeSize: targetLargeSize,
					mediumCount,
					mediumSize: targetMediumSize,
					minSmallSize,
					maxSmallSize,
					priority,
					smallCount,
					smallSize: targetSmallSize,
				} );
				if (
					! best ||
					arrangementCost( candidate, targetLargeSize ) <
						arrangementCost( best, targetLargeSize )
				) {
					best = candidate;
					if ( 0 === arrangementCost( best, targetLargeSize ) ) {
						return best;
					}
				}
				priority += 1;
			}
		}
	}
	return best;
}

function arrangementItemCount( arrangement ) {
	return (
		arrangement.largeCount +
		arrangement.mediumCount +
		arrangement.smallCount
	);
}

function arrangementSizes( arrangement, centered = false ) {
	const large = Array( arrangement.largeCount ).fill( arrangement.largeSize );
	const medium = Array( arrangement.mediumCount ).fill(
		arrangement.mediumSize
	);
	const small = Array( arrangement.smallCount ).fill( arrangement.smallSize );
	if ( ! centered ) {
		return [ ...large, ...medium, ...small ];
	}
	return [
		...small.slice( 0, small.length / 2 ),
		...medium.slice( 0, medium.length / 2 ),
		...large,
		...medium.slice( medium.length / 2 ),
		...small.slice( small.length / 2 ),
	];
}

export function createCarouselKeylines( {
	alignment,
	availableSpace,
	itemSpacing,
	leftAnchorSize,
	rightAnchorSize,
	sizes,
} ) {
	if ( ! sizes.length ) {
		return [];
	}
	const temporary = [
		{ size: leftAnchorSize, isAnchor: true },
		...sizes.map( ( size ) => ( { size, isAnchor: false } ) ),
		{ size: rightAnchorSize, isAnchor: true },
	];
	const focalSize = Math.max( ...sizes );
	const firstFocalIndex = temporary.findIndex(
		( keyline ) => ! keyline.isAnchor && keyline.size === focalSize
	);
	let lastFocalIndex = firstFocalIndex;
	while (
		lastFocalIndex < temporary.length - 1 &&
		temporary[ lastFocalIndex + 1 ].size === focalSize
	) {
		lastFocalIndex += 1;
	}
	const focalCount = lastFocalIndex - firstFocalIndex;
	const pivotIndex = firstFocalIndex;
	let pivotOffset = focalSize / 2;
	if ( 'center' === alignment ) {
		const spacingSplit =
			0 === itemSpacing || 0 === focalCount % 2 ? 0 : itemSpacing / 2;
		const spacingBeforePivot = Math.floor( focalCount / 2 ) * itemSpacing;
		pivotOffset =
			availableSpace / 2 -
			( focalSize / 2 ) * focalCount -
			spacingSplit -
			spacingBeforePivot;
	}
	return createKeylinesWithPivot( {
		availableSpace,
		itemSpacing,
		items: temporary,
		pivotIndex,
		pivotOffset,
	} );
}

function cutoffFor( size, offset, availableSpace ) {
	const start = offset - size / 2;
	const end = offset + size / 2;
	if ( 0 > start && 0 < end ) {
		return Math.abs( start );
	}
	if ( availableSpace < end && availableSpace > start ) {
		return end - availableSpace;
	}
	return 0;
}

function createKeylinesWithPivot( {
	availableSpace,
	itemSpacing,
	items,
	pivotIndex,
	pivotOffset,
} ) {
	if ( ! items.length || 0 > pivotIndex || pivotIndex >= items.length ) {
		return [];
	}
	const focalSize = Math.max(
		...items
			.filter( ( item ) => ! item.isAnchor )
			.map( ( item ) => item.size )
	);
	const firstFocalIndex = items.findIndex(
		( item ) => ! item.isAnchor && item.size === focalSize
	);
	let lastFocalIndex = firstFocalIndex;
	while (
		lastFocalIndex < items.length - 1 &&
		items[ lastFocalIndex + 1 ].size === focalSize
	) {
		lastFocalIndex += 1;
	}

	const keylines = Array( items.length );
	keylines[ pivotIndex ] = {
		size: items[ pivotIndex ].size,
		offset: pivotOffset,
		unadjustedOffset: pivotOffset,
		isAnchor: items[ pivotIndex ].isAnchor,
		isFocal: pivotIndex >= firstFocalIndex && pivotIndex <= lastFocalIndex,
		isPivot: true,
		cutoff: cutoffFor(
			items[ pivotIndex ].size,
			pivotOffset,
			availableSpace
		),
	};
	let offset = pivotOffset - focalSize / 2 - itemSpacing;
	let unadjustedOffset = offset;
	for ( let index = pivotIndex - 1; 0 <= index; index -= 1 ) {
		const keyline = items[ index ];
		const keylineOffset = offset - keyline.size / 2;
		const keylineUnadjustedOffset = unadjustedOffset - focalSize / 2;
		keylines[ index ] = {
			size: keyline.size,
			offset: keylineOffset,
			unadjustedOffset: keylineUnadjustedOffset,
			isAnchor: keyline.isAnchor,
			isFocal: index >= firstFocalIndex && index <= lastFocalIndex,
			isPivot: false,
			cutoff: cutoffFor( keyline.size, keylineOffset, availableSpace ),
		};
		offset -= keyline.size + itemSpacing;
		unadjustedOffset -= focalSize + itemSpacing;
	}
	offset = pivotOffset + focalSize / 2 + itemSpacing;
	unadjustedOffset = offset;
	for ( let index = pivotIndex + 1; index < items.length; index += 1 ) {
		const keyline = items[ index ];
		const keylineOffset = offset + keyline.size / 2;
		const keylineUnadjustedOffset = unadjustedOffset + focalSize / 2;
		keylines[ index ] = {
			size: keyline.size,
			offset: keylineOffset,
			unadjustedOffset: keylineUnadjustedOffset,
			isAnchor: keyline.isAnchor,
			isFocal: index >= firstFocalIndex && index <= lastFocalIndex,
			isPivot: false,
			cutoff: cutoffFor( keyline.size, keylineOffset, availableSpace ),
		};
		offset += keyline.size + itemSpacing;
		unadjustedOffset += focalSize + itemSpacing;
	}
	return keylines;
}

function firstIndex( keylines, predicate ) {
	return keylines.findIndex( predicate );
}

function lastIndex( keylines, predicate ) {
	return keylines.findLastIndex( predicate );
}

function moveKeyline( keylines, sourceIndex, destinationIndex ) {
	const moved = keylines.map( ( keyline ) => ( {
		size: keyline.size,
		isAnchor: keyline.isAnchor,
	} ) );
	const [ item ] = moved.splice( sourceIndex, 1 );
	moved.splice( destinationIndex, 0, item );
	return moved;
}

function moveAndCreateShiftedKeylines( {
	availableSpace,
	from,
	itemSpacing,
	sourceIndex,
	destinationIndex,
} ) {
	const pivotIndex = firstIndex( from, ( keyline ) => keyline.isPivot );
	const pivotDirection = sourceIndex > destinationIndex ? 1 : -1;
	const pivotDelta =
		( from[ sourceIndex ].size -
			from[ sourceIndex ].cutoff +
			itemSpacing ) *
		pivotDirection;
	return createKeylinesWithPivot( {
		availableSpace,
		itemSpacing,
		items: moveKeyline( from, sourceIndex, destinationIndex ),
		pivotIndex: pivotIndex + pivotDirection,
		pivotOffset: from[ pivotIndex ].offset + pivotDelta,
	} );
}

function firstIndexAfterFocalRangeWithSize( keylines, size ) {
	const lastFocalIndex = lastIndex(
		keylines,
		( keyline ) => keyline.isFocal
	);
	for ( let index = lastFocalIndex; index < keylines.length; index += 1 ) {
		if ( keylines[ index ].size === size ) {
			return index;
		}
	}
	return keylines.length - 1;
}

function lastIndexBeforeFocalRangeWithSize( keylines, size ) {
	const firstFocalIndex = firstIndex(
		keylines,
		( keyline ) => keyline.isFocal
	);
	for ( let index = firstFocalIndex - 1; 0 <= index; index -= 1 ) {
		if ( keylines[ index ].size === size ) {
			return index;
		}
	}
	return 0;
}

function createStartKeylineSteps(
	defaultKeylines,
	availableSpace,
	itemSpacing
) {
	if ( ! defaultKeylines.length ) {
		return [];
	}
	const steps = [ defaultKeylines ];
	const firstNonAnchorIndex = firstIndex(
		defaultKeylines,
		( keyline ) => ! keyline.isAnchor
	);
	const firstFocalIndex = firstIndex(
		defaultKeylines,
		( keyline ) => keyline.isFocal
	);
	const firstFocal = defaultKeylines[ firstFocalIndex ];
	if (
		firstFocalIndex === firstNonAnchorIndex &&
		0 <= firstFocal.offset - firstFocal.size / 2
	) {
		return steps;
	}
	const stepCount = firstFocalIndex - firstNonAnchorIndex;
	for ( let index = 0; index < stepCount; index += 1 ) {
		const previous = steps.at( -1 );
		const originalIndex = firstNonAnchorIndex + index;
		let destinationIndex = defaultKeylines.length - 1;
		if ( 0 < originalIndex ) {
			destinationIndex =
				firstIndexAfterFocalRangeWithSize(
					previous,
					defaultKeylines[ originalIndex - 1 ].size
				) - 1;
		}
		steps.push(
			moveAndCreateShiftedKeylines( {
				availableSpace,
				from: previous,
				itemSpacing,
				sourceIndex: firstNonAnchorIndex,
				destinationIndex,
			} )
		);
	}
	return steps;
}

function createEndKeylineSteps( defaultKeylines, availableSpace, itemSpacing ) {
	if ( ! defaultKeylines.length ) {
		return [];
	}
	const steps = [ defaultKeylines ];
	const lastNonAnchorIndex = lastIndex(
		defaultKeylines,
		( keyline ) => ! keyline.isAnchor
	);
	const lastFocalIndex = lastIndex(
		defaultKeylines,
		( keyline ) => keyline.isFocal
	);
	const lastFocal = defaultKeylines[ lastFocalIndex ];
	if (
		lastFocalIndex === lastNonAnchorIndex &&
		lastFocal.offset + lastFocal.size / 2 <= availableSpace
	) {
		return steps;
	}
	const stepCount = lastNonAnchorIndex - lastFocalIndex;
	for ( let index = 0; index < stepCount; index += 1 ) {
		const previous = steps.at( -1 );
		const originalIndex = lastNonAnchorIndex - index;
		let destinationIndex = 0;
		if ( originalIndex < defaultKeylines.length - 1 ) {
			destinationIndex =
				lastIndexBeforeFocalRangeWithSize(
					previous,
					defaultKeylines[ originalIndex + 1 ].size
				) + 1;
		}
		steps.push(
			moveAndCreateShiftedKeylines( {
				availableSpace,
				from: previous,
				itemSpacing,
				sourceIndex: lastNonAnchorIndex,
				destinationIndex,
			} )
		);
	}
	return steps;
}

function lerp( start, end, fraction ) {
	return start + ( end - start ) * fraction;
}

function lerpKeylines( from, to, fraction ) {
	return from.map( ( keyline, index ) => ( {
		size: lerp( keyline.size, to[ index ].size, fraction ),
		offset: lerp( keyline.offset, to[ index ].offset, fraction ),
		unadjustedOffset: lerp(
			keyline.unadjustedOffset,
			to[ index ].unadjustedOffset,
			fraction
		),
		isFocal: 0.5 > fraction ? keyline.isFocal : to[ index ].isFocal,
		isAnchor: 0.5 > fraction ? keyline.isAnchor : to[ index ].isAnchor,
		isPivot: 0.5 > fraction ? keyline.isPivot : to[ index ].isPivot,
		cutoff: lerp( keyline.cutoff, to[ index ].cutoff, fraction ),
	} ) );
}

function shiftDistance( steps, fromStart ) {
	if ( ! steps.length ) {
		return 0;
	}
	return fromStart
		? steps.at( -1 )[ 0 ].unadjustedOffset -
				steps[ 0 ][ 0 ].unadjustedOffset
		: steps[ 0 ].at( -1 ).unadjustedOffset -
				steps.at( -1 ).at( -1 ).unadjustedOffset;
}

function stepInterpolationPoints( totalDistance, steps, fromStart ) {
	const points = [ 0 ];
	if ( 0 === totalDistance || ! steps.length ) {
		return points;
	}
	for ( let index = 1; index < steps.length; index += 1 ) {
		const previous = steps[ index - 1 ];
		const current = steps[ index ];
		const distance = fromStart
			? current[ 0 ].unadjustedOffset - previous[ 0 ].unadjustedOffset
			: previous.at( -1 ).unadjustedOffset -
				current.at( -1 ).unadjustedOffset;
		points.push(
			index === steps.length - 1
				? 1
				: points[ index - 1 ] + distance / totalDistance
		);
	}
	return points;
}

function interpolateSteps( steps, points, interpolation ) {
	if ( 1 === steps.length ) {
		return steps[ 0 ];
	}
	let lower = points[ 0 ];
	for ( let index = 1; index < steps.length; index += 1 ) {
		const upper = points[ index ];
		if ( interpolation <= upper ) {
			const progress =
				upper === lower
					? 0
					: ( interpolation - lower ) / ( upper - lower );
			return lerpKeylines( steps[ index - 1 ], steps[ index ], progress );
		}
		lower = upper;
	}
	return steps.at( -1 );
}

export function createCarouselStrategy( {
	availableSpace,
	defaultKeylines,
	itemSpacing = 0,
} ) {
	const startKeylineSteps = createStartKeylineSteps(
		defaultKeylines,
		availableSpace,
		itemSpacing
	);
	const endKeylineSteps = createEndKeylineSteps(
		defaultKeylines,
		availableSpace,
		itemSpacing
	);
	const startShiftDistance = shiftDistance( startKeylineSteps, true );
	const endShiftDistance = shiftDistance( endKeylineSteps, false );
	return {
		defaultKeylines,
		startKeylineSteps,
		endKeylineSteps,
		startShiftDistance,
		endShiftDistance,
		startShiftPoints: stepInterpolationPoints(
			startShiftDistance,
			startKeylineSteps,
			true
		),
		endShiftPoints: stepInterpolationPoints(
			endShiftDistance,
			endKeylineSteps,
			false
		),
		itemSpacing,
	};
}

export function keylinesForScrollOffset(
	strategy,
	scrollOffset,
	maxScrollOffset
) {
	const positiveOffset = Math.max( 0, scrollOffset );
	const startBoundary = strategy.startShiftDistance;
	const endBoundary = Math.max(
		0,
		maxScrollOffset - strategy.endShiftDistance
	);
	if ( positiveOffset >= startBoundary && positiveOffset <= endBoundary ) {
		return strategy.defaultKeylines;
	}

	if ( positiveOffset > endBoundary ) {
		const interpolation =
			maxScrollOffset === endBoundary
				? 1
				: clamp(
						( positiveOffset - endBoundary ) /
							( maxScrollOffset - endBoundary ),
						0,
						1
					);
		if (
			0.01 > endBoundary &&
			2 === strategy.startKeylineSteps.length &&
			2 === strategy.endKeylineSteps.length
		) {
			return lerpKeylines(
				strategy.startKeylineSteps.at( -1 ),
				strategy.endKeylineSteps.at( -1 ),
				interpolation
			);
		}
		return interpolateSteps(
			strategy.endKeylineSteps,
			strategy.endShiftPoints,
			interpolation
		);
	}

	const interpolation =
		0 === startBoundary
			? 1
			: clamp( 1 - positiveOffset / startBoundary, 0, 1 );
	return interpolateSteps(
		strategy.startKeylineSteps,
		strategy.startShiftPoints,
		interpolation
	);
}

export function maximumScrollOffset( {
	availableSpace,
	itemCount,
	itemMainAxisSize,
	itemSpacing = 0,
} ) {
	return Math.max(
		0,
		itemMainAxisSize * itemCount +
			Math.max( 0, itemCount - 1 ) * itemSpacing -
			availableSpace
	);
}

export function snapPositionOffset( strategy, itemIndex, itemCount ) {
	const firstFocalIndex = firstIndex(
		strategy.defaultKeylines,
		( keyline ) => keyline.isFocal
	);
	const lastFocalIndex = lastIndex(
		strategy.defaultKeylines,
		( keyline ) => keyline.isFocal
	);
	const itemSize = strategy.defaultKeylines[ firstFocalIndex ]?.size || 0;
	let offset = Math.round(
		strategy.defaultKeylines[ firstFocalIndex ].unadjustedOffset -
			itemSize / 2
	);

	if ( itemIndex <= strategy.startKeylineSteps.length - 1 ) {
		const stepIndex = clamp(
			strategy.startKeylineSteps.length - 1 - itemIndex,
			0,
			strategy.startKeylineSteps.length - 1
		);
		const step = strategy.startKeylineSteps[ stepIndex ];
		const focal = step.find( ( keyline ) => keyline.isFocal );
		offset = Math.round( focal.unadjustedOffset - itemSize / 2 );
	}

	const lastItemIndex = itemCount - 1;
	const focalCount = lastFocalIndex - firstFocalIndex + 1;
	if (
		itemIndex >= lastItemIndex - ( strategy.endKeylineSteps.length - 1 ) &&
		itemCount > focalCount
	) {
		const stepIndex = clamp(
			strategy.endKeylineSteps.length - 1 - ( lastItemIndex - itemIndex ),
			0,
			strategy.endKeylineSteps.length - 1
		);
		const step = strategy.endKeylineSteps[ stepIndex ];
		const focal = [ ...step ]
			.reverse()
			.find( ( keyline ) => keyline.isFocal );
		offset = Math.round( focal.unadjustedOffset - itemSize / 2 );
	}
	return offset;
}

export function scrollOffsetForItem( strategy, itemIndex, itemCount ) {
	const itemSize = strategy.defaultKeylines.find(
		( keyline ) => keyline.isFocal
	)?.size;
	return (
		itemIndex * ( itemSize + strategy.itemSpacing ) -
		snapPositionOffset( strategy, itemIndex, itemCount )
	);
}

function strategyResult( type, arrangement, options = {} ) {
	if ( ! arrangement ) {
		return {
			type,
			alignment: options.centered ? 'center' : 'start',
			anchorSize: ANCHOR_SIZE,
			arrangement: null,
			itemMainAxisSize: 0,
			keylines: [],
			sizes: [],
		};
	}
	const alignment = options.centered ? 'center' : 'start';
	const sizes = arrangementSizes( arrangement, options.centered );
	const leftAnchorSize = options.leftAnchorSize ?? ANCHOR_SIZE;
	const rightAnchorSize = options.rightAnchorSize ?? ANCHOR_SIZE;
	const keylines = createCarouselKeylines( {
		alignment,
		availableSpace: options.availableSpace,
		itemSpacing: options.itemSpacing,
		leftAnchorSize,
		rightAnchorSize,
		sizes,
	} );
	const strategy = createCarouselStrategy( {
		availableSpace: options.availableSpace,
		defaultKeylines: keylines,
		itemSpacing: options.itemSpacing,
	} );
	return {
		type,
		alignment,
		anchorSize: rightAnchorSize,
		arrangement,
		itemMainAxisSize: arrangement.largeSize,
		keylines,
		...strategy,
		sizes,
	};
}

export function multiBrowseStrategy( {
	availableSpace,
	itemCount,
	itemSpacing = 0,
	maxSmallItemSize = CAROUSEL_MAX_SMALL_ITEM_SIZE,
	minSmallItemSize = CAROUSEL_MIN_SMALL_ITEM_SIZE,
	preferredItemWidth,
} ) {
	if ( 0 >= availableSpace || 0 >= preferredItemWidth || 0 >= itemCount ) {
		return strategyResult( 'multi-browse', null );
	}
	let smallCounts = [ 1 ];
	const mediumCounts = [ 1, 0 ];
	const targetLargeSize = Math.min( preferredItemWidth, availableSpace );
	const targetSmallSize = clamp(
		targetLargeSize / 3,
		minSmallItemSize,
		maxSmallItemSize
	);
	const targetMediumSize = ( targetLargeSize + targetSmallSize ) / 2;
	if ( availableSpace < minSmallItemSize * 2 ) {
		smallCounts = [ 0 ];
	}
	const minimumLargeSpace =
		availableSpace -
		targetMediumSize * Math.max( ...mediumCounts ) -
		maxSmallItemSize * Math.max( ...smallCounts );
	const minimumLargeCount = Math.max(
		1,
		Math.floor( minimumLargeSpace / targetLargeSize )
	);
	const maximumLargeCount = Math.ceil( availableSpace / targetLargeSize );
	const largeCounts = Array.from(
		{ length: maximumLargeCount - minimumLargeCount + 1 },
		( _, index ) => maximumLargeCount - index
	);
	const inputs = {
		availableSpace,
		itemSpacing,
		targetSmallSize,
		minSmallSize: minSmallItemSize,
		maxSmallSize: maxSmallItemSize,
		smallCounts,
		targetMediumSize,
		mediumCounts,
		targetLargeSize,
		largeCounts,
	};
	let arrangement = findLowestCostArrangement( inputs );

	if ( arrangement && arrangementItemCount( arrangement ) > itemCount ) {
		let surplus = arrangementItemCount( arrangement ) - itemCount;
		let smallCount = arrangement.smallCount;
		let mediumCount = arrangement.mediumCount;
		while ( 0 < surplus ) {
			if ( 0 < smallCount ) {
				smallCount -= 1;
			} else if ( 1 < mediumCount ) {
				mediumCount -= 1;
			}
			surplus -= 1;
		}
		arrangement = findLowestCostArrangement( {
			...inputs,
			smallCounts: [ smallCount ],
			mediumCounts: [ mediumCount ],
		} );
	}

	return strategyResult( 'multi-browse', arrangement, {
		availableSpace,
		itemSpacing,
	} );
}

function calculateMediumChildSize(
	minimumMediumSize,
	largeItemSize,
	remainingSpace
) {
	let mediumSize = Math.max( remainingSpace * 1.5, minimumMediumSize );
	const threshold = largeItemSize * MEDIUM_LARGE_ITEM_DIFF_THRESHOLD;
	if ( mediumSize > threshold ) {
		mediumSize = Math.min(
			Math.max( threshold, remainingSpace * 1.2 ),
			largeItemSize
		);
	}
	return mediumSize;
}

export function uncontainedStrategy( {
	availableSpace,
	itemSpacing = 0,
	itemWidth,
} ) {
	if ( 0 >= availableSpace || 0 >= itemWidth ) {
		return strategyResult( 'uncontained', null );
	}
	const largeItemSize = Math.min( itemWidth + itemSpacing, availableSpace );
	const largeCount = Math.max(
		1,
		Math.floor( availableSpace / largeItemSize )
	);
	const remainingSpace = availableSpace - largeCount * largeItemSize;
	const mediumCount = 0 < remainingSpace ? 1 : 0;
	const mediumSize = calculateMediumChildSize(
		ANCHOR_SIZE,
		largeItemSize,
		remainingSpace
	);
	const arrangement = {
		priority: 0,
		smallSize: 0,
		smallCount: 0,
		mediumSize,
		mediumCount,
		largeSize: largeItemSize,
		largeCount,
	};
	return strategyResult( 'uncontained', arrangement, {
		availableSpace,
		itemSpacing,
		leftAnchorSize: Math.max(
			Math.min( ANCHOR_SIZE, itemWidth ),
			mediumSize / 2
		),
		rightAnchorSize: ANCHOR_SIZE,
	} );
}

export function heroStrategy( {
	availableSpace,
	centered = false,
	itemCount,
	itemSpacing = 0,
	preferredItemWidth,
	maxSmallItemSize = CAROUSEL_MAX_SMALL_ITEM_SIZE,
	minSmallItemSize = CAROUSEL_MIN_SMALL_ITEM_SIZE,
} ) {
	if ( 0 >= availableSpace || 0 >= itemCount ) {
		return strategyResult( centered ? 'centered-hero' : 'hero', null, {
			centered,
		} );
	}
	const shouldCenter = centered && 3 <= itemCount;
	let smallCounts = [ 1 ];
	if ( 1 >= itemCount ) {
		smallCounts = [ 0 ];
	} else if ( shouldCenter ) {
		smallCounts = [ 2 ];
	}
	const targetLargeSize = Math.min(
		preferredItemWidth ?? availableSpace,
		availableSpace
	);
	const targetSmallSize = clamp(
		targetLargeSize / 3,
		minSmallItemSize,
		maxSmallItemSize
	);
	const fullScreenThreshold =
		minSmallItemSize * Math.max( ...smallCounts ) + minSmallItemSize * 1.25;
	if ( availableSpace < fullScreenThreshold ) {
		smallCounts = [ 0 ];
	}
	const minimumLargeSpace =
		availableSpace - minSmallItemSize * Math.max( ...smallCounts );
	const minimumLargeCount = Math.max(
		1,
		Math.floor( minimumLargeSpace / targetLargeSize )
	);
	const maximumLargeCount = Math.ceil( availableSpace / targetLargeSize );
	const largeCounts = Array.from(
		{ length: maximumLargeCount - minimumLargeCount + 1 },
		( _, index ) => maximumLargeCount - index
	);
	const arrangement = findLowestCostArrangement( {
		availableSpace,
		itemSpacing,
		targetSmallSize,
		minSmallSize: minSmallItemSize,
		maxSmallSize: maxSmallItemSize,
		smallCounts,
		targetMediumSize: 0,
		mediumCounts: [ 0 ],
		targetLargeSize,
		largeCounts,
	} );
	const isCentered = Boolean(
		shouldCenter &&
		arrangement &&
		itemCount >= arrangementItemCount( arrangement )
	);
	return strategyResult( isCentered ? 'centered-hero' : 'hero', arrangement, {
		availableSpace,
		centered: isCentered,
		itemSpacing,
	} );
}

export function centeredHeroStrategy( options ) {
	return heroStrategy( { ...options, centered: true } );
}
