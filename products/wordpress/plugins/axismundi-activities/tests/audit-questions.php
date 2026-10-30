<?php
/**
 * FEP-9967 Question ledger regression fixture (dev-only).
 *
 * Run: npx wp-env run cli wp eval-file wp-content/plugins/axismundi-activities/tests/audit-questions.php
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/questions.php';

$ax_q_results        = array();
$ax_q_activity_uris  = array();
$ax_q_identity_ids   = array();
$ax_q_remote_id      = 0;
$ax_q_suffix         = strtolower( wp_generate_password( 8, false, false ) );
global $wpdb;
$ax_q_op_before = function_exists( 'axismundi_op_objects_table' ) ? $wpdb->get_results( 'SELECT * FROM ' . axismundi_op_objects_table() . ' ORDER BY id', ARRAY_A ) : null;

function ax_q_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** @return array<string,mixed> */
function ax_q_poll_object( string $uri, string $actor_uri, string $mode, array $options, array $counts = array(), int $voters = 0 ) : array {
	$members = array();
	foreach ( $options as $position => $name ) {
		$members[] = array(
			'type'    => 'Note',
			'name'    => $name,
			'replies' => array( 'type' => 'Collection', 'totalItems' => (int) ( $counts[ $position ] ?? 0 ) ),
		);
	}
	return array(
		'id'           => $uri,
		'type'         => 'Question',
		'attributedTo' => $actor_uri,
		$mode          => $members,
		'votersCount'  => $voters,
	);
}

/** @return Axismundi_Activity|WP_Error */
function ax_q_vote( Axismundi_Actor $actor, string $question_uri, string $question_author_uri, string $name, array $extra = array(), string $direction = 'inbound' ) {
	$object = array_merge(
		array(
			'id'           => 'https://example.com/votes/' . wp_generate_uuid4(),
			'type'         => 'Note',
			'attributedTo' => $actor->get_uri(),
			'inReplyTo'    => $question_uri,
			'name'         => $name,
		),
		$extra
	);
	$payload = array(
			'type'   => 'Create',
			'actor'  => $actor->get_uri(),
			'object' => $object,
			'to'     => array( $question_author_uri ),
	);
	if ( 'inbound' === $direction ) {
		$payload['id'] = 'https://example.com/activities/votes/' . wp_generate_uuid4();
	}
	return axismundi_act_record_activity( $payload, $direction );
}

function ax_q_vote_object_uri( $activity ) : string {
	if ( ! $activity instanceof Axismundi_Activity ) {
		return '';
	}
	$payload = $activity->get_payload();
	$object  = $payload['object'] ?? array();
	return is_array( $object ) ? axismundi_act_member_uri( $object['id'] ?? '' ) : '';
}

