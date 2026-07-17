<?php
/**
 * Folder federation identity — permanent UUID, owner Actor, and federated visibility.
 *
 * A shared folder is a `Collection`, not a `Group`: it has no inbox or
 * outbox, receives no Follows, and is `attributedTo` its owner's Actor. This file gives a
 * folder the identity half of that: a UUID that survives renames, moves, and domain
 * changes, plus the predicate deciding whether it may be federated at all.
 *
 * Boundaries:
 * - Axismundi Actors owns `wp_ax_identities`. Everything here goes through its registry
 *   API, never SQL (Actors DATA-MODEL.md §1, §2.1).
 * - **Actors is optional.** The Media Library has never required it, and it stays that
 *   way: without Actors a folder simply has no federation identity and every accessor
 *   here returns empty / false. Local folder behaviour is untouched.
 * - Folder ACL (tier, gate, password) stays in folders.php. This file only *reads* it.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

/** Term meta holding the folder's immutable identity UUID (never the registry row id). */
const AXISMUNDI_MEDIA_FOLDER_UUID_META = '_ax_media_folder_uuid';

/**
 * Whether the Actors identity registry is available to register folder identities.
 *
 * @return bool
 */
function axismundi_media_identity_registry_available() : bool {
	return function_exists( 'axismundi_actors_register_identity' )
		&& function_exists( 'axismundi_actors_get_identity_by_uuid' )
		&& function_exists( 'axismundi_actors_set_status' );
}

/**
 * The canonical identity URI template for a folder.
 *
 * UUID-keyed and deliberately NOT the author/path display permalink: a folder's display
 * path changes on every rename and move, while its federation identity must not
 * (FEDERATED-MEDIA.md §12 — "separate identity URI from the mutable display permalink").
 * `folder` is already a reserved first segment under `/media/` (ROUTING.md §0), and the
 * top-level form is unused by the author-scoped display route.
 *
 * @return string
 */
function axismundi_media_folder_uri_template() : string {
	return home_url( '/media/folder/{uuid}' );
}

/**
 * Whether this term may own a federation identity at all.
 *
 * A hidden per-user root is a namespace mechanism, not a folder anyone can share, so it
 * never gets an identity.
 *
 * @param int $term_id Folder term ID.
 * @return bool
 */
function axismundi_media_folder_can_have_identity( int $term_id ) : bool {
	return $term_id > 0
		&& (bool) term_exists( $term_id, AXISMUNDI_MEDIA_FOLDER_TAX )
		&& ! axismundi_media_is_root_term( $term_id );
}

/**
 * The folder's permanent identity UUID, registering one on demand.
 *
 * Mirrors axismundi_media_user_root()'s get-or-create shape. Creating on demand is what
 * backfills folders that predate this feature and folders created while Actors was
 * inactive — no migration pass is needed.
 *
 * The identity is registered `internal`: the record exists and is usable as a membership
 * key immediately, while publication is a separate step driven by folder visibility
 * (Actors SPEC §2.6). Minting at creation rather than at first share is deliberate — the
 * UUID is the anchor, so it must not depend on when someone decided to share.
 *
 * @param int  $term_id Folder term ID.
 * @param bool $create  Register when missing.
 * @return string UUID, or '' when unavailable.
 */
function axismundi_media_folder_identity_uuid( int $term_id, bool $create = true ) : string {
	if ( ! axismundi_media_folder_can_have_identity( $term_id ) || ! axismundi_media_identity_registry_available() ) {
		return '';
	}
	$stored = (string) get_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_UUID_META, true );
	if ( '' !== $stored ) {
		// Trust the meta only while the registry still knows the identity; a restored
		// database or a purged registry must not leave a dangling UUID behind.
		if ( null !== axismundi_actors_get_identity_by_uuid( $stored ) ) {
			return $stored;
		}
		delete_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_UUID_META );
	}
	if ( ! $create ) {
		return '';
	}
	$identity = axismundi_actors_register_identity(
		array(
			'object_kind'  => 'folder',
			'origin'       => 'local',
			'status'       => 'internal',
			'uri_template' => axismundi_media_folder_uri_template(),
		)
	);
	if ( is_wp_error( $identity ) ) {
		return '';
	}
	update_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_UUID_META, $identity['uuid'] );
	axismundi_media_sync_folder_identity_status( $term_id );
	return (string) $identity['uuid'];
}

/**
 * The folder's canonical identity URI.
 *
 * Read from the registry rather than rebuilt from the template, so a URI rewritten by a
 * domain move stays authoritative.
 *
 * @param int  $term_id Folder term ID.
 * @param bool $create  Register an identity when missing.
 * @return string
 */
