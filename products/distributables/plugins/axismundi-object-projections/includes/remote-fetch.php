<?php
/**
 * Phase 4b - bounded administrator/background remote object discovery.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** Content types accepted for ActivityStreams object discovery. */
function axismundi_op_remote_content_types() : array {
	return array( 'application/activity+json', 'application/ld+json', 'application/json' );
}

/** Record an existing object's fetch failure and return the error. */
function axismundi_op_remote_fetch_error( string $url, string $code, string $message ) : WP_Error {
	axismundi_op_remote_object_record_failure( $url, $code );
	return new WP_Error( $code, $message );
}

/**
 * Schedule best-effort discovery of one attributed Actor without extending fetch latency.
 *
 * Object storage never depends on Actors being installed or reachable. Only the primary
 * `attributedTo` URI is warmed automatically; mentions and audience members do not fan out.
 */
function axismundi_op_schedule_remote_actor_discovery( string $actor_uri ) : bool {
	$actor_uri = trim( $actor_uri );
	if ( '' === $actor_uri || ! function_exists( 'axismundi_actors_get_by_uri' ) || ! function_exists( 'axismundi_actors_discover_remote_actor_uri' ) ) {
		return false;
	}
	if ( null !== axismundi_actors_get_by_uri( $actor_uri ) ) {
		return false;
	}
	$args = array( $actor_uri );
	if ( false !== wp_next_scheduled( 'axismundi_op_discover_remote_actor', $args ) ) {
		return true;
	}
	return false !== wp_schedule_single_event( time() + 5, 'axismundi_op_discover_remote_actor', $args );
}

/** Run one deferred Actor discovery request. */
function axismundi_op_discover_remote_actor( string $actor_uri ) : void {
	if ( ! function_exists( 'axismundi_actors_get_by_uri' ) || ! function_exists( 'axismundi_actors_discover_remote_actor_uri' ) ) {
		return;
	}
	if ( null === axismundi_actors_get_by_uri( $actor_uri ) ) {
		axismundi_actors_discover_remote_actor_uri( $actor_uri );
	}
}
add_action( 'axismundi_op_discover_remote_actor', 'axismundi_op_discover_remote_actor' );

/** Emit the post-fetch integration event and queue the primary Actor when needed. */
function axismundi_op_remote_object_fetched( array $stored ) : void {
	$actor = null;
	if ( ! empty( $stored['attributed_to_uri'] ) && function_exists( 'axismundi_actors_get_by_uri' ) ) {
		$actor = axismundi_actors_get_by_uri( (string) $stored['attributed_to_uri'] );
		if ( null === $actor ) {
			axismundi_op_schedule_remote_actor_discovery( (string) $stored['attributed_to_uri'] );
		}
	}
	/**
	 * A remote object was cached. Actor discovery, when available, is deferred.
	 *
	 * @since 0.0.7
	 * @param array<string,mixed>   $stored Stored object row.
	 * @param Axismundi_Actor|null $actor  Already-cached Actor, if any.
	 */
	do_action( 'axismundi_op_remote_object_fetched', $stored, $actor );
}

/**
 * Fetch, validate, and cache one remote ActivityStreams object.
 *
 * No redirect is followed. Render paths must never call this function.
 *
 * @param string $url Canonical or negotiated remote object URL.
 * @return array<string,mixed>|WP_Error Stored observation.
 */
function axismundi_op_remote_object_fetch( string $url ) {
	$url = trim( $url );
	if ( 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) || ! wp_http_validate_url( $url ) ) {
		return new WP_Error( 'ax_op_remote_fetch_url', __( 'Enter a safe public HTTPS object URL.', 'axismundi-object-projections' ) );
	}

	$existing = axismundi_op_remote_object_get( $url );
	$headers  = array(
		'Accept'     => 'application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams", application/json;q=0.5',
		'User-Agent' => 'Axismundi Object Projections/' . AXISMUNDI_OP_VERSION . '; ' . home_url( '/' ),
	);
	if ( is_array( $existing ) && ! empty( $existing['etag'] ) ) {
		$headers['If-None-Match'] = (string) $existing['etag'];
	}
	if ( is_array( $existing ) && ! empty( $existing['last_modified'] ) ) {
		$headers['If-Modified-Since'] = (string) $existing['last_modified'];
	}

	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 10,
			'redirection'         => 0,
			'limit_response_size' => AXISMUNDI_OP_REMOTE_PAYLOAD_MAX + 1,
			'headers'             => $headers,
		)
	);
	if ( is_wp_error( $response ) ) {
		return axismundi_op_remote_fetch_error( $url, 'ax_op_remote_fetch_http', __( 'The remote object could not be fetched.', 'axismundi-object-projections' ) );
	}

	$status = (int) wp_remote_retrieve_response_code( $response );
	if ( 304 === $status && is_array( $existing ) ) {
		$not_modified = axismundi_op_remote_object_not_modified( $url );
		if ( is_array( $not_modified ) ) {
			axismundi_op_remote_object_fetched( $not_modified );
			return $not_modified;
		}
		return new WP_Error( 'ax_op_remote_not_modified', __( 'The cached object could not be refreshed.', 'axismundi-object-projections' ) );
	}
	if ( in_array( $status, array( 401, 403 ), true ) ) {
		return axismundi_op_remote_fetch_error( $url, 'ax_op_remote_signed_fetch_required', __( 'This object requires authenticated or signed fetching, which is not available yet.', 'axismundi-object-projections' ) );
	}
	if ( 200 !== $status ) {
		return axismundi_op_remote_fetch_error( $url, 'ax_op_remote_fetch_status', __( 'The remote server returned an unexpected status.', 'axismundi-object-projections' ) );
	}

	$content_type = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $response, 'content-type' ) )[0] ) );
	if ( ! in_array( $content_type, axismundi_op_remote_content_types(), true ) ) {
		return axismundi_op_remote_fetch_error( $url, 'ax_op_remote_fetch_content_type', __( 'The remote response is not a supported JSON document.', 'axismundi-object-projections' ) );
	}
	$body = (string) wp_remote_retrieve_body( $response );
	if ( '' === $body || strlen( $body ) > AXISMUNDI_OP_REMOTE_PAYLOAD_MAX ) {
		return axismundi_op_remote_fetch_error( $url, 'ax_op_remote_fetch_size', __( 'The remote response is empty or exceeds one MiB.', 'axismundi-object-projections' ) );
	}
	try {
		$payload = json_decode( $body, true, 64, JSON_THROW_ON_ERROR );
	} catch ( JsonException $error ) {
		return axismundi_op_remote_fetch_error( $url, 'ax_op_remote_fetch_json', __( 'The remote response is not valid JSON.', 'axismundi-object-projections' ) );
	}
	if ( ! is_array( $payload ) ) {
		return axismundi_op_remote_fetch_error( $url, 'ax_op_remote_fetch_json', __( 'The remote response is not a JSON object.', 'axismundi-object-projections' ) );
	}

	$stored = axismundi_op_remote_object_store(
		$payload,
		array(
			'etag'          => (string) wp_remote_retrieve_header( $response, 'etag' ),
			'last_modified' => (string) wp_remote_retrieve_header( $response, 'last-modified' ),
		)
	);
	if ( is_wp_error( $stored ) ) {
		return $stored;
	}

	axismundi_op_remote_object_fetched( $stored );
	return $stored;
}
