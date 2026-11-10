<?php
/** Current attribution and immutable history regression; disposable local fixtures only. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once WP_PLUGIN_DIR . '/axismundi-object-projections/includes/admin-publishing-filters.php';
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
	$bulk_url = apply_filters( 'handle_bulk_actions-edit-post', admin_url( 'edit.php' ), 'ax_assign_publisher_' . $actors[1]->get_identity_id(), array( $id ) );
	$assert( str_contains( $bulk_url, 'ax_publisher_skipped=1' ) && $actors[0]->get_uri() === axismundi_op_stored_post_attribution( $id )
		&& $create->get_uri() === axismundi_act_get_object_lifecycle( $uri )->get_uri(), 'Bulk assignment skips a published post without recording Update' );
	$original = $create->get_payload();
	$replacement = axismundi_op_admin_assign_list_actor( $id, $actors[1]->get_uri(), $users[0], true );
	$assert( is_wp_error( $replacement ) && 'ax_op_attribution_frozen' === $replacement->get_error_code()
		&& $actors[0]->get_uri() === axismundi_op_stored_post_attribution( $id )
		&& $create->get_uri() === axismundi_act_get_object_lifecycle( $uri )->get_uri(), 'Published authority replacement is rejected before changing the source or ledger' );
	$assert( true === axismundi_op_set_post_attribution( $id, $actors[0]->get_uri(), $users[0], array( $actors[1]->get_uri() ) ), 'The original publisher can add a joint attribution' );
	$latest = axismundi_act_get_object_lifecycle( $uri );
	$assert( $latest && 'Update' === $latest->get_type() && $actors[0]->get_uri() === $latest->get_actor_uri()
		&& array( $actors[0]->get_uri(), $actors[1]->get_uri() ) === ( $latest->get_payload()['object']['attributedTo'] ?? null ), 'An addition emits Update under the original Create Actor' );
	$assert( $original === axismundi_act_get( $create->get_uri() )->get_payload(), 'Old Create remains byte-equivalent history' );
	$assert( $actors[0]->get_uri() === axismundi_act_get_object_attribution( $uri ), 'Current lifecycle authorization follows the original Create' );
	$failure = static fn() => new WP_Error( 'fixture', 'Publication failure' );
	add_filter( 'axismundi_op_post_attribution_changed', $failure, 1 );
	$failed = axismundi_op_set_post_attribution( $id, $actors[0]->get_uri(), $users[0], array( $actors[1]->get_uri(), $actors[2]->get_uri() ) );
	remove_filter( 'axismundi_op_post_attribution_changed', $failure, 1 );
	$assert( is_wp_error( $failed ) && array( $actors[1]->get_uri() ) === axismundi_op_stored_post_co_attribution( $id ), 'Failed Update rolls back the joint addition' );
	$removed = axismundi_op_set_post_attribution( $id, $actors[0]->get_uri(), $users[0], array() );
	$assert( is_wp_error( $removed ) && 'ax_op_attribution_removal' === $removed->get_error_code(), 'Published co-attribution cannot be removed' );
	$cleared = axismundi_op_set_post_attribution( $id, '', $users[0] );
	$assert( is_wp_error( $cleared ) && 'ax_op_attribution_frozen' === $cleared->get_error_code(), 'Published authority cannot be cleared' );
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
	$columns = apply_filters( 'manage_media_columns', array( 'title' => 'File', 'parent' => 'Uploaded to', 'date' => 'Date' ), false );
	$assert( array( 'title', 'parent', 'ax_attributed_to', 'date' ) === array_keys( $columns ), 'Media list places attribution after Uploaded to' );
	$render = static function ( string $hook, ...$args ) : string {
		ob_start();
		try {
			do_action( $hook, ...$args );
			return (string) ob_get_contents();
		} finally {
			ob_end_clean();
		}
	};
	$unassigned = $render( 'manage_media_custom_column', 'ax_attributed_to', $attachment );
	$assert( str_contains( $unassigned, esc_html__( 'Unassigned', 'axismundi-object-projections' ) ) && str_contains( $unassigned, 'aria-controls="find-actors"' ), 'Unassigned media shows an Assign action without inferring attribution from the uploader' );
	$default_options = axismundi_op_admin_list_actor_options( $attachment, $users[0], '' );
	$assert( count( $default_options ) <= 20 && in_array( $actors[0]->get_uri(), array_column( $default_options, 'uri' ), true )
		&& ! in_array( $actors[2]->get_uri(), array_column( $default_options, 'uri' ), true ), 'Administrator default picker is bounded to accessible actors' );
	$admin_options = axismundi_op_admin_list_actor_options( $attachment, $users[0], '@' . $actors[2]->get_preferred_username() );
	$assert( $actors[2]->get_uri() === ( $admin_options[0]['uri'] ?? '' ) && $admin_options[0]['adminAssignment'], 'Administrator handle search marks cross-workspace results as assignment only' );
	$assert( true === axismundi_op_admin_assign_list_actor( $attachment, $actors[2]->get_uri(), $users[0] )
		&& $actors[2]->get_uri() === axismundi_op_stored_post_attribution( $attachment )
		&& ! axismundi_act_get_object_lifecycle( $media_uri ), 'Administrator can assign a verified unpublished attachment without publishing' );
	$regular_attachment = wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/png', 'post_author' => $users[1] ) );
	$posts[] = $regular_attachment;
	$uris[] = axismundi_op_core_attachment_uri( get_post( $regular_attachment ) );
	$regular_user = new WP_User( $users[1] );
	$regular_user->set_role( 'author' );
	wp_set_current_user( $users[1] );
	try {
		$regular_options = axismundi_op_admin_list_actor_options( $regular_attachment, $users[1], $actors[2]->get_preferred_username() );
		$assert( $actors[2]->get_uri() === ( $regular_options[0]['uri'] ?? '' ) && ! $regular_options[0]['adminAssignment']
			&& array() === axismundi_op_admin_list_actor_options( $regular_attachment, $users[1], $actors[0]->get_preferred_username() )
			&& is_wp_error( axismundi_op_admin_list_actor_options( $attachment, $users[1], '' ) ), 'Regular picker searches only accessible actors and requires media edit permission' );
		$assert( is_wp_error( axismundi_op_admin_assign_list_actor( $regular_attachment, $actors[0]->get_uri(), $users[1] ) )
			&& is_wp_error( axismundi_op_admin_assign_list_actor( $attachment, $actors[2]->get_uri(), $users[1] ) ), 'Assignment rejects a forged actor and media the user cannot edit' );
		$assert( true === axismundi_op_admin_assign_list_actor( $regular_attachment, $actors[2]->get_uri(), $users[1] ), 'Regular user can assign an accessible actor to their media' );
	} finally {
		wp_set_current_user( $users[0] );
	}
	$regular_create = axismundi_act_record_source_activity( array( 'type' => 'Create', 'actor' => $actors[2]->get_uri(), 'object' => end( $uris ) ), 'outbound', 'media-picker-fixture:' . wp_generate_uuid4() );
	$assert( $regular_create instanceof Axismundi_Activity
		&& array() === axismundi_op_admin_list_actor_options( $regular_attachment, $users[0], $actors[2]->get_preferred_username() )
		&& is_wp_error( axismundi_op_admin_assign_list_actor( $regular_attachment, $actors[2]->get_uri(), $users[0] ) ), 'Published media does not permit cross-workspace search or assignment' );
	$assert( true === axismundi_op_set_post_attribution( $attachment, $actors[1]->get_uri(), $users[0] ), 'Attachment accepts independent publishing attribution' );
	$cell = $render( 'manage_media_custom_column', 'ax_attributed_to', $attachment );
	$assert( str_contains( $cell, esc_html( axismundi_actors_acting_actor_label( $actors[1] ) ) ) && str_contains( $cell, esc_attr( $actors[1]->get_uri() ) ), 'Media column renders the assigned Actor' );
	$previous_screen = $GLOBALS['current_screen'] ?? null;
	try {
		set_current_screen( 'upload' );
		$controls = $render( 'restrict_manage_posts', 'attachment', 'bar' );
		$assert( str_contains( $controls, 'name="ax_publishing_actor"' ) && str_contains( $controls, 'name="ax_not_federated"' ), 'Media list filter controls render in the core bar' );
	} finally {
		$GLOBALS['current_screen'] = $previous_screen;
	}
	$assert( ! axismundi_act_get_object_lifecycle( $media_uri ) && is_array( axismundi_op_get_object_by_uri( $media_uri ) ), 'Attachment enters registry without Create' );
	$assert( $actors[1]->get_uri() === axismundi_op_attachment_actor_uri( $media ) && (int) $media->post_author === $users[0], 'Upload user remains separate from attribution' );
	$legacy = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Unindexed publishing fixture', 'post_author' => $users[0] ) );
	$posts[] = $legacy;
	$legacy_uri = axismundi_op_post_object_uri( get_post( $legacy ) );
	$uris[] = $legacy_uri;
	$wpdb->delete( axismundi_op_objects_table(), array( 'source_owner' => 'wordpress-core', 'source_kind' => 'post', 'source_ref' => $legacy ) );
	$search = static function ( string $type, array $ids, string $actor, bool $unpublished = false, array $extra = array() ) : array {
		$query = new WP_Query( $extra + array( 'fields' => 'ids', 'post_type' => $type, 'post_status' => 'any', 'post__in' => $ids, 'ax_op_publishing_selection' => array( 'actor' => $actor, 'unpublished' => $unpublished ) ) );
		return array_map( 'intval', $query->posts );
	};
	$assert( array( $legacy ) === $search( 'post', array( $legacy, $id ), 'unassigned', true ), 'Unassigned and Not federated include an eligible post without a registry row' );
	$assert( array() === axismundi_op_admin_list_actor_options( $legacy, $users[0], $actors[2]->get_preferred_username() )
		&& is_wp_error( axismundi_op_admin_assign_list_actor( $legacy, $actors[2]->get_uri(), $users[0] ) ), 'Post picker never grants the media-only administrator exception' );
	add_filter( 'axismundi_actors_account_selection_enabled', '__return_false', PHP_INT_MAX );
	try {
		update_user_meta( $users[0], AXISMUNDI_ACTORS_ACTING_META, $actors[0]->get_identity_id() );
		foreach ( array( false, true ) as $preassigned ) {
			$list_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'List Create fixture', 'post_content' => 'body' ) );
			$posts[] = $list_id;
			$list_post = get_post( $list_id );
			$list_uri = axismundi_op_post_object_uri( $list_post );
			$uris[] = $list_uri;
			if ( $preassigned ) {
				$assert( true === axismundi_op_set_post_attribution( $list_id, $actors[1]->get_uri(), $users[0], array( $actors[0]->get_uri() ) ), 'List Create fixture has a distinct authority and co-author' );
				$cell = $render( 'axismundi_op_post_federation_actions', $list_post );
				$handles = axismundi_actors_mention_handle( $actors[1] ) . ', ' . axismundi_actors_mention_handle( $actors[0] );
				$assert( str_contains( $cell, esc_html__( 'Attributed to', 'axismundi-object-projections' ) . '<br>' )
					&& str_contains( $cell, esc_html( $handles ) )
					&& str_contains( $cell, 'data-current-actor="' . esc_attr( $actors[1]->get_uri() ) . '"' ), 'Post list renders all handles below the label but Assign still selects only the authority' );
				$mismatch = axismundi_act_submit_post_list_create( $list_post );
				$assert( is_wp_error( $mismatch ) && 'ax_act_post_list_actor_mismatch' === $mismatch->get_error_code() && ! axismundi_act_get_object_lifecycle( $list_uri ), 'Acting co-author cannot submit as the assigned authority' );
				update_user_meta( $users[0], AXISMUNDI_ACTORS_ACTING_META, $actors[1]->get_identity_id() );
			}
			$expected_actor = $actors[ $preassigned ? 1 : 0 ]->get_uri();
			$created = axismundi_act_submit_post_list_create( $list_post );
			$assert( $created instanceof Axismundi_Activity && 'Create' === $created->get_type() && $expected_actor === $created->get_actor_uri()
				&& $expected_actor === axismundi_op_stored_post_attribution( $list_id ), $preassigned ? 'Matching authority can submit Create with a co-author' : 'Unassigned list Create stores the acting Actor as authority' );
		}
	} finally {
		remove_filter( 'axismundi_actors_account_selection_enabled', '__return_false', PHP_INT_MAX );
		delete_user_meta( $users[0], AXISMUNDI_ACTORS_ACTING_META );
	}
	$assert( array( $id ) === $search( 'post', array( $legacy, $id ), (string) $actors[0]->get_identity_id(), false, array( 'author' => $users[0] ) ), 'Actor and native author filters intersect' );
	$assert( ! $search( 'post', array( $id ), (string) $actors[0]->get_identity_id(), true ), 'Not federated excludes a published object after attribution changes' );
	$assert( ! $search( 'post', array( $legacy, $id ), (string) $actors[2]->get_identity_id() ), 'Unauthorized Actor filter returns no rows' );
	$assert( array( $attachment ) === $search( 'attachment', array( $attachment ), (string) $actors[1]->get_identity_id(), true, array( 'post_status' => 'inherit' ) ), 'Media attachment supports both filters' );
	$old_request = $_REQUEST;
	try {
		$_REQUEST['query'] = array( 'ax_publishing_actor' => (string) $actors[1]->get_identity_id(), 'ax_not_federated' => '1' );
		$grid_args = apply_filters( 'ajax_query_attachments_args', array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post__in' => array( $attachment ), 'fields' => 'ids' ) );
		$assert( array( $attachment ) === array_map( 'intval', ( new WP_Query( $grid_args ) )->posts ), 'Media grid AJAX propagates both filters' );
	} finally {
		$_REQUEST = $old_request;
	}
	$bulk_url = apply_filters( 'handle_bulk_actions-edit-post', admin_url( 'edit.php' ), 'ax_assign_publisher_' . $actors[1]->get_identity_id(), array( $legacy ) );
	$assert( str_contains( $bulk_url, 'ax_publisher_assigned=1' ) && $actors[1]->get_uri() === axismundi_op_stored_post_attribution( $legacy )
		&& ! axismundi_act_get_object_lifecycle( $legacy_uri ), 'Bulk assignment registers an unpublished post without emitting an activity' );
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
	$assert( false === has_filter( 'axismundi_op_local_object_attribution', 'axismundi_act_local_object_attribution' ) && $actors[0]->get_uri() === axismundi_op_post_actor_uri( $post ), 'OP does not use a ledger attribution provider' );
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
	$assert( $actors[0]->get_uri() === axismundi_act_get_object_attribution( $legacy_uri )
		&& is_wp_error( axismundi_op_set_post_attribution( $legacy, $actors[1]->get_uri(), $users[0] ) ), 'Conflicting legacy metadata cannot override Create authority' );
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
