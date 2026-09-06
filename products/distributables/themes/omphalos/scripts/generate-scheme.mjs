#!/usr/bin/env node
/**
 * Generate a complete colour scheme from one seed, as a WordPress style variation.
 *
 * Why this exists rather than picking from data/m3-palettes.json: the twelve
 * static palettes are standalone semantic colours, not scheme families. Take
 * Blue as a primary and there is no published table at its hue + 60 to serve as
 * tertiary, and none at its hue with low chroma to serve as secondary. Static
 * palettes answer "what colour is success"; they cannot answer "what is the
 * whole scheme". That needs computing, and this is the computation.
 *
 * The constraints below are M3's TonalSpot rules, recovered by measuring the
 * baseline families rather than taken on faith, and checked with --verify:
 * applied to the baseline source hue they reproduce the published secondary,
 * tertiary, neutral and neutral-variant to within one step per channel, and
 * neutral exactly. Change a constant here and run --verify to see what it costs
 * against the published reference.
 *
 * Error is not generated and not included. M3 keeps it at a fixed hue across
 * schemes, so it stays whatever the parent (or a transformation over it) says.
 *
 * Usage:
 *   node scripts/generate-scheme.mjs <Title> <#seed> > styles/<slug>.json
 *   node scripts/generate-scheme.mjs --verify
 */

import { TonalPalette } from '../node_modules/@material/material-color-utilities/palettes/tonal_palette.js';
import { Hct } from '../node_modules/@material/material-color-utilities/hct/hct.js';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname( fileURLToPath( import.meta.url ) );
const published = JSON.parse(
	readFileSync( join( here, '..', 'data/m3-palettes.json' ), 'utf8' )
).baseline;

const argb = ( value ) => 0xff000000 | parseInt( value.replace( '#', '' ), 16 );

const hex = ( value ) =>
	'#' +
	[ 16, 8, 0 ]
		.map( ( shift ) => ( ( value >> shift ) & 255 ).toString( 16 ).padStart( 2, '0' ) )
		.join( '' )
		.toUpperCase();

const channels = ( value ) => [ 1, 3, 5 ].map( ( i ) => parseInt( value.slice( i, i + 2 ), 16 ) );

const drift = ( a, b ) => {
	const other = channels( b );
	return Math.max( ...channels( a ).map( ( c, i ) => Math.abs( c - other[ i ] ) ) );
};

/**
 * Neutral tones M3 tunes rather than derives.
 *
 * The surface-container stops were added to the scale later and do not fall out
 * of a single chroma value: generated at chroma 6 they land up to three steps
 * off the published table, worst at tone 94 (#F2ECF4 against #F3EDF7), while
 * every classic stop stays within one. Three parts in 255 is not visible, but
 * it is a real difference, and the check names it rather than loosening the
 * tolerance everywhere to hide it.
 *
 * Found by checking all seventeen consumed neutral tones. An earlier probe over
 * four of them reported neutral as exact, which it is not.
 */
const TUNED_NEUTRAL_TONES = [ 4, 6, 12, 17, 22, 24, 87, 92, 94, 96 ];

/** M3 TonalSpot, measured off the baseline families. Primary keeps the seed. */
const FAMILIES = {
	secondary: { hueShift: 0, chroma: 16 },
	tertiary: { hueShift: 60, chroma: 24 },
	neutral: { hueShift: 0, chroma: 6 },
	'neutral-variant': { hueShift: 0, chroma: 8 },
};

/** Exactly the tones the parent's sys layer reads. Anything else is dead weight. */
const CONSUMED = {
	primary: [ 20, 30, 40, 80, 90, 100 ],
	secondary: [ 20, 30, 40, 80, 90, 100 ],
	tertiary: [ 20, 30, 40, 80, 90, 100 ],
	neutral: [ 0, 4, 6, 10, 12, 17, 20, 22, 24, 87, 90, 92, 94, 95, 96, 98, 100 ],
	'neutral-variant': [ 30, 50, 60, 80, 90 ],
};

