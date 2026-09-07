<?php
/**
 * Turning declared shortcodes into images.
 *
 * Substitution is scoped to one subject's declarations, never a global search for
 * `:name:`. That is not a refinement, it is the whole correctness argument: shortcodes
 * are namespaced per instance, so `:misskey:` from misskey.io and `:misskey:` from
 * hoto.moe — both running Misskey, both shipping an emoji of that name — are different
 * pictures. A registry lookup by name alone would show one server's art under another
 * server's message.
 *
 * The registry keys on `(emoji_authority, shortcode_key)` for that reason, and the
 * renderer completes the argument by resolving a shortcode only against what the
 * Object or Actor carrying it actually declared in its own `tag[]`.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit;

/**
 * Index one subject's Emoji declarations by shortcode key.
 *
 * A key can carry more than one authority when a single Object declares two same-named
 * emoji from different servers. That case is preserved rather than collapsed, because
 * which of them a bare `:misskey:` in the body means is genuinely unknowable
 * ({@see axismundi_emoji_decorate()}).
 *
 * @param array<string,mixed> $payload      Object or Actor document.
 * @param string              $subject_uri  Declaring subject URI.
 * @return array<string,array<string,array<string,mixed>>> key => authority => registry row or declaration placeholder
 */
function axismundi_emoji_declaration_map( array $payload, string $subject_uri ) : array {
	$map = array();
	foreach ( axismundi_emoji_descriptors_from_payload( $payload, $subject_uri ) as $descriptor ) {
		$row = axismundi_emoji_get( $descriptor['emoji_authority'], $descriptor['shortcode_key'] );
		/*
		 * Keep declarations whose original bytes are not local yet. A bare shortcode may
		 * still resolve to this site's local emoji or an explicitly configured fallback
		 * authority; qualified names always retain the original declaration as their
		 * boundary and therefore cannot take that shortcut.
		 */
		$map[ $descriptor['shortcode_key'] ][ $descriptor['emoji_authority'] ] = is_array( $row )
			? $row
			: array(
				'emoji_authority' => $descriptor['emoji_authority'],
				'shortcode_key'   => $descriptor['shortcode_key'],
				'shortcode'       => $descriptor['shortcode'],
			);
	}
	return $map;
}

/**
 * Index this site's own projected Emoji declarations.
 *
 * Local documents have already passed the outbound gate, so their `tag[]` is trusted
 * application output rather than a remote claim. Do not feed it through the remote
 * descriptor parser: that parser correctly requires an HTTPS icon, while a local
 * development site legitimately projects an `http://localhost` icon. Matching the
 * emitted id back to the local registry keeps this narrow: an arbitrary Emoji tag a
 * transformer adds cannot claim a local image just by borrowing its shortcode.
 *
 * @param array<string,mixed> $payload Projected local Object or Actor.
 * @return array<string,array<string,array<string,mixed>>>
 */
function axismundi_emoji_local_declaration_map( array $payload ) : array {
	$tags = $payload['tag'] ?? array();
	if ( ! is_array( $tags ) ) {
		return array();
	}
	if ( ! array_is_list( $tags ) ) {
		$tags = array( $tags );
	}

	$map = array();
	foreach ( $tags as $tag ) {
		if ( ! is_array( $tag ) || ! in_array( 'Emoji', (array) ( $tag['type'] ?? array() ), true ) ) {
			continue;
		}
		$parsed = axismundi_emoji_parse_shortcode( (string) ( $tag['name'] ?? '' ) );
		if ( ! is_array( $parsed ) || '' !== $parsed['authority'] ) {
			continue;
		}
		$row = axismundi_emoji_local_get( $parsed['key'] );
		if ( ! is_array( $row ) || ! hash_equals( axismundi_emoji_object_id( $row ), (string) ( $tag['id'] ?? '' ) ) ) {
			continue;
		}
		$map[ $parsed['key'] ][ (string) $row['emoji_authority'] ] = $row;
	}
	return $map;
}

/**
 * Whether one row may stand in for another server's same-named emoji.
 *
 * A substitution is a guess about meaning, so it is only made where nothing marks the
 * two apart. Sensitivity is exactly such a mark: an emoji somebody flagged is one they
 * decided needed care, and quietly swapping it for — or replacing it with — an unflagged
 * picture discards that decision.
 *
 * **Only one half of this is live today, and the reason is structural.** `sensitive` is
 * not on the ActivityPub wire: an Emoji tag carries `id`, `type`, `name`, `updated`,
 * `icon`, and on Misskey `_misskey_license` — nothing else. Verified against
 * `:blobcat_hip:`, which Misskey's own catalogue marks sensitive and whose AP document
 * says nothing of the kind. So `$declared` is always unflagged, and the clause guarding
 * it never fires until §7's admin-initiated catalogue sync fills those columns.
 *
 * What *is* live is the other direction: a local emoji an operator flagged never stands
 * in for somebody else's declaration. That is a state the Local screen can really
 * produce, and the one this check earns its place on. Read it as "our flag is honoured",
 * not "sensitivity is handled".
 *
 * @param array<string,mixed> $declared   The declaration being rendered.
 * @param array<string,mixed> $substitute The row proposed in its place.
 * @return bool
 */
