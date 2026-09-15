<?php
/**
 * Custom emoji in this site's own posts and comments (dev-only; dist-excluded).
 *
 * Found missing on 2026-09-16: `:wordpress:` in an ordinary post stayed a word, because only
 * Object Projections and Actors surfaces were decorated.
 *
 * Run: npx wp-env run cli wp eval-file wp-content/plugins/axismundi-emoji/tests/audit-emoji-content.php
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-emoji.php';

$ax_ct_results = array();

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_ct_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

global $wpdb;
$ax_ct_row      = axismundi_emoji_local_get( 'wordpress' );
$ax_ct_restore  = null;

try {
	ax_ct_assert( $ax_ct_results, 'the bundled :wordpress: emoji exists and is renderable, so the checks below are meaningful', is_array( $ax_ct_row ) && axismundi_emoji_is_renderable( $ax_ct_row ) );

	ax_ct_assert( $ax_ct_results, 'post content is decorated on the_content', false !== has_filter( 'the_content', 'axismundi_emoji_decorate_site_content' ) );
	ax_ct_assert( $ax_ct_results, 'and before Core smilies, so a local emoji wins over a smiley of the same spelling', has_filter( 'the_content', 'axismundi_emoji_decorate_site_content' ) < (int) has_filter( 'the_content', 'convert_smilies' ) );

	$ax_ct_html = apply_filters( 'the_content', '<p>:wordpress: made this</p>' );
	ax_ct_assert( $ax_ct_results, 'a local shortcode in a post becomes the emoji image', str_contains( $ax_ct_html, '<img class="ax-emoji"' ) && ! str_contains( wp_strip_all_tags( $ax_ct_html ), ':wordpress:' ) );
	ax_ct_assert( $ax_ct_results, 'with the shortcode kept as its alt text', str_contains( $ax_ct_html, 'alt=":wordpress:"' ) );

	$ax_ct_code = apply_filters( 'the_content', '<pre><code>:wordpress:</code></pre>' );
	ax_ct_assert( $ax_ct_results, 'a shortcode inside code is documentation and stays text', ! str_contains( $ax_ct_code, '<img' ) );

	$ax_ct_unknown = axismundi_emoji_decorate_site_content( '<p>:not_an_emoji_here: and 10:30:00</p>' );
	ax_ct_assert( $ax_ct_results, 'an unknown shortcode and a clock time are left for Core untouched', '<p>:not_an_emoji_here: and 10:30:00</p>' === $ax_ct_unknown );

	ax_ct_assert( $ax_ct_results, 'running twice changes nothing more, so a later Object Projections pass is harmless', axismundi_emoji_decorate_site_content( $ax_ct_html ) === $ax_ct_html );

	ax_ct_assert( $ax_ct_results, 'comments are decorated too', str_contains( (string) apply_filters( 'comment_text', ':wordpress:', null, array() ), '<img class="ax-emoji"' ) );

	/*
	 * Declaration survives decoration. Several publishers hand the tokenizer `the_content`
	 * output, which this plugin now decorates; on 2026-09-16 that turned an Article's
	 * `tag[]` from [":wordpress:"] into [] because the tokenizer stripped our own <img>.
	 */
	ax_ct_assert( $ax_ct_results, 'the tokenizer counts an emoji this plugin already rendered as a use of its shortcode', array( 'wordpress' ) === axismundi_emoji_tokenize( $ax_ct_html ) );
	ax_ct_assert( $ax_ct_results, 'and the outbound tag for decorated content is the same as for the authored text', array_column( axismundi_emoji_outbound_tags( array( $ax_ct_html ) ), 'name' ) === array_column( axismundi_emoji_outbound_tags( array( '<p>:wordpress: made this</p>' ) ), 'name' ) );
	ax_ct_assert( $ax_ct_results, 'a remote emoji image (qualified alt) is not counted as a local use', array() === axismundi_emoji_tokenize( '<p><img class="ax-emoji" src="x" alt=":wordpress@example.org:" /></p>' ) );
	ax_ct_assert( $ax_ct_results, 'an unrelated image whose alt looks like a shortcode is not counted', array() === axismundi_emoji_tokenize( '<p><img class="photo" src="x" alt=":wordpress:" /></p>' ) );

	if ( function_exists( 'axismundi_op_post_to_article' ) ) {
		/*
		 * An author with an Actor, or there is no Article at all: under WP-CLI there is no
		 * current user, a post defaults to author 0, and projection refuses it with
		 * `ax_op_post_identity` — which first read here as a missing declaration.
		 */
		$ax_ct_admins  = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		$ax_ct_post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_author'  => (int) ( $ax_ct_admins[0] ?? 0 ),
				'post_title'   => 'Emoji declaration audit',
				'post_content' => '<!-- wp:paragraph --><p>:wordpress: in an Article</p><!-- /wp:paragraph -->',
			)
		);
		try {
			$ax_ct_article = is_int( $ax_ct_post_id ) && $ax_ct_post_id > 0 ? axismundi_op_post_to_article( get_post( $ax_ct_post_id ) ) : null;
			ax_ct_assert( $ax_ct_results, 'the audit post projects to an Article at all' . ( is_wp_error( $ax_ct_article ) ? ' (' . $ax_ct_article->get_error_code() . ')' : '' ), is_array( $ax_ct_article ) );
			$ax_ct_names   = is_array( $ax_ct_article ) ? array_column( array_filter( (array) ( $ax_ct_article['tag'] ?? array() ), static fn( $t ) : bool => is_array( $t ) && in_array( 'Emoji', (array) ( $t['type'] ?? array() ), true ) ), 'name' ) : array();
			ax_ct_assert( $ax_ct_results, 'with the_content decoration on, a projected Article still declares :wordpress: in tag[]', false !== has_filter( 'the_content', 'axismundi_emoji_decorate_site_content' ) && in_array( ':wordpress:', $ax_ct_names, true ) );
		} finally {
			if ( is_int( $ax_ct_post_id ) && $ax_ct_post_id > 0 ) {
				wp_delete_post( $ax_ct_post_id, true );
			}
		}
	}

	/*
	 * Withheld from publication still renders at home. Flip the row's outbound flag for the
	 * duration of one check, then put it back exactly.
	 */
	if ( is_array( $ax_ct_row ) ) {
		$ax_ct_restore = array(
			'outbound_allowed' => $ax_ct_row['outbound_allowed'],
			'review_status'    => $ax_ct_row['review_status'],
		);
		$wpdb->update( axismundi_emoji_table(), array( 'outbound_allowed' => 0 ), array( 'id' => (int) $ax_ct_row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_flush();
		ax_ct_assert( $ax_ct_results, 'an emoji withheld from publication still renders in this site\'s own content', str_contains( axismundi_emoji_decorate_site_content( '<p>:wordpress:</p>' ), '<img class="ax-emoji"' ) );

		$wpdb->update( axismundi_emoji_table(), array( 'outbound_allowed' => (int) $ax_ct_restore['outbound_allowed'], 'review_status' => 'pending' ), array( 'id' => (int) $ax_ct_row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_flush();
		ax_ct_assert( $ax_ct_results, 'but one that is not renderable (pending) stays text', ! str_contains( axismundi_emoji_decorate_site_content( '<p>:wordpress:</p>' ), '<img' ) );
	}
} catch ( Throwable $ax_ct_error ) {
	ax_ct_assert( $ax_ct_results, 'the content suite ran to completion: ' . $ax_ct_error->getMessage(), false );
} finally {
	if ( is_array( $ax_ct_row ) && is_array( $ax_ct_restore ) ) {
		$wpdb->update( axismundi_emoji_table(), array( 'outbound_allowed' => (int) $ax_ct_restore['outbound_allowed'], 'review_status' => (string) $ax_ct_restore['review_status'] ), array( 'id' => (int) $ax_ct_row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_flush();
	}
}

$ax_ct_after = axismundi_emoji_local_get( 'wordpress' );
ax_ct_assert( $ax_ct_results, 'the emoji row is restored exactly', is_array( $ax_ct_row ) && is_array( $ax_ct_after ) && $ax_ct_row['outbound_allowed'] === $ax_ct_after['outbound_allowed'] && $ax_ct_row['review_status'] === $ax_ct_after['review_status'] );

$ax_ct_failures = count( array_filter( $ax_ct_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ct_results ), $ax_ct_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ct_failures > 0 ? 1 : 0 );
}
exit( $ax_ct_failures > 0 ? 1 : 0 );
