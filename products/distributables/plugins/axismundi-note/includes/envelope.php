<?php
/**
 * Note federation envelope read/write API.
 *
 * The envelope owns the authored federation fields that have no Core Post home:
 * visibility, language, reply/context, sensitivity, and the explicit mention
 * list. It performs no Activity write, JSON-LD projection, or network request.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_NOTE_MENTION_MAX_COUNT = 50;
const AXISMUNDI_NOTE_MENTION_MAX_BYTES = 8192;

/** Canonical object identifier for one Note UUID: stable, permalink-independent. */
function axismundi_note_object_uri( string $uuid ) : string {
	$uuid = strtolower( trim( $uuid ) );
	return '' === $uuid ? '' : (string) add_query_arg( 'ax_note', rawurlencode( $uuid ), home_url( '/' ) );
}

/**
 * UUID of this site's canonical Note object URI, or null for any other address.
 *
 * The match is exact: same scheme, host, port, and home path, one and only one
 * `ax_note` query parameter, and no userinfo or fragment. An aliasing URI such
 * as a different path, an extra query argument, or a fragment is rejected so a
 * single Note has exactly one canonical identifier.
 */
function axismundi_note_local_uuid_from_uri( string $uri ) : ?string {
	$part = wp_parse_url( $uri );
	$args = array();
	if ( is_array( $part ) ) {
		wp_parse_str( (string) ( $part['query'] ?? '' ), $args );
	}
	$uuid = strtolower( trim( (string) ( $args['ax_note'] ?? '' ) ) );
	if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid ) ) {
		return null;
	}
	// Close every alias — a different path, an extra or duplicated query argument,
	// a fragment, or userinfo — by requiring an exact match against the one
	// reconstructed canonical URI. A Note has exactly one identifier string.
	return axismundi_note_object_uri( $uuid ) === $uri ? $uuid : null;
}

/** Normalize one absolute HTTP(S) URI, or '' when it is not a valid address. */
function axismundi_note_sanitize_uri( $value ) : string {
	$uri   = is_scalar( $value ) ? trim( (string) $value ) : '';
	$parts = wp_parse_url( $uri );
	return is_array( $parts )
		&& in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true )
		&& ! empty( $parts['host'] )
		&& ! isset( $parts['user'], $parts['pass'] )
		? $uri
		: '';
}

/** Canonical authored visibility, defaulting to public for an unknown value. */
function axismundi_note_sanitize_visibility( $value ) : string {
	$canonical = function_exists( 'axismundi_act_canonical_visibility' )
		? axismundi_act_canonical_visibility( (string) $value )
		: '';
	return '' !== $canonical ? $canonical : 'public';
}

/** Bounded BCP-47 language subtag string, or '' when empty. */
function axismundi_note_sanitize_language( $value ) : string {
	$tag = is_scalar( $value ) ? trim( (string) $value ) : '';
	return preg_match( '/^[A-Za-z0-9-]{1,35}$/', $tag ) ? $tag : '';
}

/** Normalize one candidate to a usable BCP-47 tag, or '' for empty/undetermined. */
function axismundi_note_normalize_language( string $language ) : string {
	$tag = function_exists( 'axismundi_actors_normalize_language_tag' )
		? axismundi_actors_normalize_language_tag( $language )
		: axismundi_note_sanitize_language( str_replace( '_', '-', $language ) );
	return 'und' === $tag ? '' : $tag;
}

/**
 * Resolve the effective BCP-47 language for one Note.
 *
 * The stored envelope value wins outright — it is the authored (and, after the
 * first Create, frozen) snapshot. Only when a draft has none does the read-time
 * chain apply: the author Actor's default language, then the author's WordPress
 * locale, then the site locale. Increment 5 writes the resolved value into the
 * envelope at the first Create; this resolver never stores anything.
 */
function axismundi_note_effective_language( WP_Post $post ) : string {
	$envelope = axismundi_note_get( $post->ID );
	$stored   = is_array( $envelope ) ? axismundi_note_normalize_language( (string) $envelope['language_tag'] ) : '';
	if ( '' !== $stored ) {
		return $stored;
	}
	$candidates = array();
	if ( function_exists( 'axismundi_actors_get_for_user' ) ) {
		$actor = axismundi_actors_get_for_user( (int) $post->post_author );
		if ( $actor instanceof Axismundi_Actor ) {
			$candidates[] = $actor->get_default_language();
		}
	}
	if ( (int) $post->post_author > 0 ) {
		$candidates[] = get_user_locale( (int) $post->post_author );
	}
	$candidates[] = function_exists( 'axismundi_actors_site_language' ) ? axismundi_actors_site_language() : get_locale();
	foreach ( $candidates as $candidate ) {
		$normalized = axismundi_note_normalize_language( (string) $candidate );
		if ( '' !== $normalized ) {
			return $normalized;
		}
	}
	return 'und';
}

