<?php
/** Forum moderation and FEP-1b12 distribution regression (dev-only). */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/managed-groups.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/relations.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/local-social.php';
require_once __DIR__ . '/../includes/repository.php';
require_once __DIR__ . '/../includes/topics.php';
require_once __DIR__ . '/../includes/memberships.php';
require_once __DIR__ . '/../includes/moderators.php';
require_once __DIR__ . '/../includes/distribution.php';
require_once __DIR__ . '/../includes/admin.php';

axismundi_forum_install();
axismundi_forum_register_topic_post_type();

global $wpdb;
$ax_fmod_results = array();
$ax_fmod_users = array();
$ax_fmod_identities = array();
$ax_fmod_posts = array();

function ax_fmod_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/** @return array{0:int,1:Axismundi_Actor|null} */
function ax_fmod_person( array &$users, array &$identities, string $role = 'editor' ) : array {
	$login = 'axfmod' . strtolower( wp_generate_password( 8, false, false ) );
	$user_id = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => $role ) );
	if ( $user_id <= 0 ) { return array( 0, null ); }
	$users[] = $user_id;
	$actor = axismundi_actors_ensure_for_user( $user_id );
	if ( ! $actor instanceof Axismundi_Actor || is_wp_error( axismundi_actors_register_handle( $actor->get_identity_id(), $login ) ) || ! axismundi_actors_set_status( $actor->get_identity_id(), 'public' ) ) {
		return array( $user_id, null );
	}
	$actor = axismundi_actors_get_for_user( $user_id );
	if ( $actor instanceof Axismundi_Actor ) { $identities[] = $actor->get_identity_id(); }
	return array( $user_id, $actor instanceof Axismundi_Actor ? $actor : null );
}

