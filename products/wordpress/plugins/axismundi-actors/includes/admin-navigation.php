<?php
/** Account administration navigation, independent of WordPress User profiles. */
defined( 'ABSPATH' ) || exit;

function axismundi_actors_accounts_menu_slug() : string { return 'axismundi-accounts'; }

/** Keep existing page identifiers and query arguments when moving their parent. */
function axismundi_actors_account_admin_url( string $page, array $args = array() ) : string {
	return add_query_arg( array_merge( $args, array( 'page' => $page ) ), admin_url( 'admin.php' ) );
}

function axismundi_actors_register_accounts_menu() : void {
	add_menu_page( __( 'Accounts', 'axismundi-actors' ), __( 'Accounts', 'axismundi-actors' ), 'read', axismundi_actors_accounts_menu_slug(), 'axismundi_actors_render_accounts_landing', 'dashicons-id' );
	add_submenu_page( axismundi_actors_accounts_menu_slug(), 'All Accounts', 'All Accounts', 'manage_options', 'axismundi-all-accounts', 'axismundi_actors_render_all_accounts' );
	add_submenu_page( axismundi_actors_accounts_menu_slug(), 'Local Actors', 'Local Actors', 'manage_options', 'axismundi-local-actors', 'axismundi_actors_render_local_actors' );
	add_submenu_page( axismundi_actors_accounts_menu_slug(), 'Add Local Actor', 'Add Local Actor', 'edit_posts', 'axismundi-add-local-actor', 'axismundi_actors_render_add_local_actor' );
}
add_action( 'admin_menu', 'axismundi_actors_register_accounts_menu', 5 );
function axismundi_actors_finish_accounts_menu() : void {
	remove_submenu_page( axismundi_actors_accounts_menu_slug(), axismundi_actors_accounts_menu_slug() );
	global $submenu;
	$parent = axismundi_actors_accounts_menu_slug();
	$order = array_flip( array( 'axismundi-all-accounts', 'axismundi-local-actors', 'axismundi-add-local-actor', 'axismundi-remote-actors', 'axismundi-managed-actors' ) );
	if ( ! current_user_can( 'manage_options' ) ) { $order['axismundi-actor-profile'] = -1; }
	if ( isset( $submenu[ $parent ] ) ) {
		usort( $submenu[ $parent ], static fn( $a, $b ) => ( $order[ $a[2] ] ?? 100 ) <=> ( $order[ $b[2] ] ?? 100 ) );
	}
}
add_action( 'admin_menu', 'axismundi_actors_finish_accounts_menu', 100 );

/** Preserve bookmarked Users/Profile URLs without intercepting writes or unrelated pages. */
function axismundi_actors_redirect_legacy_account_admin_page() : void {
	global $pagenow;
	if ( ! in_array( $pagenow, array( 'users.php', 'profile.php' ), true ) || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) { return; }
	$page = $_GET['page'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
	if ( ! is_string( $page ) || ! in_array( $page, array( 'axismundi-actor-profile', 'axismundi-remote-actors', 'axismundi-managed-actors', 'axismundi-jmap-accounts', 'axismundi-follows', 'axismundi-contacts' ), true ) ) { return; }
	nocache_headers();
	wp_safe_redirect( axismundi_actors_account_admin_url( $page, wp_unslash( $_GET ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- preserve read-only navigation arguments.
	exit;
}
// Admin's removed-parent permission check runs before admin_init.
add_action( 'init', 'axismundi_actors_redirect_legacy_account_admin_page', 0 );
