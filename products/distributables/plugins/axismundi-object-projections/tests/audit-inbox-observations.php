<?php
/**
 * Inbound Create/Update remote-object observation regression fixture.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_inbox_results       = array();
$ax_inbox_suffix        = strtolower( wp_generate_password( 8, false, false ) );
$ax_inbox_activity_uris = array();
$ax_inbox_object_uri    = 'https://remote.example/objects/inbox-' . $ax_inbox_suffix;
$ax_inbox_announce_uri  = 'https://example.com/objects/announced-' . $ax_inbox_suffix;

/** @param array<bool> $results Test results. */
function ax_inbox_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** @return Axismundi_Actor|WP_Error */
function ax_inbox_remote_actor( string $suffix ) {
	$uri = 'https://example.com/users/' . $suffix;
	return axismundi_actors_upsert_remote(
		array(
			'uri'                => $uri,
			'actor_type'         => 'Person',
			'preferred_username' => $suffix,
			'display_name'       => 'Inbox fixture ' . $suffix,
			'profile_url'        => 'https://example.com/@' . $suffix,
			'payload'            => array( 'id' => $uri, 'type' => 'Person' ),
			'endpoints'          => array( 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
		)
	);
}

/** Mock the deferred fetch of a URI-only Announce target. */
function ax_inbox_announce_fetch_mock( $preempt, array $args, string $url ) {
	if ( $url !== $GLOBALS['ax_inbox_announce_uri'] ) {
		return $preempt;
	}
	$payload = array(
		'@context'     => 'https://www.w3.org/ns/activitystreams',
		'id'           => $url,
		'type'         => 'Article',
		'attributedTo' => $GLOBALS['ax_inbox_author_uri'],
		'content'      => '<p>Cached from a public Announce.</p>',
		'to'           => array( 'https://www.w3.org/ns/activitystreams#Public' ),
	);
	return array(
		'headers'  => array( 'content-type' => 'application/activity+json' ),
		'body'     => wp_json_encode( $payload ),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => null,
	);
}

try {
	axismundi_op_install();
	axismundi_act_install();
	$author = ax_inbox_remote_actor( 'author-' . $ax_inbox_suffix );
	$other  = ax_inbox_remote_actor( 'other-' . $ax_inbox_suffix );
	$author_uri = $author instanceof Axismundi_Actor ? $author->get_uri() : '';
	$other_uri  = $other instanceof Axismundi_Actor ? $other->get_uri() : '';
	$GLOBALS['ax_inbox_announce_uri'] = $ax_inbox_announce_uri;
	$GLOBALS['ax_inbox_author_uri']   = $author_uri;
	ax_inbox_assert( $ax_inbox_results, 'fixture registers verified remote Actors', '' !== $author_uri && '' !== $other_uri );

	$create_uri = 'https://remote.example/activities/create-' . $ax_inbox_suffix;
	$create     = axismundi_act_record_activity(
		array(
			'id'     => $create_uri,
			'type'   => 'Create',
			'actor'  => $author_uri,
			'object' => array(
				'id'           => $ax_inbox_object_uri,
				'type'         => 'Question',
				'attributedTo' => $author_uri,
				'content'      => '<p>Original poll</p>',
				'oneOf'        => array( array( 'type' => 'Note', 'name' => 'One' ), array( 'type' => 'Note', 'name' => 'Two' ) ),
			),
		),
		'inbound'
	);
	if ( $create instanceof Axismundi_Activity ) {
		$ax_inbox_activity_uris[] = $create->get_uri();
	}
	$initial = axismundi_op_remote_object_get( $ax_inbox_object_uri );
	ax_inbox_assert( $ax_inbox_results, 'an inbound Create stores its inline Question snapshot', $create instanceof Axismundi_Activity && is_array( $initial ) && 'Question' === $initial['object_type'] );

	$update_uri = 'https://remote.example/activities/update-' . $ax_inbox_suffix;
	$update     = axismundi_act_record_activity(
		array(
			'id'     => $update_uri,
			'type'   => 'Update',
			'actor'  => $author_uri,
			'object' => array(
				'id'           => $ax_inbox_object_uri,
				'type'         => 'Note',
				'attributedTo' => $author_uri,
				'content'      => '<p>Poll removed</p>',
				'updated'      => '2026-07-20T10:00:00Z',
			),
		),
		'inbound'
	);
	if ( $update instanceof Axismundi_Activity ) {
		$ax_inbox_activity_uris[] = $update->get_uri();
	}
	$refreshed = axismundi_op_remote_object_get( $ax_inbox_object_uri );
	ax_inbox_assert( $ax_inbox_results, 'an inbound Update replaces the same cache row and permits Question-to-Note type changes', $update instanceof Axismundi_Activity && is_array( $refreshed ) && (int) $initial['id'] === (int) $refreshed['id'] && 'Note' === $refreshed['object_type'] && false !== strpos( (string) $refreshed['content'], 'Poll removed' ) );

	$spoof_uri = 'https://remote.example/activities/spoof-' . $ax_inbox_suffix;
	$spoof     = axismundi_act_record_activity(
		array(
			'id'     => $spoof_uri,
			'type'   => 'Update',
			'actor'  => $other_uri,
			'object' => array( 'id' => $ax_inbox_object_uri, 'type' => 'Question', 'attributedTo' => $author_uri, 'content' => '<p>Spoofed</p>' ),
		),
		'inbound'
	);
	if ( $spoof instanceof Axismundi_Activity ) {
		$ax_inbox_activity_uris[] = $spoof->get_uri();
	}
	$after_spoof = axismundi_op_remote_object_get( $ax_inbox_object_uri );
	ax_inbox_assert( $ax_inbox_results, 'a mismatched Update actor cannot overwrite the cached Object', $spoof instanceof Axismundi_Activity && is_array( $after_spoof ) && 'Note' === $after_spoof['object_type'] && false !== strpos( (string) $after_spoof['content'], 'Poll removed' ) );

	$announce_uri = 'https://remote.example/activities/announce-' . $ax_inbox_suffix;
	$announce     = axismundi_act_record_activity(
		array(
			'id'     => $announce_uri,
			'type'   => 'Announce',
			'actor'  => $author_uri,
			'object' => $ax_inbox_announce_uri,
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		),
		'inbound'
	);
	if ( $announce instanceof Axismundi_Activity ) {
		$ax_inbox_activity_uris[] = $announce->get_uri();
	}
	$announce_args = array( $ax_inbox_announce_uri );
	ax_inbox_assert( $ax_inbox_results, 'a public inbound Announce schedules its URI-only Object for deferred acquisition', $announce instanceof Axismundi_Activity && false !== wp_next_scheduled( 'axismundi_op_fetch_announced_object', $announce_args ) );

	add_filter( 'pre_http_request', 'ax_inbox_announce_fetch_mock', 10, 3 );
	axismundi_op_fetch_announced_object( $ax_inbox_announce_uri );
	remove_filter( 'pre_http_request', 'ax_inbox_announce_fetch_mock', 10 );
	$announced_object = axismundi_op_remote_object_get( $ax_inbox_announce_uri );
	ax_inbox_assert( $ax_inbox_results, 'the deferred Announce target fetch stores a renderable remote Object cache row', is_array( $announced_object ) && 'Article' === $announced_object['object_type'] && false !== strpos( (string) $announced_object['content'], 'Cached from a public Announce.' ) );
} finally {
	global $wpdb;
	foreach ( $ax_inbox_activity_uris as $uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri_hash' => hash( 'sha256', $uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	axismundi_op_remote_object_delete( $ax_inbox_object_uri );
	wp_clear_scheduled_hook( 'axismundi_op_fetch_announced_object', array( $ax_inbox_announce_uri ) );
	axismundi_op_remote_object_delete( $ax_inbox_announce_uri );
}

$ax_inbox_failures = count( array_filter( $ax_inbox_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_inbox_results ), $ax_inbox_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_inbox_failures > 0 ? 1 : 0 );
}
exit( $ax_inbox_failures > 0 ? 1 : 0 );
