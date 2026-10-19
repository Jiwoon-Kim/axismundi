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
	$document = axismundi_op_transform_object( $source );
	if ( is_wp_error( $document ) && in_array( $document->get_error_code(), array( 'ax_op_not_public', 'ax_op_no_transformer' ), true ) ) {
		return new WP_Error( 'ax_op_object_read_not_found', 'The Object is not publicly readable.', array( 'status' => 404 ) );
	}
	return is_array( $document ) ? array( 'document' => $document ) : $document;
}

/** Machine-readable storage backlog. Only named members may enter structured fallback. */
function axismundi_op_object_payload_fallbacks() : array {
	return array(
		'oneOf' => array( 'types' => array( 'Question' ), 'reason' => 'Question option rows are not structured in the remote cache.' ),
		'anyOf' => array( 'types' => array( 'Question' ), 'reason' => 'Question option rows are not structured in the remote cache.' ),
		'closed' => array( 'types' => array( 'Question' ), 'reason' => 'The remote cache has no Question closure column.' ),
		'votersCount' => array( 'types' => array( 'Question' ), 'reason' => 'The remote cache has no observed voter-count column.' ),
		'name' => array( 'types' => array(), 'reason' => 'The selected name column does not preserve authored scalar versus map presence.' ),
		'summary' => array( 'types' => array(), 'reason' => 'The selected summary column does not preserve authored scalar versus map presence.' ),
		'content' => array( 'types' => array(), 'reason' => 'The selected content column does not preserve authored scalar versus map presence.' ),
		'nameMap' => array( 'types' => array(), 'reason' => 'The remote cache structures a selected name, not its language map.' ),
		'summaryMap' => array( 'types' => array(), 'reason' => 'The remote cache structures a selected summary, not its language map.' ),
		'contentMap' => array( 'types' => array(), 'reason' => 'The remote cache structures one content language, not the complete language map.' ),
		'attachment' => array( 'types' => array(), 'reason' => 'Remote attachment descriptors have no structured source table.' ),
		'image' => array( 'types' => array(), 'reason' => 'Remote representative images have no structured source table.' ),
		'icon' => array( 'types' => array(), 'reason' => 'Remote icon descriptors have no structured source table.' ),
		'to' => array( 'types' => array(), 'reason' => 'Observed addressing remains in the preserved payload.' ),
		'cc' => array( 'types' => array(), 'reason' => 'Observed addressing remains in the preserved payload.' ),
		'audience' => array( 'types' => array(), 'reason' => 'Observed addressing remains in the preserved payload.' ),
	);
}

/** Normalize known fallback descriptor fields without interpreting unknown extensions. */
function axismundi_op_clean_object_read_value( $value, int $depth = 0 ) {
	if ( $depth > 16 ) {
		return null;
	}
	if ( ! is_array( $value ) ) {
		return $value;
	}
	$out = array();
	foreach ( $value as $key => $member ) {
		if ( in_array( $key, array( '@context', 'bto', 'bcc' ), true ) ) {
			continue;
		}
		if ( in_array( $key, array( 'content', 'summary' ), true ) && is_string( $member ) ) {
			$out[ $key ] = axismundi_op_clean_html( $member );
		} elseif ( 'name' === $key && is_string( $member ) ) {
			$out[ $key ] = sanitize_text_field( wp_strip_all_tags( $member ) );
		} elseif ( in_array( $key, array( 'id', 'href', 'url' ), true ) && is_string( $member ) ) {
			$out[ $key ] = esc_url_raw( $member, array( 'http', 'https' ) );
		} else {
			$out[ $key ] = axismundi_op_clean_object_read_value( $member, $depth + 1 );
		}
	}
	return $out;
}

