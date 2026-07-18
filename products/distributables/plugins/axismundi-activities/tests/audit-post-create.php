<?php
/**
 * Core Post → Create Activity bridge regression (dev-only).
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_create_results = array();
$ax_create_posts   = array();
$ax_create_objects = array();
$ax_create_identity_id = 0;
$GLOBALS['ax_create_http'] = 0;

/** @param bool[] $results Results. */
function ax_create_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Prove the lifecycle bridge performs no transport. */
function ax_create_http( $preempt ) {
	++$GLOBALS['ax_create_http'];
	return $preempt;
}

try {
	axismundi_act_install();
	global $wpdb;
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture verifies schema v3.
	$source_index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'source_event_hash'", ARRAY_A );
	ax_create_assert( $ax_create_results, 'schema v3+ verifies a unique source-event identity', (int) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION ) >= 3 && ! empty( $source_index ) && 0 === (int) $source_index[0]['Non_unique'] );

	$admins    = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
	$author_id = isset( $admins[0] ) ? (int) $admins[0] : 0;
	wp_set_current_user( $author_id );
	$site = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'preferred_username' => 'create-author-' . strtolower( wp_generate_password( 8, false, false ) ) ) );
	if ( $site instanceof Axismundi_Actor ) {
		$ax_create_identity_id = $site->get_identity_id();
		axismundi_actors_set_status( $ax_create_identity_id, 'public' );
		$site = axismundi_actors_get_by_identity( $ax_create_identity_id );
	}
	$actor_uri = $site instanceof Axismundi_Actor ? $site->get_uri() : '';
	add_filter( 'axismundi_op_post_actor_uri', static fn() : string => $actor_uri );
	add_filter( 'axismundi_op_post_lifecycle_owner', static fn() : string => 'axismundi', 99 );
	add_filter( 'pre_http_request', 'ax_create_http' );

	$post_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => $author_id, 'post_title' => 'Create bridge' ) );
	$ax_create_posts[] = $post_id;
	$object_uri = axismundi_op_post_object_uri( get_post( $post_id ) );
	$ax_create_objects[] = $object_uri;
	ax_create_assert( $ax_create_results, 'a draft has no Create Activity', array() === axismundi_act_get_by_object( $object_uri ) );

	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$activities = axismundi_act_get_by_object( $object_uri );
	$create     = $activities[0] ?? null;
	$create_payload = $create instanceof Axismundi_Activity ? $create->get_payload() : array();
	ax_create_assert( $ax_create_results, 'first public commit records one outbound Create with URI references and the same resolved audience as its Article', 1 === count( $activities ) && $create instanceof Axismundi_Activity && 'Create' === $create->get_type() && 'outbound' === $create->get_direction() && $actor_uri === $create->get_actor_uri() && $object_uri === $create->get_object_uri() && ! is_array( $create_payload['object'] ?? null ) && array( axismundi_act_public_audience_uri() ) === $create_payload['to'] && in_array( axismundi_op_actor_followers_url( $site ), $create_payload['cc'], true ) );
	$mismatched_projection = axismundi_act_record_post_create( get_post( $post_id ), $object_uri . '#wrong', $actor_uri );
	ax_create_assert( $ax_create_results, 'the bridge rejects event arguments that do not match the current public projection', is_wp_error( $mismatched_projection ) && 'ax_act_post_projection' === $mismatched_projection->get_error_code() );

	$rest_request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
	$rest_request->set_body_params(
		array(
			'title'  => 'REST followers Create',
			'status' => 'publish',
			'meta'   => array( AXISMUNDI_OP_POST_VISIBILITY_META => 'followers' ),
		)
	);
	$rest_response = rest_do_request( $rest_request );
	$rest_data     = $rest_response->get_data();
	$rest_post_id  = (int) ( $rest_data['id'] ?? 0 );
	if ( $rest_post_id > 0 ) {
		$ax_create_posts[] = $rest_post_id;
	}
	$rest_post       = $rest_post_id > 0 ? get_post( $rest_post_id ) : null;
	$rest_object_uri = $rest_post instanceof WP_Post ? axismundi_op_post_object_uri( $rest_post ) : '';
	$rest_create     = '' !== $rest_object_uri ? ( axismundi_act_get_by_object( $rest_object_uri )[0] ?? null ) : null;
	$rest_payload    = $rest_create instanceof Axismundi_Activity ? $rest_create->get_payload() : array();
	$ax_create_objects[] = $rest_object_uri;
	ax_create_assert( $ax_create_results, 'block-editor REST publication waits for metadata and records a followers-only Create instead of leaking the public default', 201 === $rest_response->get_status() && 'followers' === axismundi_op_post_visibility( $rest_post ) && array( axismundi_op_actor_followers_url( $site ) ) === ( $rest_payload['to'] ?? null ) && array() === ( $rest_payload['cc'] ?? null ) );

	$unknown_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => $author_id, 'post_title' => 'Unknown mention', 'post_content' => '<p><a class="mention" href="https://arbitrary.example/not-an-actor">invalid</a></p>' ) );
	$ax_create_posts[] = $unknown_id;
	$unknown_uri = axismundi_op_post_object_uri( get_post( $unknown_id ) );
	$ax_create_objects[] = $unknown_uri;
	wp_update_post( array( 'ID' => $unknown_id, 'post_status' => 'publish' ) );
	$unknown_create = axismundi_act_record_post_create( get_post( $unknown_id ), $unknown_uri, $actor_uri );
	ax_create_assert( $ax_create_results, 'an unresolved authored mention prevents Create while the committed public Article remains available for tolerant live projection', is_wp_error( $unknown_create ) && 'ax_op_post_mention_actor' === $unknown_create->get_error_code() && array() === axismundi_act_get_by_object( $unknown_uri ) && is_array( axismundi_op_transform_object( get_post( $unknown_id ) ) ) );

	wp_update_post( array( 'ID' => $post_id, 'post_title' => 'Create bridge edited' ) );
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	ax_create_assert( $ax_create_results, 'publish edits and publish-draft-publish do not mint a second Create before Delete exists', 1 === count( axismundi_act_get_by_object( $object_uri ) ) );

	$delete = axismundi_act_record_activity( array( 'type' => 'Delete', 'actor' => $actor_uri, 'object' => $object_uri ), 'outbound' );
	wp_update_post( array( 'ID' => $post_id, 'post_title' => 'Create bridge resurrected' ) );
	$after_delete = axismundi_act_get_by_object( $object_uri );
	ax_create_assert( $ax_create_results, 'an effective Delete starts a new lifecycle generation and permits one resurrection Create', $delete instanceof Axismundi_Activity && 3 === count( $after_delete ) && 'Create' === $after_delete[0]->get_type() );

	$source_uri = 'https://example.com/objects/source-' . wp_generate_password( 8, false, false );
	$ax_create_objects[] = $source_uri;
	$first_source = axismundi_act_record_source_activity( array( 'type' => 'Create', 'actor' => $actor_uri, 'object' => $source_uri ), 'outbound', 'fixture-source:' . $source_uri );
	$replay_source = axismundi_act_record_source_activity( array( 'type' => 'Create', 'actor' => $actor_uri, 'object' => $source_uri ), 'outbound', 'fixture-source:' . $source_uri );
	ax_create_assert( $ax_create_results, 'source-event replay converges on the first immutable Activity despite a newly minted candidate id', $first_source instanceof Axismundi_Activity && $replay_source instanceof Axismundi_Activity && $first_source->get_id() === $replay_source->get_id() );
	$source_conflict = axismundi_act_record_source_activity( array( 'type' => 'Like', 'actor' => $actor_uri, 'object' => $source_uri ), 'outbound', 'fixture-source:' . $source_uri );
	ax_create_assert( $ax_create_results, 'a source-event key cannot be silently reused for a different semantic Activity', is_wp_error( $source_conflict ) && 'ax_act_source_conflict' === $source_conflict->get_error_code() );

	$locked_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $author_id, 'post_title' => 'Create locked', 'post_password' => 'secret' ) );
	$attachment_id = wp_insert_attachment( array( 'post_title' => 'Create attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'post_author' => $author_id ) );
	$ax_create_posts = array_merge( $ax_create_posts, array( $locked_id, $attachment_id ) );
	ax_create_assert( $ax_create_results, 'password-protected posts and media uploads never create Activities', array() === axismundi_act_get_by_object( axismundi_op_post_object_uri( get_post( $locked_id ) ) ) );
	ax_create_assert( $ax_create_results, 'the bridge performs no HTTP request or delivery', 0 === $GLOBALS['ax_create_http'] );
} finally {
	remove_filter( 'pre_http_request', 'ax_create_http' );
	remove_all_filters( 'axismundi_op_post_actor_uri' );
	remove_all_filters( 'axismundi_op_post_lifecycle_owner' );
	global $wpdb;
	foreach ( array_unique( $ax_create_objects ) as $uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( array_filter( $ax_create_posts, 'is_int' ) as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	if ( $ax_create_identity_id > 0 ) {
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $ax_create_identity_id ), array( '%d' ) );
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $ax_create_identity_id ), array( '%d' ) );
	}
}

$ax_create_failures = count( array_filter( $ax_create_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_create_results ), $ax_create_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_create_failures > 0 ? 1 : 0 );
}
exit( $ax_create_failures > 0 ? 1 : 0 );
