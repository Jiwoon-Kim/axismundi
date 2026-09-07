<?php
/**
 * Importing a cached remote emoji, and the substitution order (dev-only; dist-excluded).
 *
 * Two things are proved here that measurement, not reasoning, had to settle.
 *
 * The import is free: the store is content-addressed and the copy re-registers bytes
 * already on disk, so the local row lands on the origin's own path. Re-fetching instead
 * would not be — the same picture arrives re-encoded through a proxy and hashes
 * differently, which is exactly how one image ends up stored three times.
 *
 * And substitution is a fallback, never an override. A shortcode is only unique within
 * the server that declared it: `:cat:` on two instances can be two pictures meaning two
 * things. When we hold what the declaring server actually published, that wins.
 *
 * No network.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-emoji.php';

global $wpdb;
$ax_imp_results = array();
$ax_imp_ids     = array();
$ax_imp_origin  = 'origin.import.test';
$ax_imp_other   = 'other.import.test';

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_imp_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/** @param int $seed Colour seed. @return string A distinct square PNG. */
function ax_imp_png( int $seed ) : string {
	$image = imagecreatetruecolor( 40, 40 );
	imagefill( $image, 0, 0, imagecolorallocate( $image, $seed % 256, ( $seed * 11 ) % 256, ( $seed * 29 ) % 256 ) );
	ob_start();
	imagepng( $image );
	imagedestroy( $image );
	return (string) ob_get_clean();
}

/**
 * Seed a cached remote row whose bytes really are on disk.
 *
 * @param string              $authority Declaring host.
 * @param string              $key       Shortcode key.
 * @param string              $bytes     File contents.
 * @param array<string,mixed> $extra     Column overrides.
 * @return int
 */
function ax_imp_seed_cached( string $authority, string $key, string $bytes, array $extra = array() ) : int {
	global $wpdb;
	$hash     = hash( 'sha256', $bytes );
	$relative = axismundi_emoji_cache_relative_path( $hash, 'png' );
	$absolute = axismundi_emoji_blob_dir() . '/' . $relative;
	wp_mkdir_p( dirname( $absolute ) );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fixture.
	file_put_contents( $absolute, $bytes );
	$now = current_time( 'mysql', true );
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
		axismundi_emoji_table(),
		array_merge(
			array(
				'emoji_authority' => $authority,
				'shortcode_key'   => $key,
				'shortcode'       => ':' . $key . ':',
				'scope'           => 'remote',
				'source_kind'     => 'remote',
				'source_url'      => 'https://cdn.' . $authority . '/' . $key . '.png',
				'review_status'   => 'approved',
				'license_state'   => 'allowed',
				'content_hash'    => $hash,
				'cached_path'     => $relative,
				'byte_size'       => strlen( $bytes ),
				'width'           => 40,
				'height'          => 40,
				'media_type'      => 'image/png',
				'category'        => 'Imported fixtures',
				'aliases'         => wp_json_encode( array( 'aliasone' ) ),
				'updated_raw'     => '2026-01-01T00:00:00Z',
				'first_seen_at'   => $now,
				'last_seen_at'    => $now,
			),
			$extra
		)
	);
	return (int) $wpdb->insert_id;
}

/** @param string $root Blob root. @return int Files on disk. */
function ax_imp_file_count( string $root ) : int {
	$n = 0;
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( $file->isFile() && 'index.php' !== $file->getFilename() ) {
			$n++;
		}
	}
	return $n;
}

