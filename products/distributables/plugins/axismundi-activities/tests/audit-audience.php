<?php
/**
 * Shared audience resolver regression (dev-only).
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/audience.php';

$ax_aud_results = array();
$GLOBALS['ax_aud_http'] = 0;
$ax_aud_identity_id = 0;

/** @param bool[] $results Results. */
function ax_aud_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Prove the resolver performs no transport. */
function ax_aud_http( $preempt ) {
	++$GLOBALS['ax_aud_http'];
	return $preempt;
}

try {
	add_filter( 'pre_http_request', 'ax_aud_http' );

	$public = axismundi_act_public_audience_uri();
	$site   = axismundi_actors_create_local(
		array(
			'actor_type'        => 'Person',
			'actor_scope'       => 'user',
			'preferred_username' => 'audience-author-' . strtolower( wp_generate_password( 8, false, false ) ),
		)
	);
	if ( $site instanceof Axismundi_Actor ) {
		$ax_aud_identity_id = $site->get_identity_id();
		axismundi_actors_set_status( $ax_aud_identity_id, 'public' );
		$site = axismundi_actors_get_by_identity( $ax_aud_identity_id );
	}
	$is_local  = $site instanceof Axismundi_Actor && $site->is_local() && 'public' === $site->get_status();
	$followers = $is_local && function_exists( 'axismundi_op_actor_followers_url' ) ? (string) axismundi_op_actor_followers_url( $site ) : '';
	ax_aud_assert( $ax_aud_results, 'the fixture Actor is public, local, and exposes a followers collection URI', $is_local && '' !== $followers );

	$mention = 'https://example.com/users/mention-' . strtolower( wp_generate_password( 8, false, false ) );

	$public_res = axismundi_act_resolve_audience( $site, 'public', array( $mention ) );
	ax_aud_assert( $ax_aud_results, 'public addresses Public in to and followers plus mentions in cc', is_array( $public_res ) && array( $public ) === $public_res['to'] && in_array( $followers, $public_res['cc'], true ) && in_array( $mention, $public_res['cc'], true ) && ! in_array( $public, $public_res['cc'], true ) && true === $public_res['public'] && 'public' === $public_res['visibility'] );

	$quiet_res = axismundi_act_resolve_audience( $site, 'quiet_public', array( $mention ) );
	ax_aud_assert( $ax_aud_results, 'quiet_public canonicalizes to unlisted with followers in to and Public plus mentions in cc', is_array( $quiet_res ) && 'unlisted' === $quiet_res['visibility'] && array( $followers ) === $quiet_res['to'] && in_array( $public, $quiet_res['cc'], true ) && in_array( $mention, $quiet_res['cc'], true ) && true === $quiet_res['public'] );

	$followers_res = axismundi_act_resolve_audience( $site, 'followers', array( $mention ) );
	ax_aud_assert( $ax_aud_results, 'followers addresses only the followers collection with mentions in cc and stays non-public', is_array( $followers_res ) && array( $followers ) === $followers_res['to'] && array( $mention ) === $followers_res['cc'] && ! in_array( $public, $followers_res['to'], true ) && false === $followers_res['public'] );

	$mentioned_res = axismundi_act_resolve_audience( $site, 'direct', array( $mention, $mention ) );
	ax_aud_assert( $ax_aud_results, 'direct canonicalizes to mentioned, dedupes recipients, and stays non-public', is_array( $mentioned_res ) && 'mentioned' === $mentioned_res['visibility'] && array( $mention ) === $mentioned_res['to'] && array() === $mentioned_res['cc'] && false === $mentioned_res['public'] );

	$invalid_mention = axismundi_act_resolve_audience( $site, 'public', array( $mention, 'not-a-uri' ) );
	ax_aud_assert( $ax_aud_results, 'one invalid mentioned recipient rejects the whole audience instead of silently narrowing it', is_wp_error( $invalid_mention ) && 'ax_act_audience_mention' === $invalid_mention->get_error_code() );

	$unknown = axismundi_act_resolve_audience( $site, 'nonsense', array() );
	ax_aud_assert( $ax_aud_results, 'an unrecognized visibility is rejected', is_wp_error( $unknown ) && 'ax_act_audience_visibility' === $unknown->get_error_code() );

	$empty_mention = axismundi_act_resolve_audience( $site, 'mentioned', array() );
	ax_aud_assert( $ax_aud_results, 'a mentioned-only object with no valid recipient is rejected', is_wp_error( $empty_mention ) && 'ax_act_audience_mentioned_empty' === $empty_mention->get_error_code() );

	if ( $site instanceof Axismundi_Actor ) {
		axismundi_actors_set_status( $site->get_identity_id(), 'internal' );
		$site = axismundi_actors_get_by_identity( $site->get_identity_id() );
	}
	$internal_result = $site instanceof Axismundi_Actor
		? axismundi_act_resolve_audience( $site, 'mentioned', array( $mention ) )
		: null;
	ax_aud_assert( $ax_aud_results, 'an internal local Actor cannot author a federated audience', is_wp_error( $internal_result ) && 'ax_act_audience_actor' === $internal_result->get_error_code() );

	ax_aud_assert( $ax_aud_results, 'the resolver performs no HTTP request', 0 === $GLOBALS['ax_aud_http'] );
} finally {
	remove_filter( 'pre_http_request', 'ax_aud_http' );
	if ( $ax_aud_identity_id > 0 ) {
		global $wpdb;
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $ax_aud_identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned Actor cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $ax_aud_identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned identity cleanup.
	}
}

$ax_aud_failures = count( array_filter( $ax_aud_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_aud_results ), $ax_aud_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_aud_failures > 0 ? 1 : 0 );
}
exit( $ax_aud_failures > 0 ? 1 : 0 );
