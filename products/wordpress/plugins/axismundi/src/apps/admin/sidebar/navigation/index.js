import clsx from 'clsx';
import {
	createContext,
	useContext,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { focus } from '@wordpress/dom';

const SidebarNavigationContext = createContext( null );

function createNavigationState() {
	let state = {
		direction: null,
		focusSelector: null,
	};

	return {
		get: () => state,
		navigate: ( direction, focusSelector = null ) => {
			state = {
				direction,
				focusSelector:
					direction === 'forward' && focusSelector
						? focusSelector
						: state.focusSelector,
			};
		},
		reset: () => {
			state = { direction: null, focusSelector: null };
		},
	};
}

function focusSidebarElement( element, direction, focusSelector ) {
	let target;

	if ( direction === 'back' && focusSelector ) {
		target = element.querySelector( focusSelector );
	}

	if ( direction && ! target ) {
		[ target ] = focus.tabbable.find( element );
	}

	target?.focus();
}

function SidebarContentWrapper( { children, screenKey, shouldAnimate } ) {
	const navigation = useSidebarNavigation();
	const wrapperRef = useRef();
	const [ animationDirection, setAnimationDirection ] = useState( null );

	useLayoutEffect( () => {
		const { direction, focusSelector } = navigation.get();
		focusSidebarElement( wrapperRef.current, direction, focusSelector );
		setAnimationDirection( direction );
	}, [ navigation, screenKey ] );

	return (
		<div
			ref={ wrapperRef }
			className={ clsx(
				'ax-admin-sidebar__screen',
				shouldAnimate && {
					'ax-admin-sidebar__screen--from-left': animationDirection === 'back',
					'ax-admin-sidebar__screen--from-right': animationDirection === 'forward',
				}
			) }
		>
			{ children }
		</div>
	);
}

export function SidebarNavigationProvider( { children } ) {
	const [ navigation ] = useState( createNavigationState );

	return (
		<SidebarNavigationContext.Provider value={ navigation }>
			{ children }
		</SidebarNavigationContext.Provider>
	);
}

export function useSidebarNavigation() {
	const navigation = useContext( SidebarNavigationContext );

	if ( ! navigation ) {
		throw new Error( 'useSidebarNavigation must be used inside SidebarNavigationProvider.' );
	}

	return navigation;
}

export function SidebarContent( { children, screenKey, shouldAnimate = false } ) {
	return (
		<SidebarContentWrapper screenKey={ screenKey } shouldAnimate={ shouldAnimate }>
			{ children }
		</SidebarContentWrapper>
	);
}