function axismundi_emoji_may_substitute( array $declared, array $substitute ) : bool {
	return empty( $declared['is_sensitive'] ) && empty( $substitute['is_sensitive'] );
}

/**
 * Resolve the presentation source for one unqualified declaration.
 *
 * This is deliberately a rendering alias, not an identity alias: the source document
 * remains attributed to its declaring authority and a qualified shortcode never enters
 * this path. The operator opts into each remote fallback authority independently, with
 * a stable priority for the rare case where more than one source supplies a name. A
 * tie at the winning priority is deliberately not broken by host name: it is a second
 * namespace collision, so the only honest fallback is the shortcode text.
 *
 * **Order matters, and the declaring authority comes first.** A shortcode is only unique
 * within the server that declared it: `:cat:` on one instance and `:cat:` on another can
 * be different pictures meaning different things, and one of them may be a dog. So when
 * we hold the bytes that server actually published, those are the answer — substitution
 * is a fallback for what we do not have, never an override of what we do. Preferring a
 * local or trusted copy over a cached original would silently redraw other people's
 * messages, which is the one thing this renderer exists to avoid.
 *
 * @param string               $key      Normalized shortcode key.
 * @param array<string,mixed>  $declared Original declaration's registry row or placeholder.
 * @return array<string,mixed>|null
 */
function axismundi_emoji_bare_presentation_row( string $key, array $declared ) : ?array {
	global $wpdb;
	// Rejection is a decision about this declaration's rendering, not merely a decision
	// not to download its own bytes. A fallback must never silently reverse it.
	if ( 'rejected' === (string) ( $declared['review_status'] ?? '' ) ) {
		return null;
	}
	// What the declaring server actually published, when we have it.
	if ( axismundi_emoji_is_renderable( $declared ) ) {
		return $declared;
	}
	if ( '' === $key || ! axismundi_emoji_ready() ) {
		return null;
	}
	$table = axismundi_emoji_table();

	// A site-owned emoji is an intentional visual override for the whole site.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	$local = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE scope = 'local' AND shortcode_key = %s AND review_status = 'approved' AND cached_path IS NOT NULL AND cached_path <> '' ORDER BY id ASC LIMIT 1", $key ), ARRAY_A );
	if ( is_array( $local ) ) {
		return axismundi_emoji_may_substitute( $declared, $local ) ? $local : null;
	}

	$authorities = axismundi_emoji_authorities_table();
	// A fallback source is a separate, explicit operator decision from auto-review.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned tables; value prepared.
	$fallbacks = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT e.*, a.fallback_priority FROM {$table} e INNER JOIN {$authorities} a ON a.emoji_authority = e.emoji_authority WHERE e.scope = 'remote' AND e.shortcode_key = %s AND e.review_status = 'approved' AND e.cached_path IS NOT NULL AND e.cached_path <> '' AND a.fallback_priority > 0 ORDER BY a.fallback_priority ASC, e.emoji_authority ASC, e.id ASC",
			$key
		),
		ARRAY_A
	);
	if ( ! empty( $fallbacks ) ) {
		$priority = (int) $fallbacks[0]['fallback_priority'];
		$winning  = array_values(
			array_filter(
				$fallbacks,
				static fn( array $row ) : bool => $priority === (int) $row['fallback_priority']
			)
		);
		if ( 1 !== count( $winning ) ) {
			return null;
		}
		return axismundi_emoji_may_substitute( $declared, $winning[0] ) ? $winning[0] : null;
	}
	return null;
}

/**
 * The image a reaction chip may show for one authority-qualified emoji.
 *
 * A reaction key is always qualified — `custom:hoto.moe:misskey` — and inline text
 * deliberately refuses to substitute for a qualified name, because there the author wrote
 * the authority into the message and swapping the picture would change what the sentence
 * says. A chip is not a sentence. Its identity, count, and label stay with the declaring
 * authority no matter what is drawn on it, so borrowing a picture changes the appearance
 * and nothing else.
 *
 * That distinction is the whole point of the exception, and it is why this is a separate
 * entry point rather than a flag on the inline path: the two surfaces must not be able to
 * drift into sharing an answer.
 *
 * The saving is the reason it exists. `hoto.moe/:misskey:` can stay `pending` with no
 * bytes of its own and still show something, as long as one designated authority's copy
 * of that name is reviewed and cached. Candidate selection is delegated rather than
 * rewritten, so a chip and a line of text agree about which fallback is eligible, what a
 * rejection means, and that a tie between two equally ranked sources is no answer at all.
 *
 * @param string $authority Declaring authority of the reacted-with emoji.
 * @param string $key       Normalized shortcode key.
 * @return array<string,mixed>|null Registry row to draw, or null to show the shortcode.
 */
