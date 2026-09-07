<?php
/**
 * Publishing this site's own emoji (docs §8).
 *
 * Everything before this file was about receiving. Sending inverts the burden: a
 * shortcode we write is meaningless to every other server unless the message carries the
 * declaration that explains it, because that is precisely how we resolve theirs. An
 * unaccompanied `:axismundi:` arrives as four words and a colon.
 *
 * So the rule is symmetric with the renderer. We substitute only what a document
 * declared; therefore we declare everything our own document uses — and nothing it does
 * not, since a `tag[]` full of emoji that appear nowhere in the text is noise other
 * servers must store.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit;

/** Rewrite base for the AS2 representation of a local emoji. */
const AXISMUNDI_EMOJI_ROUTE_BASE = 'emojis';

/**
 * The shortcodes a piece of local text actually uses.
 *
 * FEP-9098's Compatibility section asks that a shortcode sit between characters that are
 * neither alphanumeric nor a colon, and honouring that on the way out means honouring it
 * on the way in too: `http://x/a:b:c` and `10:30:00` contain colon-delimited runs that
 * are not emoji, and a naive match would declare `:30:` as one. The boundary check is
 * what separates a shortcode from punctuation that happens to look like one.
 *
 * @param string $text Local text, plain or HTML.
 * @return string[] Normalized shortcode keys, in first-use order.
 */
function axismundi_emoji_tokenize( string $text ) : array {
	if ( false === strpos( $text, ':' ) ) {
		return array();
	}
	/*
	 * Tags are stripped rather than walked. Unlike the renderer — which must not touch
	 * `<code>` — the question here is only "does this document use the emoji", and a
	 * shortcode written inside a code sample is still a shortcode a reader sees. What must
	 * not count is markup: a `:` inside an attribute or a URL is not a use.
	 */
	$plain = wp_strip_all_tags( $text );
	if ( ! preg_match_all( '/(?<![a-zA-Z0-9_:]):([a-zA-Z0-9_]{2,}):(?![a-zA-Z0-9_:])/', $plain, $matches ) ) {
		return array();
	}
	$keys = array();
	foreach ( $matches[1] as $name ) {
		$key = strtolower( $name );
		if ( ! isset( $keys[ $key ] ) ) {
			$keys[ $key ] = true;
		}
	}
	return array_keys( $keys );
}

/**
 * The canonical URL of one local emoji.
 *
 * FEP-9098 gives an Emoji an `id`, and both Mastodon and Misskey publish a dereferenceable
 * one — which is what lets a receiver enrich beyond the tag, exactly as our own
 * verification worker does with theirs. Publishing a bare tag with no `id` would ask of
 * others what we do not do ourselves.
 *
 * @param array<string,mixed> $row Local registry row.
 * @return string
 */
function axismundi_emoji_object_id( array $row ) : string {
	$key = (string) ( $row['shortcode_key'] ?? '' );
	return '' === $key ? '' : home_url( '/' . AXISMUNDI_EMOJI_ROUTE_BASE . '/' . rawurlencode( $key ) );
}

/**
 * One local emoji as an AS2 `Emoji` object.
 *
 * Deliberately the same shape we parse on the way in, minus the parts that are somebody
 * else's convention: no `_misskey_license`, because a vendor-prefixed key is a thing to
 * read tolerantly and not a thing to emit.
 *
 * @param array<string,mixed> $row Local registry row.
 * @return array<string,mixed>
 */
function axismundi_emoji_as2_object( array $row ) : array {
	/*
	 * Falling back rather than omitting. `updated` is what lets a receiver's cache expire,
	 * so an emoji published without one is cached elsewhere forever — and rows registered
	 * before this column was written would be exactly that. When the emoji has never been
	 * edited, the moment it came into being here is the honest answer and no migration is
	 * needed to supply it.
	 */
	$updated = (string) ( $row['updated_at'] ?? '' );
	if ( '' === $updated ) {
		$updated = (string) ( $row['reviewed_at'] ?? $row['first_seen_at'] ?? '' );
	}
	$object  = array(
		'id'   => axismundi_emoji_object_id( $row ),
		'type' => 'Emoji',
		'name' => (string) $row['shortcode'],
		'icon' => array(
			'type'      => 'Image',
			'mediaType' => (string) ( $row['media_type'] ?? 'image/png' ),
			'url'       => axismundi_emoji_file_url( $row ),
		),
	);
	if ( '' !== $updated ) {
		// The invalidation signal §3 relies on. Without it a receiver caches our first
		// version forever and never learns that we replaced the picture.
		$object['updated'] = gmdate( DATE_W3C, (int) strtotime( $updated . ' UTC' ) );
	}
	return $object;
}

/**
 * The `tag[]` entries for a set of local texts.
 *
 * Takes several strings rather than one because a document has several: a Note's content,
 * an Article's name and summary, an Actor's `nameMap` and `summaryMap`. Emoji are declared
 * once for the document as a whole, not per language — `tag[]` has no language axis and
 * needs none, since the same `:axismundi:` in a Korean and an English biography is the same
 * emoji. The union is taken over every variant, deduplicated by shortcode.
 *
 * @param string[] $texts Local texts that will be published.
 * @return array<int,array<string,mixed>>
 */
