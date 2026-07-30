<?php
/**
 * Forum controls embedded in the managed Group administration record.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

/** Register the immutable community picker for a local Topic draft. */
function axismundi_forum_add_topic_meta_box() : void {
	add_meta_box( 'axismundi_forum_topic_context', __( 'Community', 'axismundi-forum' ), 'axismundi_forum_render_topic_meta_box', AXISMUNDI_FORUM_TOPIC_POST_TYPE, 'side', 'high' );
}
add_action( 'add_meta_boxes_ax_topic', 'axismundi_forum_add_topic_meta_box' );

/** Render community selection before admission, then the immutable Group context. */
function axismundi_forum_render_topic_meta_box( WP_Post $post ) : void {
	wp_nonce_field( 'axismundi_forum_topic_' . $post->ID, 'axismundi_forum_topic_nonce' );
	$entry = axismundi_forum_get_topic_entry( $post->ID );
	if ( is_array( $entry ) ) {
		$group = axismundi_actors_get_by_identity( (int) $entry['group_identity_id'] );
		echo '<p><strong>' . esc_html( $group instanceof Axismundi_Actor ? $group->get_display_name() : __( 'Unavailable community', 'axismundi-forum' ) ) . '</strong></p>';
		echo '<p class="description">' . esc_html__( 'A Topic keeps its community after admission.', 'axismundi-forum' ) . '</p>';
		return;
	}
	$groups = axismundi_forum_manageable_communities( get_current_user_id() );
	echo '<p><label for="axismundi-forum-topic-group">' . esc_html__( 'Community', 'axismundi-forum' ) . '</label></p><select id="axismundi-forum-topic-group" name="group_identity_id" style="width:100%"><option value="0">' . esc_html__( '— Select a community —', 'axismundi-forum' ) . '</option>';
	foreach ( $groups as $group ) {
		if ( axismundi_forum_can_admit_local_topic( $group->get_identity_id(), $post->ID, get_current_user_id() ) ) {
			printf( '<option value="%d">%s (@%s)</option>', $group->get_identity_id(), esc_html( $group->get_display_name() ), esc_html( $group->get_preferred_username() ) );
		}
	}
	echo '</select>';
}

/** Persist the selected community on the first Topic save. */
function axismundi_forum_save_topic_context( int $post_id ) : void {
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || AXISMUNDI_FORUM_TOPIC_POST_TYPE !== get_post_type( $post_id ) ) { return; }
	$nonce = isset( $_POST['axismundi_forum_topic_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['axismundi_forum_topic_nonce'] ) ) : '';
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'axismundi_forum_topic_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) || null !== axismundi_forum_get_topic_entry( $post_id ) ) { return; }
	$group_id = isset( $_POST['group_identity_id'] ) ? absint( $_POST['group_identity_id'] ) : 0;
	if ( $group_id > 0 ) { axismundi_forum_admit_local_topic( $group_id, $post_id, get_current_user_id() ); }
}
add_action( 'save_post', 'axismundi_forum_save_topic_context' );

/** Render the community section inside Actors' managed Group screen. */
function axismundi_forum_render_group_admin_section( Axismundi_Actor $group ) : void {
	if ( ! $group->is_local() || ! $group->is_managed() || 'Group' !== $group->get_type() || ! axismundi_actors_managed_actor_can_manage( $group->get_identity_id(), get_current_user_id() ) ) {
		return;
	}
	$group_id  = $group->get_identity_id();
	$community = axismundi_forum_get_community( $group_id );
	echo '<hr><h2>' . esc_html__( 'Community', 'axismundi-forum' ) . '</h2>';
	axismundi_forum_group_admin_notice( $group_id );
	if ( ! is_array( $community ) ) {
		echo '<p>' . esc_html__( 'Enable discussion for this Group. Its public Actor profile will become the community page.', 'axismundi-forum' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="axismundi_forum_enable_community"><input type="hidden" name="group_identity_id" value="' . esc_attr( (string) $group_id ) . '">';
		wp_nonce_field( 'axismundi_forum_community_' . $group_id );
		submit_button( __( 'Enable community', 'axismundi-forum' ), 'secondary', 'submit', false );
		echo '</form>';
		return;
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="axismundi_forum_save_community"><input type="hidden" name="group_identity_id" value="' . esc_attr( (string) $group_id ) . '">';
	wp_nonce_field( 'axismundi_forum_community_' . $group_id );
	echo '<table class="form-table" role="presentation"><tr><th><label for="ax-forum-posting">' . esc_html__( 'Topic posting', 'axismundi-forum' ) . '</label></th><td><select id="ax-forum-posting" name="posting_policy">';
	foreach ( axismundi_forum_posting_policies() as $value => $label ) {
		printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $community['posting_policy'], $value, false ), esc_html( $label ) );
	}
	echo '</select></td></tr><tr><th><label for="ax-forum-membership">' . esc_html__( 'Membership', 'axismundi-forum' ) . '</label></th><td><select id="ax-forum-membership" name="membership_policy">';
	foreach ( axismundi_forum_membership_policies() as $value => $label ) {
		printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $community['membership_policy'], $value, false ), esc_html( $label ) );
	}
	echo '</select></td></tr></table>';
	submit_button( __( 'Save community settings', 'axismundi-forum' ), 'secondary' );
	echo '</form>';
	$pending = axismundi_forum_pending_memberships( $group_id );
	if ( ! empty( $pending ) ) {
		echo '<h3>' . esc_html__( 'Membership requests', 'axismundi-forum' ) . '</h3><ul>';
		foreach ( $pending as $membership ) {
			$member = axismundi_actors_get_by_identity( (int) $membership['actor_identity_id'] );
			if ( ! $member instanceof Axismundi_Actor ) { continue; }
			echo '<li>@' . esc_html( $member->get_preferred_username() ) . ' <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			echo '<input type="hidden" name="action" value="axismundi_forum_membership_decision"><input type="hidden" name="group_identity_id" value="' . esc_attr( (string) $group_id ) . '"><input type="hidden" name="actor_identity_id" value="' . esc_attr( (string) $membership['actor_identity_id'] ) . '">';
			wp_nonce_field( 'axismundi_forum_membership_' . $group_id . '_' . $membership['actor_identity_id'] );
			echo '<button class="button button-small" name="decision" value="accept">' . esc_html__( 'Accept', 'axismundi-forum' ) . '</button> <button class="button button-small" name="decision" value="reject">' . esc_html__( 'Reject', 'axismundi-forum' ) . '</button></form></li>';
		}
		echo '</ul>';
	}
}
add_action( 'axismundi_actors_managed_group_admin_sections', 'axismundi_forum_render_group_admin_section', 20 );

