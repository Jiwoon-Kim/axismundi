<?php
/** User avatars and multiple Actor profile images must remain independent. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) { ++$checks; if ( ! $ok ) { throw new RuntimeException( $label ); } echo "PASS {$label}\n"; };
$original_user = get_current_user_id();
$actors = array(); $attachments = array();
$uid = wp_insert_user( array( 'user_login' => 'axavatar_' . strtolower( wp_generate_password( 12, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
if ( is_wp_error( $uid ) ) { throw new RuntimeException( $uid->get_error_message() ); }
try {
	wp_set_current_user( $uid );
	$assert( false === has_filter( 'get_avatar_data', 'axismundi_actors_filter_avatar_data' ), 'Actor override is not registered on WordPress avatars' );
	$args = array( 'url' => 'https://avatar.example/user.png', 'found_avatar' => false );
	$assert( $args === axismundi_actors_filter_avatar_data( $args, $uid ), 'Legacy avatar callable is inert' );
	$before = get_avatar_url( $uid );
	foreach ( array( 'a', 'b' ) as $key ) {
		$actor = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'initial_owner_user_id' => $uid, 'preferred_username' => 'av' . $key . strtolower( wp_generate_password( 10, false, false ) ) ) );
		if ( is_wp_error( $actor ) ) { throw new RuntimeException( $actor->get_error_message() ); }
		$actors[] = $actor;
		$assert( '' === axismundi_actors_avatar_url( $actor ) && '' === axismundi_actors_avatar_html( $actor ) && '' === axismundi_actors_mention_avatar_url( $actor ), 'Empty Actor image does not fall back to User or Gravatar: ' . $key );
		$wire = axismundi_op_actor_transform( $actor );
		$assert( ! isset( $wire['icon'] ), 'Actor projection does not infer a User icon: ' . $key );
		$image = wp_insert_attachment( array( 'post_title' => 'Avatar fixture', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'post_author' => $uid ) );
		if ( is_wp_error( $image ) || ! $image ) { throw new RuntimeException( 'Could not create image fixture' ); }
		$attachments[] = $image;
		update_post_meta( $image, '_wp_attached_file', '2026/01/' . wp_generate_uuid4() . '.jpg' );
		$result = axismundi_actors_set_profile_media( $actor, 'avatar', $image, $uid );
		if ( is_wp_error( $result ) || false === $result ) { throw new RuntimeException( 'Could not set Actor image' ); }
		$actor = axismundi_actors_get_by_identity( $actor->get_identity_id() );
		$assert( wp_get_attachment_url( $image ) === axismundi_actors_avatar_url( $actor ), 'Explicit Actor image remains available: ' . $key );
		$selected = axismundi_actors_set_acting_actor( $uid, $actor->get_identity_id() );
		$acting = axismundi_actors_acting_actor( $uid );
		$assert( ! is_wp_error( $selected ) && $acting && $actor->get_identity_id() === $acting->get_identity_id(), 'Fixture switches acting Actor: ' . $key );
		$assert( $before === get_avatar_url( $uid ) && $before === get_avatar_url( get_userdata( $uid ) ), 'User avatar is unchanged after Actor image and selection: ' . $key );
	}
	wp_set_current_user( 0 );
	$assert( $before === get_avatar_url( $uid ), 'Anonymous viewer receives the same User avatar' );
} finally {
	foreach ( $actors as $actor ) {
		$id = $actor->get_identity_id();
		foreach ( array( axismundi_actors_managers_table(), axismundi_actors_texts_table(), axismundi_actors_keys_table(), axismundi_actors_signing_keys_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_fetch_state_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $id ) ); }
		$wpdb->delete( axismundi_actors_meta_table(), array( 'actor_id' => $id ) );
		$wpdb->delete( axismundi_actors_actors_table(), array( 'id' => $id ) );
	}
	foreach ( $attachments as $image ) { wp_delete_attachment( $image, true ); }
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $uid );
	wp_set_current_user( $original_user );
}
echo "== {$checks} checks, 0 failed ==\n";