function axismundi_emoji_outbound_tags( array $texts ) : array {
	if ( ! axismundi_emoji_ready() ) {
		return array();
	}
	$keys = array();
	foreach ( $texts as $text ) {
		foreach ( axismundi_emoji_tokenize( (string) $text ) as $key ) {
			$keys[ $key ] = true;
		}
	}
	if ( array() === $keys ) {
		return array();
	}

	global $wpdb;
	$table        = axismundi_emoji_table();
	$placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
	/*
	 * `outbound_allowed` is the whole gate, and it already carries the licence and
	 * `localOnly` decisions — those were settled when the row was written, not here, so
	 * this cannot disagree with the Local screen. An emoji withheld from publication still
	 * renders at home; the shortcode simply travels alone, which is what Misskey does with
	 * its own `localOnly` emoji.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; values prepared.
	$rows = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table}
			  WHERE scope = 'local'
			    AND outbound_allowed = 1
			    AND review_status = 'approved'
			    AND cached_path IS NOT NULL AND cached_path <> ''
			    AND shortcode_key IN ({$placeholders})
			  ORDER BY shortcode_key ASC",
			array_keys( $keys )
		),
		ARRAY_A
	);

	$tags = array();
	foreach ( $rows as $row ) {
		$object = axismundi_emoji_as2_object( $row );
		if ( '' !== (string) $object['icon']['url'] ) {
			$tags[] = $object;
		}
	}
	return $tags;
}

/**
 * Serve the AS2 document for one local emoji.
 *
 * Answered from `parse_request` rather than by redirecting, for the reason the Actors
 * profile route learned the hard way: a rewrite that 301s to a canonical form can meet
 * WordPress's own canonical redirect coming the other way, and the pair loops.
 *
 * @param WP $wp Environment.
 * @return void
 */
function axismundi_emoji_serve_route( WP $wp ) : void {
	$key = (string) ( $wp->query_vars['ax_emoji'] ?? '' );
	if ( '' === $key ) {
		$path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- matched against a strict pattern below.
		if ( ! preg_match( '#/' . AXISMUNDI_EMOJI_ROUTE_BASE . '/([a-zA-Z0-9_]{2,})/?$#', $path, $matches ) ) {
			return;
		}
		$key = $matches[1];
	}
	$row = axismundi_emoji_local_get( strtolower( $key ) );
	if ( ! is_array( $row ) || '' === (string) ( $row['cached_path'] ?? '' ) ) {
		/*
		 * Answer, rather than falling through. Returning here leaves the request to
		 * WordPress's canonical redirect, which appends a slash and then serves 200 — so a
		 * shortcode that does not exist reports success, and a receiver dereferencing an id
		 * we never published gets a page instead of an answer. This route is claimed, so a
		 * miss on it is a 404 and nothing else. (The redirect-then-200 shape is also how
		 * the Actors profile route once turned into a loop in production.)
		 */
		status_header( 404 );
		nocache_headers();
		header( 'Content-Type: application/activity+json; charset=utf-8' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON document, encoded below.
		echo wp_json_encode( array( 'error' => 'Not Found' ), JSON_UNESCAPED_SLASHES );
		exit;
	}
	$document = array_merge( array( '@context' => 'https://www.w3.org/ns/activitystreams' ), axismundi_emoji_as2_object( $row ) );
	status_header( 200 );
	header( 'Content-Type: application/activity+json; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON document, encoded below.
	echo wp_json_encode( $document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}
add_action( 'parse_request', 'axismundi_emoji_serve_route', 5 );

/** @param string[] $vars Query vars. @return string[] */
function axismundi_emoji_query_vars( array $vars ) : array {
	$vars[] = 'ax_emoji';
	return $vars;
}
add_filter( 'query_vars', 'axismundi_emoji_query_vars' );

/** @return string The rewrite pattern this plugin owns. */
function axismundi_emoji_rewrite_pattern() : string {
	return '^' . AXISMUNDI_EMOJI_ROUTE_BASE . '/([a-zA-Z0-9_]{2,})/?$';
}

/** @return void */
function axismundi_emoji_add_rewrite() : void {
	add_rewrite_rule( axismundi_emoji_rewrite_pattern(), 'index.php?ax_emoji=$matches[1]', 'top' );
}
add_action( 'init', 'axismundi_emoji_add_rewrite' );

/**
 * Install the emoji route whenever it is missing, rather than once per version counter.
 *
 * The same self-healing check Actors settled on, for the same reason: a version counter
 * records an *intent* to flush and then burns itself whether or not the flush persisted —
 * `flush_rewrite_rules()` returns void, so it can never tell. Object Projections shipped
 * exactly that gate once and every `/media/folder/{uuid}` 404'd on a live site.
 *
 * It matters more here than it looks. Without the rule a request for `/emojis/name` is not
 * merely unanswered: it reaches WordPress's canonical redirect instead, which appends a
 * slash and serves 200 — so an `id` we published in somebody's `tag[]` starts resolving to
 * a page. Answering or 404ing are both fine; quietly returning a page is not.
 *
 * @return void
 */
function axismundi_emoji_maybe_install_rewrite() : void {
	// Plain permalinks keep no rewrite table at all, so there is nothing to install and
	// nothing to compare against; the parse_request fallback covers that configuration.
	if ( '' === (string) get_option( 'permalink_structure', '' ) ) {
		return;
	}
	$rules = get_option( 'rewrite_rules' );
	if ( is_array( $rules ) && isset( $rules[ axismundi_emoji_rewrite_pattern() ] ) ) {
		return;
	}
	// Bound the retry so a rule that can never persist degrades to one flush an hour
	// rather than one per request.
	if ( get_transient( 'ax_emoji_rewrite_retry' ) ) {
		return;
	}
	set_transient( 'ax_emoji_rewrite_retry', 1, HOUR_IN_SECONDS );
	flush_rewrite_rules( false );
}
add_action( 'init', 'axismundi_emoji_maybe_install_rewrite', 11 );
