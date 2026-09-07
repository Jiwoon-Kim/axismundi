<?php
/**
 * Visibility model + enforcement (docs/SECURITY.md, docs/DATA-MODEL.md §2.0-§6).
 *
 * All enforcement is gated to Independent mode; in Core mode the plugin is inert.
 * Legacy Attachments (no _ax_media_* meta) resolve to legacy-public so they are
 * never dropped. Ownership and permission reuse post_author and core edit_post.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Attachment's explicit visibility policy with legacy-public default.
 *
 * @param int $post_id Attachment ID.
 * @return string inherit | public | unlisted | private
 */
function axismundi_media_attachment_visibility( int $post_id ) : string {
	$value = get_post_meta( $post_id, '_ax_media_visibility', true );
	return in_array( $value, array( 'inherit', 'public', 'unlisted', 'private' ), true ) ? (string) $value : 'public';
}

/**
 * Effective visibility after applying the assigned folder chain's narrowest tier.
 *
 * @param int $post_id Attachment ID.
 * @return string public | unlisted | private
 */
function axismundi_media_effective_visibility( int $post_id ) : string {
	$policy      = axismundi_media_attachment_visibility( $post_id );
	$folder_id   = function_exists( 'axismundi_media_attachment_folder' ) ? axismundi_media_attachment_folder( $post_id ) : 0;
	$folder_rank = function_exists( 'axismundi_media_folder_effective_tier_rank' )
		? axismundi_media_folder_effective_tier_rank( $folder_id )
		: 0;
	$item_rank = 'inherit' === $policy ? $folder_rank : max( axismundi_media_visibility_rank( $policy ), $folder_rank );
	return axismundi_media_visibility_from_rank( $item_rank );
}

/**
 * May the given user open the Attachment single page / REST single?
 * Password folders are allowed only after their folder cookie is unlocked.
 *
 * Ownership is `post_author`; permission reuses core `edit_post`, which maps to
 * the author or an `edit_others_posts` holder. `user_can( 0, … )` is false, so a
 * logged-out user never matches an author-0 (unowned) attachment.
 *
 * @param int      $post_id Attachment ID.
 * @param int|null $user_id User (defaults to current).
 * @return bool
 */
function axismundi_media_can_view_single( int $post_id, ?int $user_id = null ) : bool {
	$user_id = $user_id ?? get_current_user_id();
	if ( $user_id > 0 && user_can( $user_id, 'edit_post', $post_id ) ) {
		return true;
	}
	// Public + unlisted are reachable by direct id; private is not. A folder
	// password is an orthogonal gate checked after tier resolution.
	return in_array( axismundi_media_effective_visibility( $post_id ), array( 'public', 'unlisted' ), true )
		&& ( ! function_exists( 'axismundi_media_locked_gate_for_attachment' ) || 0 === axismundi_media_locked_gate_for_attachment( $post_id ) );
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
		if ( $id && 'private' === axismundi_media_effective_visibility( $id ) && ! current_user_can( 'edit_post', $id ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		} elseif ( $id && function_exists( 'axismundi_media_locked_gate_for_attachment' ) ) {
			$gate = axismundi_media_locked_gate_for_attachment( $id );
			if ( $gate > 0 ) {
				global $wp_query;
				$wp_query->set( 'ax_media_gate_required', $gate );
			}
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
			$post_id = (int) $m[1];
			if ( 'private' === axismundi_media_effective_visibility( $post_id ) && ! current_user_can( 'edit_post', $post_id ) ) {
				return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.', 'axismundi-media-library' ), array( 'status' => 404 ) );
			}
			if ( function_exists( 'axismundi_media_locked_gate_for_attachment' ) && axismundi_media_locked_gate_for_attachment( $post_id ) > 0 ) {
				return new WP_Error( 'ax_media_password_required', __( 'A media-folder password is required.', 'axismundi-media-library' ), array( 'status' => 401 ) );
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
		$force_anonymous = (bool) $query->get( 'ax_media_force_anonymous' );
		if ( ! $force_anonymous && current_user_can( 'edit_others_posts' ) ) {
			return $where;
		}
		global $wpdb;
		$uid = $force_anonymous ? 0 : (int) get_current_user_id();
		$max_rank = min( 1, max( 0, (int) $query->get( 'ax_media_visibility_max_rank' ) ) );
		$excluded = 1 === $max_rank ? "('private')" : "('unlisted','private')";
		// "mine" only applies to a real logged-in user — a logged-out uid of 0
		// must NOT match author-0 (imported) media.
		$mine = $uid > 0 ? "{$wpdb->posts}.post_author = {$uid} OR " : '';
		$gate_exclusion = $query->get( 'ax_media_allow_gated' ) ? '' : "
				AND {$wpdb->posts}.ID NOT IN (
					SELECT tr.object_id
					FROM {$wpdb->term_relationships} AS tr
					INNER JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'ax_media_folder'
					INNER JOIN {$wpdb->termmeta} AS tm ON tm.term_id = tt.term_id AND tm.meta_key = '_ax_media_folder_effective_gated'
					WHERE CAST(tm.meta_value AS UNSIGNED) = 1
				)";
		// Show: my own OR effective public + listed. An item is non-public when
		// either its explicit policy is unlisted/private OR its assigned folder's
		// derived chain rank is > 0. Legacy rows and unfiled inherit rows remain
		// public because they match neither exclusion.
		$where .= " AND (
			{$mine}(
				{$wpdb->posts}.ID NOT IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ax_media_visibility' AND meta_value IN {$excluded} )
				AND {$wpdb->posts}.ID NOT IN (
					SELECT tr.object_id
					FROM {$wpdb->term_relationships} AS tr
					INNER JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'ax_media_folder'
					INNER JOIN {$wpdb->termmeta} AS tm ON tm.term_id = tt.term_id AND tm.meta_key = '_ax_media_folder_effective_tier_rank'
					WHERE CAST(tm.meta_value AS UNSIGNED) > {$max_rank}
				)
				{$gate_exclusion}
				AND {$wpdb->posts}.ID NOT IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ax_media_listed' AND meta_value = '0' )
			)
		)"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Only an int-cast user id and static literals; no external input.
		return $where;
	},
	10,
	2
);

/* ------------------------------------------------------------------ *
 * 5. New uploads are unbound (COMPATIBILITY.md §2) — Independent mode only.
 *    post_parent = 0 is set BEFORE the INSERT (atomic) via wp_insert_attachment_data,
 *    so no earlier add_attachment callback ever observes a stale parent. Owner is
 *    post_author (core-set); visibility defaults to legacy-public via the fallback,
 *    so no meta is stamped at upload — the editor sets it explicitly.
 * ------------------------------------------------------------------ */
add_filter(
	'wp_insert_attachment_data',
	static function ( $data, $postarr, $unsanitized_postarr, $update ) {
		if ( $update ) {
			return $data; // An update, not a new attachment.
		}
		if ( 'attachment' !== ( $data['post_type'] ?? '' ) || ! axismundi_media_is_independent() ) {
			return $data;
		}
		$data['post_parent'] = 0;
		return $data;
	},
	10,
	4
);
