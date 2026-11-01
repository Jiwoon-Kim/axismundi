<?php
/** Disposable browser users and authenticated selection; never uses an existing Account. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
global $wpdb;
$mode = $args[0] ?? '';
$prefix = $args[1] ?? '';
if ( ! preg_match( '/\Aax_pb_ui_[a-f0-9]{8}\z/D', $prefix ) ) { throw new RuntimeException( 'Invalid browser fixture key' ); }
$data = get_option( $prefix, null );
if ( 'create' === $mode ) {
	if ( null !== $data ) { throw new RuntimeException( 'Fixture already exists' ); }
	$data = array( 'users' => array(), 'actors' => array() );
	update_option( $prefix, $data, false );
	$priority = has_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound' );
	if ( false !== $priority ) { remove_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', $priority ); }
	foreach ( array( 'a', 'b' ) as $suffix ) {
		$user = wp_create_user( $prefix . '_' . $suffix, wp_generate_password(), $prefix . '_' . $suffix . '@example.test' );
		if ( is_wp_error( $user ) ) { throw new RuntimeException( 'Fixture user failed' ); }
		$data['users'][] = (int) $user; update_option( $prefix, $data, false );
		( new WP_User( $user ) )->set_role( 'contributor' );
		$actor = axismundi_actors_ensure_for_user( $user );
		if ( ! $actor instanceof Axismundi_Actor ) { throw new RuntimeException( 'Fixture Actor failed' ); }
		$data['actors'][] = $actor->get_identity_id(); update_option( $prefix, $data, false );
		if ( is_wp_error( axismundi_actors_register_handle( $actor->get_identity_id(), $prefix . '_' . $suffix ) ) || ! axismundi_actors_set_status( $actor->get_identity_id(), 'public' ) ) { throw new RuntimeException( 'Fixture activation failed' ); }
		$actor = axismundi_actors_get_by_identity( $actor->get_identity_id() );
		axismundi_actors_set_local_policy( $actor, 'manually_approves_followers', false, $user );
	}
	[ $a, $b ] = array_map( 'axismundi_actors_get_by_identity', $data['actors'] );
	wp_set_current_user( $data['users'][0] );
	if ( ! is_array( axismundi_act_follow_actor( $a, $b ) ) ) { throw new RuntimeException( 'Fixture Follow failed' ); }
	$expires = time() + 900;
	$token = WP_Session_Tokens::get_instance( $data['users'][0] )->create( $expires );
	$logged_in = wp_generate_auth_cookie( $data['users'][0], $expires, 'logged_in', $token );
	$_COOKIE[ LOGGED_IN_COOKIE ] = $logged_in;
	$cookies = array( LOGGED_IN_COOKIE => $logged_in, AUTH_COOKIE => wp_generate_auth_cookie( $data['users'][0], $expires, 'auth', $token ) );
	if ( axismundi_actors_account_selection_enabled() ) {
		$session = axismundi_actors_browser_session( $data['users'][0] );
		$unsigned = 'v1|' . $a->get_identity_id() . '|' . $data['users'][0] . '|' . $session['expiration'];
		$cookies[ axismundi_actors_selection_cookie_name() ] = $unsigned . '|' . hash_hmac( 'sha256', $unsigned . '|' . $session['binding'], wp_salt( 'auth' ) );
	}
	echo wp_json_encode( array( 'cookies' => $cookies, 'subject' => $a->get_uri(), 'target' => $b->get_uri(), 'url' => axismundi_act_follows_admin_url() ) );
} elseif ( 'inspect' === $mode && is_array( $data ) ) {
	[ $a, $b ] = array_map( 'axismundi_actors_get_by_identity', $data['actors'] );
	$block = axismundi_act_get_relation( 'block', $a->get_uri(), $b->get_uri() );
	$follow = axismundi_act_get_relation( 'follow', $a->get_uri(), $b->get_uri() );
	$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i m JOIN %i a ON a.id=m.activity_id WHERE m.actor_identity_id=%d AND a.actor_uri=%s AND (a.activity_type='Block' OR (a.activity_type='Undo' AND a.object_uri=%s))", axismundi_act_inbox_table(), axismundi_act_activities_table(), $b->get_identity_id(), $a->get_uri(), $block['initiating_activity_uri'] ?? '' ) );
	echo wp_json_encode( array( 'block' => $block['state'] ?? null, 'follow' => $follow['state'] ?? null, 'receipts' => (int) $count ) );
} elseif ( 'cleanup' === $mode ) {
	if ( ! is_array( $data ) ) { echo 'No fixture'; return; }
	foreach ( $data['actors'] as $id ) {
		$actor = axismundi_actors_get_by_identity( $id );
		if ( ! $actor ) { continue; }
		$uri = $actor->get_uri();
		$activities = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE actor_uri=%s', axismundi_act_activities_table(), $uri ) );
		foreach ( $activities as $activity ) { $wpdb->delete( axismundi_act_inbox_table(), array( 'activity_id' => $activity ) ); }
		$wpdb->delete( axismundi_act_inbox_table(), array( 'actor_identity_id' => $id ) );
		$wpdb->delete( axismundi_act_relations_table(), array( 'subject_actor_uri' => $uri ) );
		$wpdb->delete( axismundi_act_relations_table(), array( 'object_actor_uri' => $uri ) );
		$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri' => $uri ) );
		foreach ( array( 'managers', 'keys', 'signing_keys', 'endpoints', 'addresses', 'texts', 'fetch_state', 'asset_cache', 'identity_relations' ) as $kind ) { $wpdb->delete( call_user_func( 'axismundi_actors_' . $kind . '_table' ), array( 'identity_id' => $id ) ); }
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $id ) );
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	foreach ( $data['users'] as $user ) { wp_delete_user( $user ); }
	delete_option( $prefix );
	echo 'Cleaned';
} else { throw new RuntimeException( 'Unknown browser fixture operation' ); }
