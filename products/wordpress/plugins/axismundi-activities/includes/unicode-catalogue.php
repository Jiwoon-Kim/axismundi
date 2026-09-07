<?php
/**
 * Static Unicode RGI emoji catalogue for the reaction picker.
 *
 * This is picker metadata, not a second Emoji registry. Unicode reactions travel as a
 * grapheme in `EmojiReact.content`; FEP-9098 Emoji objects remain only for custom emoji.
 * The browser and WordPress Core own Unicode rendering and its Twemoji fallback.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACT_UNICODE_CATALOGUE_SCHEMA  = 1;
const AXISMUNDI_ACT_UNICODE_CATALOGUE_VERSION = '17.0';

/** Absolute path to the generated RGI metadata. */
function axismundi_act_unicode_catalogue_path() : string {
	return dirname( __DIR__ ) . '/assets/unicode-rgi-' . AXISMUNDI_ACT_UNICODE_CATALOGUE_VERSION . '.json';
}

/**
 * Load and validate the generated metadata once per request.
 *
 * A broken or missing catalogue means the future picker has no Unicode choices. It must
 * not affect inbound reaction processing: that accepts a valid single grapheme without
 * consulting this convenience index, so a newer peer's reaction is never discarded just
 * because this site has not rebuilt its picker data yet.
 *
 * @return array{schema:int,unicodeVersion:string,source:string,sourceSha256:string,items:array<int,array<string,mixed>>}
 */
function axismundi_act_unicode_catalogue() : array {
	static $catalogue = null;
	if ( is_array( $catalogue ) ) {
		return $catalogue;
	}

	$empty = array(
		'schema'       => AXISMUNDI_ACT_UNICODE_CATALOGUE_SCHEMA,
		'unicodeVersion' => AXISMUNDI_ACT_UNICODE_CATALOGUE_VERSION,
		'source'       => '',
		'sourceSha256' => '',
		'items'        => array(),
	);
	$path  = axismundi_act_unicode_catalogue_path();
	$json  = is_readable( $path ) ? file_get_contents( $path ) : false;
	$data  = is_string( $json ) ? json_decode( $json, true ) : null;
	if (
		! is_array( $data )
		|| AXISMUNDI_ACT_UNICODE_CATALOGUE_SCHEMA !== (int) ( $data['schema'] ?? 0 )
		|| AXISMUNDI_ACT_UNICODE_CATALOGUE_VERSION !== (string) ( $data['unicodeVersion'] ?? '' )
		|| ! isset( $data['items'] )
		|| ! is_array( $data['items'] )
	) {
		$catalogue = $empty;
		return $catalogue;
	}

	$items = array();
	foreach ( $data['items'] as $item ) {
		if (
			! is_array( $item )
			|| '' === (string) ( $item['emoji'] ?? '' )
			|| ! preg_match( '/^unicode:U\+[0-9A-F]+(?:-U\+[0-9A-F]+)*$/', (string) ( $item['key'] ?? '' ) )
		) {
			continue;
		}
		$items[] = array(
			'emoji'       => (string) $item['emoji'],
			'key'         => (string) $item['key'],
			'group'       => (string) ( $item['group'] ?? '' ),
			'subgroup'    => (string) ( $item['subgroup'] ?? '' ),
			'name'        => (string) ( $item['name'] ?? '' ),
			'keywords'    => array_values( array_filter( array_map( 'strval', (array) ( $item['keywords'] ?? array() ) ) ) ),
			'emojiVersion' => (string) ( $item['emojiVersion'] ?? '' ),
		);
	}

	$catalogue = array(
		'schema'       => AXISMUNDI_ACT_UNICODE_CATALOGUE_SCHEMA,
		'unicodeVersion' => AXISMUNDI_ACT_UNICODE_CATALOGUE_VERSION,
		'source'       => (string) ( $data['source'] ?? '' ),
		'sourceSha256' => (string) ( $data['sourceSha256'] ?? '' ),
		'items'        => $items,
	);
	return $catalogue;
}

