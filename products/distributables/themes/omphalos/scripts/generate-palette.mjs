#!/usr/bin/env node
/**
 * Generate M3 reference tonal palettes with Google's own colour algorithms.
 *
 * The parent theme owns the base palettes as literal hexes in tokens.ref.css.
 * Producing a NEW palette by hand is not possible honestly: M3 tones are points
 * in HCT, not a lightness ramp over sRGB, so a hand-picked ramp is an invented
 * palette wearing M3's token names. This calls the real implementation instead
 * -- @material/material-color-utilities is Google's own, the same algorithms
 * behind the Material Theme Builder.
 *
 * Usage:
 *   node scripts/generate-palette.mjs <name> <#seed> [--tones 0,10,...]
 *   node scripts/generate-palette.mjs --verify
 *
 * Packaging note: the package's index.js reaches into ./dynamiccolor/ with an
 * extensionless import that Node's ESM resolver rejects, so this imports the
 * submodule directly. Everything below tonal_palette.js uses real extensions.
 */

import { TonalPalette } from '../node_modules/@material/material-color-utilities/palettes/tonal_palette.js';

/** M3's reference palette tone stops, as the parent theme publishes them. */
const DEFAULT_TONES = [ 0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 95, 98, 99, 100 ];

const hex = ( value ) =>
	'#' +
	[ 16, 8, 0 ]
		.map( ( shift ) => ( ( value >> shift ) & 255 ).toString( 16 ).padStart( 2, '0' ) )
		.join( '' )
		.toUpperCase();

const argb = ( value ) => 0xff000000 | parseInt( value.replace( '#', '' ), 16 );

const channels = ( value ) => [ 1, 3, 5 ].map( ( i ) => parseInt( value.slice( i, i + 2 ), 16 ) );

const drift = ( a, b ) => {
	const bc = channels( b );
	return Math.max( ...channels( a ).map( ( c, i ) => Math.abs( c - bc[ i ] ) ) );
};

const palette = ( seed, tones ) => {
	const p = TonalPalette.fromInt( argb( seed ) );
	return tones.map( ( tone ) => [ tone, hex( p.tone( tone ) ) ] );
};

/**
 * The parent's published baseline primary palette, with M3's baseline seed.
 *
 * Running --verify does NOT reproduce it exactly, and that is the point of
 * having it. The seed tone and the endpoints land exactly; every other tone
 * drifts, mostly by one step in a channel -- #22005D against the published
 * #21005D -- and at most by two, at tone 50, #8069BF against #7F67BE. The
 * parent ships M3's published table and this library computes the same tones
 * with slightly different rounding.
 *
 * The rule that follows: the published baseline is canonical and stays the
 * parent's. Regenerating it would churn every token for no gain. This generator
 * is for seeds M3 has not published a table for.
 */
const BASELINE = {
	primary: {
		seed: '#6750A4',
		published: {
			0: '#000000', 10: '#21005D', 20: '#381E72', 30: '#4F378B', 40: '#6750A4',
			50: '#7F67BE', 60: '#9A82DB', 70: '#B69DF8', 80: '#D0BCFF', 90: '#EADDFF',
			95: '#F6EDFF', 98: '#FEF7FF', 99: '#FFFBFE', 100: '#FFFFFF',
		},
	},
};

/**
 * Assert the invariant a generated palette has to satisfy to be trustworthy:
 * the seed round-trips exactly at its own tone, and no tone drifts more than
 * two steps per channel from the published table.
 *
 * The bound is two because that is what the measurement said, not what looked
 * tidy: one was tried first and tone 50 failed it. This is a regression guard
 * on the library, not a claim that two is meaningful -- if a future version
 * drifts further, the palettes it generates deserve another look before use.
 */
function verify() {
	let failures = 0;

	for ( const [ name, { seed, published } ] of Object.entries( BASELINE ) ) {
		console.log( '\n' + name + '  seed ' + seed );

		for ( const [ tone, want ] of Object.entries( published ) ) {
			const got = hex( TonalPalette.fromInt( argb( seed ) ).tone( Number( tone ) ) );
			const exact = want.toUpperCase() === seed.toUpperCase();
			const limit = exact ? 0 : 2;
			const distance = drift( got, want );
			const pass = distance <= limit;

			if ( ! pass ) {
				failures++;
			}

			console.log(
				'  ' + ( pass ? ' ' : '!' ) +
					' tone ' + String( tone ).padStart( 3 ) +
					'   generated ' + got +
					'   published ' + want +
					'   drift ' + distance +
					( exact ? '  (seed tone, must be 0)' : '' )
			);
		}
	}

	console.log(
		failures
			? '\n' + failures + ' tone(s) outside the allowed drift'
			: '\nWithin tolerance: the seed tone is exact and every other tone is within two steps per channel.'
	);

	process.exit( failures ? 1 : 0 );
}

const args = process.argv.slice( 2 );

if ( args[ 0 ] === '--verify' ) {
	verify();
}

const [ name, seed ] = args;

if ( ! name || ! seed ) {
	console.error( 'usage: generate-palette.mjs <name> <#seed> [--tones 0,10,...]' );
	console.error( '       generate-palette.mjs --verify' );
	process.exit( 2 );
}

const tonesFlag = args.indexOf( '--tones' );
const tones =
	tonesFlag === -1
		? DEFAULT_TONES
		: args[ tonesFlag + 1 ].split( ',' ).map( ( tone ) => Number( tone.trim() ) );

console.log( '\t/* ' + name + ', generated from ' + seed.toUpperCase() + ' */' );

for ( const [ tone, value ] of palette( seed, tones ) ) {
	console.log( '\t' + ( '--md-ref-palette-' + name + '-' + tone + ':' ).padEnd( 34 ) + ' ' + value + ';' );
}
