<?php
/**
 * URI-keyed Like and Undo state transitions.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** Validate a local Person Actor as a reaction author. */
function axismundi_act_validate_reaction_actor( Axismundi_Actor $actor ) {
	return $actor->is_local()
		&& 'Person' === $actor->get_type()
		&& 'public' === $actor->get_status()
		&& $actor->is_handle_locked()
		? true
		: new WP_Error( 'ax_act_reaction_actor', __( 'An activated public local Person actor is required.', 'axismundi-activities' ) );
}

/*
 * Like is one of the two plain vote verbs. Its queries live in votes.php so that Like and
 * Dislike cannot drift apart; these names stay because other products call them.
 */

/** @return Axismundi_Activity[] Effective Likes for one exact object, newest first and unique by Actor. */
function axismundi_act_get_effective_likes( string $object_uri, int $limit = 100 ) : array {
	return axismundi_act_get_effective_votes( 'Like', $object_uri, $limit );
}

/** Count distinct Actors with an effective Like for one object URI. */
function axismundi_act_get_like_count( string $object_uri ) : int {
	return axismundi_act_get_vote_count( 'Like', $object_uri );
}

/** Latest Like by one Actor for one object, optionally requiring effective state. */
function axismundi_act_get_actor_like( string $actor_uri, string $object_uri, bool $effective_only = true ) : ?Axismundi_Activity {
	return axismundi_act_get_actor_vote( 'Like', $actor_uri, $object_uri, $effective_only );
}

/** Whether one Actor currently likes an object. */
function axismundi_act_get_like_state( string $actor_uri, string $object_uri ) : bool {
	return axismundi_act_get_vote_state( 'Like', $actor_uri, $object_uri );
}

/** @return string[] Distinct object URIs currently liked by one Actor. */
function axismundi_act_get_liked_object_uris( string $actor_uri, int $limit = 100 ) : array {
	return axismundi_act_get_voted_object_uris( 'Like', $actor_uri, $limit );
}

/** Number of historical Like cycles for an Actor/object pair. */
function axismundi_act_like_cycle_count( string $actor_uri, string $object_uri ) : int {
	return axismundi_act_vote_cycle_count( 'Like', $actor_uri, $object_uri );
}

/** Record or return the effective Like for one object. */
function axismundi_act_like_object( Axismundi_Actor $actor, string $object_uri, string $recipient_actor_uri = '' ) {
	return axismundi_act_vote_on_object( 'Like', $actor, $object_uri, $recipient_actor_uri );
}

/** Undo the current Like by referring to the Like Activity URI, never the liked object URI. */
function axismundi_act_unlike_object( Axismundi_Actor $actor, string $object_uri ) {
	return axismundi_act_undo_vote_on_object( 'Like', $actor, $object_uri );
}

/**
 * The Emoji declaration this site may publish for a shortcode, or null.
 *
 * Reacting with a custom emoji means *declaring* it: the reaction carries the `tag[]`
 * entry that tells the receiver what `:foo:` is and where its picture lives. So the same
 * gate that governs publishing an emoji in a Note governs reacting with one — a cached
 * copy of somebody else's emoji is theirs, and re-declaring it under our Activity would
 * republish their asset as if it were ours to hand out.
 *
 * Delegated rather than re-queried so the two can never disagree about what is publishable.
 *
 * @param string $content Reaction content as typed.
 * @return array<string,mixed>|null AS2 Emoji object, or null when it may not be published.
 */
function axismundi_act_publishable_emoji_tag( string $content ) : ?array {
	if ( ! function_exists( 'axismundi_emoji_outbound_tags' ) ) {
		return null;
	}
	$tags = axismundi_emoji_outbound_tags( array( $content ) );
	// Exactly one, or the content named something other than a single emoji this site owns.
	return 1 === count( $tags ) ? $tags[0] : null;
}

/**
 * The declaration for a local emoji, and whether it may travel.
 *
 * Two answers, because they come apart. An emoji marked `local_only` is still this site's
 * own and still renders here, so reacting with it on something that stays here is an
 * ordinary thing to do; what it may not do is leave. The declaration is built either way —
 * the reaction has to be *keyed*, and a custom key is meaningless without the authority
 * that a declaration supplies — and `federates` decides whether that declaration is
 * published or dropped from the payload.
 *
 * Dropping it rather than refusing is what Misskey does with its own `localOnly` emoji: the
 * shortcode travels, the image reference does not. A remote reader of this object's
 * FEP-c0e0 collection sees `:foo:` as text, which is honest — they cannot render an emoji
 * we did not give them, and they were never going to.
 *
 * @param string $content Reaction content as typed.
 * @return array{tag:array<string,mixed>,federates:bool}|null
 */
