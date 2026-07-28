<?php
/**
 * Reading Emoji objects off the wire.
 *
 * Lenient by design. FEP-9098's Compatibility section describes what interoperable
 * emoji look like, and real instances already violate it — the measured misskey.io
 * APNG is 1.92 MiB against a 256 KB recommendation, and declares a media type the
 * section does not list. Enforcing those recommendations here would mean refusing to
 * display a large share of a real instance's emoji. They bind what we publish
 * instead (docs §2, §8): strict on send, lenient on receive.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit;

/**
 * Split a shortcode into its name and any explicit authority.
 *
 * Both forms occur. Content-layer tags we captured are bare (`:mastodon:`), while
 * Misskey's reaction layer qualifies emoji borrowed from a third instance
 * (`:09_bird@hoto.moe:` on a misskey.io note, where the emoji belongs to hoto.moe).
 * Accepting both everywhere costs nothing and assuming otherwise silently mis-files
 * every borrowed emoji under the wrong owner.
 *
 * @param string $name Raw `name` value, with or without colons.
 * @return array{key:string,authority:string}|null Null when the name is unusable.
 */
function axismundi_emoji_parse_shortcode( string $name ) : ?array {
	$name = trim( $name );
	if ( '' === $name ) {
		return null;
	}
	if ( ! str_starts_with( $name, ':' ) ) {
		$name = ':' . $name;
	}
	if ( ! str_ends_with( $name, ':' ) ) {
		$name .= ':';
	}
	if ( 1 !== preg_match( AXISMUNDI_EMOJI_SHORTCODE_PATTERN, $name, $matches ) ) {
		return null;
	}
	return array(
		'key'       => strtolower( $matches[1] ),
		'authority' => isset( $matches[2] ) ? strtolower( $matches[2] ) : '',
	);
}

/**
 * Resolve which host owns an emoji.
 *
 * Order matters and the last resort is not the obvious one. `icon.url` is never
 * consulted: in every sample captured it points at a CDN distinct from the declaring
 * host — `files.mastodon.social` for an emoji declared by `mastodon.social`,
 * `media.misskeyusercontent.com` for one declared by `misskey.io` — and the same
 * misskey emoji was seen served from both a `.com` and a `.jp` host. Keying on the
 * image location would split one emoji into several identities and merge unrelated
 * ones that share a CDN.
 *
 * @param array<string,mixed> $tag              A single `tag[]` entry.
 * @param string              $declared_by_host Host of the Object or Actor carrying it.
 * @return string Lowercased authority, or '' when none can be established.
 */
function axismundi_emoji_resolve_authority( array $tag, string $declared_by_host ) : string {
	$parsed = axismundi_emoji_parse_shortcode( (string) ( $tag['name'] ?? '' ) );
	if ( is_array( $parsed ) && '' !== $parsed['authority'] ) {
		return $parsed['authority'];
	}
	$id_host = (string) wp_parse_url( (string) ( $tag['id'] ?? '' ), PHP_URL_HOST );
	if ( '' !== $id_host ) {
		return strtolower( $id_host );
	}
	return strtolower( trim( $declared_by_host ) );
}

/**
 * Classify a licence statement.
 *
 * Three states rather than two, because in a stratified sample of misskey.io the
 * unlicensed bucket outnumbered the explicitly restricted one two to one. Folding
 * `unknown` into `restricted` would withhold more emoji than are actually restricted;
 * folding it into `allowed` would re-use assets whose terms nobody stated. Neither is
 * defensible, so `unknown` stays a state of its own and routes to a human.
 *
 * The presence of a licence string is not itself evidence of restriction: most of the
 * strings observed were grants (`Public Domain`, `CC BY 4.0`).
 *
 * @param string $text Free-text licence statement.
 * @return string unknown|allowed|restricted
 */
function axismundi_emoji_classify_license( string $text ) : string {
	$text = strtolower( trim( $text ) );
	if ( '' === $text ) {
		return 'unknown';
	}
	foreach ( array( 'prohibited', 'exclusive to', 'not permitted', 'no reuse', 'all rights reserved' ) as $needle ) {
		if ( str_contains( $text, $needle ) ) {
			return 'restricted';
		}
	}
	/*
	 * URL forms as well as prose. A licence field very often holds nothing but a link, and
	 * `creativecommons.org/publicdomain/zero/1.0/` has neither a space in "public domain"
	 * nor the string "cc0" — so the prose needles alone read the clearest possible grant as
	 * `unknown`, and an import of it would arrive local-only for no reason.
	 */
	foreach ( array( 'public domain', 'publicdomain', 'cc0', 'cc-0', 'cc by', 'cc-by', 'creative commons', 'creativecommons.org', 'mit', 'apache', 'gpl' ) as $needle ) {
		if ( str_contains( $text, $needle ) ) {
			return 'allowed';
		}
	}
	return 'unknown';
}

/**
 * Extract the licence free-text a vendor extension may carry.
 *
 * `_misskey_license` is the only one observed in the wild, and it is frequently
 * present with a null `freeText`, which is absence rather than a statement.
 *
 * @param array<string,mixed> $tag A single `tag[]` entry.
 * @return string
 */
