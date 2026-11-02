<?php
/** Community curation commands: Person Add/Remove plus the Group's independent Announce. */
defined( 'ABSPATH' ) || exit;

/** Announce only a control backed by the committed Actors-owned moderation log. */
function axismundi_act_announce_moderator_control( Axismundi_Actor $group, Axismundi_Activity $control ) {
	global $wpdb;
	if ( ! axismundi_actors_is_community( $group ) || 'public' !== $group->get_status() || ! $group->is_handle_locked()
		|| ! in_array( $control->get_type(), array( 'Add', 'Remove' ), true )
		|| $control->get_target_uri() !== axismundi_actors_moderators_uri( $group )
		|| ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE group_id=%d AND activity_uri=%s', axismundi_actors_moderators_log_table(), $group->get_identity_id(), $control->get_uri() ) ) ) { return new WP_Error( 'ax_mod_announce', 'Only a committed Community moderation decision can be announced.' ); }
	return axismundi_act_record_source_activity( array( 'type' => 'Announce', 'actor' => $group->get_uri(), 'object' => $control->get_payload(), 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ), 'cc' => array( axismundi_op_actor_followers_url( $group ) ) ), 'outbound', 'community-moderators:' . $group->get_identity_id() . ':' . $control->get_uri() );
}

function axismundi_act_submit_moderators( int $user, Axismundi_Actor $actor, Axismundi_Actor $group, array $input, string $retry_key = '', bool $bootstrap = false ) {
	$permission = axismundi_act_outbox_authorize( $user, $actor );
	if ( is_wp_error( $permission ) ) { return $permission; }
	if ( ! axismundi_actors_is_community( $group ) || ! $group->is_handle_locked() || 'Person' !== $actor->get_type() || $actor->get_uri() !== ( $input['actor'] ?? '' ) || axismundi_actors_moderators_uri( $group ) !== ( $input['target'] ?? '' ) || ! in_array( $input['type'] ?? '', array( 'Add', 'Remove' ), true ) ) { return new WP_Error( 'ax_mod_target', 'A Person must address a local Community moderator collection.', array( 'status' => 403 ) ); }
	if ( array_diff( array_keys( $input ), array( 'type', 'actor', 'object', 'target', 'audience', '@context' ) ) || ! is_string( $input['object'] ?? null ) || ( isset( $input['audience'] ) && $input['audience'] !== $group->get_uri() ) || ( '' !== $retry_key && ! preg_match( '/^[\x21-\x7e]{1,128}$/D', $retry_key ) ) ) { return new WP_Error( 'ax_mod_input', 'Invalid moderator command or Idempotency-Key.', array( 'status' => 400 ) ); }
	$member = axismundi_actors_get_by_uri( $input['object'] );
	if ( ! $member ) { $member = axismundi_actors_discover_remote_actor_uri( $input['object'] ); }
	if ( ! $member instanceof Axismundi_Actor || 'Person' !== $member->get_type() ) { return new WP_Error( 'ax_mod_member', 'The member must resolve to a Person.' ); }
	$result = axismundi_act_submission_lock( array( 'source_owner' => 'axismundi-activities', 'source_kind' => 'outbox', 'source_ref' => $actor->get_identity_id() ), static function () use ( $user, $actor, $group, $member, $input, $retry_key, $bootstrap ) {
		global $wpdb;
		$payload = array( 'type' => $input['type'], 'actor' => $actor->get_uri(), 'object' => $member->get_uri(), 'target' => axismundi_actors_moderators_uri( $group ), 'audience' => $group->get_uri(), 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ), 'cc' => array( $group->get_uri() ) );
		$source = $bootstrap ? 'moderators-initialize:' . $group->get_identity_id() : ( '' === $retry_key ? '' : 'ap-c2s:' . $actor->get_identity_id() . ':' . $user . ':' . hash( 'sha256', $retry_key ) );
		if ( '' !== $source ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE actor_uri_hash=%s AND (source_event_key=%s OR source_event_key LIKE %s)', axismundi_act_activities_table(), hash( 'sha256', $actor->get_uri() ), $source, $wpdb->esc_like( $source . ':' ) . '%' ), ARRAY_A );
			if ( $wpdb->last_error ) { return new WP_Error( 'ax_mod_read', 'Could not read moderation retry state.' ); }
			if ( $row ) { $old = axismundi_act_hydrate( $row ); return axismundi_act_outbox_same_intent( $old->get_payload(), $payload ) ? $old : new WP_Error( 'ax_c2s_retry_conflict', 'Idempotency-Key identifies a different command.', array( 'status' => 409 ) ); }
		}
		return axismundi_act_record_source_activity( $payload, 'outbound', $source, array(), static function ( array $normalized ) use ( $user, $actor, $group, $member, $input, $bootstrap ) {
			$fresh = axismundi_actors_get_by_identity( $group->get_identity_id() );
			if ( ! $fresh ) { return new WP_Error( 'ax_mod_missing', 'The Community is no longer available.' ); }
			$permission = axismundi_act_outbox_authorize( $user, $actor );
			return is_wp_error( $permission ) ? $permission : axismundi_actors_write_moderators( $fresh, $actor, $member, 'Add' === $input['type'], hash( 'sha256', $normalized['activity_uri'] ), $normalized['activity_uri'], $bootstrap, $user );
		} );
	} );
	if ( is_wp_error( $result ) ) { return $result; }
	$announced = axismundi_act_announce_moderator_control( $group, $result );
	return is_wp_error( $announced ) ? $announced : $result;
}

function axismundi_act_initialize_moderators( Axismundi_Actor $group, Axismundi_Actor $person, int $user ) {
	return axismundi_act_submit_moderators( $user, $person, $group, array( 'type' => 'Add', 'actor' => $person->get_uri(), 'object' => $person->get_uri(), 'target' => axismundi_actors_moderators_uri( $group ) ), '', true );
}

add_action( 'rest_api_init', static function () : void {
	register_rest_route( 'axismundi/v1', '/actors/(?P<uuid>[a-f0-9-]{36})/moderators/initialize', array(
		'methods' => 'POST', 'permission_callback' => 'axismundi_act_outbox_permission',
		'callback' => static function ( WP_REST_Request $request ) {
			$group = axismundi_actors_get_by_uuid( $request->get_url_params()['uuid'] );
			$body = $request->get_json_params();
			$person = is_array( $body ) && array_keys( $body ) === array( 'person' ) && is_string( $body['person'] ) ? axismundi_actors_get_by_uri( $body['person'] ) : null;
			if ( ! $group || ! $person ) { return new WP_Error( 'ax_mod_input', 'Supply an explicit cached Person URI.', array( 'status' => 400 ) ); }
			$result = axismundi_act_initialize_moderators( $group, $person, get_current_user_id() );
			return is_wp_error( $result ) ? $result : new WP_REST_Response( $result->get_payload(), 201, array( 'Location' => $result->get_uri(), 'Cache-Control' => 'no-store' ) );
		},
	) );
} );
