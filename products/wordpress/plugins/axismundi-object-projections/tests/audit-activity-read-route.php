<?php
/** Canonical Activity snapshot and negotiation boundary audit (dev-only). */
defined( 'ABSPATH' ) || exit( 1 );
$results = array();
$user_id = 0;
$identity_id = 0;
$uris = array();
$assemblies = axismundi_op_object_assemblies();
function ax_activity_read_assert( array &$results, string $label, bool $holds ) : void {
	$results[] = $holds;
	printf( "[%s] [project choice] %s\n", $holds ? 'PASS' : 'FAIL', $label );
}
function ax_activity_read_missing( $result ) : bool {
	return is_wp_error( $result ) && 404 === (int) ( $result->get_error_data()['status'] ?? 0 );
}
try {
	global $wpdb;
	$login = 'activity_read_' . strtolower( wp_generate_password( 8, false, false ) );
	$user_id = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$actor = axismundi_actors_ensure_for_user( $user_id );
	$identity_id = $actor->get_identity_id();
	axismundi_actors_register_handle( $identity_id, $login );
	axismundi_actors_set_status( $identity_id, 'public' );
	$actor = axismundi_actors_get_for_user( $user_id );
	$uuid = wp_generate_uuid4();
	$uris[] = axismundi_act_local_uri( $uuid );
	$uris[] = axismundi_act_local_uri( wp_generate_uuid4() );
	$payload = array(
		'@context' => array( 'https://www.w3.org/ns/activitystreams', array( 'auditExtension' => 'https://example.org/ns#audit' ) ),
		'id' => $uris[0], 'type' => 'Update', 'actor' => $actor->get_uri(),
		'url' => 'https://example.org/activities/display',
		'object' => array( 'id' => home_url( '/?p=999999999' ), 'type' => 'Article', 'nameMap' => array( 'en' => 'Historical title' ), 'contentMap' => array( 'en' => '<p>Historical body.</p>' ) ),
		'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		'bcc' => array( 'https://example.org/private' ), 'auditExtension' => 'Keep historical extension',
	);
	$activity = axismundi_act_record_activity( $payload, 'outbound' );
	if ( ! $activity instanceof Axismundi_Activity ) {
		throw new RuntimeException( 'The public Activity fixture could not be recorded.' );
	}
	$private = $payload;
	$private['id'] = $uris[1];
	$private['to'] = array( $actor->get_uri() );
	unset( $private['bcc'] );
	axismundi_act_record_activity( $private, 'outbound' );
	$path = wp_parse_url( $uris[0], PHP_URL_PATH );
	ax_activity_read_assert( $results, 'the canonical path and debugging selector resolve to the minted URI', $uris[0] === axismundi_op_requested_activity_uri( $path . '?activitypub' ) );
	ax_activity_read_assert( $results, 'a slashless request resolves without redirecting the identity', $uris[0] === axismundi_op_requested_activity_uri( untrailingslashit( $path ) ) );
	$base_path = (string) wp_parse_url( home_url( '/activities/' ), PHP_URL_PATH );
	ax_activity_read_assert( $results, 'malformed Activity paths are claimed as not found', '' === axismundi_op_requested_activity_uri( $base_path . 'invalid/' ) );
	ax_activity_read_assert( $results, 'other namespaces are not claimed', null === axismundi_op_requested_activity_uri( '/?ax_object=invalid&activitypub' ) );
	$calls = 0;
	$GLOBALS['axismundi_op_object_assemblies']['Object']['jsonld'] = static function ( array $document, string $representation = 'application' ) use ( &$calls ) : array {
		++$calls;
		return axismundi_op_assemble_read_jsonld( $document, $representation );
	};
	$document = axismundi_op_read_activity_jsonld( $uris[0] );
	$expected = axismundi_act_public_payload( $activity );
	$expected['@context'] = $expected['@context'] ?? axismundi_op_jsonld_context( $expected );
	ax_activity_read_assert( $results, 'a public local Activity uses the shared hierarchy assembly', is_array( $document ) && 1 === $calls && 'Update' === $document['type'] );
	ax_activity_read_assert( $results, 'canonical output matches the public Outbox snapshot', is_array( $document ) && axismundi_op_order_object_members( $expected ) === $document );
	ax_activity_read_assert( $results, 'embedded Article and language policy remain historical, without resolving its missing post', is_array( $document ) && $payload['object'] === $document['object'] );
	ax_activity_read_assert( $results, 'historical context and extension members survive', is_array( $document ) && $payload['@context'] === $document['@context'] && $payload['auditExtension'] === $document['auditExtension'] );
	ax_activity_read_assert( $results, 'blind recipients are never disclosed', is_array( $document ) && ! array_key_exists( 'bcc', $document ) && ! array_key_exists( 'bto', $document ) );
	ax_activity_read_assert( $results, 'a private Activity is not publicly readable', ax_activity_read_missing( axismundi_op_read_activity_jsonld( $uris[1] ) ) );
	ax_activity_read_assert( $results, 'unknown canonical UUIDs return not found', ax_activity_read_missing( axismundi_op_read_activity_jsonld( axismundi_act_local_uri( wp_generate_uuid4() ) ) ) );
	$table = axismundi_act_activities_table();
	$rich_payload = $activity->get_payload();
	$rich_payload['type'] = array( 'Update', 'https://example.org/ns#ExtendedUpdate' );
	$rich_payload['actor'] = array( 'id' => $actor->get_uri(), 'type' => 'Person' );
	$wpdb->update( $table, array( 'payload_json' => wp_json_encode( $rich_payload ) ), array( 'activity_uri' => $uris[0] ) );
	$rich_document = axismundi_op_read_activity_jsonld( $uris[0] );
	ax_activity_read_assert( $results, 'ledger-supported type arrays and embedded Actors retain their authored shape', is_array( $rich_document ) && $rich_payload['type'] === $rich_document['type'] && $rich_payload['actor'] === $rich_document['actor'] );
	$wpdb->update( $table, array( 'payload_json' => wp_json_encode( $activity->get_payload() ) ), array( 'activity_uri' => $uris[0] ) );
	foreach ( array( 'direction' => 'inbound', 'effective_status' => 'undone' ) as $column => $value ) {
		$wpdb->update( $table, array( $column => $value ), array( 'activity_uri' => $uris[0] ) );
		ax_activity_read_assert( $results, $column . '=' . $value . ' cannot be re-served as a local public Activity', ax_activity_read_missing( axismundi_op_read_activity_jsonld( $uris[0] ) ) );
		$wpdb->update( $table, array( $column => 'direction' === $column ? 'outbound' : 'active' ), array( 'activity_uri' => $uris[0] ) );
	}
	$deny = static function ( $candidate, Axismundi_Activity $row ) use ( $uris ) { return $row->get_uri() === $uris[0] ? null : $candidate; };
	add_filter( 'axismundi_act_public_outbox_payload', $deny, 999, 2 );
	try {
		ax_activity_read_assert( $results, 'the Outbox domain admission filter can withhold the canonical read', ax_activity_read_missing( axismundi_op_read_activity_jsonld( $uris[0] ) ) );
	} finally {
		remove_filter( 'axismundi_act_public_outbox_payload', $deny, 999 );
	}
	$admit = static function ( $candidate, Axismundi_Activity $row ) use ( $uris ) { return $row->get_uri() === $uris[1] ? $row->get_payload() : $candidate; };
	add_filter( 'axismundi_act_public_outbox_payload', $admit, 999, 2 );
	try {
		ax_activity_read_assert( $results, 'an explicit domain public-Outbox opt-in applies to canonical reads too', is_array( axismundi_op_read_activity_jsonld( $uris[1] ) ) );
	} finally {
		remove_filter( 'axismundi_act_public_outbox_payload', $admit, 999 );
	}
	$normal_assembly = $GLOBALS['axismundi_op_object_assemblies']['Object']['jsonld'];
	$GLOBALS['axismundi_op_object_assemblies']['Object']['jsonld'] = static function ( array $document ) : array {
		$document['object'] = 'https://example.org/not-the-historical-object';
		return $document;
	};
	try {
		$rewritten = axismundi_op_read_activity_jsonld( $uris[0] );
		ax_activity_read_assert( $results, 'an application assembly cannot rewrite the historical snapshot', is_wp_error( $rewritten ) && 'ax_op_activity_snapshot_changed' === $rewritten->get_error_code() );
	} finally {
		$GLOBALS['axismundi_op_object_assemblies']['Object']['jsonld'] = $normal_assembly;
	}
	axismundi_actors_set_status( $identity_id, 'internal' );
	ax_activity_read_assert( $results, 'a non-public Actor cannot expose historical Activities', ax_activity_read_missing( axismundi_op_read_activity_jsonld( $uris[0] ) ) );
} catch ( Throwable $error ) {
	ax_activity_read_assert( $results, 'fixture completes: ' . $error->getMessage(), false );
} finally {
	$GLOBALS['axismundi_op_object_assemblies'] = $assemblies;
	foreach ( $uris as $uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $uri ) );
	}
	if ( $identity_id > 0 ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table(), axismundi_actors_actors_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $identity_id ) );
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ) );
	}
	if ( $user_id > 0 && get_userdata( $user_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );
	}
}
$failed = count( array_filter( $results, static fn( bool $result ) : bool => ! $result ) );
printf( "\n== %d checks, %d failed ==\n", count( $results ), $failed );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $failed ? 1 : 0 );
}
