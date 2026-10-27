<?php
/** User folders and feeds survive removal of their AP identity. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void { ++$checks; if ( ! $ok ) { throw new RuntimeException( $label ); } };
$user = 0;
$terms = array();
$old_user = get_current_user_id();
$snapshot = static function () use ( $wpdb ) : array {
	$rows = array();
	foreach ( array( 'ax_identities', 'ax_actors', 'ax_actor_managers' ) as $suffix ) {
		$table = $wpdb->prefix . $suffix;
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ) { $rows[ $table ] = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY 1,2,3", ARRAY_A ); }
	}
	return $rows;
};
$before = $snapshot();
try {
	$assert( ! function_exists( 'axismundi_media_folder_identity_uuid' ) && ! function_exists( 'axismundi_media_folder_actor_uri' ), 'No folder federation producer remains' );
	$user = wp_insert_user( array( 'user_login' => 'axfolder' . wp_generate_password( 12, false, false ), 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$assert( ! is_wp_error( $user ), 'Fixture User created without an Actor' );
	wp_set_current_user( $user );
	$folder = axismundi_media_create_folder( 'Local folder', 0, $user );
	$assert( ! is_wp_error( $folder ), 'User folder creation succeeds' );
	$terms[] = (int) $folder;
	$assert( $user === axismundi_media_folder_owner( $folder ), 'Folder belongs to the User' );
	$assert( '' === get_term_meta( $folder, '_ax_media_folder_uuid', true ), 'Creation has no federation UUID side effect' );
	$assert( ! is_wp_error( wp_update_term( $folder, AXISMUNDI_MEDIA_FOLDER_TAX, array( 'name' => 'Renamed local folder' ) ) ), 'Folder can be renamed' );
	$assert( ! is_wp_error( axismundi_media_set_folder_tier( $folder, 'public', $user ) ), 'Visibility remains source-owned' );
	$assert( axismundi_media_folder_feed_allowed( $folder, $user ), 'Public folder retains feeds' );
	$assert( ! is_wp_error( axismundi_media_set_folder_tier( $folder, 'private', $user ) ) && ! axismundi_media_folder_feed_allowed( $folder, $user ), 'Private folder stays out of public feeds' );
	axismundi_media_set_folder_tier( $folder, 'public', $user );
	$assert( ! is_wp_error( axismundi_media_set_folder_access( $folder, 'password', 'fixture-password', $user ) ) && ! axismundi_media_folder_feed_allowed( $folder, $user ), 'Password gate remains effective' );
	$assert( function_exists( 'axismundi_media_render_atom_feed' ) && function_exists( 'axismundi_media_render_mrss_feed' ), 'Atom and Media RSS remain available' );
	update_term_meta( $folder, '_ax_media_folder_uuid', wp_generate_uuid4() );
	$assert( axismundi_media_retire_folder_identity_meta() && '' === get_term_meta( $folder, '_ax_media_folder_uuid', true ), 'Copied UUID retirement preserves the folder' );
	$assert( get_term( $folder, AXISMUNDI_MEDIA_FOLDER_TAX ) instanceof WP_Term && axismundi_media_retire_folder_identity_meta(), 'Retirement is replay-safe' );
} finally {
	foreach ( $terms as $term ) { wp_delete_term( $term, AXISMUNDI_MEDIA_FOLDER_TAX ); }
	if ( is_int( $user ) && $user > 0 ) {
		$root = axismundi_media_user_root( $user, false );
		if ( $root ) { wp_delete_term( $root, AXISMUNDI_MEDIA_FOLDER_TAX ); }
	}
	wp_set_current_user( $old_user );
	if ( is_int( $user ) && $user > 0 ) { wp_delete_user( $user ); }
}
$assert( $before === $snapshot(), 'No Actor, identity or principal fixture rows remain' );
printf( "PASS: %d local folder checks\n", $checks );
