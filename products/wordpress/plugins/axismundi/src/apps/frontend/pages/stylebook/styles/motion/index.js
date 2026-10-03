import { Scaffold } from '../../../../foundations/layout/scaffold';
import { useEffect, useRef, useState } from '@wordpress/element';

const DURATION_GROUPS = [
	{ name: 'Short', tokens: [ 'short1', 'short2', 'short3', 'short4' ] },
	{ name: 'Medium', tokens: [ 'medium1', 'medium2', 'medium3', 'medium4' ] },
	{ name: 'Long', tokens: [ 'long1', 'long2', 'long3', 'long4' ] },
	{ name: 'Extra long', tokens: [ 'extra-long1', 'extra-long2', 'extra-long3', 'extra-long4' ] },
];

const EASINGS = [
	'linear',
	'standard',
	'standard-accelerate',
	'standard-decelerate',
	'emphasized',
	'emphasized-accelerate',
	'emphasized-decelerate',
];

const CURVES = [
	{ name: 'Fast spatial', token: 'fast-spatial', kind: 'spatial' },
	{ name: 'Default spatial', token: 'default-spatial', kind: 'spatial' },
	{ name: 'Slow spatial', token: 'slow-spatial', kind: 'spatial' },
	{ name: 'Fast effects', token: 'fast-effects', kind: 'effects' },
	{ name: 'Default effects', token: 'default-effects', kind: 'effects' },
	{ name: 'Slow effects', token: 'slow-effects', kind: 'effects' },
];

function MotionSample( { label, token, type = 'duration' } ) {
	const sampleRef = useRef();
	const [ active, setActive ] = useState( false );
	const [ metrics, setMetrics ] = useState();
	const durationToken = 'curve' === type
		? `--md-sys-motion-curve-${ token }-duration`
		: 'easing' === type
			? '--md-sys-motion-duration-short3'
		: `--md-sys-motion-duration-${ token }`;
	const easingToken = 'curve' === type
		? `--md-sys-motion-curve-${ token }`
		: 'easing' === type
			? `--md-sys-motion-easing-${ token }`
			: '--md-sys-motion-easing-linear';

	useEffect( () => {
		if ( ! sampleRef.current ) {
			return;
		}

		const dot = sampleRef.current.querySelector( '.ax-stylebook-motion__dot' );
		if ( ! dot ) {
			return;
		}

		const computed = window.getComputedStyle( dot );
		setMetrics( {
			duration: computed.transitionDuration,
			easing: computed.transitionTimingFunction,
		} );
	}, [ durationToken, easingToken ] );

	return (
		<button
			aria-pressed={ active }
			className="ax-stylebook-motion__sample"
			onClick={ () => setActive( ( value ) => ! value ) }
			ref={ sampleRef }
			style={ {
				'--ax-motion-duration': `var( ${ durationToken } )`,
				'--ax-motion-easing': `var( ${ easingToken } )`,
			} }
			type="button"
		>
			<span className="ax-stylebook-motion__sample-label">{ label }</span>
			<span className="ax-stylebook-motion__track" aria-hidden="true">
				<span className="ax-stylebook-motion__dot" />
			</span>
			<span className="ax-stylebook-motion__metrics">
				{ metrics ? `${ metrics.duration } · ${ metrics.easing }` : 'Measuring...' }
			</span>
		</button>
	);
}

function DurationReference() {
	return (
		<section className="ax-stylebook-motion__section" aria-labelledby="ax-stylebook-motion-duration-title">
			<header className="ax-stylebook-motion__header">
				<p>Transition compatibility</p>
				<h2 id="ax-stylebook-motion-duration-title">Duration scale</h2>
			</header>
			<div className="ax-stylebook-motion__duration-groups">
				{ DURATION_GROUPS.map( ( group ) => (
					<section className="ax-stylebook-motion__duration-group" key={ group.name } aria-label={ group.name }>
						<h3>{ group.name }</h3>
						{ group.tokens.map( ( token ) => (
							<MotionSample key={ token } label={ token } token={ token } />
						) ) }
					</section>
				) ) }
			</div>
		</section>
	);
}

function EasingReference() {
	return (
		<section className="ax-stylebook-motion__section" aria-labelledby="ax-stylebook-motion-easing-title">
			<header className="ax-stylebook-motion__header">
				<p>Pair with a duration token for transition compatibility</p>
				<h2 id="ax-stylebook-motion-easing-title">Easing set</h2>
			</header>
			<div className="ax-stylebook-motion__samples">
				{ EASINGS.map( ( token ) => <MotionSample key={ token } label={ token } token={ token } type="easing" /> ) }
			</div>
		</section>
	);
}

function CurveReference() {
	const spatial = CURVES.filter( ( curve ) => 'spatial' === curve.kind );
	const effects = CURVES.filter( ( curve ) => 'effects' === curve.kind );

	return (
		<section className="ax-stylebook-motion__section" aria-labelledby="ax-stylebook-motion-curve-title">
			<header className="ax-stylebook-motion__header">
				<p>Theme-provided web conversion</p>
				<h2 id="ax-stylebook-motion-curve-title">Motion physics</h2>
			</header>
			<div className="ax-stylebook-motion__curve-groups">
				<section className="ax-stylebook-motion__curve-group" aria-labelledby="ax-stylebook-motion-spatial-title">
					<header>
						<h3 id="ax-stylebook-motion-spatial-title">Spatial</h3>
						<p>Position, size, shape, and rotation. These curves may overshoot.</p>
					</header>
					<div className="ax-stylebook-motion__samples">
						{ spatial.map( ( curve ) => (
							<MotionSample key={ curve.token } label={ curve.name } token={ curve.token } type="curve" />
						) ) }
					</div>
				</section>
				<section className="ax-stylebook-motion__curve-group" aria-labelledby="ax-stylebook-motion-effects-title">
					<header>
						<h3 id="ax-stylebook-motion-effects-title">Effects</h3>
						<p>Colour and opacity. These curves settle without overshoot.</p>
					</header>
					<div className="ax-stylebook-motion__samples">
						{ effects.map( ( curve ) => (
							<MotionSample key={ curve.token } label={ curve.name } token={ curve.token } type="curve" />
						) ) }
					</div>
				</section>
			</div>
		</section>
	);
}

/**
 * Runtime reference for the motion tokens supplied by the active theme.
 *
 * @return {import('@wordpress/element').ReactNode} Motion reference page.
 */
export function StylebookMotionPage() {
	const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	return (
		<Scaffold className="axismundi-social ax-stylebook ax-stylebook--motion">
			<div className="ax-stylebook-motion">
				<nav className="ax-stylebook-motion__navigation" aria-label="Stylebook">
					<a href="/social/stylebook">Stylebook</a>
					<a href="/social/stylebook/styles">Styles</a>
				</nav>
				<header className="ax-stylebook-motion__page-header">
					<p>Material style</p>
					<h1>Motion</h1>
					<p>{ reducedMotion ? 'Reduced motion is enabled by this device.' : 'Press a sample to replay its transition.' }</p>
				</header>
				<CurveReference />
				<DurationReference />
				<EasingReference />
			</div>
		</Scaffold>
	);
}
