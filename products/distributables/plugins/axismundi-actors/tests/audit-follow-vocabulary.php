<?php
/**
 * Follow vocabulary regression (dev-only; dist-excluded).
 *
 * One relation, two vocabularies. The activity on the wire and the row in the ledger stay
 * `Follow` for every kind of Actor — splitting them would be a protocol invention and would break
 * peers that already know what Follow means. Only the words change, because "Following" on a
 * community reads as if the community were a person.
 *
 * This locks that the split is presentational and nothing more.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_fv_results = array();
$ax_fv_users   = array();
$ax_fv_ids     = array();

/** @param bool[] $results Results. */
function ax_fv_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$owner         = (int) wp_insert_user( array( 'user_login' => 'axfv_' . strtolower( wp_generate_password( 9, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$ax_fv_users[] = $owner;
	wp_set_current_user( $owner );
	$person      = axismundi_actors_ensure_for_user( $owner );
	$ax_fv_ids[] = $person instanceof Axismundi_Actor ? $person->get_identity_id() : 0;
	axismundi_actors_register_handle( $person->get_identity_id(), 'axfvp' . strtolower( wp_generate_password( 7, false, false ) ) );
	axismundi_actors_set_status( $person->get_identity_id(), 'public' );
	$person = axismundi_actors_get_by_identity( $person->get_identity_id() );

	$group       = axismundi_actors_create_managed_group( array( 'owner_user_id' => $owner, 'preferred_username' => 'axfvg' . strtolower( wp_generate_password( 7, false, false ) ), 'status' => 'public' ) );
	$ax_fv_ids[] = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;

	$person_words = axismundi_actors_follow_vocabulary( 'Person' );
	$group_words  = axismundi_actors_follow_vocabulary( 'Group' );
	ax_fv_assert(
		$ax_fv_results,
		'a community is subscribed to while a person is followed, in the verb and in both collections',
		'Follow' === $person_words['verb'] && 'Followers' === $person_words['inbound'] && 'Following' === $person_words['outbound']
			&& 'Subscribe' === $group_words['verb'] && 'Subscribers' === $group_words['inbound'] && 'Subscriptions' === $group_words['outbound']
	);

	$person_state = axismundi_act_follow_button_state( $person, $person );
	$group_state  = axismundi_act_follow_button_state( $person, $group );
	ax_fv_assert(
		$ax_fv_results,
		'the follow control takes its words from the kind of Actor it points at',
		'Subscribe' === $group_state['action'] && 'Follow' === $person_state['action']
	);

	/*
	 * The signed-in control is not the only one a community shows. A logged-out visitor gets the
	 * remote-follow surface instead, and that path wrote "Follow" into its markup directly — so a
	 * community offered to be followed as though it were a person, while the state above said
	 * "Subscribe" and this audit passed. Checking the state alone is what let that through, so
	 * this reads the markup a visitor actually receives.
	 */
	$group_modal  = axismundi_act_render_remote_follow_modal( $group, 'ax-fv-group', $group->get_profile_url() );
	$person_modal = axismundi_act_render_remote_follow_modal( $person, 'ax-fv-person', $person->get_profile_url() );
	ax_fv_assert(
		$ax_fv_results,
		'the logged-out remote surface names the act the same way the signed-in control does',
		'' !== $group_modal && '' !== $person_modal
			&& false !== strpos( $group_modal, 'Subscribe to' )
			&& false === strpos( $group_modal, 'Follow this profile' )
			&& false !== strpos( $person_modal, 'Follow this profile' )
			&& false === strpos( $person_modal, 'Subscribe' )
	);

	/*
	 * The point of the whole exercise: the words changed and nothing else did. A relation type
	 * of anything but `follow` would mean a peer receiving it could not tell what we meant.
	 */
	$activity_uri = home_url( '/activities/' . wp_generate_uuid4() . '/' );
	$recorded     = axismundi_act_write_relation( 'follow', $person->get_uri(), $group->get_uri(), 'outbound', 'accepted', $activity_uri, $activity_uri );
	$relation = axismundi_act_get_relation( 'follow', $person->get_uri(), $group->get_uri() );
	ax_fv_assert(
		$ax_fv_results,
		'subscribing to a community is stored as an ordinary Follow relation, not as a second type',
		( true === $recorded || is_array( $recorded ) )
			&& is_array( $relation )
			&& 'follow' === (string) $relation['relation_type']
			&& 'accepted' === (string) $relation['state']
	);

	// A moderator is a permission the forum owns, so the list asks rather than infers it.
	$roles = axismundi_actors_follow_list_roles( $group, array( $person->get_uri() ) );
	ax_fv_assert(
		$ax_fv_results,
		'a community subscriber who runs it is marked, and the same person on their own list is not',
		array( $person->get_uri() => 'Moderator' ) === $roles
			&& array() === axismundi_actors_follow_list_roles( $person, array( $person->get_uri() ) )
	);

	// Every subscriber is not a member: membership is a separate Forum state with its own
	// evidence, and badging all of them would tell a reader nothing they came to find out.
	$members = function_exists( 'axismundi_forum_get_membership' )
		? axismundi_forum_get_membership( $group->get_identity_id(), $person->get_identity_id() )
		: null;
	ax_fv_assert(
		$ax_fv_results,
		'a Follow alone does not label someone a member of the community',
		! is_array( $roles ) || ! in_array( 'Member', array_values( $roles ), true )
	);
	unset( $members );
} finally {
	wp_set_current_user( 0 );
	foreach ( array_filter( array_unique( $ax_fv_ids ) ) as $identity_id ) {
		$fixture = axismundi_actors_get_by_identity( (int) $identity_id );
		if ( $fixture instanceof Axismundi_Actor && function_exists( 'axismundi_act_relations_table' ) ) {
			$wpdb->delete( axismundi_act_relations_table(), array( 'subject_actor_uri_hash' => hash( 'sha256', $fixture->get_uri() ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
			$wpdb->delete( axismundi_act_relations_table(), array( 'object_actor_uri_hash' => hash( 'sha256', $fixture->get_uri() ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		foreach ( array( axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_actors_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( array_filter( array_unique( $ax_fv_users ) ) as $user_id ) {
		if ( get_userdata( (int) $user_id ) ) {
			wp_delete_user( (int) $user_id );
		}
	}
}

$ax_fv_failed = count( array_filter( $ax_fv_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_fv_results ) - $ax_fv_failed, count( $ax_fv_results ) );
exit( $ax_fv_failed > 0 ? 1 : 0 );