/** Reassemble a remote observation from columns, relations, named fallbacks and opaque data. */
function axismundi_op_read_remote_jsonld_source( Axismundi_Op_Remote_Source $source, array $object, array $identity ) : array {
	$row = $source->get_row();
	$payload = (array) ( $row['payload'] ?? array() );
	$uri = (string) $row['object_uri'];
	$document = array( 'id' => $uri, 'type' => (string) $row['object_type'] );
	$columns = array( 'attributedTo' => 'attributed_to_uri', 'inReplyTo' => 'in_reply_to_uri', 'url' => 'human_url', 'mediaType' => 'media_type' );
	foreach ( $columns as $term => $column ) {
		if ( null !== ( $row[ $column ] ?? null ) && '' !== $row[ $column ] ) {
			$document[ $term ] = $row[ $column ];
		}
	}
	foreach ( array( 'published' => 'published_at', 'updated' => 'remote_updated_at' ) as $term => $column ) {
		if ( ! empty( $row[ $column ] ) ) {
			$document[ $term ] = gmdate( 'c', strtotime( $row[ $column ] . ' UTC' ) );
		}
	}
	if ( null !== ( $row['is_sensitive'] ?? null ) ) {
		$document['sensitive'] = (bool) $row['is_sensitive'];
	}
	$parents = axismundi_op_read_reply_parent_uris( $uri );
	if ( $parents ) {
		$first = $document['inReplyTo'] ?? null;
		$parents = array_values( array_unique( array_merge( null === $first ? array() : array( $first ), $parents ) ) );
		$document['inReplyTo'] = count( $parents ) === 1 ? $parents[0] : $parents;
	}
	$tags = array();
	foreach ( axismundi_op_mentions_for_object( $uri ) as $mention ) {
		$tag = array( 'type' => 'Mention', 'href' => $mention['uri'] );
		if ( '' !== $mention['handle'] ) {
			$tag['name'] = $mention['handle'];
		}
		$tags[] = $tag;
	}
	foreach ( axismundi_op_object_hashtag_chips( $uri ) as $hashtag ) {
		$tags[] = array( 'type' => 'Hashtag', 'name' => '#' . ltrim( $hashtag['name'], '#' ), 'href' => $hashtag['url'] );
	}
	if ( $tags ) {
		$document['tag'] = $tags;
	}
	$quote = axismundi_op_quote_target_for_source( $uri );
	if ( '' !== $quote ) {
		$document['quote'] = $quote;
	}
	$used = array();
	foreach ( axismundi_op_object_payload_fallbacks() as $term => $entry ) {
		if ( ! array_key_exists( $term, $document ) && array_key_exists( $term, $payload ) && ( ! $entry['types'] || in_array( $document['type'], $entry['types'], true ) ) ) {
			$document[ $term ] = axismundi_op_clean_object_read_value( $payload[ $term ] );
			$used[ $term ] = $entry['reason'];
		}
	}
	// A modeled member with no current value is not revived from an old payload.
	$handled = array_merge( array_keys( $columns ), array_keys( $document ), array_keys( $used ), array( '@context', 'id', 'type', 'published', 'updated', 'sensitive', 'contentMap', 'inReplyTo', 'quote', 'quoteUrl', 'quoteUri', '_misskey_quote', 'bto', 'bcc' ) );
	$extensions = array_diff_key( $payload, array_fill_keys( $handled, true ) );
	// Unknown tag kinds remain data, alongside the side-table-derived structured tags.
	$payload_tags = (array) ( $payload['tag'] ?? array() );
	$payload_tags = isset( $payload_tags['type'] ) ? array( $payload_tags ) : $payload_tags;
	$unknown_tags = array_filter( $payload_tags, static fn( $tag ) : bool => ! is_array( $tag ) || ! in_array( $tag['type'] ?? '', array( 'Mention', 'Hashtag' ), true ) );
	if ( $unknown_tags ) {
		$extensions['tag'] = array_values( $unknown_tags );
	}
	$observation = array( 'kind' => 'remote-observation', 'source_uri' => $uri );
	foreach ( array( 'fetched_at', 'last_success_at', 'remote_updated_at', 'payload_hash', 'etag', 'last_modified' ) as $key ) {
		$observation[ $key ] = $row[ $key ] ?? null;
	}
	if ( 'tombstone' === ( $row['object_status'] ?? '' ) ) {
		return array( 'document' => array( 'id' => $uri, 'type' => 'Tombstone' ), 'observation' => $observation );
	}
	return array( 'document' => $document, 'observation' => $observation, 'fallbacks' => $used, 'uninterpreted' => array( 'trusted' => false, 'context' => $payload['@context'] ?? null, 'members' => $extensions ) );
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
	$identity = is_int( $id ) ? axismundi_actors_get_identity( $id ) : ( is_string( $id ) ? axismundi_actors_get_identity_by_uri( $id ) : null );
	$object = is_array( $identity ) && 'object' === $identity['object_kind'] ? axismundi_op_get_object_by_identity( (int) $identity['identity_id'] ) : null;
	if ( ! is_array( $object ) ) {
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
		$assembly = axismundi_op_resolve_object_assembly( $object['as2_type'], 'jsonld' );
		if ( is_wp_error( $assembly ) ) {
			return $assembly;
		}
		$document = call_user_func( $assembly['jsonld'] ?? 'axismundi_op_assemble_read_jsonld', $document );
		if ( is_wp_error( $document ) ) {
			return $document;
		}
		if ( ! is_array( $document ) || ( $document['id'] ?? '' ) !== $identity['canonical_uri'] || ( $document['type'] ?? '' ) !== $object['as2_type'] ) {
			return new WP_Error( 'ax_op_object_read_mismatch', 'The assembly changed the registered identity or type.', array( 'status' => 409 ) );
		}
		unset( $document['@context'], $document['bto'], $document['bcc'], $document['axObservation'], $document['axUninterpreted'], $document['axPayloadFallback'] );
		$context = axismundi_op_jsonld_context( $document );
		$context = is_array( $context ) ? $context : array( $context );
		$context[] = array(
			'axObservation' => array( '@id' => 'https://github.com/Jiwoon-Kim/axismundi/ns#observation', '@type' => '@json' ),
			'axUninterpreted' => array( '@id' => 'https://github.com/Jiwoon-Kim/axismundi/ns#uninterpreted', '@type' => '@json' ),
			'axPayloadFallback' => array( '@id' => 'https://github.com/Jiwoon-Kim/axismundi/ns#payloadFallback', '@type' => '@json' ),
		);
		foreach ( array( 'observation' => 'axObservation', 'uninterpreted' => 'axUninterpreted', 'fallbacks' => 'axPayloadFallback' ) as $field => $term ) {
			if ( isset( $facts[ $field ] ) ) {
				$document[ $term ] = $facts[ $field ];
			}
		}
		return axismundi_op_order_object_members( array_merge( array( '@context' => $context ), $document ) );
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
