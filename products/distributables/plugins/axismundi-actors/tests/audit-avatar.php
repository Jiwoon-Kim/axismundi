<?php
/**
 * Phase 4c — actor avatar → WordPress avatar regression (dev-only; dist-excluded).
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/avatar.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

global $wpdb;
$ax_av_results = array();
$ax_av_ids     = array();
$ax_av_users   = array();
$ax_av_atts    = array();

/**
 * @param array  $results Accumulator.
 * @param string $label Contract.
 * @param bool   $cond Holds.
 * @return void
 */
function ax_av_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

try {
	axismundi_actors_install();

	$uid = (int) wp_insert_user( array( 'user_login' => 'ax_av_alice', 'user_pass' => wp_generate_password(), 'user_email' => 'ax-av-alice@example.test', 'role' => 'author' ) );
	$ax_av_users[] = $uid;
	$actor = axismundi_actors_ensure_for_user( $uid );
	$ax_av_ids[] = $actor->get_identity_id();

	// No avatar yet → get_avatar_data keeps the core (Gravatar) URL.
	$before = get_avatar_data( $uid, array( 'size' => 96 ) );
	ax_av_assert( $ax_av_results, 'with no actor avatar the WordPress avatar is unchanged', false !== strpos( (string) $before['url'], 'gravatar.com' ) );

	// Give the actor an image avatar with a resolvable file.
	$img = (int) wp_insert_attachment( array( 'post_title' => 'Ava', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'post_author' => $uid ) );
	$ax_av_atts[] = $img;
	update_post_meta( $img, '_wp_attached_file', '2026/01/ax-avatar.jpg' );
	axismundi_actors_set_profile_media( $actor, 'avatar', $img, $uid );
	$expected = wp_get_attachment_url( $img );

	// get_avatar_data now returns the actor avatar for a user id.
	$after = get_avatar_data( $uid, array( 'size' => 96 ) );
	ax_av_assert( $ax_av_results, 'the actor avatar overrides the WordPress avatar (by user id)', $expected === $after['url'] && true === $after['found_avatar'] );

	// It resolves through the same shapes get_avatar accepts.
	$by_email = get_avatar_data( 'ax-av-alice@example.test', array() );
	$post_id  = (int) wp_insert_post( array( 'post_title' => 'p', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => $uid ) );
	$ax_av_atts[] = $post_id; // cleaned up as a post below via wp_delete_post through attachments loop? no — track separately.
	$by_post  = get_avatar_data( get_post( $post_id ), array() );
	ax_av_assert( $ax_av_results, 'the avatar resolves from an email and from a post author', $expected === $by_email['url'] && $expected === $by_post['url'] );
	wp_delete_post( $post_id, true );
	array_pop( $ax_av_atts );

	// The site-wide filter can turn it off.
	add_filter( 'axismundi_actors_use_actor_avatar', '__return_false' );
	$disabled = get_avatar_data( $uid, array( 'size' => 96 ) );
	remove_filter( 'axismundi_actors_use_actor_avatar', '__return_false' );
	ax_av_assert( $ax_av_results, 'the axismundi_actors_use_actor_avatar filter can disable the override', false !== strpos( (string) $disabled['url'], 'gravatar.com' ) );

	// A user without an actor is untouched.
	$plain = (int) wp_insert_user( array( 'user_login' => 'ax_av_bob', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
	$ax_av_users[] = $plain;
	$plain_data = get_avatar_data( $plain, array() );
	ax_av_assert( $ax_av_results, 'a user with no actor keeps the default avatar', false !== strpos( (string) $plain_data['url'], 'gravatar.com' ) );

} finally {
	foreach ( $ax_av_atts as $att ) {
		if ( $att && get_post( $att ) ) {
			wp_delete_attachment( (int) $att, true );
		}
	}
	foreach ( array_unique( $ax_av_ids ) as $iid ) {
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( $ax_av_users as $u ) {
		if ( get_userdata( $u ) ) {
			wp_delete_user( $u );
		}
	}
}

$ax_av_failures = count( array_filter( $ax_av_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_av_results ), $ax_av_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_av_failures > 0 ? 1 : 0 );
}
exit( $ax_av_failures > 0 ? 1 : 0 );
