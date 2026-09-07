<?php
/**
 * Binary cache for approved emoji (dev-only; dist-excluded).
 *
 * HTTP is mocked through `pre_http_request`, so this exercises the storage and
 * validation contract without depending on anyone's uptime. The bytes are real: the
 * committed 2-frame APNG and the bundled WebP, because the interesting behaviour here
 * is format-specific and a synthetic blob would prove nothing about either.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-emoji.php';

/*
 * `$ax_bc_body` and `$ax_bc_status` must be declared global here, not merely assigned.
 * WP-CLI runs an eval-file inside a function, so a top-level assignment creates a local
 * that the mock's own `global` never sees — it would serve an empty body and every
 * download assertion would fail for a reason that has nothing to do with the code.
 */
global $wpdb, $ax_bc_body, $ax_bc_status;
$ax_bc_results = array();
$ax_bc_ids     = array();
$ax_bc_files   = array();
$ax_bc_body    = '';
$ax_bc_status  = 200;

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_bc_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/**
 * Serve whatever the current test has staged.
 *
 * @param mixed  $preempt Short-circuit value.
 * @param array  $args    Request args.
 * @param string $url     Request URL.
 * @return array<string,mixed>
 */
function ax_bc_mock_request( $preempt, $args, $url ) {
	global $ax_bc_body, $ax_bc_status;
	unset( $preempt, $args, $url );
	return array(
		'headers'  => array(),
		'body'     => $ax_bc_body,
		'response' => array( 'code' => $ax_bc_status, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => null,
	);
}
add_filter( 'pre_http_request', 'ax_bc_mock_request', 10, 3 );

/**
 * Seed an approved row awaiting bytes.
 *
 * @param string $key Shortcode key.
 * @return int Row id.
 */
function ax_bc_seed( string $key ) : int {
	global $wpdb;
	$now = current_time( 'mysql', true );
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
		axismundi_emoji_table(),
		array(
			'emoji_authority' => 'cache.invalid',
			'shortcode_key'   => $key,
			'shortcode'       => ':' . $key . ':',
			'source_url'      => 'https://cdn.cache.invalid/' . $key . '.bin',
			'scope'           => 'remote',
			'source_kind'     => 'remote',
			'review_status'   => 'approved',
			'first_seen_at'   => $now,
			'last_seen_at'    => $now,
		)
	);
	return (int) $wpdb->insert_id;
}

/** @param int $id Row id. @return array<string,mixed> */
function ax_bc_row( int $id ) : array {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . axismundi_emoji_table() . ' WHERE id = %d', $id ), ARRAY_A );
	return is_array( $row ) ? $row : array();
}