function axismundi_act_local_emoji_declaration( string $content ) : ?array {
	$federating = axismundi_act_publishable_emoji_tag( $content );
	if ( is_array( $federating ) ) {
		return array( 'tag' => $federating, 'federates' => true );
	}
	if ( ! function_exists( 'axismundi_emoji_local_get' ) || ! function_exists( 'axismundi_emoji_as2_object' ) || ! function_exists( 'axismundi_emoji_parse_shortcode' ) ) {
		return null;
	}
	$parsed = axismundi_emoji_parse_shortcode( $content );
	// A qualified name asks for somebody else's emoji, which this site cannot declare.
	if ( null === $parsed || '' !== (string) $parsed['authority'] ) {
		return null;
	}
	$row = axismundi_emoji_local_get( (string) $parsed['key'] );
	if ( ! is_array( $row ) || ! axismundi_emoji_is_renderable( $row ) || empty( $row['picker_visible'] ) ) {
		return null;
	}
	$tag = axismundi_emoji_as2_object( $row );
	return '' === (string) ( $tag['icon']['url'] ?? '' ) ? null : array( 'tag' => $tag, 'federates' => false );
}

/**
 * Whether an object stays on this site.
 *
 * The question a `local_only` reaction turns on. A cached remote object belongs to another
 * server and anything we attach to it is addressed there, so a reaction naming an emoji we
 * refuse to publish would arrive as a bare word.
 */
function axismundi_act_object_is_local( string $object_uri ) : bool {
	if ( function_exists( 'axismundi_op_get_remote_object' ) && is_array( axismundi_op_get_remote_object( $object_uri, false ) ) ) {
		return false;
	}
	$host = strtolower( (string) wp_parse_url( $object_uri, PHP_URL_HOST ) );
	return '' !== $host && $host === strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
}

/** Latest reaction Activity by one Actor on one object for one reaction key. */
function axismundi_act_get_actor_reaction_activity( string $actor_uri, string $object_uri, string $reaction_key, bool $effective_only = true ) : ?Axismundi_Activity {
	global $wpdb;
	$actor  = axismundi_act_uri( $actor_uri );
	$object = axismundi_act_uri( $object_uri );
	if ( '' === $actor || '' === $object || '' === $reaction_key || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return null;
	}
	$table  = axismundi_act_activities_table();
	$active = $effective_only ? "AND effective_status = 'active'" : '';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- allowlisted fixed status clause and exact lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE reaction_key_hash = %s AND reaction_key = %s AND actor_uri_hash = %s AND actor_uri = %s AND object_uri_hash = %s AND object_uri = %s {$active} ORDER BY id DESC LIMIT 1", hash( 'sha256', $reaction_key ), $reaction_key, hash( 'sha256', $actor ), $actor, hash( 'sha256', $object ), $object ), ARRAY_A );
	return is_array( $row ) ? axismundi_act_hydrate( $row ) : null;
}

/** How many times this Actor has reacted-and-undone with one key, for a stable source key. */
function axismundi_act_reaction_cycle_count( string $actor_uri, string $object_uri, string $reaction_key ) : int {
	global $wpdb;
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact triple count used for a stable source-event key.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE reaction_key_hash = %s AND reaction_key = %s AND actor_uri_hash = %s AND actor_uri = %s AND object_uri_hash = %s AND object_uri = %s", hash( 'sha256', $reaction_key ), $reaction_key, hash( 'sha256', $actor_uri ), $actor_uri, hash( 'sha256', $object_uri ), $object_uri ) );
}

/**
 * React to an object, or return the reaction already standing.
 *
 * Several reactions per Actor are allowed, because FEP-c0e0 permits them and Akkoma sends
 * them. Misskey allows one; that is a rule its own server imposes on its own users, not a
 * fact about the protocol, so it is not imposed here.
 *
 * Addressed the way `axismundi_act_like_object()` addresses a Like, including the
 * self-reaction case, because delivery already works for that shape and a reaction is the
 * same act with a picture on it.
 *
 * @param Axismundi_Actor $actor               Reacting local Actor.
 * @param string          $object_uri          Reacted-to object.
 * @param string          $content             A single emoji, or `:shortcode:` of a local emoji.
 * @param string          $recipient_actor_uri Object owner, when known.
 * @return Axismundi_Activity|WP_Error
 */
