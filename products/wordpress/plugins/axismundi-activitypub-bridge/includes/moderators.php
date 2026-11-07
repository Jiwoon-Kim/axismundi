<?php
/** Community moderator transport adapter; no Forum or principal dependencies. */
defined( 'ABSPATH' ) || exit;

add_filter( 'axismundi_activitypub_bridge_local_collection_group', static function ( $group, string $target ) {
	return $group ?? axismundi_actors_moderators_group_for_uri( $target );
}, 10, 2 );
add_filter( 'axismundi_activitypub_bridge_local_collection_owned', static function ( bool $owned, Axismundi_Actor $group, string $target ) : bool {
	return $owned || ( axismundi_actors_is_community( $group ) && axismundi_actors_moderators_uri( $group ) === axismundi_actors_collection_target_uri( $target ) );
}, 10, 3 );

function axismundi_activitypub_bridge_receive_moderators( array $control, array $context ) {
	$group = $context['group']; $person = $context['person'];
	if ( $group->is_local() ) {
		if ( $context['announced'] || ! $context['author_verified'] || ! axismundi_actors_is_community( $group ) || ! isset( $control['id'] ) ) { return new WP_Error( 'ax_mod_authority', 'A local Community needs a directly authenticated Person control with an Activity ID.', array( 'status' => 403 ) ); }
		$member = axismundi_actors_get_by_uri( $control['object'] );
		if ( ! $member ) { $member = axismundi_actors_discover_remote_actor_uri( $control['object'] ); }
		if ( ! $member instanceof Axismundi_Actor || 'Person' !== $member->get_type() ) { return new WP_Error( 'ax_mod_member', 'A moderator member must resolve to Person.', array( 'status' => 422 ) ); }
		$stored = axismundi_act_record_source_activity( $control, 'inbound', '', array(), static function () use ( $group, $person, $member, $control ) {
			$fresh = axismundi_actors_get_by_identity( $group->get_identity_id() );
			if ( ! $fresh ) { return new WP_Error( 'ax_mod_missing', 'The Community is no longer available.' ); }
			return axismundi_actors_write_moderators( $fresh, $person, $member, 'Add' === $control['type'], hash( 'sha256', $control['id'] ), $control['id'] );
		} );
		if ( is_wp_error( $stored ) ) { return $stored; }
		$announced = axismundi_act_announce_moderator_control( $group, $stored );
		return is_wp_error( $announced ) ? $announced : true;
	}
	// A Group announcement is authority to refresh its observed collection, not to
	// grant local permissions or to optimistically apply an allegedly authored Add.
	if ( ! $context['announced'] ) {
		$members = axismundi_actors_read_moderators( $group );
		if ( ! $context['author_verified'] || is_wp_error( $members ) || ! in_array( $person->get_uri(), $members['items'], true ) ) { return new WP_Error( 'ax_mod_forbidden', 'An observed existing moderator is required for a direct remote hint.', array( 'status' => 403 ) ); }
	}
	return axismundi_actors_request_moderators_sync( $group );
}
add_filter( 'axismundi_activitypub_bridge_collection_control_handler', static function ( $handler, string $target, Axismundi_Actor $group ) {
	if ( null !== $handler || axismundi_actors_collection_target_uri( $target ) !== axismundi_actors_moderators_uri( $group ) ) { return $handler; }
	if ( $group->is_local() ? ! axismundi_actors_is_community( $group ) : ! axismundi_actors_remote_moderators_reference( $group, $target ) ) { return $handler; }
	if ( ! $group->is_local() && is_wp_error( axismundi_actors_read_moderators( $group ) ) ) {
		// Receive the evidence regardless, but do not label an unverified collection as roles.
		global $wpdb;
		$state = $wpdb->get_var( $wpdb->prepare( 'SELECT collection_uri FROM %i WHERE group_id=%d', axismundi_actors_moderators_state_table(), $group->get_identity_id() ) );
		if ( $state !== $target ) { axismundi_actors_request_moderators_sync( $group ); }
		return $handler;
	}
	return 'axismundi_activitypub_bridge_receive_moderators';
}, 10, 3 );
