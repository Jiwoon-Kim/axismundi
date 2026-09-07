<?php
/**
 * Phase 3d legacy-parent migration + delete-warning regression (dev-only).
 *
 * Uses only targeted services so existing wp-env parent relationships are never
 * mutated. Self-contained fixtures are removed in finally; exits 0/1.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_d_results = array();

/**
 * @param array<bool> $results Accumulator.
 * @param string      $label   Contract.
 * @param bool        $holds   Whether it holds.
 * @return void
 */
function ax_d_assert( array &$results, string $label, bool $holds ) : void {
	$results[] = $holds;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI-only test output.
	printf( "[%s] %s\n", $holds ? 'PASS' : 'FAIL', $label );
}

$ax_d_created   = array( 'posts' => array(), 'attachments' => array() );
$ax_d_prev_mode = get_option( AXISMUNDI_MEDIA_MODE_OPTION, AXISMUNDI_MEDIA_MODE_DEFAULT );
$ax_d_prev_user = get_current_user_id();

try {
	wp_set_current_user( 1 );
	update_option( AXISMUNDI_MEDIA_MODE_OPTION, 'core' );

	$parent_a = (int) wp_insert_post( array( 'post_title' => 'ax-d parent A', 'post_status' => 'publish', 'post_type' => 'post' ) );
	$parent_b = (int) wp_insert_post( array( 'post_title' => 'ax-d parent B', 'post_status' => 'publish', 'post_type' => 'post' ) );
	$ax_d_created['posts'] = array( $parent_a, $parent_b );

	$att_a = (int) wp_insert_attachment( array( 'post_title' => 'ax-d attachment A', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'post_parent' => $parent_a ) );
	$att_b = (int) wp_insert_attachment( array( 'post_title' => 'ax-d attachment B', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'post_parent' => $parent_b ) );
	$ax_d_created['attachments'] = array( $att_a, $att_b );
	update_option( AXISMUNDI_MEDIA_MODE_OPTION, 'independent' );

	ax_d_assert( $ax_d_results, 'fixture attachment starts attached', $parent_a === (int) get_post( $att_a )->post_parent );
	$snap = axismundi_media_legacy_parent_snapshot_one( $att_a, $parent_a );
	ax_d_assert( $ax_d_results, 'snapshot records the original parent', true === $snap && $parent_a === axismundi_media_legacy_parent_snapshot_for( $att_a ) );
	ax_d_assert( $ax_d_results, 'same snapshot is idempotent', true === axismundi_media_legacy_parent_snapshot_one( $att_a, $parent_a ) );
	ax_d_assert( $ax_d_results, 'different snapshot is rejected', is_wp_error( axismundi_media_legacy_parent_snapshot_one( $att_a, $parent_b ) ) );
	ax_d_assert( $ax_d_results, 'unsnapshotted relationship must match the current parent', is_wp_error( axismundi_media_legacy_parent_snapshot_one( $att_b, $parent_a ) ) && 0 === axismundi_media_legacy_parent_snapshot_for( $att_b ) );

	$detach_dry = axismundi_media_legacy_parent_detach_one( $att_a, true );
	ax_d_assert( $ax_d_results, 'detach dry-run changes nothing', 'would_detach' === $detach_dry && $parent_a === (int) get_post( $att_a )->post_parent );
	$detach = axismundi_media_legacy_parent_detach_one( $att_a, false );
	ax_d_assert( $ax_d_results, 'detach clears post_parent after snapshot match', 'detached' === $detach && 0 === (int) get_post( $att_a )->post_parent );

	$rollback_dry = axismundi_media_legacy_parent_rollback_one( $att_a, true );
	ax_d_assert( $ax_d_results, 'rollback dry-run changes nothing', 'would_restore' === $rollback_dry && 0 === (int) get_post( $att_a )->post_parent );
	$rollback = axismundi_media_legacy_parent_rollback_one( $att_a, false );
	ax_d_assert( $ax_d_results, 'rollback restores the original parent', 'restored' === $rollback && $parent_a === (int) get_post( $att_a )->post_parent );

	// A relationship changed after snapshot ownership is ambiguous and must not be
	// detached or overwritten by rollback.
	axismundi_media_legacy_parent_snapshot_one( $att_b, $parent_b );
	wp_update_post( array( 'ID' => $att_b, 'post_parent' => $parent_a ) );
	ax_d_assert( $ax_d_results, 'changed parent is a detach conflict', 'conflict' === axismundi_media_legacy_parent_detach_one( $att_b, false ) && $parent_a === (int) get_post( $att_b )->post_parent );
	ax_d_assert( $ax_d_results, 'rollback never overwrites a nonzero conflicting parent', 'conflict' === axismundi_media_legacy_parent_rollback_one( $att_b, false ) && $parent_a === (int) get_post( $att_b )->post_parent );

	// Used-in warning is visible only when the viewer can read an indexed source.
	axismundi_media_relations_replace(
		array( 'post_id' => $parent_a, 'type' => 'post' ),
		'block_content',
		array( array( 'object_attachment_id' => $att_a, 'predicate' => 'as:attachment', 'role' => 'content' ) )
	);
	$fields = axismundi_media_attachment_fields( array(), get_post( $att_a ) );
	ax_d_assert( $ax_d_results, 'Attachment Details adds an in-use deletion warning', isset( $fields['ax_media_delete_warning'] ) && str_contains( (string) $fields['ax_media_delete_warning']['html'], '1 indexed item' ) );
	ax_d_assert( $ax_d_results, 'legacy_parent never appears in Used in', 1 === axismundi_media_relations_used_in_subject_count( $att_a ) );
} finally {
	foreach ( $ax_d_created['attachments'] as $ax_d_attachment ) {
		if ( $ax_d_attachment ) {
			wp_delete_attachment( (int) $ax_d_attachment, true );
		}
	}
	foreach ( $ax_d_created['posts'] as $ax_d_post ) {
		if ( $ax_d_post ) {
			wp_delete_post( (int) $ax_d_post, true );
		}
	}
	update_option( AXISMUNDI_MEDIA_MODE_OPTION, $ax_d_prev_mode );
	wp_set_current_user( $ax_d_prev_user );
}

$ax_d_failed = count( array_filter( $ax_d_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI-only test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_d_results ), $ax_d_failed );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_d_failed > 0 ? 1 : 0 );
}
exit( $ax_d_failed > 0 ? 1 : 0 );
