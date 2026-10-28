<?php
/** Request-local context declarations extending the existing vocabulary registry. */
defined( 'ABSPATH' ) || exit;

$GLOBALS['axismundi_op_namespaces'] = array();
$GLOBALS['axismundi_op_vocabulary_terms'] = array();
$GLOBALS['axismundi_op_vocabulary_loaded'] = false;

/** Register an identifier separately from its human-readable reference. */
function axismundi_op_register_namespace( string $prefix, array $args ) {
	if ( ! preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/D', $prefix ) || ! filter_var( $args['base_iri'] ?? '', FILTER_VALIDATE_URL ) || ! filter_var( $args['reference'] ?? '', FILTER_VALIDATE_URL ) ) {
		return new WP_Error( 'ax_op_namespace_invalid', 'A namespace requires a prefix, absolute base IRI and reference.' );
	}
	$entry = array( 'base_iri' => $args['base_iri'], 'reference' => $args['reference'] );
	if ( isset( $GLOBALS['axismundi_op_namespaces'][ $prefix ] ) ) {
		return new WP_Error( 'ax_op_namespace_duplicate', 'Namespace registrations cannot replace an existing prefix.' );
	}
	$GLOBALS['axismundi_op_namespaces'][ $prefix ] = $entry;
	return true;
}

/** Read an existing namespace; consumers reference rather than re-register it. */
function axismundi_op_namespace( string $prefix ) : ?array {
	return $GLOBALS['axismundi_op_namespaces'][ $prefix ] ?? null;
}

/**
 * Register one explicit term IRI and its conditional declaration.
 *
 * `when` is a conjunction of clauses. Each clause matches any named key/type
 * at a path regex. Context groups preserve existing inline-map boundaries.
 */
