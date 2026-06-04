<?php
/**
 * Seed the Core Theme Archive VQA page.
 *
 * Idempotent. Ensures term descriptions exist for the Terms Query specimen and
 * creates /vqa-theme-archive/ as a live pattern reference. Korean stays inside
 * this PHP file (run via `wp eval-file`).
 *
 * @package Omphalos
 */

wp_clean_themes_cache();
wp_get_theme()->cache_delete();

$parent = get_posts(
	array(
		'post_type'   => 'page',
		'name'        => 'vqa-theme',
		'post_status' => 'any',
		'numberposts' => 1,
		'fields'      => 'ids',
	)
);
$parent_id = $parent ? (int) $parent[0] : 0;

$cat = term_exists( 'vqa-theme-topic', 'category' );
if ( ! $cat ) {
	$cat = wp_insert_term( 'VQA Theme Topic', 'category', array( 'slug' => 'vqa-theme-topic' ) );
}
$cat_id = is_array( $cat ) ? (int) $cat['term_id'] : (int) $cat;

$tag = term_exists( 'vqa-theme-tag', 'post_tag' );
if ( ! $tag ) {
	$tag = wp_insert_term( 'VQA Theme Tag', 'post_tag', array( 'slug' => 'vqa-theme-tag' ) );
}
$tag_id = is_array( $tag ) ? (int) $tag['term_id'] : (int) $tag;

if ( $cat_id ) {
	wp_update_term(
		$cat_id,
		'category',
		array(
			'description' => 'Theme block VQA용 카테고리입니다. Archive Title, Query Loop, Terms Query가 실제 term context에서 렌더되는지 확인합니다.',
		)
	);
}

if ( $tag_id ) {
	wp_update_term(
		$tag_id,
		'post_tag',
		array(
			'description' => 'Theme block VQA용 태그입니다. Term Name / Description / Count 블록의 token binding을 관찰합니다.',
		)
	);
}

$slug    = 'vqa-theme-archive';
$content = '<!-- wp:pattern {"slug":"omphalos/vqa-theme-archive"} /-->';
$page    = get_posts(
	array(
		'post_type'   => 'page',
		'name'        => $slug,
		'post_status' => 'any',
		'numberposts' => 1,
		'fields'      => 'ids',
	)
);

$args = array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'VQA Theme Archive',
	'post_name'    => $slug,
	'post_content' => $content,
	'post_parent'  => $parent_id,
);

if ( $page ) {
	$args['ID'] = (int) $page[0];
	$page_id    = wp_update_post( $args );
} else {
	$page_id = wp_insert_post( $args );
}

echo 'Archive VQA ready: ' . get_permalink( $page_id ) . " (page=$page_id, parent=$parent_id, cat=$cat_id, tag=$tag_id)\n";
