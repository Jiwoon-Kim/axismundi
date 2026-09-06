#!/usr/bin/env node
/**
 * Check the transcribed M3 palettes against the parent theme's own tokens.
 *
 * data/m3-palettes.json is typed out from material.io. Typing 239 hex values by
 * hand is exactly the kind of work that goes wrong silently, and a wrong tone
 * would be indistinguishable from a design decision once it is in a stylesheet.
 *
 * The six baseline palettes are checkable: the parent already ships them in
 * tokens.ref.css, sourced independently. If all 94 of those tones match, the
 * static tones typed in the same pass are trustworthy by the same hand. That is
 * the whole argument, and it is why this file exists rather than a promise that
 * the transcription was careful.
 *
 * Usage: node scripts/check-palettes.mjs
 */

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname( fileURLToPath( import.meta.url ) );
const theme = join( here, '..' );

const data = JSON.parse( readFileSync( join( theme, 'data/m3-palettes.json' ), 'utf8' ) );
const css = readFileSync(
	join( theme, '..', 'axismundi', 'assets/styles/tokens.ref.css' ),
	'utf8'
);

const parent = {};
for ( const match of css.matchAll( /--md-ref-palette-([a-z-]+)-(\d+):\s*(#[0-9A-Fa-f]{6})/g ) ) {
	( parent[ match[ 1 ] ] ??= {} )[ match[ 2 ] ] = match[ 3 ].toUpperCase();
}

let matched = 0;
const problems = [];

for ( const [ name, tones ] of Object.entries( data.baseline ) ) {
	const theirs = parent[ name ];

	if ( ! theirs ) {
		problems.push( `palette ${ name } is not in the parent at all` );
		continue;
	}

	for ( const [ tone, want ] of Object.entries( tones ) ) {
		const got = theirs[ tone ];

		if ( ! got ) {
			problems.push( `  ${ name }-${ tone }  transcribed ${ want }, parent has no such tone` );
		} else if ( got !== want ) {
			problems.push( `  ${ name }-${ tone }  transcribed ${ want }, parent ${ got }` );
		} else {
			matched++;
		}
	}

	const extra = Object.keys( theirs ).filter( ( tone ) => ! ( tone in tones ) );

	if ( extra.length ) {
		problems.push(
			`  ${ name }: parent has tones the published table does not list: ` +
				extra.sort( ( a, b ) => a - b ).join( ', ' )
		);
	}
}

const staticTones = Object.values( data.static ).reduce( ( n, p ) => n + Object.keys( p ).length, 0 );

console.log( 'baseline transcription vs the parent\'s tokens.ref.css' );
console.log( `  ${ matched } match, ${ problems.length } problem(s)` );
problems.forEach( ( line ) => console.log( line ) );
console.log( `\nstatic palettes: ${ Object.keys( data.static ).length }, ${ staticTones } tones` );
console.log( problems.length ? '\nFAIL' : '\nOK -- the static tones came from the same pass.' );

process.exit( problems.length ? 1 : 0 );
