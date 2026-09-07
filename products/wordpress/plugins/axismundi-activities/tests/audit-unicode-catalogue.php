<?php
/**
 * Generated Unicode RGI picker catalogue (dev-only; dist-excluded).
 *
 * This verifies the picker index, not inbound federation validation. A peer may send a
 * newer valid grapheme before this generated data updates; the reaction normalizer must
 * remain independent of this file.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_uc_results = array();

/** @param bool[] $results Results. */
function ax_uc_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$ax_uc_catalogue = axismundi_act_unicode_catalogue();
	$ax_uc_items     = $ax_uc_catalogue['items'];
	ax_uc_assert( $ax_uc_results, 'the generated catalogue has the expected schema', AXISMUNDI_ACT_UNICODE_CATALOGUE_SCHEMA === $ax_uc_catalogue['schema'] );
	ax_uc_assert( $ax_uc_results, 'the generated catalogue declares Unicode Emoji 17.0', AXISMUNDI_ACT_UNICODE_CATALOGUE_VERSION === $ax_uc_catalogue['unicodeVersion'] );
	ax_uc_assert( $ax_uc_results, 'the full RGI catalogue is present rather than a quick-reaction subset', count( $ax_uc_items ) > 3900 );
	ax_uc_assert( $ax_uc_results, 'the generated source remains identifiable and reproducible', '' !== $ax_uc_catalogue['source'] && 64 === strlen( $ax_uc_catalogue['sourceSha256'] ) );
	$ax_uc_picker_source = axismundi_act_unicode_picker_source();
	ax_uc_assert( $ax_uc_results, 'the picker has a versioned full index for search but one source per browseable group', '' !== $ax_uc_picker_source['index_url'] && 9 === count( $ax_uc_picker_source['groups'] ) );
	$ax_uc_flags_file = dirname( __DIR__ ) . '/assets/unicode-rgi-17.0/flags.json';
	$ax_uc_flags_data = is_readable( $ax_uc_flags_file ) ? json_decode( (string) file_get_contents( $ax_uc_flags_file ), true ) : null;
	ax_uc_assert( $ax_uc_results, 'the Flags browse source is a separate group file, so opening it never needs the whole RGI set', is_array( $ax_uc_flags_data ) && 'Flags' === (string) ( $ax_uc_flags_data['group'] ?? '' ) && 270 === count( $ax_uc_flags_data['items'] ?? array() ) );
	$ax_uc_picker_template = (string) file_get_contents( dirname( __DIR__ ) . '/includes/reaction-blocks.php' );
	ax_uc_assert( $ax_uc_results, 'Unicode picker tiles begin as grapheme text, leaving any Twemoji replacement to WordPress Core fallback', str_contains( $ax_uc_picker_template, '<span class="axismundi-reaction-button__glyph" data-wp-text="context.item.glyph"></span>' ) && ! str_contains( $ax_uc_picker_template, 's.w.org/images/core/emoji' ) );
	$ax_uc_picker_script = (string) file_get_contents( dirname( __DIR__ ) . '/assets/reactions.js' );
	ax_uc_assert( $ax_uc_results, 'opening Custom consumes the public REST items array, not the search helper’s private envelope', str_contains( $ax_uc_picker_script, 'const items = yield response.json();' ) && str_contains( $ax_uc_picker_script, 'state.catalogue = Array.isArray( items ) ? items : [];' ) );
	ax_uc_assert( $ax_uc_results, 'closed categories contribute no tile items and Unicode browse fetches one group at a time', str_contains( $ax_uc_picker_script, 'items: state.expandedSections.includes( id ) ? entries : []' ) && str_contains( $ax_uc_picker_script, 'loadUnicodeGroup( section.slice( 4 ) )' ) );

	$ax_uc_by_emoji = array_column( $ax_uc_items, null, 'emoji' );
	$ax_uc_keys     = array_column( $ax_uc_items, 'key' );
	ax_uc_assert( $ax_uc_results, 'each picker entry has one normalized reaction key', count( $ax_uc_keys ) === count( array_unique( $ax_uc_keys ) ) );
	ax_uc_assert(
		$ax_uc_results,
		'Korean flag is available even where the operating-system picker omits it',
		isset( $ax_uc_by_emoji[ "\u{1F1F0}\u{1F1F7}" ] )
		&& 'unicode:U+1F1F0-U+1F1F7' === $ax_uc_by_emoji[ "\u{1F1F0}\u{1F1F7}" ]['key']
		&& 'Flags' === $ax_uc_by_emoji[ "\u{1F1F0}\u{1F1F7}" ]['group']
	);
	ax_uc_assert(
		$ax_uc_results,
		'variation selectors are retained for display but removed from the reaction key',
		isset( $ax_uc_by_emoji[ "\u{2764}\u{FE0F}" ] ) && 'unicode:U+2764' === $ax_uc_by_emoji[ "\u{2764}\u{FE0F}" ]['key']
	);
	ax_uc_assert(
		$ax_uc_results,
		'a family ZWJ sequence is one selectable entry rather than several characters',
		isset( $ax_uc_by_emoji[ "\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}" ] )
		&& str_contains( $ax_uc_by_emoji[ "\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}" ]['key'], 'U+200D' )
	);

	$ax_uc_flag_search = axismundi_act_find_unicode_emoji( 'South Korea' );
	ax_uc_assert( $ax_uc_results, 'English source metadata is searchable', 1 === count( array_filter( $ax_uc_flag_search['items'], static fn( array $item ) : bool => "\u{1F1F0}\u{1F1F7}" === $item['emoji'] ) ) );
	$ax_uc_flags = axismundi_act_find_unicode_emoji( '', 'Flags', 1, 100 );
	ax_uc_assert( $ax_uc_results, 'group filtering keeps Unicode display groups intact', $ax_uc_flags['total'] > 200 && 0 === count( array_filter( $ax_uc_flags['items'], static fn( array $item ) : bool => 'Flags' !== $item['group'] ) ) );

	do_action( 'rest_api_init' );
	$ax_uc_request = new WP_REST_Request( 'GET', '/axismundi/v1/reactions/unicode' );
	$ax_uc_request->set_param( 'search', 'South Korea' );
	$ax_uc_response = rest_do_request( $ax_uc_request );
	$ax_uc_data     = $ax_uc_response->get_data();
	ax_uc_assert( $ax_uc_results, 'the public REST endpoint serves picker metadata', 200 === $ax_uc_response->get_status() && AXISMUNDI_ACT_UNICODE_CATALOGUE_VERSION === (string) ( $ax_uc_data['unicode_version'] ?? '' ) );
	ax_uc_assert( $ax_uc_results, 'the REST search returns the Korean flag and public cache headers', 1 === count( $ax_uc_data['items'] ?? array() ) && "\u{1F1F0}\u{1F1F7}" === (string) $ax_uc_data['items'][0]['emoji'] && 'public, max-age=86400' === $ax_uc_response->get_headers()['Cache-Control'] );
} catch ( Throwable $ax_uc_error ) {
	ax_uc_assert( $ax_uc_results, 'the Unicode catalogue suite ran to completion: ' . $ax_uc_error->getMessage(), false );
}

$ax_uc_failures = count( array_filter( $ax_uc_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_uc_results ), $ax_uc_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_uc_failures > 0 ? 1 : 0 );
}
exit( $ax_uc_failures > 0 ? 1 : 0 );
