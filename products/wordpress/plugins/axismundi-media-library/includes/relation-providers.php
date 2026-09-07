<?php
/**
 * Phase 3b — used-in relation providers + incremental indexing.
 *
 * Each provider extracts a post's real media references (never a URL→id scan) and
 * returns a **normalized relation array OR a WP_Error**:
 *   - array (incl. `[]`): authoritative — the store atomically replaces that
 *     provider's rows (`[]` clears them).
 *   - WP_Error: collection failed — the store keeps the existing rows, so a transient
 *     parse error never wipes good Used-in data. One provider's failure never blocks
 *     another.
 *
 * Predicate mapping (DATA-MODEL §4): only the featured image is `as:image`
 * (representative); every in-content reference is `as:attachment` with a role.
 * `as:icon` / `schema:associatedMedia` are left to integration providers.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

/**
 * The provider registry: slug => callable(WP_Post): array|WP_Error.
 *
 * @return array<string,callable>
 */
function axismundi_media_relation_providers() : array {
	$providers = array(
		'featured_image' => 'axismundi_media_provider_featured_image',
		'block_content'  => 'axismundi_media_provider_block_content',
		'shortcode'      => 'axismundi_media_provider_shortcode',
	);
	/**
	 * Filter the relation providers. Integrations add slug => callable(WP_Post) that
	 * returns a normalized relation array or WP_Error.
	 *
	 * @param array<string,callable> $providers Providers.
	 */
	return (array) apply_filters( 'axismundi_media_relation_providers', $providers );
}

/**
 * Build one normalized relation (or null for an empty id).
 *
 * @param int    $id         Object attachment ID.
 * @param string $predicate  AS predicate.
 * @param string $role       Relation role.
 * @param string $source_key Provider-stable source key.
 * @return array<string,mixed>|null
 */
function axismundi_media_relation_ref( int $id, string $predicate, string $role, string $source_key ) : ?array {
	if ( $id <= 0 ) {
		return null;
	}
	return array(
		'object_attachment_id' => $id,
		'predicate'            => $predicate,
		'role'                 => $role,
		'source_key'           => $source_key,
	);
}

/* ---------------------------------------------------------------- *
 * Core providers
 * ---------------------------------------------------------------- */

/**
 * Featured image → the one `as:image` / `featured` relation.
 *
 * @param WP_Post $post Subject.
 * @return array<int,array<string,mixed>>
 */
function axismundi_media_provider_featured_image( WP_Post $post ) : array {
	$id  = (int) get_post_meta( $post->ID, '_thumbnail_id', true );
	$ref = axismundi_media_relation_ref( $id, 'as:image', 'featured', '_thumbnail_id' );
	return $ref ? array( $ref ) : array();
}

/**
 * Block tree → `as:attachment` references by role. No URL scanning.
 *
 * @param WP_Post $post Subject.
 * @return array<int,array<string,mixed>>
 */
function axismundi_media_provider_block_content( WP_Post $post ) : array {
	$content = (string) $post->post_content;
	if ( '' === $content || ! has_blocks( $content ) ) {
		return array();
	}
	$out = array();
	axismundi_media_walk_blocks( parse_blocks( $content ), $out, '' );
	return $out;
}

/**
 * Recursively collect media references from a block list.
 *
 * @param array<int,array<string,mixed>> $blocks Blocks.
 * @param array<int,array<string,mixed>> $out    Accumulator (by reference).
 * @param string                         $path   Stable block path.
 * @return void
 */
