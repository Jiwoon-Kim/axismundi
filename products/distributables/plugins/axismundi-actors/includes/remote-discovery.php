<?php
/**
 * Safe remote Actor discovery: acct WebFinger -> ActivityStreams Actor -> remote
 * identity/actor snapshot. No instance cache, delivery, keys, or inbox behavior.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACTORS_REMOTE_MAX_BYTES = 1048576;

/** @param string $value Text. @param int $length Character limit. @return string */
function axismundi_actors_remote_limit_text( string $value, int $length ) : string {
	return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
}

/**
 * Normalize a remote acct input.
 *
 * @param string $input `@user@host`, `user@host`, or `acct:user@host`.
 * @return array{acct:string,handle:string,authority:string}|WP_Error
 */
function axismundi_actors_normalize_remote_acct( string $input ) {
	$input = trim( $input );
	if ( str_starts_with( strtolower( $input ), 'acct:' ) ) {
		$input = substr( $input, 5 );
	}
	$input = ltrim( $input, '@' );
	$at    = strrpos( $input, '@' );
	if ( false === $at ) {
		return new WP_Error( 'ax_actors_remote_acct', __( 'Invalid remote acct address.', 'axismundi-actors' ) );
	}
	$handle    = trim( substr( $input, 0, $at ) );
	$authority = strtolower( rtrim( trim( substr( $input, $at + 1 ) ), '.' ) );
	if ( '' === $handle || '' === $authority || str_contains( $handle, '/' ) || str_contains( $authority, '/' ) || str_contains( $authority, '@' ) ) {
		return new WP_Error( 'ax_actors_remote_acct', __( 'Invalid remote acct address.', 'axismundi-actors' ) );
	}
	$probe = 'https://' . $authority . '/';
	if ( ! wp_http_validate_url( $probe ) || $authority === axismundi_actors_webfinger_authority() ) {
		return new WP_Error( 'ax_actors_remote_authority', __( 'Unsafe or local remote authority.', 'axismundi-actors' ) );
	}
	return array( 'acct' => $handle . '@' . $authority, 'handle' => $handle, 'authority' => $authority );
}

/**
 * Fetch one bounded HTTPS JSON document.
 *
 * @param string   $url           URL.
 * @param string[] $content_types Allowed media types, without parameters.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_actors_remote_get_json( string $url, array $content_types ) {
	if ( 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) || ! wp_http_validate_url( $url ) ) {
		return new WP_Error( 'ax_actors_remote_url', __( 'Unsafe remote URL.', 'axismundi-actors' ) );
	}
	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 10,
			'redirection'         => 0,
			'limit_response_size' => AXISMUNDI_ACTORS_REMOTE_MAX_BYTES,
			'headers'             => array( 'Accept' => implode( ', ', $content_types ) ),
			'user-agent'          => 'Axismundi Actors/' . AXISMUNDI_ACTORS_VERSION . '; ' . home_url( '/' ),
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'ax_actors_remote_status', __( 'The remote server returned an unexpected status.', 'axismundi-actors' ) );
	}
	$content_type = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $response, 'content-type' ) )[0] ) );
	if ( ! in_array( $content_type, $content_types, true ) ) {
		return new WP_Error( 'ax_actors_remote_content_type', __( 'The remote server returned an unsupported content type.', 'axismundi-actors' ) );
	}
	$body = (string) wp_remote_retrieve_body( $response );
	if ( '' === $body || strlen( $body ) >= AXISMUNDI_ACTORS_REMOTE_MAX_BYTES ) {
		return new WP_Error( 'ax_actors_remote_size', __( 'The remote response was empty or too large.', 'axismundi-actors' ) );
	}
	try {
		$data = json_decode( $body, true, 64, JSON_THROW_ON_ERROR );
	} catch ( JsonException $error ) {
		return new WP_Error( 'ax_actors_remote_json', __( 'The remote response was not valid JSON.', 'axismundi-actors' ) );
	}
	return is_array( $data ) ? $data : new WP_Error( 'ax_actors_remote_json', __( 'The remote response was not a JSON object.', 'axismundi-actors' ) );
}

/**
 * Extract a usable profile URL from an ActivityStreams `url` value.
 *
 * @param mixed  $value ActivityStreams url value.
 * @param string $fallback Canonical Actor id.
 * @return string
 */