function axismundi_act_react_to_object( Axismundi_Actor $actor, string $object_uri, string $content, string $recipient_actor_uri = '' ) {
	$valid  = axismundi_act_validate_reaction_actor( $actor );
	$object = axismundi_act_uri( $object_uri );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	if ( '' === $object ) {
		return new WP_Error( 'ax_act_reaction_object', __( 'A reaction requires a canonical object URI.', 'axismundi-activities' ) );
	}

	$content     = trim( $content );
	$declaration = str_starts_with( $content, ':' ) ? axismundi_act_local_emoji_declaration( $content ) : null;
	if ( str_starts_with( $content, ':' ) && null === $declaration ) {
		return new WP_Error( 'ax_act_reaction_emoji', __( 'Only an emoji belonging to this site can be used as a reaction.', 'axismundi-activities' ), array( 'status' => 400 ) );
	}
	/*
	 * An emoji withheld from publication may still be used at home. On something that is not
	 * at home it would federate as a bare shortcode, which is not the reaction the reader
	 * chose, so it is refused rather than silently degraded.
	 */
	if ( is_array( $declaration ) && ! $declaration['federates'] && ! axismundi_act_object_is_local( $object ) ) {
		return new WP_Error( 'ax_act_reaction_local_only', __( 'This emoji is not published beyond this site, so it cannot be used on an object from elsewhere.', 'axismundi-activities' ), array( 'status' => 400 ) );
	}
	$tag      = is_array( $declaration ) ? $declaration['tag'] : null;
	$reaction = axismundi_act_normalize_reaction( $content, is_array( $tag ) ? $tag : array() );
	if ( null === $reaction ) {
		return new WP_Error( 'ax_act_reaction_content', __( 'A reaction must be a single emoji.', 'axismundi-activities' ), array( 'status' => 400 ) );
	}

	// Already standing. Returned rather than duplicated, so a double click is one reaction.
	$existing = axismundi_act_get_actor_reaction_activity( $actor->get_uri(), $object, $reaction['key'], true );
	if ( $existing instanceof Axismundi_Activity ) {
		return $existing;
	}

	$recipient = '' !== $recipient_actor_uri ? axismundi_actors_get_by_uri( $recipient_actor_uri ) : null;
	if ( '' !== $recipient_actor_uri && ! $recipient instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_act_reaction_recipient', __( 'The object owner Actor is unavailable.', 'axismundi-activities' ) );
	}
	$payload = array( 'type' => 'EmojiReact', 'actor' => $actor->get_uri(), 'object' => $object, 'content' => $reaction['raw'] );
	if ( is_array( $tag ) && $declaration['federates'] ) {
		// The declaration travels with the reaction: without it the receiver has a shortcode
		// and no way to know whose emoji it names. A withheld emoji is the deliberate
		// exception -- it was keyed from this same declaration and then left behind.
		$payload['tag'] = array( $tag );
	}
	if ( $recipient instanceof Axismundi_Actor && $recipient->get_uri() !== $actor->get_uri() ) {
		$payload['to'] = array( $recipient->get_uri() );
	}
	$direction = $recipient instanceof Axismundi_Actor && ! $recipient->is_local() ? 'outbound' : 'local';
	if ( $recipient instanceof Axismundi_Actor && $recipient->get_uri() === $actor->get_uri() ) {
		$followers = (string) apply_filters( 'axismundi_act_actor_followers_uri', '', $actor );
		if ( '' !== $followers ) {
			$payload['cc'] = array( $followers );
			$direction     = 'outbound';
		}
	}
	$cycle  = axismundi_act_reaction_cycle_count( $actor->get_uri(), $object, $reaction['key'] ) + 1;
	$source = 'react:' . hash( 'sha256', $actor->get_uri() ) . ':' . hash( 'sha256', $object ) . ':' . hash( 'sha256', $reaction['key'] ) . ':' . $cycle;
	return axismundi_act_record_source_activity( $payload, $direction, $source );
}

