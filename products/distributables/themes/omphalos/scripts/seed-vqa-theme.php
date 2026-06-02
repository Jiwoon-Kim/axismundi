<?php
/**
 * Seed the Core Theme VQA page — give the Query Loop + post-context blocks real data
 * so the specimens render meaningfully (a dynamic Query Loop shows the install's
 * posts; post-terms needs categories/tags; post-comments-count needs comments;
 * post-author-biography needs an author bio).
 *
 * Idempotent: update-or-create by deterministic slug. Korean stays inside this PHP
 * (run via `wp eval-file`, wired into scripts/seed.ps1).
 *
 * @package Omphalos
 */

// --- a category + tag so core/post-terms has something to render.
$cat_id = wp_create_category( 'VQA Theme Topic' ); // creates if missing; returns ID

$tag = term_exists( 'vqa-theme-tag', 'post_tag' );
if ( ! $tag ) {
	$tag = wp_insert_term( 'VQA Theme Tag', 'post_tag', array( 'slug' => 'vqa-theme-tag' ) );
}
$tag_id = is_array( $tag ) ? (int) $tag['term_id'] : (int) $tag;

// mogu featured image (imported by seed.ps1).
$mogu = get_posts( array( 'post_type' => 'attachment', 'name' => 'image-placeholder-mogu-1024', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids' ) );
$mogu = $mogu ? $mogu[0] : 0;

// `wp eval-file` runs with NO current user, so wp_insert_post() would default the
// author to 0 — and then every author-family block (post-author / -name / -biography
// / avatar) renders EMPTY. Set the author explicitly.
$admin    = get_user_by( 'login', 'admin' );
$admin_id = $admin ? (int) $admin->ID : 1;

// --- demo posts (featured + no-featured), assigned to the category + tag.
$content = '<!-- wp:paragraph --><p>Core Theme VQA용 더미 게시물. Query Loop / post meta / navigation 블록이 각 context에서 어떻게 렌더되는지 관찰하기 위한 글입니다. 이 문장은 post-excerpt로 표시됩니다.</p><!-- /wp:paragraph -->';
$posts   = array(
	array( 'slug' => 'theme-vqa-armstrong', 'title' => 'WordPress 7.0 “Armstrong” 살펴보기', 'thumb' => $mogu ),
	array( 'slug' => 'theme-vqa-blocks',    'title' => 'Theme 블록 카테고리 둘러보기',        'thumb' => 0 ),
	array( 'slug' => 'theme-vqa-query',      'title' => 'Query Loop와 post-context 블록',      'thumb' => $mogu ),
);

$ids = array();
foreach ( $posts as $p ) {
	$existing = get_posts( array( 'post_type' => 'post', 'name' => $p['slug'], 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
	$args     = array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => $p['title'],
		'post_name'    => $p['slug'],
		'post_content' => $content,
		'post_author'  => $admin_id,
	);
	if ( $existing ) {
		$args['ID'] = $existing[0];
		$id         = wp_update_post( $args );
	} else {
		$id = wp_insert_post( $args );
	}
	wp_set_post_categories( $id, array( $cat_id ) );
	wp_set_post_terms( $id, array( $tag_id ), 'post_tag' );
	if ( $p['thumb'] ) {
		set_post_thumbnail( $id, $p['thumb'] );
	} else {
		delete_post_thumbnail( $id );
	}
	$ids[] = $id;
}

// --- comments on the first post (post-comments-count / -link show non-zero).
$first = $ids[0];
if ( (int) get_comments( array( 'post_id' => $first, 'count' => true ) ) < 2 ) {
	wp_insert_comment( array( 'comment_post_ID' => $first, 'comment_author' => '김지운', 'comment_author_email' => 'kim@example.com', 'comment_content' => '좋은 정리네요 — Query Loop 컨텍스트 확인용 첫 댓글.', 'comment_approved' => 1 ) );
	wp_insert_comment( array( 'comment_post_ID' => $first, 'comment_author' => 'omphalos', 'comment_author_email' => 'omphalos@example.com', 'comment_content' => '두 번째 댓글 — comment-count / -link 표시 확인용.', 'comment_approved' => 1 ) );
}

// --- author bio for the default author (post-author-biography specimen).
if ( $admin && '' === trim( (string) get_user_meta( $admin->ID, 'description', true ) ) ) {
	wp_update_user( array( 'ID' => $admin->ID, 'description' => 'Omphalos 데모 작성자 — Twenty Twenty-Five 위에 Material Design 3를 브릿지하는 파일럿을 만듭니다.' ) );
}

// --- the VQA page (pattern reference).
$vexist = get_posts( array( 'post_type' => 'page', 'name' => 'vqa-theme', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
$vargs  = array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'VQA Theme',
	'post_name'    => 'vqa-theme',
	'post_content' => '<!-- wp:pattern {"slug":"omphalos/vqa-theme"} /-->',
);
if ( $vexist ) {
	$vargs['ID'] = $vexist[0];
	wp_update_post( $vargs );
	$vid = $vexist[0];
} else {
	$vid = wp_insert_post( $vargs );
}

WP_CLI::log( 'Theme VQA ready: ' . get_permalink( $vid ) . ' (cat=' . $cat_id . ', tag=' . $tag_id . ', posts=' . implode( ',', $ids ) . ')' );