function axismundi_emoji_license_text( array $tag ) : string {
	$license = $tag['_misskey_license'] ?? null;
	if ( is_array( $license ) && is_string( $license['freeText'] ?? null ) ) {
		return trim( (string) $license['freeText'] );
	}
	return is_string( $license ) ? trim( $license ) : '';
}

/**
 * Normalize one `tag[]` entry into a registry-shaped descriptor.
 *
 * @param mixed  $tag              A `tag[]` entry, shape unverified.
 * @param string $declared_by_host Host of the Object or Actor carrying it.
 * @return array<string,mixed>|null Null when the entry is not a usable Emoji.
 */
function axismundi_emoji_descriptor_from_tag( $tag, string $declared_by_host ) : ?array {
	if ( ! is_array( $tag ) ) {
		return null;
	}
	// `toot:Emoji` and a bare `Emoji` are the same thing; a compacted document may
	// carry either, and an array of types is legal in JSON-LD.
	$types = (array) ( $tag['type'] ?? array() );
	$is_emoji = false;
	foreach ( $types as $type ) {
		if ( is_string( $type ) && str_contains( $type, 'Emoji' ) ) {
			$is_emoji = true;
			break;
		}
	}
	if ( ! $is_emoji ) {
		return null;
	}

	$parsed = axismundi_emoji_parse_shortcode( (string) ( $tag['name'] ?? '' ) );
	if ( null === $parsed ) {
		return null;
	}
	$authority = axismundi_emoji_resolve_authority( $tag, $declared_by_host );
	if ( '' === $authority ) {
		return null;
	}

	// `icon` is REQUIRED, but a missing or non-https one is a reason to skip this emoji,
	// not to reject the whole object it decorates.
	$icon = $tag['icon'] ?? array();
	$url  = is_array( $icon ) ? (string) ( $icon['url'] ?? '' ) : (string) $icon;
	$url  = esc_url_raw( trim( $url ) );
	if ( '' === $url || 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) ) {
		return null;
	}

	$license_text = axismundi_emoji_license_text( $tag );
	$updated_raw  = is_string( $tag['updated'] ?? null ) ? trim( (string) $tag['updated'] ) : '';
	$updated_ts   = '' !== $updated_raw ? strtotime( $updated_raw ) : false;

	return array(
		'emoji_authority'     => $authority,
		// Who said so, as distinct from whose emoji it is. When these differ the
		// declaration is hearsay and must not be allowed to define the emoji
		// ({@see axismundi_emoji_observe()}).
		'declared_by_host'    => strtolower( trim( $declared_by_host ) ),
		'first_party'         => '' !== $declared_by_host && strtolower( trim( $declared_by_host ) ) === $authority,
		'shortcode'           => (string) $tag['name'],
		'shortcode_key'       => $parsed['key'],
		'declared_id'         => is_string( $tag['id'] ?? null ) ? (string) $tag['id'] : '',
		'source_url'          => $url,
		'updated_raw'         => $updated_raw,
		'updated_at'          => false !== $updated_ts ? gmdate( 'Y-m-d H:i:s', $updated_ts ) : null,
		'declared_media_type' => is_array( $icon ) && is_string( $icon['mediaType'] ?? null ) ? (string) $icon['mediaType'] : '',
		'license_raw'         => isset( $tag['_misskey_license'] ) ? (string) wp_json_encode( $tag['_misskey_license'] ) : '',
		'license_text'        => $license_text,
		'license_state'       => axismundi_emoji_classify_license( $license_text ),
	);
}

/**
 * Every usable Emoji descriptor declared by one Object or Actor payload.
 *
 * Capped, because a remote can declare arbitrarily many on a single object and each
 * one becomes a review-queue row and a potential download.
 *
 * @param array<string,mixed> $payload  Object or Actor document.
 * @param string              $subject_uri URI of the declaring subject.
 * @return array<int,array<string,mixed>>
 */
function axismundi_emoji_descriptors_from_payload( array $payload, string $subject_uri ) : array {
	$host = strtolower( (string) wp_parse_url( '' !== $subject_uri ? $subject_uri : (string) ( $payload['id'] ?? '' ), PHP_URL_HOST ) );
	$tags = $payload['tag'] ?? array();
	if ( ! is_array( $tags ) ) {
		return array();
	}
	// A single tag object rather than a list is legal JSON-LD.
	if ( ! array_is_list( $tags ) ) {
		$tags = array( $tags );
	}

	$seen = array();
	$out  = array();
	foreach ( $tags as $tag ) {
		$descriptor = axismundi_emoji_descriptor_from_tag( $tag, $host );
		if ( null === $descriptor ) {
			continue;
		}
		$identity = $descriptor['emoji_authority'] . '/' . $descriptor['shortcode_key'];
		if ( isset( $seen[ $identity ] ) ) {
			continue;
		}
		$seen[ $identity ] = true;
		$out[]             = $descriptor;
		if ( count( $out ) >= AXISMUNDI_EMOJI_MAX_PER_SUBJECT ) {
			break;
		}
	}
	return $out;
}
