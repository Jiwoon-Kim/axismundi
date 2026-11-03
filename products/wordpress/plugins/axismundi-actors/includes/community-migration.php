<?php
/** Explicit legacy Group adoption, never a general profile editor. */
defined( 'ABSPATH' ) || exit;

/** SQL participant: caller owns the Activity transaction and rollback. */
function axismundi_actors_migrate_community_profile( Axismundi_Actor $group, Axismundi_Actor $person, int $user, string $activity_uri ) {
	global $wpdb;
	if ( ! user_can( $user, 'manage_options' ) ) { return new WP_Error( 'ax_migration_forbidden', 'An administrator must explicitly migrate a legacy Group.', array( 'status' => 403 ) ); }
	$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', axismundi_actors_meta_table() ) );
	if ( 'InnoDB' !== $engine ) { return new WP_Error( 'ax_migration_storage', 'Transactional Actor metadata storage is required.' ); }
	$locked = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE id IN (%d,%d) ORDER BY id FOR UPDATE', axismundi_actors_actors_table(), $group->get_identity_id(), $person->get_identity_id() ) );
	$fresh = $locked ? axismundi_actors_get_by_identity( $group->get_identity_id() ) : null;
	$person = axismundi_actors_get_by_identity( $person->get_identity_id() );
	if ( ! $fresh || ! $person || ! $fresh->is_local() || 'Group' !== $fresh->get_type()
		|| 'public' !== $fresh->get_status() || ! $fresh->is_handle_locked()
		|| ! $person->is_local() || 'Person' !== $person->get_type() || 'public' !== $person->get_status() || ! $person->is_handle_locked()
		|| ! axismundi_actors_can_act_as( $fresh, $user ) || ! axismundi_actors_can_act_as( $person, $user ) ) {
		return new WP_Error( 'ax_migration_forbidden', 'Both active local Group and Person operating permissions are required.', array( 'status' => 403 ) );
	}
	if ( null !== axismundi_actors_group_profile( $fresh ) ) { return new WP_Error( 'ax_migration_profile', 'Only an unconfigured Group can be migrated.', array( 'status' => 409 ) ); }
	// No upsert: even an empty or unfamiliar existing profile is not ours to overwrite.
	if ( false === $wpdb->insert( axismundi_actors_meta_table(), array( 'actor_id' => $fresh->get_identity_id(), 'meta_key' => 'policy:federation_profile', 'meta_value' => 'community' ) ) ) {
		return new WP_Error( 'ax_migration_write', 'The Community profile could not be assigned.' );
	}
	return axismundi_actors_write_moderators( $fresh, $person, $person, true, hash( 'sha256', $activity_uri ), $activity_uri, true, $user );
}
