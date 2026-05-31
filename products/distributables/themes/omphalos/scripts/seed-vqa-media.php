<?php
/**
 * Omphalos seed helper — render + persist the VQA Media page, server-side.
 *
 * Run via WP-CLI from seed.ps1:
 *   wp eval-file scripts/seed-vqa-media.php <imageId> <audioId> <videoId> <vttKoId>
 *
 * patterns/vqa-media.php is a seed-bound template (Inserter:false) whose
 * __*_ID__ / __*_URL__ / __*_PERMALINK__ placeholders must be replaced with this
 * install's real attachment IDs + URLs. Doing the include + substitution + page
 * write entirely in PHP keeps the pattern body — which contains Korean text and
 * em dashes, including inside the wp:video `tracks` JSON attribute — off the
 * PowerShell/console text boundary. Capturing that body through a native
 * command's stdout is code-page dependent and mojibakes multibyte characters
 * (which, in the video JSON, eats a quote and invalidates the block). PHP never
 * crosses that boundary, so this path is encoding-safe by construction.
 *
 * Positional args ($args):
 *   0 image attachment ID  (also reused as the cover / 2nd gallery image)
 *   1 audio (.ogg) attachment ID
 *   2 video (.webm) attachment ID
 *   3 Korean WebVTT attachment ID
 *
 * @package Omphalos
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return; // Only meaningful under `wp eval-file`.
}

list( $image, $audio, $video, $vtt_ko ) = array_map( 'intval', array_pad( $args, 4, 0 ) );

if ( ! $image || ! $audio || ! $video || ! $vtt_ko ) {
	WP_CLI::error( 'seed-vqa-media: missing attachment IDs (need image audio video vttKo).' );
}

$cover = $image; // Reuse the WEBP image for cover + 2nd gallery tile (self-contained).

ob_start();
include get_stylesheet_directory() . '/patterns/vqa-media.php';
$raw = ob_get_clean();

$start = strpos( $raw, '<!-- wp:' );
if ( false === $start ) {
	WP_CLI::error( 'seed-vqa-media: pattern produced no block markup.' );
}
$body = substr( $raw, $start );

$body = strtr(
	$body,
	array(
		'__IMAGE_ID__'        => (string) $image,
		'__IMAGE_URL__'       => wp_get_attachment_url( $image ),
		'__COVER_ID__'        => (string) $cover,
		'__COVER_URL__'       => wp_get_attachment_url( $cover ),
		'__AUDIO_ID__'        => (string) $audio,
		'__AUDIO_URL__'       => wp_get_attachment_url( $audio ),
		'__AUDIO_PERMALINK__' => get_permalink( $audio ),
		'__VIDEO_ID__'        => (string) $video,
		'__VIDEO_URL__'       => wp_get_attachment_url( $video ),
		'__VTT_KO_URL__'      => wp_get_attachment_url( $vtt_ko ),
	)
);

$existing = get_page_by_path( 'vqa-media', OBJECT, 'page' );
$postarr  = array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'VQA Media',
	'post_name'    => 'vqa-media',
	'post_content' => $body,
);
if ( $existing ) {
	$postarr['ID'] = $existing->ID;
	$id            = wp_update_post( $postarr, true );
} else {
	$id = wp_insert_post( $postarr, true );
}
if ( is_wp_error( $id ) ) {
	WP_CLI::error( 'seed-vqa-media: page write failed — ' . $id->get_error_message() );
}

// Self-check: no leftover placeholders, block round-trip stable.
$stored    = get_post_field( 'post_content', $id );
$roundtrip = serialize_blocks( parse_blocks( $stored ) );
$leftover  = preg_match_all( '/__[A-Z_]+__/', $stored, $m ) ? array_values( array_unique( $m[0] ) ) : array();

WP_CLI::log( 'Media VQA page seeded at /vqa-media/ (ID ' . $id . ')' );
WP_CLI::log( '  leftover_placeholders=' . ( empty( $leftover ) ? 'none' : implode( ',', $leftover ) ) );
WP_CLI::log( '  roundtrip_stable=' . ( $stored === $roundtrip ? 'y' : 'N' ) );

if ( ! empty( $leftover ) || $stored !== $roundtrip ) {
	WP_CLI::error( 'seed-vqa-media: post-write validation failed.' );
}