/**
 * Withdraw one of this Actor's reactions.
 *
 * Keyed on the reaction, not just the object: an Actor may hold several at once, and an
 * Undo that named only the object would be ambiguous about which one it retracts.
 *
 * @param Axismundi_Actor $actor        Reacting local Actor.
 * @param string          $object_uri   Reacted-to object.
 * @param string          $reaction_key Which reaction to withdraw.
 * @return Axismundi_Activity|WP_Error
 */
function axismundi_act_unreact_to_object( Axismundi_Actor $actor, string $object_uri, string $reaction_key ) {
	$valid  = axismundi_act_validate_reaction_actor( $actor );
	$object = axismundi_act_uri( $object_uri );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	$reaction = axismundi_act_get_actor_reaction_activity( $actor->get_uri(), $object, $reaction_key, true );
	if ( ! $reaction instanceof Axismundi_Activity ) {
		// Already withdrawn: return the Undo that did it rather than an error, so a repeated
		// click is idempotent for the same reason a repeated Like is.
		$latest = axismundi_act_get_actor_reaction_activity( $actor->get_uri(), $object, $reaction_key, false );
		if ( $latest instanceof Axismundi_Activity && ! $latest->is_effective() ) {
			$undos = array_filter( axismundi_act_get_by_object( $latest->get_uri(), 20 ), static fn( Axismundi_Activity $item ) : bool => 'Undo' === $item->get_type() && $item->is_effective() && $item->get_actor_uri() === $actor->get_uri() );
			if ( ! empty( $undos ) ) {
				return reset( $undos );
			}
		}
		return new WP_Error( 'ax_act_reaction_missing', __( 'There is no active reaction to withdraw.', 'axismundi-activities' ), array( 'status' => 404 ) );
	}
	$payload = array( 'type' => 'Undo', 'actor' => $actor->get_uri(), 'object' => $reaction->get_uri() );
	$to      = (array) ( $reaction->get_audience()['to'] ?? array() );
	$cc      = (array) ( $reaction->get_audience()['cc'] ?? array() );
	if ( ! empty( $to ) ) {
		$payload['to'] = $to;
	}
	if ( ! empty( $cc ) ) {
		$payload['cc'] = $cc;
	}
	return axismundi_act_record_source_activity( $payload, $reaction->get_direction(), 'unreact:' . $reaction->get_uri() );
}

