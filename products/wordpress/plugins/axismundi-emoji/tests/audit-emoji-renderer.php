<?php
/**
 * Shortcode substitution (dev-only; dist-excluded).
 *
 * The central risk this pins is namespace collision. Shortcodes are per-instance names,
 * and Misskey ships a `:misskey:` emoji, so every server running Misskey has one — the
 * same name, a different picture. A renderer that resolved names globally would show
 * one server's art inside another server's message, and nothing about the output would look
 * wrong.
 *
 * No network. Registry rows are seeded directly and pointed at real committed bytes.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-emoji.php';

global $wpdb;
$ax_r_results = array();
$ax_r_ids     = array();

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_r_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/**
 * Seed an approved, cached emoji.
 *
 * @param string $authority Declaring authority.
 * @param string $key       Shortcode key.
 * @param bool   $animated  Whether to give it a still rendition.
 * @return int Row id.
 */
function ax_r_seed( string $authority, string $key, bool $animated = false, string $scope = 'remote' ) : int {
	global $wpdb;
	$now  = current_time( 'mysql', true );
	$hash = hash( 'sha256', $authority . '/' . $key );
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
		axismundi_emoji_table(),
		array(
			'emoji_authority' => $authority,
			'shortcode_key'   => $key,
			'shortcode'       => ':' . $key . ':',
			'source_url'      => 'https://cdn.' . $authority . '/' . $key . '.png',
			'scope'           => $scope,
			'source_kind'     => 'remote',
			'review_status'   => 'approved',
			'content_hash'    => $hash,
			'cached_path'     => axismundi_emoji_cache_relative_path( $hash, 'png' ),
			'static_path'     => $animated ? axismundi_emoji_cache_relative_path( $hash, 'png', '-static' ) : '',
			'animated'        => $animated ? 1 : 0,
			'byte_size'       => 100,
			'first_seen_at'   => $now,
			'last_seen_at'    => $now,
		)
	);
	return (int) $wpdb->insert_id;
}

/**
 * One Object payload declaring the given emoji.
 *
 * @param string               $subject_uri Subject URI.
 * @param array<int,string[]>  $emoji       Pairs of [authority, key].
 * @return array<string,mixed>
 */
function ax_r_payload( string $subject_uri, array $emoji ) : array {
	$tags = array();
	foreach ( $emoji as $pair ) {
		$tags[] = array(
			'type' => 'Emoji',
			'name' => ':' . $pair[1] . ':',
			'id'   => 'https://' . $pair[0] . '/emojis/' . $pair[1],
			'icon' => array( 'url' => 'https://cdn.' . $pair[0] . '/' . $pair[1] . '.png' ),
		);
	}
	return array( 'id' => $subject_uri, 'tag' => $tags );
}

