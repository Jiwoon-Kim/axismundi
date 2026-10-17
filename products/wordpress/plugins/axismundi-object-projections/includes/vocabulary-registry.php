<?php
/**
 * Read-only ActivityStreams vocabulary snapshot and product registration seams.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** @var array<string,mixed>|null */
$GLOBALS['axismundi_op_vocabulary_snapshot'] = null;
/** @var array<string,array<string,mixed>> */
$GLOBALS['axismundi_op_vocabulary_types'] = array();
/** @var array<string,array<string,mixed>> */
$GLOBALS['axismundi_op_vocabulary_properties'] = array();
/** @var array<string,array<string,mixed>> */
$GLOBALS['axismundi_op_property_bindings'] = array();
/** @var array<string,array<string,mixed>> */
$GLOBALS['axismundi_op_normative_requirements'] = array();

/** @return array<string,mixed>|WP_Error */
function axismundi_op_vocabulary_snapshot() {
	if ( is_array( $GLOBALS['axismundi_op_vocabulary_snapshot'] ) ) {
		return $GLOBALS['axismundi_op_vocabulary_snapshot'];
	}
	$path = dirname( __DIR__ ) . '/data/activitystreams-vocabulary.json';
	$data = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null;
	if ( ! is_array( $data ) || 1 !== (int) ( $data['schema_version'] ?? 0 ) || ! is_array( $data['types'] ?? null ) || ! is_array( $data['properties'] ?? null ) ) {
		return new WP_Error( 'ax_op_vocabulary_snapshot', __( 'The bundled ActivityStreams vocabulary snapshot is invalid.', 'axismundi-object-projections' ) );
	}
	$source = (string) ( $data['source'] ?? 'https://www.w3.org/TR/activitystreams-vocabulary/' );
	foreach ( $data['types'] as $term => $definition ) {
		if ( ! is_array( $definition ) ) {
			return new WP_Error( 'ax_op_vocabulary_snapshot', __( 'A vocabulary type definition is invalid.', 'axismundi-object-projections' ) );
		}
		$data['types'][ $term ] = array_merge(
			array( 'iri' => 'https://www.w3.org/ns/activitystreams#' . $term, 'status' => 'standard', 'provenance' => 'as2-core', 'source' => array( 'specification' => $source, 'anchor' => '#dfn-' . strtolower( $term ) ) ),
			$definition
		);
	}
	foreach ( $data['properties'] as $term => $definition ) {
		if ( ! is_string( $definition ) && ! is_array( $definition ) ) {
			return new WP_Error( 'ax_op_vocabulary_snapshot', __( 'A vocabulary property definition is invalid.', 'axismundi-object-projections' ) );
		}
		$shape = is_string( $definition ) ? $definition : (string) ( $definition['value_shape'] ?? '' );
		if ( '' === $shape ) {
			return new WP_Error( 'ax_op_vocabulary_snapshot', __( 'A vocabulary property definition is invalid.', 'axismundi-object-projections' ) );
		}
		$data['properties'][ $term ] = array_merge(
			array( 'iri' => 'https://www.w3.org/ns/activitystreams#' . $term, 'status' => 'standard', 'provenance' => 'as2-core', 'source' => array( 'specification' => $source, 'anchor' => '#dfn-' . strtolower( $term ) ), 'spec_domain' => array(), 'spec_range' => array(), 'rdf_functional' => null, 'runtime_cardinality' => 'unspecified', 'jsonld_container' => null, 'semantic_family' => 'unspecified', 'index_hint' => 'none', 'implementation_notes' => '' ),
			is_array( $definition ) ? $definition : array(),
			array( 'value_shape' => $shape )
		);
	}
	foreach ( $data['types'] as $definition ) {
		foreach ( array_merge( (array) $definition['declares'], (array) $definition['excludes'] ) as $property ) {
			if ( ! isset( $data['properties'][ $property ] ) ) {
				return new WP_Error( 'ax_op_vocabulary_snapshot', __( 'A type references a property missing from the vocabulary snapshot.', 'axismundi-object-projections' ) );
			}
		}
	}
	$GLOBALS['axismundi_op_vocabulary_snapshot'] = $data;
	return $data;
}

/** @return array<string,mixed>|null */
function axismundi_op_vocabulary_type( string $term ) : ?array {
	if ( isset( $GLOBALS['axismundi_op_vocabulary_types'][ $term ] ) ) {
		return $GLOBALS['axismundi_op_vocabulary_types'][ $term ];
	}
	$snapshot = axismundi_op_vocabulary_snapshot();
	return is_array( $snapshot ) && isset( $snapshot['types'][ $term ] ) && is_array( $snapshot['types'][ $term ] ) ? $snapshot['types'][ $term ] : null;
}

/** @return array<string,mixed>|null */
function axismundi_op_vocabulary_property( string $term ) : ?array {
	if ( isset( $GLOBALS['axismundi_op_vocabulary_properties'][ $term ] ) ) {
		return $GLOBALS['axismundi_op_vocabulary_properties'][ $term ];
	}
	$snapshot = axismundi_op_vocabulary_snapshot();
	return is_array( $snapshot ) && isset( $snapshot['properties'][ $term ] ) && is_array( $snapshot['properties'][ $term ] ) ? $snapshot['properties'][ $term ] : null;
}

