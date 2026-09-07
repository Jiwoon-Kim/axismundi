<?php
/**
 * Folder federation identity regression (dev-only; dist-excluded).
 *
 * Locks: every folder gets a permanent UUID at creation but stays unpublished; the UUID
 * survives rename and move; the identity URI is UUID-keyed, not the display path; roots
 * never get one; visibility drives publication both ways; private/gated folders stay
 * fail-closed; deletion tombstones rather than deletes; a dangling UUID self-heals.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_results   = array();
$ax_terms     = array();
$ax_users     = array();
$ax_uuids     = array();
$ax_actor_ids = array();

/**
 * @param array  $results   Accumulator.
 * @param string $label     Assertion label.
 * @param bool   $condition Result.
 */
function ax_folder_identity_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

// Checked before the try: exit() skips finally, and there is nothing to clean up yet.
if ( ! axismundi_media_identity_registry_available() ) {
	echo "SKIP: Axismundi Actors is not active; folder identity is optional by design.\n";
	exit( 0 );
}

try {
	$owner = wp_insert_user(
		array(
			'user_login' => 'ax_folder_identity_owner',
			'user_pass'  => wp_generate_password(),
			'user_email' => 'ax_folder_identity_owner@example.com',
			'role'       => 'author',
		)
	);
	if ( is_wp_error( $owner ) ) {
		throw new Exception( 'could not create the owner user' );
	}
	$ax_users[] = (int) $owner;

	// A folder is attributedTo a PUBLIC Actor. create_local() makes an internal one —
	// record existence is not publication — so publish it explicitly, or every
	// federation assertion below would pass for the wrong reason.
	$actor = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'local_user_id' => (int) $owner ) );
	if ( is_wp_error( $actor ) ) {
		throw new Exception( 'could not create the owner actor' );
	}
	$ax_actor_ids[] = $actor->get_identity_id();
	axismundi_actors_register_handle( $actor->get_identity_id(), 'folder_owner_' . (int) $owner );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );

	$folder = axismundi_media_create_folder( 'Shared travel', 0, (int) $owner );
	if ( is_wp_error( $folder ) ) {
		throw new Exception( 'could not create the folder: ' . $folder->get_error_message() );
	}
	$ax_terms[] = (int) $folder;

	$uuid = axismundi_media_folder_identity_uuid( (int) $folder, false );
	$ax_uuids[] = $uuid;
	ax_folder_identity_assert(
		$ax_results,
		'a folder gets a permanent UUID at creation, without being asked for one',
		'' !== $uuid && $uuid === (string) get_term_meta( (int) $folder, AXISMUNDI_MEDIA_FOLDER_UUID_META, true )
	);

	$identity = axismundi_actors_get_identity_by_uuid( $uuid );
	ax_folder_identity_assert(
		$ax_results,
		'the identity is registered as a folder kind, local, and not an actor',
		is_array( $identity ) && 'folder' === $identity['object_kind'] && 'local' === $identity['origin']
	);

	ax_folder_identity_assert(
		$ax_results,
		'the identity URI is UUID-keyed, never the mutable display path',
		home_url( '/media/folder/' . $uuid ) === axismundi_media_folder_uri( (int) $folder )
			&& ! str_contains( axismundi_media_folder_uri( (int) $folder ), 'Shared' )
	);

	// The whole point of a UUID anchor: renaming and moving must not touch identity.
	axismundi_media_rename_folder( (int) $folder, 'Renamed travel', (int) $owner );
	$parent = axismundi_media_create_folder( 'Parent', 0, (int) $owner );
	if ( ! is_wp_error( $parent ) ) {
		$ax_terms[] = (int) $parent;
		wp_update_term( (int) $folder, AXISMUNDI_MEDIA_FOLDER_TAX, array( 'parent' => (int) $parent ) );
	}
	ax_folder_identity_assert(
		$ax_results,
		'the UUID and URI survive a rename and a move',
		$uuid === axismundi_media_folder_identity_uuid( (int) $folder, false )
			&& home_url( '/media/folder/' . $uuid ) === axismundi_media_folder_uri( (int) $folder )
	);

	$root = axismundi_media_user_root( (int) $owner );
	ax_folder_identity_assert(
		$ax_results,
		'a hidden per-user root is a namespace mechanism and never gets an identity',
		'' === axismundi_media_folder_identity_uuid( $root )
			&& '' === (string) get_term_meta( $root, AXISMUNDI_MEDIA_FOLDER_UUID_META, true )
	);

	// Record existence and publication are separate steps.
	axismundi_media_set_folder_tier( (int) $folder, 'private', (int) $owner );
	$private_identity = axismundi_actors_get_identity_by_uuid( $uuid );
	ax_folder_identity_assert(
		$ax_results,
		'a private folder keeps its identity record but is not published',
		is_array( $private_identity ) && 'internal' === $private_identity['status']
			&& ! axismundi_media_folder_federation_allowed( (int) $folder )
	);

	axismundi_media_set_folder_tier( (int) $folder, 'public', (int) $owner );
	$public_identity = axismundi_actors_get_identity_by_uuid( $uuid );
	ax_folder_identity_assert(
		$ax_results,
		'making a folder public publishes its identity',
		is_array( $public_identity ) && 'public' === $public_identity['status']
			&& axismundi_media_folder_federation_allowed( (int) $folder )
	);

	// Publication is reversible, and withdrawal is not a tombstone.
	axismundi_media_set_folder_tier( (int) $folder, 'private', (int) $owner );
	$withdrawn = axismundi_actors_get_identity_by_uuid( $uuid );
	ax_folder_identity_assert(
		$ax_results,
		'withdrawing publication returns the identity to internal, never a tombstone',
		is_array( $withdrawn ) && 'internal' === $withdrawn['status']
	);

	axismundi_media_set_folder_tier( (int) $folder, 'public', (int) $owner );
	axismundi_media_set_folder_access( (int) $folder, 'password', 'secret', (int) $owner );
	ax_folder_identity_assert(
		$ax_results,
		'a gated folder is fail-closed even while public',
		! axismundi_media_folder_federation_allowed( (int) $folder )
			&& 'internal' === ( axismundi_actors_get_identity_by_uuid( $uuid )['status'] ?? '' )
	);
	axismundi_media_set_folder_access( (int) $folder, 'open', null, (int) $owner );

	// A child inherits a private ancestor, so its identity must withdraw with it.
	$child = axismundi_media_create_folder( 'Child', (int) $folder, (int) $owner );
	if ( ! is_wp_error( $child ) ) {
		$ax_terms[]  = (int) $child;
		$child_uuid  = axismundi_media_folder_identity_uuid( (int) $child, false );
		$ax_uuids[]  = $child_uuid;
		axismundi_media_set_folder_tier( (int) $folder, 'private', (int) $owner );
		ax_folder_identity_assert(
			$ax_results,
			'a descendant of a newly private folder withdraws with its ancestor',
			'' !== $child_uuid
				&& ! axismundi_media_folder_federation_allowed( (int) $child )
				&& 'internal' === ( axismundi_actors_get_identity_by_uuid( $child_uuid )['status'] ?? '' )
		);
		axismundi_media_set_folder_tier( (int) $folder, 'public', (int) $owner );
	}

	// A restored DB or purged registry must not leave the term pointing at nothing.
	$dangling = 'deadbeef-0000-4000-8000-000000000000';
	update_term_meta( (int) $folder, AXISMUNDI_MEDIA_FOLDER_UUID_META, $dangling );
	$healed = axismundi_media_folder_identity_uuid( (int) $folder );
	$ax_uuids[] = $healed;
	ax_folder_identity_assert(
		$ax_results,
		'a UUID the registry no longer knows is re-registered rather than left dangling',
		'' !== $healed && $healed !== $dangling && null !== axismundi_actors_get_identity_by_uuid( $healed )
	);

	// A peer may hold this URI; it must resolve to "gone", not to nothing.
	$doomed = axismundi_media_create_folder( 'Doomed', 0, (int) $owner );
	if ( ! is_wp_error( $doomed ) ) {
		$doomed_uuid = axismundi_media_folder_identity_uuid( (int) $doomed, false );
		$ax_uuids[]  = $doomed_uuid;
		axismundi_media_delete_folder( (int) $doomed, (int) $owner );
		$after = axismundi_actors_get_identity_by_uuid( $doomed_uuid );
		ax_folder_identity_assert(
			$ax_results,
			'deleting a folder tombstones its identity and never reissues the UUID',
			'' !== $doomed_uuid && is_array( $after ) && 'tombstone' === $after['status']
		);
	}
} finally {
	foreach ( $ax_terms as $ax_term ) {
		wp_delete_term( $ax_term, AXISMUNDI_MEDIA_FOLDER_TAX );
	}
	foreach ( $ax_users as $ax_user ) {
		$ax_root = axismundi_media_user_root( $ax_user, false );
		if ( $ax_root > 0 ) {
			wp_delete_term( $ax_root, AXISMUNDI_MEDIA_FOLDER_TAX );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $ax_user );
	}
	// Only rows this fixture created are removed, keyed by the exact UUIDs and identity
	// ids it recorded — never a kind-wide or scope-wide sweep. The tombstone contract
	// binds production, not a test's own cleanup.
	$ax_table = axismundi_actors_identities_table();
	foreach ( array_filter( $ax_uuids ) as $ax_uuid ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $ax_table, array( 'uuid' => $ax_uuid ), array( '%s' ) );
	}
	foreach ( array_unique( $ax_actor_ids ) as $ax_actor_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_addresses_table(), array( 'identity_id' => (int) $ax_actor_id ), array( '%d' ) );
		// The actor row goes first: an identity without its profile is the orphan the
		// Actors suite asserts against.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $ax_actor_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $ax_table, array( 'id' => (int) $ax_actor_id ), array( '%d' ) );
	}
}

$ax_failed = count( array_filter( $ax_results, static fn( $r ) => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_results ), $ax_failed );
exit( $ax_failed > 0 ? 1 : 0 );
