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

// --- a real wp_navigation menu so the nav specimen renders an ACTUAL menu (a bare
// core/navigation falls back to the page list). Idempotent by slug; the pattern
// (patterns/vqa-theme.php) looks this up and references it via `ref`.
//
// Structured as a small VQA SITEMAP with one nested submenu so the dropdown / overlay
// nested-section + description specimens are observable WITHOUT bloat. "VQA Theme" is a
// submenu whose children are the FUTURE Phase-2/3 subpages (Comments / Archive) — they
// don't exist yet (placeholder anchors), they just give the nested level + a real
// reason to nest. Embeds / Embed Template / Attachment links were pulled (they belong
// in their own lanes / a footer site-map, not the Theme nav). Two items carry a
// `description` to specimen core/navigation-link description rendering (it renders).
// Korean lives in this eval-file (mojibake-safe), never PowerShell stdout.
$u = static function ( $q ) { return home_url( '/' ) . '?' . $q; };
$nav_content = '<!-- wp:home-link {"label":"Home"} /-->'
	. '<!-- wp:navigation-submenu {"label":"VQA","url":"#vqa","kind":"custom"} -->'
	.   '<!-- wp:navigation-link {"label":"Prose VQA","url":"' . $u( 'page_id=12' ) . '","kind":"custom","description":"Markdown 장문 prose 블록"} /-->'
	.   '<!-- wp:navigation-link {"label":"VQA Text","url":"' . $u( 'page_id=13' ) . '","kind":"custom","description":"텍스트 / 타이포 블록"} /-->'
	.   '<!-- wp:navigation-link {"label":"VQA Media","url":"' . $u( 'page_id=14' ) . '","kind":"custom"} /-->'
	.   '<!-- wp:navigation-link {"label":"VQA Design","url":"' . $u( 'page_id=21' ) . '","kind":"custom"} /-->'
	.   '<!-- wp:navigation-link {"label":"VQA Widgets","url":"' . $u( 'page_id=33' ) . '","kind":"custom"} /-->'
	.   '<!-- wp:navigation-submenu {"label":"VQA Theme","url":"' . $u( 'page_id=68' ) . '","kind":"custom"} -->'
	.     '<!-- wp:navigation-link {"label":"VQA Comments","url":"#vqa-comments-future","kind":"custom","description":"Phase 2 (예정)"} /-->'
	.     '<!-- wp:navigation-link {"label":"VQA Archive","url":"#vqa-archive-future","kind":"custom","description":"Phase 3 (예정)"} /-->'
	.   '<!-- /wp:navigation-submenu -->'
	.   '<!-- wp:navigation-link {"label":"VQA Embeds","url":"' . $u( 'page_id=52' ) . '","kind":"custom"} /-->'
	.   '<!-- wp:navigation-link {"label":"VQA Embed Template","url":"' . $u( 'page_id=63' ) . '","kind":"custom"} /-->'
	.   '<!-- wp:navigation-submenu {"label":"Attachment page","url":"#attachments","kind":"custom"} -->'
	.     '<!-- wp:navigation-submenu {"label":"Images","url":"#att-images","kind":"custom"} -->'
	.       '<!-- wp:navigation-link {"label":"Image (webp)","url":"' . $u( 'attachment_id=6' ) . '","kind":"custom"} /-->'
	.       '<!-- wp:navigation-link {"label":"Image (jpeg)","url":"' . $u( 'attachment_id=8' ) . '","kind":"custom"} /-->'
	.       '<!-- wp:navigation-link {"label":"Logo (png)","url":"' . $u( 'attachment_id=73' ) . '","kind":"custom"} /-->'
	.       '<!-- wp:navigation-link {"label":"Wide (png)","url":"' . $u( 'attachment_id=57' ) . '","kind":"custom"} /-->'
	.     '<!-- /wp:navigation-submenu -->'
	.     '<!-- wp:navigation-link {"label":"Audio (ogg)","url":"' . $u( 'attachment_id=7' ) . '","kind":"custom"} /-->'
	.     '<!-- wp:navigation-link {"label":"Video (webm)","url":"' . $u( 'attachment_id=9' ) . '","kind":"custom"} /-->'
	.   '<!-- /wp:navigation-submenu -->'
	. '<!-- /wp:navigation-submenu -->'
	. '<!-- wp:loginout /-->';
$nav_exist = get_posts( array( 'post_type' => 'wp_navigation', 'name' => 'vqa-theme-nav', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
$nav_args  = array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => 'VQA Theme Nav', 'post_name' => 'vqa-theme-nav', 'post_content' => $nav_content );
if ( $nav_exist ) {
	$nav_args['ID'] = $nav_exist[0];
	wp_update_post( $nav_args );
	$nav_id = $nav_exist[0];
} else {
	$nav_id = wp_insert_post( $nav_args );
}

// --- a site tagline so the core/site-tagline specimen renders (only if empty —
// blogdescription is site-owner data, like the Site Logo).
if ( '' === trim( (string) get_option( 'blogdescription' ) ) ) {
	update_option( 'blogdescription', 'Twenty Twenty-Five 위의 Material Design 3 브릿지' );
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

WP_CLI::log( 'Theme VQA ready: ' . get_permalink( $vid ) . ' (cat=' . $cat_id . ', tag=' . $tag_id . ', nav=' . $nav_id . ', posts=' . implode( ',', $ids ) . ')' );
