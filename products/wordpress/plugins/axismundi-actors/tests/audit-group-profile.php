<?php
/** Forum-independent local Group contract regression. */
defined( 'ABSPATH' ) || exit( 1 );
$validate_profile = $validate_profile ?? 'axismundi_actors_validate_group_profile';
$check_profile_update = $check_profile_update ?? 'axismundi_actors_check_group_profile_update';
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) { ++$checks; if ( ! $ok ) { throw new RuntimeException( $label ); } echo "PASS $label\n"; };
global $wpdb;
$actors = array();
$user = get_current_user_id();
$post = $_POST;
$uid = wp_insert_user( array( 'user_login' => 'axgp_' . strtolower( wp_generate_password( 10, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
if ( is_wp_error( $uid ) ) { throw new RuntimeException( 'Could not create fixture user' ); }
try {
	wp_set_current_user( $uid );
	$assert( is_wp_error( $validate_profile( 'Person', array( 'federation_profile' => 'community' ) ) ), 'Non-Group profile rejected' );
	$assert( is_wp_error( $validate_profile( 'Group', array( 'federation_profile' => 'channel' ) ) ), 'Channel profile rejected' );
	$assert( is_wp_error( $check_profile_update( array( 'federation_profile' => 'community' ) ) ), 'Existing profile cannot change' );
	$assert( true === $check_profile_update( array() ), 'Unrelated updates remain unchanged' );
	foreach ( array( 'community', 'legacy', 'person' ) as $case ) {
		$args = array( 'actor_type' => 'person' === $case ? 'Person' : 'Group', 'initial_owner_user_id' => $uid );
		if ( 'community' === $case ) { $args['federation_profile'] = 'community'; }
		$actor = axismundi_actors_create_local( $args );
		if ( is_wp_error( $actor ) ) { throw new RuntimeException( $actor->get_error_message() ); }
		$actors[$case] = $actor;
	}
	$group = $actors['community'];
	$assert( 'community' === axismundi_actors_group_profile( axismundi_actors_get_by_identity( $group->get_identity_id() ) ), 'Community survives reload' );
	$assert( null === axismundi_actors_group_profile( $actors['legacy'] ), 'Existing-style Group remains unset' );
	$assert( null === axismundi_actors_get_meta( $actors['person']->get_identity_id(), 'policy:federation_profile' ), 'Person has no stored profile' );
	foreach ( array( $group, $actors['legacy'] ) as $actor ) {
		$wire = axismundi_op_actor_transform( $actor );
		$assert( is_array( $wire ) && ! isset( $wire['attributedTo'], $wire['federation_profile'] ) && ! array_key_exists( 'attributedTo', $wire ) && ! array_key_exists( 'federation_profile', $wire ), 'Group policy adds no wire properties' );
	}
	$assert( false === axismundi_actors_store_meta( $group->get_identity_id(), 'policy:federation_profile', 'channel' ) && 'community' === axismundi_actors_group_profile( $group ), 'Generic metadata writer cannot change profile' );
	$assert( false === axismundi_actors_store_meta( $actors['legacy']->get_identity_id(), 'policy:federation_profile', 'community' ), 'Unset Group cannot acquire profile after creation' );
	foreach ( array( 'channel', 'invalid' ) as $profile ) {
		$assert( is_wp_error( axismundi_actors_create_local( array( 'actor_type' => 'Group', 'federation_profile' => $profile ) ) ), 'Unsupported profile cannot create Actor: ' . $profile );
	}
	$assert( is_wp_error( axismundi_actors_create_local( array( 'actor_type' => 'Person', 'federation_profile' => 'community' ) ) ), 'Person creation cannot persist Group policy' );
	$request = new WP_REST_Request( 'POST', '/axismundi/v1/actors/' . $group->get_uuid() . '/outbox' );
	$request->set_param( 'federation_profile', 'channel' );
	$response = rest_do_request( $request );
	$assert( 400 === $response->get_status() && 'ax_actors_profile_immutable' === $response->get_data()['code'], 'REST mutation is rejected before dispatch' );
	$_POST = array( 'action' => 'axismundi_actors_set_profile_fields', 'identity_id' => $group->get_identity_id(), 'federation_profile' => 'channel' );
	$die = static function () { return static function () { throw new RuntimeException( 'profile-admin-rejected' ); }; };
	add_filter( 'wp_die_handler', $die );
	$rejected = false;
	try { axismundi_actors_guard_group_profile_admin(); } catch ( RuntimeException $error ) { $rejected = 'profile-admin-rejected' === $error->getMessage(); }
	finally { remove_filter( 'wp_die_handler', $die ); }
	$assert( $rejected, 'Admin mutation is rejected before profile writes' );
	ob_start(); axismundi_actors_render_group_profile_field(); $html = ob_get_clean();
	$assert( false !== strpos( $html, 'value="channel" disabled' ) && false !== strpos( $html, 'value="community"' ), 'Channel placeholder is disabled' );
} finally {
	$_POST = $post;
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $uid );
	foreach ( $actors as $actor ) {
		$id = $actor->get_identity_id();
		foreach ( array( axismundi_actors_managers_table(), axismundi_actors_texts_table(), axismundi_actors_keys_table(), axismundi_actors_signing_keys_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_fetch_state_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $id ) ); }
		$wpdb->delete( axismundi_actors_meta_table(), array( 'actor_id' => $id ) );
		$wpdb->delete( axismundi_actors_actors_table(), array( 'id' => $id ) );
	}
	wp_set_current_user( $user );
}
printf( "== %d checks, 0 failed ==\n", $checks );
