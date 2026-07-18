<?php
/**
 * Note envelope store and authoring persistence regression (dev-only).
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_note_results     = array();
$ax_note_post_ids    = array();
$ax_note_user_ids    = array();
$ax_note_actor_ids   = array();
$ax_note_real_prefix  = $wpdb->prefix;
$ax_note_shadow_table = '';
$GLOBALS['ax_note_http'] = 0;

/** Break only the tombstone UPDATE so a delete-time write failure can be tested. */
function ax_note_break_tombstone( $query ) {
	return ( false !== strpos( (string) $query, 'ax_notes' ) && false !== strpos( (string) $query, "object_status = 'tombstone'" ) )
		? 'UPDATE ax_notes_deliberately_missing SET id = id WHERE 1 = 0'
		: $query;
}

/** @param bool[] $results Results. */
function ax_note_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Prove the envelope layer performs no transport. */
function ax_note_http( $preempt ) {
	++$GLOBALS['ax_note_http'];
	return $preempt;
}

/** Create a public local Actor for one fixture user. */
function ax_note_make_author( array &$user_ids, array &$actor_ids ) : ?WP_User {
	$login = 'ax_note_' . strtolower( wp_generate_password( 8, false, false ) );
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
	add_filter( 'pre_http_request', 'ax_note_http' );

	// F5: verified installer records a version only after schema is confirmed.
	$installed = axismundi_note_install_table();
	$table     = axismundi_note_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixture schema probe.
	$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixture schema probe.
	$uuid_key = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'local_uuid'", ARRAY_A );
	ax_note_assert( $ax_note_results, 'the installer verifies a ready store with a unique local UUID identity', $installed && axismundi_note_ready() && in_array( 'object_status', $columns, true ) && in_array( 'deleted_at', $columns, true ) && in_array( 'attribution_locked_at', $columns, true ) && ! empty( $uuid_key ) && 0 === (int) $uuid_key[0]['Non_unique'] );

	// F5: a throwaway prefix installs a fresh, independent verified schema. The
	// prefix is restored in the finally block even if a probe between throws.
	$shadow_prefix = $ax_note_real_prefix . 'axn_shadow_' . strtolower( wp_generate_password( 6, false, false ) ) . '_';
	$wpdb->prefix  = $shadow_prefix;
	$shadow_ok     = axismundi_note_install_table();
	$shadow_table  = axismundi_note_table();
	$ax_note_shadow_table = $shadow_table;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- throwaway shadow probe.
	$shadow_post_key = $wpdb->get_results( "SHOW INDEX FROM {$shadow_table} WHERE Key_name = 'post_id'", ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- throwaway shadow teardown.
	$wpdb->query( "DROP TABLE IF EXISTS {$shadow_table}" );
	$wpdb->prefix = $ax_note_real_prefix;
	ax_note_assert( $ax_note_results, 'a throwaway prefix installs an independent verified schema', $shadow_ok && $shadow_table !== $table && ! empty( $shadow_post_key ) && 0 === (int) $shadow_post_key[0]['Non_unique'] );

	$author = ax_note_make_author( $ax_note_user_ids, $ax_note_actor_ids );
	$actor  = $author instanceof WP_User ? axismundi_actors_get_for_user( $author->ID ) : null;
	$actor_uri = $actor instanceof Axismundi_Actor ? $actor->get_uri() : '';

	$derived_mention  = 'https://example.com/users/derived-' . strtolower( wp_generate_password( 6, false, false ) );
	$explicit_mention = 'https://example.com/users/explicit-' . strtolower( wp_generate_password( 6, false, false ) );
	$reply_uri        = 'https://example.com/objects/reply-' . strtolower( wp_generate_password( 6, false, false ) );
	$context_uri      = 'https://example.com/contexts/ctx-' . strtolower( wp_generate_password( 6, false, false ) );

	$post_id = (int) wp_insert_post(
		array(
			'post_type'    => AXISMUNDI_NOTE_POST_TYPE,
			'post_status'  => 'draft',
			'post_author'  => $author instanceof WP_User ? $author->ID : 0,
			'post_title'   => 'Envelope note',
			'post_content' => 'Hello <a class="mention" href="' . esc_url( $derived_mention ) . '">@derived</a>.',
		)
	);
	$ax_note_post_ids[] = $post_id;

	// P1: programmatic (non-admin) creation still receives a baseline envelope.
	$baseline = axismundi_note_get( $post_id );
	ax_note_assert( $ax_note_results, 'a programmatic Note insert receives a baseline envelope without the admin meta box', is_array( $baseline ) && '' !== (string) $baseline['local_uuid'] && 'public' === $baseline['visibility'] && '' !== (string) $baseline['actor_uri'] );

	$saved = axismundi_note_save(
		$post_id,
		array(
			'visibility'         => 'quiet_public',
			'language_tag'       => 'en',
			'in_reply_to_uri'    => $reply_uri,
			'context_uri'        => $context_uri,
			'sensitive'          => true,
			'content_warning'    => 'spoiler',
			'mention_actor_uris' => array( $explicit_mention ),
		)
	);
	ax_note_assert( $ax_note_results, 'saving one Note persists a canonicalized federation envelope', is_array( $saved ) && 'unlisted' === $saved['visibility'] && 'en' === $saved['language_tag'] && $reply_uri === $saved['in_reply_to_uri'] && hash( 'sha256', $reply_uri ) === $saved['in_reply_to_uri_hash'] && $context_uri === $saved['context_uri'] && 1 === (int) $saved['is_sensitive'] && 'spoiler' === $saved['content_warning'] && 'active' === $saved['object_status'] );
	ax_note_assert( $ax_note_results, 'the envelope snapshots the author Actor while attribution is unlocked', is_array( $saved ) && '' !== $actor_uri && $actor_uri === $saved['actor_uri'] && hash( 'sha256', $actor_uri ) === $saved['actor_uri_hash'] && null === $saved['attribution_locked_at'] );

	$uuid = is_array( $saved ) ? (string) $saved['local_uuid'] : '';
	$object_uri = axismundi_note_object_uri( $uuid );
	ax_note_assert( $ax_note_results, 'the canonical object URI round-trips to the immutable local UUID', '' !== $uuid && $uuid === axismundi_note_local_uuid_from_uri( $object_uri ) && is_array( axismundi_note_get_by_uri( $object_uri ) ) );

	$resaved = axismundi_note_save( $post_id, array( 'visibility' => 'followers' ) );
	ax_note_assert( $ax_note_results, 'the local UUID is immutable across re-saves', is_array( $resaved ) && $uuid === $resaved['local_uuid'] && 'followers' === $resaved['visibility'] );

	$mentions = axismundi_note_mentions( get_post( $post_id ) );
	ax_note_assert( $ax_note_results, 'the read API returns the ordered union of explicit and body-derived mentions', in_array( $explicit_mention, $mentions, true ) && in_array( $derived_mention, $mentions, true ) && count( $mentions ) === count( array_unique( $mentions ) ) );

	// P1: an explicitly invalid visibility fails closed instead of widening to public.
	$bad_visibility = axismundi_note_save( $post_id, array( 'visibility' => 'followerss' ) );
	ax_note_assert( $ax_note_results, 'an explicitly invalid visibility is rejected rather than widened to public', is_wp_error( $bad_visibility ) && 'ax_note_visibility' === $bad_visibility->get_error_code() && 'followers' === axismundi_note_get( $post_id )['visibility'] );

	// P2: a malformed explicit mention rejects the whole save; a huge list is bounded.
	$bad_mention = axismundi_note_save( $post_id, array( 'mention_actor_uris' => array( $explicit_mention, 'not-a-uri' ) ) );
	$bulk        = array();
	for ( $i = 0; $i < 60; $i++ ) {
		$bulk[] = 'https://example.com/users/cap-' . $i;
	}
	$too_many = axismundi_note_save( $post_id, array( 'mention_actor_uris' => $bulk ) );
	ax_note_assert( $ax_note_results, 'a malformed or over-limit explicit mention list fails closed', is_wp_error( $bad_mention ) && 'ax_note_mention' === $bad_mention->get_error_code() && is_wp_error( $too_many ) && 'ax_note_mention_limit' === $too_many->get_error_code() );

	// P1: content warning is capped server-side rather than failing the DB write.
	$capped = axismundi_note_save( $post_id, array( 'content_warning' => str_repeat( 'x', 700 ) ) );
	ax_note_assert( $ax_note_results, 'an over-long content warning is capped to the column width', is_array( $capped ) && 500 === mb_strlen( (string) $capped['content_warning'] ) );

	// P2: only the exact canonical URI resolves; aliases are rejected.
	$alias_extra = (string) add_query_arg( 'extra', '1', $object_uri );
	$alias_path  = home_url( '/not-canonical/?ax_note=' . $uuid );
	$alias_frag  = $object_uri . '#fragment';
	$alias_dup   = $object_uri . '&ax_note=' . $uuid;
	ax_note_assert( $ax_note_results, 'non-canonical alias URIs including a duplicated key do not resolve to the Note UUID', null === axismundi_note_local_uuid_from_uri( $alias_extra ) && null === axismundi_note_local_uuid_from_uri( $alias_path ) && null === axismundi_note_local_uuid_from_uri( $alias_frag ) && null === axismundi_note_local_uuid_from_uri( $alias_dup ) );

	// F2: locking through the owned API freezes attribution against an author change.
	$lock  = axismundi_note_lock_attribution( $post_id );
	$other = ax_note_make_author( $ax_note_user_ids, $ax_note_actor_ids );
	wp_update_post( array( 'ID' => $post_id, 'post_author' => $other instanceof WP_User ? $other->ID : 0 ) );
	$locked = axismundi_note_save( $post_id, array( 'visibility' => 'public' ) );
	ax_note_assert( $ax_note_results, 'a locked envelope keeps its original attribution after an author change', true === $lock && is_array( $locked ) && $actor_uri === $locked['actor_uri'] && '' !== $actor_uri );

	// P1: locking a nonexistent envelope is an error, not a false success.
	ax_note_assert( $ax_note_results, 'locking attribution for a nonexistent Note is rejected', is_wp_error( axismundi_note_lock_attribution( 999999999 ) ) );

	// P1: a failed tombstone write aborts the permanent delete instead of orphaning.
	$abort_post = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $author instanceof WP_User ? $author->ID : 0, 'post_title' => 'Abort note' ) );
	$ax_note_post_ids[] = $abort_post;
	add_filter( 'query', 'ax_note_break_tombstone' );
	$abort_result = wp_delete_post( $abort_post, true );
	remove_filter( 'query', 'ax_note_break_tombstone' );
	$abort_env = axismundi_note_get( $abort_post );
	ax_note_assert( $ax_note_results, 'a failed tombstone write aborts the permanent delete and preserves the active envelope', false === $abort_result && get_post( $abort_post ) instanceof WP_Post && is_array( $abort_env ) && 'active' === $abort_env['object_status'] );

	// F1: a permanent delete tombstones the envelope instead of dropping it.
	wp_delete_post( $post_id, true );
	$tombstoned = axismundi_note_get_by_uuid( $uuid );
	ax_note_assert( $ax_note_results, 'a hard delete tombstones the envelope and preserves the canonical UUID', is_array( $tombstoned ) && 'tombstone' === $tombstoned['object_status'] && null !== $tombstoned['deleted_at'] && $uuid === $tombstoned['local_uuid'] );

	ax_note_assert( $ax_note_results, 'the envelope layer performs no HTTP request', 0 === $GLOBALS['ax_note_http'] );
} finally {
	$wpdb->prefix = $ax_note_real_prefix;
	remove_filter( 'query', 'ax_note_break_tombstone' );
	remove_filter( 'pre_http_request', 'ax_note_http' );
	if ( '' !== $ax_note_shadow_table ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- throwaway shadow teardown.
		$wpdb->query( "DROP TABLE IF EXISTS {$ax_note_shadow_table}" );
	}
	foreach ( array_unique( $ax_note_post_ids ) as $pid ) {
		$wpdb->delete( axismundi_note_table(), array( 'post_id' => (int) $pid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( get_post( (int) $pid ) instanceof WP_Post ) {
			wp_delete_post( (int) $pid, true );
		}
	}
	foreach ( array_unique( $ax_note_actor_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $actor_table ) {
			$wpdb->delete( $actor_table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( ! empty( $ax_note_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_note_user_ids ) as $uid ) {
			if ( get_userdata( (int) $uid ) ) {
				wp_delete_user( (int) $uid );
			}
		}
	}
}

$ax_note_failures = count( array_filter( $ax_note_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_note_results ), $ax_note_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_note_failures > 0 ? 1 : 0 );
}
exit( $ax_note_failures > 0 ? 1 : 0 );
