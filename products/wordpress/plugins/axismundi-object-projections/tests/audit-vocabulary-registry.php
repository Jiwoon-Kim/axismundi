<?php
defined( 'ABSPATH' ) || exit( 1 );
require_once dirname( __DIR__ ) . '/includes/vocabulary-registry.php';
$results = array();
function ax_vocab_assert( array &$results, string $label, bool $condition ) : void { $results[] = $condition; printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label ); }
$question = axismundi_op_effective_properties( 'Question' );
ax_vocab_assert( $results, 'Question inherits Activity members but excludes object', in_array( 'actor', $question, true ) && in_array( 'oneOf', $question, true ) && ! in_array( 'object', $question, true ) );
$snapshot = axismundi_op_vocabulary_snapshot();
/*
 * The loader is fail-closed and returns WP_Error for an invalid snapshot, which is the
 * state this audit exists to report. Reading it as an array then fatals, and a fatal in a
 * test hides every check after it: measured 2026-10-17, removing one declared property
 * definition killed this file after its first assertion, so the consistency check written
 * to name that exact fault never ran.
 */
ax_vocab_assert( $results, 'the bundled snapshot loads at all', is_array( $snapshot ) );
$snapshot_types = is_array( $snapshot ) ? (array) ( $snapshot['types'] ?? array() ) : array();
$referenced = array();
foreach ( $snapshot_types as $type ) { $referenced = array_merge( $referenced, (array) ( $type['declares'] ?? array() ), (array) ( $type['excludes'] ?? array() ) ); }
$missing = array();
foreach ( array_unique( $referenced ) as $term ) { if ( null === axismundi_op_vocabulary_property( $term ) ) { $missing[] = $term; } }
ax_vocab_assert( $results, 'every declared or excluded property has a vocabulary definition', is_array( $snapshot ) && array() === $missing );
ax_vocab_assert( $results, 'universal id and type are explicit vocabulary terms without pretending a type declares them', is_array( $snapshot ) && array( 'id', 'type' ) === ( $snapshot['universal_properties'] ?? null ) && null !== axismundi_op_vocabulary_property( 'id' ) && null !== axismundi_op_vocabulary_property( 'type' ) );
$attributed = axismundi_op_vocabulary_property( 'attributedTo' );
$name = axismundi_op_vocabulary_property( 'name' );
ax_vocab_assert( $results, 'property definitions carry machine-readable identity, source and local value contracts', is_array( $attributed ) && 'iri-or-object' === $attributed['value_shape'] && 'one-or-many' === $attributed['runtime_cardinality'] && is_array( $attributed['source'] ) && is_array( $name ) && 'plain-text' === $name['value_shape'] && '#dfn-name' === $name['source']['anchor'] );
$duration = axismundi_op_vocabulary_property( 'duration' );
ax_vocab_assert( $results, 'a shape-only snapshot entry does not pretend its runtime cardinality was researched', is_array( $duration ) && 'duration' === $duration['value_shape'] && 'unspecified' === $duration['runtime_cardinality'] );
$raw_snapshot = json_decode( file_get_contents( dirname( __DIR__ ) . '/data/activitystreams-vocabulary.json' ), true );
$shorthand = array_filter( $raw_snapshot['properties'], 'is_string' );
ax_vocab_assert( $results, 'the scoped metadata update leaves 56 of 63 properties explicitly shape-only', 63 === count( $raw_snapshot['properties'] ) && 56 === count( $shorthand ) && 7 === count( $raw_snapshot['properties'] ) - count( $shorthand ) );
$actor = axismundi_op_vocabulary_property( 'actor' );
$subject = axismundi_op_vocabulary_property( 'subject' );
$relationship = axismundi_op_vocabulary_property( 'relationship' );
ax_vocab_assert( $results, 'actor records Activity-local attribution semantics and permits multiple values', array( 'https://www.w3.org/ns/activitystreams#Activity' ) === $actor['spec_domain'] && array( 'https://www.w3.org/ns/activitystreams#attributedTo' ) === $actor['subproperty_of'] && 'one-or-many' === $actor['runtime_cardinality'] && 'https://www.w3.org/ns/activitystreams-owl' === $actor['source']['ontology'] );
ax_vocab_assert( $results, 'subject records OWL functionality and RDF reification without inventing runtime cardinality', true === $subject['rdf_functional'] && 'unspecified' === $subject['runtime_cardinality'] && array( 'https://www.w3.org/ns/activitystreams#Relationship' ) === $subject['spec_domain'] && array( 'http://www.w3.org/1999/02/22-rdf-syntax-ns#subject' ) === $subject['subproperty_of'] && 'rdf-reification' === $subject['semantic_family'] );
ax_vocab_assert( $results, 'relationship has an IRI-valued predicate contract rather than literal text', 'iri-or-object' === $relationship['value_shape'] && array( 'http://www.w3.org/1999/02/22-rdf-syntax-ns#Property' ) === $relationship['spec_range'] && array( 'http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate' ) === $relationship['subproperty_of'] && 'https://www.w3.org/ns/activitystreams' === $relationship['source']['context'] );
ax_vocab_assert( $results, 'unresearched semantic relationships stay empty and deprecated author is not reintroduced', array() === $duration['subproperty_of'] && null === axismundi_op_vocabulary_property( 'author' ) );
$actor_context = array( 'iri' => $actor['iri'], 'kind' => 'property', 'provenance' => 'as2-core', 'source' => 'https://www.w3.org/TR/activitystreams-vocabulary/#dfn-actor', 'contributor' => 'vocabulary-audit', 'subproperty_of' => $actor['subproperty_of'] );
ax_vocab_assert( $results, 'a context declaration can preserve the imported subproperty relation', true === axismundi_op_register_vocabulary_term( 'actor', $actor_context ) );
$actor_context['subproperty_of'] = $subject['subproperty_of'];
$changed_relation = axismundi_op_register_vocabulary_term( 'actor', $actor_context );
ax_vocab_assert( $results, 'a complete context declaration cannot replace actor attribution with RDF subject semantics', is_wp_error( $changed_relation ) && 'ax_op_term_semantics' === $changed_relation->get_error_code() );
$local_policy = axismundi_op_normative_requirement( 'local-note-attribution' );
ax_vocab_assert( $results, 'the existing Note guard is recorded as local policy with a named validator and audit, not an AS2 MUST', is_array( $local_policy ) && 'LOCAL_POLICY' === $local_policy['level'] && 'c2s-local-note' === $local_policy['conformance_class'] && 'ax_op_local_author' === $local_policy['enforced_by']['error_code'] && str_contains( $local_policy['enforced_by']['validator'], 'axismundi_op_prepare_local_note' ) && str_contains( $local_policy['enforced_by']['audit'], 'audit-c2s-create-note.php' ) );
$ordered_items = axismundi_op_vocabulary_property( 'orderedItems' );
$items = axismundi_op_vocabulary_property( 'items' );
ax_vocab_assert( $results, 'items and orderedItems share the items predicate and value shape, not an invented ordered predicate', is_array( $items ) && is_array( $ordered_items ) && 'https://www.w3.org/ns/activitystreams#items' === $items['iri'] && $items['iri'] === $ordered_items['iri'] && 'members' === $items['value_shape'] && $items['value_shape'] === $ordered_items['value_shape'] );
ax_vocab_assert( $results, 'orderedItems carries the Core list container while items remains unordered', is_array( $items ) && is_array( $ordered_items ) && null === $items['jsonld_container'] && '@list' === $ordered_items['jsonld_container'] && 'https://www.w3.org/TR/activitystreams-core/' === $ordered_items['source']['specification'] && '#collections' === $ordered_items['source']['anchor'] );
// The definition passed here is complete and valid, so the only reason the call can fail
// is that the term is already imported. Passing array() instead would fail the field
// validation and pass this check with the core-term guard removed -- measured.
$ax_vocab_core_type = array( 'category' => 'activity', 'provenance' => 'product:test', 'parents' => array( 'Activity' ), 'declares' => array( 'hijacked' ), 'excludes' => array() );
ax_vocab_assert( $results, 'an imported core type cannot be redefined by a product, even with a complete definition', is_wp_error( axismundi_op_register_vocabulary_type( 'Question', $ax_vocab_core_type ) ) && ! in_array( 'hijacked', axismundi_op_effective_properties( 'Question' ), true ) );
ax_vocab_assert( $results, 'a product type can inherit the core type contract', true === axismundi_op_register_vocabulary_type( 'EmojiReact', array( 'category' => 'activity', 'provenance' => 'fep:c0e0', 'parents' => array( 'Activity' ), 'declares' => array(), 'excludes' => array() ) ) && in_array( 'actor', axismundi_op_effective_properties( 'EmojiReact' ), true ) );
ax_vocab_assert( $results, 'a product property declares its own wire term and equivalent IRI registrations share one shape', true === axismundi_op_register_vocabulary_property( 'emojiReactions', array( 'iri' => 'http://fedibird.com/ns#emojiReactions', 'provenance' => 'product:object-projections', 'value_shape' => 'collection' ) ) && true === axismundi_op_register_vocabulary_property( 'emojiReactionsAlias', array( 'iri' => 'http://fedibird.com/ns#emojiReactions', 'provenance' => 'product:test', 'value_shape' => 'collection' ) ) && 'http://fedibird.com/ns#emojiReactions' === axismundi_op_vocabulary_property( 'emojiReactions' )['iri'] );
ax_vocab_assert( $results, 'a second registration cannot silently replace a term with another shape', is_wp_error( axismundi_op_register_vocabulary_property( 'emojiReactions', array( 'iri' => 'http://fedibird.com/ns#emojiReactions', 'provenance' => 'product:test', 'value_shape' => 'string' ) ) ) && 'collection' === axismundi_op_vocabulary_property( 'emojiReactions' )['value_shape'] );
ax_vocab_assert( $results, 'a second registration cannot silently replace a term with another JSON-LD container', is_wp_error( axismundi_op_register_vocabulary_property( 'emojiReactions', array( 'iri' => 'http://fedibird.com/ns#emojiReactions', 'provenance' => 'product:test', 'value_shape' => 'collection', 'jsonld_container' => '@list' ) ) ) && null === ( axismundi_op_vocabulary_property( 'emojiReactions' )['jsonld_container'] ?? null ) );
$ax_vocab_core_property = array( 'iri' => 'https://example.com/ns#name', 'provenance' => 'product:test', 'value_shape' => 'string' );
ax_vocab_assert( $results, 'an imported core property keeps its semantics against a complete redefinition', is_wp_error( axismundi_op_register_vocabulary_property( 'name', $ax_vocab_core_property ) ) && 'as2-core' === ( axismundi_op_vocabulary_property( 'name' )['provenance'] ?? '' ) );
$requirements_before = count( axismundi_op_normative_requirements() );
ax_vocab_assert( $results, 'value bindings and normative records require their separate contracts and can be read back', is_wp_error( axismundi_op_register_property_binding( 'bad binding', array() ) ) && true === axismundi_op_register_property_binding( 'emoji-tag', array( 'read' => '__return_null' ) ) && is_array( axismundi_op_property_binding( 'emoji-tag' ) ) && 1 === count( axismundi_op_property_bindings() ) && is_wp_error( axismundi_op_register_normative_requirement( 'bad rule', array() ) ) && true === axismundi_op_register_normative_requirement( 'actor-inbox', array( 'subject' => 'Actor', 'level' => 'MUST', 'conformance_class' => 'Server', 'source' => 'https://www.w3.org/TR/activitypub/#actor-objects', 'kind' => 'payload', 'enforced_by' => 'audit-actor-projection.php' ) ) && 'Actor' === axismundi_op_normative_requirement( 'actor-inbox' )['subject'] && $requirements_before + 1 === count( axismundi_op_normative_requirements() ) );
$failures = count( array_filter( $results, static fn( bool $value ) : bool => ! $value ) );
printf( "\n== %d checks, %d failed ==\n", count( $results ), $failures );
if ( class_exists( 'WP_CLI' ) ) { WP_CLI::halt( $failures ? 1 : 0 ); }
exit( $failures ? 1 : 0 );
