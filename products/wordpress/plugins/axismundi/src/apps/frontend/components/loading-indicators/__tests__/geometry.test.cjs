const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const geometry = require( '../assets/loading-geometry.json' );
const { pathForCubics } = require( '../geometry.cjs' );

test( 'each matched endpoint emits one move, cubic commands, and a close', () => {
	for ( const pair of geometry.pairs ) {
		for ( const cubics of [ pair.start, pair.end ] ) {
			const path = pathForCubics( cubics );
			assert.ok( path.startsWith( `M${ cubics[ 0 ][ 0 ] } ${ cubics[ 0 ][ 1 ] } ` ) );
			assert.ok( path.endsWith( ' Z' ) );
			assert.equal( ( path.match( /C/g ) || [] ).length, cubics.length );
			assert.ok( ! /NaN|undefined|Infinity/.test( path ) );
		}
		assert.equal( pair.start.length, pair.end.length );
	}
} );

test( 'the static alternative is real SoftBurst geometry, and the loop returns to it', () => {
	assert.equal( geometry.shapes[ 0 ].name, 'SoftBurst' );
	assert.ok( pathForCubics( geometry.shapes[ 0 ].cubics ).length > 100 );
	assert.equal( geometry.pairs.at( -1 ).to, geometry.shapes[ 0 ].name );
} );
