<?php
/**
 * Local emoji registration (dev-only; dist-excluded).
 *
 * The rules here are the mirror image of the review rules, and the mirror is where the
 * mistakes live: what we tolerate on receive must be refused on send. So most of these
 * checks are rejections — oversized, non-square, wrong type, qualified name, duplicate
 * shortcode — because a local emoji that federates badly is a defect other people see
 * and we cannot fix.
 *
 * No network. Images are generated in memory.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-emoji.php';
// `wp_tempnam()` lives here, and the upload checks below stage files before any plugin
// code that would have loaded it.
require_once ABSPATH . 'wp-admin/includes/file.php';

global $wpdb;
$ax_local_results = array();
$ax_local_ids     = array();

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_local_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/**
 * A PNG of the given size, in memory.
 *
 * @param int $width  Pixels.
 * @param int $height Pixels.
 * @return string
 */
function ax_local_png( int $width, int $height ) : string {
	$image = imagecreatetruecolor( $width, $height );
	imagesavealpha( $image, true );
	imagefill( $image, 0, 0, imagecolorallocatealpha( $image, 0, 0, 0, 127 ) );
	imagefilledellipse( $image, (int) ( $width / 2 ), (int) ( $height / 2 ), (int) ( $width / 2 ), (int) ( $height / 2 ), imagecolorallocate( $image, 200, 30, 90 ) );
	ob_start();
	imagepng( $image );
	imagedestroy( $image );
	return (string) ob_get_clean();
}

/**
 * A square PNG that will not compress, for testing the size cap.
 *
 * @param int $side Pixels.
 * @return string
 */
function ax_local_noise_png( int $side ) : string {
	$image = imagecreatetruecolor( $side, $side );
	for ( $y = 0; $y < $side; $y++ ) {
		for ( $x = 0; $x < $side; $x++ ) {
			imagesetpixel( $image, $x, $y, imagecolorallocate( $image, ( $x * 7 + $y * 13 ) % 256, ( $x * 29 ) % 256, ( $y * 61 + $x * 3 ) % 256 ) );
		}
	}
	ob_start();
	imagepng( $image, null, 0 );
	imagedestroy( $image );
	return (string) ob_get_clean();
}

