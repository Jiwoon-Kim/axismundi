<?php
/**
 * Emoji tag parsing and identity resolution (dev-only; dist-excluded).
 *
 * Every assertion here is driven by a captured payload rather than an invented one,
 * because the interesting failures in this area are all cases where the specification
 * and the network disagree: a CDN host that is not the authority, a qualified name
 * that belongs to a third instance, a licence member present but null.
 *
 * No network, no schema. Pure functions over fixtures.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-emoji.php';

$ax_emp_results = array();
$ax_emp_dir     = __DIR__ . '/fixtures/';

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_emp_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/**
 * @param string $dir  Fixture directory.
 * @param string $name File name.
 * @return array<mixed>
 */
function ax_emp_fixture( string $dir, string $name ) : array {
	$data = json_decode( (string) file_get_contents( $dir . $name ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
	return is_array( $data ) ? $data : array();
}

/* -------------------------------------------------------------------------- *
 * Shortcode grammar
 * -------------------------------------------------------------------------- */

$ax_emp_cases = array(
	':blobcat:'           => array( 'blobcat', '' ),
	'blobcat'             => array( 'blobcat', '' ),          // colons optional on input
	':09_bird@hoto.moe:'  => array( '09_bird', 'hoto.moe' ),
	':MixedCase:'         => array( 'mixedcase', '' ),        // key is lowercased
	':x:'                 => null,
	':has space:'         => null,
	':bad-hyphen:'        => null,
	''                    => null,
);
$ax_emp_ok = true;
foreach ( $ax_emp_cases as $ax_emp_in => $ax_emp_want ) {
	$ax_emp_got = axismundi_emoji_parse_shortcode( (string) $ax_emp_in );
	if ( null === $ax_emp_want ) {
		$ax_emp_ok = $ax_emp_ok && null === $ax_emp_got;
		continue;
	}
	$ax_emp_ok = $ax_emp_ok
		&& is_array( $ax_emp_got )
		&& $ax_emp_want[0] === $ax_emp_got['key']
		&& $ax_emp_want[1] === $ax_emp_got['authority'];
}
ax_emp_assert( $ax_emp_results, 'the parser normalizes bare, colon-wrapped, qualified, and mixed-case names, and refuses malformed ones', $ax_emp_ok );

/* -------------------------------------------------------------------------- *
 * Authority resolution against real captures
 * -------------------------------------------------------------------------- */

$ax_emp_tags = ax_emp_fixture( $ax_emp_dir, 'actor-emoji-tags.json' );
$ax_emp_auth_ok  = ! empty( $ax_emp_tags );
$ax_emp_never_cdn = true;
foreach ( $ax_emp_tags as $ax_emp_entry ) {
	$ax_emp_tag  = (array) ( $ax_emp_entry['tag'] ?? array() );
	$ax_emp_host = (string) wp_parse_url( (string) ( $ax_emp_entry['observed_in'] ?? '' ), PHP_URL_HOST );
	$ax_emp_res  = axismundi_emoji_resolve_authority( $ax_emp_tag, $ax_emp_host );
	$ax_emp_id_h = strtolower( (string) wp_parse_url( (string) ( $ax_emp_tag['id'] ?? '' ), PHP_URL_HOST ) );
	$ax_emp_cdn  = strtolower( (string) wp_parse_url( (string) ( $ax_emp_tag['icon']['url'] ?? '' ), PHP_URL_HOST ) );
	$ax_emp_auth_ok   = $ax_emp_auth_ok && $ax_emp_res === $ax_emp_id_h;
	$ax_emp_never_cdn = $ax_emp_never_cdn && $ax_emp_res !== $ax_emp_cdn;
}
ax_emp_assert( $ax_emp_results, 'authority resolves to the declaring host in every captured tag', $ax_emp_auth_ok );
ax_emp_assert( $ax_emp_results, 'authority is never the CDN host, which differs in every captured tag', $ax_emp_never_cdn );

// A qualified name must win over both the id host and the carrying object's host,
// because a borrowed emoji belongs to neither.
$ax_emp_borrowed = axismundi_emoji_resolve_authority(
	array( 'type' => 'Emoji', 'name' => ':09_bird@hoto.moe:', 'id' => 'https://misskey.io/emojis/09_bird' ),
	'misskey.io'
);
ax_emp_assert( $ax_emp_results, 'an explicit @authority outranks both the id host and the declaring object', 'hoto.moe' === $ax_emp_borrowed );

$ax_emp_bare = axismundi_emoji_resolve_authority( array( 'type' => 'Emoji', 'name' => ':blobcat:' ), 'example.social' );
ax_emp_assert( $ax_emp_results, 'a bare name with no id falls back to the declaring authority', 'example.social' === $ax_emp_bare );

/* -------------------------------------------------------------------------- *
 * Descriptor extraction
 * -------------------------------------------------------------------------- */

$ax_emp_doc  = ax_emp_fixture( $ax_emp_dir, 'emoji-document-misskey-restricted.json' );
$ax_emp_desc = axismundi_emoji_descriptor_from_tag( $ax_emp_doc, 'misskey.io' );
ax_emp_assert(
	$ax_emp_results,
	'a dereferenced Emoji document yields a complete descriptor',
	is_array( $ax_emp_desc )
		&& 'misskey.io' === $ax_emp_desc['emoji_authority']
		&& 'ai_acid_misskeyio' === $ax_emp_desc['shortcode_key']
		&& ':ai_acid_misskeyio:' === $ax_emp_desc['shortcode']
);
ax_emp_assert(
	$ax_emp_results,
	'`updated` is captured both verbatim and as a comparable timestamp, since it is the only invalidation signal',
	is_array( $ax_emp_desc ) && '' !== $ax_emp_desc['updated_raw'] && null !== $ax_emp_desc['updated_at']
);
ax_emp_assert(
	$ax_emp_results,
	'a prohibition in the licence classifies as restricted',
	is_array( $ax_emp_desc ) && 'restricted' === $ax_emp_desc['license_state']
);
ax_emp_assert(
	$ax_emp_results,
	'icon.mediaType is retained separately, as a hint that bytes will later confirm or contradict',
	is_array( $ax_emp_desc ) && 'image/apng' === $ax_emp_desc['declared_media_type']
);

// A licence member that is present but null is absence, not a statement.
$ax_emp_nulled = axismundi_emoji_descriptor_from_tag(
	array(
		'type' => 'Emoji',
		'name' => ':misskey:',
		'id'   => 'https://misskey.io/emojis/misskey',
		'icon' => array( 'type' => 'Image', 'url' => 'https://media.misskeyusercontent.com/emoji/misskey.png' ),
		'_misskey_license' => array( 'freeText' => null ),
	),
	'misskey.io'
);
ax_emp_assert( $ax_emp_results, 'a present-but-null licence member reads as unknown, not as restricted', is_array( $ax_emp_nulled ) && 'unknown' === $ax_emp_nulled['license_state'] );

// Grants must not be mistaken for restrictions merely because a string exists.
$ax_emp_licenses = ax_emp_fixture( $ax_emp_dir, 'license-sample-misskey.json' );
$ax_emp_tally    = array( 'unknown' => 0, 'allowed' => 0, 'restricted' => 0 );
foreach ( $ax_emp_licenses as $ax_emp_row ) {
	++$ax_emp_tally[ axismundi_emoji_classify_license( (string) ( $ax_emp_row['license'] ?? '' ) ) ];
}
ax_emp_assert( $ax_emp_results, 'the classifier reproduces the sampled distribution: grants outnumber prohibitions, unlicensed outnumber prohibitions', $ax_emp_tally['allowed'] > $ax_emp_tally['restricted'] && $ax_emp_tally['unknown'] > $ax_emp_tally['restricted'] );

/* -------------------------------------------------------------------------- *
 * Rejection paths
 * -------------------------------------------------------------------------- */

$ax_emp_rejects = array(
	'a non-Emoji tag'          => array( 'type' => 'Hashtag', 'name' => '#wordpress' ),
	'a plain string'           => 'not-an-object',
	'a missing icon'           => array( 'type' => 'Emoji', 'name' => ':x_y:' ),
	'a non-https icon'         => array( 'type' => 'Emoji', 'name' => ':x_y:', 'icon' => array( 'url' => 'http://insecure.example/e.png' ) ),
	'an unparsable name'       => array( 'type' => 'Emoji', 'name' => ':bad name:', 'icon' => array( 'url' => 'https://e.example/e.png' ) ),
);
$ax_emp_rejected = true;
foreach ( $ax_emp_rejects as $ax_emp_tag ) {
	$ax_emp_rejected = $ax_emp_rejected && null === axismundi_emoji_descriptor_from_tag( $ax_emp_tag, 'example.social' );
}
ax_emp_assert( $ax_emp_results, 'non-Emoji tags, missing icons, insecure icons, and malformed names are skipped rather than half-stored', $ax_emp_rejected );

// A prefixed type is the same type. Mastodon compacts to `Emoji`; a document that
// keeps `toot:Emoji` must not be silently dropped.
$ax_emp_prefixed = axismundi_emoji_descriptor_from_tag(
	array( 'type' => 'toot:Emoji', 'name' => ':blobcat:', 'icon' => array( 'url' => 'https://e.example/e.png' ) ),
	'example.social'
);
ax_emp_assert( $ax_emp_results, 'a `toot:Emoji` type is recognised as an Emoji', is_array( $ax_emp_prefixed ) );

/* -------------------------------------------------------------------------- *
 * Payload extraction and caps
 * -------------------------------------------------------------------------- */

$ax_emp_many = array( 'id' => 'https://example.social/notes/1', 'tag' => array() );
for ( $ax_emp_i = 0; $ax_emp_i < AXISMUNDI_EMOJI_MAX_PER_SUBJECT + 25; $ax_emp_i++ ) {
	$ax_emp_many['tag'][] = array(
		'type' => 'Emoji',
		'name' => ':e' . $ax_emp_i . ':',
		'icon' => array( 'url' => 'https://cdn.example/e' . $ax_emp_i . '.png' ),
	);
}
$ax_emp_capped = axismundi_emoji_descriptors_from_payload( $ax_emp_many, 'https://example.social/notes/1' );
ax_emp_assert( $ax_emp_results, 'a subject declaring more emoji than the cap is truncated, so one post cannot flood the queue', count( $ax_emp_capped ) === AXISMUNDI_EMOJI_MAX_PER_SUBJECT );

// The same emoji twice in one payload is one row, not two.
$ax_emp_dupes = axismundi_emoji_descriptors_from_payload(
	array(
		'id'  => 'https://example.social/notes/2',
		'tag' => array(
			array( 'type' => 'Emoji', 'name' => ':blobcat:', 'icon' => array( 'url' => 'https://cdn.example/a.png' ) ),
			array( 'type' => 'Emoji', 'name' => ':BlobCat:', 'icon' => array( 'url' => 'https://cdn.example/b.png' ) ),
		),
	),
	'https://example.social/notes/2'
);
ax_emp_assert( $ax_emp_results, 'the same identity declared twice in one payload collapses to one descriptor', 1 === count( $ax_emp_dupes ) );

// A single tag object rather than a list is legal JSON-LD.
$ax_emp_single = axismundi_emoji_descriptors_from_payload(
	array( 'id' => 'https://example.social/notes/3', 'tag' => array( 'type' => 'Emoji', 'name' => ':blobcat:', 'icon' => array( 'url' => 'https://cdn.example/a.png' ) ) ),
	'https://example.social/notes/3'
);
ax_emp_assert( $ax_emp_results, 'a lone tag object, not wrapped in a list, is still read', 1 === count( $ax_emp_single ) );

$ax_emp_failures = count( array_filter( $ax_emp_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_emp_results ), $ax_emp_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_emp_failures > 0 ? 1 : 0 );
}
exit( $ax_emp_failures > 0 ? 1 : 0 );
