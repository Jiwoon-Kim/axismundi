<?php
/**
 * The countries there are.
 *
 * A registry rather than a taxonomy. Every installation needs to be able to say `KR` -- to validate
 * one, to offer a list of them, to know that `ZZ` is not one -- and almost none of them needs 249
 * Country terms in the database to do it. So the codes live here as data, and a Country term is made
 * only when somewhere in the world actually has to be a place in this site's geography.
 *
 * ISO 3166-1 is the authority for which codes exist, and the list is generated from a source file
 * rather than typed here: see `data/iso-3166-1.tsv` and `tools/generate-iso-3166-1.ps1`. It changes
 * -- countries are added, names are revised, codes are retired -- so it is versioned and updated
 * deliberately rather than drifting.
 *
 * What is deliberately not here is display names. The English name each record carries is a
 * reference, for a log or a fallback, and never what a person is shown: `JP` reads as `Japan`,
 * `일본` or `日本` depending on who is reading, and `Intl.DisplayNames` already knows that in every
 * language a browser has. Storing translations here would be this plugin maintaining a worse copy of
 * something the platform ships.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * Which edition of the standard this list is.
 *
 * Recorded so that a Card written today can be read against the list that was current when it was
 * written, and so that updating the data is a decision somebody makes rather than something that
 * happens.
 */
const AXISMUNDI_GEODATA_ISO_3166_1_EDITION = '2026-08';

/**
 * Every officially assigned ISO 3166-1 country, by its alpha-2 code.
 *
 * Officially assigned only: the exceptionally reserved (`AC`, `TA`) and user-assigned (`XK`) codes
 * some other systems carry are not countries the standard names, and a picker offering them would
 * be offering something a reader elsewhere may not recognise. A document that arrived carrying one
 * is a different matter and is never rewritten -- see `axismundi_geodata_is_country_code()`.
 *
 * @return array<string,array{alpha_3:string,numeric:string,name:string}>
 */
function axismundi_geodata_countries() : array {
	static $countries = null;
	if ( null === $countries ) {
		$countries = (array) require __DIR__ . '/iso-3166-1.generated.php';
	}
	return $countries;
}

/**
 * The codes alone, in order.
 *
 * @return string[]
 */
function axismundi_geodata_country_codes() : array {
	return array_keys( axismundi_geodata_countries() );
}

/**
 * Whether a code is a country this list knows.
 *
 * A question, not a gate. Somewhere that answers `false` may still be written down -- an import from
 * a system using a reserved code, or a country assigned after this list was last updated -- and
 * refusing it would make this plugin's release schedule the limit of what the world contains.
 *
 * @param string $code Alpha-2 code.
 * @return bool
 */
function axismundi_geodata_is_country_code( string $code ) : bool {
	return isset( axismundi_geodata_countries()[ strtoupper( trim( $code ) ) ] );
}

/**
 * The English reference name of a country, for a log or a fallback.
 *
 * Never for a person to read when a browser is available to name it in their own language.
 *
 * @param string $code Alpha-2 code.
 * @return string Empty when the code is not one this list knows.
 */
function axismundi_geodata_country_name( string $code ) : string {
	$record = axismundi_geodata_countries()[ strtoupper( trim( $code ) ) ] ?? array();
	return (string) ( $record['name'] ?? '' );
}

/**
 * The countries, as a screen needs them: a code and something to show before a browser names it.
 *
 * @return array<int,array{value:string,label:string}>
 */
function axismundi_geodata_country_options() : array {
	$options = array();
	foreach ( axismundi_geodata_countries() as $code => $record ) {
		$options[] = array( 'value' => (string) $code, 'label' => (string) $record['name'] );
	}
	return $options;
}
