<?php
/**
 * Binary cache for approved remote emoji.
 *
 * Downloads only what an operator has approved, stores the original bytes verbatim, and
 * derives a still frame beside them. Network access happens in cron workers and nowhere
 * else, so no front-end or admin request can block on a third-party host.
 *
 * The original is kept untouched because re-encoding destroys the thing that makes an
 * emoji an emoji here: measured against a real misskey.io APNG, Imagick cannot decode
 * the format at all and GD decodes it while silently discarding the animation and
 * keeping the PNG media type. A cache that normalises on the way in would turn every
 * animated emoji into a still with no error and no way to notice afterwards — which is
 * what the Actors asset cache does by design for avatars, and why it is not reused here.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_EMOJI_DOWNLOAD_BATCH_SIZE   = 5;
const AXISMUNDI_EMOJI_DOWNLOAD_MAX_FAILURES = 6;
const AXISMUNDI_EMOJI_DOWNLOAD_BACKOFF_BASE = 300;
const AXISMUNDI_EMOJI_DOWNLOAD_BACKOFF_CAP  = 43200;

/** Guards against a decompression bomb that is small on the wire. */
const AXISMUNDI_EMOJI_MAX_DIMENSION = 1024;
const AXISMUNDI_EMOJI_MAX_PIXELS    = 1048576;
const AXISMUNDI_EMOJI_MAX_FRAMES    = 240;

/** Default ceiling for the whole store, filterable per site. */
const AXISMUNDI_EMOJI_STORE_QUOTA = 268435456;

/** Media types we will store. Remote SVG is refused: it is script, not an image. */
const AXISMUNDI_EMOJI_CACHEABLE_TYPES = array( 'image/png', 'image/gif', 'image/webp' );

/** Seconds a store lock may be held before another worker may steal it. */
const AXISMUNDI_EMOJI_STORE_LOCK_TTL = 60;

/**
 * How long a reservation keeps charging for bytes its worker never claimed.
 *
 * Matched to the garbage-collection cadence: a crashed download leaves both an orphan
 * file and its reservation, and they should stop existing at about the same time.
 */
const AXISMUNDI_EMOJI_RESERVATION_TTL = HOUR_IN_SECONDS;

/**
 * Charge the store for bytes about to be written.
 *
 * Taken inside the admission lock, before anything touches the disk, so the quota is
 * correct for the whole window in which the file exists but the row has not claimed it
 * yet. The alternative — measuring the directory, or sweeping orphans before each
 * admission — either has to read files that may still be in flight or leaves the ceiling
 * wrong until a later sweep.
 *
 * @param int $emoji_id Registry row.
 * @param int $bytes    Bytes to reserve.
 * @return void
 */
function axismundi_emoji_reserve_bytes( int $emoji_id, int $bytes ) : void {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	$wpdb->update(
		axismundi_emoji_table(),
		array( 'reserved_bytes' => max( 0, $bytes ), 'reserved_at' => current_time( 'mysql', true ) ),
		array( 'id' => $emoji_id )
	);
}

/**
 * Release a reservation, whether it became a claim or came to nothing.
 *
 * @param int $emoji_id Registry row.
 * @return void
 */
function axismundi_emoji_release_reservation( int $emoji_id ) : void {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	$wpdb->update(
		axismundi_emoji_table(),
		array( 'reserved_bytes' => null, 'reserved_at' => null ),
		array( 'id' => $emoji_id )
	);
}

/**
 * Clear reservations whose worker never returned.
 *
 * @return int Reservations cleared.
 */
