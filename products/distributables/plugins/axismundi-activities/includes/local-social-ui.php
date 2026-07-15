<?php
/**
 * Local Follow controls and the current Actor's relationship screen.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** Current user's local relationship screen URL. */
function axismundi_act_follows_admin_url() : string {
	$parent = current_user_can( 'list_users' ) ? 'users.php' : 'profile.php';
	return add_query_arg( 'page', 'axismundi-follows', admin_url( $parent ) );
}

/** Register the self-service local relationship screen. */
function axismundi_act_register_follows_page() : void {
	if ( current_user_can( 'list_users' ) ) {
		add_users_page(
			__( 'Follows', 'axismundi-activities' ),
			__( 'Follows', 'axismundi-activities' ),
			'edit_posts',
			'axismundi-follows',
			'axismundi_act_render_follows_page'
		);
	} else {
		add_submenu_page(
			'profile.php',
			__( 'Follows', 'axismundi-activities' ),
			__( 'Follows', 'axismundi-activities' ),
			'edit_posts',
			'axismundi-follows',
			'axismundi_act_render_follows_page'
		);
	}
}
add_action( 'admin_menu', 'axismundi_act_register_follows_page' );

/** Add local Follow state to the administrator Users table. */
function axismundi_act_users_follow_column( array $columns ) : array {
	if ( axismundi_act_current_local_actor() instanceof Axismundi_Actor ) {
		$columns['ax_local_follow'] = __( 'Follow', 'axismundi-activities' );
	}
	return $columns;
}
add_filter( 'manage_users_columns', 'axismundi_act_users_follow_column', 20 );

/** Render a nonce-protected local Follow action link for one Users row. */
function axismundi_act_users_follow_column_content( string $output, string $column, int $user_id ) : string {
	if ( 'ax_local_follow' !== $column ) {
		return $output;
	}
	$subject = axismundi_act_current_local_actor();
	$target  = axismundi_actors_get_for_user( $user_id );
	if ( ! $subject instanceof Axismundi_Actor || ! $target instanceof Axismundi_Actor || ! $target->is_local() || 'Person' !== $target->get_type() || 'public' !== $target->get_status() || ! $target->is_handle_locked() || $subject->get_uri() === $target->get_uri() ) {
		return '—';
	}
	$relation = axismundi_act_get_relation( 'follow', $subject->get_uri(), $target->get_uri() );
	$state    = is_array( $relation ) ? (string) $relation['state'] : '';
	$intent   = in_array( $state, array( 'pending', 'accepted' ), true ) ? 'unfollow' : 'follow';
	$label    = 'pending' === $state ? __( 'Cancel request', 'axismundi-activities' ) : ( 'accepted' === $state ? __( 'Unfollow', 'axismundi-activities' ) : __( 'Follow', 'axismundi-activities' ) );
	$nonce = wp_create_nonce( 'axismundi_act_local_follow_' . hash( 'sha256', $target->get_uri() ) );
	return ( '' !== $state ? '<span>' . esc_html( ucfirst( $state ) ) . '</span> · ' : '' )
		. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">'
		. '<input type="hidden" name="action" value="axismundi_act_local_follow">'
		. '<input type="hidden" name="intent" value="' . esc_attr( $intent ) . '">'
		. '<input type="hidden" name="target_uri" value="' . esc_attr( $target->get_uri() ) . '">'
		. '<input type="hidden" name="return_to" value="users">'
		. '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '">'
		. '<button type="submit" class="button-link">' . esc_html( $label ) . '</button></form>';
}
add_filter( 'manage_users_custom_column', 'axismundi_act_users_follow_column_content', 20, 3 );

/** Whether the current local Actor may follow this target. */
function axismundi_act_follow_target_available( Axismundi_Actor $subject, Axismundi_Actor $target ) : bool {
	if ( $subject->get_uri() === $target->get_uri() || 'tombstone' === $target->get_status() ) {
		return false;
	}
	return $target->is_local()
		? 'Person' === $target->get_type() && 'public' === $target->get_status() && $target->is_handle_locked()
		: true;
}

