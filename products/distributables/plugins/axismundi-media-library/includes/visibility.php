<?php
/**
 * Visibility model + enforcement (docs/SECURITY.md, docs/DATA-MODEL.md §2.0-§6).
 *
 * All enforcement is gated to Independent mode; in Core mode the plugin is inert.
 * Legacy Attachments (no _ax_media_* meta) resolve to legacy-public so they are
 * never dropped. Permission keys on effective_owner_id (owner meta ?? post_author).
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

/**
 * Library owner (effective): _ax_media_owner_id, else post_author.
 *
 * @param int $post_id Attachment ID.
 * @return int
 */
function axismundi_media_effective_owner_id( int $post_id ) : int {
	$owner = (int) get_post_meta( $post_id, '_ax_media_owner_id', true );
	if ( $owner > 0 ) {
		return $owner;
	}
	$post = get_post( $post_id );
	return $post ? (int) $post->post_author : 0;
}

/**
 * Effective visibility with legacy-public default.
 *
 * @param int $post_id Attachment ID.
 * @return string public | unlisted | private
 */
function axismundi_media_effective_visibility( int $post_id ) : string {
	$value = get_post_meta( $post_id, '_ax_media_visibility', true );
	return in_array( $value, array( 'public', 'unlisted', 'private' ), true ) ? (string) $value : 'public';
}

/**
 * May the given user open the Attachment single page / REST single?
 * (protected challenge is Phase 3.)
 *
 * @param int      $post_id Attachment ID.
 * @param int|null $user_id User (defaults to current).
 * @return bool
 */
function axismundi_media_can_view_single( int $post_id, ?int $user_id = null ) : bool {
	$user_id = $user_id ?? get_current_user_id();
	if ( $user_id > 0 && ( $user_id === axismundi_media_effective_owner_id( $post_id ) || user_can( $user_id, 'edit_others_posts' ) ) ) {
		return true;
	}
	// public + unlisted are reachable by direct id; private is not.
	return in_array( axismundi_media_effective_visibility( $post_id ), array( 'public', 'unlisted' ), true );
}

/* ------------------------------------------------------------------ *
 * 1. HTML single — template_redirect guard (SECURITY.md §4.1)
 * ------------------------------------------------------------------ */
add_action(
	'template_redirect',
	static function () : void {
		if ( ! axismundi_media_is_independent() || ! is_attachment() ) {
			return;
		}
		$id = (int) get_queried_object_id();
		if ( $id && ! axismundi_media_can_view_single( $id ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}
);

/* ------------------------------------------------------------------ *
 * 2. REST single — rest_pre_dispatch route guard (SECURITY.md §4.2)
 *    404 (not 403) so existence stays hidden.
 * ------------------------------------------------------------------ */
add_filter(
	'rest_pre_dispatch',
	static function ( $result, $server, $request ) {
		if ( ! axismundi_media_is_independent() || 'GET' !== $request->get_method() ) {
			return $result;
		}
		if ( preg_match( '#^/wp/v2/media/(\d+)$#', (string) $request->get_route(), $m ) ) {
			if ( ! axismundi_media_can_view_single( (int) $m[1] ) ) {
				return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.', 'axismundi-media-library' ), array( 'status' => 404 ) );
			}
		}
		return $result;
	},
	10,
	3
);

/* ------------------------------------------------------------------ *
 * 3. REST collection + 4. media modal — flag the query, filter in SQL
 *    (SECURITY.md §4.3-§4.4). No global pre_get_posts: only these two
 *    queries set the flag, and posts_where acts only when it is set.
 * ------------------------------------------------------------------ */
add_filter(
	'rest_attachment_query',
	static function ( $args ) {
		if ( axismundi_media_is_independent() && ! current_user_can( 'edit_others_posts' ) ) {
			$args['ax_media_visibility_filter'] = true;
		}
		return $args;
	}
);
add_filter(
	'ajax_query_attachments_args',
	static function ( $args ) {
		if ( axismundi_media_is_independent() && ! current_user_can( 'edit_others_posts' ) ) {
			$args['ax_media_visibility_filter'] = true;
		}
		return $args;
	}
);
add_filter(
	'posts_where',
	static function ( $where, $query ) {
		if ( ! $query->get( 'ax_media_visibility_filter' ) ) {
			return $where;
		}
		global $wpdb;
		$uid = (int) get_current_user_id();
		// "mine" only applies to a real logged-in user — a logged-out uid of 0
		// must NOT match author-0 (imported) media.
		$mine = $uid > 0 ? "{$wpdb->posts}.post_author = {$uid} OR " : '';
		// Show: my own OR (not unlisted/private/protected AND not listed=0).
		// Legacy rows (no meta) fall through both NOT INs → included.
		$where .= " AND (
			{$mine}(
				{$wpdb->posts}.ID NOT IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ax_media_visibility' AND meta_value IN ('unlisted','private','protected') )
				AND {$wpdb->posts}.ID NOT IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ax_media_listed' AND meta_value = '0' )
			)
		)"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Only an int-cast user id and static literals; no external input.
		return $where;
	},
	10,
	2
);

/* ------------------------------------------------------------------ *
 * 5. Canonical single URL = /?attachment_id={id} (ROUTING.md §1)
 * ------------------------------------------------------------------ */
add_filter(
	'attachment_link',
	static function ( $link, $post_id ) {
		if ( ! axismundi_media_is_independent() ) {
			return $link;
		}
		return home_url( '/?attachment_id=' . (int) $post_id );
	},
	10,
	2
);

/* ------------------------------------------------------------------ *
 * 6. New-upload defaults (COMPATIBILITY.md §2) — Independent mode only.
 * ------------------------------------------------------------------ */
add_action(
	'add_attachment',
	static function ( int $post_id ) : void {
		if ( ! axismundi_media_is_independent() ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return;
		}
		if ( (int) $post->post_parent > 0 ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_parent' => 0,
				)
			);
		}
		$current = (int) get_current_user_id();
		if ( '' === get_post_meta( $post_id, '_ax_media_owner_id', true ) ) {
			update_post_meta( $post_id, '_ax_media_owner_id', $current > 0 ? $current : (int) $post->post_author );
		}
		if ( '' === get_post_meta( $post_id, '_ax_media_visibility', true ) ) {
			update_post_meta( $post_id, '_ax_media_visibility', 'public' );
			update_post_meta( $post_id, '_ax_media_listed', '1' );
			update_post_meta( $post_id, '_ax_media_searchable', '1' );
		}
		if ( '' === get_post_meta( $post_id, '_ax_media_uploaded_at', true ) ) {
			update_post_meta( $post_id, '_ax_media_uploaded_at', current_time( 'mysql' ) );
		}
	}
);