/**
 * Validate one explicit mention list, failing closed on any bad or excess input.
 *
 * URI shape only is enforced here; public Actor identity is verified at the
 * publish boundary. An invalid URI or an over-limit list is rejected rather than
 * silently narrowed, so a malformed recipient never disappears from the author's
 * intent. Blank lines are ignored. Body-derived anchor mentions stay best-effort
 * and are never routed through this validator.
 *
 * @return string[]|WP_Error
 */
function axismundi_note_validate_mentions( $value ) {
	$value = is_array( $value ) ? $value : preg_split( '/[\r\n,]+/', (string) $value );
	$uris  = array();
	foreach ( (array) $value as $member ) {
		$raw = is_scalar( $member ) ? trim( (string) $member ) : '';
		if ( '' === $raw ) {
			continue;
		}
		$uri = axismundi_note_sanitize_uri( $raw );
		if ( '' === $uri ) {
			return new WP_Error( 'ax_note_mention', __( 'Every mentioned recipient must be a valid Actor URI.', 'axismundi-note' ) );
		}
		$uris[] = $uri;
	}
	$uris = array_values( array_unique( $uris ) );
	if ( count( $uris ) > AXISMUNDI_NOTE_MENTION_MAX_COUNT
		|| strlen( (string) wp_json_encode( $uris ) ) > AXISMUNDI_NOTE_MENTION_MAX_BYTES ) {
		return new WP_Error( 'ax_note_mention_limit', __( 'That is too many mentioned recipients for one Note.', 'axismundi-note' ) );
	}
	return $uris;
}

/**
 * Validate one authored quote target, failing closed on anything but a real URI.
 *
 * Unlike in_reply_to/context, an invalid quote target is never silently
 * dropped: it authorizes a lifecycle branch (self/local-other/remote consent),
 * so a malformed value must never be mistaken for "no quote" and skip the
 * QuoteRequest gate. Only the literal string '' is a deliberate clear; any
 * other non-URI value -- including a non-scalar, boolean, or number that would
 * otherwise cast to an empty or misleading string -- fails closed rather than
 * being coerced into a clear it never asked for.
 *
 * @return string|WP_Error
 */
function axismundi_note_validate_quote_target( $value ) {
	if ( ! is_string( $value ) ) {
		return new WP_Error( 'ax_note_quote_target_uri', __( 'The quote target must be a valid absolute URI.', 'axismundi-note' ) );
	}
	$raw = trim( $value );
	if ( '' === $raw ) {
		return '';
	}
	$uri = axismundi_note_sanitize_uri( $raw );
	return '' !== $uri ? $uri : new WP_Error( 'ax_note_quote_target_uri', __( 'The quote target must be a valid absolute URI.', 'axismundi-note' ) );
}

/** Validate an explicit FEP-044f automatic-approval policy without widening it. */
function axismundi_note_validate_quote_policy( $value ) {
	if ( ! is_string( $value ) ) {
		return new WP_Error( 'ax_note_quote_policy', __( 'The Quote policy is not recognized.', 'axismundi-note' ) );
	}
	$policy = sanitize_key( $value );
	return in_array( $policy, array( 'anyone', 'followers', 'me' ), true )
		? $policy
		: new WP_Error( 'ax_note_quote_policy', __( 'The Quote policy is not recognized.', 'axismundi-note' ) );
}

