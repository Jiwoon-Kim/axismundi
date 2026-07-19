<?php
/**
 * Outbound Quote target storage (schema v2) and three-way classification (dev-only).
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_note_quote_results   = array();
$ax_note_quote_post_ids  = array();
$ax_note_quote_user_ids  = array();
$ax_note_quote_actor_ids = array();
$ax_note_quote_remote    = '';
$GLOBALS['ax_note_quote_http'] = 0;

/** @param bool[] $results Results. */
function ax_note_quote_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Prove classification performs no transport. */
function ax_note_quote_http( $preempt ) {
	++$GLOBALS['ax_note_quote_http'];
	return $preempt;
}

/** Create a public local Actor for one fixture user. */
function ax_note_quote_make_author( array &$user_ids, array &$actor_ids ) : ?WP_User {
	$login = 'ax_note_q_' . strtolower( wp_generate_password( 8, false, false ) );
	$uid   = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	if ( $uid <= 0 ) {
		return null;
	}
	$user_ids[] = $uid;
	$actor = axismundi_actors_ensure_for_user( $uid );
	if ( $actor instanceof Axismundi_Actor ) {
		$actor_ids[] = $actor->get_identity_id();
		axismundi_actors_register_handle( $actor->get_identity_id(), $login );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	}
	return get_userdata( $uid ) ?: null;
}

