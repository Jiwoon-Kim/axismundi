<?php
/**
 * Phase 4b — license / rights resolver regression (dev-only; dist-excluded).
 *
 * Self-contained; `finally` cleanup; exit 0/1. Locks: the CC condition matrix for
 * every enum value, canonical-URL-wins for standard codes, clean-break default (a
 * dropped/legacy code resolves to all-rights-reserved), authored attribution wins,
 * and the display-only attribution fallback.
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
function ax_rights_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

$ax_created = array( 'atts' => array() );

try {
	$att = (int) wp_insert_attachment( array( 'post_title' => 'Photo', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg' ) );
	$ax_created['atts'] = array( $att );

	// Condition matrix: [attribution, commercial, derivatives, share_alike, known]
	$matrix = array(
		'pdm'                 => array( false, true, true, false, true ),
		'cc0'                 => array( false, true, true, false, true ),
		'cc-by'               => array( true, true, true, false, true ),
		'cc-by-sa'            => array( true, true, true, true, true ),
		'cc-by-nd'            => array( true, true, false, false, true ),
		'cc-by-nc'            => array( true, false, true, false, true ),
		'cc-by-nc-sa'         => array( true, false, true, true, true ),
		'cc-by-nc-nd'         => array( true, false, false, false, true ),
		'all-rights-reserved' => array( false, false, false, false, false ),
		'unknown'             => array( false, false, false, false, false ),
	);
	$matrix_ok = true;
	foreach ( $matrix as $code => $exp ) {
		$c = axismundi_media_license_conditions( $code );
		$got = array( $c['attribution'], $c['commercial'], $c['derivatives'], $c['share_alike'], $c['known'] );
		if ( $exp !== $got ) {
			$matrix_ok = false;
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
			printf( "  matrix mismatch: %s\n", $code );
		}
	}
	ax_rights_assert( $ax_results, 'CC condition matrix matches for all 10 codes', $matrix_ok );

	// Canonical URL wins for standard codes; user URL used only when no canonical.
	update_post_meta( $att, '_ax_media_license', 'cc-by-sa' );
	update_post_meta( $att, '_ax_media_license_url', 'https://example.com/mine' );
	$rec = axismundi_media_license_record( $att );
	ax_rights_assert( $ax_results, 'canonical CC URL wins over user URL for standard code', 'https://creativecommons.org/licenses/by-sa/4.0/' === $rec['url'] && 'CC BY-SA' === $rec['name'] );

	update_post_meta( $att, '_ax_media_license', 'all-rights-reserved' );
	$rec = axismundi_media_license_record( $att );
	ax_rights_assert( $ax_results, 'ARR uses the user URL (no canonical)', 'https://example.com/mine' === $rec['url'] && false === $rec['conditions']['known'] );

	// Clean break: a legacy/dropped code resolves to all-rights-reserved.
	update_post_meta( $att, '_ax_media_license', 'cc-by-4.0' );
	ax_rights_assert( $ax_results, 'legacy code (cc-by-4.0) resolves to all-rights-reserved', 'all-rights-reserved' === axismundi_media_license_code( $att ) );

	// Attribution: authored wins.
	update_post_meta( $att, '_ax_media_license', 'cc-by' );
	update_post_meta( $att, '_ax_media_attribution', 'Photo by Jane, CC BY' );
	ax_rights_assert( $ax_results, 'authored attribution wins', 'Photo by Jane, CC BY' === axismundi_media_attribution_text( $att ) );

	// Attribution fallback from title + creator + license.
	delete_post_meta( $att, '_ax_media_attribution' );
	update_post_meta( $att, '_ax_media_creator_name', 'Alice' );
	$fallback = axismundi_media_attribution_text( $att );
	ax_rights_assert( $ax_results, 'attribution fallback composes title + creator + license', false !== strpos( $fallback, 'Photo' ) && false !== strpos( $fallback, 'Alice' ) && false !== strpos( $fallback, 'CC BY' ) );

} finally {
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
