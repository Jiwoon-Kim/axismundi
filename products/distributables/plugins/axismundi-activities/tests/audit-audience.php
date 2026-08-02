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
$ax_aud_identity_ids = array();
$ax_aud_users        = array();

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
	/*
	 * Reading the table backwards.
	 *
	 * Every audience this resolver can write has to be readable back as the choice that wrote it,
	 * or a card describes an Object as something other than what its author selected. The four are
	 * checked against the resolver's own output rather than against hand-written to/cc, so the
	 * inverse cannot pass by agreeing with a copy of the table that has drifted from it.
	 */
	$ax_aud_followers = $site instanceof Axismundi_Actor
		? (string) apply_filters( 'axismundi_act_actor_followers_uri', '', $site )
		: '';
	$ax_aud_roundtrip = array();
	$ax_aud_blind     = array();
	foreach ( array( 'public', 'unlisted', 'followers', 'mentioned' ) as $ax_aud_choice ) {
		$ax_aud_written = $site instanceof Axismundi_Actor
			? axismundi_act_resolve_audience( $site, $ax_aud_choice, array( $mention ) )
			: null;
		if ( ! is_array( $ax_aud_written ) ) {
			continue;
		}
		$ax_aud_roundtrip[ $ax_aud_choice ] = axismundi_act_audience_visibility( $ax_aud_written['to'], $ax_aud_written['cc'], $ax_aud_followers );
		$ax_aud_blind[ $ax_aud_choice ]     = axismundi_act_audience_visibility( $ax_aud_written['to'], $ax_aud_written['cc'], '' );
	}
	ax_aud_assert(
		$ax_aud_results,
		'every audience the resolver writes reads back as the visibility that wrote it',
		array(
			'public'    => 'public',
			'unlisted'  => 'unlisted',
			'followers' => 'followers',
			'mentioned' => 'mentioned',
		) === $ax_aud_roundtrip
	);
	/*
	 * Without the author's followers address, followers-only and mentioned-only are the same two
	 * lists of opaque URIs. Naming either one would be asserting who may read something on the
	 * strength of a guess, so both must degrade to `limited` — while the two public forms, which
	 * are distinguished by the Public collection alone, must survive not knowing it.
	 */
	ax_aud_assert(
		$ax_aud_results,
		'an unknown followers collection costs the restricted distinction and nothing else',
		array(
			'public'    => 'public',
			'unlisted'  => 'unlisted',
			'followers' => 'limited',
			'mentioned' => 'limited',
		) === $ax_aud_blind
	);
	/*
	 * Peers do not all spell the Public collection the same way. A card that read `as:Public` as
	 * restricted would mark a public Object with a lock, which is the wrong direction to be wrong
	 * in — it is the reading that misrepresents an author as more private than they chose.
	 */
	ax_aud_assert(
		$ax_aud_results,
		'the compact spellings of the Public collection are read as public, not as restricted',
		'public' === axismundi_act_audience_visibility( array( 'as:Public' ), array(), '' )
			&& 'unlisted' === axismundi_act_audience_visibility( array( 'https://example.com/u/x/followers' ), array( 'Public' ), '' )
	);

	if ( $site instanceof Axismundi_Actor ) {
		axismundi_actors_set_status( $site->get_identity_id(), 'internal' );
		$site = axismundi_actors_get_by_identity( $site->get_identity_id() );
	}
	$internal_result = $site instanceof Axismundi_Actor
		? axismundi_act_resolve_audience( $site, 'mentioned', array( $mention ) )
		: null;
	ax_aud_assert( $ax_aud_results, 'an internal local Actor cannot author a federated audience', is_wp_error( $internal_result ) && 'ax_act_audience_actor' === $internal_result->get_error_code() );


	/*
	 * Group context, read off the addressing rather than off our own tables.
	 *
	 * This is what lets a Person's Community surface contain anything remote. The predicates it
	 * replaces are local Forum ones — "is this a Topic we admitted", "is this a reply to a Topic we
	 * hold" — which are unanswerable for a Lemmy Object and are precisely why Group context has
	 * been invisible on remote profiles.
	 */
	// A managed Group needs a real owner, and this audit runs with no current user.
	$ax_aud_owner   = (int) wp_insert_user( array( 'user_login' => 'axaud_' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$ax_aud_users[] = $ax_aud_owner;
	$ax_aud_group = axismundi_actors_create_managed_group(
		array( 'owner_user_id' => $ax_aud_owner, 'preferred_username' => 'axaud' . strtolower( wp_generate_password( 6, false, false ) ), 'status' => 'internal' )
	);
	if ( $ax_aud_group instanceof Axismundi_Actor ) {
		$ax_aud_identity_ids[] = $ax_aud_group->get_identity_id();
	}
	$ax_aud_group_uri  = $ax_aud_group instanceof Axismundi_Actor ? $ax_aud_group->get_uri() : '';
	$ax_aud_second     = axismundi_actors_create_managed_group(
		array( 'owner_user_id' => $ax_aud_owner, 'preferred_username' => 'axaud' . strtolower( wp_generate_password( 6, false, false ) ), 'status' => 'internal' )
	);
	if ( $ax_aud_second instanceof Axismundi_Actor ) {
		$ax_aud_identity_ids[] = $ax_aud_second->get_identity_id();
	}
	$ax_aud_second_uri = $ax_aud_second instanceof Axismundi_Actor ? $ax_aud_second->get_uri() : '';

	$ax_aud_declared  = axismundi_act_group_context( array( 'audience' => $ax_aud_group_uri, 'to' => array( $ax_aud_group_uri ), 'cc' => array( $public ) ) );
	$ax_aud_addressed = axismundi_act_group_context( array( 'to' => array( $ax_aud_group_uri ), 'cc' => array( $public ) ) );
	$ax_aud_plain     = axismundi_act_group_context( array( 'to' => array( $public ), 'cc' => array( $mention ) ) );
	ax_aud_assert(
		$ax_aud_results,
		'a Group named in audience or addressed in to/cc is Group context, and a public post to nobody is not',
		true === $ax_aud_declared['has_group_context']
			&& $ax_aud_group_uri === $ax_aud_declared['primary_group_uri']
			&& true === $ax_aud_addressed['has_group_context']
			&& $ax_aud_group_uri === $ax_aud_addressed['primary_group_uri']
			&& false === $ax_aud_plain['has_group_context']
			&& '' === $ax_aud_plain['primary_group_uri']
	);
	/*
	 * The shape that actually arrives.
	 *
	 * A `Create` addresses Public at its root and puts `audience` on the embedded Note. Every case
	 * above hands the classifier flat top-level addressing, which is the tidy form and not the
	 * federated one — reading only the root would find `to: [Public]`, answer "no community", and
	 * lose precisely the Objects this classifier was written to catch.
	 */
	$ax_aud_enveloped = axismundi_act_group_context(
		array(
			'type'   => 'Create',
			'to'     => array( $public ),
			'object' => array( 'type' => 'Note', 'audience' => $ax_aud_group_uri, 'to' => array( $public ) ),
		)
	);
	ax_aud_assert(
		$ax_aud_results,
		'a Create carrying its community on the embedded Object is Group context, not a plain public post',
		true === $ax_aud_enveloped['has_group_context']
			&& $ax_aud_group_uri === $ax_aud_enveloped['primary_group_uri']
			&& array( $ax_aud_group_uri ) === $ax_aud_enveloped['group_uris']
	);

	/*
	 * A reply names its parent, not the community, so a thread owner has to answer for it. The
	 * contributor supplies URIs and nothing more — whether one is a Group is still the registry's
	 * answer, which is why a contributed non-Group is discarded rather than trusted.
	 */
	$ax_aud_thread = static function ( array $uris ) use ( $ax_aud_group_uri, $mention ) : array {
		unset( $uris );
		return array( $ax_aud_group_uri, $mention );
	};
	add_filter( 'axismundi_act_group_context_uris', $ax_aud_thread );
	$ax_aud_threaded = axismundi_act_group_context( array( 'to' => array( $public ) ), 'https://example.com/notes/threaded' );
	remove_filter( 'axismundi_act_group_context_uris', $ax_aud_thread );
	ax_aud_assert(
		$ax_aud_results,
		'a thread owner can name the Group a reply belongs to, but cannot make a non-Group into one',
		true === $ax_aud_threaded['has_group_context']
			&& array( $ax_aud_group_uri ) === $ax_aud_threaded['group_uris']
			&& $ax_aud_group_uri === $ax_aud_threaded['primary_group_uri']
	);
	/*
	 * Cross-posting is the case that has to fail quietly rather than confidently. Two Groups with
	 * nothing saying which one the author meant is not a tie to break by position: naming the first
	 * would let a card state the wrong community as fact. An explicit `audience` settles it, and
	 * without one the caller gets no primary and shows nothing.
	 */
	$ax_aud_crossposted = axismundi_act_group_context( array( 'to' => array( $ax_aud_group_uri, $ax_aud_second_uri ), 'cc' => array( $public ) ) );
	$ax_aud_settled     = axismundi_act_group_context( array( 'audience' => $ax_aud_second_uri, 'to' => array( $ax_aud_group_uri, $ax_aud_second_uri ) ) );
	ax_aud_assert(
		$ax_aud_results,
		'a post in two Groups keeps both and names neither, unless its audience says which was meant',
		true === $ax_aud_crossposted['has_group_context']
			&& 2 === count( $ax_aud_crossposted['group_uris'] )
			&& '' === $ax_aud_crossposted['primary_group_uri']
			&& $ax_aud_second_uri === $ax_aud_settled['primary_group_uri']
	);

	ax_aud_assert( $ax_aud_results, 'the resolver performs no HTTP request', 0 === $GLOBALS['ax_aud_http'] );
} finally {
	remove_filter( 'pre_http_request', 'ax_aud_http' );
	// Every Group the classifier fixtures created, on the same path as the Actor above.
	foreach ( $ax_aud_users as $ax_aud_user ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( (int) $ax_aud_user );
	}
	foreach ( $ax_aud_identity_ids as $ax_aud_extra ) {
		global $wpdb;
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $ax_aud_extra ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned Group cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $ax_aud_extra ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned identity cleanup.
	}
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
