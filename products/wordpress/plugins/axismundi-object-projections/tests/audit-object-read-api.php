<?php
/** Raw source JSON, with disclosure separate from HTML rendering. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks;
};
$network = 0;
$deny = static function () use ( &$network ) { ++$network; return new WP_Error( 'audit_no_http', 'No network' ); };
add_filter( 'pre_http_request', $deny, PHP_INT_MAX );
$assert( false !== $wpdb->query( 'START TRANSACTION' ), 'Fixture transaction started' );
try {
	$uri = 'https://raw-read.example/objects/' . wp_generate_uuid4() . '?a=1&b=2';
	$payload = array(
		'@context' => array( 'https://www.w3.org/ns/activitystreams', array( 'custom' => 'https://raw-read.example/ns#custom' ) ),
		'id' => $uri, 'type' => 'Note', 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		'content' => '<p onclick="bad()">Original <script>bad()</script>body</p>',
		'contentMap' => array( 'en' => '<p onmouseover="bad()">Original map</p>' ),
		'custom' => array( 'unmodeled' => true, 'emptyObject' => (object) array(), 'emptyArray' => array() ),
		'tag' => array( array( 'type' => 'Emoji', 'name' => ':custom:', 'icon' => array( 'type' => 'Image', 'url' => 'https://raw-read.example/icon.png' ) ) ),
	);
	$row = axismundi_op_store_remote_object( $payload );
	$assert( is_array( $row ), 'Remote payload stored' );
	$before = axismundi_op_get_remote_object( $uri, false );
	$read = axismundi_op_read_object_jsonld( $uri );
	$assert( wp_json_encode( $read, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) === $before['payload_json'], 'JSON preserves authored context, extensions, empty objects versus arrays and key order' );
	$assert( $payload['content'] === $read['content'] && $payload['contentMap'] === (array) $read['contentMap'], 'JSON HTML and maps are not sanitized' );
	foreach ( array( 'axObservation', 'axUninterpreted', 'axPayloadFallback' ) as $field ) {
		$assert( ! array_key_exists( $field, $read ), 'No synthetic ' . $field );
	}
	$wpdb->update( axismundi_op_remote_objects_table(), array( 'content' => 'Index-only override' ), array( 'id' => $before['id'] ) );
	$assert( wp_json_encode( $read ) === wp_json_encode( axismundi_op_read_object_jsonld( $uri ) ), 'Index values cannot overwrite payload values' );
	$after = axismundi_op_get_remote_object( $uri, false );
	$assert( $before['payload_json'] === $after['payload_json'] && $before['payload_hash'] === $after['payload_hash'] && $before['fetched_at'] === $after['fetched_at'], 'Read preserves cache bytes, hash and observation time' );
	$object = axismundi_op_get_object_by_uri( $uri );
	$request = new WP_REST_Request( 'GET', '/axismundi/v1/objects/' . $object['id'] );
	$response = rest_do_request( $request );
	$assert( 200 === $response->get_status() && wp_json_encode( $read ) === wp_json_encode( $response->get_data() ), 'Numeric route returns the document itself' );
	$assert( str_contains( $response->get_headers()['Cache-Control'], 'no-store' ), 'Response remains non-cacheable' );
	$assert( ! str_contains( axismundi_op_clean_html( $payload['content'] ), '<script' ) && ! str_contains( axismundi_op_clean_html( $payload['content'] ), 'onclick' ), 'HTML sanitizer remains available to renderers' );
	$payload['bcc'] = array( 'https://raw-read.example/secret' );
	$payload['object'] = array( 'type' => 'Note', 'bcc' => $payload['bcc'], 'content' => $payload['content'] );
	$assert( is_array( axismundi_op_store_remote_object( $payload ) ), 'Blind-address fixture stored' );
	$read = axismundi_op_read_object_jsonld( $uri );
	$assert( ! isset( $read['bcc'] ) && ! isset( $read['object']->bcc ) && $payload['object']['content'] === $read['object']->content, 'Blind-address removal does not sanitize nested HTML' );
	$private_uri = 'https://raw-read.example/objects/' . wp_generate_uuid4();
	$private = array_replace( $payload, array( 'id' => $private_uri, 'to' => array( 'https://raw-read.example/private' ) ) );
	$assert( is_array( axismundi_op_store_remote_object( $private ) ), 'Private fixture stored' );
	$private_read = axismundi_op_read_object_jsonld( $private_uri );
	$assert( is_wp_error( $private_read ) && 404 === $private_read->get_error_data()['status'], 'Raw payload still requires disclosure permission' );
	$deleted_uri = 'https://raw-read.example/objects/' . wp_generate_uuid4();
	axismundi_op_store_remote_object( array( 'id' => $deleted_uri, 'type' => 'Tombstone', 'content' => 'Old secret' ) );
	$assert( array( 'id' => $deleted_uri, 'type' => 'Tombstone' ) === axismundi_op_read_object_jsonld( $deleted_uri ), 'Deletion does not revive an old body' );
	$assert( 0 === $network, 'Reads never fetch or refresh remote sources' );
} finally {
	$wpdb->query( 'ROLLBACK' );
	remove_filter( 'pre_http_request', $deny, PHP_INT_MAX );
}
echo "PASS: {$checks} raw Object read checks\n";
