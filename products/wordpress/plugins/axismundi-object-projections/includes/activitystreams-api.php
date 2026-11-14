<?php
/** Local document editing. This surface does not submit Activities. */
defined( 'ABSPATH' ) || exit;

function axismundi_op_activitystreams_permission( WP_REST_Request $request ) {
	if ( ! get_current_user_id() ) {
		return new WP_Error( 'ax_op_edit_auth', 'Authentication is required.', array( 'status' => 401 ) );
	}
	if ( ! rest_get_authenticated_app_password() && ! wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
		return new WP_Error( 'ax_op_edit_nonce', 'Cookie authentication requires a REST nonce.', array( 'status' => 403 ) );
	}
	return true;
}

function axismundi_op_activitystreams_fields( string $type, array $extra = array() ) : array {
	$snapshot = axismundi_op_vocabulary_snapshot();
	$standard = is_array( $snapshot ) ? array_keys( $snapshot['properties'] ) : array();
	$fields = array_unique( array_merge( $standard, axismundi_op_effective_properties( $type ),
		axismundi_op_local_note_server_set(), axismundi_op_local_editable_properties( $type, true ), $extra ) );
	$result = array();
	foreach ( $fields as $field ) {
		$term = preg_replace( '/^(content|name|summary)Map$/D', '$1', $field );
		$result[ $field ] = array(
			'class' => axismundi_op_local_property_class( $type, $field, true ),
			'value_shape' => axismundi_op_vocabulary_property( $term )['value_shape'] ?? null,
		);
	}
	return $result;
}

function axismundi_op_activitystreams_response( array $data ) : WP_REST_Response {
	return new WP_REST_Response( $data, 200, array( 'Cache-Control' => 'private, no-store' ) );
}

function axismundi_op_activitystreams_types() : WP_REST_Response {
	$types = array();
	foreach ( axismundi_op_local_update_types() as $type ) {
		$types[ $type ] = array( 'properties' => axismundi_op_activitystreams_fields( $type ) );
	}
	return axismundi_op_activitystreams_response( array( 'types' => $types ) );
}

