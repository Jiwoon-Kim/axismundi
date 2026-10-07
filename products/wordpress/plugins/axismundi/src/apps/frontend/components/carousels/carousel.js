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

const PAGE_FOCUSABLE = [
	'a[href]',
	'button:not(:disabled)',
	'input:not(:disabled)',
	'select:not(:disabled)',
	'textarea:not(:disabled)',
	'[tabindex]:not([tabindex="-1"])',
].join( ',' );

const DRAG_THRESHOLD = 6;
const ADVANCED_LAYOUTS = [ 'multi-browse', 'hero' ];
const LARGE_REFERENCE_WIDTH = 184;
const MEDIUM_WIDTH = 120;
const SMALL_WIDTH = 56;
// This is a carousel-container threshold, not a window size class boundary.
const KEYLINE_CONTAINER_BREAKPOINT = 600;

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

function keylineProfile( layout, inlineSize, gap, alignment ) {
	const innerSize = Math.max( 0, inlineSize - 32 );
	if (
		'hero' === layout &&
		'center' === alignment &&
		inlineSize < KEYLINE_CONTAINER_BREAKPOINT
	) {
		return {
			roles: [ 'small', 'large', 'small' ],
			widths: [
				SMALL_WIDTH,
				innerSize - 2 * SMALL_WIDTH - 2 * gap,
				SMALL_WIDTH,
			],
		};
	}
	if ( 'hero' === layout && inlineSize < KEYLINE_CONTAINER_BREAKPOINT ) {
		return {
			roles: [ 'large', 'small' ],
			widths: [ innerSize - SMALL_WIDTH - gap, SMALL_WIDTH ],
		};
	}

	const fixedSize = MEDIUM_WIDTH + SMALL_WIDTH;
	const largeCount = Math.max(
		1,
		Math.floor(
			( innerSize - fixedSize + gap ) / ( LARGE_REFERENCE_WIDTH + gap )
		)
	);
	const largeWidth = Math.max(
		SMALL_WIDTH,
		( innerSize - fixedSize - ( largeCount + 1 ) * gap ) / largeCount
	);

	return {
		roles: [ ...Array( largeCount ).fill( 'large' ), 'medium', 'small' ],
		widths: [
			...Array( largeCount ).fill( largeWidth ),
			MEDIUM_WIDTH,
			SMALL_WIDTH,
		],
	};
}

function clamp( value, minimum, maximum ) {
	return Math.min( maximum, Math.max( minimum, value ) );
}

function lerp( start, end, progress ) {
	return start + ( end - start ) * progress;
}

function visibleCenters( widths, gap, start ) {
	let cursor = start;
	return widths.map( ( width ) => {
		const center = cursor + width / 2;
		cursor += width + gap;
		return center;
	} );
}

function interpolateKeylines( start, end, progress ) {
	return start.map( ( keyline, index ) => ( {
		loc: lerp( keyline.loc, end[ index ].loc, progress ),
		offset: lerp( keyline.offset, end[ index ].offset, progress ),
		size: lerp( keyline.size, end[ index ].size, progress ),
	} ) );
}

