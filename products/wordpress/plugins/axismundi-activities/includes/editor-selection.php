<?php
/** Editor intent and request selection, separate from committed Object attribution. @package AxismundiActivities */
defined( 'ABSPATH' ) || exit;

/** Resolve a new publication, or retain a committed Object's Actor with live permission checks. */
function axismundi_act_request_publish_actor( WP_Post $post, int $user_id ) {
	$uri = function_exists( 'axismundi_op_post_object_uri' ) ? axismundi_op_post_object_uri( $post ) : '';
	$attributed = '' !== $uri ? axismundi_act_get_object_attribution( $uri ) : '';
	if ( '' !== $attributed ) {
		$actor = axismundi_actors_get_by_uri( $attributed );
		return $actor instanceof Axismundi_Actor && axismundi_actors_can_act_as( $actor, $user_id )
			? $actor : new WP_Error( 'ax_act_c2s_permission', __( 'You cannot publish as this object\'s recorded Account.', 'axismundi-activities' ) );
	}
	if ( $user_id !== get_current_user_id() || $user_id <= 0 ) {
		return new WP_Error( 'ax_selection_user', __( 'An authenticated publisher is required.', 'axismundi-activities' ) );
	}
	$browser = array_key_exists( LOGGED_IN_COOKIE, $_COOKIE );
	if ( ! $browser ) {
		$result = axismundi_actors_resolve_account_selection( $user_id, null, 'publish', true, false );
		return is_wp_error( $result ) ? $result : $result['actor'];
	}
	$session = axismundi_actors_browser_session( $user_id );
	if ( is_wp_error( $session ) ) { return $session; }
	$result = axismundi_actors_resolve_account_selection( $user_id, null, '', true, true );
	if ( is_wp_error( $result ) ) { return $result; }
	$expected = $_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] ?? ( $_POST['ax_publish_account'] ?? null );
	if ( ! is_string( $expected ) || ! preg_match( '/\A[1-9][0-9]*\z/D', $expected )
		|| (string) (int) $expected !== $expected ) {
		return new WP_Error( 'ax_act_selection_expected', __( 'Reload the editor and choose an Account before federating this post.', 'axismundi-activities' ) );
	}
	if ( ! isset( $_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] ) ) {
		$nonce = $_POST['ax_publish_account_nonce'] ?? '';
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( wp_unslash( $nonce ), 'ax_publish_account_' . $post->ID . '_' . $expected ) ) {
			return new WP_Error( 'ax_act_selection_nonce', __( 'The publication selection has expired. Reload the editor.', 'axismundi-activities' ) );
		}
	}
	if ( (int) $expected !== $result['actor']->get_identity_id() ) {
		return new WP_Error( 'ax_act_selection_changed', __( 'The selected Account changed in another tab. Reload before federating.', 'axismundi-activities' ) );
	}
	return $result['actor'];
}

/** Classic editor pins the visible selection to this post and nonce. */
add_action( 'edit_form_top', static function ( WP_Post $post ) : void {
	if ( ! axismundi_actors_account_selection_enabled() || 'post' !== $post->post_type ) { return; }
	$result = axismundi_actors_current_workspace_result();
	$id = is_wp_error( $result ) ? 0 : $result['actor']->get_identity_id();
	echo '<input type="hidden" name="ax_publish_account" value="' . esc_attr( (string) $id ) . '">';
	wp_nonce_field( 'ax_publish_account_' . $post->ID . '_' . $id, 'ax_publish_account_nonce', false );
} );

/** Block editor freezes intent at load, not at each API request after a cross-tab switch. */
add_action( 'enqueue_block_editor_assets', static function () : void {
	if ( ! axismundi_actors_account_selection_enabled() ) { return; }
	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->post_type ) { return; }
	$result = axismundi_actors_current_workspace_result();
	$id = is_wp_error( $result ) ? 0 : $result['actor']->get_identity_id();
	wp_enqueue_script( 'axismundi-publish-selection', plugins_url( 'assets/publish-selection.js', dirname( __DIR__ ) . '/axismundi-activities.php' ), array( 'wp-api-fetch', 'wp-data', 'wp-notices', 'wp-i18n' ), AXISMUNDI_ACTIVITIES_VERSION, true );
	wp_add_inline_script( 'axismundi-publish-selection', 'window.axismundiPublishAccount = ' . wp_json_encode( (string) $id ) . ';', 'before' );
} );

/** Report a failed federation command without rejecting an already committed source save. */
add_filter( 'rest_prepare_post', static function ( $response, WP_Post $post, WP_REST_Request $request ) {
	if ( ! in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ) { return $response; }
	$key = 'ax_act_publish_error_' . get_current_user_id() . '_' . $post->ID;
	$message = get_transient( $key );
	if ( is_string( $message ) && '' !== $message ) {
		$data = $response->get_data();
		$data['axismundi_publication_error'] = $message;
		$response->set_data( $data );
		delete_transient( $key );
	}
	return $response;
}, 20, 3 );

/** Source saves succeed independently; do not silently report federation success. */
add_action( 'admin_notices', static function () : void {
	$post_id = isset( $_GET['post'] ) && is_scalar( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) { return; }
	$key = 'ax_act_publish_error_' . get_current_user_id() . '_' . $post_id;
	$message = get_transient( $key );
	if ( ! is_string( $message ) || '' === $message ) { return; }
	delete_transient( $key );
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'The WordPress source was saved, but federation failed: ', 'axismundi-activities' ) . esc_html( $message ) . '</p></div>';
} );
