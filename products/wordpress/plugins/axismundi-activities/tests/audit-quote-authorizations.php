<?php
/**
 * FEP-044f QuoteAuthorization store regression (dev-only; dist-excluded).
 *
 * Locks the consent contract: one request issues at most one authorization, the identity is
 * minted here and never reassigned, revocation keeps the row, and hash lookups verify the
 * full URI. Consent state is deliberately independent of whether a quote Object exists —
 * this store never claims to know that (SPEC §19).
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/quote-authorizations.php';

global $wpdb;
$ax_qa_results = array();
$ax_qa_uuids   = array();
$ax_qa_suffix  = strtolower( wp_generate_password( 8, false, false ) );
// Declared up front so `finally` can restore the prefix before any cleanup resolves a table
// name, even if the migration block throws while $wpdb->prefix is redirected.
$ax_qa_real_prefix   = $wpdb->prefix;
$ax_qa_real_version  = get_option( AXISMUNDI_ACT_DB_VERSION_OPTION );
$ax_qa_shadow_prefix = '';

/**
 * @param array  $results   Accumulator.
 * @param string $label     Assertion label.
 * @param bool   $condition Result.
 */
function ax_qa_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Count revocation hook firings. */
function ax_qa_count_revoked_hook() : void {
	++$GLOBALS['ax_qa_revoked_hook'];
}

