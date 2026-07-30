<?php
/**
 * Forum F1 local Topic and Page-projection regression (dev-only; dist-excluded).
 *
 * Locks the boundary that a local `ax_topic` becomes a public AS2 Page only after
 * Forum admission. The entry is contextual: deleting a Topic removes its entry,
 * and deleting a Forum removes its entries without deleting Topic source posts or
 * the managed Group Actor.
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
$ax_ft_results      = array();
$ax_ft_identity_ids = array();
$ax_ft_user_ids     = array();
$ax_ft_post_ids     = array();

/** @param bool[] $results Results. */
function ax_ft_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** True when $result is a WP_Error carrying $code. */
function ax_ft_err( $result, string $code ) : bool {
	return is_wp_error( $result ) && $code === $result->get_error_code();
}

/** Create and remember a throwaway editor. */
function ax_ft_user( array &$user_ids ) : int {
	$user_id = (int) wp_insert_user(
		array(
			'user_login' => 'ax_ft_' . strtolower( wp_generate_password( 10, false, false ) ),
			'user_pass'  => wp_generate_password(),
			'role'       => 'editor',
		)
	);
	if ( $user_id > 0 ) {
		$user_ids[] = $user_id;
	}
	return $user_id;
}

/** Create and remember a published local Topic. */
function ax_ft_topic( array &$post_ids, int $author, string $title ) : int {
	$post_id = (int) wp_insert_post(
		array(
			'post_type'    => AXISMUNDI_FORUM_TOPIC_POST_TYPE,
			'post_status'  => 'publish',
			'post_author'  => $author,
			'post_title'   => $title,
			'post_content' => '<!-- wp:paragraph --><p>Forum Topic body.</p><!-- /wp:paragraph -->',
		)
	);
	if ( $post_id > 0 ) {
		$post_ids[] = $post_id;
	}
	return $post_id;
}

