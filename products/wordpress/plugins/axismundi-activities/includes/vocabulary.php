<?php
/** Activities-owned semantic types and conditional JSON-LD declarations. */
defined( 'ABSPATH' ) || exit;

function axismundi_act_register_vocabulary() : void {
	if ( null === axismundi_op_namespace( 'litepub' ) ) {
		$result = axismundi_op_register_namespace( 'litepub', array( 'base_iri' => 'http://litepub.social/ns#', 'reference' => 'https://w3id.org/fep/c0e0' ) );
		if ( is_wp_error( $result ) ) {
			throw new LogicException( $result->get_error_message() );
		}
	}
	$types = array(
		'EmojiReact' => array( 'http://litepub.social/ns#EmojiReact', 'Like', 'c0e0', 'litepub' ),
		'QuoteRequest' => array( 'https://w3id.org/fep/044f#QuoteRequest', 'Activity', '044f', 'fep044f' ),
	);
	foreach ( $types as $term => $settings ) {
		$source = 'https://w3id.org/fep/' . $settings[2];
		$result = axismundi_op_register_vocabulary_type( $term, array(
			'iri' => $settings[0], 'tier' => 'custom', 'category' => 'activity', 'namespace' => $settings[3],
			'parents' => array( $settings[1] ), 'declares' => array(), 'excludes' => array(),
			'provenance' => 'fep:' . $settings[2], 'source' => array( 'specification' => $source ),
		) );
		if ( is_wp_error( $result ) ) {
			throw new LogicException( $result->get_error_message() );
		}
		axismundi_op_register_vocabulary_group( array( $term => $settings[0] ), array(
			'kind' => 'type', 'provenance' => 'fep:' . $settings[2], 'source' => $source,
			'contributor' => 'axismundi-activities', 'context' => array( $term => $settings[0] ),
			'when' => array( array( 'kind' => 'scalarType', 'names' => array( $term ), 'path' => '~^(object)?$~' ) ),
		) );
	}
}
add_action( 'axismundi_op_register_vocabulary', 'axismundi_act_register_vocabulary' );
