<?php
/** Current source attribution, independent of upload authorship and media rights. */
defined( 'ABSPATH' ) || exit;

const AXISMUNDI_OP_ATTRIBUTED_TO_META = '_ax_op_attributed_to';
const AXISMUNDI_OP_CO_ATTRIBUTED_TO_META = '_ax_op_co_attributed_to';

/** Normalize an unordered attribution set without fetching or granting any Actor authority. */
function axismundi_op_normalize_attribution_uris( $value ) {
	if ( '' === $value || null === $value ) { return array(); }
	$values = is_array( $value ) && array_is_list( $value ) ? $value : array( $value );
	if ( count( $values ) > 32 ) { return new WP_Error( 'ax_op_attribution_members', 'Attribution is limited to 32 members.', array( 'status' => 400 ) ); }
	$result = array();
	foreach ( $values as $member ) {
		$raw = is_array( $member ) ? ( $member['id'] ?? $member['href'] ?? null ) : $member;
		$normalized = is_string( $raw ) ? axismundi_as2_normalize_uri( $raw ) : null;
		if ( ! $normalized || empty( $normalized['resolvable'] ) ) { return new WP_Error( 'ax_op_attribution_members', 'Attribution members require HTTP(S) Actor URIs.', array( 'status' => 400 ) ); }
		$result[$normalized['uri']] = true;
	}
	$uris = array_keys( $result );
	sort( $uris, SORT_STRING );
	return $uris;
}

function axismundi_op_stored_post_co_attribution( int $post_id ) : array {
	return array_values( array_filter( get_post_meta( $post_id, AXISMUNDI_OP_CO_ATTRIBUTED_TO_META, false ), 'is_string' ) );
}

/** The supplied publisher is authority; its placement first is presentation only. */
function axismundi_op_post_attribution_uris( WP_Post $post, ?string $publisher = null ) : array {
	$publisher ??= 'attachment' === $post->post_type ? axismundi_op_attachment_actor_uri( $post ) : axismundi_op_post_actor_uri( $post );
	if ( '' === $publisher ) { return array(); }
	$others = array_values( array_diff( axismundi_op_stored_post_co_attribution( $post->ID ), array( $publisher ) ) );
	sort( $others, SORT_STRING );
	return array_values( array_unique( array_merge( array( $publisher ), $others ) ) );
}

function axismundi_op_post_attributed_to( WP_Post $post, ?string $publisher = null ) {
	$uris = axismundi_op_post_attribution_uris( $post, $publisher );
	return count( $uris ) > 1 ? $uris : ( $uris[0] ?? '' );
}

/** Resolve local publication authority independently of the unordered wire attribution set. */
function axismundi_op_local_object_publisher_uri( array $object ) : string {
	$members = axismundi_op_normalize_attribution_uris( $object['attributedTo'] ?? array() );
	if ( is_wp_error( $members ) || ! $members ) { return ''; }
	$id = is_string( $object['id'] ?? null ) ? $object['id'] : '';
	$registered = '' !== $id ? axismundi_op_get_object_by_uri( $id ) : null;
	if ( $registered && 'local' === $registered['origin'] ) {
		$publisher = (string) ( $registered['attributed_to_uri'] ?? '' );
		if ( in_array( $publisher, $members, true ) ) { return $publisher; }
		if ( '' !== $publisher ) { return ''; }
	}
	// Single-author sources remain unambiguous. Unknown multi-author sources fail closed.
	return 1 === count( $members ) ? $members[0] : '';
}

function axismundi_op_attribution_lock( int $post_id, bool $release = false ) : bool {
	global $wpdb;
	$name = 'axattr:' . substr( hash( 'sha256', $wpdb->prefix . ':' . $post_id ), 0, 48 );
	$sql = $release ? 'SELECT RELEASE_LOCK(%s)' : 'SELECT GET_LOCK(%s,0)';
	return '1' === (string) $wpdb->get_var( $wpdb->prepare( $sql, $name ) );
}

function axismundi_op_stored_post_attribution( int $post_id ) : string {
	$value = get_post_meta( $post_id, AXISMUNDI_OP_ATTRIBUTED_TO_META, true );
	return is_string( $value ) ? $value : '';
}

