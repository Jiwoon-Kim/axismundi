<?php
/** Create(Note) and Delete(local Object), sharing the ledger transaction. */
defined( 'ABSPATH' ) || exit;

function axismundi_act_submit_local_object( int $user, Axismundi_Actor $actor, array $input, string $key = '' ) {
	$permission = axismundi_act_outbox_authorize( $user, $actor );
	if ( is_wp_error( $permission ) ) { return $permission; }
	$bare = 'Note' === ( $input['type'] ?? '' );
	$type = $bare ? 'Create' : ( $input['type'] ?? '' );
	if ( ! in_array( $type, array( 'Create', 'Delete' ), true ) ) { return new WP_Error( 'ax_c2s_object_type', 'Unsupported Object command.', array( 'status' => 422 ) ); }
	if ( ! $bare && ( $input['actor'] ?? '' ) !== $actor->get_uri() ) { return new WP_Error( 'ax_c2s_actor_mismatch', 'Actor must match the outbox.', array( 'status' => 403 ) ); }
	if ( '' !== $key && ! preg_match( '/^[\x21-\x7e]{1,128}$/D', $key ) ) { return new WP_Error( 'ax_c2s_retry_key', 'Invalid Idempotency-Key.', array( 'status' => 400 ) ); }
	if ( '1' !== get_option( 'ax_op_local_objects_schema', '' ) ) { return new WP_Error( 'ax_c2s_store_unavailable', 'Local storage is unavailable.', array( 'status' => 503 ) ); }
	$note = null;
	$delete_row = null;
	if ( 'Create' === $type ) {
		$note = $bare ? $input : ( $input['object'] ?? null );
		if ( ! is_array( $note ) || array_is_list( $note ) ) { return new WP_Error( 'ax_c2s_embedded', 'Create requires an embedded Note.', array( 'status' => 400 ) ); }
		foreach ( array( 'to', 'cc', 'bto', 'bcc', 'audience' ) as $field ) {
			if ( ! $bare && isset( $input[ $field ], $note[ $field ] ) && $input[ $field ] !== $note[ $field ] ) { return new WP_Error( 'ax_c2s_audience_conflict', 'Create and Note addressing must agree.', array( 'status' => 400 ) ); }
			if ( isset( $input[ $field ] ) && ! isset( $note[ $field ] ) ) { $note[ $field ] = $input[ $field ]; }
		}
		$note = axismundi_op_prepare_local_note( $note, $actor, $user );
		if ( is_wp_error( $note ) ) { return $note; }
	} else {
		if ( ! is_string( $input['object'] ?? null ) ) { return new WP_Error( 'ax_c2s_delete_object', 'Delete requires a local Object URI.', array( 'status' => 400 ) ); }
		$delete_row = axismundi_op_get_local_object( $input['object'] );
		if ( ! $delete_row || $delete_row['attributed_to_uri'] !== $actor->get_uri() ) { return new WP_Error( 'ax_c2s_delete_actor', 'The Actor cannot delete this Object.', array( 'status' => 403 ) ); }
	}
	// A normalized semantic command is retained only as a hash, not another body/history store.
	$intent = array( 'type' => $type, 'actor' => $actor->get_uri(), 'object' => $note ?? $input['object'] );
	$sort = static function ( $value ) use ( &$sort ) { if ( is_array( $value ) ) { if ( ! array_is_list( $value ) ) { ksort( $value ); } $value = array_map( $sort, $value ); } return $value; };
	$intent_hash = hash( 'sha256', wp_json_encode( $sort( $intent ) ) );
	return axismundi_act_submission_lock( array( 'source_owner' => 'axismundi-activities', 'source_kind' => 'outbox', 'source_ref' => $actor->get_identity_id() ), static function () use ( $user, $actor, $type, $note, $delete_row, $key, $intent_hash ) {
		global $wpdb;
		$permission = axismundi_act_outbox_authorize( $user, $actor );
		if ( is_wp_error( $permission ) ) { return $permission; }
		$prefix = '' === $key ? '' : 'ap-c2s:' . $actor->get_identity_id() . ':' . $user . ':' . hash( 'sha256', $key );
		if ( '' !== $prefix ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE actor_uri_hash=%s AND (source_event_key=%s OR source_event_key LIKE %s)', axismundi_act_activities_table(), hash( 'sha256', $actor->get_uri() ), $prefix, $wpdb->esc_like( $prefix . ':' ) . '%' ), ARRAY_A );
			if ( $rows ) {
				$row = $rows[0];
				return $row['source_event_key'] === $prefix . ':' . $intent_hash ? axismundi_act_hydrate( $row ) : new WP_Error( 'ax_c2s_retry_conflict', 'Idempotency-Key already identifies different input.', array( 'status' => 409 ) );
			}
		}
		$source = '' === $prefix ? '' : $prefix . ':' . $intent_hash;
		$uuid = wp_generate_uuid4();
		$document = $note;
		$uri = $delete_row['object_uri'] ?? axismundi_op_local_object_uri( $uuid );
		$activity = array( 'type' => $type, 'actor' => $actor->get_uri(), 'object' => $uri );
		if ( 'Create' === $type ) {
			$document['id'] = $uri;
			$document['url'] = $uri;
			$document['published'] = gmdate( 'c' );
			foreach ( array( 'to', 'cc', 'bto', 'bcc', 'audience' ) as $field ) { if ( isset( $document[ $field ] ) ) { $activity[ $field ] = $document[ $field ]; } }
		} else {
			foreach ( array( 'to', 'cc', 'bto', 'bcc', 'audience' ) as $field ) { if ( isset( $delete_row['document'][ $field ] ) ) { $activity[ $field ] = $delete_row['document'][ $field ]; } }
		}
		$participant = static function () use ( $type, $document, $uuid, $uri, $actor ) {
			try {
				if ( 'Create' === $type ) {
					$result = axismundi_op_write_local_object( $document, $uuid );
				} else {
					$result = axismundi_op_tombstone_local_object( axismundi_op_get_local_object( $uri ), $actor->get_uri() );
				}
				return is_wp_error( $result ) ? $result : true;
			} catch ( Throwable $error ) {
				return new WP_Error( 'ax_c2s_object_participant', 'Object storage participant failed.', array( 'status' => 500 ) );
			}
		};
		// Match the existing local WP lifecycle convention: object URI, not a second authoritative body.
		$terms = array();
		$remember_term = static function ( $id, $tt_id, $taxonomy ) use ( &$terms ) { $terms[ $taxonomy ][] = (int) $id; };
		add_action( 'created_term', $remember_term, 999, 3 );
		try { $result = axismundi_act_record_source_activity( $activity, 'outbound', $source, array(), $participant ); }
		finally { remove_action( 'created_term', $remember_term, 999 ); }
		if ( is_wp_error( $result ) ) {
			foreach ( $terms as $taxonomy => $ids ) { clean_term_cache( $ids, $taxonomy ); }
		}
		return $result;
	} );
}
