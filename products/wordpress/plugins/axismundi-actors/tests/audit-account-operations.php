<?php
/** Development-only Account operations audit: wp eval-file. @package AxismundiActors */
defined( 'ABSPATH' ) || exit( 1 );

$users = array();
$fixtures = array();
$checks = 0;
$original_user = get_current_user_id();
$enable = '__return_true';
$assert = static function ( bool $condition, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $condition ) {
		throw new RuntimeException( $label );
	}
};
try {
	global $wpdb;
	foreach ( array( 'author', 'author', 'subscriber' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'axops' . strtolower( wp_generate_password( 12, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => $role ) );
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( $id->get_error_message() );
		}
		$users[] = (int) $id;
	}
	wp_set_current_user( $users[0] );
	$before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_actors_identities_table() );
	$result = axismundi_actors_create_account( array( 'display_name' => 'Review gated' ) );
	$assert( is_wp_error( $result ) && 'ax_actors_account_creation_disabled' === $result->get_error_code(), 'Creation disabled by default' );
	$assert( $before === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_actors_identities_table() ), 'Disabled creation writes nothing' );
	add_filter( 'axismundi_actors_account_creation_enabled', $enable );
	foreach ( array( 0, $users[2] ) as $denied ) {
		wp_set_current_user( $denied );
		$assert( is_wp_error( axismundi_actors_create_account( array( 'display_name' => 'Denied' ) ) ), 'Authentication and creation permission required' );
	}
	wp_set_current_user( $users[0] );
	foreach ( array( 'Person', 'Person', 'Organization', 'Group', 'Application', 'Service' ) as $index => $type ) {
		$actor = axismundi_actors_create_account( array( 'actor_type' => $type, 'display_name' => 'Account ' . $index ) );
		if ( ! $actor instanceof Axismundi_Actor ) {
			throw new RuntimeException( is_wp_error( $actor ) ? $actor->get_error_message() : 'Creation failed' );
		}
		$fixtures[] = $actor->get_identity_id();
		$assert( null === $actor->get_local_user_id() && ! axismundi_actors_can_act_as( $actor, $users[0] ), 'No legacy binding or implicit publication' );
		$assert( 'owner' === axismundi_actors_account_role( $actor->get_identity_id(), $users[0] ), 'Initial owner persisted' );
		$assert( 'Account ' . $index === $actor->get_display_name(), 'Account name independent of WP display name' );
		$assert( ( 'Person' === $type ) === axismundi_actors_account_is_personal( $actor, $users[0] ), 'Default personal attribution explicit in creation policy' );
	}
	$assert( 6 === count( axismundi_actors_workspace_options( $users[0] ) ), 'Multiple Person and all standard Actor types discoverable' );
	$assert( null === axismundi_actors_current_workspace(), 'Creation does not implicitly select first Account' );
	foreach ( array( array( 'display_name' => 'Bad', 'local_user_id' => $users[1] ), array( 'display_name' => '' ), array( 'display_name' => 'Bad', 'actor_type' => 'Device' ), array( 'display_name' => 'Bad', 'personal' => 1 ) ) as $invalid ) {
		$assert( is_wp_error( axismundi_actors_create_account( $invalid ) ), 'Invalid or privileged creation arguments rejected' );
	}
	$assert( is_wp_error( axismundi_actors_set_workspace( $users[1], $fixtures[0] ) ), 'Cannot switch another User' );
	$assert( true === axismundi_actors_set_default_account( $users[0], 'workspace', $fixtures[1] ), 'Explicit workspace default saved' );
	$assert( $fixtures[1] === axismundi_actors_current_workspace()->get_identity_id(), 'Explicit default used without a selection' );
	$assert( true === axismundi_actors_set_workspace( $users[0], $fixtures[2] ), 'Non-personal workspace selectable' );
	$assert( $fixtures[2] === (int) get_user_meta( $users[0], AXISMUNDI_ACTORS_ACTING_META, true ), 'One shared selection key' );
	$assert( $fixtures[2] === axismundi_actors_current_workspace()->get_identity_id(), 'Selection takes precedence' );
	$assert( null === axismundi_actors_workspace_publisher() && null === axismundi_actors_acting_actor(), 'Unpublishable workspace never substitutes a publisher' );
	$assert( is_wp_error( axismundi_actors_set_default_account( $users[0], 'urn:ietf:params:jmap:mail', $fixtures[0] ) ), 'Protocol URI not a local default purpose' );
	wp_set_current_user( $users[1] );
	$assert( is_wp_error( axismundi_actors_set_workspace( $users[1], $fixtures[0] ) ), 'Account access rechecked' );
	$assert( null === axismundi_actors_current_workspace(), 'User preferences isolated' );
	wp_set_current_user( $users[0] );
	$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $fixtures[2], 'user_id' => $users[0] ) );
	$assert( $fixtures[1] === axismundi_actors_current_workspace()->get_identity_id(), 'Revoked selection falls back to explicit default' );
	$assert( $fixtures[2] === (int) get_user_meta( $users[0], AXISMUNDI_ACTORS_ACTING_META, true ), 'Read does not rewrite stale preference' );
	$assert( true === axismundi_actors_set_workspace( $users[0], 0 ) && true === axismundi_actors_set_default_account( $users[0], 'workspace', 0 ), 'Selection and default clear independently' );
	$assert( null === axismundi_actors_current_workspace(), 'No arbitrary accessible Account fallback' );
	$assert( is_wp_error( axismundi_actors_create_account( array( 'display_name' => 'Bad', 'personal' => null ) ) ), 'Explicit null is not a personal ownership declaration' );
	$legacy = axismundi_actors_ensure_for_user( $users[0] );
	if ( ! $legacy instanceof Axismundi_Actor ) {
		throw new RuntimeException( 'Legacy fixture failed' );
	}
	$fixtures[] = $legacy->get_identity_id();
	$assert( true === axismundi_actors_set_workspace( $users[0], $fixtures[0] ), 'Additional Person remains selectable with legacy Person present' );
	$assert( null === axismundi_actors_acting_actor(), 'Legacy Person is not substituted for a valid unpublishable selection' );
	printf( "PASS: %d account operation checks\n", $checks );
} finally {
	remove_filter( 'axismundi_actors_account_creation_enabled', $enable );
	wp_set_current_user( $original_user );
	foreach ( array_reverse( $fixtures ) as $id ) {
		$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $id ) );
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $id ) );
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $id ) {
		wp_delete_user( $id );
	}
}
