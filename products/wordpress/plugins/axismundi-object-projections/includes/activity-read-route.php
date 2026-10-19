<?php
/** Canonical public Activity reads; snapshots stay in the Activities ledger. */
defined( 'ABSPATH' ) || exit;

/** Null outside our namespace, empty for malformed paths, otherwise the canonical URI. */
function axismundi_op_requested_activity_uri( string $request_uri ) : ?string {
	$path = wp_parse_url( $request_uri, PHP_URL_PATH );
	$base = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ) . '/activities';
	if ( ! is_string( $path ) || ( $path !== $base && ! str_starts_with( $path, $base . '/' ) ) ) {
		return null;
	}
	$pattern = '#\A' . preg_quote( $base, '#' ) . '/([a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12})/?\z#i';
	if ( ! preg_match( $pattern, $path, $match ) || ! function_exists( 'axismundi_act_local_uri' ) ) {
		return '';
	}
	return axismundi_act_local_uri( strtolower( $match[1] ) );
}

/** Read a local public snapshot, never synthesize an Activity from its current Object. */
function axismundi_op_read_activity_jsonld( string $uri ) {
	$missing = new WP_Error( 'ax_op_activity_not_found', 'The public Activity was not found.', array( 'status' => 404 ) );
	if ( ! function_exists( 'axismundi_act_get' ) ) {
		return $missing;
	}
	$activity = axismundi_act_get( $uri );
	if ( ! $activity instanceof Axismundi_Activity || 'outbound' !== $activity->get_direction() || ! $activity->is_effective() || null === $activity->get_local_uuid() || axismundi_act_local_uri( $activity->get_local_uuid() ) !== $uri ) {
		return $missing;
	}
	$actor = axismundi_actors_get_by_uri( $activity->get_actor_uri() );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || ! axismundi_op_actor_visible( $actor ) ) {
		return $missing;
	}
	// Reuse the Outbox's domain-owned admission seam, including deliberate public opt-ins.
	$payload = apply_filters( 'axismundi_act_public_outbox_payload', axismundi_act_public_payload( $activity ), $activity );
	if ( ! is_array( $payload ) ) {
		return $missing;
	}
	unset( $payload['bto'], $payload['bcc'] );
	if ( ( $payload['id'] ?? '' ) !== $uri || axismundi_act_type( $payload['type'] ?? '' ) !== $activity->get_type() || axismundi_act_member_uri( $payload['actor'] ?? '' ) !== $activity->get_actor_uri() ) {
		return new WP_Error( 'ax_op_activity_mismatch', 'The public snapshot contradicts its ledger identity.', array( 'status' => 500 ) );
	}
	$assembly = axismundi_op_resolve_object_assembly( $activity->get_type(), 'jsonld' );
	if ( is_wp_error( $assembly ) ) {
		return $assembly;
	}
	try {
		$document = call_user_func( $assembly['jsonld'], $payload, 'snapshot' );
	} catch ( Throwable $error ) {
		return new WP_Error( 'ax_op_activity_assembly', 'The Activity assembly failed.', array( 'status' => 500 ) );
	}
	if ( is_wp_error( $document ) ) {
		return $document;
	}
	if ( ! is_array( $document ) ) {
		return new WP_Error( 'ax_op_activity_assembly', 'The Activity assembly returned no document.', array( 'status' => 500 ) );
	}
	// A historical local snapshot keeps its authored context, including extension terms.
	$document['@context'] = $payload['@context'] ?? axismundi_op_jsonld_context( $payload );
	$expected = $payload;
	$expected['@context'] = $document['@context'];
	// The application assembler must not rewrite a published Activity's history.
	if ( axismundi_op_order_object_members( $document ) !== axismundi_op_order_object_members( $expected ) ) {
		return new WP_Error( 'ax_op_activity_snapshot_changed', 'The assembly changed the historical snapshot.', array( 'status' => 500 ) );
	}
	return axismundi_op_order_object_members( $document );
}

/** Serve only negotiated GET/HEAD, before canonical redirects; HTML routing is untouched. */
function axismundi_op_activity_template_redirect() : void {
	if ( ! axismundi_op_is_negotiated_request() ) {
		return;
	}
	$uri = axismundi_op_requested_activity_uri( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
	if ( null === $uri ) {
		return;
	}
	$document = '' === $uri ? new WP_Error( 'ax_op_activity_not_found', '', array( 'status' => 404 ) ) : axismundi_op_read_activity_jsonld( $uri );
	nocache_headers();
	if ( is_wp_error( $document ) ) {
		axismundi_op_emit_error( (int) ( $document->get_error_data()['status'] ?? 500 ) );
	}
	status_header( 200 );
	header( 'Content-Type: application/activity+json; charset=' . get_option( 'blog_charset' ) );
	header( 'Vary: Accept', false );
	header( 'Access-Control-Allow-Origin: *' );
	header( 'Access-Control-Allow-Methods: GET, HEAD' );
	header( 'X-Content-Type-Options: nosniff' );
	if ( 'HEAD' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
		echo wp_json_encode( $document, JSON_HEX_TAG | JSON_HEX_AMP ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON document, not HTML.
	}
	exit;
}
add_action( 'template_redirect', 'axismundi_op_activity_template_redirect', 0 );
