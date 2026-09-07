<?php
/**
 * Local Actor profile → outbound Update lifecycle regression (dev-only).
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_actor_update_results = array();
$ax_actor_update_id      = 0;
$ax_actor_update_uri     = '';

/** @param bool[] $results Results. */
function ax_actor_update_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	axismundi_act_install();
	$actor = axismundi_actors_create_local(
		array(
			'actor_type'         => 'Person',
			'actor_scope'        => 'user',
			'preferred_username' => 'actor-update-' . strtolower( wp_generate_password( 8, false, false ) ),
		)
	);
	if ( $actor instanceof Axismundi_Actor ) {
		$ax_actor_update_id  = $actor->get_identity_id();
		$ax_actor_update_uri = $actor->get_uri();
		axismundi_actors_set_status( $ax_actor_update_id, 'public' );
		axismundi_actors_set_default_language( $ax_actor_update_id, 'en-US' );
		axismundi_actors_set_text( $ax_actor_update_id, 'name', 'en-US', 'English profile name' );
		axismundi_actors_set_text( $ax_actor_update_id, 'name', 'ko-KR', '한국어 프로필 이름' );
		axismundi_actors_set_text( $ax_actor_update_id, 'summary', 'en-US', '<p>English profile summary</p>' );
		axismundi_actors_set_text( $ax_actor_update_id, 'summary', 'ko-KR', '<p>한국어 프로필 소개</p>' );
		$actor = axismundi_actors_get_by_identity( $ax_actor_update_id );
	}

	ax_actor_update_assert( $ax_actor_update_results, 'fixture has a public local Actor', $actor instanceof Axismundi_Actor && $actor->is_local() && 'public' === $actor->get_status() );
	if ( $actor instanceof Axismundi_Actor ) {
		do_action( 'axismundi_actors_local_actor_profile_updated', $actor );
		$first   = axismundi_act_get_object_lifecycle( $ax_actor_update_uri );
		$payload = $first instanceof Axismundi_Activity ? $first->get_payload() : array();
		$object  = (array) ( $payload['object'] ?? array() );
		ax_actor_update_assert( $ax_actor_update_results, 'a profile save records an outbound Update with the embedded Actor representation', $first instanceof Axismundi_Activity && 'Update' === $first->get_type() && 'outbound' === $first->get_direction() && $ax_actor_update_uri === $first->get_actor_uri() && $ax_actor_update_uri === $first->get_object_uri() && $ax_actor_update_uri === (string) ( $object['id'] ?? '' ) );
		ax_actor_update_assert( $ax_actor_update_results, 'the Update scalar uses the configured default language while maps preserve the other translations', 'English profile name' === (string) ( $object['name'] ?? '' ) && '<p>English profile summary</p>' === (string) ( $object['summary'] ?? '' ) && '한국어 프로필 이름' === (string) ( $object['nameMap']['ko-KR'] ?? '' ) && '<p>한국어 프로필 소개</p>' === (string) ( $object['summaryMap']['ko-KR'] ?? '' ) );
		ax_actor_update_assert( $ax_actor_update_results, 'the Update addresses Public and the Actor followers collection', array( axismundi_act_public_audience_uri() ) === (array) ( $payload['to'] ?? array() ) && in_array( axismundi_op_actor_followers_url( $actor ), (array) ( $payload['cc'] ?? array() ), true ) );

		do_action( 'axismundi_actors_local_actor_profile_updated', $actor );
		$unchanged = axismundi_act_get_by_object( $ax_actor_update_uri );
		ax_actor_update_assert( $ax_actor_update_results, 'saving an unchanged profile reuses its Update instead of publishing another one', 1 === count( $unchanged ) && $first instanceof Axismundi_Activity && $unchanged[0]->get_id() === $first->get_id() );

		axismundi_actors_set_text( $ax_actor_update_id, 'name', 'en-US', 'Changed English profile name' );
		do_action( 'axismundi_actors_local_actor_profile_updated', axismundi_actors_get_by_identity( $ax_actor_update_id ) );
		$changed = axismundi_act_get_by_object( $ax_actor_update_uri );
		$latest  = axismundi_act_get_object_lifecycle( $ax_actor_update_uri );
		ax_actor_update_assert( $ax_actor_update_results, 'a changed profile records a new Update whose current document carries the changed scalar', 2 === count( $changed ) && $latest instanceof Axismundi_Activity && 'Changed English profile name' === (string) ( $latest->get_payload()['object']['name'] ?? '' ) );
	}
} finally {
	if ( '' !== $ax_actor_update_uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'object_uri_hash' => hash( 'sha256', $ax_actor_update_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	if ( $ax_actor_update_id > 0 ) {
		$wpdb->delete( axismundi_actors_texts_table(), array( 'identity_id' => $ax_actor_update_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $ax_actor_update_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $ax_actor_update_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
}

$failures = count( array_filter( $ax_actor_update_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_actor_update_results ), $failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $failures > 0 ? 1 : 0 );
}
exit( $failures > 0 ? 1 : 0 );
