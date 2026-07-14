<?php
/**
 * Standalone HTTP content negotiation on existing WordPress object URLs.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether this plugin may own content negotiation for the current request.
 *
 * The official ActivityPub plugin already negotiates the same canonical URLs. Its
 * presence therefore disables this standalone router, while keeping registry and
 * renderer APIs available to a future compatibility adapter.
 *
 * @return bool
 */
function axismundi_op_standalone_router_enabled() : bool {
	$enabled = ! defined( 'ACTIVITYPUB_PLUGIN_VERSION' );
	/**
	 * Filter standalone negotiation ownership.
	 *
	 * @since 0.0.2
	 * @param bool $enabled Whether this plugin may negotiate canonical URLs.
	 */
	return (bool) apply_filters( 'axismundi_op_standalone_router_enabled', $enabled );
}

/**
 * Parse one Accept header for a precise ActivityStreams representation.
 *
 * Bare application/json and unprofiled application/ld+json are intentionally not
 * accepted, preventing API clients from unexpectedly receiving ActivityStreams.
 *
 * @param string $accept Raw Accept header.
 * @return bool
 */
function axismundi_op_accepts_activitystreams( string $accept ) : bool {
	foreach ( explode( ',', strtolower( $accept ) ) as $range ) {
		$range = trim( $range );
		if ( '' === $range || preg_match( '/(?:^|;)\s*q\s*=\s*0(?:\.0*)?\s*(?:;|$)/', $range ) ) {
			continue;
		}
		if ( 1 === preg_match( '/^application\/activity\+json(?:\s*;|$)/', $range ) ) {
			return true;
		}
		if ( 1 === preg_match( '/^application\/ld\+json(?:\s*;|$)/', $range )
			&& false !== strpos( $range, 'profile="https://www.w3.org/ns/activitystreams"' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Whether an explicit browser-friendly representation selector is present.
 *
 * Like the official ActivityPub plugin, the parameter's presence is the signal; its
 * value is not part of object identity and is intentionally ignored.
 *
 * @return bool
 */
function axismundi_op_explicit_activitypub_requested() : bool {
	return isset( $_GET['activitypub'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public representation selector.
}

/**
 * Whether the current request asks for ActivityStreams JSON-LD.
 *
 * @return bool
 */
function axismundi_op_is_negotiated_request() : bool {
	if ( ! axismundi_op_standalone_router_enabled() || is_admin() || wp_doing_ajax() || wp_is_serving_rest_request() || is_feed() ) {
		return false;
	}
	$method = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) );
	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
		return false;
	}
	if ( axismundi_op_explicit_activitypub_requested() ) {
		return true;
	}
	$accept = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ?? '' ) );
	return axismundi_op_accepts_activitystreams( $accept );
}

/**
 * Resolve the current WordPress source without changing query state.
 *
 * @return mixed|null
 */
function axismundi_op_current_source() {
	$source = get_queried_object();
	return $source instanceof WP_Post ? $source : null;
}

/**
 * Merge one token into a comma-separated response header.
 *
 * @param string $current Existing value.
 * @param string $token   Token to add.
 * @return string
 */
function axismundi_op_merge_header_token( string $current, string $token ) : string {
	$tokens = array_filter( array_map( 'trim', explode( ',', $current ) ) );
	$lower  = array_map( 'strtolower', $tokens );
	if ( ! in_array( strtolower( $token ), $lower, true ) ) {
		$tokens[] = $token;
	}
	return implode( ', ', $tokens );
}

/**
 * Emit a negotiated error without exposing private source details.
 *
 * @param int $status HTTP status.
 * @return void
 */
function axismundi_op_emit_error( int $status ) : void {
	status_header( $status );
	header( 'Content-Type: application/activity+json; charset=' . get_option( 'blog_charset' ) );
	header( 'Vary: Accept', false );
	header( 'X-Content-Type-Options: nosniff' );
	if ( 'HEAD' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) ) {
		echo wp_json_encode( array( 'error' => 404 === $status ? 'not_found' : 'projection_failed' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded response.
	}
	exit;
}

/**
 * Serve the negotiated representation before core canonical redirects run.
 *
 * @return void
 */
function axismundi_op_template_redirect() : void {
	if ( ! axismundi_op_is_negotiated_request() ) {
		return;
	}
	$source = axismundi_op_current_source();
	if ( null === $source ) {
		return;
	}

	$object = axismundi_op_transform_object( $source );
	if ( is_wp_error( $object ) ) {
		if ( 'ax_op_no_transformer' === $object->get_error_code() ) {
			return;
		}
		axismundi_op_emit_error( 'ax_op_not_public' === $object->get_error_code() ? 404 : 500 );
	}

	status_header( 200 );
	header( 'Content-Type: application/activity+json; charset=' . get_option( 'blog_charset' ) );
	header( 'Vary: Accept', false );
	header( 'Access-Control-Allow-Origin: *' );
	header( 'Access-Control-Allow-Methods: GET, HEAD' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Link: <' . esc_url_raw( (string) $object['url'] ) . '>; rel="alternate"; type="text/html"', false );
	if ( 'HEAD' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) ) {
		echo wp_json_encode( $object, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded response.
	}
	exit;
}
add_action( 'template_redirect', 'axismundi_op_template_redirect', 1 );

/**
 * Add alternate discovery and cache variation to a public HTML object response.
 *
 * @param array<string,string> $headers Response headers.
 * @return array<string,string>
 */
function axismundi_op_html_headers( array $headers ) : array {
	if ( ! axismundi_op_standalone_router_enabled() || axismundi_op_is_negotiated_request() ) {
		return $headers;
	}
	$source = axismundi_op_current_source();
	if ( null === $source ) {
		return $headers;
	}
	$transformer = axismundi_op_resolve_object_transformer( $source );
	if ( null === $transformer ) {
		return $headers;
	}
	try {
		if ( null !== $transformer['visible'] && true !== call_user_func( $transformer['visible'], $source ) ) {
			return $headers;
		}
		$id = (string) call_user_func( $transformer['uri'], $source );
	} catch ( Throwable $error ) {
		return $headers;
	}
	if ( '' === $id ) {
		return $headers;
	}
	$headers['Vary'] = axismundi_op_merge_header_token( (string) ( $headers['Vary'] ?? '' ), 'Accept' );
	$link            = '<' . esc_url_raw( $id ) . '>; rel="alternate"; type="application/activity+json"';
	$headers['Link'] = isset( $headers['Link'] ) && '' !== $headers['Link'] ? $headers['Link'] . ', ' . $link : $link;
	return $headers;
}
add_filter( 'wp_headers', 'axismundi_op_html_headers' );

/**
 * Print HTML discovery metadata for public projected objects.
 *
 * @return void
 */
function axismundi_op_html_alternate_link() : void {
	$source = axismundi_op_current_source();
	if ( null === $source || ! axismundi_op_standalone_router_enabled() ) {
		return;
	}
	$transformer = axismundi_op_resolve_object_transformer( $source );
	if ( null === $transformer ) {
		return;
	}
	try {
		if ( null !== $transformer['visible'] && true !== call_user_func( $transformer['visible'], $source ) ) {
			return;
		}
		$id = (string) call_user_func( $transformer['uri'], $source );
	} catch ( Throwable $error ) {
		return;
	}
	if ( '' !== $id ) {
		printf( "\n<link rel=\"alternate\" type=\"application/activity+json\" href=\"%s\" />\n", esc_url( $id ) );
	}
}
add_action( 'wp_head', 'axismundi_op_html_alternate_link' );