try {
	add_filter( 'pre_http_request', 'ax_note_quote_http' );

	// The v2 migration verifies its own columns and index, same discipline as v1.
	$installed = axismundi_note_install_table();
	$table     = axismundi_note_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixture schema probe.
	$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixture schema probe.
	$quote_key = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'quote_target_uri_hash'", ARRAY_A );
	ax_note_quote_assert( $ax_note_quote_results, 'schema v2 installs the quote target columns and a supporting index', $installed && in_array( 'quote_target_uri', $columns, true ) && in_array( 'quote_target_uri_hash', $columns, true ) && ! empty( $quote_key ) );

	$author = ax_note_quote_make_author( $ax_note_quote_user_ids, $ax_note_quote_actor_ids );
	$other  = ax_note_quote_make_author( $ax_note_quote_user_ids, $ax_note_quote_actor_ids );
	$actor       = $author instanceof WP_User ? axismundi_actors_get_for_user( $author->ID ) : null;
	$other_actor = $other instanceof WP_User ? axismundi_actors_get_for_user( $other->ID ) : null;
	$actor_uri       = $actor instanceof Axismundi_Actor ? $actor->get_uri() : '';
	$other_actor_uri = $other_actor instanceof Axismundi_Actor ? $other_actor->get_uri() : '';

	// A Note by the same author, used as a self-quote target.
	$own_post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $author instanceof WP_User ? $author->ID : 0, 'post_content' => 'Own note.' ) );
	$ax_note_quote_post_ids[] = $own_post_id;
	$own_envelope = axismundi_note_get( $own_post_id );
	$own_uri      = is_array( $own_envelope ) ? axismundi_note_object_uri( (string) $own_envelope['local_uuid'] ) : '';

	// A Note by a different local author, used as a local-other quote target.
	$other_post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $other instanceof WP_User ? $other->ID : 0, 'post_content' => 'Other note.' ) );
	$ax_note_quote_post_ids[] = $other_post_id;
	$other_envelope = axismundi_note_get( $other_post_id );
	$other_uri      = is_array( $other_envelope ) ? axismundi_note_object_uri( (string) $other_envelope['local_uuid'] ) : '';

	// A quoting Note whose envelope stores the outbound target.
	$quoting_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $author instanceof WP_User ? $author->ID : 0, 'post_content' => 'Quoting note.' ) );
	$ax_note_quote_post_ids[] = $quoting_id;

	$saved = axismundi_note_save( $quoting_id, array( 'quote_target_uri' => $other_uri ) );
	ax_note_quote_assert( $ax_note_quote_results, 'saving a quote target persists the URI and its hash', is_array( $saved ) && $other_uri === $saved['quote_target_uri'] && hash( 'sha256', $other_uri ) === $saved['quote_target_uri_hash'] );

	$envelope_view = axismundi_note_get_envelope( $quoting_id );
	ax_note_quote_assert( $ax_note_quote_results, 'the structured envelope view exposes quoteTarget', $other_uri === $envelope_view['quoteTarget'] );

	$rest_saved = axismundi_note_save_envelope( $quoting_id, array( 'quoteTarget' => $own_uri ) );
	ax_note_quote_assert( $ax_note_quote_results, 'the structured REST save writes quoteTarget through the same fail-closed contract', is_array( $rest_saved ) && $own_uri === $rest_saved['quote_target_uri'] );

	$absent = axismundi_note_save( $quoting_id, array( 'visibility' => 'public' ) );
	ax_note_quote_assert( $ax_note_quote_results, 'an absent quote_target_uri field preserves the stored value', is_array( $absent ) && $own_uri === $absent['quote_target_uri'] );

	$cleared = axismundi_note_save( $quoting_id, array( 'quote_target_uri' => 'not-a-uri' ) );
	ax_note_quote_assert( $ax_note_quote_results, 'a malformed quote target sanitizes to empty rather than failing the save, matching in_reply_to/context', is_array( $cleared ) && '' === $cleared['quote_target_uri'] && '' === $cleared['quote_target_uri_hash'] );

	// Classification: self.
	$self_class = axismundi_note_quote_classify( $own_uri, $actor_uri );
	ax_note_quote_assert( $ax_note_quote_results, 'quoting one\'s own local Note classifies as self', AXISMUNDI_NOTE_QUOTE_SELF === $self_class );

	// Classification: local-other.
	$local_other_class = axismundi_note_quote_classify( $other_uri, $actor_uri );
	ax_note_quote_assert( $ax_note_quote_results, 'quoting a different local Actor\'s Note classifies as local-other', AXISMUNDI_NOTE_QUOTE_LOCAL_OTHER === $local_other_class );

	// Classification: remote, seeded through the Object Projections remote-object cache
	// (a read of a stored observation, not a fetch).
	$remote_actor_uri = 'https://remote.example/actors/quote-target-' . strtolower( wp_generate_password( 6, false, false ) );
	$ax_note_quote_remote = 'https://remote.example/objects/quote-target-' . strtolower( wp_generate_password( 6, false, false ) );
	$stored_remote = axismundi_op_remote_object_store(
		array(
			'id'           => $ax_note_quote_remote,
			'type'         => 'Note',
			'attributedTo' => $remote_actor_uri,
			'content'      => 'Remote quoted note.',
		)
	);
	$remote_class = axismundi_note_quote_classify( $ax_note_quote_remote, $actor_uri );
	ax_note_quote_assert( $ax_note_quote_results, 'quoting a cached remote object classifies as remote', is_array( $stored_remote ) && AXISMUNDI_NOTE_QUOTE_REMOTE === $remote_class );

	// Classification: unresolved (never cached and not a local Note).
	$unknown_class = axismundi_note_quote_classify( 'https://remote.example/objects/never-seen', $actor_uri );
	ax_note_quote_assert( $ax_note_quote_results, 'an uncached, unowned target is unresolved rather than misclassified', is_wp_error( $unknown_class ) && 'ax_note_quote_unresolved' === $unknown_class->get_error_code() );

	// Invalid input fails closed rather than guessing.
	$blank_class = axismundi_note_quote_classify( '', $actor_uri );
	ax_note_quote_assert( $ax_note_quote_results, 'an empty target URI fails closed', is_wp_error( $blank_class ) && 'ax_note_quote_target' === $blank_class->get_error_code() );

	ax_note_quote_assert( $ax_note_quote_results, 'quote storage and classification perform no HTTP request', 0 === $GLOBALS['ax_note_quote_http'] );
} finally {
	remove_filter( 'pre_http_request', 'ax_note_quote_http' );
	if ( '' !== $ax_note_quote_remote && function_exists( 'axismundi_op_remote_object_delete' ) ) {
		axismundi_op_remote_object_delete( $ax_note_quote_remote );
	}
	foreach ( array_unique( $ax_note_quote_post_ids ) as $pid ) {
		$wpdb->delete( axismundi_note_table(), array( 'post_id' => (int) $pid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( get_post( (int) $pid ) instanceof WP_Post ) {
			wp_delete_post( (int) $pid, true );
		}
	}
	foreach ( array_unique( $ax_note_quote_actor_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $actor_table ) {
			$wpdb->delete( $actor_table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( ! empty( $ax_note_quote_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_note_quote_user_ids ) as $uid ) {
			if ( get_userdata( (int) $uid ) ) {
				wp_delete_user( (int) $uid );
			}
		}
	}
}

$ax_note_quote_failures = count( array_filter( $ax_note_quote_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_note_quote_results ), $ax_note_quote_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_note_quote_failures > 0 ? 1 : 0 );
}
exit( $ax_note_quote_failures > 0 ? 1 : 0 );
