<?php
/**
 * Received mention links point at this site's view of the person (dev-only; dist-excluded).
 *
 * The fixture reproduces the real wire shape, taken from a live Misskey note: the anchor
 * points at the origin's human profile page while `tag[].href` carries the Actor URI.
 * Those two strings are deliberately different, which is why the hashtag rewrite's
 * href-matching cannot be reused here and the Actor has to be resolved first.
 *
 * No network.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_ml_results = array();
$ax_ml_uri     = 'https://mention-localize.test/notes/1';
$ax_ml_host    = 'example.org';
$ax_ml_actor   = 'https://' . $ax_ml_host . '/actors/mention-fixture';
$ax_ml_profile = 'https://' . $ax_ml_host . '/@someone';
$ax_ml_created = null;

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_ml_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

try {
	if ( ! function_exists( 'axismundi_actors_upsert_remote' ) ) {
		ax_ml_assert( $ax_ml_results, 'Actors is available to resolve mentions against', false );
		throw new RuntimeException( 'Actors not active' );
	}

	/*
	 * A body whose mention anchor is the origin's profile page, not the Actor URI. The
	 * uncached mention beside it is the case that must be left alone: a reader sent to a
	 * profile this site cannot render is worse off than one following the original link.
	 */
	$ax_ml_payload = array(
		'id'           => $ax_ml_uri,
		'type'         => 'Note',
		'attributedTo' => 'https://mention-localize.test/users/author',
		'content'      => '<p><a href="' . $ax_ml_profile . '" class="u-url mention">@someone@' . $ax_ml_host . '</a> hello '
			. '<a href="https://stranger.test/@nobody" class="u-url mention">@nobody@stranger.test</a> and '
			. '<a href="https://example.net/page">a plain link</a></p>',
		'published'    => '2026-07-29T00:00:00Z',
		'tag'          => array(
			array( 'type' => 'Mention', 'href' => $ax_ml_actor, 'name' => '@someone@' . $ax_ml_host ),
			array( 'type' => 'Mention', 'href' => 'https://stranger.test/actors/unknown', 'name' => '@nobody@stranger.test' ),
		),
	);

	$ax_ml_created = axismundi_actors_upsert_remote(
		array(
			'uri'                => $ax_ml_actor,
			'actor_type'         => 'Person',
			'preferred_username' => 'someone',
			'display_name'       => 'Someone',
			'profile_url'        => $ax_ml_profile,
			'payload'            => array( 'id' => $ax_ml_actor, 'type' => 'Person', 'preferredUsername' => 'someone', 'url' => $ax_ml_profile ),
			'endpoints'          => array(
				'inbox'  => 'https://' . $ax_ml_host . '/actors/mention-fixture/inbox',
				'outbox' => 'https://' . $ax_ml_host . '/actors/mention-fixture/outbox',
			),
		)
	);
	if ( is_wp_error( $ax_ml_created ) ) {
		throw new RuntimeException( 'Actor fixture: ' . $ax_ml_created->get_error_message() );
	}
	$ax_ml_stored = axismundi_op_store_remote_object( $ax_ml_payload );
	ax_ml_assert( $ax_ml_results, 'the fixture Object is cached', ! is_wp_error( $ax_ml_stored ) );

	$ax_ml_model = array( 'object_uri' => $ax_ml_uri, 'content_html' => $ax_ml_payload['content'] );
	$ax_ml_html  = apply_filters( 'axismundi_op_object_content_html', wp_kses_post( $ax_ml_payload['content'] ), $ax_ml_model );
	$ax_ml_hub   = axismundi_actors_profile_hub_url( $ax_ml_created );

	ax_ml_assert( $ax_ml_results, 'a mention of a cached Actor links to this site\'s profile for them', '' !== $ax_ml_hub && str_contains( $ax_ml_html, 'href="' . esc_url( $ax_ml_hub ) . '"' ) );
	ax_ml_assert( $ax_ml_results, 'and no longer to the origin instance\'s profile page', ! str_contains( $ax_ml_html, 'href="' . $ax_ml_profile . '"' ) );
	/*
	 * A page on this site is the contract; which page is not. The hub is `/@handle@domain`
	 * once an acct address has been recorded for the Actor and `/actors/{uuid}` before
	 * then — this fixture writes the Actor directly and so takes the second form. Asserting
	 * the prettier shape would be testing how the fixture was built rather than what the
	 * rewrite promises.
	 */
	ax_ml_assert( $ax_ml_results, 'and that address is served by this site rather than the origin', str_starts_with( $ax_ml_hub, home_url( '/' ) ) );

	/*
	 * The declaration named a second Actor this site has never cached. Rewriting it would
	 * send a reader to a page we cannot render, so the author's link stands.
	 */
	ax_ml_assert( $ax_ml_results, 'a mention of an Actor we have never cached keeps its original link', str_contains( $ax_ml_html, 'href="https://stranger.test/@nobody"' ) );
	ax_ml_assert( $ax_ml_results, 'and an ordinary link is untouched', str_contains( $ax_ml_html, 'href="https://example.net/page"' ) );
	ax_ml_assert( $ax_ml_results, 'the visible handle is never rewritten, only where it points', str_contains( $ax_ml_html, '>@someone@' . $ax_ml_host . '</a>' ) );

	/*
	 * Senders differ about which address they link. Misskey writes the profile page;
	 * others write the Actor URI. Both are matched, or the rewrite would work against one
	 * implementation and silently not against the other.
	 */
	$ax_ml_by_uri = apply_filters(
		'axismundi_op_object_content_html',
		wp_kses_post( '<p><a href="' . $ax_ml_actor . '" class="mention">@someone@' . $ax_ml_host . '</a></p>' ),
		$ax_ml_model
	);
	ax_ml_assert( $ax_ml_results, 'an anchor written as the Actor URI is localized too', str_contains( $ax_ml_by_uri, 'href="' . esc_url( $ax_ml_hub ) . '"' ) );

	// Rendering must not create Actors, any more than it creates hashtag terms.
	$ax_ml_before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_actors_identities_table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- assertion.
	apply_filters( 'axismundi_op_object_content_html', wp_kses_post( $ax_ml_payload['content'] ), $ax_ml_model );
	ax_ml_assert( $ax_ml_results, 'rendering caches no Actor, because a page view is not a discovery', (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_actors_identities_table() ) === $ax_ml_before ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- assertion.
} catch ( Throwable $ax_ml_error ) {
	ax_ml_assert( $ax_ml_results, 'the mention localization suite ran to completion: ' . $ax_ml_error->getMessage(), false );
} finally {
	$wpdb->delete( axismundi_op_remote_objects_table(), array( 'object_uri_hash' => hash( 'sha256', $ax_ml_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	if ( $ax_ml_created instanceof Axismundi_Actor ) {
		$ax_ml_identity = $ax_ml_created->get_identity_id();
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $ax_ml_identity ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => $ax_ml_identity ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $ax_ml_identity ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
}

$ax_ml_failures = count( array_filter( $ax_ml_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ml_results ), $ax_ml_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ml_failures > 0 ? 1 : 0 );
}
exit( $ax_ml_failures > 0 ? 1 : 0 );
