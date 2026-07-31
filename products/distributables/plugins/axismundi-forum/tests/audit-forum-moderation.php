<?php
/** Forum moderation and FEP-1b12 distribution regression (dev-only). */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/managed-groups.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/relations.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/local-social.php';
require_once WP_PLUGIN_DIR . '/axismundi-activitypub-bridge/includes/transport.php';
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
	list( $site_editor_user, $site_editor ) = ax_fmod_person( $ax_fmod_users, $ax_fmod_identities );
	list( $contributor_user, $contributor ) = ax_fmod_person( $ax_fmod_users, $ax_fmod_identities, 'contributor' );
	$group = axismundi_actors_create_managed_group( array( 'owner_user_id' => $owner_user, 'preferred_username' => 'axfmodg' . strtolower( wp_generate_password( 6, false, false ) ), 'status' => 'public' ) );
	if ( $group instanceof Axismundi_Actor ) { $ax_fmod_identities[] = $group->get_identity_id(); }
	$group_id = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	if ( $owner instanceof Axismundi_Actor && $group instanceof Axismundi_Actor ) { axismundi_act_follow_actor( $owner, $group ); }
	if ( $admin2 instanceof Axismundi_Actor && $group instanceof Axismundi_Actor ) { axismundi_act_follow_actor( $admin2, $group ); }
	if ( $alice instanceof Axismundi_Actor && $group instanceof Axismundi_Actor ) { axismundi_act_follow_actor( $alice, $group ); }
	if ( $bob instanceof Axismundi_Actor && $group instanceof Axismundi_Actor ) { axismundi_act_follow_actor( $bob, $group ); }
	if ( $contributor instanceof Axismundi_Actor && $group instanceof Axismundi_Actor ) { axismundi_act_follow_actor( $contributor, $group ); }

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
			&& function_exists( 'axismundi_act_public_audience_uri' ) && in_array( axismundi_act_public_audience_uri(), (array) ( $add->get_audience()['to'] ?? array() ), true )
			&& in_array( $group->get_uri(), (array) ( $add->get_audience()['cc'] ?? array() ), true )
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
	$owner_explicit = $owner instanceof Axismundi_Actor ? axismundi_forum_set_actor_moderator( $group_id, $owner->get_identity_id(), $owner_user, true ) : new WP_Error( 'fixture' );
	$owner_demote = $owner instanceof Axismundi_Actor ? axismundi_forum_set_actor_moderator( $group_id, $owner->get_identity_id(), $owner_user, false ) : new WP_Error( 'fixture' );
	$owner_membership = $owner instanceof Axismundi_Actor ? axismundi_forum_get_membership( $group_id, $owner->get_identity_id() ) : null;
	ob_start();
	axismundi_forum_render_group_admin_section( $group );
	$manager_screen = (string) ob_get_clean();
	ax_fmod_assert(
		$ax_fmod_results,
		'a Group manager is labelled as a derived moderator rather than being offered a redundant explicit moderator transition',
		true === $owner_explicit && is_wp_error( $owner_demote ) && 'ax_forum_moderator_manager' === $owner_demote->get_error_code() && $owner instanceof Axismundi_Actor
			&& is_array( $owner_membership ) && 'member' === (string) $owner_membership['membership_role']
			&& str_contains( $manager_screen, 'Moderator (manager)' )
			&& ! str_contains( $manager_screen, 'axismundi_forum_moderator_' . $group_id . '_' . $owner->get_identity_id() )
			&& str_contains( $manager_screen, 'Remove moderator' ) && str_contains( $manager_screen, 'axismundi_forum_moderator_decision' )
	);
	wp_set_current_user( 0 );

	$topic_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_FORUM_TOPIC_POST_TYPE, 'post_status' => 'publish', 'post_author' => $bob_user, 'post_title' => 'Pending Topic' ) );
	if ( $topic_id > 0 ) { $ax_fmod_posts[] = $topic_id; }
	$topic = get_post( $topic_id );
	$approval_policy = axismundi_forum_set_topic_approval_policy( $group_id, $owner_user, 'approval' );
	$admitted = true === $approval_policy ? axismundi_forum_admit_local_topic( $group_id, $topic_id, $bob_user ) : $approval_policy;
	$pending_entry = axismundi_forum_get_topic_entry( $topic_id );
	$create = is_array( $pending_entry ) ? axismundi_act_get( (string) ( $pending_entry['accepted_activity_uri'] ?? '' ) ) : null;
	$author_outbox = $bob instanceof Axismundi_Actor && function_exists( 'axismundi_act_get_public_outbox' ) ? axismundi_act_get_public_outbox( $bob->get_uri() ) : array();
	$author_outbox_ids = array_map( static fn( array $payload ) : string => (string) ( $payload['id'] ?? '' ), $author_outbox );
	ax_fmod_assert(
		$ax_fmod_results,
		'a public local Topic records one Public-routable direct Create to its Group before it waits in the pending queue, never the author profile feed or outbox',
		$create instanceof Axismundi_Activity && true === $admitted && is_array( $pending_entry) && $group instanceof Axismundi_Actor
			&& 'pending' === (string) $pending_entry['admission_state']
			&& $create->get_uri() === (string) $pending_entry['accepted_activity_uri']
			&& in_array( $group->get_uri(), (array) ( $create->get_audience()['to'] ?? array() ), true )
			&& axismundi_act_has_public_audience( $create )
			&& function_exists( 'axismundi_act_actor_feed_item' ) && null === axismundi_act_actor_feed_item( $create )
			&& ! in_array( $create->get_uri(), $author_outbox_ids, true )
			&& function_exists( 'axismundi_activitypub_bridge_is_direct_group_submission' ) && axismundi_activitypub_bridge_is_direct_group_submission( $create )
			&& 1 === count( axismundi_forum_pending_topic_entries( $group_id ) )
	);
	$cc_addressed_create = $create instanceof Axismundi_Activity && $group instanceof Axismundi_Actor
		? Axismundi_Activity::from_row(
			array(
				'id'               => 0,
				'activity_uri'     => home_url( '/activities/fixture-topic-cc-addressed/' ),
				'local_uuid'       => null,
				'activity_type'    => 'Create',
				'actor_uri'        => $create->get_actor_uri(),
				'object_uri'       => $create->get_object_uri(),
				'target_uri'       => null,
				'instrument_uri'   => null,
				'direction'        => 'outbound',
				'effective_status' => 'active',
				'audience'         => array( 'to' => array( axismundi_act_public_audience_uri() ), 'cc' => array( $group->get_uri() ) ),
				'payload'          => $create->get_payload(),
				'published_at'     => null,
			)
		)
		: null;
	ax_fmod_assert(
		$ax_fmod_results,
		'a direct Topic submission remains out of the Person feed and public outbox when its Group address is carried in cc',
		$cc_addressed_create instanceof Axismundi_Activity
			&& axismundi_forum_is_direct_topic_submission_activity( $cc_addressed_create )
			&& null === axismundi_forum_topic_submission_public_outbox_payload( array(), $cc_addressed_create )
			&& ! axismundi_forum_topic_submission_actor_feed_visible( true, $cc_addressed_create )
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
		'a public-scope moderator approval records Group Announce(Create) to Public and followers before making the entry visible',
		true === $approved && is_array( $approved_entry) && 'visible' === (string) $approved_entry['admission_state']
			&& $outer instanceof Axismundi_Activity && 'Announce' === $outer->get_type() && $group instanceof Axismundi_Actor
			&& $group->get_uri() === $outer->get_actor_uri() && 'Create' === (string) ( $outer->get_payload()['object']['type'] ?? '' )
			&& function_exists( 'axismundi_act_public_audience_uri' ) && in_array( axismundi_act_public_audience_uri(), (array) ( $outer->get_audience()['to'] ?? array() ), true )
			&& in_array( $followers, (array) ( $outer->get_audience()['cc'] ?? array() ), true )
	);
	$group_outbox = function_exists( 'axismundi_act_get_public_outbox' ) && $group instanceof Axismundi_Actor
		? axismundi_act_get_public_outbox( $group->get_uri() )
		: array();
	$group_outbox_ids = array_map( static fn( array $payload ) : string => (string) ( $payload['id'] ?? '' ), $group_outbox );
	ax_fmod_assert(
		$ax_fmod_results,
		'the public community Group outbox includes its Public approval Announce without opening ordinary followers-only delivery',
		$outer instanceof Axismundi_Activity && in_array( $outer->get_uri(), $group_outbox_ids, true )
	);
	wp_set_current_user( $bob_user );
	$visible_edit = $topic instanceof WP_Post ? wp_update_post( array( 'ID' => $topic->ID, 'post_content' => 'Visible Topic revision.' ), true ) : new WP_Error( 'fixture' );
	wp_set_current_user( 0 );
	$edited_visible_entry = axismundi_forum_get_topic_entry( $topic_id );
	$visible_update_announce = is_array( $edited_visible_entry ) ? axismundi_act_get( (string) $edited_visible_entry['announced_activity_uri'] ) : null;
	$distributions = is_array( $edited_visible_entry ) ? axismundi_forum_entry_distributions( (int) $edited_visible_entry['id'] ) : array();
	ax_fmod_assert(
		$ax_fmod_results,
		'editing a visible local Topic records its Person Update and a fresh Group Announce(Update) while retaining the Create distribution',
		! is_wp_error( $visible_edit) && $outer instanceof Axismundi_Activity && $outer->is_effective()
			&& $visible_update_announce instanceof Axismundi_Activity && $visible_update_announce->is_effective()
			&& $visible_update_announce->get_uri() !== $outer->get_uri()
			&& 'Update' === (string) ( $visible_update_announce->get_payload()['object']['type'] ?? '' )
			&& 2 === count( $distributions )
	);
	$withdrawn = is_array( $approved_entry ) ? axismundi_forum_withdraw_announced_entry( (int) $approved_entry['id'], $alice_user ) : new WP_Error( 'fixture' );
	$withdrawn_entry = axismundi_forum_get_topic_entry( $topic_id );
	$withdrawn_announce = $outer instanceof Axismundi_Activity ? axismundi_act_get( $outer->get_uri() ) : null;
	$withdrawn_update_announce = $visible_update_announce instanceof Axismundi_Activity ? axismundi_act_get( $visible_update_announce->get_uri() ) : null;
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
			&& $withdrawn_update_announce instanceof Axismundi_Activity && ! $withdrawn_update_announce->is_effective()
			&& $group instanceof Axismundi_Actor && $group->get_uri() === $withdrawal->get_actor_uri()
			&& $outer instanceof Axismundi_Activity && $outer->get_uri() === $withdrawal->get_object_uri()
			&& function_exists( 'axismundi_act_public_audience_uri' ) && in_array( axismundi_act_public_audience_uri(), (array) ( $withdrawal->get_audience()['to'] ?? array() ), true )
			&& in_array( $followers, (array) ( $withdrawal->get_audience()['cc'] ?? array() ), true )
			&& ( $withdrawn_source = get_post( $topic_id ) ) instanceof WP_Post && 'pending' === $withdrawn_source->post_status
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
	$members_scope = axismundi_forum_set_distribution_scope( $group_id, $owner_user, 'members' );
	$members_withdrawn = is_array( $unchanged_reapproved_entry ) ? axismundi_forum_withdraw_announced_entry( (int) $unchanged_reapproved_entry['id'], $alice_user ) : new WP_Error( 'fixture' );
	$members_pending = axismundi_forum_get_topic_entry( $topic_id );
	$members_reapproved = is_array( $members_pending ) ? axismundi_forum_approve_pending_entry( (int) $members_pending['id'], $alice_user ) : new WP_Error( 'fixture' );
	$members_entry = axismundi_forum_get_topic_entry( $topic_id );
	$members_announce = is_array( $members_entry ) ? axismundi_act_get( (string) $members_entry['announced_activity_uri'] ) : null;
	$members_article = $topic instanceof WP_Post ? axismundi_forum_topic_to_article( $topic ) : null;
	$members_outbox = function_exists( 'axismundi_act_get_public_outbox' ) && $group instanceof Axismundi_Actor ? axismundi_act_get_public_outbox( $group->get_uri() ) : array();
	$members_outbox_ids = array_map( static fn( array $payload ) : string => (string) ( $payload['id'] ?? '' ), $members_outbox );
	ax_fmod_assert(
		$ax_fmod_results,
		'a members-scope re-approval addresses only followers and stays out of the public Group outbox',
		true === $members_scope && true === $members_withdrawn && true === $members_reapproved && $members_announce instanceof Axismundi_Activity
			&& in_array( $followers, (array) ( $members_announce->get_audience()['to'] ?? array() ), true )
			&& empty( (array) ( $members_announce->get_audience()['cc'] ?? array() ) )
			&& ! axismundi_act_has_public_audience( $members_announce ) && ! in_array( $members_announce->get_uri(), $members_outbox_ids, true )
			&& is_array( $members_article ) && ! in_array( axismundi_act_public_audience_uri(), (array) ( $members_article['cc'] ?? array() ), true )
	);
	wp_set_current_user( $bob_user );
	$author_pending = wp_update_post( array( 'ID' => $topic_id, 'post_status' => 'pending' ), true );
	wp_set_current_user( 0 );
	$author_withdrawn_entry = axismundi_forum_get_topic_entry( $topic_id );
	$author_undo = $members_announce instanceof Axismundi_Activity ? axismundi_act_get_by_object( $members_announce->get_uri(), 10 ) : array();
	$author_withdrawal = null;
	foreach ( $author_undo as $candidate ) {
		if ( $candidate instanceof Axismundi_Activity && 'Undo' === $candidate->get_type() && $candidate->is_effective() ) {
			$author_withdrawal = $candidate;
			break;
		}
	}
	ax_fmod_assert(
		$ax_fmod_results,
		'an author moving their published local Topic to pending withdraws the Group Announce and returns it to Topic submissions',
		! is_wp_error( $author_pending) && is_array( $author_withdrawn_entry)
			&& 'pending' === (string) $author_withdrawn_entry['admission_state']
			&& $author_withdrawal instanceof Axismundi_Activity
			&& ( $author_source = get_post( $topic_id ) ) instanceof WP_Post && 'pending' === $author_source->post_status
	);
	$topic_object_uri = $topic instanceof WP_Post ? axismundi_forum_topic_object_uri( $topic ) : '';
	$distributed_entry_id = is_array( $author_withdrawn_entry ) ? (int) $author_withdrawn_entry['id'] : 0;
	wp_set_current_user( $bob_user );
	$distributed_deleted = $topic_id > 0 ? wp_delete_post( $topic_id, true ) : false;
	wp_set_current_user( 0 );
	$distributed_delete = '' !== $topic_object_uri ? axismundi_act_get_object_lifecycle( $topic_object_uri ) : null;
	$delete_announces = $distributed_delete instanceof Axismundi_Activity ? axismundi_act_get_by_object( $distributed_delete->get_uri(), 10 ) : array();
	$delete_announce = null;
	foreach ( $delete_announces as $candidate ) {
		if ( $candidate instanceof Axismundi_Activity && 'Announce' === $candidate->get_type() && $group instanceof Axismundi_Actor && $group->get_uri() === $candidate->get_actor_uri() ) {
			$delete_announce = $candidate;
			break;
		}
	}
	ax_fmod_assert(
		$ax_fmod_results,
		'permanently deleting a once-distributed pending Topic records Person Delete to its Group and Group Announce(Delete) to prior followers',
		$distributed_deleted instanceof WP_Post && $distributed_delete instanceof Axismundi_Activity && 'Delete' === $distributed_delete->get_type()
			&& $group instanceof Axismundi_Actor && in_array( $group->get_uri(), (array) ( $distributed_delete->get_audience()['to'] ?? array() ), true )
			&& $delete_announce instanceof Axismundi_Activity && 'Delete' === (string) ( $delete_announce->get_payload()['object']['type'] ?? '' )
			&& in_array( $followers, (array) ( $delete_announce->get_audience()['to'] ?? array() ), true )
			&& $distributed_entry_id > 0 && count( axismundi_forum_entry_distributions( $distributed_entry_id ) ) >= 1
			&& null === get_post( $topic_id )
	);

	$unannounced_topic_id = (int) wp_insert_post(
		array(
			'post_type'   => AXISMUNDI_FORUM_TOPIC_POST_TYPE,
			'post_status' => 'pending',
			'post_author' => $bob_user,
			'post_title'  => 'Unannounced pending Topic',
		)
	);
	if ( $unannounced_topic_id > 0 ) { $ax_fmod_posts[] = $unannounced_topic_id; }
	$unannounced_admitted = $unannounced_topic_id > 0 ? axismundi_forum_admit_local_topic( $group_id, $unannounced_topic_id, $bob_user ) : new WP_Error( 'fixture' );
	$unannounced_uri = $unannounced_topic_id > 0 && ( $unannounced_topic = get_post( $unannounced_topic_id ) ) instanceof WP_Post ? axismundi_forum_topic_object_uri( $unannounced_topic ) : '';
	wp_set_current_user( $bob_user );
	$unannounced_deleted = $unannounced_topic_id > 0 ? wp_delete_post( $unannounced_topic_id, true ) : false;
	wp_set_current_user( 0 );
	$unannounced_delete = '' !== $unannounced_uri ? axismundi_act_get_object_lifecycle( $unannounced_uri ) : null;
	$unannounced_announces = $unannounced_delete instanceof Axismundi_Activity ? axismundi_act_get_by_object( $unannounced_delete->get_uri(), 10 ) : array();
	ax_fmod_assert(
		$ax_fmod_results,
		'permanently deleting a never-announced pending Topic records its direct Delete but does not fabricate a Group Announce',
		true === $unannounced_admitted && $unannounced_deleted instanceof WP_Post && $unannounced_delete instanceof Axismundi_Activity && 'Delete' === $unannounced_delete->get_type()
			&& empty( array_filter( $unannounced_announces, static fn( Axismundi_Activity $activity ) : bool => 'Announce' === $activity->get_type() ) )
	);

	$contributor_topic_id = (int) wp_insert_post(
		array(
			'post_type'   => AXISMUNDI_FORUM_TOPIC_POST_TYPE,
			'post_status' => 'pending',
			'post_author' => $contributor_user,
			'post_title'  => 'Contributor review Topic',
		)
	);
	if ( $contributor_topic_id > 0 ) { $ax_fmod_posts[] = $contributor_topic_id; }
	$contributor_admitted = $contributor_topic_id > 0
		? axismundi_forum_admit_local_topic( $group_id, $contributor_topic_id, $contributor_user )
		: new WP_Error( 'fixture' );
	$contributor_pending = axismundi_forum_get_topic_entry( $contributor_topic_id );
	ax_fmod_assert(
		$ax_fmod_results,
		'a contributor native pending Topic enters the same Group approval queue without becoming a public source post',
		true === $contributor_admitted && is_array( $contributor_pending)
			&& 'pending' === (string) $contributor_pending['admission_state']
			&& ( $contributor_source = get_post( $contributor_topic_id ) ) instanceof WP_Post
			&& 'pending' === $contributor_source->post_status
	);
	$contributor_approved = is_array( $contributor_pending )
		? axismundi_forum_approve_pending_entry( (int) $contributor_pending['id'], $alice_user )
		: new WP_Error( 'fixture' );
	$contributor_visible = axismundi_forum_get_topic_entry( $contributor_topic_id );
	ax_fmod_assert(
		$ax_fmod_results,
		'Group approval publishes the contributor source and its Forum entry together only after the Announce exists',
		true === $contributor_approved && is_array( $contributor_visible)
			&& 'visible' === (string) $contributor_visible['admission_state']
			&& '' !== (string) $contributor_visible['announced_activity_uri']
			&& ( $contributor_source = get_post( $contributor_topic_id ) ) instanceof WP_Post
			&& 'publish' === $contributor_source->post_status
	);
	wp_set_current_user( $site_editor_user );
	$contributor_withdrawn = $contributor_topic_id > 0
		? wp_update_post( array( 'ID' => $contributor_topic_id, 'post_status' => 'pending' ), true )
		: new WP_Error( 'fixture' );
	wp_set_current_user( 0 );
	$contributor_pending_again = axismundi_forum_get_topic_entry( $contributor_topic_id );
	ax_fmod_assert(
		$ax_fmod_results,
		'a site editor who is not a Group moderator moving another author Topic to pending also undoes the Group Announce and returns both states to review',
		! is_wp_error( $contributor_withdrawn) && is_array( $contributor_pending_again)
			&& 'pending' === (string) $contributor_pending_again['admission_state']
			&& ( $contributor_source = get_post( $contributor_topic_id ) ) instanceof WP_Post
			&& 'pending' === $contributor_source->post_status
			&& ! axismundi_forum_user_can_moderate( $group_id, $site_editor_user )
	);
	$editor_topic_id = (int) wp_insert_post(
		array(
			'post_type'   => AXISMUNDI_FORUM_TOPIC_POST_TYPE,
			'post_status' => 'publish',
			'post_author' => $editor_user,
			'post_title'  => 'Editor review Topic',
		)
	);
	if ( $editor_topic_id > 0 ) { $ax_fmod_posts[] = $editor_topic_id; }
	$previous_post = $_POST;
	wp_set_current_user( $editor_user );
	$_POST = array(
		'axismundi_forum_topic_nonce' => wp_create_nonce( 'axismundi_forum_topic_' . $editor_topic_id ),
		'community_target'            => 'local:' . $group_id,
	);
	axismundi_forum_save_topic_context( $editor_topic_id );
	$_POST = $previous_post;
	wp_set_current_user( 0 );
	$editor_entry = axismundi_forum_get_topic_entry( $editor_topic_id );
	ax_fmod_assert(
		$ax_fmod_results,
		'the editor save path enters community review once when it changes a published Topic to pending',
		is_array( $editor_entry) && 'pending' === (string) $editor_entry['admission_state']
			&& ( $editor_source = get_post( $editor_topic_id ) ) instanceof WP_Post
			&& 'pending' === $editor_source->post_status
			&& 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . axismundi_forum_entries_table() . ' WHERE source_post_id = %d', $editor_topic_id ) )
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
			&& function_exists( 'axismundi_act_public_audience_uri' ) && in_array( axismundi_act_public_audience_uri(), (array) ( $remove->get_audience()['to'] ?? array() ), true )
			&& $group instanceof Axismundi_Actor && in_array( $group->get_uri(), (array) ( $remove->get_audience()['cc'] ?? array() ), true )
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