function axismundi_media_folder_uri( int $term_id, bool $create = true ) : string {
	$uuid = axismundi_media_folder_identity_uuid( $term_id, $create );
	if ( '' === $uuid ) {
		return '';
	}
	$identity = axismundi_actors_get_identity_by_uuid( $uuid );
	return is_array( $identity ) ? (string) $identity['canonical_uri'] : '';
}

/**
 * Resolve one folder from its federation UUID.
 *
 * The term-meta lookup is local storage owned by Media Library. Callers never query the
 * Actors registry or taxonomy internals directly.
 *
 * @param string $uuid Folder identity UUID.
 * @return WP_Term|null
 */
function axismundi_media_folder_from_identity_uuid( string $uuid ) : ?WP_Term {
	$uuid = strtolower( trim( $uuid ) );
	if ( 1 !== preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid ) ) {
		return null;
	}
	// Immutable UUID lookup is the canonical reverse index for a folder identity.
	// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	$terms = get_terms(
		array(
			'taxonomy'   => AXISMUNDI_MEDIA_FOLDER_TAX,
			'hide_empty' => false,
			'number'     => 2,
			'meta_key'   => AXISMUNDI_MEDIA_FOLDER_UUID_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Immutable identity lookup.
			'meta_value' => $uuid,                            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Immutable identity lookup.
		)
	);
	// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	if ( is_wp_error( $terms ) || 1 !== count( $terms ) || ! $terms[0] instanceof WP_Term ) {
		return null;
	}
	return $terms[0];
}

/**
 * The Actor URI a folder is attributed to.
 *
 * Resolved live from the owning user rather than copied onto the term: Actors keeps local
 * profile truth on the user and warns that copying causes drift (SPEC §2.7).
 *
 * @param int $term_id Folder term ID.
 * @return string Empty when the owner has no local Actor.
 */
function axismundi_media_folder_actor_uri( int $term_id ) : string {
	if ( ! function_exists( 'axismundi_actors_get_for_user' ) ) {
		return '';
	}
	$owner = axismundi_media_folder_owner( $term_id );
	if ( $owner <= 0 ) {
		return '';
	}
	$actor = axismundi_actors_get_for_user( $owner );
	if ( ! $actor instanceof Axismundi_Actor || ! function_exists( 'axismundi_actors_is_public_profile' ) || ! axismundi_actors_is_public_profile( $actor ) ) {
		return '';
	}
	return $actor->get_uri();
}

/**
 * Whether a folder may be federated.
 *
 * The same fail-closed shape as attachment federation: public or unlisted, ungated, and
 * attributable to a public Actor. Anonymous and cache-safe — no owner/editor bypass, so
 * the answer never varies by viewer.
 *
 * @param int $term_id Folder term ID.
 * @return bool
 */
function axismundi_media_folder_federation_allowed( int $term_id ) : bool {
	return axismundi_media_folder_can_have_identity( $term_id )
		&& axismundi_media_folder_effective_tier_rank( $term_id ) <= axismundi_media_visibility_rank( 'unlisted' )
		&& ! axismundi_media_folder_effective_gate( $term_id )
		&& '' !== axismundi_media_folder_actor_uri( $term_id );
}

/**
 * Query a slice of the anonymously visible media held directly by one folder.
 *
 * Offset-based rather than paged, because a federated listing puts child folders ahead of
 * media (§3.1) and the media slice therefore begins wherever the children stop — a page
 * boundary that `paged` cannot express.
 *
 * @param int $term_id Folder term ID.
 * @param int $offset  Media rows to skip.
 * @param int $limit   Media rows to return; 0 counts without fetching.
 * @return array{ids:int[],total:int}
 */
function axismundi_media_folder_media_slice( int $term_id, int $offset, int $limit ) : array {
	$query = new WP_Query(
		array(
			'post_type'                    => 'attachment',
			'post_status'                  => 'inherit',
			'posts_per_page'               => max( 1, $limit ),
			'offset'                       => max( 0, $offset ),
			'fields'                       => 'ids',
			'author'                       => axismundi_media_folder_owner( $term_id ),
			'ignore_sticky_posts'          => true,
			'no_found_rows'                => false,
			'ax_media_visibility_filter'   => true,
			'ax_media_force_anonymous'     => true,
			'ax_media_visibility_max_rank' => axismundi_media_folder_effective_tier_rank( $term_id ),
			'tax_query'                    => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Canonical folder membership query.
				array(
					'taxonomy'         => AXISMUNDI_MEDIA_FOLDER_TAX,
					'field'            => 'term_id',
					'terms'            => array( $term_id ),
					'include_children' => false,
				),
			),
			'meta_key'                     => '_ax_media_folder_added_at', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Folder order is the collection timeline.
			'orderby'                      => array( 'meta_value' => 'DESC', 'ID' => 'DESC' ),
		)
	);
	return array(
		// A zero limit means the caller only needs the count, so the fetched row is dropped.
		'ids'   => $limit > 0 ? array_map( 'intval', $query->posts ) : array(),
		'total' => (int) $query->found_posts,
	);
}

