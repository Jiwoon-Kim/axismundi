<?php
/**
 * Phase 2a folder REST API (namespace axismundi-media/v1). Thin wrappers over the
 * folders.php service; the service enforces ownership and — for moves — per-
 * attachment `edit_post`. Registered only in Independent mode.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_MEDIA_REST_NS = 'axismundi-media/v1';

/**
 * Base permission: a logged-in user who can upload files. Fine-grained folder
 * ownership and per-attachment rights are checked in the service layer.
 *
 * @return bool
 */
function axismundi_media_rest_can_edit() : bool {
	return is_user_logged_in() && current_user_can( 'upload_files' );
}

/**
 * Register the folder routes (Independent mode only).
 *
 * @return void
 */
function axismundi_media_register_folder_routes() : void {
	if ( ! axismundi_media_is_independent() ) {
		return;
	}

	register_rest_route(
		AXISMUNDI_MEDIA_REST_NS,
		'/folders',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'axismundi_media_rest_list_folders',
				'permission_callback' => 'axismundi_media_rest_can_edit',
				'args'                => array(
					'owner' => array(
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'axismundi_media_rest_create_folder',
				'permission_callback' => 'axismundi_media_rest_can_edit',
				'args'                => array(
					'name'   => array(
						'type'     => 'string',
						'required' => true,
					),
					'parent' => array(
						'type'              => 'integer',
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			),
		)
	);

	register_rest_route(
		AXISMUNDI_MEDIA_REST_NS,
		'/folders/(?P<id>\d+)',
		array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => 'axismundi_media_rest_rename_folder',
				'permission_callback' => 'axismundi_media_rest_can_edit',
				'args'                => array(
					'name' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'axismundi_media_rest_delete_folder',
				'permission_callback' => 'axismundi_media_rest_can_edit',
			),
		)
	);

	register_rest_route(
		AXISMUNDI_MEDIA_REST_NS,
		'/folders/move',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'axismundi_media_rest_move_attachments',
			'permission_callback' => 'axismundi_media_rest_can_edit',
			'args'                => array(
				'attachments' => array(
					'type'     => 'array',
					'required' => true,
					'items'    => array( 'type' => 'integer' ),
				),
				'folder'      => array(
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_media_register_folder_routes' );

/**
 * GET /folders — the requested owner's folder tree (self by default; another user
 * only with edit_others_posts).
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function axismundi_media_rest_list_folders( WP_REST_Request $request ) : WP_REST_Response {
	$owner = (int) $request->get_param( 'owner' );
	if ( $owner <= 0 || ( $owner !== get_current_user_id() && ! current_user_can( 'edit_others_posts' ) ) ) {
		$owner = get_current_user_id();
	}
	return new WP_REST_Response( axismundi_media_user_folders( $owner ), 200 );
}

/**
 * POST /folders — create a folder owned by the current user.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_media_rest_create_folder( WP_REST_Request $request ) {
	$term = axismundi_media_create_folder(
		(string) $request->get_param( 'name' ),
		(int) $request->get_param( 'parent' )
	);
	if ( is_wp_error( $term ) ) {
		return $term;
	}
	return new WP_REST_Response( array( 'id' => $term ), 201 );
}

/**
 * POST /folders/{id} — rename.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_media_rest_rename_folder( WP_REST_Request $request ) {
	$res = axismundi_media_rename_folder(
		(int) $request['id'],
		(string) $request->get_param( 'name' )
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return new WP_REST_Response( array( 'id' => $res ), 200 );
}

/**
 * DELETE /folders/{id} — delete; contents move to the root (never deleted).
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_media_rest_delete_folder( WP_REST_Request $request ) {
	$res = axismundi_media_delete_folder( (int) $request['id'] );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return new WP_REST_Response( array( 'deleted' => true ), 200 );
}

/**
 * POST /folders/move — move attachments into a folder (0 = unfiled). Each
 * attachment must pass `edit_post`; denied ones are reported, not silently skipped.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_media_rest_move_attachments( WP_REST_Request $request ) {
	$ids = array_map( 'absint', (array) $request->get_param( 'attachments' ) );
	$res = axismundi_media_move_attachments( $ids, (int) $request->get_param( 'folder' ) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$status = empty( $res['moved'] ) && ! empty( $res['denied'] ) ? 403 : 200;
	return new WP_REST_Response( $res, $status );
}