function axismundi_media_walk_blocks( array $blocks, array &$out, string $path ) : void {
	foreach ( $blocks as $i => $block ) {
		$name  = (string) ( $block['blockName'] ?? '' );
		$attrs = (array) ( $block['attrs'] ?? array() );
		$key   = $path . '/' . $i;

		// Gallery is handled whole to avoid double-counting: modern inner core/image
		// blocks win; the legacy `ids` attr is a fallback only when there are none.
		if ( 'core/gallery' === $name ) {
			$has_inner = false;
			foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $j => $inner ) {
				if ( 'core/image' === ( $inner['blockName'] ?? '' ) && ! empty( $inner['attrs']['id'] ) ) {
					$ref = axismundi_media_relation_ref( (int) $inner['attrs']['id'], 'as:attachment', 'gallery', $key . '/img/' . $j );
					if ( $ref ) {
						$out[]     = $ref;
						$has_inner = true;
					}
				}
			}
			if ( ! $has_inner && ! empty( $attrs['ids'] ) && is_array( $attrs['ids'] ) ) {
				foreach ( $attrs['ids'] as $gid ) {
					$ref = axismundi_media_relation_ref( (int) $gid, 'as:attachment', 'gallery', $key . '/ids' );
					if ( $ref ) {
						$out[] = $ref;
					}
				}
			}
			continue; // do not recurse — gallery images are already captured.
		}

		$ref = null;
		switch ( $name ) {
			case 'core/image':
				$ref = axismundi_media_relation_ref( (int) ( $attrs['id'] ?? 0 ), 'as:attachment', 'content', $key );
				break;
			case 'core/cover':
				// A cover using the featured image carries no id (the featured provider owns it).
				$ref = axismundi_media_relation_ref( (int) ( $attrs['id'] ?? 0 ), 'as:attachment', 'cover', $key );
				break;
			case 'core/media-text':
				$ref = axismundi_media_relation_ref( (int) ( $attrs['mediaId'] ?? 0 ), 'as:attachment', 'media_text', $key );
				break;
			case 'core/file':
				$ref = axismundi_media_relation_ref( (int) ( $attrs['id'] ?? 0 ), 'as:attachment', 'file', $key );
				break;
			case 'core/audio':
				$ref = axismundi_media_relation_ref( (int) ( $attrs['id'] ?? 0 ), 'as:attachment', 'audio', $key );
				break;
			case 'core/video':
				$ref = axismundi_media_relation_ref( (int) ( $attrs['id'] ?? 0 ), 'as:attachment', 'video', $key );
				break;
		}
		if ( $ref ) {
			$out[] = $ref;
		}

		if ( ! empty( $block['innerBlocks'] ) ) {
			axismundi_media_walk_blocks( $block['innerBlocks'], $out, $key );
		}
	}
}

/**
 * Classic shortcodes that carry explicit numeric ids — [gallery]/[playlist]. Core
 * [audio]/[video] store URLs, not ids, so they are out of scope (URL scan ban); a
 * numeric-id variant is an integration provider's job.
 *
 * @param WP_Post $post Subject.
 * @return array<int,array<string,mixed>>
 */
function axismundi_media_provider_shortcode( WP_Post $post ) : array {
	$content = (string) $post->post_content;
	if ( false === strpos( $content, '[gallery' ) && false === strpos( $content, '[playlist' ) ) {
		return array();
	}
	$out     = array();
	$pattern = get_shortcode_regex( array( 'gallery', 'playlist' ) );
	if ( preg_match_all( '/' . $pattern . '/s', $content, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $m ) {
			$tag  = $m[2];
			$atts = shortcode_parse_atts( $m[3] );
			if ( empty( $atts['ids'] ) ) {
				continue;
			}
			foreach ( array_map( 'intval', explode( ',', (string) $atts['ids'] ) ) as $gid ) {
				$ref = axismundi_media_relation_ref( $gid, 'as:attachment', 'gallery', $tag . ':ids' );
				if ( $ref ) {
					$out[] = $ref;
				}
			}
		}
	}
	return $out;
}

/* ---------------------------------------------------------------- *
 * Orchestrator + incremental hooks
 * ---------------------------------------------------------------- */

/**
 * Collect and optionally write every provider for one post.
 *
 * Dry-run uses the store's exact dedup/aggregation routine and never mutates. A
 * provider returning WP_Error is reported and preserves its current rows. Public
 * visibility is not an indexing condition; the reverse lookup owns read filtering.
 *
 * @param int  $post_id Subject post ID.
 * @param bool $dry_run Do not write or clear rows.
 * @return array{status:string,dry_run:bool,written:int,providers:array<string,int>,errors:array<string,string>}
 */
