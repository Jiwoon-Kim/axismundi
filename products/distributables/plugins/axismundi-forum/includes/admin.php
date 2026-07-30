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
	$remote = axismundi_forum_get_remote_topic_group( $post );
	if ( $remote instanceof Axismundi_Actor ) {
		echo '<p><strong>' . esc_html( $remote->get_display_name() ) . ' (@' . esc_html( $remote->get_preferred_username() ) . ')</strong></p>';
		echo '<p class="description">' . esc_html__( 'A Topic keeps its remote community after selection.', 'axismundi-forum' ) . '</p>';
		return;
	}
	$user_id      = get_current_user_id();
	$communities  = axismundi_forum_manageable_communities( $user_id );
	$joined       = function_exists( 'axismundi_forum_joined_local_communities_for_user' ) ? axismundi_forum_joined_local_communities_for_user( $user_id ) : array();
	$joined       = array_filter( $joined, static fn( Axismundi_Actor $group ) : bool => axismundi_forum_can_admit_local_topic( $group->get_identity_id(), $post->ID, $user_id ) );
	$remote_groups = function_exists( 'axismundi_forum_followed_remote_groups_for_user' ) ? axismundi_forum_followed_remote_groups_for_user( $user_id ) : array();
	echo '<p><label for="axismundi-forum-topic-group">' . esc_html__( 'Community', 'axismundi-forum' ) . '</label></p><select id="axismundi-forum-topic-group" name="community_target" style="width:100%"><option value="">' . esc_html__( '— Select a community —', 'axismundi-forum' ) . '</option>';
	if ( ! empty( $communities ) ) {
		echo '<optgroup label="' . esc_attr__( 'My communities', 'axismundi-forum' ) . '">';
		foreach ( $communities as $group ) {
			if ( axismundi_forum_can_admit_local_topic( $group->get_identity_id(), $post->ID, get_current_user_id() ) ) {
				printf( '<option value="local:%d">%s (@%s)</option>', $group->get_identity_id(), esc_html( $group->get_display_name() ), esc_html( $group->get_preferred_username() ) );
			}
		}
		echo '</optgroup>';
	}
	if ( ! empty( $joined ) ) {
		echo '<optgroup label="' . esc_attr__( 'Communities I\'ve joined', 'axismundi-forum' ) . '">';
		foreach ( $joined as $group ) {
			printf( '<option value="local:%d">%s (@%s)</option>', $group->get_identity_id(), esc_html( $group->get_display_name() ), esc_html( $group->get_preferred_username() ) );
		}
		echo '</optgroup>';
	}
	if ( ! empty( $remote_groups ) ) {
		echo '<optgroup label="' . esc_attr__( 'Followed remote communities', 'axismundi-forum' ) . '">';
		foreach ( $remote_groups as $group ) {
			printf( '<option value="remote:%d">%s (@%s)</option>', $group->get_identity_id(), esc_html( $group->get_display_name() ), esc_html( $group->get_preferred_username() ) );
		}
		echo '</optgroup>';
	}
	echo '</select>';
}

