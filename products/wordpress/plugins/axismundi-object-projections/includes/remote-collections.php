<?php
/**
 * Administrator-only, metadata-only remote Collection probe.
 *
 * Collections are not stored in the remote-object cache. This bounded probe reads a root
 * and, at most, its first page so a shared-folder contract can be inspected before any
 * shadow attachment or binary cache exists.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** Fetch and validate one remote ActivityStreams Collection document. */
function axismundi_op_remote_collection_document( string $url ) {
	$url = trim( $url );
	if ( 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) || ! wp_http_validate_url( $url ) ) {
		return new WP_Error( 'ax_op_collection_url', __( 'Enter a safe public HTTPS Collection URL.', 'axismundi-object-projections' ) );
	}
	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 10,
			'redirection'         => 0,
			'limit_response_size' => AXISMUNDI_OP_REMOTE_PAYLOAD_MAX + 1,
			'headers'             => array(
				'Accept'     => 'application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams", application/json;q=0.5',
				'User-Agent' => 'Axismundi Object Projections/' . AXISMUNDI_OP_VERSION . '; ' . home_url( '/' ),
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'ax_op_collection_http', __( 'The remote Collection could not be fetched.', 'axismundi-object-projections' ) );
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	if ( in_array( $status, array( 401, 403 ), true ) ) {
		return new WP_Error( 'ax_op_collection_signed', __( 'This Collection requires authenticated or signed fetching.', 'axismundi-object-projections' ) );
	}
	if ( 200 !== $status ) {
		return new WP_Error( 'ax_op_collection_status', __( 'The remote server returned an unexpected Collection status.', 'axismundi-object-projections' ) );
	}
	$content_type = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $response, 'content-type' ) )[0] ) );
	if ( ! in_array( $content_type, axismundi_op_remote_content_types(), true ) ) {
		return new WP_Error( 'ax_op_collection_content_type', __( 'The response is not a supported JSON document.', 'axismundi-object-projections' ) );
	}
	$body = (string) wp_remote_retrieve_body( $response );
	if ( '' === $body || strlen( $body ) > AXISMUNDI_OP_REMOTE_PAYLOAD_MAX ) {
		return new WP_Error( 'ax_op_collection_size', __( 'The Collection is empty or exceeds one MiB.', 'axismundi-object-projections' ) );
	}
	try {
		$payload = json_decode( $body, true, 64, JSON_THROW_ON_ERROR );
	} catch ( JsonException $error ) {
		return new WP_Error( 'ax_op_collection_json', __( 'The Collection is not valid JSON.', 'axismundi-object-projections' ) );
	}
	if ( ! is_array( $payload ) ) {
		return new WP_Error( 'ax_op_collection_json', __( 'The Collection is not a JSON object.', 'axismundi-object-projections' ) );
	}
	$types = is_array( $payload['type'] ?? null ) ? $payload['type'] : array( $payload['type'] ?? '' );
	if ( empty( array_intersect( $types, array( 'Collection', 'OrderedCollection', 'CollectionPage', 'OrderedCollectionPage' ) ) ) ) {
		return new WP_Error( 'ax_op_collection_type', __( 'The document is not an ActivityStreams Collection.', 'axismundi-object-projections' ) );
	}
	return $payload;
}

/** URI from a scalar or Link-valued collection member. */
function axismundi_op_remote_collection_uri( $value ) : string {
	if ( is_string( $value ) ) {
		return esc_url_raw( $value );
	}
	if ( is_array( $value ) ) {
		foreach ( array( 'href', 'id' ) as $key ) {
			if ( isset( $value[ $key ] ) && is_string( $value[ $key ] ) ) {
				return esc_url_raw( $value[ $key ] );
			}
		}
	}
	return '';
}

/** Normalize orderedItems/items into a bounded list without fetching item URLs. */
function axismundi_op_remote_collection_items( array $payload ) : array {
	$items = $payload['orderedItems'] ?? $payload['items'] ?? array();
	if ( ! is_array( $items ) ) {
		return array();
	}
	if ( ! array_is_list( $items ) ) {
		$items = array( $items );
	}
	return array_slice( $items, 0, 50 );
}

/**
 * Fetch a Collection root and optionally its same-host first page.
 *
 * @return array{root:array<string,mixed>,page:?array<string,mixed>,items:array<int,mixed>}|WP_Error
 */
function axismundi_op_remote_collection_fetch( string $url ) {
	$root = axismundi_op_remote_collection_document( $url );
	if ( is_wp_error( $root ) ) {
		return $root;
	}
	$page  = null;
	$items = axismundi_op_remote_collection_items( $root );
	$first = axismundi_op_remote_collection_uri( $root['first'] ?? '' );
	if ( empty( $items ) && '' !== $first ) {
		if ( strtolower( (string) wp_parse_url( $first, PHP_URL_HOST ) ) !== strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) ) {
			return new WP_Error( 'ax_op_collection_page_host', __( 'The first Collection page points to a different host.', 'axismundi-object-projections' ) );
		}
		$page = axismundi_op_remote_collection_document( $first );
		if ( is_wp_error( $page ) ) {
			return $page;
		}
		$part_of = axismundi_op_remote_collection_uri( $page['partOf'] ?? '' );
		$root_id = axismundi_op_remote_collection_uri( $root['id'] ?? '' );
		if ( '' !== $part_of && '' !== $root_id && $part_of !== $root_id ) {
			return new WP_Error( 'ax_op_collection_part_of', __( 'The first page does not belong to the requested Collection.', 'axismundi-object-projections' ) );
		}
		$items = axismundi_op_remote_collection_items( $page );
	}
	return array( 'root' => $root, 'page' => $page, 'items' => $items );
}