/** Render one reusable nonce-protected Follow control. */
function axismundi_act_follow_control_html( Axismundi_Actor $subject, Axismundi_Actor $target, string $button_class = 'button', string $return_to = '' ) : string {
	if ( ! axismundi_act_follow_target_available( $subject, $target ) ) {
		return '';
	}
	$relation   = axismundi_act_get_relation( 'follow', $subject->get_uri(), $target->get_uri() );
	$state      = is_array( $relation ) ? (string) $relation['state'] : '';
	$legacy     = is_array( $relation ) && 'legacy_snapshot' === (string) ( $relation['evidence_type'] ?? '' );
	$intent     = in_array( $state, array( 'pending', 'accepted' ), true ) ? 'unfollow' : 'follow';
	$label      = 'pending' === $state ? __( 'Cancel request', 'axismundi-activities' ) : ( 'accepted' === $state ? __( 'Unfollow', 'axismundi-activities' ) : __( 'Follow', 'axismundi-activities' ) );
	if ( $legacy && in_array( $state, array( 'legacy_pending', 'accepted' ), true ) ) {
		return '<p class="ax-local-follow__legacy"><strong>' . esc_html( 'accepted' === $state ? __( 'Following', 'axismundi-activities' ) : __( 'Follow request pending', 'axismundi-activities' ) ) . '</strong> <span>' . esc_html__( 'Imported relationship; the original Follow Activity is unavailable.', 'axismundi-activities' ) . '</span></p>';
	}
	ob_start();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="axismundi_act_follow">
		<input type="hidden" name="intent" value="<?php echo esc_attr( $intent ); ?>">
		<input type="hidden" name="target_uri" value="<?php echo esc_attr( $target->get_uri() ); ?>">
		<?php if ( '' !== $return_to ) : ?><input type="hidden" name="return_to" value="<?php echo esc_attr( $return_to ); ?>"><?php endif; ?>
		<?php wp_nonce_field( 'axismundi_act_follow_' . hash( 'sha256', $target->get_uri() ) ); ?>
		<button type="submit" class="<?php echo esc_attr( $button_class ); ?>"><?php echo esc_html( $label ); ?></button>
	</form>
	<?php
	return (string) ob_get_clean();
}

