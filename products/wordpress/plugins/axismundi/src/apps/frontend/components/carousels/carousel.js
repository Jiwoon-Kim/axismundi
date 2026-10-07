/**
 * Material 3 Carousel semantics and keyboard contract.
 *
 * Geometry deliberately does not live here yet. `carousel.yml` records four
 * layout strategies, but Uncontained is the first one that can be implemented
 * without inventing M3's unpublished keyline algorithm. This component owns
 * only the contract shared by every strategy: a named, non-focusable group of
 * directly actionable items.
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
 * @param {'uncontained'}                          [props.layout='uncontained']        Implemented M3 layout.
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

export function Carousel( {
	label,
	children,
	layout = 'uncontained',
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
	if ( 'uncontained' !== layout ) {
		warning(
			`Carousel: layout "${ layout }" is recorded but not implemented; using uncontained.`
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
	let itemPosition = 0;

	useEffect( () => {
		const track = trackRef.current;
		if ( ! track ) {
			return undefined;
		}

		let drag;
		let suppressClick = false;
		let suppressionTimer;

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
			track.scrollLeft = drag.startScrollLeft - distance;
			event.preventDefault();
		}

		function endDrag( event ) {
			if ( ! drag || drag.pointerId !== event.pointerId ) {
				return;
			}

			if ( track.hasPointerCapture( event.pointerId ) ) {
				track.releasePointerCapture( event.pointerId );
			}
			delete track.dataset.dragging;
			drag = undefined;
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
			data-layout="uncontained"
			data-scroll-behavior={ behavior }
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