try {
	$owner    = ax_ft_user( $ax_ft_user_ids );
	$stranger = ax_ft_user( $ax_ft_user_ids );
	$group = axismundi_actors_create_managed_group(
		array(
			'owner_user_id'      => $owner,
			'preferred_username' => 'axft' . strtolower( wp_generate_password( 6, false, false ) ),
			'status'             => 'public',
		)
	);
	$group_id = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	if ( $group_id > 0 ) {
		$ax_ft_identity_ids[] = $group_id;
	}
	$author_actor = axismundi_actors_ensure_for_user( $owner );
	$author_id    = $author_actor instanceof Axismundi_Actor ? $author_actor->get_identity_id() : 0;
	if ( $author_id > 0 ) {
		$ax_ft_identity_ids[] = $author_id;
	}
	$author_public = $author_id > 0
		&& true === axismundi_actors_register_handle( $author_id, 'axft' . strtolower( wp_generate_password( 8, false, false ) ) )
		&& axismundi_actors_set_status( $author_id, 'public' );

	$community      = $group_id;
	$topic          = ax_ft_topic( $ax_ft_post_ids, $owner, 'Audit Topic One' );
	$topic2         = ax_ft_topic( $ax_ft_post_ids, $owner, 'Audit Topic Two' );
	$outsider_topic = ax_ft_topic( $ax_ft_post_ids, $stranger, 'Audit Restricted Topic' );

	ax_ft_assert(
		$ax_ft_results,
		'Topic remains a REST-visible CPT and no ax_forum post type exists to stand in for a community',
		post_type_exists( AXISMUNDI_FORUM_TOPIC_POST_TYPE )
			&& (bool) get_post_type_object( AXISMUNDI_FORUM_TOPIC_POST_TYPE )->show_in_rest
			&& ! post_type_exists( 'ax_forum' )
	);

	$community_ready = $group_id > 0 && axismundi_forum_is_community( $community );
	$manager_policy = $community_ready ? axismundi_forum_set_posting_policy( $community, $owner, 'managers' ) : new WP_Error( 'fixture' );
	ax_ft_assert(
		$ax_ft_results,
		'posting policy is manager-owned: a manager can restrict admission and an outsider cannot submit',
		true === $manager_policy
			&& 'managers' === axismundi_forum_get_posting_policy( $community )
			&& ax_ft_err( axismundi_forum_set_posting_policy( $community, $stranger, 'open' ), 'ax_forum_forbidden' )
			&& ax_ft_err( axismundi_forum_admit_local_topic( $community, $outsider_topic, $stranger ), 'ax_forum_topic_forbidden' )
	);
	$open_policy = axismundi_forum_set_posting_policy( $community, $owner, 'open' );
	$admit       = true === $open_policy ? axismundi_forum_admit_local_topic( $community, $topic, $owner ) : $open_policy;
	$entry = axismundi_forum_get_topic_entry( $topic );
	ax_ft_assert(
		$ax_ft_results,
		'admission records one contextual Topic entry with its public submitting Person',
		true === $admit
			&& is_array( $entry )
			// One key now, and it is the community itself.
			&& ! array_key_exists( 'forum_post_id', $entry )
			&& $group_id === (int) $entry['group_identity_id']
			&& 'topic' === $entry['entry_type']
			&& axismundi_forum_topic_object_uri( get_post( $topic ) ) === $entry['object_uri']
			&& $author_id === (int) $entry['submission_actor_identity_id']
	);

	ax_ft_assert(
		$ax_ft_results,
		'the Forum Topic query reads its contextual projection rather than the Group activity feed',
		array( $topic ) === axismundi_forum_topic_ids( $community )
	);

	$projected = function_exists( 'axismundi_op_transform_object' ) ? axismundi_op_transform_object( get_post( $topic ) ) : null;
	ax_ft_assert(
		$ax_ft_results,
		'an admitted public Topic projects as an Article with Group audience, thread context, and open comments',
		is_array( $projected )
			&& $author_public
			&& 'Article' === $projected['type']
			&& axismundi_forum_topic_object_uri( get_post( $topic ) ) === $projected['id']
			&& $group instanceof Axismundi_Actor
			&& $group->get_uri() === $projected['audience']
			&& array( $group->get_uri() ) === (array) $projected['to']
			&& array() === (array) $projected['cc']
			// context is the thread, audience is the Group; conflating them left no way to
			// name one discussion (Constitution Article 13).
			&& axismundi_forum_topic_context_uri( get_post( $topic ) ) === $projected['context']
			&& $group->get_uri() !== $projected['context']
			&& true === $projected['commentsEnabled']
	);

	$forbidden_lock = axismundi_forum_set_topic_locked( $topic, $stranger, true );
	$locked         = axismundi_forum_set_topic_locked( $topic, $owner, true );
	$locked_page    = function_exists( 'axismundi_op_transform_object' ) ? axismundi_op_transform_object( get_post( $topic ) ) : null;
	$sticky         = axismundi_forum_set_topic_sticky( $topic, $owner, true );
	$admit_second   = axismundi_forum_admit_local_topic( $community, $topic2, $owner );
	$sticky_order   = axismundi_forum_topic_ids( $community );
	$state_after    = axismundi_forum_get_topic_entry( $topic );
	$unlocked       = axismundi_forum_set_topic_locked( $topic, $owner, false );
	$unsticky       = axismundi_forum_set_topic_sticky( $topic, $owner, false );
	$state_final    = axismundi_forum_get_topic_entry( $topic );
	ax_ft_assert(
		$ax_ft_results,
		'only a Group manager may lock or pin a Topic; lock projects commentsEnabled false and both controls reopen cleanly',
		ax_ft_err( $forbidden_lock, 'ax_forum_forbidden' )
			&& true === $locked
			&& is_array( $locked_page )
			&& false === $locked_page['commentsEnabled']
			&& true === $sticky
			&& true === $admit_second
			&& array( $topic, $topic2 ) === $sticky_order
			&& is_array( $state_after )
			&& ! empty( $state_after['locked_at'] )
			&& ! empty( $state_after['sticky_position'] )
			&& true === $unlocked
			&& true === $unsticky
			&& is_array( $state_final )
			&& empty( $state_final['locked_at'] )
			&& empty( $state_final['sticky_position'] )
	);

	$resolved = apply_filters( 'axismundi_op_resolve_source_by_uri', null, axismundi_forum_topic_object_uri( get_post( $topic ) ) );
	ax_ft_assert(
		$ax_ft_results,
		'the stable Page URI resolves back only to its admitted Topic source',
		$resolved instanceof WP_Post && $topic === $resolved->ID
	);

	ax_ft_assert(
		$ax_ft_results,
		'a Topic cannot silently acquire a second Forum context',
		ax_ft_err( axismundi_forum_admit_local_topic( $community, $topic, $owner ), 'ax_forum_topic_context' )
	);

	wp_delete_post( $topic, true );
	ax_ft_assert(
		$ax_ft_results,
		'deleting a Topic removes only its contextual entry and leaves the community intact',
		null === axismundi_forum_get_topic_entry( $topic ) && axismundi_forum_is_community( $community )
	);

	// Removing a community's entries is the closest thing left to deleting the old Forum post.
	// What must survive is everything Forum does not own: the Topic source and the Group Actor.
	axismundi_forum_delete_entries_for_community( $community );
	ax_ft_assert(
		$ax_ft_results,
		'clearing a community removes its Topic entries but leaves the Topic source and Group Actor intact',
		null === axismundi_forum_get_topic_entry( $topic2 )
			&& get_post( $topic2 ) instanceof WP_Post
			&& axismundi_actors_get_by_identity( $group_id ) instanceof Axismundi_Actor
	);
} finally {
	$entries    = axismundi_forum_entries_table();
	$settings   = axismundi_forum_settings_table();
	$identities = axismundi_actors_identities_table();
	$actors     = axismundi_actors_actors_table();
	$addresses  = axismundi_actors_addresses_table();
	$managers   = axismundi_actors_managers_table();
	foreach ( array_unique( $ax_ft_post_ids ) as $post_id ) {
		$wpdb->delete( $entries, array( 'source_post_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		if ( get_post( (int) $post_id ) ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_unique( $ax_ft_identity_ids ) as $identity_id ) {
		$wpdb->delete( $entries, array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $settings, array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $addresses, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $managers, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $actors, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $identities, array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	if ( ! empty( $ax_ft_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_ft_user_ids ) as $user_id ) {
			if ( get_userdata( (int) $user_id ) ) {
				wp_delete_user( (int) $user_id );
			}
		}
	}
}

$ax_ft_failed = count( array_filter( $ax_ft_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_ft_results ) - $ax_ft_failed, count( $ax_ft_results ) );
exit( $ax_ft_failed > 0 ? 1 : 0 );
