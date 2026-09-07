<?php
/**
 * Emoji reactions in the Activity ledger (dev-only; dist-excluded).
 *
 * FEP-c0e0 requires that `Like` carrying `content` be processed exactly like
 * `EmojiReact`, so the wire has two spellings for one thing and the ledger must not
 * care which arrived. What it must care about is the opposite direction: a plain `Like`
 * has no `content`, means something different, and must never be counted as a reaction
 * or have reactions counted into it.
 *
 * The idempotency behaviour these checks rely on already exists — the ledger keys on
 * `activity_uri_hash`, and every recorded Activity recomputes its own effectiveness, so
 * an `Undo` that arrives before its target still applies when the target shows up. This
 * file adds the reaction axis on top of that rather than restating it.
 *
 * No network. Fixtures use `example.com`, because `wp_http_validate_url()` refuses a
 * `.test` host and the Actor fixture would be rejected before any Activity is recorded.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_er_results    = array();
$ax_er_suffix     = strtolower( wp_generate_password( 8, false, false ) );
$ax_er_alice      = 'https://example.com/users/alice-' . $ax_er_suffix;
$ax_er_bob        = 'https://example.com/users/bob-' . $ax_er_suffix;
$ax_er_object     = 'https://example.com/notes/' . $ax_er_suffix;
$ax_er_identities = array();

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_er_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/**
 * Cache one remote Person so the ledger will accept Activities from it.
 *
 * @param string $uri        Actor URI.
 * @param array  $identities Accumulator for cleanup.
 * @return Axismundi_Actor|null
 */
