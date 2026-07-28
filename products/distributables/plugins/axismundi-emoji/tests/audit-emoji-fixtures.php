<?php
/**
 * E0 fixture and contract harness (dev-only; dist-excluded).
 *
 * There is no implementation to test yet. What this pins is the *evidence* E1 will be
 * written against: that the captured payloads still have the shape the architecture
 * document claims, and that the generated APNG fixture really does reproduce the
 * behaviour of the 2 MiB Misskey original it stands in for.
 *
 * That last point is the reason this file exists at E0. The interesting facts about
 * APNG here are negative ones — Imagick cannot decode it, GD silently drops the
 * animation — and a fixture that failed to reproduce them would let E1 be written
 * against an APNG that behaves like an ordinary PNG, which is precisely the mistake
 * the Actors asset cache already makes.
 *
 * No network. The real Misskey emoji is deliberately not committed: it is 1.92 MiB and
 * its own licence says it may not be used off Misskey.io. Point
 * AXISMUNDI_EMOJI_REFERENCE_APNG at a local copy to run the integration checks; without
 * it they skip rather than fail.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

// The plugin need not be activated for this to run: at E0 it defines constants and
// nothing else, and the harness is checking those definitions against captured
// evidence rather than any runtime behaviour.
require_once dirname( __DIR__ ) . '/axismundi-emoji.php';

$ax_emo_results = array();
$ax_emo_skipped = 0;
$ax_emo_dir     = __DIR__ . '/fixtures/';

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_emo_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/**
 * @param string $label Reason.
 * @return void
 */
function ax_emo_skip( string $label ) : void {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[SKIP] %s\n", $label );
}

/**
 * @param string $dir  Fixture directory.
 * @param string $name File name.
 * @return array<mixed>|null
 */
function ax_emo_fixture( string $dir, string $name ) : ?array {
	$path = $dir . $name;
	if ( ! is_readable( $path ) ) {
		return null;
	}
	$data = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
	return is_array( $data ) ? $data : null;
}

/*
 * ---------------------------------------------------------------------------
 * Wire contract (docs §2, §3)
 * ---------------------------------------------------------------------------
 */

$ax_emo_tags = ax_emo_fixture( $ax_emo_dir, 'actor-emoji-tags.json' );
ax_emo_assert( $ax_emo_results, 'captured Actor Emoji tags are present', is_array( $ax_emo_tags ) && count( $ax_emo_tags ) >= 2 );

if ( is_array( $ax_emo_tags ) ) {
	$ax_emo_shapes = true;
	$ax_emo_split  = 0;
	foreach ( $ax_emo_tags as $ax_emo_entry ) {
		$ax_emo_tag = (array) ( $ax_emo_entry['tag'] ?? array() );
		$ax_emo_shapes = $ax_emo_shapes
			&& 'Emoji' === ( $ax_emo_tag['type'] ?? '' )
			&& is_string( $ax_emo_tag['name'] ?? null )
			&& str_starts_with( (string) $ax_emo_tag['name'], ':' )
			&& is_string( $ax_emo_tag['icon']['url'] ?? null );

		// §2: the CDN host is not the authority. If this ever stops being true for
		// every sample, the identity rule needs re-examining, not the fixture.
		$ax_emo_id_host   = (string) wp_parse_url( (string) ( $ax_emo_tag['id'] ?? '' ), PHP_URL_HOST );
		$ax_emo_icon_host = (string) wp_parse_url( (string) ( $ax_emo_tag['icon']['url'] ?? '' ), PHP_URL_HOST );
		if ( '' !== $ax_emo_id_host && $ax_emo_id_host !== $ax_emo_icon_host ) {
			++$ax_emo_split;
		}
	}
	ax_emo_assert( $ax_emo_results, 'every captured tag carries type, colon-wrapped name, and icon.url', $ax_emo_shapes );
	ax_emo_assert( $ax_emo_results, 'the icon host differs from the declaring authority in every sample, so it cannot be identity', count( $ax_emo_tags ) === $ax_emo_split );
}

