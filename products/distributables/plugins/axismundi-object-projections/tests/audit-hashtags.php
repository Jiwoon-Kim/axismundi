<?php
/**
 * Shared hashtag vocabulary and remote observation index regression.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/hashtags.php';
require_once dirname( __DIR__ ) . '/includes/object-relations.php';
require_once dirname( __DIR__ ) . '/includes/remote-objects.php';

$ax_hashtag_results = array();
$ax_hashtag_uri     = 'https://hashtags.example/objects/audit-note';
$ax_hashtag_terms   = array();
$ax_hashtag_name    = 'AuditTag' . strtolower( wp_generate_password( 8, false, false ) );
$ax_hashtag_second  = 'AuditTag' . strtolower( wp_generate_password( 8, false, false ) );

/** @param array<bool> $results Results. @param string $label Label. @param bool $condition Condition. */
function ax_hashtag_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	global $wpdb;
	$installed = axismundi_op_install();
	axismundi_op_register_hashtag_taxonomy();
	$table = axismundi_op_remote_object_hashtags_table();
	ax_hashtag_assert( $ax_hashtag_results, 'the shared ax_hashtag taxonomy registers for Posts, attachments, and Notes without replacing post_tag', taxonomy_exists( AXISMUNDI_OP_HASHTAG_TAXONOMY ) && is_object_in_taxonomy( 'post', AXISMUNDI_OP_HASHTAG_TAXONOMY ) && is_object_in_taxonomy( 'attachment', AXISMUNDI_OP_HASHTAG_TAXONOMY ) && is_object_in_taxonomy( 'ax_note', AXISMUNDI_OP_HASHTAG_TAXONOMY ) && taxonomy_exists( 'post_tag' ) );
	ax_hashtag_assert( $ax_hashtag_results, 'normalization strips one hash and case-folds comparison without collapsing distinct spelling', array( 'name' => 'Busan', 'key' => 'busan' ) === axismundi_op_normalize_hashtag( '#Busan' ) && '부산_여행' !== ( axismundi_op_normalize_hashtag( '부산여행' )['name'] ?? '' ) );

	$payload = array(
		'id'           => $ax_hashtag_uri,
		'type'         => 'Note',
		'attributedTo' => 'https://hashtags.example/users/alice',
		'to'           => 'https://www.w3.org/ns/activitystreams#Public',
		'content'      => '<p>Tagged remote note.</p>',
		'tag'          => array(
			array( 'type' => 'Hashtag', 'name' => '#' . $ax_hashtag_name, 'href' => 'https://hashtags.example/tags/audit' ),
			array( 'type' => 'Hashtag', 'name' => '#' . strtoupper( $ax_hashtag_name ), 'href' => 'https://hashtags.example/tags/AUDIT' ),
			array( 'type' => 'Mention', 'name' => '@alice', 'href' => 'https://hashtags.example/users/alice' ),
		),
	);
	$stored = axismundi_op_remote_object_store( $payload );
	$rows   = is_array( $stored )
		? (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE remote_object_id = %d", (int) $stored['id'] ), ARRAY_A )
		: array();
	ax_hashtag_assert( $ax_hashtag_results, 'a remote Hashtag materializes one shared normalized term and keeps source evidence in a rebuildable relation', is_array( $stored ) && 1 === count( $rows ) && $ax_hashtag_name === (string) $rows[0]['observed_name'] && 'https://hashtags.example/tags/audit' === (string) $rows[0]['source_href'] );
	$first_term = axismundi_op_ensure_hashtag_term( $ax_hashtag_name );
	if ( $first_term instanceof WP_Term ) {
		$ax_hashtag_terms[] = (int) $first_term->term_id;
	}

	$payload['tag'] = array( array( 'type' => 'Hashtag', 'name' => '#' . $ax_hashtag_second, 'href' => 'https://hashtags.example/tags/second' ) );
	$updated        = axismundi_op_remote_object_store( $payload );
	$rows           = is_array( $updated )
		? (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE remote_object_id = %d", (int) $updated['id'] ), ARRAY_A )
		: array();
	ax_hashtag_assert( $ax_hashtag_results, 'refresh replaces stale remote hashtag evidence rather than accumulating it', is_array( $updated ) && 1 === count( $rows ) && $ax_hashtag_second === (string) $rows[0]['observed_name'] );
	$second_term = axismundi_op_ensure_hashtag_term( $ax_hashtag_second );
	if ( $second_term instanceof WP_Term ) {
		$ax_hashtag_terms[] = (int) $second_term->term_id;
	}

	$post_id = wp_insert_post( array( 'post_status' => 'publish', 'post_type' => 'post', 'post_title' => 'Hashtag audit post', 'post_author' => 1 ), true );
	$term    = $first_term;
	if ( $term instanceof WP_Term && ! is_wp_error( $post_id ) ) {
		wp_set_object_terms( $post_id, array( (int) $term->term_id ), AXISMUNDI_OP_HASHTAG_TAXONOMY );
	}
	$post = is_wp_error( $post_id ) ? null : get_post( $post_id );
	$tags = $post instanceof WP_Post ? axismundi_op_post_hashtag_tags( $post ) : array();
	ax_hashtag_assert( $ax_hashtag_results, 'local Posts serialize assigned shared terms as ActivityStreams Hashtag links', $term instanceof WP_Term && 1 === count( $tags ) && 'Hashtag' === $tags[0]['type'] && '#' . strtolower( $ax_hashtag_name ) === strtolower( $tags[0]['name'] ) && false !== strpos( $tags[0]['href'], '/hashtag/' ) );

	$attachment_id = wp_insert_post( array( 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_title' => 'Hashtag audit attachment', 'post_author' => 1 ), true );
	if ( $term instanceof WP_Term && ! is_wp_error( $attachment_id ) ) {
		wp_set_object_terms( $attachment_id, array( (int) $term->term_id ), AXISMUNDI_OP_HASHTAG_TAXONOMY );
	}
	$attachment = is_wp_error( $attachment_id ) ? null : get_post( $attachment_id );
	ax_hashtag_assert( $ax_hashtag_results, 'attachment assignments remain local-search metadata and do not federate by default', $attachment instanceof WP_Post && array() === axismundi_op_post_hashtag_tags( $attachment ) );

	if ( ! is_wp_error( $post_id ) ) {
		wp_delete_post( $post_id, true );
	}
	if ( ! is_wp_error( $attachment_id ) ) {
		wp_delete_attachment( $attachment_id, true );
	}
	$deleted = axismundi_op_remote_object_delete( $ax_hashtag_uri );
	$rows    = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE remote_object_id = %d", (int) ( $updated['id'] ?? 0 ) ), ARRAY_A );
	ax_hashtag_assert( $ax_hashtag_results, 'deleting a remote observation removes only its rebuildable hashtag relation rows', $deleted && empty( $rows ) );
} finally {
	axismundi_op_remote_object_delete( $ax_hashtag_uri );
	foreach ( array_unique( $ax_hashtag_terms ) as $term_id ) {
		wp_delete_term( (int) $term_id, AXISMUNDI_OP_HASHTAG_TAXONOMY );
	}
}

$ax_hashtag_failures = count( array_filter( $ax_hashtag_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_hashtag_results ), $ax_hashtag_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_hashtag_failures > 0 ? 1 : 0 );
}
exit( $ax_hashtag_failures > 0 ? 1 : 0 );
