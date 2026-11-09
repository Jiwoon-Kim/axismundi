<?php
/** Run with --skip-plugins=axismundi-activities,axismundi-activitypub-bridge. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( function_exists( 'axismundi_act_get_object_attribution' ) ) { throw new RuntimeException( 'Activities must be skipped, not merely unhooked.' ); }
$user = wp_insert_user( array( 'user_login' => 'opattr' . strtolower( wp_generate_password( 10, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
$actor = null;
$posts = $uris = array();
$checks = 0;
$assert = static function ( $ok, string $label ) use ( &$checks ) : void { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; echo "PASS $label\n"; };
try {
	wp_set_current_user( $user );
	$actor = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'personal_owner_user_id' => $user, 'initial_owner_user_id' => $user, 'preferred_username' => 'opattr' . strtolower( wp_generate_password( 12, false, false ) ) ) );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	foreach ( array( 'post', 'attachment' ) as $type ) {
		$id = wp_insert_post( array( 'post_type' => $type, 'post_author' => $user, 'post_status' => 'attachment' === $type ? 'inherit' : 'publish', 'post_title' => 'OP-only attribution', 'post_content' => 'body', 'post_mime_type' => 'attachment' === $type ? 'image/png' : '' ) );
		$posts[] = $id;
		if ( 'attachment' === $type ) { update_post_meta( $id, '_wp_attached_file', 'fixture.png' ); }
		$post = get_post( $id );
		$uri = 'attachment' === $type ? axismundi_op_core_attachment_uri( $post ) : axismundi_op_post_object_uri( $post );
		$uris[] = $uri;
		$assert( true === axismundi_op_set_post_attribution( $id, $actor->get_uri(), $user ), 'OP-only Account mutation: ' . $type );
		$doc = axismundi_op_transform_object( $post );
		$assert( is_array( $doc ) && $actor->get_uri() === ( $doc['attributedTo'] ?? '' ), 'OP-only JSON-LD retains attribution: ' . $type );
		$assert( is_array( axismundi_op_get_object_by_uri( $uri ) ), 'OP-only registry admits source: ' . $type );
	}
	echo "PASS $checks OP-only checks\n";
} finally {
	global $wpdb;
	foreach ( $posts as $id ) { wp_delete_post( $id, true ); }
	foreach ( $uris as $uri ) { $wpdb->delete( axismundi_op_objects_table(), array( 'canonical_uri_hash' => hash( 'sha256', $uri ) ) ); axismundi_op_delete_object_listing_projection( $uri ); }
	if ( $actor instanceof Axismundi_Actor ) {
		foreach ( array( axismundi_actors_addresses_table(), axismundi_actors_managers_table(), axismundi_actors_signing_keys_table(), axismundi_actors_actors_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $actor->get_identity_id() ) ); }
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $actor->get_identity_id() ) );
	}
	wp_delete_user( $user );
}