/** Trusted repository writer; callers establish mutation authority before calling. */
function axismundi_op_store_post_attribution( WP_Post $post, string $uri, ?array $co_attributed_to = null ) {
	if ( ! in_array( $post->post_type, array( 'post', 'attachment' ), true ) || wp_is_post_revision( $post->ID ) ) {
		return new WP_Error( 'ax_op_attribution_source', 'This source has no editable publishing Account.', array( 'status' => 400 ) );
	}
	if ( '' !== $uri ) {
		$actor = axismundi_actors_get_by_uri( $uri );
		if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || $actor->get_uri() !== $uri ) {
			return new WP_Error( 'ax_op_attribution_actor', 'A canonical local Actor URI is required.', array( 'status' => 400 ) );
		}
	}
	$others = axismundi_op_normalize_attribution_uris( $co_attributed_to ?? axismundi_op_stored_post_co_attribution( $post->ID ) );
	if ( is_wp_error( $others ) ) { return $others; }
	$others = array_values( array_diff( $others, array( $uri ) ) );
	if ( count( $others ) > 31 ) { return new WP_Error( 'ax_op_attribution_members', 'Attribution is limited to 32 members including the publisher.', array( 'status' => 400 ) ); }
	if ( '' === $uri && $others ) { return new WP_Error( 'ax_op_attribution_publisher', 'Joint attribution requires an explicit local publishing Account.', array( 'status' => 400 ) ); }
	if ( ! axismundi_op_attribution_lock( $post->ID ) ) { return new WP_Error( 'ax_op_attribution_busy', 'Attribution is being changed.', array( 'status' => 409 ) ); }
	global $wpdb;
	try {
		// This repository writer runs outside ledger transactions; publication calls it after commit.
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) { throw new RuntimeException( 'Attribution transaction unavailable.' ); }
		if ( ! metadata_exists( 'post', $post->ID, AXISMUNDI_OP_ATTRIBUTED_TO_META ) || axismundi_op_stored_post_attribution( $post->ID ) !== $uri ) {
			if ( false === update_post_meta( $post->ID, AXISMUNDI_OP_ATTRIBUTED_TO_META, wp_slash( $uri ) ) ) { throw new RuntimeException( 'Publishing Account write failed.' ); }
		}
		if ( axismundi_op_stored_post_co_attribution( $post->ID ) !== $others ) {
			if ( metadata_exists( 'post', $post->ID, AXISMUNDI_OP_CO_ATTRIBUTED_TO_META ) && ! delete_post_meta( $post->ID, AXISMUNDI_OP_CO_ATTRIBUTED_TO_META ) ) { throw new RuntimeException( 'Attribution replacement failed.' ); }
			foreach ( $others as $other ) {
				if ( ! add_post_meta( $post->ID, AXISMUNDI_OP_CO_ATTRIBUTED_TO_META, wp_slash( $other ) ) ) { throw new RuntimeException( 'Joint attribution write failed.' ); }
			}
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'Attribution commit failed.' ); }
		wp_cache_delete( $post->ID, 'post_meta' );
	} catch ( Throwable $error ) {
		$wpdb->query( 'ROLLBACK' );
		wp_cache_delete( $post->ID, 'post_meta' );
		return new WP_Error( 'ax_op_attribution_write', 'Attribution could not be stored.', array( 'status' => 500 ) );
	} finally { axismundi_op_attribution_lock( $post->ID, true ); }
	axismundi_op_register_local_post_object( $post );
	$object_uri = 'attachment' === $post->post_type ? axismundi_op_core_attachment_uri( $post ) : axismundi_op_post_object_uri( $post );
	axismundi_op_refresh_object_listing_projection( $object_uri );
	return true;
}

/**
 * Public mutation service: WP editing and Account operating grants are separate checks.
 * Explicit site-admin assignment may bypass the Account grant only for verified unpublished attachments.
 */
