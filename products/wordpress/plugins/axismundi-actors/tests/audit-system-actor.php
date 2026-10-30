<?php
/** System provisioning and non-deletion contract. Run with wp eval-file. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks;
};
$actor = axismundi_actors_get_system_actor();
$assert( $actor instanceof Axismundi_Actor, 'Automatic system Actor exists' );
$id = $actor->get_identity_id();
$retirement = get_option( 'ax_actors_legacy_site_retired', false );
$before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_actors_actors_table() );
$key = axismundi_actors_signing_key_snapshot( $actor );
$assert( $actor->is_local() && 'Application' === $actor->get_type(), 'Local Application body' );
$assert( 'wordpress' === $actor->get_preferred_username(), 'Default wordpress handle' );
$assert( null === $actor->get_local_user_id() && null === $actor->get_personal_owner_user_id(), 'No WP user or personal owner binding' );
$assert( is_array( $retirement ) && ! in_array( $id, $retirement['ids'], true ), 'Retired instance Actor not reused' );
$assert( is_array( $key ), 'Local signing key provisioned' );
foreach ( range( 1, 3 ) as $_ ) {
	$assert( $id === axismundi_actors_ensure_system_actor()->get_identity_id(), 'Ensure reuses bound identity' );
}
$assert( $before === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_actors_actors_table() ), 'Ensure creates no duplicate Actors' );
$assert( false === axismundi_actors_set_status( $id, 'tombstone' ), 'Common deletion transition denied' );
$assert( false === axismundi_actors_cleanup_deleted_actor( $id ), 'Direct deletion cleanup denied' );
$assert( $key === axismundi_actors_signing_key_snapshot( axismundi_actors_get_system_actor() ), 'Deletion attempt preserves signing key' );
$assert( $actor->get_preferred_username() === axismundi_actors_get_system_actor()->get_preferred_username(), 'Deletion attempt preserves handle' );
$assert( false === get_option( 'ax_actors_instance_identity_id', false ), 'Legacy instance binding removed' );
$assert( axismundi_actors_retire_legacy_site_actor() && $retirement === get_option( 'ax_actors_legacy_site_retired' ), 'Retirement replays without new writes' );
foreach ( $retirement['ids'] as $retired_id ) {
	$retired = axismundi_actors_get_by_identity( $retired_id );
	$assert( $retired && 'tombstone' === $retired->get_status(), 'Old identity remains only as a Tombstone' );
	$assert( null === axismundi_actors_signing_key_snapshot( $retired ), 'Retired identity cannot sign' );
}
if ( function_exists( 'axismundi_op_post_article_generator' ) ) {
	$assert( $actor->get_uri() === axismundi_op_post_article_generator()['id'], 'Article generator uses the new system identity' );
}
if ( function_exists( 'axismundi_activitypub_bridge_legacy_local_actor' ) ) {
	$assert( null === axismundi_activitypub_bridge_legacy_local_actor( 0 ) && null === axismundi_activitypub_bridge_legacy_local_actor( -1 ), 'Legacy site submissions are not remapped to the new identity' );
}
axismundi_actors_seed();
$assert( $before === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_actors_actors_table() ), 'Activation seed replay creates neither duplicate nor legacy site Actor' );
$assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE identity_id=%d', axismundi_actors_managers_table(), $id ) ), 'No implicit operating principal grant' );
if ( function_exists( '\\Axismundi\\Jmap\\accounts_table' ) && '1' === get_option( 'ax_jmap_db_version' ) ) {
	$assert( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE actor_id=%d', \Axismundi\Jmap\accounts_table(), $id ) ), 'JMAP Account shares the Actor ID' );
}
$wpdb->query( 'START TRANSACTION' );
$icon_url = 'https://branding.example/site-icon.png';
$icon_filter = static function () use ( &$icon_url ) : string { return $icon_url; };
try {
	update_option( 'blogname', 'System branding audit' );
	update_option( 'site_icon', 0 );
	add_filter( 'get_site_icon_url', $icon_filter );
	axismundi_actors_set_text( $id, 'name', 'en', 'Stale custom system name' );
	$assert( 'System branding audit' === $actor->get_display_name(), 'Existing Actor object reads live site title' );
	$assert( 'System branding audit' === axismundi_actors_resolve_text( $actor, 'name', 'en' ), 'Site title wins over stored Actor name translation' );
	$assert( ! isset( axismundi_actors_name_map( $actor )['en']['name'] ), 'No stale Actor nameMap published' );
	$assert( $icon_url === $actor->get_icon_uri() && $icon_url === axismundi_actors_avatar_url( $actor ), 'Compact and block avatar use site icon' );
	$assert( str_contains( axismundi_actors_avatar_html( $actor ), $icon_url ), 'SSR avatar uses site icon' );
	$profile = axismundi_actors_profile_data( $actor );
	$assert( 'System branding audit' === $profile['name'] && $icon_url === $profile['avatar'], 'Profile data uses site branding' );
	if ( function_exists( 'axismundi_op_transform_object' ) ) {
		$document = axismundi_op_transform_object( $actor );
		$assert( is_array( $document ) && 'System branding audit' === $document['name'] && $icon_url === $document['icon']['url'], 'AP projection uses site title and icon' );
	}
	update_option( 'blogname', 'Changed system branding' );
	$assert( 'Changed system branding' === $actor->get_display_name(), 'Title change does not require Actor recreation' );
	$icon_url = '';
	$assert( '' === axismundi_actors_avatar_url( $actor ) && '' === axismundi_actors_avatar_html( $actor ), 'Cleared site icon does not fall back to Actor avatar or Gravatar' );
	remove_filter( 'get_site_icon_url', $icon_filter );
	update_option( 'ax_actors_system_actor_username', 'future_system_name', false );
	$assert( 'wordpress' === axismundi_actors_ensure_system_actor()->get_preferred_username(), 'Changing seed preference does not rename existing Actor' );
	delete_option( 'ax_actors_system_actor_username' );
	// Simulate a disabled body without invoking a transaction-owning transition inside this fixture.
	$wpdb->update( axismundi_actors_actors_table(), array( 'status' => 'disabled' ), array( 'id' => $id ) );
	$assert( 'disabled' === axismundi_actors_ensure_system_actor()->get_status(), 'Disabled system Actor is not reactivated or replaced' );
	delete_option( 'ax_actors_system_actor_id' );
	$assert( null === axismundi_actors_get_system_actor(), 'Getter never provisions' );
	$error = axismundi_actors_ensure_system_actor();
	$assert( is_wp_error( $error ) && 'ax_system_actor_handle_taken' === $error->get_error_code(), 'Unbound occupied handle is not adopted or suffixed' );
	add_option( 'ax_actors_system_actor_id', PHP_INT_MAX, '', false );
	$assert( is_wp_error( axismundi_actors_ensure_system_actor() ), 'Invalid binding fails closed rather than recreating' );
	$assert( $before === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_actors_actors_table() ), 'Recovery failures create no Actors' );
} finally {
	remove_filter( 'get_site_icon_url', $icon_filter );
	$wpdb->query( 'ROLLBACK' );
	foreach ( array( 'ax_actors_system_actor_id', 'ax_actors_system_actor_username', 'blogname', 'site_icon', 'notoptions', 'alloptions' ) as $cache_key ) { wp_cache_delete( $cache_key, 'options' ); }
}
$assert( $id === axismundi_actors_get_system_actor()->get_identity_id(), 'Fixture restores original system binding' );
echo "PASS system-actor {$checks}\n";
