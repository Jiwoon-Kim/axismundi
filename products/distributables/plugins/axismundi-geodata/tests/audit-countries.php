<?php
/**
 * The country registry, and what it is for.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit( 1 );

// This is normally an admin-only include; the audit exercises the markup that term.php receives.
require_once dirname( __DIR__ ) . '/includes/term-fields.php';

$ax_geo_iso_results = array();

/** @param bool[] $results Results. */
function ax_geo_iso_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

$ax_geo_iso_all = axismundi_geodata_countries();

/*
 * Every officially assigned code, and only those. 249 is what the standard assigns; a list that has
 * drifted from it is one this site would be quietly wrong about somewhere.
 */
ax_geo_iso_assert(
	$ax_geo_iso_results,
	'the countries there are, all of them, each with the three ways of writing it down',
	249 === count( $ax_geo_iso_all )
		&& count( $ax_geo_iso_all ) === count( array_unique( array_column( $ax_geo_iso_all, 'alpha_3' ) ) )
		&& count( $ax_geo_iso_all ) === count( array_unique( array_column( $ax_geo_iso_all, 'numeric' ) ) )
		&& array( 'alpha_3' => 'KOR', 'numeric' => '410', 'name' => 'Korea, Republic of' ) === $ax_geo_iso_all['KR']
		&& array( 'alpha_3' => 'JPN', 'numeric' => '392', 'name' => 'Japan' ) === $ax_geo_iso_all['JP']
);

/*
 * Shaped the way the standard shapes them, so a code that is not one is recognisably not one.
 */
$ax_geo_iso_shaped = true;
foreach ( $ax_geo_iso_all as $ax_geo_iso_code => $ax_geo_iso_record ) {
	if ( 1 !== preg_match( '/^[A-Z]{2}$/', (string) $ax_geo_iso_code )
		|| 1 !== preg_match( '/^[A-Z]{3}$/', (string) $ax_geo_iso_record['alpha_3'] )
		|| 1 !== preg_match( '/^[0-9]{3}$/', (string) $ax_geo_iso_record['numeric'] )
		|| '' === trim( (string) $ax_geo_iso_record['name'] ) ) {
		$ax_geo_iso_shaped = false;
	}
}
ax_geo_iso_assert( $ax_geo_iso_results, 'and each of them written the way the standard writes it', $ax_geo_iso_shaped );

/*
 * Places with no telephone numbering plan are still places somebody lives. These seven are exactly
 * what a country list taken from phone number rules leaves out, which is why that was the wrong
 * place to take one from.
 */
ax_geo_iso_assert(
	$ax_geo_iso_results,
	'somewhere with no telephone numbering plan is still somewhere, and is on the list',
	isset( $ax_geo_iso_all['AQ'], $ax_geo_iso_all['BV'], $ax_geo_iso_all['GS'], $ax_geo_iso_all['HM'], $ax_geo_iso_all['PN'], $ax_geo_iso_all['TF'], $ax_geo_iso_all['UM'] )
);

/*
 * And what the standard does not assign is not offered. `AC` and `TA` are exceptionally reserved and
 * `XK` is user-assigned: a picker offering them would be offering something a reader elsewhere may
 * not recognise. A document that arrived carrying one is a different matter -- asking is not
 * refusing, and nothing here rewrites a code somebody else wrote.
 */
ax_geo_iso_assert(
	$ax_geo_iso_results,
	'what the standard does not assign is not offered, and asking is not refusing',
	! axismundi_geodata_is_country_code( 'AC' )
		&& ! axismundi_geodata_is_country_code( 'XK' )
		&& axismundi_geodata_is_country_code( 'kr' )
		&& '' === axismundi_geodata_country_name( 'ZZ' )
		&& 'Japan' === axismundi_geodata_country_name( 'JP' )
);

/*
 * Names are a fallback and never what a person is shown: `JP` reads as `Japan`, `일본` or `日本`
 * depending on who is reading, and the platform already knows that in every language a browser has.
 * A translation stored here would be this plugin maintaining a worse copy of it.
 */
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
$ax_geo_iso_src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/iso-3166-1.generated.php' );
ax_geo_iso_assert(
	$ax_geo_iso_results,
	'the names are a fallback, not a translation table this would have to keep up',
	! str_contains( $ax_geo_iso_src, '__(' )
		&& ! str_contains( $ax_geo_iso_src, 'esc_html' )
		// Generated from a source file rather than typed, and versioned so updating it is a decision.
		&& str_contains( $ax_geo_iso_src, 'Run: powershell -File tools/generate-iso-3166-1.ps1' )
		&& is_readable( dirname( __DIR__ ) . '/data/iso-3166-1.tsv' )
		&& 1 === preg_match( '/^\d{4}-\d{2}$/', AXISMUNDI_GEODATA_ISO_3166_1_EDITION )
);

