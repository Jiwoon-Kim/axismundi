<?php
/**
 * Phase 3c reindex service + Attachment Details regression (dev-only).
 *
 * Self-contained; creates local subjects/attachments, restores mode/current user,
 * cleans every fixture in finally, and exits 0/1.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_c_results = array();

/**
 * @param array<bool> $results Accumulator.
 * @param string      $label   Contract.
 * @param bool        $holds   Whether it holds.
 * @return void
 */
function ax_c_assert( array &$results, string $label, bool $holds ) : void {
	$results[] = $holds;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI-only test output.
	printf( "[%s] %s\n", $holds ? 'PASS' : 'FAIL', $label );
}

/** Build a core/image block referencing an Attachment ID. */
function ax_c_image_block( int $id ) : string {
	return '<!-- wp:image {"id":' . $id . '} --><figure class="wp-block-image"><img class="wp-image-' . $id . '"/></figure><!-- /wp:image -->';
}

$ax_c_created   = array( 'posts' => array(), 'attachments' => array() );
$ax_c_prev_mode = get_option( AXISMUNDI_MEDIA_MODE_OPTION, AXISMUNDI_MEDIA_MODE_DEFAULT );
$ax_c_prev_user = get_current_user_id();

try {
	wp_set_current_user( 1 );
	update_option( AXISMUNDI_MEDIA_MODE_OPTION, 'independent' );
	axismundi_media_relations_install();

	$att_a = (int) wp_insert_attachment( array( 'post_title' => 'ax-c attachment A', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg' ) );
	$att_b = (int) wp_insert_attachment( array( 'post_title' => 'ax-c attachment B', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg' ) );
	$ax_c_created['attachments'] = array( $att_a, $att_b );

	$post_id = (int) wp_insert_post(
		array(
			'post_title'   => 'ax-c relation source',
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_content' => ax_c_image_block( $att_a ) . ax_c_image_block( $att_a ),
		)
	);
	$ax_c_created['posts'][] = $post_id;

	// Start from an empty store: dry-run must report the exact deduped row count and
	// leave it empty.
	axismundi_media_relations_delete_subject( $post_id );
	$dry = axismundi_media_relations_reindex_post( $post_id, true );
	ax_c_assert( $ax_c_results, 'dry-run reports one deduped block row', 1 === ( $dry['providers']['block_content'] ?? 0 ) && 1 === $dry['written'] );
	ax_c_assert( $ax_c_results, 'dry-run writes no relation rows', 0 === count( axismundi_media_relations_for_subject( $post_id ) ) );

	$first = axismundi_media_relations_reindex_post( $post_id, false );
	$rows  = axismundi_media_relations_for_subject( $post_id, 'block_content' );
	ax_c_assert( $ax_c_results, 'mutating reindex writes one row', 1 === $first['written'] && 1 === count( $rows ) );
	ax_c_assert( $ax_c_results, 'duplicate block occurrences aggregate to two', 2 === (int) $rows[0]['occurrence_count'] );

	$second = axismundi_media_relations_reindex_post( $post_id, false );
	ax_c_assert( $ax_c_results, 'second reindex is idempotent', 1 === $second['written'] && 1 === count( axismundi_media_relations_for_subject( $post_id, 'block_content' ) ) );

	wp_update_post( array( 'ID' => $post_id, 'post_content' => ax_c_image_block( $att_b ) ) );
	$new_rows = axismundi_media_relations_for_subject( $post_id, 'block_content' );
	ax_c_assert( $ax_c_results, 'incremental update removes stale object and indexes new object', 1 === count( $new_rows ) && $att_b === (int) $new_rows[0]['object_attachment_id'] );

	$fields = axismundi_media_attachment_fields( array(), get_post( $att_b ) );
	ax_c_assert( $ax_c_results, 'Attachment Details renames Folder to Location', 'Location' === ( $fields['ax_media_folder']['label'] ?? '' ) );
	$used_html = (string) ( $fields['ax_media_used_in']['html'] ?? '' );
	ax_c_assert( $ax_c_results, 'Used in UI shows the readable source and role', str_contains( $used_html, 'ax-c relation source' ) && str_contains( $used_html, 'Content' ) );
	ax_c_assert( $ax_c_results, 'Saved in remains absent until Phase 5', ! isset( $fields['ax_media_saved_in'] ) );
	ax_c_assert( $ax_c_results, 'WP-CLI relation command class is registered', class_exists( 'Axismundi_Media_Relations_CLI' ) );
} finally {
	foreach ( $ax_c_created['posts'] as $ax_c_post ) {
		if ( $ax_c_post ) {
			wp_delete_post( (int) $ax_c_post, true );
		}
	}
	foreach ( $ax_c_created['attachments'] as $ax_c_attachment ) {
		if ( $ax_c_attachment ) {
			wp_delete_attachment( (int) $ax_c_attachment, true );
		}
	}
	update_option( AXISMUNDI_MEDIA_MODE_OPTION, $ax_c_prev_mode );
	wp_set_current_user( $ax_c_prev_user );
}

$ax_c_failed = count( array_filter( $ax_c_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI-only test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_c_results ), $ax_c_failed );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_c_failed > 0 ? 1 : 0 );
}
exit( $ax_c_failed > 0 ? 1 : 0 );
