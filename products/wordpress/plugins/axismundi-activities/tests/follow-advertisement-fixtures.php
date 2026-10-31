<?php
/** Real Actor projections against effective inbound Follow policy; owned fixtures only. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
return static function () : array {
	global $wpdb;
	$option = 'axismundi_activities_auto_accept_local_follows';
	$before = get_option( $option, null );
	$ids = array();
	$user = wp_insert_user( array( 'user_login' => 'axadv' . wp_generate_password( 12, false, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
	if ( is_wp_error( $user ) ) { throw new RuntimeException( 'Cannot create advertisement fixture user' ); }
	$force = static fn( $required, $actor ) => true;
	try {
		foreach ( array( 'Person', 'Group' ) as $type ) {
			$actor = axismundi_actors_create_local( array( 'actor_type' => $type, 'initial_owner_user_id' => (int) $user, 'display_name' => 'Follow advertisement fixture' ) );
			if ( ! $actor instanceof Axismundi_Actor ) { throw new RuntimeException( 'Cannot create advertisement fixture Actor' ); }
			$id = $actor->get_identity_id();
			$ids[] = $id;
			if ( is_wp_error( axismundi_actors_register_handle( $id, 'axadv' . strtolower( wp_generate_password( 12, false, false ) ) ) ) || ! axismundi_actors_set_status( $id, 'public' ) ) { throw new RuntimeException( 'Cannot activate advertisement fixture' ); }
			$actors[ $type ] = axismundi_actors_get_by_identity( $id );
			if ( null !== $actors[ $type ]->get_policy_flag( 'manually_approves_followers' ) ) { throw new RuntimeException( 'Fixture requires undeclared approval flag' ); }
		}
		$person = $actors['Person'];
		$advertises = static fn( $actor ) => axismundi_op_actor_transform( $actor )['manuallyApprovesFollowers'];
		update_option( $option, true, false );
		$results = array( 'automatic Person advertises false' => false === $advertises( $person ) && axismundi_act_local_follow_can_auto_accept( $person ) );
		update_option( $option, false, false );
		$results['site option matches effective approval'] = true === $advertises( $person ) && axismundi_act_local_follow_requires_approval( $person ) && ! axismundi_act_local_follow_can_auto_accept( $person );
		update_option( $option, true, false );
		add_filter( 'axismundi_act_local_follow_requires_approval', $force, 100, 2 );
		try {
			$results['policy filter matches effective approval'] = true === $advertises( $person ) && axismundi_act_local_follow_requires_approval( $person ) && ! axismundi_act_local_follow_can_auto_accept( $person );
		} finally { remove_filter( 'axismundi_act_local_follow_requires_approval', $force, 100 ); }
		$group = $actors['Group'];
		$results['Group matches effective approval'] = true === $advertises( $group ) && ! axismundi_act_local_follow_can_auto_accept( $group );
		return $results;
	} finally {
		remove_filter( 'axismundi_act_local_follow_requires_approval', $force, 100 );
		if ( null === $before ) { delete_option( $option ); } else { update_option( $option, $before, false ); }
		foreach ( $ids as $id ) {
			foreach ( array( axismundi_actors_managers_table(), axismundi_actors_signing_keys_table(), axismundi_actors_keys_table(), axismundi_actors_endpoints_table(), axismundi_actors_addresses_table(), axismundi_actors_texts_table(), axismundi_actors_fetch_state_table(), axismundi_actors_asset_cache_table(), axismundi_actors_identity_relations_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $id ) ); }
			$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $id ) );
			$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
		}
		wp_delete_user( (int) $user );
	}
};
