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



/**
 * Describe an Announce for the unified interaction block.
 *
 * Two ways to repost, and the template chooses. With `announceMenu` the control opens a menu
 * offering Repost and Quote, which is what a card wants when both are on offer; without it the
 * control reposts directly, which is what a dense row wants when Quote is its own control beside
 * it. The menu is a menu — two commands, an up/down model — rather than the dialog the emoji
 * picker is, because the picker holds a search field and a grid and this holds two verbs.
 *
 * @param array    $attributes Block attributes.
 * @param WP_Block $block      Block instance.
 * @return array<string,mixed>|null
 */
function axismundi_act_describe_announce_interaction( array $attributes, WP_Block $block ) : ?array {
	$object_uri = axismundi_act_like_block_object_uri( $attributes, $block );
	if ( '' === $object_uri ) {
		return null;
	}
	axismundi_act_no_cache_like_state();
	$actor        = axismundi_act_current_local_actor();
	$target       = $actor instanceof Axismundi_Actor ? axismundi_act_resolve_announce_target( $object_uri ) : null;
	$can_announce = $actor instanceof Axismundi_Actor && ! is_wp_error( $target ) && true === axismundi_act_can_announce_object( $actor, $object_uri );
	$quote_url    = $actor instanceof Axismundi_Actor ? (string) apply_filters( 'axismundi_act_quote_compose_url', '', $object_uri ) : '';
	$is_announced = $actor instanceof Axismundi_Actor ? axismundi_act_get_announce_state( $actor->get_uri(), $object_uri ) : false;
	/*
	 * A menu is advertised only when a menu is actually rendered.
	 *
	 * A reader who cannot repost gets no menu, so promising one with `aria-haspopup` — and
	 * pointing `aria-controls` at an id that is not on the page — describes a control that does
	 * not exist. The disabled button already says the true thing on its own.
	 */
	$delegated    = axismundi_act_interaction_is_delegated();
	$renders_menu = ! empty( $attributes['announceMenu'] ) && $can_announce;
	$menu_id      = $renders_menu ? wp_unique_id( 'ax-announce-menu-' ) : '';
	$anchor_id    = $renders_menu ? wp_unique_id( 'ax-announce-trigger-' ) : '';
	axismundi_act_seed_announce_menu_state();

	$context = array(
		'objectUri'     => $object_uri,
		'announces'     => axismundi_act_get_announce_count( $object_uri ),
		'isAnnounced'   => $is_announced,
		'isPending'     => false,
		'canAnnounce'   => $can_announce,
		'isDisabled'    => ! $can_announce,
		'menuId'        => $menu_id,
		'anchorId'      => $anchor_id,
		'quoteUrl'      => $quote_url,
		'endpoint'      => rest_url( 'axismundi/v1/announces' ),
		'nonce'         => $can_announce ? wp_create_nonce( 'wp_rest' ) : '',
		'error'         => '',
		'errorFallback' => __( 'The repost could not be saved.', 'axismundi-activities' ),
	);

	$after = $renders_menu && ! $delegated ? axismundi_act_render_announce_menu( $menu_id ) : '';

	$bindings = array(
		'data-wp-on--click'          => $renders_menu ? 'actions.toggleMenu' : 'actions.toggleAnnounce',
		'data-wp-bind--disabled'     => 'context.isDisabled',
		'data-wp-class--is-selected' => 'context.isAnnounced',
		'data-wp-bind--aria-pressed' => 'context.isAnnounced',
	);
	if ( $renders_menu ) {
		$bindings['aria-haspopup'] = 'menu';
		$bindings['aria-controls'] = $menu_id;
		$bindings['id']            = $anchor_id;
		$bindings['data-wp-bind--aria-expanded'] = 'state.isMenuOpen';
	}

	return array(
		'icon'       => 'sync',
		'label'      => __( 'Repost', 'axismundi-activities' ),
		'aria_label' => $can_announce
			? ( $renders_menu ? __( 'Repost or quote', 'axismundi-activities' ) : __( 'Repost', 'axismundi-activities' ) )
			: ( is_user_logged_in() ? __( 'Activate a public Actor profile to repost.', 'axismundi-activities' ) : __( 'Log in to repost.', 'axismundi-activities' ) ),
		'count'      => (int) $context['announces'],
		'count_bind' => 'context.announces',
		'selected'   => $is_announced,
		'toggle'     => true,
		'disabled'   => ! $can_announce,
		'namespace'  => 'axismundi/announce-button',
		'module'     => 'axismundi-interaction-announce',
		'context'    => $context,
		'bindings'   => $bindings,
		'delegated'  => array(
			'data-ax-action'     => $renders_menu ? 'announce-menu' : 'announce',
			'data-ax-object-uri' => $object_uri,
			'data-ax-endpoint'   => (string) $context['endpoint'],
			'data-ax-nonce'      => (string) $context['nonce'],
		) + ( $renders_menu ? array(
			'id'                 => $anchor_id,
			'aria-haspopup'      => 'menu',
			'aria-expanded'      => 'false',
			'data-ax-quote-url'  => $quote_url,
		) : array() ),
		'after'      => $after,
	);
}

