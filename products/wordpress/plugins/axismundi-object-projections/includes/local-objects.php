<?php
/** OP-owned current local Object bodies. No WP_Post, remote cache or history store. */
defined( 'ABSPATH' ) || exit;

function axismundi_op_local_objects_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_local_objects';
}

function axismundi_op_install_local_objects() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = axismundi_op_local_objects_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		local_uuid char(36) NOT NULL,
		object_uri text NOT NULL,
		object_uri_hash char(64) NOT NULL,
		object_type varchar(64) NOT NULL,
		object_status varchar(12) NOT NULL DEFAULT 'active',
		attributed_to_uri text NOT NULL,
		attributed_to_uri_hash char(64) NOT NULL,
		document_json longtext NOT NULL,
		document_hash char(64) NOT NULL,
		version bigint(20) unsigned NOT NULL DEFAULT 1,
		publicly_readable tinyint(1) NOT NULL DEFAULT 0,
		published_at datetime NOT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		deleted_at datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY local_uuid (local_uuid),
		UNIQUE KEY object_uri_hash (object_uri_hash),
		KEY author_status (attributed_to_uri_hash,object_status,id)
	) ENGINE=InnoDB {$charset};" );
	$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
	$keys = $wpdb->get_results( "SHOW INDEX FROM {$table}", ARRAY_A );
	$unique = array();
	foreach ( $keys ?? array() as $key ) { if ( 0 === (int) $key['Non_unique'] ) { $unique[ $key['Key_name'] ][] = $key['Column_name']; } }
	$valid = is_array( $columns ) && ! array_diff( array( 'id', 'local_uuid', 'object_uri', 'object_uri_hash', 'object_type', 'object_status', 'attributed_to_uri', 'attributed_to_uri_hash', 'document_json', 'document_hash', 'version', 'publicly_readable', 'published_at', 'created_at', 'updated_at', 'deleted_at' ), $columns ) && 'InnoDB' === $engine
		&& array( 'id' ) === ( $unique['PRIMARY'] ?? array() ) && array( 'local_uuid' ) === ( $unique['local_uuid'] ?? array() ) && array( 'object_uri_hash' ) === ( $unique['object_uri_hash'] ?? array() );
	if ( $valid ) { update_option( 'ax_op_local_objects_schema', '1', false ); }
	return $valid;
}
add_action( 'plugins_loaded', static function () : void {
	if ( '1' !== get_option( 'ax_op_local_objects_schema', '' ) ) { axismundi_op_install_local_objects(); }
} );

function axismundi_op_local_object_uri( string $uuid ) : string {
	return home_url( '/objects/' . strtolower( $uuid ) );
}

function axismundi_op_get_local_object( string $uri ) : ?array {
	global $wpdb;
	if ( '1' !== get_option( 'ax_op_local_objects_schema', '' ) ) { return null; }
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE object_uri_hash=%s AND object_uri=%s', axismundi_op_local_objects_table(), hash( 'sha256', $uri ), $uri ), ARRAY_A );
	if ( ! is_array( $row ) ) { return null; }
	$row['document'] = json_decode( $row['document_json'], true );
	return $row;
}

