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

// --- the Comments VQA page (pattern reference), comments OPEN.
$slug   = 'vqa-theme-comments';
$exist  = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
$args   = array(
	'post_type'      => 'page',
	'post_status'    => 'publish',
	'post_title'     => 'VQA Theme Comments',
	'post_name'      => $slug,
	'post_content'   => '<!-- wp:pattern {"slug":"omphalos/vqa-theme-comments"} /-->',
	'comment_status' => 'open',
);
if ( $exist ) {
	$args['ID'] = $exist[0];
	$pid        = wp_update_post( $args );
} else {
	$pid = wp_insert_post( $args );
}
// Force comments open even if WP defaults closed them for pages.
wp_update_post( array( 'ID' => $pid, 'comment_status' => 'open' ) );

// --- seed comments on the PAGE (so core/comments renders them). Two top-level + one reply.
$have = (int) get_comments( array( 'post_id' => $pid, 'count' => true ) );
if ( $have < 3 ) {
	// clear any partial set so the threaded structure is deterministic.
	foreach ( get_comments( array( 'post_id' => $pid, 'fields' => 'ids' ) ) as $cid ) {
		wp_delete_comment( (int) $cid, true );
	}
	$c1 = wp_insert_comment( array(
		'comment_post_ID'      => $pid,
		'comment_author'       => '김지운',
		'comment_author_email' => 'kim@example.com',
		'comment_content'      => '첫 번째 최상위 댓글 — comment-author-name / -date / -content / -reply-link specimen.',
		'comment_approved'     => 1,
		'user_id'              => $admin_id,
	) );
	wp_insert_comment( array(
		'comment_post_ID'      => $pid,
		'comment_author'       => 'omphalos',
		'comment_author_email' => 'omphalos@example.com',
		'comment_content'      => '두 번째 최상위 댓글 — 목록 rhythm / 두 번째 항목 관찰용.',
		'comment_approved'     => 1,
	) );
	wp_insert_comment( array(
		'comment_post_ID'      => $pid,
		'comment_author'       => '김지운',
		'comment_author_email' => 'kim@example.com',
		'comment_content'      => '대댓글(threaded reply) — comment-template 중첩 / 들여쓰기 specimen.',
		'comment_approved'     => 1,
		'comment_parent'       => (int) $c1,
		'user_id'              => $admin_id,
	) );
}

WP_CLI::log( 'Comments VQA ready: ' . get_permalink( $pid ) . ' (page=' . $pid . ', comments=' . (int) get_comments( array( 'post_id' => $pid, 'count' => true ) ) . ')' );