function itemGeometry( location, keylines, itemSize ) {
	const first = keylines[ 0 ];
	const last = keylines[ keylines.length - 1 ];
	if ( location <= first.loc ) {
		return {
			offset:
				first.offset +
				( location - first.loc ) * ( first.size / itemSize ),
			size: first.size,
		};
	}
	if ( location >= last.loc ) {
		return {
			offset:
				last.offset +
				( location - last.loc ) * ( last.size / itemSize ),
			size: last.size,
		};
	}

	const rightIndex = keylines.findIndex(
		( keyline ) => keyline.loc >= location
	);
	const left = keylines[ rightIndex - 1 ];
	const right = keylines[ rightIndex ];
	const progress =
		0 === right.loc - left.loc
			? 0
			: ( location - left.loc ) / ( right.loc - left.loc );
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
	className,
	...props
} ) {
	const rootRef = useRef();
	const trackRef = useRef();
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
	const effectiveBehavior = ADVANCED_LAYOUTS.includes( implementedLayout )
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

		function settleToNearestItem() {
			if ( 'snap' !== rootRef.current?.dataset.scrollBehavior ) {
				delete track.dataset.dragging;
				return;
			}

			const rtl = 'rtl' === window.getComputedStyle( track ).direction;
			const current = rtl ? -track.scrollLeft : track.scrollLeft;
			const maximum = Math.max( 0, track.scrollWidth - track.clientWidth );
			const paddingStart =
				parseFloat(
					window.getComputedStyle( track ).paddingInlineStart
				) || 0;
			const targets = [
				...track.querySelectorAll( '.ax-carousel-item' ),
			].map( ( item, index, allItems ) =>
				index === allItems.length - 1
					? maximum
					: clamp( item.offsetLeft - paddingStart, 0, maximum )
			);
			const target = targets.reduce(
				( nearest, candidate ) =>
					Math.abs( candidate - current ) <
					Math.abs( nearest - current )
						? candidate
						: nearest,
				targets[ 0 ] ?? 0
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
				! event.isPrimary
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

		track.addEventListener( 'pointerdown', startDrag );
		track.addEventListener( 'pointermove', moveDrag );
		track.addEventListener( 'pointerup', endDrag );
		track.addEventListener( 'pointercancel', endDrag );
		track.addEventListener( 'click', preventDraggedClick, true );
		track.addEventListener( 'dragstart', preventNativeDrag );

		return () => {
			if ( settleFrame ) {
				window.cancelAnimationFrame( settleFrame );
			}
			if ( suppressionTimer ) {
				track.ownerDocument.defaultView.clearTimeout(
					suppressionTimer
				);
			}
			track.removeEventListener( 'pointerdown', startDrag );
			track.removeEventListener( 'pointermove', moveDrag );
			track.removeEventListener( 'pointerup', endDrag );
			track.removeEventListener( 'pointercancel', endDrag );
			track.removeEventListener( 'click', preventDraggedClick, true );
			track.removeEventListener( 'dragstart', preventNativeDrag );
		};
	}, [] );

	useEffect( () => {
		if ( ! ADVANCED_LAYOUTS.includes( implementedLayout ) ) {
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
		}

		function measureKeylines() {
			if ( reducedMotion.matches ) {
				clearKeylines();
				return;
			}

			const trackStyle = window.getComputedStyle( track );
			const gap =
				parseFloat( trackStyle.columnGap || trackStyle.gap ) || 8;
			const profile = keylineProfile(
				implementedLayout,
				track.clientWidth,
				gap,
				implementedAlignment
			);
			const domItems = [
				...track.querySelectorAll( '.ax-carousel-item' ),
			];
			const itemSize = Math.max( ...profile.widths );
			domItems.forEach( ( item ) => {
				item.style.setProperty(
					'--ax-carousel-item-width',
					`${ itemSize }px`
				);
			} );

			const paddingStart = 16;
			const edgePadding = 16;
			const stride = itemSize + gap;
			const defaultOffsets = visibleCenters(
				profile.widths,
				gap,
				paddingStart
			);
			const endWidths =
				'hero' === implementedLayout &&
				'center' === implementedAlignment &&
				'small' === profile.roles[ 0 ]
					? [
							...Array( profile.widths.length - 1 ).fill(
								SMALL_WIDTH
							),
							itemSize,
						]
					: [ ...profile.widths ].reverse();
			const endExtent =
				endWidths.reduce( ( total, width ) => total + width, 0 ) +
				gap * ( endWidths.length - 1 );
			const endOffsets = visibleCenters(
				endWidths,
				gap,
				track.clientWidth - edgePadding - endExtent
			);
			const defaultKeylines = profile.widths.map( ( size, index ) => ( {
				loc: paddingStart + itemSize / 2 + index * stride,
				offset: defaultOffsets[ index ],
				size,
			} ) );
			const firstLargeIndex = profile.roles.indexOf( 'large' );
			const startWidths = firstLargeIndex > 0
				? [
						itemSize,
						...Array( profile.widths.length - 1 ).fill(
							SMALL_WIDTH
						),
					]
				: profile.widths;
			const startOffsets = visibleCenters(
				startWidths,
				gap,
				paddingStart
			);
			const startKeylines = startWidths.map( ( size, index ) => ( {
				loc: paddingStart + itemSize / 2 + index * stride,
				offset: startOffsets[ index ],
				size,
			} ) );

			geometry = {
				defaultKeylines,
				endOffsets,
				endWidths,
				gap,
				itemSize,
				items: domItems,
				paddingStart,
				profile,
				rtl: 'rtl' === trackStyle.direction,
				startKeylines,
				startSteps: Math.max( 0, firstLargeIndex ),
				stride,
			};
			root.dataset.keylineProfile = profile.roles.join( '/' );
			renderKeylines();
		}

		function renderKeylines() {
			if ( ! geometry || reducedMotion.matches ) {
				return;
			}
			const {
				defaultKeylines,
				endOffsets,
				endWidths,
				itemSize,
				items: geometryItems,
				paddingStart,
				profile,
				rtl,
				startKeylines,
				startSteps,
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
			const startShiftRange = Math.min(
				maximumScroll / 2,
				stride * startSteps
			);
			const endShiftRange = Math.min(
				maximumScroll / 2,
				stride * Math.max( 1, profile.widths.length - 1 )
			);
			const startProgress = startShiftRange
				? clamp( 1 - scrollOffset / startShiftRange, 0, 1 )
				: 0;
			const endProgress = endShiftRange
				? clamp(
						( scrollOffset - ( maximumScroll - endShiftRange ) ) /
							endShiftRange,
						0,
						1
					)
				: 0;
			const lastNaturalCenter =
				paddingStart +
				itemSize / 2 +
				Math.max( 0, geometryItems.length - 1 ) * stride -
				maximumScroll;
			const endKeylines = endWidths.map( ( size, index ) => ( {
				loc:
					lastNaturalCenter -
					( endWidths.length - 1 - index ) * stride,
				offset: endOffsets[ index ],
				size,
			} ) );
			let keylines = defaultKeylines;
			if ( 0 < startProgress ) {
				keylines = interpolateKeylines(
					defaultKeylines,
					startKeylines,
					startProgress
				);
			} else if ( 0 < endProgress ) {
				keylines = interpolateKeylines(
					defaultKeylines,
					endKeylines,
					endProgress
				);
			}

			geometryItems.forEach( ( item, index ) => {
				const naturalCenter =
					paddingStart + itemSize / 2 + index * stride - scrollOffset;
				const { offset, size } = itemGeometry(
					naturalCenter,
					keylines,
					itemSize
				);
				item.style.setProperty(
					'--ax-carousel-item-offset',
					`${ ( rtl ? -1 : 1 ) * ( offset - naturalCenter ) }px`
				);
				item.style.setProperty(
					'--ax-carousel-mask-width',
					`${ clamp( size, 1, itemSize ) }px`
				);
				item.dataset.sizeRole = sizeRole( size, itemSize );
			} );
			let keylineState = 'default';
			if ( 0.999 <= startProgress ) {
				keylineState = 'start';
			} else if ( 0 < startProgress ) {
				keylineState = 'shifting-start';
			} else if ( 0.999 <= endProgress ) {
				keylineState = 'end';
			} else if ( 0 < endProgress ) {
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
		};
	}, [ implementedAlignment, implementedLayout ] );

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
		actions[ to ].scrollIntoView( {
			behavior: 'auto',
			block: 'nearest',
			inline: 'nearest',
		} );
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
