<?php
/** OP-owned term registrations and legacy declaration groups. */
defined( 'ABSPATH' ) || exit;

/** Register a feature's terms with one shared condition and context group. */
function axismundi_op_register_vocabulary_group( array $iris, array $args ) : void {
	foreach ( $iris as $term => $iri ) {
		$result = axismundi_op_register_vocabulary_term( $term, array_merge( $args, array( 'iri' => $iri ) ) );
		if ( is_wp_error( $result ) ) {
			throw new LogicException( $result->get_error_message() );
		}
	}
}

/** Shared namespace definitions do not imply declarations in emitted documents. */
function axismundi_op_register_vocabulary_namespaces() : void {
	$namespaces = array(
		'as' => array( 'https://www.w3.org/ns/activitystreams#', 'https://www.w3.org/TR/activitystreams-vocabulary/' ),
		'ldp' => array( 'http://www.w3.org/ns/ldp#', 'https://www.w3.org/ns/ldp' ),
		'schema' => array( 'https://schema.org/', 'https://schema.org/' ),
		'toot' => array( 'http://joinmastodon.org/ns#', 'https://docs.joinmastodon.org/spec/activitypub/' ),
		'sec' => array( 'https://w3id.org/security#', 'https://w3id.org/security/v1' ),
		'misskey' => array( 'https://misskey-hub.net/ns#', 'https://misskey-hub.net/ns' ),
		'gts' => array( 'https://gotosocial.org/ns#', 'https://docs.gotosocial.org/en/latest/federation/' ),
		'dcterms' => array( 'http://purl.org/dc/terms/', 'https://www.dublincore.org/specifications/dublin-core/dcmi-terms/' ),
		'fep044f' => array( 'https://w3id.org/fep/044f#', 'https://w3id.org/fep/044f' ),
		'fep8a8e' => array( 'https://w3id.org/fep/8a8e/', 'https://w3id.org/fep/8a8e' ),
	);
	foreach ( $namespaces as $prefix => $values ) {
		$result = axismundi_op_register_namespace( $prefix, array( 'base_iri' => $values[0], 'reference' => $values[1] ) );
		if ( is_wp_error( $result ) ) {
			throw new LogicException( $result->get_error_message() );
		}
	}
}
add_action( 'axismundi_op_register_vocabulary', 'axismundi_op_register_vocabulary_namespaces', -100 );