/** Keep Object Projections interaction leases aligned with committed Like/Undo rows. */
function axismundi_act_sync_reaction_lease( Axismundi_Activity $activity ) : void {
	if ( ! function_exists( 'axismundi_op_get_remote_object' ) || ! function_exists( 'axismundi_op_add_lease' ) || ! function_exists( 'axismundi_op_release_lease' ) ) {
		return;
	}
	/*
	 * `EmojiReact` holds a lease for the same reason `Like` does: it points at a cached
	 * remote object, and letting that object be collected while our reaction still refers to
	 * it would leave a chip on nothing. Each Activity leases under its own URI, so an Actor
	 * holding several reactions holds several leases and withdrawing one releases only that
	 * one.
	 */
	if ( in_array( $activity->get_type(), array( 'Like', 'Dislike', 'EmojiReact' ), true ) && $activity->is_effective() && null !== $activity->get_object_uri() && axismundi_op_get_remote_object( $activity->get_object_uri() ) ) {
		axismundi_op_add_lease( $activity->get_object_uri(), 'interaction', $activity->get_uri() );
		return;
	}
	if ( 'Undo' !== $activity->get_type() || null === $activity->get_object_uri() ) {
		return;
	}
	$target = axismundi_act_get( $activity->get_object_uri() );
	if ( $target instanceof Axismundi_Activity && in_array( $target->get_type(), array( 'Like', 'Dislike', 'EmojiReact' ), true ) && null !== $target->get_object_uri() ) {
		axismundi_op_release_lease( $target->get_object_uri(), 'interaction', $target->get_uri() );
	}
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_act_sync_reaction_lease', 20 );

/**
 * Whether a string is one emoji, for installs without `ext-intl`.
 *
 * `grapheme_strlen()` is the right answer and is preferred wherever it exists, but intl is
 * not a WordPress requirement and shared hosts routinely ship without it. Refusing to
 * classify there would be worse than approximating: every received emoji reaction would
 * fall through to a plain `Like`, inflating the favourite count and losing the reaction,
 * silently and on exactly the installs least able to diagnose it.
 *
 * So this recognises the shapes UAX#29 keeps together that a naive codepoint count would
 * split: a regional-indicator pair, a base with a skin-tone modifier, a ZWJ sequence, a
 * keycap, and a tag sequence. It is narrower than intl by design — it errs toward
 * rejecting an exotic cluster rather than accepting two emoji as one.
 *
 * @param string $text Variation selectors already stripped.
 */
function axismundi_act_single_emoji_fallback( string $text ) : bool {
	$base = '\x{00A9}\x{00AE}\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{21AA}\x{231A}-\x{231B}\x{2328}\x{23CF}\x{23E9}-\x{23FA}\x{24C2}\x{25AA}-\x{25FE}\x{2600}-\x{27BF}\x{2934}-\x{2935}\x{2B00}-\x{2BFF}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F000}-\x{1FAFF}';
	$unit = '[' . $base . '][\x{1F3FB}-\x{1F3FF}]?';
	$pattern = '/^(?:'
		. '[\x{1F1E6}-\x{1F1FF}]{2}'          // Regional-indicator pair: one flag.
		. '|[0-9#*]\x{20E3}'                  // Keycap.
		. '|\x{1F3F4}[\x{E0020}-\x{E007E}]+\x{E007F}' // Subdivision flag tag sequence.
		. '|' . $unit . '(?:\x{200D}' . $unit . ')*'
		. ')$/u';
	return 1 === preg_match( $pattern, $text );
}

/**
 * Get one UTF-8 code point without making mbstring a runtime requirement.
 *
 * Emoji reactions must remain reactions on hosts without ext-intl or mbstring;
 * silently reducing them to a plain Like changes their meaning and count.
 */
function axismundi_act_unicode_codepoint( string $character ) : ?int {
	if ( function_exists( 'mb_ord' ) ) {
		$codepoint = mb_ord( $character, 'UTF-8' );
		return false === $codepoint ? null : $codepoint;
	}

	$bytes = array_values( unpack( 'C*', $character ) ?: array() );
	$count = count( $bytes );
	if ( 1 === $count && $bytes[0] <= 0x7F ) {
		return $bytes[0];
	}

	$first  = $bytes[0] ?? -1;
	$length = 0;
	$value  = 0;
	if ( $first >= 0xC2 && $first <= 0xDF ) {
		$length = 2;
		$value  = $first & 0x1F;
	} elseif ( $first >= 0xE0 && $first <= 0xEF ) {
		$length = 3;
		$value  = $first & 0x0F;
	} elseif ( $first >= 0xF0 && $first <= 0xF4 ) {
		$length = 4;
		$value  = $first & 0x07;
	}

	if ( $length !== $count ) {
		return null;
	}
	for ( $index = 1; $index < $length; $index++ ) {
		if ( $bytes[ $index ] < 0x80 || $bytes[ $index ] > 0xBF ) {
			return null;
		}
		$value = ( $value << 6 ) | ( $bytes[ $index ] & 0x3F );
	}

	return $value;
}

/**
 * The reaction one `content` value expresses, or null when it is not a reaction.
 *
 * `raw` is what the sender wrote and a reader is shown; `key` is the stable chip
 * identity. The key removes only U+FE0E/U+FE0F, preserving skin tones, ZWJ sequences,
 * and flags as distinct choices.
 *
 * @param mixed               $content Reaction content.
 * @param array<string,mixed> $tag     Optional declared Emoji tag for a custom reaction.
 * @return array{key:string,raw:string,kind:string}|null
 */
function axismundi_act_normalize_reaction( $content, array $tag = array(), string $self_authority = '' ) : ?array {
	$raw = is_scalar( $content ) ? trim( (string) $content ) : '';
	if ( '' === $raw || strlen( $raw ) > 191 ) {
		return null;
	}

	/*
	 * A custom reaction names an emoji that means nothing without its authority: two
	 * servers both ship `:misskey:`. The shortcode parser already resolves the qualified
	 * form and the declaring host, so this reuses it rather than inventing a second answer.
	 */
	if ( str_starts_with( $raw, ':' ) && str_ends_with( $raw, ':' ) ) {
		if ( ! function_exists( 'axismundi_emoji_parse_shortcode' ) ) {
			return null;
		}
		$parsed = axismundi_emoji_parse_shortcode( $raw );
		if ( null === $parsed ) {
			return null;
		}
		/*
		 * The declaration is the evidence, so a custom reaction always needs one. A
		 * shortcode alone names nothing: `:misskey:` arriving bare could belong to any
		 * server that ships it, and guessing would merge two instances' emoji under one
		 * chip. The tag has to name the same emoji, or the pair is describing two
		 * different things and we cannot tell which half to believe.
		 */
		/*
		 * One exception, and only for an Activity this site is the author of. An emoji
		 * withheld from publication is reacted with by dropping its declaration from the
		 * payload, so there is nothing left to read it back from -- but the authority is not
		 * in question either, because we are it. A received Activity gets no such benefit:
		 * there the declaration is the only evidence of whose emoji is meant.
		 */
		if ( array() === $tag && '' !== $self_authority && '' === (string) $parsed['authority'] ) {
			return array( 'key' => 'custom:' . strtolower( $self_authority ) . ':' . $parsed['key'], 'raw' => $raw, 'kind' => 'custom' );
		}

		$declared_name = axismundi_emoji_parse_shortcode( trim( (string) ( $tag['name'] ?? '' ) ) );
		if ( null === $declared_name || $declared_name['key'] !== $parsed['key'] ) {
			return null;
		}

		/*
		 * Authority comes from the declaration's `id`, which is a URL the sender had to
		 * publish under. Taking it from the shortcode instead would let anyone mint
		 * `custom:victim.example:foo` by typing it — a chip attributed to a site that
		 * never declared the emoji.
		 */
		// Through the Emoji plugin's own rule, so a reaction key names the authority the
		// registry filed the emoji under -- including its port, on a site that has one.
		$authority = '' !== (string) $declared_name['authority']
			? strtolower( (string) $declared_name['authority'] )
			: axismundi_emoji_url_authority( (string) ( $tag['id'] ?? '' ) );
		if ( '' === $authority ) {
			return null;
		}
		// A qualified `content` states its own authority. When the two disagree one of them
		// is wrong and nothing distinguishes which, so neither is trusted.
		$claimed = (string) $parsed['authority'];
		if ( '' !== $claimed && strtolower( $claimed ) !== $authority ) {
			return null;
		}
		return array( 'key' => 'custom:' . $authority . ':' . $parsed['key'], 'raw' => $raw, 'kind' => 'custom' );
	}

	/*
	 * One extended grapheme cluster, which is the unit a reader perceives as a single
	 * emoji: counting codepoints would reject flags, skin tones, and ZWJ sequences, all of
	 * which are several codepoints and one emoji.
	 *
	 * `grapheme_strlen()` rather than PCRE's `\X`, which is not the same test here —
	 * measured, `^\X$` matches `❤👍` as a single cluster and would let two emoji through as
	 * one reaction. intl implements UAX#29 and returns 2 for that, 1 for a flag, a toned
	 * thumb, and a ZWJ family.
	 */
	$stripped = preg_replace( '/[\x{FE0E}\x{FE0F}]/u', '', $raw );
	$stripped = is_string( $stripped ) ? $stripped : '';
	if ( '' === $stripped ) {
		return null;
	}
	$single_grapheme = ! function_exists( 'grapheme_strlen' ) || 1 === grapheme_strlen( $stripped );
	// A single grapheme can be ordinary text. The fallback is also our deliberately
	// conservative emoji-membership check when ext-intl is unavailable.
	if ( ! $single_grapheme || ! axismundi_act_single_emoji_fallback( $stripped ) ) {
		return null;
	}
	// A lone ASCII character is text, not a reaction; without this, `Like` with content "a"
	// would become a chip.
	if ( 1 === preg_match( '/^[\x00-\x7F]$/', $stripped ) ) {
		return null;
	}
	$characters = preg_split( '//u', $stripped, -1, PREG_SPLIT_NO_EMPTY );
	if ( ! is_array( $characters ) ) {
		return null;
	}
	$codepoints = array();
	foreach ( $characters as $char ) {
		$codepoint = axismundi_act_unicode_codepoint( $char );
		if ( null === $codepoint ) {
			return null;
		}
		$codepoints[] = sprintf( 'U+%04X', $codepoint );
	}
	return array( 'key' => 'unicode:' . implode( ' ', $codepoints ), 'raw' => $raw, 'kind' => 'unicode' );
}

/**
 * Reaction chips for one object, each counting the Actors behind it.
 *
 * `COUNT(DISTINCT actor_uri_hash)` inside each key, which is the same aggregate the plain
 * Like count uses and a different partition: one Actor sending `❤️` twice is one person on
 * that chip, and the same Actor also sending `👍` is one person on each of two chips.
 *
 * @param string $object_uri Reacted-to object.
 * @return array<int,array{reaction_key:string,reaction_raw:string,count:int}>
 */
function axismundi_act_get_reaction_counts( string $object_uri ) : array {
	global $wpdb;
	$uri = axismundi_act_uri( $object_uri );
	if ( '' === $uri || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return array();
	}
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed aggregate over the authoritative ledger.
	$rows = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT reaction_key, MIN(reaction_raw) AS reaction_raw, COUNT(DISTINCT actor_uri_hash) AS actor_count
			   FROM {$table}
			  WHERE object_uri_hash = %s AND object_uri = %s
			    AND effective_status = 'active'
			    AND reaction_key IS NOT NULL
			  GROUP BY reaction_key
			  ORDER BY actor_count DESC, reaction_key ASC",
			hash( 'sha256', $uri ),
			$uri
		),
		ARRAY_A
	);
	return array_map(
		static fn( array $row ) : array => array(
			'reaction_key' => (string) $row['reaction_key'],
			'reaction_raw' => (string) $row['reaction_raw'],
			'count'        => (int) $row['actor_count'],
		),
		$rows
	);
}

