<?php
/**
 * Forum binding admin surface (docs/AXISMUNDI-FORUM-ARCHITECTURE.md §6-F0).
 *
 * A meta box on the `ax_forum` editor lets a manager select one managed Group they
 * administer and bind it, or unbind the current one. Every decision routes through the
 * Actors authority kernel and the binding repository — this file renders and collects
 * input only, and never touches actor tables or invents its own authority.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

/** Register the binding meta box on the Forum editor. */
function axismundi_forum_add_meta_box() : void {
	add_meta_box(
		'axismundi_forum_group_binding',
		__( 'Bound Group Actor', 'axismundi-forum' ),
		'axismundi_forum_render_meta_box',
		'ax_forum',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes_ax_forum', 'axismundi_forum_add_meta_box' );

/** Register the immutable Forum-context picker for a local Topic draft. */
function axismundi_forum_add_topic_meta_box() : void {
	add_meta_box(
		'axismundi_forum_topic_context',
		__( 'Forum Context', 'axismundi-forum' ),
		'axismundi_forum_render_topic_meta_box',
		AXISMUNDI_FORUM_TOPIC_POST_TYPE,
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes_ax_topic', 'axismundi_forum_add_topic_meta_box' );

/** Render a Forum selector before admission, then a read-only context after it. */
function axismundi_forum_render_topic_meta_box( WP_Post $post ) : void {
	wp_nonce_field( 'axismundi_forum_topic_' . $post->ID, 'axismundi_forum_topic_nonce' );
	$entry = axismundi_forum_get_topic_entry( $post->ID );
	if ( is_array( $entry ) ) {
		$forum = get_post( (int) $entry['forum_post_id'] );
		echo '<p><strong>' . esc_html( $forum instanceof WP_Post ? get_the_title( $forum ) : __( 'Unavailable Forum', 'axismundi-forum' ) ) . '</strong></p>';
		echo '<p class="description">' . esc_html__( 'A Topic keeps its Forum context after admission.', 'axismundi-forum' ) . '</p>';
		if ( axismundi_forum_user_can_manage( (int) $entry['forum_post_id'], get_current_user_id() ) ) {
			echo '<p><label><input type="hidden" name="axismundi_forum_topic_locked" value="0"><input type="checkbox" name="axismundi_forum_topic_locked" value="1" ' . checked( ! empty( $entry['locked_at'] ), true, false ) . '> ';
			echo esc_html__( 'Lock replies', 'axismundi-forum' );
			echo '</label></p>';
			echo '<p><label><input type="hidden" name="axismundi_forum_topic_sticky" value="0"><input type="checkbox" name="axismundi_forum_topic_sticky" value="1" ' . checked( ! empty( $entry['sticky_position'] ), true, false ) . '> ';
			echo esc_html__( 'Pin topic', 'axismundi-forum' );
			echo '</label></p>';
		}
		return;
	}
	$forums = get_posts( array( 'post_type' => 'ax_forum', 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC' ) );
	echo '<p><label for="axismundi_forum_topic_forum">' . esc_html__( 'Forum', 'axismundi-forum' ) . '</label></p>';
	echo '<select id="axismundi_forum_topic_forum" name="axismundi_forum_topic_forum" style="width:100%;">';
	echo '<option value="0">' . esc_html__( '— Select a Forum —', 'axismundi-forum' ) . '</option>';
	foreach ( $forums as $forum ) {
		if ( ! axismundi_forum_can_admit_local_topic( $forum->ID, $post->ID, get_current_user_id() ) ) {
			continue;
		}
		printf( '<option value="%d">%s</option>', (int) $forum->ID, esc_html( get_the_title( $forum ) ) );
	}
	echo '</select>';
	echo '<p class="description">' . esc_html__( 'A Forum may allow all Topic editors or Group managers only.', 'axismundi-forum' ) . '</p>';
}

/** Save an initial Topic context; recontextualization is deliberately not implicit. */
function axismundi_forum_save_topic_context( int $post_id ) : void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || AXISMUNDI_FORUM_TOPIC_POST_TYPE !== get_post_type( $post_id ) ) {
		return;
	}
	$nonce = isset( $_POST['axismundi_forum_topic_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['axismundi_forum_topic_nonce'] ) ) : '';
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'axismundi_forum_topic_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$entry = axismundi_forum_get_topic_entry( $post_id );
	if ( is_array( $entry ) ) {
		$results = array();
		if ( isset( $_POST['axismundi_forum_topic_locked'] ) ) {
			$results[] = axismundi_forum_set_topic_locked( $post_id, get_current_user_id(), '1' === (string) wp_unslash( $_POST['axismundi_forum_topic_locked'] ) );
		}
		if ( isset( $_POST['axismundi_forum_topic_sticky'] ) ) {
			$results[] = axismundi_forum_set_topic_sticky( $post_id, get_current_user_id(), '1' === (string) wp_unslash( $_POST['axismundi_forum_topic_sticky'] ) );
		}
		foreach ( $results as $result ) {
			if ( is_wp_error( $result ) ) {
				set_transient( 'axismundi_forum_topic_error_' . $post_id, $result->get_error_message(), 60 );
			}
		}
		return;
	}
	$forum_id = isset( $_POST['axismundi_forum_topic_forum'] ) ? absint( $_POST['axismundi_forum_topic_forum'] ) : 0;
	if ( $forum_id <= 0 ) {
		return;
	}
	$result = axismundi_forum_admit_local_topic( $forum_id, $post_id, get_current_user_id() );
	if ( is_wp_error( $result ) ) {
		set_transient( 'axismundi_forum_topic_error_' . $post_id, $result->get_error_message(), 60 );
	}
}
add_action( 'save_post', 'axismundi_forum_save_topic_context' );

/**
 * Render the binding control: the current binding with an Unbind option, or a select
 * of Groups the current user manages that are still free to bind.
 *
 * @param WP_Post $post The Forum being edited.
 * @return void
 */
function axismundi_forum_render_meta_box( WP_Post $post ) : void {
	wp_nonce_field( 'axismundi_forum_bind_' . $post->ID, 'axismundi_forum_bind_nonce' );

	if ( ! axismundi_forum_actors_available() ) {
		echo '<p>' . esc_html__( 'Axismundi Actors is not active; binding is unavailable.', 'axismundi-forum' ) . '</p>';
		return;
	}

	$bound = axismundi_forum_get_bound_group( $post->ID );
	if ( $bound instanceof Axismundi_Actor ) {
		printf(
			'<p><strong>%s</strong><br><span class="description">@%s</span></p>',
			esc_html( $bound->get_display_name() ),
			esc_html( $bound->get_preferred_username() )
		);
		if ( axismundi_forum_user_can_manage( $post->ID, get_current_user_id() ) ) {
			$policy = axismundi_forum_get_posting_policy( $post->ID );
			echo '<p><label for="axismundi_forum_posting_policy">' . esc_html__( 'Topic posting', 'axismundi-forum' ) . '</label></p>';
			echo '<select id="axismundi_forum_posting_policy" name="axismundi_forum_posting_policy" style="width:100%;">';
			foreach ( axismundi_forum_posting_policies() as $value => $label ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $policy, $value, false ), esc_html( $label ) );
			}
			echo '</select>';
			$membership_policy = axismundi_forum_get_membership_policy( $post->ID );
			echo '<p><label for="axismundi_forum_membership_policy">' . esc_html__( 'Group membership', 'axismundi-forum' ) . '</label></p>';
			echo '<select id="axismundi_forum_membership_policy" name="axismundi_forum_membership_policy" style="width:100%;">';
			foreach ( axismundi_forum_membership_policies() as $value => $label ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $membership_policy, $value, false ), esc_html( $label ) );
			}
			echo '</select>';
			$pending_memberships = axismundi_forum_pending_memberships( $post->ID );
			if ( ! empty( $pending_memberships ) ) {
				echo '<p><strong>' . esc_html__( 'Membership requests', 'axismundi-forum' ) . '</strong></p><ul>';
				foreach ( $pending_memberships as $membership ) {
					$member = axismundi_actors_get_by_identity( (int) $membership['actor_identity_id'] );
					if ( ! $member instanceof Axismundi_Actor ) {
						continue;
					}
					echo '<li><span>@' . esc_html( $member->get_preferred_username() ) . '</span> ';
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
					echo '<input type="hidden" name="action" value="axismundi_forum_membership_decision">';
					echo '<input type="hidden" name="forum_post_id" value="' . esc_attr( (string) $post->ID ) . '">';
					echo '<input type="hidden" name="actor_identity_id" value="' . esc_attr( (string) $membership['actor_identity_id'] ) . '">';
					wp_nonce_field( 'axismundi_forum_membership_' . $post->ID . '_' . $membership['actor_identity_id'] );
					echo '<button class="button button-small" name="decision" value="accept">' . esc_html__( 'Accept', 'axismundi-forum' ) . '</button> ';
					echo '<button class="button button-small" name="decision" value="reject">' . esc_html__( 'Reject', 'axismundi-forum' ) . '</button>';
					echo '</form></li>';
				}
				echo '</ul>';
			}
			echo '<p><label><input type="checkbox" name="axismundi_forum_unbind" value="1"> ';
			echo esc_html__( 'Unbind this Group', 'axismundi-forum' );
			echo '</label></p>';
		} else {
			echo '<p class="description">' . esc_html__( 'Only a manager of this Group can change its Forum binding.', 'axismundi-forum' ) . '</p>';
		}
		echo '<p class="description">' . esc_html__( 'Unbinding removes the link only. The Group Actor is never deleted.', 'axismundi-forum' ) . '</p>';
		return;
	}

	$user_id = get_current_user_id();
	$groups  = axismundi_actors_list_manageable_groups( $user_id );
	// Offer only Groups that are Forum-eligible and not already bound elsewhere.
	$options = array();
	foreach ( $groups as $group ) {
		if ( ! $group->is_managed() || 'Group' !== $group->get_type() ) {
			continue;
		}
		if ( axismundi_forum_get_forum_for_group( $group->get_identity_id() ) > 0 ) {
			continue;
		}
		$options[] = $group;
	}

	if ( empty( $options ) ) {
		echo '<p>' . esc_html__( 'You have no unbound Group Actors to bind. Create one in Actors first.', 'axismundi-forum' ) . '</p>';
		return;
	}

	echo '<p><label for="axismundi_forum_group_id">' . esc_html__( 'Select a Group you manage:', 'axismundi-forum' ) . '</label></p>';
	echo '<select id="axismundi_forum_group_id" name="axismundi_forum_group_id" style="width:100%;">';
	echo '<option value="0">' . esc_html__( '— None —', 'axismundi-forum' ) . '</option>';
	foreach ( $options as $group ) {
		printf(
			'<option value="%d">%s (@%s)</option>',
			(int) $group->get_identity_id(),
			esc_html( $group->get_display_name() ),
			esc_html( $group->get_preferred_username() )
		);
	}
	echo '</select>';
	echo '<p class="description">' . esc_html__( 'Save the Forum to bind the selected Group.', 'axismundi-forum' ) . '</p>';
}

/**
 * Persist a bind/unbind request submitted with the Forum save. Meta-box fields post
 * through the block editor's compatibility form, so `save_post` is the collection
 * point. Errors surface as an admin notice on the next screen.
 *
 * @param int $post_id The Forum post id.
 * @return void
 */
function axismundi_forum_save_meta_box( int $post_id ) : void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( 'ax_forum' !== get_post_type( $post_id ) ) {
		return;
	}
	$nonce = isset( $_POST['axismundi_forum_bind_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['axismundi_forum_bind_nonce'] ) ) : '';
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'axismundi_forum_bind_' . $post_id ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( ! empty( $_POST['axismundi_forum_unbind'] ) ) {
		$result = axismundi_forum_unbind_group( $post_id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			set_transient( 'axismundi_forum_bind_error_' . $post_id, $result->get_error_message(), 60 );
		}
		return;
	}

	if ( null !== axismundi_forum_get_binding( $post_id ) && ( isset( $_POST['axismundi_forum_posting_policy'] ) || isset( $_POST['axismundi_forum_membership_policy'] ) ) ) {
		$results = array();
		if ( isset( $_POST['axismundi_forum_posting_policy'] ) ) {
			$results[] = axismundi_forum_set_posting_policy( $post_id, get_current_user_id(), sanitize_key( wp_unslash( $_POST['axismundi_forum_posting_policy'] ) ) );
		}
		if ( isset( $_POST['axismundi_forum_membership_policy'] ) ) {
			$results[] = axismundi_forum_set_membership_policy( $post_id, get_current_user_id(), sanitize_key( wp_unslash( $_POST['axismundi_forum_membership_policy'] ) ) );
		}
		foreach ( $results as $result ) {
			if ( is_wp_error( $result ) ) {
				set_transient( 'axismundi_forum_bind_error_' . $post_id, $result->get_error_message(), 60 );
			}
		}
		return;
	}

	$group_id = isset( $_POST['axismundi_forum_group_id'] ) ? (int) $_POST['axismundi_forum_group_id'] : 0;
	if ( $group_id <= 0 ) {
		return;
	}
	$result = axismundi_forum_bind_group( $post_id, $group_id, get_current_user_id() );
	if ( is_wp_error( $result ) ) {
		set_transient( 'axismundi_forum_bind_error_' . $post_id, $result->get_error_message(), 60 );
	}
}
add_action( 'save_post', 'axismundi_forum_save_meta_box' );

/** Show any deferred bind error once, on the Forum editor. */
function axismundi_forum_bind_error_notice() : void {
	$screen = get_current_screen();
	if ( ! $screen instanceof WP_Screen || 'ax_forum' !== $screen->post_type ) {
		return;
	}
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen id.
	if ( $post_id <= 0 ) {
		return;
	}
	$error = get_transient( 'axismundi_forum_bind_error_' . $post_id );
	if ( false === $error ) {
		return;
	}
	delete_transient( 'axismundi_forum_bind_error_' . $post_id );
	echo '<div class="notice notice-error is-dismissible"><p>';
	echo esc_html( (string) $error );
	echo '</p></div>';
}
add_action( 'admin_notices', 'axismundi_forum_bind_error_notice' );

/** Show a deferred Topic admission or manager-control error once, on the Topic editor. */
function axismundi_forum_topic_error_notice() : void {
	$screen = get_current_screen();
	if ( ! $screen instanceof WP_Screen || AXISMUNDI_FORUM_TOPIC_POST_TYPE !== $screen->post_type ) {
		return;
	}
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen id.
	if ( $post_id <= 0 ) {
		return;
	}
	$error = get_transient( 'axismundi_forum_topic_error_' . $post_id );
	if ( false === $error ) {
		return;
	}
	delete_transient( 'axismundi_forum_topic_error_' . $post_id );
	echo '<div class="notice notice-error is-dismissible"><p>';
	echo esc_html( (string) $error );
	echo '</p></div>';
}
add_action( 'admin_notices', 'axismundi_forum_topic_error_notice' );

/** Handle one manager's explicit Accept or Reject of a pending Forum membership. */
function axismundi_forum_handle_membership_decision() : void {
	$forum_post_id    = isset( $_POST['forum_post_id'] ) ? absint( $_POST['forum_post_id'] ) : 0;
	$actor_identity_id = isset( $_POST['actor_identity_id'] ) ? absint( $_POST['actor_identity_id'] ) : 0;
	$decision         = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
	check_admin_referer( 'axismundi_forum_membership_' . $forum_post_id . '_' . $actor_identity_id );
	$result = axismundi_forum_respond_to_membership_request( $forum_post_id, $actor_identity_id, get_current_user_id(), $decision );
	if ( is_wp_error( $result ) ) {
		set_transient( 'axismundi_forum_bind_error_' . $forum_post_id, $result->get_error_message(), 60 );
	}
	wp_safe_redirect( get_edit_post_link( $forum_post_id, 'url' ) ?: admin_url( 'edit.php?post_type=ax_forum' ) );
	exit;
}
add_action( 'admin_post_axismundi_forum_membership_decision', 'axismundi_forum_handle_membership_decision' );
