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
	// The option survives deactivating Object Projections, so it does not say the store's
	// functions are loaded. Measured 2026-11-14: option '1', every axismundi_op_local_*
	// undefined. Everything below calls them, so both conditions are one unavailability.
	if ( '1' !== get_option( 'ax_op_local_objects_schema', '' ) || ! function_exists( 'axismundi_op_local_update_types' ) ) { return new WP_Error( 'ax_c2s_store_unavailable', 'Local storage is unavailable.', array( 'status' => 503 ) ); }
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
		// Same policy as before; the refusal now names what it refused. A composer cannot place a
		// blanket error on an input, and cannot tell a property the server owns from one the author
		// misspelled. Nothing here widens what is accepted.
		//
		// Keys are qualified because an Update carries two documents: `to` on the Object and `to`
		// on the Activity are different inputs. Unqualified, a composer cannot tell which field to
		// mark, and when both are present one refusal silently overwrites the other.
		$properties = array();
		$reasons = array();
		$owned = function_exists( 'axismundi_op_local_note_server_set' ) ? axismundi_op_local_note_server_set() : array();
		if ( ! is_array( $note ) ) {
			$reasons[] = 'object_required';
		} else {
			if ( ! is_string( $note['id'] ?? null ) || '' === $note['id'] ) { $properties['object.id'] = 'required'; }
			if ( array_key_exists( 'type', $note ) && ! in_array( $note['type'], axismundi_op_local_update_types(), true ) ) { $properties['object.type'] = 'unsupported'; }
			foreach ( array_diff( array_keys( $note ), array_merge( array( 'id', 'type' ), $editable ) ) as $field ) {
				if ( in_array( $field, $owned, true ) ) {
					$class = 'server_set';
				} elseif ( function_exists( 'axismundi_op_local_property_known' ) && axismundi_op_local_property_known( (string) $field ) ) {
					$class = 'not_updatable';
				} else {
					$class = 'unsupported_property';
				}
				$properties[ 'object.' . $field ] = $class;
			}
			if ( ! array_intersect( array_keys( $note ), $editable ) ) { $reasons[] = 'no_editable_properties'; }
		}
		foreach ( array_intersect( array_keys( $input ), array( 'to', 'cc', 'bto', 'bcc', 'audience' ) ) as $field ) {
			$properties[ 'activity.' . $field ] = 'not_updatable';
		}
		if ( $properties || $reasons ) {
			return new WP_Error( 'ax_c2s_update_patch', 'Update requires a local Object ID and editable fields only.', array( 'status' => 422, 'properties' => $properties, 'reasons' => $reasons ) );
		}
	} else {
		if ( ! is_string( $input['object'] ?? null ) ) { return new WP_Error( 'ax_c2s_delete_object', 'Delete requires a local Object URI.', array( 'status' => 400 ) ); }
		$delete_row = axismundi_op_get_local_object( $input['object'] );
		/*
		 * The target must be an Object. An Activity URI names a recorded event, and this
		 * ledger only grows: a `Delete` naming a `Delete` could add an activity but never
		 * remove one, and the recorded event would not become a `Tombstone` either. The verb
		 * for withdrawing the effect of a recorded activity is `Undo`, which is itself
		 * recorded. Saying so is a different refusal from saying the Actor lacks authority,
		 * which is what this used to answer for any URI that was not a local Object.
		 */
		if ( ! $delete_row ) {
			return new WP_Error( 'ax_c2s_delete_target', 'Delete requires a local Object URI; the effect of a recorded Activity is withdrawn with Undo.', array( 'status' => 422 ) );
		}
		if ( $delete_row['attributed_to_uri'] !== $actor->get_uri() ) {
			return new WP_Error( 'ax_c2s_delete_actor', 'The Actor cannot delete this Object.', array( 'status' => 403 ) );
		}
		/*
		 * Update already requires an active Object; Delete did not, so a second Delete
		 * recorded a second activity for an Object that was already a Tombstone (measured
		 * 2026-11-14). FEP-c3ab rule 7 is what this should do -- return the activity the
		 * earlier submission recorded, "without recording or delivering another" -- and rule 7
		 * also forbids answering one Actor's submission with another Actor's activity, so the
		 * prior Delete is matched on its actor. A Tombstone with no Delete of its own is a
		 * different state: the withdrawal happened outside this ledger, and recording one now
		 * would announce a past event as a present one.
		 */
		if ( 'active' !== $delete_row['object_status'] ) {
			$prior = axismundi_act_get_object_lifecycle( $delete_row['object_uri'] );
			if ( $prior instanceof Axismundi_Activity && 'Delete' === $prior->get_type()
				&& hash_equals( $prior->get_actor_uri(), $actor->get_uri() ) ) {
				return $prior;
			}
			return new WP_Error( 'ax_c2s_delete_state', 'Only an active Object may be deleted.', array( 'status' => 409 ) );
		}
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
			if ( 'active' !== $update_row['object_status'] || ! in_array( $update_row['object_type'], axismundi_op_local_update_types(), true )
				|| ( isset( $note['type'] ) && $note['type'] !== $update_row['object_type'] ) ) {
				return new WP_Error( 'ax_c2s_update_state', 'Update requires an active Object of the same type.', array( 'status' => 409 ) );
			}
			$document = $update_row['document'];
			$patch = array_diff_key( $note, array( 'id' => true, 'type' => true ) );
			$clean = axismundi_op_prepare_local_document( array( 'type' => $update_row['object_type'] ) + $patch, $actor, array( $update_row['object_type'] ) );
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
