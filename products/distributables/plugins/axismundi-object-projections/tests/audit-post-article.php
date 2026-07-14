<?php
/**
 * Phase 2 — Core Post → Article regression (dev-only).
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/registry.php';
require_once dirname( __DIR__ ) . '/includes/renderer.php';
require_once dirname( __DIR__ ) . '/includes/post-article.php';

$ax_article_results = array();
$ax_article_posts   = array();

/** @param array<bool> $results Results. @param string $label Label. @param bool $condition Condition. */
function ax_article_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$author_id = get_current_user_id();
	if ( $author_id <= 0 ) {
		$admins    = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
		$author_id = isset( $admins[0] ) ? (int) $admins[0] : 0;
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_author'  => $author_id,
			'post_title'   => 'Projection Article',
			'post_content' => '<!-- wp:paragraph --><p>Hello <strong>world</strong>.</p><!-- /wp:paragraph -->',
			'post_excerpt' => 'A short summary.',
		)
	);
	$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Not an Article provider' ) );
	$draft_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => $author_id, 'post_title' => 'Draft' ) );
	$locked_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $author_id, 'post_title' => 'Locked', 'post_password' => 'secret' ) );
	$ax_article_posts = array_filter( array( $post_id, $page_id, $draft_id, $locked_id ), 'is_int' );

	$post   = get_post( $post_id );
	$page   = get_post( $page_id );
	$draft  = get_post( $draft_id );
	$locked = get_post( $locked_id );

	ax_article_assert( $ax_article_results, 'the built-in transformer supports core posts but not pages', $post instanceof WP_Post && $page instanceof WP_Post && axismundi_op_post_article_supports( $post ) && ! axismundi_op_post_article_supports( $page ) );

	add_filter( 'axismundi_op_post_actor_uri', static fn( string $uri, WP_Post $source ) : string => 'https://example.com/actors/test-author', 10, 2 );
	$GLOBALS['axismundi_op_loaded']              = false;
	$GLOBALS['axismundi_op_object_transformers'] = array();
	$GLOBALS['axismundi_op_sequence']            = 0;
	$article = axismundi_op_transform_object( $post );
	if ( is_wp_error( $article ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI diagnostic.
		printf( "[DEBUG] public article error: %s — %s\n", $article->get_error_code(), $article->get_error_message() );
	}

	ax_article_assert(
		$ax_article_results,
		'a public post projects to Article with stable id, human url, Actor attribution, and renderer context',
		is_array( $article )
			&& 'Article' === $article['type']
			&& add_query_arg( 'p', $post_id, home_url( '/' ) ) === $article['id']
			&& get_permalink( $post_id ) === $article['url']
			&& 'https://example.com/actors/test-author' === $article['attributedTo']
			&& 'https://www.w3.org/ns/activitystreams' === $article['@context']
			&& 'A short summary.' === $article['summary']
	);
	$content_contract = is_array( $article ) && false !== strpos( (string) $article['content'], '<p' ) && ! empty( $article['published'] ) && ! empty( $article['updated'] );
	if ( ! $content_contract && is_array( $article ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI diagnostic.
		printf( "[DEBUG] content=%s published=%s updated=%s\n", (string) $article['content'], (string) $article['published'], (string) $article['updated'] );
	}
	ax_article_assert( $ax_article_results, 'Article content is rendered HTML and has published/updated timestamps', $content_contract );

	$draft_result  = axismundi_op_transform_object( $draft );
	$locked_result = axismundi_op_transform_object( $locked );
	ax_article_assert( $ax_article_results, 'draft and password-protected posts fail closed as not public', is_wp_error( $draft_result ) && 'ax_op_not_public' === $draft_result->get_error_code() && is_wp_error( $locked_result ) && 'ax_op_not_public' === $locked_result->get_error_code() );

	add_filter( 'axismundi_op_post_object_uri', static fn( string $uri, WP_Post $source ) : string => get_permalink( $source ), 10, 2 );
	$legacy = axismundi_op_transform_object( $post );
	if ( is_wp_error( $legacy ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI diagnostic.
		printf( "[DEBUG] legacy article error: %s — %s\n", $legacy->get_error_code(), $legacy->get_error_message() );
	}
	ax_article_assert( $ax_article_results, 'the object-uri filter preserves an adapter legacy permalink id choice', is_array( $legacy ) && get_permalink( $post ) === $legacy['id'] );
	remove_all_filters( 'axismundi_op_post_object_uri' );

	remove_all_filters( 'axismundi_op_post_actor_uri' );
	$without_actor = axismundi_op_transform_object( $post );
	$expected_public_actor = '' !== axismundi_op_post_actor_uri( $post );
	ax_article_assert(
		$ax_article_results,
		'a post without a public Actor fails closed, while an existing public user/site Actor remains valid',
		$expected_public_actor ? is_array( $without_actor ) : is_wp_error( $without_actor ) && 'ax_op_not_public' === $without_actor->get_error_code()
	);
} finally {
	remove_all_filters( 'axismundi_op_post_actor_uri' );
	remove_all_filters( 'axismundi_op_post_object_uri' );
	foreach ( $ax_article_posts as $created_post_id ) {
		wp_delete_post( (int) $created_post_id, true );
	}
}

$ax_article_failures = count( array_filter( $ax_article_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_article_results ), $ax_article_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_article_failures > 0 ? 1 : 0 );
}
exit( $ax_article_failures > 0 ? 1 : 0 );
