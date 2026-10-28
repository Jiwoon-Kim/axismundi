<?php
/** W3C Recommendation type and property coverage; no source or database mutation. */
defined( 'ABSPATH' ) || exit( 1 );
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $ok ) { throw new RuntimeException( $label ); }
};
$core = array(
	'Object' => array( 'object', array() ), 'Link' => array( 'link', array() ),
	'Activity' => array( 'activity', array( 'Object' ) ), 'IntransitiveActivity' => array( 'activity', array( 'Activity' ) ),
	'Collection' => array( 'collection', array( 'Object' ) ), 'OrderedCollection' => array( 'collection', array( 'Collection' ) ),
	'CollectionPage' => array( 'collection', array( 'Collection' ) ), 'OrderedCollectionPage' => array( 'collection', array( 'OrderedCollection', 'CollectionPage' ) ),
);
$activities = explode( ' ', 'Accept Add Announce Arrive Block Create Delete Dislike Flag Follow Ignore Invite Join Leave Like Listen Move Offer Question Read Reject Remove TentativeReject TentativeAccept Travel Undo Update View' );
$actors = explode( ' ', 'Application Group Organization Person Service' );
$objects = explode( ' ', 'Article Audio Document Event Image Note Page Place Profile Relationship Tombstone Video' );
$parents = array( 'TentativeAccept' => 'Accept', 'TentativeReject' => 'Reject', 'Block' => 'Ignore', 'Invite' => 'Offer', 'Arrive' => 'IntransitiveActivity', 'Travel' => 'IntransitiveActivity', 'Question' => 'IntransitiveActivity', 'Audio' => 'Document', 'Image' => 'Document', 'Video' => 'Document', 'Page' => 'Document' );
$expected = $core;
foreach ( array( 'activity' => $activities, 'actor' => $actors, 'object' => $objects, 'link' => array( 'Mention' ) ) as $category => $terms ) {
	foreach ( $terms as $term ) { $expected[ $term ] = array( $category, array( $parents[ $term ] ?? ( 'activity' === $category ? 'Activity' : ( 'Mention' === $term ? 'Link' : 'Object' ) ) ) ); }
}
$snapshot = axismundi_op_vocabulary_snapshot();
$assert( is_array( $snapshot ), 'Standard vocabulary loads' );
$names = array_keys( $snapshot['types'] );
$wanted = array_keys( $expected );
sort( $names ); sort( $wanted );
$assert( 54 === count( $wanted ) && $wanted === $names, 'Exact 54 standard types, without vendor types or omissions' );
foreach ( $expected as $term => [ $category, $ancestors ] ) {
	$type = axismundi_op_vocabulary_type( $term );
	$tier = isset( $core[ $term ] ) ? 'core' : 'extended';
	$assert( $type && $tier === $type['tier'] && $category === $type['category'] && $ancestors === $type['parents'], $term . ' has the standard tier, category and direct parents' );
	$assert( 'https://www.w3.org/ns/activitystreams#' . $term === $type['iri'] && 'as2-core' === $type['provenance'], $term . ' retains its standard IRI and source' );
}
// Section 4 defines 62 properties; orderedItems is the additional Core @list alias.
$properties = explode( ' ', 'id type actor attachment attributedTo audience bcc bto cc context current first generator icon image inReplyTo instrument last location items oneOf anyOf closed origin next object prev preview result replies tag target to url accuracy altitude content name duration height href hreflang partOf latitude longitude mediaType endTime published startTime radius rel startIndex summary totalItems units updated width subject relationship describes formerType deleted' );
$actual = array_keys( $snapshot['properties'] );
$wanted_properties = array_merge( $properties, array( 'orderedItems' ) );
sort( $actual ); sort( $wanted_properties );
$assert( 62 === count( $properties ) && $actual === $wanted_properties, 'All Section 4 properties plus orderedItems, without duplicate language-map predicates' );
foreach ( $snapshot['types'] as $term => $type ) {
	foreach ( array_merge( $type['declares'], $type['excludes'] ) as $property ) { $assert( isset( $snapshot['properties'][ $property ] ), $term . ' property closure: ' . $property ); }
	foreach ( $type['parents'] as $parent ) { $assert( isset( $snapshot['types'][ $parent ] ), $term . ' parent exists: ' . $parent ); }
}
foreach ( array( 'Arrive', 'Travel', 'Question' ) as $term ) { $assert( ! in_array( 'object', axismundi_op_effective_properties( $term ), true ) && in_array( 'actor', axismundi_op_effective_properties( $term ), true ), $term . ' inherits Activity but excludes object' ); }
$assert( in_array( 'object', axismundi_op_effective_properties( 'Create' ), true ), 'Create inherits object rather than the IntransitiveActivity exclusion' );
$page = axismundi_op_effective_properties( 'OrderedCollectionPage' );
$assert( in_array( 'orderedItems', $page, true ) && in_array( 'partOf', $page, true ) && in_array( 'startIndex', $page, true ), 'OrderedCollectionPage preserves both inheritance branches' );
foreach ( array( 'image', 'object', 'relationship' ) as $property ) { $assert( '#dfn-' . $property . '-term' === axismundi_op_vocabulary_property( $property )['source']['anchor'], $property . ' property source differs from its similarly named type anchor' ); }
$assert( ! isset( $snapshot['types']['Hashtag'] ) && ! isset( $snapshot['types']['Emoji'] ) && ! isset( $snapshot['types']['EmojiReact'] ) && ! isset( $snapshot['properties']['sensitive'] ), 'Vendor and CG terms remain outside the AS2 baseline' );
printf( "PASS: %d standard vocabulary checks (54 types, 62 properties plus orderedItems)\n", $checks );
