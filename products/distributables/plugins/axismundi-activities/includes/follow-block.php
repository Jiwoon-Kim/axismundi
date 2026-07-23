<?php
/**
 * Follow REST mutation and Interactivity API block.
 *
 * The relationship ledger stays authoritative. This module only turns its
 * materialized Follow state into a reusable profile and notification control.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** Resolve the Actor represented by one Follow button instance without fetching. */
function axismundi_act_follow_button_target( array $attributes, WP_Block $block ) : ?Axismundi_Actor {
	$uri = isset( $attributes['actorUri'] ) ? axismundi_act_uri( (string) $attributes['actorUri'] ) : '';
	if ( '' !== $uri ) {
		$actor = axismundi_actors_get_by_uri( $uri );
		return $actor instanceof Axismundi_Actor ? $actor : null;
	}
	if ( function_exists( 'axismundi_actors_current_actor' ) ) {
		$actor = axismundi_actors_current_actor();
		if ( $actor instanceof Axismundi_Actor ) {
			return $actor;
		}
	}
	$identity_id = isset( $block->context['axismundi/actorId'] ) ? (string) $block->context['axismundi/actorId'] : '';
	if ( '' !== $identity_id && function_exists( 'axismundi_actors_resolve_block_actor' ) ) {
		$actor = axismundi_actors_resolve_block_actor( $identity_id );
		return $actor instanceof Axismundi_Actor ? $actor : null;
	}
	return null;
}

/** Resolve a canonical target URI for the REST API without a network request. */
function axismundi_act_follow_rest_target( string $target_uri ) {
	$uri    = axismundi_act_uri( $target_uri );
	$target = '' !== $uri ? axismundi_actors_get_by_uri( $uri ) : null;
	if ( ! $target instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_act_follow_target', __( 'The Actor is unavailable.', 'axismundi-activities' ), array( 'status' => 404 ) );
	}
	return $target;
}

/** Return the public identifier an external Fediverse server should follow. */
function axismundi_act_remote_follow_target_resource( Axismundi_Actor $target ) {
	if ( 'tombstone' === $target->get_status() ) {
		return new WP_Error( 'ax_act_remote_follow_target', __( 'The Actor is unavailable.', 'axismundi-activities' ), array( 'status' => 404 ) );
	}
	if ( ! $target->is_local() ) {
		return $target->get_uri();
	}
	if ( 'public' !== $target->get_status() || ! $target->is_handle_locked() || '' === $target->get_preferred_username() ) {
		return new WP_Error( 'ax_act_remote_follow_target', __( 'The Actor is unavailable.', 'axismundi-activities' ), array( 'status' => 404 ) );
	}
	return 'acct:' . $target->get_preferred_username() . '@' . axismundi_actors_webfinger_authority();
}

/** Rate-limit unauthenticated WebFinger probes by the directly connected IP. */
function axismundi_act_remote_follow_rate_limit() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
	/** @param string $ip Direct client address; trusted deployments may provide it explicitly. */
	$ip = (string) apply_filters( 'axismundi_act_remote_follow_client_ip', $ip );
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return new WP_Error( 'ax_act_remote_follow_rate_limit', __( 'Remote follow is temporarily unavailable.', 'axismundi-activities' ), array( 'status' => 429 ) );
	}
	$key   = 'ax_remote_follow_' . md5( $ip );
	$count = (int) get_transient( $key );
	if ( $count >= 10 ) {
		return new WP_Error( 'ax_act_remote_follow_rate_limit', __( 'Too many remote follow attempts. Please try again shortly.', 'axismundi-activities' ), array( 'status' => 429, 'retry_after' => MINUTE_IN_SECONDS ) );
	}
	set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
	return true;
}

/** Resolve an anonymous visitor's remote-follow browser URL. */
function axismundi_act_rest_remote_follow( WP_REST_Request $request ) {
	$target = axismundi_act_follow_rest_target( (string) $request['target_uri'] );
	if ( is_wp_error( $target ) ) {
		return $target;
	}
	$rate = axismundi_act_remote_follow_rate_limit();
	if ( is_wp_error( $rate ) ) {
		return $rate;
	}
	$resource = axismundi_act_remote_follow_target_resource( $target );
	if ( is_wp_error( $resource ) ) {
		return $resource;
	}
	$template = axismundi_actors_remote_follow_template( (string) $request['resource'] );
	if ( is_wp_error( $template ) ) {
		return $template;
	}
	$url = str_replace( '{uri}', rawurlencode( $resource ), $template );
	return new WP_REST_Response( array( 'url' => $url ), 200 );
}

