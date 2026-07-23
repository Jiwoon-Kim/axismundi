<?php
/**
 * Actor Activity feed projection regression (dev-only).
 *
 * The feed row is an Activity (Activities owns selection and verb framing); the
 * card content is an Object resolved local-or-remote and rendered by Object
 * Projections. This audit exercises both seams with a cached remote object.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_feed_results    = array();
$ax_feed_user_id    = 0;
$ax_feed_identity   = 0;
$ax_feed_identities = array();
$ax_feed_activities = array();
$ax_feed_remote     = array();

/** @param bool[] $results Results. */
function ax_feed_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$login           = 'ax_feed_' . strtolower( wp_generate_password( 8, false, false ) );
	$ax_feed_user_id = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$actor           = axismundi_actors_ensure_for_user( $ax_feed_user_id );
	if ( $actor instanceof Axismundi_Actor ) {
		$ax_feed_identity     = $actor->get_identity_id();
		$ax_feed_identities[] = $ax_feed_identity;
		axismundi_actors_register_handle( $ax_feed_identity, $login );
		axismundi_actors_set_status( $ax_feed_identity, 'public' );
		$actor = axismundi_actors_get_for_user( $ax_feed_user_id );
	}

	ax_feed_assert( $ax_feed_results, 'fixture creates a local public Actor', $actor instanceof Axismundi_Actor && $actor->is_local() );
	if ( ! $actor instanceof Axismundi_Actor ) {
		throw new RuntimeException( 'Fixture Actor was not created.' );
	}
	$actor_uri  = $actor->get_uri();
	$public_uri = 'https://www.w3.org/ns/activitystreams#Public';

	// A cached remote Object authored by a remote Actor other than the profile owner.
	$remote_slug      = strtolower( wp_generate_password( 8, false, false ) );
	$remote_actor_uri = 'https://example.com/users/' . $remote_slug;
	$remote_actor     = axismundi_actors_upsert_remote(
		array(
			'uri'                => $remote_actor_uri,
			'actor_type'         => 'Person',
			'preferred_username' => 'owner_' . $remote_slug,
			'display_name'       => 'Remote owner',
			'profile_url'        => $remote_actor_uri,
			'endpoints'          => array( 'inbox' => $remote_actor_uri . '/inbox', 'outbox' => $remote_actor_uri . '/outbox' ),
			'payload'            => array( 'id' => $remote_actor_uri, 'type' => 'Person', 'preferredUsername' => 'owner_' . $remote_slug, 'inbox' => $remote_actor_uri . '/inbox', 'outbox' => $remote_actor_uri . '/outbox' ),
		)
	);
	if ( $remote_actor instanceof Axismundi_Actor ) {
		$ax_feed_identities[] = $remote_actor->get_identity_id();
	}
	$remote_note_uri   = 'https://example.com/notes/' . wp_generate_uuid4();
	$observed_note_uri = 'https://example.com/notes/' . wp_generate_uuid4();
	$unanchored_note_uri = 'https://example.com/notes/' . wp_generate_uuid4();
	$private_note_uri  = 'https://example.com/notes/' . wp_generate_uuid4();
	$tomb_note_uri     = 'https://example.com/notes/' . wp_generate_uuid4();
	$ax_feed_remote    = array( $remote_note_uri, $observed_note_uri, $unanchored_note_uri, $private_note_uri, $tomb_note_uri );
	$stored_active    = axismundi_op_remote_object_store( array( 'id' => $remote_note_uri, 'type' => 'Note', 'attributedTo' => $remote_actor_uri, 'content' => 'Boosted remote note body.', 'to' => array( $public_uri ) ) );
	$stored_observed  = axismundi_op_remote_object_store( array( 'id' => $observed_note_uri, 'type' => 'Note', 'attributedTo' => $remote_actor_uri, 'content' => 'Fetched reply parent body.', 'published' => '2026-07-22T00:00:00Z', 'to' => array( $public_uri ) ) );
	$stored_unanchored = axismundi_op_remote_object_store( array( 'id' => $unanchored_note_uri, 'type' => 'Note', 'attributedTo' => $remote_actor_uri, 'content' => 'Still cache-only parent body.', 'published' => '2026-07-21T00:00:00Z', 'to' => array( $public_uri ) ) );
	$stored_private   = axismundi_op_remote_object_store( array( 'id' => $private_note_uri, 'type' => 'Note', 'attributedTo' => $remote_actor_uri, 'content' => 'Followers-only cached parent.', 'to' => array( $remote_actor_uri . '/followers' ) ) );
	$stored_tomb      = axismundi_op_remote_object_store( array( 'id' => $tomb_note_uri, 'type' => 'Note', 'attributedTo' => $remote_actor_uri, 'content' => 'Tombstoned remote note.', 'to' => array( $public_uri ) ) );
	ax_feed_assert( $ax_feed_results, 'fixture caches public and followers-only remote Objects for one Actor', is_array( $stored_active ) && is_array( $stored_observed ) && is_array( $stored_unanchored ) && is_array( $stored_private ) && is_array( $stored_tomb ) && $remote_actor instanceof Axismundi_Actor );

	// --- Object rendering (Object Projections integration on the feed filter) ---
	$announce_item = array( 'type' => 'Announce', 'actor_uri' => $actor_uri, 'object_uri' => $remote_note_uri );
	$announce_html = (string) apply_filters( 'axismundi_act_actor_feed_object_html', '', $announce_item );
	ax_feed_assert( $ax_feed_results, 'a boosted cached remote Object renders its object card', '' !== $announce_html && false !== strpos( $announce_html, 'Boosted remote note body.' ) && false !== strpos( $announce_html, 'axismundi-object' ) );

	$spoof_item = array( 'type' => 'Create', 'actor_uri' => $actor_uri, 'object_uri' => $remote_note_uri );
	ax_feed_assert( $ax_feed_results, 'a Create whose object author is not the acting Actor renders nothing', '' === (string) apply_filters( 'axismundi_act_actor_feed_object_html', '', $spoof_item ) );

	$missing_item = array( 'type' => 'Announce', 'actor_uri' => $actor_uri, 'object_uri' => 'https://example.com/notes/' . wp_generate_uuid4() );
	$missing_object_html = (string) apply_filters( 'axismundi_act_actor_feed_object_html', '', $missing_item );
	$missing_fallback_html = '' === $missing_object_html ? (string) apply_filters( 'axismundi_act_actor_feed_missing_object_html', '', $missing_item ) : $missing_object_html;
	ax_feed_assert( $ax_feed_results, 'an uncached public Object URI renders a safe external reference and queues deferred acquisition rather than erasing its Announce', false !== strpos( $missing_fallback_html, 'axismundi-object-card--external-reference' ) && false !== strpos( $missing_fallback_html, 'example.com' ) && false !== strpos( $missing_fallback_html, 'target="_blank"' ) && false !== wp_next_scheduled( 'axismundi_op_fetch_announced_object', array( $missing_item['object_uri'] ) ) );

	$wpdb->update( axismundi_op_remote_objects_table(), array( 'object_status' => 'tombstone' ), array( 'object_uri_hash' => hash( 'sha256', $tomb_note_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$tomb_item = array( 'type' => 'Announce', 'actor_uri' => $actor_uri, 'object_uri' => $tomb_note_uri );
	$tomb_object_html = (string) apply_filters( 'axismundi_act_actor_feed_object_html', '', $tomb_item );
	$tomb_fallback_html = '' === $tomb_object_html ? (string) apply_filters( 'axismundi_act_actor_feed_missing_object_html', '', $tomb_item ) : $tomb_object_html;
	ax_feed_assert( $ax_feed_results, 'a tombstoned object remains hidden rather than becoming an external reference', '' === $tomb_fallback_html );

	// --- Selection (Activities ledger query) ---
	$create_uri   = home_url( '/activities/' . wp_generate_uuid4() . '/' );
	$private_uri  = home_url( '/activities/' . wp_generate_uuid4() . '/' );
	$like_uri     = home_url( '/activities/' . wp_generate_uuid4() . '/' );
	$update_uri   = home_url( '/activities/' . wp_generate_uuid4() . '/' );
	$remote_create_uri = 'https://example.com/activities/' . wp_generate_uuid4();
	$remote_announce_uri = 'https://example.com/activities/' . wp_generate_uuid4();
	$remote_uncached_announce_uri = 'https://example.com/activities/' . wp_generate_uuid4();
	$remote_uncached_object_uri = 'https://unresolved.example/objects/' . wp_generate_uuid4();
	$note_uri     = home_url( '/notes/' . wp_generate_uuid4() . '/' );
	$ax_feed_activities = array( $create_uri, $private_uri, $like_uri, $update_uri, $remote_create_uri, $remote_announce_uri, $remote_uncached_announce_uri );

	axismundi_act_record_activity( array( 'id' => $create_uri, 'type' => 'Create', 'actor' => $actor_uri, 'object' => array( 'id' => $note_uri, 'type' => 'Note', 'content' => '<p>Authored.</p>' ), 'to' => array( $public_uri ) ), 'outbound' );
	axismundi_act_record_activity( array( 'id' => $private_uri, 'type' => 'Create', 'actor' => $actor_uri, 'object' => array( 'id' => home_url( '/notes/' . wp_generate_uuid4() . '/' ), 'type' => 'Note', 'content' => '<p>Followers only.</p>' ), 'to' => array( $actor_uri . '/followers' ) ), 'outbound' );
	axismundi_act_record_activity( array( 'id' => $like_uri, 'type' => 'Like', 'actor' => $actor_uri, 'object' => $note_uri, 'to' => array( $public_uri ) ), 'outbound' );
	axismundi_act_record_activity( array( 'id' => $update_uri, 'type' => 'Update', 'actor' => $actor_uri, 'object' => array( 'id' => $note_uri, 'type' => 'Note', 'content' => '<p>Edited.</p>' ), 'to' => array( $public_uri ) ), 'outbound' );
	axismundi_act_record_activity( array( 'id' => $remote_create_uri, 'type' => 'Create', 'actor' => $remote_actor_uri, 'object' => $remote_note_uri, 'to' => array( $public_uri ) ), 'inbound' );
	axismundi_act_record_activity( array( 'id' => $remote_announce_uri, 'type' => 'Announce', 'actor' => $remote_actor_uri, 'object' => $observed_note_uri, 'to' => array( $public_uri ) ), 'inbound' );
	axismundi_act_record_activity( array( 'id' => $remote_uncached_announce_uri, 'type' => 'Announce', 'actor' => $remote_actor_uri, 'object' => $remote_uncached_object_uri, 'to' => array( $public_uri ) ), 'inbound' );

	$announce = axismundi_act_announce_object( $actor, $remote_note_uri, $remote_actor_uri );
	$announce_uri = $announce instanceof Axismundi_Activity ? $announce->get_uri() : '';
	if ( '' !== $announce_uri ) {
		$ax_feed_activities[] = $announce_uri;
	}

	$items   = axismundi_act_actor_feed_items( $actor, 20 );
	$ids     = array_column( $items, 'id' );
	$types   = array_values( array_unique( array_column( $items, 'type' ) ) );
	$only_ca = array() === array_diff( $types, array( 'Create', 'Announce' ) );
	ax_feed_assert(
		$ax_feed_results,
		'the feed selects only public Create and Announce (Update, Like, and followers-only excluded)',
		$only_ca
			&& in_array( $create_uri, $ids, true )
			&& in_array( $announce_uri, $ids, true )
			&& ! in_array( $private_uri, $ids, true )
			&& ! in_array( $like_uri, $ids, true )
			&& ! in_array( $update_uri, $ids, true )
	);
	$remote_items    = axismundi_act_actor_feed_items( $remote_actor, 20 );
	$remote_ids      = array_column( $remote_items, 'id' );
	$observed_items  = array_values( array_filter( $remote_items, static fn( array $item ) : bool => 'observed_object' === (string) ( $item['kind'] ?? '' ) ) );
	$observed_item   = $observed_items[0] ?? array();
	ax_feed_assert( $ax_feed_results, 'a public cached remote Actor reads its inbound Create rows and resolves the cached Object through the same feed contract', in_array( $remote_create_uri, $remote_ids, true ) && false !== strpos( (string) apply_filters( 'axismundi_act_actor_feed_object_html', '', array_values( array_filter( $remote_items, static fn( array $item ) : bool => $remote_create_uri === (string) ( $item['id'] ?? '' ) ) )[0] ?? array() ), 'Boosted remote note body.' ) );
	ax_feed_assert( $ax_feed_results, 'a public cache-only Object such as a fetched remote inReplyTo parent appears as one observed row without manufacturing a Create Activity', 1 === count( $observed_items ) && $unanchored_note_uri === (string) ( $observed_item['object_uri'] ?? '' ) && false !== strpos( (string) apply_filters( 'axismundi_act_actor_feed_object_html', '', $observed_item ), 'Still cache-only parent body.' ) && ! in_array( 'observed:' . hash( 'sha256', $private_note_uri ), $remote_ids, true ) );
	$announced_object_rows = array_values( array_filter( $remote_items, static fn( array $item ) : bool => $observed_note_uri === (string) ( $item['object_uri'] ?? '' ) ) );
	ax_feed_assert( $ax_feed_results, 'an Object already framed by an Announce is not also added as an observed fallback card', 1 === count( $announced_object_rows ) && $remote_announce_uri === (string) ( $announced_object_rows[0]['id'] ?? '' ) && 'activity' === (string) ( $announced_object_rows[0]['kind'] ?? '' ) );
	$previous_current_actor = $GLOBALS['axismundi_actors_current_actor'] ?? null;
	$GLOBALS['axismundi_actors_current_actor'] = $remote_actor;
	$remote_feed_markup = axismundi_act_render_actor_activity_feed();
	$GLOBALS['axismundi_actors_current_actor'] = $previous_current_actor;
	ax_feed_assert( $ax_feed_results, 'a public remote Actor profile renders an uncached Announce as a Boosted external-object row', false !== strpos( $remote_feed_markup, 'axismundi-activity-feed__boost' ) && false !== strpos( $remote_feed_markup, 'axismundi-object-card--external-reference' ) && false !== strpos( $remote_feed_markup, 'unresolved.example' ) );

	$undo = axismundi_act_unannounce_object( $actor, $remote_note_uri );
	if ( $undo instanceof Axismundi_Activity ) {
		$ax_feed_activities[] = $undo->get_uri();
	}
	$after_ids = array_column( axismundi_act_actor_feed_items( $actor, 20 ), 'id' );
	ax_feed_assert( $ax_feed_results, 'an undone Announce drops out of the feed while the authored Create stays', ! in_array( $announce_uri, $after_ids, true ) && in_array( $create_uri, $after_ids, true ) );

	axismundi_actors_set_status( $ax_feed_identity, 'internal' );
	ax_feed_assert( $ax_feed_results, 'a non-public Actor exposes no public Activity feed', array() === axismundi_act_actor_feed_items( $actor, 20 ) );
} finally {
	foreach ( $ax_feed_activities as $activity_uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $activity_uri ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture cleanup.
	}
	foreach ( $ax_feed_remote as $uri ) {
		$wpdb->delete( axismundi_op_remote_objects_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture cleanup.
		if ( function_exists( 'axismundi_op_object_leases_table' ) ) {
			$wpdb->delete( axismundi_op_object_leases_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture cleanup.
		}
	}
	if ( isset( $missing_item['object_uri'] ) ) {
		wp_clear_scheduled_hook( 'axismundi_op_fetch_announced_object', array( $missing_item['object_uri'] ) );
	}
	foreach ( array_unique( array_filter( $ax_feed_identities ) ) as $iid ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $iid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture cleanup.
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $iid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $iid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture cleanup.
	}
	if ( $ax_feed_user_id > 0 && get_userdata( $ax_feed_user_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $ax_feed_user_id );
	}
}

$ax_feed_failures = count( array_filter( $ax_feed_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_feed_results ), $ax_feed_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_feed_failures > 0 ? 1 : 0 );
}
exit( $ax_feed_failures > 0 ? 1 : 0 );