/** Seed server-visible values for menu directives whose client getters take over on hydration. */
function axismundi_act_seed_announce_menu_state() : void {
	static $seeded = false;
	if ( $seeded ) {
		return;
	}
	wp_interactivity_state(
		'axismundi/announce-button',
		array(
			'menuOpenFor' => '',
			'isMenuOpen'  => false,
			'isMenuHidden' => true,
		)
	);
	$seeded = true;
}

/** The compact command menu shared by a standalone Announce and the feed-level host. */
function axismundi_act_render_announce_menu( string $menu_id ) : string {
	return '<div class="axismundi-announce-menu" role="menu" id="' . esc_attr( $menu_id ) . '" hidden'
		. ' data-wp-bind--hidden="state.isMenuHidden" data-wp-class--is-open="state.isMenuOpen">'
		. '<button type="button" role="menuitem" class="axismundi-announce-menu__action" data-wp-on--click="actions.toggleAnnounce" data-wp-bind--disabled="context.isDisabled">'
		. '<span class="material-symbols-outlined" aria-hidden="true">sync</span> <span data-wp-text="state.announceLabel">' . esc_html__( 'Repost', 'axismundi-activities' ) . '</span></button>'
		. '<a role="menuitem" class="axismundi-announce-menu__action" data-wp-bind--href="context.quoteUrl" data-wp-bind--hidden="!context.quoteUrl">'
		. '<span class="material-symbols-outlined" aria-hidden="true">format_quote</span> ' . esc_html__( 'Quote', 'axismundi-activities' ) . '</a>'
		. '</div>';
}

/** Render the one hydrated announce menu a delegated activity feed shares. */
function axismundi_act_render_feed_announce_menu_host() : string {
	$actor = axismundi_act_current_local_actor();
	if ( ! $actor instanceof Axismundi_Actor ) {
		return '';
	}
	axismundi_act_seed_announce_menu_state();
	if ( function_exists( 'wp_enqueue_script_module' ) ) {
		wp_enqueue_script_module( 'axismundi-interaction-announce' );
	}
	$menu_id = wp_unique_id( 'ax-announce-feed-' );
	$context = array(
		'objectUri'     => '',
		'menuId'        => $menu_id,
		'anchorId'      => '',
		'quoteUrl'      => '',
		'announces'     => 0,
		'isAnnounced'   => false,
		'isPending'     => false,
		'canAnnounce'   => false,
		'isDisabled'    => true,
		'endpoint'      => rest_url( 'axismundi/v1/announces' ),
		'nonce'         => wp_create_nonce( 'wp_rest' ),
		'error'         => '',
		'errorFallback' => __( 'The repost could not be saved.', 'axismundi-activities' ),
	);
	return '<div class="axismundi-announce-menu-host" data-wp-interactive="axismundi/announce-button" '
		. wp_interactivity_data_wp_context( $context )
		. ' data-wp-on-document--click="actions.openFeedMenu" data-wp-watch="callbacks.menuLifecycle">'
		. axismundi_act_render_announce_menu( $menu_id )
		. '<span class="axismundi-interaction__status" data-wp-text="context.error" aria-live="polite"></span>'
		. '</div>';
}

/** Offer Announce as an interaction type. */
function axismundi_act_register_announce_interaction_type() : void {
	if ( function_exists( 'axismundi_act_register_interaction_type' ) ) {
		axismundi_act_register_interaction_type(
			'announce',
			array(
				'describe' => 'axismundi_act_describe_announce_interaction',
				'label'    => __( 'Repost', 'axismundi-activities' ),
				'icon'     => 'sync',
			)
		);
	}
}
add_action( 'axismundi_act_register_interaction_types', 'axismundi_act_register_announce_interaction_type' );
