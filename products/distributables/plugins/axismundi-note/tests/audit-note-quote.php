<?php
/**
 * Outbound Quote target/policy storage and three-way classification (dev-only).
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_note_quote_results        = array();
$ax_note_quote_post_ids       = array();
$ax_note_quote_user_ids       = array();
$ax_note_quote_actor_ids      = array();
$ax_note_quote_remote         = '';
$ax_note_quote_spoof_remote   = '';
$ax_note_quote_unknown_remote = '';
$ax_note_quote_gone_remote    = '';
$ax_note_quote_shadow_table   = '';
$ax_note_quote_real_prefix    = $wpdb->prefix;
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

/** Create one cached remote Actor with deliverable inbox/outbox endpoints. */
function ax_note_quote_make_remote_actor( array &$actor_ids, string $slug ) : ?Axismundi_Actor {
	$uri     = 'https://example.com/actors/' . $slug;
	$payload = array(
		'id'                => $uri,
		'type'              => 'Person',
		'preferredUsername' => $slug,
		'inbox'             => $uri . '/inbox',
		'outbox'            => $uri . '/outbox',
	);
	$record = axismundi_actors_normalize_remote_actor_payload( $payload, $uri );
	$actor  = is_array( $record ) ? axismundi_actors_upsert_remote( $record ) : null;
	if ( $actor instanceof Axismundi_Actor ) {
		$actor_ids[] = $actor->get_identity_id();
		return $actor;
	}
	return null;
}

