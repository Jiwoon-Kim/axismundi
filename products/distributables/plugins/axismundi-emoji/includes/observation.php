<?php
/**
 * Recording emoji observed in federated payloads.
 *
 * Observation is metadata only. Nothing is downloaded here and nothing becomes
 * visible: a new row is `pending` and its shortcode stays plain text until a human
 * approves it (docs §7). The point of this layer is to make the review queue exist
 * without having fetched a byte.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit;

/**
 * A hearsay Emoji id is a verification candidate, never canonical metadata.
 *
 * The worker may only dereference HTTPS URIs hosted by the authority named in the
 * shortcode/id identity. This keeps an unrelated sender from turning the verification
 * queue into a request queue for arbitrary hosts, while still retaining the one URL
 * needed to ask the named authority what its Emoji actually is.
 *
 * @param array<string,mixed> $descriptor Parsed tag descriptor.
 * @return string HTTPS candidate URI, or an empty string.
 */
function axismundi_emoji_verification_uri( array $descriptor ) : string {
	$uri       = esc_url_raw( trim( (string) ( $descriptor['declared_id'] ?? '' ) ) );
	$authority = strtolower( trim( (string) ( $descriptor['emoji_authority'] ?? '' ) ) );
	$host      = axismundi_emoji_url_authority( $uri );
	$scheme    = strtolower( (string) wp_parse_url( $uri, PHP_URL_SCHEME ) );
	return '' !== $authority && $authority === $host && 'https' === $scheme ? $uri : '';
}

/**
 * Record one observed emoji, creating or refreshing its registry row.
 *
 * The interesting case is re-observation. Identity is `(emoji_authority,
 * shortcode_key)` and survives a re-upload, so the same key can come back pointing at
 * a different picture. `updated` is the only standard signal for that, which is why a
 * strictly newer value sends an approved row back for review rather than quietly
 * serving the old cache under a name that now means something else.
 *
 * Fields the remote controls are refreshed on every sighting; fields we own — review
 * state, timestamps of our decisions — are not.
 *
 * @param array<string,mixed> $descriptor From {@see axismundi_emoji_descriptor_from_tag()}.
 * @return int|null Registry row id, or null when the row could not be written.
 */