function axismundi_op_register_vocabulary_term( string $term, array $args ) {
	foreach ( array( 'iri', 'kind', 'provenance', 'source', 'contributor' ) as $required ) {
		if ( empty( $args[ $required ] ) || ! is_string( $args[ $required ] ) ) {
			return new WP_Error( 'ax_op_term_invalid', 'A term requires explicit semantic and ownership metadata.' );
		}
	}
	if ( '' === $term || ! preg_match( '~^https?://~', $args['iri'] ) || ! in_array( $args['kind'], array( 'property', 'type' ), true ) ) {
		return new WP_Error( 'ax_op_term_invalid', 'Invalid term name, absolute IRI or kind.' );
	}
	$entry = array_merge( array( 'context' => null, 'when' => array(), 'group' => '', 'priority' => 10, 'jsonld_container' => null ), $args );
	$semantic = 'type' === $entry['kind'] ? axismundi_op_vocabulary_type( $term ) : axismundi_op_vocabulary_property( $term );
	$opposite = 'type' === $entry['kind'] ? axismundi_op_vocabulary_property( $term ) : axismundi_op_vocabulary_type( $term );
	if ( ! $semantic && $opposite ) {
		return new WP_Error( 'ax_op_term_kind', 'A registered property cannot be replaced by a type or vice versa.' );
	}
	if ( $semantic && ( $semantic['iri'] !== $entry['iri']
		|| ( 'as2-core' === ( $semantic['provenance'] ?? '' ) && ( 'as2-core' !== $entry['provenance'] || null !== $entry['context'] ) )
		|| ( isset( $entry['value_shape'] ) && $entry['value_shape'] !== ( $semantic['value_shape'] ?? null ) )
		|| $entry['jsonld_container'] !== ( $semantic['jsonld_container'] ?? null ) ) ) {
		return new WP_Error( 'ax_op_term_semantics', 'Context registrations cannot replace imported vocabulary semantics.' );
	}
	foreach ( array( 'runtime_cardinality', 'spec_domain', 'spec_range', 'rdf_functional', 'parents', 'category' ) as $field ) {
		if ( $semantic && array_key_exists( $field, $args ) && $args[ $field ] !== ( $semantic[ $field ] ?? null ) ) {
			return new WP_Error( 'ax_op_term_semantics', 'Context registrations cannot override existing semantic constraints.' );
		}
	}
	if ( str_starts_with( $entry['provenance'], 'product:' ) ) {
		$prefix = (string) ( $entry['namespace'] ?? '' );
		$namespace = axismundi_op_namespace( $prefix );
		if ( ! $namespace || in_array( $prefix, array( 'as', 'ldp', 'schema', 'toot', 'sec', 'misskey', 'gts', 'fep044f', 'fep8a8e', 'dcterms' ), true )
			|| ! str_starts_with( $entry['iri'], $namespace['base_iri'] ) ) {
			return new WP_Error( 'ax_op_term_namespace', 'Product terms must use their own registered namespace.' );
		}
	}
	foreach ( $entry['when'] as $clause ) {
		if ( ! in_array( $clause['kind'] ?? '', array( 'key', 'keyNotNull', 'type', 'scalarType', 'formerType' ), true ) || empty( $clause['names'] ) || ! is_array( $clause['names'] ) || false === @preg_match( $clause['path'] ?? '~.*~', '' ) ) {
			return new WP_Error( 'ax_op_term_condition', 'Invalid declaration condition.' );
		}
	}
	if ( null !== $entry['context'] && ! is_string( $entry['context'] ) && ! is_array( $entry['context'] ) ) {
		return new WP_Error( 'ax_op_term_context', 'Context must be an IRI or inline map.' );
	}
	if ( null !== $entry['context'] && empty( $entry['when'] ) ) {
		return new WP_Error( 'ax_op_term_condition', 'An emitted declaration requires an explicit use condition.' );
	}
	foreach ( is_array( $entry['context'] ) ? $entry['context'] : array() as $name => $mapping ) {
		$namespace = axismundi_op_namespace( (string) $name );
		if ( $namespace && $mapping !== $namespace['base_iri'] ) {
			return new WP_Error( 'ax_op_term_prefix_conflict', 'Inline context conflicts with a registered namespace.' );
		}
	}
	if ( is_array( $entry['context'] ) && isset( $entry['context'][ $term ] ) ) {
		$mapping = $entry['context'][ $term ];
		$mapped_iri = is_array( $mapping ) ? ( $mapping['@id'] ?? '' ) : $mapping;
		if ( is_string( $mapped_iri ) && false !== strpos( $mapped_iri, ':' ) ) {
			list( $prefix, $suffix ) = explode( ':', $mapped_iri, 2 );
			$namespace = axismundi_op_namespace( $prefix );
			if ( $namespace ) {
				$mapped_iri = $namespace['base_iri'] . $suffix;
			}
		}
		if ( $mapped_iri !== $entry['iri'] ) {
			return new WP_Error( 'ax_op_term_context_iri', 'The inline mapping must agree with the explicit term IRI.' );
		}
	}
	if ( isset( $GLOBALS['axismundi_op_vocabulary_terms'][ $term ] ) ) {
		$existing = $GLOBALS['axismundi_op_vocabulary_terms'][ $term ];
		$contributors = $existing['contributors'];
		unset( $existing['contributors'], $existing['contributor'], $existing['sequence'] );
		$compare = $entry;
		unset( $compare['contributor'] );
		if ( $existing !== $compare ) {
			return new WP_Error( 'ax_op_term_conflict', 'Conflicting vocabulary re-registration.' );
		}
		$GLOBALS['axismundi_op_vocabulary_terms'][ $term ]['contributors'] = array_values( array_unique( array_merge( $contributors, array( $entry['contributor'] ) ) ) );
		return true;
	}
	$entry['contributors'] = array( $entry['contributor'] );
	$entry['sequence'] = count( $GLOBALS['axismundi_op_vocabulary_terms'] );
	$GLOBALS['axismundi_op_vocabulary_terms'][ $term ] = $entry;
	return true;
}

/** Load feature-owned declarations once, without a dependency on plugin load order. */
function axismundi_op_load_vocabulary() : void {
	if ( $GLOBALS['axismundi_op_vocabulary_loaded'] ) {
		return;
	}
	$namespaces = $GLOBALS['axismundi_op_namespaces'];
	$terms = $GLOBALS['axismundi_op_vocabulary_terms'];
	$types = $GLOBALS['axismundi_op_vocabulary_types'];
	$properties = $GLOBALS['axismundi_op_vocabulary_properties'];
	$GLOBALS['axismundi_op_vocabulary_loaded'] = true;
	try {
		do_action( 'axismundi_op_register_vocabulary' );
	} catch ( Throwable $error ) {
		$GLOBALS['axismundi_op_namespaces'] = $namespaces;
		$GLOBALS['axismundi_op_vocabulary_terms'] = $terms;
		$GLOBALS['axismundi_op_vocabulary_types'] = $types;
		$GLOBALS['axismundi_op_vocabulary_properties'] = $properties;
		$GLOBALS['axismundi_op_vocabulary_loaded'] = false;
		throw $error;
	}
}

