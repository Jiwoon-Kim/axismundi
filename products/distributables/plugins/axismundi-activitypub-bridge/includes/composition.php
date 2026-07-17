<?php
/**
 * Compose the official plugin's protocol surfaces with Axismundi domain stores.
 *
 * @package AxismundiActivityPubBridge
 */

defined( 'ABSPATH' ) || exit;

/** Remove one registered callback without requiring its class to be loaded here. */
function axismundi_activitypub_bridge_remove_callback( string $hook, string $class, string $method, int $priority = 10 ) : void {
	remove_action( $hook, array( $class, $method ), $priority );
	remove_filter( $hook, array( $class, $method ), $priority );
}

/**
 * Unhook the official Inbox domain handlers after they register themselves.
 *
 * Signature verification and REST request validation remain controller-owned.
 */
function axismundi_activitypub_bridge_unregister_inbox_handlers() : void {
	if ( ! axismundi_activitypub_bridge_ready() ) {
		return;
	}

	$callbacks = array(
		array( 'activitypub_inbox_accept', 'Activitypub\\Handler\\Accept', 'handle_accept' ),
		array( 'activitypub_validate_object', 'Activitypub\\Handler\\Accept', 'validate_object' ),
		array( 'activitypub_inbox_announce', 'Activitypub\\Handler\\Announce', 'handle_announce' ),
		array( 'activitypub_inbox_create', 'Activitypub\\Handler\\Collection_Sync', 'handle_collection_synchronization' ),
		array( 'http_request_args', 'Activitypub\\Handler\\Collection_Sync', 'maybe_add_headers', -1 ),
		array( 'activitypub_handled_inbox_create', 'Activitypub\\Handler\\Create', 'handle_create' ),
		array( 'activitypub_validate_object', 'Activitypub\\Handler\\Create', 'validate_object' ),
		array( 'post_activitypub_add_to_outbox', 'Activitypub\\Handler\\Create', 'maybe_unbury' ),
		array( 'activitypub_inbox_delete', 'Activitypub\\Handler\\Delete', 'handle_delete' ),
		array( 'activitypub_skip_inbox_storage', 'Activitypub\\Handler\\Delete', 'skip_inbox_storage' ),
		array( 'activitypub_defer_signature_verification', 'Activitypub\\Handler\\Delete', 'defer_signature_verification' ),
		array( 'activitypub_delete_remote_actor_interactions', 'Activitypub\\Handler\\Delete', 'delete_interactions' ),
		array( 'activitypub_delete_remote_actor_posts', 'Activitypub\\Handler\\Delete', 'delete_posts' ),
		array( 'activitypub_get_outbox_activity', 'Activitypub\\Handler\\Delete', 'outbox_activity' ),
		array( 'post_activitypub_add_to_outbox', 'Activitypub\\Handler\\Delete', 'maybe_bury' ),
		array( 'activitypub_inbox_feature_request', 'Activitypub\\Handler\\Feature_Request', 'handle_feature_request' ),
		array( 'activitypub_rest_inbox_disallowed', 'Activitypub\\Handler\\Feature_Request', 'handle_blocked_request' ),
		array( 'activitypub_validate_object', 'Activitypub\\Handler\\Feature_Request', 'validate_object' ),
		array( 'activitypub_inbox_follow', 'Activitypub\\Handler\\Follow', 'handle_follow' ),
		array( 'activitypub_handled_follow', 'Activitypub\\Handler\\Follow', 'queue_accept' ),
		array( 'activitypub_inbox_shared_follow', 'Activitypub\\Handler\\Follow', 'reject_application_follow' ),
		array( 'activitypub_inbox_like', 'Activitypub\\Handler\\Like', 'handle_like' ),
		array( 'activitypub_get_outbox_activity', 'Activitypub\\Handler\\Like', 'outbox_activity' ),
		array( 'activitypub_inbox_move', 'Activitypub\\Handler\\Move', 'handle_move' ),
		array( 'activitypub_inbox_quote_request', 'Activitypub\\Handler\\Quote_Request', 'handle_quote_request' ),
		array( 'activitypub_rest_inbox_disallowed', 'Activitypub\\Handler\\Quote_Request', 'handle_blocked_request' ),
		array( 'delete_comment', 'Activitypub\\Handler\\Quote_Request', 'handle_quote_delete' ),
		array( 'activitypub_validate_object', 'Activitypub\\Handler\\Quote_Request', 'validate_object' ),
		array( 'activitypub_inbox_reject', 'Activitypub\\Handler\\Reject', 'handle_reject' ),
		array( 'activitypub_validate_object', 'Activitypub\\Handler\\Reject', 'validate_object' ),
		array( 'activitypub_inbox_undo', 'Activitypub\\Handler\\Undo', 'handle_undo' ),
		array( 'activitypub_validate_object', 'Activitypub\\Handler\\Undo', 'validate_object' ),
		array( 'activitypub_handled_inbox_update', 'Activitypub\\Handler\\Update', 'handle_update' ),
	);

	foreach ( $callbacks as $callback ) {
		axismundi_activitypub_bridge_remove_callback( $callback[0], $callback[1], $callback[2], $callback[3] ?? 10 );
	}
}
add_action( 'activitypub_register_handlers', 'axismundi_activitypub_bridge_unregister_inbox_handlers', 100 );