function axismundi_emoji_observe( array $descriptor ) : ?int {
	global $wpdb;
	$authority = (string) ( $descriptor['emoji_authority'] ?? '' );
	$key       = (string) ( $descriptor['shortcode_key'] ?? '' );
	if ( '' === $authority || '' === $key || ! axismundi_emoji_ready() ) {
		return null;
	}

	$now      = current_time( 'mysql', true );
	$existing = axismundi_emoji_get( $authority, $key );
	$table    = axismundi_emoji_table();

	/*
	 * Only the authority may define its own emoji.
	 *
	 * Authority is resolved from the emoji's `id` host or an explicit `@domain`, both of
	 * which are strings a third party can simply type. Without this gate, any Object
	 * from anywhere could declare `:mastodon:` with a Mastodon `id`, an `icon.url` on a
	 * host it controls, and an `updated` far in the future — overwriting the canonical
	 * row's image source and, because a newer `updated` re-queues, pulling an already
	 * approved emoji back into review. That is cache poisoning through a field nobody
	 * authenticates.
	 *
	 * A third-party sighting is still worth recording: it proves the emoji is in use and
	 * keeps the reference graph honest for GC. It just may not say what the emoji *is*.
	 * The canonical fields are written when the declaring host is the authority, or
	 * later by an enrichment fetch of the emoji `id` from that authority.
	 *
	 * Note this correctly makes every qualified name hearsay: `:09_bird@hoto.moe:` seen
	 * on a misskey.io note is misskey.io talking about hoto.moe's emoji.
	 */
	$first_party = ! empty( $descriptor['first_party'] );

	if ( ! $first_party ) {
		$verification_uri = axismundi_emoji_verification_uri( $descriptor );
		if ( null === $existing ) {
			/*
			 * Record its existence, unverified, so the enrichment fetch has something to
			 * work from and the queue shows it.
			 *
			 * Always `pending`, even where the operator has set this authority to
			 * auto-approve. An auto-approval is a judgement about that authority's
			 * emoji, and nothing here came from that authority — only a claim about it.
			 * Honouring the default now would mint a row labelled `approved` that cannot
			 * render for want of a source URL, which is a lie in the admin UI rather
			 * than a safe default. The rule applies once the metadata is verified.
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
			$done = $wpdb->insert(
				$table,
				array(
					'emoji_authority' => $authority,
					'shortcode_key'   => $key,
					'shortcode'       => (string) ( $descriptor['shortcode'] ?? '' ),
					'verification_uri'=> $verification_uri,
					'scope'           => 'remote',
					'source_kind'     => 'remote',
					'review_status'   => 'pending',
					'review_reason'   => 'unverified',
					'first_seen_at'   => $now,
					'last_seen_at'    => $now,
				)
			);
			return false === $done ? null : (int) $wpdb->insert_id;
		}
		// Touch only what a bystander is entitled to affect. A valid candidate URI fills
		// an empty slot, but can never replace one already queued for verification.
		$fields = array( 'last_seen_at' => $now );
		if ( '' !== $verification_uri && '' === (string) ( $existing['verification_uri'] ?? '' ) ) {
			$fields['verification_uri'] = $verification_uri;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
		$wpdb->update( $table, $fields, array( 'id' => (int) $existing['id'] ) );
		return (int) $existing['id'];
	}

	$remote_fields = array(
		'shortcode'           => (string) ( $descriptor['shortcode'] ?? '' ),
		'declared_id'         => (string) ( $descriptor['declared_id'] ?? '' ),
		'source_url'          => (string) ( $descriptor['source_url'] ?? '' ),
		'updated_raw'         => (string) ( $descriptor['updated_raw'] ?? '' ),
		'updated_at'          => $descriptor['updated_at'] ?? null,
		'declared_media_type' => (string) ( $descriptor['declared_media_type'] ?? '' ),
		'license_raw'         => (string) ( $descriptor['license_raw'] ?? '' ),
		'license_text'        => (string) ( $descriptor['license_text'] ?? '' ),
		'license_state'       => (string) ( $descriptor['license_state'] ?? 'unknown' ),
		'last_seen_at'        => $now,
		'verified_at'         => $now,
	);
	if ( null !== $existing && in_array( (string) ( $existing['review_reason'] ?? '' ), array( 'unverified', 'verify_queued', 'verify_failed' ), true ) ) {
		/*
		 * The row has just been told what it is by the authority itself, so everything
		 * recorded while it was only hearsay is now spent.
		 *
		 * A default belongs to a verified authority, not to a bystander's assertion.
		 * Applying it only now makes an auto-approved authority useful without allowing
		 * hearsay to mint the incoherent state "approved, but no source to render".
		 *
		 * The retry counters are cleared with it. Leaving a stale `next_attempt_at` on a
		 * row that has succeeded would silently withhold it from a later queue it has
		 * every right to be in.
		 */
		$remote_fields['review_status']    = axismundi_emoji_authority_default( $authority );
		$remote_fields['review_reason']    = null;
		$remote_fields['verification_uri'] = null;
		$remote_fields['failure_count']    = 0;
		$remote_fields['next_attempt_at']  = null;
	}

	if ( null === $existing ) {
		// An authority the operator has already ruled on decides for its emoji; without
		// such a ruling the row waits for a person.
		$remote_fields['emoji_authority'] = $authority;
		$remote_fields['shortcode_key']   = $key;
		$remote_fields['scope']           = 'remote';
		$remote_fields['source_kind']     = 'remote';
		$remote_fields['review_status']   = axismundi_emoji_authority_default( $authority );
		$remote_fields['first_seen_at']   = $now;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
		$done = $wpdb->insert( $table, $remote_fields );
		return false === $done ? null : (int) $wpdb->insert_id;
	}

	if ( axismundi_emoji_updated_is_newer( (string) ( $descriptor['updated_raw'] ?? '' ), (string) ( $existing['updated_raw'] ?? '' ) )
		&& 'approved' === (string) $existing['review_status'] ) {
		/*
		 * Back to the queue, and marked so the operator can tell this apart from a
		 * first sighting: an emoji that was visible and is now text again is a
		 * regression somebody has to be told about, not something to discover.
		 */
		$remote_fields['review_status'] = 'pending';
		$remote_fields['review_reason'] = 'changed';
		$remote_fields['cached_path']   = null;
		$remote_fields['static_path']   = null;
		$remote_fields['fetched_at']    = null;
		$remote_fields['failure_count'] = 0;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	$done = $wpdb->update( $table, $remote_fields, array( 'id' => (int) $existing['id'] ) );
	return false === $done ? null : (int) $existing['id'];
}

/**
 * Whether an incoming `updated` is strictly newer than the stored one.
 *
 * Strictly, and only when both parse. An unparsable or absent value is not evidence
 * of change, and treating it as such would re-queue an approved emoji every time a
 * server omitted the field.
 *
 * @param string $incoming Incoming raw value.
 * @param string $stored   Stored raw value.
 * @return bool
 */
function axismundi_emoji_updated_is_newer( string $incoming, string $stored ) : bool {
	if ( '' === $incoming || '' === $stored ) {
		return false;
	}
	$a = strtotime( $incoming );
	$b = strtotime( $stored );
	return false !== $a && false !== $b && $a > $b;
}

/**
 * Link an emoji to the Object or Actor that declared it.
 *
 * References are what make garbage collection possible: an emoji nothing points at
 * any more is an emoji whose bytes can go. They are also how a re-observation
 * refreshes `last_seen_at` without touching the emoji's own review state.
 *
 * @param int    $emoji_id     Registry row.
 * @param string $subject_kind object|actor.
 * @param string $subject_uri  Declaring subject URI.
 * @return bool
 */
function axismundi_emoji_add_reference( int $emoji_id, string $subject_kind, string $subject_uri ) : bool {
	global $wpdb;
	$subject_kind = 'actor' === $subject_kind ? 'actor' : 'object';
	$subject_uri  = trim( $subject_uri );
	if ( $emoji_id <= 0 || '' === $subject_uri || ! axismundi_emoji_ready() ) {
		return false;
	}
	$table = axismundi_emoji_references_table();
	$now   = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; values prepared.
	$done = $wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$table} (emoji_id, subject_kind, subject_uri_hash, last_seen_at) VALUES (%d, %s, %s, %s)
			 ON DUPLICATE KEY UPDATE last_seen_at = VALUES(last_seen_at)",
			$emoji_id,
			$subject_kind,
			hash( 'sha256', $subject_uri ),
			$now
		)
	);
	return false !== $done;
}

