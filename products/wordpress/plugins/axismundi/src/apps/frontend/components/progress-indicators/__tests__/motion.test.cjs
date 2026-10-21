const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const postcss = require( 'postcss' );
const css = fs.readFileSync( path.resolve( __dirname, '../../../styles/components/progress-indicator.css' ), 'utf8' );

// Independent transcription of pinned Compose linear and MDC advance specs, in ms.
const reference = {
	'head-1': [ 1750, [ [ 0, 0 ], [ 1000, 1 ], [ 1750, 1 ] ] ],
	'tail-1': [ 1750, [ [ 0, 0 ], [ 250, 0 ], [ 1250, 1 ], [ 1750, 1 ] ] ],
	'head-2': [ 1750, [ [ 0, 0 ], [ 650, 0 ], [ 1500, 1 ], [ 1750, 1 ] ] ],
	'tail-2': [ 1750, [ [ 0, 0 ], [ 900, 0 ], [ 1750, 1 ] ] ],
	'global-turn': [ 5400, [ [ 0, 0 ], [ 5400, 1520 ] ] ],
	'arc-head': [ 5400, [ [ 0, 0 ], [ 667, 250 ], [ 1350, 250 ], [ 2017, 500 ], [ 2700, 500 ], [ 3367, 750 ], [ 4050, 750 ], [ 4717, 1000 ], [ 5400, 1000 ] ] ],
	'arc-tail': [ 5400, [ [ 0, 0 ], [ 667, 0 ], [ 1334, 250 ], [ 2017, 250 ], [ 2684, 500 ], [ 3367, 500 ], [ 4034, 750 ], [ 4717, 750 ], [ 5384, 1000 ], [ 5400, 1000 ] ] ],
};
function validate( source ) {
	const ast = postcss.parse( source );
	for ( const [ name, [ duration, expected ] ] of Object.entries( reference ) ) {
		const property = `--ax-progress-${ name === 'global-turn' ? 'turn' : name }`;
		const observed = [];
		ast.walkAtRules( 'keyframes', ( rule ) => {
			if ( rule.params !== `ax-progress-${ name }` ) return;
			rule.each( ( frame ) => frame.walkDecls( property, ( declaration ) => {
				for ( const selector of frame.selector.split( ',' ) ) {
					const part = selector.trim();
					observed.push( [ part === 'from' ? 0 : part === 'to' ? duration : parseFloat( part ) / 100 * duration, Number( declaration.value ) ] );
				}
			} ) );
		} );
		assert.equal( observed.length, expected.length, name );
		expected.forEach( ( [ time, value ], i ) => {
			assert.ok( Math.abs( time - observed[ i ][ 0 ] ) < .001, `${ name } ${ time }ms` );
			assert.equal( observed[ i ][ 1 ], value, `${ name } ${ time }ms value` );
		} );
		assert.ok( source.includes( `ax-progress-${ name } ${ duration }ms` ), `${ name } period` );
	}
	assert.match( source, /animation-timing-function: var\(--md-sys-motion-easing-emphasized-accelerate\)/ );
	assert.ok( source.includes( 'ax-progress-head-1 1750ms var(--md-sys-motion-easing-emphasized-accelerate)' ), 'root easing cannot silently become linear' );
	assert.ok( source.includes( 'ax-progress-arc-head 5400ms cubic-bezier(.4, 0, .2, 1)' ), 'head uses upstream FastOutSlowIn' );
	assert.ok( source.includes( 'ax-progress-arc-tail 5400ms cubic-bezier(.4, 0, .2, 1)' ), 'tail uses upstream FastOutSlowIn' );
}
test( 'CSS clocks match Compose linear and MDC Android advance circular milestones', () => validate( css ) );

test( 'advance loop joins at the same angle and sweep without a reset jump', () => {
	const rotation = reference[ 'global-turn' ][ 1 ].at( -1 )[ 1 ];
	const head = reference[ 'arc-head' ][ 1 ].at( -1 )[ 1 ];
	const tail = reference[ 'arc-tail' ][ 1 ].at( -1 )[ 1 ];
	assert.equal( ( rotation + tail ) % 360, 0 );
	assert.equal( 20 + head - tail, 20 );
} );
test( 'motion comparison rejects timing, amplitude and rotation mutations', () => {
	for ( const [ original, changed ] of [ [ '57.142857%', '60%' ], [ '--ax-progress-arc-head: 250', '--ax-progress-arc-head: 200' ], [ '--ax-progress-turn: 1520', '--ax-progress-turn: 360' ], [ '1750ms var(--md-sys-motion-easing-emphasized-accelerate)', '1750ms linear' ] ] ) {
		assert.throws( () => validate( css.replace( original, changed ) ) );
	}
} );