/** Register a product-owned type without modifying an imported core definition. */
function axismundi_op_register_vocabulary_type( string $term, array $definition ) {
	if ( '' === $term || axismundi_op_vocabulary_type( $term ) || ! isset( $definition['category'], $definition['provenance'] ) || ! is_array( $definition['parents'] ?? null ) || ! is_array( $definition['declares'] ?? null ) || ! is_array( $definition['excludes'] ?? null ) ) {
		return new WP_Error( 'ax_op_vocabulary_type', __( 'A vocabulary type must be new and declare its category, provenance, parents, properties, and exclusions.', 'axismundi-object-projections' ) );
	}
	$GLOBALS['axismundi_op_vocabulary_types'][ $term ] = $definition;
	return true;
}

/** Register a product-owned property term without overwriting imported core semantics. */
function axismundi_op_register_vocabulary_property( string $term, array $definition ) {
	if ( '' === $term || empty( $definition['iri'] ) || empty( $definition['provenance'] ) || empty( $definition['value_shape'] ) ) {
		return new WP_Error( 'ax_op_vocabulary_property', __( 'A vocabulary property must be new and declare its IRI, provenance, and value shape.', 'axismundi-object-projections' ) );
	}
	$existing = axismundi_op_vocabulary_property( $term );
	if ( is_array( $existing ) && ( ( $existing['iri'] ?? '' ) !== $definition['iri'] || ( $existing['value_shape'] ?? '' ) !== $definition['value_shape'] || ( $existing['jsonld_container'] ?? null ) !== ( $definition['jsonld_container'] ?? null ) ) ) {
		return new WP_Error( 'ax_op_vocabulary_property', __( 'A vocabulary property term cannot be rebound to another IRI, value shape, or JSON-LD container.', 'axismundi-object-projections' ) );
	}
	$snapshot            = axismundi_op_vocabulary_snapshot();
	$imported_properties = is_array( $snapshot ) ? (array) $snapshot['properties'] : array();
	foreach ( array_merge( $imported_properties, $GLOBALS['axismundi_op_vocabulary_properties'] ) as $registered_term => $registered ) {
		if ( $registered_term !== $term && ( $registered['iri'] ?? '' ) === $definition['iri'] && ( $registered['value_shape'] ?? '' ) !== $definition['value_shape'] ) {
			return new WP_Error( 'ax_op_vocabulary_property_conflict', __( 'One vocabulary IRI cannot have conflicting value shapes.', 'axismundi-object-projections' ) );
		}
	}
	$GLOBALS['axismundi_op_vocabulary_properties'][ $term ] = $definition;
	return true;
}

/** @return array<int,string> */
function axismundi_op_effective_properties( string $term ) : array {
	$visited = array();
	$resolve = static function ( string $current ) use ( &$resolve, &$visited ) : array {
		if ( isset( $visited[ $current ] ) ) {
			return array();
		}
		$visited[ $current ] = true;
		$type = axismundi_op_vocabulary_type( $current );
		if ( null === $type ) {
			return array();
		}
		$properties = array();
		foreach ( (array) $type['parents'] as $parent ) {
			$properties = array_merge( $properties, $resolve( (string) $parent ) );
		}
		$properties = array_merge( $properties, (array) $type['declares'] );
		return array_values( array_diff( array_values( array_unique( $properties ) ), (array) $type['excludes'] ) );
	};
	return $resolve( $term );
}

/** Register a product-owned value reader; it never mutates the vocabulary snapshot. */
function axismundi_op_register_property_binding( string $id, array $binding ) {
	if ( '' === sanitize_key( $id ) || ! is_callable( $binding['read'] ?? null ) ) {
		return new WP_Error( 'ax_op_property_binding', __( 'A property binding needs a lowercase id and read callback.', 'axismundi-object-projections' ) );
	}
	$GLOBALS['axismundi_op_property_bindings'][ $id ] = $binding;
	return true;
}

/** @return array<string,mixed>|null */
function axismundi_op_property_binding( string $id ) : ?array {
	return $GLOBALS['axismundi_op_property_bindings'][ $id ] ?? null;
}

/** @return array<string,array<string,mixed>> */
function axismundi_op_property_bindings() : array {
	return $GLOBALS['axismundi_op_property_bindings'];
}

/** Register a product conformance record; enforcement remains named, not implied. */
function axismundi_op_register_normative_requirement( string $id, array $requirement ) {
	if ( '' === sanitize_key( $id ) || empty( $requirement['subject'] ) || empty( $requirement['level'] ) || empty( $requirement['conformance_class'] ) || empty( $requirement['source'] ) || empty( $requirement['kind'] ) || empty( $requirement['enforced_by'] ) ) {
		return new WP_Error( 'ax_op_normative_requirement', __( 'A normative requirement needs every registered contract field.', 'axismundi-object-projections' ) );
	}
	$GLOBALS['axismundi_op_normative_requirements'][ $id ] = $requirement;
	return true;
}

/** @return array<string,mixed>|null */
function axismundi_op_normative_requirement( string $id ) : ?array {
	return $GLOBALS['axismundi_op_normative_requirements'][ $id ] ?? null;
}

/** @return array<string,array<string,mixed>> */
function axismundi_op_normative_requirements() : array {
	return $GLOBALS['axismundi_op_normative_requirements'];
}
