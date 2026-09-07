<?php
/**
 * Generic identity registry regression (dev-only; dist-excluded).
 *
 * Self-contained; `finally` cleanup of every row it creates; exit 0/1. Locks: actor
 * kinds are not creatable here (the orphan guard); a local URI carries its own UUID;
 * URI uniqueness; tombstone is not a birth state; UUID survives a URI rewrite; a remote
 * URI is never rewritten.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/identity-registry.php';

global $wpdb;
$ax_results  = array();
$ax_identity_ids = array();

/**
 * @param array  $results   Accumulator.
 * @param string $label     Assertion label.
 * @param bool   $condition Result.
 */
function ax_identity_registry_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	axismundi_actors_install();

	// The orphan guard: an identity row claiming to be an actor with no wp_ax_actors row
	// could never be hydrated by any actor lookup, so the registry refuses the kind.
	$actor_attempt = axismundi_actors_register_identity(
		array( 'object_kind' => 'actor', 'uri_template' => home_url( '/actors/{uuid}' ) )
	);
	ax_identity_registry_assert(
		$ax_results,
		'the registry refuses to create an actor identity',
		is_wp_error( $actor_attempt ) && 'ax_actors_identity_kind' === $actor_attempt->get_error_code()
	);

	$unknown = axismundi_actors_register_identity( array( 'object_kind' => 'sandwich', 'uri_template' => home_url( '/x/{uuid}' ) ) );
	ax_identity_registry_assert(
		$ax_results,
		'an unreserved kind is refused',
		is_wp_error( $unknown ) && 'ax_actors_identity_kind' === $unknown->get_error_code()
	);

	$folder = axismundi_actors_register_identity(
		array( 'object_kind' => 'folder', 'uri_template' => home_url( '/media/folders/{uuid}' ) )
	);
	if ( ! is_wp_error( $folder ) ) {
		$ax_identity_ids[] = (int) $folder['identity_id'];
	}
	ax_identity_registry_assert(
		$ax_results,
		'a folder identity registers with its UUID embedded in its own canonical URI',
		is_array( $folder )
			&& 'folder' === $folder['object_kind']
			&& 'local' === $folder['origin']
			&& home_url( '/media/folders/' . $folder['uuid'] ) === $folder['canonical_uri']
	);

	// Record existence and public exposure are separate steps (SPEC §2.6).
	ax_identity_registry_assert(
		$ax_results,
		'a new identity is internal until it is published',
		is_array( $folder ) && 'internal' === $folder['status']
	);

	$no_template = axismundi_actors_register_identity(
		array( 'object_kind' => 'folder', 'uri_template' => home_url( '/media/folders/fixed' ) )
	);
	ax_identity_registry_assert(
		$ax_results,
		'a local identity without a {uuid} placeholder is refused',
		is_wp_error( $no_template ) && 'ax_actors_identity_template' === $no_template->get_error_code()
	);

	$born_dead = axismundi_actors_register_identity(
		array( 'object_kind' => 'folder', 'status' => 'tombstone', 'uri_template' => home_url( '/media/folders/{uuid}' ) )
	);
	ax_identity_registry_assert(
		$ax_results,
		'tombstone is an end state, not a birth state',
		is_wp_error( $born_dead ) && 'ax_actors_identity_status' === $born_dead->get_error_code()
	);

	ax_identity_registry_assert(
		$ax_results,
		'an identity resolves identically by id, uuid, and canonical URI',
		is_array( $folder )
			&& axismundi_actors_get_identity( (int) $folder['identity_id'] ) === $folder
			&& axismundi_actors_get_identity_by_uuid( $folder['uuid'] ) === $folder
			&& axismundi_actors_get_identity_by_uri( $folder['canonical_uri'] ) === $folder
	);

	$remote_uri = 'https://remote.example/media/folders/abc';
	$remote     = axismundi_actors_register_identity(
		array( 'object_kind' => 'folder', 'origin' => 'remote', 'status' => 'public', 'canonical_uri' => $remote_uri )
	);
	if ( ! is_wp_error( $remote ) ) {
		$ax_identity_ids[] = (int) $remote['identity_id'];
	}
	ax_identity_registry_assert(
		$ax_results,
		'a remote identity keeps its source URI and still gets a local UUID record key',
		is_array( $remote ) && $remote_uri === $remote['canonical_uri'] && '' !== $remote['uuid']
	);

	$dupe = axismundi_actors_register_identity(
		array( 'object_kind' => 'collection', 'origin' => 'remote', 'canonical_uri' => $remote_uri )
	);
	ax_identity_registry_assert(
		$ax_results,
		'one canonical URI cannot be registered twice, even under another kind',
		is_wp_error( $dupe ) && 'ax_actors_identity_exists' === $dupe->get_error_code()
	);

	// The UUID is the only immutable anchor: a domain move rewrites the URI around it.
	$moved = is_array( $folder ) ? axismundi_actors_set_identity_uri( (int) $folder['identity_id'], 'https://moved.example/media/folders/' . $folder['uuid'] ) : false;
	$after = is_array( $folder ) ? axismundi_actors_get_identity( (int) $folder['identity_id'] ) : null;
	ax_identity_registry_assert(
		$ax_results,
		'a URI rewrite preserves the UUID and is resolvable at the new URI only',
		true === $moved
			&& is_array( $after )
			&& $after['uuid'] === $folder['uuid']
			&& 'https://moved.example/media/folders/' . $folder['uuid'] === $after['canonical_uri']
			&& null === axismundi_actors_get_identity_by_uri( $folder['canonical_uri'] )
	);

	$remote_move = is_array( $remote ) ? axismundi_actors_set_identity_uri( (int) $remote['identity_id'], 'https://elsewhere.example/x' ) : false;
	ax_identity_registry_assert(
		$ax_results,
		'a remote identity URI is its source of truth and cannot be rewritten',
		is_wp_error( $remote_move ) && 'ax_actors_identity_remote' === $remote_move->get_error_code()
	);

	// set_status() is kind-agnostic and already owns the transition contract.
	$tombstoned = is_array( $folder ) && axismundi_actors_set_status( (int) $folder['identity_id'], 'tombstone' );
	$dead       = is_array( $folder ) ? axismundi_actors_get_identity( (int) $folder['identity_id'] ) : null;
	ax_identity_registry_assert(
		$ax_results,
		'a folder identity tombstones through the shared status contract',
		$tombstoned && is_array( $dead ) && 'tombstone' === $dead['status']
	);
} finally {
	$ax_table = axismundi_actors_identities_table();
	foreach ( $ax_identity_ids as $ax_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $ax_table, array( 'id' => $ax_id ), array( '%d' ) );
	}
}

$ax_failed = count( array_filter( $ax_results, static fn( $r ) => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_results ) - $ax_failed, count( $ax_results ) );
exit( $ax_failed > 0 ? 1 : 0 );