function axismundi_emoji_expire_reservations() : int {
	global $wpdb;
	if ( ! axismundi_emoji_ready() ) {
		return 0;
	}
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	return (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET reserved_bytes = NULL, reserved_at = NULL WHERE reserved_at IS NOT NULL AND reserved_at < %s", gmdate( 'Y-m-d H:i:s', time() - AXISMUNDI_EMOJI_RESERVATION_TTL ) ) );
}

/**
 * Take the store-wide write lock.
 *
 * Atomic publish makes one file safe; it does nothing for the quota, which is a
 * property of the whole store. Two overlapping cron passes handling *different* hashes
 * each read the remaining space, each see room, and each write — so a store can be
 * pushed past its ceiling by exactly as many workers as happen to overlap. The check
 * and the write have to be one indivisible step.
 *
 * `add_option()` is the lock because the options table has a unique index on
 * `option_name`, so exactly one caller can create the row: the same mechanism WordPress
 * core uses for `WP_Upgrader::create_lock()`.
 *
 * The value carries a random token, not just a timestamp, and that token is what makes
 * release safe. A holder that overruns the TTL has its lock stolen, and if release were
 * an unconditional `delete_option()` the overrunning worker would then delete the *new*
 * holder's lock on its way out — reopening the very window this exists to close, at the
 * moment two workers are already known to be overlapping. Release therefore deletes
 * only a row still carrying its own token.
 *
 * The lock deliberately does not cover the HTTP download. Holding it across a
 * multi-second fetch would serialise every worker behind the slowest remote host, and
 * the race being closed is about bytes on disk, not bytes in flight.
 *
 * @return string Opaque token to release with, or '' when the lock is held elsewhere.
 */
function axismundi_emoji_acquire_store_lock() : string {
	global $wpdb;
	$now   = time();
	$token = $now . ':' . wp_generate_password( 16, false );
	if ( add_option( 'ax_emoji_store_lock', $token, '', false ) ) {
		return $token;
	}
	$held    = (string) get_option( 'ax_emoji_store_lock', '' );
	$started = (int) strtok( $held, ':' );
	if ( $started > 0 && ( $now - $started ) < AXISMUNDI_EMOJI_STORE_LOCK_TTL ) {
		return '';
	}
	// Stale. Steal it, but only if nobody else got there in the meantime.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- compare-and-set on the options table.
	$stolen = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = 'ax_emoji_store_lock' AND option_value = %s", $token, $held ) );
	if ( $stolen > 0 ) {
		wp_cache_delete( 'ax_emoji_store_lock', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		return $token;
	}
	return '';
}

/**
 * Release the store-wide write lock, if this token still owns it.
 *
 * @param string $token Token returned by the matching acquire.
 * @return void
 */
function axismundi_emoji_release_store_lock( string $token ) : void {
	global $wpdb;
	if ( '' === $token ) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- compare-and-delete on the options table.
	$deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = 'ax_emoji_store_lock' AND option_value = %s", $token ) );
	if ( $deleted > 0 ) {
		wp_cache_delete( 'ax_emoji_store_lock', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	}
}

/**
 * Absolute blob root.
 *
 * `blobs/`, not a directory per instance. Where the bytes came from is a property of
 * the registry row — `emoji_authority`, `source_url`, `source_kind` — and encoding it
 * in the path would put the same fact in two places that can disagree. It would also
 * defeat the point of content addressing: `hoto.moe/:misskey:` and `misskey.io/:misskey:`
 * are different identities that may still be the same image, and a per-instance layout
 * would store it twice while a per-hash layout stores it once and lets reference
 * counting decide when it goes.
 *
 * The name is deliberately not `cache/`. E2 puts locally uploaded emoji in the same
 * store, and a local upload is not a cache of anything; only `source_kind` separates
 * them, which is where that distinction belongs.
 *
 * @return string
 */
function axismundi_emoji_blob_dir() : string {
	$uploads = wp_upload_dir();
	return trailingslashit( $uploads['basedir'] ) . 'axismundi-emoji/blobs';
}

/** @return string Public blob root URL. */
function axismundi_emoji_blob_url() : string {
	$uploads = wp_upload_dir();
	return trailingslashit( $uploads['baseurl'] ) . 'axismundi-emoji/blobs';
}

/** @return string Absolute blob root. Deprecated alias kept for call sites. */
function axismundi_emoji_cache_dir() : string {
	return axismundi_emoji_blob_dir();
}

/** @return string Public blob root URL. Deprecated alias kept for call sites. */
function axismundi_emoji_cache_url() : string {
	return axismundi_emoji_blob_url();
}

/**
 * Content-addressed location for one stored file.
 *
 * Sharded two levels so a store with tens of thousands of emoji never puts them all in
 * one directory, and keyed by hash so two rows with identical bytes share a file. The
 * same emoji was observed served from two different CDN hosts, so this is not a
 * hypothetical saving.
 *
 * @param string $hash      Content hash.
 * @param string $extension File extension, no dot.
 * @param string $suffix    Optional rendition suffix.
 * @return string Path relative to the cache root.
 */
function axismundi_emoji_cache_relative_path( string $hash, string $extension, string $suffix = '' ) : string {
	return substr( $hash, 0, 2 ) . '/' . substr( $hash, 2, 2 ) . '/' . $hash . $suffix . '.' . $extension;
}

/** @param string $mime Media type. @return string Extension, or '' when unsupported. */
function axismundi_emoji_extension_for( string $mime ) : string {
	return array(
		'image/png'  => 'png',
		'image/gif'  => 'gif',
		'image/webp' => 'webp',
	)[ $mime ] ?? '';
}

/**
 * Count the image descriptors in a GIF by walking its block structure.
 *
 * A frame is an image descriptor (`0x2C`), and the earlier shortcut of counting Graphic
 * Control Extensions instead was wrong twice over: a GCE is optional, so a genuine
 * multi-frame GIF carrying none was classified as still and would have been served to a
 * reduced-motion reader with no alternative; and `0x2C` cannot simply be searched for,
 * because compressed pixel data contains that byte constantly. Only walking the blocks
 * distinguishes structure from payload.
 *
 * @param string $bytes File contents.
 * @return int Frame count, 0 when the structure is unreadable.
 */
function axismundi_emoji_count_gif_frames( string $bytes ) : int {
	$length = strlen( $bytes );
	$offset = 10; // Header (6) + logical screen descriptor's first four bytes.
	if ( $length < 14 ) {
		return 0;
	}
	$packed = ord( $bytes[10] );
	$offset = 13;
	if ( 0 !== ( $packed & 0x80 ) ) {
		$offset += 3 * ( 2 ** ( ( $packed & 0x07 ) + 1 ) ); // Global colour table.
	}

	/** Skip a chain of length-prefixed sub-blocks terminated by a zero byte. */
	$skip_sub_blocks = static function ( string $data, int $at ) : int {
		$size = strlen( $data );
		while ( $at < $size ) {
			$block = ord( $data[ $at ] );
			++$at;
			if ( 0 === $block ) {
				return $at;
			}
			$at += $block;
		}
		return $size;
	};

	$frames = 0;
	while ( $offset < $length ) {
		$marker = ord( $bytes[ $offset ] );
		++$offset;
		if ( 0x3B === $marker ) {
			break; // Trailer.
		}
		if ( 0x21 === $marker ) {
			++$offset; // Extension label.
			$offset = $skip_sub_blocks( $bytes, $offset );
			continue;
		}
		if ( 0x2C === $marker ) {
			++$frames;
			if ( $offset + 9 > $length ) {
				break;
			}
			$local   = ord( $bytes[ $offset + 8 ] );
			$offset += 9;
			if ( 0 !== ( $local & 0x80 ) ) {
				$offset += 3 * ( 2 ** ( ( $local & 0x07 ) + 1 ) ); // Local colour table.
			}
			++$offset; // LZW minimum code size.
			$offset = $skip_sub_blocks( $bytes, $offset );
			continue;
		}
		// An unrecognised marker means the structure is not what it claims; stop rather
		// than guess, and let the caller treat a zero count as unreadable.
		return $frames;
	}
	return $frames;
}

/**
 * Identify bytes, ignoring what the server said they were.
 *
 * `Content-Type` is not usable here: the CDN serving misskey.io's emoji returns
 * `application/octet-stream` for a valid APNG, and `icon.mediaType` is a remote claim.
 * Only the bytes decide, and the animation flag has to come from them too because
 * ActivityPub carries no such field.
 *
 * @param string $bytes File contents.
 * @return array{mime:string,animated:bool,frames:int}
 */
function axismundi_emoji_sniff( string $bytes ) : array {
	$none = array( 'mime' => '', 'animated' => false, 'frames' => 0 );
	if ( strlen( $bytes ) < 16 ) {
		return $none;
	}

	if ( str_starts_with( $bytes, "\x89PNG\r\n\x1a\n" ) ) {
		// APNG is a PNG carrying an `acTL` chunk; the frame count lives in it.
		$actl = strpos( $bytes, 'acTL' );
		if ( false !== $actl ) {
			$frames = (int) ( unpack( 'N', substr( $bytes, $actl + 4, 4 ) )[1] ?? 0 );
			return array( 'mime' => 'image/png', 'animated' => $frames > 1, 'frames' => $frames );
		}
		return array( 'mime' => 'image/png', 'animated' => false, 'frames' => 1 );
	}

	if ( str_starts_with( $bytes, 'GIF87a' ) || str_starts_with( $bytes, 'GIF89a' ) ) {
		$frames = axismundi_emoji_count_gif_frames( $bytes );
		return array( 'mime' => 'image/gif', 'animated' => $frames > 1, 'frames' => max( 1, $frames ) );
	}

	if ( str_starts_with( $bytes, 'RIFF' ) && 'WEBP' === substr( $bytes, 8, 4 ) ) {
		// An animated WebP is an extended (VP8X) file with the ANIM flag set.
		$animated = 'VP8X' === substr( $bytes, 12, 4 ) && 0 !== ( ord( $bytes[20] ?? "\0" ) & 0x02 );
		return array( 'mime' => 'image/webp', 'animated' => $animated, 'frames' => $animated ? substr_count( $bytes, 'ANMF' ) : 1 );
	}

	return $none;
}

/**
 * When a row that just failed to download may be tried again.
 *
 * Shares `next_attempt_at` with verification, which is safe because the two queues are
 * mutually exclusive: verification wants `review_reason = 'verify_queued'`, download
 * wants `review_status = 'approved'`. A row is never in both.
 *
 * @param int $failures Failure count after the attempt.
 * @return string UTC datetime.
 */
function axismundi_emoji_download_backoff( int $failures ) : string {
	$delay = AXISMUNDI_EMOJI_DOWNLOAD_BACKOFF_BASE * ( 2 ** max( 0, $failures - 1 ) );
	return gmdate( 'Y-m-d H:i:s', time() + (int) min( AXISMUNDI_EMOJI_DOWNLOAD_BACKOFF_CAP, $delay ) );
}

/**
 * Record a failed download attempt.
 *
 * @param int    $emoji_id Registry row id.
 * @param string $reason   Short machine reason.
 * @return void
 */
function axismundi_emoji_mark_download_failure( int $emoji_id, string $reason ) : void {
	global $wpdb;
	if ( $emoji_id <= 0 || ! axismundi_emoji_ready() ) {
		return;
	}
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	$failures = 1 + (int) $wpdb->get_var( $wpdb->prepare( "SELECT failure_count FROM {$table} WHERE id = %d", $emoji_id ) );
	$fields   = array(
		'failure_count'   => $failures,
		'next_attempt_at' => axismundi_emoji_download_backoff( $failures ),
	);
	if ( $failures >= AXISMUNDI_EMOJI_DOWNLOAD_MAX_FAILURES ) {
		/*
		 * The approval stands. Only the fetching stops, because an operator saying "show
		 * this" and a CDN being unreachable are different facts, and turning the second
		 * into a reversal of the first would quietly discard a human decision.
		 */
		$fields['review_reason']   = 'cache_failed:' . substr( $reason, 0, 32 );
		$fields['next_attempt_at'] = null;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	$wpdb->update( $table, $fields, array( 'id' => $emoji_id ) );
}

/**
 * Put a row back in the queue shortly, without blaming it.
 *
 * Distinct from {@see axismundi_emoji_mark_download_failure()} on purpose: contention
 * is our own scheduling, not a fault of the emoji or its host, so it must not consume
 * one of the attempts that lead to abandonment.
 *
 * @param int $emoji_id Registry row id.
 * @return void
 */
function axismundi_emoji_defer_download( int $emoji_id ) : void {
	global $wpdb;
	if ( $emoji_id <= 0 || ! axismundi_emoji_ready() ) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	$wpdb->update(
		axismundi_emoji_table(),
		array( 'next_attempt_at' => gmdate( 'Y-m-d H:i:s', time() + AXISMUNDI_EMOJI_STORE_LOCK_TTL ) ),
		array( 'id' => $emoji_id )
	);
}

/** @return int Total bytes currently attributed to distinct cached files. */
function axismundi_emoji_store_size() : int {
	global $wpdb;
	if ( ! axismundi_emoji_ready() ) {
		return 0;
	}
	$table = axismundi_emoji_table();
	/*
	 * The predicate must be exactly the one GC treats as a claim, or the two disagree
	 * about what is on disk. It used to count any row with a `content_hash`, but revoke,
	 * reject, and an `updated` change all clear `cached_path` while leaving the hash and
	 * size behind — so GC deleted the file and the quota went on counting its bytes
	 * forever. A few cycles of that and an empty store refuses new downloads.
	 */
	/*
	 * Claimed files plus live reservations.
	 *
	 * Both files per hash, not just the original: an animated emoji also writes a still
	 * PNG, and counting only `byte_size` meant the ceiling measured roughly half of what
	 * an animation-heavy store occupied — a quota that does not bound the disk is not a
	 * quota.
	 *
	 * Reservations close the other half of the gap. Bytes exist on disk from the moment
	 * they are published, but the row does not claim them until the download completes,
	 * and a worker that dies in between leaves a file nothing accounts for. Counting the
	 * reservation from before the write means the quota is right during that window
	 * rather than an hour later when GC happens to run, and it never has to inspect a
	 * file that may still be in flight. A reservation older than its TTL is ignored here
	 * and cleared by the sweep, matching the GC pass that removes the orphan itself.
	 */
	/*
	 * Remote rows only, because the ceiling exists to bound how much of *other people's*
	 * content we accumulate. Local emoji live in the same blob store — E2 uploads share
	 * it so an identical image is stored once — but they are assets this site chose to
	 * create, bounded by the upload flow that created them. Counting them here would let
	 * a site with a large local set stop caching remote emoji, which is the opposite of
	 * what an operator who uploaded them intended.
	 */
	$cutoff = gmdate( 'Y-m-d H:i:s', time() - AXISMUNDI_EMOJI_RESERVATION_TTL );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	$claimed = (int) $wpdb->get_var( "SELECT COALESCE(SUM(byte_size),0) + COALESCE(SUM(static_byte_size),0) FROM (SELECT DISTINCT content_hash, byte_size, static_byte_size FROM {$table} WHERE scope = 'remote' AND content_hash IS NOT NULL AND content_hash <> '' AND cached_path IS NOT NULL AND cached_path <> '') AS distinct_files" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	$reserved = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(reserved_bytes),0) FROM {$table} WHERE scope = 'remote' AND reserved_bytes IS NOT NULL AND reserved_at IS NOT NULL AND reserved_at >= %s", $cutoff ) );
	return $claimed + $reserved;
}

/**
 * Produce a still frame for `prefers-reduced-motion`.
 *
 * GD explicitly, not the default editor. Imagick reports no APNG decode delegate and
 * returns a `WP_Error` for exactly the files that need a still the most, so leaving the
 * choice to `wp_get_image_editor()` would fail on every animated PNG.
 *
 * The written path is returned rather than assumed, because `WP_Image_Editor::save()`
 * does not honour an arbitrary filename: it forces the extension to match the output
 * mime type. Asked for `…-static.ab12cd34.part` it silently writes
 * `…-static.ab12cd34.png`, so checking the requested path reports failure for a
 * rendition that was in fact produced.
 *
 * @param string $source_path Absolute path to the original.
 * @param string $target_path Requested absolute path; the extension may be replaced.
 * @return string Path actually written, or '' on failure.
 */
function axismundi_emoji_write_static_rendition( string $source_path, string $target_path ) : string {
	$force_gd = static fn() : array => array( 'WP_Image_Editor_GD' );
	add_filter( 'wp_image_editors', $force_gd, 99 );
	try {
		$editor = wp_get_image_editor( $source_path );
		if ( is_wp_error( $editor ) ) {
			return '';
		}
		$saved = $editor->save( $target_path, 'image/png' );
		if ( is_wp_error( $saved ) ) {
			return '';
		}
		$written = (string) ( $saved['path'] ?? '' );
		return '' !== $written && file_exists( $written ) ? $written : '';
	} finally {
		remove_filter( 'wp_image_editors', $force_gd, 99 );
	}
}

/**
 * Download, validate, and store one approved emoji.
 *
 * @param array<string,mixed> $row Registry row.
 * @return bool Whether the row became renderable.
 */
function axismundi_emoji_cache_row( array $row ) : bool {
	global $wpdb;
	$emoji_id = (int) ( $row['id'] ?? 0 );
	$url      = (string) ( $row['source_url'] ?? '' );
	if ( $emoji_id <= 0 || '' === $url || 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) ) {
		axismundi_emoji_mark_download_failure( $emoji_id, 'url' );
		return false;
	}

	/*
	 * No redirects, matching the verification fetch.
	 *
	 * The operator approved a specific `source_url`. Following a redirect would let the
	 * bytes actually cached come from a host nobody reviewed, changeable at any time
	 * without an `updated` bump to re-open review — the approval would still read as
	 * current while meaning something else. Both emoji CDNs we measured answer their
	 * icon URLs directly with no redirect, so this costs nothing in practice, and a
	 * server that starts redirecting surfaces as a visible failure rather than as a
	 * silent substitution.
	 */
	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 15,
			'redirection'         => 0,
			'limit_response_size' => AXISMUNDI_EMOJI_MAX_BYTES + 1,
			'user-agent'          => 'Axismundi Emoji/' . AXISMUNDI_EMOJI_VERSION . '; ' . home_url( '/' ),
		)
	);
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		axismundi_emoji_mark_download_failure( $emoji_id, 'http' );
		return false;
	}

	$bytes = (string) wp_remote_retrieve_body( $response );
	if ( '' === $bytes || strlen( $bytes ) > AXISMUNDI_EMOJI_MAX_BYTES ) {
		axismundi_emoji_mark_download_failure( $emoji_id, 'size' );
		return false;
	}

	$sniffed = axismundi_emoji_sniff( $bytes );
	if ( ! in_array( $sniffed['mime'], AXISMUNDI_EMOJI_CACHEABLE_TYPES, true ) ) {
		axismundi_emoji_mark_download_failure( $emoji_id, 'type' );
		return false;
	}
	if ( $sniffed['frames'] > AXISMUNDI_EMOJI_MAX_FRAMES ) {
		axismundi_emoji_mark_download_failure( $emoji_id, 'frames' );
		return false;
	}

	$hash    = hash( 'sha256', $bytes );
	$extension = axismundi_emoji_extension_for( $sniffed['mime'] );
	$root      = axismundi_emoji_cache_dir();
	$relative  = axismundi_emoji_cache_relative_path( $hash, $extension );
	$absolute  = $root . '/' . $relative;

	if ( ! wp_mkdir_p( dirname( $absolute ) ) ) {
		axismundi_emoji_mark_download_failure( $emoji_id, 'mkdir' );
		return false;
	}
	/*
	 * Admission is decided by whether these bytes are already *counted*, not by whether
	 * a file happens to sit at the path.
	 *
	 * The two differ after a crash. Publishing writes the file before the row claims it,
	 * so a worker that dies in between leaves bytes on disk that the quota — computed
	 * from claims — does not see. Keying the bypass on `file_exists()` would then let the
	 * next attempt adopt that orphan without any quota check at all, and real disk usage
	 * could drift above the ceiling until GC happened to run. Asking whether another row
	 * already claims the hash sends an orphan through admission exactly like new bytes,
	 * while still keeping a genuine second claimant free.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; values prepared.
	$already_counted = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . axismundi_emoji_table() . " WHERE content_hash = %s AND cached_path IS NOT NULL AND cached_path <> '' AND id <> %d",
			$hash,
			$emoji_id
		)
	);

	/*
	 * From here the function can leave through a dozen exits — dimension caps, a failed
	 * still rendition, a stale claim — and every one of them must give the reservation
	 * back. Releasing it in a single `finally` is the only way that stays true as the
	 * validation steps change; a release before each `return` is a list that will one day
	 * be missing an entry, and a leaked reservation charges the store for an hour.
	 */
	$reserved = false;
	try {
	if ( 0 === $already_counted ) {
		$lock = axismundi_emoji_acquire_store_lock();
		if ( '' === $lock ) {
			/*
			 * Another worker is admitting bytes. Defer without counting a failure: nothing
			 * is wrong with this emoji or its host, and charging it a retry would push a
			 * blameless row towards abandonment purely because two cron passes overlapped.
			 */
			axismundi_emoji_defer_download( $emoji_id );
			return false;
		}
		try {
			/*
			 * Both the check and the write happen under the lock, which is the point of it.
			 *
			 * A still rendition for an animated file is produced after this point and its
			 * size cannot be known in advance, so an admission can overshoot by one such
			 * file. That is bounded and self-correcting — the still is recorded in
			 * `static_byte_size` and counted by every later admission — whereas reserving a
			 * worst-case second copy up front would roughly halve usable capacity for a
			 * rendition that measured 90 KB against a 1.92 MiB original.
			 */
			$quota = (int) apply_filters( 'axismundi_emoji_store_quota', AXISMUNDI_EMOJI_STORE_QUOTA );
			if ( axismundi_emoji_store_size() + strlen( $bytes ) > $quota ) {
				axismundi_emoji_mark_download_failure( $emoji_id, 'quota' );
				return false;
			}
			// Charge for the bytes before writing them, so the window between publish and
			// claim is never a hole in the accounting.
			axismundi_emoji_reserve_bytes( $emoji_id, strlen( $bytes ) );
			$reserved = true;
			if ( ! file_exists( $absolute ) ) {
				/*
				 * Publish atomically: write beside the target, then rename.
				 *
				 * Two workers can hold the same hash at once — the same image under two
				 * shortcodes is exactly what content addressing is for — and a reader
				 * arriving mid-write would otherwise `getimagesize()` a truncated file, or
				 * GC would delete a partial one out from under the writer. `rename()`
				 * within a directory is atomic, so a concurrent reader sees either nothing
				 * or the whole file, and the loser of a race overwrites identical bytes.
				 */
				$staging = $absolute . '.' . wp_generate_password( 8, false ) . '.part';
				if ( false === file_put_contents( $staging, $bytes ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- plugin-owned cache directory.
					axismundi_emoji_mark_download_failure( $emoji_id, 'write' );
					return false;
				}
				if ( ! rename( $staging, $absolute ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rename -- atomic publish within one directory.
					wp_delete_file( $staging );
					axismundi_emoji_mark_download_failure( $emoji_id, 'publish' );
					return false;
				}
			}
		} finally {
			axismundi_emoji_release_store_lock( $lock );
		}
	}

	$size = (array) getimagesize( $absolute );
	$w    = (int) ( $size[0] ?? 0 );
	$h    = (int) ( $size[1] ?? 0 );
	if ( $w <= 0 || $h <= 0 || $w > AXISMUNDI_EMOJI_MAX_DIMENSION || $h > AXISMUNDI_EMOJI_MAX_DIMENSION || ( $w * $h ) > AXISMUNDI_EMOJI_MAX_PIXELS ) {
		axismundi_emoji_maybe_delete_file( $absolute, $hash );
		axismundi_emoji_mark_download_failure( $emoji_id, 'dimensions' );
		return false;
	}

	/*
	 * An animated emoji is only publishable once a still exists to offer a
	 * reduced-motion reader. Failing that, the shortcode text is the correct answer:
	 * serving an unstoppable animation to somebody who asked for none is worse than
	 * serving them a word.
	 */
	$static_relative = '';
	if ( $sniffed['animated'] ) {
		$static_relative = axismundi_emoji_cache_relative_path( $hash, 'png', '-static' );
		$static_absolute = $root . '/' . $static_relative;
		if ( ! file_exists( $static_absolute ) ) {
			// `.staging` sits in the stem, not the extension, so it survives the editor
			// rewriting the suffix and still marks the file as collectable debris.
			$static_request = $root . '/' . axismundi_emoji_cache_relative_path( $hash, 'png', '-static.staging' . wp_generate_password( 8, false ) );
			$static_written = axismundi_emoji_write_static_rendition( $absolute, $static_request );
			if ( '' === $static_written ) {
				axismundi_emoji_mark_download_failure( $emoji_id, 'static' );
				return false;
			}
			if ( ! rename( $static_written, $static_absolute ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rename -- atomic publish within one directory.
				wp_delete_file( $static_written );
				axismundi_emoji_mark_download_failure( $emoji_id, 'publish' );
				return false;
			}
		}
	}

	/*
	 * Claim the download only if the row still describes what was downloaded.
	 *
	 * A download takes seconds, and in that window an observation can arrive with a
	 * newer `updated`, which sends the row back to review, clears its cache, and points
	 * `source_url` at a different picture. Writing unconditionally on `id` alone would
	 * restore the *old* bytes over that, clear `review_reason`, and leave a row reading
	 * as approved-and-cached — so the replacement never gets downloaded and the operator
	 * approves a new emoji only to be shown the previous image. A revoke landing
	 * mid-flight would be resurrected the same way.
	 *
	 * Matching `source_url`, `updated_raw`, and `review_status` makes the write a
	 * compare-and-set against the state the download was started from. Zero affected
	 * rows means the world moved on and these bytes are stale, which is not a failure —
	 * the row is already queued for whatever it became.
	 */
	$static_size = '' !== $static_relative && file_exists( $root . '/' . $static_relative )
		? (int) filesize( $root . '/' . $static_relative ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize -- plugin-owned cache file.
		: 0;

	/*
	 * Written as an explicit statement rather than `$wpdb->update()` because the
	 * comparison has to survive NULLs: `updated_raw` is absent for any server that omits
	 * the field, and `WHERE updated_raw = ''` never matches NULL, so a conditional
	 * update built from an array would reject every such emoji.
	 */
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; values prepared.
	$claimed = (int) $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$table} SET
				content_hash = %s, cached_path = %s, static_path = %s, media_type = %s,
				animated = %d, frame_count = %d, byte_size = %d, static_byte_size = %d,
				width = %d, height = %d, fetched_at = %s,
				failure_count = 0, next_attempt_at = NULL, review_reason = NULL
			 WHERE id = %d
			   AND review_status = 'approved'
			   AND COALESCE(source_url, '') = %s
			   AND COALESCE(updated_raw, '') = %s",
			$hash,
			$relative,
			$static_relative,
			$sniffed['mime'],
			$sniffed['animated'] ? 1 : 0,
			$sniffed['frames'],
			strlen( $bytes ),
			$static_size,
			$w,
			$h,
			current_time( 'mysql', true ),
			$emoji_id,
			$url,
			(string) ( $row['updated_raw'] ?? '' )
		)
	);
	return $claimed > 0;
	} finally {
		if ( $reserved ) {
			axismundi_emoji_release_reservation( $emoji_id );
		}
	}
}

/**
 * Remove a cached file when no row still claims its hash.
 *
 * @param string $absolute Absolute path.
 * @param string $hash     Content hash.
 * @return void
 */
function axismundi_emoji_maybe_delete_file( string $absolute, string $hash ) : void {
	global $wpdb;
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	$claims = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE content_hash = %s AND cached_path IS NOT NULL AND cached_path <> ''", $hash ) );
	if ( $claims > 0 || ! file_exists( $absolute ) ) {
		return;
	}
	wp_delete_file( $absolute );
	$static = preg_replace( '/\.[a-z]+$/', '-static.png', $absolute );
	if ( is_string( $static ) && file_exists( $static ) ) {
		wp_delete_file( $static );
	}
}

/**
 * Approved rows still waiting for bytes.
 *
 * @param int $limit Batch limit.
 * @return array<int,array<string,mixed>>
 */
function axismundi_emoji_download_queue( int $limit = AXISMUNDI_EMOJI_DOWNLOAD_BATCH_SIZE ) : array {
	global $wpdb;
	if ( ! axismundi_emoji_ready() ) {
		return array();
	}
	$table = axismundi_emoji_table();
	$limit = max( 1, min( 25, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; static predicates and prepared values.
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table}
			 WHERE review_status = 'approved'
			   AND ( cached_path IS NULL OR cached_path = '' )
			   AND source_url IS NOT NULL AND source_url <> ''
			   AND failure_count < %d
			   AND ( next_attempt_at IS NULL OR next_attempt_at <= %s )
			 ORDER BY next_attempt_at IS NOT NULL, next_attempt_at ASC, last_seen_at DESC, id ASC
			 LIMIT %d",
			AXISMUNDI_EMOJI_DOWNLOAD_MAX_FAILURES,
			current_time( 'mysql', true ),
			$limit
		),
		ARRAY_A
	);
}

