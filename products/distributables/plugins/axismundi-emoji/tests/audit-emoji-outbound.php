<?php
/**
 * Publishing local emoji (dev-only; dist-excluded).
 *
 * The symmetry is the contract. We render only what a document declared, so we must
 * declare everything our own document uses — and nothing it does not, because a `tag[]`
 * carrying emoji that appear nowhere in the text is storage every receiver pays for.
 *
 * Tokenizing is where that goes wrong quietly: `10:30:00` and `http://x/a:b:c` contain
 * colon-delimited runs that are not emoji, and a naive match declares `:30:` as one.
 *
 * No network.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-emoji.php';

global $wpdb;
$ax_out_results = array();
$ax_out_ids     = array();

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_out_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/** @param int $seed Colour seed. @return string A distinct square PNG. */
function ax_out_png( int $seed ) : string {
	$image = imagecreatetruecolor( 32, 32 );
	imagefill( $image, 0, 0, imagecolorallocate( $image, $seed % 256, ( $seed * 17 ) % 256, ( $seed * 43 ) % 256 ) );
	ob_start();
	imagepng( $image );
	imagedestroy( $image );
	return (string) ob_get_clean();
}

/** @param array<int,array<string,mixed>> $tags Tag list. @return string[] */
function ax_out_names( array $tags ) : array {
	return array_map( static fn( array $t ) : string => (string) ( $t['name'] ?? '' ), $tags );
}