/** Unhook official publication schedulers while Axismundi owns local lifecycle state. */
function axismundi_activitypub_bridge_unregister_domain_schedulers() : void {
	if ( ! axismundi_activitypub_bridge_ready() ) {
		return;
	}

	$callbacks = array(
		array( 'wp_after_insert_post', 'Activitypub\\Scheduler\\Post', 'triage', 33 ),
		array( 'add_attachment', 'Activitypub\\Scheduler\\Post', 'transition_attachment_status' ),
		array( 'edit_attachment', 'Activitypub\\Scheduler\\Post', 'transition_attachment_status' ),
		array( 'delete_attachment', 'Activitypub\\Scheduler\\Post', 'transition_attachment_status' ),
		array( 'post_stuck', 'Activitypub\\Scheduler\\Post', 'schedule_featured_add' ),
		array( 'post_unstuck', 'Activitypub\\Scheduler\\Post', 'schedule_featured_remove' ),
		array( 'transition_post_status', 'Activitypub\\Scheduler\\Actor', 'schedule_post_activity', 33 ),
		array( 'post_stuck', 'Activitypub\\Scheduler\\Actor', 'sticky_post_update' ),
		array( 'post_unstuck', 'Activitypub\\Scheduler\\Actor', 'sticky_post_update' ),
		array( 'update_option_site_icon', 'Activitypub\\Scheduler\\Actor', 'blog_user_update' ),
		array( 'update_option_blogdescription', 'Activitypub\\Scheduler\\Actor', 'blog_user_update' ),
		array( 'update_option_blogname', 'Activitypub\\Scheduler\\Actor', 'blog_user_update' ),
		array( 'add_option_activitypub_header_image', 'Activitypub\\Scheduler\\Actor', 'blog_user_update' ),
		array( 'update_option_activitypub_header_image', 'Activitypub\\Scheduler\\Actor', 'blog_user_update' ),
		array( 'add_option_activitypub_blog_identifier', 'Activitypub\\Scheduler\\Actor', 'blog_user_update' ),
		array( 'update_option_activitypub_blog_identifier', 'Activitypub\\Scheduler\\Actor', 'blog_user_update' ),
		array( 'add_option_activitypub_blog_description', 'Activitypub\\Scheduler\\Actor', 'blog_user_update' ),
		array( 'update_option_activitypub_blog_description', 'Activitypub\\Scheduler\\Actor', 'blog_user_update' ),
		array( 'pre_set_theme_mod_custom_logo', 'Activitypub\\Scheduler\\Actor', 'blog_user_update' ),
		array( 'pre_set_theme_mod_header_image', 'Activitypub\\Scheduler\\Actor', 'blog_user_update' ),
		array( 'profile_update', 'Activitypub\\Scheduler\\Actor', 'user_update' ),
		array( 'added_user_meta', 'Activitypub\\Scheduler\\Actor', 'user_meta_update' ),
		array( 'updated_user_meta', 'Activitypub\\Scheduler\\Actor', 'user_meta_update' ),
		array( 'add_option_activitypub_actor_mode', 'Activitypub\\Scheduler\\Actor', 'blog_user_update' ),
		array( 'update_option_activitypub_actor_mode', 'Activitypub\\Scheduler\\Actor', 'blog_user_update' ),
		array( 'add_option_activitypub_default_feature_policy', 'Activitypub\\Scheduler\\Actor', 'schedule_all_profile_updates' ),
		array( 'update_option_activitypub_default_feature_policy', 'Activitypub\\Scheduler\\Actor', 'schedule_all_profile_updates' ),
		array( 'delete_user', 'Activitypub\\Scheduler\\Actor', 'schedule_user_delete' ),
		array( 'post_types_to_delete_with_user', 'Activitypub\\Scheduler\\Actor', 'post_types_to_delete_with_user' ),
		array( 'transition_comment_status', 'Activitypub\\Scheduler\\Comment', 'schedule_comment_activity', 20 ),
		array( 'wp_insert_comment', 'Activitypub\\Scheduler\\Comment', 'schedule_comment_activity_on_insert' ),
		array( 'delete_comment', 'Activitypub\\Scheduler\\Comment', 'schedule_comment_delete_activity' ),
		array( 'activitypub_collection_sync', 'Activitypub\\Scheduler\\Collection_Sync', 'schedule_reconciliation' ),
		array( 'activitypub_followers_sync_reconcile', 'Activitypub\\Scheduler\\Collection_Sync', 'reconcile_followers' ),
	);

	foreach ( $callbacks as $callback ) {
		axismundi_activitypub_bridge_remove_callback( $callback[0], $callback[1], $callback[2], $callback[3] ?? 10 );
	}
}
add_action( 'activitypub_register_schedulers', 'axismundi_activitypub_bridge_unregister_domain_schedulers', 100 );

