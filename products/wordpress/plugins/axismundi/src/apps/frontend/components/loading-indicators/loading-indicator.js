import { useEffect, useRef } from '@wordpress/element';
import warning from '@wordpress/warning';
import geometry from './assets/loading-geometry.json';
import { pathForCubics } from './geometry.cjs';

const STATIC_PATH = pathForCubics( geometry.shapes[ 0 ].cubics );

/**
 * Short, indeterminate loading. Geometry is plugin data; motion and colour are
 * theme tokens. Reduced motion keeps the first shape and the same semantics.
 * This Web profile uses the existing slow spatial curve, not an Android spring solver.
 */
export function LoadingIndicator( {
	label = 'Loading',
	contained = false,
	size = 48,
	animated = true,
	decorative = false,
	className,
	style,
	...props
} ) {
	const rootRef = useRef( null );
	const svgRef = useRef( null );
	const pathRef = useRef( null );
	const validSize = Number.isFinite( size ) ? Math.min( 240, Math.max( 24, size ) ) : 48;
	if ( size !== validSize ) warning( 'LoadingIndicator: size must be between 24 and 240 pixels.' );

	useEffect( () => {
		const root = rootRef.current;
		const svg = svgRef.current;
		const path = pathRef.current;
		const reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' );
		let inView = true;
		let disposed = false;
		let generation = 0;
		let animations = [];
		const supported = typeof path.animate === 'function' && window.CSS?.supports( 'd', `path("${ STATIC_PATH }")` );

		const stop = () => {
			++generation;
			animations.forEach( ( animation ) => animation.cancel() );
			animations = [];
		};
		const sync = () => {
			stop();
			if ( disposed || ! animated || reduced.matches || document.hidden || ! inView || ! supported ) return;
			const computed = getComputedStyle( root );
			const durationValue = computed.getPropertyValue( '--md-sys-motion-curve-slow-spatial-duration' ).trim();
			const easing = computed.getPropertyValue( '--md-sys-motion-curve-slow-spatial' ).trim();
			const duration = parseFloat( durationValue ) * ( durationValue.endsWith( 'ms' ) ? 1 : 1000 );
			if ( ! Number.isFinite( duration ) || duration <= 0 || ! easing ) return;
			const currentGeneration = generation;
			let step = 0;
			const next = () => {
				if ( disposed || currentGeneration !== generation ) return;
				const pair = geometry.pairs[ step % geometry.pairs.length ];
				const previous = animations;
				const morph = path.animate( [
					{ d: `path("${ pathForCubics( pair.start ) }")`, transform: `rotate(${ ( step * 90 ) % 360 }deg)` },
					{ d: `path("${ pathForCubics( pair.end ) }")`, transform: `rotate(${ ( step * 90 ) % 360 + 90 }deg)` },
				], { duration, easing, fill: 'forwards' } );
				const rotation = svg.animate( [
					{ transform: `rotate(${ ( step * 50 ) % 360 }deg)` },
					{ transform: `rotate(${ ( step * 50 ) % 360 + 50 }deg)` },
				], { duration, easing: 'linear', fill: 'forwards' } );
				animations = [ morph, rotation ];
				previous.forEach( ( animation ) => animation.cancel() );
				morph.finished.then( () => { ++step; next(); } ).catch( () => {} );
			};
			next();
		};
		const observer = typeof IntersectionObserver === 'function' ? new IntersectionObserver( ( entries ) => {
			const visible = entries[ 0 ].isIntersecting;
			if ( visible !== inView ) { inView = visible; sync(); }
		} ) : null;
		observer?.observe( root );
		reduced.addEventListener( 'change', sync );
		document.addEventListener( 'visibilitychange', sync );
		sync();
		return () => {
			disposed = true;
			stop();
			observer?.disconnect();
			reduced.removeEventListener( 'change', sync );
			document.removeEventListener( 'visibilitychange', sync );
		};
	}, [ animated ] );

	return (
		<span { ...props } ref={ rootRef }
			className={ [ 'ax-loading-indicator', className ].filter( Boolean ).join( ' ' ) }
			data-contained={ contained ? '' : undefined }
			role={ decorative ? undefined : 'progressbar' }
			aria-label={ decorative ? undefined : label }
			aria-hidden={ decorative ? true : undefined }
			style={ { ...style, '--ax-loading-scale': validSize / 48, '--ax-loading-static-path': `path("${ STATIC_PATH }")` } }>
			<svg ref={ svgRef } viewBox="0 0 1 1" aria-hidden="true" focusable="false">
				<path ref={ pathRef } d={ STATIC_PATH } />
			</svg>
		</span>
	);
}