try {
	add_filter( 'pre_http_request', 'ax_note_quote_http' );

	// The v4 migration verifies its own columns and index, same discipline as v1.
	$installed = axismundi_note_install_table();
	$table     = axismundi_note_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixture schema probe.
	$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixture schema probe.
	$quote_key = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'quote_target_uri_hash'", ARRAY_A );
	ax_note_quote_assert( $ax_note_quote_results, 'schema v4 installs the quote target, anyone-default policy, and supporting index', $installed && in_array( 'quote_target_uri', $columns, true ) && in_array( 'quote_target_uri_hash', $columns, true ) && in_array( 'quote_policy', $columns, true ) && ! empty( $quote_key ) );

	// P2: exercise the real v1->v2 ALTER path against a populated table, not just a
	// fresh install -- a hand-built v1-shaped table (the schema before this slice,
	// with no quote columns) carrying one existing row, then upgraded in place.
	$shadow_prefix = $ax_note_quote_real_prefix . 'axnq_shadow_' . strtolower( wp_generate_password( 6, false, false ) ) . '_';
	$wpdb->prefix  = $shadow_prefix;
	$shadow_table  = axismundi_note_table();
	$ax_note_quote_shadow_table = $shadow_table;
	$charset       = $wpdb->get_charset_collate();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- throwaway v1-shaped fixture table.
	$wpdb->query(
		"CREATE TABLE {$shadow_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			local_uuid char(36) NOT NULL,
			actor_uri text NOT NULL,
			actor_uri_hash char(64) NOT NULL,
			visibility varchar(16) NOT NULL,
			language_tag varchar(35) NOT NULL,
			in_reply_to_uri text NOT NULL,
			in_reply_to_uri_hash char(64) NOT NULL,
			context_uri text NOT NULL,
			context_uri_hash char(64) NOT NULL,
			is_sensitive tinyint(1) unsigned NOT NULL DEFAULT 0,
			content_warning varchar(500) NOT NULL DEFAULT '',
			mention_actor_uris_json longtext NOT NULL,
			object_status varchar(16) NOT NULL DEFAULT 'active',
			attribution_locked_at datetime NULL,
			deleted_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_id (post_id),
			UNIQUE KEY local_uuid (local_uuid),
			KEY actor_uri_hash (actor_uri_hash),
			KEY in_reply_to_uri_hash (in_reply_to_uri_hash),
			KEY context_uri_hash (context_uri_hash),
			KEY object_status (object_status)
		) ENGINE=InnoDB {$charset}"
	);
	$fixture_uuid  = wp_generate_uuid4();
	$fixture_actor = 'https://example.com/actors/pre-migration';
	$now           = current_time( 'mysql', true );
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- throwaway v1-shaped fixture row.
		$shadow_table,
		array(
			'post_id'                 => 999001,
			'local_uuid'              => $fixture_uuid,
			'actor_uri'               => $fixture_actor,
			'actor_uri_hash'          => hash( 'sha256', $fixture_actor ),
			'visibility'              => 'public',
			'language_tag'            => 'en',
			'in_reply_to_uri'         => '',
			'in_reply_to_uri_hash'    => '',
			'context_uri'             => '',
			'context_uri_hash'        => '',
			'is_sensitive'            => 0,
			'content_warning'         => '',
			'mention_actor_uris_json' => '[]',
			'object_status'           => 'active',
			'created_at'              => $now,
			'updated_at'              => $now,
		)
	);
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- throwaway v1-shaped fixture probe.
	$pre_row_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$shadow_table} WHERE local_uuid = %s", $fixture_uuid ) );

	$upgraded = axismundi_note_install_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- throwaway v1-shaped fixture probe.
	$post_upgrade_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$shadow_table} WHERE local_uuid = %s", $fixture_uuid ), ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- throwaway v1-shaped fixture probe.
	$post_columns = $wpdb->get_col( "SHOW COLUMNS FROM {$shadow_table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- throwaway v1-shaped fixture probe.
	$post_quote_key = $wpdb->get_results( "SHOW INDEX FROM {$shadow_table} WHERE Key_name = 'quote_target_uri_hash'", ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- throwaway shadow teardown.
	$wpdb->query( "DROP TABLE IF EXISTS {$shadow_table}" );
	$ax_note_quote_shadow_table = '';
	$wpdb->prefix = $ax_note_quote_real_prefix;

	ax_note_quote_assert(
		$ax_note_quote_results,
		'a v1 table with an existing row upgrades to v4 in place and receives the new anyone default',
		1 === $pre_row_count
		&& $upgraded
		&& is_array( $post_upgrade_row )
		&& $fixture_uuid === $post_upgrade_row['local_uuid']
		&& in_array( 'quote_target_uri', $post_columns, true )
		&& in_array( 'quote_target_uri_hash', $post_columns, true )
		&& in_array( 'quote_policy', $post_columns, true )
		&& ! empty( $post_quote_key )
		&& '' === (string) ( $post_upgrade_row['quote_target_uri'] ?? '' )
		&& '' === (string) $post_upgrade_row['quote_target_uri_hash']
		&& 'anyone' === (string) $post_upgrade_row['quote_policy']
	);

	$author = ax_note_quote_make_author( $ax_note_quote_user_ids, $ax_note_quote_actor_ids );
	$other  = ax_note_quote_make_author( $ax_note_quote_user_ids, $ax_note_quote_actor_ids );
	$actor       = $author instanceof WP_User ? axismundi_actors_get_for_user( $author->ID ) : null;
	$other_actor = $other instanceof WP_User ? axismundi_actors_get_for_user( $other->ID ) : null;
	$actor_uri       = $actor instanceof Axismundi_Actor ? $actor->get_uri() : '';
	$other_actor_uri = $other_actor instanceof Axismundi_Actor ? $other_actor->get_uri() : '';
	$anyone_policy   = axismundi_note_quote_interaction_policy( array( 'actor_uri' => $actor_uri, 'quote_policy' => 'anyone' ) );
	$followers_policy = axismundi_note_quote_interaction_policy( array( 'actor_uri' => $actor_uri, 'quote_policy' => 'followers' ) );
	$me_policy       = axismundi_note_quote_interaction_policy( array( 'actor_uri' => $actor_uri, 'quote_policy' => 'me' ) );
	ax_note_quote_assert(
		$ax_note_quote_results,
		'the three authored Quote-policy projections preserve Public, Followers, and author-only semantics',
		axismundi_act_public_audience_uri() === $anyone_policy['canQuote']['automaticApproval']
		&& $actor instanceof Axismundi_Actor
		&& axismundi_op_actor_followers_url( $actor ) === $followers_policy['canQuote']['automaticApproval']
		&& $actor_uri === $me_policy['canQuote']['automaticApproval']
	);

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

	$policy_saved = axismundi_note_save( $quoting_id, array( 'quote_policy' => 'followers' ) );
	ax_note_quote_assert( $ax_note_quote_results, 'saving a Quote policy preserves the explicit automatic-approval choice', is_array( $policy_saved ) && 'followers' === $policy_saved['quote_policy'] );

	$rest_saved = axismundi_note_save_envelope( $quoting_id, array( 'quoteTarget' => $own_uri, 'quotePolicy' => 'me' ) );
	ax_note_quote_assert( $ax_note_quote_results, 'the structured REST save writes quoteTarget and quotePolicy through the same contract', is_array( $rest_saved ) && $own_uri === $rest_saved['quote_target_uri'] && 'me' === $rest_saved['quote_policy'] );

	$bad_policy = axismundi_note_save( $quoting_id, array( 'quote_policy' => 'surprise-me' ) );
	ax_note_quote_assert( $ax_note_quote_results, 'an invalid Quote policy fails closed instead of widening or clearing consent', is_wp_error( $bad_policy ) && 'ax_note_quote_policy' === $bad_policy->get_error_code() && 'me' === axismundi_note_get( $quoting_id )['quote_policy'] );

	$absent = axismundi_note_save( $quoting_id, array( 'visibility' => 'public' ) );
	ax_note_quote_assert( $ax_note_quote_results, 'an absent quote_target_uri field preserves the stored value', is_array( $absent ) && $own_uri === $absent['quote_target_uri'] );

	// P1: an explicitly invalid, non-empty quote target must fail closed rather than
	// silently clearing -- the author's expressed quote intent must never be dropped
	// into an ungated ordinary Create.
	$rejected = axismundi_note_save( $quoting_id, array( 'quote_target_uri' => 'not-a-uri' ) );
	ax_note_quote_assert( $ax_note_quote_results, 'an explicitly invalid quote target fails closed instead of silently clearing it', is_wp_error( $rejected ) && 'ax_note_quote_target_uri' === $rejected->get_error_code() && $own_uri === axismundi_note_get( $quoting_id )['quote_target_uri'] );

	$non_string = axismundi_note_save( $quoting_id, array( 'quote_target_uri' => array( $own_uri ) ) );
	ax_note_quote_assert( $ax_note_quote_results, 'a non-string quote target fails closed instead of being coerced into a clear', is_wp_error( $non_string ) && 'ax_note_quote_target_uri' === $non_string->get_error_code() && $own_uri === axismundi_note_get( $quoting_id )['quote_target_uri'] );

	// An explicit empty string is a deliberate clear, distinct from the malformed case above.
	$cleared = axismundi_note_save( $quoting_id, array( 'quote_target_uri' => '' ) );
	ax_note_quote_assert( $ax_note_quote_results, 'an explicit empty quote target clears the stored value', is_array( $cleared ) && '' === $cleared['quote_target_uri'] && '' === $cleared['quote_target_uri_hash'] );

	// Restore a target for the classification checks below.
	axismundi_note_save( $quoting_id, array( 'quote_target_uri' => $own_uri ) );

	// Classification: self.
	$self_class = axismundi_note_quote_classify( $own_uri, $actor_uri );
	ax_note_quote_assert( $ax_note_quote_results, 'quoting one\'s own local Note classifies as self', AXISMUNDI_NOTE_QUOTE_SELF === $self_class );

	// Classification: local-other.
	$local_other_class = axismundi_note_quote_classify( $other_uri, $actor_uri );
	ax_note_quote_assert( $ax_note_quote_results, 'quoting a different local Actor\'s Note classifies as local-other', AXISMUNDI_NOTE_QUOTE_LOCAL_OTHER === $local_other_class );

	// Classification: remote, seeded through the Object Projections remote-object cache
	// (a read of a stored observation, not a fetch).
	$remote_actor = ax_note_quote_make_remote_actor( $ax_note_quote_actor_ids, 'quote-target-' . strtolower( wp_generate_password( 6, false, false ) ) );
	$remote_actor_uri = $remote_actor instanceof Axismundi_Actor ? $remote_actor->get_uri() : '';
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
	ax_note_quote_assert( $ax_note_quote_results, 'quoting a cached object attributed to a registered remote Actor classifies as remote', $remote_actor instanceof Axismundi_Actor && is_array( $stored_remote ) && AXISMUNDI_NOTE_QUOTE_REMOTE === $remote_class );

	// A cached Object alone is insufficient: without a registered Actor and inbox,
	// Bridge cannot address the QuoteRequest and the quoting Note would remain held.
	$unknown_actor_uri = 'https://remote.example/actors/unknown-' . strtolower( wp_generate_password( 6, false, false ) );
	$ax_note_quote_unknown_remote = 'https://remote.example/objects/unknown-actor-' . strtolower( wp_generate_password( 6, false, false ) );
	$stored_unknown = axismundi_op_remote_object_store(
		array(
			'id'           => $ax_note_quote_unknown_remote,
			'type'         => 'Note',
			'attributedTo' => $unknown_actor_uri,
			'content'      => 'Remote note with no cached Actor.',
		)
	);
	$unknown_actor_class = axismundi_note_quote_classify( $ax_note_quote_unknown_remote, $actor_uri );
	ax_note_quote_assert( $ax_note_quote_results, 'a cached object whose remote Actor is unregistered remains unresolved', is_array( $stored_unknown ) && is_wp_error( $unknown_actor_class ) && 'ax_note_quote_unresolved' === $unknown_actor_class->get_error_code() );

	$gone_actor = ax_note_quote_make_remote_actor( $ax_note_quote_actor_ids, 'gone-' . strtolower( wp_generate_password( 6, false, false ) ) );
	if ( $gone_actor instanceof Axismundi_Actor ) {
		axismundi_actors_set_status( $gone_actor->get_identity_id(), 'tombstone' );
	}
	$ax_note_quote_gone_remote = 'https://remote.example/objects/gone-actor-' . strtolower( wp_generate_password( 6, false, false ) );
	$stored_gone = axismundi_op_remote_object_store(
		array(
			'id'           => $ax_note_quote_gone_remote,
			'type'         => 'Note',
			'attributedTo' => $gone_actor instanceof Axismundi_Actor ? $gone_actor->get_uri() : '',
			'content'      => 'Remote note whose Actor is gone.',
		)
	);
	$gone_class = axismundi_note_quote_classify( $ax_note_quote_gone_remote, $actor_uri );
	ax_note_quote_assert( $ax_note_quote_results, 'a cached object attributed to a tombstoned remote Actor is refused', is_array( $stored_gone ) && is_wp_error( $gone_class ) && 'ax_note_quote_actor_gone' === $gone_class->get_error_code() );

	// P1: a remote-cache row claiming attributedTo = a real local Actor URI must never
	// earn self/local-other -- that is a spoofed or stale observation, not evidence the
	// object is actually ours, so it must fail closed instead of skipping QuoteRequest.
	$ax_note_quote_spoof_remote = 'https://remote.example/objects/spoofed-' . strtolower( wp_generate_password( 6, false, false ) );
	$stored_spoof = axismundi_op_remote_object_store(
		array(
			'id'           => $ax_note_quote_spoof_remote,
			'type'         => 'Note',
			'attributedTo' => $other_actor_uri,
			'content'      => 'Spoofed remote note claiming local attribution.',
		)
	);
	$spoof_class = axismundi_note_quote_classify( $ax_note_quote_spoof_remote, $actor_uri );
	ax_note_quote_assert( $ax_note_quote_results, 'a remote-cache object claiming a local Actor URI fails closed rather than classifying as local', is_array( $stored_spoof ) && is_wp_error( $spoof_class ) && 'ax_note_quote_origin_mismatch' === $spoof_class->get_error_code() );

	// Classification: unresolved (never cached and not a local Note).
	$unknown_class = axismundi_note_quote_classify( 'https://remote.example/objects/never-seen', $actor_uri );
	ax_note_quote_assert( $ax_note_quote_results, 'an uncached, unowned target is unresolved rather than misclassified', is_wp_error( $unknown_class ) && 'ax_note_quote_unresolved' === $unknown_class->get_error_code() );

	// Invalid input fails closed rather than guessing.
	$blank_class = axismundi_note_quote_classify( '', $actor_uri );
	ax_note_quote_assert( $ax_note_quote_results, 'an empty target URI fails closed', is_wp_error( $blank_class ) && 'ax_note_quote_target' === $blank_class->get_error_code() );

	ax_note_quote_assert( $ax_note_quote_results, 'quote storage and classification perform no HTTP request', 0 === $GLOBALS['ax_note_quote_http'] );
} finally {
	remove_filter( 'pre_http_request', 'ax_note_quote_http' );
	$wpdb->prefix = $ax_note_quote_real_prefix;
	if ( '' !== $ax_note_quote_shadow_table ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- throwaway shadow teardown.
		$wpdb->query( "DROP TABLE IF EXISTS {$ax_note_quote_shadow_table}" );
	}
	if ( '' !== $ax_note_quote_remote && function_exists( 'axismundi_op_remote_object_delete' ) ) {
		axismundi_op_remote_object_delete( $ax_note_quote_remote );
	}
	if ( '' !== $ax_note_quote_spoof_remote && function_exists( 'axismundi_op_remote_object_delete' ) ) {
		axismundi_op_remote_object_delete( $ax_note_quote_spoof_remote );
	}
	foreach ( array( $ax_note_quote_unknown_remote, $ax_note_quote_gone_remote ) as $remote_uri ) {
		if ( '' !== $remote_uri && function_exists( 'axismundi_op_remote_object_delete' ) ) {
			axismundi_op_remote_object_delete( $remote_uri );
		}
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
