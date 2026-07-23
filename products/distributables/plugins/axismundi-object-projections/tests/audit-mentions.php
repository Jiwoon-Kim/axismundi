<?php
/**
 * Object-to-Actor Mention relation projection regression.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/mentions.php';
require_once dirname( __DIR__ ) . '/includes/remote-objects.php';

$ax_mention_results = array();
$ax_mention_post_id = 0;
$ax_mention_remote  = 'https://mentions-audit.example/objects/' . strtolower( wp_generate_password( 10, false, false ) );
$ax_mention_actor_a = 'https://mentions-audit.example/actors/alice';
$ax_mention_actor_b = 'https://mentions-audit.example/actors/bob';
$ax_mention_actor_c = 'https://mentions-audit.example/actors/carol';

/** Record one assertion in the mention projection audit. */
function ax_mention_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Keep shared-DB assertions scoped to this audit's generated source URI. */
function ax_mention_rows_for_source( array $rows, string $source_uri ) : array {
	return array_values(
		array_filter(
			$rows,
			static fn( array $row ) : bool => $source_uri === (string) ( $row['source_object_uri'] ?? '' )
		)
	);
}

try {
	axismundi_op_install();
	$ax_mention_post_id = (int) wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_author'  => 1,
			'post_title'   => 'Mention audit local source',
			'post_content' => '<p><a class="mention" href="' . esc_url( $ax_mention_actor_a ) . '">@alice</a></p>',
		)
	);
	update_post_meta( $ax_mention_post_id, AXISMUNDI_OP_POST_MENTIONS_META, array( $ax_mention_actor_b ) );
	$post = get_post( $ax_mention_post_id );
	$source_uri = $post instanceof WP_Post ? axismundi_op_post_object_uri( $post ) : '';
	$alice_rows = ax_mention_rows_for_source( axismundi_op_object_mentions_for_actor( $ax_mention_actor_a ), $source_uri );
	$bob_rows   = ax_mention_rows_for_source( axismundi_op_object_mentions_for_actor( $ax_mention_actor_b ), $source_uri );
	ax_mention_assert( $ax_mention_results, 'a local Post keeps inline and explicit canonical Actor URI edges as separate provenance', 1 === count( $alice_rows ) && 'inline' === (string) $alice_rows[0]['origin'] && 1 === count( $bob_rows ) && 'explicit' === (string) $bob_rows[0]['origin'] && $source_uri === (string) $alice_rows[0]['source_object_uri'] );

	$stored = axismundi_op_remote_object_store(
		array(
			'id'           => $ax_mention_remote,
			'type'         => 'Note',
			'attributedTo' => 'https://mentions-audit.example/actors/source',
			'to'           => 'https://www.w3.org/ns/activitystreams#Public',
			'content'      => '<p>Remote mention.</p>',
			'tag'          => array(
				array( 'type' => 'Mention', 'href' => $ax_mention_actor_c, 'name' => '@carol@mentions-audit.example' ),
				array( 'type' => 'Mention', 'href' => 'not a URI', 'name' => '@invalid' ),
			),
		)
	);
	$carol_rows = ax_mention_rows_for_source( axismundi_op_object_mentions_for_actor( $ax_mention_actor_c ), $ax_mention_remote );
	ax_mention_assert( $ax_mention_results, 'a remote Mention creates an unresolved-safe URI edge without requiring an Actor cache row', is_array( $stored ) && 1 === count( $carol_rows ) && 'remote' === (string) $carol_rows[0]['origin'] && $ax_mention_remote === (string) $carol_rows[0]['source_object_uri'] );

	$updated_payload = array(
		'id'           => $ax_mention_remote,
		'type'         => 'Note',
		'attributedTo' => 'https://mentions-audit.example/actors/source',
		'to'           => 'https://www.w3.org/ns/activitystreams#Public',
		'content'      => '<p>Remote mention updated.</p>',
		'tag'          => array( array( 'type' => 'Mention', 'href' => $ax_mention_actor_a, 'name' => '@alice@mentions-audit.example' ) ),
	);
	axismundi_op_remote_object_store( $updated_payload );
	$carol_rows = ax_mention_rows_for_source( axismundi_op_object_mentions_for_actor( $ax_mention_actor_c ), $ax_mention_remote );
	$alice_rows = ax_mention_rows_for_source( axismundi_op_object_mentions_for_actor( $ax_mention_actor_a ), $ax_mention_remote );
	ax_mention_assert( $ax_mention_results, 'a remote refresh replaces stale Mention evidence rather than accumulating it', empty( $carol_rows ) && 1 === count( $alice_rows ) );

	axismundi_op_remote_object_delete( $ax_mention_remote );
	$alice_rows = ax_mention_rows_for_source( axismundi_op_object_mentions_for_actor( $ax_mention_actor_a ), $ax_mention_remote );
	ax_mention_assert( $ax_mention_results, 'deleting a remote observation removes only its rebuildable Mention edges', empty( $alice_rows ) );

	wp_delete_post( $ax_mention_post_id, true );
	$ax_mention_post_id = 0;
	$alice_rows = ax_mention_rows_for_source( axismundi_op_object_mentions_for_actor( $ax_mention_actor_a ), $source_uri );
	$bob_rows   = ax_mention_rows_for_source( axismundi_op_object_mentions_for_actor( $ax_mention_actor_b ), $source_uri );
	ax_mention_assert( $ax_mention_results, 'deleting a local Post removes its explicit and inline Mention edges', empty( $alice_rows ) && empty( $bob_rows ) );
} finally {
	axismundi_op_remote_object_delete( $ax_mention_remote );
	if ( $ax_mention_post_id > 0 ) {
		wp_delete_post( $ax_mention_post_id, true );
	}
}

$ax_mention_failures = count( array_filter( $ax_mention_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_mention_results ), $ax_mention_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_mention_failures > 0 ? 1 : 0 );
}
exit( $ax_mention_failures > 0 ? 1 : 0 );