/** Validate before allocation; preserve structured values instead of selecting their first member. */
function axismundi_op_prepare_local_note( array $input, Axismundi_Actor $actor, int $user_id = 0 ) {
	if ( 'Note' !== ( $input['type'] ?? '' ) ) { return new WP_Error( 'ax_op_local_type', 'This authoring slice accepts Note only.', array( 'status' => 422 ) ); }
	if ( isset( $input['attributedTo'] ) && $input['attributedTo'] !== $actor->get_uri() ) { return new WP_Error( 'ax_op_local_author', 'Object attribution conflicts with the outbox Actor.', array( 'status' => 403 ) ); }
	unset( $input['id'], $input['url'], $input['published'], $input['updated'], $input['deleted'], $input['formerType'], $input['@context'] );
	$input['attributedTo'] = $actor->get_uri();
	if ( isset( $input['sensitive'] ) && ! is_bool( $input['sensitive'] ) ) { return new WP_Error( 'ax_op_local_sensitive', 'Sensitive must be a boolean.', array( 'status' => 400 ) ); }
	foreach ( array( 'content', 'summary', 'name' ) as $field ) {
		$clean = 'name' === $field ? 'sanitize_text_field' : 'axismundi_op_clean_html';
		if ( isset( $input[ $field ] ) ) {
			if ( ! is_string( $input[ $field ] ) ) { return new WP_Error( 'ax_op_local_text', 'Natural-language scalars must be strings.', array( 'status' => 400 ) ); }
			$input[ $field ] = $clean( $input[ $field ] );
		}
		if ( isset( $input[ $field . 'Map' ] ) ) {
			if ( ! is_array( $input[ $field . 'Map' ] ) || array_is_list( $input[ $field . 'Map' ] ) ) { return new WP_Error( 'ax_op_local_map', 'Language maps must be keyed objects.', array( 'status' => 400 ) ); }
			foreach ( $input[ $field . 'Map' ] as $language => $text ) {
				if ( ! is_string( $text ) || ! preg_match( '/^[a-zA-Z]{2,8}(?:-[a-zA-Z0-9]{1,8})*$/D', (string) $language ) ) { return new WP_Error( 'ax_op_local_map', 'Invalid language map entry.', array( 'status' => 400 ) ); }
				$input[ $field . 'Map' ][ $language ] = $clean( $text );
			}
		}
	}
	foreach ( array( 'to', 'cc', 'bto', 'bcc', 'audience', 'inReplyTo', 'context' ) as $field ) {
		if ( ! isset( $input[ $field ] ) ) { continue; }
		$members = axismundi_as2_normalize_members( $input[ $field ] );
		if ( ! $members && ! axismundi_as2_members_explicitly_empty( $input[ $field ] ) ) { return new WP_Error( 'ax_op_local_reference', 'Malformed Object references.', array( 'status' => 400 ) ); }
	}
	if ( isset( $input['tag'] ) && ( ! is_array( $input['tag'] ) || ! array_is_list( $input['tag'] ) ) ) { return new WP_Error( 'ax_op_local_tags', 'Tags must be a list of descriptors.', array( 'status' => 400 ) ); }
	// Unknown media ownership is not an implicit upload grant. URI descriptors are references only.
	if ( isset( $input['attachment'] ) && ( ! is_array( $input['attachment'] ) || ! array_is_list( $input['attachment'] ) ) ) { return new WP_Error( 'ax_op_local_attachments', 'Attachments must be descriptor lists.', array( 'status' => 400 ) ); }
	foreach ( $input['attachment'] ?? array() as $attachment ) {
		if ( ! is_array( $attachment ) || ! is_string( $attachment['url'] ?? null ) || '' === axismundi_op_relation_uri( $attachment['url'] ) ) { return new WP_Error( 'ax_op_local_attachment', 'Attachments require an explicit HTTP(S) descriptor URL.', array( 'status' => 400 ) ); }
		$id = attachment_url_to_postid( $attachment['url'] );
		if ( $id && ! user_can( $user_id, 'edit_post', $id ) ) { return new WP_Error( 'ax_op_local_attachment_permission', 'The principal cannot use this local media attachment.', array( 'status' => 403 ) ); }
	}
	if ( strlen( wp_json_encode( $input ) ?: '' ) > 900000 ) { return new WP_Error( 'ax_op_local_size', 'Object exceeds the local body limit.', array( 'status' => 413 ) ); }
	return $input;
}

