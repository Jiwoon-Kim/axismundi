<?php
/**
 * Announce REST mutation and Interactivity API block.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** Resolve one cached or locally projected object without network access. */
function axismundi_act_resolve_announce_target( string $object_uri ) {
	$uri = axismundi_act_uri( $object_uri );
	if ( '' === $uri ) {
		return new WP_Error( 'ax_act_announce_target', __( 'The object URI is invalid.', 'axismundi-activities' ) );
	}
	$missing = new WP_Error( 'ax_act_announce_target_missing', __( 'The object is not available as a public local projection or public remote observation.', 'axismundi-activities' ), array( 'status' => 404 ) );
	/** @param array<string,mixed>|WP_Error $target Resolved target. @param string $uri Canonical object URI. */
	return apply_filters( 'axismundi_act_resolve_announce_target', $missing, $uri );
}

/** Register the Announce mutation endpoint. */
function axismundi_act_register_announce_rest_route() : void {
	register_rest_route(
		'axismundi/v1',
		'/announces',
		array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'axismundi_act_rest_announce_object',
				'permission_callback' => 'axismundi_act_like_rest_permission',
				'args'                => array( 'object_uri' => array( 'required' => true, 'type' => 'string', 'format' => 'uri' ) ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'axismundi_act_rest_unannounce_object',
				'permission_callback' => 'axismundi_act_like_rest_permission',
				'args'                => array( 'object_uri' => array( 'required' => true, 'type' => 'string', 'format' => 'uri' ) ),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_act_register_announce_rest_route' );

/** Build the authoritative mutation response. */
function axismundi_act_announce_rest_response( Axismundi_Actor $actor, string $object_uri, Axismundi_Activity $activity ) : WP_REST_Response {
	return new WP_REST_Response(
		array(
			'object_uri'     => $object_uri,
			'is_announced'   => axismundi_act_get_announce_state( $actor->get_uri(), $object_uri ),
			'announce_count' => axismundi_act_get_announce_count( $object_uri ),
			'activity_uri'   => $activity->get_uri(),
		),
		200
	);
}

/** Handle Announce. */
function axismundi_act_rest_announce_object( WP_REST_Request $request ) {
	$actor  = axismundi_act_current_local_actor();
	$target = axismundi_act_resolve_announce_target( (string) $request['object_uri'] );
	if ( ! $actor instanceof Axismundi_Actor || is_wp_error( $target ) ) {
		return is_wp_error( $target ) ? $target : new WP_Error( 'ax_act_announce_actor', __( 'No active local Actor is available.', 'axismundi-activities' ), array( 'status' => 403 ) );
	}
	$activity = axismundi_act_announce_object( $actor, (string) $target['object_uri'], (string) $target['recipient_uri'] );
	return is_wp_error( $activity ) ? $activity : axismundi_act_announce_rest_response( $actor, (string) $target['object_uri'], $activity );
}

/** Handle Undo(Announce). */
function axismundi_act_rest_unannounce_object( WP_REST_Request $request ) {
	$actor = axismundi_act_current_local_actor();
	$uri   = axismundi_act_uri( (string) $request['object_uri'] );
	if ( ! $actor instanceof Axismundi_Actor || '' === $uri ) {
		return new WP_Error( 'ax_act_announce_actor', __( 'No active local Actor or valid object is available.', 'axismundi-activities' ), array( 'status' => 403 ) );
	}
	$activity = axismundi_act_unannounce_object( $actor, $uri );
	return is_wp_error( $activity ) ? $activity : axismundi_act_announce_rest_response( $actor, $uri, $activity );
}

/** Mark pages containing the Boost block as private to shared caches for logged-in users. */
function axismundi_act_prepare_announce_button_cache_policy() : void {
	$post = get_queried_object();
	if ( $post instanceof WP_Post && ( has_block( 'axismundi/boost-button', $post ) || has_block( 'axismundi/announce-button', $post ) ) ) {
		axismundi_act_no_cache_like_state();
	}
}
add_action( 'template_redirect', 'axismundi_act_prepare_announce_button_cache_policy', 1 );

/** Render the reusable Repost/Quote menu for the current projected Object. */
function axismundi_act_render_announce_button( array $attributes, string $content, WP_Block $block ) : string {
	$object_uri = axismundi_act_like_block_object_uri( $attributes, $block );
	if ( '' === $object_uri ) {
		return '';
	}
	axismundi_act_no_cache_like_state();
	$actor        = axismundi_act_current_local_actor();
	$target       = $actor instanceof Axismundi_Actor ? axismundi_act_resolve_announce_target( $object_uri ) : null;
	$can_announce = $actor instanceof Axismundi_Actor && ! is_wp_error( $target ) && true === axismundi_act_can_announce_object( $actor, $object_uri );
	$quote_url    = $actor instanceof Axismundi_Actor ? (string) apply_filters( 'axismundi_act_quote_compose_url', '', $object_uri ) : '';
	$dialog_id    = 'ax-announce-menu-' . wp_unique_id();
	$has_dialog   = function_exists( 'axismundi_dialogs_render_interaction_dialog' );
	$context      = array(
		'objectUri'     => $object_uri,
		'announces'     => axismundi_act_get_announce_count( $object_uri ),
		'isAnnounced'   => $actor instanceof Axismundi_Actor ? axismundi_act_get_announce_state( $actor->get_uri(), $object_uri ) : false,
		'isPending'     => false,
		'canAnnounce'   => $can_announce,
		'dialogId'      => $has_dialog ? $dialog_id : '',
		'endpoint'      => rest_url( 'axismundi/v1/announces' ),
		'nonce'         => $can_announce ? wp_create_nonce( 'wp_rest' ) : '',
		'error'         => '',
		'errorFallback' => __( 'The repost could not be saved.', 'axismundi-activities' ),
	);
	$label = $has_dialog && $actor instanceof Axismundi_Actor
		? __( 'Repost or quote', 'axismundi-activities' )
		: ( is_user_logged_in() ? __( 'Activate a public Actor profile to repost or quote.', 'axismundi-activities' ) : __( 'Log in to repost or quote.', 'axismundi-activities' ) );
	$dialog = '';
	if ( $has_dialog && $actor instanceof Axismundi_Actor ) {
		ob_start();
		?>
		<div class="axismundi-announce-menu">
			<button type="button" class="axismundi-announce-menu__action" data-wp-on--click="actions.toggleAnnounce" data-wp-bind--disabled="state.isDisabled"><span class="material-symbols-outlined" aria-hidden="true">sync</span> <span data-wp-text="state.announceLabel"><?php esc_html_e( 'Repost', 'axismundi-activities' ); ?></span></button>
			<?php if ( '' !== $quote_url ) : ?>
				<a class="axismundi-announce-menu__action" href="<?php echo esc_url( $quote_url ); ?>"><span class="material-symbols-outlined" aria-hidden="true">format_quote</span> <?php esc_html_e( 'Quote', 'axismundi-activities' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
		$dialog = axismundi_dialogs_render_interaction_dialog(
			array(
				'id'              => $dialog_id,
				'title'           => __( 'Repost or quote', 'axismundi-activities' ),
				'body'            => (string) ob_get_clean(),
				'close_action'    => 'actions.closeMenu',
				'cancel_action'   => 'actions.onMenuCancel',
				'backdrop_action' => 'actions.onMenuBackdrop',
			)
		);
	}
	ob_start();
	?>
	<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="axismundi/announce-button" <?php echo wp_interactivity_data_wp_context( $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<button type="button" class="axismundi-announce-button__button" data-wp-on--click="actions.openMenu" data-wp-bind--aria-pressed="context.isAnnounced" data-wp-bind--disabled="state.isDisabled" data-wp-class--is-announced="context.isAnnounced" aria-label="<?php echo esc_attr( $label ); ?>" title="<?php echo esc_attr( $label ); ?>" aria-haspopup="dialog" aria-controls="<?php echo esc_attr( $has_dialog ? $dialog_id : '' ); ?>"<?php disabled( ! $has_dialog || ! ( $actor instanceof Axismundi_Actor ) ); ?>>
			<span class="material-symbols-outlined" aria-hidden="true">sync</span>
			<?php if ( ! empty( $attributes['showLabel'] ) ) : ?>
				<span class="axismundi-announce-button__label" aria-hidden="true"><?php esc_html_e( 'Repost', 'axismundi-activities' ); ?></span>
			<?php endif; ?>
			<?php if ( ! isset( $attributes['showCount'] ) || (bool) $attributes['showCount'] ) : ?>
				<span class="axismundi-announce-button__count" data-wp-text="context.announces" aria-hidden="true"><?php echo esc_html( number_format_i18n( $context['announces'] ) ); ?></span>
			<?php endif; ?>
		</button>
		<span class="axismundi-announce-button__status" data-wp-text="context.error" aria-live="polite"></span>
		<?php echo $dialog; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dialog helper and body escape all dynamic values. ?>
	</div>
	<?php
	return (string) ob_get_clean();
}

/** Register the nested Announce menu block. */
function axismundi_act_register_announce_button_block() : void {
	register_block_type( dirname( __DIR__ ) . '/blocks/announce-button', array( 'render_callback' => 'axismundi_act_render_announce_button' ) );
}
add_action( 'init', 'axismundi_act_register_announce_button_block' );

/** Render the dynamic Boost button. */
function axismundi_act_render_boost_button( array $attributes, string $content, WP_Block $block ) : string {
	$object_uri = axismundi_act_like_block_object_uri( $attributes, $block );
	if ( '' === $object_uri ) {
		return '';
	}
	axismundi_act_no_cache_like_state();
	$actor       = axismundi_act_current_local_actor();
	$target      = $actor instanceof Axismundi_Actor ? axismundi_act_resolve_announce_target( $object_uri ) : null;
	$can_announce = $actor instanceof Axismundi_Actor && ! is_wp_error( $target ) && true === axismundi_act_can_announce_object( $actor, $object_uri );
	$context     = array(
		'objectUri'     => $object_uri,
		'announces'     => axismundi_act_get_announce_count( $object_uri ),
		'isAnnounced'   => $actor instanceof Axismundi_Actor ? axismundi_act_get_announce_state( $actor->get_uri(), $object_uri ) : false,
		'isPending'     => false,
		'canAnnounce'   => $can_announce,
		'endpoint'      => rest_url( 'axismundi/v1/announces' ),
		'nonce'         => $can_announce ? wp_create_nonce( 'wp_rest' ) : '',
		'error'         => '',
		'errorFallback' => __( 'The Boost could not be saved.', 'axismundi-activities' ),
	);
	$label = $can_announce ? __( 'Boost', 'axismundi-activities' ) : ( is_user_logged_in() ? __( 'This object cannot be boosted.', 'axismundi-activities' ) : __( 'Log in to Boost.', 'axismundi-activities' ) );
	ob_start();
	?>
	<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="axismundi/boost-button" <?php echo wp_interactivity_data_wp_context( $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<button type="button" class="axismundi-boost-button__button" data-wp-on--click="actions.toggleAnnounce" data-wp-bind--aria-pressed="context.isAnnounced" data-wp-bind--disabled="state.isDisabled" data-wp-class--is-announced="context.isAnnounced" aria-label="<?php echo esc_attr( $label ); ?>" title="<?php echo esc_attr( $label ); ?>"<?php disabled( ! $can_announce ); ?>>
			<span class="material-symbols-outlined" aria-hidden="true">sync</span>
			<span class="axismundi-boost-button__count" data-wp-text="context.announces" aria-hidden="true"><?php echo esc_html( number_format_i18n( $context['announces'] ) ); ?></span>
		</button>
		<span class="axismundi-boost-button__status" data-wp-text="context.error" aria-live="polite"></span>
	</div>
	<?php
	return (string) ob_get_clean();
}

/** Register the dynamic Boost block. */
function axismundi_act_register_boost_button_block() : void {
	register_block_type( dirname( __DIR__ ) . '/blocks/boost-button', array( 'render_callback' => 'axismundi_act_render_boost_button' ) );
}
add_action( 'init', 'axismundi_act_register_boost_button_block' );
