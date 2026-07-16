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
	if ( is_array( $object ) && array_key_exists( 'sensitive', $object ) ) {
		$context[] = array( 'sensitive' => 'as:sensitive' );
	}
	if ( is_array( $object ) && array_key_exists( 'dcterms:subject', $object ) ) {
		$context[] = array( 'dcterms' => 'http://purl.org/dc/terms/' );
	}
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
 * Resolve the HTML representation URL from a string or Link-valued `url`.
 *
 * An ordered FEP-1311 `url[]` deliberately lists media Links before the human page, so the
 * first href is a media file. Match on `mediaType` first; only fall back to the first href
 * when nothing declares itself as HTML, which keeps `url`-derived required members present.
 */
function axismundi_op_object_html_url( array $object ) : string {
	$url = $object['url'] ?? '';
	if ( is_string( $url ) ) {
		return $url;
	}
	if ( isset( $url['href'] ) && is_string( $url['href'] ) ) {
		return $url['href'];
	}
	$links = is_array( $url ) ? $url : array();
	foreach ( $links as $link ) {
		if ( is_array( $link ) && 'text/html' === ( $link['mediaType'] ?? '' ) && isset( $link['href'] ) && is_string( $link['href'] ) ) {
			return $link['href'];
		}
	}
	foreach ( $links as $link ) {
		if ( is_array( $link ) && isset( $link['href'] ) && is_string( $link['href'] ) ) {
			return $link['href'];
		}
	}
	return '';
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
	// Name is plain text; content / summary use the dedicated federation allowlist.
	if ( isset( $object['name'] ) ) {
		$object['name'] = sanitize_text_field( wp_strip_all_tags( (string) $object['name'] ) );
	}
	foreach ( axismundi_op_html_members() as $member ) {
		if ( isset( $object[ $member ] ) ) {
			$object[ $member ] = axismundi_op_clean_html( (string) $object[ $member ] );
		}
	}
	// A preview is an embedded fallback object, not an independently identified object.
	if ( isset( $object['preview'] ) && is_array( $object['preview'] ) ) {
		if ( isset( $object['preview']['name'] ) ) {
			$object['preview']['name'] = sanitize_text_field( wp_strip_all_tags( (string) $object['preview']['name'] ) );
		}
		foreach ( axismundi_op_html_members() as $member ) {
			if ( isset( $object['preview'][ $member ] ) ) {
				$object['preview'][ $member ] = axismundi_op_clean_html( (string) $object['preview'][ $member ] );
			}
		}
		unset( $object['preview']['@context'] );
	}
	// The renderer is the sole owner of @context — drop any caller-supplied one, then
	// prepend the canonical context so it is deterministic and first.
	unset( $object['@context'] );
	return array_merge( array( '@context' => axismundi_op_jsonld_context( $object ) ), $object );
}

/**
 * Validate and finalize one Activity payload for transport.
 *
 * Activities owns the immutable domain payload; this renderer remains the sole owner of
 * JSON-LD context assembly. Activity documents require actor rather than the object
 * projection members attributedTo/url.
 *
 * @param array<string,mixed> $activity    Activity payload.
 * @param string              $expected_id Ledger Activity URI.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_op_finalize_activity( array $activity, string $expected_id ) {
	foreach ( array( 'id', 'type', 'actor' ) as $member ) {
		if ( ! isset( $activity[ $member ] ) || '' === $activity[ $member ] ) {
			return new WP_Error( 'ax_op_invalid_activity', __( 'The Activity is missing a required member.', 'axismundi-object-projections' ) );
		}
	}
	if ( (string) $activity['id'] !== $expected_id ) {
		return new WP_Error( 'ax_op_id_mismatch', __( 'An Activity id must equal its ledger URI.', 'axismundi-object-projections' ) );
	}
	unset( $activity['@context'] );
	return array_merge( array( '@context' => axismundi_op_jsonld_context( $activity ) ), $activity );
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
