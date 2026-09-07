<?php
/**
 * Phase 3b — relation provider + incremental-hook regression (dev-only; dist-excluded).
 *
 * Self-contained; `finally` cleanup; exit 0/1. Locks: role/predicate mapping, gallery
 * dedup (modern inner wins, legacy ids fallback), URL-only exclusion, gallery/playlist
 * shortcodes, save re-index removing stale rows, featured add/change/delete, a
 * provider WP_Error preserving existing rows, trash clearing, attachment delete
 * removing both sides, and the integration provider filter.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_results = array();

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_prov_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/** Build a block delimiter string. */
function ax_blk( string $name, array $attrs = array(), string $inner = '' ) : string {
	$json = $attrs ? ' ' . wp_json_encode( $attrs ) : '';
	return "<!-- wp:{$name}{$json} -->{$inner}<!-- /wp:{$name} -->\n";
}

/** role => object_attachment_id map of a subject's active relations. */
function ax_prov_map( int $post_id ) : array {
	$map = array();
	foreach ( axismundi_media_relations_for_subject( $post_id ) as $r ) {
		$map[ $r['role'] ][] = array( (int) $r['object_attachment_id'], $r['predicate'], (int) $r['occurrence_count'] );
	}
	return $map;
}

// Integration provider (also the WP_Error test): a filter-registered "flaky" provider.
$GLOBALS['ax_flaky_mode'] = 'ok';
$GLOBALS['ax_flaky_att']  = 0;
add_filter(
	'axismundi_media_relation_providers',
	function ( $providers ) {
		$providers['flaky'] = function ( $post ) {
			if ( 'error' === $GLOBALS['ax_flaky_mode'] ) {
				return new WP_Error( 'ax_flaky', 'simulated collection failure' );
			}
			$ref = axismundi_media_relation_ref( (int) $GLOBALS['ax_flaky_att'], 'as:attachment', 'content', 'flaky' );
			return $ref ? array( $ref ) : array();
		};
		return $providers;
	}
);

$ax_created = array( 'posts' => array(), 'atts' => array() );

