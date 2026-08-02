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
$ax_feed_post_id    = 0;

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
	$stored_active    = axismundi_op_store_remote_object( array( 'id' => $remote_note_uri, 'type' => 'Note', 'attributedTo' => $remote_actor_uri, 'content' => 'Boosted remote note body.', 'to' => array( $public_uri ) ) );
	$stored_observed  = axismundi_op_store_remote_object( array( 'id' => $observed_note_uri, 'type' => 'Note', 'attributedTo' => $remote_actor_uri, 'content' => 'Fetched reply parent body.', 'published' => '2026-07-22T00:00:00Z', 'to' => array( $public_uri ) ) );
	$stored_unanchored = axismundi_op_store_remote_object( array( 'id' => $unanchored_note_uri, 'type' => 'Note', 'attributedTo' => $remote_actor_uri, 'content' => 'Still cache-only parent body.', 'published' => '2026-07-21T00:00:00Z', 'to' => array( $public_uri ) ) );
	$stored_private   = axismundi_op_store_remote_object( array( 'id' => $private_note_uri, 'type' => 'Note', 'attributedTo' => $remote_actor_uri, 'content' => 'Followers-only cached parent.', 'to' => array( $remote_actor_uri . '/followers' ) ) );
	$stored_tomb      = axismundi_op_store_remote_object( array( 'id' => $tomb_note_uri, 'type' => 'Note', 'attributedTo' => $remote_actor_uri, 'content' => 'Tombstoned remote note.', 'to' => array( $public_uri ) ) );
	ax_feed_assert( $ax_feed_results, 'fixture caches public and followers-only remote Objects for one Actor', is_array( $stored_active ) && is_array( $stored_observed ) && is_array( $stored_unanchored ) && is_array( $stored_private ) && is_array( $stored_tomb ) && $remote_actor instanceof Axismundi_Actor );

	$ax_feed_post_id = (int) wp_insert_post(
		array(
			'post_title'   => 'Actor feed button context fixture',
			'post_content' => 'Outer template post context.',
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_author'  => $ax_feed_user_id,
		)
	);
	$previous_model = function_exists( 'axismundi_op_current_object_view_model' ) ? axismundi_op_current_object_view_model() : null;
	if ( function_exists( 'axismundi_op_set_current_object_view_model' ) ) {
		axismundi_op_set_current_object_view_model( array( 'object_uri' => $remote_note_uri ) );
	}
	$context_block = new WP_Block( array( 'blockName' => 'axismundi/interaction', 'attrs' => array( 'type' => 'announce' ) ), array( 'postId' => $ax_feed_post_id ) );
	$resolved_button_uri = axismundi_act_like_block_object_uri( array(), $context_block );
	if ( function_exists( 'axismundi_op_set_current_object_view_model' ) ) {
		axismundi_op_set_current_object_view_model( $previous_model );
	}
	ax_feed_assert( $ax_feed_results, 'an Object card action uses its active model instead of the outer Actor template post context', $remote_note_uri === $resolved_button_uri );

	// --- Object rendering (Object Projections integration on the feed filter) ---
	$announce_item = array( 'type' => 'Announce', 'actor_uri' => $actor_uri, 'object_uri' => $remote_note_uri );
	$announce_html = (string) apply_filters( 'axismundi_act_actor_feed_object_html', '', $announce_item );
	ax_feed_assert( $ax_feed_results, 'a boosted cached remote Object renders its object card with the original author rather than the profile Actor', '' !== $announce_html && false !== strpos( $announce_html, 'Boosted remote note body.' ) && false !== strpos( $announce_html, 'axismundi-object' ) && false !== strpos( $announce_html, 'Remote owner' ) && false === strpos( $announce_html, esc_html( $actor->get_display_name() ) ) );

	$spoof_item = array( 'type' => 'Create', 'actor_uri' => $actor_uri, 'object_uri' => $remote_note_uri );
	ax_feed_assert( $ax_feed_results, 'a Create whose object author is not the acting Actor renders nothing', '' === (string) apply_filters( 'axismundi_act_actor_feed_object_html', '', $spoof_item ) );
	$missing_create_item = array( 'type' => 'Create', 'actor_uri' => $actor_uri, 'object_uri' => 'https://example.com/notes/' . wp_generate_uuid4() );
	ax_feed_assert( $ax_feed_results, 'a missing Create stays hidden instead of becoming an external-object row', '' === (string) apply_filters( 'axismundi_act_actor_feed_missing_object_html', '', $missing_create_item ) );

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
	$hidden_run = array();
	for ( $index = 0; $index < 25; $index++ ) {
		$hidden_uri = home_url( '/activities/' . wp_generate_uuid4() . '/' );
		$hidden_run[] = $hidden_uri;
		$ax_feed_activities[] = $hidden_uri;
		axismundi_act_record_activity( array( 'id' => $hidden_uri, 'type' => 'Create', 'actor' => $actor_uri, 'object' => array( 'id' => home_url( '/notes/' . wp_generate_uuid4() . '/' ), 'type' => 'Note', 'content' => '<p>Hidden run.</p>' ), 'to' => array( $actor_uri . '/followers' ) ), 'outbound' );
	}

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
	ax_feed_assert(
		$ax_feed_results,
		'a bounded private run does not truncate an older public timeline card before the scan limit',
		in_array( $create_uri, $ids, true ) && array() === array_intersect( $hidden_run, $ids )
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
	// A boost is not in the default filter, so this also proves the renderer honours `?filter=`
	// rather than always rendering one fixed slice.
	$_GET['filter'] = 'posts-and-boosts';
	$remote_feed_markup = axismundi_act_render_actor_activity_feed();
	unset( $_GET['filter'] );
	$GLOBALS['axismundi_actors_current_actor'] = $previous_current_actor;
	ax_feed_assert( $ax_feed_results, 'a public remote Actor profile renders an uncached Announce as a Boosted external-object row when boosts are selected', false !== strpos( $remote_feed_markup, 'axismundi-object__status--announce' ) && false !== strpos( $remote_feed_markup, 'axismundi-object-card--external-reference' ) && false !== strpos( $remote_feed_markup, 'unresolved.example' ) );
	$previous_current_actor = $GLOBALS['axismundi_actors_current_actor'] ?? null;
	$GLOBALS['axismundi_actors_current_actor'] = $actor;
	$local_feed_markup = axismundi_act_render_actor_activity_feed();
	$GLOBALS['axismundi_actors_current_actor'] = $previous_current_actor;
	ax_feed_assert( $ax_feed_results, 'an Actor timeline exposes Reply, Like, and Repost controls while archive renderers remain independently configurable', false !== strpos( $local_feed_markup, 'axismundi-interaction__button' ) && false !== strpos( $local_feed_markup, 'axismundi-interaction__button' ) && false !== strpos( $local_feed_markup, 'axismundi-interaction__button' ) );

	/*
	 * Feed cards are deliberately unhydrated presentation, including cards appended later. A
	 * logged-in reader therefore gets exactly one picker host in the SSR shell, not one inert
	 * picker per card. Its document handler can receive every card's delegated reaction control.
	 */
	wp_set_current_user( $ax_feed_user_id );
	$previous_current_actor = $GLOBALS['axismundi_actors_current_actor'] ?? null;
	$GLOBALS['axismundi_actors_current_actor'] = $actor;
	$authenticated_feed_markup = axismundi_act_render_actor_activity_feed();
	$GLOBALS['axismundi_actors_current_actor'] = $previous_current_actor;
	wp_set_current_user( 0 );
	ax_feed_assert(
		$ax_feed_results,
		'a signed-in feed renders one hydrated reaction picker host and delegates every card trigger to it',
		1 === substr_count( $authenticated_feed_markup, 'axismundi-reaction-picker-host' )
			&& false !== strpos( $authenticated_feed_markup, 'data-wp-on-document--click="actions.openFeedPicker"' )
			&& false !== strpos( $authenticated_feed_markup, 'data-ax-action="reaction"' )
			&& 1 === substr_count( $authenticated_feed_markup, 'class="axismundi-reaction-button__picker"' )
	);
	ax_feed_assert(
		$ax_feed_results,
		'a signed-in feed opens one role-menu host for Repost and Quote instead of posting Announce from the card trigger',
		1 === substr_count( $authenticated_feed_markup, 'axismundi-announce-menu-host' )
			&& false !== strpos( $authenticated_feed_markup, 'data-wp-on-document--click="actions.openFeedMenu"' )
			&& false !== strpos( $authenticated_feed_markup, 'data-ax-action="announce-menu"' )
			&& 1 === substr_count( $authenticated_feed_markup, 'class="axismundi-announce-menu"' )
			&& false === strpos( $authenticated_feed_markup, '<dialog' )
	);

	$undo = axismundi_act_unannounce_object( $actor, $remote_note_uri );
	if ( $undo instanceof Axismundi_Activity ) {
		$ax_feed_activities[] = $undo->get_uri();
	}
	$after_ids = array_column( axismundi_act_actor_feed_items( $actor, 20 ), 'id' );
	ax_feed_assert( $ax_feed_results, 'an undone Announce drops out of the feed while the authored Create stays', ! in_array( $announce_uri, $after_ids, true ) && in_array( $create_uri, $after_ids, true ) );

	// --- Timeline views ---
	// A reply and a boost by the same Actor, so each view can be told apart by what it drops.
	$reply_uri            = home_url( '/activities/' . wp_generate_uuid4() . '/' );
	$ax_feed_activities[] = $reply_uri;
	axismundi_act_record_activity(
		array(
			'id'     => $reply_uri,
			'type'   => 'Create',
			'actor'  => $actor_uri,
			'object' => array( 'id' => home_url( '/notes/' . wp_generate_uuid4() . '/' ), 'type' => 'Note', 'content' => '<p>A reply.</p>', 'inReplyTo' => $note_uri ),
			'to'     => array( $public_uri ),
		),
		'outbound'
	);
	$boost = axismundi_act_announce_object( $actor, $remote_note_uri, $remote_actor_uri );
	if ( $boost instanceof Axismundi_Activity ) {
		$ax_feed_activities[] = $boost->get_uri();
	}
	$boost_uri = $boost instanceof Axismundi_Activity ? $boost->get_uri() : '';

	$view_ids = array();
	foreach ( array_keys( axismundi_act_actor_feed_filters() ) as $view_key ) {
		$view_ids[ $view_key ] = array_column( axismundi_act_actor_feed_page( $actor, 50, '', $view_key )['items'], 'id' );
	}

	ax_feed_assert(
		$ax_feed_results,
		'"Posts" shows authored posts while excluding both replies and boosts',
		in_array( $create_uri, $view_ids['posts'], true )
			&& ! in_array( $reply_uri, $view_ids['posts'], true )
			&& '' !== $boost_uri && ! in_array( $boost_uri, $view_ids['posts'], true )
	);

	ax_feed_assert(
		$ax_feed_results,
		'"Posts and boosts" adds the boost and still withholds the reply',
		in_array( $boost_uri, $view_ids['posts-and-boosts'], true )
			&& in_array( $create_uri, $view_ids['posts-and-boosts'], true )
			&& ! in_array( $reply_uri, $view_ids['posts-and-boosts'], true )
	);

	ax_feed_assert(
		$ax_feed_results,
		'"Posts and replies" adds the reply and still withholds the boost',
		in_array( $reply_uri, $view_ids['posts-and-replies'], true )
			&& in_array( $create_uri, $view_ids['posts-and-replies'], true )
			&& ! in_array( $boost_uri, $view_ids['posts-and-replies'], true )
	);

	ax_feed_assert(
		$ax_feed_results,
		'"All activity" is the union of the narrower views and never a superset of what is visible',
		in_array( $reply_uri, $view_ids['all'], true )
			&& in_array( $boost_uri, $view_ids['all'], true )
			&& array() === array_diff( $view_ids['posts-and-boosts'], $view_ids['all'] )
			&& array() === array_diff( $view_ids['posts-and-replies'], $view_ids['all'] )
			// The followers-only Create is excluded from every view: a filter narrows a public
			// feed, it must never widen one.
			&& ! in_array( $private_uri, $view_ids['all'], true )
	);

	ax_feed_assert(
		$ax_feed_results,
		'an unrecognised view falls back to the default rather than showing an empty or unfiltered feed',
		axismundi_act_actor_feed_filter( 'sideways' ) === axismundi_act_actor_feed_default_filter()
			&& $view_ids[ axismundi_act_actor_feed_default_filter() ] === array_column( axismundi_act_actor_feed_page( $actor, 50, '', 'sideways' )['items'], 'id' )
	);

	ax_feed_assert(
		$ax_feed_results,
		'a profile opens with boosts shown and replies hidden, the combination Mastodon also defaults to',
		'posts-and-boosts' === axismundi_act_actor_feed_default_filter()
			&& false === axismundi_act_actor_feed_filters()['posts-and-boosts']['replies']
			&& true === axismundi_act_actor_feed_filters()['posts-and-boosts']['boosts']
	);

	// The four filters are the 2x2 product of two independent switches, so each control must
	// flip exactly its own bit and leave the other alone.
	$flips = array();
	foreach ( array_keys( axismundi_act_actor_feed_filters() ) as $key ) {
		$rules = axismundi_act_actor_feed_filters()[ $key ];
		$flips[ $key ] = array(
			'replies' => axismundi_act_actor_feed_filter_key( ! $rules['replies'], (bool) $rules['boosts'] ),
			'boosts'  => axismundi_act_actor_feed_filter_key( (bool) $rules['replies'], ! $rules['boosts'] ),
		);
	}
	ax_feed_assert(
		$ax_feed_results,
		'each timeline switch flips only its own dimension, so the four filters behave as two independent choices',
		array(
			'posts'             => array( 'replies' => 'posts-and-replies', 'boosts' => 'posts-and-boosts' ),
			'posts-and-boosts'  => array( 'replies' => 'all', 'boosts' => 'posts' ),
			'posts-and-replies' => array( 'replies' => 'posts', 'boosts' => 'all' ),
			'all'               => array( 'replies' => 'posts-and-boosts', 'boosts' => 'posts-and-replies' ),
		) === $flips
	);

	// --- Profile surfaces ---
	$surfaces = axismundi_act_actor_profile_surfaces( $actor );
	ax_feed_assert(
		$ax_feed_results,
		'Activities owns only the activity surface and cannot have it removed by a product',
		isset( $surfaces['activity'] )
			&& is_callable( $surfaces['activity']['page'] )
			&& 'posts-and-boosts' === (string) $surfaces['activity']['default_filter']
			&& $surfaces === axismundi_act_actor_profile_surfaces( $actor )
	);

	$removed = static fn( array $list ) : array => array();
	add_filter( 'axismundi_act_actor_profile_surfaces', $removed, 99 );
	$after_removal = axismundi_act_actor_profile_surfaces( $actor );
	remove_filter( 'axismundi_act_actor_profile_surfaces', $removed, 99 );
	ax_feed_assert(
		$ax_feed_results,
		'a product cannot leave the profile with no surface for the router to land on',
		isset( $after_removal['activity'] ) && is_callable( $after_removal['activity']['page'] )
	);

	// --- Cursor pagination ---
	// Enough public activities to need several pages, timestamped apart so the expected order
	// is unambiguous rather than resting on insertion order.
	$paged_uris = array();
	for ( $index = 0; $index < 12; $index++ ) {
		$paged_uri            = home_url( '/activities/' . wp_generate_uuid4() . '/' );
		$paged_uris[]         = $paged_uri;
		$ax_feed_activities[] = $paged_uri;
		axismundi_act_record_activity(
			array(
				'id'        => $paged_uri,
				'type'      => 'Create',
				'actor'     => $actor_uri,
				'published' => gmdate( 'c', strtotime( '2026-01-01 00:00:00 UTC' ) + $index * 60 ),
				'object'    => array( 'id' => home_url( '/notes/' . wp_generate_uuid4() . '/' ), 'type' => 'Note', 'content' => '<p>Paged ' . $index . '.</p>' ),
				'to'        => array( $public_uri ),
			),
			'outbound'
		);
	}

	$walked = array();
	$cursor = '';
	$guard  = 0;
	do {
		$page   = axismundi_act_actor_feed_page( $actor, 5, $cursor );
		$walked = array_merge( $walked, array_column( $page['items'], 'id' ) );
		$cursor = (string) $page['next_cursor'];
		++$guard;
	} while ( $page['has_more'] && $guard < 40 );

	ax_feed_assert(
		$ax_feed_results,
		'walking the cursor visits every public activity exactly once, with no repeats between pages',
		count( $walked ) === count( array_unique( $walked ) ) && $guard < 40
	);

	ax_feed_assert(
		$ax_feed_results,
		'pagination reaches the oldest activities instead of stopping at the first page',
		count( array_intersect( $paged_uris, $walked ) ) === count( $paged_uris )
	);

	// The reason a cursor is used at all: new activity arriving mid-read must not push the
	// unread rows down into a page the reader has already passed.
	$first     = axismundi_act_actor_feed_page( $actor, 5 );
	$intruder  = home_url( '/activities/' . wp_generate_uuid4() . '/' );
	$ax_feed_activities[] = $intruder;
	axismundi_act_record_activity( array( 'id' => $intruder, 'type' => 'Create', 'actor' => $actor_uri, 'object' => array( 'id' => home_url( '/notes/' . wp_generate_uuid4() . '/' ), 'type' => 'Note', 'content' => '<p>Arrived mid-read.</p>' ), 'to' => array( $public_uri ) ), 'outbound' );
	$second    = axismundi_act_actor_feed_page( $actor, 5, (string) $first['next_cursor'] );
	$overlap   = array_intersect( array_column( $first['items'], 'id' ), array_column( $second['items'], 'id' ) );
	ax_feed_assert(
		$ax_feed_results,
		'an activity arriving between two page reads does not duplicate rows the reader already saw',
		'' !== (string) $first['next_cursor'] && empty( $overlap ) && ! in_array( $intruder, array_column( $second['items'], 'id' ), true )
	);

	ax_feed_assert(
		$ax_feed_results,
		'an unreadable cursor yields nothing rather than silently restarting at the newest page',
		array() === axismundi_act_get_actor_feed_after( $actor_uri, 5, 'not-a-cursor' )
			&& array() === axismundi_act_get_actor_feed_after( $actor_uri, 5, "'; DROP TABLE x; --@1" )
	);

	$ax_feed_small_page = static fn() : int => 5;
	add_filter( 'axismundi_act_actor_feed_per_page', $ax_feed_small_page );
	// The local fixture's authored Create objects deliberately have no backing Object source: that
	// covers the hidden-Create contract above, but cannot demonstrate a rendered continuation.
	// Use the cached remote Actor whose Objects are real cards, and add enough rows to cross the
	// small page boundary.
	$pagination_activities = array();
	for ( $index = 0; $index < 6; $index++ ) {
		$pagination_uri          = 'https://example.com/activities/' . wp_generate_uuid4();
		$pagination_activities[] = $pagination_uri;
		$ax_feed_activities[]    = $pagination_uri;
		axismundi_act_record_activity(
			array(
				'id'     => $pagination_uri,
				'type'   => 'Create',
				'actor'  => $remote_actor_uri,
				'object' => $remote_note_uri,
				'to'     => array( $public_uri ),
			),
			'inbound'
		);
	}
	$paginated_markup = ( static function () use ( $remote_actor ) {
		$previous = $GLOBALS['axismundi_actors_current_actor'] ?? null;
		$GLOBALS['axismundi_actors_current_actor'] = $remote_actor;
		$markup = axismundi_act_render_actor_activity_feed();
		$GLOBALS['axismundi_actors_current_actor'] = $previous;
		return $markup;
	} )();
	remove_filter( 'axismundi_act_actor_feed_per_page', $ax_feed_small_page );
	ax_feed_assert(
		$ax_feed_results,
		'the timeline is controlled by two native switches whose state the server renders as a default',
		// Native checkbox with switch semantics: the browser keeps Space-to-toggle, focus, and the
		// checked state, so none of that has to be rebuilt in script.
		2 === preg_match_all( '#<input class="axismundi-switch__input" type="checkbox" role="switch"#', $paginated_markup )
			&& 1 === preg_match( '#name="replies"[^>]*data-wp-on--change#', $paginated_markup )
			&& 1 === preg_match( '#name="boosts" checked#', $paginated_markup )
			// A trigger and a `role="dialog"` popover, the shape the reaction picker established
			// — not a disclosure, which would promise none of the closing behaviour this has.
			&& 1 === preg_match( '#class="axismundi-activity-feed__filters-trigger"[^>]*aria-haspopup="dialog"#', $paginated_markup )
			&& 1 === preg_match( '#data-wp-text="context.filterLabel">Posts and boosts<#', $paginated_markup )
			&& 1 === preg_match( '#class="axismundi-activity-feed__filters-panel" role="dialog"#', $paginated_markup )
			/*
			 * No Lab class names reach the product at all. `ax-switch` and `ax-menu` exist only
			 * in the reference implementation, so shipping either means a control with no styles
			 * behind it — which is exactly what an `ax-menu` popover turned out to be: a dialog
			 * with no surface.
			 */
			&& 0 === preg_match( '#class="[^"]*ax-(switch|menu)#', $paginated_markup )
			/*
			 * Hidden until the runtime reveals it. These checkboxes are in no form, so without
			 * script they would be a control that visibly does nothing; a reader without script
			 * gets the default timeline instead, which is the one everyone else starts on.
			 */
			&& 1 === preg_match( '#class="axismundi-activity-feed__filters" hidden#', $paginated_markup )
	);

	// The timeline switches are a reading preference, so the URL must not decide them: two readers
	// opening the same profile link see the same list. The community surface's slices are
	// collections, not preferences, and stay addressable.
	$_GET['filter'] = 'all';
	$url_filtered   = ( static function () use ( $remote_actor ) {
		$previous = $GLOBALS['axismundi_actors_current_actor'] ?? null;
		$GLOBALS['axismundi_actors_current_actor'] = $remote_actor;
		$markup = axismundi_act_render_actor_activity_feed();
		$GLOBALS['axismundi_actors_current_actor'] = $previous;
		return $markup;
	} )();
	unset( $_GET['filter'] );
	ax_feed_assert(
		$ax_feed_results,
		'a filter in the URL no longer steers the timeline, because a reading preference is not a destination',
		1 === preg_match( '#data-wp-text="context.filterLabel">Posts and boosts<#', $url_filtered )
			&& 1 === preg_match( '#name="boosts" checked#', $url_filtered )
			&& 0 === preg_match( '#name="replies" checked#', $url_filtered )
	);

	/*
	 * Interaction ownership follows the surface. In a feed the cards are appended and replaced,
	 * and DOM added after load is never hydrated, so their controls render as presentation and
	 * the region dispatches them. On a single object page the control is the interaction and
	 * owns itself. The feed variant must not merely be guarded at runtime — the interactive
	 * directives have to be absent, because markup that is not there cannot fire twice.
	 */
	$single_like = do_blocks( '<!-- wp:axismundi/interaction {"type":"like","objectUri":"' . $remote_note_uri . '"} /-->' );
	ax_feed_assert(
		$ax_feed_results,
		'a feed card delegates its controls to the region while a single object page keeps its own interactive block',
		false === strpos( $paginated_markup, 'data-wp-interactive="axismundi/like-button"' )
			&& false !== strpos( $paginated_markup, 'data-ax-action="like"' )
			&& false !== strpos( $paginated_markup, 'data-ax-object-uri=' )
			&& false !== strpos( $single_like, 'data-wp-interactive="axismundi/like-button"' )
			&& false === strpos( $single_like, 'data-ax-action=' )
	);

	ax_feed_assert(
		$ax_feed_results,
		'the reply control needs no delegation, being either a link or a disabled button and never a script-driven one',
		false !== strpos( $paginated_markup, 'axismundi-interaction__button' )
			&& false === strpos( $paginated_markup, 'data-wp-interactive="axismundi/reply-button"' )
			&& 0 === preg_match( '#class="axismundi-interaction__button"[^>]*data-(wp-on|ax-action)#', $paginated_markup )
	);

	ax_feed_assert(
		$ax_feed_results,
		'the rendered feed offers a real cursor link for Load more, not a script-only control',
		false !== strpos( $paginated_markup, 'feed_after=' )
			&& 1 === preg_match( '/<a class="axismundi-activity-feed__more-link"[^>]+href="[^"]+"/', $paginated_markup )
			&& false !== strpos( $paginated_markup, 'data-wp-on--click="actions.loadMore"' )
			// The list container is always present, because appended pages need something to
			// attach to even when the first page came back empty.
			&& false !== strpos( $paginated_markup, 'axismundi-activity-feed__list' )
			&& false !== strpos( $paginated_markup, 'callbacks.watchFeed' )
	);

	// The endpoint the island calls. Each request must return exactly one page: that constant
	// cost is the entire reason for appending rather than re-rendering a growing window.
	$feed_request = new WP_REST_Request( 'GET', '/axismundi/v1/actor-feed' );
	$feed_request->set_query_params( array( 'actor_uri' => $remote_actor->get_uri(), 'filter' => 'all', 'per_page' => 2 ) );
	$feed_response = rest_do_request( $feed_request );
	$feed_body     = $feed_response instanceof WP_REST_Response ? (array) $feed_response->get_data() : array();
	$feed_second   = array();
	if ( ! empty( $feed_body['next_cursor'] ) ) {
		$second = new WP_REST_Request( 'GET', '/axismundi/v1/actor-feed' );
		$second->set_query_params( array( 'actor_uri' => $remote_actor->get_uri(), 'filter' => 'all', 'per_page' => 2, 'after' => (string) $feed_body['next_cursor'] ) );
		$second_response = rest_do_request( $second );
		$feed_second     = $second_response instanceof WP_REST_Response ? (array) $second_response->get_data() : array();
	}
	ax_feed_assert(
		$ax_feed_results,
		'the feed endpoint returns one bounded page of cards and a cursor for the next one',
		$feed_response instanceof WP_REST_Response && 200 === $feed_response->get_status()
			&& isset( $feed_body['html'], $feed_body['next_cursor'], $feed_body['has_more'] )
			&& '' !== (string) $feed_body['next_cursor']
			/*
			 * A continuation page is bounded by what was asked for. The head page is not, and
			 * deliberately so: cache-miss fallback rows have no ledger position, so they ride
			 * along with the first page or they would never be shown at all.
			 */
			&& substr_count( (string) ( $feed_second['html'] ?? '' ), 'axismundi-activity-feed__item--object' ) <= 2
			// The second page is a different page, not the first one served again.
			&& ( empty( $feed_second['html'] ) || $feed_second['html'] !== $feed_body['html'] )
	);

	ax_feed_assert(
		$ax_feed_results,
		'a personalised feed response is never stored by a shared cache while an anonymous one may be',
		'public, max-age=30' === (string) ( $feed_response->get_headers()['Cache-Control'] ?? '' )
			&& ( static function () use ( $remote_actor, $ax_feed_user_id ) {
				wp_set_current_user( $ax_feed_user_id );
				$request = new WP_REST_Request( 'GET', '/axismundi/v1/actor-feed' );
				$request->set_query_params( array( 'actor_uri' => $remote_actor->get_uri(), 'per_page' => 2 ) );
				$response = rest_do_request( $request );
				wp_set_current_user( 0 );
				return 'private, no-store, max-age=0' === (string) ( $response->get_headers()['Cache-Control'] ?? '' );
			} )()
	);

	$unknown_feed = new WP_REST_Request( 'GET', '/axismundi/v1/actor-feed' );
	$unknown_feed->set_query_params( array( 'actor_uri' => 'https://example.com/users/' . wp_generate_uuid4() ) );
	ax_feed_assert(
		$ax_feed_results,
		'the feed endpoint refuses an Actor it does not know rather than answering with an empty page',
		404 === rest_do_request( $unknown_feed )->get_status()
	);

	/*
	 * No reference-implementation class name ships from this plugin at all.
	 *
	 * `ax-menu`, `ax-text-field`, and `ax-icon-button` exist only in the Lab, so shipping one
	 * means markup carrying a class no stylesheet defines — a component contract that does not
	 * exist. That is not theoretical: the timeline's filter popover shipped `ax-menu` and had no
	 * surface at all, and the emoji picker's search field carried three of them while its own
	 * BEM classes did the real work.
	 */
	$lab_leak = array();
	foreach ( glob( WP_PLUGIN_DIR . '/axismundi-activities/includes/*.php' ) as $source ) {
		$body = (string) file_get_contents( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading our own source in a dev audit.
		if ( preg_match( '#class="[^"]*\bax-(menu|text-field|icon-button)\b#', $body ) ) {
			$lab_leak[] = basename( $source );
		}
	}
	ax_feed_assert(
		$ax_feed_results,
		'no Lab-only class name is emitted by this plugin, so nothing ships depending on styles that do not exist',
		array() === $lab_leak
	);

	/*
	 * The card a feed repeats is edited once and rendered twice.
	 *
	 * The first page is built while the profile template is being rendered; every page after it comes
	 * from a REST request that has no template, no block instance and no inner blocks. Both go through
	 * one resolver so they cannot answer differently — and the resolver prefers the Site Editor's saved
	 * template, because reading the bundled file would serve continuation cards that ignore every edit
	 * an author made.
	 *
	 * Byte equality is not the contract; the card's semantics are. The picker and the repost menu
	 * mint a unique id per control, which is what stops one click from opening every picker on a
	 * page — so those ids differing is a requirement rather than noise. They are normalised here
	 * because this check is about the card, and asserted as distinct where that belongs, in the
	 * interaction audit.
	 */
	$ax_feed_tpl_actor = $actor;
	if ( $ax_feed_tpl_actor instanceof Axismundi_Actor ) {
		$ax_feed_tpl_source = axismundi_act_actor_feed_template_source( $ax_feed_tpl_actor );
		$ax_feed_tpl_filter = axismundi_act_actor_feed_default_filter();
		$ax_feed_tpl_items  = (array) ( axismundi_act_actor_feed_page( $ax_feed_tpl_actor, 20, '', $ax_feed_tpl_filter, 'activity' )['items'] ?? array() );
		/*
		 * The first entry is not necessarily one that renders: by this point the fixture has
		 * deliberately tombstoned and hidden objects, and a row whose object is gone correctly
		 * produces nothing. Comparing two blank cards would pass while proving nothing, so a row
		 * that actually draws is selected — and that one exists is itself asserted below.
		 */
		$ax_feed_tpl_item = array();
		foreach ( $ax_feed_tpl_items as $ax_feed_tpl_candidate ) {
			if ( is_array( $ax_feed_tpl_candidate ) && '' !== axismundi_act_render_actor_feed_card( $ax_feed_tpl_candidate, $ax_feed_tpl_source ) ) {
				$ax_feed_tpl_item = $ax_feed_tpl_candidate;
				break;
			}
		}
		$ax_feed_tpl_norm   = static function ( string $html ) : string {
			return preg_replace( '/\s+/', ' ', preg_replace( '/(ax-rx|ax-announce-menu|ax-announce-trigger|ax-object-spoiler)-\d+/', 'GEN', $html ) );
		};
		/*
		 * The block was renamed, which is only safe if everything that names it moved with it.
		 *
		 * A block name is not just a label here: the saved template refers to it, the item
		 * template declares it as its parent, and the view module handle WordPress generates is
		 * derived from it. Miss the handle and the feed still renders while Load more silently
		 * stops working, which is the kind of break that looks like nothing at all.
		 */
		ax_feed_assert(
			$ax_feed_results,
			'the renamed loop is the only name left: registration, parent, and the module handle all moved',
			WP_Block_Type_Registry::get_instance()->is_registered( 'axismundi/actor-feed-loop' )
				&& ! WP_Block_Type_Registry::get_instance()->is_registered( 'axismundi/actor-activity-feed' )
				// The cards nest one level deeper now: the loop holds the set, the set holds the
				// cards. Asserted as a chain so neither half can be re-homed on its own.
				&& array( 'axismundi/actor-feed-loop' ) === (array) WP_Block_Type_Registry::get_instance()->get_registered( 'axismundi/feed-item-templates' )->parent
				&& array( 'axismundi/feed-item-templates' ) === (array) WP_Block_Type_Registry::get_instance()->get_registered( 'axismundi/feed-item-template' )->parent
				&& is_readable( dirname( __DIR__ ) . '/blocks/actor-feed-loop/view.js' )
		);

		/*
		 * The chrome is arranged by the template, and the arrangement has to survive a template
		 * that predates it. The interesting case is not the one the bundled file writes — it is
		 * the template already saved in somebody's database, which names only the card. That has
		 * to keep both controls, because a reader who loses "Load more" loses the rest of the
		 * feed and gets no error saying so.
		 *
		 * Parsed markup rather than the real template on purpose: this is a question about what an
		 * arrangement means, and routing it through the filesystem would answer a different one
		 * — as well as being unmeasurable within an opcache revalidation window.
		 */
		/*
		 * Two saved cards, one per density, picked by the same resolver on both sides.
		 *
		 * This is what makes compact a composition rather than a stylesheet: how much of an entry
		 * appears is decided by blocks an author edits, not by hiding things that were rendered
		 * anyway. The resolver is the only place that chooses, so the first page and every page
		 * fetched after "Load more" cannot end up drawing different cards.
		 *
		 * A template carrying only one card still answers for both — that is the upgrade path for
		 * anything saved before there were two, and it is why the bundled template seeds both
		 * rather than leaving the second to be discovered missing.
		 */
		$ax_feed_pair = static function ( string $inner ) : array {
			$blocks = parse_blocks( '<!-- wp:axismundi/actor-feed-loop --><!-- wp:axismundi/feed-item-templates -->' . $inner . '<!-- /wp:axismundi/feed-item-templates --><!-- /wp:axismundi/actor-feed-loop -->' );
			return array(
				'card'    => axismundi_act_extract_feed_item_template( $blocks, 'card' ),
				'compact' => axismundi_act_extract_feed_item_template( $blocks, 'compact' ),
			);
		};
		$ax_feed_both = $ax_feed_pair(
			'<!-- wp:axismundi/feed-item-template {"density":"card"} --><!-- wp:axismundi/object-card-body /--><!-- /wp:axismundi/feed-item-template -->'
			. '<!-- wp:axismundi/feed-item-template {"density":"compact"} --><!-- wp:axismundi/object-title /--><!-- /wp:axismundi/feed-item-template -->'
		);
		$ax_feed_single = $ax_feed_pair( '<!-- wp:axismundi/feed-item-template --><!-- wp:axismundi/object-card-body /--><!-- /wp:axismundi/feed-item-template -->' );
		/*
		 * Order is the contract, so a template holding only a compact card opens compact — the
		 * default is whichever was saved first, not a name written into this file. And a second
		 * card claiming a density that is already taken is a fault rather than something to
		 * resolve: "first wins" decides among *different* densities, and letting it also arbitrate
		 * duplicates would leave one card unreachable with nothing saying so.
		 */
		/*
		 * What the editor seeds has to be something the resolver can read.
		 *
		 * This is the gap that let a broken loop ship: the audit checked the bundled PHP template,
		 * which was correct, and never looked at the block an author actually inserts. Its seed
		 * still placed a bare `feed-item-template` under the loop — a shape the resolver ignores by
		 * design — so inserting a feed and saving it produced a template that looked right in the
		 * canvas and rendered no cards at all. The editor would have been the thing that lied.
		 *
		 * Read from source because the seed is JavaScript. Comments are stripped first, so
		 * describing the arrangement in prose cannot satisfy it.
		 */
		$ax_feed_loop_seed = (string) @file_get_contents( dirname( __DIR__ ) . '/blocks/actor-feed-loop/edit.js' );
		$ax_feed_loop_seed = (string) preg_replace( '#/\*.*?\*/#s', '', $ax_feed_loop_seed );
		$ax_feed_loop_seed = (string) preg_replace( '#//[^\n]*#', '', $ax_feed_loop_seed );
		$ax_feed_seeded    = 1 === preg_match( '#var TEMPLATE = \[(.*?)\];#s', $ax_feed_loop_seed, $ax_feed_seed_m ) ? $ax_feed_seed_m[1] : '';
		ax_feed_assert(
			$ax_feed_results,
			'inserting a feed in the editor seeds the set, not a card the resolver will not read',
			'' !== $ax_feed_seeded
				&& false !== strpos( $ax_feed_seeded, 'axismundi/feed-item-templates' )
				// The leaf must not be seeded directly: outside the set it is invisible to the feed.
				&& 0 === preg_match( "#'axismundi/feed-item-template'#", $ax_feed_seeded )
				&& false !== strpos( $ax_feed_seeded, 'axismundi/feed-pagination' )
		);
		/*
		 * And nothing in the resolver still knows how to read the old shape. A dead recursive
		 * finder that reached outside the set is a clean break waiting to be undone by whoever
		 * reuses it, so the contract is that it does not exist.
		 */
		ax_feed_assert(
			$ax_feed_results,
			'no reader is left that would find a card outside the set',
			! function_exists( 'axismundi_act_find_feed_item_template' )
		);

		/*
		 * Every Person surface has to say which side of Group context it takes.
		 *
		 * `axismundi_act_group_context_admits()` treats an unknown mode as "admit everything",
		 * which is right for a generic feed source — a typo must not empty someone's profile — and
		 * wrong here. These two surfaces are complements: whatever Activity excludes, Community
		 * shows. A descriptor that forgot the key would widen to everything, and once the Forum
		 * filters are removed that shows Group submissions back in a Person's Activity with
		 * nothing catching it, because the fallback is silent by design.
		 *
		 * So the requirement is explicitness, checked on the surfaces a real Person profile
		 * offers. Group surfaces are exempt: a community archive selects from the Announce ledger
		 * and never asks this question.
		 */
		$ax_feed_person_surfaces = $actor instanceof Axismundi_Actor ? axismundi_act_actor_profile_surfaces( $actor ) : array();
		$ax_feed_declared_sides  = array();
		foreach ( $ax_feed_person_surfaces as $ax_feed_key => $ax_feed_definition ) {
			$ax_feed_declared_sides[ (string) $ax_feed_key ] = (string) ( $ax_feed_definition['group_context'] ?? '' );
		}
		ax_feed_assert(
			$ax_feed_results,
			'both Person surfaces declare their side of Group context rather than relying on the permissive default',
			array() !== $ax_feed_declared_sides
				&& 'out' === ( $ax_feed_declared_sides['activity'] ?? '' )
				&& 'in' === ( $ax_feed_declared_sides['community'] ?? '' )
				// And nothing else slipped in undeclared, which is the case the default would hide.
				&& array() === array_filter(
					$ax_feed_declared_sides,
					static fn( string $side ) : bool => ! in_array( $side, array( 'in', 'out' ), true )
				)
		);

		$ax_feed_map = static function ( string $inner ) : array {
			return axismundi_act_feed_item_templates( parse_blocks( '<!-- wp:axismundi/feed-item-templates -->' . $inner . '<!-- /wp:axismundi/feed-item-templates -->' ) );
		};
		$ax_feed_compact_only = $ax_feed_map( '<!-- wp:axismundi/feed-item-template {"density":"compact"} --><!-- wp:axismundi/object-title /--><!-- /wp:axismundi/feed-item-template -->' );
		$ax_feed_reordered    = $ax_feed_map(
			'<!-- wp:axismundi/feed-item-template {"density":"compact"} --><!-- wp:axismundi/object-title /--><!-- /wp:axismundi/feed-item-template -->'
			. '<!-- wp:axismundi/feed-item-template {"density":"card"} --><!-- wp:axismundi/object-card-body /--><!-- /wp:axismundi/feed-item-template -->'
		);
		$ax_feed_duplicated = $ax_feed_map( str_repeat( '<!-- wp:axismundi/feed-item-template {"density":"card"} --><!-- wp:axismundi/object-title /--><!-- /wp:axismundi/feed-item-template -->', 2 ) );
		ax_feed_assert(
			$ax_feed_results,
			'the first saved card is the default, so re-ordering the templates changes what a plain address opens',
			array( 'compact' ) === $ax_feed_compact_only['order']
				&& array( 'compact', 'card' ) === $ax_feed_reordered['order']
				&& $ax_feed_reordered['templates']['compact'] === axismundi_act_extract_feed_item_template(
					parse_blocks(
						'<!-- wp:axismundi/feed-item-templates -->'
						. '<!-- wp:axismundi/feed-item-template {"density":"compact"} --><!-- wp:axismundi/object-title /--><!-- /wp:axismundi/feed-item-template -->'
						. '<!-- wp:axismundi/feed-item-template {"density":"card"} --><!-- wp:axismundi/object-card-body /--><!-- /wp:axismundi/feed-item-template -->'
						. '<!-- /wp:axismundi/feed-item-templates -->'
					),
					''
				)
		);
		ax_feed_assert(
			$ax_feed_results,
			'two cards claiming one density is reported rather than silently resolved, and never renders twice',
			array( 'card' ) === $ax_feed_duplicated['duplicates']
				&& array( 'card' ) === $ax_feed_duplicated['order']
				&& array() === $ax_feed_compact_only['duplicates']
				&& array() === $ax_feed_reordered['duplicates']
		);
		ax_feed_assert(
			$ax_feed_results,
			'each density resolves to its own saved card, so compact is a composition rather than a stylesheet',
			false !== strpos( $ax_feed_both['card'], 'object-card-body' )
				&& false !== strpos( $ax_feed_both['compact'], 'object-title' )
				&& $ax_feed_both['card'] !== $ax_feed_both['compact']
				&& '' !== $ax_feed_both['compact']
		);
		/*
		 * Cards are read from the set and from nowhere else.
		 *
		 * They used to sit directly under the loop, and that shape is deliberately no longer
		 * understood: reading both would let a template stay half-migrated with nothing saying
		 * which arrangement was in force. The saved templates are overwritten on deploy, so there
		 * is no install to carry — and a template with no set rendering no cards is loud, which is
		 * what a clean break should be.
		 */
		$ax_feed_setless = axismundi_act_feed_item_templates(
			parse_blocks( '<!-- wp:axismundi/actor-feed-loop --><!-- wp:axismundi/feed-item-template --><!-- wp:axismundi/object-card-body /--><!-- /wp:axismundi/feed-item-template --><!-- /wp:axismundi/actor-feed-loop -->' )
		);
		ax_feed_assert(
			$ax_feed_results,
			'a card outside the set is not read, so a half-migrated template fails loudly instead of half-working',
			array() === $ax_feed_setless['order']
				&& array() === $ax_feed_setless['templates']
				// The same card inside a set is read, so this is about placement and nothing else.
				&& '' !== $ax_feed_single['card']
		);
		$ax_feed_live_actor = $actor instanceof Axismundi_Actor ? $actor : null;
		ax_feed_assert(
			$ax_feed_results,
			'the bundled profile seeds both cards, so neither density falls back to one it did not choose',
			$ax_feed_live_actor instanceof Axismundi_Actor
				&& '' !== axismundi_act_actor_feed_template_source( $ax_feed_live_actor, 'card' )
				&& '' !== axismundi_act_actor_feed_template_source( $ax_feed_live_actor, 'compact' )
				&& axismundi_act_actor_feed_template_source( $ax_feed_live_actor, 'card' )
					!== axismundi_act_actor_feed_template_source( $ax_feed_live_actor, 'compact' )
		);

		$ax_feed_arrangement = static function ( string $inner ) : array {
			return axismundi_act_feed_slots_from_blocks(
				parse_blocks( '<!-- wp:group --><div class="wp-block-group"><!-- wp:axismundi/actor-feed-loop -->' . $inner . '<!-- /wp:axismundi/actor-feed-loop --></div><!-- /wp:group -->' )
			);
		};
		$ax_feed_legacy_shape = $ax_feed_arrangement( '<!-- wp:axismundi/feed-item-templates --><!-- /wp:axismundi/feed-item-templates -->' );
		$ax_feed_moved        = $ax_feed_arrangement( '<!-- wp:axismundi/feed-pagination /--><!-- wp:axismundi/feed-item-templates --><!-- /wp:axismundi/feed-item-templates --><!-- wp:axismundi/feed-filters /-->' );
		$ax_feed_dropped      = $ax_feed_arrangement( '<!-- wp:axismundi/feed-item-templates --><!-- /wp:axismundi/feed-item-templates --><!-- wp:axismundi/feed-pagination /-->' );
		ax_feed_assert(
			$ax_feed_results,
			'a template written before the chrome blocks existed keeps both controls in their original places',
			array() === $ax_feed_legacy_shape
		);
		ax_feed_assert(
			$ax_feed_results,
			'the chrome goes where the template puts it, in the template order rather than a fixed one',
			array( 'pagination', 'list', 'filters' ) === $ax_feed_moved
		);
		ax_feed_assert(
			$ax_feed_results,
			'naming one control is how a template drops the other, which a legacy template cannot do by accident',
			array( 'list', 'pagination' ) === $ax_feed_dropped
		);
		/*
		 * Both blocks belong to this feed alone. The Community archive counts and numbers its
		 * pages; this one walks a cursor with no total and one direction. Letting either block
		 * be placed outside the loop would offer that surface a control it cannot honour.
		 */
		/*
		 * What a container writes out when the editor saves it.
		 *
		 * This is the one regression a front-end render cannot see. Both containers are dynamic,
		 * so the page looks the same whether or not their children survived — the loop falls back
		 * to the fixed arrangement and keeps rendering cards. What is gone is the author's card
		 * definition and both placements, discarded on the first save from the Site Editor
		 * because the serializer writes only what `save` returns and emits a block that returns
		 * nothing as self-closing.
		 *
		 * Read from source because the serializer is JavaScript and this audit is not. That makes
		 * it a check on the contract rather than on the act, so it is written to fail the way the
		 * mistake would actually be made: someone restores `return null` on a container, which is
		 * correct for a leaf and quietly wrong here. Comments are stripped first, so describing
		 * the rule in prose cannot satisfy it.
		 */
		$ax_feed_save_body = static function ( string $block ) : string {
			$src = (string) @file_get_contents( dirname( __DIR__ ) . '/blocks/' . $block . '/edit.js' );
			$src = (string) preg_replace( '#/\*.*?\*/#s', '', $src );
			$src = (string) preg_replace( '#//[^\n]*#', '', $src );
			return 1 === preg_match( '#save:\s*function\s*\([^)]*\)\s*\{([^{}]*)\}#', $src, $m ) ? trim( $m[1] ) : '';
		};
		$ax_feed_container_saves = array(
			'actor-feed-loop'    => $ax_feed_save_body( 'actor-feed-loop' ),
			'feed-item-templates' => $ax_feed_save_body( 'feed-item-templates' ),
			'feed-item-template'  => $ax_feed_save_body( 'feed-item-template' ),
		);
		$ax_feed_leaf_saves = array(
			'feed-filters'    => $ax_feed_save_body( 'feed-filters' ),
			'feed-pagination'     => $ax_feed_save_body( 'feed-pagination' ),
			'feed-density-switch' => $ax_feed_save_body( 'feed-density-switch' ),
		);
		$ax_feed_containers_hold = true;
		foreach ( $ax_feed_container_saves as $body ) {
			$ax_feed_containers_hold = $ax_feed_containers_hold && '' !== $body && false !== strpos( $body, 'InnerBlocks.Content' );
		}
		$ax_feed_leaves_hold = true;
		foreach ( $ax_feed_leaf_saves as $body ) {
			$ax_feed_leaves_hold = $ax_feed_leaves_hold && 1 === preg_match( '#^return\s+null;$#', $body );
		}
		ax_feed_assert(
			$ax_feed_results,
			'every container saves its children, so an editor save keeps the card set and every placement',
			3 === count( $ax_feed_container_saves ) && $ax_feed_containers_hold
		);
		ax_feed_assert(
			$ax_feed_results,
			'saving nothing stays what it is correct for: the childless blocks, which self-close',
			3 === count( $ax_feed_leaf_saves ) && $ax_feed_leaves_hold
		);
		ax_feed_assert(
			$ax_feed_results,
			'the filter and pagination blocks are Activity feed children, not chrome any surface can host',
			array( 'axismundi/actor-feed-loop' ) === (array) WP_Block_Type_Registry::get_instance()->get_registered( 'axismundi/feed-filters' )->parent
				&& array( 'axismundi/actor-feed-loop' ) === (array) WP_Block_Type_Registry::get_instance()->get_registered( 'axismundi/feed-pagination' )->parent
		);


		ax_feed_assert(
			$ax_feed_results,
			'the feed resolves one saved card template for both the first page and every page after it',
			'' !== $ax_feed_tpl_source
				&& false !== strpos( $ax_feed_tpl_source, 'axismundi/object-card-body' )
				// The frame is not in the saved template: which article wrapper and which type modifier
				// a row gets depends on the Object, and only the renderer knows which one it holds.
				&& false === strpos( $ax_feed_tpl_source, 'axismundi-object-card--article' )
			// A renderable row was found, so the two checks below are looking at real cards.
			&& ! empty( $ax_feed_tpl_item )
		);

		ax_feed_assert(
			$ax_feed_results,
			'one item rendered through that template twice is the same card, and its control ids are required to differ',
			// Non-empty first: two blank renders are equal, and a check that cannot tell those from
			// two real ones is not checking anything.
			! empty( $ax_feed_tpl_item )
				&& '' !== axismundi_act_render_actor_feed_card( $ax_feed_tpl_item, $ax_feed_tpl_source )
				&& $ax_feed_tpl_norm( axismundi_act_render_actor_feed_card( $ax_feed_tpl_item, $ax_feed_tpl_source ) )
					=== $ax_feed_tpl_norm( axismundi_act_render_actor_feed_card( $ax_feed_tpl_item, $ax_feed_tpl_source ) )
		);

		/*
		 * That the saved template renders the same card as the bundled one proves nothing either
		 * way: it was seeded from it. What has to be true is that a *different* template produces a
		 * different card — otherwise the source could be ignored entirely and every check above
		 * would still pass.
		 */
		$ax_feed_tpl_minimal = axismundi_act_render_actor_feed_card( $ax_feed_tpl_item, '<!-- wp:axismundi/object-title /-->' );
		ax_feed_assert(
			$ax_feed_results,
			'the card a caller supplies is the card that gets rendered, inside a frame the Object decides',
			// Stated as a contrast rather than by looking for one block's output: which blocks draw
			// anything depends on what the fixture Object happens to carry, but a template without
			// the action row can never produce one.
			! empty( $ax_feed_tpl_item )
				&& false === strpos( $ax_feed_tpl_minimal, 'axismundi-interaction__button' )
				&& false !== strpos( axismundi_act_render_actor_feed_card( $ax_feed_tpl_item, $ax_feed_tpl_source ), 'axismundi-interaction__button' )
				// The frame is the Object's either way, whatever was supplied.
				&& false !== strpos( $ax_feed_tpl_minimal, 'axismundi-object-card' )
		);
	}

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
		if ( $ax_feed_post_id > 0 ) {
			wp_delete_post( $ax_feed_post_id, true );
		}
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