/**
 * Query one page of anonymously visible direct folder media.
 *
 * The media half of a listing. Child folders are the other half and are supplied by
 * axismundi_media_folder_federation_entries(); this function deliberately answers only
 * "what media does this folder hold", which is what the archive and feed surfaces want.
 *
 * @param int $term_id Folder term ID.
 * @param int $page Page number.
 * @param int $per_page Items per page.
 * @return array{ids:int[],total:int,pages:int,page:int,per_page:int}|WP_Error
 */
function axismundi_media_folder_federation_items( int $term_id, int $page = 1, int $per_page = 20 ) {
	if ( ! axismundi_media_folder_federation_allowed( $term_id ) ) {
		return new WP_Error( 'ax_media_folder_not_public', __( 'The shared folder is not publicly available.', 'axismundi-media-library' ), array( 'status' => 404 ) );
	}
	$page     = max( 1, $page );
	$per_page = min( 50, max( 1, $per_page ) );
	$slice    = axismundi_media_folder_media_slice( $term_id, ( $page - 1 ) * $per_page, $per_page );
	return array(
		'ids'      => $slice['ids'],
		'total'    => $slice['total'],
		'pages'    => (int) ceil( $slice['total'] / $per_page ),
		'page'     => $page,
		'per_page' => $per_page,
	);
}

/**
 * The child folders of one folder that may be federated in their own right.
 *
 * A child is listed only if it would federate alone, so this reuses the same gate rather
 * than inventing a second one: an internal, private, or gated child is absent, because a
 * name is a disclosure and a hidden folder whose existence is advertised is not hidden
 * (FEDERATED-MEDIA.md §3.1).
 *
 * @param int $term_id Folder term ID.
 * @return int[] Child term IDs, ordered by name.
 */
function axismundi_media_folder_federation_children( int $term_id ) : array {
	$children = get_terms(
		array(
			'taxonomy'   => AXISMUNDI_MEDIA_FOLDER_TAX,
			'hide_empty' => false,
			'parent'     => $term_id,
			'orderby'    => 'name',
			'order'      => 'ASC',
			'fields'     => 'ids',
		)
	);
	if ( is_wp_error( $children ) ) {
		return array();
	}
	$visible = array();
	foreach ( (array) $children as $child_id ) {
		if ( axismundi_media_folder_federation_allowed( (int) $child_id ) ) {
			$visible[] = (int) $child_id;
		}
	}
	return $visible;
}

/**
 * Query one page of a folder's federated listing: its visible children, then its media.
 *
 * A folder is an OS-directory affordance, and a directory that cannot show its
 * subdirectories is not one, so a listing carries both kinds (FEDERATED-MEDIA.md §3.1).
 * Ordering is children first and is not a preference: media order is
 * `_ax_media_folder_added_at`, which a child folder has no value for, so the two cannot
 * interleave under one key. The resulting order is total, so page boundaries stay stable
 * even where they cut across both kinds.
 *
 * Nothing recurses. A child is returned as an id for the caller to reference, never with
 * its own listing folded in.
 *
 * @param int $term_id  Folder term ID.
 * @param int $page     Page number.
 * @param int $per_page Entries per page.
 * @return array{entries:array<int,array{kind:string,id:int}>,total:int,pages:int,page:int,per_page:int}|WP_Error
 */
