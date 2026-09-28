<?php
/**
 * Late-escaping regression for server-rendered blocks (dev-only).
 *
 * Every block `render.php` escapes at the point it prints, which only works if the allowlist
 * covers what the blocks actually emit. `wp_kses_post()` does not: measured against a rendered
 * card it removes `template`, `input`, `srcset`, `decoding`, `draggable`, `aria-pressed` and the
 * Interactivity directives those elements carry. Losing them does not read as a security fix, it
 * reads as the page quietly breaking, so this asserts the filter takes nothing away.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_esc_results = array();
$ax_esc_posts   = array();
$ax_esc_users   = array();
$ax_esc_ids     = array();

/** @param bool[] $results Results. */
function ax_esc_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Every element name a fragment opens. */
function ax_esc_tags( string $html ) : array {
	preg_match_all( '/<([a-zA-Z0-9-]+)/', $html, $matches );
	$tags = array_unique( $matches[1] );
	sort( $tags );
	return $tags;
}

/** Every attribute name a fragment sets. */
function ax_esc_attributes( string $html ) : array {
	preg_match_all( '/\s([a-zA-Z-]+(?::[a-zA-Z-]+)?)=/', $html, $matches );
	$attributes = array_unique( $matches[1] );
	sort( $attributes );
	return $attributes;
}

try {
	$author_id = (int) wp_insert_user( array( 'user_login' => 'ax-esc-' . wp_generate_password( 8, false, false ), 'user_pass' => wp_generate_password( 20 ), 'role' => 'administrator' ) );
	$ax_esc_users[] = $author_id;
	wp_set_current_user( $author_id );
	$actor = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'local_user_id' => $author_id, 'preferred_username' => 'esc-' . strtolower( wp_generate_password( 8, false, false ) ) ) );
	if ( $actor instanceof Axismundi_Actor ) {
		$ax_esc_ids[] = $actor->get_identity_id();
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	}

	$post_id = (int) wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_author'  => $author_id,
			'post_title'   => 'Escaping probe',
			'post_excerpt' => 'A summary that earns a read-more link.',
			'post_content' => '<!-- wp:paragraph --><p>Body with <a href="https://example.org">a link</a>.</p><!-- /wp:paragraph -->',
		)
	);
	$ax_esc_posts[] = $post_id;
	$object_uri = axismundi_op_post_object_uri( get_post( $post_id ) );

	// Interactions on purpose: the reaction bar is what carries the Interactivity directives.
	$rendered = axismundi_op_render_object_by_uri( $object_uri, array( 'headingTag' => 'h3', 'interactions' => true, 'viewerScoped' => true ) );
	$filtered = wp_kses( $rendered, axismundi_op_allowed_block_html() );

	ax_esc_assert( $ax_esc_results, 'a card renders something to check', '' !== trim( $rendered ) && false !== strpos( $rendered, 'data-wp-interactive' ) );
	ax_esc_assert(
		$ax_esc_results,
		'late escaping removes no element a block emits: ' . ( implode( ',', array_diff( ax_esc_tags( $rendered ), ax_esc_tags( $filtered ) ) ) ?: 'none lost' ),
		array() === array_diff( ax_esc_tags( $rendered ), ax_esc_tags( $filtered ) )
	);
	ax_esc_assert(
		$ax_esc_results,
		'late escaping removes no attribute a block sets: ' . ( implode( ',', array_diff( ax_esc_attributes( $rendered ), ax_esc_attributes( $filtered ) ) ) ?: 'none lost' ),
		array() === array_diff( ax_esc_attributes( $rendered ), ax_esc_attributes( $filtered ) )
	);

	// The stock post allowlist is not a substitute, and this records why.
	$post_set = wp_kses_post( $rendered );
	ax_esc_assert(
		$ax_esc_results,
		'the stock post allowlist would strip block markup, which is why this plugin declares its own',
		array() !== array_merge( array_diff( ax_esc_tags( $rendered ), ax_esc_tags( $post_set ) ), array_diff( ax_esc_attributes( $rendered ), ax_esc_attributes( $post_set ) ) )
	);

	// And it is still a filter, not a pass-through.
	$hostile  = '<div data-wp-interactive="x"><script>alert(1)</script><a href="javascript:alert(2)" onclick="alert(3)">x</a><iframe src="https://evil.example"></iframe></div>';
	$defanged = wp_kses( $hostile, axismundi_op_allowed_block_html() );
	ax_esc_assert(
		$ax_esc_results,
		'script, javascript: URLs, event handlers and frames do not survive it',
		false === strpos( $defanged, '<script' )
			&& false === strpos( $defanged, 'javascript:' )
			&& false === strpos( $defanged, 'onclick' )
			&& false === strpos( $defanged, '<iframe' )
			&& false !== strpos( $defanged, 'data-wp-interactive' )
	);
} finally {
	global $wpdb;
	wp_set_current_user( isset( $author_id ) ? (int) $author_id : 0 );
	foreach ( array_filter( $ax_esc_posts ) as $id ) {
		$uri = axismundi_op_post_object_uri( get_post( $id ) );
		if ( function_exists( 'axismundi_act_activities_table' ) ) {
			$wpdb->delete( axismundi_act_activities_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		wp_delete_post( $id, true );
	}
	foreach ( array_filter( $ax_esc_ids ) as $identity_id ) {
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( array_filter( $ax_esc_users ) as $id ) {
		wp_delete_user( $id );
	}
}

$ax_esc_failures = count( array_filter( $ax_esc_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_esc_results ), $ax_esc_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_esc_failures > 0 ? 1 : 0 );
}
exit( $ax_esc_failures > 0 ? 1 : 0 );
