/**
 * Material 3 Carousel semantics and keyboard contract.
 *
 * Uncontained uses consumer-owned width. Multi-browse and start-aligned Hero
 * use the measured Community Kit profiles at their reference container sizes;
 * wider containers distribute additional space among large items. This is a
 * local keyline policy, not a claim that M3 publishes the algorithm.
 *
 * M3 calls the container role "container", which is not an ARIA role. The WAI
 * ARIA Authoring Practices carousel pattern permits `region` or `group`; this
 * component uses `group` because Home alone contains four carousels and turning
 * each one into a landmark would make landmark navigation noisy. A product
 * surface that truly warrants a landmark should put the named region around
 * this component rather than changing the shared primitive's role.
 *
 * APG's slide-show example and M3 are not the same widget. APG moves between
 * hidden slides with previous and next buttons. M3 requires every item to be
 * directly reachable, prohibits controls in or beside the container, and says
 * Tab or arrows move between items. Therefore all item actions remain in the
 * native Tab order; Left and Right add adjacent-item movement without roving
 * tabindex, while Up and Down move to the nearest focusable element outside the
 * carousel. Movement never wraps.
 *
 * `children` are direct CarouselItem elements so the container can provide the
 * current and total values that M3 requires in every item label. Wrapping an
 * item in another component would hide its position from this contract.
 *
 * @param {Object}                                 props                               Component props.
 * @param {string}                                 props.label                         Accessible name; do not include the word carousel.
 * @param {import('@wordpress/element').ReactNode} props.children                      Direct CarouselItem children.
 * @param {'uncontained'|'multi-browse'|'hero'}    [props.layout='uncontained']        Implemented layouts.
 * @param {'start'|'center'}                       [props.alignment='start']           Measured Hero alignment.
 * @param {'default'|'snap'}                       [props.scrollBehavior='default']    Scrolling behavior.
 * @param {string}                                 [props.roleDescription='carousel']  Localized role description.
 * @param {string}                                 [props.itemRoleDescription='slide'] Localized item role description.
 * @param {Function}                               [props.formatPosition]              Builds each item's position name.
 * @param {number}                                 [props.preferredItemWidth]          Target large-item width for advanced layouts.
 * @param {string}                                 [props.className]                   Additional component class name.
 * @return {import('@wordpress/element').ReactNode} Carousel group.
 */

import {
	Children,
	cloneElement,
	isValidElement,
	useEffect,
	useRef,
} from '@wordpress/element';
import warning from '@wordpress/warning';

import { CarouselItem } from './carousel-item';
import {
	heroStrategy,
	keylinesForScrollOffset,
	multiBrowseStrategy,
	scrollOffsetForItem,
	uncontainedStrategy,
} from './carousel-strategy';

const PAGE_FOCUSABLE = [
	'a[href]',
	'button:not(:disabled)',
	'input:not(:disabled)',
	'select:not(:disabled)',
	'textarea:not(:disabled)',
	'[tabindex]:not([tabindex="-1"])',
].join( ',' );

const DRAG_THRESHOLD = 6;
/*
 * Two different questions, and they used to share one list.
 *
 * Every layout is masked at the container edge. M3 says carousel items must be
 * fully visible on-screen "except for the uncontained layout", which is the one
 * layout whose items are allowed to be cut off; its uniform size is the slot,
 * not a claim that nothing is masked. Only multi-browse and hero are required
 * to snap -- uncontained is published with both default and snap scrolling, so
 * forcing it here would remove a choice the guidelines give the caller.
 */
const KEYLINE_LAYOUTS = [ 'uncontained', 'multi-browse', 'hero' ];
const SNAP_REQUIRED_LAYOUTS = [ 'multi-browse', 'hero' ];
const SMALL_WIDTH = 56;

function isAvailable( element ) {
	return (
		! element.closest( '[hidden], [inert]' ) &&
		0 < element.getClientRects().length
	);
}

function leaveCarousel( root, active, direction ) {
	const focusable = [
		...root.ownerDocument.querySelectorAll( PAGE_FOCUSABLE ),
	].filter( isAvailable );
	const from = focusable.indexOf( active );
	if ( 0 > from ) {
		return false;
	}

	for (
		let index = from + direction;
		0 <= index && index < focusable.length;
		index += direction
	) {
		if ( ! root.contains( focusable[ index ] ) ) {
			focusable[ index ].focus();
			return true;
		}
	}

	return false;
}