try {
	axismundi_emoji_install();
	$ax_bc_dir   = dirname( __DIR__ ) . '/';
	$ax_bc_apng  = (string) file_get_contents( $ax_bc_dir . 'tests/fixtures/animated-2frame.apng' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fixture.
	$ax_bc_webp  = (string) file_get_contents( $ax_bc_dir . 'emoji/axismundi.webp' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fixture.
	$ax_bc_root  = axismundi_emoji_cache_dir();

	/* ---------------------------------------------------------------- *
	 * A still image
	 * ---------------------------------------------------------------- */

	$ax_bc_body = $ax_bc_webp;
	$still      = ax_bc_seed( 'still' );
	$ax_bc_ids[] = $still;
	$ok         = axismundi_emoji_cache_row( ax_bc_row( $still ) );
	$still_row  = ax_bc_row( $still );
	$ax_bc_files[] = $ax_bc_root . '/' . $still_row['cached_path'];

	ax_bc_assert( $ax_bc_results, 'an approved emoji is downloaded and becomes renderable', $ok && axismundi_emoji_is_renderable( $still_row ) );
	ax_bc_assert( $ax_bc_results, 'the stored bytes are the original, unmodified', file_exists( $ax_bc_root . '/' . $still_row['cached_path'] ) && hash( 'sha256', (string) file_get_contents( $ax_bc_root . '/' . $still_row['cached_path'] ) ) === $still_row['content_hash'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fixture.
	ax_bc_assert( $ax_bc_results, 'the media type comes from the bytes, not from the response headers the mock never sent', 'image/webp' === $still_row['media_type'] );
	ax_bc_assert( $ax_bc_results, 'a still image needs no separate reduced-motion rendition', '' === (string) $still_row['static_path'] && 0 === (int) $still_row['animated'] );

	/* ---------------------------------------------------------------- *
	 * An animation
	 * ---------------------------------------------------------------- */

	$ax_bc_body  = $ax_bc_apng;
	$anim        = ax_bc_seed( 'anim' );
	$ax_bc_ids[] = $anim;
	axismundi_emoji_cache_row( ax_bc_row( $anim ) );
	$anim_row      = ax_bc_row( $anim );
	$ax_bc_files[] = $ax_bc_root . '/' . $anim_row['cached_path'];
	$ax_bc_files[] = $ax_bc_root . '/' . $anim_row['static_path'];

	ax_bc_assert( $ax_bc_results, 'an APNG is recognised as animated from its acTL chunk, which ActivityPub never declares', 1 === (int) $anim_row['animated'] && 2 === (int) $anim_row['frame_count'] );
	ax_bc_assert( $ax_bc_results, 'the animation is preserved rather than flattened on the way in', str_contains( (string) file_get_contents( $ax_bc_root . '/' . $anim_row['cached_path'] ), 'acTL' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fixture.
	ax_bc_assert( $ax_bc_results, 'a still rendition is produced for prefers-reduced-motion readers', '' !== (string) $anim_row['static_path'] && file_exists( $ax_bc_root . '/' . $anim_row['static_path'] ) );
	ax_bc_assert( $ax_bc_results, 'that rendition really is a still, so the reduced-motion promise is kept', ! str_contains( (string) file_get_contents( $ax_bc_root . '/' . $anim_row['static_path'] ), 'acTL' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fixture.

	/* ---------------------------------------------------------------- *
	 * Deduplication
	 * ---------------------------------------------------------------- */

	$ax_bc_body  = $ax_bc_webp;
	$twin        = ax_bc_seed( 'twin' );
	$ax_bc_ids[] = $twin;
	axismundi_emoji_cache_row( ax_bc_row( $twin ) );
	$twin_row = ax_bc_row( $twin );
	ax_bc_assert( $ax_bc_results, 'byte-identical emoji share one stored file, which is why the hash is the storage key', $twin_row['content_hash'] === $still_row['content_hash'] && $twin_row['cached_path'] === $still_row['cached_path'] );

	/* ---------------------------------------------------------------- *
	 * Refusals
	 * ---------------------------------------------------------------- */

	$ax_bc_body  = str_repeat( 'A', AXISMUNDI_EMOJI_MAX_BYTES + 64 );
	$big         = ax_bc_seed( 'toobig' );
	$ax_bc_ids[] = $big;
	$big_ok      = axismundi_emoji_cache_row( ax_bc_row( $big ) );
	ax_bc_assert( $ax_bc_results, 'a file over the per-file cap is refused and stays unrenderable', ! $big_ok && ! axismundi_emoji_is_renderable( ax_bc_row( $big ) ) );

	$ax_bc_body  = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
	$svg         = ax_bc_seed( 'svg' );
	$ax_bc_ids[] = $svg;
	$svg_ok      = axismundi_emoji_cache_row( ax_bc_row( $svg ) );
	ax_bc_assert( $ax_bc_results, 'SVG is refused by byte inspection, whatever the source URL claimed', ! $svg_ok && '' === (string) ax_bc_row( $svg )['cached_path'] );

	$ax_bc_status = 500;
	$ax_bc_body   = $ax_bc_webp;
	$down         = ax_bc_seed( 'down' );
	$ax_bc_ids[]  = $down;
	axismundi_emoji_cache_row( ax_bc_row( $down ) );
	$down_row     = ax_bc_row( $down );
	$ax_bc_status = 200;
	ax_bc_assert( $ax_bc_results, 'a failed download backs off instead of retrying immediately', 1 === (int) $down_row['failure_count'] && null !== $down_row['next_attempt_at'] );
	ax_bc_assert( $ax_bc_results, 'and the approval survives, because a CDN outage is not a reversal of a human decision', 'approved' === (string) $down_row['review_status'] );
	$due = array_map( static fn( array $r ) : string => (string) $r['shortcode_key'], axismundi_emoji_download_queue( 25 ) );
	ax_bc_assert( $ax_bc_results, 'a row that just failed leaves the download queue, so it cannot hot-loop', ! in_array( 'down', $due, true ) );

	for ( $i = 0; $i < AXISMUNDI_EMOJI_DOWNLOAD_MAX_FAILURES; $i++ ) {
		axismundi_emoji_mark_download_failure( $down, 'http' );
	}
	$dead = ax_bc_row( $down );
	ax_bc_assert( $ax_bc_results, 'an unreachable source is eventually abandoned rather than retried forever', str_starts_with( (string) $dead['review_reason'], 'cache_failed' ) && 'approved' === (string) $dead['review_status'] );

	/* ---------------------------------------------------------------- *
	 * Quota
	 * ---------------------------------------------------------------- */

	$tiny_quota = static fn() : int => 1;
	add_filter( 'axismundi_emoji_store_quota', $tiny_quota );
	$ax_bc_body  = str_repeat( "\x89PNG\r\n\x1a\n", 8 ) . 'not-really';
	$quota       = ax_bc_seed( 'quota' );
	$ax_bc_ids[] = $quota;
	$quota_ok    = axismundi_emoji_cache_row( ax_bc_row( $quota ) );
	remove_filter( 'axismundi_emoji_store_quota', $tiny_quota );
	ax_bc_assert( $ax_bc_results, 'a full store refuses new bytes rather than growing without bound', ! $quota_ok );

	/* ---------------------------------------------------------------- *
	 * Garbage collection
	 * ---------------------------------------------------------------- */

	$orphan = $ax_bc_root . '/' . axismundi_emoji_cache_relative_path( str_repeat( 'f', 64 ), 'png' );
	wp_mkdir_p( dirname( $orphan ) );
	file_put_contents( $orphan, 'orphan' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fixture.
	$collected = axismundi_emoji_collect_garbage();
	ax_bc_assert( $ax_bc_results, 'a cached file no row claims is collected', $collected >= 1 && ! file_exists( $orphan ) );
	ax_bc_assert( $ax_bc_results, 'a file two rows still share is not collected with one of them', file_exists( $ax_bc_root . '/' . $still_row['cached_path'] ) );

	/*
	 * Both staging shapes the code can actually produce: the `.part` used for the
	 * original download, and the `-static.staging…` used for the still rendition. The
	 * marker has to live in the stem for the second one, because the image editor
	 * rewrites the extension — an earlier attempt put it in the suffix and left files
	 * that no cleanup path could ever match.
	 */
	$stale_part   = $ax_bc_root . '/' . axismundi_emoji_cache_relative_path( str_repeat( 'e', 64 ), 'png' ) . '.abcd1234.part';
	$stale_static = $ax_bc_root . '/' . axismundi_emoji_cache_relative_path( str_repeat( 'e', 64 ), 'png', '-static.stagingAbCd1234' );
	foreach ( array( $stale_part, $stale_static ) as $stale ) {
		wp_mkdir_p( dirname( $stale ) );
		file_put_contents( $stale, 'partial' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fixture.
		touch( $stale, time() - ( 2 * HOUR_IN_SECONDS ) );
	}
	axismundi_emoji_collect_garbage();
	ax_bc_assert( $ax_bc_results, 'staging files left by a crashed worker are eventually collected, since nothing can ever claim them', ! file_exists( $stale_part ) && ! file_exists( $stale_static ) );

	/* ---------------------------------------------------------------- *
	 * The quota must follow the disk
	 *
	 * Revoking, rejecting, and an `updated` change all clear `cached_path` and leave the
	 * hash behind. GC reads that as unclaimed and deletes the file, so a quota counting
	 * anything with a hash keeps charging for bytes that are gone — and after a few
	 * cycles an empty store refuses new downloads.
	 * ---------------------------------------------------------------- */

	/*
	 * Bytes nothing else can claim, generated rather than taken from the bundled asset.
	 * The store is content-addressed, so reusing the shipped image here would put this
	 * row on the same file as the local `:axismundi:` registry row, and "GC deletes an
	 * unclaimed hash" would then be asserting something false about a shared blob.
	 * Enumerating the other claimants by hand is what made this fragile: the set grows.
	 */
	$ax_bc_unique  = imagecreatetruecolor( 8, 8 );
	imagefill( $ax_bc_unique, 0, 0, imagecolorallocate( $ax_bc_unique, 17, 71, 137 ) );
	ob_start();
	imagepng( $ax_bc_unique );
	imagedestroy( $ax_bc_unique );
	$ax_bc_solo    = (string) ob_get_clean();
	$ax_bc_body    = $ax_bc_solo;
	$cycle         = ax_bc_seed( 'cycle' );
	$ax_bc_ids[]   = $cycle;
	axismundi_emoji_cache_row( ax_bc_row( $cycle ) );
	$cycle_row     = ax_bc_row( $cycle );
	$ax_bc_files[] = $ax_bc_root . '/' . $cycle_row['cached_path'];
	$size_cached   = axismundi_emoji_store_size();
	ax_bc_assert( $ax_bc_results, 'a cached emoji counts against the quota', $size_cached > 0 );

	axismundi_emoji_review_apply( $cycle, 'revoke', 1 );
	$size_revoked = axismundi_emoji_store_size();
	// Sole claimant, so releasing it should return exactly those bytes — no more, since
	// the unrelated cached emoji are untouched.
	ax_bc_assert(
		$ax_bc_results,
		'revoking releases the quota it was holding, instead of charging for bytes GC is about to delete',
		$size_cached - $size_revoked === strlen( $ax_bc_solo )
	);

	axismundi_emoji_collect_garbage();
	ax_bc_assert( $ax_bc_results, 'and the file really is gone, so quota and disk agree', ! file_exists( $ax_bc_root . '/' . $cycle_row['cached_path'] ) );

	// Re-approve and re-download: the store must accept it again.
	axismundi_emoji_review_apply( $cycle, 'approve', 1 );
	$ax_bc_body  = $ax_bc_webp;
	$re_ok       = axismundi_emoji_cache_row( ax_bc_row( $cycle ) );
	$cycle_again = ax_bc_row( $cycle );
	ax_bc_assert( $ax_bc_results, 'an emoji cached, revoked, collected, and approved again downloads cleanly', $re_ok && axismundi_emoji_is_renderable( $cycle_again ) );

	/* ---------------------------------------------------------------- *
	 * A download that finished too late
	 *
	 * A fetch takes seconds. In that window an observation can arrive with a newer
	 * `updated`, sending the row back to review with a different `source_url`. Writing
	 * on `id` alone would restore the old bytes over that and clear the review flag, so
	 * the row would read as approved-and-cached, the replacement would never be fetched,
	 * and approving the new emoji would display the previous image.
	 * ---------------------------------------------------------------- */

	$ax_bc_body   = $ax_bc_webp;
	$raced        = ax_bc_seed( 'raced' );
	$ax_bc_ids[]  = $raced;
	$wpdb->update( axismundi_emoji_table(), array( 'updated_raw' => '2024-01-01T00:00:00Z' ), array( 'id' => $raced ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	$raced_row    = ax_bc_row( $raced );

	// The world moves on while the worker holds an older snapshot of the row.
	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- simulates a concurrent observation.
		axismundi_emoji_table(),
		array( 'updated_raw' => '2025-09-09T00:00:00Z', 'review_status' => 'pending', 'review_reason' => 'changed', 'source_url' => 'https://cdn.cache.invalid/raced-v2.bin' ),
		array( 'id' => $raced )
	);
	$stale_ok  = axismundi_emoji_cache_row( $raced_row );
	$after_race = ax_bc_row( $raced );
	ax_bc_assert( $ax_bc_results, 'a download that finished after the emoji changed does not claim the row', ! $stale_ok && '' === (string) $after_race['cached_path'] );
	ax_bc_assert( $ax_bc_results, 'and the changed flag survives, so the replacement still reaches review', 'changed' === (string) $after_race['review_reason'] && 'pending' === (string) $after_race['review_status'] );

	// A revoke landing mid-flight must not be resurrected either.
	$revoked_mid = ax_bc_seed( 'revokedmid' );
	$ax_bc_ids[] = $revoked_mid;
	$mid_row     = ax_bc_row( $revoked_mid );
	$wpdb->update( axismundi_emoji_table(), array( 'review_status' => 'rejected' ), array( 'id' => $revoked_mid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	ax_bc_assert( $ax_bc_results, 'a download completing after a rejection cannot resurrect the emoji', ! axismundi_emoji_cache_row( $mid_row ) && 'rejected' === (string) ax_bc_row( $revoked_mid )['review_status'] );

	/* ---------------------------------------------------------------- *
	 * The still rendition counts against the quota
	 * ---------------------------------------------------------------- */

	$ax_bc_body   = $ax_bc_apng;
	$counted      = ax_bc_seed( 'counted' );
	$ax_bc_ids[]  = $counted;
	axismundi_emoji_cache_row( ax_bc_row( $counted ) );
	$counted_row   = ax_bc_row( $counted );
	$ax_bc_files[] = $ax_bc_root . '/' . $counted_row['cached_path'];
	$ax_bc_files[] = $ax_bc_root . '/' . $counted_row['static_path'];
	$on_disk       = (int) filesize( $ax_bc_root . '/' . $counted_row['cached_path'] ) + (int) filesize( $ax_bc_root . '/' . $counted_row['static_path'] );

	ax_bc_assert( $ax_bc_results, 'the still rendition\'s size is recorded, not silently untracked', (int) $counted_row['static_byte_size'] > 0 );
	ax_bc_assert(
		$ax_bc_results,
		'the quota counts both files per hash, so the ceiling bounds actual disk use',
		(int) $counted_row['byte_size'] + (int) $counted_row['static_byte_size'] === $on_disk
	);

	/* ---------------------------------------------------------------- *
	 * Store-wide admission lock
	 *
	 * Atomic publish protects one hash. The quota is a property of the whole store, so
	 * two workers on *different* hashes could each read the remaining space, each see
	 * room, and each write — overshooting the ceiling by however many passes overlap.
	 * ---------------------------------------------------------------- */

	$lock_a = axismundi_emoji_acquire_store_lock();
	ax_bc_assert( $ax_bc_results, 'the store lock is exclusive', '' !== $lock_a && '' === axismundi_emoji_acquire_store_lock() );

	/*
	 * The payload must be bytes the store does not already hold. An emoji whose file is
	 * already on disk never reaches the admission path at all — correctly, since it costs
	 * no new space — so reusing an earlier fixture here would test nothing. A trailing
	 * suffix past the PNG stream changes the hash while leaving a decodable image.
	 */
	$ax_bc_novel  = $ax_bc_webp . 'AX-LOCK-PROBE';
	$ax_bc_body   = $ax_bc_novel;
	$blocked      = ax_bc_seed( 'blocked' );
	$ax_bc_ids[]  = $blocked;
	$blocked_ok   = axismundi_emoji_cache_row( ax_bc_row( $blocked ) );
	$blocked_row  = ax_bc_row( $blocked );
	ax_bc_assert( $ax_bc_results, 'a worker locked out defers instead of caching', ! $blocked_ok && '' === (string) $blocked_row['cached_path'] );
	ax_bc_assert( $ax_bc_results, 'and is not charged a failure for losing a race it did not cause', 0 === (int) $blocked_row['failure_count'] && null !== $blocked_row['next_attempt_at'] );

	axismundi_emoji_release_store_lock( $lock_a );
	$lock_b = axismundi_emoji_acquire_store_lock();
	ax_bc_assert( $ax_bc_results, 'releasing lets the next worker in', '' !== $lock_b );
	axismundi_emoji_release_store_lock( $lock_b );

	// A crashed holder must not wedge the queue forever.
	$stale_token = ( time() - ( AXISMUNDI_EMOJI_STORE_LOCK_TTL * 2 ) ) . ':abandoned';
	update_option( 'ax_emoji_store_lock', $stale_token, false );
	$lock_c = axismundi_emoji_acquire_store_lock();
	ax_bc_assert( $ax_bc_results, 'a lock abandoned by a crashed worker can be stolen once it is stale', '' !== $lock_c );

	/*
	 * The overrunning worker now finishes and releases. It must not take the new
	 * holder's lock with it: that would open the admission window at precisely the
	 * moment two workers are known to be overlapping, which is the case the lock exists
	 * for. This is why the value carries a token rather than only a timestamp.
	 */
	axismundi_emoji_release_store_lock( $stale_token );
	ax_bc_assert( $ax_bc_results, 'a worker whose lock was stolen cannot delete its successor\'s lock on the way out', '' === axismundi_emoji_acquire_store_lock() );
	axismundi_emoji_release_store_lock( $lock_c );

	// With the lock free the deferred row caches normally.
	$wpdb->update( axismundi_emoji_table(), array( "next_attempt_at" => null ), array( "id" => $blocked ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	$ax_bc_body = $ax_bc_novel;
	ax_bc_assert( $ax_bc_results, 'the deferred emoji caches once the lock is free', axismundi_emoji_cache_row( ax_bc_row( $blocked ) ) );

	/* ---------------------------------------------------------------- *
	 * Reservations
	 *
	 * Bytes exist on disk from publish, but the row only claims them when the download
	 * completes. Without a reservation covering that window, a crash leaves a file the
	 * quota cannot see, and the *next* download of some other hash passes a check that
	 * is measuring less than the disk holds.
	 * ---------------------------------------------------------------- */

	$ax_bc_body   = $ax_bc_webp . 'AX-RESERVE-PROBE';
	$reserving    = ax_bc_seed( 'reserving' );
	$ax_bc_ids[]  = $reserving;
	$before_size  = axismundi_emoji_store_size();
	axismundi_emoji_reserve_bytes( $reserving, 5000 );
	$during_size  = axismundi_emoji_store_size();
	ax_bc_assert( $ax_bc_results, 'a reservation charges the store before any bytes are written', $during_size - $before_size === 5000 );

	// A quota with no room left must refuse, counting the reservation.
	$reserved_quota = static fn() : int => $during_size;
	add_filter( 'axismundi_emoji_store_quota', $reserved_quota );
	$blocked_by_reservation = ax_bc_seed( 'blockedbyres' );
	$ax_bc_ids[]            = $blocked_by_reservation;
	$res_ok                 = axismundi_emoji_cache_row( ax_bc_row( $blocked_by_reservation ) );
	remove_filter( 'axismundi_emoji_store_quota', $reserved_quota );
	ax_bc_assert( $ax_bc_results, 'another download is refused while that reservation stands, so the ceiling holds during the window', ! $res_ok );

	axismundi_emoji_release_reservation( $reserving );
	ax_bc_assert( $ax_bc_results, 'releasing a reservation returns its bytes', axismundi_emoji_store_size() === $before_size );

	// A worker that never came back must not charge forever.
	axismundi_emoji_reserve_bytes( $reserving, 5000 );
	$wpdb->update( axismundi_emoji_table(), array( 'reserved_at' => gmdate( 'Y-m-d H:i:s', time() - ( AXISMUNDI_EMOJI_RESERVATION_TTL * 2 ) ) ), array( 'id' => $reserving ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	ax_bc_assert( $ax_bc_results, 'an expired reservation stops counting, so a crashed worker cannot wedge the quota', axismundi_emoji_store_size() === $before_size );
	ax_bc_assert( $ax_bc_results, 'and the sweep clears it', axismundi_emoji_expire_reservations() >= 1 && null === ax_bc_row( $reserving )['reserved_at'] );

	// A completed download leaves nothing reserved: the claim is what counts now.
	$ax_bc_body    = $ax_bc_webp . 'AX-RESERVE-PROBE';
	$settled       = ax_bc_seed( 'settled' );
	$ax_bc_ids[]   = $settled;
	axismundi_emoji_cache_row( ax_bc_row( $settled ) );
	$settled_row   = ax_bc_row( $settled );
	$ax_bc_files[] = $ax_bc_root . '/' . $settled_row['cached_path'];
	ax_bc_assert( $ax_bc_results, 'a successful download converts its reservation into a claim rather than double-counting', null === $settled_row['reserved_at'] && '' !== (string) $settled_row['cached_path'] );

	// And a failure gives it back rather than leaking it for an hour.
	$ax_bc_body  = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';
	$leaky       = ax_bc_seed( 'leaky' );
	$ax_bc_ids[] = $leaky;
	$size_before_fail = axismundi_emoji_store_size();
	axismundi_emoji_cache_row( ax_bc_row( $leaky ) );
	ax_bc_assert( $ax_bc_results, 'a rejected download leaves no reservation behind', null === ax_bc_row( $leaky )['reserved_at'] && axismundi_emoji_store_size() === $size_before_fail );

	/* ---------------------------------------------------------------- *
	 * A shared blob store, two kinds of owner
	 *
	 * E2 puts locally uploaded emoji in the same content-addressed store, so an identical
	 * image is stored once whoever supplied it. What must not be shared is the ceiling:
	 * it bounds how much of other people's content we accumulate, and a site's own
	 * uploads are not that.
	 * ---------------------------------------------------------------- */

	$ax_bc_body    = $ax_bc_webp . 'AX-LOCAL-PROBE';
	$local_like    = ax_bc_seed( 'localish' );
	$ax_bc_ids[]   = $local_like;
	axismundi_emoji_cache_row( ax_bc_row( $local_like ) );
	$local_row     = ax_bc_row( $local_like );
	$ax_bc_files[] = $ax_bc_root . '/' . $local_row['cached_path'];
	$with_remote   = axismundi_emoji_store_size();
	$wpdb->update( axismundi_emoji_table(), array( 'scope' => 'local', 'source_kind' => 'upload' ), array( 'id' => $local_like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	$without_local = axismundi_emoji_store_size();

	ax_bc_assert(
		$ax_bc_results,
		'a locally owned emoji does not consume the remote cache ceiling, though it shares the blob store',
		$with_remote - $without_local === (int) $local_row['byte_size'] + (int) $local_row['static_byte_size']
	);
	axismundi_emoji_collect_garbage();
	ax_bc_assert(
		$ax_bc_results,
		'and garbage collection still protects its file, because a claim is a claim whatever its scope',
		file_exists( $ax_bc_root . '/' . $local_row['cached_path'] )
	);

	/* ---------------------------------------------------------------- *
	 * Orphan adoption after a crash
	 *
	 * Publishing writes the file before the row claims it, so a worker that dies in
	 * between leaves bytes on disk that a claim-derived quota cannot see. Adopting such
	 * a file must still go through admission, or real disk usage drifts above the
	 * ceiling until GC happens to run.
	 * ---------------------------------------------------------------- */

	$ax_bc_orphan_bytes = $ax_bc_webp . 'AX-ORPHAN-PROBE';
	$ax_bc_orphan_hash  = hash( 'sha256', $ax_bc_orphan_bytes );
	$ax_bc_orphan_file  = $ax_bc_root . '/' . axismundi_emoji_cache_relative_path( $ax_bc_orphan_hash, 'webp' );
	wp_mkdir_p( dirname( $ax_bc_orphan_file ) );
	file_put_contents( $ax_bc_orphan_file, $ax_bc_orphan_bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- simulates a crash between publish and claim.
	$ax_bc_files[] = $ax_bc_orphan_file;

	$tiny_quota_again = static fn() : int => 1;
	add_filter( 'axismundi_emoji_store_quota', $tiny_quota_again );
	$ax_bc_body   = $ax_bc_orphan_bytes;
	$orphaned     = ax_bc_seed( 'orphaned' );
	$ax_bc_ids[]  = $orphaned;
	$orphan_ok    = axismundi_emoji_cache_row( ax_bc_row( $orphaned ) );
	remove_filter( 'axismundi_emoji_store_quota', $tiny_quota_again );
	ax_bc_assert(
		$ax_bc_results,
		'adopting a file left behind by a crash is subject to the quota, not exempt from it because the path exists',
		! $orphan_ok && '' === (string) ax_bc_row( $orphaned )['cached_path']
	);

	// With room, the same orphan is adopted rather than re-downloaded into a duplicate.
	$ax_bc_body = $ax_bc_orphan_bytes;
	$wpdb->update( axismundi_emoji_table(), array( 'failure_count' => 0, 'next_attempt_at' => null, 'review_reason' => null ), array( 'id' => $orphaned ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	$adopt_ok    = axismundi_emoji_cache_row( ax_bc_row( $orphaned ) );
	$adopt_row   = ax_bc_row( $orphaned );
	ax_bc_assert( $ax_bc_results, 'once there is room the orphan is adopted, and its hash is what the row now claims', $adopt_ok && $ax_bc_orphan_hash === (string) $adopt_row['content_hash'] );

	/* ---------------------------------------------------------------- *
	 * GIF frame detection
	 * ---------------------------------------------------------------- */

	// Two frames, deliberately with no Graphic Control Extension: the old detector
	// counted GCEs, so this exact file was misread as a still image.
	$gif  = "GIF89a\x01\x00\x01\x00\x80\x00\x00\xFF\xFF\xFF\x00\x00\x00";
	$gif .= "\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00";
	$gif .= "\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00";
	$gif .= "\x3B";
	$gif_sniff = axismundi_emoji_sniff( $gif );
	ax_bc_assert( $ax_bc_results, 'a multi-frame GIF carrying no Graphic Control Extension is still recognised as animated', 'image/gif' === $gif_sniff['mime'] && $gif_sniff['animated'] && 2 === $gif_sniff['frames'] );

	$single  = "GIF89a\x01\x00\x01\x00\x80\x00\x00\xFF\xFF\xFF\x00\x00\x00";
	$single .= "\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00";
	$single .= "\x3B";
	$single_sniff = axismundi_emoji_sniff( $single );
	ax_bc_assert( $ax_bc_results, 'a single-frame GIF is not mistaken for an animation by pixel data that happens to contain 0x2C', ! $single_sniff['animated'] && 1 === $single_sniff['frames'] );
} catch ( Throwable $error ) {
	ax_bc_assert( $ax_bc_results, 'the binary cache suite ran to completion: ' . $error->getMessage(), false );
} finally {
	remove_filter( 'pre_http_request', 'ax_bc_mock_request', 10 );
	// Cleanup cannot use the token-scoped release: a test may have left a lock under a
	// token this scope no longer has, and leaving one behind would wedge the next run.
	delete_option( 'ax_emoji_store_lock' );
	foreach ( array_unique( $ax_bc_ids ) as $id ) {
		$wpdb->delete( axismundi_emoji_table(), array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	/*
	 * Deleting outright would be wrong even here. The store is content-addressed, so a
	 * fixture's path is not the fixture's property: these bytes are the bundled
	 * `:axismundi:` image, which means the path this suite cached to is the same path the
	 * local registry row points at. An unconditional delete took that file out from under
	 * a row that still claimed it and left the shipped emoji rendering as a broken image.
	 * `maybe_delete_file()` is the operation that exists for exactly this.
	 */
	foreach ( array_unique( array_filter( $ax_bc_files ) ) as $file ) {
		if ( ! file_exists( $file ) ) {
			continue;
		}
		$ax_bc_stem = preg_replace( '/(-static(\.staging[a-zA-Z0-9]*)?)?\.[a-z]+$/', '', basename( $file ) );
		axismundi_emoji_maybe_delete_file( $file, is_string( $ax_bc_stem ) ? $ax_bc_stem : '' );
	}
	axismundi_emoji_collect_garbage();
}

$ax_bc_failures = count( array_filter( $ax_bc_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_bc_results ), $ax_bc_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_bc_failures > 0 ? 1 : 0 );
}
exit( $ax_bc_failures > 0 ? 1 : 0 );