/** Expose metadata without deriving an IRI from the compact term name. */
function axismundi_op_vocabulary_term( string $term ) : ?array {
	axismundi_op_load_vocabulary();
	$declaration = $GLOBALS['axismundi_op_vocabulary_terms'][ $term ] ?? array();
	$semantic = axismundi_op_vocabulary_property( $term ) ?? axismundi_op_vocabulary_type( $term );
	return $declaration || $semantic ? array_merge( $semantic ?? array(), $declaration ) : null;
}

/** Normalize supported type spellings by explicit registered IRI, never by term name. */
function axismundi_op_vocabulary_type_aliases( string $type ) : array {
	$iri = $type;
	$colon = strpos( $type, ':' );
	if ( false !== $colon ) {
		$namespace = axismundi_op_namespace( substr( $type, 0, $colon ) );
		if ( $namespace ) {
			$iri = $namespace['base_iri'] . substr( $type, $colon + 1 );
		}
	}
	$aliases = array( $type );
	foreach ( $GLOBALS['axismundi_op_vocabulary_terms'] as $name => $term ) {
		if ( 'type' === $term['kind'] && $term['iri'] === $iri ) {
			$aliases[] = $name;
		}
	}
	return array_values( array_unique( $aliases ) );
}

/** Collect property and type occurrences in one walk, ignoring supplied contexts. */
function axismundi_op_collect_vocabulary( ?array $object ) : array {
	$index = array( 'key' => array(), 'keyNotNull' => array(), 'type' => array(), 'scalarType' => array(), 'formerType' => array() );
	$walk = static function ( array $node, string $path ) use ( &$walk, &$index ) : void {
		foreach ( $node as $key => $value ) {
			if ( '@context' === $key ) {
				continue;
			}
			$index['key'][ $path ][] = $key;
			if ( null !== $value ) {
				$index['keyNotNull'][ $path ][] = $key;
			}
			if ( in_array( $key, array( 'type', 'formerType' ), true ) ) {
				if ( is_string( $value ) ) {
					$type_path = 'formerType' === $key ? $path . '@formerType' : $path;
					$index['scalarType'][ $type_path ] = axismundi_op_vocabulary_type_aliases( $value );
				}
				foreach ( (array) $value as $type ) {
					if ( is_string( $type ) ) {
						$index[ $key ][ $path ] = array_merge( $index[ $key ][ $path ] ?? array(), axismundi_op_vocabulary_type_aliases( $type ) );
						if ( 'formerType' === $key ) {
							$index['type'][ $path . '@formerType' ][] = $type;
						}
					}
				}
			}
			if ( is_array( $value ) ) {
				$walk( $value, ltrim( $path . '/' . rawurlencode( (string) $key ), '/' ) );
			}
		}
	};
	$walk( $object ?? array(), '' );
	return $index;
}

/** Test a registered condition against the already collected occurrences. */
function axismundi_op_vocabulary_matches( array $when, array $index ) : bool {
	foreach ( $when as $clause ) {
		$matched = false;
		foreach ( $index[ $clause['kind'] ] as $path => $names ) {
			if ( preg_match( $clause['path'] ?? '~.*~', (string) $path ) && array_intersect( $clause['names'], $names ) ) {
				$matched = true;
				break;
			}
		}
		if ( ! $matched ) {
			return false;
		}
	}
	return true;
}

/** Serialize only used declarations, retaining stable inline groups and ordering. */
function axismundi_op_vocabulary_context( ?array $object ) : array {
	axismundi_op_load_vocabulary();
	$index = axismundi_op_collect_vocabulary( $object );
	$terms = $GLOBALS['axismundi_op_vocabulary_terms'];
	uasort( $terms, static fn( array $a, array $b ) : int => array( $a['priority'], $a['sequence'] ) <=> array( $b['priority'], $b['sequence'] ) );
	$context = array( 'https://www.w3.org/ns/activitystreams' );
	$groups = array();
	foreach ( $terms as $term ) {
		$declaration = $term['context'];
		if ( null === $declaration || ! axismundi_op_vocabulary_matches( $term['when'], $index ) ) {
			continue;
		}
		if ( '' !== $term['group'] && is_array( $declaration ) && isset( $groups[ $term['group'] ] ) ) {
			$position = $groups[ $term['group'] ];
			$context[ $position ] = array_merge( $context[ $position ], $declaration );
		} elseif ( ! in_array( $declaration, $context, true ) ) {
			if ( '' !== $term['group'] && is_array( $declaration ) ) {
				$groups[ $term['group'] ] = count( $context );
			}
			$context[] = $declaration;
		}
	}
	return $context;
}