function ax_er_actor( string $uri, array &$identities ) : ?Axismundi_Actor {
	$actor = axismundi_actors_upsert_remote(
		array(
			'uri'                => $uri,
			'actor_type'         => 'Person',
			'preferred_username'  => 'u' . substr( hash( 'sha256', $uri ), 0, 8 ),
			'display_name'       => 'Fixture',
			'profile_url'        => $uri,
			'endpoints'          => array( 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
			'payload'            => array( 'id' => $uri, 'type' => 'Person', 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
		)
	);
	if ( $actor instanceof Axismundi_Actor ) {
		$identities[] = $actor->get_identity_id();
		return $actor;
	}
	return null;
}

/** @param string $uri Object URI. @param string $key Reaction key. @return int */
function ax_er_chip( string $uri, string $key ) : int {
	$counts = axismundi_act_get_reaction_counts( $uri );
	foreach ( $counts as $row ) {
		if ( (string) ( $row['reaction_key'] ?? '' ) === $key ) {
			return (int) ( $row['count'] ?? 0 );
		}
	}
	return 0;
}

try {
	foreach ( array( 'axismundi_act_get_reaction_counts', 'axismundi_act_normalize_reaction' ) as $ax_er_fn ) {
		if ( ! function_exists( $ax_er_fn ) ) {
			ax_er_assert( $ax_er_results, 'the reaction API exists: ' . $ax_er_fn, false );
			throw new RuntimeException( $ax_er_fn . ' is not implemented' );
		}
	}
	axismundi_act_install();
	$ax_er_a = ax_er_actor( $ax_er_alice, $ax_er_identities );
	$ax_er_b = ax_er_actor( $ax_er_bob, $ax_er_identities );
	axismundi_op_store_remote_object( array( 'id' => $ax_er_object, 'type' => 'Note', 'attributedTo' => $ax_er_alice, 'content' => 'target' ) );
	ax_er_assert( $ax_er_results, 'the fixture caches two Actors and one target Object', $ax_er_a instanceof Axismundi_Actor && $ax_er_b instanceof Axismundi_Actor );

	$ax_er_react = static function ( string $actor, string $id, $content, array $extra = array() ) use ( $ax_er_object ) {
		return axismundi_act_record_activity(
			array_merge( array( 'id' => $actor . '/likes/' . $id, 'type' => 'Like', 'actor' => $actor, 'object' => $ax_er_object, 'content' => $content ), $extra ),
			'inbound'
		);
	};
	$ax_er_undo = static function ( string $actor, string $id, string $target ) use ( $ax_er_object ) {
		return axismundi_act_record_activity( array( 'id' => $actor . '/undos/' . $id, 'type' => 'Undo', 'actor' => $actor, 'object' => $target ), 'inbound' );
	};

	// -- Normalization ------------------------------------------------------------------

	/*
	 * Measured, not assumed: the captured Misskey reaction list carries `❤` as a bare
	 * U+2764, while every keyboard and picker produces U+2764 U+FE0F. Keying on the exact
	 * grapheme would put the same visible heart in two chips.
	 */
	$ax_er_bare = axismundi_act_normalize_reaction( "\u{2764}" );
	$ax_er_vs16 = axismundi_act_normalize_reaction( "\u{2764}\u{FE0F}" );
	ax_er_assert( $ax_er_results, 'a heart with and without the presentation selector share one key', is_array( $ax_er_bare ) && is_array( $ax_er_vs16 ) && $ax_er_bare['key'] === $ax_er_vs16['key'] );
	ax_er_assert( $ax_er_results, 'but each keeps the text its sender actually wrote', "\u{2764}" === $ax_er_bare['raw'] && "\u{2764}\u{FE0F}" === $ax_er_vs16['raw'] );

	/*
	 * A skin tone is a statement about the person reacting, not a rendering variant, so it
	 * stays a distinct reaction. The same holds for ZWJ sequences and flags: collapsing
	 * them would merge things their senders chose apart.
	 */
	$ax_er_plain_thumb = axismundi_act_normalize_reaction( "\u{1F44D}" );
	$ax_er_toned_thumb = axismundi_act_normalize_reaction( "\u{1F44D}\u{1F3FF}" );
	ax_er_assert( $ax_er_results, 'a skin tone is a different reaction, not a variant of the same one', $ax_er_plain_thumb['key'] !== $ax_er_toned_thumb['key'] );
	$ax_er_flag = axismundi_act_normalize_reaction( "\u{1F1F0}\u{1F1F7}" );
	ax_er_assert( $ax_er_results, 'a regional-indicator flag survives as one reaction', is_array( $ax_er_flag ) && '' !== $ax_er_flag['key'] );

	// Not a single grapheme, so not a reaction; `Like` with prose is still a plain Like.
	ax_er_assert( $ax_er_results, 'ordinary prose in content is not a reaction', null === axismundi_act_normalize_reaction( 'nice post' ) );
	ax_er_assert( $ax_er_results, 'and neither are two graphemes at once', null === axismundi_act_normalize_reaction( "\u{2764}\u{1F44D}" ) );
	ax_er_assert( $ax_er_results, 'a non-emoji single grapheme is not mistaken for a reaction', null === axismundi_act_normalize_reaction( '가' ) );
	ax_er_assert( $ax_er_results, 'reaction identity does not require mbstring', is_int( axismundi_act_unicode_codepoint( "\u{2764}" ) ) && 0x2764 === axismundi_act_unicode_codepoint( "\u{2764}" ) );

	// -- Custom reactions: the tag is the evidence ---------------------------------------

	/*
	 * A custom reaction's `content` is a shortcode, and a shortcode names nothing on its
	 * own — two servers both ship `:misskey:`. The accompanying `Emoji` tag is what says
	 * whose, so it is required and it must agree with the content. Without that agreement a
	 * sender could pair `:foo:` with a tag pointing anywhere and mint
	 * `custom:victim.example:foo`, an identity nobody published.
	 */
	$ax_er_tag = static fn( string $name, string $id ) : array => array(
		'type' => 'Emoji',
		'name' => $name,
		'id'   => $id,
		'icon' => array( 'type' => 'Image', 'url' => 'https://cdn.example.com/e.png' ),
	);

	$ax_er_ok = axismundi_act_normalize_reaction( ':foo:', $ax_er_tag( ':foo:', 'https://misskey.example/emojis/foo' ) );
	ax_er_assert( $ax_er_results, 'a bare shortcode takes its authority from the tag that declares it', is_array( $ax_er_ok ) && 'custom:misskey.example:foo' === $ax_er_ok['key'] );

	ax_er_assert(
		$ax_er_results,
		'a tag naming a different emoji is refused, not silently trusted for its host',
		null === axismundi_act_normalize_reaction( ':foo:', $ax_er_tag( ':bar:', 'https://victim.example/emojis/bar' ) )
	);
	ax_er_assert( $ax_er_results, 'a custom reaction with no declaration at all is refused', null === axismundi_act_normalize_reaction( ':foo:' ) );
	ax_er_assert( $ax_er_results, 'and a qualified name is refused too when nothing declares it', null === axismundi_act_normalize_reaction( ':foo@hoto.example:' ) );

	/*
	 * A qualified name states its own authority. If the declaration disagrees, one of them
	 * is wrong and there is no way to tell which, so neither is believed.
	 */
	ax_er_assert(
		$ax_er_results,
		'a qualified name whose declaration agrees resolves to that authority',
		'custom:hoto.example:foo' === ( axismundi_act_normalize_reaction( ':foo@hoto.example:', $ax_er_tag( ':foo:', 'https://hoto.example/emojis/foo' ) )['key'] ?? '' )
	);
	ax_er_assert(
		$ax_er_results,
		'and one whose declaration names another host is refused',
		null === axismundi_act_normalize_reaction( ':foo@hoto.example:', $ax_er_tag( ':foo:', 'https://elsewhere.example/emojis/foo' ) )
	);
	ax_er_assert( $ax_er_results, 'a declaration with no usable id is not an authority', null === axismundi_act_normalize_reaction( ':foo:', array( 'type' => 'Emoji', 'name' => ':foo:' ) ) );

	/*
	 * Check the ledger boundary, not only the helper. A malformed custom reaction must
	 * be rejected rather than falling through into a plain Like and inflating favourites.
	 * FEP-c0e0 allows vocabulary-qualified Emoji tags, so `toot:Emoji` is evidence too.
	 */
	$ax_er_custom_activity = $ax_er_react(
		$ax_er_bob,
		'custom-qualified-type',
		':foo:',
		array( 'tag' => array( array_merge( $ax_er_tag( ':foo:', 'https://misskey.example/emojis/foo' ), array( 'type' => 'toot:Emoji' ) ) ) )
	);
	ax_er_assert( $ax_er_results, 'a vocabulary-qualified Emoji tag proves a custom reaction', $ax_er_custom_activity instanceof Axismundi_Activity && 1 === ax_er_chip( $ax_er_object, 'custom:misskey.example:foo' ) );
	$ax_er_bare_custom = $ax_er_react( $ax_er_bob, 'custom-bare', ':unproven:' );
	ax_er_assert( $ax_er_results, 'an unproven custom shortcode is refused instead of becoming a plain Like', is_wp_error( $ax_er_bare_custom ) );
	$ax_er_duplicate_tags = $ax_er_react(
		$ax_er_bob,
		'custom-ambiguous',
		':foo:',
		array( 'tag' => array( $ax_er_tag( ':foo:', 'https://misskey.example/emojis/foo' ), $ax_er_tag( ':foo:', 'https://hoto.example/emojis/foo' ) ) )
	);
	ax_er_assert( $ax_er_results, 'two Emoji declarations are ambiguous and refused', is_wp_error( $ax_er_duplicate_tags ) );

	// -- Counting -------------------------------------------------------------------------

	$ax_er_heart = $ax_er_bare['key'];
	$ax_er_thumb = $ax_er_plain_thumb['key'];

	$ax_er_react( $ax_er_alice, 'h1', "\u{2764}" );
	$ax_er_react( $ax_er_alice, 'h2', "\u{2764}\u{FE0F}" );
	ax_er_assert( $ax_er_results, 'one Actor reacting twice with the same emoji counts once on that chip', 1 === ax_er_chip( $ax_er_object, $ax_er_heart ) );

	$ax_er_react( $ax_er_alice, 't1', "\u{1F44D}" );
	ax_er_assert( $ax_er_results, 'the same Actor with a different emoji counts on both chips', 1 === ax_er_chip( $ax_er_object, $ax_er_heart ) && 1 === ax_er_chip( $ax_er_object, $ax_er_thumb ) );

	$ax_er_react( $ax_er_bob, 'h1', "\u{2764}" );
	ax_er_assert( $ax_er_results, 'a second Actor adds to the chip they share', 2 === ax_er_chip( $ax_er_object, $ax_er_heart ) );

	// -- Undo -------------------------------------------------------------------------------

	/*
	 * Two Activities express one reaction here. Undoing one leaves the other active, so the
	 * chip must survive — the count is over Actors with an effective reaction, not over
	 * Activities.
	 */
	$ax_er_undo( $ax_er_alice, 'u1', $ax_er_alice . '/likes/h1' );
	ax_er_assert( $ax_er_results, 'undoing one of two Activities for the same reaction leaves the chip standing', 2 === ax_er_chip( $ax_er_object, $ax_er_heart ) );
	$ax_er_undo( $ax_er_alice, 'u2', $ax_er_alice . '/likes/h2' );
	ax_er_assert( $ax_er_results, 'undoing the last one removes that Actor from the chip', 1 === ax_er_chip( $ax_er_object, $ax_er_heart ) );
	ax_er_assert( $ax_er_results, 'and leaves their other reaction alone', 1 === ax_er_chip( $ax_er_object, $ax_er_thumb ) );

	// -- The boundary with plain Like -------------------------------------------------------

	/*
	 * The whole reason for a separate key. A plain Like and an emoji reaction are different
	 * statements, and each aggregate must ignore the other or the two meanings we split
	 * apart are silently rejoined in the numbers.
	 */
	$ax_er_likes_before = axismundi_act_get_like_count( $ax_er_object );
	ax_er_assert( $ax_er_results, 'reactions never appear in the plain Like count', 0 === $ax_er_likes_before );

	axismundi_act_record_activity( array( 'id' => $ax_er_bob . '/likes/plain', 'type' => 'Like', 'actor' => $ax_er_bob, 'object' => $ax_er_object ), 'inbound' );
	ax_er_assert( $ax_er_results, 'a Like without content is a Like', 1 === axismundi_act_get_like_count( $ax_er_object ) );
	ax_er_assert( $ax_er_results, 'and it appears on no reaction chip', 1 === ax_er_chip( $ax_er_object, $ax_er_heart ) && 1 === ax_er_chip( $ax_er_object, $ax_er_thumb ) );

	// -- EmojiReact spellings ----------------------------------------------------------------

	/*
	 * FEP-c0e0 defines `EmojiReact`; Akkoma sends it under the LitePub IRI. The ledger's
	 * type vocabulary matches exact strings, so both spellings have to canonicalize or one
	 * federation partner's reactions vanish without an error.
	 */
	$ax_er_compact = axismundi_act_record_activity(
		array( 'id' => $ax_er_bob . '/reacts/compact', 'type' => 'EmojiReact', 'actor' => $ax_er_bob, 'object' => $ax_er_object, 'content' => "\u{2B50}" ),
		'inbound'
	);
	$ax_er_iri = axismundi_act_record_activity(
		array( 'id' => $ax_er_alice . '/reacts/iri', 'type' => 'http://litepub.social/ns#EmojiReact', 'actor' => $ax_er_alice, 'object' => $ax_er_object, 'content' => "\u{2B50}" ),
		'inbound'
	);
	$ax_er_star = axismundi_act_normalize_reaction( "\u{2B50}" )['key'];
	ax_er_assert( $ax_er_results, 'a compact EmojiReact is accepted', $ax_er_compact instanceof Axismundi_Activity );
	ax_er_assert( $ax_er_results, 'and the same activity under its LitePub IRI is too', $ax_er_iri instanceof Axismundi_Activity );
	ax_er_assert( $ax_er_results, 'both land on the one chip, from two Actors', 2 === ax_er_chip( $ax_er_object, $ax_er_star ) );
	ax_er_assert(
		$ax_er_results,
		'and both are stored under one canonical type, so later queries need not know the spellings',
		$ax_er_compact instanceof Axismundi_Activity && $ax_er_iri instanceof Axismundi_Activity && $ax_er_compact->get_type() === $ax_er_iri->get_type()
	);

	// -- Evidence --------------------------------------------------------------------------

	/*
	 * `reaction_raw` is a convenience for the UI, which needs a string to show without
	 * re-parsing the payload. A convenience that can drift from the evidence is worse than
	 * none, so it is asserted against the stored payload rather than trusted.
	 */
	$ax_er_table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned ledger.
	$ax_er_rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT payload_json, reaction_raw, reaction_key FROM {$ax_er_table} WHERE object_uri_hash = %s AND reaction_key IS NOT NULL", hash( 'sha256', $ax_er_object ) ), ARRAY_A );
	$ax_er_drift = 0;
	foreach ( $ax_er_rows as $ax_er_row ) {
		$ax_er_payload = json_decode( (string) $ax_er_row['payload_json'], true );
		if ( ! is_array( $ax_er_payload ) || (string) ( $ax_er_payload['content'] ?? '' ) !== (string) $ax_er_row['reaction_raw'] ) {
			$ax_er_drift++;
		}
	}
	ax_er_assert( $ax_er_results, 'every stored reaction_raw is exactly the content its payload carries', $ax_er_rows && 0 === $ax_er_drift );

	// -- The schema must be verified, not assumed ---------------------------------------------

	/*
	 * The same lesson v5 recorded about `instrument_uri_hash`: `dbDelta()` can add a column
	 * and fail to add an index, and a version stored on that outcome is never retried. Here
	 * the cost is silent — every chip query falls back to a scan and nothing looks broken
	 * until an object has thousands of reactions.
	 */
	$ax_er_index = (array) $wpdb->get_results( "SHOW INDEX FROM {$ax_er_table} WHERE Key_name = 'reaction_chip'", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema assertion.
	ax_er_assert( $ax_er_results, 'the chip aggregate has its covering index', ! empty( $ax_er_index ) );
	$ax_er_reader = new ReflectionFunction( 'axismundi_act_install' );
	$ax_er_source = (string) file_get_contents( $ax_er_reader->getFileName() );
	ax_er_assert( $ax_er_results, 'and the installer verifies that index before recording the version, so a failed migration retries', str_contains( $ax_er_source, 'reaction_index' ) );

	// -- Single-emoji detection without ext-intl ------------------------------------------------

	/*
	 * `grapheme_strlen()` is authoritative but comes from `ext-intl`, which WordPress does
	 * not require and shared hosts do not always have. Falling through to "not a reaction"
	 * there would quietly turn every received emoji reaction into a favourite, inflating a
	 * count and losing the reaction — a downgrade nobody would see.
	 */
	ax_er_assert( $ax_er_results, 'a fallback exists for installs without ext-intl', function_exists( 'axismundi_act_single_emoji_fallback' ) );
	if ( function_exists( 'axismundi_act_single_emoji_fallback' ) ) {
		$ax_er_cases = array(
			"\u{2764}"                                  => true,
			"\u{1F44D}\u{1F3FF}"                        => true,
			"\u{1F1F0}\u{1F1F7}"                        => true,
			"\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}" => true,
			"\u{2764}\u{1F44D}"                         => false,
			'ab'                                        => false,
			'a'                                         => false,
		);
		$ax_er_wrong = 0;
		foreach ( $ax_er_cases as $ax_er_input => $ax_er_want ) {
			if ( axismundi_act_single_emoji_fallback( (string) $ax_er_input ) !== $ax_er_want ) {
				$ax_er_wrong++;
			}
		}
		ax_er_assert( $ax_er_results, 'and it agrees with intl on flags, skin tones, ZWJ sequences, and two-emoji strings', 0 === $ax_er_wrong );
	}

	// -- Misskey's second spelling of the same field -------------------------------------------

	/*
	 * Misskey sends the reaction in both `content` and `_misskey_reaction`. Agreeing, they
	 * are one fact stated twice. Disagreeing, there is no principled way to pick — choosing
	 * silently would record a reaction nobody sent, so the Activity is refused.
	 */
	$ax_er_agree = $ax_er_react( $ax_er_bob, 'mk-ok', "\u{1F425}", array( '_misskey_reaction' => "\u{1F425}" ) );
	ax_er_assert( $ax_er_results, 'Misskey stating the reaction twice, agreeing, is accepted once', $ax_er_agree instanceof Axismundi_Activity );
	$ax_er_conflict = $ax_er_react( $ax_er_bob, 'mk-bad', "\u{1F425}", array( '_misskey_reaction' => "\u{2764}" ) );
	ax_er_assert( $ax_er_results, 'the two disagreeing is refused rather than resolved by guessing', is_wp_error( $ax_er_conflict ) );
} catch ( Throwable $ax_er_error ) {
	ax_er_assert( $ax_er_results, 'the reaction suite ran to completion: ' . $ax_er_error->getMessage(), false );
} finally {
	$ax_er_table = axismundi_act_activities_table();
	foreach ( array( $ax_er_alice, $ax_er_bob ) as $ax_er_uri ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$ax_er_table} WHERE actor_uri_hash = %s", hash( 'sha256', $ax_er_uri ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	if ( function_exists( 'axismundi_op_remote_objects_table' ) ) {
		$wpdb->delete( axismundi_op_remote_objects_table(), array( 'object_uri_hash' => hash( 'sha256', $ax_er_object ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( array_unique( $ax_er_identities ) as $ax_er_identity ) {
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $ax_er_identity ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => (int) $ax_er_identity ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $ax_er_identity ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
}

$ax_er_failures = count( array_filter( $ax_er_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_er_results ), $ax_er_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_er_failures > 0 ? 1 : 0 );
}
exit( $ax_er_failures > 0 ? 1 : 0 );
