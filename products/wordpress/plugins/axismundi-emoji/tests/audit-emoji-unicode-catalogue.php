<?php
/**
 * Generated Unicode RGI catalogue (dev-only; dist-excluded).
 *
 * Verifies the picker data Emoji owns. Moved from Activities' audit-unicode-catalogue.php;
 * the assertions about the reaction picker's markup stayed there with the markup.
 *
 * Run: npx wp-env run cli wp eval-file wp-content/plugins/axismundi-emoji/tests/audit-emoji-unicode-catalogue.php
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-emoji.php';

$ax_ec_results = array();

/** @param bool[] $results Results. */
function ax_ec_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** A string from code points, so no escape notation sits in this file. */
function ax_ec_cp( int ...$points ) : string {
	return implode( '', array_map( 'mb_chr', $points ) );
}

try {
	$ax_ec_catalogue = axismundi_emoji_unicode_catalogue();
	$ax_ec_items     = $ax_ec_catalogue['items'];
	ax_ec_assert( $ax_ec_results, 'the generated catalogue has the expected schema', AXISMUNDI_EMOJI_UNICODE_CATALOGUE_SCHEMA === $ax_ec_catalogue['schema'] );
	ax_ec_assert( $ax_ec_results, 'the generated catalogue declares Unicode Emoji 17.0', AXISMUNDI_EMOJI_UNICODE_CATALOGUE_VERSION === $ax_ec_catalogue['unicodeVersion'] );
	ax_ec_assert( $ax_ec_results, 'the full RGI catalogue is present: all 3,944 fully-qualified sequences', 3944 === count( $ax_ec_items ) );

	$ax_ec_source = (string) file_get_contents( dirname( __DIR__ ) . '/assets/fonts/noto-color-emoji/source.txt' );
	ax_ec_assert(
		$ax_ec_results,
		'it is built from the same emoji-test.txt as the flags font subset, so data and font agree on Emoji 17.0',
		64 === strlen( $ax_ec_catalogue['sourceSha256'] ) && str_contains( strtolower( $ax_ec_source ), $ax_ec_catalogue['sourceSha256'] )
	);
	ax_ec_assert( $ax_ec_results, 'its Unicode licence travels beside the data', is_readable( dirname( __DIR__ ) . '/assets/unicode/catalogue/rgi-17.0.LICENSE.txt' ) );

	$ax_ec_picker = axismundi_emoji_unicode_picker_source();
	ax_ec_assert( $ax_ec_results, 'a picker gets one full index for search and one file per browseable group', str_ends_with( $ax_ec_picker['index_url'], '/axismundi-emoji/assets/unicode/catalogue/rgi-17.0.json' ) && 9 === count( $ax_ec_picker['groups'] ) );
	ax_ec_assert( $ax_ec_results, 'group names come from the data, in Unicode order', array_keys( $ax_ec_picker['groups'] ) === axismundi_emoji_unicode_groups() && 'Smileys & Emotion' === axismundi_emoji_unicode_groups()[0] );

	$ax_ec_flags_file = dirname( __DIR__ ) . '/assets/unicode/catalogue/rgi-17.0/flags.json';
	$ax_ec_flags_data = is_readable( $ax_ec_flags_file ) ? json_decode( (string) file_get_contents( $ax_ec_flags_file ), true ) : null;
	ax_ec_assert( $ax_ec_results, 'Flags is its own file, so opening it never needs the whole RGI set', is_array( $ax_ec_flags_data ) && 'Flags' === (string) ( $ax_ec_flags_data['group'] ?? '' ) && 270 === count( $ax_ec_flags_data['items'] ?? array() ) );

	$ax_ec_kr        = ax_ec_cp( 0x1F1F0, 0x1F1F7 );
	$ax_ec_heart     = ax_ec_cp( 0x2764, 0xFE0F );
	$ax_ec_family    = ax_ec_cp( 0x1F468, 0x200D, 0x1F469, 0x200D, 0x1F467, 0x200D, 0x1F466 );
	$ax_ec_by_emoji  = array_column( $ax_ec_items, null, 'emoji' );
	$ax_ec_keys      = array_column( $ax_ec_items, 'key' );
	ax_ec_assert( $ax_ec_results, 'each entry has one normalized key', count( $ax_ec_keys ) === count( array_unique( $ax_ec_keys ) ) );
	ax_ec_assert( $ax_ec_results, 'the Korean flag is available even where the operating-system picker omits it', 'unicode:U+1F1F0-U+1F1F7' === ( $ax_ec_by_emoji[ $ax_ec_kr ]['key'] ?? '' ) && 'Flags' === ( $ax_ec_by_emoji[ $ax_ec_kr ]['group'] ?? '' ) );
	ax_ec_assert( $ax_ec_results, 'variation selectors are kept for display but removed from the key', 'unicode:U+2764' === ( $ax_ec_by_emoji[ $ax_ec_heart ]['key'] ?? '' ) );
	ax_ec_assert( $ax_ec_results, 'a family ZWJ sequence is one entry rather than several characters', str_contains( (string) ( $ax_ec_by_emoji[ $ax_ec_family ]['key'] ?? '' ), 'U+200D' ) );

	$ax_ec_search = axismundi_emoji_find_unicode( 'South Korea' );
	ax_ec_assert( $ax_ec_results, 'English source metadata is searchable', 1 === count( array_filter( $ax_ec_search['items'], static fn( array $item ) : bool => $ax_ec_kr === $item['emoji'] ) ) );
	$ax_ec_flags = axismundi_emoji_find_unicode( '', 'Flags', 1, 100 );
	ax_ec_assert( $ax_ec_results, 'group filtering keeps Unicode groups intact', $ax_ec_flags['total'] > 200 && 0 === count( array_filter( $ax_ec_flags['items'], static fn( array $item ) : bool => 'Flags' !== $item['group'] ) ) );

	do_action( 'rest_api_init' );
	$ax_ec_request = new WP_REST_Request( 'GET', '/axismundi/v1/emoji/unicode' );
	$ax_ec_request->set_param( 'search', 'South Korea' );
	$ax_ec_response = rest_do_request( $ax_ec_request );
	$ax_ec_data     = $ax_ec_response->get_data();
	ax_ec_assert( $ax_ec_results, 'the public REST route lives under emoji/, beside emoji/local', 200 === $ax_ec_response->get_status() && AXISMUNDI_EMOJI_UNICODE_CATALOGUE_VERSION === (string) ( $ax_ec_data['unicode_version'] ?? '' ) );
	ax_ec_assert( $ax_ec_results, 'the REST search returns the Korean flag with public cache headers', 1 === count( $ax_ec_data['items'] ?? array() ) && $ax_ec_kr === (string) $ax_ec_data['items'][0]['emoji'] && 'public, max-age=86400' === $ax_ec_response->get_headers()['Cache-Control'] );
	ax_ec_assert( $ax_ec_results, 'Emoji serves nothing under reactions/, the name the route had while Activities owned it', ! str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/includes/unicode-catalogue.php' ), "'/reactions/" ) );
} catch ( Throwable $ax_ec_error ) {
	ax_ec_assert( $ax_ec_results, 'the Unicode catalogue suite ran to completion: ' . $ax_ec_error->getMessage(), false );
}

$ax_ec_failures = count( array_filter( $ax_ec_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ec_results ), $ax_ec_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ec_failures > 0 ? 1 : 0 );
}
exit( $ax_ec_failures > 0 ? 1 : 0 );