function axismundi_emoji_reaction_presentation_row( string $authority, string $key ) : ?array {
	if ( '' === $authority || '' === $key ) {
		return null;
	}
	$declared = axismundi_emoji_get( $authority, $key );
	/*
	 * A restrictive licence never blocked *rendering* the original — showing a message the
	 * way its author wrote it is not re-using their asset as ours (§3). Standing a
	 * different file in its place is a different act, and one the declaring authority's
	 * terms give us no standing to perform, so restriction stops the substitution while
	 * leaving the original's own bytes usable.
	 */
	if ( is_array( $declared ) && 'restricted' === (string) ( $declared['license_state'] ?? '' ) ) {
		return axismundi_emoji_is_renderable( $declared ) ? $declared : null;
	}
	// A declaration we never observed is not evidence of anything, and is passed through as
	// a placeholder so the shared resolver has an authority and a name to reason about.
	return axismundi_emoji_bare_presentation_row(
		$key,
		is_array( $declared ) ? $declared : array( 'emoji_authority' => $authority, 'shortcode_key' => $key )
	);
}

/** @param array<string,mixed> $row Registry row. @return string Public URL of the original. */
function axismundi_emoji_file_url( array $row ) : string {
	$path = (string) ( $row['cached_path'] ?? '' );
	return '' === $path ? '' : axismundi_emoji_cache_url() . '/' . $path;
}

/** @param array<string,mixed> $row Registry row. @return string Public URL of the still, or ''. */
function axismundi_emoji_static_url( array $row ) : string {
	$path = (string) ( $row['static_path'] ?? '' );
	return '' === $path ? '' : axismundi_emoji_cache_url() . '/' . $path;
}

/**
 * The image markup for one emoji.
 *
 * `alt` and `title` reproduce the original shortcode verbatim, so a screen reader, a
 * copy-paste, and every plain-text surface all carry the same string. An animated emoji
 * is wrapped in `<picture>` with the still offered to `prefers-reduced-motion`, which is
 * the reason the cache insists on producing one.
 *
 * @param array<string,mixed> $row Registry row.
 * @param string              $presented_shortcode Exact token written by the author, or ''.
 * @return string
 */
function axismundi_emoji_image_markup( array $row, string $presented_shortcode = '' ) : string {
	$src = axismundi_emoji_file_url( $row );
	if ( '' === $src ) {
		return '';
	}
	/*
	 * Deliberately not `loading="lazy"`. An emoji is a glyph inside a line of text, not a
	 * content image: deferring it makes a word appear before the characters inside it and
	 * lets them pop in afterwards, shifting nothing but reading badly. Core reaches the
	 * same conclusion from the other direction by excluding small images and the first
	 * content image from lazy loading — an inline 1em glyph is squarely in that category.
	 *
	 * This is a rendering-quality decision, not a correctness one: `loading="lazy"` does
	 * load in ordinary browsers.
	 */
	$shortcode = '' !== $presented_shortcode ? $presented_shortcode : (string) ( $row['shortcode'] ?? '' );
	$img       = sprintf(
		'<img class="ax-emoji" src="%1$s" alt="%2$s" title="%2$s" decoding="async" draggable="false" />',
		esc_url( $src ),
		esc_attr( $shortcode )
	);
	$still = axismundi_emoji_static_url( $row );
	if ( empty( $row['animated'] ) || '' === $still ) {
		return $img;
	}
	return sprintf(
		'<picture class="ax-emoji-picture"><source srcset="%s" media="(prefers-reduced-motion: reduce)" />%s</picture>',
		esc_url( $still ),
		$img
	);
}

/**
 * Replace declared shortcodes in one run of plain text.
 *
 * Two rules do the work. A shortcode is replaced only if this subject declared it, so an
 * undeclared `:cool:` is left for WordPress's own smilies to handle exactly as the site
 * owner configured. And a bare name that the subject declared from two different
 * authorities is left alone: picking one would be a coin flip that shows a stranger's
 * image, and the shortcode text is the honest answer.
 *
 * @param string                                          $text Plain text run.
 * @param array<string,array<string,array<string,mixed>>> $map  Declaration index.
 * @return string
 */
