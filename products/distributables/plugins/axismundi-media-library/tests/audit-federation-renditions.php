<?php
/**
 * FEP-1311 federation rendition service regression (dev-only; dist-excluded).
 * No network, no real uploads: attachment metadata is synthesized.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_fed_results = array();
$ax_fed_ids     = array();

/**
 * @param array  $results Accumulator.
 * @param string $label Contract.
 * @param bool   $cond Holds.
 * @return void
 */
function ax_fed_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/**
 * Create an attachment with synthesized subsize metadata.
 *
 * @param string                    $mime  MIME type.
 * @param array<string,array>       $sizes `sizes` metadata.
 * @return int Attachment ID.
 */
function ax_fed_attachment( string $mime, array $sizes ) : int {
	$id = (int) wp_insert_attachment(
		array( 'post_title' => 'ax fed probe', 'post_mime_type' => $mime, 'post_status' => 'inherit' ),
		'2026/07/ax-fed-probe.jpg'
	);
	wp_update_attachment_metadata( $id, array( 'file' => '2026/07/ax-fed-probe.jpg', 'width' => 4000, 'height' => 3000, 'sizes' => $sizes ) );
	return $id;
}

/** One synthesized subsize entry. */
function ax_fed_size( string $file, int $w, int $h, int $bytes ) : array {
	return array( 'file' => $file, 'width' => $w, 'height' => $h, 'mime-type' => 'image/jpeg', 'filesize' => $bytes );
}