/** Persist the selected community on the first Topic save. */
function axismundi_forum_save_topic_context( int $post_id ) : void {
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || AXISMUNDI_FORUM_TOPIC_POST_TYPE !== get_post_type( $post_id ) ) { return; }
	$nonce = isset( $_POST['axismundi_forum_topic_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['axismundi_forum_topic_nonce'] ) ) : '';
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'axismundi_forum_topic_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) || null !== axismundi_forum_get_topic_entry( $post_id ) ) { return; }
	$target = isset( $_POST['community_target'] ) ? sanitize_text_field( wp_unslash( $_POST['community_target'] ) ) : '';
	if ( preg_match( '/^(local|remote):(\d+)$/', $target, $matches ) ) {
		$group_id = (int) $matches[2];
		$result = 'local' === $matches[1]
			? axismundi_forum_admit_local_topic( $group_id, $post_id, get_current_user_id() )
			: axismundi_forum_bind_remote_topic_group( $post_id, get_current_user_id(), $group_id );
		if ( ! is_wp_error( $result ) && 'publish' === get_post_status( $post_id ) && 'remote' === $matches[1] ) {
			$topic = get_post( $post_id );
			if ( $topic instanceof WP_Post ) {
				axismundi_forum_record_remote_topic_commit( $topic );
			}
		}
	}
}
add_action( 'save_post', 'axismundi_forum_save_topic_context' );

/** Render the community section inside Actors' managed Group screen. */
function axismundi_forum_render_group_admin_section( Axismundi_Actor $group ) : void {
	$user_id = get_current_user_id();
	if ( ! $group->is_local() || ! $group->is_managed() || 'Group' !== $group->get_type() ) {
		return;
	}
	$group_id  = $group->get_identity_id();
	$can_manage = axismundi_actors_managed_actor_can_manage( $group_id, $user_id );
	$can_moderate = function_exists( 'axismundi_forum_user_can_moderate' ) && axismundi_forum_user_can_moderate( $group_id, $user_id );
	if ( ! $can_manage && ! $can_moderate ) {
		return;
	}
	$community = axismundi_forum_get_community( $group_id );
	if ( ! is_array( $community ) ) {
		return;
	}
	echo '<hr><h2>' . esc_html__( 'Community', 'axismundi-forum' ) . '</h2>';
	axismundi_forum_group_admin_notice( $group_id );
	if ( $can_manage ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="axismundi_forum_save_community"><input type="hidden" name="group_identity_id" value="' . esc_attr( (string) $group_id ) . '">';
	wp_nonce_field( 'axismundi_forum_community_' . $group_id );
	echo '<table class="form-table" role="presentation"><tr><th><label for="ax-forum-posting">' . esc_html__( 'Topic posting', 'axismundi-forum' ) . '</label></th><td><select id="ax-forum-posting" name="posting_policy">';
	foreach ( axismundi_forum_posting_policies() as $value => $label ) {
		printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $community['posting_policy'], $value, false ), esc_html( $label ) );
	}
	echo '</select></td></tr><tr><th><label for="ax-forum-membership">' . esc_html__( 'Membership approval', 'axismundi-forum' ) . '</label></th><td><select id="ax-forum-membership" name="membership_policy">';
	foreach ( axismundi_forum_membership_policies() as $value => $label ) {
		printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $community['membership_policy'], $value, false ), esc_html( $label ) );
	}
	echo '</select><p class="description">' . esc_html__( 'A Group Follow is a membership request. Choose whether new requests are accepted automatically or require approval.', 'axismundi-forum' ) . '</p></td></tr></table>';
	echo '<table class="form-table" role="presentation"><tr><th><label for="ax-forum-topic-approval">' . esc_html__( 'Topic approval', 'axismundi-forum' ) . '</label></th><td><select id="ax-forum-topic-approval" name="topic_approval_policy">';
	foreach ( axismundi_forum_topic_approval_policies() as $value => $label ) {
		printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $community['topic_approval_policy'], $value, false ), esc_html( $label ) );
	}
	echo '</select><p class="description">' . esc_html__( 'When approval is required, valid Topic submissions wait here until a moderator approves the Group Announce.', 'axismundi-forum' ) . '</p></td></tr></table>';
	submit_button( __( 'Save community settings', 'axismundi-forum' ), 'secondary' );
	echo '</form>';
	}
	$member_page = isset( $_GET['ax_forum_member_page'] ) ? max( 1, absint( $_GET['ax_forum_member_page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
	$members     = axismundi_forum_get_membership_page( $group_id, 'accepted', $member_page );
	echo '<h3>' . esc_html__( 'Members', 'axismundi-forum' ) . ' <span class="count">(' . esc_html( number_format_i18n( (int) $members['total'] ) ) . ')</span></h3>';
	if ( empty( $members['items'] ) ) {
		echo '<p class="description">' . esc_html__( 'No accepted members yet.', 'axismundi-forum' ) . '</p>';
	} else {
		echo '<ul>';
		foreach ( $members['items'] as $membership ) {
			$member = axismundi_actors_get_by_identity( (int) $membership['actor_identity_id'] );
			if ( ! $member instanceof Axismundi_Actor ) {
				continue;
			}
			$label = '@' . $member->get_preferred_username();
			echo '<li><a href="' . esc_url( $member->get_profile_url() ) . '">' . esc_html( $label ) . '</a></li>';
		}
		echo '</ul>';
		$pages = max( 1, (int) ceil( (int) $members['total'] / 50 ) );
		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'ax_forum_member_page', '%#%', axismundi_actors_managed_groups_admin_url( $group_id ) ), 'format' => '', 'current' => $member_page, 'total' => $pages ) ) ) . '</div></div>';
		}
	}
	$pending = $can_manage ? axismundi_forum_pending_memberships( $group_id ) : array();
	if ( ! empty( $pending ) ) {
		echo '<h3>' . esc_html__( 'Membership requests', 'axismundi-forum' ) . ' <span class="count">(' . esc_html( number_format_i18n( count( $pending ) ) ) . ')</span></h3><ul>';
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
	$pending_topics = axismundi_forum_pending_topic_entries( $group_id );
	if ( ! empty( $pending_topics ) ) {
		echo '<h3>' . esc_html__( 'Topic submissions', 'axismundi-forum' ) . ' <span class="count">(' . esc_html( number_format_i18n( count( $pending_topics ) ) ) . ')</span></h3><ul>';
		foreach ( $pending_topics as $entry ) {
			$author = axismundi_actors_get_by_identity( (int) ( $entry['submission_actor_identity_id'] ?? 0 ) );
			$label = $author instanceof Axismundi_Actor ? '@' . $author->get_preferred_username() : __( 'Unknown author', 'axismundi-forum' );
			echo '<li>' . esc_html( $label ) . ' <code>' . esc_html( (string) $entry['object_uri'] ) . '</code> <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			echo '<input type="hidden" name="action" value="axismundi_forum_topic_decision"><input type="hidden" name="group_identity_id" value="' . esc_attr( (string) $group_id ) . '"><input type="hidden" name="entry_id" value="' . esc_attr( (string) $entry['id'] ) . '">';
			wp_nonce_field( 'axismundi_forum_topic_' . $group_id . '_' . $entry['id'] );
			echo '<button class="button button-small" name="decision" value="approve">' . esc_html__( 'Approve and announce', 'axismundi-forum' ) . '</button> <button class="button button-small" name="decision" value="pending">' . esc_html__( 'Keep pending', 'axismundi-forum' ) . '</button></form></li>';
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

function axismundi_forum_handle_save_community() : void {
	$group_id = isset( $_POST['group_identity_id'] ) ? absint( $_POST['group_identity_id'] ) : 0;
	check_admin_referer( 'axismundi_forum_community_' . $group_id );
	$result = axismundi_forum_set_posting_policy( $group_id, get_current_user_id(), sanitize_key( (string) ( $_POST['posting_policy'] ?? '' ) ) );
	if ( ! is_wp_error( $result ) ) {
		$result = axismundi_forum_set_membership_policy( $group_id, get_current_user_id(), sanitize_key( (string) ( $_POST['membership_policy'] ?? '' ) ) );
	}
	if ( ! is_wp_error( $result ) ) {
		$result = axismundi_forum_set_topic_approval_policy( $group_id, get_current_user_id(), sanitize_key( (string) ( $_POST['topic_approval_policy'] ?? '' ) ) );
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

/** Approve a pending Topic only through the Group distribution command. */
function axismundi_forum_handle_topic_decision() : void {
	$group_id = isset( $_POST['group_identity_id'] ) ? absint( $_POST['group_identity_id'] ) : 0;
	$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
	check_admin_referer( 'axismundi_forum_topic_' . $group_id . '_' . $entry_id );
	$result = 'approve' === sanitize_key( (string) ( $_POST['decision'] ?? '' ) ) && function_exists( 'axismundi_forum_approve_pending_entry' )
		? axismundi_forum_approve_pending_entry( $entry_id, get_current_user_id() )
		: new WP_Error( 'ax_forum_topic_decision', __( 'The Topic submission remains pending.', 'axismundi-forum' ) );
	axismundi_forum_group_admin_redirect( $group_id, $result );
}
add_action( 'admin_post_axismundi_forum_topic_decision', 'axismundi_forum_handle_topic_decision' );