/** Fetch one envelope row by its owning post id. */
function axismundi_note_get( int $post_id ) : ?array {
	global $wpdb;
	if ( $post_id <= 0 || ! axismundi_note_ready() ) {
		return null;
	}
	$table = axismundi_note_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- URI-keyed custom repository lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d", $post_id ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/** Fetch one envelope row by its immutable local UUID. */
function axismundi_note_get_by_uuid( string $uuid ) : ?array {
	global $wpdb;
	$uuid = strtolower( trim( $uuid ) );
	if ( '' === $uuid || ! axismundi_note_ready() ) {
		return null;
	}
	$table = axismundi_note_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- URI-keyed custom repository lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE local_uuid = %s", $uuid ), ARRAY_A );
	return is_array( $row ) && hash_equals( (string) $row['local_uuid'], $uuid ) ? $row : null;
}

/** Fetch one envelope row by its canonical object URI (hash-scoped, exact-verified). */
function axismundi_note_get_by_uri( string $uri ) : ?array {
	$uuid = axismundi_note_local_uuid_from_uri( $uri );
	return null !== $uuid && '' !== $uuid ? axismundi_note_get_by_uuid( $uuid ) : null;
}

/**
 * Ordered union of the stored explicit mentions and body-derived anchor mentions.
 *
 * The body-derived set is never copied into the envelope: it is re-derived from
 * post_content through the neutral Object Projections parser so deleting an
 * anchor removes the recipient, exactly as Core Post Article mentions behave.
 *
 * @return string[]
 */
function axismundi_note_mentions( WP_Post $post ) : array {
	$envelope = axismundi_note_get( $post->ID );
	$explicit = array();
	if ( is_array( $envelope ) ) {
		$decoded  = json_decode( (string) $envelope['mention_actor_uris_json'], true );
		$explicit = is_array( $decoded ) ? array_values( array_filter( array_map( 'strval', $decoded ) ) ) : array();
	}
	$derived = function_exists( 'axismundi_op_content_mention_uris' )
		? axismundi_op_content_mention_uris( $post->post_content )
		: array();
	return array_values( array_unique( array_merge( $explicit, $derived ) ) );
}

/** Resolve the draft attribution Actor URI for one post's current author. */
function axismundi_note_author_actor_uri( WP_Post $post ) : string {
	if ( ! function_exists( 'axismundi_actors_get_for_user' ) ) {
		return '';
	}
	$actor = axismundi_actors_get_for_user( (int) $post->post_author );
	return $actor instanceof Axismundi_Actor ? $actor->get_uri() : '';
}

/**
 * Create or update one Note's federation envelope from authored fields.
 *
 * The local UUID is minted once and never changes. Attribution follows the
	 * current author only while the envelope is unlocked; first federation exposure
	 * sets attribution_locked_at and freezes both the Actor and language snapshot.
 * Storage is lenient (URI shape only); strict public-Actor verification is a
 * publish-boundary concern. Callers own capability and nonce checks.
 *
 * @param array<string,mixed> $fields Authored envelope fields.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_note_save( int $post_id, array $fields ) {
	global $wpdb;
	if ( ! axismundi_note_ready() ) {
		return new WP_Error( 'ax_note_store', __( 'The Note envelope store is unavailable.', 'axismundi-note' ) );
	}
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || AXISMUNDI_NOTE_POST_TYPE !== $post->post_type ) {
		return new WP_Error( 'ax_note_post', __( 'A Note post is required.', 'axismundi-note' ) );
	}

	$existing = axismundi_note_get( $post_id );
	$uuid     = is_array( $existing ) ? (string) $existing['local_uuid'] : wp_generate_uuid4();
	// The current author is the candidate attribution; whether it is actually
	// written is decided atomically at UPDATE time against attribution_locked_at,
	// so a concurrent lock cannot be overwritten by an in-flight save.
	$actor    = axismundi_note_author_actor_uri( $post );

	// An absent field preserves the stored value; a present field overwrites it.
	// An explicitly invalid value fails closed rather than widening the audience.
	if ( array_key_exists( 'visibility', $fields ) ) {
		$visibility = function_exists( 'axismundi_act_canonical_visibility' ) ? axismundi_act_canonical_visibility( (string) $fields['visibility'] ) : '';
		if ( '' === $visibility ) {
			return new WP_Error( 'ax_note_visibility', __( 'The audience visibility is not recognized.', 'axismundi-note' ) );
		}
	} else {
		$visibility = axismundi_note_sanitize_visibility( $existing['visibility'] ?? 'public' );
	}

	if ( array_key_exists( 'mention_actor_uris', $fields ) ) {
		$mentions = axismundi_note_validate_mentions( $fields['mention_actor_uris'] );
		if ( is_wp_error( $mentions ) ) {
			return $mentions;
		}
	} else {
		$prior    = is_array( $existing ) ? json_decode( (string) $existing['mention_actor_uris_json'], true ) : array();
		$mentions = is_array( $prior ) ? array_values( array_filter( array_map( 'strval', $prior ) ) ) : array();
	}

	if ( array_key_exists( 'language_tag', $fields ) ) {
		$raw_language = is_scalar( $fields['language_tag'] ) ? trim( (string) $fields['language_tag'] ) : '';
		$language     = '' === $raw_language ? '' : axismundi_note_normalize_language( $raw_language );
		if ( '' !== $raw_language && '' === $language ) {
			return new WP_Error( 'ax_note_language', __( 'Enter a valid BCP-47 language tag.', 'axismundi-note' ) );
		}
	} else {
		$language = is_array( $existing ) ? axismundi_note_normalize_language( (string) $existing['language_tag'] ) : '';
	}
	if ( is_array( $existing )
		&& ! empty( $existing['attribution_locked_at'] )
		&& array_key_exists( 'language_tag', $fields )
		&& ! hash_equals( (string) $existing['language_tag'], $language ) ) {
		return new WP_Error( 'ax_note_language_locked', __( 'A federated Note keeps its original language snapshot.', 'axismundi-note' ) );
	}

	if ( array_key_exists( 'quote_target_uri', $fields ) ) {
		$quote_target = axismundi_note_validate_quote_target( $fields['quote_target_uri'] );
		if ( is_wp_error( $quote_target ) ) {
			return $quote_target;
		}
	} else {
		$quote_target = (string) ( $existing['quote_target_uri'] ?? '' );
	}
	if ( array_key_exists( 'quote_policy', $fields ) ) {
		$quote_policy = axismundi_note_validate_quote_policy( $fields['quote_policy'] );
		if ( is_wp_error( $quote_policy ) ) {
			return $quote_policy;
		}
	} else {
		$quote_policy = (string) ( $existing['quote_policy'] ?? 'anyone' );
	}

	$sensitive   = array_key_exists( 'sensitive', $fields ) ? ( empty( $fields['sensitive'] ) ? 0 : 1 ) : (int) ( $existing['is_sensitive'] ?? 0 );
	$in_reply_to = axismundi_note_sanitize_uri( $fields['in_reply_to_uri'] ?? ( $existing['in_reply_to_uri'] ?? '' ) );
	$context     = axismundi_note_sanitize_uri( $fields['context_uri'] ?? ( $existing['context_uri'] ?? '' ) );
	$warning     = mb_substr( sanitize_text_field( (string) ( $fields['content_warning'] ?? ( $existing['content_warning'] ?? '' ) ) ), 0, 500 );
	$now         = current_time( 'mysql', true );

	$data = array(
		'post_id'                 => $post_id,
		'local_uuid'              => $uuid,
		'actor_uri'               => $actor,
		'actor_uri_hash'          => hash( 'sha256', $actor ),
		'visibility'              => $visibility,
		'language_tag'            => $language,
		'in_reply_to_uri'         => $in_reply_to,
		'in_reply_to_uri_hash'    => '' === $in_reply_to ? '' : hash( 'sha256', $in_reply_to ),
		'context_uri'             => $context,
		'context_uri_hash'        => '' === $context ? '' : hash( 'sha256', $context ),
		'quote_target_uri'        => $quote_target,
		'quote_target_uri_hash'   => '' === $quote_target ? '' : hash( 'sha256', $quote_target ),
		'quote_policy'            => $quote_policy,
		'is_sensitive'            => $sensitive,
		'content_warning'         => $warning,
		'mention_actor_uris_json' => (string) wp_json_encode( $mentions ),
		'updated_at'              => $now,
	);
	$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' );

	if ( is_array( $existing ) ) {
		$table = axismundi_note_table();
		// actor_uri and its hash only change while attribution is still unlocked;
		// the CASE decides this in the same statement that writes the row.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Trusted table identifier; values are prepared.
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET
					visibility = %s, language_tag = %s, in_reply_to_uri = %s, in_reply_to_uri_hash = %s,
					context_uri = %s, context_uri_hash = %s, quote_target_uri = %s, quote_target_uri_hash = %s, quote_policy = %s,
					is_sensitive = %d, content_warning = %s,
					mention_actor_uris_json = %s, updated_at = %s,
					actor_uri = CASE WHEN attribution_locked_at IS NULL THEN %s ELSE actor_uri END,
					actor_uri_hash = CASE WHEN attribution_locked_at IS NULL THEN %s ELSE actor_uri_hash END
				WHERE post_id = %d",
				$data['visibility'], $data['language_tag'], $data['in_reply_to_uri'], $data['in_reply_to_uri_hash'],
				$data['context_uri'], $data['context_uri_hash'], $data['quote_target_uri'], $data['quote_target_uri_hash'], $data['quote_policy'],
				$data['is_sensitive'], $data['content_warning'],
				$data['mention_actor_uris_json'], $now, $actor, hash( 'sha256', $actor ), $post_id
			)
		);
	} else {
		$data['object_status'] = 'active';
		$data['created_at']    = $now;
		$format[]              = '%s';
		$format[]              = '%s';
		$result                = $wpdb->insert( axismundi_note_table(), $data, $format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( false === $result ) {
		return new WP_Error( 'ax_note_write', __( 'The Note envelope could not be written.', 'axismundi-note' ) );
	}

	$saved = axismundi_note_get( $post_id );
	return is_array( $saved ) ? $saved : new WP_Error( 'ax_note_write', __( 'The Note envelope could not be saved.', 'axismundi-note' ) );
}

/**
 * Structured envelope view for one Note, for the REST editor field.
 *
 * @return array<string,mixed>
 */
function axismundi_note_get_envelope( int $post_id ) : array {
	$row      = axismundi_note_get( $post_id );
	$mentions = array();
	$post     = get_post( $post_id );
	$quote_status = $post instanceof WP_Post && function_exists( 'axismundi_note_quote_status' )
		? axismundi_note_quote_status( $post )
		: array( 'state' => 'none', 'target' => '', 'request' => '', 'authorization' => '', 'error' => '' );
	if ( is_array( $row ) ) {
		$decoded  = json_decode( (string) $row['mention_actor_uris_json'], true );
		$mentions = is_array( $decoded ) ? array_values( array_filter( array_map( 'strval', $decoded ) ) ) : array();
	}
	return array(
		'visibility'     => is_array( $row ) ? (string) $row['visibility'] : 'public',
		'language'       => is_array( $row ) ? (string) $row['language_tag'] : '',
		'inReplyTo'      => is_array( $row ) ? (string) $row['in_reply_to_uri'] : '',
		'context'        => is_array( $row ) ? (string) $row['context_uri'] : '',
		'quoteTarget'    => is_array( $row ) ? (string) ( $row['quote_target_uri'] ?? '' ) : '',
		'quotePolicy'    => is_array( $row ) ? (string) ( $row['quote_policy'] ?? 'anyone' ) : 'anyone',
		'quoteStatus'    => $quote_status,
		'sensitive'      => is_array( $row ) && ! empty( $row['is_sensitive'] ),
		'contentWarning' => is_array( $row ) ? (string) $row['content_warning'] : '',
		'mentions'       => $mentions,
		'attachments'    => function_exists( 'axismundi_note_attachment_ids' ) ? axismundi_note_attachment_ids( $post_id ) : array(),
	);
}

/**
 * Validate and atomically store one structured authored envelope.
 *
 * The single source of authority for the block-editor panel: the React panel is
 * only an editing surface, so this maps the structured field to the same
 * fail-closed `axismundi_note_save()` contract. An absent key preserves its
 * stored value; a present invalid value fails the whole save.
 *
 * @param array<string,mixed> $envelope Structured authored fields.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_note_save_envelope( int $post_id, array $envelope ) {
	$attachments = null;
	if ( array_key_exists( 'attachments', $envelope ) ) {
		$attachments = function_exists( 'axismundi_note_validate_attachment_ids' )
			? axismundi_note_validate_attachment_ids( $post_id, $envelope['attachments'] )
			: new WP_Error( 'ax_note_attachment_provider', __( 'The Note attachment service is unavailable.', 'axismundi-note' ) );
		if ( is_wp_error( $attachments ) ) {
			return $attachments;
		}
	}
	$previous = axismundi_note_get_envelope( $post_id );
	$map = array(
		'visibility'     => 'visibility',
		'language'       => 'language_tag',
		'inReplyTo'      => 'in_reply_to_uri',
		'context'        => 'context_uri',
		'quoteTarget'    => 'quote_target_uri',
		'quotePolicy'    => 'quote_policy',
		'sensitive'      => 'sensitive',
		'contentWarning' => 'content_warning',
		'mentions'       => 'mention_actor_uris',
	);
	$fields = array();
	foreach ( $map as $incoming => $stored ) {
		if ( array_key_exists( $incoming, $envelope ) ) {
			$fields[ $stored ] = $envelope[ $incoming ];
		}
	}
	$saved = axismundi_note_save( $post_id, $fields );
	if ( is_wp_error( $saved ) || null === $attachments ) {
		return $saved;
	}
	$related = axismundi_note_replace_attachments( $post_id, $attachments );
	if ( is_wp_error( $related ) ) {
		// The relation store preserves its prior rows on failure. Compensate the
		// envelope write so one structured REST field never partially succeeds.
		$restore = $previous;
		unset( $restore['attachments'] );
		axismundi_note_save_envelope( $post_id, $restore );
		return $related;
	}
	return $saved;
}

/**
 * Freeze one Note's attribution at its first federation, idempotently.
 *
 * Increment 5 calls this the moment it records the first Create so a later
 * author change can never rewrite an already-published Object's attribution.
 * The conditional UPDATE only sets the timestamp while it is still NULL.
 *
 * @return bool|WP_Error
 */
function axismundi_note_lock_attribution( int $post_id ) {
	global $wpdb;
	if ( ! axismundi_note_ready() ) {
		return new WP_Error( 'ax_note_store', __( 'The Note envelope store is unavailable.', 'axismundi-note' ) );
	}
	$now   = current_time( 'mysql', true );
	$table = axismundi_note_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Trusted table identifier.
	$result = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET attribution_locked_at = %s, updated_at = %s WHERE post_id = %d AND attribution_locked_at IS NULL", $now, $now, $post_id ) );
	if ( false === $result ) {
		return new WP_Error( 'ax_note_write', __( 'The Note attribution could not be locked.', 'axismundi-note' ) );
	}
	if ( $result >= 1 ) {
		return true;
	}
	// Zero rows changed: distinguish an already-locked envelope from a missing one.
	$existing = axismundi_note_get( $post_id );
	if ( ! is_array( $existing ) ) {
		return new WP_Error( 'ax_note_post', __( 'There is no Note envelope to lock.', 'axismundi-note' ) );
	}
	return ! empty( $existing['attribution_locked_at'] )
		? true
		: new WP_Error( 'ax_note_write', __( 'The Note attribution could not be locked.', 'axismundi-note' ) );
}

/** Convert one active envelope to a tombstone; false only on a write failure. */
function axismundi_note_tombstone( int $post_id ) : bool {
	global $wpdb;
	$now   = current_time( 'mysql', true );
	$table = axismundi_note_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Trusted table identifier.
	$result = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET object_status = 'tombstone', deleted_at = %s, updated_at = %s WHERE post_id = %d AND object_status <> 'tombstone'", $now, $now, $post_id ) );
	return false !== $result;
}

/**
 * Guarantee a baseline envelope for every saved Note, including REST and WP-CLI.
 *
 * The Classic Editor meta box only runs in wp-admin, so this always-loaded hook
 * mints the canonical UUID and author attribution for programmatic creation.
 * It runs before the meta-box handler and preserves any authored field the
 * handler later writes.
 */
function axismundi_note_ensure_baseline( int $post_id, WP_Post $post ) : void {
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		|| wp_is_post_revision( $post_id )
		|| AXISMUNDI_NOTE_POST_TYPE !== $post->post_type
		|| 'auto-draft' === $post->post_status
	) {
		return;
	}
	if ( ! is_array( axismundi_note_get( $post_id ) ) ) {
		axismundi_note_save( $post_id, array() );
	}
}
add_action( 'save_post_' . AXISMUNDI_NOTE_POST_TYPE, 'axismundi_note_ensure_baseline', 9, 2 );

/**
 * Tombstone one Note's envelope at permanent deletion, aborting on write failure.
 *
 * The row is never dropped: the canonical UUID and Actor snapshot must survive
 * the Core Post so a later Delete Activity and Tombstone projection stay
 * expressible. `pre_delete_post` fires only on permanent deletion (not trash) and
 * can short-circuit it, so a failed tombstone write returns false to abort the
 * deletion rather than orphan an active envelope for a post that no longer exists.
 *
 * @param WP_Post|false|null $delete Short-circuit value (null lets deletion proceed).
 * @return WP_Post|false|null
 */
function axismundi_note_pre_delete_post( $delete, WP_Post $post ) {
	if ( AXISMUNDI_NOTE_POST_TYPE !== $post->post_type || ! axismundi_note_ready() ) {
		return $delete;
	}
	$envelope = axismundi_note_get( $post->ID );
	if ( ! is_array( $envelope ) || 'tombstone' === $envelope['object_status'] ) {
		return $delete;
	}
	return axismundi_note_tombstone( $post->ID ) ? $delete : false;
}
add_filter( 'pre_delete_post', 'axismundi_note_pre_delete_post', 10, 2 );