/**
 * Build a scheme, optionally taking the primary family from a published table.
 *
 * Generating the primary family from its own tone 40 does NOT reproduce the
 * published palette, and not by a rounding margin. Measured across the twelve
 * static palettes at only the tones a scheme consumes, the worst case is Yellow
 * at tone 80: generated #FFB77C against the published #FCBD00, a pale orange
 * where M3 publishes a vivid amber. Cyan is 38 off at tone 80, Green 34, Blue
 * variant 31, Pink 23, Purple 22, Red 21.
 *
 * M3's static palettes hold far more chroma through the light tones than a
 * plain tonal palette built at the seed's hue and chroma does. They are tuned,
 * not derived. So where a published table exists it is used verbatim, and every
 * consumed tone (20, 30, 40, 80, 90, 100) is present in all eleven of them.
 *
 * The other families have no published counterpart at these hues -- M3 does not
 * publish a secondary for Orange -- so those stay generated, which is the only
 * honest option for them.
 *
 * @param {string}      seed      Source colour.
 * @param {Object|null} publishedPrimary Published tone table for the primary family.
 */
function scheme( seed, publishedPrimary = null ) {
	const source = Hct.fromInt( argb( seed ) );
	const palettes = { primary: TonalPalette.fromInt( argb( seed ) ) };

	for ( const [ family, rule ] of Object.entries( FAMILIES ) ) {
		/*
		 * Never derive a family more chromatic than the seed.
		 *
		 * M3's constants assume a saturated source. Applied to a near-neutral
		 * one they invert the scheme: measured on the published static Grey,
		 * whose tone 40 has chroma 1.6, the constants produce a secondary at 16
		 * and a tertiary at 24 -- both far more colourful than the primary they
		 * are supposed to sit under. Static Grey variant does the same at 3.6.
		 *
		 * Clamping to the seed's own chroma makes a neutral seed give a neutral
		 * scheme, which is what asking for Grey means. It touches nothing else:
		 * every other published static palette has a tone-40 chroma between
		 * 35.9 and 81.1, all above the largest constant here.
		 */
		palettes[ family ] = TonalPalette.fromHueAndChroma(
			source.hue + rule.hueShift,
			Math.min( rule.chroma, source.chroma )
		);
	}

	const tokens = [];

	for ( const [ family, tones ] of Object.entries( CONSUMED ) ) {
		for ( const tone of tones ) {
			const fromTable =
				family === 'primary' && publishedPrimary ? publishedPrimary[ String( tone ) ] : null;

			tokens.push( [
				`--md-ref-palette-${ family }-${ tone }`,
				fromTable ?? hex( palettes[ family ].tone( tone ) ),
			] );
		}
	}

	return { source, tokens };
}

function verify() {
	const seed = published.primary[ '40' ];
	const source = Hct.fromInt( argb( seed ) );
	let worst = 0;

	console.log( `source ${ seed }   hue ${ source.hue.toFixed( 1 ) }   chroma ${ source.chroma.toFixed( 1 ) }\n` );

	for ( const [ family, rule ] of Object.entries( FAMILIES ) ) {
		const palette = TonalPalette.fromHueAndChroma( source.hue + rule.hueShift, rule.chroma );
		const table = published[ family ];
		let familyWorst = 0;
		let tunedWorst = 0;

		for ( const tone of CONSUMED[ family ] ) {
			const want = table[ String( tone ) ];

			if ( ! want ) {
				continue;
			}

			const distance = drift( hex( palette.tone( tone ) ), want );

			if ( family === 'neutral' && TUNED_NEUTRAL_TONES.includes( tone ) ) {
				tunedWorst = Math.max( tunedWorst, distance );
			} else {
				familyWorst = Math.max( familyWorst, distance );
			}
		}

		worst = Math.max( worst, familyWorst );
		console.log(
			`  ${ family.padEnd( 16 ) } hue ${ ( source.hue + rule.hueShift ).toFixed( 1 ).padStart( 5 ) }` +
				`  chroma ${ String( rule.chroma ).padStart( 2 ) }   worst drift ${ familyWorst }` +
				( tunedWorst ? `   (tuned surface stops: ${ tunedWorst })` : '' )
		);
	}

	console.log(
		worst <= 1
			? '\nOK -- on every tone M3 derives, the constraints reproduce the published baseline\nwithin one step per channel. The tuned surface-container stops are reported\nseparately and run to three.'
			: `\nFAIL -- worst drift ${ worst } on a derived tone, above the one step these constraints are supposed to hold to.`
	);

	process.exit( worst <= 1 ? 0 : 1 );
}

