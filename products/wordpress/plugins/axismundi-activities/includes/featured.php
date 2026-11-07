<?php
/** Local Person curation: Activity and Actors-owned membership commit together. */
defined( 'ABSPATH' ) || exit;

function axismundi_act_submit_featured( int $user, Axismundi_Actor $actor, array $input, string $key = '' ) {
	$permission = axismundi_act_outbox_authorize( $user, $actor );
	if ( is_wp_error( $permission ) ) { return $permission; }
	$type = axismundi_act_type( $input['type'] ?? '' );
	if ( 'Person' !== $actor->get_type() || ! in_array( $type, array( 'Add', 'Remove' ), true )
		|| ( $input['actor'] ?? '' ) !== $actor->get_uri() || axismundi_actors_collection_target_uri( $input['target'] ?? '' ) !== axismundi_actors_featured_uri( $actor ) ) {
		return new WP_Error( 'ax_c2s_featured_target', 'Only this Person\'s featured collection may be curated.', array( 'status' => 403 ) );
	}
	$uri = $input['object'] ?? null;
	if ( ! is_string( $uri ) || '' === axismundi_act_uri( $uri ) || array_diff( array_keys( $input ), array( '@context', 'id', 'type', 'actor', 'object', 'target' ) ) ) {
		return new WP_Error( 'ax_c2s_featured_body', 'Featured requires a plain Add/Remove with a URI Object.', array( 'status' => 400 ) );
	}
	if ( '' !== $key && ! preg_match( '/^[\x21-\x7e]{1,128}$/D', $key ) ) { return new WP_Error( 'ax_c2s_retry_key', 'Invalid Idempotency-Key.', array( 'status' => 400 ) ); }
	return axismundi_act_submission_lock( array( 'source_owner' => 'axismundi-activities', 'source_kind' => 'outbox', 'source_ref' => $actor->get_identity_id() ), static function () use ( $user, $actor, $type, $uri, $key ) {
		global $wpdb;
		$payload = array( 'type' => $type, 'actor' => $actor->get_uri(), 'object' => $uri, 'target' => axismundi_actors_featured_uri( $actor ), 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ) );
		$source = '' === $key ? '' : 'ap-c2s:' . $actor->get_identity_id() . ':' . $user . ':' . hash( 'sha256', $key );
		if ( '' !== $source ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE actor_uri_hash=%s AND (source_event_key=%s OR source_event_key LIKE %s)', axismundi_act_activities_table(), hash( 'sha256', $actor->get_uri() ), $source, $wpdb->esc_like( $source . ':' ) . '%' ), ARRAY_A );
			if ( $row ) {
				$old = axismundi_act_hydrate( $row );
				return axismundi_act_outbox_same_intent( $old->get_payload(), $payload ) ? $old : new WP_Error( 'ax_c2s_retry_conflict', 'Idempotency-Key already identifies different input.', array( 'status' => 409 ) );
			}
		}
		return axismundi_act_record_source_activity( $payload, 'outbound', $source, array(), static function () use ( $user, $actor, $uri, $type ) {
			$permission = axismundi_act_outbox_authorize( $user, $actor );
			return is_wp_error( $permission ) ? $permission : axismundi_actors_write_featured( $actor, $uri, 'Add' === $type, $user );
		} );
	} );
}
