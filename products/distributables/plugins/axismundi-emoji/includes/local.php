<?php
/**
 * Local emoji registration (docs §8).
 *
 * A local emoji is not a cached copy of somebody else's asset, and almost every rule
 * in §7 inverts here. There is no licence to interpret, no authority to trust, and no
 * approval to withhold — those questions are about a third party's work. What replaces
 * them is the opposite constraint: FEP-9098's Compatibility recommendations, which are
 * lenient guidance on receive and hard rules on send. This file is where they bind.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit;

/*
 * `AXISMUNDI_EMOJI_OUTBOUND_MAX_BYTES` and `AXISMUNDI_EMOJI_OUTBOUND_MEDIA_TYPES` are
 * declared with the rest of the contract in the plugin file. They are the FEP-9098
 * Compatibility figures, and this file is the first place they actually bind: everything
 * before E2 only ever received emoji, where the same numbers are guidance rather than
 * rules.
 */

/**
 * The authority a local emoji is published under.
 *
 * FEP-9098 identifies an emoji by `(name, domain)`, so ours carry this site's domain.
 * Actors already resolved what that is for WebFinger and federation, and disagreeing
 * with it here would mean this site publishes emoji under one name and Actors under
 * another. The `home_url()` fallback is only for a site running Emoji without Actors.
 *
 * @return string
 */
function axismundi_emoji_local_authority() : string {
	if ( function_exists( 'axismundi_actors_webfinger_authority' ) ) {
		$authority = strtolower( trim( (string) axismundi_actors_webfinger_authority() ) );
		if ( '' !== $authority ) {
			return $authority;
		}
	}
	$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	$port = wp_parse_url( home_url( '/' ), PHP_URL_PORT );
	return strtolower( $host . ( $port ? ':' . (int) $port : '' ) );
}

/**
 * Validate a shortcode we intend to publish.
 *
 * The parser accepts a qualified `:name@domain:` because that form really arrives from
 * Misskey. It must not be accepted here: qualifying a name is how one server refers to
 * *another* server's emoji, so a local emoji claiming one would publish an identity we
 * do not own.
 *
 * @param string $shortcode Shortcode with or without colons.
 * @return string|WP_Error Normalized lookup key.
 */
function axismundi_emoji_validate_local_shortcode( string $shortcode ) {
	$parsed = axismundi_emoji_parse_shortcode( $shortcode );
	if ( null === $parsed ) {
		return new WP_Error( 'ax_emoji_shortcode', __( 'A shortcode must be at least two characters of letters, digits, or underscores.', 'axismundi-emoji' ) );
	}
	if ( '' !== $parsed['authority'] ) {
		return new WP_Error( 'ax_emoji_shortcode_qualified', __( 'A local shortcode cannot name another server.', 'axismundi-emoji' ) );
	}
	return $parsed['key'];
}

/**
 * Find a local emoji by shortcode, whatever authority it was registered under.
 *
 * Uniqueness for local emoji is site-wide rather than per-authority, which is not the
 * same question the UNIQUE index answers. A site that changes domain keeps its existing
 * rows under the old authority, and a per-authority lookup would happily register a
 * second `:axismundi:` under the new one — two rows, one shortcode, and an outbound
 * `tag[]` that cannot say which is meant.
 *
 * @param string $key Normalized shortcode key.
 * @return array<string,mixed>|null
 */