function axismundi_op_set_post_attribution( int $post_id, string $uri, int $user_id, ?array $co_attributed_to = null, bool $unpublished_only = false, bool $allow_admin_media_assignment = false, bool $allow_federated_update = true ) {
	$post = $post_id > 0 ? get_post( $post_id ) : null;
	if ( ! $post instanceof WP_Post || ! user_can( $user_id, 'edit_post', $post_id ) ) {
		return new WP_Error( 'ax_op_attribution_permission', 'You cannot edit this source.', array( 'status' => 403 ) );
	}
	$actor = '' !== $uri ? axismundi_actors_get_by_uri( $uri ) : null;
	if ( '' !== $uri ) {
		if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || 'public' !== $actor->get_status() || ! $actor->is_handle_locked() ) {
			return new WP_Error( 'ax_op_attribution_permission', 'You cannot publish as this Account.', array( 'status' => 403 ) );
		}
		if ( ! axismundi_actors_can_act_as( $actor, $user_id ) ) {
			if ( ! $allow_admin_media_assignment || 'attachment' !== $post->post_type || ! user_can( $user_id, 'manage_options' ) ) {
				return new WP_Error( 'ax_op_attribution_permission', 'You cannot publish as this Account.', array( 'status' => 403 ) );
			}
			// Administrative attribution is not authority to send a federated Update.
			$unpublished_only = true;
		}
	}
	if ( ! axismundi_op_attribution_lock( $post_id ) ) { return new WP_Error( 'ax_op_attribution_busy', 'Attribution is being changed.', array( 'status' => 409 ) ); }
	try {
	if ( $unpublished_only ) {
		$object_uri = 'attachment' === $post->post_type ? axismundi_op_core_attachment_uri( $post ) : axismundi_op_post_object_uri( $post );
		if ( ! function_exists( 'axismundi_act_has_outbound_create' ) || false !== axismundi_act_has_outbound_create( $object_uri ) ) {
			return new WP_Error( 'ax_op_attribution_published', __( 'Only verified unpublished objects can be assigned without the ordinary publishing workflow.', 'axismundi-object-projections' ), array( 'status' => 409 ) );
		}
	}
	$old = axismundi_op_stored_post_attribution( $post_id );
	$old_others = axismundi_op_stored_post_co_attribution( $post_id );
	$new_others = axismundi_op_normalize_attribution_uris( $co_attributed_to ?? $old_others );
	if ( is_wp_error( $new_others ) ) { return $new_others; }
	$new_others = array_values( array_diff( $new_others, array( $uri ) ) );
	if ( 'post' === $post->post_type && function_exists( 'axismundi_act_outbound_create_actor' ) ) {
		$creator = axismundi_act_outbound_create_actor( axismundi_op_post_object_uri( $post ) );
		if ( null === $creator ) {
			return new WP_Error( 'ax_op_attribution_history', __( 'Publication history is unavailable. Try again before changing attribution.', 'axismundi-object-projections' ), array( 'status' => 503 ) );
		}
		if ( '' !== $creator ) {
			if ( $creator !== $uri ) {
				return new WP_Error( 'ax_op_attribution_frozen', __( 'The actor that published this post cannot be changed.', 'axismundi-object-projections' ), array( 'status' => 409 ) );
			}
			$existing = array_diff( array_merge( array( $old ), $old_others ), array( '', $creator ) );
			if ( array_diff( $existing, $new_others ) ) {
				return new WP_Error( 'ax_op_attribution_removal', __( 'Existing attribution members cannot be removed after publication.', 'axismundi-object-projections' ), array( 'status' => 409 ) );
			}
		}
	}
	if ( $uri === $old && $old_others === $new_others ) { return true; }
	$existed = metadata_exists( 'post', $post_id, AXISMUNDI_OP_ATTRIBUTED_TO_META );
	$result = axismundi_op_store_post_attribution( $post, $uri, $new_others );
	if ( is_wp_error( $result ) ) { return $result; }
	$result = apply_filters( 'axismundi_op_post_attribution_changed', true, $post, $old, $uri, $user_id, $old_others, $new_others, $unpublished_only, $allow_federated_update );
	if ( is_wp_error( $result ) ) {
		// Do not roll back over an independently committed newer choice.
		if ( axismundi_op_stored_post_attribution( $post_id ) === $uri && axismundi_op_stored_post_co_attribution( $post_id ) === $new_others ) {
			axismundi_op_store_post_attribution( $post, $old, $old_others );
			if ( ! $existed ) { delete_post_meta( $post_id, AXISMUNDI_OP_ATTRIBUTED_TO_META ); }
		}
		return $result;
	}
	return true;
	} finally { axismundi_op_attribution_lock( $post_id, true ); }
}

add_action( 'init', static function () : void {
	foreach ( array( 'post', 'attachment' ) as $type ) {
		// REST writes must use the mutation service, not a generic meta permission check.
		register_post_meta( $type, AXISMUNDI_OP_ATTRIBUTED_TO_META, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => 'esc_url_raw', 'auth_callback' => '__return_false' ) );
		register_post_meta( $type, AXISMUNDI_OP_CO_ATTRIBUTED_TO_META, array( 'type' => 'string', 'single' => false, 'show_in_rest' => false, 'sanitize_callback' => 'esc_url_raw', 'auth_callback' => '__return_false' ) );
	}
} );

