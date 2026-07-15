<?php
/**
 * Phase 1 — the renderer.
 *
 * A transformer produces a plain associative array; the renderer is the **single owner**
 * of validation, HTML sanitization, and the JSON-LD `@context`. It guarantees the four
 * required members (`id`, `type`, `attributedTo`, `url`), that the emitted `id` equals
 * the transformer's declared object URI (a transformer cannot mint a mismatched id), and
 * that no caller-supplied `@context` leaks through. It distinguishes three outcomes:
 * no transformer, a transformer error, and a not-public source — never conflating them.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/**
 * The ActivityStreams 2.0 context this renderer owns. Extensions may be added through
 * the `axismundi_op_jsonld_context` filter — never by a transformer's return value.
 *
 * @param array<string,mixed>|null $object Object being finalized, when available.
 * @return array<int,mixed>|string
 */
function axismundi_op_jsonld_context( ?array $object = null ) {
	$context = array( 'https://www.w3.org/ns/activitystreams' );
	/**
	 * Filter the JSON-LD `@context` entries.
	 *
	 * @since 0.0.1
	 * @param array<int,mixed>        $context Context IRIs / inline maps.
	 * @param array<string,mixed>|null $object Object being finalized, when available.
	 */
	$context = (array) apply_filters( 'axismundi_op_jsonld_context', $context, $object );
	return 1 === count( $context ) ? $context[0] : array_values( $context );
}

/**
 * Members whose values are HTML and must be sanitized before emission.
 *
 * @return array<int,string>
 */
function axismundi_op_html_members() : array {
	return array( 'content', 'summary' );
}

/**
 * Validate + finalize one transformer result into an emittable JSON-LD object.
 *
 * @param array<string,mixed> $object      Transformer output.
 * @param string              $expected_id The transformer's declared object URI.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_op_finalize_object( array $object, string $expected_id ) {
	$actor_types = array( 'Application', 'Group', 'Organization', 'Person', 'Service' );
	$required    = in_array( (string) ( $object['type'] ?? '' ), $actor_types, true )
		? array( 'id', 'type', 'url' )
		: array( 'id', 'type', 'attributedTo', 'url' );
	foreach ( $required as $member ) {
		if ( ! isset( $object[ $member ] ) || '' === $object[ $member ] ) {
			return new WP_Error( 'ax_op_invalid_object', __( 'The projected value is missing a required member.', 'axismundi-object-projections' ) );
		}
	}
	if ( (string) $object['id'] !== $expected_id ) {
		return new WP_Error( 'ax_op_id_mismatch', __( 'A projected object id must equal its declared object URI.', 'axismundi-object-projections' ) );
	}
	// Name is plain text; content / summary are a bounded HTML allowlist.
	if ( isset( $object['name'] ) ) {
		$object['name'] = sanitize_text_field( wp_strip_all_tags( (string) $object['name'] ) );
	}
	foreach ( axismundi_op_html_members() as $member ) {
		if ( isset( $object[ $member ] ) ) {
			$object[ $member ] = wp_kses_post( (string) $object[ $member ] );
		}
	}
	// The renderer is the sole owner of @context — drop any caller-supplied one, then
	// prepend the canonical context so it is deterministic and first.
	unset( $object['@context'] );
	return array_merge( array( '@context' => axismundi_op_jsonld_context( $object ) ), $object );
}

/**
 * Project one WordPress source into an ActivityStreams object.
 *
 * @param mixed $source Source (e.g. WP_Post).
 * @return array<string,mixed>|WP_Error `ax_op_no_transformer` when nothing supports the
 *                                      source, `ax_op_not_public` when a visibility gate
 *                                      denies it, or the transformer's own WP_Error.
 */
function axismundi_op_transform_object( $source ) {
	$transformer = axismundi_op_resolve_object_transformer( $source );
	if ( null === $transformer ) {
		return new WP_Error( 'ax_op_no_transformer', __( 'No transformer supports this source.', 'axismundi-object-projections' ) );
	}
	return axismundi_op_run_transformer( $transformer, $source );
}

/**
 * Project one WordPress source into an ActivityStreams collection.
 *
 * @param mixed $source Source.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_op_transform_collection( $source ) {
	$transformer = axismundi_op_resolve_collection_transformer( $source );
	if ( null === $transformer ) {
		return new WP_Error( 'ax_op_no_transformer', __( 'No transformer supports this source.', 'axismundi-object-projections' ) );
	}
	return axismundi_op_run_transformer( $transformer, $source );
}

/**
 * Run one resolved transformer: visibility gate → declared URI → transform → finalize.
 * Callback exceptions become a WP_Error rather than a fatal.
 *
 * @param array<string,mixed> $transformer Resolved definition.
 * @param mixed               $source      Source.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_op_run_transformer( array $transformer, $source ) {
	try {
		if ( null !== $transformer['visible'] && true !== call_user_func( $transformer['visible'], $source ) ) {
			return new WP_Error( 'ax_op_not_public', __( 'This source is not publicly projectable.', 'axismundi-object-projections' ) );
		}
		$expected_id = (string) call_user_func( $transformer['uri'], $source );
		if ( '' === $expected_id ) {
			return new WP_Error( 'ax_op_no_uri', __( 'The transformer produced no object URI.', 'axismundi-object-projections' ) );
		}
		$result = call_user_func( $transformer['transform'], $source );
	} catch ( \Throwable $error ) {
		return new WP_Error( 'ax_op_transform_threw', __( 'The transformer raised an error.', 'axismundi-object-projections' ) );
	}
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	if ( ! is_array( $result ) ) {
		return new WP_Error( 'ax_op_transform_result', __( 'A transformer must return an array or WP_Error.', 'axismundi-object-projections' ) );
	}
	return axismundi_op_finalize_object( $result, $expected_id );
}
