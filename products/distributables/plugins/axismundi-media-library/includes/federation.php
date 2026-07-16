<?php
/**
 * FEP-1311 federation rendition service.
 *
 * The Media Library owns *which* already-generated derivatives may be advertised to the
 * federation; Object Projections only serializes the result and never reads attachment
 * metadata internals. The contract lives in
 * axismundi-object-projections/docs/MEDIA-RENDITIONS.md.
 *
 * Two rules are structural rather than advisory here:
 * the original file is never advertised, and an entry whose byte size cannot be read is
 * omitted rather than guessed.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

/**
 * Core sizes eligible for federation.
 *
 * Deliberately not `get_intermediate_image_sizes()` in full: themes and plugins register
 * many extra sizes, and advertising all of them would explode the rendition list. A size
 * still has to be registered *and* generated to qualify.
 */
const AXISMUNDI_MEDIA_FEDERATION_SIZES = array( 'thumbnail', 'medium', 'medium_large', 'large' );

/** Maximum advertised renditions. */
const AXISMUNDI_MEDIA_FEDERATION_MAX = 4;

/**
 * Resolve the federation rendition policy.
 *
 * The policy may be narrowed but cannot introduce the original: that exclusion is
 * enforced structurally in the entry builder, not by policy.
 *
 * @param array<string,mixed> $policy Caller overrides.
 * @return array<string,mixed>
 */
function axismundi_media_federation_rendition_policy( array $policy = array() ) : array {
	$defaults = array(
		'sizes'         => AXISMUNDI_MEDIA_FEDERATION_SIZES,
		'max'           => AXISMUNDI_MEDIA_FEDERATION_MAX,
		'max_bytes'     => 8388608,
		'max_dimension' => 4096,
		'max_pixels'    => 16000000,
	);
	$policy = array_merge( $defaults, $policy );
	/**
	 * Filter the federation rendition policy.
	 *
	 * @since 0.0.27
	 * @param array<string,mixed> $policy Resolved policy.
	 */
	return (array) apply_filters( 'axismundi_media_federation_rendition_policy', $policy );
}

/**
 * Anonymous, cache-safe federation gate.
 *
 * Mirrors the projection boundary: never consult an owner/editor bypass, because a
 * federated representation must not widen with the current user's login state.
 *
 * @param int $attachment_id Attachment.
 * @return bool
 */
function axismundi_media_federation_renditions_allowed( int $attachment_id ) : bool {
	if ( ! function_exists( 'axismundi_media_is_independent' ) || ! axismundi_media_is_independent() ) {
		return false;
	}
	$tier = axismundi_media_effective_visibility( $attachment_id );
	return in_array( $tier, array( 'public', 'unlisted' ), true )
		&& 0 === (int) axismundi_media_locked_gate_for_attachment( $attachment_id );
}

/**
 * Byte size of one generated subsize, or 0 when it cannot be established.
 *
 * @param int                 $attachment_id Attachment.
 * @param array<string,mixed> $info          One `sizes` entry.
 * @return int
 */
function axismundi_media_federation_rendition_bytes( int $attachment_id, array $info ) : int {
	if ( ! empty( $info['filesize'] ) ) {
		return (int) $info['filesize'];
	}
	if ( empty( $info['file'] ) ) {
		return 0;
	}
	$path = dirname( (string) get_attached_file( $attachment_id ) ) . '/' . $info['file'];
	return file_exists( $path ) ? (int) filesize( $path ) : 0;
}

/**
 * Build one advertisable rendition, or null when it fails any structural rule.
 *
 * @param int                 $attachment_id Attachment.
 * @param string              $size          Registered size name.
 * @param array<string,mixed> $info          One `sizes` entry.
 * @param array<string,mixed> $policy        Resolved policy.
 * @return array{url:string,mediaType:string,width:int,height:int,size:int}|null
 */
function axismundi_media_federation_rendition_entry( int $attachment_id, string $size, array $info, array $policy ) : ?array {
	$src = wp_get_attachment_image_src( $attachment_id, $size );
	// $src[3] is core's is-intermediate flag. False means core fell back to the full file,
	// so treating it as a rendition would advertise the original.
	if ( ! $src || empty( $src[0] ) || empty( $src[3] ) ) {
		return null;
	}
	$width  = (int) $src[1];
	$height = (int) $src[2];
	if ( $width <= 0 || $height <= 0 ) {
		return null;
	}
	$bytes = axismundi_media_federation_rendition_bytes( $attachment_id, $info );
	if ( $bytes <= 0 ) {
		return null;
	}
	if ( $width > (int) $policy['max_dimension'] || $height > (int) $policy['max_dimension'] ) {
		return null;
	}
	if ( $width * $height > (int) $policy['max_pixels'] || $bytes > (int) $policy['max_bytes'] ) {
		return null;
	}
	return array(
		'url'       => (string) $src[0],
		'mediaType' => ! empty( $info['mime-type'] ) ? (string) $info['mime-type'] : (string) get_post_mime_type( $attachment_id ),
		'width'     => $width,
		'height'    => $height,
		'size'      => $bytes,
	);
}

/**
 * Every already-generated derivative this attachment may advertise, largest first.
 *
 * Returns an empty array — never the original — when nothing qualifies. A caller with no
 * renditions emits only its human HTML Link (MEDIA-RENDITIONS.md §3.3).
 *
 * Images only in this increment: there is no transcoding substrate, so multiple versions
 * are never invented for video/audio/documents (§6).
 *
 * @param int                 $attachment_id Attachment.
 * @param array<string,mixed> $policy        Optional policy overrides.
 * @return array<int,array{url:string,mediaType:string,width:int,height:int,size:int}>
 */
function axismundi_media_federation_renditions( int $attachment_id, array $policy = array() ) : array {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
		return array();
	}
	if ( ! axismundi_media_federation_renditions_allowed( $attachment_id ) ) {
		return array();
	}
	if ( ! str_starts_with( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) {
		return array();
	}
	$meta = wp_get_attachment_metadata( $attachment_id );
	if ( empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
		return array();
	}

	$policy     = axismundi_media_federation_rendition_policy( $policy );
	$registered = get_intermediate_image_sizes();
	$entries    = array();
	foreach ( (array) $policy['sizes'] as $size ) {
		$size = (string) $size;
		if ( ! in_array( $size, $registered, true ) || empty( $meta['sizes'][ $size ] ) || ! is_array( $meta['sizes'][ $size ] ) ) {
			continue;
		}
		$entry = axismundi_media_federation_rendition_entry( $attachment_id, $size, $meta['sizes'][ $size ], $policy );
		if ( null !== $entry ) {
			$entries[] = $entry;
		}
	}

	$seen   = array();
	$unique = array();
	foreach ( $entries as $entry ) {
		$key = $entry['url'] . '|' . $entry['width'] . 'x' . $entry['height'];
		if ( isset( $seen[ $entry['url'] ] ) || isset( $seen[ $entry['width'] . 'x' . $entry['height'] ] ) ) {
			continue;
		}
		$seen[ $entry['url'] ]                              = true;
		$seen[ $entry['width'] . 'x' . $entry['height'] ]   = true;
		unset( $key );
		$unique[] = $entry;
	}
	usort( $unique, static fn( array $a, array $b ) : int => ( $b['width'] * $b['height'] ) <=> ( $a['width'] * $a['height'] ) );
	return array_slice( $unique, 0, max( 1, (int) $policy['max'] ) );
}
