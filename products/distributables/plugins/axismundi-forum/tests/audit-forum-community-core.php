<?php
/**
 * Managed-Group community core regression (dev-only; dist-excluded).
 *
 * The Group identity is the only community key. This locks the replacement for the retired
 * ax_forum CPT: publishing a managed Group creates a community, policies live on that Group, Topic
 * admission writes the Group key, and neither an unrelated user nor an unrelated Actor gains
 * authority through Forum.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/managed-groups.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/relations.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/local-social.php';
require_once __DIR__ . '/../includes/repository.php';
require_once __DIR__ . '/../includes/topics.php';
require_once __DIR__ . '/../includes/memberships.php';
require_once __DIR__ . '/../includes/admin.php';

axismundi_forum_install();
axismundi_forum_register_topic_post_type();

global $wpdb;
$GLOBALS['ax_fc_results'] = array();
$ax_fc_users      = array();
$ax_fc_identities = array();
$ax_fc_topics     = array();

function ax_fc_assert( string $label, bool $condition ) : void {
	$GLOBALS['ax_fc_results'][] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI audit output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

function ax_fc_user() : int {
	global $ax_fc_users;
	$user_id = (int) wp_insert_user(
		array(
			'user_login' => 'ax_fc_' . strtolower( wp_generate_password( 10, false, false ) ),
			'user_pass'  => wp_generate_password(),
			'role'       => 'editor',
		)
	);
	if ( $user_id > 0 ) {
		$ax_fc_users[] = $user_id;
	}
	return $user_id;
}

try {
	$owner    = ax_fc_user();
	$stranger = ax_fc_user();
	$owner_actor = axismundi_actors_ensure_for_user( $owner );
	if ( $owner_actor instanceof Axismundi_Actor ) {
		$ax_fc_identities[] = $owner_actor->get_identity_id();
		axismundi_actors_register_handle( $owner_actor->get_identity_id(), 'axfcowner' . strtolower( wp_generate_password( 7, false, false ) ) );
		axismundi_actors_set_status( $owner_actor->get_identity_id(), 'public' );
		$owner_actor = axismundi_actors_get_by_identity( $owner_actor->get_identity_id() );
	}
	$group    = axismundi_actors_create_managed_group(
		array(
			'owner_user_id'     => $owner,
			'preferred_username' => 'axfc' . strtolower( wp_generate_password( 8, false, false ) ),
			'status'             => 'internal',
		)
	);
	$group_id = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	if ( $group_id > 0 ) {
		$ax_fc_identities[] = $group_id;
		if ( $group instanceof Axismundi_Actor && ! $group->is_handle_locked() ) {
			axismundi_actors_register_handle( $group_id, $group->get_preferred_username() );
			$group = axismundi_actors_get_by_identity( $group_id );
		}
	}

	ax_fc_assert(
		'an internal managed Group is not a public community',
		$group instanceof Axismundi_Actor && ! axismundi_forum_is_community( $group_id )
	);
	axismundi_actors_set_status( $group_id, 'public' );
	$group = axismundi_actors_get_by_identity( $group_id );
	ax_fc_assert(
		'publishing a managed Group immediately creates its community policy row',
		$group instanceof Axismundi_Actor && is_array( axismundi_forum_get_community( $group_id ) )
	);
	$auto_follow = $owner_actor instanceof Axismundi_Actor && $group instanceof Axismundi_Actor
		? axismundi_act_get_relation( 'follow', $owner_actor->get_uri(), $group->get_uri() )
		: null;
	$auto_membership = $owner_actor instanceof Axismundi_Actor ? axismundi_forum_get_membership( $group_id, $owner_actor->get_identity_id() ) : null;
	ax_fc_assert(
		'publishing a community joins its owner and creates the matching membership projection',
		is_array( $auto_follow ) && 'accepted' === (string) $auto_follow['state'] && is_array( $auto_membership ) && 'accepted' === (string) $auto_membership['membership_state']
	);
	wp_set_current_user( $owner );
	ob_start();
	if ( $group instanceof Axismundi_Actor ) {
		axismundi_forum_render_group_admin_section( $group );
	}
	$community_admin = (string) ob_get_clean();
	ax_fc_assert(
		'the managed Group community screen exposes its member list, membership controls, and an empty Topic review queue',
		$owner_actor instanceof Axismundi_Actor && str_contains( $community_admin, 'Members' ) && str_contains( $community_admin, 'Membership approval' ) && str_contains( $community_admin, 'Topic submissions' ) && str_contains( $community_admin, 'No Topic submissions are awaiting review.' ) && str_contains( $community_admin, function_exists( 'axismundi_actors_federated_mention_name' ) ? axismundi_actors_federated_mention_name( $owner_actor ) : '@' . $owner_actor->get_preferred_username() )
	);

	$policy = axismundi_forum_set_posting_policy( $group_id, $owner, 'managers' );
	ax_fc_assert(
		'posting policy is stored against the Group identity',
		true === $policy && 'managers' === axismundi_forum_get_posting_policy( $group_id )
	);
	ax_fc_assert(
		'a non-manager cannot alter Group-scoped community policy',
		is_wp_error( axismundi_forum_set_posting_policy( $group_id, $stranger, 'open' ) )
	);

	$topic = (int) wp_insert_post(
		array(
			'post_type'   => AXISMUNDI_FORUM_TOPIC_POST_TYPE,
			'post_status' => 'publish',
			'post_author' => $owner,
			'post_title'  => 'Community core audit',
		)
	);
	if ( $topic > 0 ) {
		$ax_fc_topics[] = $topic;
	}
	$admitted = axismundi_forum_admit_local_topic( $group_id, $topic, $owner );
	$entry    = axismundi_forum_get_topic_entry( $topic );
	ax_fc_assert(
		'a Topic admission stores the Group identity directly',
		true === $admitted && is_array( $entry ) && $group_id === (int) $entry['group_identity_id']
	);
} finally {
	foreach ( array_unique( $ax_fc_topics ) as $topic_id ) {
		$wpdb->delete( axismundi_forum_entries_table(), array( 'source_post_id' => (int) $topic_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture cleanup.
		if ( get_post( (int) $topic_id ) ) {
			wp_delete_post( (int) $topic_id, true );
		}
	}
	foreach ( array_unique( $ax_fc_identities ) as $identity_id ) {
		$actor = axismundi_actors_get_by_identity( (int) $identity_id );
		if ( $actor instanceof Axismundi_Actor ) {
			$wpdb->delete( axismundi_act_relations_table(), array( 'subject_actor_uri_hash' => hash( 'sha256', $actor->get_uri() ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture cleanup.
			$wpdb->delete( axismundi_act_relations_table(), array( 'object_actor_uri_hash' => hash( 'sha256', $actor->get_uri() ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture cleanup.
			$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri_hash' => hash( 'sha256', $actor->get_uri() ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture cleanup.
		}
		$wpdb->delete( axismundi_forum_settings_table(), array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture cleanup.
		$wpdb->delete( axismundi_forum_memberships_table(), array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture cleanup.
		$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture cleanup.
	}
	if ( ! empty( $ax_fc_users ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_fc_users ) as $user_id ) {
			if ( get_userdata( (int) $user_id ) ) {
				wp_delete_user( (int) $user_id );
			}
		}
	}
}

$ax_fc_results = (array) $GLOBALS['ax_fc_results'];
$ax_fc_failed  = count( array_filter( $ax_fc_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI audit output.
printf( "\n%d/%d passed\n", count( $ax_fc_results ) - $ax_fc_failed, count( $ax_fc_results ) );
exit( $ax_fc_failed > 0 ? 1 : 0 );
