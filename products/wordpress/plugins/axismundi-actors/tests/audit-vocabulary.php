<?php
/** Global status removal and scope-independent Account policy. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/admin.php';
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $ok ) { throw new RuntimeException( $label ); }
};
$tables = array( axismundi_actors_identities_table() => 'id', axismundi_actors_actors_table() => 'identity_id', axismundi_actors_managers_table() => 'identity_id,principal_kind,principal_id' );
$snapshot = static function () use ( $wpdb, $tables ) : array {
	$result = array();
	foreach ( $tables as $table => $order ) { $result[ $table ] = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY {$order}", ARRAY_A ); }
	return $result;
};
$before = $snapshot();
$old_user = get_current_user_id();
$instance = get_option( 'ax_actors_instance_identity_id', false );
$ids = $objects = array();
$user = 0;
$network = static fn() => new WP_Error( 'vocabulary_no_http', 'No network in this audit.' );
add_filter( 'pre_http_request', $network );
try {
	$user = wp_insert_user( array( 'user_login' => 'axvocab' . strtolower( wp_generate_password( 12, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
	$assert( ! is_wp_error( $user ), 'Fixture User exists' );
	wp_set_current_user( $user );
	foreach ( array( 'Person', 'Group', 'Application' ) as $type ) {
		$a = axismundi_actors_create_local( array( 'actor_type' => $type, 'initial_owner_user_id' => $user, 'personal_owner_user_id' => 'Person' === $type ? $user : null ) );
		$assert( $a instanceof Axismundi_Actor && 'public' === $a->get_status(), 'Creation defaults to public for ' . $type );
		$ids[] = $a->get_identity_id();
	}
	$assert( is_wp_error( axismundi_actors_create_local( array( 'status' => 'internal' ) ) ), 'Actor creation rejects internal' );
	$assert( ! axismundi_actors_set_status( $ids[0], 'internal' ), 'Actor transition rejects internal' );
	$assert( null === axismundi_actors_signing_key_snapshot( axismundi_actors_get_by_identity( $ids[0] ) ), 'Handleless Actor cannot sign before its profile is advertised' );
	foreach ( $ids as $id ) {
		axismundi_actors_register_handle( $id, 'axvocab' . $id );
		$a = axismundi_actors_get_by_identity( $id );
		$assert( null !== axismundi_actors_signing_key_snapshot( $a ) && null !== axismundi_actors_local_public_key( $a ), 'Published Actor signs with its advertised key' );
	}
	$wpdb->update( axismundi_actors_actors_table(), array( 'actor_scope' => 'site' ), array( 'identity_id' => $ids[1] ) );
	$group = axismundi_actors_get_by_identity( $ids[1] );
	$assert( 'owner' === axismundi_actors_principal_account_role( $ids[1], 'wp_user', $user ) && axismundi_actors_can_access_workspace( $group, $user ), 'Stale site scope cannot deny a principal grant' );
	$assert( $group->has_principals() && ! axismundi_actors_account_is_personal( $group, $user ), 'Shared Account access is not personal attribution' );
	$grant = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE identity_id = %d', axismundi_actors_managers_table(), $ids[2] ), ARRAY_A );
	try {
		$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $ids[2] ) );
		$assert( '' === axismundi_actors_principal_account_role( $ids[2], 'wp_user', $user ) && ! axismundi_actors_can_manage( axismundi_actors_get_by_identity( $ids[2] ), $user ), 'An Account without a principal grant remains inaccessible' );
	} finally { $wpdb->insert( axismundi_actors_managers_table(), $grant ); }
	update_option( 'ax_actors_instance_identity_id', $ids[1], false );
	$assert( axismundi_actors_is_instance_actor( $group ) && ! axismundi_actors_is_instance_actor( axismundi_actors_get_by_identity( $ids[2] ) ), 'Instance use is configured, never inferred from Application type' );
	$assert( 'owner' === axismundi_actors_principal_account_role( $ids[1], 'wp_user', $user ), 'Configured instance use does not override principal authority' );
	$assert( axismundi_actors_set_status( $ids[1], 'disabled' ), 'Actor can be disabled' );
	$disabled = axismundi_actors_get_by_identity( $ids[1] );
	$assert( ! axismundi_actors_set_status( $ids[1], 'public', 'internal' ) && 'disabled' === axismundi_actors_get_by_identity( $ids[1] )->get_status(), 'Migration cannot re-enable a concurrently disabled Actor' );
	$assert( '' === axismundi_actors_account_role( $ids[1], $user ) && null === axismundi_actors_signing_key_snapshot( $disabled ), 'Disabled Account cannot act or sign' );
	$assert( axismundi_actors_can_manage( $disabled, $user ) && ! is_wp_error( axismundi_actors_admin_profile_actor( $ids[1] ) ), 'Existing principal can explicitly recover a disabled profile' );
	axismundi_actors_set_status( $ids[1], 'public' );
	$identity = axismundi_op_register_object( array( 'canonical_uri' => home_url( '/vocab-fixture/' . wp_generate_uuid4() ), 'origin' => 'local', 'source_owner' => 'vocabulary-audit', 'source_kind' => 'fixture', 'as2_type' => 'Note' ) );
	$assert( is_array( $identity ), 'Object identity is registered' );
	$objects[] = (int) $identity['identity_id'];
	$assert( 'public' === $identity['status'] && null === axismundi_actors_get_identity_by_uri( $identity['canonical_uri'] ), 'Object registry is separate from Actor identifiers' );
	$assert( is_wp_error( axismundi_op_register_object( array( 'status' => 'internal' ) ) ), 'Object creation rejects internal' );
	foreach ( array( 'draft', 'future', 'private', 'pending', 'trash' ) as $status ) {
		$post = new WP_Post( (object) array( 'ID' => 0, 'post_type' => 'post', 'post_status' => $status, 'post_password' => '' ) );
		$assert( ! axismundi_op_post_article_visible( $post ), 'Registry presence does not expose ' . $status . ' WordPress posts' );
	}
	$events = array();
	$listener = static function ( $id, $status ) use ( &$events ) { $events[] = array( $id, $status ); };
	add_action( 'axismundi_actors_status_changed', $listener, 999, 2 );
	try {
		$wpdb->update( axismundi_actors_identities_table(), array( 'status' => 'internal' ), array( 'id' => $ids[0] ) );
		$assert( axismundi_actors_migrate_actor_vocabulary( 500 ), 'Legacy Actor statuses migrate through the service' );
		$assert( in_array( array( $ids[0], 'public' ), $events, true ), 'Normal status listeners run for migrated identities' );
		$events = array();
		$assert( axismundi_actors_migrate_actor_vocabulary( 500 ) && array() === $events, 'Migration replay does not refire completed transitions' );
	} finally { remove_action( 'axismundi_actors_status_changed', $listener, 999 ); }
	$assert( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . axismundi_actors_identities_table() . " WHERE status = 'internal'" ), 'No internal identity remains globally' );
} finally {
	if ( false === $instance ) { delete_option( 'ax_actors_instance_identity_id' ); } else { update_option( 'ax_actors_instance_identity_id', $instance, false ); }
	foreach ( $ids as $id ) {
		$a = axismundi_actors_get_by_identity( $id );
		if ( $a ) {
			$hash = hash( 'sha256', $a->get_uri() );
			$calendars = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE authority_actor_uri_hash = %s', axismundi_cal_calendars_table(), $hash ) );
			foreach ( $calendars as $calendar ) {
				foreach ( array( axismundi_cal_acl_table(), axismundi_cal_entries_list_table() ) as $table ) { $wpdb->delete( $table, array( 'calendar_id' => $calendar ) ); }
				$wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => $calendar ) );
			}
			$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri_hash' => $hash ) );
		}
		foreach ( array( axismundi_forum_settings_table() => 'group_identity_id', axismundi_actors_managers_table() => 'identity_id', axismundi_actors_addresses_table() => 'identity_id', axismundi_actors_signing_keys_table() => 'identity_id', axismundi_actors_keys_table() => 'identity_id', axismundi_actors_endpoints_table() => 'identity_id', axismundi_actors_texts_table() => 'identity_id', axismundi_actors_actors_table() => 'identity_id', axismundi_actors_identities_table() => 'id' ) as $table => $key ) { $wpdb->delete( $table, array( $key => $id ) ); }
	}
	foreach ( $objects as $id ) { $wpdb->delete( axismundi_op_objects_table(), array( 'id' => $id ) ); }
	wp_set_current_user( $old_user );
	if ( is_int( $user ) && $user > 0 ) { wp_delete_user( $user ); }
	remove_filter( 'pre_http_request', $network );
}
$assert( $before === $snapshot(), 'Fixture preserves identities, Actors and principal rows exactly' );
printf( "PASS: %d vocabulary checks\n", $checks );
