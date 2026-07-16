<?php
/** Remote Collection metadata probe regression (dev-only; dist-excluded). */
defined( 'ABSPATH' ) || exit( 1 );

$ax_op_collection_results  = array();
$ax_op_collection_requests = array();
function ax_op_remote_collection_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
}

$ax_op_collection_mock = static function ( $preempt, $args, $url ) use ( &$ax_op_collection_requests ) {
	$ax_op_collection_requests[] = $url;
	$documents = array(
		'https://example.com/media/folder/11111111-1111-4111-8111-111111111111' => array( 'id' => 'https://example.com/media/folder/11111111-1111-4111-8111-111111111111', 'type' => 'OrderedCollection', 'totalItems' => 2, 'first' => 'https://example.com/media/folder/11111111-1111-4111-8111-111111111111/page/1' ),
		'https://example.com/media/folder/11111111-1111-4111-8111-111111111111/page/1' => array( 'id' => 'https://example.com/media/folder/11111111-1111-4111-8111-111111111111/page/1', 'type' => 'OrderedCollectionPage', 'partOf' => 'https://example.com/media/folder/11111111-1111-4111-8111-111111111111', 'orderedItems' => array( array( 'id' => 'https://example.com/?attachment_id=1', 'type' => 'Image', 'name' => 'One', 'url' => array( array( 'type' => 'Link', 'href' => 'https://example.com/one-1024.jpg', 'mediaType' => 'image/jpeg', 'width' => 1024, 'height' => 768, 'size' => 1000 ) ) ), 'https://example.com/?attachment_id=2' ) ),
		'https://example.com/not-collection' => array( 'id' => 'https://example.com/not-collection', 'type' => 'Note' ),
		'https://example.com/cross-host' => array( 'id' => 'https://example.com/cross-host', 'type' => 'OrderedCollection', 'first' => 'https://example.net/page/1' ),
	);
	if ( ! isset( $documents[ $url ] ) ) { return $preempt; }
	return array( 'headers' => array( 'content-type' => 'application/activity+json' ), 'body' => wp_json_encode( $documents[ $url ] ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
};
add_filter( 'pre_http_request', $ax_op_collection_mock, 10, 3 );
try {
	$ax_op_collection_root  = 'https://example.com/media/folder/11111111-1111-4111-8111-111111111111';
	$ax_op_collection_probe = axismundi_op_remote_collection_fetch( $ax_op_collection_root );
	ax_op_remote_collection_assert( $ax_op_collection_results, 'probe reads the root and same-host first page', is_array( $ax_op_collection_probe ) && 2 === count( $ax_op_collection_probe['items'] ) && 2 === count( $ax_op_collection_requests ) );
	ax_op_remote_collection_assert( $ax_op_collection_results, 'item URLs and media binaries are never fetched', ! in_array( 'https://example.com/?attachment_id=2', $ax_op_collection_requests, true ) && ! in_array( 'https://example.com/one-1024.jpg', $ax_op_collection_requests, true ) );
	ax_op_remote_collection_assert( $ax_op_collection_results, 'embedded metadata is retained for inspector display', 'Image' === ( $ax_op_collection_probe['items'][0]['type'] ?? '' ) && 1024 === (int) ( $ax_op_collection_probe['items'][0]['url'][0]['width'] ?? 0 ) );
	$ax_op_collection_wrong = axismundi_op_remote_collection_fetch( 'https://example.com/not-collection' );
	ax_op_remote_collection_assert( $ax_op_collection_results, 'non-Collection documents are rejected', is_wp_error( $ax_op_collection_wrong ) && 'ax_op_collection_type' === $ax_op_collection_wrong->get_error_code() );
	$ax_op_collection_cross = axismundi_op_remote_collection_fetch( 'https://example.com/cross-host' );
	ax_op_remote_collection_assert( $ax_op_collection_results, 'automatic first-page follow is restricted to the Collection host', is_wp_error( $ax_op_collection_cross ) && 'ax_op_collection_page_host' === $ax_op_collection_cross->get_error_code() );
} finally {
	remove_filter( 'pre_http_request', $ax_op_collection_mock, 10 );
}
$ax_op_collection_failed = count( array_filter( $ax_op_collection_results, static fn( $ok ) => ! $ok ) );
printf( "\n== %d checks, %d failed ==\n", count( $ax_op_collection_results ), $ax_op_collection_failed ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
if ( $ax_op_collection_failed ) { exit( 1 ); }
