<?php
/**
 * Optional Actors integration contracts (dev-only; dist-excluded).
 *
 * Emoji remains usable without Actors. When both are active, an observed authority
 * joins the existing NodeInfo queue and both administration surfaces link by host.
 * NodeInfo is intentionally not used as Emoji verification evidence.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-emoji.php';
require_once dirname( __DIR__, 2 ) . '/axismundi-actors/includes/admin.php';

global $wpdb;
$ax_emoji_actors_results = array();
$ax_emoji_actors_id      = 0;
$ax_emoji_actors_host    = 'example.com';

/** @param array<bool> $results @param string $label @param bool $condition @return void */
function ax_emoji_actors_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	axismundi_emoji_install();

	/*
	 * `axismundi_actors_ensure_remote_instance_host_cached()` returns early when the host is
	 * already in the instance ledger — correctly, since the queue exists to fill it once. So
	 * the assertion below is about a *first* observation, and it only means that if the host
	 * genuinely starts unknown. Any earlier run or audit that observed `example.com` leaves
	 * that row behind and turns this into a false failure, which is what made it flaky.
	 */
	$wpdb->delete( axismundi_actors_instances_table(), array( 'host_hash' => axismundi_actors_host_hash( $ax_emoji_actors_host ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture setup.
	$ax_emoji_actors_stale = wp_get_scheduled_event( 'axismundi_actors_cache_remote_instance', array( $ax_emoji_actors_host ) );
	if ( false !== $ax_emoji_actors_stale ) {
		wp_unschedule_event( $ax_emoji_actors_stale->timestamp, 'axismundi_actors_cache_remote_instance', array( $ax_emoji_actors_host ) );
	}

	$subject = 'https://example.com/notes/emoji-integration';
	axismundi_emoji_observe_payload(
		array(
			'id' => $subject,
			'tag' => array(
				array(
					'type' => 'Emoji',
					'name' => ':integration:',
					'id'   => 'https://example.com/emojis/integration',
					'icon' => array( 'url' => 'https://cdn.example.com/integration.png' ),
				),
			),
		),
		$subject
	);
	$row = axismundi_emoji_get( $ax_emoji_actors_host, 'integration' );
	if ( is_array( $row ) ) {
		$ax_emoji_actors_id = (int) $row['id'];
	}
	$scheduled = wp_get_scheduled_event( 'axismundi_actors_cache_remote_instance', array( $ax_emoji_actors_host ) );
	ax_emoji_actors_assert( $ax_emoji_actors_results, 'an observed emoji authority schedules Actors’ existing NodeInfo cache worker', is_array( $row ) && false !== $scheduled );
	ax_emoji_actors_assert( $ax_emoji_actors_results, 'an authority filter returns only that host’s emoji rows', 1 === count( axismundi_emoji_review_queue( 'all', 20, $ax_emoji_actors_host ) ) );

	ob_start();
	axismundi_emoji_render_authority_link( $ax_emoji_actors_host );
	$emoji_link = (string) ob_get_clean();
	ax_emoji_actors_assert( $ax_emoji_actors_results, 'Emoji authority links stay in the filtered Emoji catalogue', str_contains( $emoji_link, 'page=axismundi-emoji' ) && str_contains( $emoji_link, 'authority=example.com' ) );

	axismundi_actors_upsert_instance( $ax_emoji_actors_host, array( 'software_name' => 'fixture', 'software_version' => '1.0', 'fetch_status' => 'ok' ) );
	ob_start();
	axismundi_actors_render_instance_table( array( axismundi_actors_get_instance( $ax_emoji_actors_host ) ) );
	$instances = (string) ob_get_clean();
	ax_emoji_actors_assert( $ax_emoji_actors_results, 'Cached instances exposes the Emoji count and an authority-filtered review link', str_contains( $instances, '>Emojis<' ) && str_contains( $instances, 'authority=example.com' ) && str_contains( $instances, '>1<' ) );

	$actor_name_renderer = file_get_contents( WP_PLUGIN_DIR . '/axismundi-actors/blocks/actor-name/render.php' );
	ax_emoji_actors_assert(
		$ax_emoji_actors_results,
		'the standalone Actor Name block uses the same escaped display-name decoration seam as Actor Identity',
		is_string( $actor_name_renderer ) && str_contains( $actor_name_renderer, "'axismundi_actors_display_name_html'" ) && str_contains( $actor_name_renderer, 'esc_html( $axismundi_actor_name_value )' )
	);

	/*
	 * The biography seam.
	 *
	 * A name is escaped text and a summary is sanitized HTML, so this is a different
	 * substitution pass against the same declarations, and it is exercised end to end
	 * through the real filter rather than by looking for the hook in the source.
	 */
	$ax_bio_host    = 'example.org';
	$ax_bio_uri     = 'https://' . $ax_bio_host . '/users/someone';
	$ax_bio_hash    = hash( 'sha256', $ax_bio_host . '/declared' );
	$ax_bio_payload = array(
		'id'      => $ax_bio_uri,
		'type'    => 'Person',
		'summary' => '<p>hello :declared: and :undeclared: <code>:declared:</code></p>',
		'tag'     => array(
			array(
				'type' => 'Emoji',
				'name' => ':declared:',
				'id'   => 'https://' . $ax_bio_host . '/emojis/declared',
				'icon' => array( 'type' => 'Image', 'url' => 'https://cdn.' . $ax_bio_host . '/declared.png' ),
			),
		),
	);
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
		axismundi_emoji_table(),
		array(
			'emoji_authority' => $ax_bio_host,
			'shortcode_key'   => 'declared',
			'shortcode'       => ':declared:',
			'source_url'      => 'https://cdn.' . $ax_bio_host . '/declared.png',
			'scope'           => 'remote',
			'source_kind'     => 'remote',
			'review_status'   => 'approved',
			'content_hash'    => $ax_bio_hash,
			'cached_path'     => axismundi_emoji_cache_relative_path( $ax_bio_hash, 'png' ),
			'byte_size'       => 100,
			'first_seen_at'   => current_time( 'mysql', true ),
			'last_seen_at'    => current_time( 'mysql', true ),
		)
	);
	$ax_bio_emoji_id = (int) $wpdb->insert_id;
	$ax_bio_actor    = axismundi_actors_upsert_remote(
		array(
			'uri'                => $ax_bio_uri,
			'actor_type'         => 'Person',
			'preferred_username' => 'someone',
			'display_name'       => 'Someone',
			'summary'            => $ax_bio_payload['summary'],
			'profile_url'        => $ax_bio_uri,
			'payload'            => $ax_bio_payload,
			'endpoints'          => array(
				'inbox'  => 'https://' . $ax_bio_host . '/users/someone/inbox',
				'outbox' => 'https://' . $ax_bio_host . '/users/someone/outbox',
			),
		)
	);
	if ( is_wp_error( $ax_bio_actor ) ) {
		throw new RuntimeException( 'remote Actor fixture: ' . $ax_bio_actor->get_error_message() );
	}

	$ax_bio_html = (string) apply_filters(
		'axismundi_actors_summary_html',
		wp_kses_post( wpautop( $ax_bio_payload['summary'] ) ),
		$ax_bio_actor
	);
	ax_emoji_actors_assert( $ax_emoji_actors_results, 'a shortcode the Actor declared becomes its custom emoji in the biography', str_contains( $ax_bio_html, 'alt=":declared:"' ) );
	ax_emoji_actors_assert( $ax_emoji_actors_results, 'an undeclared shortcode in a biography is left for whatever layer owns it', str_contains( $ax_bio_html, ':undeclared:' ) && ! str_contains( $ax_bio_html, 'alt=":undeclared:"' ) );
	ax_emoji_actors_assert( $ax_emoji_actors_results, 'a shortcode inside <code> stays text, because a biography may quote one as an example', str_contains( $ax_bio_html, '<code>:declared:</code>' ) );

	/*
	 * Decoration still needs an actual Actor declaration. Passing no subject must not
	 * turn an arbitrary local-looking shortcode into an image; local Actors now supply
	 * their declarations through their own projected outbound `tag[]`.
	 */
	$ax_bio_local = axismundi_emoji_decorate_summary( '<p>a local :declared: b</p>', null );
	ax_emoji_actors_assert( $ax_emoji_actors_results, 'a biography without an Actor declaration remains text rather than globally claiming a shortcode', ! str_contains( $ax_bio_local, 'alt=' ) );

	$ax_bio_renderer = file_get_contents( WP_PLUGIN_DIR . '/axismundi-actors/blocks/actor-biography/render.php' );
	ax_emoji_actors_assert(
		$ax_emoji_actors_results,
		'the biography block sanitizes before it offers the seam, so decoration cannot be stripped back out',
		is_string( $ax_bio_renderer ) && str_contains( $ax_bio_renderer, "'axismundi_actors_summary_html'" ) && str_contains( $ax_bio_renderer, 'wp_kses_post( wpautop(' )
	);
} catch ( Throwable $error ) {
	ax_emoji_actors_assert( $ax_emoji_actors_results, 'the optional Actors integration suite ran to completion: ' . $error->getMessage(), false );
} finally {
	if ( $ax_emoji_actors_id > 0 ) {
		$wpdb->delete( axismundi_emoji_references_table(), array( 'emoji_id' => $ax_emoji_actors_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_emoji_table(), array( 'id' => $ax_emoji_actors_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	$wpdb->delete( axismundi_actors_instances_table(), array( 'host_hash' => axismundi_actors_host_hash( $ax_emoji_actors_host ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->delete( axismundi_emoji_table(), array( 'emoji_authority' => 'example.org' ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	// Only ever this fixture's own ids. A host-wide sweep here would be a live-data hazard.
	if ( isset( $ax_bio_actor ) && $ax_bio_actor instanceof Axismundi_Actor ) {
		$ax_bio_identity = $ax_bio_actor->get_identity_id();
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $ax_bio_identity ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => $ax_bio_identity ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $ax_bio_identity ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	$wpdb->delete( axismundi_actors_instances_table(), array( 'host_hash' => axismundi_actors_host_hash( 'example.org' ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$event = wp_get_scheduled_event( 'axismundi_actors_cache_remote_instance', array( $ax_emoji_actors_host ) );
	if ( false !== $event ) {
		wp_unschedule_event( $event->timestamp, 'axismundi_actors_cache_remote_instance', array( $ax_emoji_actors_host ) );
	}
}

$failures = count( array_filter( $ax_emoji_actors_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_emoji_actors_results ), $failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $failures > 0 ? 1 : 0 );
}
exit( $failures > 0 ? 1 : 0 );