try {
	ax_q_assert( $ax_q_results, 'the Activities Question schema installs with ordered options and nullable vote choice positions', axismundi_act_install_questions_schema() );
	$author = axismundi_actors_get_system_actor();
	$remote_uri = 'https://example.com/users/question-voter-' . $ax_q_suffix;
	$voter = axismundi_actors_upsert_remote(
		array(
			'uri' => $remote_uri,
			'actor_type' => 'Person',
			'preferred_username' => 'question-voter-' . $ax_q_suffix,
			'display_name' => 'Question fixture voter',
			'profile_url' => 'https://example.com/@question-voter-' . $ax_q_suffix,
			'payload' => array( 'id' => $remote_uri, 'type' => 'Person' ),
			'endpoints' => array( 'inbox' => $remote_uri . '/inbox', 'outbox' => $remote_uri . '/outbox' ),
		)
	);
	$ax_q_remote_id = $voter instanceof Axismundi_Actor ? $voter->get_identity_id() : 0;
	ax_q_assert( $ax_q_results, 'the fixture has a local Question author and a known inbound voter', $author instanceof Axismundi_Actor && $voter instanceof Axismundi_Actor );

	$created = $author instanceof Axismundi_Actor ? axismundi_act_create_question( $author, array( 'mode' => 'oneOf', 'options' => array( 'Cats', 'Dogs' ) ) ) : null;
	if ( $created instanceof Axismundi_Activity ) {
		$ax_q_activity_uris[] = $created->get_uri();
	}
	$question_uri = $created instanceof Axismundi_Activity ? axismundi_act_member_uri( $created->get_payload()['object']['id'] ?? '' ) : '';
	$question = axismundi_act_get_question_by_uri( $question_uri );
	if ( is_array( $question ) ) {
		$ax_q_identity_ids[] = (int) $question['identity_id'];
	}
	ax_q_assert( $ax_q_results, 'Create(Question) registers a local Question identity and ordered zero tally projection', $created instanceof Axismundi_Activity && is_array( $question ) && 'oneOf' === $question['mode'] && array( 'Cats', 'Dogs' ) === $question['options'] && array( 0, 0 ) === $question['option_counts'] );
	$question_object = function_exists( 'axismundi_op_get_object_by_uri' ) ? axismundi_op_get_object_by_uri( $question_uri ) : null;
	ax_q_assert( $ax_q_results, 'the optional OP adapter references a separately keyed Activities Question', ! function_exists( 'axismundi_op_get_object_by_uri' ) || ( is_array( $question_object ) && 'axismundi-activities' === (string) $question_object['source_owner'] && 'question-ledger' === (string) $question_object['source_kind'] && 'Question' === (string) $question_object['as2_type'] && (int) ( $question['identity_id'] ?? 0 ) === (int) $question_object['source_ref'] ) );

	$first = $voter instanceof Axismundi_Actor && $author instanceof Axismundi_Actor ? ax_q_vote( $voter, $question_uri, $author->get_uri(), 'Cats' ) : null;
	if ( $first instanceof Axismundi_Activity ) {
		$ax_q_activity_uris[] = $first->get_uri();
	}
	$after_first = axismundi_act_get_question_by_uri( $question_uri );
	ax_q_assert( $ax_q_results, 'an addressed content-less Vote Create increments the local authoritative tally', $first instanceof Axismundi_Activity && is_array( $after_first ) && array( 1, 0 ) === $after_first['option_counts'] && 1 === (int) $after_first['votes_count'] && 1 === (int) $after_first['voters_count'] );
	$first_object_uri = ax_q_vote_object_uri( $first );
	ax_q_assert( $ax_q_results, 'a valid Vote remains a stored thread edge but is excluded from replies by the shared projection filter', '' !== $first_object_uri && ! apply_filters( 'axismundi_op_thread_include_reply', true, $first_object_uri, $question_uri ) );

	$duplicate = $voter instanceof Axismundi_Actor && $author instanceof Axismundi_Actor ? ax_q_vote( $voter, $question_uri, $author->get_uri(), 'Dogs' ) : null;
	if ( $duplicate instanceof Axismundi_Activity ) {
		$ax_q_activity_uris[] = $duplicate->get_uri();
	}
	$after_duplicate = axismundi_act_get_question_by_uri( $question_uri );
	ax_q_assert( $ax_q_results, 'a second oneOf vote remains evidence but is ignored by the projection', $duplicate instanceof Axismundi_Activity && is_array( $after_duplicate ) && array( 1, 0 ) === $after_duplicate['option_counts'] );
	$duplicate_object_uri = ax_q_vote_object_uri( $duplicate );
	ax_q_assert( $ax_q_results, 'a duplicate Vote is excluded from replies even though its tally projection is ignored', '' !== $duplicate_object_uri && ! apply_filters( 'axismundi_op_thread_include_reply', true, $duplicate_object_uri, $question_uri ) );

	$content_map = $voter instanceof Axismundi_Actor && $author instanceof Axismundi_Actor ? ax_q_vote( $voter, $question_uri, $author->get_uri(), 'Cats', array( 'contentMap' => array( 'en' => '<p>Not a vote.</p>' ) ) ) : null;
	if ( $content_map instanceof Axismundi_Activity ) {
		$ax_q_activity_uris[] = $content_map->get_uri();
	}
	$after_content_map = axismundi_act_get_question_by_uri( $question_uri );
	ax_q_assert( $ax_q_results, 'contentMap text prevents a reply from being counted as a Vote', $content_map instanceof Axismundi_Activity && is_array( $after_content_map ) && array( 1, 0 ) === $after_content_map['option_counts'] );
	$content_map_object_uri = ax_q_vote_object_uri( $content_map );
	ax_q_assert( $ax_q_results, 'a content-bearing reply remains visible to the shared reply projection filter', '' !== $content_map_object_uri && apply_filters( 'axismundi_op_thread_include_reply', true, $content_map_object_uri, $question_uri ) );

	$direct_vote = $author instanceof Axismundi_Actor ? axismundi_act_vote_question( $author, $question_uri, 1 ) : null;
	if ( $direct_vote instanceof Axismundi_Activity ) {
		$ax_q_activity_uris[] = $direct_vote->get_uri();
	}
	$direct_payload = $direct_vote instanceof Axismundi_Activity ? $direct_vote->get_payload() : array();
	ax_q_assert( $ax_q_results, 'the local vote service creates a constrained Create(Note) without a WordPress draft post', $direct_vote instanceof Axismundi_Activity && 'Create' === $direct_vote->get_type() && 'Note' === (string) ( $direct_payload['object']['type'] ?? '' ) && ! array_key_exists( 'content', (array) ( $direct_payload['object'] ?? array() ) ) && $question_uri === (string) ( $direct_payload['object']['inReplyTo'] ?? '' ) && 'Dogs' === (string) ( $direct_payload['object']['name'] ?? '' ) );

	$public_vote = $voter instanceof Axismundi_Actor && $author instanceof Axismundi_Actor ? axismundi_act_record_activity(
		array(
			'id' => 'https://example.com/activities/votes/' . wp_generate_uuid4(),
			'type' => 'Create',
			'actor' => $voter->get_uri(),
			'object' => array( 'id' => 'https://example.com/votes/' . wp_generate_uuid4(), 'type' => 'Note', 'attributedTo' => $voter->get_uri(), 'inReplyTo' => $question_uri, 'name' => 'Cats' ),
			'to' => array( 'https://www.w3.org/ns/activitystreams#Public', $author->get_uri() ),
		),
		'inbound'
	) : null;
	if ( $public_vote instanceof Axismundi_Activity ) {
		$ax_q_activity_uris[] = $public_vote->get_uri();
	}
	ax_q_assert( $ax_q_results, 'a publicly addressed Vote is still excluded from human feeds and outbox listings', $public_vote instanceof Axismundi_Activity && ! axismundi_act_is_publicly_renderable( $public_vote ) && null === axismundi_act_public_payload( $public_vote ) );

	$remote_question_uri = 'https://example.com/questions/remote-' . wp_generate_uuid4();
	$remote_created = $voter instanceof Axismundi_Actor ? axismundi_act_record_activity(
		array( 'id' => 'https://example.com/activities/questions/' . wp_generate_uuid4(), 'type' => 'Create', 'actor' => $voter->get_uri(), 'object' => ax_q_poll_object( $remote_question_uri, $voter->get_uri(), 'anyOf', array( 'Red', 'Blue' ), array( 7, 3 ), 8 ) ),
		'inbound'
	) : null;
	if ( $remote_created instanceof Axismundi_Activity ) {
		$ax_q_activity_uris[] = $remote_created->get_uri();
	}
	$remote_question = axismundi_act_get_question_by_uri( $remote_question_uri );
	if ( is_array( $remote_question ) ) {
		$ax_q_identity_ids[] = (int) $remote_question['identity_id'];
	}
	ax_q_assert( $ax_q_results, 'an inbound Question keeps its author-reported tally as remote observation', $remote_created instanceof Axismundi_Activity && is_array( $remote_question ) && 'remote' === $remote_question['authority_origin'] && array( 7, 3 ) === $remote_question['option_counts'] );

	$local_vote = $author instanceof Axismundi_Actor && $voter instanceof Axismundi_Actor ? ax_q_vote( $author, $remote_question_uri, $voter->get_uri(), 'Red', array(), 'outbound' ) : null;
	if ( $local_vote instanceof Axismundi_Activity ) {
		$ax_q_activity_uris[] = $local_vote->get_uri();
	}
	$after_remote_vote = axismundi_act_get_question_by_uri( $remote_question_uri );
	ax_q_assert( $ax_q_results, 'a local vote for a remote Question is retained without changing the remote tally observation', $local_vote instanceof Axismundi_Activity && is_array( $after_remote_vote ) && array( 7, 3 ) === $after_remote_vote['option_counts'] && 8 === (int) $after_remote_vote['voters_count'] );

	$remote_reset = $voter instanceof Axismundi_Actor ? axismundi_act_record_activity(
		array( 'id' => 'https://example.com/activities/questions/' . wp_generate_uuid4(), 'type' => 'Update', 'actor' => $voter->get_uri(), 'object' => ax_q_poll_object( $remote_question_uri, $voter->get_uri(), 'oneOf', array( 'Tea', 'Coffee' ), array( 0, 0 ), 0 ) ),
		'inbound'
	) : null;
	if ( $remote_reset instanceof Axismundi_Activity ) {
		$ax_q_activity_uris[] = $remote_reset->get_uri();
	}
	$after_reset = axismundi_act_get_question_by_uri( $remote_question_uri );
	ax_q_assert( $ax_q_results, 'an FEP-9967 option edit resets its remote tally before replacing the definition', $remote_reset instanceof Axismundi_Activity && is_array( $after_reset ) && 'oneOf' === $after_reset['mode'] && array( 'Tea', 'Coffee' ) === $after_reset['options'] && array( 0, 0 ) === $after_reset['option_counts'] );
} finally {
	global $wpdb;
	$object_uris = array_filter( array( $question_uri ?? '', $remote_question_uri ?? '' ) );
	foreach ( array_unique( $ax_q_activity_uris ) as $uri ) {
		$activity = axismundi_act_get( $uri );
		$object = $activity instanceof Axismundi_Activity ? ( $activity->get_payload()['object'] ?? null ) : null;
		if ( is_array( $object ) && ! empty( $object['id'] ) ) { $object_uris[] = $object['id']; }
	}
	foreach ( array_unique( $object_uris ) as $uri ) {
		if ( function_exists( 'axismundi_op_delete_remote_object' ) ) { axismundi_op_delete_remote_object( $uri ); }
		$object = function_exists( 'axismundi_op_get_object_by_uri' ) ? axismundi_op_get_object_by_uri( $uri ) : null;
		if ( is_array( $object ) ) { $wpdb->delete( axismundi_op_objects_table(), array( 'id' => $object['id'] ) ); }
	}
	foreach ( array_unique( $ax_q_activity_uris ) as $uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri_hash' => hash( 'sha256', $uri ), 'activity_uri' => $uri ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned ledger cleanup.
	}
	foreach ( array_unique( $ax_q_identity_ids ) as $identity_id ) {
		$wpdb->delete( axismundi_act_question_votes_table(), array( 'question_identity_id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned projection cleanup.
		$wpdb->delete( axismundi_act_questions_table(), array( 'identity_id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned projection cleanup.
		if ( function_exists( 'axismundi_op_objects_table' ) ) {
			$wpdb->delete( axismundi_op_objects_table(), array( 'source_owner' => 'axismundi-activities', 'source_kind' => 'question-ledger', 'source_ref' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned Object slice cleanup.
		}
	}
	if ( $ax_q_remote_id > 0 ) {
		foreach ( array( axismundi_actors_endpoints_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table(), axismundi_actors_identity_relations_table(), axismundi_actors_asset_cache_table(), axismundi_actors_addresses_table(), axismundi_actors_texts_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $ax_q_remote_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned Actor cleanup.
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $ax_q_remote_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned Actor cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $ax_q_remote_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned Actor cleanup.
	}
}

ax_q_assert( $ax_q_results, 'Question fixtures leave the optional OP index unchanged', null === $ax_q_op_before || $ax_q_op_before === $wpdb->get_results( 'SELECT * FROM ' . axismundi_op_objects_table() . ' ORDER BY id', ARRAY_A ) );
$ax_q_failures = count( array_filter( $ax_q_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_q_results ), $ax_q_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_q_failures > 0 ? 1 : 0 );
}
exit( $ax_q_failures > 0 ? 1 : 0 );