function clamp( value, minimum, maximum ) {
	return Math.min( maximum, Math.max( minimum, value ) );
}

function lerp( start, end, progress ) {
	return start + ( end - start ) * progress;
}

function itemGeometry( location, keylines, itemSize ) {
	const first = keylines[ 0 ];
	const last = keylines[ keylines.length - 1 ];
	if ( location <= first.unadjustedOffset ) {
		return {
			offset:
				first.offset +
				( location - first.unadjustedOffset ) *
					( first.size / itemSize ),
			size: first.size,
		};
	}
	if ( location >= last.unadjustedOffset ) {
		return {
			offset:
				last.offset +
				( location - last.unadjustedOffset ) * ( last.size / itemSize ),
			size: last.size,
		};
	}

	const rightIndex = keylines.findIndex(
		( keyline ) => keyline.unadjustedOffset >= location
	);
	const left = keylines[ rightIndex - 1 ];
	const right = keylines[ rightIndex ];
	const progress =
		0 === right.unadjustedOffset - left.unadjustedOffset
			? 0
			: ( location - left.unadjustedOffset ) /
				( right.unadjustedOffset - left.unadjustedOffset );
	return {
		offset: lerp( left.offset, right.offset, progress ),
		size: lerp( left.size, right.size, progress ),
	};
}

function sizeRole( size, itemSize ) {
	if ( size >= itemSize - 1 ) {
		return 'large';
	}
	if ( size <= SMALL_WIDTH + 1 ) {
		return 'small';
	}
	return 'medium';
}

