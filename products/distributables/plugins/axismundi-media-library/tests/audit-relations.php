<?php
/**
 * Phase 3a — used-in relation core regression (dev-only; excluded from the dist ZIP).
 *
 * Self-contained: creates its own posts / attachments / user, cleans up in `finally`,
 * exits 0 (all contracts hold) or 1 (regressed). Locks: dedup with nullable URIs,
 * occurrence aggregation, role distinctness, per-provider isolation, empty-replace
 * clears, idempotence, reverse lookup, and the read-access filter.
 *
 *   npx wp-env run cli wp eval-file \
 *     wp-content/plugins/axismundi-media-library/tests/audit-relations.php
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';

$ax_results = array();

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_rel_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/** @return int count of active rows for a subject+provider. */
function ax_rel_count( int $post_id, string $provider ) : int {
	return count( axismundi_media_relations_for_subject( $post_id, $provider ) );
}

$ax_created = array( 'posts' => array(), 'atts' => array(), 'users' => array() );

try {
	axismundi_media_relations_install(); // ensure the table exists in this context

	$att  = (int) wp_insert_attachment( array( 'post_title' => 'ax-rel att', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg' ) );
	$att2 = (int) wp_insert_attachment( array( 'post_title' => 'ax-rel att2', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg' ) );
	$ax_created['atts'] = array( $att, $att2 );

	$pub  = (int) wp_insert_post( array( 'post_title' => 'ax-rel published', 'post_status' => 'publish', 'post_type' => 'post', 'post_content' => 'x' ) );
	$priv = (int) wp_insert_post( array( 'post_title' => 'ax-rel private', 'post_status' => 'private', 'post_type' => 'post', 'post_content' => 'x', 'post_author' => 1 ) );
	$ax_created['posts'] = array( $pub, $priv );

	// Dedup + occurrence + role distinctness: 3× content(att) collapse to one row
	// (count 3); a featured/as:image row is distinct.
	$n = axismundi_media_relations_replace(
		array( 'post_id' => $pub ),
		'block_content',
		array(
			array( 'predicate' => 'as:attachment', 'role' => 'content', 'object_attachment_id' => $att ),
			array( 'predicate' => 'as:attachment', 'role' => 'content', 'object_attachment_id' => $att ),
			array( 'predicate' => 'as:attachment', 'role' => 'content', 'object_attachment_id' => $att ),
			array( 'predicate' => 'as:image', 'role' => 'featured', 'object_attachment_id' => $att ),
		)
	);
	ax_rel_assert( $ax_results, 'replace dedups to 2 rows (nullable URIs)', 2 === $n );

	$by_role = array();
	foreach ( axismundi_media_relations_for_subject( $pub, 'block_content' ) as $row ) {
		$by_role[ $row['role'] ] = (int) $row['occurrence_count'];
	}
	ax_rel_assert( $ax_results, 'content occurrence_count aggregates to 3', 3 === ( $by_role['content'] ?? 0 ) );
	ax_rel_assert( $ax_results, 'featured role stays distinct (count 1)', 1 === ( $by_role['featured'] ?? 0 ) );

	// Per-provider isolation: a featured_image replace must not touch block_content.
	axismundi_media_relations_replace( array( 'post_id' => $pub ), 'featured_image', array( array( 'predicate' => 'as:image', 'role' => 'featured', 'object_attachment_id' => $att2 ) ) );
	ax_rel_assert( $ax_results, 'provider isolation: block_content untouched by featured_image replace', 2 === ax_rel_count( $pub, 'block_content' ) && 1 === ax_rel_count( $pub, 'featured_image' ) );

	// Idempotence: re-running the same block_content replace yields the same rows.
	axismundi_media_relations_replace(
		array( 'post_id' => $pub ),
		'block_content',
		array(
			array( 'role' => 'content', 'object_attachment_id' => $att ),
			array( 'role' => 'content', 'object_attachment_id' => $att ),
			array( 'role' => 'content', 'object_attachment_id' => $att ),
			array( 'predicate' => 'as:image', 'role' => 'featured', 'object_attachment_id' => $att ),
		)
	);
	ax_rel_assert( $ax_results, 'idempotent: re-replace keeps block_content at 2 rows', 2 === ax_rel_count( $pub, 'block_content' ) );

	// Empty replace clears only that provider.
	axismundi_media_relations_replace( array( 'post_id' => $pub ), 'block_content', array() );
	ax_rel_assert( $ax_results, 'empty replace clears block_content, keeps featured_image', 0 === ax_rel_count( $pub, 'block_content' ) && 1 === ax_rel_count( $pub, 'featured_image' ) );

	// Reverse lookup: att2 is used in the published post.
	ax_rel_assert( $ax_results, 'reverse lookup finds att2 usage (admin)', 1 === count( axismundi_media_relations_used_in( $att2, 1 ) ) );

	// Read filter: att is now used only by the private post (owned by admin 1).
	axismundi_media_relations_replace( array( 'post_id' => $priv ), 'block_content', array( array( 'role' => 'content', 'object_attachment_id' => $att ) ) );
	$sub_existing = get_user_by( 'login', 'ax-rel-sub' );
	if ( $sub_existing ) {
		wp_delete_user( $sub_existing->ID, 1 );
	}
	$sub = (int) wp_insert_user( array( 'user_login' => 'ax-rel-sub', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
	$ax_created['users'] = array( $sub );

	ax_rel_assert( $ax_results, 'read filter: admin sees private-source usage of att', count( axismundi_media_relations_used_in( $att, 1 ) ) >= 1 );
	ax_rel_assert( $ax_results, 'read filter: subscriber does NOT see private-source usage', 0 === count( axismundi_media_relations_used_in( $att, $sub ) ) );

} finally {
	foreach ( $ax_created['posts'] as $ax_p ) {
		if ( $ax_p ) {
			axismundi_media_relations_delete_subject( (int) $ax_p );
			wp_delete_post( (int) $ax_p, true );
		}
	}
	foreach ( $ax_created['atts'] as $ax_a ) {
		if ( $ax_a ) {
			wp_delete_attachment( (int) $ax_a, true );
		}
	}
	foreach ( $ax_created['users'] as $ax_u ) {
		if ( $ax_u ) {
			wp_delete_user( (int) $ax_u, 1 );
		}
	}
}

$ax_fail = 0;
foreach ( $ax_results as $ax_r ) {
	if ( ! $ax_r ) {
		++$ax_fail;
	}
}
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
printf( "\n== %d checks, %d failed ==\n", count( $ax_results ), $ax_fail );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_fail > 0 ? 1 : 0 );
}
exit( $ax_fail > 0 ? 1 : 0 );
