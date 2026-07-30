<?php
/**
 * Managed-Group community core regression (dev-only; dist-excluded).
 *
 * The Group identity is the only community key. This locks the replacement for the retired
 * ax_forum CPT: manager authority enables a community, policies live on that Group, Topic
 * admission writes the Group key, and neither an unrelated user nor an unrelated Actor gains
 * authority through Forum.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/managed-groups.php';
require_once __DIR__ . '/../includes/repository.php';
require_once __DIR__ . '/../includes/topics.php';

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
	$group    = axismundi_actors_create_managed_group(
		array(
			'owner_user_id'     => $owner,
			'preferred_username' => 'axfc' . strtolower( wp_generate_password( 8, false, false ) ),
		)
	);
	$group_id = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	if ( $group_id > 0 ) {
		$ax_fc_identities[] = $group_id;
	}

	ax_fc_assert(
		'a managed Group has no community until a manager enables it',
		$group instanceof Axismundi_Actor && ! axismundi_forum_is_community( $group_id )
	);
	ax_fc_assert(
		'a non-manager cannot enable a Group community',
		is_wp_error( axismundi_forum_enable_community( $group_id, $stranger ) ) && ! axismundi_forum_is_community( $group_id )
	);
	ax_fc_assert(
		'a Group manager enables the community without a second post or binding record',
		true === axismundi_forum_enable_community( $group_id, $owner )
			&& is_array( axismundi_forum_get_community( $group_id ) )
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
	ax_fc_assert(
		'a community with Topic entries cannot be disabled',
		is_wp_error( axismundi_forum_disable_community( $group_id, $owner ) )
	);
} finally {
	foreach ( array_unique( $ax_fc_topics ) as $topic_id ) {
		$wpdb->delete( axismundi_forum_entries_table(), array( 'source_post_id' => (int) $topic_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture cleanup.
		if ( get_post( (int) $topic_id ) ) {
			wp_delete_post( (int) $topic_id, true );
		}
	}
	foreach ( array_unique( $ax_fc_identities ) as $identity_id ) {
		$wpdb->delete( axismundi_forum_settings_table(), array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture cleanup.
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
