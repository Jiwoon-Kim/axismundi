<?php
/**
 * Phase 2 — Core Post → Article regression (dev-only).
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/registry.php';
require_once dirname( __DIR__ ) . '/includes/renderer.php';
require_once dirname( __DIR__ ) . '/includes/post-settings.php';
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
	add_filter( 'axismundi_op_post_lifecycle_owner', static fn() : string => 'fixture' );
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
			'post_content' => '<!-- wp:paragraph --><p>Hello <strong>world</strong>.</p><!-- /wp:paragraph --><!-- wp:more --><!--more--><!-- /wp:more --><!-- wp:paragraph --><p>Extended body.</p><!-- /wp:paragraph -->',
			'post_excerpt' => '<em>A short summary.</em>',
		)
	);
	$fallback_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_author'  => $author_id,
			'post_title'   => 'Excerpt Preview',
			'post_content' => '<p>Full body without a More block.</p>',
			'post_excerpt' => 'Editorial excerpt.',
		)
	);
	$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Not an Article provider' ) );
	$draft_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => $author_id, 'post_title' => 'Draft' ) );
	$locked_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $author_id, 'post_title' => 'Locked', 'post_password' => 'secret' ) );
	$ax_article_posts = array_filter( array( $post_id, $fallback_id, $page_id, $draft_id, $locked_id ), 'is_int' );

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
			&& get_permalink( $post_id ) === $article['url']['href']
			&& 'text/html' === $article['url']['mediaType']
			&& 'https://example.com/actors/test-author' === $article['attributedTo']
			&& is_array( $article['@context'] )
			&& 'https://www.w3.org/ns/activitystreams' === $article['@context'][0]
			&& array( 'sensitive' => 'as:sensitive' ) === $article['@context'][1]
			&& false !== strpos( (string) $article['summary'], '<em>A short summary.</em>' )
			&& ! isset( $article['interactionPolicy'], $article['quoteAuthorization'] )
	);
	$content_contract = is_array( $article ) && false !== strpos( (string) $article['content'], '<p' ) && ! empty( $article['published'] ) && ! empty( $article['updated'] );
	if ( ! $content_contract && is_array( $article ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI diagnostic.
		printf( "[DEBUG] content=%s published=%s updated=%s\n", (string) $article['content'], (string) $article['published'], (string) $article['updated'] );
	}
	ax_article_assert( $ax_article_results, 'Article content is rendered HTML and has published/updated timestamps', $content_contract );
	$previous_global_post = $GLOBALS['post'] ?? null;
	$GLOBALS['post']      = $page;
	$context_post_id      = 0;
	$context_probe        = static function ( string $content ) use ( &$context_post_id ) : string {
		$context_post_id = get_the_ID();
		return $content;
	};
	add_filter( 'the_content', $context_probe, 999 );
	axismundi_op_post_article_content( $post );
	remove_filter( 'the_content', $context_probe, 999 );
	ax_article_assert(
		$ax_article_results,
		'the content pipeline receives the projected Post context and restores the caller global afterward',
		$post_id === $context_post_id && $page === ( $GLOBALS['post'] ?? null )
	);
	if ( null === $previous_global_post ) {
		unset( $GLOBALS['post'] );
	} else {
		$GLOBALS['post'] = $previous_global_post;
	}
	ax_article_assert(
		$ax_article_results,
		'a More block creates an embedded Note preview with no independent id/url or read-more link',
		is_array( $article ) && isset( $article['preview'] )
			&& 'Note' === $article['preview']['type']
			&& 'https://example.com/actors/test-author' === $article['preview']['attributedTo']
			&& ! empty( $article['preview']['published'] )
			&& ! isset( $article['preview']['id'], $article['preview']['url'] )
			&& false !== strpos( (string) $article['preview']['content'], 'Hello' )
			&& false === strpos( (string) $article['preview']['content'], 'Extended body' )
			&& false === stripos( (string) $article['preview']['content'], 'read more' )
	);
	$fallback = axismundi_op_transform_object( get_post( $fallback_id ) );
	ax_article_assert(
		$ax_article_results,
		'a manual Excerpt remains the Article summary and supplies the Note preview when no More block exists',
		is_array( $fallback ) && false !== strpos( (string) $fallback['summary'], 'Editorial excerpt.' )
			&& false !== strpos( (string) $fallback['preview']['content'], '<strong>Excerpt Preview</strong>' )
			&& false !== strpos( (string) $fallback['preview']['content'], 'Editorial excerpt.' )
	);

	update_post_meta( $post_id, AXISMUNDI_OP_POST_SENSITIVE_META, '1' );
	update_post_meta( $post_id, AXISMUNDI_OP_POST_WARNING_META, 'Spoilers & distressing imagery' );
	$sensitive = axismundi_op_transform_object( get_post( $post_id ) );
	ax_article_assert(
		$ax_article_results,
		'sensitive Post metadata emits the boolean and dcterms subject without replacing the Excerpt summary',
		is_array( $sensitive )
			&& true === $sensitive['sensitive']
			&& 'Spoilers & distressing imagery' === $sensitive['dcterms:subject']
			&& false !== strpos( (string) $sensitive['summary'], 'A short summary.' )
			&& in_array( array( 'dcterms' => 'http://purl.org/dc/terms/' ), $sensitive['@context'], true )
	);
	update_post_meta( $post_id, AXISMUNDI_OP_POST_SENSITIVE_META, '0' );
	$standard = axismundi_op_transform_object( get_post( $post_id ) );
	ax_article_assert(
		$ax_article_results,
		'a stored warning is not exposed while the sensitive flag is disabled',
		is_array( $standard ) && false === $standard['sensitive'] && ! isset( $standard['dcterms:subject'] )
	);

	update_post_meta( $post_id, AXISMUNDI_OP_POST_QUOTE_POLICY_META, 'anyone' );
	$quote_anyone  = axismundi_op_transform_object( get_post( $post_id ) );
	$policy_context = array(
		'gts'               => 'https://gotosocial.org/ns#',
		'interactionPolicy' => array( '@id' => 'gts:interactionPolicy', '@type' => '@id' ),
		'canQuote'          => array( '@id' => 'gts:canQuote', '@type' => '@id' ),
		'automaticApproval' => array( '@id' => 'gts:automaticApproval', '@type' => '@id' ),
	);
	ax_article_assert(
		$ax_article_results,
		'an explicit anyone policy projects FEP-044f canQuote without fabricating authorization evidence',
		is_array( $quote_anyone )
			&& 'https://www.w3.org/ns/activitystreams#Public' === $quote_anyone['interactionPolicy']['canQuote']['automaticApproval']
			&& in_array( $policy_context, $quote_anyone['@context'], true )
			&& ! isset( $quote_anyone['quoteAuthorization'] )
	);
	update_post_meta( $post_id, AXISMUNDI_OP_POST_QUOTE_POLICY_META, 'me' );
	$quote_me = axismundi_op_transform_object( get_post( $post_id ) );
	ax_article_assert( $ax_article_results, 'the me policy advertises only the author Actor and preserves the self-quote exception', is_array( $quote_me ) && 'https://example.com/actors/test-author' === $quote_me['interactionPolicy']['canQuote']['automaticApproval'] );
	$site_actor = axismundi_actors_get_site_actor();
	update_post_meta( $post_id, AXISMUNDI_OP_POST_QUOTE_POLICY_META, 'followers' );
	$followers_policy = $site_actor instanceof Axismundi_Actor ? axismundi_op_post_quote_interaction_policy( get_post( $post_id ), $site_actor->get_uri() ) : null;
	ax_article_assert( $ax_article_results, 'the followers policy references the OP-owned stable Followers address without dereferencing it', is_array( $followers_policy ) && axismundi_op_actor_followers_url( $site_actor ) === $followers_policy['canQuote']['automaticApproval'] );
	delete_post_meta( $post_id, AXISMUNDI_OP_POST_QUOTE_POLICY_META );
	$quote_unreported = axismundi_op_transform_object( get_post( $post_id ) );
	ax_article_assert( $ax_article_results, 'removing the explicit policy removes interactionPolicy instead of inventing a default', is_array( $quote_unreported ) && ! isset( $quote_unreported['interactionPolicy'] ) );

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
	remove_all_filters( 'axismundi_op_post_lifecycle_owner' );

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
	remove_all_filters( 'axismundi_op_post_lifecycle_owner' );
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