/*
 * A registry, not 249 rows in somebody's database. Every installation can say `KR`; almost none of
 * them needs a Country term for each of them, and one is made when somewhere actually has to be a
 * place in this site's geography.
 */
// A bare audit bootstrap may not have registered the taxonomy yet; that is still zero persisted terms.
$ax_geo_iso_term_count = wp_count_terms( array( 'taxonomy' => 'ax_geo_area', 'hide_empty' => false ) );
$ax_geo_iso_term_count = is_wp_error( $ax_geo_iso_term_count ) ? 0 : (int) $ax_geo_iso_term_count;
ax_geo_iso_assert(
	$ax_geo_iso_results,
	'knowing every country does not mean keeping a term for every country',
	0 === $ax_geo_iso_term_count
		|| count( $ax_geo_iso_all ) > $ax_geo_iso_term_count
);

/*
 * A subdivision code has two jobs: it is a stable code somebody may import, and it tells GeoData
 * which Country term it can belong under. It is not a display-name table or a claim that every
 * country has a uniform province/county hierarchy.
 */
$ax_geo_iso_subdivisions = axismundi_geodata_subdivisions();
ax_geo_iso_assert(
	$ax_geo_iso_results,
	'the subdivision register holds the CLDR snapshot’s codes under the countries that assign them',
	5046 === count( $ax_geo_iso_subdivisions )
		&& 'KR' === (string) ( $ax_geo_iso_subdivisions['KR-26'] ?? '' )
		&& 'JP' === (string) ( $ax_geo_iso_subdivisions['JP-13'] ?? '' )
		&& 'US' === (string) ( $ax_geo_iso_subdivisions['US-DC'] ?? '' )
		&& AXISMUNDI_GEODATA_ISO_3166_2_EDITION === 'CLDR-48.2'
);

ax_geo_iso_assert(
	$ax_geo_iso_results,
	'a subdivision’s country is available in either direction without turning terms into a global tree',
	axismundi_geodata_is_subdivision_code( 'kr-26' )
		&& ! axismundi_geodata_is_subdivision_code( 'KR-XX' )
		&& 'KR' === axismundi_geodata_subdivision_country_code( 'KR-26' )
		&& '' === axismundi_geodata_subdivision_country_code( 'KR-XX' )
		&& array( 'KR-11' => 'KR', 'KR-26' => 'KR' ) === array_intersect_key(
			axismundi_geodata_subdivisions_for_country( 'KR' ),
			array_flip( array( 'KR-11', 'KR-26' ) )
		)
);

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's generated runtime source in a dev fixture.
$ax_geo_iso_2_src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/iso-3166-2.generated.php' );
ax_geo_iso_assert(
	$ax_geo_iso_results,
	'the registry comes from a versioned Unicode CLDR input and carries its licence',
	str_contains( $ax_geo_iso_2_src, 'Unicode CLDR 48.2 subdivision containment (Unicode-3.0).' )
		&& is_readable( dirname( __DIR__ ) . '/data/iso-3166-2.tsv' )
		&& is_readable( dirname( __DIR__ ) . '/data/LICENSE-UNICODE-3.0.txt' )
		&& str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/data/LICENSE-UNICODE-3.0.txt' ), 'UNICODE LICENSE V3' )
);

/*
 * A Geo Area Country is authored from the registry, not by guessing two letters. This changes only
 * the term-editor control: REST/import sanitisation remains deliberately tolerant of a newer or
 * exceptional code, and a value already on a term must remain visible until somebody changes it.
 */
$ax_geo_iso_country_select = axismundi_geodata_country_code_select( 'ax_geo_country_code', 'KR' );
$ax_geo_iso_unknown_select = axismundi_geodata_country_code_select( 'ax_geo_country_code', 'XK' );
ax_geo_iso_assert(
	$ax_geo_iso_results,
	'a Country term chooses one registered code, while an imported unknown code is retained rather than erased',
	str_contains( $ax_geo_iso_country_select, '<select name="ax_geo_country_code" id="ax_geo_country_code">' )
		&& 250 === substr_count( $ax_geo_iso_country_select, '<option ' )
		&& str_contains( $ax_geo_iso_country_select, 'Korea, Republic of (KR)' )
		&& str_contains( $ax_geo_iso_unknown_select, 'XK (not in this registry)' )
		&& ! str_contains( $ax_geo_iso_country_select, '<input type="text"' )
);

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_geo_iso_results ), count( array_filter( $ax_geo_iso_results, static fn( bool $ok ) : bool => ! $ok ) ) );
if ( in_array( false, $ax_geo_iso_results, true ) ) {
	exit( 1 );
}
