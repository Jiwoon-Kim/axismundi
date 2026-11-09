<?php
/** Create(Note) and Delete(local Object), sharing the ledger transaction. */
defined( 'ABSPATH' ) || exit;

function axismundi_act_submit_local_object( int $user, Axismundi_Actor $actor, array $input, string $key = '' ) {
	$permission = axismundi_act_outbox_authorize( $user, $actor );
	if ( is_wp_error( $permission ) ) { return $permission; }
	$bare = 'Note' === ( $input['type'] ?? '' );
	$type = $bare ? 'Create' : ( $input['type'] ?? '' );
	if ( ! in_array( $type, array( 'Create', 'Update', 'Delete' ), true ) ) { return new WP_Error( 'ax_c2s_object_type', 'Unsupported Object command.', array( 'status' => 422 ) ); }
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
		$note = axismundi_act_prepare_community_submission( $note, $actor, $user );
		if ( is_wp_error( $note ) ) { return $note; }
	} elseif ( 'Update' === $type ) {
		$note = $input['object'] ?? null;
		$editable = array( 'content', 'contentMap', 'name', 'nameMap', 'summary', 'summaryMap', 'sensitive' );
		if ( ! is_array( $note ) || ! is_string( $note['id'] ?? null ) || '' === $note['id']
			|| ( array_key_exists( 'type', $note ) && 'Note' !== $note['type'] )
			|| array_diff( array_keys( $note ), array_merge( array( 'id', 'type' ), $editable ) )
			|| ! array_intersect( array_keys( $note ), $editable )
			|| array_intersect( array_keys( $input ), array( 'to', 'cc', 'bto', 'bcc', 'audience' ) ) ) {
			return new WP_Error( 'ax_c2s_update_patch', 'Update requires a Note ID and editable fields only.', array( 'status' => 422 ) );
		}
	} else {
		if ( ! is_string( $input['object'] ?? null ) ) { return new WP_Error( 'ax_c2s_delete_object', 'Delete requires a local Object URI.', array( 'status' => 400 ) ); }
		$delete_row = axismundi_op_get_local_object( $input['object'] );
		if ( ! $delete_row || $delete_row['attributed_to_uri'] !== $actor->get_uri() ) { return new WP_Error( 'ax_c2s_delete_actor', 'The Actor cannot delete this Object.', array( 'status' => 403 ) ); }
	}
	// A normalized semantic command is retained only as a hash, not another body/history store.
	$intent = array( 'type' => $type, 'actor' => $actor->get_uri(), 'object' => $note ?? $input['object'] );
	$sort = static function ( $value ) use ( &$sort ) { if ( is_array( $value ) ) { if ( ! array_is_list( $value ) ) { ksort( $value ); } $value = array_map( $sort, $value ); } return $value; };
	$intent_hash = hash( 'sha256', wp_json_encode( $sort( $intent ) ) );
	$result = axismundi_act_submission_lock( array( 'source_owner' => 'axismundi-activities', 'source_kind' => 'outbox', 'source_ref' => $actor->get_identity_id() ), static function () use ( $user, $actor, $type, $note, $delete_row, $key, $intent_hash ) {
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
		$update_row = null;
		if ( 'Update' === $type ) {
			$uri = $note['id'];
			$update_row = axismundi_op_get_local_object( $uri );
			if ( ! $update_row || $update_row['attributed_to_uri'] !== $actor->get_uri() ) {
				return new WP_Error( 'ax_c2s_update_actor', 'Only the recorded author may update this Object.', array( 'status' => 403 ) );
			}
			if ( 'active' !== $update_row['object_status'] || 'Note' !== $update_row['object_type'] ) {
				return new WP_Error( 'ax_c2s_update_state', 'Only active Notes may be updated.', array( 'status' => 409 ) );
			}
			$document = $update_row['document'];
			$patch = array_diff_key( $note, array( 'id' => true, 'type' => true ) );
			$clean = axismundi_op_prepare_local_note( array( 'type' => 'Note' ) + $patch, $actor, $user );
			if ( is_wp_error( $clean ) ) { return $clean; }
			foreach ( $patch as $field => $value ) {
				if ( null === $value ) { unset( $document[ $field ] ); }
				else { $document[ $field ] = $clean[ $field ]; }
			}
			$document['updated'] = gmdate( 'c' );
		}
		$activity = array( 'type' => $type, 'actor' => $actor->get_uri(), 'object' => $uri );
		if ( 'Create' === $type ) {
			$document['id'] = $uri;
			$document['url'] = $uri;
			$document['published'] = gmdate( 'c' );
			foreach ( array( 'to', 'cc', 'bto', 'bcc', 'audience' ) as $field ) { if ( isset( $document[ $field ] ) ) { $activity[ $field ] = $document[ $field ]; } }
			// Group forwarding must preserve a creation snapshot, including after local deletion.
			$group = ! empty( $document['audience'] ) ? axismundi_actors_get_by_uri( axismundi_act_member_uri( $document['audience'] ) ) : null;
			if ( $group && 'Group' === $group->get_type() ) { $activity['object'] = $document; }
		} elseif ( 'Update' === $type ) {
			foreach ( array( 'to', 'cc', 'bto', 'bcc', 'audience' ) as $field ) { if ( isset( $document[ $field ] ) ) { $activity[ $field ] = $document[ $field ]; } }
		} else {
			foreach ( array( 'to', 'cc', 'bto', 'bcc', 'audience' ) as $field ) { if ( isset( $delete_row['document'][ $field ] ) ) { $activity[ $field ] = $delete_row['document'][ $field ]; } }
		}
		$participant = static function () use ( $type, $document, $uuid, $uri, $actor, $update_row ) {
			try {
				if ( 'Create' === $type ) {
					$result = axismundi_op_write_local_object( $document, $uuid );
				} elseif ( 'Update' === $type ) {
					$result = axismundi_op_update_local_object( $update_row, $document );
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
	return is_wp_error( $result ) ? $result : axismundi_act_receive_community_submission( $result );
}
