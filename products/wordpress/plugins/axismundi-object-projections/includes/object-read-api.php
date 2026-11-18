<?php
/** Registry-only Object reads for application consumers, not federation transport. */
defined( 'ABSPATH' ) || exit;

/** Load Core sources by the registry reference; never infer a post from a URI. */
function axismundi_op_load_registered_core_source( array $object, array $identity ) {
	$post = get_post( (int) ( $object['source_ref'] ?? 0 ) );
	return $post instanceof WP_Post && $post->post_type === $object['source_kind'] ? $post : null;
}

/** Load the exact cached observation named by the registry. This read does not touch leases. */
function axismundi_op_load_registered_remote_source( array $object, array $identity ) {
	$row = axismundi_op_get_remote_object( (string) $identity['canonical_uri'], false );
	return is_array( $row ) && (int) $row['id'] === (int) $object['source_ref']
		? new Axismundi_Op_Remote_Source( $row ) : null;
}

/** Reuse the owning local projection and its existing public disclosure gate. */
function axismundi_op_read_local_jsonld_source( $source, array $object, array $identity ) {
	if ( $source instanceof Axismundi_OP_Local_Source ) {
		return ! empty( $source->row['publicly_readable'] )
			? array( 'document' => $source->row['document'] )
			: new WP_Error( 'ax_op_object_read_not_found', 'The Object is not publicly readable.', array( 'status' => 404 ) );
	}
	$document = axismundi_op_transform_object( $source );
	if ( is_wp_error( $document ) && in_array( $document->get_error_code(), array( 'ax_op_not_public', 'ax_op_no_transformer' ), true ) ) {
		return new WP_Error( 'ax_op_object_read_not_found', 'The Object is not publicly readable.', array( 'status' => 404 ) );
	}
	return is_array( $document ) ? array( 'document' => $document ) : $document;
}



/** Return the stored remote document, not a reconstruction from indexing columns. */
function axismundi_op_read_remote_jsonld_source( Axismundi_Op_Remote_Source $source, array $object, array $identity ) : array {
	$row = $source->get_row();
	$payload = json_decode( (string) ( $row['payload_json'] ?? '' ) );
	$document = $payload instanceof stdClass ? get_object_vars( $payload ) : null;
	if ( 'tombstone' === ( $row['object_status'] ?? '' ) ) {
		$document = array( 'id' => $row['object_uri'], 'type' => 'Tombstone' );
	}
	return array( 'document' => $document );
}

/** Disclosure filtering is independent of HTML rendering; preserve contexts and extensions. */
function axismundi_op_read_document_addresses( array $document, bool $show_top_level_blind = false, int $depth = 0 ) : array {
	foreach ( $document as $key => $value ) {
		if ( in_array( $key, array( 'bto', 'bcc' ), true ) && ! ( 0 === $depth && $show_top_level_blind ) ) {
			unset( $document[ $key ] );
		} elseif ( '@context' !== $key && is_array( $value ) ) {
			$document[ $key ] = axismundi_op_read_document_addresses( $value, false, $depth + 1 );
		} elseif ( '@context' !== $key && $value instanceof stdClass ) {
			$document[ $key ] = (object) axismundi_op_read_document_addresses( get_object_vars( $value ), false, $depth + 1 );
		}
	}
	return $document;
}

/** Default JSON-LD type assembly; canonical context and HTML normalization are shared. */
function axismundi_op_assemble_read_jsonld( array $document, string $representation = 'application' ) : array {
	if ( 'snapshot' === $representation ) {
		unset( $document['bto'], $document['bcc'] );
		return $document;
	}
	unset( $document['@context'], $document['bto'], $document['bcc'] );
	if ( is_string( $document['url'] ?? null ) ) {
		$document['url'] = array( 'type' => 'Link', 'href' => $document['url'] );
		if ( ! empty( $document['mediaType'] ) ) {
			$document['url']['mediaType'] = $document['mediaType'];
		}
	}
	if ( isset( $document['name'] ) ) {
		$document['name'] = sanitize_text_field( wp_strip_all_tags( (string) $document['name'] ) );
	}
	foreach ( axismundi_op_html_members() as $term ) {
		if ( isset( $document[ $term ] ) ) {
			$document[ $term ] = axismundi_op_clean_html( (string) $document[ $term ] );
		}
	}
	foreach ( array( 'nameMap' => false, 'contentMap' => true, 'summaryMap' => true ) as $term => $html ) {
		if ( isset( $document[ $term ] ) ) {
			$document[ $term ] = axismundi_op_clean_language_map( $document[ $term ], $html );
		}
	}
	return $document;
}

