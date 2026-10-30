<?php
/** Domain moderation does not grant local Account access. Rollback-only policy fixtures. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; };
$users = get_users( array( 'number' => 2, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
if ( count( $users ) < 2 ) { throw new RuntimeException( 'Two existing Users required; no User fixture is created.' ); }
$owner = (int) $users[0]; $reader = (int) $users[1];
$principal_snapshot = static fn() => $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A );
$before = $principal_snapshot();
$actor_count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_actors_actors_table() );
$activity_count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_act_activities_table() );
$network = 0;
$deny_network = static function () use ( &$network ) { ++$network; return new WP_Error( 'boundary_no_network', 'Unexpected network' ); };
add_filter( 'pre_http_request', $deny_network, PHP_INT_MAX );
$wpdb->query( 'START TRANSACTION' );
try {
	// Minimal repository fixtures avoid creation hooks, nested commits and leaked provisioning.
	$make = static function ( string $type, bool $local ) use ( $wpdb ) : Axismundi_Actor {
		$uuid = wp_generate_uuid4(); $name = 'axbound_' . substr( str_replace( '-', '', $uuid ), 0, 16 );
		$uri = $local ? home_url( '/actors/' . $uuid ) : 'https://principal-boundary.example/actors/' . $uuid;
		$now = current_time( 'mysql', true );
		$ok = $wpdb->insert( axismundi_actors_actors_table(), array( 'uuid' => $uuid, 'canonical_uri' => $uri, 'canonical_uri_hash' => hash( 'sha256', $uri ), 'origin' => $local ? 'local' : 'remote', 'status' => 'public', 'actor_type' => $type, 'preferred_username' => $name, 'local_handle_key' => $local ? $name : null, 'handle_locked_at' => $local ? $now : null, 'created_at' => $now, 'updated_at' => $now ) );
		if ( false === $ok ) { throw new RuntimeException( 'Actor fixture insert failed: ' . $wpdb->last_error ); }
		$id = (int) $wpdb->insert_id;
		$wpdb->update( axismundi_actors_actors_table(), array( 'identity_id' => $id ), array( 'id' => $id ) );
		$actor = axismundi_actors_get_by_identity( $id );
		if ( ! $actor ) { throw new RuntimeException( 'Actor fixture lookup failed' ); }
		return $actor;
	};
	$group = $make( 'Group', true ); $other_group = $make( 'Group', true );
	$local = $make( 'Person', true ); $remote = $make( 'Person', false );
	$gid = $group->get_identity_id(); $rid = $remote->get_identity_id(); $lid = $local->get_identity_id();
	$grant = static function ( int $target, string $kind, int $id ) use ( $wpdb ) {
		$now = current_time( 'mysql', true );
		if ( false === $wpdb->insert( axismundi_actors_managers_table(), array( 'identity_id' => $target, 'principal_kind' => $kind, 'principal_id' => $id, 'user_id' => 'wp_user' === $kind ? $id : null, 'role' => 'owner', 'created_at' => $now, 'updated_at' => $now ) ) ) { throw new RuntimeException( 'Grant fixture failed' ); }
	};
	$grant( $gid, 'wp_user', $owner ); $grant( $other_group->get_identity_id(), 'wp_user', $owner ); $grant( $lid, 'wp_user', $reader );
	$member = static function ( int $actor_id, string $state, string $role ) use ( $wpdb, $gid ) {
		$now = current_time( 'mysql', true );
		if ( false === $wpdb->replace( axismundi_forum_memberships_table(), array( 'group_identity_id' => $gid, 'actor_identity_id' => $actor_id, 'membership_state' => $state, 'membership_role' => $role, 'created_at' => $now, 'updated_at' => $now ) ) ) { throw new RuntimeException( 'Membership fixture failed' ); }
	};
	$member( $rid, 'accepted', 'moderator' ); $member( $lid, 'accepted', 'moderator' );
	$assert( null !== axismundi_actors_resolve_principal( 'remote_actor', $rid ), 'Remote identity remains a valid common subject' );
	$assert( axismundi_forum_actor_is_moderator( $gid, $remote ), 'Accepted remote moderator has domain authority' );
	$assert( isset( axismundi_forum_effective_moderators( $gid )[ $rid ] ), 'Remote moderator appears in public moderator projection' );
	$assert( ! axismundi_forum_actor_is_moderator( $other_group->get_identity_id(), $remote ), 'Moderation is scoped to its Group' );
	$assert( is_wp_error( axismundi_actors_set_principal_role( $gid, 'remote_actor', $rid, 'manager' ) ), 'Remote moderator cannot be granted local Account operation' );
	$grant( $gid, 'remote_actor', $rid );
	$assert( '' === axismundi_actors_principal_account_role( $gid, 'remote_actor', $rid ), 'Stale remote owner row is inactive' );
	$assert( ! axismundi_actors_principal_can_manage( $gid, 'remote_actor', $rid ), 'Stale remote owner cannot manage Account' );
	$assert( array() === axismundi_actors_accounts_for_principal( 'remote_actor', $rid ), 'Remote subject lists no operating Accounts' );
	$assert( ! in_array( 'remote_actor', array_column( axismundi_actors_account_principals( $gid ), 'principal_kind' ), true ), 'Account principal roster excludes remote stale grant' );
	$assert( true === axismundi_actors_set_principal_role( $gid, 'remote_actor', $rid, null ), 'Stale grant remains explicitly revocable' );
	$assert( axismundi_forum_actor_is_moderator( $gid, $remote ), 'Revoking Account row does not revoke separate moderation membership' );
	$assert( axismundi_forum_user_can_moderate( $gid, $reader ), 'Local operator can use explicitly granted Actor moderation' );
	$assert( ! axismundi_actors_can_access_workspace( $group, $reader ), 'Moderator gains no Group Account workspace' );
	$assert( ! axismundi_actors_can_act_as( $group, $reader ), 'Moderator gains no arbitrary Group publisher authority' );
	if ( function_exists( '\\Axismundi\\Jmap\\account_actors' ) ) {
		$assert( ! isset( \Axismundi\Jmap\account_actors( $reader )[ 'a' . $gid ] ), 'Moderation does not add Group to JMAP Account inventory' );
		$assert( null === \Axismundi\Jmap\resolve_account_actor( 'a' . $gid, $reader ), 'JMAP Account lookup rejects moderation-only User' );
	}
	if ( function_exists( '\\Axismundi\\Jmap\\accounts_table' ) && '1' === get_option( 'ax_jmap_db_version' ) ) {
		$assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE actor_id=%d', \Axismundi\Jmap\accounts_table(), $rid ) ), 'Remote moderator receives no local JMAP Account' );
	}
	$member( $rid, 'pending', 'moderator' );
	$assert( ! axismundi_forum_actor_is_moderator( $gid, $remote ), 'Pending moderator membership grants nothing' );
	$member( $rid, 'accepted', 'member' );
	$assert( ! axismundi_forum_actor_is_moderator( $gid, $remote ), 'Ordinary member has no moderation authority' );
	$member( $rid, 'accepted', 'moderator' );
	foreach ( array( 'disabled', 'tombstone' ) as $status ) {
		$wpdb->update( axismundi_actors_actors_table(), array( 'status' => $status ), array( 'id' => $rid ) );
		$assert( ! axismundi_forum_actor_is_moderator( $gid, $remote ), $status . ' remote denied even with a stale Actor object' );
		$assert( ! isset( axismundi_forum_effective_moderators( $gid )[ $rid ] ), $status . ' remote omitted from public roster' );
		$assert( is_wp_error( axismundi_forum_set_actor_moderator( $gid, $rid, $owner, true ) ), $status . ' remote cannot receive a new role transition' );
	}
	$assert( 0 === $network, 'Policy reads do not fetch or authenticate remote Actors' );
	$assert( $activity_count === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_act_activities_table() ), 'Denied transitions record no Activity' );
} finally {
	$wpdb->query( 'ROLLBACK' ); remove_filter( 'pre_http_request', $deny_network, PHP_INT_MAX );
}
$assert( $before === $principal_snapshot(), 'Pre-existing principal rows preserved exactly' );
$assert( $actor_count === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_actors_actors_table() ), 'No Actor fixture leaks' );
echo "PASS principal-boundaries {$checks}\n";
