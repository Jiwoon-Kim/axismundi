/**
 * The app's one window size class observation.
 *
 * `viewport.json` declares Material's five classes and, until this file, nothing
 * read it. The thresholds are taken from there rather than repeated here, so the
 * JSON is the source for the JavaScript side.
 *
 * THIS IS NOT FOR STYLING. Anything a media query can express stays a media
 * query, in the stylesheet that owns the layout. This hook exists for the
 * decisions CSS cannot make -- the ones where the DOM itself differs. The
 * navigation item is the measured case: its two orientations put the label
 * inside the indicator or beside it, which no media query can switch, while
 * every width and gap it has is CSS. Using this for styling would put the same
 * breakpoint in two places and let them drift.
 *
 * `DECISION-FRONTEND-ADAPTIVE-LAYOUT.md` owns the vocabulary and the figures;
 * `PLAN-FRONTEND-CANONICAL-LAYOUTS.md` §2 records why this exists and why it has
 * no null phase.
 *
 * THE FIRST VALUE IS SYNCHRONOUS. `useState` with a function calls it during the
 * first render, so the first paint already has the right class. An earlier draft
 * of the plan proposed returning `null` until mount; that solves a
 * server-rendering problem this app does not have -- `index.js` mounts with
 * `createRoot` and nothing hydrates -- while creating the exact failure the
 * signal exists to prevent, one frame of the wrong DOM.
 *
 * @return {'compact'|'medium'|'expanded'|'large'|'extraLarge'} Current class.
 */

import { useEffect, useState } from '@wordpress/element';
import viewport from './viewport.json';

/*
 * Widest first, so the first match wins. `compact` has a `minWidth` of 0 and is
 * therefore the floor: it answers when nothing above it does.
 */
const CLASSES = Object.entries( viewport.windowSizeClasses )
	.map( ( [ name, { minWidth } ] ) => ( { name, minWidth } ) )
	.sort( ( a, b ) => b.minWidth - a.minWidth );

const QUERY = ( minWidth ) => `(min-width: ${ minWidth }px)`;

/** The class the window is in right now. Safe to call during render. */
export function resolveWindowSizeClass() {
	const match = CLASSES.find(
		( { minWidth } ) => 0 === minWidth || window.matchMedia( QUERY( minWidth ) ).matches
	);
	return match.name;
}

export function useWindowSizeClass() {
	const [ sizeClass, setSizeClass ] = useState( resolveWindowSizeClass );

	useEffect( () => {
		/*
		 * Read once on mount as well. A resize between the first render and this
		 * subscription would otherwise be missed, and the component would hold a
		 * class the window has already left.
		 */
		setSizeClass( resolveWindowSizeClass() );

		const update = () => setSizeClass( resolveWindowSizeClass() );
		/*
		 * One list per threshold rather than a `resize` listener: the browser
		 * only notifies when a boundary is actually crossed, so dragging a window
		 * across a class fires once instead of on every pixel.
		 */
		const lists = CLASSES.filter( ( { minWidth } ) => 0 < minWidth ).map( ( { minWidth } ) =>
			window.matchMedia( QUERY( minWidth ) )
		);
		lists.forEach( ( list ) => list.addEventListener( 'change', update ) );

		return () => lists.forEach( ( list ) => list.removeEventListener( 'change', update ) );
	}, [] );

	return sizeClass;
}