try {
	// A full ladder: thumbnail / medium / medium_large / large.
	$full = ax_fed_attachment(
		'image/jpeg',
		array(
			'thumbnail'    => ax_fed_size( 'p-150x150.jpg', 150, 150, 6000 ),
			'medium'       => ax_fed_size( 'p-300x225.jpg', 300, 225, 18000 ),
			'medium_large' => ax_fed_size( 'p-768x576.jpg', 768, 576, 70000 ),
			'large'        => ax_fed_size( 'p-1024x768.jpg', 1024, 768, 140000 ),
		)
	);
	$ax_fed_ids[] = $full;
	$out = axismundi_media_federation_renditions( $full );
	$widths = array_map( static fn( array $r ) : int => $r['width'], $out );
	ax_fed_assert(
		$ax_fed_results,
		'a full size ladder returns at most 4 renditions, largest first, with FEP-1311 members',
		4 === count( $out ) && array( 1024, 768, 300, 150 ) === $widths
			&& array( 'url', 'mediaType', 'width', 'height', 'size' ) === array_keys( $out[0] )
			&& 140000 === $out[0]['size'] && 'image/jpeg' === $out[0]['mediaType']
	);

	// The original is never advertised.
	$original = wp_get_attachment_url( $full );
	ax_fed_assert(
		$ax_fed_results,
		'the original file URL never appears in the rendition list',
		! in_array( $original, array_map( static fn( array $r ) : string => $r['url'], $out ), true )
	);

	// No generated subsize at all -> empty, never an original fallback.
	$bare = ax_fed_attachment( 'image/jpeg', array() );
	$ax_fed_ids[] = $bare;
	ax_fed_assert( $ax_fed_results, 'an image with no generated derivative returns nothing rather than the original', array() === axismundi_media_federation_renditions( $bare ) );

	// A subsize whose byte size cannot be established is omitted, not guessed.
	$sizeless = ax_fed_attachment( 'image/jpeg', array( 'medium' => array( 'file' => 'missing-300x225.jpg', 'width' => 300, 'height' => 225, 'mime-type' => 'image/jpeg' ) ) );
	$ax_fed_ids[] = $sizeless;
	ax_fed_assert( $ax_fed_results, 'a subsize with no readable byte size is omitted', array() === axismundi_media_federation_renditions( $sizeless ) );

	// Jetpack Photon virtual subsizes have no local file or derivative byte count. Admit
	// only its explicitly marked, trusted URL and do not invent a serialized size.
	$photon_url = 'https://i0.wp.com/example.test/wp-content/uploads/2026/07/probe.webp?fit=1024%2C768&ssl=1';
	$virtual    = ax_fed_attachment(
		'image/webp',
		array(
			'large' => array(
				'file'       => 'probe.webp',
				'width'      => 1024,
				'height'     => 768,
				'mime-type'  => 'image/webp',
				'source_url' => $photon_url,
			),
		)
	);
	$ax_fed_ids[] = $virtual;
	$virtual_meta = wp_get_attachment_metadata( $virtual );
	$virtual_meta['filesize'] = 250000;
	wp_update_attachment_metadata( $virtual, $virtual_meta );
	$virtual_out = axismundi_media_federation_renditions( $virtual );
	ax_fed_assert(
		$ax_fed_results,
		'a trusted provider metadata URL is sufficient without a request-context marker/registry/downsize filter or invented byte size',
		1 === count( $virtual_out ) && $photon_url === $virtual_out[0]['url']
			&& 'image/webp' === $virtual_out[0]['mediaType'] && ! isset( $virtual_out[0]['size'] )
	);
	$virtual_capped = axismundi_media_federation_renditions( $virtual, array( 'max_bytes' => 200000 ) );
	ax_fed_assert( $ax_fed_results, 'a virtual rendition is rejected when its source exceeds the byte ceiling', array() === $virtual_capped );

	$untrusted_url = 'https://cdn.example.test/probe.webp?width=1024';
	$untrusted_meta = $virtual_meta;
	$untrusted_meta['sizes']['large']['source_url'] = $untrusted_url;
	wp_update_attachment_metadata( $virtual, $untrusted_meta );
	$untrusted_out = axismundi_media_federation_renditions( $virtual );
	ax_fed_assert( $ax_fed_results, 'an arbitrary virtual CDN URL remains fail-closed', array() === $untrusted_out );
	$diagnostics = axismundi_media_federation_rendition_diagnostics( $virtual );
	ax_fed_assert(
		$ax_fed_results,
		'the authenticated diagnostic service reports each policy gate without filesystem paths',
		isset( $diagnostics['sizes']['large'] ) && null === $diagnostics['sizes']['large']['accepted']
			&& ! isset( $diagnostics['path'] )
	);

	// Duplicate dimensions collapse.
	$dupe = ax_fed_attachment(
		'image/jpeg',
		array(
			'medium'       => ax_fed_size( 'd-300x225.jpg', 300, 225, 18000 ),
			'medium_large' => ax_fed_size( 'd-300x225.jpg', 300, 225, 18000 ),
			'large'        => ax_fed_size( 'd-1024x768.jpg', 1024, 768, 140000 ),
		)
	);
	$ax_fed_ids[] = $dupe;
	$dupe_out = axismundi_media_federation_renditions( $dupe );
	ax_fed_assert( $ax_fed_results, 'identical dimensions/URLs are deduplicated', 2 === count( $dupe_out ) );

	// Policy caps drop oversized entries.
	$capped = axismundi_media_federation_renditions( $full, array( 'max_bytes' => 20000 ) );
	$capped_widths = array_map( static fn( array $r ) : int => $r['width'], $capped );
	ax_fed_assert( $ax_fed_results, 'a byte cap drops entries above it', array( 300, 150 ) === $capped_widths );
	ax_fed_assert( $ax_fed_results, 'a max policy limits the returned count', 2 === count( axismundi_media_federation_renditions( $full, array( 'max' => 2 ) ) ) );

	// Non-image types never invent versions.
	$video = ax_fed_attachment( 'video/mp4', array( 'medium' => ax_fed_size( 'v-300x225.jpg', 300, 225, 18000 ) ) );
	$ax_fed_ids[] = $video;
	ax_fed_assert( $ax_fed_results, 'video and other non-image types return no renditions', array() === axismundi_media_federation_renditions( $video ) );

	// Private media is fail-closed.
	update_post_meta( $full, '_ax_media_visibility', 'private' );
	$private_out = axismundi_media_federation_renditions( $full );
	update_post_meta( $full, '_ax_media_visibility', 'public' );
	ax_fed_assert( $ax_fed_results, 'private media returns nothing (fail-closed)', array() === $private_out && 4 === count( axismundi_media_federation_renditions( $full ) ) );

	// Unknown / non-attachment input.
	ax_fed_assert( $ax_fed_results, 'a missing or non-attachment id returns nothing', array() === axismundi_media_federation_renditions( 0 ) && array() === axismundi_media_federation_renditions( 999999999 ) );

} finally {
	foreach ( array_unique( $ax_fed_ids ) as $id ) {
		wp_delete_attachment( (int) $id, true );
	}
}

$ax_fed_failures = count( array_filter( $ax_fed_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_fed_results ), $ax_fed_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_fed_failures > 0 ? 1 : 0 );
}
exit( $ax_fed_failures > 0 ? 1 : 0 );