/**
 * Derive all presentational Follow state from the relation ledger.
 *
 * This pure shape is intentionally reusable from notifications. `state` is the
 * outgoing relation; `follows_you` is the accepted inverse edge.
 *
 * @return array<string,mixed>
 */
function axismundi_act_follow_button_state( ?Axismundi_Actor $subject, Axismundi_Actor $target ) : array {
	$state       = 'none';
	$follows_you = false;
	$legacy      = false;
	$self        = false;
	if ( $subject instanceof Axismundi_Actor ) {
		$self = hash_equals( $subject->get_uri(), $target->get_uri() );
		if ( ! $self ) {
			$relation = axismundi_act_get_relation( 'follow', $subject->get_uri(), $target->get_uri() );
			if ( is_array( $relation ) && in_array( (string) ( $relation['state'] ?? '' ), array( 'pending', 'accepted', 'legacy_pending' ), true ) ) {
				$state  = (string) $relation['state'];
				$legacy = 'legacy_snapshot' === (string) ( $relation['evidence_type'] ?? '' );
			}
			$inverse = axismundi_act_get_relation( 'follow', $target->get_uri(), $subject->get_uri() );
			$follows_you = is_array( $inverse ) && 'accepted' === (string) ( $inverse['state'] ?? '' );
		}
	}

	$label  = __( 'Follow', 'axismundi-activities' );
	$action = __( 'Follow', 'axismundi-activities' );
	if ( $legacy && in_array( $state, array( 'accepted', 'legacy_pending' ), true ) ) {
		$label  = __( 'Re-follow', 'axismundi-activities' );
		$action = $label;
	} elseif ( 'pending' === $state ) {
		$label  = __( 'Requested', 'axismundi-activities' );
		$action = __( 'Cancel follow request', 'axismundi-activities' );
	} elseif ( 'accepted' === $state && $follows_you ) {
		$label  = __( 'Mutual', 'axismundi-activities' );
		$action = __( 'Unfollow', 'axismundi-activities' );
	} elseif ( 'accepted' === $state ) {
		$label  = __( 'Following', 'axismundi-activities' );
		$action = __( 'Unfollow', 'axismundi-activities' );
	} elseif ( $follows_you ) {
		$label  = __( 'Follow back', 'axismundi-activities' );
		$action = $label;
	}

	return array(
		'state'       => $state,
		'follows_you' => $follows_you,
		'legacy'      => $legacy,
		'self'        => $self,
		'label'       => $label,
		'action'      => $action,
	);
}

/** REST permission gate: only an activated public local Person may mutate Follow state. */
function axismundi_act_follow_rest_permission() : bool {
	return axismundi_act_current_local_actor() instanceof Axismundi_Actor;
}

/** Build the canonical REST response for a Follow mutation. */
function axismundi_act_follow_rest_response( Axismundi_Actor $subject, Axismundi_Actor $target ) : WP_REST_Response {
	$state = axismundi_act_follow_button_state( $subject, $target );
	return new WP_REST_Response(
		array(
			'target_uri'  => $target->get_uri(),
			'state'       => $state['state'],
			'follows_you' => $state['follows_you'],
			'legacy'      => $state['legacy'],
			'label'       => $state['label'],
			'action'      => $state['action'],
		),
		200
	);
}

/** Start (or re-start after an imported snapshot) one outgoing Follow. */
function axismundi_act_rest_follow_actor( WP_REST_Request $request ) {
	$subject = axismundi_act_current_local_actor();
	$target  = axismundi_act_follow_rest_target( (string) $request['target_uri'] );
	if ( ! $subject instanceof Axismundi_Actor || is_wp_error( $target ) ) {
		return is_wp_error( $target ) ? $target : new WP_Error( 'ax_act_follow_actor', __( 'No active local Actor is available.', 'axismundi-activities' ), array( 'status' => 403 ) );
	}
	if ( ! axismundi_act_follow_target_available( $subject, $target ) ) {
		return new WP_Error( 'ax_act_follow_target_unavailable', __( 'This Actor cannot be followed.', 'axismundi-activities' ), array( 'status' => 409 ) );
	}
	$before = axismundi_act_follow_button_state( $subject, $target );
	$result = $before['legacy'] && in_array( $before['state'], array( 'accepted', 'legacy_pending' ), true )
		? axismundi_act_refollow_imported_remote_actor( $subject, $target )
		: axismundi_act_follow_actor( $subject, $target );
	return is_wp_error( $result ) ? $result : axismundi_act_follow_rest_response( $subject, $target );
}

