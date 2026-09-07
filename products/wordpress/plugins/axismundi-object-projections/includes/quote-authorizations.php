<?php
/**
 * FEP-044f QuoteAuthorization representations.
 *
 * Activities owns identity and lifecycle state. This module only dereferences that state as
 * JSON-LD and never creates, approves, revokes, or forwards an authorization.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** Build an active authorization document without embedding either referenced Object. */
function axismundi_op_quote_authorization_document( array $authorization ) {
	$id      = (string) ( $authorization['authorization_uri'] ?? '' );
	$uuid    = (string) ( $authorization['uuid'] ?? '' );
	$author  = (string) ( $authorization['author_actor_uri'] ?? '' );
	$quoting = (string) ( $authorization['quoting_object_uri'] ?? '' );
	$quoted  = (string) ( $authorization['quoted_object_uri'] ?? '' );
	$actor   = '' !== $author && function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( $author ) : null;
	if ( '' === $id || ! wp_is_uuid( $uuid )
		|| ! function_exists( 'axismundi_act_quote_authorization_uri' )
		|| ! hash_equals( axismundi_act_quote_authorization_uri( $uuid ), $id )
		|| ! $actor instanceof Axismundi_Actor || ! $actor->is_local()
		|| 'public' !== $actor->get_status() || ! $actor->is_handle_locked()
		|| '' === $quoting || '' === $quoted || hash_equals( $quoting, $quoted )
	) {
		return new WP_Error( 'ax_op_quote_authorization', __( 'The QuoteAuthorization cannot be projected.', 'axismundi-object-projections' ) );
	}
	$object = array(
		'id'                => $id,
		'type'              => 'QuoteAuthorization',
		'attributedTo'      => $author,
		'interactingObject' => $quoting,
		'interactionTarget' => $quoted,
	);
	return array_merge( array( '@context' => axismundi_op_jsonld_context( $object ) ), $object );
}

/** Build a privacy-minimal tombstone for a revoked authorization. */
function axismundi_op_quote_authorization_tombstone( array $authorization ) : array {
	$object = array(
		'id'         => (string) $authorization['authorization_uri'],
		'type'       => 'Tombstone',
		'formerType' => 'QuoteAuthorization',
	);
	if ( ! empty( $authorization['revoked_at'] ) ) {
		$timestamp = strtotime( (string) $authorization['revoked_at'] . ' UTC' );
		if ( false !== $timestamp ) {
			$object['deleted'] = gmdate( 'c', $timestamp );
		}
	}
	return array_merge( array( '@context' => axismundi_op_jsonld_context( $object ) ), $object );
}

/** Whether this request targets the exact canonical authorization query surface. */
function axismundi_op_quote_authorization_request_uuid() : ?string {
	if ( ! isset( $_GET['ax_quote_authorization'] ) || array_keys( $_GET ) !== array( 'ax_quote_authorization' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public identity route.
		return null;
	}
	$request_path = (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_PATH );
	$home_path    = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( untrailingslashit( $request_path ) !== untrailingslashit( $home_path ) ) {
		return null;
	}
	$uuid = strtolower( sanitize_text_field( wp_unslash( $_GET['ax_quote_authorization'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public identity route.
	return wp_is_uuid( $uuid ) ? $uuid : null;
}

/** Emit one authorization or revoked tombstone before core canonical redirects. */
function axismundi_op_quote_authorization_template_redirect() : void {
	$uuid = axismundi_op_quote_authorization_request_uuid();
	if ( null === $uuid || ! function_exists( 'axismundi_act_get_quote_authorization' ) || ! function_exists( 'axismundi_act_quote_authorization_uri' ) ) {
		return;
	}
	$method = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) );
	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
		status_header( 405 );
		header( 'Allow: GET, HEAD' );
		exit;
	}
	$authorization = axismundi_act_get_quote_authorization( axismundi_act_quote_authorization_uri( $uuid ) );
	if ( ! is_array( $authorization ) ) {
		axismundi_op_emit_error( 404 );
	}
	$status = 'revoked' === (string) $authorization['status'] ? 410 : 200;
	$object = 410 === $status
		? axismundi_op_quote_authorization_tombstone( $authorization )
		: axismundi_op_quote_authorization_document( $authorization );
	if ( is_wp_error( $object ) ) {
		axismundi_op_emit_error( 404 );
	}
	status_header( $status );
	header( 'Content-Type: application/activity+json; charset=' . get_option( 'blog_charset' ) );
	header( 'Cache-Control: no-store' );
	header( 'Access-Control-Allow-Origin: *' );
	header( 'Access-Control-Allow-Methods: GET, HEAD' );
	header( 'X-Content-Type-Options: nosniff' );
	if ( 'HEAD' !== $method ) {
		echo wp_json_encode( $object, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded response.
	}
	exit;
}
add_action( 'template_redirect', 'axismundi_op_quote_authorization_template_redirect', -1 );