try {
	axismundi_emoji_install();

	$key         = 'ax_shared_probe';
	$misskey_io  = ax_r_seed( 'misskey.test', $key );
	$hoto_moe    = ax_r_seed( 'hoto.test', $key );
	$bird        = ax_r_seed( 'hoto.test', '09_bird', true );
	$ax_r_ids    = array( $misskey_io, $hoto_moe, $bird );

	$row_io   = axismundi_emoji_get( 'misskey.test', $key );
	$row_hoto = axismundi_emoji_get( 'hoto.test', $key );

	/* ---------------------------------------------------------------- *
	 * 1. Same name, two servers, two rows
	 * ---------------------------------------------------------------- */

	ax_r_assert(
		$ax_r_results,
		'the same :misskey: name from two servers is two registry rows with two files',
		is_array( $row_io ) && is_array( $row_hoto )
			&& (int) $row_io['id'] !== (int) $row_hoto['id']
			&& $row_io['content_hash'] !== $row_hoto['content_hash']
	);

	// Each declaring Object renders its own authority's file and no other.
	$from_io   = axismundi_emoji_decorate( 'hello :' . $key . ': there', axismundi_emoji_declaration_map( ax_r_payload( 'https://misskey.test/notes/1', array( array( 'misskey.test', $key ) ) ), 'https://misskey.test/notes/1' ) );
	$from_hoto = axismundi_emoji_decorate( 'hello :' . $key . ': there', axismundi_emoji_declaration_map( ax_r_payload( 'https://hoto.test/notes/1', array( array( 'hoto.test', $key ) ) ), 'https://hoto.test/notes/1' ) );

	ax_r_assert(
		$ax_r_results,
		'an Object declaring one server\'s emoji renders that server\'s file',
		str_contains( $from_io, (string) $row_io['cached_path'] ) && ! str_contains( $from_io, (string) $row_hoto['cached_path'] )
	);
	ax_r_assert(
		$ax_r_results,
		'the identical shortcode in the other server\'s Object renders the other file instead',
		str_contains( $from_hoto, (string) $row_hoto['cached_path'] ) && ! str_contains( $from_hoto, (string) $row_io['cached_path'] )
	);

	/* ---------------------------------------------------------------- *
	 * 1b. Explicit presentation fallbacks do not alter declaration identity
	 * ---------------------------------------------------------------- */

	axismundi_emoji_set_authority_default( 'misskey.test', 'pending', 1, 10 );
	$wpdb->update( axismundi_emoji_table(), array( 'review_status' => 'rejected' ), array( 'id' => $hoto_moe ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	$rejected_map = axismundi_emoji_declaration_map( ax_r_payload( 'https://hoto.test/notes/rejected', array( array( 'hoto.test', $key ) ) ), 'https://hoto.test/notes/rejected' );
	$rejected     = axismundi_emoji_decorate( ':' . $key . ':', $rejected_map );
	ax_r_assert(
		$ax_r_results,
		'a rejected declaration remains plain text even when a local or fallback source has the same name',
		':' . $key . ':' === $rejected
	);
	$wpdb->update( axismundi_emoji_table(), array( 'review_status' => 'pending', 'cached_path' => '' ), array( 'id' => $hoto_moe ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	$fallback_map = axismundi_emoji_declaration_map( ax_r_payload( 'https://hoto.test/notes/fallback', array( array( 'hoto.test', $key ) ) ), 'https://hoto.test/notes/fallback' );
	$fallback     = axismundi_emoji_decorate( ':' . $key . ':', $fallback_map );
	ax_r_assert(
		$ax_r_results,
		'a configured fallback authority supplies a bare name when the declaring authority has no cached bytes',
		str_contains( $fallback, (string) $row_io['cached_path'] )
	);
	$mixed_case = axismundi_emoji_decorate( ':Ax_Shared_Probe:', $fallback_map );
	ax_r_assert(
		$ax_r_results,
		'a fallback image retains the author\'s exact token in alt and title rather than borrowing the source row\'s casing',
		str_contains( $mixed_case, 'alt=":Ax_Shared_Probe:"' ) && str_contains( $mixed_case, 'title=":Ax_Shared_Probe:"' )
	);
	$tied_fallback = ax_r_seed( 'tie.test', $key );
	$ax_r_ids[]    = $tied_fallback;
	axismundi_emoji_set_authority_default( 'tie.test', 'pending', 1, 10 );
	$tied = axismundi_emoji_decorate( ':' . $key . ':', $fallback_map );
	ax_r_assert(
		$ax_r_results,
		'two fallback authorities at the same winning priority leave a colliding bare name as text rather than choosing by host name',
		':' . $key . ':' === $tied
	);
	axismundi_emoji_set_authority_default( 'tie.test', 'pending', 1, 20 );
	$untied = axismundi_emoji_decorate( ':' . $key . ':', $fallback_map );
	ax_r_assert(
		$ax_r_results,
		'a lower fallback priority leaves the representative source deterministic again',
		str_contains( $untied, (string) $row_io['cached_path'] )
	);
	ax_r_assert(
		$ax_r_results,
		'an authority-qualified name never crosses the configured fallback boundary',
		':' . $key . '@hoto.test:' === axismundi_emoji_decorate( ':' . $key . '@hoto.test:', $fallback_map )
	);
	$local_shared = ax_r_seed( 'local.test', $key, false, 'local' );
	$ax_r_ids[]   = $local_shared;
	$local_row    = axismundi_emoji_get( 'local.test', $key );
	$local_first  = axismundi_emoji_decorate( ':' . $key . ':', $fallback_map );
	ax_r_assert(
		$ax_r_results,
		'a local same-named emoji wins over an explicitly configured remote fallback',
		is_array( $local_row ) && str_contains( $local_first, (string) $local_row['cached_path'] )
	);
	$wpdb->delete( axismundi_emoji_table(), array( 'id' => $local_shared ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup before the scoped tests below.
	$wpdb->update( axismundi_emoji_table(), array( 'review_status' => 'approved', 'cached_path' => $row_hoto['cached_path'] ), array( 'id' => $hoto_moe ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- restore the original declaration case.
	$wpdb->delete( axismundi_emoji_authorities_table(), array( 'emoji_authority' => 'misskey.test' ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->delete( axismundi_emoji_authorities_table(), array( 'emoji_authority' => 'tie.test' ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.

	/* ---------------------------------------------------------------- *
	 * 2. Ambiguity is not resolved by guessing
	 * ---------------------------------------------------------------- */

	$both = axismundi_emoji_declaration_map(
		ax_r_payload( 'https://misskey.test/notes/2', array( array( 'misskey.test', $key ), array( 'hoto.test', $key ) ) ),
		'https://misskey.test/notes/2'
	);
	$ambiguous = axismundi_emoji_decorate( 'look: :' . $key . ': !', $both );
	ax_r_assert(
		$ax_r_results,
		'one Object declaring two same-named emoji leaves a bare shortcode as text rather than picking one',
		str_contains( $ambiguous, ':' . $key . ':' ) && ! str_contains( $ambiguous, '<img' )
	);

	// Qualifying it removes the ambiguity, so it renders — and renders the right one.
	$qualified = axismundi_emoji_decorate( 'look: :' . $key . '@hoto.test: !', $both );
	ax_r_assert(
		$ax_r_results,
		'an authority-qualified name is unambiguous even when the bare form is not',
		str_contains( $qualified, (string) $row_hoto['cached_path'] ) && ! str_contains( $qualified, (string) $row_io['cached_path'] )
	);

	/* ---------------------------------------------------------------- *
	 * 3. Admin and picker surfaces disambiguate
	 * ---------------------------------------------------------------- */

	// At least two, not exactly two: a real instance this site has observed may legitimately
	// claim the same name, and the contract is that the collision is reported, not its size.
	$collisions = axismundi_emoji_colliding_keys();
	ax_r_assert( $ax_r_results, 'the registry can report which shortcode names more than one server claims', ( $collisions[ $key ] ?? 0 ) >= 2 );
	ax_r_assert(
		$ax_r_results,
		'a colliding name is shown qualified in listings, so two rows are not both labelled :misskey:',
		':' . $key . '@hoto.test:' === axismundi_emoji_display_shortcode( $row_hoto, true )
			&& ':' . $key . '@misskey.test:' === axismundi_emoji_display_shortcode( $row_io, true )
	);
	ax_r_assert(
		$ax_r_results,
		'a name nothing else claims is left unqualified, since the suffix would only be noise',
		':09_bird:' === axismundi_emoji_display_shortcode( (array) axismundi_emoji_get( 'hoto.test', '09_bird' ), false )
	);

	/* ---------------------------------------------------------------- *
	 * Scope: substitution is never a global name lookup
	 * ---------------------------------------------------------------- */

	$undeclared = axismundi_emoji_decorate( 'plain :' . $key . ': text', array() );
	ax_r_assert( $ax_r_results, 'a subject that declared nothing renders no emoji, however many the registry holds', 'plain :' . $key . ': text' === $undeclared );

	$other_map = axismundi_emoji_declaration_map( ax_r_payload( 'https://hoto.test/notes/3', array( array( 'hoto.test', '09_bird' ) ) ), 'https://hoto.test/notes/3' );
	$wrong_one = axismundi_emoji_decorate( ':' . $key . ': and :09_bird:', $other_map );
	ax_r_assert(
		$ax_r_results,
		'only the declared shortcode is replaced; an undeclared one stays text even though the registry has it',
		str_contains( $wrong_one, ':' . $key . ':' ) && str_contains( $wrong_one, '<img' )
	);

	/* ---------------------------------------------------------------- *
	 * WordPress smilies coexistence
	 * ---------------------------------------------------------------- */

	$cool = ax_r_seed( 'misskey.test', 'cool' );
	$ax_r_ids[] = $cool;
	$cool_map   = axismundi_emoji_declaration_map( ax_r_payload( 'https://misskey.test/notes/4', array( array( 'misskey.test', 'cool' ) ) ), 'https://misskey.test/notes/4' );
	$declared   = axismundi_emoji_decorate( 'yes :cool:', $cool_map );
	$core_owned = axismundi_emoji_decorate( 'yes :cool:', array() );
	ax_r_assert( $ax_r_results, 'a declared :cool: becomes the custom emoji', str_contains( $declared, '<img' ) );
	ax_r_assert( $ax_r_results, 'an undeclared :cool: is left for WordPress smilies exactly as the site configured them', 'yes :cool:' === $core_owned );

	/* ---------------------------------------------------------------- *
	 * Where substitution may and may not happen
	 * ---------------------------------------------------------------- */

	$map_io = axismundi_emoji_declaration_map( ax_r_payload( 'https://misskey.test/notes/5', array( array( 'misskey.test', $key ) ) ), 'https://misskey.test/notes/5' );

	ax_r_assert( $ax_r_results, 'a shortcode inside <code> is documentation and stays text', str_contains( axismundi_emoji_decorate( '<code>:' . $key . ':</code>', $map_io ), '<code>:' . $key . ':</code>' ) );
	ax_r_assert( $ax_r_results, 'the same holds inside <pre>', str_contains( axismundi_emoji_decorate( '<pre>:' . $key . ':</pre>', $map_io ), '<pre>:' . $key . ':</pre>' ) );
	ax_r_assert( $ax_r_results, 'an attribute value is never rewritten', str_contains( axismundi_emoji_decorate( '<a title=":' . $key . ':">x</a>', $map_io ), 'title=":' . $key . ':"' ) );
	ax_r_assert( $ax_r_results, 'a URL containing the pattern is not rewritten, because it lives in an attribute', str_contains( axismundi_emoji_decorate( '<a href="https://e.example/a:' . $key . ':b">x</a>', $map_io ), 'a:' . $key . ':b' ) );
	ax_r_assert( $ax_r_results, 'ordinary body text is decorated', str_contains( axismundi_emoji_decorate( '<p>hi :' . $key . ':</p>', $map_io ), '<img' ) );

	/* ---------------------------------------------------------------- *
	 * Markup contract
	 * ---------------------------------------------------------------- */

	$markup = axismundi_emoji_image_markup( (array) $row_io );
	ax_r_assert( $ax_r_results, 'alt and title reproduce the original shortcode, so text extraction matches the plain-text surfaces', str_contains( $markup, 'alt=":' . $key . ':"' ) && str_contains( $markup, 'title=":' . $key . ':"' ) );
	ax_r_assert( $ax_r_results, 'a still emoji needs no <picture>', ! str_contains( $markup, '<picture' ) );
	// A glyph inside a word should not arrive after the word does. Core excludes small and
	// first-viewport images from lazy loading for the same reason.
	ax_r_assert( $ax_r_results, 'an emoji is not lazy-loaded, since it is a glyph inside a line rather than a content image', ! str_contains( $markup, 'loading=' ) );

	$animated_markup = axismundi_emoji_image_markup( (array) axismundi_emoji_get( 'hoto.test', '09_bird' ) );
	ax_r_assert(
		$ax_r_results,
		'an animated emoji offers its still to prefers-reduced-motion',
		str_contains( $animated_markup, '<picture class="ax-emoji-picture"' ) && str_contains( $animated_markup, 'media="(prefers-reduced-motion: reduce)"' )
	);

	$emoji_css = file_get_contents( dirname( __DIR__ ) . '/assets/emoji.css' );
	ax_r_assert(
		$ax_r_results,
		'custom emoji inherit the surrounding font size rather than their source image dimensions',
		is_string( $emoji_css ) && str_contains( $emoji_css, 'img.ax-emoji' ) && str_contains( $emoji_css, 'inline-size: 1em' ) && str_contains( $emoji_css, 'block-size: 1em' )
	);
	ax_r_assert(
		$ax_r_results,
		'animated emoji retain that same one-em box through their picture wrapper',
		is_string( $emoji_css ) && str_contains( $emoji_css, 'picture.ax-emoji-picture' ) && str_contains( $emoji_css, 'picture.ax-emoji-picture > img.ax-emoji' )
	);

	/* ---------------------------------------------------------------- *
	 * Only approved and cached emoji render
	 * ---------------------------------------------------------------- */

	$wpdb->update( axismundi_emoji_table(), array( 'review_status' => 'pending' ), array( 'id' => $misskey_io ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	$unapproved = axismundi_emoji_decorate( 'hi :' . $key . ':', axismundi_emoji_declaration_map( ax_r_payload( 'https://misskey.test/notes/6', array( array( 'misskey.test', $key ) ) ), 'https://misskey.test/notes/6' ) );
	ax_r_assert( $ax_r_results, 'an emoji awaiting review renders as its shortcode, not as an image', 'hi :' . $key . ':' === $unapproved );

	$wpdb->update( axismundi_emoji_table(), array( 'review_status' => 'approved', 'cached_path' => '' ), array( 'id' => $misskey_io ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	$uncached = axismundi_emoji_decorate( 'hi :' . $key . ':', axismundi_emoji_declaration_map( ax_r_payload( 'https://misskey.test/notes/7', array( array( 'misskey.test', $key ) ) ), 'https://misskey.test/notes/7' ) );
	ax_r_assert( $ax_r_results, 'an approved emoji whose bytes have not arrived yet also stays text', 'hi :' . $key . ':' === $uncached );
} catch ( Throwable $error ) {
	ax_r_assert( $ax_r_results, 'the renderer suite ran to completion: ' . $error->getMessage(), false );
} finally {
	foreach ( array_unique( $ax_r_ids ) as $id ) {
		$wpdb->delete( axismundi_emoji_table(), array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
}

$ax_r_failures = count( array_filter( $ax_r_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_r_results ), $ax_r_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_r_failures > 0 ? 1 : 0 );
}
exit( $ax_r_failures > 0 ? 1 : 0 );
