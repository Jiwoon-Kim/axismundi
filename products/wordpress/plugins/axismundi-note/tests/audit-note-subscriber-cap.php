<?php
/** Subscriber Note authoring capability regression (dev-only). */
defined( 'ABSPATH' ) || exit( 1 );

$results = array();
$user_id = 0;
$post_id = 0;
$other_id = 0;
$published_id = 0;
$operator_id = 0;
$old_user = get_current_user_id();
$suffix = strtolower( wp_generate_password( 8, false, false ) );
$assert = static function ( string $label, bool $ok ) use ( &$results ) : void {
	$results[] = $ok;
	printf( "[%s] %s\n", $ok ? 'PASS' : 'FAIL', $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
};

try {
	$user_id = wp_create_user( 'ax_note_sub_' . $suffix, wp_generate_password( 24 ), 'ax-note-sub-' . $suffix . '@example.test' );
	if ( is_wp_error( $user_id ) ) {
		throw new RuntimeException( $user_id->get_error_message() );
	}
	( new WP_User( $user_id ) )->set_role( 'subscriber' );
	$actor = axismundi_actors_ensure_for_user( $user_id );
	if ( ! $actor instanceof Axismundi_Actor || is_wp_error( axismundi_actors_register_handle( $actor->get_identity_id(), 'axnotesub' . $suffix ) ) || ! axismundi_actors_set_status( $actor->get_identity_id(), 'public' ) ) {
		throw new RuntimeException( 'Could not activate fixture Actor.' );
	}
	wp_set_current_user( $user_id );
	$assert( 'a subscriber with an activated public Actor receives the Note create capability', current_user_can( 'create_ax_notes' ) );
	$ax_note_type = get_post_type_object( AXISMUNDI_NOTE_POST_TYPE );
	$assert( 'a public Actor receives the Note list capability dynamically while publication remains the authorship gate', $ax_note_type instanceof WP_Post_Type && 'edit_ax_notes' === $ax_note_type->cap->edit_posts && 'create_ax_notes' === $ax_note_type->cap->publish_posts && current_user_can( $ax_note_type->cap->edit_posts ) && current_user_can( $ax_note_type->cap->publish_posts ) );
	$request = new WP_REST_Request( 'POST', '/wp/v2/' . AXISMUNDI_NOTE_POST_TYPE );
	$request->set_param( 'content', 'Subscriber reply body' );
	$request->set_param( 'status', 'draft' );
	$response = rest_do_request( $request );
	$data = $response->get_data();
	$post_id = is_array( $data ) ? (int) ( $data['id'] ?? 0 ) : 0;
	$assert( 'the Core Note REST controller creates that subscriber Note', 201 === $response->get_status() && $post_id > 0 && $user_id === (int) get_post_field( 'post_author', $post_id ) );
	$other_id = wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => 1, 'post_content' => 'Other' ), true );
	$assert( 'the subscriber cannot edit another user\'s Note', ! is_wp_error( $other_id ) && ! current_user_can( 'edit_post', (int) $other_id ) );
	$assert( 'the subscriber cannot delete or read another user\'s private Note', ! is_wp_error( $other_id ) && ! current_user_can( 'delete_post', (int) $other_id ) && ! current_user_can( 'read_post', (int) $other_id ) );
	$published_id = wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'publish', 'post_author' => $user_id, 'post_content' => 'Public' ), true );
	$assert( 'a published Note keeps Core\'s public read mapping instead of receiving do_not_allow', ! is_wp_error( $published_id ) && ! in_array( 'do_not_allow', map_meta_cap( 'read_post', 0, (int) $published_id ), true ) );
	$assert( 'a public-Actor author receives the plural primitives used by published Note edit and trash screens', ! is_wp_error( $published_id ) && current_user_can( $ax_note_type->cap->delete_posts ) && current_user_can( $ax_note_type->cap->edit_published_posts ) && current_user_can( $ax_note_type->cap->delete_published_posts ) );

	$operator_id = wp_create_user( 'ax_note_admin_' . $suffix, wp_generate_password( 24 ), 'ax-note-admin-' . $suffix . '@example.test' );
	if ( is_wp_error( $operator_id ) ) {
		throw new RuntimeException( $operator_id->get_error_message() );
	}
	( new WP_User( $operator_id ) )->set_role( 'administrator' );
	wp_set_current_user( $operator_id );
	$assert( 'an administrator without a public Actor can enter the Note list but cannot author a federated Note', current_user_can( 'edit_ax_notes' ) && ! current_user_can( 'create_ax_notes' ) );
	$assert( 'an administrator may delete and read another author\'s private Note but may not edit it', ! is_wp_error( $other_id ) && current_user_can( 'delete_post', (int) $other_id ) && current_user_can( 'read_post', (int) $other_id ) && ! current_user_can( 'edit_post', (int) $other_id ) );
	$assert( 'an administrator receives the plural delete primitive used by Note bulk trash actions', current_user_can( $ax_note_type->cap->delete_posts ) && current_user_can( $ax_note_type->cap->delete_others_posts ) );
} catch ( Throwable $error ) {
	$assert( 'the subscriber Note capability audit ran to completion: ' . $error->getMessage(), false );
} finally {
	wp_set_current_user( $old_user );
	if ( $post_id > 0 ) { wp_delete_post( $post_id, true ); }
	if ( ! is_wp_error( $other_id ) && $other_id > 0 ) { wp_delete_post( $other_id, true ); }
	if ( ! is_wp_error( $published_id ) && $published_id > 0 ) { wp_delete_post( $published_id, true ); }
	if ( $user_id > 0 ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $user_id ); }
	if ( $operator_id > 0 ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $operator_id ); }
}
$failures = count( array_filter( $results, static fn( bool $ok ) : bool => ! $ok ) );
printf( "\n== %d checks, %d failed ==\n", count( $results ), $failures ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
exit( $failures ? 1 : 0 );
