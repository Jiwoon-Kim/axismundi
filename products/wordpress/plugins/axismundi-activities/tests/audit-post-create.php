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
$ax_create_identities = array();
$ax_create_users = array();
$GLOBALS['ax_create_http'] = 0;

/** @param bool[] $results Results. */
function ax_create_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Pin the fixture's publishing identity through the command seam. */
function ax_create_pin_actor() : string {
	return (string) $GLOBALS['ax_create_actor_uri'];
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

	// A dedicated author, so the fixture never mutates an administrator's own Actor.
	$author_id = (int) wp_insert_user( array( 'user_login' => 'ax-create-author-' . wp_generate_password( 8, false, false ), 'user_pass' => wp_generate_password( 20 ), 'role' => 'administrator' ) );
	$ax_create_users[] = $author_id;
	wp_set_current_user( $author_id );
	$site = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'local_user_id' => $author_id, 'preferred_username' => 'create-author-' . strtolower( wp_generate_password( 8, false, false ) ) ) );
	if ( $site instanceof Axismundi_Actor ) {
		$ax_create_identity_id = $site->get_identity_id();
		axismundi_actors_set_status( $ax_create_identity_id, 'public' );
		$site = axismundi_actors_get_by_identity( $ax_create_identity_id );
	}
	$actor_uri = $site instanceof Axismundi_Actor ? $site->get_uri() : '';
	$GLOBALS['ax_create_actor_uri'] = $actor_uri;
	add_filter( 'axismundi_op_post_actor_uri', static fn() : string => $actor_uri );
	axismundi_actors_set_acting_actor( $author_id, $ax_create_identity_id );
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
	$after_republish = axismundi_act_get_by_object( $object_uri );
	$after_republish_types = array_map( static fn( Axismundi_Activity $activity ) : string => $activity->get_type(), $after_republish );
	ax_create_assert( $ax_create_results, 'an Article withdrawal records Delete and a later publication starts a new Create generation', 3 === count( $after_republish ) && 2 === count( array_filter( $after_republish_types, static fn( string $type ) : bool => 'Create' === $type ) ) && 1 === count( array_filter( $after_republish_types, static fn( string $type ) : bool => 'Delete' === $type ) ) );

	$delete = axismundi_act_record_activity( array( 'type' => 'Delete', 'actor' => $actor_uri, 'object' => $object_uri ), 'outbound' );
	wp_update_post( array( 'ID' => $post_id, 'post_title' => 'Create bridge resurrected' ) );
	$after_delete = axismundi_act_get_by_object( $object_uri );
	ax_create_assert( $ax_create_results, 'an effective Delete starts a new lifecycle generation and permits one resurrection Create', $delete instanceof Axismundi_Activity && 5 === count( $after_delete ) && 'Create' === $after_delete[0]->get_type() );

	$permanent_id = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $author_id, 'post_title' => 'Permanent delete bridge' ) );
	$permanent_post = $permanent_id > 0 ? get_post( $permanent_id ) : null;
	$permanent_uri = $permanent_post instanceof WP_Post ? axismundi_op_post_object_uri( $permanent_post ) : '';
	$ax_create_objects[] = $permanent_uri;
	$permanently_deleted = $permanent_id > 0 && false !== wp_delete_post( $permanent_id, true );
	$permanent_lifecycle = '' !== $permanent_uri ? axismundi_act_get_object_lifecycle( $permanent_uri ) : null;
	ax_create_assert( $ax_create_results, 'permanent Article deletion records Delete before WordPress removes its source post', $permanently_deleted && $permanent_lifecycle instanceof Axismundi_Activity && 'Delete' === $permanent_lifecycle->get_type() );

	// The command decides the publishing identity; the projection no longer does.
	$recorded_wins = axismundi_act_publish_actor_uri( get_post( $post_id ), $object_uri );
	ax_create_assert( $ax_create_results, 'a committed lifecycle settles attribution, so a later save never re-derives it', $actor_uri === $recorded_wins );

	$proxy_id = (int) wp_insert_user( array( 'user_login' => 'ax-create-proxy-' . wp_generate_password( 8, false, false ), 'user_pass' => wp_generate_password( 20 ), 'role' => 'administrator' ) );
	$proxy_actor = $proxy_id > 0 ? axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'local_user_id' => $proxy_id, 'preferred_username' => 'create-proxy-' . strtolower( wp_generate_password( 8, false, false ) ) ) ) : null;
	if ( $proxy_actor instanceof Axismundi_Actor ) {
		$ax_create_identities[] = $proxy_actor->get_identity_id();
		axismundi_actors_set_status( $proxy_actor->get_identity_id(), 'public' );
	}
	$ax_create_users[] = $proxy_id;
	$unpublished_id = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => $author_id, 'post_title' => 'Proxy save' ) );
	$ax_create_posts[] = $unpublished_id;
	wp_set_current_user( $proxy_id );
	$proxy_resolved = axismundi_act_publish_actor_uri( get_post( $unpublished_id ), '' );
	wp_set_current_user( $author_id );
	ax_create_assert( $ax_create_results, 'a save made on behalf of another account never hands that post to the saver identity', $proxy_actor instanceof Axismundi_Actor && $proxy_actor->get_uri() !== $proxy_resolved );

	// Attribution belongs to the Create, not to whoever performed the newest Activity.
	$attrib_id = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $author_id, 'post_title' => 'Foreign update' ) );
	$ax_create_posts[] = $attrib_id;
	$attrib_uri = $attrib_id > 0 ? axismundi_op_post_object_uri( get_post( $attrib_id ) ) : '';
	$ax_create_objects[] = $attrib_uri;
	$foreign_uri = $proxy_actor instanceof Axismundi_Actor ? $proxy_actor->get_uri() : '';
	axismundi_act_record_activity( array( 'type' => 'Update', 'actor' => $foreign_uri, 'object' => $attrib_uri ), 'outbound' );
	$attrib_latest = '' !== $attrib_uri ? axismundi_act_get_object_lifecycle( $attrib_uri ) : null;
	$attribution = '' !== $attrib_uri ? axismundi_act_get_object_attribution( $attrib_uri ) : '';
	$attrib_resolved = $attrib_id > 0 ? axismundi_act_publish_actor_uri( get_post( $attrib_id ), $attrib_uri ) : '';
	ax_create_assert( $ax_create_results, 'an Update performed by another Actor does not hand the Object to them', '' !== $foreign_uri && $attrib_latest instanceof Axismundi_Activity && $foreign_uri === $attrib_latest->get_actor_uri() && $actor_uri === $attribution && $actor_uri === $attrib_resolved );

	$attrib_deleted = $attrib_id > 0 && false !== wp_delete_post( $attrib_id, true );
	$attrib_final = '' !== $attrib_uri ? axismundi_act_get_object_lifecycle( $attrib_uri ) : null;
	ax_create_assert( $ax_create_results, 'the Actor an Object is attributed to can still withdraw it after someone else updated it', $attrib_deleted && $attrib_final instanceof Axismundi_Activity && 'Delete' === $attrib_final->get_type() && $actor_uri === $attrib_final->get_actor_uri() );

	// The acting Actor is read live and re-checked, not taken from a stored preference.
	$org = axismundi_actors_create_managed_actor( array( 'owner_user_id' => $author_id, 'actor_type' => 'Organization', 'preferred_username' => 'create-org-' . strtolower( wp_generate_password( 8, false, false ) ) ) );
	$org_uri = '';
	if ( $org instanceof Axismundi_Actor ) {
		$ax_create_identities[] = $org->get_identity_id();
		axismundi_actors_set_status( $org->get_identity_id(), 'public' );
		$org = axismundi_actors_get_by_identity( $org->get_identity_id() );
		$org_uri = $org instanceof Axismundi_Actor ? $org->get_uri() : '';
		axismundi_actors_set_acting_actor( $author_id, $org->get_identity_id() );
	}
	// Publishing as the Organization, then proving only its managers may withdraw that.
	$org_published_id = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $author_id, 'post_title' => 'Organization article' ) );
	$org_published_uri = $org_published_id > 0 ? axismundi_op_post_object_uri( get_post( $org_published_id ) ) : '';
	$ax_create_objects[] = $org_published_uri;
	$org_attribution = '' !== $org_published_uri ? axismundi_act_get_object_attribution( $org_published_uri ) : '';
	wp_set_current_user( $proxy_id );
	$org_foreign_delete = axismundi_act_submit_c2s( $proxy_id, 0, array( 'operation' => 'Delete', 'post_id' => $org_published_id ) );
	wp_set_current_user( $author_id );
	$org_delete_rows = '' !== $org_published_uri ? array_filter( axismundi_act_get_by_object( $org_published_uri ), static fn( Axismundi_Activity $a ) : bool => 'Delete' === $a->get_type() ) : array();
	ax_create_assert( $ax_create_results, 'an account that may delete the post but does not manage its Organization cannot withdraw it in that name', '' !== $org_uri && $org_uri === $org_attribution && $org_foreign_delete instanceof WP_Error && 'ax_act_c2s_permission' === $org_foreign_delete->get_error_code() && array() === $org_delete_rows );

	$org_owner_deleted = $org_published_id > 0 && false !== wp_delete_post( $org_published_id, true );
	$org_final = '' !== $org_published_uri ? axismundi_act_get_object_lifecycle( $org_published_uri ) : null;
	ax_create_assert( $ax_create_results, 'a manager of that Organization withdraws the same object normally', $org_owner_deleted && $org_final instanceof Axismundi_Activity && 'Delete' === $org_final->get_type() && $org_uri === $org_final->get_actor_uri() );

	$org_post_id = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => $author_id, 'post_title' => 'Organization draft' ) );
	$ax_create_posts[] = $org_post_id;
	$org_resolved = $org_post_id > 0 ? axismundi_act_publish_actor_uri( get_post( $org_post_id ), '' ) : '';
	// A sole owner cannot be removed, so eligibility is withdrawn the way it actually is.
	if ( $org instanceof Axismundi_Actor ) {
		axismundi_actors_set_status( $org->get_identity_id(), 'disabled' );
	}
	$org_after_revoke = $org_post_id > 0 ? axismundi_act_publish_actor_uri( get_post( $org_post_id ), '' ) : '';
	ax_create_assert( $ax_create_results, 'an author publishing as an Organization they manage gets that identity, and loses it the moment that identity stops being eligible', '' !== $org_uri && $org_uri === $org_resolved && $org_uri !== $org_after_revoke );

	// The Delete must be signed by whoever published, or the source post becomes unremovable.
	$diverged_id = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $author_id, 'post_title' => 'Diverged attribution' ) );
	$diverged_post = $diverged_id > 0 ? get_post( $diverged_id ) : null;
	$diverged_uri = $diverged_post instanceof WP_Post ? axismundi_op_post_object_uri( $diverged_post ) : '';
	$ax_create_objects[] = $diverged_uri;
	$diverged_created = '' !== $diverged_uri && axismundi_act_get_object_lifecycle( $diverged_uri ) instanceof Axismundi_Activity;
	remove_all_filters( 'axismundi_op_post_actor_uri' );
	add_filter( 'axismundi_op_post_actor_uri', static fn() : string => 'https://example.com/actors/derived-elsewhere' );
	$diverged_deleted = $diverged_id > 0 && false !== wp_delete_post( $diverged_id, true );
	$diverged_lifecycle = '' !== $diverged_uri ? axismundi_act_get_object_lifecycle( $diverged_uri ) : null;
	remove_all_filters( 'axismundi_op_post_actor_uri' );
	add_filter( 'axismundi_op_post_actor_uri', static fn() : string => (string) $GLOBALS['ax_create_actor_uri'] );
	ax_create_assert( $ax_create_results, 'an Article whose attribution differs from its derived author still deletes, signed by the Actor that published it', $diverged_created && $diverged_deleted && $diverged_lifecycle instanceof Axismundi_Activity && 'Delete' === $diverged_lifecycle->get_type() && $actor_uri === $diverged_lifecycle->get_actor_uri() );

	// The earlier divergence fixture pinned a projected Actor; attribution must answer for itself here.
	remove_all_filters( 'axismundi_op_post_actor_uri' );
	// A scheduled publication has no session and therefore no chosen identity.
	$when = gmdate( 'Y-m-d H:i:s', time() + 60 );
	$scheduled_id = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'future', 'post_author' => $author_id, 'post_title' => 'Scheduled publication', 'post_date_gmt' => $when, 'post_date' => get_date_from_gmt( $when ) ) );
	$ax_create_posts[] = $scheduled_id;
	$scheduled_uri = $scheduled_id > 0 ? axismundi_op_post_object_uri( get_post( $scheduled_id ) ) : '';
	$ax_create_objects[] = $scheduled_uri;
	wp_set_current_user( 0 );
	wp_publish_post( $scheduled_id );
	wp_set_current_user( $author_id );
	$scheduled_object = $scheduled_id > 0 ? axismundi_op_transform_object( get_post( $scheduled_id ) ) : null;
	ax_create_assert( $ax_create_results, 'a scheduled publication becomes public without federating under an identity nobody selected', 'publish' === get_post_status( $scheduled_id ) && array() === axismundi_act_get_by_object( $scheduled_uri ) && is_array( $scheduled_object ) && ! array_key_exists( 'attributedTo', $scheduled_object ) );

	// The command service refuses what it cannot verify, before anything reaches the ledger.
	$guard_id = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => $author_id, 'post_title' => 'Command guards' ) );
	$ax_create_posts[] = $guard_id;
	$guard_version = (string) get_post( $guard_id )->post_modified_gmt;
	$guard_command = static fn( array $over = array() ) : array => array_merge( array( 'operation' => 'Create', 'post_id' => $guard_id, 'expected_version' => $guard_version ), $over );
	$guard_stale = axismundi_act_submit_c2s( $author_id, $ax_create_identity_id, $guard_command( array( 'expected_version' => '2000-01-01 00:00:00' ) ) );
	$guard_unversioned = axismundi_act_submit_c2s( $author_id, $ax_create_identity_id, $guard_command( array( 'expected_version' => '' ) ) );
	$guard_operation = axismundi_act_submit_c2s( $author_id, $ax_create_identity_id, $guard_command( array( 'operation' => 'Update' ) ) );
	$guard_principal = axismundi_act_submit_c2s( 0, $ax_create_identity_id, $guard_command() );
	$guard_identity = $proxy_actor instanceof Axismundi_Actor ? axismundi_act_submit_c2s( $author_id, $proxy_actor->get_identity_id(), $guard_command() ) : null;
	$guard_subject = axismundi_act_submit_c2s( $author_id, $ax_create_identity_id, $guard_command( array( 'post_id' => 0 ) ) );
	$guard_codes = array_map( static fn( $r ) : string => $r instanceof WP_Error ? $r->get_error_code() : 'not-an-error', array( $guard_stale, $guard_unversioned, $guard_operation, $guard_principal, $guard_identity, $guard_subject ) );
	ax_create_assert( $ax_create_results, 'the command service refuses a stale version, a missing version, an operation it does not perform, an unauthenticated principal, an identity the principal may not use, and a subject that does not exist', array( 'ax_act_c2s_stale', 'ax_act_c2s_version', 'ax_act_c2s_operation', 'ax_act_c2s_principal', 'ax_act_c2s_permission', 'ax_act_c2s_subject' ) === $guard_codes && array() === axismundi_act_get_by_object( axismundi_op_post_object_uri( get_post( $guard_id ) ) ) );

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
	axismundi_actors_set_acting_actor( $author_id, 0 );
	remove_all_filters( 'axismundi_op_post_lifecycle_owner' );
	global $wpdb;
	foreach ( array_unique( $ax_create_objects ) as $uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( array_filter( $ax_create_posts, 'is_int' ) as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( array_unique( array_filter( $ax_create_identities ) ) as $identity_id ) {
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity_id ), array( '%d' ) );
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ), array( '%d' ) );
	}
	axismundi_actors_set_acting_actor( $author_id, 0 );
	foreach ( array_filter( $ax_create_users ) as $user_id ) {
		wp_delete_user( $user_id );
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
