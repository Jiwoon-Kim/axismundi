<?php
/** Conditional published-context declarations (dev-only; run with wp eval-file). */
defined( 'ABSPATH' ) || exit( 1 );

$checks = 0;
$assert = static function ( bool $condition, string $message ) use ( &$checks ) : void {
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	++$checks;
};
$base = 'https://www.w3.org/ns/activitystreams';
$security = 'https://w3id.org/security/v1';
$event = 'https://w3id.org/fep/8a8e';
$misc = 'https://purl.archive.org/miscellany/1';
$assert( function_exists( 'axismundi_actors_register_vocabulary' ), 'Actors provider loaded' );
$assert( function_exists( 'axismundi_cal_register_event_vocabulary' ), 'Calendar provider loaded' );

foreach ( array( null, array(), array( 'type' => 'Note', 'content' => 'Unchanged' ), array( 'type' => 'Collection', 'items' => array() ) ) as $object ) {
	$assert( $base === axismundi_op_jsonld_context( $object ), 'No extension properties retains exact scalar context' );
}
foreach ( array( array( 'publicKey' => null ), array( 'object' => array( 'publicKey' => array( 'owner' => 'https://example.test/actor', 'publicKeyPem' => 'fixture' ) ) ) ) as $object ) {
	$context = (array) axismundi_op_jsonld_context( $object );
	$assert( in_array( $security, $context, true ), 'Security declaration for top-level or embedded publicKey' );
	$assert( ! in_array( $event, $context, true ) && ! in_array( $misc, $context, true ), 'Security does not add unrelated contexts' );
}
foreach ( array( 'owner', 'publicKeyPem' ) as $property ) {
	$assert( $base === axismundi_op_jsonld_context( array( 'object' => array( $property => 'Unrelated value' ) ) ), 'Standalone ' . $property . ' does not claim the Security vocabulary' );
}
foreach ( array( 'eventStatus', 'joinMode', 'organizers', 'timezone' ) as $property ) {
	$context = (array) axismundi_op_jsonld_context( array( 'object' => array( $property => '' ) ) );
	$assert( in_array( $event, $context, true ), 'Embedded Event declaration for ' . $property );
	$assert( ! in_array( $security, $context, true ) && ! in_array( $misc, $context, true ), 'Event does not add unrelated contexts' );
}
foreach ( array( 'sensitive', 'manuallyApprovesFollowers' ) as $property ) {
	foreach ( array( array( $property => false ), array( 'attachment' => array( array( $property => false ) ) ) ) as $object ) {
		$before = serialize( $object );
		$context = (array) axismundi_op_jsonld_context( $object );
		$assert( in_array( $misc, $context, true ), 'False-valued or embedded miscellany property declares context' );
		$assert( ! in_array( array( 'sensitive' => 'as:sensitive' ), $context, true ), 'Old inline declaration removed' );
		$assert( $before === serialize( $object ), 'Context builder preserves document values' );
	}
}
$object = array( 'publicKey' => array( 'owner' => 'https://example.test/actor' ), 'sensitive' => false, 'object' => array( 'joinMode' => 'free' ) );
$context = (array) axismundi_op_jsonld_context( $object );
foreach ( array( $security, $event, $misc ) as $iri ) {
	$assert( 1 === count( array_keys( $context, $iri, true ) ), 'Combined document declares each context exactly once' );
}
$assert( $security === axismundi_op_vocabulary_term( 'publicKey' )['context'], 'Actors declaration metadata is registered' );
$assert( $event === axismundi_op_vocabulary_term( 'eventStatus' )['context'], 'Calendar declaration metadata is registered' );
$assert( $base === axismundi_op_jsonld_context( array( '@context' => array( 'owner' => 'fixture' ) ) ), 'Supplied context is not scanned as document properties' );
echo 'PASS: ' . $checks . " context declaration checks\n";
