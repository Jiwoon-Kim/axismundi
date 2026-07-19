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

	// Jetpack Photon virtual subsizes have no local file, derivative byte count, or raw
	// metadata URL. WordPress image downsize resolves the provider URL at runtime.
	$photon_url = 'https://i0.wp.com/example.test/wp-content/uploads/2026/07/probe.webp?fit=1024%2C768&ssl=1';
	$virtual    = ax_fed_attachment(
		'image/webp',
		array(
			'large' => array(
				'file'      => 'probe.webp',
				'width'     => 1024,
				'height'    => 768,
				'mime-type' => 'image/webp',
				'virtual'   => true,
			),
		)
	);
	$ax_fed_ids[] = $virtual;
	$virtual_meta = wp_get_attachment_metadata( $virtual );
	$virtual_meta['filesize'] = 250000;
	wp_update_attachment_metadata( $virtual, $virtual_meta );
	$trusted_downsize = static function ( $downsize, int $attachment_id, $size ) use ( $virtual, $photon_url ) {
		if ( apply_filters( 'jetpack_photon_override_image_downsize', false, compact( 'attachment_id', 'size' ) ) ) {
			return $downsize;
		}
		return $virtual === $attachment_id && 'large' === $size ? array( $photon_url, 1024, 768, true ) : $downsize;
	};
	$editor_override = static fn() : bool => true;
	add_filter( 'image_downsize', $trusted_downsize, 10, 3 );
	add_filter( 'jetpack_photon_override_image_downsize', $editor_override, 999999 );
	$virtual_out = axismundi_media_federation_renditions( $virtual );
	ax_fed_assert(
		$ax_fed_results,
		'a trusted runtime provider URL remains available inside an editor-originated REST save',
		1 === count( $virtual_out ) && $photon_url === $virtual_out[0]['url']
			&& 'image/webp' === $virtual_out[0]['mediaType'] && ! isset( $virtual_out[0]['size'] )
	);
	ax_fed_assert(
		$ax_fed_results,
		'the federation lookup restores the provider editor override after its scoped downsize',
		true === apply_filters( 'jetpack_photon_override_image_downsize', false )
	);
	$virtual_capped = axismundi_media_federation_renditions( $virtual, array( 'max_bytes' => 200000 ) );
	ax_fed_assert( $ax_fed_results, 'a virtual rendition is rejected when its source exceeds the byte ceiling', array() === $virtual_capped );
	remove_filter( 'image_downsize', $trusted_downsize, 10 );
	remove_filter( 'jetpack_photon_override_image_downsize', $editor_override, 999999 );

	$provider_source = ax_fed_attachment(
		'image/png',
		array(
			'medium' => array( 'file' => 'provider.png', 'width' => 212, 'height' => 300, 'mime-type' => 'image/png', 'virtual' => true ),
		)
	);
	$ax_fed_ids[] = $provider_source;
	$provider_meta = wp_get_attachment_metadata( $provider_source );
	$provider_meta['width'] = 724;
	$provider_meta['height'] = 1023;
	$provider_meta['filesize'] = 311951;
	wp_update_attachment_metadata( $provider_source, $provider_meta );
	$provider_url = 'https://i0.wp.com/example.test/wp-content/uploads/2026/07/provider.png?fit=724%2C1023&ssl=1';
	$provider_downsize = static function ( $downsize, int $attachment_id, $size ) use ( $provider_source, $provider_url ) {
		return $provider_source === $attachment_id && is_array( $size ) && array( 724, 1023 ) === array_values( $size )
			? array( $provider_url, 724, 1023, true )
			: $downsize;
	};
	add_filter( 'image_downsize', $provider_downsize, 10, 3 );
	$embedded_out = axismundi_media_federation_renditions( $provider_source, array( 'max' => 1, 'max_dimension' => 1024, 'provider_source' => true ) );
	ax_fed_assert(
		$ax_fed_results,
		'an embedded role may select a bounded provider derivative larger than stored virtual subsizes without exposing the original',
		1 === count( $embedded_out ) && $provider_url === $embedded_out[0]['url']
			&& 724 === $embedded_out[0]['width'] && 1023 === $embedded_out[0]['height']
			&& wp_get_attachment_url( $provider_source ) !== $embedded_out[0]['url']
	);
	$provider_meta['sizes'] = array();
	wp_update_attachment_metadata( $provider_source, $provider_meta );
	$provider_without_sizes = axismundi_media_federation_renditions( $provider_source, array( 'max' => 1, 'max_dimension' => 1024, 'provider_source' => true ) );
	ax_fed_assert(
		$ax_fed_results,
		'an explicit embedded provider policy does not depend on a persisted subsize inventory',
		1 === count( $provider_without_sizes ) && $provider_url === $provider_without_sizes[0]['url']
	);
	remove_filter( 'image_downsize', $provider_downsize, 10 );

	$untrusted_url = 'https://cdn.example.test/probe.webp?width=1024';
	$untrusted_downsize = static function ( $downsize, int $attachment_id, $size ) use ( $virtual, $untrusted_url ) {
		return $virtual === $attachment_id && 'large' === $size ? array( $untrusted_url, 1024, 768, true ) : $downsize;
	};
	add_filter( 'image_downsize', $untrusted_downsize, 10, 3 );
	$untrusted_out = axismundi_media_federation_renditions( $virtual );
	ax_fed_assert( $ax_fed_results, 'an arbitrary virtual CDN URL remains fail-closed', array() === $untrusted_out );
	$diagnostics = axismundi_media_federation_rendition_diagnostics( $virtual );
	ax_fed_assert(
		$ax_fed_results,
		'the authenticated diagnostic service reports each policy gate without filesystem paths',
		isset( $diagnostics['sizes']['large'] ) && null === $diagnostics['sizes']['large']['accepted']
			&& $untrusted_url === $diagnostics['sizes']['large']['downsize_url']
			&& ! isset( $diagnostics['path'] )
	);
	remove_filter( 'image_downsize', $untrusted_downsize, 10 );
	$previous_user = get_current_user_id();
	wp_set_current_user( 0 );
	$public_diagnostic = rest_do_request( '/axismundi/v1/media/' . $virtual . '/federation-diagnostics' );
	update_post_meta( $virtual, '_ax_media_visibility', 'private' );
	$private_diagnostic = rest_do_request( '/axismundi/v1/media/' . $virtual . '/federation-diagnostics' );
	update_post_meta( $virtual, '_ax_media_visibility', 'public' );
	wp_set_current_user( $previous_user );
	ax_fed_assert(
		$ax_fed_results,
		'anonymous diagnostics are limited to media already eligible for public federation',
		200 === $public_diagnostic->get_status() && 404 === $private_diagnostic->get_status()
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
