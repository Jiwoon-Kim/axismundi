<?php
/** Small real-ledger scenario shared by baseline and in-memory mutation controls. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
return static function ( callable $record, callable $reconcile ) : void {
	global $wpdb;
	$old_user = get_current_user_id();
	$hook = 'axismundi_activitypub_bridge_queue_outbound';
	$priority = has_action( 'axismundi_act_activity_recorded', $hook );
	$ids = $uris = array();
	$user = 0;
	$assert = static function ( bool $ok, string $label ) : void { if ( ! $ok ) { throw new RuntimeException( $label ); } };
	$http = static fn() => new WP_Error( 'mutation_no_http', 'No network in mutation fixture.' );
	try {
		if ( false !== $priority ) { remove_action( 'axismundi_act_activity_recorded', $hook, $priority ); }
		add_filter( 'pre_http_request', $http, PHP_INT_MAX );
		$login = 'axbm' . strtolower( wp_generate_password( 12, false, false ) );
		$user = wp_create_user( $login, wp_generate_password(), $login . '@example.test' );
		if ( is_wp_error( $user ) ) { $user = 0; throw new LogicException( 'Mutation user setup failed' ); }
		( new WP_User( $user ) )->set_role( 'contributor' );
		$a = axismundi_actors_ensure_for_user( $user );
		if ( ! $a instanceof Axismundi_Actor ) { throw new LogicException( 'Mutation Actor setup failed' ); }
		$ids[] = $a->get_identity_id(); $uris[] = $a->get_uri();
		axismundi_actors_register_handle( $a->get_identity_id(), $login );
		axismundi_actors_set_status( $a->get_identity_id(), 'public' );
		$a = axismundi_actors_get_by_identity( $a->get_identity_id() );
		axismundi_actors_set_local_policy( $a, 'manually_approves_followers', true, $user );
		wp_set_current_user( $user );
		$remote = 'https://example.com/axbm/' . $login;
		$r = axismundi_actors_upsert_remote( array( 'uri' => $remote, 'actor_type' => 'Person', 'payload' => array( 'id' => $remote, 'type' => 'Person' ), 'preferred_username' => $login, 'profile_url' => $remote, 'endpoints' => array( 'inbox' => $remote . '/inbox', 'outbox' => $remote . '/outbox' ) ) );
		if ( ! $r instanceof Axismundi_Actor ) { throw new LogicException( 'Mutation remote setup failed' ); }
		$ids[] = $r->get_identity_id(); $uris[] = $remote;
		$follow = axismundi_act_follow_remote_actor( $a, $r );
		if ( ! is_array( $follow ) ) { throw new LogicException( 'Mutation Follow setup failed' ); }
		$emit = static function ( string $type, string $object ) use ( $record, $remote ) {
			return $record( array( 'id' => $remote . '/' . wp_generate_uuid4(), 'type' => $type, 'actor' => $remote, 'object' => $object ), 'inbound', '' );
		};
		$accept = $emit( 'Accept', $follow['initiating_activity_uri'] );
		if ( ! $accept instanceof Axismundi_Activity ) { throw new LogicException( 'Mutation Accept setup failed' ); }
		$block = $emit( 'Block', $a->get_uri() );
		if ( ! $block instanceof Axismundi_Activity ) { throw new LogicException( 'Mutation Block setup failed' ); }
		$get = static fn() => axismundi_act_get_relation( 'follow', $a->get_uri(), $remote );
		$assert( 'rejected' === $get()['state'], 'block-ends-follow' );
		$denied = $record( array( 'type' => 'Follow', 'actor' => $a->get_uri(), 'object' => $remote ), 'outbound', '' );
		$assert( is_wp_error( $denied ) && 'ax_act_follow_blocked' === $denied->get_error_code(), 'blocked-follow-denied' );
		$undo = $emit( 'Undo', $block->get_uri() );
		if ( ! $undo instanceof Axismundi_Activity ) { throw new LogicException( 'Mutation Undo setup failed' ); }
		$assert( 'rejected' === $get()['state'], 'unblock-no-restore' );
		$result = $reconcile( axismundi_act_transaction_row( $follow['initiating_activity_uri'] ) );
		$assert( is_array( $result ) && 'rejected' === $result['state'] && 'rejected' === $get()['state'], 'reconcile-does-not-revive' );
	} finally {
		foreach ( $uris as $uri ) {
			$activities = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE actor_uri=%s', axismundi_act_activities_table(), $uri ) );
			foreach ( $activities as $id ) { $wpdb->delete( axismundi_act_inbox_table(), array( 'activity_id' => $id ) ); }
			$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri' => $uri ) );
			$wpdb->delete( axismundi_act_relations_table(), array( 'subject_actor_uri' => $uri ) );
			$wpdb->delete( axismundi_act_relations_table(), array( 'object_actor_uri' => $uri ) );
		}
		foreach ( $ids as $id ) {
			foreach ( array( axismundi_actors_managers_table(), axismundi_actors_addresses_table(), axismundi_actors_keys_table(), axismundi_actors_signing_keys_table(), axismundi_actors_endpoints_table(), axismundi_actors_texts_table(), axismundi_actors_fetch_state_table(), axismundi_actors_asset_cache_table(), axismundi_actors_identity_relations_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $id ) ); }
			$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $id ) );
			$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
		}
		if ( $user ) { wp_delete_user( $user ); }
		wp_set_current_user( $old_user );
		remove_filter( 'pre_http_request', $http, PHP_INT_MAX );
		if ( false !== $priority ) { add_action( 'axismundi_act_activity_recorded', $hook, $priority ); }
	}
};