function axismundi_actors_remote_profile_url( $value, string $fallback ) : string {
	$candidates = is_array( $value ) && array_is_list( $value ) ? $value : array( $value );
	foreach ( $candidates as $candidate ) {
		$url = is_string( $candidate ) ? $candidate : ( is_array( $candidate ) ? (string) ( $candidate['href'] ?? '' ) : '' );
		if ( in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) {
			return esc_url_raw( $url );
		}
	}
	return $fallback;
}

/**
 * Validate and normalize an ActivityStreams Actor document.
 *
 * @param array<string,mixed> $payload  Decoded Actor JSON.
 * @param string              $expected_uri WebFinger self URI.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_actors_normalize_remote_actor_payload( array $payload, string $expected_uri ) {
	$uri  = (string) ( $payload['id'] ?? '' );
	$type = (string) ( $payload['type'] ?? '' );
	if ( $uri !== $expected_uri || 'https' !== strtolower( (string) wp_parse_url( $uri, PHP_URL_SCHEME ) ) || ! wp_http_validate_url( $uri ) ) {
		return new WP_Error( 'ax_actors_remote_identity', __( 'The remote Actor identity did not match WebFinger.', 'axismundi-actors' ) );
	}
	if ( ! in_array( $type, array( 'Person', 'Organization', 'Application', 'Service', 'Group' ), true ) ) {
		return new WP_Error( 'ax_actors_remote_type', __( 'Unsupported remote Actor type.', 'axismundi-actors' ) );
	}
	$inbox  = esc_url_raw( (string) ( $payload['inbox'] ?? '' ) );
	$outbox = esc_url_raw( (string) ( $payload['outbox'] ?? '' ) );
	if ( 'https' !== strtolower( (string) wp_parse_url( $inbox, PHP_URL_SCHEME ) ) || ! wp_http_validate_url( $inbox ) || 'https' !== strtolower( (string) wp_parse_url( $outbox, PHP_URL_SCHEME ) ) || ! wp_http_validate_url( $outbox ) ) {
		return new WP_Error( 'ax_actors_remote_endpoints', __( 'The remote Actor has invalid inbox or outbox endpoints.', 'axismundi-actors' ) );
	}
	$username = sanitize_text_field( (string) ( $payload['preferredUsername'] ?? '' ) );
	if ( '' === $username ) {
		return new WP_Error( 'ax_actors_remote_username', __( 'The remote Actor has no preferred username.', 'axismundi-actors' ) );
	}
	return array(
		'uri'                => $uri,
		'actor_type'         => $type,
		'preferred_username' => axismundi_actors_remote_limit_text( $username, 191 ),
		'display_name'       => axismundi_actors_remote_limit_text( sanitize_text_field( wp_strip_all_tags( (string) ( $payload['name'] ?? '' ) ) ), 191 ),
		'summary'            => wp_kses_post( (string) ( $payload['summary'] ?? '' ) ),
		'profile_url'        => axismundi_actors_remote_profile_url( $payload['url'] ?? null, $uri ),
		'inbox_uri'          => $inbox,
		'outbox_uri'         => $outbox,
		'payload'            => $payload,
	);
}

/**
 * Fetch, validate, and persist one canonical Actor URI.
 *
 * @param string $self          Expected canonical ActivityStreams Actor URI.
 * @param string $verified_acct Optional already-verified bare acct address.
 * @return Axismundi_Actor|WP_Error
 */