add_action( 'rest_api_init', static function () : void {
	register_rest_field(
		'post',
		'axismundi_attribution',
		array(
			'get_callback'    => static function ( array $data ) : ?array {
				$id = (int) ( $data['id'] ?? 0 );
				if ( ! $id || ! current_user_can( 'edit_post', $id ) ) { return null; }
				return array(
					'publisher' => axismundi_op_stored_post_attribution( $id ),
				);
			},
			'update_callback' => static function ( array $value, WP_Post $post ) {
				if ( 'post' !== $post->post_type ) { return true; }
				return axismundi_op_set_post_attribution( $post->ID, $value['publisher'], get_current_user_id() );
			},
			'schema'          => array(
				'type'                 => 'object',
				'context'              => array( 'edit' ),
				'additionalProperties' => false,
				'properties'           => array(
					'publisher' => array( 'type' => 'string', 'required' => true ),
				),
			),
		)
	);
} );

add_action( 'add_meta_boxes', static function () : void {
	$screen = get_current_screen();
	foreach ( array( 'post', 'attachment' ) as $type ) {
		if ( 'post' === $type && $screen && $screen->is_block_editor() ) { continue; }
		add_meta_box( 'ax-op-attribution', 'attributedTo', 'axismundi_op_post_attribution_box', $type, 'side' );
	}
} );

function axismundi_op_post_attribution_box( WP_Post $post ) : void {
	$current = axismundi_op_stored_post_attribution( $post->ID );
	wp_nonce_field( 'ax_op_attribution_' . $post->ID, 'ax_op_attribution_nonce' );
	echo '<p><label for="ax-op-publisher">' . esc_html__( 'Publishing Account', 'axismundi-object-projections' ) . '</label></p><select id="ax-op-publisher" name="ax_op_attributed_to" style="max-width:100%">';
	printf( '<option value="" %s>%s</option>', selected( $current, '', false ), esc_html__( 'Unattributed', 'axismundi-object-projections' ) );
	$choices = axismundi_actors_accounts_for_user( get_current_user_id() );
	$seen = false;
	foreach ( $choices as $actor ) {
		if ( ! $actor->is_local() || 'public' !== $actor->get_status() || ! axismundi_actors_can_act_as( $actor, get_current_user_id() ) ) { continue; }
		$seen = $seen || $current === $actor->get_uri();
		printf( '<option value="%s" %s>%s</option>', esc_attr( $actor->get_uri() ), selected( $current, $actor->get_uri(), false ), esc_html( $actor->get_display_name() ) );
	}
	if ( '' !== $current && ! $seen ) {
		printf( '<option value="%s" selected>%s</option>', esc_attr( $current ), esc_html( $current ) );
	}
	echo '</select>';
	echo '<p>' . esc_html__( 'Changes to previously delivered copies may not be reflected on remote servers.', 'axismundi-object-projections' ) . '</p>';
}

function axismundi_op_save_post_attribution( int $post_id, WP_Post $post ) : void {
	if ( ! in_array( $post->post_type, array( 'post', 'attachment' ), true ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }
	$nonce = isset( $_POST['ax_op_attribution_nonce'] ) && is_string( $_POST['ax_op_attribution_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ax_op_attribution_nonce'] ) ) : '';
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'ax_op_attribution_' . $post_id ) ) { return; }
	if ( ! isset( $_POST['ax_op_attributed_to'] ) || ! is_string( $_POST['ax_op_attributed_to'] ) ) { return; }
	$uri = esc_url_raw( wp_unslash( $_POST['ax_op_attributed_to'] ) );
	if ( '' === $uri && '' !== trim( wp_unslash( $_POST['ax_op_attributed_to'] ) ) ) {
		set_transient( 'ax_op_attribution_error_' . get_current_user_id(), __( 'Invalid publishing Account URI.', 'axismundi-object-projections' ), MINUTE_IN_SECONDS );
		return;
	}
	$result = axismundi_op_set_post_attribution( $post_id, $uri, get_current_user_id() );
	if ( is_wp_error( $result ) ) { set_transient( 'ax_op_attribution_error_' . get_current_user_id(), $result->get_error_message(), MINUTE_IN_SECONDS ); }
}
add_action( 'save_post', 'axismundi_op_save_post_attribution', 20, 2 );
add_action( 'edit_attachment', static function ( int $post_id ) : void {
	$post = get_post( $post_id );
	if ( $post instanceof WP_Post ) { axismundi_op_save_post_attribution( $post_id, $post ); }
}, 20 );

add_action( 'admin_notices', static function () : void {
	$key = 'ax_op_attribution_error_' . get_current_user_id();
	$error = get_transient( $key );
	if ( is_string( $error ) && '' !== $error ) { delete_transient( $key ); echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>'; }
} );