try {
	axismundi_emoji_install();
	wp_set_current_user( 1 );
	foreach ( array( 'outpublic', 'outprivate' ) as $ax_out_key ) {
		$ax_out_stale = axismundi_emoji_local_get( $ax_out_key );
		if ( is_array( $ax_out_stale ) ) {
			axismundi_emoji_delete_local( (int) $ax_out_stale['id'] );
		}
	}

	// -- Tokenizing ----------------------------------------------------------------------

	ax_out_assert( $ax_out_results, 'a shortcode in ordinary prose is found', array( 'outpublic' ) === axismundi_emoji_tokenize( 'hello :outpublic: there' ) );
	ax_out_assert( $ax_out_results, 'one at the very start and end of the text is found too', array( 'aa', 'bb' ) === axismundi_emoji_tokenize( ':aa: and :bb:' ) );
	ax_out_assert( $ax_out_results, 'the same emoji used twice is declared once', array( 'twice' ) === axismundi_emoji_tokenize( ':twice: and :twice: again' ) );

	/*
	 * The boundary rule, which is the whole reason this is not a plain search for `:x:`.
	 * FEP-9098 asks that a shortcode sit between characters that are neither alphanumeric
	 * nor a colon, and these are what that rule is protecting against.
	 */
	ax_out_assert( $ax_out_results, 'a clock time is not three emoji', array() === axismundi_emoji_tokenize( 'at 10:30:00 today' ) );
	ax_out_assert( $ax_out_results, 'a colon-delimited path is not an emoji', array() === axismundi_emoji_tokenize( 'see http://example.com/a:bb:c' ) );
	ax_out_assert( $ax_out_results, 'a name glued to a word is not one either', array() === axismundi_emoji_tokenize( 'x:notone:y' ) );

	/*
	 * The exact boundary set, pinned because a picker has to reproduce it.
	 *
	 * Any surface that inserts a shortcode must decide whether to add a separating space,
	 * and it can only decide that correctly by asking the same question this tokenizer
	 * asks. A simplified `[A-Za-z0-9:]` looks equivalent and is not: `_` is a legal
	 * shortcode character, so it cannot also be a boundary, and a picker using the shorter
	 * set would decline to add a space after `foo_` and then watch the tokenizer decline to
	 * declare what it just inserted — visible nowhere, with no error.
	 *
	 * A line ending is a boundary, and so is the start or end of the text. FEP-9098's
	 * Compatibility wording reads as excluding line endings; neither we nor Mastodon
	 * implement it that way, and a shortcode alone on its line is an ordinary thing to
	 * write. §2 of the architecture document records the divergence.
	 */
	foreach ( array( 'a', 'Z', '0', '_', ':' ) as $ax_out_bad ) {
		ax_out_assert(
			$ax_out_results,
			sprintf( 'a shortcode touching "%s" is not declared, so an inserter must separate it', $ax_out_bad ),
			array() === axismundi_emoji_tokenize( $ax_out_bad . ':bounded: x' ) && array() === axismundi_emoji_tokenize( 'x :bounded:' . $ax_out_bad )
		);
	}
	foreach ( array( '-' => 'a hyphen', '(' => 'an opening bracket', '가' => 'a Korean syllable', '.' => 'a full stop' ) as $ax_out_ok => $ax_out_label ) {
		ax_out_assert(
			$ax_out_results,
			sprintf( '%s is a boundary, so no separator is needed there', $ax_out_label ),
			array( 'bounded' ) === axismundi_emoji_tokenize( $ax_out_ok . ':bounded:' . $ax_out_ok )
		);
	}
	ax_out_assert( $ax_out_results, 'a line ending is a boundary, contrary to a literal reading of the spec text', array( 'bounded' ) === axismundi_emoji_tokenize( "before\n:bounded:\nafter" ) );
	ax_out_assert( $ax_out_results, 'and so is the start or end of the text', array( 'bounded' ) === axismundi_emoji_tokenize( ':bounded:' ) );
	ax_out_assert( $ax_out_results, 'a single character is too short to be one', array() === axismundi_emoji_tokenize( 'a :x: b' ) );

	/*
	 * Markup is stripped rather than walked. Unlike rendering — which must leave `<code>`
	 * alone — the question here is only whether the document uses the emoji, while a colon
	 * inside an attribute or a URL is not a use at all.
	 */
	ax_out_assert( $ax_out_results, 'a shortcode inside markup attributes is not a use', array() === axismundi_emoji_tokenize( '<a href="http://x/a:bb:c" title="a:bb:c">link</a>' ) );
	ax_out_assert( $ax_out_results, 'but one in the visible text of that markup is', array( 'visible' ) === axismundi_emoji_tokenize( '<p>a <strong>:visible:</strong> b</p>' ) );

	// -- What gets declared ----------------------------------------------------------------

	$ax_out_public  = axismundi_emoji_register_local( ax_out_png( 3 ), ':outpublic:', array( 'category' => 'Outbound' ) );
	$ax_out_private = axismundi_emoji_register_local( ax_out_png( 7 ), ':outprivate:', array( 'local_only' => true ) );
	foreach ( array( $ax_out_public, $ax_out_private ) as $ax_out_row ) {
		if ( is_wp_error( $ax_out_row ) ) {
			throw new RuntimeException( 'fixture: ' . $ax_out_row->get_error_message() );
		}
		$ax_out_ids[] = (int) $ax_out_row['id'];
	}

	$ax_out_tags = axismundi_emoji_outbound_tags( array( 'body with :outpublic: in it' ) );
	ax_out_assert( $ax_out_results, 'an emoji the text uses is declared', array( ':outpublic:' ) === ax_out_names( $ax_out_tags ) );
	ax_out_assert( $ax_out_results, 'an emoji the text does not use is not declared, so receivers store nothing spare', array() === axismundi_emoji_outbound_tags( array( 'body with no emoji at all' ) ) );
	ax_out_assert( $ax_out_results, 'a shortcode with no local emoji behind it declares nothing', array() === axismundi_emoji_outbound_tags( array( 'body with :nosuchemoji: in it' ) ) );

	/*
	 * `localOnly` decides here, exactly as Misskey's does: the shortcode still travels in
	 * the text, but with no declaration to explain it, so the receiver shows the word.
	 * Publishing the image anyway would make the flag meaningless.
	 */
	ax_out_assert( $ax_out_results, 'a local-only emoji is not declared, so its image never leaves', array() === axismundi_emoji_outbound_tags( array( 'body with :outprivate: in it' ) ) );

	// -- The union across a document's several texts ------------------------------------------

	/*
	 * `tag[]` has no language axis and needs none: the same `:outpublic:` in a Korean and
	 * an English biography is the same emoji, so the declaration is taken once over every
	 * variant rather than per language.
	 */
	$ax_out_union = axismundi_emoji_outbound_tags(
		array(
			'한국어 본문에 :outpublic: 있음',
			'English body with :outpublic: too',
			'A title with :outpublic:',
		)
	);
	ax_out_assert( $ax_out_results, 'one emoji used in several language variants is declared once', array( ':outpublic:' ) === ax_out_names( $ax_out_union ) );

	// -- The shape of a declaration -------------------------------------------------------------

	$ax_out_tag = $ax_out_tags[0] ?? array();
	ax_out_assert( $ax_out_results, 'a declaration is an AS2 Emoji', 'Emoji' === (string) ( $ax_out_tag['type'] ?? '' ) );
	ax_out_assert( $ax_out_results, 'named exactly as it is written in the text', ':outpublic:' === (string) ( $ax_out_tag['name'] ?? '' ) );
	ax_out_assert( $ax_out_results, 'with an image a receiver can actually fetch', str_starts_with( (string) ( $ax_out_tag['icon']['url'] ?? '' ), home_url() ) && 'Image' === (string) ( $ax_out_tag['icon']['type'] ?? '' ) );
	ax_out_assert( $ax_out_results, 'and a media type, so a receiver need not guess before downloading', 'image/png' === (string) ( $ax_out_tag['icon']['mediaType'] ?? '' ) );
	/*
	 * `updated` is the invalidation signal §3 relies on when receiving. Publishing without
	 * it would ask others to cache our first version forever.
	 */
	ax_out_assert( $ax_out_results, 'and an updated timestamp, the same signal we rely on from others', '' !== (string) ( $ax_out_tag['updated'] ?? '' ) );
	ax_out_assert( $ax_out_results, 'with a dereferenceable id, which is what we ourselves follow on receipt', str_contains( (string) ( $ax_out_tag['id'] ?? '' ), '/emojis/outpublic' ) );

	// Editing the emoji must move `updated`, or a receiver never learns the picture changed.
	$ax_out_before = (string) $ax_out_tag['updated'];
	sleep( 1 );
	axismundi_emoji_update_local( (int) $ax_out_public['id'], array( 'category' => 'Changed' ) );
	$ax_out_after = axismundi_emoji_outbound_tags( array( ':outpublic:' ) )[0]['updated'] ?? '';
	ax_out_assert( $ax_out_results, 'changing the emoji moves that timestamp, so a cached copy elsewhere expires', $ax_out_after !== $ax_out_before );

	// -- The id resolves ---------------------------------------------------------------------

	$ax_out_row  = axismundi_emoji_local_get( 'outpublic' );
	$ax_out_doc  = axismundi_emoji_as2_object( $ax_out_row );
	ax_out_assert( $ax_out_results, 'the published id is this site\'s own URL', str_starts_with( (string) $ax_out_doc['id'], home_url() ) );

	$ax_out_rules = get_option( 'rewrite_rules' );
	ax_out_assert( $ax_out_results, 'and a rewrite exists to answer it', is_array( $ax_out_rules ) && ! empty( array_filter( array_keys( $ax_out_rules ), static fn( string $r ) : bool => str_contains( $r, 'emojis/' ) ) ) );

	// -- Consumers are wired ------------------------------------------------------------------

	$ax_out_note = file_get_contents( WP_PLUGIN_DIR . '/axismundi-note/includes/federation.php' );
	ax_out_assert( $ax_out_results, 'a Note declares the emoji its content uses', is_string( $ax_out_note ) && str_contains( $ax_out_note, 'axismundi_emoji_outbound_tags' ) );
	$ax_out_article = file_get_contents( WP_PLUGIN_DIR . '/axismundi-object-projections/includes/post-article.php' );
	ax_out_assert( $ax_out_results, 'and so does an Article', is_string( $ax_out_article ) && str_contains( $ax_out_article, 'axismundi_emoji_outbound_tags' ) );

	/*
	 * An Actor too. A display name and a biography are published text like any other, so a
	 * shortcode in either needs the same declaration a Note's body does — otherwise the
	 * name that renders here arrives elsewhere as a bare word.
	 */
	$ax_out_actor = axismundi_actors_get_site_actor();
	if ( $ax_out_actor instanceof Axismundi_Actor && function_exists( 'axismundi_actors_set_text' ) ) {
		/*
		 * Written through the texts API rather than the `display_name` column, because that
		 * is where a local Actor's name actually comes from — `axismundi_actors_profile_data()`
		 * resolves it per language and never reads the column. Setting the column instead
		 * changes nothing a reader or a receiver sees.
		 */
		$ax_out_identity = $ax_out_actor->get_identity_id();
		$ax_out_lang     = 'en';
		$ax_out_previous = axismundi_actors_resolve_text( $ax_out_actor, 'summary', $ax_out_lang );
		axismundi_actors_set_text( $ax_out_identity, 'summary', $ax_out_lang, 'A bio using :outpublic: and :outprivate: here.' );

		$ax_out_doc  = axismundi_op_actor_transform( axismundi_actors_get_by_uri( $ax_out_actor->get_uri() ) );
		$ax_out_tagn = ax_out_names( (array) ( $ax_out_doc['tag'] ?? array() ) );
		ax_out_assert( $ax_out_results, 'an Actor declares the emoji its profile text uses', in_array( ':outpublic:', $ax_out_tagn, true ) );
		ax_out_assert( $ax_out_results, 'and withholds a local-only one there too, exactly as a Note does', ! in_array( ':outprivate:', $ax_out_tagn, true ) );

		/*
		 * The language that did *not* win resolution still counts. The document publishes one
		 * `summary`, so gathering only the projected scalar would leave a Korean biography's
		 * emoji undeclared whenever the English one happened to be resolved — and would break
		 * silently the moment `summaryMap` ships. `tag[]` has no language axis, so the union
		 * over stored variants is the answer in both eras.
		 */
		/*
		 * Read the stored row, not the resolved value. `resolve_text()` falls back across
		 * languages, so asking it for `ko` when no Korean row exists returns the English one
		 * — and writing that back would invent a Korean row containing whatever the English
		 * summary happened to be at that moment. It did, and the next assertion caught it.
		 */
		$ax_out_map     = axismundi_actors_get_text_map( $ax_out_identity );
		$ax_out_ko_prev = $ax_out_map['ko']['summary'] ?? null;
		axismundi_actors_set_text( $ax_out_identity, 'summary', $ax_out_lang, 'An English bio with no emoji.' );
		axismundi_actors_set_text( $ax_out_identity, 'summary', 'ko', '한국어 소개에만 :outpublic: 있음' );
		$ax_out_multi = axismundi_op_actor_transform( axismundi_actors_get_by_uri( $ax_out_actor->get_uri() ) );
		ax_out_assert(
			$ax_out_results,
			'an emoji used only in a language variant the document did not publish is still declared',
			! str_contains( (string) ( $ax_out_multi['summary'] ?? '' ), ':outpublic:' )
				&& in_array( ':outpublic:', ax_out_names( (array) ( $ax_out_multi['tag'] ?? array() ) ), true )
		);
		// Only restore a row that genuinely existed; otherwise remove the one this test made.
		if ( null !== $ax_out_ko_prev ) {
			axismundi_actors_set_text( $ax_out_identity, 'summary', 'ko', $ax_out_ko_prev );
		} else {
			$wpdb->delete( axismundi_actors_texts_table(), array( 'identity_id' => $ax_out_identity, 'field_name' => 'summary', 'language_tag' => 'ko' ), array( '%d', '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}

		axismundi_actors_set_text( $ax_out_identity, 'summary', $ax_out_lang, $ax_out_previous );
		$ax_out_clean = axismundi_op_actor_transform( axismundi_actors_get_by_uri( $ax_out_actor->get_uri() ) );
		ax_out_assert( $ax_out_results, 'and declares none once the profile stops using any', ! in_array( ':outpublic:', ax_out_names( (array) ( $ax_out_clean['tag'] ?? array() ) ), true ) );
	}
	/*
	 * Both call it through `function_exists`, so the two products keep working with Emoji
	 * deactivated — the shortcode simply travels alone, which is what a receiver shows
	 * when it has no declaration to resolve.
	 */
	ax_out_assert( $ax_out_results, 'both guard the call, so neither product requires this plugin', is_string( $ax_out_note ) && str_contains( $ax_out_note, "function_exists( 'axismundi_emoji_outbound_tags' )" ) && is_string( $ax_out_article ) && str_contains( $ax_out_article, "function_exists( 'axismundi_emoji_outbound_tags' )" ) );
} catch ( Throwable $ax_out_error ) {
	ax_out_assert( $ax_out_results, 'the outbound suite ran to completion: ' . $ax_out_error->getMessage(), false );
} finally {
	wp_set_current_user( 1 );
	foreach ( array_unique( array_filter( $ax_out_ids ) ) as $ax_out_id ) {
		axismundi_emoji_delete_local( (int) $ax_out_id );
	}
}

$ax_out_failures = count( array_filter( $ax_out_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_out_results ), $ax_out_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_out_failures > 0 ? 1 : 0 );
}
exit( $ax_out_failures > 0 ? 1 : 0 );
