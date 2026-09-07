<?php
/** Shared-folder OrderedCollection regression (dev-only; dist-excluded). */
defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$results = array();
$terms = array();
$posts = array();
$users = array();
$identities = array();

function ax_op_folder_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
}

if ( ! axismundi_op_media_folder_available() ) {
	echo "SKIP: Media Library folder federation API unavailable.\n";
	exit( 0 );
}

try {
	$user = wp_insert_user( array( 'user_login' => 'ax_op_folder_owner', 'user_pass' => wp_generate_password(), 'user_email' => 'ax_op_folder_owner@example.com', 'role' => 'author' ) );
	if ( is_wp_error( $user ) ) { throw new Exception( 'user setup failed' ); }
	$users[] = (int) $user;
	$actor = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'local_user_id' => (int) $user ) );
	if ( is_wp_error( $actor ) ) { throw new Exception( 'actor setup failed' ); }
	$identities[] = $actor->get_identity_id();
	axismundi_actors_register_handle( $actor->get_identity_id(), 'op_folder_' . (int) $user );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );

	$folder = axismundi_media_create_folder( 'Federated folder', 0, (int) $user );
	if ( is_wp_error( $folder ) ) { throw new Exception( 'folder setup failed' ); }
	$terms[] = (int) $folder;
	axismundi_media_set_folder_tier( (int) $folder, 'public', (int) $user );

	foreach ( array( 'First image', 'Second image' ) as $index => $title ) {
		$id = wp_insert_attachment( array( 'post_title' => $title, 'post_status' => 'inherit', 'post_author' => (int) $user, 'post_mime_type' => 'image/jpeg' ) );
		$posts[] = (int) $id;
		update_post_meta( (int) $id, '_ax_media_visibility', 'public' );
		update_post_meta( (int) $id, '_wp_attachment_image_alt', 'Alt ' . ( $index + 1 ) );
		axismundi_media_set_attachment_folder( (int) $id, (int) $folder );
		update_post_meta( (int) $id, '_ax_media_folder_added_at', gmdate( 'Y-m-d H:i:s', time() + $index ) );
	}

	$uuid = axismundi_media_folder_identity_uuid( (int) $folder, false );
	$folder_identity = axismundi_actors_get_identity_by_uuid( $uuid );
	if ( is_array( $folder_identity ) ) { $identities[] = (int) $folder_identity['identity_id']; }
	$root = axismundi_op_transform_collection( new Axismundi_OP_Media_Folder_Collection( get_term( (int) $folder, AXISMUNDI_MEDIA_FOLDER_TAX ) ) );
	ax_op_folder_assert( $results, 'public folder projects as an OrderedCollection with stable UUID identity', is_array( $root ) && 'OrderedCollection' === $root['type'] && axismundi_media_folder_uri( (int) $folder, false ) === $root['id'] );
	ax_op_folder_assert( $results, 'root advertises totalItems and a bounded first page', 2 === (int) ( $root['totalItems'] ?? -1 ) && str_ends_with( (string) ( $root['first'] ?? '' ), '/page/1' ) );
	ax_op_folder_assert( $results, 'folder attribution is the owner Person Actor while local ownership stays on the media author', $actor->get_uri() === ( $root['attributedTo'] ?? '' ) && (int) $user === axismundi_media_folder_owner( (int) $folder ) );

	$page = axismundi_op_transform_collection( new Axismundi_OP_Media_Folder_Collection( get_term( (int) $folder, AXISMUNDI_MEDIA_FOLDER_TAX ), 1 ) );
	ax_op_folder_assert( $results, 'first page contains direct Attachment descriptors in folder-added order', is_array( $page ) && 'OrderedCollectionPage' === $page['type'] && 2 === count( $page['orderedItems'] ?? array() ) && 'Second image' === ( $page['orderedItems'][0]['name'] ?? '' ) );
	ax_op_folder_assert( $results, 'embedded items do not repeat a nested JSON-LD context', ! isset( $page['orderedItems'][0]['@context'] ) && ( $page['partOf'] ?? '' ) === $root['id'] );

	update_post_meta( $posts[0], '_ax_media_visibility', 'private' );
	$anonymous_root = axismundi_op_transform_collection( new Axismundi_OP_Media_Folder_Collection( get_term( (int) $folder, AXISMUNDI_MEDIA_FOLDER_TAX ) ) );
	ax_op_folder_assert( $results, 'anonymous collection count excludes private media even when the caller is an administrator', is_array( $anonymous_root ) && 1 === (int) $anonymous_root['totalItems'] );

	$resolved = axismundi_media_folder_from_identity_uuid( $uuid );
	ax_op_folder_assert( $results, 'UUID route lookup resolves exactly one local folder', $resolved instanceof WP_Term && (int) $folder === $resolved->term_id );

	update_post_meta( $posts[0], '_ax_media_visibility', 'public' );

	// A folder is an OS-directory affordance, so its listing carries its children too
	// (FEDERATED-MEDIA §3.1). The hidden child is named to sort FIRST, so its absence
	// cannot be mistaken for an ordering accident.
	$visible_child = axismundi_media_create_folder( 'ZZ Visible child', (int) $folder, (int) $user );
	$hidden_child  = axismundi_media_create_folder( 'AA Hidden child', (int) $folder, (int) $user );
	if ( is_wp_error( $visible_child ) || is_wp_error( $hidden_child ) ) { throw new Exception( 'child setup failed' ); }
	$terms[] = (int) $visible_child;
	$terms[] = (int) $hidden_child;
	foreach ( array( $visible_child, $hidden_child ) as $child_term ) {
		$child_uuid = axismundi_media_folder_identity_uuid( (int) $child_term, false );
		$child_row  = '' !== $child_uuid ? axismundi_actors_get_identity_by_uuid( $child_uuid ) : null;
		if ( is_array( $child_row ) ) { $identities[] = (int) $child_row['identity_id']; }
	}
	axismundi_media_set_folder_tier( (int) $visible_child, 'public', (int) $user );
	axismundi_media_set_folder_tier( (int) $hidden_child, 'private', (int) $user );

	$mixed_root = axismundi_op_transform_collection( new Axismundi_OP_Media_Folder_Collection( get_term( (int) $folder, AXISMUNDI_MEDIA_FOLDER_TAX ) ) );
	ax_op_folder_assert( $results, 'totalItems counts visible children alongside media, and excludes a private child', is_array( $mixed_root ) && 3 === (int) ( $mixed_root['totalItems'] ?? -1 ) );

	$mixed_page = axismundi_op_transform_collection( new Axismundi_OP_Media_Folder_Collection( get_term( (int) $folder, AXISMUNDI_MEDIA_FOLDER_TAX ), 1 ) );
	$mixed_items = is_array( $mixed_page ) ? (array) ( $mixed_page['orderedItems'] ?? array() ) : array();
	ax_op_folder_assert(
		$results,
		'children lead the listing and media follow, never interleaved',
		3 === count( $mixed_items )
			&& 'OrderedCollection' === ( $mixed_items[0]['type'] ?? '' )
			&& 'ZZ Visible child' === ( $mixed_items[0]['name'] ?? '' )
			&& 'Image' === ( $mixed_items[1]['type'] ?? '' )
			&& 'Image' === ( $mixed_items[2]['type'] ?? '' )
	);
	ax_op_folder_assert(
		$results,
		'a private child is absent from the listing even though its name would sort first',
		! in_array( 'AA Hidden child', array_column( $mixed_items, 'name' ), true )
			&& axismundi_media_folder_uri( (int) $hidden_child, false ) !== ( $mixed_items[0]['id'] ?? '' )
	);
	ax_op_folder_assert(
		$results,
		'a child is a shallow reference: identity, name, count, and a way in — never its own items',
		axismundi_media_folder_uri( (int) $visible_child, false ) === ( $mixed_items[0]['id'] ?? '' )
			&& 0 === (int) ( $mixed_items[0]['totalItems'] ?? -1 )
			&& ! isset( $mixed_items[0]['orderedItems'] )
			&& ! isset( $mixed_items[0]['first'] )
			&& '' !== (string) ( $mixed_items[0]['url'] ?? '' )
	);

	// Page boundaries have to stay stable where they cut across both kinds.
	$paged = axismundi_media_folder_federation_entries( (int) $folder, 1, 2 );
	$paged_second = axismundi_media_folder_federation_entries( (int) $folder, 2, 2 );
	ax_op_folder_assert(
		$results,
		'pagination spans children and media as one sequence',
		is_array( $paged ) && 3 === (int) $paged['total'] && 2 === (int) $paged['pages']
			&& 'folder' === ( $paged['entries'][0]['kind'] ?? '' )
			&& 'media' === ( $paged['entries'][1]['kind'] ?? '' )
			&& is_array( $paged_second ) && 1 === count( $paged_second['entries'] )
			&& 'media' === ( $paged_second['entries'][0]['kind'] ?? '' )
	);

	// A parent turning private must take its children out of listings with it.
	axismundi_media_set_folder_tier( (int) $folder, 'private', (int) $user );
	ax_op_folder_assert(
		$results,
		'a child inheriting a newly private parent stops being federated',
		! axismundi_media_folder_federation_allowed( (int) $visible_child )
			&& array() === axismundi_media_folder_federation_children( (int) $folder )
	);
	axismundi_media_set_folder_tier( (int) $folder, 'public', (int) $user );

	axismundi_media_set_folder_tier( (int) $folder, 'private', (int) $user );
	$hidden = axismundi_op_transform_collection( new Axismundi_OP_Media_Folder_Collection( get_term( (int) $folder, AXISMUNDI_MEDIA_FOLDER_TAX ) ) );
	ax_op_folder_assert( $results, 'private folder is fail-closed without deleting its identity', is_wp_error( $hidden ) && 'ax_op_not_public' === $hidden->get_error_code() && null !== axismundi_actors_get_identity_by_uuid( $uuid ) );
} finally {
	foreach ( $posts as $post_id ) { wp_delete_attachment( $post_id, true ); }
	foreach ( $terms as $term_id ) { wp_delete_term( $term_id, AXISMUNDI_MEDIA_FOLDER_TAX ); }
	foreach ( $users as $user_id ) {
		$root_id = axismundi_media_user_root( $user_id, false );
		if ( $root_id > 0 ) { wp_delete_term( $root_id, AXISMUNDI_MEDIA_FOLDER_TAX ); }
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );
	}
	foreach ( array_unique( $identities ) as $identity_id ) {
		$wpdb->delete( axismundi_actors_addresses_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
}

$failed = count( array_filter( $results, static fn( $ok ) => ! $ok ) );
printf( "\n== %d checks, %d failed ==\n", count( $results ), $failed ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
if ( $failed ) { exit( 1 ); }
