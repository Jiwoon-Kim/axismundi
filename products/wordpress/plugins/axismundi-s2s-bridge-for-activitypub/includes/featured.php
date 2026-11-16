<?php
/** Verified featured delivery observations, not invented remote Activity identities. */
defined( 'ABSPATH' ) || exit;

function axismundi_activitypub_bridge_featured_receipts_table() : string { global $wpdb; return $wpdb->prefix . 'ax_featured_receipts'; }

function axismundi_activitypub_bridge_install_featured_receipts() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = axismundi_activitypub_bridge_featured_receipts_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		actor_id bigint(20) unsigned NOT NULL,
		remote_activity_uri text DEFAULT NULL,
		payload_json longtext NOT NULL,
		received_at datetime NOT NULL,
		outcome varchar(100) NOT NULL,
		PRIMARY KEY  (id),
		KEY actor_receipts (actor_id,id)
	) ENGINE=InnoDB {$charset};" );
	$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
	if ( ! is_array( $columns ) || array_diff( array( 'actor_id', 'remote_activity_uri', 'payload_json', 'received_at', 'outcome' ), $columns ) ) { return false; }
	update_option( 'ax_bridge_featured_receipts_schema', '1', false );
	return true;
}
add_action( 'plugins_loaded', static function () : void {
	if ( '1' !== get_option( 'ax_bridge_featured_receipts_schema', '' ) ) { axismundi_activitypub_bridge_install_featured_receipts(); }
} );

/** This shape alone grants nothing; the HTTP verifier and collection ownership checks still run. */
function axismundi_activitypub_bridge_featured_control( array $payload ) : bool {
	return in_array( $payload['type'] ?? '', array( 'Add', 'Remove' ), true )
		&& is_string( $payload['actor'] ?? null ) && '' !== axismundi_act_member_uri( $payload['actor'] )
		&& is_string( $payload['object'] ?? null ) && '' !== axismundi_act_member_uri( $payload['object'] )
		&& is_string( $payload['target'] ?? null ) && '' !== axismundi_act_member_uri( $payload['target'] );
}

/** Called only after verified ingress and recipient moderation, like record_inbox itself. */
function axismundi_activitypub_bridge_receive_featured( array $payload, Axismundi_Actor $sender ) {
	global $wpdb;
	if ( '1' !== get_option( 'ax_bridge_featured_receipts_schema', '' ) ) { return new WP_Error( 'ax_bridge_featured_schema', 'Featured receipts are unavailable.', array( 'status' => 503 ) ); }
	if ( $sender->is_local() || 'Person' !== $sender->get_type() || ! axismundi_activitypub_bridge_featured_control( $payload )
		|| $sender->get_uri() !== $payload['actor'] || axismundi_actors_featured_uri( $sender ) !== $payload['target'] ) {
		return new WP_Error( 'ax_bridge_featured_authority', 'The sender does not control this featured collection.', array( 'status' => 403 ) );
	}
	$json = wp_json_encode( $payload );
	if ( ! is_string( $json ) || strlen( $json ) > 1024 * 1024 ) { return new WP_Error( 'ax_bridge_featured_size', 'Featured delivery exceeds one MiB.' ); }
	$ok = $wpdb->insert( axismundi_activitypub_bridge_featured_receipts_table(), array(
		'actor_id' => $sender->get_identity_id(), 'remote_activity_uri' => $payload['id'] ?? null,
		'payload_json' => $json, 'received_at' => current_time( 'mysql', true ), 'outcome' => 'received',
	) );
	if ( false === $ok ) { return new WP_Error( 'ax_bridge_featured_receipt', 'Featured receipt could not be recorded.' ); }
	$id = (int) $wpdb->insert_id;
	// Signals never directly apply membership: a stale Add cannot override a newer snapshot.
	$result = axismundi_actors_request_featured_sync( $sender );
	$wpdb->update( axismundi_activitypub_bridge_featured_receipts_table(), array( 'outcome' => is_wp_error( $result ) ? $result->get_error_code() : 'sync-requested' ), array( 'id' => $id ) );
	return is_wp_error( $result ) ? $result : array( 'featured_receipt' => $id );
}

/** The official shared route requires id; narrowly adapt only anonymous-identity featured controls. */
function axismundi_activitypub_bridge_idless_featured_inbox( $result, WP_REST_Server $server, WP_REST_Request $request ) {
	if ( null !== $result || '/activitypub/1.0/inbox' !== $request->get_route() || 'POST' !== $request->get_method() ) { return $result; }
	$payload = $request->get_json_params();
	if ( ! is_array( $payload ) || isset( $payload['id'] ) || ! axismundi_activitypub_bridge_featured_control( $payload ) ) { return $result; }
	if ( strlen( $request->get_body() ) > 1024 * 1024 || ! axismundi_activitypub_bridge_ready() ) { return new WP_Error( 'ax_bridge_featured_body', 'Featured delivery is unavailable or too large.', array( 'status' => 400 ) ); }
	$verified = ( new Activitypub\Rest\Inbox_Controller() )->verify_signature( $request, true );
	if ( is_wp_error( $verified ) || true !== $verified ) { return is_wp_error( $verified ) ? $verified : new WP_Error( 'ax_bridge_signature', 'A verified signature is required.', array( 'status' => 401 ) ); }
	$received = axismundi_activitypub_bridge_record_inbox( $payload, array(), true );
	return is_wp_error( $received ) ? $received : new WP_REST_Response( null, 202, array( 'Cache-Control' => 'private, no-store' ) );
}
add_filter( 'rest_pre_dispatch', 'axismundi_activitypub_bridge_idless_featured_inbox', 20, 3 );