try {
	list( $owner_user, $owner ) = ax_fmod_person( $ax_fmod_users, $ax_fmod_identities );
	list( $admin2_user, $admin2 ) = ax_fmod_person( $ax_fmod_users, $ax_fmod_identities );
	list( $alice_user, $alice ) = ax_fmod_person( $ax_fmod_users, $ax_fmod_identities );
	list( $bob_user, $bob ) = ax_fmod_person( $ax_fmod_users, $ax_fmod_identities );
	list( $editor_user, $editor ) = ax_fmod_person( $ax_fmod_users, $ax_fmod_identities );
	$group = axismundi_actors_create_managed_group( array( 'owner_user_id' => $owner_user, 'preferred_username' => 'axfmodg' . strtolower( wp_generate_password( 6, false, false ) ), 'status' => 'public' ) );
	if ( $group instanceof Axismundi_Actor ) { $ax_fmod_identities[] = $group->get_identity_id(); }
	$group_id = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	if ( $admin2 instanceof Axismundi_Actor && $group instanceof Axismundi_Actor ) { axismundi_act_follow_actor( $admin2, $group ); }
	if ( $alice instanceof Axismundi_Actor && $group instanceof Axismundi_Actor ) { axismundi_act_follow_actor( $alice, $group ); }
	if ( $bob instanceof Axismundi_Actor && $group instanceof Axismundi_Actor ) { axismundi_act_follow_actor( $bob, $group ); }

	$promoted = $alice instanceof Axismundi_Actor ? axismundi_forum_set_actor_moderator( $group_id, $alice->get_identity_id(), $owner_user, true ) : new WP_Error( 'fixture' );
	$add = $alice instanceof Axismundi_Actor ? array_values( array_filter( axismundi_act_get_by_object( $alice->get_uri(), 20 ), static fn( Axismundi_Activity $activity ) : bool => 'Add' === $activity->get_type() ) ) : array();
	$add = empty( $add ) ? null : reset( $add );
	$add_wrapper = $add instanceof Axismundi_Activity ? array_values( array_filter( axismundi_act_get_by_object( $add->get_uri(), 20 ), static fn( Axismundi_Activity $activity ) : bool => 'Announce' === $activity->get_type() ) ) : array();
	$add_wrapper = empty( $add_wrapper ) ? null : reset( $add_wrapper );
	$group_projection = function_exists( 'axismundi_op_transform_object' ) ? axismundi_op_transform_object( $group ) : null;
	$moderator_url = $group instanceof Axismundi_Actor ? axismundi_forum_moderator_collection_url( $group ) : '';
	ax_fmod_assert(
		$ax_fmod_results,
		'a Group manager is an effective moderator and promotes an accepted Actor member through Announce(Add) to the attributedTo collection',
		true === $promoted
			&& axismundi_forum_user_can_moderate( $group_id, $owner_user )
			&& $alice instanceof Axismundi_Actor
			&& axismundi_forum_actor_is_moderator( $group_id, $alice )
			&& $add instanceof Axismundi_Activity && $owner instanceof Axismundi_Actor && $owner->get_uri() === $add->get_actor_uri()
			&& $moderator_url === $add->get_target_uri() && $add_wrapper instanceof Axismundi_Activity && $group instanceof Axismundi_Actor
			&& $group->get_uri() === $add_wrapper->get_actor_uri() && is_array( $group_projection) && $moderator_url === (string) ( $group_projection['attributedTo'] ?? '' )
		);
	$alice_communities = axismundi_forum_moderated_communities( $alice_user );
	ax_fmod_assert(
		$ax_fmod_results,
		'an explicit Actor moderator appears in their moderated community list without local manager delegation',
		$alice instanceof Axismundi_Actor
			&& isset( $alice_communities[ $group_id ] )
			&& ! axismundi_actors_managed_actor_can_manage( $group_id, $alice_user )
	);
	axismundi_actors_add_manager( $group_id, $editor_user, 'editor' );
	$editor_denied = $bob instanceof Axismundi_Actor ? axismundi_forum_set_actor_moderator( $group_id, $bob->get_identity_id(), $editor_user, true ) : new WP_Error( 'fixture' );
	ax_fmod_assert(
		$ax_fmod_results,
		'a delegated editor cannot change the public moderator collection',
		is_wp_error( $editor_denied ) && 'ax_forum_forbidden' === $editor_denied->get_error_code()
	);
	$admin2_moderator = $admin2 instanceof Axismundi_Actor ? axismundi_forum_set_actor_moderator( $group_id, $admin2->get_identity_id(), $owner_user, true ) : new WP_Error( 'fixture' );
	$admin2_manager = $admin2 instanceof Axismundi_Actor ? axismundi_forum_promote_moderator_to_manager( $group_id, $admin2->get_identity_id(), $owner_user ) : new WP_Error( 'fixture' );
	$alice_manager = $alice instanceof Axismundi_Actor ? axismundi_forum_promote_moderator_to_manager( $group_id, $alice->get_identity_id(), $admin2_user ) : new WP_Error( 'fixture' );
	$admin2_removed = $admin2 instanceof Axismundi_Actor ? axismundi_forum_remove_moderator_manager( $group_id, $admin2->get_identity_id(), $alice_user ) : new WP_Error( 'fixture' );
	$alice_manager_removed = $alice instanceof Axismundi_Actor ? axismundi_forum_remove_moderator_manager( $group_id, $alice->get_identity_id(), $owner_user ) : new WP_Error( 'fixture' );
	ax_fmod_assert(
		$ax_fmod_results,
		'a manager moderator delegates manager access to Alice, who can revoke admin2 without changing either Actor moderator role',
		true === $admin2_moderator && true === $admin2_manager && true === $alice_manager && true === $admin2_removed && true === $alice_manager_removed
			&& $alice instanceof Axismundi_Actor && $admin2 instanceof Axismundi_Actor
			&& axismundi_forum_actor_is_moderator( $group_id, $alice ) && axismundi_forum_actor_is_moderator( $group_id, $admin2 )
			&& ! axismundi_actors_managed_actor_can_manage( $group_id, $admin2_user )
			&& ! axismundi_actors_managed_actor_can_manage( $group_id, $alice_user )
	);
	wp_set_current_user( $owner_user );
	ob_start();
	axismundi_forum_render_group_admin_section( $group );
	$manager_screen = (string) ob_get_clean();
	ax_fmod_assert(
		$ax_fmod_results,
		'a Group manager sees the explicit moderator control beside an accepted member',
		str_contains( $manager_screen, 'Remove moderator' ) && str_contains( $manager_screen, 'axismundi_forum_moderator_decision' )
	);
	wp_set_current_user( 0 );

	$topic_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_FORUM_TOPIC_POST_TYPE, 'post_status' => 'publish', 'post_author' => $bob_user, 'post_title' => 'Pending Topic' ) );
	if ( $topic_id > 0 ) { $ax_fmod_posts[] = $topic_id; }
	$topic = get_post( $topic_id );
	$approval_policy = axismundi_forum_set_topic_approval_policy( $group_id, $owner_user, 'approval' );
	$admitted = true === $approval_policy ? axismundi_forum_admit_local_topic( $group_id, $topic_id, $bob_user ) : $approval_policy;
	$pending_entry = axismundi_forum_get_topic_entry( $topic_id );
	$create = is_array( $pending_entry ) ? axismundi_act_get( (string) ( $pending_entry['accepted_activity_uri'] ?? '' ) ) : null;
	ax_fmod_assert(
		$ax_fmod_results,
		'a local Topic records one direct Create to its Group before it waits in the pending queue, never the author public feed',
		$create instanceof Axismundi_Activity && true === $admitted && is_array( $pending_entry) && $group instanceof Axismundi_Actor
			&& 'pending' === (string) $pending_entry['admission_state']
			&& $create->get_uri() === (string) $pending_entry['accepted_activity_uri']
			&& in_array( $group->get_uri(), (array) ( $create->get_audience()['to'] ?? array() ), true )
			&& ! axismundi_act_has_public_audience( $create )
			&& function_exists( 'axismundi_act_actor_feed_item' ) && null === axismundi_act_actor_feed_item( $create )
			&& 1 === count( axismundi_forum_pending_topic_entries( $group_id ) )
	);
	wp_set_current_user( $alice_user );
	ob_start();
	axismundi_forum_render_group_admin_section( $group );
	$moderator_screen = (string) ob_get_clean();
	ax_fmod_assert(
		$ax_fmod_results,
		'an Actor moderator may review a Group Topic queue with an explicit rejection reason and approve or reject controls without receiving manager-only policy controls',
		str_contains( $moderator_screen, 'Topic submissions' ) && str_contains( $moderator_screen, 'Reason required when rejecting' ) && str_contains( $moderator_screen, 'Approve and announce' ) && str_contains( $moderator_screen, 'Reject submission' ) && ! str_contains( $moderator_screen, 'Save community settings' )
	);
	wp_set_current_user( 0 );

	$approved = is_array( $pending_entry ) ? axismundi_forum_approve_pending_entry( (int) $pending_entry['id'], $alice_user ) : new WP_Error( 'fixture' );
	$approved_entry = axismundi_forum_get_topic_entry( $topic_id );
	$outer = is_array( $approved_entry ) ? axismundi_act_get( (string) $approved_entry['announced_activity_uri'] ) : null;
	$followers = function_exists( 'axismundi_op_actor_followers_url' ) && $group instanceof Axismundi_Actor ? axismundi_op_actor_followers_url( $group ) : '';
	ax_fmod_assert(
		$ax_fmod_results,
		'a moderator approval records Group Announce(Create) to followers before making the entry visible',
		true === $approved && is_array( $approved_entry) && 'visible' === (string) $approved_entry['admission_state']
			&& $outer instanceof Axismundi_Activity && 'Announce' === $outer->get_type() && $group instanceof Axismundi_Actor
			&& $group->get_uri() === $outer->get_actor_uri() && 'Create' === (string) ( $outer->get_payload()['object']['type'] ?? '' )
			&& in_array( $followers, (array) ( $outer->get_audience()['to'] ?? array() ), true )
	);
	$group_outbox = function_exists( 'axismundi_act_get_public_outbox' ) && $group instanceof Axismundi_Actor
		? axismundi_act_get_public_outbox( $group->get_uri() )
		: array();
	$group_outbox_ids = array_map( static fn( array $payload ) : string => (string) ( $payload['id'] ?? '' ), $group_outbox );
	ax_fmod_assert(
		$ax_fmod_results,
		'the public community Group outbox includes its followers-addressed approval Announce without making ordinary followers-only delivery public',
		$outer instanceof Axismundi_Activity && in_array( $outer->get_uri(), $group_outbox_ids, true )
	);
	$withdrawn = is_array( $approved_entry ) ? axismundi_forum_withdraw_announced_entry( (int) $approved_entry['id'], $alice_user ) : new WP_Error( 'fixture' );
	$withdrawn_entry = axismundi_forum_get_topic_entry( $topic_id );
	$withdrawn_announce = $outer instanceof Axismundi_Activity ? axismundi_act_get( $outer->get_uri() ) : null;
	$undo = $outer instanceof Axismundi_Activity ? axismundi_act_get_by_object( $outer->get_uri(), 10 ) : array();
	$withdrawal = null;
	foreach ( $undo as $candidate ) {
		if ( $candidate instanceof Axismundi_Activity && 'Undo' === $candidate->get_type() && $candidate->is_effective() ) {
			$withdrawal = $candidate;
			break;
		}
	}
	ax_fmod_assert(
		$ax_fmod_results,
		'withdrawing an approved Topic sends the Group direct Undo of its Announce and returns the entry to pending without deleting its source',
		true === $withdrawn && is_array( $withdrawn_entry) && 'pending' === (string) $withdrawn_entry['admission_state']
			&& $withdrawn_announce instanceof Axismundi_Activity && ! $withdrawn_announce->is_effective() && $withdrawal instanceof Axismundi_Activity
			&& $group instanceof Axismundi_Actor && $group->get_uri() === $withdrawal->get_actor_uri()
			&& $outer instanceof Axismundi_Activity && $outer->get_uri() === $withdrawal->get_object_uri()
			&& in_array( $followers, (array) ( $withdrawal->get_audience()['to'] ?? array() ), true ) && $topic instanceof WP_Post && 'publish' === $topic->post_status
	);
	$edited = $topic instanceof WP_Post ? wp_update_post( array( 'ID' => $topic->ID, 'post_content' => 'Revised pending Topic body.' ), true ) : new WP_Error( 'fixture' );
	$reapproved = ! is_wp_error( $edited ) && is_array( $withdrawn_entry ) ? axismundi_forum_approve_pending_entry( (int) $withdrawn_entry['id'], $alice_user ) : new WP_Error( 'fixture' );
	$reapproved_entry = axismundi_forum_get_topic_entry( $topic_id );
	$reannounce = is_array( $reapproved_entry ) ? axismundi_act_get( (string) $reapproved_entry['announced_activity_uri'] ) : null;
	ax_fmod_assert(
		$ax_fmod_results,
		'a withdrawn and edited Topic is re-approved through a fresh Group Announce(Update), never a mutated Create',
		true === $reapproved && $reannounce instanceof Axismundi_Activity && $reannounce->is_effective()
			&& $outer instanceof Axismundi_Activity && $reannounce->get_uri() !== $outer->get_uri()
			&& 'Update' === (string) ( $reannounce->get_payload()['object']['type'] ?? '' )
			&& is_array( $reapproved_entry ) && $reapproved_entry['accepted_activity_uri'] !== $pending_entry['accepted_activity_uri']
	);
	$withdrawn_unchanged = is_array( $reapproved_entry ) ? axismundi_forum_withdraw_announced_entry( (int) $reapproved_entry['id'], $alice_user ) : new WP_Error( 'fixture' );
	$unchanged_entry = axismundi_forum_get_topic_entry( $topic_id );
	$reapproved_unchanged = is_array( $unchanged_entry ) ? axismundi_forum_approve_pending_entry( (int) $unchanged_entry['id'], $alice_user ) : new WP_Error( 'fixture' );
	$unchanged_reapproved_entry = axismundi_forum_get_topic_entry( $topic_id );
	$unchanged_reannounce = is_array( $unchanged_reapproved_entry ) ? axismundi_act_get( (string) $unchanged_reapproved_entry['announced_activity_uri'] ) : null;
	ax_fmod_assert(
		$ax_fmod_results,
		'an unchanged re-approval reuses its immutable Update while issuing only a fresh Group Announce cycle',
		true === $withdrawn_unchanged && true === $reapproved_unchanged && $reannounce instanceof Axismundi_Activity && $unchanged_reannounce instanceof Axismundi_Activity
			&& $reannounce->get_uri() !== $unchanged_reannounce->get_uri()
			&& (string) ( $reannounce->get_payload()['object']['id'] ?? '' ) === (string) ( $unchanged_reannounce->get_payload()['object']['id'] ?? '' )
			&& 'Update' === (string) ( $unchanged_reannounce->get_payload()['object']['type'] ?? '' )
	);

	$remote_uri = 'https://remote.example/topics/' . wp_generate_uuid4();
	$wpdb->insert( axismundi_forum_entries_table(), array( 'group_identity_id' => $group_id, 'object_uri' => $remote_uri, 'object_uri_hash' => hash( 'sha256', $remote_uri ), 'entry_type' => 'topic', 'admission_state' => 'visible', 'moderation_state' => 'visible', 'created_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$remote_entry_id = (int) $wpdb->insert_id;
	$held = axismundi_forum_set_entry_moderation_state( $remote_entry_id, $editor_user, 'pending' );
	$remote_entry = axismundi_forum_get_entry( $remote_entry_id );
	ax_fmod_assert(
		$ax_fmod_results,
		'a site editor can place a remote-only entry with no WordPress post into the internal moderation queue',
		true === $held && is_array( $remote_entry) && 'pending' === (string) $remote_entry['moderation_state'] && empty( $remote_entry['source_post_id'] )
	);
	$demoted = $alice instanceof Axismundi_Actor ? axismundi_forum_set_actor_moderator( $group_id, $alice->get_identity_id(), $owner_user, false ) : new WP_Error( 'fixture' );
	$remove = $alice instanceof Axismundi_Actor ? array_values( array_filter( axismundi_act_get_by_object( $alice->get_uri(), 20 ), static fn( Axismundi_Activity $activity ) : bool => 'Remove' === $activity->get_type() ) ) : array();
	$remove = empty( $remove ) ? null : reset( $remove );
	$remove_wrapper = $remove instanceof Axismundi_Activity ? array_values( array_filter( axismundi_act_get_by_object( $remove->get_uri(), 20 ), static fn( Axismundi_Activity $activity ) : bool => 'Announce' === $activity->get_type() ) ) : array();
	$remove_wrapper = empty( $remove_wrapper ) ? null : reset( $remove_wrapper );
	$effective = axismundi_forum_effective_moderators( $group_id );
	ax_fmod_assert(
		$ax_fmod_results,
		'demoting an explicit moderator records Announce(Remove) and removes that Actor from attributedTo without changing local manager delegation',
		true === $demoted && $alice instanceof Axismundi_Actor && ! isset( $effective[ $alice->get_identity_id() ] )
			&& $remove instanceof Axismundi_Activity && $moderator_url === $remove->get_target_uri()
			&& $remove_wrapper instanceof Axismundi_Activity && $group instanceof Axismundi_Actor && $group->get_uri() === $remove_wrapper->get_actor_uri()
			&& axismundi_forum_user_can_manage( $group_id, $owner_user )
	);
} finally {
	foreach ( $ax_fmod_posts as $post_id ) { wp_delete_post( $post_id, true ); }
	foreach ( $ax_fmod_identities as $identity_id ) {
		$wpdb->delete( axismundi_forum_memberships_table(), array( 'group_identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( axismundi_forum_memberships_table(), array( 'actor_identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
	foreach ( $ax_fmod_users as $user_id ) { wp_delete_user( $user_id ); }
}

$failed = count( array_filter( $ax_fmod_results, static fn( $value ) : bool => ! $value ) );
printf( "\n%d/%d passed\n", count( $ax_fmod_results ) - $failed, count( $ax_fmod_results ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
exit( $failed > 0 ? 1 : 0 );