export function Carousel( {
	label,
	children,
	layout = 'uncontained',
	alignment = 'start',
	scrollBehavior = 'default',
	roleDescription = 'carousel',
	itemRoleDescription = 'slide',
	formatPosition,
	preferredItemWidth,
	className,
	...props
} ) {
	const rootRef = useRef();
	const trackRef = useRef();
	const geometryRef = useRef();
	const childArray = Children.toArray( children );
	const items = childArray.filter(
		( child ) => isValidElement( child ) && CarouselItem === child.type
	);

	if ( ! label ) {
		warning(
			'Carousel: `label` is required. M3 requires the container to be named, and the name should describe its contents rather than repeat the word carousel.'
		);
	}
	if ( items.length !== childArray.length ) {
		warning(
			'Carousel: every direct child must be a CarouselItem so its position and set size can be announced.'
		);
	}
	const behavior = [ 'default', 'snap' ].includes( scrollBehavior )
		? scrollBehavior
		: 'default';
	if ( behavior !== scrollBehavior ) {
		warning(
			`Carousel: unknown scrollBehavior "${ scrollBehavior }"; using default.`
		);
	}
	const implementedLayout = [
		'uncontained',
		'multi-browse',
		'hero',
	].includes( layout )
		? layout
		: 'uncontained';
	if ( implementedLayout !== layout ) {
		warning(
			`Carousel: layout "${ layout }" is not implemented; using uncontained.`
		);
	}
	const implementedAlignment =
		'hero' === implementedLayout && 'center' === alignment
			? 'center'
			: 'start';
	if ( implementedAlignment !== alignment ) {
		warning(
			`Carousel: center alignment is implemented for Hero only; using start alignment for ${ implementedLayout }.`
		);
	}
	const effectiveBehavior = SNAP_REQUIRED_LAYOUTS.includes(
		implementedLayout
	)
		? 'snap'
		: behavior;
	if ( effectiveBehavior !== behavior ) {
		warning(
			`Carousel: ${ implementedLayout } requires snap scrolling; using snap.`
		);
	}
	let itemPosition = 0;

	useEffect( () => {
		const track = trackRef.current;
		if ( ! track ) {
			return undefined;
		}

		let drag;
		let suppressClick = false;
		let suppressionTimer;
		let settleFrame;
		let scrollEndTimer;

		function settleToNearestItem() {
			if ( 'snap' !== rootRef.current?.dataset.scrollBehavior ) {
				delete track.dataset.dragging;
				return;
			}

			const rtl = 'rtl' === window.getComputedStyle( track ).direction;
			const current = rtl ? -track.scrollLeft : track.scrollLeft;
			const maximum = Math.max(
				0,
				track.scrollWidth - track.clientWidth
			);
			const currentGeometry = geometryRef.current;
			/*
			 * The same split as the keyboard: the strategy's snap positions
			 * are generated from one width, so with strides that differ an
			 * item's own left edge is where a release should land. Leaving
			 * this on the strategy put a drag down between two items.
			 */
			const targets = currentGeometry
				? currentGeometry.items.map( ( _, index ) =>
						clamp(
							currentGeometry.uniform
								? scrollOffsetForItem(
										currentGeometry.strategy,
										index,
										currentGeometry.items.length
									)
								: currentGeometry.centers[ index ] -
										( currentGeometry.boxes[ index ] +
											currentGeometry.gap ) /
											2,
							0,
							maximum
						)
					)
				: [ current ];
			const target = targets.reduce(
				( nearest, candidate ) =>
					Math.abs( candidate - current ) <
					Math.abs( nearest - current )
						? candidate
						: nearest,
				targets[ 0 ] ?? current
			);

			delete track.dataset.dragging;
			settleFrame = window.requestAnimationFrame( () => {
				settleFrame = undefined;
				track.scrollTo( {
					behavior: window.matchMedia(
						'(prefers-reduced-motion: reduce)'
					).matches
						? 'auto'
						: 'smooth',
					left: rtl ? -target : target,
				} );
			} );
		}

		function startDrag( event ) {
			if (
				0 !== event.button ||
				'touch' === event.pointerType ||
				event.isPrimary === false
			) {
				return;
			}

			drag = {
				pointerId: event.pointerId,
				startX: event.clientX,
				startScrollLeft: track.scrollLeft,
				moved: false,
			};
			track.setPointerCapture( event.pointerId );
		}

		function moveDrag( event ) {
			if ( ! drag || drag.pointerId !== event.pointerId ) {
				return;
			}

			const distance = event.clientX - drag.startX;
			if ( ! drag.moved && DRAG_THRESHOLD > Math.abs( distance ) ) {
				return;
			}

			drag.moved = true;
			suppressClick = true;
			track.dataset.dragging = '';
			const rtl = 'rtl' === window.getComputedStyle( track ).direction;
			track.scrollLeft =
				drag.startScrollLeft + ( rtl ? distance : -distance );
			event.preventDefault();
		}

		function endDrag( event ) {
			if ( ! drag || drag.pointerId !== event.pointerId ) {
				return;
			}

			if ( track.hasPointerCapture( event.pointerId ) ) {
				track.releasePointerCapture( event.pointerId );
			}
			const moved = drag.moved;
			drag = undefined;
			if ( moved ) {
				settleToNearestItem();
			} else {
				delete track.dataset.dragging;
			}
			if ( suppressClick ) {
				suppressionTimer = track.ownerDocument.defaultView.setTimeout(
					() => {
						suppressClick = false;
						suppressionTimer = undefined;
					},
					0
				);
			}
		}

		function preventDraggedClick( event ) {
			if ( ! suppressClick ) {
				return;
			}
			if ( suppressionTimer ) {
				track.ownerDocument.defaultView.clearTimeout(
					suppressionTimer
				);
				suppressionTimer = undefined;
			}
			suppressClick = false;
			event.preventDefault();
			event.stopPropagation();
		}

		function preventNativeDrag( event ) {
			event.preventDefault();
		}

		function scheduleSettle() {
			if (
				track.dataset.dragging !== undefined ||
				'snap' !== rootRef.current?.dataset.scrollBehavior
			) {
				return;
			}
			if ( scrollEndTimer ) {
				track.ownerDocument.defaultView.clearTimeout( scrollEndTimer );
			}
			scrollEndTimer = track.ownerDocument.defaultView.setTimeout( () => {
				scrollEndTimer = undefined;
				settleToNearestItem();
			}, 120 );
		}

		track.addEventListener( 'pointerdown', startDrag );
		track.addEventListener( 'pointermove', moveDrag );
		track.addEventListener( 'pointerup', endDrag );
		track.addEventListener( 'pointercancel', endDrag );
		track.addEventListener( 'click', preventDraggedClick, true );
		track.addEventListener( 'dragstart', preventNativeDrag );
		track.addEventListener( 'scroll', scheduleSettle, { passive: true } );

		return () => {
			if ( settleFrame ) {
				window.cancelAnimationFrame( settleFrame );
			}
			if ( suppressionTimer ) {
				track.ownerDocument.defaultView.clearTimeout(
					suppressionTimer
				);
			}
			if ( scrollEndTimer ) {
				track.ownerDocument.defaultView.clearTimeout( scrollEndTimer );
			}
			track.removeEventListener( 'pointerdown', startDrag );
			track.removeEventListener( 'pointermove', moveDrag );
			track.removeEventListener( 'pointerup', endDrag );
			track.removeEventListener( 'pointercancel', endDrag );
			track.removeEventListener( 'click', preventDraggedClick, true );
			track.removeEventListener( 'dragstart', preventNativeDrag );
			track.removeEventListener( 'scroll', scheduleSettle );
		};
	}, [] );

	useEffect( () => {
		if ( ! KEYLINE_LAYOUTS.includes( implementedLayout ) ) {
			return undefined;
		}
		const root = rootRef.current;
		const track = trackRef.current;
		if ( ! root || ! track ) {
			return undefined;
		}

		let animationFrame;
		let geometry;

		function clearKeylines() {
			const domItems = [
				...track.querySelectorAll( '.ax-carousel-item' ),
			];
			domItems.forEach( ( item ) => {
				item.style.removeProperty( '--ax-carousel-item-width' );
				item.style.removeProperty( '--ax-carousel-item-offset' );
				item.style.removeProperty( '--ax-carousel-mask-width' );
				item.style.removeProperty( 'visibility' );
				delete item.dataset.sizeRole;
			} );
			delete root.dataset.keylineProfile;
			delete root.dataset.keylineState;
			geometryRef.current = undefined;
		}

		/*
		 * Reduced motion asks for one size, not for no size. Removing the
		 * widths left `flex: 0 0 var(--ax-carousel-item-width)` with nothing to
		 * resolve, so the advanced layouts fell back to `auto` and collapsed to
		 * the intrinsic width of an image -- measured at 31px and 49px, which
		 * is the unrecognisable item the guidelines tell you to avoid. The
		 * uniform size is the strategy's focal width, and uncontained keeps the
		 * caller's, which it never stopped having.
		 */
		function applyUniformGeometry( domItems, uncontained, boxSize ) {
			domItems.forEach( ( item ) => {
				if ( uncontained ) {
					item.style.removeProperty( '--ax-carousel-item-width' );
				} else {
					item.style.setProperty(
						'--ax-carousel-item-width',
						`${ boxSize }px`
					);
				}
				item.style.removeProperty( '--ax-carousel-item-offset' );
				item.style.removeProperty( '--ax-carousel-mask-width' );
				item.style.removeProperty( 'visibility' );
				delete item.dataset.sizeRole;
			} );
			delete root.dataset.keylineProfile;
			delete root.dataset.keylineState;
			geometry = undefined;
			geometryRef.current = undefined;
		}

		function measureKeylines() {
			const trackStyle = window.getComputedStyle( track );
			const gap =
				parseFloat( trackStyle.columnGap || trackStyle.gap ) || 8;

			/*
			 * Uncontained's width belongs to the caller: UncontainedCarousel
			 * writes it onto the root and the strategy takes it as an input.
			 * Read it before anything is written per item, so a re-measure
			 * cannot feed the previous answer back in.
			 */
			const uncontained = 'uncontained' === implementedLayout;
			const domItems = [
				...track.querySelectorAll( '.ax-carousel-item' ),
			];

			/*
			 * Multi-aspect is "the same layout as the uncontained carousel but
			 * with items of various sizes", so it is masked at the edges like
			 * every other layout. What it cannot have is one stride, so each
			 * item carries its own box and the item centres are a running sum
			 * instead of index times stride. For a uniform carousel every box
			 * is the same and the sum reduces to exactly the old arithmetic.
			 *
			 * The keylines still need one width to be built from, and it is the
			 * widest box: the focal position has to be able to show the largest
			 * item whole. M3 publishes no equation for the varying-width case,
			 * so that choice is ours.
			 */
			let declaredItemWidth = 0;
			let boxes = [];
			if ( uncontained ) {
				const declared = parseFloat(
					window
						.getComputedStyle( root )
						.getPropertyValue( '--ax-carousel-item-width' )
				);
				if ( 0 < declared ) {
					declaredItemWidth = declared;
					boxes = domItems.map( () => declared );
				} else {
					/*
					 * No uniform width, so the items size themselves and each
					 * one is measured. The keylines still need a single width
					 * to be built from and it is the widest box, because the
					 * focal position has to be able to show the largest item
					 * whole. M3 publishes no equation for the varying-width
					 * case, so that choice is this project's.
					 */
					boxes = domItems.map(
						( item ) => item.getBoundingClientRect().width
					);
					declaredItemWidth = Math.max( 0, ...boxes );
				}
				if ( ! ( 0 < declaredItemWidth ) ) {
					clearKeylines();
					return;
				}
			}
			const paddingStart =
				parseFloat( trackStyle.paddingInlineStart ) || 0;
			const paddingEnd = parseFloat( trackStyle.paddingInlineEnd ) || 0;
			const availableSpace = Math.max(
				0,
				track.clientWidth - paddingStart - paddingEnd
			);
			let strategyResult;
			if ( uncontained ) {
				strategyResult = uncontainedStrategy( {
					availableSpace,
					itemSpacing: gap,
					itemWidth: declaredItemWidth,
				} );
			} else if ( 'multi-browse' === implementedLayout ) {
				strategyResult = multiBrowseStrategy( {
					availableSpace,
					itemCount: domItems.length,
					itemSpacing: gap,
					preferredItemWidth:
						preferredItemWidth || Math.min( 186, availableSpace ),
				} );
			} else {
				strategyResult = heroStrategy( {
					alignment: implementedAlignment,
					availableSpace,
					centered: 'center' === implementedAlignment,
					itemCount: domItems.length,
					itemSpacing: gap,
					preferredItemWidth,
				} );
			}
			const itemSize = strategyResult.itemMainAxisSize;
			if ( ! itemSize || ! strategyResult.defaultKeylines.length ) {
				clearKeylines();
				return;
			}

			/*
			 * `itemMainAxisSize` means different things in the two paths, and
			 * `carousel-strategy.test.js` holds the difference. Uncontained
			 * folds the spacing into it, so it is a stride and the box is one
			 * gap smaller; the advanced strategies size the box itself, so the
			 * stride is that size plus a gap. Writing the strategy's number as
			 * the uncontained item width would widen every item by one gap.
			 */
			const boxSize = uncontained ? declaredItemWidth : itemSize;
			if ( ! uncontained ) {
				boxes = domItems.map( () => itemSize );
			}

			if ( reducedMotion.matches ) {
				applyUniformGeometry( domItems, uncontained, boxSize );
				return;
			}

			if ( ! uncontained ) {
				domItems.forEach( ( item ) => {
					item.style.setProperty(
						'--ax-carousel-item-width',
						`${ itemSize }px`
					);
				} );
			}

			const stride = uncontained ? itemSize : itemSize + gap;

			/*
			 * Base centres, before the scroll offset.
			 *
			 * The two paths cannot share one line, and a version that tried to
			 * put every item's centre half a stride into its own slot was
			 * wrong by half a gap for the advanced layouts: their itemSize is
			 * the box, so the first centre is itemSize / 2, not stride / 2.
			 * Four pixels of that moved every item off its keyline, and the
			 * centred hero rendered 59, 249, 55 where the strategy says 56,
			 * 252, 56. Uncontained is the case where the two agree, because
			 * its itemSize already has the spacing folded in.
			 */
			let runningStart = 0;
			const centers = boxes.map( ( box, index ) => {
				if ( ! uncontained ) {
					return itemSize / 2 + index * stride;
				}
				const itemStride = box + gap;
				const center = runningStart + itemStride / 2;
				runningStart += itemStride;
				return center;
			} );

			/*
			 * Items of one width or of many. The keylines are built from a
			 * single width, so their offsets describe where items sit when
			 * every stride is the same. When the strides differ, those offsets
			 * are not where the items are, and moving items onto them slides
			 * them over each other and closes the gaps.
			 */
			const uniform = boxes.every(
				( box ) => 0.5 > Math.abs( box - boxes[ 0 ] )
			);

			geometry = {
				availableSpace,
				boxSize,
				boxes,
				centers,
				uniform,
				gap,
				itemSize,
				items: domItems,
				rtl: 'rtl' === trackStyle.direction,
				strategy: strategyResult,
				stride,
			};
			geometryRef.current = geometry;
			root.dataset.keylineProfile = strategyResult.sizes
				.map( ( size ) => sizeRole( size, boxSize ) )
				.join( '/' );
			renderKeylines();
		}

		function renderKeylines() {
			if ( ! geometry || reducedMotion.matches ) {
				return;
			}
			const {
				availableSpace,
				boxSize,
				boxes: geometryBoxes,
				centers,
				itemSize,
				items: geometryItems,
				rtl,
				strategy,
				stride,
				uniform,
			} = geometry;
			const maximumScroll = Math.max(
				0,
				track.scrollWidth - track.clientWidth
			);
			const scrollOffset = clamp(
				rtl ? -track.scrollLeft : track.scrollLeft,
				0,
				maximumScroll
			);
			const keylines = keylinesForScrollOffset(
				strategy,
				scrollOffset,
				maximumScroll
			);

			geometryItems.forEach( ( item, index ) => {
				const naturalCenter =
					( centers[ index ] ?? itemSize / 2 + index * stride ) -
					scrollOffset;
				const { offset, size } = itemGeometry(
					naturalCenter,
					keylines,
					itemSize
				);
				/*
				 * A uniform carousel moves its items onto the keylines. One
				 * with items of various sizes leaves them where the layout put
				 * them -- M3 calls it "the same layout as the uncontained
				 * carousel", and the layout is the flow of their own widths --
				 * and takes only the mask from the keylines.
				 */
				item.style.setProperty(
					'--ax-carousel-item-offset',
					uniform
						? `${ ( rtl ? -1 : 1 ) * ( offset - naturalCenter ) }px`
						: '0px'
				);
				const box = geometryBoxes[ index ] ?? boxSize;

				/*
				 * OFF STAGE IS NOT PAINTED. The anchor keylines are the
				 * positions an item interpolates towards as it leaves, and
				 * they sit outside the available space; a scroll container
				 * clips at its padding box, so items resting on them were
				 * drawn in the padding as slivers past the smallest real item.
				 *
				 * Hiding them is not the same as clipping the track to its
				 * content box, which was tried first: that also sliced items
				 * still on their way out, cutting as much as 17px off a pill
				 * that should have been whole. An item is either on stage or
				 * not painted at all.
				 */
				/* Painted where it ends up, so that is where off stage is judged. */
				const paintedCenter = uniform ? offset : naturalCenter;
				const paintedSize = uniform ? size : box;
				const offStage =
					paintedCenter - paintedSize / 2 >= availableSpace - 0.5 ||
					paintedCenter + paintedSize / 2 <= 0.5;
				item.style.visibility = offStage ? 'hidden' : '';

				/*
				 * The mask is a clip centred in the item, which is right when
				 * every item is the same width and has been moved onto a
				 * keyline: the window and the box share a centre. With items
				 * of various sizes neither holds, and a centred clip leaves
				 * the slice floating in its own box -- measured as a 101px gap
				 * where the carousel declares 8. Those items are cut off by
				 * the container instead, which is what "items flow past the
				 * edge of the screen" describes.
				 */
				item.style.setProperty(
					'--ax-carousel-mask-width',
					`${ uniform ? clamp( size, 1, box ) : box }px`
				);
				item.dataset.sizeRole = sizeRole( size, box );
			} );
			const endBoundary = Math.max(
				0,
				maximumScroll - strategy.endShiftDistance
			);
			let keylineState = 'default';
			if ( 0 === scrollOffset ) {
				keylineState = 'start';
			} else if ( scrollOffset < strategy.startShiftDistance ) {
				keylineState = 'shifting-start';
			} else if ( maximumScroll === scrollOffset ) {
				keylineState = 'end';
			} else if ( scrollOffset > endBoundary ) {
				keylineState = 'shifting-end';
			}
			root.dataset.keylineState = keylineState;
		}

		function scheduleKeylines() {
			if ( animationFrame ) {
				return;
			}
			animationFrame = window.requestAnimationFrame( () => {
				animationFrame = undefined;
				renderKeylines();
			} );
		}

		const reducedMotion = window.matchMedia(
			'(prefers-reduced-motion: reduce)'
		);
		measureKeylines();
		const observer = new window.ResizeObserver( measureKeylines );
		observer.observe( track );
		track.addEventListener( 'scroll', scheduleKeylines, { passive: true } );
		const onMotionPreferenceChange = () => measureKeylines();
		reducedMotion.addEventListener( 'change', onMotionPreferenceChange );
		return () => {
			if ( animationFrame ) {
				window.cancelAnimationFrame( animationFrame );
			}
			observer.disconnect();
			track.removeEventListener( 'scroll', scheduleKeylines );
			reducedMotion.removeEventListener(
				'change',
				onMotionPreferenceChange
			);
			geometryRef.current = undefined;
		};
	}, [ implementedAlignment, implementedLayout, preferredItemWidth ] );

	function moveFocus( event ) {
		const root = rootRef.current;
		const active = event.target.closest( '[data-carousel-action]' );
		if ( ! root || ! active || ! root.contains( active ) ) {
			return;
		}

		if ( 'ArrowUp' === event.key || 'ArrowDown' === event.key ) {
			const moved = leaveCarousel(
				root,
				active,
				'ArrowUp' === event.key ? -1 : 1
			);
			if ( moved ) {
				event.preventDefault();
			}
			return;
		}

		const physicalStep = { ArrowLeft: -1, ArrowRight: 1 }[ event.key ];
		if ( ! physicalStep ) {
			return;
		}

		const actions = [
			...root.querySelectorAll( '[data-carousel-action]' ),
		].filter(
			( action ) =>
				0 <= action.tabIndex && ! action.matches( ':disabled' )
		);
		const from = actions.indexOf( active );
		if ( 0 > from ) {
			return;
		}

		const rtl =
			'rtl' ===
			root.ownerDocument.defaultView.getComputedStyle( root ).direction;
		const to = from + ( rtl ? -physicalStep : physicalStep );
		if ( 0 > to || to >= actions.length ) {
			return;
		}

		event.preventDefault();

		const track = trackRef.current;
		const geometry = geometryRef.current;
		if ( track && geometry ) {
			const maximum = Math.max(
				0,
				track.scrollWidth - track.clientWidth
			);

			/*
			 * `scrollOffsetForItem` reads the strategy, whose keylines are
			 * generated from one width, so it answers for a carousel where
			 * every stride is the same. When they are not, an item's own left
			 * edge is where it has to go, and the strides are already in the
			 * centres the renderer built from the measured boxes.
			 */
			const own =
				geometry.centers[ to ] -
				( geometry.boxes[ to ] + geometry.gap ) / 2;
			const target = clamp(
				geometry.uniform
					? scrollOffsetForItem(
							geometry.strategy,
							to,
							actions.length
						)
					: own,
				0,
				maximum
			);
			track.scrollTo( {
				behavior: 'auto',
				left: geometry.rtl ? -target : target,
			} );
		} else {
			actions[ to ].scrollIntoView( {
				behavior: 'auto',
				block: 'nearest',
				inline: 'nearest',
			} );
		}

		/*
		 * Scroll first, then focus, and take the item off the hidden list on
		 * the way. An item resting on an anchor keyline is `visibility:
		 * hidden`, and a hidden element cannot be focused: focus went to the
		 * document body instead, which ended the walk because the handler only
		 * runs from inside an item. The scroll above has already put this one
		 * on stage, so the next render agrees.
		 */
		actions[ to ]
			.closest( '.ax-carousel-item' )
			?.style.removeProperty( 'visibility' );
		actions[ to ].focus();
	}

	return (
		<div
			{ ...props }
			ref={ rootRef }
			aria-label={ label || undefined }
			aria-roledescription={ roleDescription }
			className={ [ 'ax-carousel', className ]
				.filter( Boolean )
				.join( ' ' ) }
			data-alignment={ implementedAlignment }
			data-layout={ implementedLayout }
			data-scroll-behavior={ effectiveBehavior }
			role="group"
		>
			<div className="ax-carousel__items" ref={ trackRef }>
				{ childArray.map( ( child ) => {
					if (
						! isValidElement( child ) ||
						CarouselItem !== child.type
					) {
						return child;
					}
					itemPosition += 1;
					return cloneElement( child, {
						onNavigate: moveFocus,
						position: itemPosition,
						setSize: items.length,
						roleDescription: itemRoleDescription,
						...( formatPosition ? { formatPosition } : {} ),
					} );
				} ) }
			</div>
		</div>
	);
}