/** SQL participant only: caller owns the transaction, schema and post-commit effects. */
function axismundi_op_write_local_object( array $document, string $uuid ) {
	global $wpdb;
	$uri = axismundi_op_local_object_uri( $uuid );
	$now = current_time( 'mysql', true );
	$json = wp_json_encode( $document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( ! is_string( $json ) || ( $document['id'] ?? '' ) !== $uri ) { return new WP_Error( 'ax_op_local_document', 'Invalid allocated Object document.' ); }
	$ok = $wpdb->insert( axismundi_op_local_objects_table(), array(
		'local_uuid' => $uuid, 'object_uri' => $uri, 'object_uri_hash' => hash( 'sha256', $uri ), 'object_type' => $document['type'],
		'attributed_to_uri' => $document['attributedTo'], 'attributed_to_uri_hash' => hash( 'sha256', $document['attributedTo'] ),
		'document_json' => $json, 'document_hash' => hash( 'sha256', $json ), 'publicly_readable' => (int) axismundi_act_audience_is_public( axismundi_act_audience( $document ) ),
		'published_at' => $now, 'created_at' => $now, 'updated_at' => $now,
	) );
	if ( false === $ok ) { return new WP_Error( 'ax_op_local_write', 'Local Object insert failed.' ); }
	$row = axismundi_op_get_local_object( $uri );
	$index = axismundi_op_register_object( array( 'canonical_uri' => $uri, 'origin' => 'local', 'source_owner' => 'axismundi-object-projections', 'source_kind' => 'local-object', 'source_ref' => (int) $row['id'], 'as2_type' => $document['type'], 'attributed_to_uri' => $document['attributedTo'] ) );
	if ( is_wp_error( $index ) ) { return $index; }
	return axismundi_op_project_local_object( $row ) ? $row : new WP_Error( 'ax_op_local_projection', 'Local Object projection failed.' );
}

/** All mandatory projections participate in the caller's transaction. */
function axismundi_op_project_local_object( array $row ) : bool {
	global $wpdb;
	$document = $row['document'];
	$uri = $row['object_uri'];
	$active = 'active' === $row['object_status'];
	$mentions = array();
	foreach ( $document['tag'] ?? array() as $tag ) {
		if ( is_array( $tag ) && 'Mention' === ( $tag['type'] ?? '' ) && is_string( $tag['href'] ?? null ) ) { $mentions[] = $tag['href']; }
	}
	if ( ! axismundi_op_replace_in_reply_to_relations( $uri, $active ? ( $document['inReplyTo'] ?? array() ) : array(), true, 'c2s', false )
		|| ! axismundi_op_replace_object_mentions( $uri, array( 'explicit' => $mentions ), false )
		|| ! axismundi_op_index_quote_relations( array( 'object_uri' => $uri, 'attributed_to_uri' => $row['attributed_to_uri'], 'payload' => $document, '_relation_observation_complete' => true ), false ) ) { return false; }
	if ( ! $active ) {
		foreach ( array( axismundi_op_tag_references_table(), axismundi_op_term_usage_table() ) as $table ) {
			if ( false === $wpdb->delete( $table, array( 'source_uri' => $uri, 'source_uri_hash' => hash( 'sha256', $uri ) ) ) ) { return false; }
		}
	}
	$tags = $active ? axismundi_op_replace_tag_references( $uri, $document['tag'] ?? array(), array( 'provider' => 'c2s', 'role' => 'content', 'complete' => true, 'feed_sort_at' => $row['published_at'] ), false ) : true;
	if ( is_wp_error( $tags ) ) { return false; }
	if ( function_exists( 'axismundi_media_relations_replace' ) ) {
		if ( ( new ReflectionFunction( 'axismundi_media_relations_replace' ) )->getNumberOfParameters() < 5 ) { return false; }
		$media = array();
		foreach ( $document['attachment'] ?? array() as $position => $descriptor ) {
			$media[] = array( 'predicate' => 'as:attachment', 'role' => 'content', 'object_uri' => $descriptor['url'], 'object_attachment_id' => attachment_url_to_postid( $descriptor['url'] ), 'source_key' => $row['local_uuid'] . ':' . $position );
		}
		$written = axismundi_media_relations_replace( array( 'uri' => $uri, 'type' => 'local_object' ), 'ax-op-local', $media, 'usage', false );
		if ( is_wp_error( $written ) ) { return false; }
	}
	$parent = $active ? ( axismundi_op_primary_in_reply_to_relation( $uri ) ?? '' ) : '';
	if ( $active && ! axismundi_op_record_thread_edge( $uri, $parent ) ) { return false; }
	return axismundi_op_write_object_listing_projection( $uri, array( 'payload' => $document, 'object_status' => $row['object_status'], 'listing_source' => 'local', 'listing_publicly_listable' => $active && ! empty( $row['publicly_readable'] ), 'listing_attributed_to_uri' => $row['attributed_to_uri'], 'listing_is_reply' => ! empty( $document['inReplyTo'] ) ) );
}

/** Tombstone current body using CAS, never rewrite Activity history or delete shared binaries. */
function axismundi_op_tombstone_local_object( array $row, string $actor_uri ) {
	global $wpdb;
	if ( ! hash_equals( $row['attributed_to_uri'], $actor_uri ) ) { return new WP_Error( 'ax_op_local_delete_actor', 'Only the recorded author may delete this Object.', array( 'status' => 403 ) ); }
	if ( 'tombstone' === $row['object_status'] ) { return true; }
	$now = current_time( 'mysql', true );
	$document = array( 'id' => $row['object_uri'], 'type' => 'Tombstone', 'formerType' => $row['object_type'], 'deleted' => gmdate( 'c', strtotime( $now . ' UTC' ) ) );
	$json = wp_json_encode( $document );
	$done = $wpdb->update( axismundi_op_local_objects_table(), array( 'object_type' => 'Tombstone', 'object_status' => 'tombstone', 'document_json' => $json, 'document_hash' => hash( 'sha256', $json ), 'version' => (int) $row['version'] + 1, 'updated_at' => $now, 'deleted_at' => $now ), array( 'id' => $row['id'], 'version' => $row['version'], 'object_status' => 'active' ) );
	if ( 1 !== $done ) { return new WP_Error( 'ax_op_local_version', 'Object version changed.', array( 'status' => 409 ) ); }
	$updated = axismundi_op_get_local_object( $row['object_uri'] );
	$index = axismundi_op_register_object( array( 'canonical_uri' => $row['object_uri'], 'origin' => 'local', 'source_owner' => 'axismundi-object-projections', 'source_kind' => 'local-object', 'source_ref' => (int) $row['id'], 'as2_type' => 'Tombstone', 'attributed_to_uri' => $row['attributed_to_uri'] ) );
	if ( is_wp_error( $index ) ) { return $index; }
	return axismundi_op_project_local_object( $updated ) ? true : new WP_Error( 'ax_op_local_delete_projection', 'Tombstone projection failed.' );
}

final class Axismundi_OP_Local_Source {
	public function __construct( public array $row ) {}
}

function axismundi_op_resolve_local_body( $source, string $uri ) {
	if ( null !== $source ) { return $source; }
	$row = axismundi_op_get_local_object( $uri );
	return $row ? new Axismundi_OP_Local_Source( $row ) : null;
}
add_filter( 'axismundi_op_resolve_source_by_uri', 'axismundi_op_resolve_local_body', 5, 2 );

function axismundi_op_register_local_body_readers() : void {
	axismundi_op_register_object_transformer( 'local-body', array(
		'supports' => static fn( $source ) : bool => $source instanceof Axismundi_OP_Local_Source,
		'object_uri' => static fn( $source ) : string => $source->row['object_uri'],
		'transform' => static fn( $source ) : array => $source->row['document'],
		'visible' => static fn( $source ) : bool => ! empty( $source->row['publicly_readable'] ), 'local' => true, 'priority' => 5,
	) );
}
add_action( 'axismundi_op_register_transformers', 'axismundi_op_register_local_body_readers' );

function axismundi_op_local_body_view( $source ) : ?array {
	if ( ! $source instanceof Axismundi_OP_Local_Source ) { return null; }
	$row = $source->row;
	$payload = $row['document'];
	// Reuse the established document-to-view normalization, not the remote cache storage.
	$row['payload'] = $payload;
	$row['is_sensitive'] = ! empty( $payload['sensitive'] );
	$row['remote_updated_at'] = $row['updated_at'];
	$row['name'] = ''; $row['content'] = ''; $row['summary'] = '';
	return axismundi_op_remote_source_view_model( new Axismundi_Op_Remote_Source( $row ) );
}
add_action( 'init', static function () : void {
	axismundi_op_register_object_source_reader( 'local-body', array(
		'supports' => static fn( $source ) : bool => $source instanceof Axismundi_OP_Local_Source, 'read' => 'axismundi_op_local_body_view',
		'bindings' => array( array( 'owner' => 'axismundi-object-projections', 'kind' => 'local-object' ) ),
		'load' => static function ( $object ) {
			$row = axismundi_op_get_local_object( $object['canonical_uri'] );
			return $row && (int) $row['id'] === (int) $object['source_ref'] ? new Axismundi_OP_Local_Source( $row ) : null;
		},
		'document' => 'axismundi_op_read_local_jsonld_source', 'priority' => 5,
	) );
} );
