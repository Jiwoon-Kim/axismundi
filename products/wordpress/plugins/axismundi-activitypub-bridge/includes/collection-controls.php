<?php
/** Authenticated Group announcements of collection controls; no membership or principal writes. */
defined( 'ABSPATH' ) || exit;

function axismundi_activitypub_bridge_collection_controls_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_collection_controls';
}

function axismundi_activitypub_bridge_install_collection_controls() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = axismundi_activitypub_bridge_collection_controls_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		operation_key char(64) NOT NULL,
		group_uri text NOT NULL,
		target_uri text NOT NULL,
		remote_activity_uri text DEFAULT NULL,
		envelope_uri text DEFAULT NULL,
		envelope_json longtext NOT NULL,
		payload_json longtext NOT NULL,
		payload_hash char(64) NOT NULL,
		outcome varchar(32) NOT NULL,
		received_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY operation_key (operation_key)
	) ENGINE=InnoDB {$charset};" );
	$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
	if ( array_diff( array( 'operation_key', 'group_uri', 'target_uri', 'remote_activity_uri', 'envelope_uri', 'envelope_json', 'payload_json', 'payload_hash', 'outcome', 'received_at' ), (array) $columns ) ) { return false; }
	update_option( 'ax_bridge_collection_controls_schema', '2', false );
	return true;
}
add_action( 'plugins_loaded', static function () : void {
	if ( '2' !== get_option( 'ax_bridge_collection_controls_schema' ) ) { axismundi_activitypub_bridge_install_collection_controls(); }
} );

/** Stable object-key ordering only; arrays and all supplied fields retain their meaning. */
function axismundi_activitypub_bridge_control_json( array $payload ) : string {
	$sort = static function ( $value ) use ( &$sort ) {
		if ( ! is_array( $value ) ) { return $value; }
		if ( ! array_is_list( $value ) ) { ksort( $value ); }
		return array_map( $sort, $value );
	};
	return (string) wp_json_encode( $sort( $payload ) );
}

/** A declared reference is not a moderator role or an operating grant. */
function axismundi_activitypub_bridge_group_advertises_collection( Axismundi_Actor $group, string $target ) : bool {
	if ( $group->is_local() || 'Group' !== $group->get_type() ) { return false; }
	$document = axismundi_actors_get_remote_payload( $group->get_identity_id() );
	$references = array_merge(
		axismundi_activitypub_bridge_member_uris( $document['attributedTo'] ?? array() ),
		axismundi_activitypub_bridge_member_uris( $document['featured'] ?? array() )
	);
	// Exact advertised URI plus same origin: never infer authority from a path suffix or domain alone.
	$origin = static fn( string $uri ) : array => array( wp_parse_url( $uri, PHP_URL_SCHEME ), wp_parse_url( $uri, PHP_URL_HOST ), wp_parse_url( $uri, PHP_URL_PORT ) );
	return in_array( $target, $references, true ) && $origin( $target ) === $origin( $group->get_uri() );
}