/**
 * Effective reaction Activities for an Object's public FEP-c0e0 collection.
 *
 * Counts are a UI projection; the collection intentionally returns the original
 * Like-with-content or EmojiReact payloads so peers can inspect the evidence.
 *
 * @param string $object_uri Reacted-to object.
 * @param int    $limit      Bounded collection size.
 * @return Axismundi_Activity[]
 */
function axismundi_act_get_effective_reactions( string $object_uri, int $limit = 100 ) : array {
	global $wpdb;
	$uri = axismundi_act_uri( $object_uri );
	if ( '' === $uri || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return array();
	}
	$table = axismundi_act_activities_table();
	$limit = max( 1, min( 200, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact public reaction collection over the authoritative ledger.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE object_uri_hash = %s AND object_uri = %s AND effective_status = 'active' AND reaction_key IS NOT NULL ORDER BY COALESCE(published_at, received_at, created_at) DESC, id DESC LIMIT %d", hash( 'sha256', $uri ), $uri, $limit ), ARRAY_A );
	return array_map( 'axismundi_act_hydrate', $rows );
}

/**
 * How many effective reaction Activities one Object has.
 *
 * Separate from the returned page: `axismundi_act_get_effective_reactions()` is bounded,
 * and reporting the page size as `totalItems` would understate a busy Object the moment it
 * passes the bound. Counted over exactly the same filter, so the two never disagree.
 *
 * @param string $object_uri Reacted-to object.
 */
function axismundi_act_get_reaction_activity_count( string $object_uri ) : int {
	global $wpdb;
	$uri = axismundi_act_uri( $object_uri );
	if ( '' === $uri || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return 0;
	}
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed aggregate over the authoritative ledger.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE object_uri_hash = %s AND object_uri = %s AND effective_status = 'active' AND reaction_key IS NOT NULL", hash( 'sha256', $uri ), $uri ) );
}

/**
 * The reaction keys one Actor currently has on one object.
 *
 * Plural on purpose. FEP-c0e0 permits several reactions per Actor and Akkoma sends them;
 * Misskey allows one. The ledger records what arrived, and a single-choice rule belongs to
 * whichever peer imposes it, not here.
 *
 * @param string $actor_uri  Reacting Actor.
 * @param string $object_uri Reacted-to object.
 * @return string[]
 */
function axismundi_act_get_actor_reactions( string $actor_uri, string $object_uri ) : array {
	global $wpdb;
	$actor  = axismundi_act_uri( $actor_uri );
	$object = axismundi_act_uri( $object_uri );
	if ( '' === $actor || '' === $object || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return array();
	}
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed lookup over the authoritative ledger.
	return array_values(
		array_unique(
			array_map(
				'strval',
				(array) $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT reaction_key FROM {$table}
						  WHERE actor_uri_hash = %s AND actor_uri = %s
						    AND object_uri_hash = %s AND object_uri = %s
						    AND effective_status = 'active' AND reaction_key IS NOT NULL",
						hash( 'sha256', $actor ),
						$actor,
						hash( 'sha256', $object ),
						$object
					)
				)
			)
		)
	);
}