const args = process.argv.slice( 2 );

if ( args[ 0 ] === '--verify' ) {
	verify();
}

/*
 * --css <slug> emits the scheme gated behind an attribute rather than as a
 * variation file. That is what the Theme Controls plugin ships, and it is a
 * better mechanism than the variation this script was written for.
 *
 * `:root[data-ax-scheme="blue"]` is specificity (0,2,0) against the parent's
 * bare `:root` at (0,1,0), so it wins on specificity and never on order.
 * Measured: a stylesheet inserted FIRST in head, ahead of every one of the
 * theme's own, still takes effect the moment the attribute is set. That is why
 * a pre-generated scheme needs no adoptedStyleSheets, no late enqueue and no
 * colour library on the page -- setting one attribute is the entire runtime,
 * which is also what lets it happen before first paint.
 */
const cssFlag = args.indexOf( '--css' );
const slug = cssFlag === -1 ? null : args[ cssFlag + 1 ];

const publishedFlag = args.indexOf( '--published' );
const publishedName = publishedFlag === -1 ? null : args[ publishedFlag + 1 ];

const consumedByFlags = new Set( [ cssFlag, cssFlag + 1, publishedFlag, publishedFlag + 1 ] );
const positional = args.filter( ( _, i ) => ! consumedByFlags.has( i ) );

const staticPalettes = JSON.parse(
	readFileSync( join( here, '..', 'data/m3-palettes.json' ), 'utf8' )
).static;

let [ title, seed ] = positional;

if ( publishedName ) {
	const table = staticPalettes[ publishedName ];

	if ( ! table ) {
		console.error( `unknown published palette: ${ publishedName }` );
		console.error( `known: ${ Object.keys( staticPalettes ).join( ', ' ) }` );
		process.exit( 2 );
	}

	// The seed is the table's own tone 40, so it never has to be typed twice
	// and can never disagree with the table it is supposed to describe.
	seed = seed || table[ '40' ];
}

if ( ! title || ! seed ) {
	console.error( 'usage: generate-scheme.mjs <Title> <#seed> [--css <slug>]' );
	console.error( '       generate-scheme.mjs <Title> --published <palette> [--css <slug>]' );
	console.error( '       generate-scheme.mjs --verify' );
	process.exit( 2 );
}

const { source, tokens } = scheme( seed, publishedName ? staticPalettes[ publishedName ] : null );

/*
 * The scheme is carried as styles.css, not as settings.color.palette.
 *
 * The parent's palette entries are all var(--md-sys-color-*) and its
 * settings.custom is empty: every actual colour value lives in CSS, and
 * theme.json only names them. A variation that rewrote the palette with
 * literals would break that indirection and still leave everything styled
 * through --md-sys-color-* on the old scheme. Redefining the reference tokens
 * is the only edit that reaches all of it, and styles.css is how a variation
 * emits raw CSS.
 */
const css =
	`/* ${ title } — generated from ${ seed.toUpperCase() }, ` +
	`hue ${ source.hue.toFixed( 1 ) } chroma ${ source.chroma.toFixed( 1 ) }. ` +
	`Error is untouched: M3 holds it at a fixed hue across schemes. */\n` +
	':root{' +
	tokens.map( ( [ name, value ] ) => `${ name }:${ value };` ).join( '' ) +
	'}';

if ( slug ) {
	console.log(
		`/* ${ title } -- generated from ${ seed.toUpperCase() }, ` +
			`hue ${ source.hue.toFixed( 1 ) } chroma ${ source.chroma.toFixed( 1 ) }. */`
	);
	console.log( `:root[data-ax-scheme="${ slug }"] {` );
	tokens.forEach( ( [ name, value ] ) => console.log( `\t${ ( name + ':' ).padEnd( 34 ) } ${ value };` ) );
	console.log( '}' );
} else {
	console.log(
		JSON.stringify(
			{
				$schema: 'https://schemas.wp.org/wp/7.1/theme.json',
				version: 3,
				title,
				styles: { css },
			},
			null,
			'\t'
		)
	);
}
