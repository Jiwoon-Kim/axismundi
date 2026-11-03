<?php
/** Pure response-policy fixtures; no source writes or network requests. */
defined( 'ABSPATH' ) || exit( 1 );
$project = $article_response ?? 'axismundi_op_article_response';
$checks = 0;
$assert = static function ( $ok, $label ) use ( &$checks ) {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks;
};
$document = array(
	'@context' => 'https://www.w3.org/ns/activitystreams',
	'id' => 'https://article-list.example/article', 'type' => 'Article',
	'name' => 'Title', 'summary' => 'Summary', 'content' => '<p>Body</p>',
	'contentMap' => array( 'ko' => '<p>Body in Korean</p>' ),
	'image' => array( 'type' => 'Image', 'url' => 'https://article-list.example/cover', 'width' => 640, 'height' => 480 ),
	'attachment' => array( array( 'type' => 'Image', 'url' => 'https://article-list.example/media', 'mediaType' => 'image/png', 'width' => 100, 'height' => 100 ) ),
	'preview' => array( 'type' => 'Note', 'content' => 'Preview', 'attachment' => array( 'type' => 'Image', 'url' => 'https://article-list.example/cover' ) ),
);
$original = $document;
global $wpdb;
$queries = $wpdb->num_queries;
$list = $project( $document, 'list' );
$assert( ! array_key_exists( 'content', $list ), 'List omits content' );
$assert( ! array_key_exists( 'contentMap', $list ), 'List omits contentMap' );
$assert( ! array_key_exists( 'attachment', $list ), 'Default list omits top-level attachment' );
$assert( $document['id'] === $list['id'], 'List retains identity' );
$assert( $document['image'] === $list['image'], 'Cover and dimensions survive' );
$assert( $document['preview'] === $list['preview'], 'Nested preview is untouched' );
$included = $project( $document, 'list', 'include' );
$assert( $document['attachment'] === $included['attachment'], 'Requested attachment descriptors survive' );
$assert( ! isset( $included['content'], $included['contentMap'] ) && ! array_key_exists( 'content', $included ) && ! array_key_exists( 'contentMap', $included ), 'Attachments never enable body' );
$assert( $document === $project( $document, 'detail' ), 'Detail remains complete' );
$assert( $document === $project( $document ), 'Default response remains detail' );
$assert( $original === $document, 'Source document is unchanged' );
$note = array_replace( $document, array( 'type' => 'Note' ) );
$assert( $note === $project( $note, 'list' ), 'Note body and media are untouched' );
$tombstone = array( 'id' => $document['id'], 'type' => 'Tombstone' );
$assert( $tombstone === $project( $tombstone, 'list' ), 'Tombstone remains minimal' );
$assert( is_wp_error( $project( $document, 'unknown' ) ), 'Unknown representation fails closed' );
$assert( is_wp_error( $project( $document, 'list', 'unknown' ) ), 'Unknown attachment policy fails closed' );
$assert( $queries === $wpdb->num_queries, 'Projection performs no persistence queries' );
$assert( is_wp_error( axismundi_op_read_application_article_list( PHP_INT_MAX ) ), 'List reader preserves unknown-ID denial' );
printf( "== %d checks, 0 failed ==\n", $checks );