$ax_emo_doc = ax_emo_fixture( $ax_emo_dir, 'emoji-document-misskey-restricted.json' );
ax_emo_assert(
	$ax_emo_results,
	'an emoji id dereferences to a standalone Emoji document, so metadata needs no vendor API',
	is_array( $ax_emo_doc ) && 'Emoji' === ( $ax_emo_doc['type'] ?? '' ) && is_string( $ax_emo_doc['updated'] ?? null )
);
ax_emo_assert(
	$ax_emo_results,
	'a restrictive licence arrives over ActivityPub, not only through a REST catalogue',
	is_array( $ax_emo_doc ) && str_contains( strtolower( (string) ( $ax_emo_doc['_misskey_license']['freeText'] ?? '' ) ), 'prohibited' )
);
ax_emo_assert(
	$ax_emo_results,
	'icon.mediaType can declare image/apng even though the CDN serves octet-stream',
	is_array( $ax_emo_doc ) && 'image/apng' === ( $ax_emo_doc['icon']['mediaType'] ?? '' )
);

/*
 * ---------------------------------------------------------------------------
 * Licence classification (docs §3)
 * ---------------------------------------------------------------------------
 */

$ax_emo_licenses = ax_emo_fixture( $ax_emo_dir, 'license-sample-misskey.json' );
if ( is_array( $ax_emo_licenses ) ) {
	$ax_emo_unknown    = 0;
	$ax_emo_restricted = 0;
	$ax_emo_allowed    = 0;
	$ax_emo_pd_nsfw    = false;
	foreach ( $ax_emo_licenses as $ax_emo_row ) {
		$ax_emo_text = strtolower( (string) ( $ax_emo_row['license'] ?? '' ) );
		if ( '' === $ax_emo_text ) {
			++$ax_emo_unknown;
		} elseif ( str_contains( $ax_emo_text, 'prohibited' ) || str_contains( $ax_emo_text, 'exclusive to' ) ) {
			++$ax_emo_restricted;
		} else {
			++$ax_emo_allowed;
		}
		if ( ! empty( $ax_emo_row['isSensitive'] ) && str_contains( $ax_emo_text, 'public domain' ) ) {
			$ax_emo_pd_nsfw = true;
		}
	}
	ax_emo_assert( $ax_emo_results, 'all three licence states occur in one real catalogue, so two states cannot express it', $ax_emo_unknown > 0 && $ax_emo_allowed > 0 && $ax_emo_restricted > 0 );
	// The argument for a third state is not that `unknown` is the largest bucket — it is
	// not — but that it outnumbers `restricted`. Folding it into `restricted` would
	// withhold more emoji than are actually restricted; folding it into `allowed` would
	// re-use assets whose terms nobody stated.
	ax_emo_assert( $ax_emo_results, 'unlicensed emoji outnumber explicitly restricted ones, so folding unknown either way misfiles more than it protects', $ax_emo_unknown > $ax_emo_restricted );
	ax_emo_assert( $ax_emo_results, 'a Public Domain emoji is also flagged sensitive, so licence and NSFW are independent axes', $ax_emo_pd_nsfw );
}

$ax_emo_reactions = ax_emo_fixture( $ax_emo_dir, 'reaction-types-misskey.json' );
ax_emo_assert(
	$ax_emo_results,
	'qualified :name@authority: shortcodes occur alongside bare Unicode reactions',
	is_array( $ax_emo_reactions )
		&& (bool) array_filter( $ax_emo_reactions, static fn( $r ) : bool => 1 === preg_match( '/^:[^:]+@[^:]+:$/', (string) $r ) )
		&& (bool) array_filter( $ax_emo_reactions, static fn( $r ) : bool => ! str_starts_with( (string) $r, ':' ) )
);

/*
 * ---------------------------------------------------------------------------
 * Shortcode grammar (docs §2)
 * ---------------------------------------------------------------------------
 */

$ax_emo_grammar = array(
	':blobcat:'            => array( 'blobcat', '' ),
	':09_bird@hoto.moe:'   => array( '09_bird', 'hoto.moe' ),
	':ai_acid_misskeyio:'  => array( 'ai_acid_misskeyio', '' ),
	':x:'                  => null,   // one character
	':has space:'          => null,
	':bad-hyphen:'         => null,
);
$ax_emo_grammar_ok = true;
foreach ( $ax_emo_grammar as $ax_emo_input => $ax_emo_want ) {
	$ax_emo_hit = 1 === preg_match( AXISMUNDI_EMOJI_SHORTCODE_PATTERN, $ax_emo_input, $ax_emo_m );
	if ( null === $ax_emo_want ) {
		$ax_emo_grammar_ok = $ax_emo_grammar_ok && ! $ax_emo_hit;
		continue;
	}
	$ax_emo_grammar_ok = $ax_emo_grammar_ok
		&& $ax_emo_hit
		&& $ax_emo_want[0] === ( $ax_emo_m[1] ?? '' )
		&& $ax_emo_want[1] === ( $ax_emo_m[2] ?? '' );
}
ax_emo_assert( $ax_emo_results, 'the shortcode grammar accepts bare and authority-qualified names and rejects malformed ones', $ax_emo_grammar_ok );