/** Called only by verified ingress. The outer signer and inner author remain distinct. */
function axismundi_activitypub_bridge_receive_collection_control( array $payload, Axismundi_Actor $sender, array $recipients ) {
	global $wpdb;
	if ( $sender->is_local() || $sender->get_uri() !== axismundi_act_member_uri( $payload['actor'] ?? '' ) ) { return new WP_Error( 'ax_bridge_control_sender', 'The payload does not identify its verified sender.' ); }
	$announced = 'Announce' === ( $payload['type'] ?? '' );
	$inner = $announced ? ( $payload['object'] ?? null ) : $payload;
	if ( ! is_array( $inner ) || ! in_array( $inner['type'] ?? '', array( 'Add', 'Remove' ), true ) ) {
		return new WP_Error( 'ax_bridge_control_unsupported', 'This nested Activity is not supported.', array( 'status' => 400 ) );
	}
	foreach ( array( 'actor', 'object', 'target' ) as $field ) {
		if ( ! is_string( $inner[$field] ?? null ) || '' === axismundi_act_member_uri( $inner[$field] ) ) { return new WP_Error( 'ax_bridge_control_shape', 'Collection controls require URI actor, object and target.' ); }
	}
	$person = axismundi_actors_get_by_uri( $inner['actor'] );
	if ( ! $person ) { $person = axismundi_actors_discover_remote_actor_uri( $inner['actor'] ); }
	if ( ! $person instanceof Axismundi_Actor || 'Person' !== $person->get_type() || $person->get_uri() !== $inner['actor'] ) {
		return new WP_Error( 'ax_bridge_control_actor', 'The inner author must resolve to a Person.', array( 'status' => 403 ) );
	}
	$group = $announced ? $sender : axismundi_actors_get_by_uri( axismundi_act_member_uri( $inner['audience'] ?? '' ) );
	if ( ! $group instanceof Axismundi_Actor || ! axismundi_activitypub_bridge_group_advertises_collection( $group, $inner['target'] )
		|| ( ! $announced && $sender->get_uri() !== $person->get_uri() ) ) {
		return new WP_Error( 'ax_bridge_control_owner', 'The announcement does not identify the advertised collection owner.', array( 'status' => 403 ) );
	}
	if ( isset( $inner['audience'] ) && $group->get_uri() !== axismundi_act_member_uri( $inner['audience'] ) ) { return new WP_Error( 'ax_bridge_control_audience', 'The control audience contradicts its Group.' ); }
	if ( isset( $inner['id'] ) && ( ! is_string( $inner['id'] ) || '' === axismundi_act_member_uri( $inner['id'] ) ) ) { return new WP_Error( 'ax_bridge_control_id', 'Invalid inner Activity identity.' ); }
	$ledger_inner = $inner;
	// The Group signature attests the envelope, not a cross-origin Person payload.
	if ( $announced && isset( $inner['id'] ) ) {
		if ( wp_parse_url( $inner['id'], PHP_URL_HOST ) !== wp_parse_url( $person->get_uri(), PHP_URL_HOST ) ) { return new WP_Error( 'ax_bridge_control_origin', 'The inner Activity does not originate at its author.' ); }
		$original = axismundi_actors_remote_get_json( $inner['id'], array( 'application/activity+json', 'application/ld+json', 'application/json' ) );
		if ( is_wp_error( $original ) ) { return $original; }
		$ledger_inner = $original;
		$compare = $inner;
		unset( $original['@context'], $compare['@context'] );
		if ( axismundi_activitypub_bridge_control_json( $original ) !== axismundi_activitypub_bridge_control_json( $compare ) ) { return new WP_Error( 'ax_bridge_control_original', 'The nested Activity differs from its authoritative representation.' ); }
	}
	$json = axismundi_activitypub_bridge_control_json( $inner );
	if ( '' === $json || strlen( $json ) > 1024 * 1024 ) { return new WP_Error( 'ax_bridge_control_size', 'Collection control is too large.' ); }
	$intent = $inner;
	unset( $intent['@context'] );
	$intent_json = axismundi_activitypub_bridge_control_json( $intent );
	$key = hash( 'sha256', isset( $inner['id'] ) ? 'id:' . $inner['id'] : 'intent:' . $group->get_uri() . ':' . ( $announced ? ( $payload['id'] ?? '' ) : '' ) . ':' . $intent_json );
	$lock = 'ax-control-' . substr( $key, 0, 48 );
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,5)', $lock ) ) ) { return new WP_Error( 'ax_bridge_control_busy', 'Collection control is busy.' ); }
	try {
		$table = axismundi_activitypub_bridge_collection_controls_table();
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE operation_key=%s', $table, $key ), ARRAY_A );
		if ( $wpdb->last_error ) { return new WP_Error( 'ax_bridge_control_store', 'Collection receipt is unavailable.' ); }
		if ( $row && ( $row['payload_hash'] !== hash( 'sha256', $intent_json ) || $row['group_uri'] !== $group->get_uri() ) ) { return new WP_Error( 'ax_bridge_control_conflict', 'Conflicting collection Activity replay.' ); }
		// The ledger cannot name an anonymous inner object. Retain that envelope as evidence only.
		if ( $announced && isset( $inner['id'] ) ) {
			$outer = axismundi_act_record_activity( $payload, 'inbound', $recipients );
			if ( is_wp_error( $outer ) ) { return $outer; }
		}
		if ( isset( $inner['id'] ) ) {
			$stored = axismundi_act_record_activity( $ledger_inner, 'inbound', $recipients );
			if ( is_wp_error( $stored ) ) { return $stored; }
		}
		if ( ! $row ) {
			if ( false === $wpdb->insert( $table, array( 'operation_key' => $key, 'group_uri' => $group->get_uri(), 'target_uri' => $inner['target'], 'remote_activity_uri' => $inner['id'] ?? null,
				'envelope_uri' => $announced ? ( $payload['id'] ?? null ) : null, 'envelope_json' => wp_json_encode( $payload ), 'payload_json' => $json, 'payload_hash' => hash( 'sha256', $intent_json ), 'outcome' => 'unclaimed', 'received_at' => current_time( 'mysql', true ) ) ) ) { return new WP_Error( 'ax_bridge_control_store', 'Could not store collection receipt.' ); }
			$row = array( 'id' => (int) $wpdb->insert_id, 'outcome' => 'unclaimed' );
		}
		// No handlers are registered by this slice. A handler must authorize the Person separately.
		$handler = apply_filters( 'axismundi_activitypub_bridge_collection_control_handler', null, $inner['target'], $group );
		if ( 'handled' !== $row['outcome'] && is_callable( $handler ) ) {
			$result = $handler( $inner, array( 'operation_key' => $key, 'group' => $group, 'person' => $person, 'announced' => $announced, 'author_verified' => ! $announced || isset( $inner['id'] ) ) );
			if ( is_wp_error( $result ) ) { return $result; }
			if ( true === $result ) {
				if ( false === $wpdb->update( $table, array( 'outcome' => 'handled' ), array( 'id' => $row['id'] ) ) ) { return new WP_Error( 'ax_bridge_control_store', 'Could not finish collection receipt.' ); }
				$row['outcome'] = 'handled';
			}
		}
		return array( 'collection_control_receipt' => (int) $row['id'], 'outcome' => $row['outcome'] );
	} finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
}
