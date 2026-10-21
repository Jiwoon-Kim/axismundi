import { useEffect, useId, useRef, useState } from '@wordpress/element';
import warning from '@wordpress/warning';
import { circularPath, linearPath } from './wave-geometry.cjs';

/** Omit value for indeterminate progress; max must be finite and positive. */
export function ProgressIndicator( {
	value, max = 1, label = 'Loading', circular = false, showTrack = true,
	showStop = true, animated = true, wavy = false, thick = false, className, style, ...props
} ) {
	const validMax = Number.isFinite( max ) && max > 0 ? max : 1;
	const determinate = typeof value === 'number' && Number.isFinite( value );
	const current = determinate ? Math.min( validMax, Math.max( 0, value ) ) : undefined;
	if ( validMax !== max || ( value !== undefined && ! determinate ) ) warning( 'ProgressIndicator: supply a finite value and a positive finite max, or omit value.' );
	const fraction = determinate ? current / validMax : 0;
	const root = useRef( null );
	const [ width, setWidth ] = useState( 240 );
	const [ running, setRunning ] = useState( true );
	const maskId = `ax-progress-${ useId().replace( /:/g, '' ) }`;
	useEffect( () => {
		const element = root.current;
		let inView = true;
		const sync = () => setRunning( inView && ! document.hidden );
		const intersection = typeof IntersectionObserver === 'function' ? new IntersectionObserver( ( entries ) => {
			inView = entries[ 0 ].isIntersecting; sync();
		} ) : null;
		const resize = ! circular && typeof ResizeObserver === 'function' ? new ResizeObserver( ( entries ) => {
			const next = entries[ 0 ].contentRect.width;
			if ( next > 0 ) setWidth( next );
		} ) : null;
		intersection?.observe( element );
		resize?.observe( element );
		document.addEventListener( 'visibilitychange', sync );
		sync();
		return () => {
			intersection?.disconnect(); resize?.disconnect();
			document.removeEventListener( 'visibilitychange', sync );
		};
	}, [ circular ] );
	const stroke = thick ? 8 : 4;
	const size = ( wavy ? 48 : 40 ) + ( thick ? 4 : 0 );
	const radius = ( size - stroke ) / 2;
	const center = size / 2;
	const height = stroke + ( wavy ? 6 : 0 );
	const y = wavy ? 3 : 0;
	const wavelength = determinate ? 40 : 20;
	const waveLine = wavy && ! circular ? linearPath( width, stroke, wavelength ) : null;
	const length = circular ? 2 * Math.PI * radius : width;
	const activeLength = length * fraction;
	// Caps consume two pixels at either end. Ramp the gap near zero/full.
	const gap = Math.min( 4, activeLength, length - activeLength );
	const activeDash = Math.max( 0, activeLength - stroke );
	const trackStart = circular ? activeLength + gap + stroke / 2 : activeLength + gap;
	const trackEnd = circular ? length - gap - stroke / 2 : length;
	const circle = ( extra ) => <circle cx={ center } cy={ center } r={ radius } { ...extra } />;
	const waveActive = () => circular
		? <g className="ax-progress__wave-phase"><path className="ax-progress__wave-arc" d={ circularPath( true, stroke ) }
			pathLength="300" style={ { '--ax-wave-flat-path': `path("${ circularPath( false, stroke ) }")` } } /></g>
		: <g className="ax-progress__wave-phase">{ [ 1, ...( determinate ? [] : [ 2 ] ) ].map( ( line ) => <path key={ line }
			className="ax-progress__wave-line" data-line={ line } d={ waveLine.path } pathLength={ waveLine.logicalLength }
			style={ { '--ax-wave-flat-path': `path("${ linearPath( width, stroke, wavelength, false ).path }")` } } /> ) }</g>;
	const indeterminateActive = ( masking = false ) => circular
		? circle( { className: 'ax-progress__sweep', strokeDasharray: `${ length * .25 } ${ length }` } )
		: [ 1, 2 ].map( ( line ) => <rect key={ line } className="ax-progress__line" data-line={ line }
			data-cutout={ masking ? '' : undefined } x={ width * .3 } y={ y } width={ width * .3 } height={ stroke } rx={ stroke / 2 } /> );
	return (
		<span { ...props } ref={ root }
			className={ [ 'ax-progress', className ].filter( Boolean ).join( ' ' ) }
			data-circular={ circular ? '' : undefined }
			data-wavy={ wavy ? '' : undefined } data-thick={ thick ? '' : undefined }
			data-indeterminate={ ! determinate ? '' : undefined }
			data-paused={ ! animated || ! running ? '' : undefined }
			role="progressbar" aria-label={ label } aria-valuemin={ 0 } aria-valuemax={ validMax } aria-valuenow={ current }
			style={ { ...style, '--ax-progress-width': `${ width }px`, '--ax-progress-stroke': `${ stroke }px`,
				'--ax-progress-center': `${ center }px`, '--ax-progress-length': length,
				'--md-comp-progress-indicator-linear-height': `${ height }px`,
				'--md-comp-progress-indicator-circular-size': `${ size }px`,
				'--md-comp-progress-indicator-circular-active-indicator-thickness': `${ stroke }px`,
				'--ax-progress-wave-cap': circular ? stroke / length * 100 : stroke / 2,
				'--ax-progress-wave-visible': Math.max( .001, fraction * 100 - ( fraction === 1 ? 0 : stroke / length * 100 ) ),
				'--ax-progress-wave-length': `${ wavelength }px`, '--ax-progress-fraction': fraction,
				...( determinate ? { '--ax-progress-head-1': fraction, '--ax-progress-tail-1': 0, '--ax-progress-sweep': fraction } : {} ),
			} }>
			<svg viewBox={ circular ? `0 0 ${ size } ${ size }` : `0 0 ${ width } ${ height }` } aria-hidden="true" focusable="false">
				{ ! determinate && <defs><mask id={ maskId } maskUnits="userSpaceOnUse" x="0" y="0" width={ circular ? size : width } height={ circular ? size : height }>
					<rect width="100%" height="100%" fill="white" />
					<g className="ax-progress__orbit" stroke="black" fill="black">
						{ indeterminateActive( true ) }
					</g>
				</mask></defs> }
				{ showTrack && ( ! determinate
					? ( circular ? circle( { className: 'ax-progress__track', mask: `url(#${ maskId })` } ) : <rect className="ax-progress__track" width={ width } y={ y } height={ stroke } rx={ stroke / 2 } mask={ `url(#${ maskId })` } /> )
					: fraction === 0
						? ( circular ? circle( { className: 'ax-progress__track' } ) : <rect className="ax-progress__track" width={ width } y={ y } height={ stroke } rx={ stroke / 2 } /> )
						: fraction < 1 && trackEnd > trackStart && ( circular
							? circle( { className: 'ax-progress__track', strokeDasharray: `${ trackEnd - trackStart } ${ length }`, strokeDashoffset: -trackStart } )
							: <rect className="ax-progress__track" x={ trackStart } width={ trackEnd - trackStart } y={ y } height={ stroke } rx={ stroke / 2 } /> ) ) }
				{ wavy ? ( ! determinate || fraction > 0 ) && <g className={ `ax-progress__active${ determinate ? '' : ' ax-progress__orbit' }` }>{ waveActive() }</g>
					: determinate ? fraction > 0 && ( circular
						? circle( { className: 'ax-progress__active', strokeDasharray: fraction === 1 ? undefined : `${ activeDash } ${ length }`, strokeDashoffset: -stroke / 2 } )
						: <rect className="ax-progress__active" width={ activeLength } height={ stroke } rx={ stroke / 2 } /> )
						: <g className="ax-progress__orbit ax-progress__active">{ indeterminateActive() }</g> }
				{ ! circular && determinate && showStop && fraction < 1 && <circle className="ax-progress__stop" cx={ width - 2 } cy={ height / 2 } r="2" /> }
			</svg>
		</span>
	);
}