function axismundi_op_activitystreams_local_object( WP_REST_Request $request ) {
	$user = get_current_user_id();
	$actor_uuid = $request->get_query_params()['actor'] ?? null;
	if ( ! is_string( $actor_uuid ) || ! wp_is_uuid( $actor_uuid ) ) {
		return new WP_Error( 'ax_op_edit_actor_required', 'Specify the acting Actor UUID in the actor query parameter.', array( 'status' => 400 ) );
	}
	$actor = function_exists( 'axismundi_actors_get_by_uuid' ) ? axismundi_actors_get_by_uuid( $actor_uuid ) : null;
	if ( ! $user || ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || ! axismundi_actors_can_act_as( $actor, $user ) ) {
		return new WP_Error( 'ax_op_edit_forbidden', 'The principal cannot act as this Actor.', array( 'status' => 403 ) );
	}
	if ( ! function_exists( 'axismundi_act_submission_lock' ) || ! function_exists( 'axismundi_act_first_outbound_create_row' )
		|| ! function_exists( 'axismundi_act_has_outbound_delete' ) || '1' !== get_option( 'ax_op_local_objects_schema', '' ) ) {
		return new WP_Error( 'ax_op_edit_unavailable', 'Local editing or publication history is unavailable.', array( 'status' => 503 ) );
	}
	$uri = axismundi_op_local_object_uri( $request->get_url_params()['uuid'] );
	$write = 'PATCH' === $request->get_method();
	if ( $write && strlen( $request->get_body() ) > 1024 * 1024 ) {
		return new WP_Error( 'ax_op_local_size', 'Object patch exceeds the body limit.', array( 'status' => 413 ) );
	}
	$input = $write ? $request->get_json_params() : array();
	if ( $write && ( ! is_int( $input['version'] ?? null ) || $input['version'] < 1
		|| ! is_array( $input['object'] ?? null ) || array_is_list( $input['object'] ) ) ) {
		return new WP_Error( 'ax_op_edit_patch', 'Supply a version and a non-empty object patch.', array( 'status' => 400 ) );
	}
	$work = static function () use ( $uri, $actor, $user, $write, $input ) {
		if ( ! axismundi_actors_can_act_as( $actor, $user ) ) {
			return new WP_Error( 'ax_op_edit_forbidden', 'The principal cannot act as this Actor.', array( 'status' => 403 ) );
		}
		$row = axismundi_op_get_local_object( $uri );
		if ( ! $row ) { return new WP_Error( 'ax_op_edit_missing', 'Local Object not found.', array( 'status' => 404 ) ); }
		if ( $row['attributed_to_uri'] !== $actor->get_uri() ) {
			return new WP_Error( 'ax_op_edit_forbidden', 'Only the attributed Actor may edit this Object.', array( 'status' => 403 ) );
		}
		$first = axismundi_act_first_outbound_create_row( $uri );
		$deleted = axismundi_act_has_outbound_delete( $uri );
		if ( null === $first || null === $deleted ) {
			return new WP_Error( 'ax_op_edit_unavailable', 'Publication history is unavailable.', array( 'status' => 503 ) );
		}
		if ( $deleted || 'active' !== $row['object_status'] ) {
			return new WP_Error( 'ax_op_edit_inactive', 'Only active Objects may be edited.', array( 'status' => 409 ) );
		}
		if ( $first ) {
			return new WP_Error( 'ax_op_edit_published', 'This Object is published. Submit Update to the Actor outbox.', array( 'status' => 409 ) );
		}
		$type = $row['object_type'];
		$document = $row['document'];
		if ( ! in_array( $type, axismundi_op_local_update_types(), true ) || ! is_array( $document ) ) {
			return new WP_Error( 'ax_op_edit_type', 'This Object cannot be edited here.', array( 'status' => 422 ) );
		}
		$version = (int) $row['version'];
		if ( $write ) {
			if ( $input['version'] !== $version ) {
				return new WP_Error( 'ax_op_local_version', 'Object version changed.', array( 'status' => 409 ) );
			}
			$properties = array();
			foreach ( $input['object'] as $field => $value ) {
				$class = axismundi_op_local_property_class( $type, (string) $field, true );
				if ( 'editable' !== $class ) { $properties[ 'object.' . $field ] = $class; }
			}
			if ( $properties ) {
				return new WP_Error( 'ax_op_edit_properties', 'Only editable Object fields may be patched.', array( 'status' => 422, 'properties' => $properties ) );
			}
			$clean = axismundi_op_prepare_local_document( array( 'type' => $type ) + $input['object'], $actor, array( $type ) );
			if ( is_wp_error( $clean ) ) { return $clean; }
			foreach ( $input['object'] as $field => $value ) {
				if ( null === $value ) { unset( $document[ $field ] ); }
				else { $document[ $field ] = $clean[ $field ]; }
			}
			$document['updated'] = gmdate( 'c' );
			global $wpdb;
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				return new WP_Error( 'ax_op_edit_unavailable', 'The Object transaction is unavailable.', array( 'status' => 503 ) );
			}
			$committed = false;
			$terms = array();
			$remember = static function ( $id, $tt_id, $taxonomy ) use ( &$terms ): void { $terms[ $taxonomy ][] = (int) $id; };
			add_action( 'created_term', $remember, 999, 3 );
			try {
				$saved = axismundi_op_update_local_object( $row, $document );
				if ( is_wp_error( $saved ) ) { return $saved; }
				$committed = false !== $wpdb->query( 'COMMIT' );
				if ( ! $committed ) {
					return new WP_Error( 'ax_op_local_write', 'The Object transaction could not commit.', array( 'status' => 500 ) );
				}
			} finally {
				remove_action( 'created_term', $remember, 999 );
				if ( ! $committed ) {
					$wpdb->query( 'ROLLBACK' );
					foreach ( $terms as $taxonomy => $ids ) { clean_term_cache( $ids, $taxonomy ); }
				}
			}
			++$version;
		}
		return axismundi_op_activitystreams_response( array( 'object' => $document, 'version' => $version,
			'properties' => axismundi_op_activitystreams_fields( $type, array_keys( $document ) ) ) );
	};
	$result = $write ? axismundi_act_submission_lock( array( 'source_owner' => 'axismundi-activities',
		'source_kind' => 'outbox', 'source_ref' => $actor->get_identity_id() ), $work ) : $work();
	if ( is_wp_error( $result ) && 'ax_act_pending_lock' === $result->get_error_code() ) {
		return new WP_Error( 'ax_op_edit_unavailable', 'The publication lock is unavailable. Retry the request.', array( 'status' => 503 ) );
	}
	return $result;
}

function axismundi_op_register_activitystreams_routes() : void {
	register_rest_route( 'axismundi/activitystreams', '/local-objects/types', array(
		'methods' => WP_REST_Server::READABLE, 'callback' => 'axismundi_op_activitystreams_types',
		'permission_callback' => 'axismundi_op_activitystreams_permission',
	) );
	register_rest_route( 'axismundi/activitystreams', '/local-objects/(?P<uuid>[0-9a-f-]{36})', array(
		'methods' => array( 'GET', 'PATCH' ), 'callback' => 'axismundi_op_activitystreams_local_object',
		'permission_callback' => 'axismundi_op_activitystreams_permission',
	) );
}
add_action( 'rest_api_init', 'axismundi_op_register_activitystreams_routes' );
