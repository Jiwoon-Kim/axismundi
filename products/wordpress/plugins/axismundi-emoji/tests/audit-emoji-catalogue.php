<?php
/**
 * Local emoji catalogue and its search endpoint (dev-only; dist-excluded).
 *
 * The load-bearing assertion in this file is a negative one: an observed remote emoji
 * must never appear in a picker result. Offering one would reuse an asset whose licence
 * and availability belong to somebody else, and would reintroduce the namespace
 * collision the renderer exists to avoid — two servers ship `:misskey:` and a flat list
 * cannot say which was meant.
 *
 * No network. Images are generated in memory.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-emoji.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

global $wpdb;
$ax_cat_results = array();
$ax_cat_ids     = array();
$ax_cat_remote  = 'catalogue-remote.example.org';

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_cat_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/**
 * A distinct square PNG, so no two fixtures share a content hash.
 *
 * @param int $seed Colour seed.
 * @return string
 */
function ax_cat_png( int $seed ) : string {
	$image = imagecreatetruecolor( 48, 48 );
	imagefill( $image, 0, 0, imagecolorallocate( $image, $seed % 256, ( $seed * 7 ) % 256, ( $seed * 13 ) % 256 ) );
	ob_start();
	imagepng( $image );
	imagedestroy( $image );
	return (string) ob_get_clean();
}

/** @param array<int,array<string,mixed>> $items Result items. @return string[] */
function ax_cat_names( array $items ) : array {
	return array_map( static fn( array $item ) : string => (string) $item['name'], $items );
}

