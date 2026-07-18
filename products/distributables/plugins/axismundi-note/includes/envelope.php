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
	$home = wp_parse_url( home_url( '/' ) );
	$part = wp_parse_url( $uri );
	if ( ! is_array( $home ) || ! is_array( $part )
		|| isset( $part['user'] ) || isset( $part['pass'] ) || isset( $part['fragment'] )
		|| strtolower( (string) ( $home['scheme'] ?? '' ) ) !== strtolower( (string) ( $part['scheme'] ?? '' ) )
		|| strtolower( (string) ( $home['host'] ?? '' ) ) !== strtolower( (string) ( $part['host'] ?? '' ) )
		|| (int) ( $home['port'] ?? 0 ) !== (int) ( $part['port'] ?? 0 )
		|| untrailingslashit( (string) ( $home['path'] ?? '' ) ) !== untrailingslashit( (string) ( $part['path'] ?? '' ) )
	) {
		return null;
	}
	$args = array();
	wp_parse_str( (string) ( $part['query'] ?? '' ), $args );
	if ( array( 'ax_note' ) !== array_keys( $args ) ) {
		return null;
	}
	$uuid = strtolower( trim( (string) $args['ax_note'] ) );
	return preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid ) ? $uuid : null;
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
 * current author only while the envelope is unlocked; once increment 5 records
 * the first Create it sets attribution_locked_at and the Actor URI freezes.
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
	$locked   = is_array( $existing ) && ! empty( $existing['attribution_locked_at'] );
	$uuid     = is_array( $existing ) ? (string) $existing['local_uuid'] : wp_generate_uuid4();
	$actor    = $locked ? (string) $existing['actor_uri'] : axismundi_note_author_actor_uri( $post );

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
		'language_tag'            => axismundi_note_sanitize_language( $fields['language_tag'] ?? ( $existing['language_tag'] ?? '' ) ),
		'in_reply_to_uri'         => $in_reply_to,
		'in_reply_to_uri_hash'    => '' === $in_reply_to ? '' : hash( 'sha256', $in_reply_to ),
		'context_uri'             => $context,
		'context_uri_hash'        => '' === $context ? '' : hash( 'sha256', $context ),
		'is_sensitive'            => $sensitive,
		'content_warning'         => $warning,
		'mention_actor_uris_json' => (string) wp_json_encode( $mentions ),
		'updated_at'              => $now,
	);
	$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' );

	if ( is_array( $existing ) ) {
		$result = $wpdb->update( axismundi_note_table(), $data, array( 'post_id' => $post_id ), $format, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
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
 * Convert one Note's envelope to a tombstone on permanent post deletion.
 *
 * The row is never dropped: the canonical UUID and Actor snapshot must survive
 * the Core Post so a later Delete Activity and Tombstone projection stay
 * expressible. Trash is not deletion, so this fires only on hard delete.
 */
function axismundi_note_on_before_delete_post( int $post_id, WP_Post $post ) : void {
	if ( AXISMUNDI_NOTE_POST_TYPE !== $post->post_type || ! axismundi_note_ready() ) {
		return;
	}
	if ( ! is_array( axismundi_note_get( $post_id ) ) ) {
		return;
	}
	global $wpdb;
	$now = current_time( 'mysql', true );
	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		axismundi_note_table(),
		array( 'object_status' => 'tombstone', 'deleted_at' => $now, 'updated_at' => $now ),
		array( 'post_id' => $post_id ),
		array( '%s', '%s', '%s' ),
		array( '%d' )
	);
}
add_action( 'before_delete_post', 'axismundi_note_on_before_delete_post', 10, 2 );
