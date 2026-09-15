<?php
/**
 * Reaction picker's use of the Unicode catalogue (dev-only; dist-excluded).
 *
 * The catalogue itself moved to Axismundi Emoji, with its data assertions
 * (axismundi-emoji/tests/audit-emoji-unicode-catalogue.php). What stays here is this
 * plugin's side: the picker reads Emoji's source, keeps no copy of its own, and its markup
 * and script still consume the data the way the catalogue shapes it.
 *
 * Reaction normalization must remain independent of the catalogue; a peer may send a newer
 * valid grapheme before any site rebuilds its picker data.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_uc_results = array();

/** @param bool[] $results Results. */
function ax_uc_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$ax_uc_root = dirname( __DIR__ );

	// -- No copy here --------------------------------------------------------------------------

	ax_uc_assert( $ax_uc_results, 'Activities bundles no Unicode catalogue data of its own', array() === glob( $ax_uc_root . '/assets/unicode-rgi-*' ) );
	ax_uc_assert( $ax_uc_results, 'nor its builder or loader', ! file_exists( $ax_uc_root . '/scripts/build-unicode-emoji-catalogue.ps1' ) && ! file_exists( $ax_uc_root . '/includes/unicode-catalogue.php' ) );
	ax_uc_assert( $ax_uc_results, 'and serves no Unicode catalogue route; Emoji serves it under emoji/unicode', ! function_exists( 'axismundi_act_rest_unicode_catalogue' ) );

	$ax_uc_reactions = (string) file_get_contents( $ax_uc_root . '/includes/reactions.php' );
	ax_uc_assert( $ax_uc_results, 'the reaction normalizer does not consult any catalogue', ! str_contains( $ax_uc_reactions, 'unicode_catalogue' ) && ! str_contains( $ax_uc_reactions, 'emoji_unicode_picker_source' ) );

	// -- Reading Emoji's source ----------------------------------------------------------------

	$ax_uc_blocks = (string) file_get_contents( $ax_uc_root . '/includes/reaction-blocks.php' );
	ax_uc_assert( $ax_uc_results, 'the picker source is guarded, so the picker survives Emoji being inactive', str_contains( $ax_uc_blocks, "function_exists( 'axismundi_emoji_unicode_picker_source' )" ) );

	/*
	 * Every Emoji constant the reaction code reads must be behind defined(). One unguarded
	 * AXISMUNDI_EMOJI_CATALOGUE_MAX_PER_PAGE was a fatal error on any page with a reaction
	 * block while Emoji was inactive (reproduced with --skip-plugins=axismundi-emoji).
	 */
	$ax_uc_unguarded = array();
	foreach ( array( 'reaction-blocks.php', 'reactions.php', 'reaction-summary.php', 'repository.php' ) as $ax_uc_file ) {
		$ax_uc_code = (string) file_get_contents( $ax_uc_root . '/includes/' . $ax_uc_file );
		preg_match_all( '/\bAXISMUNDI_EMOJI_[A-Z0-9_]+\b/', $ax_uc_code, $ax_uc_constants );
		foreach ( array_unique( $ax_uc_constants[0] ) as $ax_uc_constant ) {
			if ( ! str_contains( $ax_uc_code, "defined( '" . $ax_uc_constant . "' )" ) ) {
				$ax_uc_unguarded[] = $ax_uc_file . ': ' . $ax_uc_constant;
			}
		}
	}
	ax_uc_assert( $ax_uc_results, 'every Emoji constant the reaction code reads is behind defined()' . ( $ax_uc_unguarded ? ' (unguarded: ' . implode( ', ', $ax_uc_unguarded ) . ')' : '' ), array() === $ax_uc_unguarded );
	ax_uc_assert( $ax_uc_results, 'with no catalogue endpoint the picker reports an empty catalogue instead of fetching the page', str_contains( $ax_uc_script ?? (string) file_get_contents( $ax_uc_root . '/assets/reactions.js' ), 'if ( ! state.catalogueEndpoint ) {' ) );

	$ax_uc_source = axismundi_act_unicode_picker_source();
	if ( function_exists( 'axismundi_emoji_unicode_picker_source' ) ) {
		ax_uc_assert( $ax_uc_results, 'with Emoji active, the picker reads Emoji\'s index', str_contains( $ax_uc_source['index_url'], '/axismundi-emoji/assets/unicode/catalogue/' ) );
		ax_uc_assert( $ax_uc_results, 'and one file per browseable group, unchanged in shape', 9 === count( $ax_uc_source['groups'] ) && isset( $ax_uc_source['groups']['Flags'] ) );
	}

	// -- The picker's consumption contract, unchanged by the move -------------------------------

	ax_uc_assert( $ax_uc_results, 'Unicode picker tiles begin as grapheme text, leaving image replacement to WordPress Core fallback', str_contains( $ax_uc_blocks, '<span class="axismundi-reaction-button__glyph" data-wp-text="context.item.glyph"></span>' ) && ! str_contains( $ax_uc_blocks, 's.w.org/images/core/emoji' ) );
	$ax_uc_script = (string) file_get_contents( $ax_uc_root . '/assets/reactions.js' );
	ax_uc_assert( $ax_uc_results, 'opening Custom consumes the public REST items array, not the search helper\'s private envelope', str_contains( $ax_uc_script, 'const items = yield response.json();' ) && str_contains( $ax_uc_script, 'state.catalogue = Array.isArray( items ) ? items : [];' ) );
	ax_uc_assert( $ax_uc_results, 'closed categories contribute no tile items and Unicode browse fetches one group at a time', str_contains( $ax_uc_script, 'items: state.expandedSections.includes( id ) ? entries : []' ) && str_contains( $ax_uc_script, 'loadUnicodeGroup( section.slice( 4 ) )' ) );
	ax_uc_assert( $ax_uc_results, 'with no index source the search path returns before fetching, so an empty source is safe', str_contains( $ax_uc_script, 'if ( state.unicodeIndexLoaded || ! state.unicodeIndexSource ) {' ) );
} catch ( Throwable $ax_uc_error ) {
	ax_uc_assert( $ax_uc_results, 'the Unicode picker suite ran to completion: ' . $ax_uc_error->getMessage(), false );
}

$ax_uc_failures = count( array_filter( $ax_uc_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_uc_results ), $ax_uc_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_uc_failures > 0 ? 1 : 0 );
}
exit( $ax_uc_failures > 0 ? 1 : 0 );