function axismundi_media_relations_reindex_post( int $post_id, bool $dry_run = false ) : array {
	$report = array(
		'status'    => 'indexed',
		'dry_run'   => $dry_run,
		'written'   => 0,
		'providers' => array(),
		'errors'    => array(),
	);
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		$report['status'] = 'skipped';
		return $report;
	}
	$post = get_post( $post_id );
	if ( ! $post ) {
		$report['status']            = 'missing';
		$report['errors']['subject'] = __( 'The relation subject does not exist.', 'axismundi-media-library' );
		return $report;
	}
	if ( in_array( $post->post_status, array( 'auto-draft', 'trash' ), true ) ) {
		$report['status']  = 'cleared';
		$report['written'] = count( axismundi_media_relations_for_subject( $post_id ) );
		if ( ! $dry_run ) {
			axismundi_media_relations_delete_subject( $post_id );
		}
		return $report;
	}
	$subject = array( 'post_id' => $post_id, 'type' => 'post' );
	foreach ( axismundi_media_relation_providers() as $slug => $callback ) {
		if ( ! is_callable( $callback ) ) {
			$report['errors'][ (string) $slug ] = __( 'The relation provider is not callable.', 'axismundi-media-library' );
			continue;
		}
		$result = call_user_func( $callback, $post );
		if ( is_wp_error( $result ) ) {
			$report['errors'][ (string) $slug ] = $result->get_error_message();
			continue;
		}
		$count = count( axismundi_media_relations_prepare_rows( (array) $result ) );
		$report['providers'][ (string) $slug ] = $count;
		if ( $dry_run ) {
			$report['written'] += $count;
			continue;
		}
		$written = axismundi_media_relations_replace( $subject, (string) $slug, (array) $result );
		if ( is_wp_error( $written ) ) {
			$report['errors'][ (string) $slug ] = $written->get_error_message();
			continue;
		}
		$report['written'] += (int) $written;
	}
	return $report;
}

/**
 * (Re)index one post for incremental hooks. Errors preserve prior provider rows and
 * are intentionally non-fatal here; Phase 3c CLI surfaces them to operators.
 *
 * @param int $post_id Subject post ID.
 * @return void
 */
function axismundi_media_index_post( int $post_id ) : void {
	axismundi_media_relations_reindex_post( $post_id, false );
}

/**
 * Reindex on any post save (editor, REST, importers all fire this).
 *
 * @param int $post_id Post ID.
 * @return void
 */
function axismundi_media_relations_on_save( int $post_id ) : void {
	axismundi_media_index_post( (int) $post_id );
}
add_action( 'wp_after_insert_post', 'axismundi_media_relations_on_save', 20, 1 );

/**
 * Reindex the featured relation when `_thumbnail_id` changes outside a full save.
 * The first arg is untyped: `deleted_post_meta` passes an array of meta ids, the
 * add/update actions pass a single int — we use neither.
 *
 * @param int|int[] $meta_id   Meta row ID(s) (unused).
 * @param int       $object_id Post ID.
 * @param string    $meta_key  Meta key.
 * @return void
 */
function axismundi_media_relations_on_thumbnail_meta( $meta_id, int $object_id, string $meta_key ) : void {
	if ( '_thumbnail_id' !== $meta_key ) {
		return;
	}
	$post = get_post( $object_id );
	if ( ! $post || wp_is_post_revision( $object_id ) || wp_is_post_autosave( $object_id )
		|| in_array( $post->post_status, array( 'auto-draft', 'trash' ), true ) ) {
		return;
	}
	$result = axismundi_media_provider_featured_image( $post );
	axismundi_media_relations_replace( array( 'post_id' => (int) $object_id, 'type' => 'post' ), 'featured_image', $result );
}
add_action( 'added_post_meta', 'axismundi_media_relations_on_thumbnail_meta', 10, 3 );
add_action( 'updated_post_meta', 'axismundi_media_relations_on_thumbnail_meta', 10, 3 );
add_action( 'deleted_post_meta', 'axismundi_media_relations_on_thumbnail_meta', 10, 3 );

/**
 * On delete, remove the post's subject rows; for an attachment, also remove the
 * object-side rows where it was used.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function axismundi_media_relations_on_delete( int $post_id ) : void {
	$post = get_post( $post_id );
	axismundi_media_relations_delete_subject( (int) $post_id );
	if ( $post && 'attachment' === $post->post_type ) {
		axismundi_media_relations_delete_object( (int) $post_id );
	}
}
add_action( 'before_delete_post', 'axismundi_media_relations_on_delete', 10, 1 );

/**
 * Attachment deletion (wp_delete_attachment does not reliably fire before_delete_post)
 * — remove both the subject and object-side rows. Idempotent with on_delete.
 *
 * @param int $post_id Attachment ID.
 * @return void
 */
function axismundi_media_relations_on_delete_attachment( int $post_id ) : void {
	axismundi_media_relations_delete_subject( (int) $post_id );
	axismundi_media_relations_delete_object( (int) $post_id );
}
add_action( 'delete_attachment', 'axismundi_media_relations_on_delete_attachment', 10, 1 );