/** OP's existing declarations, retaining the previous output order and scope. */
function axismundi_op_register_core_vocabulary() : void {
	$as = 'https://www.w3.org/ns/activitystreams#';
	$common = array( 'kind' => 'property', 'provenance' => 'as2-core', 'source' => 'https://www.w3.org/TR/activitystreams-vocabulary/', 'contributor' => 'axismundi-object-projections', 'priority' => 0 );
	foreach ( array( 'attachment', 'image', 'icon' ) as $term ) {
		axismundi_op_register_vocabulary_group( array( $term => $as . $term ), $common );
	}
	axismundi_op_register_vocabulary_group( array( 'inbox' => 'http://www.w3.org/ns/ldp#inbox' ), array_merge( $common, array( 'provenance' => 'ldp', 'source' => 'https://www.w3.org/TR/activitypub/#inbox-property' ) ) );
	axismundi_op_register_vocabulary_group( array( 'orderedItems' => $as . 'items' ), array_merge( $common, array( 'jsonld_container' => '@list' ) ) );
	$fep = 'https://w3id.org/fep/044f#';
	$extension = array_merge( $common, array( 'provenance' => 'fep:044f', 'source' => 'https://w3id.org/fep/044f' ) );
	axismundi_op_register_vocabulary_group( array( 'QuoteRequest' => $fep . 'QuoteRequest' ), array_merge( $extension, array(
		'kind' => 'type', 'context' => array( 'QuoteRequest' => $fep . 'QuoteRequest' ),
		'when' => array( array( 'kind' => 'scalarType', 'names' => array( 'QuoteRequest' ), 'path' => '~^(object)?$~' ) ),
	) ) );
	$qa = array( 'QuoteAuthorization' => $fep . 'QuoteAuthorization', 'gts' => 'https://gotosocial.org/ns#', 'interactingObject' => array( '@id' => 'gts:interactingObject', '@type' => '@id' ), 'interactionTarget' => array( '@id' => 'gts:interactionTarget', '@type' => '@id' ) );
	// Former types are collected separately; both spellings share one declaration.
	foreach ( array( 'QuoteAuthorization', 'interactingObject', 'interactionTarget' ) as $term ) {
		$iri = 'QuoteAuthorization' === $term ? $fep . $term : 'https://gotosocial.org/ns#' . $term;
		axismundi_op_register_vocabulary_group( array( $term => $iri ), array_merge( $extension, array(
			'kind' => 'QuoteAuthorization' === $term ? 'type' : 'property', 'context' => $qa,
			'when' => array( array( 'kind' => 'scalarType', 'names' => array( 'QuoteAuthorization' ), 'path' => '~^(@formerType)?$~' ) ),
		) ) );
	}
	foreach ( array( 'quote', 'quoteAuthorization' ) as $term ) {
		axismundi_op_register_vocabulary_group( array( $term => $fep . $term ), array_merge( $extension, array(
			'context' => array( $term => array( '@id' => $fep . $term, '@type' => '@id' ) ), 'group' => 'quote-references',
			'when' => array( array( 'kind' => 'key', 'names' => array( $term ), 'path' => '~^$~' ) ),
		) ) );
	}
	// Intentional deviation from the report's unversioned SHOULD: retain the major tier.
	axismundi_op_register_vocabulary_group( array( 'sensitive' => $as . 'sensitive', 'manuallyApprovesFollowers' => $as . 'manuallyApprovesFollowers' ), array_merge( $common, array(
		'provenance' => 'cg-final:apmisc', 'source' => 'https://www.w3.org/community/reports/socialcg/CG-FINAL-apmisc-20260828/',
		'context' => 'https://purl.archive.org/miscellany/1', 'when' => array( array( 'kind' => 'key', 'names' => array( 'sensitive', 'manuallyApprovesFollowers' ) ) ),
	) ) );
	axismundi_op_register_vocabulary_group( array( 'dcterms:subject' => 'http://purl.org/dc/terms/subject' ), array_merge( $common, array(
		'provenance' => 'vendor:dcterms', 'source' => 'https://www.dublincore.org/specifications/dublin-core/dcmi-terms/#subject', 'context' => array( 'dcterms' => 'http://purl.org/dc/terms/' ),
		'when' => array( array( 'kind' => 'key', 'names' => array( 'dcterms:subject' ), 'path' => '~^$~' ) ),
	) ) );
	$policy = array( 'gts' => 'https://gotosocial.org/ns#' );
	foreach ( array( 'interactionPolicy', 'canQuote', 'automaticApproval' ) as $term ) {
		$policy[ $term ] = array( '@id' => 'gts:' . $term, '@type' => '@id' );
	}
	axismundi_op_register_vocabulary_group( array( 'interactionPolicy' => 'https://gotosocial.org/ns#interactionPolicy', 'canQuote' => 'https://gotosocial.org/ns#canQuote', 'automaticApproval' => 'https://gotosocial.org/ns#automaticApproval' ), array_merge( $common, array(
		'provenance' => 'vendor:gts', 'source' => 'https://docs.gotosocial.org/en/latest/federation/', 'context' => $policy,
		'when' => array( array( 'kind' => 'key', 'names' => array( 'interactionPolicy' ), 'path' => '~^$~' ) ),
	) ) );
}
add_action( 'axismundi_op_register_vocabulary', 'axismundi_op_register_core_vocabulary', -90 );
