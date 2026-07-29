<?php
/**
 * Display fallback for reaction chips (dev-only; dist-excluded).
 *
 * The point of this path is storage. A reaction naming `hoto.moe/:misskey:` can be shown
 * without ever downloading hoto.moe's copy, as long as one designated authority's copy of
 * that name is reviewed and cached:
 *
 *   hoto.moe/:misskey:   pending, no bytes
 *     └─ drawn as ──>    rep.example/:misskey:   approved, cached
 *
 * What must not move is the reaction itself. The key stays `custom:hoto.moe:misskey`, the
 * label stays the shortcode its sender wrote, and the count stays partitioned by that key,
 * so the borrowed picture changes the appearance and nothing that can be aggregated,
 * federated, or counted.
 *
 * This is the one place a *qualified* name may be substituted, and the exception is
 * deliberate. Inline text refuses, because there the author wrote the authority into the
 * sentence and swapping the picture would change what the sentence says. A chip is not a
 * sentence: it is an icon plus a number, and left as bare `:misskey:` text it stops doing
 * its job.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_fb_results = array();
$ax_fb_rows    = array();
$ax_fb_auths   = array();

/** @param bool[] $results Results. */
function ax_fb_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	/*
	 * A shortcode nobody else on this install uses. Reusing a real name would silently test
	 * the site's own `:misskey:` instead of the fixture, and every assertion would pass for
	 * the wrong reason.
	 */
	$ax_fb_key   = 'fb' . strtolower( wp_generate_password( 10, false, false ) );
	$ax_fb_table = axismundi_emoji_table();
	$ax_fb_auth  = axismundi_emoji_authorities_table();
	$ax_fb_now   = current_time( 'mysql', true );

	$ax_fb_seed = static function ( string $authority, string $review, ?string $cached, string $license = 'unknown', int $sensitive = 0 ) use ( $wpdb, $ax_fb_table, $ax_fb_now, $ax_fb_key, &$ax_fb_rows ) : void {
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$ax_fb_table,
			array(
				'scope'           => 'remote',
				'emoji_authority' => $authority,
				'shortcode'       => ':' . $ax_fb_key . ':',
				'shortcode_key'   => $ax_fb_key,
				'review_status'   => $review,
				'cached_path'     => $cached,
				'license_state'   => $license,
				'is_sensitive'    => $sensitive,
				'first_seen_at'   => $ax_fb_now,
				'last_seen_at'    => $ax_fb_now,
			)
		);
		$ax_fb_rows[] = (int) $wpdb->insert_id;
	};
	$ax_fb_set = static function ( string $authority, array $fields ) use ( $wpdb, $ax_fb_table, $ax_fb_key ) : void {
		$wpdb->update( $ax_fb_table, $fields, array( 'emoji_authority' => $authority, 'shortcode_key' => $ax_fb_key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	};
	/** @return array{file:string,borrowed:?bool} */
	$ax_fb_chip = static function () use ( $ax_fb_key ) : array {
		$presentation = axismundi_act_reaction_presentation( 'custom:hoto.example:' . $ax_fb_key, ':' . $ax_fb_key . ':' );
		return array(
			'file'     => is_array( $presentation['image'] ) ? basename( (string) $presentation['image']['url'] ) : '',
			'borrowed' => is_array( $presentation['image'] ) ? (bool) $presentation['image']['borrowed'] : null,
			'label'    => (string) $presentation['label'],
		);
	};

	$ax_fb_seed( 'hoto.example', 'pending', null );
	$ax_fb_seed( 'rep.example', 'approved', 'ab/cd/rep.png' );
	$wpdb->insert( $ax_fb_auth, array( 'emoji_authority' => 'rep.example', 'review_default' => 'pending', 'fallback_priority' => 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$ax_fb_auths[] = 'rep.example';

	if ( ! function_exists( 'axismundi_act_reaction_presentation' ) ) {
		ax_fb_assert( $ax_fb_results, 'Activities is available to exercise the chip entry point', false );
	} else {
		// -- The saving this exists for ---------------------------------------------------

		$ax_fb_borrowed = $ax_fb_chip();
		ax_fb_assert( $ax_fb_results, 'an uncached declaration is drawn with a designated authority\'s copy', 'rep.png' === $ax_fb_borrowed['file'] );
		ax_fb_assert( $ax_fb_results, 'and the chip says the picture was borrowed rather than passing it off as the original', true === $ax_fb_borrowed['borrowed'] );
		ax_fb_assert( $ax_fb_results, 'while the label still reads the shortcode its sender wrote', ':' . $ax_fb_key . ':' === $ax_fb_borrowed['label'] );

		/*
		 * Identity is the thing that must not move. If borrowing a picture could change the
		 * key, two servers' emoji would merge into one chip and the count would be a fiction.
		 */
		$ax_fb_normalized = function_exists( 'axismundi_act_normalize_reaction' )
			? axismundi_act_normalize_reaction(
				':' . $ax_fb_key . ':',
				array( 'type' => 'Emoji', 'name' => ':' . $ax_fb_key . ':', 'id' => 'https://hoto.example/emojis/' . $ax_fb_key, 'icon' => array( 'type' => 'Image', 'url' => 'https://hoto.example/e.png' ) )
			)
			: null;
		ax_fb_assert(
			$ax_fb_results,
			'the reaction key still names the declaring authority, not the one that lent the image',
			'custom:hoto.example:' . $ax_fb_key === (string) ( $ax_fb_normalized['key'] ?? '' )
		);

		// -- The original always outranks a stand-in --------------------------------------

		$ax_fb_set( 'hoto.example', array( 'review_status' => 'approved', 'cached_path' => 'ff/ee/own.png' ) );
		$ax_fb_own = $ax_fb_chip();
		ax_fb_assert( $ax_fb_results, 'once the declaring authority\'s own bytes are held, those are what is shown', 'own.png' === $ax_fb_own['file'] );
		ax_fb_assert( $ax_fb_results, 'and nothing is marked borrowed', false === $ax_fb_own['borrowed'] );

		// -- Conditions that withdraw the loan --------------------------------------------

		$ax_fb_set( 'hoto.example', array( 'review_status' => 'rejected', 'cached_path' => null ) );
		ax_fb_assert( $ax_fb_results, 'a rejection is a decision about rendering, so no stand-in may quietly reverse it', '' === $ax_fb_chip()['file'] );

		/*
		 * A restrictive licence never blocked rendering the original -- showing a message as
		 * its author wrote it is not re-using their asset. Putting a different file under
		 * their name is a different act, and their terms give no standing for it.
		 */
		$ax_fb_set( 'hoto.example', array( 'review_status' => 'pending', 'license_state' => 'restricted' ) );
		ax_fb_assert( $ax_fb_results, 'a restricted original is not given somebody else\'s picture', '' === $ax_fb_chip()['file'] );

		$ax_fb_set( 'hoto.example', array( 'license_state' => 'unknown', 'is_sensitive' => 1 ) );
		ax_fb_assert( $ax_fb_results, 'nor is one somebody flagged sensitive', '' === $ax_fb_chip()['file'] );

		$ax_fb_set( 'hoto.example', array( 'is_sensitive' => 0 ) );
		$ax_fb_set( 'rep.example', array( 'is_sensitive' => 1 ) );
		ax_fb_assert( $ax_fb_results, 'and a flagged candidate never stands in for an unflagged declaration', '' === $ax_fb_chip()['file'] );

		// The whole point of requiring `approved + cached` of the *candidate*: anything less
		// either bypasses the review gate or emits a URL with no file behind it.
		$ax_fb_set( 'rep.example', array( 'is_sensitive' => 0, 'review_status' => 'pending' ) );
		ax_fb_assert( $ax_fb_results, 'an unreviewed candidate cannot lend an image, which would bypass the review gate', '' === $ax_fb_chip()['file'] );

		$ax_fb_set( 'rep.example', array( 'review_status' => 'approved', 'cached_path' => null ) );
		ax_fb_assert( $ax_fb_results, 'and one with no cached bytes cannot either, which would emit a broken image', '' === $ax_fb_chip()['file'] );

		$ax_fb_set( 'rep.example', array( 'cached_path' => 'ab/cd/rep.png' ) );
		$wpdb->update( $ax_fb_auth, array( 'fallback_priority' => 0 ), array( 'emoji_authority' => 'rep.example' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		ax_fb_assert( $ax_fb_results, 'an authority nobody designated lends nothing, however well-stocked it is', '' === $ax_fb_chip()['file'] );

		/*
		 * A tie is a second namespace collision. Breaking it by host name would be inventing
		 * an answer, so the honest output is the shortcode text.
		 */
		$wpdb->update( $ax_fb_auth, array( 'fallback_priority' => 1 ), array( 'emoji_authority' => 'rep.example' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ax_fb_seed( 'tie.example', 'approved', 'zz/yy/tie.png' );
		$wpdb->insert( $ax_fb_auth, array( 'emoji_authority' => 'tie.example', 'review_default' => 'pending', 'fallback_priority' => 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ax_fb_auths[] = 'tie.example';
		ax_fb_assert( $ax_fb_results, 'two sources at the same rank is no answer, so the chip falls back to text', '' === $ax_fb_chip()['file'] );

		// -- A site-owned emoji is the operator's clearest statement of intent -------------

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$ax_fb_table,
			array(
				'scope'           => 'local',
				'emoji_authority' => axismundi_emoji_local_authority(),
				'shortcode'       => ':' . $ax_fb_key . ':',
				'shortcode_key'   => $ax_fb_key,
				'review_status'   => 'approved',
				'cached_path'     => 'ef/gh/site-own.png',
				'first_seen_at'   => $ax_fb_now,
				'last_seen_at'    => $ax_fb_now,
			)
		);
		$ax_fb_rows[] = (int) $wpdb->insert_id;
		$ax_fb_local  = $ax_fb_chip();
		ax_fb_assert( $ax_fb_results, 'this site\'s own emoji outranks any designated remote source, and settles the tie above', 'site-own.png' === $ax_fb_local['file'] );
		ax_fb_assert( $ax_fb_results, 'and is still reported as borrowed, because the reaction is not ours', true === $ax_fb_local['borrowed'] );
	}

	// -- Inline text keeps refusing what the chip allows ----------------------------------

	/*
	 * The boundary this whole exception rests on. If a qualified inline name ever starts
	 * substituting, the exception stops being an exception and remote message bodies begin
	 * changing meaning.
	 */
	$ax_fb_map  = array( $ax_fb_key => array( 'hoto.example' => array( 'emoji_authority' => 'hoto.example', 'shortcode_key' => $ax_fb_key, 'shortcode' => ':' . $ax_fb_key . ':' ) ) );
	$ax_fb_text = axismundi_emoji_replace_in_text( 'a :' . $ax_fb_key . '@hoto.example: b', $ax_fb_map );
	ax_fb_assert( $ax_fb_results, 'a qualified name in body text is still left as written, never substituted', str_contains( $ax_fb_text, ':' . $ax_fb_key . '@hoto.example:' ) );
} finally {
	foreach ( array_unique( $ax_fb_rows ) as $ax_fb_row ) {
		$wpdb->delete( axismundi_emoji_table(), array( 'id' => (int) $ax_fb_row ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( array_unique( $ax_fb_auths ) as $ax_fb_authority ) {
		$wpdb->delete( axismundi_emoji_authorities_table(), array( 'emoji_authority' => (string) $ax_fb_authority ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}

$ax_fb_failures = count( array_filter( $ax_fb_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_fb_results ), $ax_fb_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_fb_failures > 0 ? 1 : 0 );
}
exit( $ax_fb_failures > 0 ? 1 : 0 );