/** Case-fold a query without requiring mbstring. */
function axismundi_act_unicode_catalogue_fold( string $value ) : string {
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
}

/**
 * Find picker entries by their standardized English metadata.
 *
 * There is intentionally no made-up localisation here. A translated search index can be
 * layered over this exact data later; claiming Korean search before providing Korean data
 * would make the picker look partially broken.
 *
 * @return array{items:array<int,array<string,mixed>>,total:int,total_pages:int,page:int,per_page:int}
 */
function axismundi_act_find_unicode_emoji( string $search = '', string $group = '', int $page = 1, int $per_page = 100 ) : array {
	$catalogue = axismundi_act_unicode_catalogue();
	$needle    = axismundi_act_unicode_catalogue_fold( trim( $search ) );
	$group     = trim( $group );
	$matches   = array();
	foreach ( $catalogue['items'] as $item ) {
		if ( '' !== $group && $group !== $item['group'] ) {
			continue;
		}
		if ( '' !== $needle ) {
			$haystack = implode( ' ', array_merge( array( $item['emoji'], $item['key'], $item['group'], $item['subgroup'], $item['name'] ), $item['keywords'] ) );
			if ( ! str_contains( axismundi_act_unicode_catalogue_fold( $haystack ), $needle ) ) {
				continue;
			}
		}
		$matches[] = $item;
	}

	$per_page    = max( 1, min( 100, $per_page ) );
	$total       = count( $matches );
	$total_pages = max( 1, (int) ceil( $total / $per_page ) );
	$page        = max( 1, min( $total_pages, $page ) );
	return array(
		'items'       => array_slice( $matches, ( $page - 1 ) * $per_page, $per_page ),
		'total'       => $total,
		'total_pages' => $total_pages,
		'page'        => $page,
		'per_page'    => $per_page,
	);
}

/** @return string[] Unicode Emoji group names in Unicode's stable display order. */
function axismundi_act_unicode_emoji_groups() : array {
	$groups = array();
	foreach ( axismundi_act_unicode_catalogue()['items'] as $item ) {
		if ( '' !== $item['group'] && ! in_array( $item['group'], $groups, true ) ) {
			$groups[] = $item['group'];
		}
	}
	return $groups;
}

/** Register the static, public Unicode picker catalogue. */
function axismundi_act_register_unicode_catalogue_rest_route() : void {
	register_rest_route(
		'axismundi/v1',
		'/reactions/unicode',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_act_rest_unicode_catalogue',
			// This contains only standard Unicode metadata. No local emoji, usage history, or
			// viewer-specific state is present, so it is deliberately public and cacheable.
			'permission_callback' => '__return_true',
			'args'                => array(
				'search'   => array( 'type' => 'string', 'default' => '' ),
				'group'    => array( 'type' => 'string', 'default' => '' ),
				'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
				'per_page' => array( 'type' => 'integer', 'default' => 100, 'minimum' => 1, 'maximum' => 100 ),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_act_register_unicode_catalogue_rest_route' );

/** Answer one page of the generated Unicode RGI catalogue. */
function axismundi_act_rest_unicode_catalogue( WP_REST_Request $request ) : WP_REST_Response {
	$page      = axismundi_act_find_unicode_emoji( (string) $request['search'], (string) $request['group'], (int) $request['page'], (int) $request['per_page'] );
	$catalogue = axismundi_act_unicode_catalogue();
	$response  = new WP_REST_Response(
		array(
			'schema'          => $catalogue['schema'],
			'unicode_version' => $catalogue['unicodeVersion'],
			'groups'          => axismundi_act_unicode_emoji_groups(),
			'items'           => $page['items'],
		),
		200
	);
	$response->header( 'X-WP-Total', (string) $page['total'] );
	$response->header( 'X-WP-TotalPages', (string) $page['total_pages'] );
	$response->header( 'Cache-Control', 'public, max-age=86400' );
	return $response;
}
