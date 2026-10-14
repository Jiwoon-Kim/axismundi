import {
	cloneElement,
	createPortal,
	isValidElement,
	useCallback,
	useEffect,
	useId,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';
import warning from '@wordpress/warning';
import { placePopover } from '../overlays/place';

const HIDE_DELAY = 1500;
const SHOW_DELAY = 700;

let activeTooltip;

function mergeDescribedBy( existing, id ) {
	return [ existing, id ].filter( Boolean ).join( ' ' );
}

function setRef( ref, value ) {
	if ( 'function' === typeof ref ) {
		ref( value );
	} else if ( ref ) {
		ref.current = value;
	}
}

/**
 * Plain M3 tooltip. The trigger stays the focus owner; the popover only
 * describes it and is moved to document.body when native popovers are absent.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactElement} props.children Interactive trigger.
 * @param {string} props.content Plain tooltip text.
 * @param {string} [props.accessibleName] Trigger accessible name for ARIA policy.
 * @param {boolean} [props.hasVisualBoundary=true] Whether placement uses the 4dp boundary gap.
 * @return {import('@wordpress/element').ReactNode} Trigger and tooltip surface.
 */
export function Tooltip( {
	children,
	content,
	accessibleName,
	hasVisualBoundary = true,
} ) {
	const id = useId();
	const triggerRef = useRef();
	const tooltipRef = useRef();
	const showTimer = useRef();
	const hideTimer = useRef();
	const [ open, setOpen ] = useState( false );
	const [ canUsePopover, setCanUsePopover ] = useState( false );
	const descriptive = content && content !== accessibleName;

	useEffect( () => {
		setCanUsePopover(
			'undefined' !== typeof HTMLElement && 'function' === typeof HTMLElement.prototype.showPopover
		);
	}, [] );

	const clearTimers = useCallback( () => {
		window.clearTimeout( showTimer.current );
		window.clearTimeout( hideTimer.current );
	}, [] );

	const close = useCallback( () => {
		clearTimers();
		setOpen( false );
		if ( activeTooltip === close ) {
			activeTooltip = undefined;
		}
	}, [ clearTimers ] );

	const show = useCallback( () => {
		if ( ! content ) {
			return;
		}
		window.clearTimeout( hideTimer.current );
		if ( activeTooltip && activeTooltip !== close ) {
			activeTooltip();
		}
		activeTooltip = close;
		window.clearTimeout( showTimer.current );
		showTimer.current = window.setTimeout( () => setOpen( true ), SHOW_DELAY );
	}, [ close, content ] );

	const scheduleClose = useCallback( () => {
		window.clearTimeout( showTimer.current );
		window.clearTimeout( hideTimer.current );
		hideTimer.current = window.setTimeout( close, HIDE_DELAY );
	}, [ close ] );

	useLayoutEffect( () => {
		const tooltip = tooltipRef.current;
		const trigger = triggerRef.current;
		if ( ! tooltip || ! trigger ) {
			return undefined;
		}
		if ( ! open ) {
			if ( canUsePopover && tooltip.matches( ':popover-open' ) ) {
				tooltip.hidePopover();
			}
			return undefined;
		}

		if ( canUsePopover && ! tooltip.matches( ':popover-open' ) ) {
			tooltip.showPopover();
		}
		const place = () => {
			const inAppBar = Boolean( trigger.closest( '.ax-app-bar' ) );
			placePopover(
				tooltip,
				trigger.getBoundingClientRect(),
				'block',

				hasVisualBoundary ? 4 : 8,
				inAppBar,
				'center'
			);
		};
		place();
		window.addEventListener( 'resize', place );
		window.addEventListener( 'scroll', place, true );
		return () => {
			window.removeEventListener( 'resize', place );
			window.removeEventListener( 'scroll', place, true );
		};
	}, [ canUsePopover, hasVisualBoundary, open ] );

	useEffect( () => () => {
		clearTimers();
		if ( activeTooltip === close ) {
			activeTooltip = undefined;
		}
	}, [ clearTimers, close ] );

	if ( ! isValidElement( children ) ) {
		warning( 'Tooltip: `children` must be one interactive React element.' );
		return children;
	}

	const trigger = cloneElement( children, {
		ref: ( node ) => {
			triggerRef.current = node;
			setRef( children.ref, node );
		},
		'aria-describedby': descriptive ? mergeDescribedBy( children.props['aria-describedby'], id ) : children.props['aria-describedby'],
		onFocus: ( event ) => {
			children.props.onFocus?.( event );
			if ( ! event.defaultPrevented ) {
				show();
			}
		},
		onBlur: ( event ) => {
			children.props.onBlur?.( event );
			if ( ! event.defaultPrevented ) {
				scheduleClose();
			}
		},
		onPointerEnter: ( event ) => {
			children.props.onPointerEnter?.( event );
			if ( 'touch' !== event.pointerType && ! event.defaultPrevented ) {
				show();
			}
		},
		onPointerLeave: ( event ) => {
			children.props.onPointerLeave?.( event );
			if ( ! event.defaultPrevented ) {
				scheduleClose();
			}
		},
	} );

	const surface = (
		<div
			aria-hidden={ descriptive ? undefined : 'true' }
			className={ [ 'ax-tooltip', open && 'is-open' ].filter( Boolean ).join( ' ' ) }
			data-fallback={ canUsePopover ? undefined : '' }
			id={ id }
			popover={ canUsePopover ? 'manual' : undefined }
			ref={ tooltipRef }
			role={ descriptive ? 'tooltip' : undefined }
		>
			{ content }
		</div>
	);

	return <>{ trigger }{ canUsePopover ? surface : document.body ? createPortal( surface, document.body ) : surface }</>;
}