/** Print a one-shot Group-scoped admin notice. */
function axismundi_forum_group_admin_notice( int $group_id ) : void {
	$message = get_transient( 'axismundi_forum_group_notice_' . $group_id );
	if ( false !== $message ) {
		delete_transient( 'axismundi_forum_group_notice_' . $group_id );
		echo '<div class="notice notice-error"><p>' . esc_html( (string) $message ) . '</p></div>';
	}
}

/** Redirect back to the managed Group record, retaining errors there. */
function axismundi_forum_group_admin_redirect( int $group_id, $result ) : void {
	if ( is_wp_error( $result ) ) {
		set_transient( 'axismundi_forum_group_notice_' . $group_id, $result->get_error_message(), 60 );
	}
	wp_safe_redirect( axismundi_actors_managed_groups_admin_url( $group_id ) );
	exit;
}

function axismundi_forum_handle_enable_community() : void {
	$group_id = isset( $_POST['group_identity_id'] ) ? absint( $_POST['group_identity_id'] ) : 0;
	check_admin_referer( 'axismundi_forum_community_' . $group_id );
	axismundi_forum_group_admin_redirect( $group_id, axismundi_forum_enable_community( $group_id, get_current_user_id() ) );
}
add_action( 'admin_post_axismundi_forum_enable_community', 'axismundi_forum_handle_enable_community' );

function axismundi_forum_handle_save_community() : void {
	$group_id = isset( $_POST['group_identity_id'] ) ? absint( $_POST['group_identity_id'] ) : 0;
	check_admin_referer( 'axismundi_forum_community_' . $group_id );
	$result = axismundi_forum_set_posting_policy( $group_id, get_current_user_id(), sanitize_key( (string) ( $_POST['posting_policy'] ?? '' ) ) );
	if ( ! is_wp_error( $result ) ) {
		$result = axismundi_forum_set_membership_policy( $group_id, get_current_user_id(), sanitize_key( (string) ( $_POST['membership_policy'] ?? '' ) ) );
	}
	axismundi_forum_group_admin_redirect( $group_id, $result );
}
add_action( 'admin_post_axismundi_forum_save_community', 'axismundi_forum_handle_save_community' );

function axismundi_forum_handle_membership_decision() : void {
	$group_id = isset( $_POST['group_identity_id'] ) ? absint( $_POST['group_identity_id'] ) : 0;
	$actor_id = isset( $_POST['actor_identity_id'] ) ? absint( $_POST['actor_identity_id'] ) : 0;
	check_admin_referer( 'axismundi_forum_membership_' . $group_id . '_' . $actor_id );
	axismundi_forum_group_admin_redirect( $group_id, axismundi_forum_respond_to_membership_request( $group_id, $actor_id, get_current_user_id(), sanitize_key( (string) ( $_POST['decision'] ?? '' ) ) ) );
}
add_action( 'admin_post_axismundi_forum_membership_decision', 'axismundi_forum_handle_membership_decision' );
