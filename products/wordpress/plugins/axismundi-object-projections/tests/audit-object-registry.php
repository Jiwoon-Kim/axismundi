<?php
/**
 * Object vertical-slice regression (dev-only).
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__, 2 ) . '/axismundi-actors/includes/repository.php';
require_once dirname( __DIR__, 2 ) . '/axismundi-actors/includes/activitystreams-uri.php';
require_once dirname( __DIR__, 2 ) . '/axismundi-actors/includes/identity-registry.php';
require_once dirname( __DIR__ ) . '/includes/object-relations.php';
require_once dirname( __DIR__ ) . '/includes/object-registry.php';
require_once dirname( __DIR__ ) . '/includes/remote-objects.php';

global $wpdb;
$ax_results      = array();
$ax_identity_ids = array();
$ax_attachment   = 0;
$ax_actor_identity = 0;
$ax_remote_uri   = 'https://registry.remote.example/objects/audit-object';
$ax_remote_actor_uri = 'https://example.com/users/object-registry-fixture';

function ax_object_registry_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	axismundi_actors_install();
	$installed = axismundi_op_install();
	$objects   = axismundi_op_objects_table();
	$remote    = axismundi_op_remote_objects_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture verifies the Object slice schema.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$objects}" );
	ax_object_registry_assert(
		$ax_results,
		'the Object vertical slice is keyed by the shared identity and carries source, type, and attribution shadows',
		$installed && in_array( 'source_owner', $columns, true ) && in_array( 'as2_type_observed_at', $columns, true ) && in_array( 'attributed_identity_id', $columns, true )
	);

	$uri   = home_url( '/?p=987654' );
	$first = axismundi_op_register_object(
		array(
			'canonical_uri' => $uri,
			'origin'        => 'local',
			'source_owner'  => 'wordpress-core',
			'source_kind'   => 'post',
			'source_ref'    => 987654,
			'as2_type'      => 'Note',
		)
	);
	if ( is_array( $first ) ) {
		$ax_identity_ids[] = (int) $first['identity_id'];
	}
	$second = axismundi_op_register_object(
		array(
			'canonical_uri' => $uri,
			'origin'        => 'local',
			'source_owner'  => 'axismundi-note',
			'source_kind'   => 'note-post',
			'source_ref'    => 987654,
			'as2_type'      => 'Question',
		)
	);
	$identity = is_array( $first ) ? axismundi_actors_get_identity( (int) $first['identity_id'] ) : null;
	ax_object_registry_assert(
		$ax_results,
		'a source can re-declare Note as Question without changing its shared identity',
		is_array( $first ) && is_array( $second ) && (int) $first['identity_id'] === (int) $second['identity_id'] && 'Question' === (string) $second['as2_type'] && is_array( $identity ) && $uri === (string) $identity['canonical_uri']
	);

	$menu = new WP_Post(
		(object) array(
			'ID' => 987655,
			'post_type' => 'nav_menu_item',
			'post_status' => 'publish',
		)
	);
	ax_object_registry_assert( $ax_results, 'an unlisted WordPress post type receives no Object registration profile', null === axismundi_op_local_object_profile( $menu ) );

	$ax_attachment = (int) wp_insert_post(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image/jpeg',
			'post_title'     => 'Object registry attachment fixture',
		)
	);
	$attachment = $ax_attachment > 0 ? get_post( $ax_attachment ) : null;
	$media      = $attachment instanceof WP_Post ? axismundi_op_register_local_post_object( $attachment ) : null;
	if ( is_array( $media ) ) {
		$ax_identity_ids[] = (int) $media['identity_id'];
	}
	ax_object_registry_assert(
		$ax_results,
		'a core attachment registers as Image without the optional Media Library transformer',
		is_array( $media ) && 'Image' === (string) $media['as2_type'] && 'wordpress-core' === (string) $media['source_owner']
	);

	$stored = axismundi_op_store_remote_object(
		array(
			'id'           => $ax_remote_uri,
			'type'         => 'Article',
			'attributedTo' => $ax_remote_actor_uri,
			'content'      => '<p>Remote body.</p>',
		)
	);
	$remote_object = is_array( $stored ) ? axismundi_op_get_object_by_identity( (int) $stored['identity_id'] ) : null;
	ax_object_registry_assert(
		$ax_results,
		'an Object fetched before its author is known stores its URI with a null attributed identity shadow',
		is_array( $stored ) && (int) $stored['identity_id'] > 0 && null === $stored['attributed_identity_id'] && is_array( $remote_object ) && null === $remote_object['attributed_identity_id']
	);
	$actor = axismundi_actors_upsert_remote(
		array(
			'uri'                => $ax_remote_actor_uri,
			'actor_type'         => 'Person',
			'preferred_username' => 'object_registry_fixture',
			'display_name'       => 'Object Registry Fixture',
			'profile_url'        => $ax_remote_actor_uri,
			'endpoints'          => array( 'inbox' => $ax_remote_actor_uri . '/inbox', 'outbox' => $ax_remote_actor_uri . '/outbox' ),
			'payload'            => array( 'id' => $ax_remote_actor_uri, 'type' => 'Person', 'preferredUsername' => 'object_registry_fixture' ),
		)
	);
	$ax_actor_identity = $actor instanceof Axismundi_Actor ? $actor->get_identity_id() : 0;
	$resolved_object   = is_array( $stored ) ? axismundi_op_get_object_by_identity( (int) $stored['identity_id'] ) : null;
	$resolved_id       = is_array( $resolved_object ) ? axismundi_op_resolve_object_attributed_identity( $resolved_object ) : null;
	$resolved_row      = is_array( $stored ) ? axismundi_op_get_object_by_identity( (int) $stored['identity_id'] ) : null;
	ax_object_registry_assert(
		$ax_results,
		'an Object resolves a later-known author into its shadow without rewriting the canonical attribution URI',
		$ax_actor_identity > 0 && $ax_actor_identity === $resolved_id && is_array( $resolved_row ) && $ax_remote_actor_uri === (string) $resolved_row['attributed_to_uri'] && $ax_actor_identity === (int) $resolved_row['attributed_identity_id']
	);
	$canonical_remote = axismundi_op_remote_object_uri( 'HTTPS://REGISTRY.REMOTE.EXAMPLE:443/objects/./canonical' );
	ax_object_registry_assert(
		$ax_results,
		'the remote cache and Object identity use the same canonical URI before hashing their shadows',
		'https://registry.remote.example/objects/canonical' === $canonical_remote
	);

	if ( is_array( $stored ) ) {
		$ax_identity_ids[] = (int) $stored['identity_id'];
		$wpdb->update( $remote, array( 'identity_id' => null, 'attributed_identity_id' => null ), array( 'id' => (int) $stored['id'] ), array( '%d', '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- simulate a pre-v15 cache row.
		update_option( AXISMUNDI_OP_DB_VERSION_OPTION, '14', false );
		$backfilled = axismundi_op_install();
		$row        = axismundi_op_get_remote_object( $ax_remote_uri );
		ax_object_registry_assert(
			$ax_results,
		'the v15 upgrade backfills remote Object and newly resolvable author identity shadows without publishing anything',
		$backfilled && is_array( $row ) && (int) $row['identity_id'] === (int) $stored['identity_id'] && $ax_actor_identity === (int) $row['attributed_identity_id']
		);
	}
} finally {
	if ( $ax_attachment > 0 ) {
		wp_delete_post( $ax_attachment, true );
	}
	axismundi_op_delete_remote_object( $ax_remote_uri );
	foreach ( array_unique( array_filter( $ax_identity_ids ) ) as $identity_id ) {
		$wpdb->delete( axismundi_op_objects_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	if ( $ax_actor_identity > 0 ) {
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => $ax_actor_identity ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $ax_actor_identity ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $ax_actor_identity ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
}

$failed = count( array_filter( $ax_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_results ), $failed );
exit( $failed > 0 ? 1 : 0 );