try {
	axismundi_media_relations_install();

	$a = array();
	for ( $i = 1; $i <= 8; $i++ ) {
		$a[ $i ] = (int) wp_insert_attachment( array( 'post_title' => "ax-prov a{$i}", 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg' ) );
	}
	$ax_created['atts'] = array_values( $a );
	$GLOBALS['ax_flaky_att'] = $a[8];

	// --- Role/predicate mapping + URL-only exclusion ---------------------------
	$content = ax_blk( 'image', array( 'id' => $a[2] ) )
		. ax_blk( 'image', array(), '<figure class="wp-block-image"><img src="http://x/y.jpg"/></figure>' ) // no id -> excluded
		. ax_blk( 'cover', array( 'id' => $a[3] ) )
		. ax_blk( 'media-text', array( 'mediaId' => $a[4] ) )
		. ax_blk( 'file', array( 'id' => $a[5] ) )
		. ax_blk( 'audio', array( 'id' => $a[6] ) )
		. ax_blk( 'video', array( 'id' => $a[7] ) );
	$pub = (int) wp_insert_post( array( 'post_title' => 'ax-prov roles', 'post_status' => 'publish', 'post_type' => 'post', 'post_content' => $content ) );
	$ax_created['posts'][] = $pub;
	// update_post_meta (not set_post_thumbnail) — bare fixture attachments have no
	// real image file, which set_post_thumbnail requires; the featured provider only
	// reads _thumbnail_id, and this still fires the meta hook.
	update_post_meta( $pub, '_thumbnail_id', $a[1] ); // featured

	$map = ax_prov_map( $pub );
	ax_prov_assert( $ax_results, 'featured is as:image/featured', isset( $map['featured'] ) && array( $a[1], 'as:image', 1 ) === $map['featured'][0] );
	ax_prov_assert( $ax_results, 'content core/image is as:attachment', isset( $map['content'] ) && $a[2] === $map['content'][0][0] && 'as:attachment' === $map['content'][0][1] );
	ax_prov_assert( $ax_results, 'cover/media_text/file/audio/video roles mapped', isset( $map['cover'], $map['media_text'], $map['file'], $map['audio'], $map['video'] ) );
	$all_ids = array();
	foreach ( $map as $rows ) {
		foreach ( $rows as $row ) {
			$all_ids[] = $row[0];
		}
	}
	ax_prov_assert( $ax_results, 'URL-only image (no id) not indexed', ! in_array( 0, $all_ids, true ) );

	// --- Gallery dedup: modern inner wins, legacy ids ignored when inner present -
	$gallery = ax_blk( 'gallery', array( 'ids' => array( $a[4] ) ), ax_blk( 'image', array( 'id' => $a[2] ) ) . ax_blk( 'image', array( 'id' => $a[3] ) ) );
	$gpost   = (int) wp_insert_post( array( 'post_title' => 'ax-prov gallery', 'post_status' => 'publish', 'post_type' => 'post', 'post_content' => $gallery ) );
	$ax_created['posts'][] = $gpost;
	$gmap = ax_prov_map( $gpost );
	$gids = isset( $gmap['gallery'] ) ? array_column( $gmap['gallery'], 0 ) : array();
	sort( $gids );
	ax_prov_assert( $ax_results, 'modern gallery uses inner images, legacy ids ignored', array( $a[2], $a[3] ) === $gids );

	// legacy gallery: no inner blocks -> ids fallback
	$legacy = ax_blk( 'gallery', array( 'ids' => array( $a[5], $a[6] ) ) );
	$lpost  = (int) wp_insert_post( array( 'post_title' => 'ax-prov legacy gallery', 'post_status' => 'publish', 'post_type' => 'post', 'post_content' => $legacy ) );
	$ax_created['posts'][] = $lpost;
	$lids = array_column( ax_prov_map( $lpost )['gallery'] ?? array(), 0 );
	sort( $lids );
	ax_prov_assert( $ax_results, 'legacy gallery ids used when no inner images', array( $a[5], $a[6] ) === $lids );

	// --- Classic shortcodes + audio URL exclusion ------------------------------
	$classic = '[gallery ids="' . $a[2] . ',' . $a[3] . '"] [audio src="http://x/s.mp3"]';
	$cpost   = (int) wp_insert_post( array( 'post_title' => 'ax-prov shortcode', 'post_status' => 'publish', 'post_type' => 'post', 'post_content' => $classic ) );
	$ax_created['posts'][] = $cpost;
	$sids = array_column( ax_prov_map( $cpost )['gallery'] ?? array(), 0 );
	sort( $sids );
	ax_prov_assert( $ax_results, 'gallery shortcode ids indexed; audio URL ignored', array( $a[2], $a[3] ) === $sids );

	// --- Save re-index removes stale rows --------------------------------------
	wp_update_post( array( 'ID' => $cpost, 'post_content' => '[gallery ids="' . $a[7] . '"]' ) );
	$sids2 = array_column( ax_prov_map( $cpost )['gallery'] ?? array(), 0 );
	ax_prov_assert( $ax_results, 'save re-index removes stale, keeps new', array( $a[7] ) === $sids2 );

	// --- Featured change + delete via thumbnail meta ---------------------------
	update_post_meta( $pub, '_thumbnail_id', $a[8] );
	$feat = ax_prov_map( $pub )['featured'][0][0] ?? 0;
	ax_prov_assert( $ax_results, 'featured change reindexes to new thumbnail', $a[8] === $feat );
	delete_post_meta( $pub, '_thumbnail_id' );
	ax_prov_assert( $ax_results, 'featured delete clears featured relation', ! isset( ax_prov_map( $pub )['featured'] ) );

	// --- Provider WP_Error preserves existing rows -----------------------------
	axismundi_media_index_post( $pub ); // flaky ok -> writes a flaky row for a[8]
	$has_flaky = count( axismundi_media_relations_for_subject( $pub, 'flaky' ) );
	$GLOBALS['ax_flaky_mode'] = 'error';
	axismundi_media_index_post( $pub ); // flaky errors -> must NOT clear its rows
	$still_flaky = count( axismundi_media_relations_for_subject( $pub, 'flaky' ) );
	ax_prov_assert( $ax_results, 'integration provider filter registered + wrote a row', 1 === $has_flaky );
	ax_prov_assert( $ax_results, 'provider WP_Error preserves existing rows (not cleared)', 1 === $still_flaky );
	$GLOBALS['ax_flaky_mode'] = 'ok';

	// --- Trash clears ----------------------------------------------------------
	wp_update_post( array( 'ID' => $lpost, 'post_status' => 'trash' ) );
	ax_prov_assert( $ax_results, 'trash clears the subject relations', 0 === count( axismundi_media_relations_for_subject( $lpost ) ) );

	// --- Attachment delete removes subject + object rows -----------------------
	$obj_before = count( axismundi_media_relations_used_in( $a[7], 1 ) ); // a7 used by cpost gallery
	wp_delete_attachment( $a[7], true ); // fires before_delete_post
	$obj_after = count( axismundi_media_relations_used_in( $a[7], 1 ) );
	ax_prov_assert( $ax_results, 'attachment delete removes object-side rows', $obj_before >= 1 && 0 === $obj_after );
	$ax_created['atts'] = array_values( array_diff( $ax_created['atts'], array( $a[7] ) ) );

} finally {
	foreach ( $ax_created['posts'] as $ax_p ) {
		if ( $ax_p ) {
			axismundi_media_relations_delete_subject( (int) $ax_p );
			wp_delete_post( (int) $ax_p, true );
		}
	}
	foreach ( $ax_created['atts'] as $ax_a ) {
		if ( $ax_a ) {
			wp_delete_attachment( (int) $ax_a, true );
		}
	}
}

$ax_fail = 0;
foreach ( $ax_results as $ax_r ) {
	if ( ! $ax_r ) {
		++$ax_fail;
	}
}
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
printf( "\n== %d checks, %d failed ==\n", count( $ax_results ), $ax_fail );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_fail > 0 ? 1 : 0 );
}
exit( $ax_fail > 0 ? 1 : 0 );
