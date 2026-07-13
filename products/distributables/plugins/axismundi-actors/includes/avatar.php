<?php
/**
 * Phase 4c — use a local Person actor's avatar as their WordPress avatar.
 *
 * When a user's actor has an `avatar_attachment_id`, WordPress avatars (comments,
 * admin, author blocks) show it instead of the Gravatar. Default on; a site can turn
 * it off with the `axismundi_actors_use_actor_avatar` filter. Only Person actors are
 * affected — the header image stays Actor-profile-only, and site / remote actors are
 * not WordPress users so `get_avatar` never resolves to them.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve a WordPress user id from the many shapes `get_avatar` accepts.
 *
 * @param mixed $id_or_email User id, email, WP_User, WP_Post, or WP_Comment.
 * @return int User id, or 0.
 */
function axismundi_actors_resolve_avatar_user( $id_or_email ) : int {
	if ( is_numeric( $id_or_email ) ) {
		return (int) $id_or_email;
	}
	if ( $id_or_email instanceof WP_User ) {
		return (int) $id_or_email->ID;
	}
	if ( $id_or_email instanceof WP_Post ) {
		return (int) $id_or_email->post_author;
	}
	if ( $id_or_email instanceof WP_Comment ) {
		if ( ! empty( $id_or_email->user_id ) ) {
			return (int) $id_or_email->user_id;
		}
		$user = ! empty( $id_or_email->comment_author_email ) ? get_user_by( 'email', $id_or_email->comment_author_email ) : false;
		return $user ? (int) $user->ID : 0;
	}
	if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
		$user = get_user_by( 'email', $id_or_email );
		return $user ? (int) $user->ID : 0;
	}
	return 0;
}

/**
 * Swap in the actor avatar when the resolved user's actor carries one.
 *
 * @param array $args        get_avatar_data arguments.
 * @param mixed $id_or_email Avatar subject.
 * @return array
 */
function axismundi_actors_filter_avatar_data( array $args, $id_or_email ) : array {
	/**
	 * Whether an actor's avatar should override the WordPress avatar (default true).
	 *
	 * @param bool $enabled Enabled.
	 */
	if ( ! (bool) apply_filters( 'axismundi_actors_use_actor_avatar', true ) ) {
		return $args;
	}
	if ( ! function_exists( 'axismundi_actors_get_for_user' ) ) {
		return $args;
	}
	$user_id = axismundi_actors_resolve_avatar_user( $id_or_email );
	if ( $user_id <= 0 ) {
		return $args;
	}
	$actor = axismundi_actors_get_for_user( $user_id );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return $args;
	}
	$attachment_id = $actor->get_avatar_attachment_id();
	if ( $attachment_id <= 0 ) {
		return $args;
	}
	$size = isset( $args['size'] ) ? (int) $args['size'] : 96;
	$url  = wp_get_attachment_image_url( $attachment_id, array( $size, $size ) );
	if ( ! $url ) {
		$url = wp_get_attachment_url( $attachment_id );
	}
	if ( $url ) {
		$args['url']          = $url;
		$args['found_avatar'] = true;
	}
	return $args;
}
add_filter( 'get_avatar_data', 'axismundi_actors_filter_avatar_data', 10, 2 );