try {
	axismundi_emoji_install();
	wp_set_current_user( 1 );

	foreach ( array( 'catalpha', 'catbeta', 'catgamma', 'catprivate' ) as $ax_cat_key ) {
		$ax_cat_stale = axismundi_emoji_local_get( $ax_cat_key );
		if ( is_array( $ax_cat_stale ) ) {
			axismundi_emoji_delete_local( (int) $ax_cat_stale['id'] );
		}
	}
	$wpdb->delete( axismundi_emoji_table(), array( 'emoji_authority' => $ax_cat_remote ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.

	$ax_cat_seed = array(
		'catalpha'   => array( 'seed' => 11, 'args' => array( 'category' => 'Fixtures', 'aliases' => array( 'thumbsup', 'yes' ) ) ),
		'catbeta'    => array( 'seed' => 37, 'args' => array( 'category' => 'Fixtures' ) ),
		'catgamma'   => array( 'seed' => 59, 'args' => array( 'category' => 'Other' ) ),
		'catprivate' => array( 'seed' => 83, 'args' => array( 'category' => 'Fixtures', 'local_only' => true ) ),
	);
	foreach ( $ax_cat_seed as $ax_cat_key => $ax_cat_spec ) {
		$ax_cat_row = axismundi_emoji_register_local( ax_cat_png( $ax_cat_spec['seed'] ), ':' . $ax_cat_key . ':', $ax_cat_spec['args'] );
		if ( is_wp_error( $ax_cat_row ) ) {
			throw new RuntimeException( $ax_cat_key . ': ' . $ax_cat_row->get_error_message() );
		}
		$ax_cat_ids[] = (int) $ax_cat_row['id'];
	}

	// An approved, cached remote emoji — the thing a picker must not offer.
	$ax_cat_hash = hash( 'sha256', $ax_cat_remote . '/catalpha' );
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
		axismundi_emoji_table(),
		array(
			'emoji_authority' => $ax_cat_remote,
			'shortcode_key'   => 'catalpha',
			'shortcode'       => ':catalpha:',
			'scope'           => 'remote',
			'source_kind'     => 'remote',
			'source_url'      => 'https://cdn.' . $ax_cat_remote . '/catalpha.png',
			'review_status'   => 'approved',
			'picker_visible'  => 1,
			'content_hash'    => $ax_cat_hash,
			'cached_path'     => axismundi_emoji_cache_relative_path( $ax_cat_hash, 'png' ),
			'byte_size'       => 100,
			'width'           => 48,
			'height'          => 48,
			'category'        => 'Fixtures',
			'first_seen_at'   => current_time( 'mysql', true ),
			'last_seen_at'    => current_time( 'mysql', true ),
		)
	);

	// -- What the catalogue contains ---------------------------------------------------

	$ax_cat_all = axismundi_emoji_search_local( array( 'per_page' => 100 ) );
	$ax_cat_names = ax_cat_names( $ax_cat_all['items'] );
	ax_cat_assert( $ax_cat_results, 'the catalogue lists this site\'s own emoji', array() !== array_intersect( array( 'catalpha', 'catbeta', 'catgamma' ), $ax_cat_names ) );

	/*
	 * The one that matters. The remote fixture is approved, cached, picker_visible, and
	 * shares a shortcode with a local emoji — every property that would let a careless
	 * query return it.
	 */
	$ax_cat_remote_leaked = 0;
	foreach ( $ax_cat_all['items'] as $ax_cat_item ) {
		$ax_cat_row = $wpdb->get_row( $wpdb->prepare( 'SELECT scope FROM ' . axismundi_emoji_table() . ' WHERE id = %d', (int) $ax_cat_item['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
		if ( 'local' !== (string) ( $ax_cat_row['scope'] ?? '' ) ) {
			$ax_cat_remote_leaked++;
		}
	}
	ax_cat_assert( $ax_cat_results, 'an approved, cached remote emoji is never offered, whatever its review state', 0 === $ax_cat_remote_leaked );
	ax_cat_assert( $ax_cat_results, 'even when it shares a shortcode with a local one, so the collision cannot surface here', 1 === count( array_keys( $ax_cat_names, 'catalpha', true ) ) );

	// A local row whose bytes are missing would render as a broken image the moment it
	// was picked, so it is not offered either.
	$ax_cat_gamma = axismundi_emoji_local_get( 'catgamma' );
	$wpdb->update( axismundi_emoji_table(), array( 'cached_path' => '' ), array( 'id' => (int) $ax_cat_gamma['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- simulating a lost blob.
	ax_cat_assert( $ax_cat_results, 'an emoji whose bytes are missing is withheld rather than offered as a broken tile', ! in_array( 'catgamma', ax_cat_names( axismundi_emoji_search_local( array( 'per_page' => 100 ) )['items'] ), true ) );
	$wpdb->update( axismundi_emoji_table(), array( 'cached_path' => $ax_cat_gamma['cached_path'] ), array( 'id' => (int) $ax_cat_gamma['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- restore.

	// -- Search ------------------------------------------------------------------------

	ax_cat_assert( $ax_cat_results, 'a search matches on the shortcode', array( 'catbeta' ) === ax_cat_names( axismundi_emoji_search_local( array( 'search' => 'catbeta' ) )['items'] ) );
	ax_cat_assert( $ax_cat_results, 'and on an alias, which is the only reason aliases exist', array( 'catalpha' ) === ax_cat_names( axismundi_emoji_search_local( array( 'search' => 'thumbsup' ) )['items'] ) );
	ax_cat_assert( $ax_cat_results, 'search is case-insensitive', array( 'catbeta' ) === ax_cat_names( axismundi_emoji_search_local( array( 'search' => 'CATBETA' ) )['items'] ) );
	ax_cat_assert( $ax_cat_results, 'a search matching nothing returns nothing rather than everything', array() === axismundi_emoji_search_local( array( 'search' => 'zzzznope' ) )['items'] );
	/*
	 * A LIKE built by hand would treat `%` as a wildcard and return the whole catalogue
	 * for a user who typed a percent sign.
	 */
	ax_cat_assert( $ax_cat_results, 'wildcard characters in a search are escaped, not honoured', array() === axismundi_emoji_search_local( array( 'search' => '%' ) )['items'] );

	// -- Filters -----------------------------------------------------------------------

	$ax_cat_fixtures = ax_cat_names( axismundi_emoji_search_local( array( 'category' => 'Fixtures', 'per_page' => 100 ) )['items'] );
	ax_cat_assert( $ax_cat_results, 'a category filter narrows to that category', in_array( 'catalpha', $ax_cat_fixtures, true ) && ! in_array( 'catgamma', $ax_cat_fixtures, true ) );
	ax_cat_assert( $ax_cat_results, 'categories in use are listed for grouping', array() !== array_intersect( array( 'Fixtures', 'Other' ), axismundi_emoji_local_categories() ) );

	/*
	 * `localOnly` is withheld by default. Omitting the image from `tag[]` is not the whole
	 * story: the `:shortcode:` text still leaves the site, so a Note or Article composer
	 * offering one produces a message that reads correctly at home and as a bare word
	 * everywhere else. Misskey treats the field the same way — a `localOnly` emoji in a
	 * federated note arrives with `"tag": []`.
	 */
	ax_cat_assert( $ax_cat_results, 'a local-only emoji is withheld by default, because almost every composer federates', ! in_array( 'catprivate', ax_cat_names( axismundi_emoji_search_local( array( 'per_page' => 100 ) )['items'] ), true ) );
	ax_cat_assert( $ax_cat_results, 'and a consumer that omits the parameter entirely still gets the safe answer', ! in_array( 'catprivate', ax_cat_names( (array) rest_do_request( new WP_REST_Request( 'GET', '/axismundi/v1/emoji/local' ) )->get_data() ), true ) );
	ax_cat_assert( $ax_cat_results, 'only a composer that says its output never leaves is offered one', in_array( 'catprivate', ax_cat_names( axismundi_emoji_search_local( array( 'federated' => false, 'per_page' => 100 ) )['items'] ), true ) );
	ax_cat_assert( $ax_cat_results, 'an ordinary emoji is offered either way', in_array( 'catalpha', ax_cat_names( axismundi_emoji_search_local( array( 'per_page' => 100 ) )['items'] ), true ) );

	// -- Paging ------------------------------------------------------------------------

	$ax_cat_page1 = axismundi_emoji_search_local( array( 'per_page' => 1, 'page' => 1, 'category' => 'Fixtures' ) );
	$ax_cat_page2 = axismundi_emoji_search_local( array( 'per_page' => 1, 'page' => 2, 'category' => 'Fixtures' ) );
	ax_cat_assert( $ax_cat_results, 'paging returns one page at a time', 1 === count( $ax_cat_page1['items'] ) && 1 === count( $ax_cat_page2['items'] ) );
	ax_cat_assert( $ax_cat_results, 'consecutive pages do not repeat a row', ax_cat_names( $ax_cat_page1['items'] ) !== ax_cat_names( $ax_cat_page2['items'] ) );
	// Stated as a relationship rather than a number, so it keeps meaning when the fixture
	// set changes — which is exactly what a policy change to the default filter does.
	$ax_cat_unpaged = axismundi_emoji_search_local( array( 'per_page' => 100, 'category' => 'Fixtures' ) );
	ax_cat_assert( $ax_cat_results, 'the total counts the whole match, not the page', $ax_cat_page1['total'] === count( $ax_cat_unpaged['items'] ) && $ax_cat_page1['total'] > 1 );
	ax_cat_assert( $ax_cat_results, 'an oversized page size is capped rather than honoured', count( axismundi_emoji_search_local( array( 'per_page' => 100000 ) )['items'] ) <= AXISMUNDI_EMOJI_CATALOGUE_MAX_PER_PAGE );

	// -- What an item carries -----------------------------------------------------------

	$ax_cat_item = axismundi_emoji_search_local( array( 'search' => 'catalpha' ) )['items'][0] ?? array();
	ax_cat_assert( $ax_cat_results, 'an item carries the shortcode a picker inserts as plain text', ':catalpha:' === (string) ( $ax_cat_item['shortcode'] ?? '' ) );
	ax_cat_assert( $ax_cat_results, 'and a URL for its tile, which is all the image is for', '' !== (string) ( $ax_cat_item['url'] ?? '' ) );
	ax_cat_assert( $ax_cat_results, 'and its aliases, so a picker can show why a search matched', array( 'thumbsup', 'yes' ) === ( $ax_cat_item['aliases'] ?? array() ) );

	// -- The endpoint --------------------------------------------------------------------

	$ax_cat_request  = new WP_REST_Request( 'GET', '/axismundi/v1/emoji/local' );
	$ax_cat_request->set_param( 'search', 'catbeta' );
	$ax_cat_response = rest_do_request( $ax_cat_request );
	ax_cat_assert( $ax_cat_results, 'the route is registered and answers', 200 === $ax_cat_response->get_status() );
	ax_cat_assert( $ax_cat_results, 'and returns the matching emoji', array( 'catbeta' ) === ax_cat_names( (array) $ax_cat_response->get_data() ) );
	ax_cat_assert( $ax_cat_results, 'with a total header, so a picker can page without guessing', '1' === (string) ( $ax_cat_response->get_headers()['X-WP-Total'] ?? '' ) );

	$ax_cat_cats = rest_do_request( new WP_REST_Request( 'GET', '/axismundi/v1/emoji/local/categories' ) );
	ax_cat_assert( $ax_cat_results, 'the categories route answers too', 200 === $ax_cat_cats->get_status() && in_array( 'Fixtures', (array) $ax_cat_cats->get_data(), true ) );

	/*
	 * The images are public, but the catalogue is not: it lists what this site has,
	 * including local-only emoji, which is editorial information rather than something a
	 * reader needs. So the gate follows the editor.
	 */
	wp_set_current_user( 0 );
	ax_cat_assert( $ax_cat_results, 'a logged-out visitor cannot enumerate the catalogue', ! axismundi_emoji_can_read_catalogue() );
	ax_cat_assert( $ax_cat_results, 'and the route refuses them', 401 === rest_do_request( new WP_REST_Request( 'GET', '/axismundi/v1/emoji/local' ) )->get_status() );
	wp_set_current_user( 1 );
} catch ( Throwable $ax_cat_error ) {
	ax_cat_assert( $ax_cat_results, 'the catalogue suite ran to completion: ' . $ax_cat_error->getMessage(), false );
} finally {
	wp_set_current_user( 1 );
	foreach ( array_unique( array_filter( $ax_cat_ids ) ) as $ax_cat_id ) {
		axismundi_emoji_delete_local( (int) $ax_cat_id );
	}
	$wpdb->delete( axismundi_emoji_table(), array( 'emoji_authority' => $ax_cat_remote ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
}

$ax_cat_failures = count( array_filter( $ax_cat_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_cat_results ), $ax_cat_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_cat_failures > 0 ? 1 : 0 );
}
exit( $ax_cat_failures > 0 ? 1 : 0 );
