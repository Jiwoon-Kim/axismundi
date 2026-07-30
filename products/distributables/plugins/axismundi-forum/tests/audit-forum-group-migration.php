<?php
/**
 * Forum group-rekey migration regression (dev-only; dist-excluded).
 *
 * The community is the Group Actor, not the `ax_forum` post that stood in for it. This locks
 * the first half of that move: policies leave post meta for a Group-keyed settings table, and
 * the entry and membership projections gain the Group identity, while the binding table and
 * the posts are still present so the result can be checked against them.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/managed-groups.php';
require_once __DIR__ . '/../includes/repository.php';
require_once __DIR__ . '/../includes/cpt.php';
require_once __DIR__ . '/../includes/topics.php';
require_once __DIR__ . '/../includes/memberships.php';

axismundi_forum_install();
axismundi_forum_register_post_type();

global $wpdb;
$ax_gm_results = array();
$ax_gm_users   = array();
$ax_gm_ids     = array();
$ax_gm_posts   = array();

/** @param bool[] $results Results. */
function ax_gm_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$owner         = (int) wp_insert_user( array( 'user_login' => 'axgm_' . strtolower( wp_generate_password( 9, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$ax_gm_users[] = $owner;
	$group         = axismundi_actors_create_managed_group( array( 'owner_user_id' => $owner, 'preferred_username' => 'axgm' . strtolower( wp_generate_password( 7, false, false ) ), 'status' => 'public' ) );
	$ax_gm_ids[]   = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	$group_id      = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;

	$forum         = (int) wp_insert_post( array( 'post_type' => 'ax_forum', 'post_status' => 'publish', 'post_author' => $owner, 'post_title' => 'Group Migration Audit' ) );
	$ax_gm_posts[] = $forum;
	$bound         = $group_id > 0 ? axismundi_forum_bind_group( $forum, $group_id, $owner ) : new WP_Error( 'fixture' );

	// A community that chose non-default policies must keep them across the move.
	update_post_meta( $forum, AXISMUNDI_FORUM_POSTING_POLICY_META, 'managers' );
	update_post_meta( $forum, AXISMUNDI_FORUM_MEMBERSHIP_POLICY_META, 'approval' );
	$wpdb->delete( axismundi_forum_settings_table(), array( 'group_identity_id' => $group_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- re-run the migration for this fixture.

	$moved    = axismundi_forum_migrate_bindings_to_settings();
	$settings = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . axismundi_forum_settings_table() . ' WHERE group_identity_id = %d', $group_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit read.
	ax_gm_assert(
		$ax_gm_results,
		'community policies move from ax_forum post meta onto the Group identity, values intact',
		true === $bound
			&& $moved >= 1
			&& is_array( $settings )
			&& 'managers' === (string) $settings['posting_policy']
			&& 'approval' === (string) $settings['membership_policy']
	);

	$again          = axismundi_forum_migrate_bindings_to_settings();
	$after_rerun    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . axismundi_forum_settings_table() . ' WHERE group_identity_id = %d', $group_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit read.
	ax_gm_assert(
		$ax_gm_results,
		'a second migration pass leaves an already-migrated community untouched',
		is_array( $after_rerun )
			&& 'managers' === (string) $after_rerun['posting_policy']
			&& 'approval' === (string) $after_rerun['membership_policy']
			&& (string) $after_rerun['created_at'] === (string) $settings['created_at']
			&& 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . axismundi_forum_settings_table() . ' WHERE group_identity_id = %d', $group_id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit read.
	);
	unset( $again );

	// A membership written under the old key must gain the Group identity.
	$member_uri = 'https://example.com/users/axgm' . strtolower( wp_generate_password( 7, false, false ) );
	$member     = axismundi_actors_upsert_remote(
		array(
			'uri'                => $member_uri,
			'actor_type'         => 'Person',
			'preferred_username' => 'axgmmember',
			'display_name'       => 'axgmmember',
			'profile_url'        => $member_uri,
			'endpoints'          => array( 'inbox' => $member_uri . '/inbox', 'outbox' => $member_uri . '/outbox' ),
			'payload'            => array( 'id' => $member_uri, 'type' => 'Person', 'preferredUsername' => 'axgmmember', 'inbox' => $member_uri . '/inbox', 'outbox' => $member_uri . '/outbox' ),
		)
	);
	if ( $member instanceof Axismundi_Actor ) {
		$ax_gm_ids[] = $member->get_identity_id();
		$wpdb->insert(
			axismundi_forum_memberships_table(),
			array(
				'forum_post_id'                    => $forum,
				'group_identity_id'                => 0,
				'actor_identity_id'                => $member->get_identity_id(),
				'membership_evidence_activity_uri' => 'https://example.com/activities/legacy',
				'membership_state'                 => 'accepted',
				'created_at'                       => current_time( 'mysql', true ),
				'updated_at'                       => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture written under the pre-migration key.
	}
	$rekeyed = axismundi_forum_migrate_projections_to_group();
	$row     = $member instanceof Axismundi_Actor
		? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . axismundi_forum_memberships_table() . ' WHERE forum_post_id = %d AND actor_identity_id = %d', $forum, $member->get_identity_id() ), ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit read.
		: null;
	ax_gm_assert(
		$ax_gm_results,
		'a membership keyed only to the ax_forum post is rekeyed onto its Group identity',
		is_array( $rekeyed )
			&& $rekeyed['memberships'] >= 1
			&& is_array( $row )
			&& $group_id === (int) $row['group_identity_id']
			// The old key is kept so the rekey stays checkable until it is verified.
			&& $forum === (int) $row['forum_post_id']
			&& 'accepted' === (string) $row['membership_state']
	);
} finally {
	foreach ( array_unique( $ax_gm_posts ) as $post_id ) {
		$wpdb->delete( axismundi_forum_memberships_table(), array( 'forum_post_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_forum_entries_table(), array( 'forum_post_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_forum_bindings_table(), array( 'forum_post_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		if ( get_post( (int) $post_id ) ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_filter( array_unique( $ax_gm_ids ) ) as $identity_id ) {
		$wpdb->delete( axismundi_forum_settings_table(), array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	if ( ! empty( $ax_gm_users ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_gm_users ) as $user_id ) {
			if ( get_userdata( (int) $user_id ) ) {
				wp_delete_user( (int) $user_id );
			}
		}
	}
}

$ax_gm_failed = count( array_filter( $ax_gm_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_gm_results ) - $ax_gm_failed, count( $ax_gm_results ) );
exit( $ax_gm_failed > 0 ? 1 : 0 );