try {
	axismundi_emoji_install();
	foreach ( array( 'audittest', 'audittwo', 'auditdup' ) as $ax_local_key ) {
		$ax_local_stale = axismundi_emoji_local_get( $ax_local_key );
		if ( is_array( $ax_local_stale ) ) {
			axismundi_emoji_delete_local( (int) $ax_local_stale['id'] );
		}
	}

	// -- Identity ---------------------------------------------------------------------

	$ax_local_authority = axismundi_emoji_local_authority();
	ax_local_assert( $ax_local_results, 'local emoji are published under this site\'s own authority', '' !== $ax_local_authority && false === strpos( $ax_local_authority, '/' ) );
	if ( function_exists( 'axismundi_actors_webfinger_authority' ) ) {
		ax_local_assert( $ax_local_results, 'and that authority is the one Actors already federates under, not a second opinion', $ax_local_authority === strtolower( axismundi_actors_webfinger_authority() ) );
	}

	ax_local_assert( $ax_local_results, 'a plain shortcode validates', 'audittest' === axismundi_emoji_validate_local_shortcode( ':audittest:' ) );
	ax_local_assert( $ax_local_results, 'colons are optional when writing one', 'audittest' === axismundi_emoji_validate_local_shortcode( 'audittest' ) );
	ax_local_assert( $ax_local_results, 'a single character is refused, per FEP-9098\'s two-character minimum', is_wp_error( axismundi_emoji_validate_local_shortcode( ':a:' ) ) );
	ax_local_assert( $ax_local_results, 'punctuation is refused rather than mangled into something else', is_wp_error( axismundi_emoji_validate_local_shortcode( ':my-emoji:' ) ) );
	/*
	 * The qualified form is accepted everywhere else, because Misskey really sends it.
	 * Accepting it here would let this site publish an emoji identified as another
	 * server's — the one place the parser's leniency would become a lie on the wire.
	 */
	ax_local_assert( $ax_local_results, 'a name qualified with another server is refused, because we cannot publish their identity', is_wp_error( axismundi_emoji_validate_local_shortcode( ':audittest@misskey.io:' ) ) );

	// -- What we refuse to publish ----------------------------------------------------

	ax_local_assert( $ax_local_results, 'a non-square image is refused, because clients mis-render it', is_wp_error( axismundi_emoji_register_local( ax_local_png( 64, 32 ), ':audittest:' ) ) );
	ax_local_assert( $ax_local_results, 'bytes that are not an image at all are refused', is_wp_error( axismundi_emoji_register_local( 'not an image', ':audittest:' ) ) );
	ax_local_assert( $ax_local_results, 'an empty file is refused', is_wp_error( axismundi_emoji_register_local( '', ':audittest:' ) ) );

	/*
	 * The send/receive asymmetry, stated as a number. The ingestion cap is far above
	 * this on purpose — a real instance ships emoji many times FEP-9098's
	 * recommendation, and refusing to *display* those would be the worse failure.
	 */
	ax_local_assert(
		$ax_local_results,
		'the publish cap is FEP-9098\'s 256 KB and sits far below what we accept from the network',
		262144 === AXISMUNDI_EMOJI_OUTBOUND_MAX_BYTES && AXISMUNDI_EMOJI_OUTBOUND_MAX_BYTES < AXISMUNDI_EMOJI_MAX_BYTES
	);
	// Noise, not a shape: a flat or transparent image of any dimensions compresses to a
	// few KB and would never reach the cap, so the assertion would pass without testing it.
	$ax_local_big = ax_local_noise_png( 700 );
	ax_local_assert(
		$ax_local_results,
		'an image over that cap is refused with the size named, so the operator can act on it',
		strlen( $ax_local_big ) > AXISMUNDI_EMOJI_OUTBOUND_MAX_BYTES
			&& is_wp_error( axismundi_emoji_register_local( $ax_local_big, ':audittest:' ) )
	);
	ax_local_assert( $ax_local_results, 'and nothing was registered by any of those attempts', null === axismundi_emoji_local_get( 'audittest' ) );

	// -- Registration -----------------------------------------------------------------

	$ax_local_bytes = ax_local_png( 128, 128 );
	$ax_local_row   = axismundi_emoji_register_local( $ax_local_bytes, ':audittest:', array( 'category' => 'Audit' ) );
	if ( is_wp_error( $ax_local_row ) ) {
		throw new RuntimeException( 'registration: ' . $ax_local_row->get_error_message() );
	}
	$ax_local_ids[] = (int) $ax_local_row['id'];

	ax_local_assert( $ax_local_results, 'a registered local emoji is approved, never pending — there is no third party to review', 'approved' === $ax_local_row['review_status'] && 'local' === $ax_local_row['scope'] );
	ax_local_assert( $ax_local_results, 'it is publishable and offered to the picker', 1 === (int) $ax_local_row['outbound_allowed'] && 1 === (int) $ax_local_row['picker_visible'] );
	ax_local_assert( $ax_local_results, 'its bytes are on disk at the content-addressed path', file_exists( axismundi_emoji_blob_dir() . '/' . $ax_local_row['cached_path'] ) );
	ax_local_assert( $ax_local_results, 'the stored file is byte-identical to what was uploaded', hash( 'sha256', (string) file_get_contents( axismundi_emoji_blob_dir() . '/' . $ax_local_row['cached_path'] ) ) === $ax_local_row['content_hash'] );
	ax_local_assert( $ax_local_results, 'the store directory is not browsable', file_exists( axismundi_emoji_blob_dir() . '/index.php' ) );
	ax_local_assert( $ax_local_results, 'a second emoji with the same shortcode is refused', is_wp_error( axismundi_emoji_register_local( ax_local_png( 64, 64 ), ':audittest:' ) ) );

	/*
	 * Uniqueness is site-wide, not per-authority. The UNIQUE index answers a different
	 * question, and a site that changed domain would otherwise be able to register a
	 * second row for the same name under the new authority.
	 */
	$wpdb->update( axismundi_emoji_table(), array( 'emoji_authority' => 'renamed.example.com' ), array( 'id' => (int) $ax_local_row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- simulating a site rename.
	ax_local_assert( $ax_local_results, 'a site rename does not let the same shortcode be registered twice', is_wp_error( axismundi_emoji_register_local( ax_local_png( 64, 64 ), ':audittest:' ) ) );
	$wpdb->update( axismundi_emoji_table(), array( 'emoji_authority' => $ax_local_authority ), array( 'id' => (int) $ax_local_row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- restore.

	// -- The quota boundary -----------------------------------------------------------

	/*
	 * The ceiling bounds how much of *other people's* content we accumulate. Charging a
	 * site's own uploads to it would let a large local set stop remote caching, which is
	 * the opposite of what the operator who uploaded them intended.
	 */
	$ax_local_before = axismundi_emoji_store_size();
	$ax_local_two    = axismundi_emoji_register_local( ax_local_png( 96, 96 ), ':audittwo:' );
	if ( ! is_wp_error( $ax_local_two ) ) {
		$ax_local_ids[] = (int) $ax_local_two['id'];
	}
	ax_local_assert( $ax_local_results, 'a local upload does not consume the remote cache quota', ! is_wp_error( $ax_local_two ) && axismundi_emoji_store_size() === $ax_local_before );

	// -- localOnly --------------------------------------------------------------------

	$ax_local_private = axismundi_emoji_register_local( ax_local_png( 64, 64 ), ':auditdup:', array( 'local_only' => true ) );
	if ( ! is_wp_error( $ax_local_private ) ) {
		$ax_local_ids[] = (int) $ax_local_private['id'];
	}
	ax_local_assert( $ax_local_results, 'a localOnly emoji is registered but never publishable, so the two flags cannot disagree', ! is_wp_error( $ax_local_private ) && 1 === (int) $ax_local_private['local_only'] && 0 === (int) $ax_local_private['outbound_allowed'] );

	// -- Deletion ---------------------------------------------------------------------

	$ax_local_path = axismundi_emoji_blob_dir() . '/' . $ax_local_row['cached_path'];
	ax_local_assert( $ax_local_results, 'deleting a local emoji removes its row', axismundi_emoji_delete_local( (int) $ax_local_row['id'] ) && null === axismundi_emoji_local_get( 'audittest' ) );
	ax_local_assert( $ax_local_results, 'and collects its bytes, since nothing else claimed them', ! file_exists( $ax_local_path ) );

	/*
	 * A local upload and a cached remote emoji can be the same picture. GC is
	 * reference-counted and scope-blind precisely so deleting one does not blind the
	 * other, which would be a remote emoji rendering as a broken image.
	 */
	$ax_local_shared = ax_local_png( 72, 72 );
	$ax_local_hash   = hash( 'sha256', $ax_local_shared );
	$ax_local_keep   = axismundi_emoji_register_local( $ax_local_shared, ':audittest:' );
	if ( is_wp_error( $ax_local_keep ) ) {
		throw new RuntimeException( 'shared-blob fixture: ' . $ax_local_keep->get_error_message() );
	}
	$ax_local_ids[] = (int) $ax_local_keep['id'];
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a remote row claiming the same blob.
		axismundi_emoji_table(),
		array(
			'emoji_authority' => 'shared-blob.example.org',
			'shortcode_key'   => 'audittest',
			'shortcode'       => ':audittest:',
			'scope'           => 'remote',
			'source_kind'     => 'remote',
			'review_status'   => 'approved',
			'content_hash'    => $ax_local_hash,
			'cached_path'     => $ax_local_keep['cached_path'],
			'byte_size'       => strlen( $ax_local_shared ),
			'first_seen_at'   => current_time( 'mysql', true ),
			'last_seen_at'    => current_time( 'mysql', true ),
		)
	);
	$ax_local_remote_id = (int) $wpdb->insert_id;
	axismundi_emoji_delete_local( (int) $ax_local_keep['id'] );
	ax_local_assert( $ax_local_results, 'a blob a remote row still claims survives deletion of the local one that shared it', file_exists( axismundi_emoji_blob_dir() . '/' . $ax_local_keep['cached_path'] ) );
	$wpdb->delete( axismundi_emoji_table(), array( 'id' => $ax_local_remote_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	axismundi_emoji_maybe_delete_file( axismundi_emoji_blob_dir() . '/' . $ax_local_keep['cached_path'], $ax_local_hash );
	ax_local_assert( $ax_local_results, 'and is collected once the last claim goes', ! file_exists( axismundi_emoji_blob_dir() . '/' . $ax_local_keep['cached_path'] ) );

	// -- Upload ------------------------------------------------------------------------

	/*
	 * Core does the accepting: the rule is not "must create an attachment" but "must not
	 * bypass WordPress's upload validation". These run the real pipeline through
	 * `wp_handle_sideload()`, which is the entry point for a file already on disk —
	 * `wp_handle_upload()` requires `is_uploaded_file()`, true only inside a genuine POST,
	 * so it cannot be reached from here at all. Same validation either way; the browser
	 * path differs only in that one test.
	 */
	wp_set_current_user( 1 );

	$ax_local_tmp = wp_tempnam( 'ax-emoji-upload.png' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fixture staging.
	file_put_contents( $ax_local_tmp, ax_local_png( 110, 110 ) );
	$ax_local_uploaded = axismundi_emoji_handle_upload(
		array( 'name' => 'upload.png', 'type' => 'image/png', 'tmp_name' => $ax_local_tmp, 'error' => 0, 'size' => filesize( $ax_local_tmp ) ),
		':auditup:',
		array( 'sideload' => true )
	);
	if ( ! is_wp_error( $ax_local_uploaded ) ) {
		$ax_local_ids[] = (int) $ax_local_uploaded['id'];
	}
	ax_local_assert( $ax_local_results, 'an uploaded image becomes a registered local emoji', ! is_wp_error( $ax_local_uploaded ) && 'local' === $ax_local_uploaded['scope'] );
	ax_local_assert( $ax_local_results, 'and its bytes land in the content-addressed store, not the media tree', ! is_wp_error( $ax_local_uploaded ) && file_exists( axismundi_emoji_blob_dir() . '/' . $ax_local_uploaded['cached_path'] ) );
	ax_local_assert( $ax_local_results, 'no attachment post is created for it, because the registry row is the record', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_title LIKE %s", '%auditup%' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- assertion about Core's table.

	/*
	 * The filter must not outlive its one call. Left registered, it would divert any other
	 * plugin's upload in the same request into the emoji store — a bug that only shows up
	 * when two plugins upload in one request.
	 */
	ax_local_assert( $ax_local_results, 'the upload-directory diversion is removed once the file is accepted', false === has_filter( 'upload_dir', 'axismundi_emoji_upload_dir' ) );
	$ax_local_dirs = wp_upload_dir();
	ax_local_assert( $ax_local_results, 'so an unrelated upload in the same request still goes to the media tree', false === strpos( (string) $ax_local_dirs['path'], 'axismundi-emoji' ) );

	// A rejected upload must leave nothing behind, in the store or in the registry.
	$ax_local_tmp2 = wp_tempnam( 'ax-emoji-oblong.png' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fixture staging.
	file_put_contents( $ax_local_tmp2, ax_local_png( 80, 40 ) );
	$ax_local_rejected = axismundi_emoji_handle_upload(
		array( 'name' => 'oblong.png', 'type' => 'image/png', 'tmp_name' => $ax_local_tmp2, 'error' => 0, 'size' => filesize( $ax_local_tmp2 ) ),
		':auditbad:',
		array( 'sideload' => true )
	);
	ax_local_assert( $ax_local_results, 'a non-square upload is refused', is_wp_error( $ax_local_rejected ) );
	ax_local_assert( $ax_local_results, 'and leaves no registry row behind', null === axismundi_emoji_local_get( 'auditbad' ) );
	ax_local_assert( $ax_local_results, 'nor a stray accepted file in the store', ! is_dir( axismundi_emoji_blob_dir() . '/incoming' ) || array() === array_diff( (array) scandir( axismundi_emoji_blob_dir() . '/incoming' ), array( '.', '..' ) ) );

	// A bad shortcode is refused before anything is written at all.
	$ax_local_tmp3 = wp_tempnam( 'ax-emoji-name.png' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fixture staging.
	file_put_contents( $ax_local_tmp3, ax_local_png( 64, 64 ) );
	ax_local_assert(
		$ax_local_results,
		'a shortcode naming another server is refused before the file is even accepted',
		is_wp_error( axismundi_emoji_handle_upload( array( 'name' => 'n.png', 'type' => 'image/png', 'tmp_name' => $ax_local_tmp3, 'error' => 0, 'size' => filesize( $ax_local_tmp3 ) ), ':auditup@misskey.io:' ) )
			&& file_exists( $ax_local_tmp3 )
	);
	wp_delete_file( $ax_local_tmp3 );

	// -- What the bundle ships, and under what terms ------------------------------------

	/*
	 * Two bundled files, two different kinds of right, and the difference is load-bearing.
	 *
	 * `:axismundi:` is ours and the copyright holder released it under the plugin's GPL.
	 * `:wordpress:` is the WordPress Foundation's trademark, included under a policy that
	 * permits referring to WordPress — which is permission to *use the mark*, not a
	 * copyright licence. Describing it as GPL would be a false statement about somebody
	 * else's property, and it is exactly the sort of thing a later tidy-up flattens.
	 */
	$ax_local_notice = (string) file_get_contents( dirname( __DIR__ ) . '/emoji/LICENSE.txt' );
	ax_local_assert( $ax_local_results, 'each bundled emoji has a file beside it stating its terms', str_contains( $ax_local_notice, 'axismundi.webp' ) && str_contains( $ax_local_notice, 'wordpress.webp' ) );
	ax_local_assert( $ax_local_results, 'our own asset is released under the plugin\'s licence', str_contains( $ax_local_notice, 'GPL-3.0-or-later' ) );
	ax_local_assert(
		$ax_local_results,
		'and the WordPress mark is not described as GPL, because a trademark permission is not a copyright licence',
		str_contains( $ax_local_notice, 'NOT licensed under the GPL' )
			&& str_contains( $ax_local_notice, 'wordpressfoundation.org/trademark-policy' )
	);
	foreach ( AXISMUNDI_EMOJI_BUNDLED as $ax_local_key => $ax_local_spec ) {
		$ax_local_row = axismundi_emoji_local_get( (string) $ax_local_key );
		ax_local_assert(
			$ax_local_results,
			sprintf( ':%s: ships registered and within the rules we enforce on anyone else', $ax_local_key ),
			is_array( $ax_local_row )
				&& (int) $ax_local_row['width'] === (int) $ax_local_row['height']
				&& (int) $ax_local_row['byte_size'] <= AXISMUNDI_EMOJI_OUTBOUND_MAX_BYTES
				&& in_array( (string) $ax_local_row['media_type'], AXISMUNDI_EMOJI_OUTBOUND_MEDIA_TYPES, true )
		);
	}

	// -- The bundled emoji ------------------------------------------------------------

	$ax_local_bundled = axismundi_emoji_local_get( 'axismundi' );
	ax_local_assert( $ax_local_results, 'the emoji this plugin ships with is registered', is_array( $ax_local_bundled ) );
	ax_local_assert( $ax_local_results, 'as a square WebP within the publish cap, so it federates correctly', is_array( $ax_local_bundled ) && 'image/webp' === $ax_local_bundled['media_type'] && (int) $ax_local_bundled['width'] === (int) $ax_local_bundled['height'] && (int) $ax_local_bundled['byte_size'] <= AXISMUNDI_EMOJI_OUTBOUND_MAX_BYTES );
	/*
	 * Deleting it has to stick. Re-registering on every activation or schema check would
	 * mean an operator who removed it on purpose has to keep removing it.
	 */
	$ax_local_bundled_marker = get_option( 'ax_emoji_bundled_registered', array() );
	ax_local_assert( $ax_local_results, 'each bundled emoji is marked done independently, so a deliberate deletion is not undone', is_array( $ax_local_bundled_marker ) && isset( $ax_local_bundled_marker['axismundi'], $ax_local_bundled_marker['wordpress'] ) );
	ax_local_assert( $ax_local_results, 'the old scalar marker means only the original bundle was registered, so a new bundled emoji can still be provisioned', array( 'axismundi' => '0.1.0' ) === axismundi_emoji_bundled_registration_marker( '0.1.0' ) );
	ax_local_assert( $ax_local_results, 'bundle provisioning runs after a no-op schema check, so a new asset reaches existing installations', 6 === has_action( 'init', 'axismundi_emoji_bootstrap_bundled' ) );
	$ax_local_wordpress = axismundi_emoji_local_get( 'wordpress' );
	if ( is_array( $ax_local_wordpress ) ) {
		axismundi_emoji_delete_local( (int) $ax_local_wordpress['id'] );
		update_option( 'ax_emoji_bundled_registered', '0.1.0', false );
		axismundi_emoji_maybe_upgrade();
		ax_local_assert( $ax_local_results, 'a current schema does not hide the legacy-marker condition by rerunning install', null === axismundi_emoji_local_get( 'wordpress' ) );
		axismundi_emoji_bootstrap_bundled();
		ax_local_assert( $ax_local_results, 'the post-schema bootstrap registers a bundle added after the old marker was written', is_array( axismundi_emoji_local_get( 'wordpress' ) ) );
	} else {
		ax_local_assert( $ax_local_results, 'the bundle-upgrade regression setup has its WordPress emoji row', false );
		ax_local_assert( $ax_local_results, 'the post-schema bootstrap registers a bundle added after the old marker was written', false );
	}
	axismundi_emoji_register_bundled();
	ax_local_assert( $ax_local_results, 'and calling the installer again does not create a second copy', 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . axismundi_emoji_table() . ' WHERE scope = %s AND shortcode_key = %s', 'local', 'axismundi' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.

	/*
	 * The store is shared and content-addressed, so a claim released elsewhere can collect
	 * a file this row still points at — which is exactly how the shipped emoji ended up
	 * registered but unrenderable. Unlike every other emoji here, its bytes ship with the
	 * plugin, so the loss is repairable rather than permanent.
	 */
	if ( is_array( $ax_local_bundled ) ) {
		$ax_local_bundled_file = axismundi_emoji_blob_dir() . '/' . $ax_local_bundled['cached_path'];
		wp_delete_file( $ax_local_bundled_file );
		ax_local_assert( $ax_local_results, 'a bundled emoji whose blob went missing is repaired from the shipped file', axismundi_emoji_restore_bundled_blob() && file_exists( $ax_local_bundled_file ) );
		ax_local_assert( $ax_local_results, 'and the restored bytes hash to what the row already recorded', hash( 'sha256', (string) file_get_contents( $ax_local_bundled_file ) ) === $ax_local_bundled['content_hash'] );
		ax_local_assert( $ax_local_results, 'repairing an intact blob is a no-op rather than a rewrite', false === axismundi_emoji_restore_bundled_blob() );
	}
} catch ( Throwable $ax_local_error ) {
	ax_local_assert( $ax_local_results, 'the local registration suite ran to completion: ' . $ax_local_error->getMessage(), false );
} finally {
	foreach ( array_unique( array_filter( $ax_local_ids ) ) as $ax_local_id ) {
		axismundi_emoji_delete_local( (int) $ax_local_id );
	}
	$wpdb->delete( axismundi_emoji_table(), array( 'emoji_authority' => 'shared-blob.example.org' ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
}

$ax_local_failures = count( array_filter( $ax_local_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_local_results ), $ax_local_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_local_failures > 0 ? 1 : 0 );
}
exit( $ax_local_failures > 0 ? 1 : 0 );
