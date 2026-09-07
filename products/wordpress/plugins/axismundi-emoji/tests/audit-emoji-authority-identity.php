<?php
/**
 * Authority identity: one spelling, everywhere (dev-only; dist-excluded).
 *
 * FEP-9098 keys an emoji by `(name, domain)`, which makes the domain an identity rather
 * than a label. Two spellings of it are two emoji. The registry filed this site's own
 * emoji under the authority WebFinger addresses it by — with the port — while every path
 * that *referred* to an emoji derived a bare host, so on a site not served from 443 an
 * emoji could be stored and then never found again by the reactions and documents naming
 * it. Measured before the fix:
 *
 *   tag id             http://localhost:8884/emojis/axismundi
 *   reaction key       custom:localhost:axismundi
 *   registry authority localhost:8884           -> lookup missed, every time
 *
 * Nothing about that is visible on a production host, which is why it survived: on 443
 * the two spellings coincide. These checks fix the rule in place at both ends and pin the
 * upgrade that refiles rows written under the old one.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_ai_results = array();
$ax_ai_rows    = array();
$ax_ai_auths   = array();

/** @param bool[] $results Results. */
function ax_ai_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	// -- The rule itself -----------------------------------------------------------------

	ax_ai_assert( $ax_ai_results, 'an ordinary host is its own authority', 'a.example' === axismundi_emoji_url_authority( 'https://a.example/emojis/x' ) );
	ax_ai_assert( $ax_ai_results, 'a non-default port is part of the identity, not decoration', 'a.example:8443' === axismundi_emoji_url_authority( 'https://a.example:8443/emojis/x' ) );
	ax_ai_assert( $ax_ai_results, 'and a development host keeps its port too', 'localhost:8884' === axismundi_emoji_url_authority( 'http://localhost:8884/emojis/x' ) );
	/*
	 * The two spellings of the same origin must not become two authorities, or writing
	 * the port out explicitly would split one emoji in half.
	 */
	ax_ai_assert( $ax_ai_results, 'an explicitly written 443 is dropped under https', 'a.example' === axismundi_emoji_url_authority( 'https://a.example:443/emojis/x' ) );
	ax_ai_assert( $ax_ai_results, 'as is an explicitly written 80 under http', 'a.example' === axismundi_emoji_url_authority( 'http://a.example:80/emojis/x' ) );
	ax_ai_assert( $ax_ai_results, 'a host is lowercased, since a domain is case-insensitive', 'a.example:9000' === axismundi_emoji_url_authority( 'https://A.Example:9000/x' ) );
	ax_ai_assert( $ax_ai_results, 'and something with no host is no authority at all', '' === axismundi_emoji_url_authority( 'not a url' ) );

	// -- Both ends agree ------------------------------------------------------------------

	ax_ai_assert(
		$ax_ai_results,
		'this site files its own emoji under the authority its own URL resolves to',
		axismundi_emoji_local_authority() === axismundi_emoji_url_authority( home_url( '/' ) )
	);
	ax_ai_assert(
		$ax_ai_results,
		'a declaration resolves to that same authority, which is what makes a local emoji findable',
		axismundi_emoji_local_authority() === axismundi_emoji_resolve_authority(
			array( 'type' => 'Emoji', 'name' => ':axismundi:', 'id' => home_url( '/emojis/axismundi' ) ),
			''
		)
	);
	/*
	 * A qualified shortcode still wins over the URL. The emoji belongs to whoever the name
	 * says it belongs to; the document carrying it is only the messenger.
	 */
	ax_ai_assert(
		$ax_ai_results,
		'a qualified name still outranks the URL that carried it',
		'owner.example' === axismundi_emoji_resolve_authority(
			array( 'type' => 'Emoji', 'name' => ':borrowed@owner.example:', 'id' => 'https://relay.example:9000/emojis/borrowed' ),
			'relay.example'
		)
	);

	// The end the bug actually broke: a reaction naming this site's own emoji.
	if ( function_exists( 'axismundi_act_normalize_reaction' ) ) {
		$ax_ai_reaction = axismundi_act_normalize_reaction(
			':axismundi:',
			array( 'type' => 'Emoji', 'name' => ':axismundi:', 'id' => home_url( '/emojis/axismundi' ), 'icon' => array( 'type' => 'Image', 'url' => 'https://example.com/e.png' ) )
		);
		ax_ai_assert(
			$ax_ai_results,
			'a reaction to a local emoji keys on the authority the registry filed it under',
			'custom:' . axismundi_emoji_local_authority() . ':axismundi' === (string) ( $ax_ai_reaction['key'] ?? '' )
		);
		ax_ai_assert(
			$ax_ai_results,
			'so the emoji it names can actually be found',
			is_array( axismundi_emoji_get( axismundi_emoji_local_authority(), 'axismundi' ) )
		);
	}

	// -- The upgrade that refiles rows written under the old rule --------------------------

	$ax_ai_table = axismundi_emoji_table();
	$ax_ai_now   = current_time( 'mysql', true );
	$ax_ai_seed  = static function ( string $authority, string $key, string $shortcode, string $declared ) use ( $wpdb, $ax_ai_table, $ax_ai_now, &$ax_ai_rows ) : int {
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$ax_ai_table,
			array(
				'scope'           => 'remote',
				'emoji_authority' => $authority,
				'shortcode'       => $shortcode,
				'shortcode_key'   => $key,
				'declared_id'     => $declared,
				'review_status'   => 'approved',
				'first_seen_at'   => $ax_ai_now,
				'last_seen_at'    => $ax_ai_now,
			)
		);
		$id = (int) $wpdb->insert_id;
		$ax_ai_rows[] = $id;
		return $id;
	};
	$ax_ai_authority_of = static fn( int $id ) : string => (string) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT emoji_authority FROM {$ax_ai_table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

	$ax_ai_drift    = $ax_ai_seed( 'peer.example', 'drifted', ':drifted:', 'https://peer.example:8443/emojis/drifted' );
	$ax_ai_borrowed = $ax_ai_seed( 'owner.example', 'borrowed', ':borrowed@owner.example:', 'https://relay.example:9000/emojis/borrowed' );
	$ax_ai_target   = $ax_ai_seed( 'busy.example:7000', 'taken', ':taken:', 'https://busy.example:7000/emojis/taken' );
	$ax_ai_stale    = $ax_ai_seed( 'busy.example', 'taken', ':taken:', 'https://busy.example:7000/emojis/taken' );
	$ax_ai_plain    = $ax_ai_seed( 'plain.example', 'plain', ':plain:', 'https://plain.example:443/emojis/plain' );
	$wpdb->insert( axismundi_emoji_authorities_table(), array( 'emoji_authority' => 'peer.example', 'review_default' => 'approved', 'fallback_priority' => 3 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$ax_ai_auths[] = 'peer.example';
	$ax_ai_auths[] = 'peer.example:8443';

	axismundi_emoji_migrate_ported_authorities();

	ax_ai_assert( $ax_ai_results, 'a row stored under a bare host moves to the port its declaration names', 'peer.example:8443' === $ax_ai_authority_of( $ax_ai_drift ) );
	ax_ai_assert(
		$ax_ai_results,
		'a row whose authority came from a qualified name is left alone, since its URL is not its owner',
		'owner.example' === $ax_ai_authority_of( $ax_ai_borrowed )
	);
	/*
	 * Merging would mean choosing whose cached bytes and whose review decision survive.
	 * That is an operator's call, so the upgrade declines to make it.
	 */
	ax_ai_assert( $ax_ai_results, 'an occupied destination is not overwritten', 'busy.example:7000' === $ax_ai_authority_of( $ax_ai_target ) );
	ax_ai_assert( $ax_ai_results, 'and the stale duplicate is left in place rather than silently merged', 'busy.example' === $ax_ai_authority_of( $ax_ai_stale ) );
	ax_ai_assert( $ax_ai_results, 'a default port written out explicitly does not move anything', 'plain.example' === $ax_ai_authority_of( $ax_ai_plain ) );
	ax_ai_assert(
		$ax_ai_results,
		'the authority review policy follows its emoji, so an operator approval keeps applying',
		'peer.example:8443' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT emoji_authority FROM ' . axismundi_emoji_authorities_table() . ' WHERE emoji_authority = %s', 'peer.example:8443' ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	);

	// Running it twice must be a no-op, since upgrades re-run on every version check.
	axismundi_emoji_migrate_ported_authorities();
	ax_ai_assert( $ax_ai_results, 'and running the upgrade again changes nothing', 'peer.example:8443' === $ax_ai_authority_of( $ax_ai_drift ) && 'busy.example' === $ax_ai_authority_of( $ax_ai_stale ) );
} finally {
	foreach ( array_unique( $ax_ai_rows ) as $ax_ai_row ) {
		$wpdb->delete( axismundi_emoji_table(), array( 'id' => (int) $ax_ai_row ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( array_unique( $ax_ai_auths ) as $ax_ai_auth ) {
		$wpdb->delete( axismundi_emoji_authorities_table(), array( 'emoji_authority' => (string) $ax_ai_auth ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}

$ax_ai_failures = count( array_filter( $ax_ai_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ai_results ), $ax_ai_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ai_failures > 0 ? 1 : 0 );
}
exit( $ax_ai_failures > 0 ? 1 : 0 );
