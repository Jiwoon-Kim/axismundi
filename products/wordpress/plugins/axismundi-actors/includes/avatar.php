<?php
/**
 * WordPress User avatars and Actor profile images are independent.
 *
 * The legacy adapter remains callable but never overrides WordPress avatars.
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
 * Compatibility no-op. No WordPress avatar hook is registered by Actors.
 *
 * @param array $args        get_avatar_data arguments.
 * @param mixed $id_or_email Avatar subject.
 * @return array
 */
function axismundi_actors_filter_avatar_data( array $args, $id_or_email ) : array {
	return $args;
}