/** Schedule a bounded download pass, collapsing repeated approvals. */
function axismundi_emoji_schedule_download_pass( int $delay = 10 ) : void {
	if ( ! wp_next_scheduled( 'axismundi_emoji_process_download_batch' ) ) {
		wp_schedule_single_event( time() + max( 1, $delay ), 'axismundi_emoji_process_download_batch' );
	}
}

/** Hook adapter for argument-less actions. */
function axismundi_emoji_queue_download_pass() : void {
	axismundi_emoji_schedule_download_pass();
}
add_action( 'axismundi_emoji_approved', 'axismundi_emoji_queue_download_pass' );

/**
 * Process one download batch, rescheduling only while work remains.
 *
 * @return void
 */
function axismundi_emoji_process_download_batch() : void {
	foreach ( axismundi_emoji_download_queue() as $row ) {
		axismundi_emoji_cache_row( $row );
	}
	if ( array() !== axismundi_emoji_download_queue( 1 ) ) {
		axismundi_emoji_schedule_download_pass( 15 );
	}
}
add_action( 'axismundi_emoji_process_download_batch', 'axismundi_emoji_process_download_batch' );

/** Retry stalled downloads at a low cadence. */
function axismundi_emoji_schedule_download_recovery() : void {
	if ( ! wp_next_scheduled( 'axismundi_emoji_download_recovery' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'axismundi_emoji_download_recovery' );
	}
}
add_action( 'init', 'axismundi_emoji_schedule_download_recovery' );
add_action( 'axismundi_emoji_download_recovery', 'axismundi_emoji_queue_download_pass' );