try {
	axismundi_emoji_install();
	wp_set_current_user( 1 );
	foreach ( array( $ax_imp_origin, $ax_imp_other ) as $ax_imp_host ) {
		$wpdb->delete( axismundi_emoji_table(), array( 'emoji_authority' => $ax_imp_host ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( array( 'impfree', 'imprestricted', 'impsensitive', 'impcat' ) as $ax_imp_key ) {
		$ax_imp_stale = axismundi_emoji_local_get( $ax_imp_key );
		if ( is_array( $ax_imp_stale ) ) {
			axismundi_emoji_delete_local( (int) $ax_imp_stale['id'] );
		}
	}

	$ax_imp_root  = axismundi_emoji_blob_dir();
	$ax_imp_bytes = ax_imp_png( 5 );
	$ax_imp_src   = ax_imp_seed_cached( $ax_imp_origin, 'impfree', $ax_imp_bytes );
	$ax_imp_ids[] = $ax_imp_src;

	// -- The import costs nothing --------------------------------------------------------

	$ax_imp_files_before = ax_imp_file_count( $ax_imp_root );
	$ax_imp_quota_before = axismundi_emoji_store_size();
	$ax_imp_local        = axismundi_emoji_import_remote( $ax_imp_src );
	if ( is_wp_error( $ax_imp_local ) ) {
		throw new RuntimeException( 'import: ' . $ax_imp_local->get_error_message() );
	}
	$ax_imp_ids[] = (int) $ax_imp_local['id'];

	$ax_imp_source_row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . axismundi_emoji_table() . ' WHERE id = %d', $ax_imp_src ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	ax_imp_assert( $ax_imp_results, 'the imported row points at the origin\'s own blob, because the bytes were never re-encoded', $ax_imp_local['cached_path'] === $ax_imp_source_row['cached_path'] );
	ax_imp_assert( $ax_imp_results, 'so nothing new is written to disk', ax_imp_file_count( $ax_imp_root ) === $ax_imp_files_before );
	ax_imp_assert( $ax_imp_results, 'and the remote quota is unmoved, since a local row is not other people\'s content', axismundi_emoji_store_size() === $ax_imp_quota_before );
	ax_imp_assert( $ax_imp_results, 'the copy is a local, approved emoji', 'local' === $ax_imp_local['scope'] && 'approved' === $ax_imp_local['review_status'] );
	ax_imp_assert( $ax_imp_results, 'and carries the origin\'s category and aliases, which is what makes it usable', 'Imported fixtures' === (string) $ax_imp_local['category'] && str_contains( (string) $ax_imp_local['aliases'], 'aliasone' ) );

	// -- Provenance ----------------------------------------------------------------------

	ax_imp_assert( $ax_imp_results, 'where it came from is recorded, not just that it is local', $ax_imp_origin === (string) $ax_imp_local['imported_from_authority'] && (int) $ax_imp_local['imported_from_id'] === $ax_imp_src );
	ax_imp_assert( $ax_imp_results, 'along with the origin\'s version at copy time', '2026-01-01T00:00:00Z' === (string) $ax_imp_local['imported_updated_raw'] );
	ax_imp_assert( $ax_imp_results, 'and it is distinguishable from an ordinary upload', 'import' === (string) $ax_imp_local['source_kind'] );

	/*
	 * Provenance is only worth recording if something reads it. `updated` is the same
	 * invalidation signal §3 uses for the cache, so it answers staleness here too.
	 */
	ax_imp_assert( $ax_imp_results, 'a fresh import is not reported as stale', array() === axismundi_emoji_stale_imports() );
	$wpdb->update( axismundi_emoji_table(), array( 'updated_raw' => '2026-06-01T00:00:00Z' ), array( 'id' => $ax_imp_src ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the origin publishes a revision.
	$ax_imp_stale_list = axismundi_emoji_stale_imports();
	ax_imp_assert( $ax_imp_results, 'when the origin publishes a newer version, our copy is reported as behind it', 1 === count( $ax_imp_stale_list ) && (int) $ax_imp_stale_list[0]['id'] === (int) $ax_imp_local['id'] );

	// -- Sharing the blob ----------------------------------------------------------------

	ax_imp_assert( $ax_imp_results, 'deleting the local copy leaves the origin\'s cached image alone', axismundi_emoji_delete_local( (int) $ax_imp_local['id'] ) && file_exists( $ax_imp_root . '/' . $ax_imp_source_row['cached_path'] ) );
	$ax_imp_local = axismundi_emoji_import_remote( $ax_imp_src );
	$ax_imp_ids[] = is_wp_error( $ax_imp_local ) ? 0 : (int) $ax_imp_local['id'];
	ax_imp_assert( $ax_imp_results, 'and it can be imported again afterwards', ! is_wp_error( $ax_imp_local ) );

	// -- What import refuses --------------------------------------------------------------

	$ax_imp_restricted = ax_imp_seed_cached( $ax_imp_origin, 'imprestricted', ax_imp_png( 9 ), array( 'license_state' => 'restricted' ) );
	$ax_imp_ids[]      = $ax_imp_restricted;
	ax_imp_assert( $ax_imp_results, 'a restricted licence blocks the copy, because displaying a message is not re-using its assets', is_wp_error( axismundi_emoji_import_remote( $ax_imp_restricted ) ) );

	$ax_imp_pending = ax_imp_seed_cached( $ax_imp_origin, 'imppending', ax_imp_png( 13 ), array( 'review_status' => 'pending' ) );
	$ax_imp_ids[]   = $ax_imp_pending;
	ax_imp_assert( $ax_imp_results, 'an unreviewed emoji cannot be copied before somebody has looked at it', is_wp_error( axismundi_emoji_import_remote( $ax_imp_pending ) ) );

	$ax_imp_uncached = ax_imp_seed_cached( $ax_imp_origin, 'impuncached', ax_imp_png( 17 ), array( 'cached_path' => '', 'content_hash' => null ) );
	$ax_imp_ids[]    = $ax_imp_uncached;
	ax_imp_assert( $ax_imp_results, 'an uncached emoji cannot be copied, since there are no bytes to copy', is_wp_error( axismundi_emoji_import_remote( $ax_imp_uncached ) ) );

	/*
	 * An import is not a licence. `localOnly` is the origin asking that its emoji not
	 * travel further, and an unstated licence is not a permissive one, so a copy is never
	 * publishable on arrival — lifting that is a decision a person makes with the terms in
	 * front of them.
	 */
	$ax_imp_unknown = ax_imp_seed_cached( $ax_imp_origin, 'impunknown', ax_imp_png( 23 ), array( 'license_state' => 'unknown' ) );
	$ax_imp_ids[]   = $ax_imp_unknown;
	$ax_imp_copy    = axismundi_emoji_import_remote( $ax_imp_unknown );
	$ax_imp_ids[]   = is_wp_error( $ax_imp_copy ) ? 0 : (int) $ax_imp_copy['id'];
	ax_imp_assert( $ax_imp_results, 'an emoji whose licence nobody stated arrives local-only rather than publishable', ! is_wp_error( $ax_imp_copy ) && 1 === (int) $ax_imp_copy['local_only'] && 0 === (int) $ax_imp_copy['outbound_allowed'] );
	ax_imp_assert( $ax_imp_results, 'and the origin\'s licence state travels with it, so the question stays answerable', ! is_wp_error( $ax_imp_copy ) && 'unknown' === (string) $ax_imp_copy['license_state'] );

	// -- Editing what the wire could not carry ---------------------------------------------

	/*
	 * An imported emoji arrives without a category, aliases, or a licence because
	 * ActivityPub's `tag[]` has no such fields — name, icon, `updated`, and nothing else.
	 * Re-fetching would not help; these live only in each server's own admin API. So they
	 * are editable, and that is the only path there is.
	 */
	// The copy re-imported above, still in place.
	$ax_imp_copy   = $ax_imp_local;
	$ax_imp_edited = axismundi_emoji_update_local(
		(int) $ax_imp_copy['id'],
		array( 'category' => 'Reactions', 'aliases' => 'kitty, neko', 'license_text' => 'CC0 1.0', 'local_only' => false, 'is_sensitive' => false )
	);
	ax_imp_assert( $ax_imp_results, 'a category can be supplied by hand, since the wire never carried one', ! is_wp_error( $ax_imp_edited ) && 'Reactions' === (string) $ax_imp_edited['category'] );
	ax_imp_assert( $ax_imp_results, 'aliases accept a comma-separated list and normalize to keys', ! is_wp_error( $ax_imp_edited ) && array( 'kitty', 'neko' ) === json_decode( (string) $ax_imp_edited['aliases'], true ) );
	ax_imp_assert( $ax_imp_results, 'stating a permissive licence reclassifies it rather than just recording words', 'allowed' === (string) $ax_imp_edited['license_state'] );
	ax_imp_assert( $ax_imp_results, 'and that is what lets an operator publish a copy they have checked', 0 === (int) $ax_imp_edited['local_only'] && 1 === (int) $ax_imp_edited['outbound_allowed'] );

	/*
	 * The licence vetoes sharing rather than merely advising it. Otherwise an operator
	 * could mark an emoji restricted and leave it publishing — the contradiction the
	 * three-state axis exists to catch.
	 */
	$ax_imp_vetoed = axismundi_emoji_update_local( (int) $ax_imp_copy['id'], array( 'license_text' => 'All rights reserved', 'local_only' => false ) );
	ax_imp_assert( $ax_imp_results, 'declaring a restrictive licence forces sharing off even when the form asked to publish', 'restricted' === (string) $ax_imp_vetoed['license_state'] && 1 === (int) $ax_imp_vetoed['local_only'] && 0 === (int) $ax_imp_vetoed['outbound_allowed'] );

	$ax_imp_flagged = axismundi_emoji_update_local( (int) $ax_imp_copy['id'], array( 'is_sensitive' => true ) );
	ax_imp_assert( $ax_imp_results, 'sensitivity is editable, because nothing on the wire supplies it either', 1 === (int) $ax_imp_flagged['is_sensitive'] );

	/*
	 * The shortcode is the identity. Messages already published carry `:name:` as text, so
	 * a rename would leave every one of them pointing at nothing.
	 */
	$ax_imp_renamed = axismundi_emoji_update_local( (int) $ax_imp_copy['id'], array( 'shortcode' => ':somethingelse:', 'category' => 'Reactions' ) );
	ax_imp_assert( $ax_imp_results, 'the shortcode is not editable, because already-published messages carry it as text', ! is_wp_error( $ax_imp_renamed ) && 'impfree' === (string) $ax_imp_renamed['shortcode_key'] );

	axismundi_emoji_delete_local( (int) $ax_imp_copy['id'] );

	// -- Substitution order ----------------------------------------------------------------

	/*
	 * The declaring server's own bytes win. `:cat:` on two instances can be two pictures
	 * meaning two different things, so preferring a local or trusted copy over one we
	 * actually hold would redraw somebody else's message.
	 */
	$ax_imp_declared = ax_imp_seed_cached( $ax_imp_other, 'impcat', ax_imp_png( 31 ) );
	$ax_imp_ids[]    = $ax_imp_declared;
	$ax_imp_localcat = axismundi_emoji_register_local( ax_imp_png( 41 ), ':impcat:' );
	$ax_imp_ids[]    = is_wp_error( $ax_imp_localcat ) ? 0 : (int) $ax_imp_localcat['id'];
	$ax_imp_row      = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . axismundi_emoji_table() . ' WHERE id = %d', $ax_imp_declared ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.

	$ax_imp_shown = axismundi_emoji_bare_presentation_row( 'impcat', $ax_imp_row );
	ax_imp_assert( $ax_imp_results, 'a cached declaration renders its own server\'s image, not a same-named local one', is_array( $ax_imp_shown ) && (int) $ax_imp_shown['id'] === $ax_imp_declared );

	// With the declaration uncached there is nothing to be faithful to, so the local one stands in.
	$ax_imp_uncached_decl = array_merge( $ax_imp_row, array( 'cached_path' => '', 'content_hash' => null ) );
	$ax_imp_shown         = axismundi_emoji_bare_presentation_row( 'impcat', $ax_imp_uncached_decl );
	ax_imp_assert( $ax_imp_results, 'an uncached declaration falls back to the local emoji of that name', is_array( $ax_imp_shown ) && 'local' === (string) $ax_imp_shown['scope'] );

	/*
	 * Sensitivity is a mark that the two are not interchangeable. Swapping either way
	 * discards somebody's decision, so text is the answer — the same answer an ambiguous
	 * bare name already gets.
	 */
	$ax_imp_sensitive_decl = array_merge( $ax_imp_uncached_decl, array( 'is_sensitive' => 1 ) );
	ax_imp_assert( $ax_imp_results, 'a declaration marked sensitive is never quietly replaced by an unflagged picture', null === axismundi_emoji_bare_presentation_row( 'impcat', $ax_imp_sensitive_decl ) );

	/*
	 * That last check passes on a fixture the wire cannot currently produce, and saying so
	 * is the point: an AP Emoji tag carries `id`, `type`, `name`, `updated`, `icon`, and on
	 * Misskey `_misskey_license` — never `sensitive`. Verified against `:blobcat_hip:`,
	 * which Misskey's catalogue marks sensitive and whose AP document does not. The clause
	 * is therefore dormant until §7's catalogue sync fills the column; asserting it here
	 * keeps it correct for that day rather than pretending it guards anything now.
	 */
	ax_imp_assert(
		$ax_imp_results,
		'and a received declaration cannot carry that flag at all yet, which is why the clause above is dormant',
		! in_array( 'sensitive', array_map( 'strtolower', array_keys( array( 'id' => 1, 'type' => 1, 'name' => 1, 'updated' => 1, 'icon' => 1, '_misskey_license' => 1 ) ) ), true )
			&& null === ( axismundi_emoji_descriptor_from_tag( array( 'type' => 'Emoji', 'name' => ':wireflag:', 'id' => 'https://' . $ax_imp_other . '/emojis/wireflag', 'icon' => array( 'url' => 'https://cdn.' . $ax_imp_other . '/wireflag.png' ) ), 'https://' . $ax_imp_other . '/notes/1' )['is_sensitive'] ?? null )
	);
	$wpdb->update( axismundi_emoji_table(), array( 'is_sensitive' => 1 ), array( 'id' => (int) $ax_imp_localcat['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	ax_imp_assert( $ax_imp_results, 'nor does a sensitive substitute get shown in place of one that was not', null === axismundi_emoji_bare_presentation_row( 'impcat', $ax_imp_uncached_decl ) );
	$wpdb->update( axismundi_emoji_table(), array( 'is_sensitive' => 0 ), array( 'id' => (int) $ax_imp_localcat['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- restore.

	// The fix from review: a rejection is about rendering, and no substitute may reverse it.
	$ax_imp_rejected = array_merge( $ax_imp_uncached_decl, array( 'review_status' => 'rejected' ) );
	ax_imp_assert( $ax_imp_results, 'a rejected declaration stays text even though a local emoji of that name exists', null === axismundi_emoji_bare_presentation_row( 'impcat', $ax_imp_rejected ) );
} catch ( Throwable $ax_imp_error ) {
	ax_imp_assert( $ax_imp_results, 'the import suite ran to completion: ' . $ax_imp_error->getMessage(), false );
} finally {
	wp_set_current_user( 1 );
	foreach ( array_unique( array_filter( $ax_imp_ids ) ) as $ax_imp_id ) {
		$ax_imp_row = $wpdb->get_row( $wpdb->prepare( 'SELECT scope, content_hash, cached_path FROM ' . axismundi_emoji_table() . ' WHERE id = %d', (int) $ax_imp_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
		if ( ! is_array( $ax_imp_row ) ) {
			continue;
		}
		if ( 'local' === (string) $ax_imp_row['scope'] ) {
			axismundi_emoji_delete_local( (int) $ax_imp_id );
			continue;
		}
		$wpdb->delete( axismundi_emoji_table(), array( 'id' => (int) $ax_imp_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		if ( '' !== (string) $ax_imp_row['cached_path'] ) {
			// Never unconditionally: these paths are content-addressed and may be shared.
			axismundi_emoji_maybe_delete_file( axismundi_emoji_blob_dir() . '/' . $ax_imp_row['cached_path'], (string) $ax_imp_row['content_hash'] );
		}
	}
	foreach ( array( $ax_imp_origin, $ax_imp_other ) as $ax_imp_host ) {
		$wpdb->delete( axismundi_emoji_table(), array( 'emoji_authority' => $ax_imp_host ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
}

$ax_imp_failures = count( array_filter( $ax_imp_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_imp_results ), $ax_imp_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_imp_failures > 0 ? 1 : 0 );
}
exit( $ax_imp_failures > 0 ? 1 : 0 );