function axismundi_media_folder_federation_entries( int $term_id, int $page = 1, int $per_page = 20 ) {
	if ( ! axismundi_media_folder_federation_allowed( $term_id ) ) {
		return new WP_Error( 'ax_media_folder_not_public', __( 'The shared folder is not publicly available.', 'axismundi-media-library' ), array( 'status' => 404 ) );
	}
	$page     = max( 1, $page );
	$per_page = min( 50, max( 1, $per_page ) );
	$offset   = ( $page - 1 ) * $per_page;

	$children    = axismundi_media_folder_federation_children( $term_id );
	$child_count = count( $children );

	$entries = array();
	foreach ( array_slice( $children, $offset, $per_page ) as $child_id ) {
		$entries[] = array( 'kind' => 'folder', 'id' => (int) $child_id );
	}

	// The media slice starts where the children stop; a page filled by children still needs
	// the media count for `total`, hence the zero-limit count.
	$slice = axismundi_media_folder_media_slice(
		$term_id,
		max( 0, $offset - $child_count ),
		$per_page - count( $entries )
	);
	foreach ( $slice['ids'] as $attachment_id ) {
		$entries[] = array( 'kind' => 'media', 'id' => (int) $attachment_id );
	}

	$total = $child_count + (int) $slice['total'];
	return array(
		'entries'  => $entries,
		'total'    => $total,
		'pages'    => (int) ceil( $total / $per_page ),
		'page'     => $page,
		'per_page' => $per_page,
	);
}

/**
 * Align a folder identity's registry status with its current visibility.
 *
 * Publication is reversible and the identity row survives either way: making a folder
 * private returns it to `internal` (record kept, exposure withdrawn), never a tombstone,
 * which is reserved for deletion.
 *
 * @param int $term_id Folder term ID.
 * @return void
 */
function axismundi_media_sync_folder_identity_status( int $term_id ) : void {
	if ( ! axismundi_media_identity_registry_available() ) {
		return;
	}
	// Never register an identity as a side effect of a visibility change; only update one
	// that already exists.
	$uuid = axismundi_media_folder_identity_uuid( $term_id, false );
	if ( '' === $uuid ) {
		return;
	}
	$identity = axismundi_actors_get_identity_by_uuid( $uuid );
	if ( ! is_array( $identity ) || 'tombstone' === $identity['status'] ) {
		return;
	}
	$target = axismundi_media_folder_federation_allowed( $term_id ) ? 'public' : 'internal';
	if ( $target !== $identity['status'] ) {
		axismundi_actors_set_status( (int) $identity['identity_id'], $target );
	}
}
add_action( 'axismundi_media_folder_visibility_changed', 'axismundi_media_sync_folder_identity_status' );

/**
 * Mint a folder's identity as soon as our service creates it.
 *
 * Deliberately NOT hooked to `created_{taxonomy}`: a hidden root is inserted as a term
 * before its `_ax_media_folder_root` marker is written, so at that moment a root is
 * indistinguishable from a folder and would be handed an identity it must never have.
 * Folders created directly through the taxonomy API instead pick their identity up from
 * the get-or-create accessor on first use.
 *
 * @param int $term_id Created folder term ID.
 * @return void
 */
function axismundi_media_folder_identity_on_create( int $term_id ) : void {
	axismundi_media_folder_identity_uuid( $term_id );
}
add_action( 'axismundi_media_folder_created', 'axismundi_media_folder_identity_on_create' );

/**
 * Tombstone a deleted folder's identity; never hard-delete it.
 *
 * A remote peer may hold the folder URI in a collection or a replica. The identity must
 * survive as a tombstone so that reference resolves to "gone" rather than to nothing, and
 * so the UUID can never be reissued. This is the same contract as a deleted user's Actor
 * (Actors DATA-MODEL.md §2).
 *
 * @param int $term_id Deleted term ID.
 * @return void
 */
function axismundi_media_folder_identity_on_delete( int $term_id ) : void {
	if ( ! axismundi_media_identity_registry_available() ) {
		return;
	}
	$uuid = (string) get_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_UUID_META, true );
	if ( '' === $uuid ) {
		return;
	}
	$identity = axismundi_actors_get_identity_by_uuid( $uuid );
	if ( is_array( $identity ) ) {
		axismundi_actors_set_status( (int) $identity['identity_id'], 'tombstone' );
	}
}

/**
 * Capture the identity before WordPress drops the term and its meta.
 *
 * `delete_term` fires after term meta is gone, so the UUID must be read on the `pre_`
 * hook or the link to the identity row is lost and the row is orphaned.
 *
 * @param int    $term_id  Term about to be deleted.
 * @param string $taxonomy Taxonomy.
 * @return void
 */
function axismundi_media_folder_identity_on_delete_check( int $term_id, string $taxonomy ) : void {
	if ( AXISMUNDI_MEDIA_FOLDER_TAX === $taxonomy ) {
		axismundi_media_folder_identity_on_delete( $term_id );
	}
}
add_action( 'pre_delete_term', 'axismundi_media_folder_identity_on_delete_check', 10, 2 );