/**
 * Delete cached files no row claims any more.
 *
 * @return int Files removed.
 */
function axismundi_emoji_collect_garbage() : int {
	global $wpdb;
	if ( ! axismundi_emoji_ready() ) {
		return 0;
	}
	$root = axismundi_emoji_cache_dir();
	if ( ! is_dir( $root ) ) {
		return 0;
	}
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	$claimed = (array) $wpdb->get_col( "SELECT DISTINCT content_hash FROM {$table} WHERE content_hash IS NOT NULL AND content_hash <> '' AND cached_path IS NOT NULL AND cached_path <> ''" );
	$claimed = array_flip( $claimed );

	$removed  = 0;
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}
		// A staging file abandoned by a crashed worker: it can never be claimed, since its
		// name is not a hash, so nothing else would ever remove it.
		if ( str_ends_with( $file->getFilename(), '.part' ) || str_contains( $file->getFilename(), '.staging' ) ) {
			if ( $file->getMTime() < time() - HOUR_IN_SECONDS ) {
				wp_delete_file( $file->getPathname() );
				++$removed;
			}
			continue;
		}
		$name = preg_replace( '/(-static)?\.[a-z]+$/', '', $file->getFilename() );
		if ( is_string( $name ) && 64 === strlen( $name ) && ! isset( $claimed[ $name ] ) ) {
			wp_delete_file( $file->getPathname() );
			++$removed;
		}
	}
	return $removed;
}
add_action( 'axismundi_emoji_download_recovery', 'axismundi_emoji_collect_garbage', 20 );
add_action( 'axismundi_emoji_download_recovery', 'axismundi_emoji_expire_reservations', 25 );
