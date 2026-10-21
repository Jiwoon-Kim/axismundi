const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const geometry = require( '../assets/circular-wave.json' );
const { circularPath, linearPath } = require( '../wave-geometry.cjs' );

test( 'export has matched, continuous, closed circle/star cubics and nine lobes', () => {
	assert.equal( geometry.vertices, 9 );
	assert.equal( geometry.circle.length, geometry.wave.length );
	for ( const curves of [ geometry.circle, geometry.wave ] ) {
		for ( let i = 0; i < curves.length; ++i ) {
			const curve = curves[ i ], next = curves[ ( i + 1 ) % curves.length ];
			assert.equal( curve.length, 8 ); assert.ok( curve.every( Number.isFinite ) );
			assert.ok( Math.hypot( curve[ 6 ] - next[ 0 ], curve[ 7 ] - next[ 1 ] ) < .00001 );
		}
	}
	for ( const stroke of [ 4, 8 ] ) {
		const wave = circularPath( true, stroke ), flat = circularPath( false, stroke );
		assert.equal( wave.match( /C/g ).length, geometry.wave.length * 3 );
		assert.equal( wave.replace( /[-\d.]+/g, '' ), flat.replace( /[-\d.]+/g, '' ) );
		assert.doesNotMatch( wave, /NaN|Infinity/ );
	}
} );

test( 'linear quadratic geometry extends two wavelengths with bounded amplitude', () => {
	for ( const width of [ 40, 240, 758 ] ) for ( const stroke of [ 4, 8 ] ) for ( const wavelength of [ 20, 40 ] ) {
		const { path, logicalLength } = linearPath( width, stroke, wavelength );
		assert.ok( logicalLength >= width + 2 * wavelength );
		assert.ok( path.includes( ' Q' ) ); assert.doesNotMatch( path, /NaN|Infinity/ );
		assert.equal( path.match( /Q/g ).length, logicalLength / ( wavelength / 2 ) );
	}
} );
