import {
	createContext,
	useContext,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import warning from '@wordpress/warning';

const TabsContext = createContext( null );
const ACTIVATION_KEYS = [ ' ', 'Enter' ];

// The indicator travels from where it was to where it belongs. Taken from
// material-web's own tab, which animates 250ms on the emphasized curve and
// falls back to a fade when the viewer asks for less motion.
const INDICATOR_DURATION = 250;
const INDICATOR_EASING = 'cubic-bezier(0.2, 0, 0, 1)';

const prefersReducedMotion = () =>
	window.matchMedia?.( '(prefers-reduced-motion: reduce)' ).matches ?? false;

/**
 * Animates the newly active indicator out of the one that was active.
 *
 * Every tab keeps an indicator element, hidden until its tab is selected, so
 * the outgoing rectangle is still measurable when the incoming one appears.
 *
 * @param {HTMLElement|null|undefined} from Previously active indicator.
 * @param {HTMLElement|null|undefined} to   Newly active indicator.
 * @return {void}
 */
function morphIndicator( from, to ) {
	if ( ! to ) {
		return;
	}

	to.getAnimations().forEach( ( animation ) => animation.cancel() );

	const toRect = to.getBoundingClientRect();
	const fromRect = from?.getBoundingClientRect();
	const scale = fromRect ? fromRect.width / toRect.width : NaN;

	const start =
		! prefersReducedMotion() && fromRect && ! isNaN( scale ) && 0 < scale
			? {
					transform: `translateX( ${ ( fromRect.left - toRect.left ).toFixed( 4 ) }px ) scaleX( ${ scale.toFixed( 4 ) } )`,
			  }
			: { opacity: 0 };

	// `transform: none` rather than an empty frame: Safari can drop the
	// animation without it.
	to.animate( [ start, { opacity: 1, transform: 'none' } ], {
		duration: INDICATOR_DURATION,
		easing: INDICATOR_EASING,
	} );
}

const firstEnabledId = ( tabs ) => tabs.find( ( tab ) => ! tab.disabled )?.id;

const useTabs = () => {
	const context = useContext( TabsContext );

	if ( ! context ) {
		warning( 'Tabs composition must be rendered inside <Tabs>.' );
	}

	return context;
};

/**
 * Owns selection state shared by a tab bar and its corresponding panels.
 */
export function Tabs( {
	children,
	className = '',
	defaultActiveId,
	id,
	label,
	onChange,
	activeId: controlledActiveId,
	tabs,
	...props
} ) {
	const initialTab = tabs.find(
		( tab ) => tab.id === defaultActiveId && ! tab.disabled
	);
	const initialId = initialTab?.id ?? firstEnabledId( tabs );
	const [ uncontrolledActiveId, setUncontrolledActiveId ] = useState( initialId );
	const activeId = controlledActiveId ?? uncontrolledActiveId;
	const [ focusedId, setFocusedId ] = useState( activeId );

	// The focused tab is tracked separately from the active one, because arrow
	// keys move focus without selecting. Both have to be recovered when the
	// set changes: a focusedId pointing at a tab that is gone or disabled
	// leaves no tab carrying tabindex="0", and the tablist drops out of the
	// tab order entirely.
	useEffect( () => {
		const isUsable = ( id ) =>
			tabs.some( ( tab ) => tab.id === id && ! tab.disabled );

		if ( ! isUsable( activeId ) || ! isUsable( focusedId ) ) {
			setFocusedId( isUsable( activeId ) ? activeId : firstEnabledId( tabs ) );
		}
	}, [ activeId, focusedId, tabs ] );

	const select = ( nextId ) => {
		const nextTab = tabs.find( ( tab ) => tab.id === nextId && ! tab.disabled );

		if ( ! nextTab ) {
			return;
		}

		if ( controlledActiveId === undefined ) {
			setUncontrolledActiveId( nextId );
		}

		setFocusedId( nextId );
		onChange?.( nextId );
	};

	return (
		<TabsContext.Provider
			value={ {
				activeId,
				focusedId,
				id,
				label,
				select,
				setFocusedId,
				tabs,
			} }
		>
			<section className={ `ax-tabs ${ className }`.trim() } { ...props }>
				{ children }
			</section>
		</TabsContext.Provider>
	);
}

function TabBar( {
	className = '',
	scrollable = false,
	variant,
	...props
} ) {
	const context = useTabs();
	const listRef = useRef();
	const previousActiveId = useRef();

	// The active id this bar last painted an indicator for. Reading it in an
	// effect, not during render, keeps the outgoing element alive long enough
	// to be measured.
	const activeId = context?.activeId;

	useEffect( () => {
		const bar = listRef.current;

		if ( ! bar || previousActiveId.current === activeId ) {
			return;
		}

		const indicatorFor = ( id ) =>
			bar.querySelector( `[data-tab-id="${ id }"] .ax-tab-bar__indicator` );

		if ( undefined !== previousActiveId.current ) {
			morphIndicator( indicatorFor( previousActiveId.current ), indicatorFor( activeId ) );
		}

		previousActiveId.current = activeId;
	}, [ activeId ] );

	// A scrollable bar is the one place a pointer has no way through: there is
	// no drag here the way the Carousel has one, and on the desktop the mouse
	// is the usual instrument. A vertical wheel moves the bar sideways, and
	// only while the bar can still move that way, so the page keeps scrolling
	// at either end instead of trapping the gesture.
	useEffect( () => {
		const bar = listRef.current;

		if ( ! bar || ! scrollable ) {
			return;
		}

		const onWheel = ( event ) => {
			if ( event.ctrlKey || 0 !== event.deltaX ) {
				return;
			}

			const delta = event.deltaY;
			const limit = bar.scrollWidth - bar.clientWidth;
			const at = Math.abs( bar.scrollLeft );

			if ( 0 >= limit || ( 0 < delta && at >= limit - 1 ) || ( 0 > delta && 1 >= at ) ) {
				return;
			}

			event.preventDefault();
			bar.scrollBy( { left: 'rtl' === getComputedStyle( bar ).direction ? -delta : delta } );
		};

		// Not React's onWheel: that listener is passive and cannot preventDefault.
		bar.addEventListener( 'wheel', onWheel, { passive: false } );

		return () => bar.removeEventListener( 'wheel', onWheel );
	}, [ scrollable ] );

	if ( ! context ) {
		return null;
	}

	const { focusedId, id, label, select, setFocusedId, tabs } = context;

	// A tab without a visible label still has to have a name. M3 warns that an
	// icon's meaning may not be clear, and the kit publishes an icon-only
	// layout for the primary style only -- it is not forbidden elsewhere, it is
	// simply unspecified, and nothing here has to branch on the variant to
	// allow it. See products/styleguide/_data/tabs.yml.
	tabs.forEach( ( tab ) => {
		if ( ! tab.label && ! tab.name ) {
			warning( `Tabs: tab "${ tab.id }" has no label, so it needs a name for assistive technology.` );
		}
		if ( tab.badge && ! tab.badgeDescription ) {
			warning( `Tabs: tab "${ tab.id }" has a Badge, so it needs a badgeDescription for assistive technology.` );
		}
	} );
	const enabledTabs = tabs.filter( ( tab ) => ! tab.disabled );

	// Focus moves now, not on the next frame. A tab with tabindex="-1" is
	// focusable programmatically, so there is nothing to wait for, and a frame
	// that never arrives -- a hidden document -- would swallow the arrow key.
	const focusTab = ( nextId ) => {
		setFocusedId( nextId );

		const nextButton = [
			...( listRef.current?.querySelectorAll( '[data-tab-id]' ) ?? [] ),
		].find( ( button ) => button.dataset.tabId === nextId );

		nextButton?.focus();
		nextButton?.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
	};

	const onKeyDown = ( event ) => {
		const currentId = event.currentTarget.dataset.tabId;
		const currentIndex = enabledTabs.findIndex( ( tab ) => tab.id === currentId );
		let nextTab;

		if ( ACTIVATION_KEYS.includes( event.key ) ) {
			event.preventDefault();
			select( currentId );
			return;
		}

		if ( 'Home' === event.key ) {
			nextTab = enabledTabs[ 0 ];
		} else if ( 'End' === event.key ) {
			nextTab = enabledTabs.at( -1 );
		} else if ( [ 'ArrowLeft', 'ArrowRight' ].includes( event.key ) ) {
			const isRtl = 'rtl' === getComputedStyle( event.currentTarget ).direction;
			const isForward =
				( 'ArrowRight' === event.key && ! isRtl ) ||
				( 'ArrowLeft' === event.key && isRtl );
			nextTab = enabledTabs[ currentIndex + ( isForward ? 1 : -1 ) ];
		}

		if ( ! nextTab || nextTab.id === currentId ) {
			return;
		}

		event.preventDefault();
		focusTab( nextTab.id );
	};

	return (
		<div
			{ ...props }
			aria-label={ label }
			className={ `ax-tab-bar ${ className }`.trim() }
			data-layout={ scrollable ? 'scrollable' : 'fixed' }
			data-variant={ variant }
			ref={ listRef }
			role="tablist"
		>
			{ tabs.map( ( tab ) => {
				const selected = activeId === tab.id;
				const focused = focusedId === tab.id;
				const iconAnchorsBadge = tab.icon && ( 'primary' === variant || ! tab.label );
				const tabId = `${ id }-tab-${ tab.id }`;
				const panelId = `${ id }-panel-${ tab.id }`;
				const accessibleName = tab.badgeDescription
					? [ tab.label ?? tab.name, tab.badgeDescription ].filter( Boolean ).join( ', ' )
					: tab.label
						? undefined
						: tab.name;

				return (
					<button
						aria-controls={ panelId }
						aria-label={ accessibleName }
						aria-selected={ selected }
						className="ax-tab-bar__tab"
						data-layout={ tab.label ? undefined : 'icon-only' }
						data-tab-id={ tab.id }
						disabled={ tab.disabled }
						id={ tabId }
						key={ tab.id }
						onClick={ () => select( tab.id ) }
						onFocus={ () => setFocusedId( tab.id ) }
						onKeyDown={ onKeyDown }
						role="tab"
						tabIndex={ focused ? 0 : -1 }
						type="button"
					>
						{ /*
						   The indicator belongs to the content box on a primary
						   tab and to the whole tab on a secondary one, which is
						   how material-web's `fullWidthIndicator` places it. It
						   is rendered on every tab, hidden until selected, so
						   the outgoing rectangle can still be measured.
						 */ }
						<span className="ax-tab-bar__content">
							{ tab.icon && (
								<span aria-hidden="true" className="ax-tab-bar__icon">
									{ tab.icon }
									{ iconAnchorsBadge && tab.badge ? <span className="ax-tab-bar__badge">{ tab.badge }</span> : null }
								</span>
							) }
							{ tab.label && (
								<span className="ax-tab-bar__label-with-badge">
									<span className="ax-tab-bar__label">{ tab.label }</span>
									{ ! iconAnchorsBadge && tab.badge ? <span className="ax-tab-bar__inline-badge">{ tab.badge }</span> : null }
								</span>
							) }
							{ 'primary' === variant && <span aria-hidden="true" className="ax-tab-bar__indicator" /> }
						</span>
						{ 'primary' !== variant && <span aria-hidden="true" className="ax-tab-bar__indicator" /> }
					</button>
				);
			} ) }
		</div>
	);
}

export function PrimaryTabBar( props ) {
	return <TabBar { ...props } variant="primary" />;
}

export function SecondaryTabBar( props ) {
	return <TabBar { ...props } variant="secondary" />;
}

/**
 * Renders the panels paired with the nearest Tabs state root.
 */
export function TabPanels( { className = '', ...props } ) {
	const context = useTabs();

	if ( ! context ) {
		return null;
	}

	const { activeId, id, tabs } = context;

	return (
		<div className={ `ax-tabs__panels ${ className }`.trim() } { ...props }>
			{ tabs.map( ( tab ) => {
				const selected = activeId === tab.id;

				return (
					<div
						aria-labelledby={ `${ id }-tab-${ tab.id }` }
						className="ax-tabs__panel"
						hidden={ ! selected }
						id={ `${ id }-panel-${ tab.id }` }
						key={ tab.id }
						role="tabpanel"
						tabIndex={ selected ? 0 : -1 }
					>
						{ tab.panel }
					</div>
				);
			} ) }
		</div>
	);
}