function axismundi_emoji_replace_in_text( string $text, array $map ) : string {
	if ( array() === $map || false === strpos( $text, ':' ) ) {
		return $text;
	}
	return (string) preg_replace_callback(
		'/:([a-zA-Z0-9_]{2,})(?:@([a-z0-9.-]+\.[a-z]{2,}))?:/i',
		static function ( array $m ) use ( $map ) : string {
			$key       = strtolower( $m[1] );
			$authority = isset( $m[2] ) ? strtolower( $m[2] ) : '';
			if ( ! isset( $map[ $key ] ) ) {
				return $m[0];
			}
			if ( '' !== $authority ) {
				// An explicitly qualified name says which one it means, so it is never
				// ambiguous even when the subject declared several of that name.
				return isset( $map[ $key ][ $authority ] ) && axismundi_emoji_is_renderable( $map[ $key ][ $authority ] )
					? axismundi_emoji_image_markup( $map[ $key ][ $authority ], $m[0] )
					: $m[0];
			}
			if ( 1 !== count( $map[ $key ] ) ) {
				return $m[0]; // Ambiguous: two authorities, one bare name.
			}
			$row = axismundi_emoji_bare_presentation_row( $key, (array) reset( $map[ $key ] ) );
			return is_array( $row ) ? axismundi_emoji_image_markup( $row, $m[0] ) : $m[0];
		},
		$text
	);
}

/**
 * Decorate a rendered HTML fragment.
 *
 * Text nodes only, using the same splitter Core's `convert_smilies()` uses, and never
 * inside `pre`, `code`, `script`, `style`, or `textarea` — a shortcode shown as an
 * example is documentation, not an emoji. Attributes are untouched by construction,
 * since the splitter hands tags back whole.
 *
 * @param string                                          $html Sanitized HTML.
 * @param array<string,array<string,array<string,mixed>>> $map  Declaration index.
 * @return string
 */
function axismundi_emoji_decorate( string $html, array $map ) : string {
	if ( array() === $map || '' === $html ) {
		return $html;
	}
	$parts  = wp_html_split( $html );
	$opaque = 0;
	foreach ( $parts as $index => $part ) {
		if ( '' === $part ) {
			continue;
		}
		if ( '<' === $part[0] ) {
			if ( 1 === preg_match( '#^<\s*(/)?\s*(pre|code|script|style|textarea)\b#i', $part, $tag ) ) {
				$opaque = '' === ( $tag[1] ?? '' ) ? $opaque + 1 : max( 0, $opaque - 1 );
			}
			continue;
		}
		if ( 0 === $opaque ) {
			$parts[ $index ] = axismundi_emoji_replace_in_text( $part, $map );
		}
	}
	return implode( '', $parts );
}

/**
 * Decorate plain text that is about to become HTML.
 *
 * For a value like a display name, which arrives as text and is escaped by its caller.
 * The caller must escape *before* calling this, because the return value contains
 * markup: decoration is the last step, after sanitizing, never before.
 *
 * @param string                                          $escaped_text Already-escaped text.
 * @param array<string,array<string,array<string,mixed>>> $map          Declaration index.
 * @return string
 */
function axismundi_emoji_decorate_escaped( string $escaped_text, array $map ) : string {
	return axismundi_emoji_replace_in_text( $escaped_text, $map );
}

/**
 * How to write a shortcode where several instances may share the name.
 *
 * Admin lists, review queues, and picker results show emoji from many servers side by
 * side, and there `:misskey:` twice over is unreadable. Qualifying the name is only
 * noise when nothing collides, so it is applied exactly when it disambiguates.
 *
 * @param array<string,mixed> $row       Registry row.
 * @param bool                $ambiguous Whether another authority shares this name.
 * @return string
 */
function axismundi_emoji_display_shortcode( array $row, bool $ambiguous ) : string {
	$shortcode = (string) ( $row['shortcode'] ?? '' );
	$authority = (string) ( $row['emoji_authority'] ?? '' );
	if ( ! $ambiguous || '' === $authority || str_contains( $shortcode, '@' ) ) {
		return $shortcode;
	}
	return ':' . trim( $shortcode, ':' ) . '@' . $authority . ':';
}

/**
 * Which shortcode keys are claimed by more than one authority.
 *
 * @return array<string,int> key => number of authorities
 */
function axismundi_emoji_colliding_keys() : array {
	global $wpdb;
	if ( ! axismundi_emoji_ready() ) {
		return array();
	}
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	$rows = (array) $wpdb->get_results( "SELECT shortcode_key, COUNT(DISTINCT emoji_authority) AS authorities FROM {$table} GROUP BY shortcode_key HAVING authorities > 1", ARRAY_A );
	$out  = array();
	foreach ( $rows as $row ) {
		$out[ (string) $row['shortcode_key'] ] = (int) $row['authorities'];
	}
	return $out;
}
