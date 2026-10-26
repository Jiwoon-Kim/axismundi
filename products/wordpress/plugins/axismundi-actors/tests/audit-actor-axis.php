<?php
/** Cross-plugin Account selection, stored attribution and plural inventory audit. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/admin.php';
require_once WP_PLUGIN_DIR . '/axismundi-calendar/includes/admin-calendars.php';
global $wpdb;
$original_user = get_current_user_id();
$cookies = $_COOKIE;
$get = $_GET;
$users = $actors = $posts = $folders = $object_ids = array();
$checks = 0;
$gate = static fn() => true;
$adapter = static fn() => true;
$network = static fn() => new WP_Error( 'axis_no_http', 'No network in this audit.' );
$bridge = has_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound' );
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $ok ) { throw new RuntimeException( $label ); }
};
$before = $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A );
add_filter( 'axismundi_actors_account_selection_enabled', $gate, PHP_INT_MAX );
add_filter( 'axismundi_actors_account_selection_adapter_available', $adapter, PHP_INT_MAX );
add_filter( 'pre_http_request', $network );
if ( false !== $bridge ) { remove_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', $bridge ); }
try {
	foreach ( array( 'author', 'administrator' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'axaxis' . strtolower( wp_generate_password( 12, false, false ) ),
			'user_pass' => wp_generate_password(), 'role' => $role ) );
		$assert( ! is_wp_error( $id ), 'User fixture created' );
		$users[] = (int) $id;
	}
	$user = $users[0];
	wp_set_current_user( $user );
	foreach ( array( 'Person', 'Person', 'Organization', 'Group' ) as $index => $type ) {
		$actor = axismundi_actors_create_local( array( 'actor_type' => $type, 'actor_scope' => 'Person' === $type ? 'user' : 'managed',
			'local_user_id' => 0 === $index ? $user : null, 'personal_owner_user_id' => 'Person' === $type ? $user : null,
			'initial_owner_user_id' => $user, 'preferred_username' => 'axaxis' . strtolower( wp_generate_password( 12, false, false ) ) ) );
		$assert( $actor instanceof Axismundi_Actor, 'Account fixture created' );
		$actors[] = $actor->get_identity_id();
		$assert( ! is_wp_error( axismundi_actors_set_status( $actor->get_identity_id(), 'public' ) ), 'Account fixture published' );
	}
	list( $person_a, $person_b, $organization, $group ) = array_map( 'axismundi_actors_get_by_identity', $actors );
	$expires = time() + 600;
	$token = WP_Session_Tokens::get_instance( $user )->create( $expires );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user, $expires, 'logged_in', $token );
	$assert( true === axismundi_actors_set_account_selection( $actors[1] ), 'Select second Person B, not legacy Person A' );
	$assert( $actors[1] === axismundi_act_current_local_actor()->get_identity_id(), 'Social actor follows selected Person B' );
	$assert( $person_b->get_uri() === axismundi_cal_signed_in_actor_uri(), 'Calendar participation follows selected Account' );
	$assert( $actors[1] === axismundi_cal_authoring_actor_identity(), 'New Event authoring uses shared selection kernel' );
	$assert( $actors[1] === axismundi_forum_selected_actor( $user, true )->get_identity_id(), 'Forum submission uses selected Person B' );
	$choices = axismundi_cal_admin_creatable_authorities();
	$assert( isset( $choices[ $person_a->get_uri() ], $choices[ $person_b->get_uri() ], $choices[ $organization->get_uri() ] ), 'Explicit Calendar authority picker offers every directly authorized Account' );
	$inventory = axismundi_actors_list_local_actors( 'public' );
	$assert( isset( $inventory[ $actors[0] ], $inventory[ $actors[1] ], $inventory[ $actors[2] ] ), 'Transport inventory includes multiple Persons and Organization without User inference' );
	$assert( axismundi_forum_is_community( $actors[3] ), 'Published Group has Forum policy' );
	$assert( true === axismundi_forum_set_posting_policy( $actors[3], $user, 'open' ), 'Community accepts explicit Person submissions' );
	$id = wp_insert_post( array( 'post_type' => AXISMUNDI_FORUM_TOPIC_POST_TYPE, 'post_status' => 'draft',
		'post_author' => $user, 'post_title' => 'Second Account Topic fixture', 'post_content' => '<p>Stored publisher.</p>' ), true );
	$assert( ! is_wp_error( $id ), 'Topic source created' );
	$posts[] = (int) $id;
	$entry = axismundi_forum_admit_local_topic( $actors[3], $id, $user );
	$assert( ! is_wp_error( $entry ), 'Selected Person B submits a Topic' );
	$entry = axismundi_forum_get_topic_entry( $id );
	$assert( $actors[1] === (int) $entry['submission_actor_identity_id'], 'Initial submission freezes selected Account B' );
	$topic = get_post( $id );
	$uri = axismundi_forum_topic_object_uri( $topic );
	$create = axismundi_act_get_object_lifecycle( $uri );
	$assert( $create instanceof Axismundi_Activity && 'Create' === $create->get_type() && $person_b->get_uri() === $create->get_actor_uri(), 'Topic Create uses B, not post_author Person A' );
	$assert( true === axismundi_actors_set_account_selection( $actors[2] ), 'Switch to Organization' );
	$projection = axismundi_forum_topic_to_article( $topic );
	$assert( is_array( $projection ) && $person_b->get_uri() === $projection['attributedTo'], 'Topic projection retains B after switch to Organization' );
	$assert( null === axismundi_act_current_local_actor(), 'Organization does not bypass the existing Person social policy' );
	$assert( $organization->get_uri() === axismundi_cal_signed_in_actor_uri(), 'Calendar data identity may be an Organization' );
	$assert( $actors[2] === axismundi_actors_admin_profile_actor()->get_identity_id(), 'Actor Profile edits selected Organization' );
	$_GET = array();
	$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_actors_identities_table() );
	ob_start();
	try { axismundi_actors_render_admin_page(); $html = ob_get_contents(); } finally { ob_end_clean(); }
	$assert( str_contains( $html, 'name="identity_id" value="' . $actors[2] . '"' ) && str_contains( $html, esc_html( $organization->get_uri() ) ), 'Profile forms bind displayed Account identity' );
	$assert( $count === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_actors_identities_table() ), 'Profile GET creates no identities' );
	$assert( ! axismundi_actors_can_manage( $organization, $users[1] ), 'WP administrator without a principal grant cannot edit an Account' );
	$assert( is_wp_error( axismundi_actors_admin_profile_actor( 0 ) ), 'Invalid explicit profile target cannot fall back to current Account' );
	$grant = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE identity_id = %d AND principal_kind = 'wp_user' AND principal_id = %d", axismundi_actors_managers_table(), $actors[2], $user ), ARRAY_A );
	$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $actors[2], 'principal_kind' => 'wp_user', 'principal_id' => $user ) );
	$assert( is_wp_error( axismundi_actors_admin_profile_actor( $actors[2] ) ), 'Pinned profile target rechecks revoked authority' );
	$assert( false !== $wpdb->insert( axismundi_actors_managers_table(), $grant ), 'Fixture authority restored explicitly' );
	$assert( true === axismundi_actors_set_principal_role( $actors[1], 'wp_user', $users[1], 'editor' ), 'Another User may operate Person B' );
	$assert( true === axismundi_actors_set_principal_role( $actors[3], 'local_account', $actors[1], 'manager' ), 'Person B has explicit Group moderation authority' );
	$assert( isset( axismundi_forum_moderated_communities( $users[1] )[ $actors[3] ] ) && '' === axismundi_actors_account_role( $actors[3], $users[1] ), 'Plural Forum inventory sees B moderation without granting transitive Account access' );
	wp_update_post( array( 'ID' => $id, 'post_author' => $users[1] ) );
	$assert( $person_b->get_uri() === axismundi_forum_topic_author( get_post( $id ) )->get_uri(), 'Changing WordPress editor responsibility does not change Topic publisher' );
	$delete = axismundi_forum_record_topic_delete( get_post( $id ) );
	$assert( $delete instanceof Axismundi_Activity && 'Delete' === $delete->get_type() && $person_b->get_uri() === $delete->get_actor_uri(), 'Topic Delete uses recorded publisher B' );
	wp_update_post( array( 'ID' => $id, 'post_author' => $user ) );
	$wpdb->delete( axismundi_act_activities_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) );
	$wpdb->update( axismundi_forum_entries_table(), array( 'submission_actor_identity_id' => 0 ), array( 'source_post_id' => $id ) );
	$assert( null === axismundi_forum_topic_author( get_post( $id ) ) && is_wp_error( axismundi_forum_topic_to_article( get_post( $id ) ) ), 'Unknown Topic attribution is not inferred from either User or current Account' );

	$folder = axismundi_media_create_folder( 'Axis folder ' . wp_generate_uuid4(), 0, $user );
	$assert( ! is_wp_error( $folder ), 'Folder fixture created' );
	$folders[] = (int) $folder;
	$folder_uri = axismundi_media_folder_uri( $folder, false );
	$assert( '' === axismundi_media_folder_actor_uri( $folder ), 'Folder storage ownership does not invent publication attribution' );
	$recorded = axismundi_act_record_source_activity( array( 'type' => 'Create', 'actor' => $person_b->get_uri(), 'object' => $folder_uri ), 'outbound', 'axis-folder:' . wp_generate_uuid4() );
	$assert( $recorded instanceof Axismundi_Activity && $person_b->get_uri() === axismundi_media_folder_actor_uri( $folder ), 'Folder reads recorded B even while Organization is selected' );
	update_term_meta( $folder, '_ax_media_folder_owner', $users[1] );
	$assert( $person_b->get_uri() === axismundi_media_folder_actor_uri( $folder ), 'Folder storage-owner change does not reattribute publication' );
	axismundi_actors_clear_account_selection();
	$assert( null === axismundi_act_current_local_actor() && '' === axismundi_cal_signed_in_actor_uri()
		&& null === axismundi_forum_selected_actor( $user ) && is_wp_error( axismundi_actors_admin_profile_actor() ), 'Missing selection fails closed in every acting surface' );
	$assert( $person_b->get_uri() === axismundi_media_folder_actor_uri( $folder ), 'Historical folder attribution remains readable without selection' );
} finally {
	foreach ( $posts as $id ) {
		$post = get_post( $id );
		if ( $post instanceof WP_Post ) {
			$uri = axismundi_forum_topic_object_uri( $post );
			$identity = axismundi_actors_get_identity_by_uri( $uri );
			if ( is_array( $identity ) ) { $object_ids[] = (int) $identity['identity_id']; }
			$wpdb->delete( axismundi_act_activities_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) );
		}
		$wpdb->delete( axismundi_forum_entries_table(), array( 'source_post_id' => $id ) );
		wp_delete_post( $id, true );
	}
	foreach ( $folders as $id ) {
		$uri = axismundi_media_folder_uri( $id, false );
		$identity = axismundi_actors_get_identity_by_uri( $uri );
		if ( is_array( $identity ) ) { $object_ids[] = (int) $identity['identity_id']; }
		$wpdb->delete( axismundi_act_activities_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) );
		wp_delete_term( $id, AXISMUNDI_MEDIA_FOLDER_TAX );
	}
	foreach ( $actors as $id ) {
		$actor = axismundi_actors_get_by_identity( $id );
		if ( $actor instanceof Axismundi_Actor ) {
			$hash = hash( 'sha256', $actor->get_uri() );
			$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri_hash' => $hash ) );
			$wpdb->delete( axismundi_act_relations_table(), array( 'subject_actor_uri_hash' => $hash ) );
			$wpdb->delete( axismundi_act_relations_table(), array( 'object_actor_uri_hash' => $hash ) );
			$calendars = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE authority_actor_uri_hash = %s', axismundi_cal_calendars_table(), $hash ) );
			foreach ( $calendars as $calendar ) {
				foreach ( array( axismundi_cal_acl_table(), axismundi_cal_entries_list_table() ) as $table ) { $wpdb->delete( $table, array( 'calendar_id' => $calendar ) ); }
				$wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => $calendar ) );
			}
		}
		$wpdb->delete( axismundi_forum_memberships_table(), array( 'group_identity_id' => $id ) );
		$wpdb->delete( axismundi_forum_memberships_table(), array( 'actor_identity_id' => $id ) );
		$wpdb->delete( axismundi_forum_settings_table(), array( 'group_identity_id' => $id ) );
		foreach ( array( axismundi_actors_managers_table(), axismundi_actors_addresses_table(), axismundi_actors_signing_keys_table(), axismundi_actors_keys_table(), axismundi_actors_endpoints_table(), axismundi_actors_texts_table(), axismundi_actors_actors_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $id ) ); }
		$wpdb->delete( axismundi_actors_managers_table(), array( 'principal_kind' => 'local_account', 'principal_id' => $id ) );
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	foreach ( $object_ids as $id ) {
		$wpdb->delete( axismundi_op_objects_table(), array( 'identity_id' => $id ) );
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	wp_set_current_user( $original_user );
	foreach ( $users as $id ) {
		$root = axismundi_media_user_root( $id, false );
		if ( $root > 0 ) { wp_delete_term( $root, AXISMUNDI_MEDIA_FOLDER_TAX ); }
		wp_delete_user( $id );
	}
	$_COOKIE = $cookies;
	$_GET = $get;
	remove_filter( 'axismundi_actors_account_selection_enabled', $gate, PHP_INT_MAX );
	remove_filter( 'axismundi_actors_account_selection_adapter_available', $adapter, PHP_INT_MAX );
	remove_filter( 'pre_http_request', $network );
	if ( false !== $bridge ) { add_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', $bridge ); }
}
$assert( $before === $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A ), 'All pre-existing principal rows survive unchanged' );
printf( "PASS: %d Actor axis checks\n", $checks );