try {
	ax_qa_assert( $ax_qa_results, 'the verified consent schema installs before its version is recorded', axismundi_act_install() && axismundi_act_quote_authorizations_ready() );

	$ax_qa_request = 'https://remote.example/users/bob/statuses/1/quote-' . $ax_qa_suffix;
	$ax_qa_quoting = 'https://remote.example/users/bob/statuses/' . $ax_qa_suffix;
	$ax_qa_quoted  = 'https://local.example/?p=' . wp_rand( 1000, 9999 );
	$ax_qa_bob     = 'https://remote.example/users/bob';
	$ax_qa_alice   = 'https://local.example/actors/alice-' . $ax_qa_suffix;
	$ax_qa_args    = array(
		'request_activity_uri' => $ax_qa_request,
		'quoting_object_uri'   => $ax_qa_quoting,
		'quoted_object_uri'    => $ax_qa_quoted,
		'requester_actor_uri'  => $ax_qa_bob,
		'author_actor_uri'     => $ax_qa_alice,
	);

	$ax_qa_issued = axismundi_act_issue_quote_authorization( $ax_qa_args );
	if ( is_array( $ax_qa_issued ) ) {
		$ax_qa_uuids[] = $ax_qa_issued['uuid'];
	}
	ax_qa_assert(
		$ax_qa_results,
		'issuing mints a local identity that resolves back to its own UUID',
		is_array( $ax_qa_issued )
			&& 'active' === $ax_qa_issued['status']
			&& axismundi_act_quote_authorization_uri( $ax_qa_issued['uuid'] ) === $ax_qa_issued['authorization_uri']
			&& $ax_qa_issued['uuid'] === axismundi_act_quote_authorization_uuid_from_uri( $ax_qa_issued['authorization_uri'] )
	);

	ax_qa_assert(
		$ax_qa_results,
		'the authorization records both Objects and both Actors without conflating them',
		is_array( $ax_qa_issued )
			&& $ax_qa_quoting === $ax_qa_issued['quoting_object_uri']
			&& $ax_qa_quoted === $ax_qa_issued['quoted_object_uri']
			&& $ax_qa_bob === $ax_qa_issued['requester_actor_uri']
			&& $ax_qa_alice === $ax_qa_issued['author_actor_uri']
			&& $ax_qa_request === $ax_qa_issued['request_activity_uri']
	);

	// A re-delivered QuoteRequest must return the decision already issued, never a second
	// identity for the same consent.
	$ax_qa_replay = axismundi_act_issue_quote_authorization( $ax_qa_args );
	$ax_qa_table  = axismundi_act_quote_authorizations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table name; fixture row count.
	$ax_qa_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ax_qa_table} WHERE request_activity_uri_hash = %s", hash( 'sha256', $ax_qa_request ) ) );
	ax_qa_assert(
		$ax_qa_results,
		'a re-delivered request returns the existing authorization instead of minting a second identity',
		is_array( $ax_qa_replay )
			&& is_array( $ax_qa_issued )
			&& $ax_qa_replay['uuid'] === $ax_qa_issued['uuid']
			&& 1 === $ax_qa_rows
	);

	ax_qa_assert(
		$ax_qa_results,
		'lookup by request URI and by the standing triple resolve the same authorization',
		is_array( $ax_qa_issued )
			&& ( axismundi_act_get_quote_authorization_for_request( $ax_qa_request )['uuid'] ?? '' ) === $ax_qa_issued['uuid']
			&& ( axismundi_act_get_active_quote_authorization( $ax_qa_quoting, $ax_qa_quoted, $ax_qa_alice )['uuid'] ?? '' ) === $ax_qa_issued['uuid']
	);

	// The hash is an accelerator, not the identity.
	$ax_qa_forged = axismundi_act_get_quote_authorization( 'https://evil.example/?ax_quote_authorization=' . ( $ax_qa_issued['uuid'] ?? '' ) );
	ax_qa_assert(
		$ax_qa_results,
		'a foreign URI carrying a known UUID resolves nothing',
		null === $ax_qa_forged
			&& null === axismundi_act_quote_authorization_uuid_from_uri( 'https://evil.example/?ax_quote_authorization=' . ( $ax_qa_issued['uuid'] ?? '' ) )
	);

	// Same host is not the same identity: the path and the query shape are part of the URI
	// this site actually minted.
	$ax_qa_uuid_only = (string) ( $ax_qa_issued['uuid'] ?? '' );
	ax_qa_assert(
		$ax_qa_results,
		'a same-host URI at another path, with extra arguments, or carrying credentials is not the canonical identity',
		null === axismundi_act_quote_authorization_uuid_from_uri( home_url( '/not-canonical/' ) . '?ax_quote_authorization=' . $ax_qa_uuid_only )
			&& null === axismundi_act_quote_authorization_uuid_from_uri( add_query_arg( 'extra', '1', axismundi_act_quote_authorization_uri( $ax_qa_uuid_only ) ) )
			&& null === axismundi_act_quote_authorization_uuid_from_uri( axismundi_act_quote_authorization_uri( $ax_qa_uuid_only ) . '#frag' )
			&& $ax_qa_uuid_only === axismundi_act_quote_authorization_uuid_from_uri( axismundi_act_quote_authorization_uri( $ax_qa_uuid_only ) )
	);

	// One standing consent per triple, whatever request asked for it.
	$ax_qa_other_request = axismundi_act_issue_quote_authorization( array_merge( $ax_qa_args, array( 'request_activity_uri' => $ax_qa_request . '-other' ) ) );
	$ax_qa_active_rows   = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table name; fixture row count.
		$wpdb->prepare( "SELECT COUNT(*) FROM {$ax_qa_table} WHERE quoting_object_uri_hash = %s AND status = 'active'", hash( 'sha256', $ax_qa_quoting ) )
	);
	ax_qa_assert(
		$ax_qa_results,
		'a different request for the same triple returns the standing authorization instead of issuing a second one',
		is_array( $ax_qa_other_request )
			&& $ax_qa_other_request['uuid'] === ( $ax_qa_issued['uuid'] ?? '' )
			&& 1 === $ax_qa_active_rows
	);

	$ax_qa_self = axismundi_act_issue_quote_authorization( array_merge( $ax_qa_args, array( 'request_activity_uri' => $ax_qa_request . '-self', 'quoting_object_uri' => $ax_qa_quoted ) ) );
	ax_qa_assert(
		$ax_qa_results,
		'an Object cannot hold an authorization to quote itself',
		is_wp_error( $ax_qa_self ) && 'ax_act_quote_auth_self' === $ax_qa_self->get_error_code()
	);

	$ax_qa_incomplete = axismundi_act_issue_quote_authorization( array_merge( $ax_qa_args, array( 'request_activity_uri' => $ax_qa_request . '-x', 'author_actor_uri' => '' ) ) );
	ax_qa_assert(
		$ax_qa_results,
		'an authorization without both Objects and both Actors is refused',
		is_wp_error( $ax_qa_incomplete ) && 'ax_act_quote_auth_args' === $ax_qa_incomplete->get_error_code()
	);

	// Revocation withdraws consent without erasing the record. The URI must keep meaning
	// "revoked" rather than "never existed".
	$ax_qa_revoked = axismundi_act_revoke_quote_authorization( (string) ( $ax_qa_issued['authorization_uri'] ?? '' ), 'fixture' );
	ax_qa_assert(
		$ax_qa_results,
		'revoking keeps the row and its identity while withdrawing the standing authorization',
		is_array( $ax_qa_revoked )
			&& 'revoked' === $ax_qa_revoked['status']
			&& null !== $ax_qa_revoked['revoked_at']
			&& $ax_qa_revoked['uuid'] === ( $ax_qa_issued['uuid'] ?? '' )
			&& null !== axismundi_act_get_quote_authorization( $ax_qa_revoked['authorization_uri'] )
			&& null === axismundi_act_get_active_quote_authorization( $ax_qa_quoting, $ax_qa_quoted, $ax_qa_alice )
	);

	// Step 5 hangs Delete forwarding on this hook, so a lost race must not fire it twice.
	$GLOBALS['ax_qa_revoked_hook'] = 0;
	add_action( 'axismundi_act_quote_authorization_revoked', 'ax_qa_count_revoked_hook' );

	// Idempotent, and the original revocation timestamp is not overwritten by the second call.
	$ax_qa_revoke_again = axismundi_act_revoke_quote_authorization( (string) ( $ax_qa_issued['authorization_uri'] ?? '' ) );
	ax_qa_assert(
		$ax_qa_results,
		'revoking twice returns the same withdrawal rather than erroring or moving its timestamp',
		is_array( $ax_qa_revoke_again )
			&& 'revoked' === $ax_qa_revoke_again['status']
			&& null !== $ax_qa_revoke_again['revoked_at']
			&& $ax_qa_revoke_again['revoked_at'] === ( $ax_qa_revoked['revoked_at'] ?? '' )
			&& $ax_qa_revoke_again['uuid'] === ( $ax_qa_revoked['uuid'] ?? '' )
	);

	// Simulate the lost race directly: the row is already revoked, so the conditional UPDATE
	// changes nothing and the caller must not announce a withdrawal it did not perform.
	$GLOBALS['ax_qa_revoked_hook'] = 0;
	axismundi_act_revoke_quote_authorization( (string) ( $ax_qa_issued['authorization_uri'] ?? '' ) );
	remove_action( 'axismundi_act_quote_authorization_revoked', 'ax_qa_count_revoked_hook' );
	ax_qa_assert(
		$ax_qa_results,
		'a caller that did not win the withdrawal does not fire the revocation hook',
		0 === $GLOBALS['ax_qa_revoked_hook']
	);


	// A revoked authorization still occupies its request, so the same request cannot be
	// replayed into a fresh grant.
	$ax_qa_after_revoke = axismundi_act_issue_quote_authorization( $ax_qa_args );
	ax_qa_assert(
		$ax_qa_results,
		'a revoked decision is not re-granted by replaying its original request',
		is_array( $ax_qa_after_revoke ) && 'revoked' === $ax_qa_after_revoke['status'] && $ax_qa_after_revoke['uuid'] === ( $ax_qa_issued['uuid'] ?? '' )
	);

	// A new request for the same pair is a new decision and gets its own identity.
	$ax_qa_second = axismundi_act_issue_quote_authorization( array_merge( $ax_qa_args, array( 'request_activity_uri' => $ax_qa_request . '-2' ) ) );
	if ( is_array( $ax_qa_second ) ) {
		$ax_qa_uuids[] = $ax_qa_second['uuid'];
	}
	ax_qa_assert(
		$ax_qa_results,
		'a fresh request for the same pair mints a new identity and stands again',
		is_array( $ax_qa_second )
			&& 'active' === $ax_qa_second['status']
			&& $ax_qa_second['uuid'] !== ( $ax_qa_issued['uuid'] ?? '' )
			&& ( axismundi_act_get_active_quote_authorization( $ax_qa_quoting, $ax_qa_quoted, $ax_qa_alice )['uuid'] ?? '' ) === $ax_qa_second['uuid']
	);

	// With $ax_qa_second standing, the unique index — not the courtesy read in the issue
	// path — is what forbids a second standing consent. Bypass the code to prove it.
	$ax_qa_dup_uri = 'https://x.example/dup-' . $ax_qa_suffix;
	$ax_qa_forced  = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- deliberate constraint probe.
		$ax_qa_table,
		array(
			'local_uuid' => wp_generate_uuid4(), 'authorization_uri' => $ax_qa_dup_uri, 'authorization_uri_hash' => hash( 'sha256', $ax_qa_dup_uri ),
			'request_activity_uri' => $ax_qa_request . '-forced', 'request_activity_uri_hash' => hash( 'sha256', $ax_qa_request . '-forced' ),
			'quoted_object_uri' => $ax_qa_quoted, 'quoted_object_uri_hash' => hash( 'sha256', $ax_qa_quoted ),
			'quoting_object_uri' => $ax_qa_quoting, 'quoting_object_uri_hash' => hash( 'sha256', $ax_qa_quoting ),
			'requester_actor_uri' => $ax_qa_bob, 'requester_actor_uri_hash' => hash( 'sha256', $ax_qa_bob ),
			'author_actor_uri' => $ax_qa_alice, 'author_actor_uri_hash' => hash( 'sha256', $ax_qa_alice ),
			'status' => 'active',
			'standing_key' => axismundi_act_quote_standing_key( $ax_qa_quoting, $ax_qa_quoted, $ax_qa_alice ),
			'created_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ),
		)
	);
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table name; fixture row count.
	$ax_qa_revoked_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ax_qa_table} WHERE quoting_object_uri_hash = %s AND status = 'revoked'", hash( 'sha256', $ax_qa_quoting ) ) );
	ax_qa_assert(
		$ax_qa_results,
		'the database refuses a second standing authorization for one triple, while revoked rows for that triple still accumulate',
		false === $ax_qa_forced && $ax_qa_revoked_rows >= 1
	);

	ax_qa_assert(
		$ax_qa_results,
		'revoking an unknown authorization is an error rather than a silent success',
		is_wp_error( axismundi_act_revoke_quote_authorization( axismundi_act_quote_authorization_uri( wp_generate_uuid4() ) ) )
	);

	// The v5 -> v6 upgrade runs once per site. Reproduce it on a throwaway prefix rather
	// than the shared ledger: dropping a real table to prove a migration would destroy the
	// consent record it is supposed to protect.
	$ax_qa_shadow_prefix = $ax_qa_real_prefix . 'axqa' . $ax_qa_suffix . '_';
	$wpdb->prefix        = $ax_qa_shadow_prefix;
	$ax_qa_shadow        = axismundi_act_quote_authorizations_table();
	axismundi_act_install();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- throwaway fixture table.
	$wpdb->query( "DROP TABLE IF EXISTS {$ax_qa_shadow}" );
	delete_option( AXISMUNDI_ACT_DB_VERSION_OPTION );
	$ax_qa_absent = ! (bool) $wpdb->get_var( "SHOW TABLES LIKE '{$ax_qa_shadow}'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- throwaway fixture table.
	$ax_qa_before = (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' );

	$ax_qa_migrated = axismundi_act_install();

	$ax_qa_present = (bool) $wpdb->get_var( "SHOW TABLES LIKE '{$ax_qa_shadow}'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- throwaway fixture table.
	$wpdb->prefix  = $ax_qa_real_prefix;
	update_option( AXISMUNDI_ACT_DB_VERSION_OPTION, $ax_qa_real_version, false );

	ax_qa_assert(
		$ax_qa_results,
		'a site without the consent table gains it on upgrade, and the version is withheld until it exists',
		$ax_qa_absent && '' === $ax_qa_before && $ax_qa_migrated && $ax_qa_present
	);

	$ax_qa_real_table = axismundi_act_quote_authorizations_table();
	ax_qa_assert(
		$ax_qa_results,
		'the migration test leaves the real consent table and version untouched',
		$wpdb->prefix === $ax_qa_real_prefix
			&& AXISMUNDI_ACT_DB_VERSION === (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION )
			&& (bool) $wpdb->get_var( "SHOW TABLES LIKE '{$ax_qa_real_table}'" ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- real-table safety check.
	);
} finally {
	// Before any cleanup resolves a table name.
	$wpdb->prefix = $ax_qa_real_prefix;
	update_option( AXISMUNDI_ACT_DB_VERSION_OPTION, $ax_qa_real_version, false );
	if ( '' !== $ax_qa_shadow_prefix ) {
		foreach ( array( 'ax_quote_authorizations', 'ax_activities', 'ax_activity_relations' ) as $ax_qa_shadow_name ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$ax_qa_shadow_prefix}{$ax_qa_shadow_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- fixture-owned throwaway table.
		}
	}
	$ax_qa_table = axismundi_act_quote_authorizations_table();
	foreach ( array_unique( array_filter( $ax_qa_uuids ) ) as $ax_qa_uuid ) {
		$wpdb->delete( $ax_qa_table, array( 'local_uuid' => $ax_qa_uuid ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
}

$ax_qa_failed = count( array_filter( $ax_qa_results, static fn( $r ) => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_qa_results ), $ax_qa_failed );
exit( $ax_qa_failed > 0 ? 1 : 0 );
