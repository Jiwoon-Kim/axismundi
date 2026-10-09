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
 * @param {boolean}                                [props.multiAspect=false]           Uses varied-ratio Uncontained geometry.
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
	multiAspect = false,
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
	const implementedMultiAspect =
		'uncontained' === implementedLayout && Boolean( multiAspect );
	if ( multiAspect && ! implementedMultiAspect ) {
		warning(
			'Carousel: multiAspect is an Uncontained configuration; ignoring it for this layout.'
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
			const targets = currentGeometry
				? currentGeometry.items.map( ( _, index ) =>
						clamp(
							scrollOffsetForItem(
								currentGeometry.strategy,
								index,
								currentGeometry.items.length
							),
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
				if ( implementedMultiAspect ) {
					boxes = domItems.map(
						( item ) => item.getBoundingClientRect().width
					);
					declaredItemWidth = Math.max( 0, ...boxes );
				} else {
					declaredItemWidth = parseFloat(
						window
							.getComputedStyle( root )
							.getPropertyValue( '--ax-carousel-item-width' )
					);
					boxes = domItems.map( () => declaredItemWidth );
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
			 * Base centres, before the scroll offset. Uniform uncontained gives
			 * boxes[i] + gap === itemSize, so centre i is itemSize / 2 plus i
			 * strides, which is what this used to compute directly.
			 */
			let runningStart = 0;
			const centers = boxes.map( ( box ) => {
				const itemStride = uncontained ? box + gap : stride;
				const center = runningStart + itemStride / 2;
				runningStart += itemStride;
				return center;
			} );

			geometry = {
				availableSpace,
				boxSize,
				boxes,
				centers,
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
				boxSize,
				boxes: geometryBoxes,
				centers,
				itemSize,
				items: geometryItems,
				rtl,
				strategy,
				stride,
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
				item.style.setProperty(
					'--ax-carousel-item-offset',
					`${ ( rtl ? -1 : 1 ) * ( offset - naturalCenter ) }px`
				);
				const box = geometryBoxes[ index ] ?? boxSize;
				item.style.setProperty(
					'--ax-carousel-mask-width',
					`${ clamp( size, 1, box ) }px`
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
	}, [
		implementedAlignment,
		implementedLayout,
		implementedMultiAspect,
		preferredItemWidth,
	] );

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
		actions[ to ].focus();
		const track = trackRef.current;
		const geometry = geometryRef.current;
		if ( track && geometry ) {
			const maximum = Math.max(
				0,
				track.scrollWidth - track.clientWidth
			);
			const target = clamp(
				scrollOffsetForItem( geometry.strategy, to, actions.length ),
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
			data-multi-aspect={ implementedMultiAspect ? '' : undefined }
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
