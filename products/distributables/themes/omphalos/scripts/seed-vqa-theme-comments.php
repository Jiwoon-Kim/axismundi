<?php
/**
 * Seed the Comments VQA page (THEME-VQA-ROUTE §3 Phase 2). core/comments renders the
 * CURRENT object's approved comments, so the page must have comments OPEN and real
 * comments. Seeds two top-level comments + one threaded reply (so the nesting / reply
 * structure is observable) on the page itself.
 *
 * Idempotent: update-or-create by deterministic slug; comments only inserted if the page
 * has fewer than the seeded set. Korean stays inside this PHP (run via `wp eval-file`,
 * wired into scripts/seed.ps1).
 *
 * @package Omphalos
 */

// `wp eval-file` runs with NO current user → set comment authors explicitly (display only;
// these are guest comments, not registered users).
$admin    = get_user_by( 'login', 'admin' );
$admin_id = $admin ? (int) $admin->ID : 0;

// Threaded comments must be ON with depth >= 5 for the depth-5 reply chain below to nest
// (core caps display at thread_comments_depth; deeper replies flatten otherwise).
update_option( 'thread_comments', 1 );
if ( (int) get_option( 'thread_comments_depth' ) < 5 ) {
	update_option( 'thread_comments_depth', 5 );
}

// Parent = the Theme VQA page (page hierarchy: Comments is a child of VQA Theme).
$omph_parent    = get_posts( array( 'post_type' => 'page', 'name' => 'vqa-theme', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
$omph_parent_id = $omph_parent ? (int) $omph_parent[0] : 0;

// --- the Comments VQA page (pattern reference), comments OPEN, child of VQA Theme.
$slug   = 'vqa-theme-comments';
$exist  = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
$args   = array(
	'post_type'      => 'page',
	'post_status'    => 'publish',
	'post_title'     => 'VQA Theme Comments',
	'post_name'      => $slug,
	'post_parent'    => $omph_parent_id,
	'post_content'   => '<!-- wp:pattern {"slug":"omphalos/vqa-theme-comments"} /-->',
	'comment_status' => 'open',
	'page_template'  => 'page-with-comments',
);
if ( $exist ) {
	$args['ID'] = $exist[0];
	$pid        = wp_update_post( $args );
} else {
	$pid = wp_insert_post( $args );
}
// Force comments open even if WP defaults closed them for pages.
wp_update_post( array( 'ID' => $pid, 'comment_status' => 'open' ) );
// Assign the custom page template that renders core/comments in TEMPLATE context.
update_post_meta( $pid, '_wp_page_template', 'page-with-comments' );

// --- seed comments on the PAGE (so core/comments renders them): a single 5-DEEP reply chain
// (depth 1 → 5, to the thread_comments_depth limit) so comment-template nesting is observable
// at every level, plus a second top-level comment for list breadth.
$want = 6;
$have = (int) get_comments( array( 'post_id' => $pid, 'count' => true ) );
if ( $have < $want ) {
	// clear any partial set so the threaded structure is deterministic.
	foreach ( get_comments( array( 'post_id' => $pid, 'fields' => 'ids' ) ) as $cid ) {
		wp_delete_comment( (int) $cid, true );
	}
	$mk = static function ( $pid, $author, $email, $content, $parent, $uid ) {
		return (int) wp_insert_comment( array(
			'comment_post_ID'      => $pid,
			'comment_author'       => $author,
			'comment_author_email' => $email,
			'comment_content'      => $content,
			'comment_approved'     => 1,
			'comment_parent'       => (int) $parent,
			'user_id'              => (int) $uid,
		) );
	};
	$d1 = $mk( $pid, '김지운',   'kim@example.com',      '깊이 1 — 최상위 댓글 (threaded chain 1/5).',                 0,   $admin_id );
	$d2 = $mk( $pid, 'omphalos', 'omphalos@example.com', '깊이 2 — 대댓글 (2/5).',                                     $d1, 0 );
	$d3 = $mk( $pid, '김지운',   'kim@example.com',      '깊이 3 — 대대댓글 (3/5).',                                   $d2, $admin_id );
	$d4 = $mk( $pid, 'omphalos', 'omphalos@example.com', '깊이 4 — (4/5).',                                            $d3, 0 );
	$mk( $pid, '김지운',   'kim@example.com',      '깊이 5 — 최대 깊이 (5/5, thread_comments_depth 한계).',      $d4, $admin_id );
	$mk( $pid, '방문자',   'guest@example.com',    '두 번째 최상위 댓글 — 목록 rhythm / breadth 관찰용.',         0,   0 );
}

WP_CLI::log( 'Comments VQA ready: ' . get_permalink( $pid ) . ' (page=' . $pid . ', comments=' . (int) get_comments( array( 'post_id' => $pid, 'count' => true ) ) . ')' );
