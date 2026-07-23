<?php
/**
 * Mixed hashtag archive regression: local Objects and public remote observations.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/hashtags.php';
require_once dirname( __DIR__ ) . '/includes/hashtag-archive.php';
require_once dirname( __DIR__ ) . '/includes/remote-objects.php';

$ax_hashtag_archive_results = array();
$ax_hashtag_archive_slug    = 'audit-archive-' . strtolower( wp_generate_password( 10, false, false ) );
$ax_hashtag_archive_name    = 'AuditArchive' . strtolower( wp_generate_password( 8, false, false ) );
$ax_hashtag_archive_public  = 'https://archive-tags.example/objects/public-' . $ax_hashtag_archive_slug;
$ax_hashtag_archive_private = 'https://archive-tags.example/objects/private-' . $ax_hashtag_archive_slug;
$ax_hashtag_archive_post_id = 0;
$ax_hashtag_archive_term_id = 0;
$ax_hashtag_archive_tpl_id  = 0;

/** Record one assertion in the CLI-friendly archive audit. */
function ax_hashtag_archive_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	axismundi_op_install();
	axismundi_op_register_hashtag_taxonomy();
	$term = axismundi_op_ensure_hashtag_term( $ax_hashtag_archive_name );
	if ( $term instanceof WP_Term ) {
		$ax_hashtag_archive_term_id = (int) $term->term_id;
	}
	$ax_hashtag_archive_post_id = (int) wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_author'  => 1,
			'post_title'   => 'Hashtag archive local object',
			'post_content' => '<p>Local archive content.</p>',
		),
		true
	);
	if ( $term instanceof WP_Term && $ax_hashtag_archive_post_id > 0 ) {
		wp_set_object_terms( $ax_hashtag_archive_post_id, array( $term->term_id ), AXISMUNDI_OP_HASHTAG_TAXONOMY );
	}
	$public = axismundi_op_remote_object_store(
		array(
			'id'           => $ax_hashtag_archive_public,
			'type'         => 'Note',
			'attributedTo' => 'https://archive-tags.example/users/alice',
			'to'           => 'https://www.w3.org/ns/activitystreams#Public',
			'content'      => '<p>Public remote archive content.</p>',
			'published'    => '2026-07-22T00:00:00Z',
			'tag'          => array( array( 'type' => 'Hashtag', 'name' => '#' . $ax_hashtag_archive_name ) ),
		)
	);
	$private = axismundi_op_remote_object_store(
		array(
			'id'           => $ax_hashtag_archive_private,
			'type'         => 'Note',
			'attributedTo' => 'https://archive-tags.example/users/alice',
			'to'           => 'https://archive-tags.example/users/bob',
			'content'      => '<p>Private remote archive content.</p>',
			'published'    => '2026-07-22T01:00:00Z',
			'tag'          => array( array( 'type' => 'Hashtag', 'name' => '#' . $ax_hashtag_archive_name ) ),
		)
	);
	$all   = $term instanceof WP_Term ? axismundi_op_get_hashtag_archive_items( $term, 'all' ) : array();
	$uris  = array_column( $all, 'object_uri' );
	$post  = $ax_hashtag_archive_post_id > 0 ? get_post( $ax_hashtag_archive_post_id ) : null;
	$local = $post instanceof WP_Post ? axismundi_op_post_object_uri( $post ) : '';
	ax_hashtag_archive_assert( $ax_hashtag_archive_results, 'all mode merges public local and public remote Objects by canonical URI', '' !== $local && in_array( $local, $uris, true ) && in_array( $ax_hashtag_archive_public, $uris, true ) );
	ax_hashtag_archive_assert( $ax_hashtag_archive_results, 'a remote Object without an explicit public audience never enters the archive', ! in_array( $ax_hashtag_archive_private, $uris, true ) );
	$remote = $term instanceof WP_Term ? axismundi_op_get_hashtag_archive_items( $term, 'remote' ) : array();
	ax_hashtag_archive_assert( $ax_hashtag_archive_results, 'remote mode returns the public remote cache projection without local WordPress Objects', array( $ax_hashtag_archive_public ) === array_column( $remote, 'object_uri' ) );
	$post_only = $term instanceof WP_Term ? axismundi_op_get_hashtag_archive_items( $term, 'post' ) : array();
	ax_hashtag_archive_assert( $ax_hashtag_archive_results, 'post mode returns the local Post projection without remote cache Objects', array( $local ) === array_column( $post_only, 'object_uri' ) );
	ax_hashtag_archive_assert( $ax_hashtag_archive_results, 'both archive sources resolve through the common Object card renderer', '' !== axismundi_op_render_object_by_uri( $local ) && '' !== axismundi_op_render_object_by_uri( $ax_hashtag_archive_public ) );

	// The archive is a block inside the normal template hierarchy, never a
	// `template_redirect` render that would bypass the theme's site chrome.
	ax_hashtag_archive_assert( $ax_hashtag_archive_results, 'the archive is a registered dynamic block rather than a direct template render', WP_Block_Type_Registry::get_instance()->is_registered( 'axismundi/hashtag-archive' ) && false === has_action( 'template_redirect', 'axismundi_op_hashtag_archive_template_redirect' ) );

	// Render through the block pipeline rather than the bare callback so block
	// supports resolve exactly as they do in the template hierarchy.
	global $wp_query;
	$ax_markup     = '<!-- wp:axismundi/hashtag-archive /-->';
	$ax_prev_query = $wp_query;
	$off_context   = do_blocks( $ax_markup );
	$wp_query      = new WP_Query( array( AXISMUNDI_OP_HASHTAG_TAXONOMY => $term instanceof WP_Term ? $term->slug : '' ) );
	$in_context    = do_blocks( $ax_markup );
	$wp_query      = $ax_prev_query;
	ax_hashtag_archive_assert( $ax_hashtag_archive_results, 'the archive block renders nothing outside a hashtag term context', '' === $off_context );
	ax_hashtag_archive_assert(
		$ax_hashtag_archive_results,
		'the archive block renders both local and remote cards with its type filters',
		false !== strpos( $in_context, 'axismundi-hashtag-archive__filters' )
			&& false !== strpos( $in_context, 'Local archive content' )
			&& false !== strpos( $in_context, 'Public remote archive content' )
	);

	$ax_hierarchy = array( 'taxonomy-' . AXISMUNDI_OP_HASHTAG_TAXONOMY, 'taxonomy', 'archive', 'index' );
	$ax_bundled   = resolve_block_template( 'taxonomy', $ax_hierarchy, '' );
	ax_hashtag_archive_assert(
		$ax_hashtag_archive_results,
		'the bundled default resolves through the standard taxonomy hierarchy',
		$ax_bundled instanceof WP_Block_Template && 'taxonomy-' . AXISMUNDI_OP_HASHTAG_TAXONOMY === $ax_bundled->slug && 'plugin' === $ax_bundled->source
	);
	$ax_hashtag_archive_tpl_id = (int) wp_insert_post(
		array(
			'post_type'    => 'wp_template',
			'post_name'    => 'taxonomy-' . AXISMUNDI_OP_HASHTAG_TAXONOMY,
			'post_title'   => 'Hashtag archive customization',
			'post_status'  => 'publish',
			'post_content' => '<!-- wp:paragraph --><p>Site Editor customization.</p><!-- /wp:paragraph -->',
		)
	);
	if ( $ax_hashtag_archive_tpl_id > 0 ) {
		wp_set_object_terms( $ax_hashtag_archive_tpl_id, get_stylesheet(), 'wp_theme' );
		wp_cache_flush();
	}
	$ax_customized = resolve_block_template( 'taxonomy', $ax_hierarchy, '' );
	ax_hashtag_archive_assert(
		$ax_hashtag_archive_results,
		'a Site Editor customization takes precedence over the bundled default',
		$ax_customized instanceof WP_Block_Template && 'custom' === $ax_customized->source
	);
} finally {
	if ( $ax_hashtag_archive_tpl_id > 0 ) {
		wp_delete_post( $ax_hashtag_archive_tpl_id, true );
		wp_cache_flush();
	}
	if ( $ax_hashtag_archive_post_id > 0 ) {
		wp_delete_post( $ax_hashtag_archive_post_id, true );
	}
	axismundi_op_remote_object_delete( $ax_hashtag_archive_public );
	axismundi_op_remote_object_delete( $ax_hashtag_archive_private );
	if ( $ax_hashtag_archive_term_id > 0 ) {
		wp_delete_term( $ax_hashtag_archive_term_id, AXISMUNDI_OP_HASHTAG_TAXONOMY );
	}
}

$ax_hashtag_archive_failures = count( array_filter( $ax_hashtag_archive_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_hashtag_archive_results ), $ax_hashtag_archive_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_hashtag_archive_failures > 0 ? 1 : 0 );
}
exit( $ax_hashtag_archive_failures > 0 ? 1 : 0 );
