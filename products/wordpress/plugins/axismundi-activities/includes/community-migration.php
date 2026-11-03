<?php
/** One-time Community adoption plus resumable Group announcement. */
defined( 'ABSPATH' ) || exit;

function axismundi_act_community_migration_control( Axismundi_Actor $group ) : ?Axismundi_Activity {
	global $wpdb;
	$key = 'community-profile-migration:' . $group->get_identity_id();
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE source_event_hash=%s AND source_event_key=%s', axismundi_act_activities_table(), hash( 'sha256', $key ), $key ), ARRAY_A );
	return is_array( $row ) ? axismundi_act_hydrate( $row ) : null;
}

function axismundi_act_community_migration_announced( Axismundi_Actor $group, Axismundi_Activity $control ) : bool {
	foreach ( axismundi_act_get_by_object( $control->get_uri() ) as $activity ) {
		if ( 'Announce' === $activity->get_type() && $activity->get_actor_uri() === $group->get_uri() ) { return true; }
	}
	return false;
}

/** Fresh permission checks apply both to initial adoption and to announcement retries. */
function axismundi_act_migrate_community_group( int $user, Axismundi_Actor $group, Axismundi_Actor $person ) {
	if ( ! user_can( $user, 'manage_options' ) || ! $group->is_local() || 'Group' !== $group->get_type() || 'Person' !== $person->get_type()
		|| true !== axismundi_act_outbox_authorize( $user, $group ) || true !== axismundi_act_outbox_authorize( $user, $person ) ) {
		return new WP_Error( 'ax_migration_forbidden', 'Administrator and both Actor operating permissions are required.', array( 'status' => 403 ) );
	}
	// Serialize against the same Person outbox used by normal moderator bootstrap.
	$control = axismundi_act_submission_lock( array( 'source_owner' => 'axismundi-activities', 'source_kind' => 'outbox', 'source_ref' => $person->get_identity_id() ), static function () use ( $user, $group, $person ) {
		$group = axismundi_actors_get_by_identity( $group->get_identity_id() );
		$person = axismundi_actors_get_by_identity( $person->get_identity_id() );
		if ( ! $group || ! $person || ! user_can( $user, 'manage_options' )
			|| true !== axismundi_act_outbox_authorize( $user, $group ) || true !== axismundi_act_outbox_authorize( $user, $person ) ) { return new WP_Error( 'ax_migration_forbidden', 'Actor access changed.', array( 'status' => 403 ) ); }
		$old = axismundi_act_community_migration_control( $group );
		if ( $old ) {
			global $wpdb;
			$log = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE group_id=%d AND actor_uri=%s AND person_uri=%s AND verb='Initialize' AND activity_uri=%s", axismundi_actors_moderators_log_table(), $group->get_identity_id(), $person->get_uri(), $person->get_uri(), $old->get_uri() ) );
			return axismundi_actors_is_community( $group ) && $old->get_actor_uri() === $person->get_uri() && $old->get_object_uri() === $person->get_uri() && $old->get_target_uri() === axismundi_actors_moderators_uri( $group ) && $log
				? $old : new WP_Error( 'ax_migration_conflict', 'A different migration already owns this Group.', array( 'status' => 409 ) );
		}
		if ( null !== axismundi_actors_group_profile( $group ) ) { return new WP_Error( 'ax_migration_profile', 'Only an unconfigured Group can be migrated.', array( 'status' => 409 ) ); }
		return axismundi_act_record_source_activity(
			array( 'type' => 'Add', 'actor' => $person->get_uri(), 'object' => $person->get_uri(), 'target' => axismundi_actors_moderators_uri( $group ), 'audience' => $group->get_uri(), 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ), 'cc' => array( $group->get_uri() ) ),
			'outbound', 'community-profile-migration:' . $group->get_identity_id(), array(),
			static fn( array $normalized ) => axismundi_actors_migrate_community_profile( $group, $person, $user, $normalized['activity_uri'] )
		);
	} );
	if ( is_wp_error( $control ) ) { return $control; }
	$result = axismundi_act_announce_moderator_control( $group, $control );
	return is_wp_error( $result ) ? new WP_Error( 'ax_migration_announce', 'Migration committed, but its announcement failed. Retry with the same Group and Person.', array( 'status' => 503 ) ) : $control;
}