function axismundi_actors_discover_remote_actor_uri( string $self, string $verified_acct = '' ) {
	if ( 'https' !== strtolower( (string) wp_parse_url( $self, PHP_URL_SCHEME ) ) || ! wp_http_validate_url( $self ) ) {
		return new WP_Error( 'ax_actors_remote_self', __( 'Unsafe ActivityStreams Actor URL.', 'axismundi-actors' ) );
	}
	$payload = axismundi_actors_remote_get_json( $self, array( 'application/activity+json', 'application/ld+json', 'application/json' ) );
	if ( is_wp_error( $payload ) ) {
		return $payload;
	}
	$record = axismundi_actors_normalize_remote_actor_payload( $payload, $self );
	if ( is_wp_error( $record ) ) {
		return $record;
	}
	$actor = axismundi_actors_upsert_remote( $record );
	if ( is_wp_error( $actor ) ) {
		return $actor;
	}
	if ( '' !== $verified_acct && ! axismundi_actors_record_verified_acct_address( $actor->get_identity_id(), $verified_acct ) ) {
		return new WP_Error( 'ax_actors_remote_address', __( 'Could not record the verified remote acct address.', 'axismundi-actors' ) );
	}
	/**
	 * A remote actor was discovered and persisted. The instance ledger caches its
	 * host's NodeInfo from here.
	 *
	 * @param Axismundi_Actor $actor Discovered remote actor.
	 */
	do_action( 'axismundi_actors_remote_actor_discovered', $actor );
	return $actor;
}

/**
 * Discover and persist one remote Actor from an acct address.
 *
 * @param string $acct Remote acct input.
 * @return Axismundi_Actor|WP_Error
 */
function axismundi_actors_discover_remote_actor( string $acct ) {
	$parsed = axismundi_actors_normalize_remote_acct( $acct );
	if ( is_wp_error( $parsed ) ) {
		return $parsed;
	}
	$webfinger_url = add_query_arg( 'resource', 'acct:' . $parsed['acct'], 'https://' . $parsed['authority'] . '/.well-known/webfinger' );
	$jrd = axismundi_actors_remote_get_json( $webfinger_url, array( 'application/jrd+json', 'application/json' ) );
	if ( is_wp_error( $jrd ) ) {
		return $jrd;
	}
	if ( strtolower( (string) ( $jrd['subject'] ?? '' ) ) !== 'acct:' . strtolower( $parsed['acct'] ) || ! isset( $jrd['links'] ) || ! is_array( $jrd['links'] ) ) {
		return new WP_Error( 'ax_actors_remote_webfinger', __( 'The WebFinger response did not match the requested acct address.', 'axismundi-actors' ) );
	}
	$self = '';
	foreach ( $jrd['links'] as $link ) {
		if ( ! is_array( $link ) || 'self' !== (string) ( $link['rel'] ?? '' ) ) {
			continue;
		}
		$type = strtolower( trim( explode( ';', (string) ( $link['type'] ?? '' ) )[0] ) );
		if ( in_array( $type, array( 'application/activity+json', 'application/ld+json' ), true ) ) {
			$self = (string) ( $link['href'] ?? '' );
			break;
		}
	}
	return axismundi_actors_discover_remote_actor_uri( $self, $parsed['acct'] );
}

/**
 * Admin-friendly discovery input: acct, `/@handle` profile URL, or canonical URI.
 * Profile aliases are resolved back through WebFinger rather than trusted as ids.
 *
 * @param string $input User input.
 * @return Axismundi_Actor|WP_Error
 */
function axismundi_actors_discover_remote_input( string $input ) {
	$input = trim( $input );
	if ( 'https' !== strtolower( (string) wp_parse_url( $input, PHP_URL_SCHEME ) ) ) {
		return axismundi_actors_discover_remote_actor( $input );
	}
	$host = axismundi_actors_webfinger_authority_from_url( $input );
	$path = trim( (string) wp_parse_url( $input, PHP_URL_PATH ), '/' );
	if ( '' !== $host && str_starts_with( $path, '@' ) && ! str_contains( $path, '/' ) ) {
		return axismundi_actors_discover_remote_actor( rawurldecode( substr( $path, 1 ) ) . '@' . $host );
	}
	return axismundi_actors_discover_remote_actor_uri( $input );
}
