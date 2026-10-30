<?php
/** Accounts navigation and capability matrix. Run with wp eval-file. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once dirname( __DIR__ ) . '/includes/admin.php';
require_once WP_PLUGIN_DIR . '/axismundi-contacts/includes/admin.php';
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; };
$old_user = get_current_user_id();
$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
if ( ! $admin ) { throw new RuntimeException( 'Existing administrator required' ); }
try {
	foreach ( array( 'administrator', 'reader' ) as $kind ) {
		wp_set_current_user( 0 ); wp_set_current_user( $admin );
		if ( 'reader' === $kind ) { wp_get_current_user()->allcaps = array( 'read' => true ); }
		$GLOBALS['menu'] = $GLOBALS['submenu'] = $GLOBALS['admin_page_hooks'] = $GLOBALS['_registered_pages'] = $GLOBALS['_parent_pages'] = $GLOBALS['_wp_submenu_nopriv'] = array();
		axismundi_actors_register_accounts_menu();
		axismundi_actors_register_admin_pages();
		axismundi_act_register_follows_page();
		axismundi_contacts_admin_menu();
		axismundi_actors_finish_accounts_menu();
		$parent = axismundi_actors_accounts_menu_slug();
		$pages = array_column( $GLOBALS['submenu'][ $parent ] ?? array(), 2 );
		$assert( in_array( 'axismundi-actor-profile', $pages, true ), $kind . ': Actor Profile under Accounts' );
		$assert( in_array( 'axismundi-contacts', $pages, true ), $kind . ': Contacts under Accounts' );
		$assert( ( 'administrator' === $kind ) === in_array( 'axismundi-follows', $pages, true ), $kind . ': Follows keeps edit_posts gate' );
		$assert( ( 'administrator' === $kind ) === in_array( 'axismundi-remote-actors', $pages, true ), $kind . ': Remote Actors keeps manage_options gate' );
		$assert( ! isset( $GLOBALS['submenu']['users.php'] ) && ! isset( $GLOBALS['submenu']['profile.php'] ), $kind . ': no old Users/Profile entries' );
		$assert( ! in_array( $parent, $pages, true ), $kind . ': no duplicate Accounts child' );
		$assert( ( 'administrator' === $kind ? 'axismundi-all-accounts' : 'axismundi-actor-profile' ) === $pages[0], $kind . ': first accessible child' );
		$assert( ( 'administrator' === $kind ) === in_array( 'axismundi-local-actors', $pages, true ), $kind . ': local inventory requires administration' );
		$assert( ( 'administrator' === $kind ) === in_array( 'axismundi-add-local-actor', $pages, true ), $kind . ': creation retains edit_posts gate' );
		$assert( in_array( 'axismundi-managed-actors', $pages, true ), $kind . ': Managed Actors retained' );
		if ( 'administrator' === $kind ) {
			$assert( array_slice( $pages, 0, 5 ) === array( 'axismundi-all-accounts', 'axismundi-local-actors', 'axismundi-add-local-actor', 'axismundi-remote-actors', 'axismundi-managed-actors' ), 'requested menu order' );
			foreach ( array( 'axismundi_actors_render_all_accounts', 'axismundi_actors_render_local_actors', 'axismundi_actors_render_add_local_actor' ) as $render ) {
				ob_start(); $render(); $html = ob_get_clean();
				$assert( str_contains( $html, '<h1>' ), $render . ': renders' );
			}
		}
		$assert( str_contains( axismundi_actors_admin_url(), '/admin.php?page=axismundi-actor-profile' ), $kind . ': profile URL uses admin parent' );
		$assert( str_contains( axismundi_act_follows_admin_url(), '/admin.php?page=axismundi-follows' ), $kind . ': follow URL uses admin parent' );
		$assert( str_contains( axismundi_contacts_screen_url(), '/admin.php?page=axismundi-contacts' ), $kind . ': contact URL uses admin parent' );
		$assert( get_plugin_page_hookname( 'axismundi-contacts', $parent ) === $GLOBALS['axismundi_contacts_admin_hook'], $kind . ': Contacts assets use registered screen hook' );
		$assert( str_contains( \Axismundi\Actors\Selection\account_selection_page_url(), '/admin.php?page=axismundi-jmap-accounts' ), $kind . ': selection URL independent of list_users' );
	}
} finally {
	wp_set_current_user( 0 ); wp_set_current_user( $old_user );
}
echo "PASS accounts-navigation {$checks}\n";
