/* Copyright 2024 The Android Open Source Project
 * SPDX-License-Identifier: Apache-2.0
 * Web path adaptation of pinned AndroidX wavy progress; see LICENSE.md.
 */
const geometry = require( './assets/circular-wave.json' );

// Matched AndroidX cubics, repeated so phase-shifted segments never hit a seam.
function circularPath( wave, stroke ) {
	const curves = wave ? geometry.wave : geometry.circle;
	const diameter = 44;
	const n = ( value ) => Number( ( value * diameter + stroke / 2 ).toFixed( 5 ) );
	let path = `M${ n( curves[ 0 ][ 0 ] ) } ${ n( curves[ 0 ][ 1 ] ) }`;
	for ( let cycle = 0; cycle < 3; ++cycle ) {
		for ( const curve of curves ) path += ` C${ curve.slice( 2 ).map( n ).join( ' ' ) }`;
	}
	return path;
}

// Compose uses alternating quadratic control points, not sampled sine waves.
function linearPath( width, stroke, wavelength, wave = true ) {
	const center = ( stroke + 6 ) / 2;
	const half = wavelength / 2;
	let path = `M0 ${ center }`;
	for ( let i = 0; i < Math.ceil( ( width + 2 * wavelength ) / half ); ++i ) {
		path += ` Q${ ( i + .5 ) * half } ${ center + ( wave ? ( i % 2 ? -6 : 6 ) : 0 ) } ${ ( i + 1 ) * half } ${ center }`;
	}
	return { path, logicalLength: Math.ceil( ( width + 2 * wavelength ) / half ) * half };
}

module.exports = { circularPath, linearPath };
