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
// The definition passed here is complete and valid, so the only reason the call can fail
// is that the term is already imported. Passing array() instead would fail the field
// validation and pass this check with the core-term guard removed -- measured.
$ax_vocab_core_type = array( 'category' => 'activity', 'provenance' => 'product:test', 'parents' => array( 'Activity' ), 'declares' => array( 'hijacked' ), 'excludes' => array() );
ax_vocab_assert( $results, 'an imported core type cannot be redefined by a product, even with a complete definition', is_wp_error( axismundi_op_register_vocabulary_type( 'Question', $ax_vocab_core_type ) ) && ! in_array( 'hijacked', axismundi_op_effective_properties( 'Question' ), true ) );
ax_vocab_assert( $results, 'a product type can inherit the core type contract', true === axismundi_op_register_vocabulary_type( 'EmojiReact', array( 'category' => 'activity', 'provenance' => 'fep:c0e0', 'parents' => array( 'Activity' ), 'declares' => array(), 'excludes' => array() ) ) && in_array( 'actor', axismundi_op_effective_properties( 'EmojiReact' ), true ) );
ax_vocab_assert( $results, 'a product property declares its own wire term and equivalent IRI registrations share one shape', true === axismundi_op_register_vocabulary_property( 'emojiReactions', array( 'iri' => 'http://fedibird.com/ns#emojiReactions', 'provenance' => 'product:object-projections', 'value_shape' => 'collection' ) ) && true === axismundi_op_register_vocabulary_property( 'emojiReactionsAlias', array( 'iri' => 'http://fedibird.com/ns#emojiReactions', 'provenance' => 'product:test', 'value_shape' => 'collection' ) ) && 'http://fedibird.com/ns#emojiReactions' === axismundi_op_vocabulary_property( 'emojiReactions' )['iri'] );
ax_vocab_assert( $results, 'a second registration cannot silently replace a term with another shape', is_wp_error( axismundi_op_register_vocabulary_property( 'emojiReactions', array( 'iri' => 'http://fedibird.com/ns#emojiReactions', 'provenance' => 'product:test', 'value_shape' => 'string' ) ) ) && 'collection' === axismundi_op_vocabulary_property( 'emojiReactions' )['value_shape'] );
$ax_vocab_core_property = array( 'iri' => 'https://example.com/ns#name', 'provenance' => 'product:test', 'value_shape' => 'string' );
ax_vocab_assert( $results, 'an imported core property keeps its semantics against a complete redefinition', is_wp_error( axismundi_op_register_vocabulary_property( 'name', $ax_vocab_core_property ) ) && 'as2-core' === ( axismundi_op_vocabulary_property( 'name' )['provenance'] ?? '' ) );
ax_vocab_assert( $results, 'value bindings and normative records require their separate contracts and can be read back', is_wp_error( axismundi_op_register_property_binding( 'bad binding', array() ) ) && true === axismundi_op_register_property_binding( 'emoji-tag', array( 'read' => '__return_null' ) ) && is_array( axismundi_op_property_binding( 'emoji-tag' ) ) && 1 === count( axismundi_op_property_bindings() ) && is_wp_error( axismundi_op_register_normative_requirement( 'bad rule', array() ) ) && true === axismundi_op_register_normative_requirement( 'actor-inbox', array( 'subject' => 'Actor', 'level' => 'MUST', 'conformance_class' => 'Server', 'source' => 'https://www.w3.org/TR/activitypub/#actor-objects', 'kind' => 'payload', 'enforced_by' => 'audit-actor-projection.php' ) ) && 'Actor' === axismundi_op_normative_requirement( 'actor-inbox' )['subject'] && 1 === count( axismundi_op_normative_requirements() ) );
$failures = count( array_filter( $results, static fn( bool $value ) : bool => ! $value ) );
printf( "\n== %d checks, %d failed ==\n", count( $results ), $failures );
if ( class_exists( 'WP_CLI' ) ) { WP_CLI::halt( $failures ? 1 : 0 ); }
exit( $failures ? 1 : 0 );