/** Remove notification materialization owned by the official domain model. */
function axismundi_activitypub_bridge_unregister_mailer_handlers() : void {
	if ( ! axismundi_activitypub_bridge_ready() ) {
		return;
	}

	axismundi_activitypub_bridge_remove_callback( 'activitypub_handled_follow', 'Activitypub\\Mailer', 'new_follower' );
	axismundi_activitypub_bridge_remove_callback( 'activitypub_inbox_create', 'Activitypub\\Mailer', 'direct_message' );
	axismundi_activitypub_bridge_remove_callback( 'activitypub_inbox_create', 'Activitypub\\Mailer', 'mention', 20 );
}
add_action( 'init', 'axismundi_activitypub_bridge_unregister_mailer_handlers', 100 );

/**
 * Yield the public presentation routes to Object Projections.
 *
 * This runs after Router::init() but before its priority-11 rewrite callback.
 */
function axismundi_activitypub_bridge_unregister_presentation_router() : void {
	if ( ! axismundi_activitypub_bridge_ready() ) {
		return;
	}

	$callbacks = array(
		array( 'init', 'Activitypub\\Router', 'add_rewrite_rules', 11 ),
		array( 'send_headers', 'Activitypub\\Router', 'add_headers' ),
		array( 'template_include', 'Activitypub\\Router', 'render_activitypub_template', 99 ),
		array( 'template_redirect', 'Activitypub\\Router', 'template_redirect' ),
		array( 'redirect_canonical', 'Activitypub\\Router', 'redirect_canonical' ),
		array( 'redirect_canonical', 'Activitypub\\Router', 'no_trailing_redirect' ),
		array( 'query_vars', 'Activitypub\\Router', 'add_query_vars' ),
		array( 'parse_query', 'Activitypub\\Router', 'fix_is_home_check' ),
	);
	foreach ( $callbacks as $callback ) {
		axismundi_activitypub_bridge_remove_callback( $callback[0], $callback[1], $callback[2], $callback[3] ?? 10 );
	}
}
add_action( 'init', 'axismundi_activitypub_bridge_unregister_presentation_router', 10 );

/** Disable the two pre-init surfaces that do not expose a behavior registration seam. */
function axismundi_activitypub_bridge_disable_pre_init_conflicts() : void {
	if ( ! axismundi_activitypub_bridge_ready() ) {
		return;
	}

	// Object Projections owns the public Actor/Object routes, including /@handle.
	remove_action( 'init', array( 'Activitypub\\Router', 'init' ) );

	// Transitional compatibility with the superseded experimental fork worker.
	if ( class_exists( 'Activitypub\\External_Delivery' ) ) {
		remove_action( 'init', array( 'Activitypub\\External_Delivery', 'init' ) );
	}
}
add_action( 'plugins_loaded', 'axismundi_activitypub_bridge_disable_pre_init_conflicts', 50 );
