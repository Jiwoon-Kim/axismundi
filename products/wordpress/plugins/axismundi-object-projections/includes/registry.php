<?php
/**
 * Phase 1 — the transformer registry.
 *
 * Domain plugins (Core posts, Media Library, Notes, …) register how one of their
 * sources projects into an ActivityStreams object or collection. The registry is
 * **request-local and unpersisted**; a transformer is a pure projection — it must not
 * write to the database or the network, and it never decides content negotiation or
 * owns a route. See docs/TRANSFORMERS.md.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** @var array<string,array<string,mixed>> Object transformers, by id. */
$GLOBALS['axismundi_op_object_transformers'] = array();

/** @var array<string,array<string,mixed>> Collection transformers, by id. */
$GLOBALS['axismundi_op_collection_transformers'] = array();

/** @var int Stable request-local registration sequence. */
$GLOBALS['axismundi_op_sequence'] = 0;

/** @var bool Whether the registration action has fired this request. */
$GLOBALS['axismundi_op_loaded'] = false;

/**
 * Validate the shared transformer argument shape.
 *
 * @param string              $id        Stable slug.
 * @param array<string,mixed> $args      Definition.
 * @param string              $uri_key   Required URI-callback key (object_uri|collection_uri).
 * @return array<string,mixed>|WP_Error Normalized definition.
 */
function axismundi_op_normalize_transformer( string $id, array $args, string $uri_key ) {
	$key = sanitize_key( $id );
	if ( '' === $key || $key !== $id ) {
		return new WP_Error( 'ax_op_transformer_id', __( 'Transformer IDs must be non-empty lowercase slugs.', 'axismundi-object-projections' ) );
	}
	if ( ! is_callable( $args['supports'] ?? null ) || ! is_callable( $args[ $uri_key ] ?? null ) || ! is_callable( $args['transform'] ?? null ) ) {
		return new WP_Error( 'ax_op_transformer_args', __( 'A transformer requires callable supports, URI, and transform callbacks.', 'axismundi-object-projections' ) );
	}
	if ( isset( $args['visible'] ) && ! is_callable( $args['visible'] ) ) {
		return new WP_Error( 'ax_op_transformer_visible', __( 'The optional visibility callback must be callable.', 'axismundi-object-projections' ) );
	}
	return array(
		'id'        => $key,
		'supports'  => $args['supports'],
		'uri'       => $args[ $uri_key ],
		'transform' => $args['transform'],
		'visible'   => $args['visible'] ?? null,
		'priority'  => isset( $args['priority'] ) ? (int) $args['priority'] : 10,
	);
}

/**
 * Register one object transformer (a WordPress source → an ActivityStreams object).
 *
 * @param string              $id   Stable slug.
 * @param array<string,mixed> $args supports, object_uri, transform, [visible], [priority].
 * @return true|WP_Error
 */
function axismundi_op_register_object_transformer( string $id, array $args ) {
	$definition = axismundi_op_normalize_transformer( $id, $args, 'object_uri' );
	if ( is_wp_error( $definition ) ) {
		return $definition;
	}
	return axismundi_op_store_transformer( 'axismundi_op_object_transformers', $definition );
}

/**
 * Register one collection transformer (a WordPress source → an AS OrderedCollection).
 *
 * @param string              $id   Stable slug.
 * @param array<string,mixed> $args supports, collection_uri, transform, [visible], [priority].
 * @return true|WP_Error
 */
function axismundi_op_register_collection_transformer( string $id, array $args ) {
	$definition = axismundi_op_normalize_transformer( $id, $args, 'collection_uri' );
	if ( is_wp_error( $definition ) ) {
		return $definition;
	}
	return axismundi_op_store_transformer( 'axismundi_op_collection_transformers', $definition );
}

/**
 * Insert a normalized definition into a registry global, warning on a duplicate id.
 *
 * @param string              $global     Registry global name.
 * @param array<string,mixed> $definition Normalized definition.
 * @return true
 */
function axismundi_op_store_transformer( string $global, array $definition ) {
	$registry = &$GLOBALS[ $global ];
	if ( isset( $registry[ $definition['id'] ] ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				/* translators: %s: transformer ID. */
				esc_html__( 'Transformer "%s" is already registered; the later definition replaces it.', 'axismundi-object-projections' ),
				esc_html( $definition['id'] )
			),
			'0.0.1'
		);
	}
	++$GLOBALS['axismundi_op_sequence'];
	$definition['order']            = (int) $GLOBALS['axismundi_op_sequence'];
	$registry[ $definition['id'] ] = $definition;
	return true;
}

/**
 * Fire the one-shot registration action (memoized per request).
 *
 * @return void
 */
function axismundi_op_load_transformers() : void {
	if ( $GLOBALS['axismundi_op_loaded'] ) {
		return;
	}
	$GLOBALS['axismundi_op_loaded'] = true;
	/**
	 * Register object and collection transformers.
	 *
	 * @since 0.0.1
	 */
	do_action( 'axismundi_op_register_transformers' );
}

/**
 * Sort a registry by (priority ASC, registration order ASC).
 *
 * @param array<string,array<string,mixed>> $registry Registry.
 * @return array<int,array<string,mixed>>
 */
function axismundi_op_sort_registry( array $registry ) : array {
	$list = array_values( $registry );
	usort(
		$list,
		static function ( array $a, array $b ) : int {
			return array( $a['priority'], $a['order'] ) <=> array( $b['priority'], $b['order'] );
		}
	);
	return $list;
}

/**
 * @return array<int,array<string,mixed>> Object transformers in deterministic order.
 */
function axismundi_op_object_transformers() : array {
	axismundi_op_load_transformers();
	return axismundi_op_sort_registry( $GLOBALS['axismundi_op_object_transformers'] );
}

/**
 * @return array<int,array<string,mixed>> Collection transformers in deterministic order.
 */
function axismundi_op_collection_transformers() : array {
	axismundi_op_load_transformers();
	return axismundi_op_sort_registry( $GLOBALS['axismundi_op_collection_transformers'] );
}

/**
 * First transformer that supports a source, with callback errors isolated so one
 * misbehaving plugin cannot break resolution for the rest.
 *
 * @param array<int,array<string,mixed>> $transformers Ordered transformers.
 * @param mixed                          $source       Candidate source.
 * @return array<string,mixed>|null
 */
function axismundi_op_resolve( array $transformers, $source ) : ?array {
	foreach ( $transformers as $transformer ) {
		try {
			if ( true === call_user_func( $transformer['supports'], $source ) ) {
				return $transformer;
			}
		} catch ( \Throwable $error ) {
			continue;
		}
	}
	return null;
}

/**
 * @param mixed $source Source.
 * @return array<string,mixed>|null Resolved object transformer.
 */
function axismundi_op_resolve_object_transformer( $source ) : ?array {
	return axismundi_op_resolve( axismundi_op_object_transformers(), $source );
}

/**
 * @param mixed $source Source.
 * @return array<string,mixed>|null Resolved collection transformer.
 */
function axismundi_op_resolve_collection_transformer( $source ) : ?array {
	return axismundi_op_resolve( axismundi_op_collection_transformers(), $source );
}
