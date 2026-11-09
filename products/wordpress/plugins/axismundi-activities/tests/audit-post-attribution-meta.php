<?php
/** Current attribution and immutable history regression; disposable local fixtures only. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
global $wpdb;
$checks = 0;
$assert = static function ( $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks;
	echo "PASS $label\n";
};
$previous_user = get_current_user_id();
$users = $actors = $posts = $uris = array();
$stop_http = static fn() => new WP_Error( 'fixture_network_disabled', 'No external requests in this audit.' );
add_filter( 'pre_http_request', $stop_http );
remove_action( 'axismundi_op_object_publish_candidate', 'axismundi_act_on_object_publish_candidate', 10 );
try {
	foreach ( array( 'owner', 'stranger' ) as $name ) {
		$id = wp_insert_user( array( 'user_login' => 'axattr' . $name . strtolower( wp_generate_password( 9, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
		$assert( is_int( $id ), 'User fixture created' );
		$users[] = $id;
	}
	wp_set_current_user( $users[0] );
	foreach ( array( $users[0], $users[0], $users[1] ) as $user ) {
		$actor = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'personal_owner_user_id' => $user, 'initial_owner_user_id' => $user, 'preferred_username' => 'attr' . strtolower( wp_generate_password( 12, false, false ) ) ) );
		$assert( $actor instanceof Axismundi_Actor, 'Actor fixture created' );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
		$actors[] = axismundi_actors_get_by_identity( $actor->get_identity_id() );
	}
	$id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Attribution fixture', 'post_content' => 'body', 'post_author' => $users[0] ) );
	$posts[] = $id;
	$post = get_post( $id );
	$uri = axismundi_op_post_object_uri( $post );
	$uris[] = $uri;
	$assert( '' === axismundi_op_post_actor_uri( $post ), 'WP author does not imply attribution' );
	$assert( true === axismundi_op_set_post_attribution( $id, $actors[0]->get_uri(), $users[0] ), 'Explicit attribution before Create is allowed' );
	$assert( ! axismundi_act_get_object_lifecycle( $uri ), 'Setting attribution does not invent Create' );
	axismundi_act_on_object_publish_candidate( $post, $uri, $actors[0]->get_uri() );
	$assert( ! axismundi_act_get_object_lifecycle( $uri ), 'Attribution-only editor save does not trigger first publication' );
	$assert( $actors[0]->get_uri() === axismundi_op_post_actor_uri( $post ), 'OP reads current postmeta' );
	$assert( is_wp_error( axismundi_op_set_post_attribution( $id, $actors[2]->get_uri(), $users[0] ) ), 'WP editor still needs target Account authority' );
	$assert( is_wp_error( axismundi_op_set_post_attribution( $id, $actors[0]->get_uri(), 0 ) ), 'Anonymous cannot mutate attribution' );
	$create = axismundi_act_record_object_commit( axismundi_op_transform_object( $post ) );
	$assert( $create instanceof Axismundi_Activity && 'Create' === $create->get_type(), 'Publication records Create' );
	$original = $create->get_payload();
	$assert( true === axismundi_op_set_post_attribution( $id, $actors[1]->get_uri(), $users[0] ), 'Published attribution may change to another authorized Account' );
	$latest = axismundi_act_get_object_lifecycle( $uri );
	$assert( $latest && 'Update' === $latest->get_type() && $actors[1]->get_uri() === ( $latest->get_payload()['object']['attributedTo'] ?? '' ), 'Reassignment emits Update with current attribution' );
	$assert( $original === axismundi_act_get( $create->get_uri() )->get_payload(), 'Old Create remains byte-equivalent history' );
	$assert( $actors[1]->get_uri() === axismundi_act_get_object_attribution( $uri ), 'Current lifecycle authorization follows source metadata' );
	$failure = static fn() => new WP_Error( 'fixture', 'Publication failure' );
	add_filter( 'axismundi_op_post_attribution_changed', $failure, 1 );
	$failed = axismundi_op_set_post_attribution( $id, $actors[0]->get_uri(), $users[0] );
	remove_filter( 'axismundi_op_post_attribution_changed', $failure, 1 );
	$assert( is_wp_error( $failed ) && $actors[1]->get_uri() === axismundi_op_post_actor_uri( $post ), 'Failed Update rolls back current attribution' );
	$assert( is_wp_error( axismundi_op_set_post_attribution( $id, '', $users[0] ) ) && $actors[1]->get_uri() === axismundi_op_post_actor_uri( $post ), 'Unpublishable reassignment rolls back metadata' );
	$draft_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'REST attribution fixture' ) );
	$posts[] = $draft_id;
	$draft_uri = axismundi_op_post_object_uri( get_post( $draft_id ) );
	$uris[] = $draft_uri;
	$assert( true === axismundi_op_set_post_attribution( $draft_id, $actors[0]->get_uri(), $users[0], array( $actors[2]->get_uri() ) ), 'Draft starts with an existing co-attribution' );
	$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $draft_id );
	$request->set_body_params( array( 'axismundi_attribution' => array( 'publisher' => $actors[1]->get_uri() ) ) );
	$response = rest_do_request( $request );
	$assert( 200 === $response->get_status() && $actors[1]->get_uri() === axismundi_op_stored_post_attribution( $draft_id ), 'Block editor REST save persists the selected Actor' );
	$assert( array( $actors[2]->get_uri() ) === axismundi_op_stored_post_co_attribution( $draft_id ) && ! axismundi_act_get_object_lifecycle( $draft_uri ), 'Draft save preserves hidden co-attribution without a Create' );
	$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $draft_id );
	$request->set_body_params( array( 'status' => 'publish', 'axismundi_attribution' => array( 'publisher' => $actors[0]->get_uri() ) ) );
	$response = rest_do_request( $request );
	$assert( 200 === $response->get_status() && 'publish' === get_post_status( $draft_id ) && $actors[0]->get_uri() === axismundi_op_stored_post_attribution( $draft_id ) && empty( $GLOBALS['axismundi_act_attribution_only_saves'][$draft_id] ), 'Explicit Publish with a changed Actor is not an attribution-only save' );
	$attachment = wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/png', 'post_title' => 'Media attribution fixture', 'post_author' => $users[0] ) );
	$posts[] = $attachment;
	update_post_meta( $attachment, '_wp_attached_file', 'fixture.png' );
	$media = get_post( $attachment );
	$media_uri = axismundi_op_core_attachment_uri( $media );
	$uris[] = $media_uri;
	$assert( true === axismundi_op_set_post_attribution( $attachment, $actors[1]->get_uri(), $users[0] ), 'Attachment accepts independent publishing attribution' );
	$assert( ! axismundi_act_get_object_lifecycle( $media_uri ) && is_array( axismundi_op_get_object_by_uri( $media_uri ) ), 'Attachment enters registry without Create' );
	$assert( $actors[1]->get_uri() === axismundi_op_attachment_actor_uri( $media ) && (int) $media->post_author === $users[0], 'Upload user remains separate from attribution' );
	$assert( true === axismundi_op_set_post_attribution( $attachment, $actors[1]->get_uri(), $users[0], array( $actors[2]->get_uri() ) ), 'Attachment starts with an existing co-attribution' );
	$previous_post = $_POST;
	try {
		$_POST = array(
			'ax_op_attribution_nonce' => wp_create_nonce( 'ax_op_attribution_' . $attachment ),
			'ax_op_attributed_to'     => $actors[0]->get_uri(),
		);
		do_action( 'edit_attachment', $attachment );
	} finally {
		$_POST = $previous_post;
	}
	$assert( $actors[0]->get_uri() === axismundi_op_stored_post_attribution( $attachment ) && array( $actors[2]->get_uri() ) === axismundi_op_stored_post_co_attribution( $attachment ), 'Attachment Update preserves hidden co-attribution' );
	// Absence of Activities must not remove source attribution; neither reader has a ledger filter.
	$assert( false === has_filter( 'axismundi_op_local_object_attribution', 'axismundi_act_local_object_attribution' ) && $actors[1]->get_uri() === axismundi_op_post_actor_uri( $post ), 'OP does not use a ledger attribution provider' );
	$legacy = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Legacy attribution fixture', 'post_content' => 'legacy' ) );
	$posts[] = $legacy;
	$legacy_post = get_post( $legacy );
	$legacy_uri = axismundi_op_post_object_uri( $legacy_post );
	$uris[] = $legacy_uri;
	remove_action( 'axismundi_act_activity_recorded', 'axismundi_act_store_recorded_post_attribution', 5 );
	$old = axismundi_act_record_source_activity( array( 'type' => 'Create', 'actor' => $actors[0]->get_uri(), 'object' => $legacy_uri, 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ) ), 'outbound', 'attribution-fixture:' . wp_generate_uuid4() );
	add_action( 'axismundi_act_activity_recorded', 'axismundi_act_store_recorded_post_attribution', 5 );
	$report = axismundi_act_migrate_post_attribution( false, $legacy - 1, 1 );
	$assert( isset( $report['candidates'][$legacy] ) && ! metadata_exists( 'post', $legacy, AXISMUNDI_OP_ATTRIBUTED_TO_META ), 'Migration dry run does not write' );
	$report = axismundi_act_migrate_post_attribution( true, $legacy - 1, 1 );
	$assert( 1 === $report['written'] && $actors[0]->get_uri() === axismundi_op_stored_post_attribution( $legacy ), 'Explicit migration writes unambiguous evidence' );
	$assert( 1 === axismundi_act_migrate_post_attribution( true, $legacy - 1, 1 )['unchanged'], 'Migration is idempotent' );
	axismundi_op_store_post_attribution( $legacy_post, $actors[1]->get_uri() );
	$report = axismundi_act_migrate_post_attribution( true, $legacy - 1, 1 );
	$assert( array( $legacy ) === $report['conflicts'] && $actors[1]->get_uri() === axismundi_op_stored_post_attribution( $legacy ), 'Migration never overwrites conflicting metadata' );
	echo "PASS $checks attribution checks\n";
} finally {
	remove_filter( 'pre_http_request', $stop_http );
	remove_action( 'axismundi_act_activity_recorded', 'axismundi_act_store_recorded_post_attribution', 5 );
	foreach ( $uris as $uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) );
		$wpdb->delete( axismundi_op_objects_table(), array( 'canonical_uri_hash' => hash( 'sha256', $uri ) ) );
		axismundi_op_delete_object_listing_projection( $uri );
	}
	foreach ( $posts as $id ) { wp_delete_post( $id, true ); }
	foreach ( $actors as $actor ) {
		$id = $actor->get_identity_id();
		foreach ( array( axismundi_actors_addresses_table(), axismundi_actors_managers_table(), axismundi_actors_signing_keys_table(), axismundi_actors_actors_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $id ) ); }
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	foreach ( $users as $id ) { wp_delete_user( $id ); }
	wp_set_current_user( $previous_user );
	unset( $GLOBALS['axismundi_act_attribution_only_saves'] );
	add_action( 'axismundi_act_activity_recorded', 'axismundi_act_store_recorded_post_attribution', 5 );
	add_action( 'axismundi_op_object_publish_candidate', 'axismundi_act_on_object_publish_candidate', 10, 4 );
}