function axismundi_emoji_local_get( string $key ) : ?array {
	global $wpdb;
	$key = strtolower( trim( $key ) );
	if ( '' === $key || ! axismundi_emoji_ready() ) {
		return null;
	}
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE scope = 'local' AND shortcode_key = %s", $key ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/** @return array<int,array<string,mixed>> Every local emoji, newest first. */
function axismundi_emoji_local_all() : array {
	global $wpdb;
	if ( ! axismundi_emoji_ready() ) {
		return array();
	}
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	return (array) $wpdb->get_results( "SELECT * FROM {$table} WHERE scope = 'local' ORDER BY category ASC, shortcode_key ASC", ARRAY_A );
}

/**
 * Create the blob root with a directory-listing guard.
 *
 * These files are not attachments, so nothing in Core will ever create this directory,
 * index it, or clean it up. The guard is ours to place for the same reason.
 *
 * @return bool
 */
function axismundi_emoji_ensure_blob_dir() : bool {
	$root = axismundi_emoji_blob_dir();
	if ( ! wp_mkdir_p( $root ) ) {
		return false;
	}
	$guard = $root . '/index.php';
	if ( ! file_exists( $guard ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- plugin-owned store guard.
		file_put_contents( $guard, "<?php\n// Silence is golden.\n" );
	}
	return true;
}

/**
 * Check bytes against what we are willing to publish.
 *
 * @param string $bytes File contents.
 * @return array{mime:string,animated:bool,frames:int,width:int,height:int}|WP_Error
 */
function axismundi_emoji_inspect_local_bytes( string $bytes ) {
	$size = strlen( $bytes );
	if ( 0 === $size ) {
		return new WP_Error( 'ax_emoji_empty', __( 'The file is empty.', 'axismundi-emoji' ) );
	}
	if ( $size > AXISMUNDI_EMOJI_OUTBOUND_MAX_BYTES ) {
		return new WP_Error(
			'ax_emoji_too_large',
			sprintf(
				/* translators: 1: file size, 2: maximum size. */
				__( 'Emoji this site publishes must be %2$s or smaller; this file is %1$s.', 'axismundi-emoji' ),
				size_format( $size ),
				size_format( AXISMUNDI_EMOJI_OUTBOUND_MAX_BYTES )
			)
		);
	}

	/*
	 * Our own allowlist, applied in addition to Core's upload validation rather than
	 * instead of it. `get_allowed_mime_types()` is filtered, and an SVG-enabling plugin —
	 * a common thing to install — would otherwise silently make SVG a valid emoji upload
	 * on a site that never decided that.
	 */
	$sniffed = axismundi_emoji_sniff( $bytes );
	if ( ! in_array( $sniffed['mime'], AXISMUNDI_EMOJI_OUTBOUND_MEDIA_TYPES, true ) ) {
		return new WP_Error( 'ax_emoji_type', __( 'Emoji must be a PNG, GIF, or WebP image.', 'axismundi-emoji' ) );
	}

	$dimensions = @getimagesizefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- malformed images report through the false return.
	$width      = is_array( $dimensions ) ? (int) ( $dimensions[0] ?? 0 ) : 0;
	$height     = is_array( $dimensions ) ? (int) ( $dimensions[1] ?? 0 ) : 0;
	if ( $width < 1 || $height < 1 ) {
		return new WP_Error( 'ax_emoji_dimensions', __( 'The image dimensions could not be read.', 'axismundi-emoji' ) );
	}
	if ( $width !== $height ) {
		return new WP_Error(
			'ax_emoji_square',
			sprintf(
				/* translators: 1: width, 2: height. */
				__( 'Emoji must be square; this image is %1$d×%2$d.', 'axismundi-emoji' ),
				$width,
				$height
			)
		);
	}
	if ( $width > AXISMUNDI_EMOJI_MAX_DIMENSION ) {
		return new WP_Error( 'ax_emoji_dimensions', __( 'The image is larger than this site stores.', 'axismundi-emoji' ) );
	}

	return array(
		'mime'     => $sniffed['mime'],
		'animated' => $sniffed['animated'],
		'frames'   => $sniffed['frames'],
		'width'    => $width,
		'height'   => $height,
	);
}

/**
 * Register bytes as a local emoji.
 *
 * A local row is `approved` on creation and never `pending`. The review axis exists to
 * decide whether to display somebody else's asset, and there is no such question about
 * one this site made and chose to upload — leaving it pending would ask the operator to
 * approve their own decision.
 *
 * @param string              $bytes File contents.
 * @param string              $shortcode Requested shortcode.
 * @param array<string,mixed> $args  category, aliases, local_only, outbound_allowed, is_sensitive.
 * @return array<string,mixed>|WP_Error The stored row.
 */
function axismundi_emoji_register_local( string $bytes, string $shortcode, array $args = array() ) {
	global $wpdb;
	if ( ! axismundi_emoji_ready() ) {
		return new WP_Error( 'ax_emoji_unavailable', __( 'The emoji registry is not installed.', 'axismundi-emoji' ) );
	}
	$key = axismundi_emoji_validate_local_shortcode( $shortcode );
	if ( is_wp_error( $key ) ) {
		return $key;
	}
	$existing = axismundi_emoji_local_get( $key );
	if ( is_array( $existing ) ) {
		return new WP_Error( 'ax_emoji_duplicate', sprintf( /* translators: %s: shortcode. */ __( '%s is already registered on this site.', 'axismundi-emoji' ), ':' . $key . ':' ) );
	}
	$inspected = axismundi_emoji_inspect_local_bytes( $bytes );
	if ( is_wp_error( $inspected ) ) {
		return $inspected;
	}

	$hash      = hash( 'sha256', $bytes );
	$extension = axismundi_emoji_extension_for( $inspected['mime'] );
	$relative  = axismundi_emoji_cache_relative_path( $hash, $extension );
	$absolute  = axismundi_emoji_blob_dir() . '/' . $relative;
	if ( ! axismundi_emoji_ensure_blob_dir() || ! wp_mkdir_p( dirname( $absolute ) ) ) {
		return new WP_Error( 'ax_emoji_store', __( 'The emoji store is not writable.', 'axismundi-emoji' ) );
	}
	/*
	 * Content-addressed, so an identical file already present is the same file. Writing
	 * it again would be correct but pointless; skipping the write is what lets a local
	 * upload and a cached remote emoji share one blob when they happen to be the same
	 * picture. Reference-counted GC is scope-blind for exactly this case.
	 */
	if ( ! file_exists( $absolute ) ) {
		$staging = $absolute . '.staging' . wp_generate_password( 8, false );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- plugin-owned store.
		if ( false === file_put_contents( $staging, $bytes ) ) {
			return new WP_Error( 'ax_emoji_store', __( 'The emoji file could not be written.', 'axismundi-emoji' ) );
		}
		// Publish by rename so a reader never sees a partial file at the final path.
		if ( ! rename( $staging, $absolute ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rename -- atomic publish within one directory.
			wp_delete_file( $staging );
			return new WP_Error( 'ax_emoji_store', __( 'The emoji file could not be published.', 'axismundi-emoji' ) );
		}
	}

	$static_relative = '';
	if ( $inspected['animated'] ) {
		$static_relative = axismundi_emoji_cache_relative_path( $hash, 'png', '-static' );
		$static_absolute = axismundi_emoji_blob_dir() . '/' . $static_relative;
		if ( ! file_exists( $static_absolute ) ) {
			$written = axismundi_emoji_write_static_rendition( $absolute, axismundi_emoji_blob_dir() . '/' . axismundi_emoji_cache_relative_path( $hash, 'png', '-static.staging' . wp_generate_password( 8, false ) ) );
			if ( '' === $written || ! rename( $written, $static_absolute ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rename -- atomic publish within one directory.
				if ( '' !== $written ) {
					wp_delete_file( $written );
				}
				axismundi_emoji_maybe_delete_file( $absolute, $hash );
				return new WP_Error( 'ax_emoji_static', __( 'A still frame could not be generated, so this animated emoji cannot be published.', 'axismundi-emoji' ) );
			}
		}
	}

	$now      = current_time( 'mysql', true );
	$aliases  = array_values( array_filter( array_map( 'sanitize_key', (array) ( $args['aliases'] ?? array() ) ) ) );
	$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
		axismundi_emoji_table(),
		array(
			'scope'            => 'local',
			'source_kind'      => 'local',
			'emoji_authority'  => axismundi_emoji_local_authority(),
			'shortcode'        => ':' . $key . ':',
			'shortcode_key'    => $key,
			'media_type'       => $inspected['mime'],
			'animated'         => $inspected['animated'] ? 1 : 0,
			'frame_count'      => (int) $inspected['frames'],
			'byte_size'        => strlen( $bytes ),
			'static_byte_size' => '' !== $static_relative ? (int) filesize( axismundi_emoji_blob_dir() . '/' . $static_relative ) : 0, // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize -- plugin-owned store.
			'width'            => $inspected['width'],
			'height'           => $inspected['height'],
			'content_hash'     => $hash,
			'cached_path'      => $relative,
			'static_path'      => $static_relative,
			'license_state'    => 'allowed',
			'review_status'    => 'approved',
			/*
			 * For a remote row `updated_at` is what the origin told us; for one of ours it is
			 * when we last changed it. Same meaning, same column, and it is what goes out as
			 * `updated` — a receiver caching our emoji has no other way to learn we replaced
			 * the picture, which is exactly the signal we rely on from them.
			 */
			'updated_at'       => $now,
			'reviewed_at'      => $now,
			'reviewed_by'      => get_current_user_id(),
			'local_only'       => ! empty( $args['local_only'] ) ? 1 : 0,
			'is_sensitive'     => ! empty( $args['is_sensitive'] ) ? 1 : 0,
			'category'         => '' !== (string) ( $args['category'] ?? '' ) ? sanitize_text_field( (string) $args['category'] ) : null,
			'picker_visible'   => 1,
			// `local_only` is a request not to redistribute, so it decides this rather
			// than merely annotating it; the two must not be able to disagree.
			'outbound_allowed' => ! empty( $args['local_only'] ) ? 0 : 1,
			'aliases'          => array() === $aliases ? null : wp_json_encode( $aliases ),
			'first_seen_at'    => $now,
			'last_seen_at'     => $now,
			'fetched_at'       => $now,
			'verified_at'      => $now,
		)
	);
	if ( false === $inserted ) {
		axismundi_emoji_maybe_delete_file( $absolute, $hash );
		return new WP_Error( 'ax_emoji_store', __( 'The emoji could not be registered.', 'axismundi-emoji' ) );
	}

	/** Fires after a local emoji is registered. @param int $emoji_id Registry row id. */
	do_action( 'axismundi_emoji_local_registered', (int) $wpdb->insert_id );
	return (array) axismundi_emoji_local_get( $key );
}

/**
 * Edit a local emoji's catalogue fields.
 *
 * The shortcode is deliberately not among them. It is the identity: already-published
 * messages carry `:name:` as text, and renaming would leave every one of them pointing at
 * something that no longer exists. Delete and re-add is the honest way to change a name,
 * because it is honestly a different emoji.
 *
 * Everything else is editable precisely because it is *not* carried on the wire.
 * ActivityPub's `tag[]` gives an Emoji a name, an icon, and an `updated` — no category, no
 * aliases, no licence. Those fields exist only in each server's own admin API, so an
 * imported emoji arrives without them and no amount of re-fetching would supply them.
 * Filling them in by hand is not a workaround; it is the only path there is.
 *
 * @param int                 $emoji_id Local registry row id.
 * @param array<string,mixed> $fields   category, aliases, is_sensitive, local_only, license_text.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_emoji_update_local( int $emoji_id, array $fields ) {
	global $wpdb;
	if ( ! axismundi_emoji_ready() ) {
		return new WP_Error( 'ax_emoji_unavailable', __( 'The emoji registry is not installed.', 'axismundi-emoji' ) );
	}
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND scope = 'local'", $emoji_id ), ARRAY_A );
	if ( ! is_array( $row ) ) {
		return new WP_Error( 'ax_emoji_missing', __( 'That local emoji does not exist.', 'axismundi-emoji' ) );
	}

	$update = array();
	if ( array_key_exists( 'category', $fields ) ) {
		$category             = sanitize_text_field( (string) $fields['category'] );
		$update['category']   = '' === $category ? null : $category;
	}
	if ( array_key_exists( 'aliases', $fields ) ) {
		$aliases           = is_array( $fields['aliases'] ) ? $fields['aliases'] : preg_split( '/[\s,]+/', (string) $fields['aliases'] );
		$aliases           = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $aliases ) ) ) );
		$update['aliases'] = array() === $aliases ? null : wp_json_encode( $aliases );
	}
	if ( array_key_exists( 'is_sensitive', $fields ) ) {
		$update['is_sensitive'] = empty( $fields['is_sensitive'] ) ? 0 : 1;
	}
	if ( array_key_exists( 'license_text', $fields ) ) {
		$license                 = sanitize_text_field( (string) $fields['license_text'] );
		$update['license_text']  = '' === $license ? null : $license;
		$update['license_state'] = axismundi_emoji_classify_license( $license );
	}

	/*
	 * Sharing is settled last, because the licence can veto it. A `restricted` licence is
	 * the one state that says in words that this may not travel, so it decides rather than
	 * merely advises — otherwise an operator could reclassify an emoji as restricted and
	 * leave it publishing, which is the contradiction the three-state axis exists to catch.
	 */
	$license_state = (string) ( $update['license_state'] ?? $row['license_state'] );
	if ( array_key_exists( 'local_only', $fields ) ) {
		$update['local_only'] = empty( $fields['local_only'] ) ? 0 : 1;
	}
	$local_only = (int) ( $update['local_only'] ?? $row['local_only'] );
	if ( 'restricted' === $license_state ) {
		$local_only           = 1;
		$update['local_only'] = 1;
	}
	$update['outbound_allowed'] = $local_only ? 0 : 1;

	if ( array() === $update ) {
		return $row;
	}
	// Moves whenever anything published changes, so `updated` on the wire stays truthful
	// and a receiver's cache invalidates instead of holding our first version forever.
	$update['updated_at'] = current_time( 'mysql', true );
	$wpdb->update( $table, $update, array( 'id' => $emoji_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.

	/** Fires after a local emoji's catalogue fields change. @param int $emoji_id Row id. */
	do_action( 'axismundi_emoji_local_updated', $emoji_id );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	return (array) $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $emoji_id ), ARRAY_A );
}

/**
 * Remove a local emoji.
 *
 * The blob is only collected when nothing else claims its hash, which is the case that
 * matters when a local upload happens to be byte-identical to a cached remote emoji.
 *
 * @param int $emoji_id Registry row id.
 * @return bool
 */
function axismundi_emoji_delete_local( int $emoji_id ) : bool {
	global $wpdb;
	if ( $emoji_id <= 0 || ! axismundi_emoji_ready() ) {
		return false;
	}
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND scope = 'local'", $emoji_id ), ARRAY_A );
	if ( ! is_array( $row ) ) {
		return false;
	}
	$hash     = (string) ( $row['content_hash'] ?? '' );
	$relative = (string) ( $row['cached_path'] ?? '' );
	$deleted  = $wpdb->delete( $table, array( 'id' => $emoji_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	if ( false === $deleted ) {
		return false;
	}
	$wpdb->delete( axismundi_emoji_references_table(), array( 'emoji_id' => $emoji_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	if ( '' !== $hash && '' !== $relative ) {
		axismundi_emoji_maybe_delete_file( axismundi_emoji_blob_dir() . '/' . $relative, $hash );
	}
	return true;
}

/**
 * Divert one upload into the emoji store.
 *
 * Registered immediately before a single `wp_handle_upload()` call and removed in a
 * `finally`. Left registered for the rest of the request it would divert any other
 * plugin's upload in the same request into the emoji store — a bug that only appears
 * when two plugins upload in one request, which is exactly the kind that ships.
 *
 * @param array<string,mixed> $dirs Upload directory descriptor.
 * @return array<string,mixed>
 */
function axismundi_emoji_upload_dir( array $dirs ) : array {
	/*
	 * Built from the values Core just handed us, never from `axismundi_emoji_blob_dir()`.
	 * That helper calls `wp_upload_dir()`, which applies this very filter — calling it
	 * from inside the filter recurses until the process dies, with no error anywhere
	 * because the stack overflow kills PHP outright rather than raising anything.
	 */
	$dirs['path']   = trailingslashit( (string) $dirs['basedir'] ) . 'axismundi-emoji/blobs/incoming';
	$dirs['url']    = trailingslashit( (string) $dirs['baseurl'] ) . 'axismundi-emoji/blobs/incoming';
	$dirs['subdir'] = '';
	return $dirs;
}

/**
 * Accept an uploaded file as a local emoji.
 *
 * Core does the accepting. The rule that matters is not "must create an attachment" but
 * "must not bypass WordPress's upload validation", so `wp_handle_upload()` is mandatory
 * and `move_uploaded_file()` is not an option — while the registry row, not an
 * attachment post, remains the record.
 *
 * Two entry points, because WordPress draws the distinction and it is a real one.
 * `wp_handle_upload()` requires `is_uploaded_file()`, which is only ever true for a
 * genuine POST; a file already on disk — fetched, imported, or handed over by a CLI run —
 * has to arrive through `wp_handle_sideload()` instead. Both run the same validation, and
 * copying a cached remote emoji into the local registry will need the second one.
 *
 * @param array<string,mixed> $file      One `$_FILES`-shaped entry.
 * @param string              $shortcode Requested shortcode.
 * @param array<string,mixed> $args      Passed through to registration. `sideload` selects
 *                                       the entry point for a file not posted by a browser.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_emoji_handle_upload( array $file, string $shortcode, array $args = array() ) {
	if ( ! current_user_can( 'upload_files' ) ) {
		return new WP_Error( 'ax_emoji_capability', __( 'You cannot upload files.', 'axismundi-emoji' ) );
	}
	$sideload = ! empty( $args['sideload'] );
	unset( $args['sideload'] );
	// Validate the name before touching the filesystem: a bad shortcode makes the upload
	// pointless, and failing first means nothing is written that then has to be removed.
	$key = axismundi_emoji_validate_local_shortcode( $shortcode );
	if ( is_wp_error( $key ) ) {
		return $key;
	}
	if ( is_array( axismundi_emoji_local_get( $key ) ) ) {
		return new WP_Error( 'ax_emoji_duplicate', sprintf( /* translators: %s: shortcode. */ __( '%s is already registered on this site.', 'axismundi-emoji' ), ':' . $key . ':' ) );
	}
	if ( ! axismundi_emoji_ensure_blob_dir() || ! wp_mkdir_p( axismundi_emoji_blob_dir() . '/incoming' ) ) {
		return new WP_Error( 'ax_emoji_store', __( 'The emoji store is not writable.', 'axismundi-emoji' ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	add_filter( 'upload_dir', 'axismundi_emoji_upload_dir' );
	try {
		/*
		 * `mimes` is our allowlist, applied through Core rather than after it.
		 * `get_allowed_mime_types()` is filtered and any other active plugin can widen it;
		 * an SVG-enabling plugin — a common thing to install — would otherwise silently
		 * make SVG a valid emoji upload on a site that never decided that. Naming the three
		 * types here means what Core accepts for this call cannot be widened from outside.
		 */
		$overrides = array(
			'test_form' => false,
			'mimes'     => array( 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp' ),
		);
		$handled   = $sideload ? wp_handle_sideload( $file, $overrides ) : wp_handle_upload( $file, $overrides );
	} finally {
		remove_filter( 'upload_dir', 'axismundi_emoji_upload_dir' );
	}

	if ( ! is_array( $handled ) || isset( $handled['error'] ) ) {
		return new WP_Error( 'ax_emoji_upload', (string) ( $handled['error'] ?? __( 'The upload failed.', 'axismundi-emoji' ) ) );
	}

	$staged = (string) $handled['file'];
	try {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file this request just accepted.
		$bytes = (string) file_get_contents( $staged );
		return axismundi_emoji_register_local( $bytes, ':' . $key . ':', $args );
	} finally {
		// The content-addressed copy is the stored one, so the accepted file is debris
		// either way — including when registration rejected it.
		if ( file_exists( $staged ) ) {
			wp_delete_file( $staged );
		}
	}
}

/**
 * Copy a cached remote emoji into the local registry.
 *
 * The bytes are the ones already on disk, not a fresh download. That is what makes this
 * free: the store is content-addressed, so registering identical bytes resolves to the
 * same path and the two rows share one blob — no new disk, no network, and reference
 * counting keeps the file alive while either row claims it. Re-fetching would risk the
 * opposite, since the intervening proxy or optimiser that produced a different encoding
 * is exactly how the same picture ends up stored twice.
 *
 * What is deliberately *not* carried over is permission. A licence nobody stated does not
 * become one when the file changes hands, and `localOnly` is the origin's request that
 * its emoji not travel further — honouring it here is the whole point of the flag. So an
 * import may be displayed and picked locally, and publishing it is a separate decision a
 * person makes afterwards, with the origin recorded so the question stays answerable.
 *
 * @param int                 $emoji_id  Cached remote registry row id.
 * @param string              $shortcode Requested local shortcode; defaults to the origin's.
 * @param array<string,mixed> $args      category, aliases, local_only overrides.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_emoji_import_remote( int $emoji_id, string $shortcode = '', array $args = array() ) {
	global $wpdb;
	if ( ! axismundi_emoji_ready() ) {
		return new WP_Error( 'ax_emoji_unavailable', __( 'The emoji registry is not installed.', 'axismundi-emoji' ) );
	}
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	$source = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND scope = 'remote'", $emoji_id ), ARRAY_A );
	if ( ! is_array( $source ) ) {
		return new WP_Error( 'ax_emoji_missing', __( 'That emoji is not in the registry.', 'axismundi-emoji' ) );
	}
	if ( 'approved' !== (string) $source['review_status'] ) {
		return new WP_Error( 'ax_emoji_unreviewed', __( 'Approve this emoji before copying it into your own registry.', 'axismundi-emoji' ) );
	}
	/*
	 * The one licence state that blocks import outright (§8). Displaying a message the way
	 * its author wrote it is not re-using their asset; putting it in our catalogue is.
	 */
	if ( 'restricted' === (string) $source['license_state'] ) {
		return new WP_Error( 'ax_emoji_license', __( 'This emoji’s licence does not permit copying it into your own registry.', 'axismundi-emoji' ) );
	}
	$relative = (string) ( $source['cached_path'] ?? '' );
	$absolute = '' === $relative ? '' : axismundi_emoji_blob_dir() . '/' . $relative;
	if ( '' === $absolute || ! file_exists( $absolute ) ) {
		return new WP_Error( 'ax_emoji_uncached', __( 'This emoji’s image is not cached yet, so there is nothing to copy.', 'axismundi-emoji' ) );
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file this plugin wrote.
	$bytes  = (string) file_get_contents( $absolute );
	$parsed = axismundi_emoji_parse_shortcode( '' !== $shortcode ? $shortcode : (string) $source['shortcode'] );
	if ( null === $parsed ) {
		return new WP_Error( 'ax_emoji_shortcode', __( 'That shortcode cannot be used locally.', 'axismundi-emoji' ) );
	}
	$aliases = json_decode( (string) ( $source['aliases'] ?? '' ), true );
	$created = axismundi_emoji_register_local(
		$bytes,
		':' . $parsed['key'] . ':',
		array(
			'category'     => (string) ( $args['category'] ?? ( $source['category'] ?? '' ) ),
			'aliases'      => is_array( $args['aliases'] ?? null ) ? $args['aliases'] : ( is_array( $aliases ) ? $aliases : array() ),
			'is_sensitive' => ! empty( $source['is_sensitive'] ),
			/*
			 * Never publishable on arrival. `localOnly` is the origin asking that its emoji
			 * not travel, and an unstated licence is not a permissive one — so an import
			 * starts local-only whichever applies, and an operator who has checked the terms
			 * can lift it deliberately.
			 */
			'local_only'   => array_key_exists( 'local_only', $args )
				? ! empty( $args['local_only'] )
				: ( ! empty( $source['local_only'] ) || 'allowed' !== (string) $source['license_state'] ),
		)
	);
	if ( is_wp_error( $created ) ) {
		return $created;
	}

	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
		$table,
		array(
			'source_kind'             => 'import',
			'imported_from_authority' => (string) $source['emoji_authority'],
			'imported_from_id'        => (int) $source['id'],
			'imported_updated_raw'    => $source['updated_raw'],
			'imported_at'             => current_time( 'mysql', true ),
			'license_state'           => (string) $source['license_state'],
			'license_text'            => $source['license_text'],
			'license_raw'             => $source['license_raw'],
		),
		array( 'id' => (int) $created['id'] )
	);

	/** Fires after a cached remote emoji is copied into the local registry. */
	do_action( 'axismundi_emoji_imported', (int) $created['id'], (int) $source['id'] );
	return (array) axismundi_emoji_local_get( $parsed['key'] );
}

/**
 * Local emoji whose origin has published a newer version since they were copied.
 *
 * Provenance is only worth recording if something reads it. `updated` is the invalidation
 * signal §3 already defines, so the same comparison that returns a cached remote emoji to
 * the review queue also answers whether our copy of it has fallen behind.
 *
 * @return array<int,array<string,mixed>>
 */
function axismundi_emoji_stale_imports() : array {
	global $wpdb;
	if ( ! axismundi_emoji_ready() ) {
		return array();
	}
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	return (array) $wpdb->get_results(
		"SELECT l.*, o.updated_raw AS origin_updated_raw
		   FROM {$table} l
		   INNER JOIN {$table} o ON o.id = l.imported_from_id
		  WHERE l.scope = 'local'
		    AND l.imported_from_id IS NOT NULL
		    AND COALESCE(o.updated_raw, '') <> ''
		    AND COALESCE(o.updated_raw, '') <> COALESCE(l.imported_updated_raw, '')
		  ORDER BY l.shortcode_key ASC",
		ARRAY_A
	);
}

/**
 * Register the emoji this plugin ships with.
 *
 * Each bundled shortcode is registered at most once. The per-shortcode marker is what
 * makes deletion mean something: without it every activation, update, or schema check
 * would put a removed emoji back. It also lets a later plugin release add a new bundled
 * emoji without treating every existing installation as already provisioned.
 *
 * @return void
 */
function axismundi_emoji_register_bundled() : void {
	if ( ! axismundi_emoji_ready() ) {
		return;
	}
	$registered = axismundi_emoji_bundled_registration_marker( get_option( 'ax_emoji_bundled_registered', array() ) );
	foreach ( AXISMUNDI_EMOJI_BUNDLED as $key => $spec ) {
		if ( array_key_exists( $key, $registered ) ) {
			continue;
		}
		$path = dirname( __DIR__ ) . '/' . $spec['file'];
		if ( is_readable( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- plugin-owned bundled asset.
			$bytes = (string) file_get_contents( $path );
			/*
			 * A failure here is not retried. A duplicate means somebody already registered that
			 * name themselves, and their emoji is the one they meant; overwriting it because we
			 * happen to ship the same shortcode would be the plugin outranking its operator.
			 */
			axismundi_emoji_register_local( $bytes, ':' . $key . ':', array( 'category' => $spec['category'] ) );
		}
		// Record even an unreadable or duplicate asset: retrying it on every request helps nobody.
		$registered[ $key ] = AXISMUNDI_EMOJI_VERSION;
	}
	update_option( 'ax_emoji_bundled_registered', $registered, false );
	axismundi_emoji_restore_bundled_blob();
}

/**
 * Normalize the bundled-registration marker introduced before more than one asset shipped.
 *
 * @param mixed $marker Stored marker.
 * @return array<string, string> Registered bundled shortcodes keyed without colons.
 */
function axismundi_emoji_bundled_registration_marker( $marker ) : array {
	if ( is_array( $marker ) ) {
		return $marker;
	}
	if ( '' === (string) $marker ) {
		return array();
	}
	// The old scalar could only have meant the original bundled `:axismundi:` asset.
	return array( 'axismundi' => (string) $marker );
}

/**
 * Put the bundled emoji's bytes back if its blob went missing.
 *
 * Every other emoji in this store is somebody else's and can only be recovered by
 * fetching it again; this one ships with the plugin, so a missing blob is repairable
 * and there is no reason to leave a registered emoji rendering as a broken image. The
 * store is content-addressed and shared, which is what makes the loss possible in the
 * first place: a claim released elsewhere can collect a file this row still points at.
 *
 * Deliberately narrow. A missing *row* means somebody deleted the emoji on purpose and
 * is left alone; only a row without its file is repaired, and only when the bytes on
 * disk would hash to what the row already recorded.
 *
 * @return bool Whether a repair was made.
 */
function axismundi_emoji_restore_bundled_blob() : bool {
	$repaired = false;
	foreach ( AXISMUNDI_EMOJI_BUNDLED as $key => $spec ) {
		$row = axismundi_emoji_local_get( (string) $key );
		if ( ! is_array( $row ) ) {
			continue;
		}
		$relative = (string) ( $row['cached_path'] ?? '' );
		$hash     = (string) ( $row['content_hash'] ?? '' );
		if ( '' === $relative || '' === $hash ) {
			continue;
		}
		$absolute = axismundi_emoji_blob_dir() . '/' . $relative;
		if ( file_exists( $absolute ) ) {
			continue;
		}
		$path = dirname( __DIR__ ) . '/' . $spec['file'];
		if ( ! is_readable( $path ) ) {
			continue;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- plugin-owned bundled asset.
		$bytes = (string) file_get_contents( $path );
		if ( hash( 'sha256', $bytes ) !== $hash ) {
			// The shipped asset changed between releases. Rewriting here would put bytes at a
			// path that contradicts the hash naming them, so the row needs re-registering
			// rather than repairing, and that is not this function's decision to make.
			continue;
		}
		if ( ! axismundi_emoji_ensure_blob_dir() || ! wp_mkdir_p( dirname( $absolute ) ) ) {
			continue;
		}
		$staging = $absolute . '.staging' . wp_generate_password( 8, false );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- plugin-owned store.
		if ( false === file_put_contents( $staging, $bytes ) ) {
			continue;
		}
		if ( ! rename( $staging, $absolute ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rename -- atomic publish within one directory.
			wp_delete_file( $staging );
			continue;
		}
		$repaired = true;
	}
	return $repaired;
}