/** Undo one outgoing Follow. A concurrent already-undone edge converges to current state. */
function axismundi_act_rest_unfollow_actor( WP_REST_Request $request ) {
	$subject = axismundi_act_current_local_actor();
	$target  = axismundi_act_follow_rest_target( (string) $request['target_uri'] );
	if ( ! $subject instanceof Axismundi_Actor || is_wp_error( $target ) ) {
		return is_wp_error( $target ) ? $target : new WP_Error( 'ax_act_follow_actor', __( 'No active local Actor is available.', 'axismundi-activities' ), array( 'status' => 403 ) );
	}
	if ( ! axismundi_act_follow_target_available( $subject, $target ) ) {
		return new WP_Error( 'ax_act_follow_target_unavailable', __( 'This Actor cannot be unfollowed.', 'axismundi-activities' ), array( 'status' => 409 ) );
	}
	$result = axismundi_act_unfollow_actor( $subject, $target );
	if ( is_wp_error( $result ) && 'ax_act_follow_missing' !== $result->get_error_code() ) {
		return $result;
	}
	return axismundi_act_follow_rest_response( $subject, $target );
}

/** Register Follow mutations. */
function axismundi_act_register_follow_rest_route() : void {
	register_rest_route(
		'axismundi/v1',
		'/follows',
		array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'axismundi_act_rest_follow_actor',
				'permission_callback' => 'axismundi_act_follow_rest_permission',
				'args'                => array( 'target_uri' => array( 'required' => true, 'type' => 'string', 'format' => 'uri' ) ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'axismundi_act_rest_unfollow_actor',
				'permission_callback' => 'axismundi_act_follow_rest_permission',
				'args'                => array( 'target_uri' => array( 'required' => true, 'type' => 'string', 'format' => 'uri' ) ),
			),
		)
	);
	register_rest_route(
		'axismundi/v1',
		'/remote-follow',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'axismundi_act_rest_remote_follow',
			'permission_callback' => '__return_true',
			'args'                => array(
				'target_uri' => array( 'required' => true, 'type' => 'string', 'format' => 'uri' ),
				'resource'   => array( 'required' => true, 'type' => 'string' ),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_act_register_follow_rest_route' );

/** Follow state and a REST nonce are personal; never serve them from shared cache. */
function axismundi_act_prepare_follow_button_cache_policy() : void {
	if ( is_user_logged_in() && function_exists( 'axismundi_actors_current_actor' ) && axismundi_actors_current_actor() instanceof Axismundi_Actor ) {
		axismundi_act_no_cache_like_state();
	}
}
add_action( 'template_redirect', 'axismundi_act_prepare_follow_button_cache_policy', 1 );

/** Render the anonymous remote-follow modal when Dialogs exposes its native surface. */
function axismundi_act_render_remote_follow_modal( Axismundi_Actor $target, string $modal_id, string $profile_url ) : string {
	if ( ! function_exists( 'axismundi_dialogs_render_interaction_dialog' ) ) {
		return '';
	}
	$resource = axismundi_act_remote_follow_target_resource( $target );
	if ( is_wp_error( $resource ) ) {
		return '';
	}
	$name = $target->get_display_name() ?: $target->get_preferred_username();
	ob_start();
	?>
	<p><?php esc_html_e( 'Follow this profile from your own Fediverse account.', 'axismundi-activities' ); ?></p>
	<div class="axismundi-remote-follow__target">
		<label for="<?php echo esc_attr( $modal_id . '-target' ); ?>"><?php esc_html_e( 'Profile to follow', 'axismundi-activities' ); ?></label>
		<input id="<?php echo esc_attr( $modal_id . '-target' ); ?>" type="text" readonly value="<?php echo esc_attr( $resource ); ?>">
		<button type="button" class="axismundi-remote-follow__copy" data-wp-on--click="actions.copyRemoteFollowTarget"><span data-wp-text="context.copyLabel"><?php esc_html_e( 'Copy', 'axismundi-activities' ); ?></span></button>
	</div>
	<form class="axismundi-remote-follow__form" data-wp-on--submit="actions.submitRemoteFollow">
		<label for="<?php echo esc_attr( $modal_id . '-profile' ); ?>"><?php esc_html_e( 'Your Fediverse handle', 'axismundi-activities' ); ?></label>
		<input id="<?php echo esc_attr( $modal_id . '-profile' ); ?>" type="text" autocomplete="url" placeholder="@username@example.com" data-wp-bind--value="context.remoteProfile" data-wp-on--input="actions.updateRemoteFollowProfile" data-wp-bind--aria-invalid="context.remoteError" data-wp-bind--disabled="context.remoteBusy" required>
		<p class="axismundi-remote-follow__help"><?php esc_html_e( 'We will open your server so you can complete the follow there.', 'axismundi-activities' ); ?></p>
		<p class="axismundi-remote-follow__error" role="alert" data-wp-bind--hidden="!context.remoteError" data-wp-text="context.remoteError"></p>
		<div class="wp-block-buttons"><div class="wp-block-button"><button type="submit" class="wp-block-button__link wp-element-button" data-wp-bind--disabled="context.remoteBusy"><span data-wp-bind--hidden="context.remoteBusy"><?php esc_html_e( 'Continue', 'axismundi-activities' ); ?></span><span data-wp-bind--hidden="!context.remoteBusy"><?php esc_html_e( 'Opening…', 'axismundi-activities' ); ?></span></button></div></div>
	</form>
	<p class="axismundi-remote-follow__local"><a href="<?php echo esc_url( wp_login_url( $profile_url ) ); ?>"><?php esc_html_e( 'Log in or create an account on this site instead', 'axismundi-activities' ); ?></a></p>
	<details class="axismundi-remote-follow__about"><summary><?php esc_html_e( 'Why do I need my handle?', 'axismundi-activities' ); ?></summary><p><?php esc_html_e( 'Fediverse accounts live on different servers. Your handle lets us send you to the right server without creating an account here.', 'axismundi-activities' ); ?></p></details>
	<?php
	$body = (string) ob_get_clean();
	return axismundi_dialogs_render_interaction_dialog(
		array(
			'id'              => $modal_id,
			/* translators: %s: actor display name. */
			'title'           => sprintf( __( 'Follow %s', 'axismundi-activities' ), $name ),
			'body'            => $body,
			'close_action'    => 'actions.closeRemoteFollowDialog',
			'cancel_action'   => 'actions.onRemoteFollowDialogCancel',
			'backdrop_action' => 'actions.onRemoteFollowDialogBackdrop',
		)
	);
}

/** Render the dynamic Follow button. */
function axismundi_act_render_follow_button( array $attributes, string $content, WP_Block $block ) : string {
	$target = axismundi_act_follow_button_target( $attributes, $block );
	if ( ! $target instanceof Axismundi_Actor || 'tombstone' === $target->get_status() ) {
		return '';
	}

	$subject = axismundi_act_current_local_actor();
	if ( $subject instanceof Axismundi_Actor && hash_equals( $subject->get_uri(), $target->get_uri() ) ) {
		return '';
	}
	$profile_url = $target->get_profile_url();
	if ( ! is_user_logged_in() ) {
		$modal_id = 'ax-remote-follow-' . wp_unique_id();
		$modal    = axismundi_act_render_remote_follow_modal( $target, $modal_id, $profile_url );
		if ( '' !== $modal ) {
			$context = array(
				'targetUri'          => $target->get_uri(),
				'remoteModalId'      => $modal_id,
				'remoteEndpoint'     => rest_url( 'axismundi/v1/remote-follow' ),
				'remoteProfile'      => '',
				'remoteBusy'         => false,
				'remoteError'        => '',
				'copyDefaultLabel'   => __( 'Copy', 'axismundi-activities' ),
				'copyLabel'          => __( 'Copy', 'axismundi-activities' ),
				'copiedLabel'        => __( 'Copied!', 'axismundi-activities' ),
				'emptyProfileError'  => __( 'Enter your Fediverse handle.', 'axismundi-activities' ),
				'invalidProfileError'=> __( 'Enter a valid Fediverse handle.', 'axismundi-activities' ),
				'remoteFollowError'  => __( 'Your server could not open this follow request.', 'axismundi-activities' ),
			);
			ob_start();
			?>
			<div <?php echo get_block_wrapper_attributes( array( 'class' => 'axismundi-follow-button is-anonymous' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="axismundi/follow-button" <?php echo wp_interactivity_data_wp_context( $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><div class="wp-block-button"><button type="button" class="wp-block-button__link wp-element-button axismundi-follow-button__button" aria-haspopup="dialog" aria-controls="<?php echo esc_attr( $modal_id ); ?>" data-wp-on--click="actions.openRemoteFollowDialog"><?php esc_html_e( 'Follow', 'axismundi-activities' ); ?></button></div><?php echo $modal; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dialog helper renders escaped chrome and this function renders the escaped body. ?></div>
			<?php
			return (string) ob_get_clean();
		}
		ob_start();
		?>
		<div <?php echo get_block_wrapper_attributes( array( 'class' => 'axismundi-follow-button is-anonymous' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><a class="axismundi-follow-button__button" href="<?php echo esc_url( wp_login_url( $profile_url ) ); ?>"><?php esc_html_e( 'Log in to follow', 'axismundi-activities' ); ?></a><a class="axismundi-follow-button__remote" href="https://joinmastodon.org/servers/" rel="external noopener noreferrer" target="_blank"><?php esc_html_e( 'Use another Fediverse server', 'axismundi-activities' ); ?></a></div>
		<?php
		return (string) ob_get_clean();
	}
	if ( ! $subject instanceof Axismundi_Actor ) {
		$profile_admin = current_user_can( 'list_users' ) ? admin_url( 'users.php?page=axismundi-actor-profile' ) : admin_url( 'profile.php?page=axismundi-actor-profile' );
		ob_start();
		?>
		<div <?php echo get_block_wrapper_attributes( array( 'class' => 'axismundi-follow-button is-unavailable' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated block wrapper attributes. ?>><a class="axismundi-follow-button__button" href="<?php echo esc_url( $profile_admin ); ?>"><?php esc_html_e( 'Activate your Actor profile to follow', 'axismundi-activities' ); ?></a></div>
		<?php
		return (string) ob_get_clean();
	}
	if ( ! axismundi_act_follow_target_available( $subject, $target ) ) {
		return '';
	}

	axismundi_act_no_cache_like_state();
	$state   = axismundi_act_follow_button_state( $subject, $target );
	$context = array(
		'targetUri'     => $target->get_uri(),
		'relationState' => $state['state'],
		'followsYou'    => $state['follows_you'],
		'isLegacy'      => $state['legacy'],
		'isPending'     => false,
		'canFollow'     => true,
		'endpoint'      => rest_url( 'axismundi/v1/follows' ),
		'nonce'         => wp_create_nonce( 'wp_rest' ),
		'error'         => '',
		'errorFallback' => __( 'The Follow could not be saved.', 'axismundi-activities' ),
		'labels'        => array(
			'follow'       => __( 'Follow', 'axismundi-activities' ),
			'followBack'   => __( 'Follow back', 'axismundi-activities' ),
			'following'    => __( 'Following', 'axismundi-activities' ),
			'mutual'       => __( 'Mutual', 'axismundi-activities' ),
			'requested'    => __( 'Requested', 'axismundi-activities' ),
			'reFollow'     => __( 'Re-follow', 'axismundi-activities' ),
			'cancel'       => __( 'Cancel follow request', 'axismundi-activities' ),
			'unfollow'     => __( 'Unfollow', 'axismundi-activities' ),
		),
	);
	ob_start();
	?>
	<div <?php echo get_block_wrapper_attributes( array( 'class' => 'axismundi-follow-button' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="axismundi/follow-button" <?php echo wp_interactivity_data_wp_context( $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<button type="button" class="axismundi-follow-button__button" data-wp-on--click="actions.toggleFollow" data-wp-bind--disabled="state.isDisabled" data-wp-bind--aria-pressed="state.isFollowing" data-wp-bind--aria-label="state.actionLabel" data-wp-bind--title="state.actionLabel" data-wp-class--is-following="state.isFollowing" data-wp-class--is-mutual="state.isMutual"><span data-wp-text="state.label"><?php echo esc_html( $state['label'] ); ?></span></button>
		<span class="axismundi-follow-button__status" data-wp-text="context.error" aria-live="polite"></span>
	</div>
	<?php
	return (string) ob_get_clean();
}

/** Register the reusable Follow control block. */
function axismundi_act_register_follow_button_block() : void {
	register_block_type( dirname( __DIR__ ) . '/blocks/follow-button', array( 'render_callback' => 'axismundi_act_render_follow_button' ) );
}
add_action( 'init', 'axismundi_act_register_follow_button_block' );
