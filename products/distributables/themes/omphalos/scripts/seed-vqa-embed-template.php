<?php
/**
 * Seed the Embed Template VQA page — observe the /embed/ Object Card (the card our
 * OWN post/page renders as when embedded elsewhere) across the variants that matter
 * for the article card: post/page × featured-image shape.
 *
 * It SELF-EMBEDS demo content (a core/embed block pointing at our own permalink), so
 * each specimen renders the real wp-embedded-content iframe → /embed/ card, exactly
 * as a remote WP site would show it. post/page share ONE article card (the unified
 * embed-content.php); only the meta differs by type — which is what we observe here.
 * 404 / error surface is intentionally out of this first VQA. Attachment
 * (image/video/audio) variants are a later phase.
 *
 * Run via `wp eval-file` (wired into scripts/seed.ps1). Idempotent: update-or-create
 * by deterministic slug; re-running never duplicates. Korean stays inside this PHP.
 *
 * @package Omphalos
 */

// --- a wide (>=1.75) image so the RECTANGULAR featured-image branch is exercised.
function omphalos_et_wide_image_id() {
	$found = get_posts( array( 'post_type' => 'attachment', 'name' => 'et-wide-rect', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $found ) {
		return $found[0];
	}
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return 0; // GD unavailable — rectangular specimen will fall back to no image.
	}
	$up   = wp_upload_dir();
	$file = trailingslashit( $up['path'] ) . 'et-wide-rect.png';
	$img  = imagecreatetruecolor( 1600, 600 );
	for ( $x = 0; $x < 1600; $x++ ) {
		$c = imagecolorallocate( $img, 40 + (int) ( $x / 16 ), 70, 170 - (int) ( $x / 16 ) );
		imageline( $img, $x, 0, $x, 600, $c );
	}
	imagepng( $img, $file );
	imagedestroy( $img );
	$type = wp_check_filetype( $file );
	$id   = wp_insert_attachment( array(
		'post_mime_type' => $type['type'],
		'post_title'     => 'ET wide rectangular',
		'post_name'      => 'et-wide-rect',
		'post_status'    => 'inherit',
	), $file );
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
	return $id;
}

$mogu = get_posts( array( 'post_type' => 'attachment', 'name' => 'image-placeholder-mogu-1024', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids' ) );
$mogu = $mogu ? $mogu[0] : 0;            // 1024×768 → square branch
$wide = omphalos_et_wide_image_id();     // 1600×600 → rectangular branch

$summary = '<!-- wp:paragraph --><p>임베드 템플릿 검증용 더미. /embed/ 카드에는 본문 전체가 아니라 이 발췌(excerpt)만 들어갑니다 — link preview / object summary 표면이기 때문입니다. 제목과 사이트 푸터는 원문으로 이동합니다.</p><!-- /wp:paragraph -->';

$items = array(
	array( 'slug' => 'et-post-square', 'type' => 'post', 'title' => 'Post — square featured image',      'thumb' => $mogu ),
	array( 'slug' => 'et-post-rect',   'type' => 'post', 'title' => 'Post — rectangular featured image', 'thumb' => $wide ),
	array( 'slug' => 'et-post-plain',  'type' => 'post', 'title' => 'Post — no featured image',          'thumb' => 0 ),
	array( 'slug' => 'et-page-plain',  'type' => 'page', 'title' => 'Page — no featured image',          'thumb' => 0 ),
	array( 'slug' => 'et-page-image',  'type' => 'page', 'title' => 'Page — featured image',             'thumb' => $mogu ),
);

foreach ( $items as &$it ) {
	$existing = get_posts( array( 'post_type' => $it['type'], 'name' => $it['slug'], 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
	$args     = array(
		'post_type'    => $it['type'],
		'post_status'  => 'publish',
		'post_title'   => $it['title'],
		'post_name'    => $it['slug'],
		'post_content' => $summary,
	);
	if ( $existing ) {
		$args['ID'] = $existing[0];
		$id         = wp_update_post( $args );
	} else {
		$id = wp_insert_post( $args );
	}
	if ( $it['thumb'] ) {
		set_post_thumbnail( $id, $it['thumb'] );
	} else {
		delete_post_thumbnail( $id );
	}
	$it['id']  = $id;
	$it['url'] = get_permalink( $id );
}
unset( $it );

// --- build the VQA page: a self-embed (core/embed) of each specimen permalink.
$body  = '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Embed Template VQA</h1><!-- /wp:heading -->';
$body .= '<!-- wp:paragraph --><p>남이 내 글/페이지를 임베드했을 때의 <code>/embed/</code> Object Card(self-embed로 렌더). post/page는 통일된 article card이고, 차이는 메타 정책에서만 관찰합니다.</p><!-- /wp:paragraph -->';
foreach ( $items as $it ) {
	$u     = esc_url( $it['url'] );
	$body .= '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $it['title'] ) . '</h2><!-- /wp:heading -->';
	$body .= '<!-- wp:embed {"url":"' . $u . '","type":"wp-embed","providerNameSlug":"omphalos"} -->'
		. '<figure class="wp-block-embed is-type-wp-embed is-provider-omphalos wp-block-embed-omphalos"><div class="wp-block-embed__wrapper">' . "\n"
		. $u . "\n"
		. '</div></figure><!-- /wp:embed -->';
}

$vexist = get_posts( array( 'post_type' => 'page', 'name' => 'vqa-embed-template', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
$vargs  = array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'VQA Embed Template',
	'post_name'    => 'vqa-embed-template',
	'post_content' => $body,
);
if ( $vexist ) {
	$vargs['ID'] = $vexist[0];
	wp_update_post( $vargs );
	$vid = $vexist[0];
} else {
	$vid = wp_insert_post( $vargs );
}

WP_CLI::log( 'Embed Template VQA ready: ' . get_permalink( $vid ) . ' (square thumb=' . $mogu . ', wide thumb=' . $wide . ')' );