/** Append Follow state/action to a local or cached remote Actor profile block. */
function axismundi_act_render_profile_follow_control( string $content ) : string {
	$target  = function_exists( 'axismundi_actors_current_actor' ) ? axismundi_actors_current_actor() : null;
	$subject = axismundi_act_current_local_actor();
	if ( ! $target instanceof Axismundi_Actor || ! $subject instanceof Axismundi_Actor || ! axismundi_act_follow_target_available( $subject, $target ) ) {
		return $content;
	}
	$notice   = isset( $_GET['ax_follow_notice'] ) ? sanitize_key( wp_unslash( $_GET['ax_follow_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect notice.
	$messages = array(
		'followed'  => __( 'Following.', 'axismundi-activities' ),
		'requested' => __( 'Follow request sent.', 'axismundi-activities' ),
		'unfollowed' => __( 'No longer following.', 'axismundi-activities' ),
		'error'     => __( 'The Follow action could not be completed.', 'axismundi-activities' ),
	);
	wp_enqueue_style( 'axismundi-activities-local-social', plugins_url( 'assets/local-social.css', dirname( __DIR__ ) . '/axismundi-activities.php' ), array(), AXISMUNDI_ACTIVITIES_VERSION );
	ob_start();
	?>
	<div class="ax-local-follow">
		<?php if ( isset( $messages[ $notice ] ) ) : ?><p class="ax-local-follow__notice" role="status"><?php echo esc_html( $messages[ $notice ] ); ?></p><?php endif; ?>
		<?php echo axismundi_act_follow_control_html( $subject, $target, 'wp-element-button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped form markup. ?>
	</div>
	<?php
	return $content . (string) ob_get_clean();
}
add_filter( 'render_block_axismundi/actor-profile', 'axismundi_act_render_profile_follow_control' );

/** Add the same Follow control to Actors' administrator remote-detail seam. */
function axismundi_act_render_remote_admin_follow_control( Axismundi_Actor $target ) : void {
	$subject = axismundi_act_current_local_actor();
	if ( ! $subject instanceof Axismundi_Actor || ! axismundi_act_follow_target_available( $subject, $target ) ) {
		return;
	}
	echo '<h3>' . esc_html__( 'Relationship', 'axismundi-activities' ) . '</h3><div class="ax-local-follow">';
	echo axismundi_act_follow_control_html( $subject, $target, 'button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped form markup.
	echo '</div>';
}
add_action( 'axismundi_actors_remote_actor_actions', 'axismundi_act_render_remote_admin_follow_control' );

/** Safe post-action redirect. */
function axismundi_act_follow_redirect( string $fallback, string $notice ) : void {
	$url = wp_validate_redirect( (string) wp_get_referer(), $fallback );
	wp_safe_redirect( add_query_arg( 'ax_follow_notice', $notice, $url ) );
	exit;
}

/** Follow/unfollow action from an Actor profile. */
function axismundi_act_handle_local_follow() : void {
	$subject    = axismundi_act_current_local_actor();
	$target_uri = isset( $_POST['target_uri'] ) ? esc_url_raw( wp_unslash( $_POST['target_uri'] ) ) : '';
	$intent     = isset( $_POST['intent'] ) ? sanitize_key( wp_unslash( $_POST['intent'] ) ) : '';
	$return_to  = isset( $_POST['return_to'] ) ? sanitize_key( wp_unslash( $_POST['return_to'] ) ) : '';
	$nonce_action = isset( $_POST['action'] ) && 'axismundi_act_local_follow' === sanitize_key( wp_unslash( $_POST['action'] ) ) ? 'axismundi_act_local_follow_' : 'axismundi_act_follow_';
	check_admin_referer( $nonce_action . hash( 'sha256', $target_uri ) );
	$target   = axismundi_actors_get_by_uri( $target_uri );
	$fallback = $target instanceof Axismundi_Actor && '' !== $target->get_profile_url() ? $target->get_profile_url() : home_url( '/' );
	if ( 'follows' === $return_to ) {
		$fallback = axismundi_act_follows_admin_url();
	} elseif ( 'users' === $return_to && current_user_can( 'list_users' ) ) {
		$fallback = admin_url( 'users.php' );
	}
	if ( ! $subject instanceof Axismundi_Actor || ! $target instanceof Axismundi_Actor || ! in_array( $intent, array( 'follow', 'unfollow' ), true ) ) {
		axismundi_act_follow_redirect( $fallback, 'error' );
	}
	$result = 'follow' === $intent
		? axismundi_act_follow_actor( $subject, $target )
		: axismundi_act_unfollow_actor( $subject, $target );
	if ( is_wp_error( $result ) ) {
		axismundi_act_follow_redirect( $fallback, 'error' );
	}
	$notice = 'unfollow' === $intent ? 'unfollowed' : ( 'pending' === (string) $result['state'] ? 'requested' : 'followed' );
	axismundi_act_follow_redirect( $fallback, $notice );
}
add_action( 'admin_post_axismundi_act_local_follow', 'axismundi_act_handle_local_follow' );
add_action( 'admin_post_axismundi_act_follow', 'axismundi_act_handle_local_follow' );

/** Accept/reject one pending request from the self-service screen. */
function axismundi_act_handle_follow_decision() : void {
	$target      = axismundi_act_current_local_actor();
	$follow_uri  = isset( $_POST['follow_uri'] ) ? esc_url_raw( wp_unslash( $_POST['follow_uri'] ) ) : '';
	$decision    = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
	check_admin_referer( 'axismundi_act_follow_decision_' . hash( 'sha256', $follow_uri ) );
	$result = $target instanceof Axismundi_Actor ? axismundi_act_respond_to_local_follow( $target, $follow_uri, $decision ) : new WP_Error( 'ax_act_actor', __( 'An activated local Actor is required.', 'axismundi-activities' ) );
	axismundi_act_follow_redirect( axismundi_act_follows_admin_url(), is_wp_error( $result ) ? 'error' : $decision . 'ed' );
}
add_action( 'admin_post_axismundi_act_follow_decision', 'axismundi_act_handle_follow_decision' );

/** Save the current Actor's follower approval preference through Actors' repository API. */
function axismundi_act_handle_follow_policy() : void {
	$actor = axismundi_act_current_local_actor();
	check_admin_referer( 'axismundi_act_follow_policy' );
	$value  = isset( $_POST['require_approval'] );
	$result = $actor instanceof Axismundi_Actor && function_exists( 'axismundi_actors_set_local_policy' )
		? axismundi_actors_set_local_policy( $actor, 'manually_approves_followers', $value )
		: new WP_Error( 'ax_act_actor', __( 'An activated local Actor is required.', 'axismundi-activities' ) );
	axismundi_act_follow_redirect( axismundi_act_follows_admin_url(), is_wp_error( $result ) ? 'error' : 'saved' );
}
add_action( 'admin_post_axismundi_act_follow_policy', 'axismundi_act_handle_follow_policy' );

/** Local Actor label linked to its profile. */
function axismundi_act_local_actor_label( string $uri ) : string {
	$actor = axismundi_actors_get_by_uri( $uri );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return '<code>' . esc_html( $uri ) . '</code>';
	}
	return '<a href="' . esc_url( $actor->get_profile_url() ) . '">' . esc_html( $actor->get_display_name() ) . '</a> <code>@' . esc_html( $actor->get_preferred_username() ) . '</code>';
}

/** Render the current Actor's requests and accepted edges. */
function axismundi_act_render_follows_page() : void {
	$actor = axismundi_act_current_local_actor();
	if ( ! $actor instanceof Axismundi_Actor ) {
		wp_die( esc_html__( 'Activate and publish your Actor profile before using local follows.', 'axismundi-activities' ) );
	}
	$requests      = axismundi_act_get_pending_follow_requests( $actor->get_uri() );
	$sent_requests = axismundi_act_get_pending_following_requests( $actor->get_uri() );
	$followers     = axismundi_act_get_followers( $actor->get_uri(), 200 );
	$following     = axismundi_act_get_following( $actor->get_uri(), 200 );
	$approval      = axismundi_act_local_follow_requires_approval( $actor );
	$notice        = isset( $_GET['ax_follow_notice'] ) ? sanitize_key( wp_unslash( $_GET['ax_follow_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect notice.
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Follows', 'axismundi-activities' ); ?></h1>
		<?php if ( in_array( $notice, array( 'accepted', 'rejected', 'saved' ), true ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Follow settings updated.', 'axismundi-activities' ); ?></p></div><?php elseif ( 'error' === $notice ) : ?><div class="notice notice-error"><p><?php esc_html_e( 'The Follow action could not be completed.', 'axismundi-activities' ); ?></p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="axismundi_act_follow_policy">
			<?php wp_nonce_field( 'axismundi_act_follow_policy' ); ?>
			<label><input type="checkbox" name="require_approval" value="1" <?php checked( $approval ); ?>> <?php esc_html_e( 'Require approval for new followers', 'axismundi-activities' ); ?></label>
			<?php submit_button( __( 'Save', 'axismundi-activities' ), 'secondary', 'submit', false ); ?>
		</form>

		<h2><?php esc_html_e( 'Follow requests', 'axismundi-activities' ); ?></h2>
		<table class="widefat striped"><tbody>
		<?php if ( empty( $requests ) ) : ?><tr><td><?php esc_html_e( 'No pending requests.', 'axismundi-activities' ); ?></td></tr><?php else : foreach ( $requests as $request ) : ?>
			<tr><td><?php echo axismundi_act_local_actor_label( (string) $request['subject_actor_uri'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes complete markup. ?></td><td>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="axismundi_act_follow_decision"><input type="hidden" name="follow_uri" value="<?php echo esc_attr( (string) $request['initiating_activity_uri'] ); ?>">
					<?php wp_nonce_field( 'axismundi_act_follow_decision_' . hash( 'sha256', (string) $request['initiating_activity_uri'] ) ); ?>
					<button class="button button-primary" name="decision" value="accept"><?php esc_html_e( 'Accept', 'axismundi-activities' ); ?></button> <button class="button" name="decision" value="reject"><?php esc_html_e( 'Reject', 'axismundi-activities' ); ?></button>
				</form>
			</td></tr>
		<?php endforeach; endif; ?>
		</tbody></table>

		<h2><?php esc_html_e( 'Sent requests', 'axismundi-activities' ); ?></h2>
		<table class="widefat striped"><tbody>
		<?php if ( empty( $sent_requests ) ) : ?><tr><td><?php esc_html_e( 'No pending sent requests.', 'axismundi-activities' ); ?></td></tr><?php else : foreach ( $sent_requests as $request ) : ?>
			<tr><td><?php echo axismundi_act_local_actor_label( (string) $request['object_actor_uri'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes complete markup. ?></td><td>
				<?php
				$sent_target = axismundi_actors_get_by_uri( (string) $request['object_actor_uri'] );
				if ( $sent_target instanceof Axismundi_Actor ) {
					echo axismundi_act_follow_control_html( $actor, $sent_target, 'button', 'follows' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped form markup.
				}
				?>
			</td></tr>
		<?php endforeach; endif; ?>
		</tbody></table>

		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;margin-top:24px">
			<section><h2><?php esc_html_e( 'Followers', 'axismundi-activities' ); ?></h2><ul><?php foreach ( $followers as $uri ) : ?><li><?php echo axismundi_act_local_actor_label( $uri ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes complete markup. ?></li><?php endforeach; ?></ul></section>
			<section><h2><?php esc_html_e( 'Following', 'axismundi-activities' ); ?></h2><ul><?php foreach ( $following as $uri ) : ?><li><?php echo axismundi_act_local_actor_label( $uri ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes complete markup. ?></li><?php endforeach; ?></ul></section>
		</div>
	</div>
	<?php
}