/**
 * Observe every emoji one payload declares, and record what declared them.
 *
 * @param array<string,mixed> $payload      Object or Actor document.
 * @param string              $subject_uri  Declaring subject URI.
 * @param string              $subject_kind object|actor.
 * @return int Number of emoji recorded.
 */
function axismundi_emoji_observe_payload( array $payload, string $subject_uri, string $subject_kind = 'object' ) : int {
	$recorded = 0;
	foreach ( axismundi_emoji_descriptors_from_payload( $payload, $subject_uri ) as $descriptor ) {
		$emoji_id = axismundi_emoji_observe( $descriptor );
		if ( null === $emoji_id ) {
			continue;
		}
		axismundi_emoji_add_reference( $emoji_id, $subject_kind, $subject_uri );
		/*
		 * Optional integration point: Actors may cache NodeInfo for this authority, but
		 * Emoji neither requires that plugin nor uses its record as emoji verification.
		 */
		do_action( 'axismundi_emoji_authority_observed', (string) $descriptor['emoji_authority'] );
		++$recorded;
	}
	return $recorded;
}

/**
 * Whether an emoji may currently be rendered as an image.
 *
 * Both halves are required. Approval without bytes would emit a broken image, and
 * bytes without approval would show something nobody has looked at. Anything else
 * falls back to the plain shortcode, which is a correct rendering rather than a
 * failure state.
 *
 * @param array<string,mixed> $row Registry row.
 * @return bool
 */
function axismundi_emoji_is_renderable( array $row ) : bool {
	return 'approved' === (string) ( $row['review_status'] ?? '' )
		&& '' !== (string) ( $row['cached_path'] ?? '' );
}
