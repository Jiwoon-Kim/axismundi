<?php
/** Namespace, term and conditional serialization regression audit. */
defined( 'ABSPATH' ) || exit( 1 );
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) {
		throw new RuntimeException( 'FAIL: ' . $label );
	}
	++$checks;
};
axismundi_op_load_vocabulary();
$namespaces = $GLOBALS['axismundi_op_namespaces'];
$terms = $GLOBALS['axismundi_op_vocabulary_terms'];
try {
	$assert( 'http://joinmastodon.org/ns#' === axismundi_op_namespace( 'toot' )['base_iri'], 'Dead namespace identifier is not replaced by reference' );
	$assert( axismundi_op_namespace( 'toot' )['base_iri'] !== axismundi_op_namespace( 'toot' )['reference'], 'Identifier and reference stay separate' );
	$assert( is_wp_error( axismundi_op_register_namespace( 'schema', array( 'base_iri' => 'http://schema.org#', 'reference' => 'https://schema.org/' ) ) ), 'Conflicting prefix is rejected' );
	$assert( 'https://schema.org/' === axismundi_op_namespace( 'schema' )['base_iri'], 'Failed prefix registration leaves existing definition intact' );
	$assert( 'http://www.w3.org/ns/ldp#inbox' === axismundi_op_vocabulary_term( 'inbox' )['iri'], 'Inbox is the LDP alias, not as plus term name' );
	$assert( 'https://www.w3.org/ns/activitystreams#items' === axismundi_op_vocabulary_term( 'orderedItems' )['iri'], 'orderedItems is the items alias' );
	$assert( '@list' === axismundi_op_vocabulary_term( 'orderedItems' )['jsonld_container'], 'Ordered alias retains its list container' );
	$definition = array( 'iri' => 'https://example.test/ns#example', 'kind' => 'property', 'provenance' => 'vendor:example', 'source' => 'https://example.test/docs', 'contributor' => 'fixture-a' );
	$assert( true === axismundi_op_register_vocabulary_term( 'fixtureExample', $definition ), 'New term registration succeeds' );
	$assert( true === axismundi_op_register_vocabulary_term( 'fixtureExample', array_merge( $definition, array( 'contributor' => 'fixture-b' ) ) ), 'Identical term allows another contributor' );
	$assert( array( 'fixture-a', 'fixture-b' ) === axismundi_op_vocabulary_term( 'fixtureExample' )['contributors'], 'Contributors are recorded without changing meaning' );
	$assert( is_wp_error( axismundi_op_register_vocabulary_term( 'fixtureExample', array_merge( $definition, array( 'iri' => 'https://example.test/ns#other' ) ) ) ), 'Conflicting term IRI rejected' );
	$assert( is_wp_error( axismundi_op_register_vocabulary_term( 'name', $definition ) ), 'Imported core property cannot be rebound' );
	$assert( is_wp_error( axismundi_op_register_vocabulary_term( 'name', array_merge( $definition, array( 'kind' => 'type' ) ) ) ), 'Changing kind cannot bypass imported core protection' );
	$assert( is_wp_error( axismundi_op_register_vocabulary_term( 'fixtureMint', array_merge( $definition, array( 'namespace' => 'as', 'provenance' => 'product:fixture', 'iri' => 'https://www.w3.org/ns/activitystreams#fixtureMint' ) ) ) ), 'Product term cannot mint in someone else namespace' );
	$inline = axismundi_op_register_vocabulary_term( 'fixtureInline', array_merge( $definition, array( 'context' => array( 'schema' => 'http://schema.org#' ), 'when' => array( array( 'kind' => 'key', 'names' => array( 'fixtureInline' ) ) ) ) ) );
	$assert( is_wp_error( $inline ) && 'ax_op_term_prefix_conflict' === $inline->get_error_code(), 'Inline context cannot override registered prefix' );
	$assert( is_wp_error( axismundi_op_register_vocabulary_term( 'fixtureNoCondition', array_merge( $definition, array( 'context' => 'https://example.test/context' ) ) ) ), 'Unconditional declaration registration rejected' );
	$base = 'https://www.w3.org/ns/activitystreams';
	$qr = array( 'QuoteRequest' => 'https://w3id.org/fep/044f#QuoteRequest' );
	$assert( serialize( array( $base, $qr ) ) === serialize( axismundi_op_jsonld_context( array( 'type' => 'QuoteRequest' ) ) ), 'QuoteRequest bytes unchanged' );
	$assert( array( $base, $qr ) === axismundi_op_jsonld_context( array( 'object' => array( 'type' => 'QuoteRequest' ) ) ), 'Directly embedded QuoteRequest declared' );
	$assert( $base === axismundi_op_jsonld_context( array( 'preview' => array( 'type' => 'QuoteRequest' ) ) ), 'Legacy QuoteRequest scope is not broadened' );
	$assert( $base === axismundi_op_jsonld_context( array( 'type' => array( 'QuoteRequest' ) ) ), 'Legacy scalar-only QuoteRequest condition preserved' );
	$qa = array( 'QuoteAuthorization' => 'https://w3id.org/fep/044f#QuoteAuthorization', 'gts' => 'https://gotosocial.org/ns#', 'interactingObject' => array( '@id' => 'gts:interactingObject', '@type' => '@id' ), 'interactionTarget' => array( '@id' => 'gts:interactionTarget', '@type' => '@id' ) );
	$assert( array( $base, $qa ) === axismundi_op_jsonld_context( array( 'formerType' => 'QuoteAuthorization' ) ), 'Former QuoteAuthorization context unchanged' );
	$quote = array( 'quote' => array( '@id' => 'https://w3id.org/fep/044f#quote', '@type' => '@id' ), 'quoteAuthorization' => array( '@id' => 'https://w3id.org/fep/044f#quoteAuthorization', '@type' => '@id' ) );
	$assert( array( $base, $quote ) === axismundi_op_jsonld_context( array( 'quote' => 'https://example.test/q', 'quoteAuthorization' => 'https://example.test/a' ) ), 'Quote maps retain their single grouped entry and order' );
	$assert( $base === axismundi_op_jsonld_context( array( 'unknown' => array( 'type' => 'Unknown' ), '@context' => array( 'publicKey' => 'ignored' ) ) ), 'Unknown values and supplied context do not create declarations' );
	$assert( null === axismundi_op_vocabulary_term( 'attachment' )['context'], 'AS2 core term needs no extension context' );
	$assert( 'https://purl.archive.org/miscellany/1' === axismundi_op_vocabulary_term( 'sensitive' )['context'], 'Same AS namespace has different term-level declaration dependencies' );
	if ( function_exists( 'axismundi_emoji_register_vocabulary' ) ) {
		foreach ( array( 'Emoji', 'toot:Emoji', 'http://joinmastodon.org/ns#Emoji' ) as $type ) {
			foreach ( array( array( 'tag' => array( array( 'type' => $type ) ) ), array( 'object' => array( 'object' => array( 'tag' => array( array( 'type' => array( $type ) ) ) ) ) ) ) as $object ) {
				$assert( array( $base, array( 'Emoji' => 'http://joinmastodon.org/ns#Emoji' ) ) === axismundi_op_jsonld_context( $object ), 'Emoji aliases and object-chain scope retain exact context bytes' );
			}
		}
		$assert( $base === axismundi_op_jsonld_context( array( 'preview' => array( 'tag' => array( array( 'type' => 'Emoji' ) ) ) ) ), 'Emoji preview scope is not broadened' );
		$assert( $base === axismundi_op_jsonld_context( array( 'type' => 'Emoji' ) ), 'Standalone Emoji retains legacy context behavior' );
	}
	if ( axismundi_op_media_library_available() ) {
		$media = array( $base, array( 'schema' => 'https://schema.org/', 'license' => array( '@id' => 'schema:license', '@type' => '@id' ) ) );
		foreach ( array( 'Image', 'Video', 'Audio', 'Document' ) as $type ) {
			foreach ( array( array( 'type' => $type ), array( 'type' => $type, 'usedIn' => array() ) ) as $object ) {
				$expected = $media;
				if ( isset( $object['usedIn'] ) ) {
					$expected[] = array( 'usedIn' => array( '@id' => 'https://github.com/Jiwoon-Kim/axismundi/ns#usedIn', '@type' => '@id' ) );
				}
				$assert( $expected === axismundi_op_jsonld_context( $object ), 'Media context bytes match legacy declaration including usedIn gate' );
			}
		}
		$assert( $media === axismundi_op_jsonld_context( array( 'type' => 'Image', 'usedIn' => null ) ), 'Null usedIn retains legacy isset behavior' );
		$assert( $base === axismundi_op_jsonld_context( array( 'type' => array( 'Image' ) ) ), 'Media root type remains scalar-only' );
	}
} finally {
	$GLOBALS['axismundi_op_namespaces'] = $namespaces;
	$GLOBALS['axismundi_op_vocabulary_terms'] = $terms;
}
echo 'PASS: ' . $checks . " vocabulary context checks\n";
