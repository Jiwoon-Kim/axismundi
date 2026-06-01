<?php
/**
 * Idempotent VQA demo posts — give core/latest-posts (and the feed-less lists)
 * enough real content for the list/grid VQA to be meaningful. Update-or-create by
 * deterministic slug, so re-running the seed never duplicates. Korean content
 * stays inside this PHP file (run via `wp eval-file`), never crossing the
 * PowerShell/console text boundary that mojibakes multibyte chars.
 *
 * Run from scripts/seed.ps1:
 *   npx wp-env run cli wp eval-file wp-content/themes/omphalos/scripts/seed-vqa-posts.php
 *
 * @package Omphalos
 */

$demo = array(
	array(
		'slug'    => 'vqa-demo-m3-bridge',
		'title'   => 'M3 디자인 시스템을 블록 테마에 브릿지하기',
		'content' => 'Omphalos는 Twenty Twenty-Five 위에 Material Design 3 디자인 언어를 얹는 호환 파일럿입니다. 코어 블록 출력을 먼저 관찰하고, 그 위에 토큰 기반 계약을 입혀 다크/라이트 양 스킴에서 일관되게 보이도록 합니다.',
	),
	array(
		'slug'    => 'vqa-demo-list-grid',
		'title'   => '콘텐츠 컬렉션의 list / grid 듀얼리티',
		'content' => 'Latest Posts와 RSS는 toolbar에서 list/grid를 전환합니다. List view는 밀도 중심(RSS=grouped feed, Latest Posts=teaser cards), Grid view는 filled card grid로 해석합니다. 카드는 elevated가 아니라 surface 채움입니다.',
	),
	array(
		'slug'    => 'vqa-demo-search-bar',
		'title'   => 'core/search를 in-content Search Bar 브릿지로',
		'content' => 'core/search는 앱바형 56dp Search Bar가 아니라 본문 안의 compact 48px 필드로 봅니다. button-inside는 elevation과 standard/text trailing을 가진 Search Bar 브릿지, 나머지 variant는 WP form/icon-button baseline으로 둡니다.',
	),
	array(
		'slug'    => 'vqa-demo-tokens',
		'title'   => '토큰 레이어로 다크 모드까지 한 번에',
		'content' => 'ref → sys.light/dark → comp 순서의 토큰 레이어 덕분에 한 번 계약하면 양 스킴이 자동으로 따라옵니다. 코어 블록이 자체 하드코딩 색을 가진 경우(예: Calendar)만 별도로 토큰화해 잡아줍니다.',
	),
);

$done = 0;
foreach ( $demo as $p ) {
	$existing = get_posts( array(
		'post_type'   => 'post',
		'name'        => $p['slug'],
		'post_status' => 'any',
		'numberposts' => 1,
		'fields'      => 'ids',
	) );
	$args = array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => $p['title'],
		'post_name'    => $p['slug'],
		'post_content' => '<!-- wp:paragraph --><p>' . $p['content'] . '</p><!-- /wp:paragraph -->',
	);
	if ( $existing ) {
		$args['ID'] = $existing[0];
		wp_update_post( $args );
	} else {
		wp_insert_post( $args );
	}
	$done++;
}
WP_CLI::log( 'VQA demo posts ensured: ' . $done );
