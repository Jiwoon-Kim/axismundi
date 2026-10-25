<?php
/** V2 inline declarations, including comparison against the pre-V2 registry. */
defined( 'ABSPATH' ) || exit( 1 );
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) {
		throw new RuntimeException( 'FAIL: ' . $label );
	}
	++$checks;
};
axismundi_op_load_vocabulary();
$base = 'https://www.w3.org/ns/activitystreams';
$schema = array( 'schema' => 'https://schema.org/', 'PropertyValue' => 'schema:PropertyValue', 'value' => 'schema:value' );
$size = array( 'peertube' => 'https://joinpeertube.org/ns#', 'size' => 'peertube:size' );
foreach ( array( 'PropertyValue', 'schema:PropertyValue', 'https://schema.org/PropertyValue' ) as $type ) {
	$object = array( 'type' => 'Person', 'attachment' => array( array( 'type' => $type, 'name' => 'Website', 'value' => 'https://example.test/' ) ) );
	$assert( array( $base, $schema ) === axismundi_op_jsonld_context( $object ), 'Profile field aliases produce one exact schema map' );
}
$assert( $base === axismundi_op_jsonld_context( array( 'value' => 'unrelated' ) ), 'Unrelated value does not declare schema terms' );
foreach ( array( 'url', 'attachment' ) as $property ) {
	$assert( array( $base, $size ) === axismundi_op_jsonld_context( array( $property => array( array( 'type' => 'Link', 'href' => 'https://example.test/file', 'size' => 0 ) ) ) ), 'Nested rendition Link declares integer byte size, including zero' );
}
$assert( $base === axismundi_op_jsonld_context( array( 'size' => 42 ) ), 'Unrelated size is not treated as a media rendition' );
$assert( $base === axismundi_op_jsonld_context( array( 'url' => array( array( 'type' => 'Link', 'href' => 'https://example.test/file' ) ) ) ), 'Link without size does not declare PeerTube' );
$terms = $GLOBALS['axismundi_op_vocabulary_terms'];
$namespaces = $GLOBALS['axismundi_op_namespaces'];
try {
	$assert( 'https://schema.org/' === axismundi_op_namespace( 'schema' )['base_iri'], 'Schema uses canonical namespace' );
	$assert( 'axismundi-actors' === axismundi_op_vocabulary_term( 'value' )['contributor'], 'Actors owns profile field terms' );
	$assert( 'https://joinpeertube.org/ns#size' === axismundi_op_vocabulary_term( 'size' )['iri'], 'Size is PeerTube bytes, not schema contentSize' );
	$assert( is_wp_error( axismundi_op_register_namespace( 'schema', array( 'base_iri' => 'http://schema.org#', 'reference' => 'https://schema.org/' ) ) ), 'Legacy schema namespace cannot replace canonical registration' );
	$definition = axismundi_op_vocabulary_term( 'size' );
	unset( $GLOBALS['axismundi_op_vocabulary_terms']['size'], $GLOBALS['axismundi_op_namespaces']['peertube'], $definition['contributors'], $definition['sequence'] );
	$missing = axismundi_op_register_vocabulary_term( 'size', $definition );
	$assert( is_wp_error( $missing ) && 'ax_op_term_context_iri' === $missing->get_error_code(), 'Missing PeerTube registration rejects prefixed size declaration' );
} finally {
	$GLOBALS['axismundi_op_vocabulary_terms'] = $terms;
	$GLOBALS['axismundi_op_namespaces'] = $namespaces;
}

// Source fixtures are read only. No Actor, post, or profile settings are changed.
$actor = axismundi_actors_get_for_user( 1 );
$post = get_page_by_path( 'vqa-demo-post-1', OBJECT, 'post' );
$events = get_posts( array( 'post_type' => 'ax_event', 'post_status' => 'publish', 'numberposts' => 20 ) );
$event = null;
foreach ( $events as $candidate ) {
	$projection = axismundi_op_transform_object( $candidate );
	if ( is_array( $projection ) ) {
		$event = $projection;
		break;
	}
}
$assert( $actor instanceof Axismundi_Actor && $post instanceof WP_Post && is_array( $event ), 'Actor, Article and Event comparison fixtures are available' );
$documents = array(
	'actor' => axismundi_op_actor_transform( $actor ),
	'post' => axismundi_op_transform_object( $post ),
	'attachment' => axismundi_op_transform_object( get_post( 166 ) ),
	'event' => $event,
	'followers' => axismundi_op_actor_followers_transform( new Axismundi_OP_Actor_Followers( $actor ) ),
	'outbox' => axismundi_op_actor_outbox_transform( new Axismundi_OP_Actor_Outbox( $actor ) ),
);
foreach ( $documents as $name => $document ) {
	$assert( is_array( $document ), 'Comparison fixture is a document: ' . $name );
	$after = axismundi_op_jsonld_context( $document );
	try {
		foreach ( array( 'PropertyValue', 'value', 'size' ) as $term ) {
			unset( $GLOBALS['axismundi_op_vocabulary_terms'][ $term ] );
		}
		unset( $GLOBALS['axismundi_op_namespaces']['peertube'] );
		$before = axismundi_op_jsonld_context( $document );
	} finally {
		$GLOBALS['axismundi_op_vocabulary_terms'] = $terms;
		$GLOBALS['axismundi_op_namespaces'] = $namespaces;
	}
	$changed = wp_json_encode( $before ) !== wp_json_encode( $after );
	$assert( $changed === in_array( $name, array( 'actor', 'attachment' ), true ), 'Only Actor and attachment contexts change: ' . $name );
	echo 'CONTEXT ' . $name . ': ' . ( $changed ? 'changed' : 'identical' ) . "\n";
}
echo 'PASS: ' . $checks . " inline declaration checks\n";
if ( 'actor-json' === ( $args[0] ?? '' ) ) {
	echo 'ACTOR_JSON:' . wp_json_encode( array_merge( array( '@context' => axismundi_op_jsonld_context( $documents['actor'] ) ), $documents['actor'] ) ) . "\n";
}
