<?php
/**
 * Axismundi Object Projections integration.
 *
 * The Object body is the surface where the three emoji layers actually meet: a remote
 * post can contain a declared custom `:misskey:`, an undeclared `:cool:` that belongs
 * to Core's smilies, and Unicode that belongs to nobody here. Decoration therefore runs
 * after sanitizing and before `convert_smilies()`, claiming only what the Object itself
 * declared and leaving the rest to the layer that owns it.
 *
 * Guarded throughout: with Object Projections absent, nothing in this file runs.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit;

/**
 * Record the emoji a stored remote Object declares.
 *
 * @param mixed $stored Stored remote-object row or payload.
 * @return void
 */
function axismundi_emoji_observe_remote_object( $stored ) : void {
	if ( ! is_array( $stored ) || ! axismundi_emoji_ready() ) {
		return;
	}
	$uri     = (string) ( $stored['object_uri'] ?? $stored['id'] ?? '' );
	$payload = $stored['payload'] ?? $stored['payload_json'] ?? null;
	if ( is_string( $payload ) ) {
		$payload = json_decode( $payload, true );
	}
	if ( ! is_array( $payload ) ) {
		// The row may be the payload itself rather than a wrapper around one.
		$payload = isset( $stored['tag'] ) ? $stored : null;
	}
	if ( ! is_array( $payload ) || '' === $uri ) {
		return;
	}
	axismundi_emoji_observe_payload( $payload, $uri, 'object' );
}
add_action( 'axismundi_op_remote_object_observed', 'axismundi_emoji_observe_remote_object', 40 );
add_action( 'axismundi_op_remote_object_fetched', 'axismundi_emoji_observe_remote_object', 40 );

/**
 * The declarations belonging to one Object view model.
 *
 * Remote Objects read their own stored payload. Local Objects reconstruct that same
 * payload through the source transformer, so the document's current outbound `tag[]`
 * is also the declaration that governs its home rendering. This keeps local rendering
 * and federation in lockstep without treating every locally-known shortcode as used.
 *
 * @param array<string,mixed> $model Object view model.
 * @return array<string,array<string,array<string,mixed>>>
 */
function axismundi_emoji_object_declarations( array $model ) : array {
	$uri = (string) ( $model['object_uri'] ?? $model['id'] ?? '' );
	if ( '' === $uri || ! function_exists( 'axismundi_op_remote_object_get' ) || ! axismundi_emoji_ready() ) {
		return array();
	}
	$row = axismundi_op_remote_object_get( $uri );
	if ( is_array( $row ) ) {
		$payload = $row['payload'] ?? $row['payload_json'] ?? null;
		if ( is_string( $payload ) ) {
			$payload = json_decode( $payload, true );
		}
		return is_array( $payload ) ? axismundi_emoji_declaration_map( $payload, $uri ) : array();
	}

	if ( ! function_exists( 'axismundi_op_resolve_source_by_uri' ) || ! function_exists( 'axismundi_op_transform_object' ) ) {
		return array();
	}
	$source = axismundi_op_resolve_source_by_uri( $uri );
	if ( null === $source ) {
		return array();
	}
	$payload = axismundi_op_transform_object( $source );
	return is_array( $payload ) ? axismundi_emoji_local_declaration_map( $payload ) : array();
}

/**
 * Decorate a sanitized Object body.
 *
 * @param string              $body  Sanitized body HTML.
 * @param array<string,mixed> $model Object view model.
 * @return string
 */
function axismundi_emoji_decorate_object_content( string $body, array $model ) : string {
	if ( false === strpos( $body, ':' ) ) {
		return $body;
	}
	$map = axismundi_emoji_object_declarations( $model );
	return array() === $map ? $body : axismundi_emoji_decorate( $body, $map );
}

/*
 * Priority 9: `convert_smilies()` runs at 20 on the content filters, and this must come
 * first so a shortcode the Object declared becomes its custom emoji rather than a Core
 * smiley. Anything undeclared falls through untouched and Core handles it exactly as the
 * site owner configured — the two layers cooperate rather than compete.
 */
add_filter( 'axismundi_op_object_content_html', 'axismundi_emoji_decorate_object_content', 9, 2 );