/*
 * ---------------------------------------------------------------------------
 * WordPress collision surface (docs §6)
 * ---------------------------------------------------------------------------
 */

smilies_init();
global $wpsmiliestrans;
$ax_emo_colon = array_filter(
	array_keys( (array) $wpsmiliestrans ),
	static fn( string $t ) : bool => 1 === preg_match( '/^:[a-zA-Z0-9_]{2,}:$/', $t )
);
ax_emo_assert( $ax_emo_results, 'WordPress core claims shortcodes in the same :word: form, so undeclared names must be left alone', count( $ax_emo_colon ) > 0 );
ax_emo_assert(
	$ax_emo_results,
	'an Actor display name is not a convert_smilies surface, so that collision is confined to content filters',
	! has_filter( 'the_title', 'convert_smilies' )
);

/*
 * ---------------------------------------------------------------------------
 * Outbound conformance (docs §2, §8) — our own emoji must satisfy FEP-9098's
 * Compatibility section even though ingestion deliberately does not enforce it.
 * ---------------------------------------------------------------------------
 */

$ax_emo_own = axismundi_emoji_bundled_path();
if ( '' === $ax_emo_own ) {
	ax_emo_assert( $ax_emo_results, 'the bundled :axismundi: emoji ships with the plugin, so nothing depends on a third party\'s restricted asset', false );
} else {
	$ax_emo_own_bytes = (string) file_get_contents( $ax_emo_own ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- bundled asset.
	$ax_emo_own_size  = (array) getimagesize( $ax_emo_own );
	ax_emo_assert( $ax_emo_results, 'the bundled emoji is square, as FEP-9098 advises for clients that mis-render otherwise', ( $ax_emo_own_size[0] ?? 0 ) === ( $ax_emo_own_size[1] ?? -1 ) );
	ax_emo_assert( $ax_emo_results, 'the bundled emoji is within the 256 KB the spec recommends for interoperability', strlen( $ax_emo_own_bytes ) <= AXISMUNDI_EMOJI_OUTBOUND_MAX_BYTES );
	ax_emo_assert( $ax_emo_results, 'the bundled emoji uses one of the three media types the spec names', in_array( (string) ( $ax_emo_own_size['mime'] ?? '' ), AXISMUNDI_EMOJI_OUTBOUND_MEDIA_TYPES, true ) );
	/*
	 * Emoji sit on arbitrary backgrounds, so a flattened alpha channel is a defect the
	 * PNG source would never have revealed.
	 *
	 * Mind the scale: GD's alpha is 7-bit and **inverted** relative to the 8-bit alpha
	 * of PNG or CSS. Here `0` means fully opaque and `127` means fully transparent, so
	 * asserting `127` at a corner is asserting that the corner is see-through. Reading
	 * this as "alpha 127 = nearly solid" is the natural mistake and gives exactly the
	 * wrong verdict.
	 */
	$ax_emo_alpha = false;
	if ( function_exists( 'imagecreatefromwebp' ) ) {
		$ax_emo_im = @imagecreatefromwebp( $ax_emo_own ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- absence is the assertion.
		if ( false !== $ax_emo_im ) {
			$ax_emo_alpha = 127 === ( ( imagecolorat( $ax_emo_im, 0, 0 ) >> 24 ) & 0x7F );
		}
	}
	ax_emo_assert( $ax_emo_results, 'the bundled emoji keeps its transparency, since it is composited over unknown backgrounds', $ax_emo_alpha );
	ax_emo_assert( $ax_emo_results, 'its shortcode parses under the same grammar remote emoji are held to', 1 === preg_match( AXISMUNDI_EMOJI_SHORTCODE_PATTERN, AXISMUNDI_EMOJI_BUNDLED_SHORTCODE ) );
}

// The ingestion cap must sit above the recommendation, or real emoji are refused.
ax_emo_assert(
	$ax_emo_results,
	'the ingestion cap exceeds the publishing recommendation, so we accept what the network actually sends',
	AXISMUNDI_EMOJI_MAX_BYTES > AXISMUNDI_EMOJI_OUTBOUND_MAX_BYTES
);

/*
 * ---------------------------------------------------------------------------
 * APNG behaviour (docs §6) — the generated fixture must stand in for the real file
 * ---------------------------------------------------------------------------
 */

$ax_emo_apng = $ax_emo_dir . 'animated-2frame.apng';
if ( ! is_readable( $ax_emo_apng ) ) {
	ax_emo_assert( $ax_emo_results, 'the generated APNG fixture is present', false );
} else {
	$ax_emo_bytes = (string) file_get_contents( $ax_emo_apng ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
	$ax_emo_actl  = strpos( $ax_emo_bytes, 'acTL' );
	ax_emo_assert( $ax_emo_results, 'the fixture is a valid PNG container that WordPress types as image/png', 'image/png' === wp_get_image_mime( $ax_emo_apng ) );
	ax_emo_assert( $ax_emo_results, 'the fixture is genuinely animated (acTL, two frames, infinite loop)', false !== $ax_emo_actl && 2 === unpack( 'N', substr( $ax_emo_bytes, $ax_emo_actl + 4, 4 ) )[1] );
	ax_emo_assert( $ax_emo_results, 'the fixture stays small enough to commit', strlen( $ax_emo_bytes ) < 4096 );

	// The two negative facts E1 depends on.
	$ax_emo_imagick = wp_get_image_editor( $ax_emo_apng );
	ax_emo_assert(
		$ax_emo_results,
		'the default image editor cannot decode APNG, so E1 must not assume an editor is available',
		is_wp_error( $ax_emo_imagick ) || ! ( $ax_emo_imagick instanceof WP_Image_Editor_Imagick )
	);

	add_filter( 'wp_image_editors', static fn() : array => array( 'WP_Image_Editor_GD' ) );
	$ax_emo_gd = wp_get_image_editor( $ax_emo_apng );
	if ( is_wp_error( $ax_emo_gd ) ) {
		ax_emo_assert( $ax_emo_results, 'GD can open the APNG to produce a static rendition', false );
	} else {
		$ax_emo_static = $ax_emo_gd->save( wp_tempnam( 'ax-emoji-static' ) . '.png', 'image/png' );
		$ax_emo_out    = is_wp_error( $ax_emo_static ) ? '' : (string) file_get_contents( (string) $ax_emo_static['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local temp.
		ax_emo_assert( $ax_emo_results, 'GD produces a static rendition for prefers-reduced-motion', '' !== $ax_emo_out );
		ax_emo_assert( $ax_emo_results, 'that rendition has lost the animation, which is why originals must be kept verbatim', '' !== $ax_emo_out && false === strpos( $ax_emo_out, 'acTL' ) );
		if ( ! is_wp_error( $ax_emo_static ) ) {
			wp_delete_file( (string) $ax_emo_static['path'] );
		}
	}
	remove_all_filters( 'wp_image_editors' );
}

/*
 * ---------------------------------------------------------------------------
 * Optional integration against the untracked reference sample
 * ---------------------------------------------------------------------------
 */

$ax_emo_reference = defined( 'AXISMUNDI_EMOJI_REFERENCE_APNG' ) ? (string) AXISMUNDI_EMOJI_REFERENCE_APNG : (string) getenv( 'AXISMUNDI_EMOJI_REFERENCE_APNG' );
if ( '' === $ax_emo_reference || ! is_readable( $ax_emo_reference ) ) {
	ax_emo_skip( 'reference APNG integration — set AXISMUNDI_EMOJI_REFERENCE_APNG to a local copy (deliberately not committed: 1.92 MiB, licence forbids off-instance use)' );
	++$ax_emo_skipped;
} else {
	$ax_emo_real = (string) file_get_contents( $ax_emo_reference ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- operator-supplied path.
	ax_emo_assert( $ax_emo_results, 'the reference sample exceeds nothing but the per-file cap headroom we assumed', strlen( $ax_emo_real ) <= AXISMUNDI_EMOJI_MAX_BYTES );
	ax_emo_assert( $ax_emo_results, 'the reference sample behaves like the fixture: typed image/png and animated', 'image/png' === wp_get_image_mime( $ax_emo_reference ) && false !== strpos( $ax_emo_real, 'acTL' ) );
	$ax_emo_real_editor = wp_get_image_editor( $ax_emo_reference );
	ax_emo_assert( $ax_emo_results, 'the reference sample reproduces the default-editor decode failure', is_wp_error( $ax_emo_real_editor ) || ! ( $ax_emo_real_editor instanceof WP_Image_Editor_Imagick ) );
}

$ax_emo_failures = count( array_filter( $ax_emo_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed, %d skipped ==\n", count( $ax_emo_results ), $ax_emo_failures, $ax_emo_skipped );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_emo_failures > 0 ? 1 : 0 );
}
exit( $ax_emo_failures > 0 ? 1 : 0 );
