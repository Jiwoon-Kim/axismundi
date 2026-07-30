<?php
/**
 * Forum F3 outbound remote-Group Topic regression (dev-only; dist-excluded).
 *
 * Locks the portable path: local public Person Follow -> remote Group -> Page
 * Create addressed directly to the Group inbox; only the Group redistributes it.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/managed-groups.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/relations.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/local-social.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/object-lifecycle.php';
require_once __DIR__ . '/../includes/repository.php';
require_once __DIR__ . '/../includes/topics.php';
require_once __DIR__ . '/../includes/memberships.php';
require_once __DIR__ . '/../includes/outbound-topics.php';
require_once __DIR__ . '/../includes/admin.php';

axismundi_forum_install();
axismundi_forum_register_topic_post_type();

global $wpdb;
$ax_fot_results = array();
$ax_fot_user_ids = array();
$ax_fot_identity_ids = array();
$ax_fot_post_ids = array();
$ax_fot_activity_uris = array();

/** @param bool[] $results Results. */
function ax_fot_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

function ax_fot_user( array &$user_ids ) : int {
	$user_id = (int) wp_insert_user( array( 'user_login' => 'ax_fot_' . strtolower( wp_generate_password( 10, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
	if ( $user_id > 0 ) {
		$user_ids[] = $user_id;
	}
	return $user_id;
}

function ax_fot_remote_group( array &$identity_ids, string $suffix ) {
	$uri = 'https://example.com/groups/' . $suffix;
	$group = axismundi_actors_upsert_remote(
		array(
			'uri' => $uri, 'actor_type' => 'Group', 'preferred_username' => $suffix, 'display_name' => $suffix, 'profile_url' => $uri,
			'endpoints' => array( 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
			'payload' => array( 'id' => $uri, 'type' => 'Group', 'preferredUsername' => $suffix, 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
		)
	);
	if ( $group instanceof Axismundi_Actor ) {
		$identity_ids[] = $group->get_identity_id();
	}
	return $group;
}

try {
	$author_user = ax_fot_user( $ax_fot_user_ids );
	$author = axismundi_actors_ensure_for_user( $author_user );
	if ( $author instanceof Axismundi_Actor ) {
		$ax_fot_identity_ids[] = $author->get_identity_id();
		axismundi_actors_register_handle( $author->get_identity_id(), 'axfot' . strtolower( wp_generate_password( 8, false, false ) ) );
		axismundi_actors_set_status( $author->get_identity_id(), 'public' );
		$author = axismundi_actors_get_by_identity( $author->get_identity_id() );
	}
	$group = ax_fot_remote_group( $ax_fot_identity_ids, 'community_' . strtolower( wp_generate_password( 7, false, false ) ) );
	$local_group = axismundi_actors_create_managed_group(
		array(
			'owner_user_id'      => $author_user,
			'preferred_username' => 'local_' . strtolower( wp_generate_password( 7, false, false ) ),
			'status'             => 'public',
		)
	);
	if ( $local_group instanceof Axismundi_Actor ) {
		$ax_fot_identity_ids[] = $local_group->get_identity_id();
	}
	$joined_owner = ax_fot_user( $ax_fot_user_ids );
	$joined_group = axismundi_actors_create_managed_group(
		array(
			'owner_user_id'      => $joined_owner,
			'preferred_username' => 'joined_' . strtolower( wp_generate_password( 7, false, false ) ),
			'status'             => 'public',
		)
	);
	if ( $joined_group instanceof Axismundi_Actor ) {
		$ax_fot_identity_ids[] = $joined_group->get_identity_id();
		axismundi_forum_set_posting_policy( $joined_group->get_identity_id(), $joined_owner, 'members' );
		if ( $author instanceof Axismundi_Actor ) {
			axismundi_forum_write_membership( $joined_group->get_identity_id(), $author->get_identity_id(), 'accepted', 'https://example.com/activities/follow-' . wp_generate_uuid4() );
		}
	}
	$follow = $author instanceof Axismundi_Actor && $group instanceof Axismundi_Actor ? axismundi_act_follow_remote_actor( $author, $group ) : new WP_Error( 'fixture' );
	if ( is_array( $follow ) && ! empty( $follow['initiating_activity_uri'] ) ) {
		$ax_fot_activity_uris[] = (string) $follow['initiating_activity_uri'];
	}
	$topic = $group instanceof Axismundi_Actor ? axismundi_forum_create_remote_topic( $author_user, $group->get_identity_id(), array( 'title' => 'Outbound community topic', 'content' => '<p>Fediverse community content.</p>' ) ) : new WP_Error( 'fixture' );
	if ( $topic instanceof WP_Post ) {
		$ax_fot_post_ids[] = $topic->ID;
	}
	$object_uri = $topic instanceof WP_Post ? axismundi_forum_topic_object_uri( $topic ) : '';
	$lifecycle = '' !== $object_uri ? axismundi_act_get_object_lifecycle( $object_uri ) : null;
	if ( $lifecycle instanceof Axismundi_Activity ) {
		$ax_fot_activity_uris[] = $lifecycle->get_uri();
	}
	$object = $lifecycle instanceof Axismundi_Activity ? (array) ( $lifecycle->get_payload()['object'] ?? array() ) : array();
	$inboxes = function_exists( 'axismundi_activitypub_bridge_activity_inboxes' ) && $lifecycle instanceof Axismundi_Activity ? axismundi_activitypub_bridge_activity_inboxes( $lifecycle ) : array();
	ax_fot_assert(
		$ax_fot_results,
		'a public local Person follows a cached remote Group before an outbound Topic can be created',
		$author instanceof Axismundi_Actor && $group instanceof Axismundi_Actor && is_array( $follow ) && in_array( (string) $follow['state'], array( 'pending', 'accepted', 'legacy_pending' ), true )
	);
	if ( $author instanceof Axismundi_Actor && $group instanceof Axismundi_Actor && is_array( $follow ) && 'accepted' !== (string) $follow['state'] ) {
		$accept_uri = 'https://example.com/activities/accept-' . wp_generate_uuid4();
		$accepted   = axismundi_act_record_activity(
			array( 'id' => $accept_uri, 'type' => 'Accept', 'actor' => $group->get_uri(), 'object' => (string) $follow['initiating_activity_uri'] ),
			'inbound'
		);
		if ( $accepted instanceof Axismundi_Activity ) {
			$ax_fot_activity_uris[] = $accept_uri;
		}
	}
	$followed_groups = axismundi_forum_followed_remote_groups_for_user( $author_user );
	ax_fot_assert(
		$ax_fot_results,
		'the Topic picker source lists an accepted remote Group only through the author Follow projection',
		$group instanceof Axismundi_Actor && isset( $followed_groups[ $group->get_identity_id() ] ) && $group->get_uri() === $followed_groups[ $group->get_identity_id() ]->get_uri()
	);
	$picker_topic_id = (int) wp_insert_post(
		array(
			'post_type'   => AXISMUNDI_FORUM_TOPIC_POST_TYPE,
			'post_status' => 'draft',
			'post_author' => $author_user,
			'post_title'  => 'Community picker audit',
		)
	);
	if ( $picker_topic_id > 0 ) {
		$ax_fot_post_ids[] = $picker_topic_id;
	}
	$manager_topic_id = (int) wp_insert_post(
		array(
			'post_type'   => AXISMUNDI_FORUM_TOPIC_POST_TYPE,
			'post_status' => 'draft',
			'post_author' => $joined_owner,
			'post_title'  => 'Manager membership boundary audit',
		)
	);
	if ( $manager_topic_id > 0 ) {
		$ax_fot_post_ids[] = $manager_topic_id;
	}
	$joined_blocked = $joined_group instanceof Axismundi_Actor
		&& true === axismundi_forum_set_posting_policy( $joined_group->get_identity_id(), $joined_owner, 'managers' )
		&& ! axismundi_forum_can_admit_local_topic( $joined_group->get_identity_id(), $picker_topic_id, $author_user );
	$joined_can_post = $joined_group instanceof Axismundi_Actor
		&& true === axismundi_forum_set_posting_policy( $joined_group->get_identity_id(), $joined_owner, 'members' )
		&& axismundi_forum_can_admit_local_topic( $joined_group->get_identity_id(), $picker_topic_id, $author_user );
	$manager_not_member = $joined_group instanceof Axismundi_Actor
		&& ! axismundi_forum_can_admit_local_topic( $joined_group->get_identity_id(), $manager_topic_id, $joined_owner );
	$joined_memberships = axismundi_forum_joined_local_communities_for_user( $author_user );
	ax_fot_assert(
		$ax_fot_results,
		'an accepted local member may submit only under members policy to a community they do not manage',
		$joined_group instanceof Axismundi_Actor && $joined_blocked && $joined_can_post && $manager_not_member && isset( $joined_memberships[ $joined_group->get_identity_id() ] )
	);
	wp_set_current_user( $author_user );
	ob_start();
	$picker_topic = get_post( $picker_topic_id );
	if ( $picker_topic instanceof WP_Post ) {
		axismundi_forum_render_topic_meta_box( $picker_topic );
	}
	$picker_markup = (string) ob_get_clean();
	$picker_text   = html_entity_decode( $picker_markup, ENT_QUOTES, get_bloginfo( 'charset' ) );
	ax_fot_assert(
		$ax_fot_results,
		'the Topic editor presents manageable, joined local, and accepted followed remote Groups as distinct community targets',
		$local_group instanceof Axismundi_Actor && $joined_group instanceof Axismundi_Actor && $group instanceof Axismundi_Actor
			&& str_contains( $picker_markup, 'value="local:' . $local_group->get_identity_id() . '"' )
			&& str_contains( $picker_markup, 'value="local:' . $joined_group->get_identity_id() . '"' )
			&& str_contains( $picker_markup, 'value="remote:' . $group->get_identity_id() . '"' )
			&& str_contains( $picker_markup, 'My communities' )
			&& str_contains( $picker_text, 'Communities I\'ve joined' )
			&& str_contains( $picker_markup, 'Followed remote communities' )
	);
	ax_fot_assert(
		$ax_fot_results,
		'the outbound Topic is a local ax_topic source bound to the remote Group rather than a local Forum entry',
		$topic instanceof WP_Post && AXISMUNDI_FORUM_TOPIC_POST_TYPE === $topic->post_type && $group instanceof Axismundi_Actor
			&& $group->get_identity_id() === (int) get_post_meta( $topic->ID, AXISMUNDI_FORUM_REMOTE_GROUP_META, true )
			&& null === axismundi_forum_get_topic_entry( $topic->ID )
	);
	ax_fot_assert(
		$ax_fot_results,
		'the committed outbound Create embeds an Article attributed to the Person and addresses only the remote Group',
		$lifecycle instanceof Axismundi_Activity && 'Create' === $lifecycle->get_type() && 'outbound' === $lifecycle->get_direction()
			&& $author instanceof Axismundi_Actor && $group instanceof Axismundi_Actor && $author->get_uri() === (string) ( $object['attributedTo'] ?? '' )
			&& 'Article' === (string) ( $object['type'] ?? '' )
			// The remote Group is the delivery target, not the conversation identity.
			&& $group->get_uri() !== (string) ( $object['context'] ?? '' ) && '' !== (string) ( $object['context'] ?? '' )
			&& $group->get_uri() === (string) ( $object['audience'] ?? '' ) && array( $group->get_uri() ) === (array) ( $object['to'] ?? array() )
			&& array() === (array) ( $object['cc'] ?? array() ) && ! axismundi_act_has_public_audience( $lifecycle )
	);
	ax_fot_assert(
		$ax_fot_results,
		'the Bridge resolves the remote Group inbox from the direct to address for actual delivery',
		$group instanceof Axismundi_Actor && in_array( $group->get_uri() . '/inbox', $inboxes, true )
	);

	$unfollowed_group = ax_fot_remote_group( $ax_fot_identity_ids, 'unfollowed_' . strtolower( wp_generate_password( 7, false, false ) ) );
	$blocked = $unfollowed_group instanceof Axismundi_Actor ? axismundi_forum_create_remote_topic( $author_user, $unfollowed_group->get_identity_id(), array( 'title' => 'Must not publish' ) ) : new WP_Error( 'fixture' );
	ax_fot_assert(
		$ax_fot_results,
		'a cached remote Group without the author Follow relation cannot receive an outbound Topic',
		is_wp_error( $blocked ) && 'ax_forum_remote_topic_forbidden' === $blocked->get_error_code()
	);
} finally {
	$entries = axismundi_forum_entries_table();
	$activities = axismundi_act_activities_table();
	$relations = axismundi_act_relations_table();
	$identities = axismundi_actors_identities_table();
	$actors = axismundi_actors_actors_table();
	$addresses = axismundi_actors_addresses_table();
	$endpoints = axismundi_actors_endpoints_table();
	foreach ( array_unique( $ax_fot_post_ids ) as $post_id ) {
		$wpdb->delete( $entries, array( 'source_post_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		if ( get_post( (int) $post_id ) ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_unique( $ax_fot_activity_uris ) as $activity_uri ) {
		$wpdb->delete( $activities, array( 'activity_uri_hash' => hash( 'sha256', $activity_uri ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( array_unique( $ax_fot_identity_ids ) as $identity_id ) {
		$actor = axismundi_actors_get_by_identity( (int) $identity_id );
		if ( $actor instanceof Axismundi_Actor ) {
			$wpdb->delete( $relations, array( 'subject_actor_uri_hash' => hash( 'sha256', $actor->get_uri() ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
			$wpdb->delete( $relations, array( 'object_actor_uri_hash' => hash( 'sha256', $actor->get_uri() ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		$wpdb->delete( $endpoints, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $addresses, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_forum_settings_table(), array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $actors, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $identities, array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	if ( ! empty( $ax_fot_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_fot_user_ids ) as $user_id ) {
			if ( get_userdata( (int) $user_id ) ) {
				wp_delete_user( (int) $user_id );
			}
		}
	}
}

$ax_fot_failed = count( array_filter( $ax_fot_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_fot_results ) - $ax_fot_failed, count( $ax_fot_results ) );
exit( $ax_fot_failed > 0 ? 1 : 0 );