/**
 * One registry-only read, by integer identity id or exact canonical URI.
 * Elevated classes require a trusted server grant; the REST route exposes only public reads.
 *
 * @return array<string,mixed>|WP_Error
 */
function axismundi_op_read_object_jsonld( $id, string $requester = 'public', bool $granted = false ) {
	if ( ! in_array( $requester, array( 'public', 'owner', 'authenticated-peer' ), true ) || ( 'public' !== $requester && ! $granted ) ) {
		return new WP_Error( 'ax_op_object_read_forbidden', 'An explicit disclosure grant is required.', array( 'status' => 403 ) );
	}
	$object = is_int( $id ) ? axismundi_op_get_object_by_identity( $id ) : ( is_string( $id ) ? axismundi_op_get_object_by_uri( $id ) : null );
	$identity = $object;
	if ( ! is_array( $object ) || empty( $object['canonical_uri'] ) || 'public' !== $object['status'] ) {
		return new WP_Error( 'ax_op_object_read_not_found', 'The registered Object was not found.', array( 'status' => 404 ) );
	}
	$reader = null;
	foreach ( axismundi_op_object_source_readers() as $candidate ) {
		foreach ( $candidate['bindings'] ?? array() as $binding ) {
			if ( $binding['owner'] === $object['source_owner'] && $binding['kind'] === $object['source_kind'] ) {
				$reader = $candidate;
				break 2;
			}
		}
	}
	if ( null === $reader ) {
		return new WP_Error( 'ax_op_object_reader_missing', 'No reader owns this registry source.', array( 'status' => 404 ) );
	}
	try {
		$source = call_user_func( $reader['load'], $object, $identity );
		if ( null === $source || is_wp_error( $source ) ) {
			return new WP_Error( 'ax_op_object_read_not_found', 'The registered source is unavailable.', array( 'status' => 404 ) );
		}
		if ( 'remote' === $identity['origin'] && ( ! $source instanceof Axismundi_Op_Remote_Source || ( 'public' === $requester && ! axismundi_op_cached_object_publicly_viewable( $source->get_row() ) ) ) ) {
			return new WP_Error( 'ax_op_object_read_not_found', 'The Object is not publicly readable.', array( 'status' => 404 ) );
		}
		$facts = call_user_func( $reader['document'], $source, $object, $identity );
		if ( is_wp_error( $facts ) ) {
			return $facts;
		}
		$document = $facts['document'] ?? null;
		if ( ! is_array( $document ) || ( $document['id'] ?? '' ) !== $identity['canonical_uri'] || ( $document['type'] ?? '' ) !== $object['as2_type'] ) {
			return new WP_Error( 'ax_op_object_read_mismatch', 'The source contradicts its registry identity or type.', array( 'status' => 409 ) );
		}
		return axismundi_op_read_document_addresses( $document );
	} catch ( Throwable $error ) {
		return new WP_Error( 'ax_op_object_read_failed', 'The Object reader failed.', array( 'status' => 500 ) );
	}
}

/** Public application API: no requester or grant is accepted from query parameters. */
function axismundi_op_get_object_read( WP_REST_Request $request ) {
	$uri = $request->get_param( 'uri' );
	$id = $request->get_param( 'id' );
	if ( ( null === $uri ) === ( null === $id ) ) {
		return new WP_Error( 'ax_op_object_read_argument', 'Supply exactly one canonical URI or identity id.', array( 'status' => 400 ) );
	}
	$result = axismundi_op_read_object_jsonld( null !== $uri ? $uri : (int) $id );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	$response = rest_ensure_response( $result );
	$response->header( 'Content-Type', 'application/ld+json; charset=' . get_option( 'blog_charset' ) );
	$response->header( 'Cache-Control', 'no-store' );
	return $response;
}

/** Register a new read surface without changing any published Object URI. */
function axismundi_op_register_object_read_route() : void {
	register_rest_route( 'axismundi/v1', '/objects/read', array(
		'methods' => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback' => 'axismundi_op_get_object_read',
		'args' => array( 'uri' => array( 'type' => 'string', 'format' => 'uri' ), 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
	) );
}
add_action( 'rest_api_init', 'axismundi_op_register_object_read_route' );
