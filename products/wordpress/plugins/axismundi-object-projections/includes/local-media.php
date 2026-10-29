<?php
/** Media reference identity, independent of source editing permission. */
defined( 'ABSPATH' ) || exit;

function axismundi_op_media_reference_url( string $url ) : string {
	if ( str_starts_with( $url, '//' ) ) { $url = wp_parse_url( home_url(), PHP_URL_SCHEME ) . ':' . $url; }
	return axismundi_op_relation_uri( $url );
}

/** Classify known document identities locally, without dereferencing external URLs. */
function axismundi_op_media_url_is_object( string $url ) : bool {
	if ( axismundi_op_get_local_object( $url ) || axismundi_op_get_remote_object( $url, false ) ) { return true; }
	$parts = wp_parse_url( $url );
	$home = wp_parse_url( home_url() );
	$port = static fn( array $value ) : int => (int) ( $value['port'] ?? ( 'https' === ( $value['scheme'] ?? '' ) ? 443 : 80 ) );
	if ( ! is_array( $parts ) || ! is_array( $home ) || strtolower( $parts['host'] ?? '' ) !== strtolower( $home['host'] ?? '' ) || $port( $parts ) !== $port( $home ) ) { return false; }
	$local_prefix = trailingslashit( $home['path'] ?? '' ) . 'objects/';
	if ( str_starts_with( $parts['path'] ?? '', $local_prefix ) ) {
		$uuid = substr( $parts['path'], strlen( $local_prefix ) );
		if ( preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $uuid ) && axismundi_op_get_local_object( axismundi_op_local_object_uri( $uuid ) ) ) { return true; }
	}
	$uploads = wp_parse_url( wp_get_upload_dir()['baseurl'] );
	// A file query is not a WP query. Keep upload references, including renditions, usable.
	if ( is_array( $uploads ) && strtolower( $parts['host'] ?? '' ) === strtolower( $uploads['host'] ?? '' ) && $port( $parts ) === $port( $uploads ) && str_starts_with( $parts['path'] ?? '', trailingslashit( $uploads['path'] ?? '' ) ) ) { return false; }
	$query = array();
	parse_str( $parts['query'] ?? '', $query );
	foreach ( array( 'p', 'attachment_id', 'page_id' ) as $key ) {
		if ( isset( $query[ $key ] ) && is_scalar( $query[ $key ] ) && ctype_digit( (string) $query[ $key ] ) && get_post( (int) $query[ $key ] ) instanceof WP_Post ) { return true; }
	}
	$id = url_to_postid( $url );
	return $id > 0 && get_post( $id ) instanceof WP_Post;
}

/** Resolve known local originals/renditions without fetching or guessing ownership. */
function axismundi_op_local_attachment_id( string $url ) : int {
	global $wpdb;
	$url = axismundi_op_media_reference_url( $url );
	if ( '' === $url ) { return 0; }
	$parts = wp_parse_url( $url );
	$uploads = wp_get_upload_dir();
	$base = wp_parse_url( $uploads['baseurl'] );
	$hosts = (array) apply_filters( 'axismundi_media_federation_virtual_hosts', array( 'i0.wp.com', 'i1.wp.com', 'i2.wp.com' ) );
	// Photon preserves the origin host/path inside its path; queries describe transforms.
	if ( in_array( strtolower( $parts['host'] ?? '' ), array_map( 'strtolower', $hosts ), true ) && 'https' === ( $parts['scheme'] ?? '' ) ) {
		$origin = wp_parse_url( ( $base['scheme'] ?? 'https' ) . '://' . ltrim( $parts['path'] ?? '', '/' ) );
		if ( is_array( $origin ) ) { $parts = $origin; }
	}
	if ( strtolower( $parts['host'] ?? '' ) !== strtolower( $base['host'] ?? '' ) || (int) ( $parts['port'] ?? 0 ) !== (int) ( $base['port'] ?? 0 ) ) { return 0; }
	$path = rawurldecode( $parts['path'] ?? '' );
	$prefix = trailingslashit( rawurldecode( $base['path'] ?? '' ) );
	if ( ! str_starts_with( $path, $prefix ) ) { return 0; }
	$relative = substr( $path, strlen( $prefix ) );
	if ( '' === $relative || in_array( '..', explode( '/', $relative ), true ) ) { return 0; }
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wp_attached_file' AND meta_value=%s LIMIT 1", $relative ) );
	if ( $id && 'attachment' === get_post_type( $id ) ) { return $id; }
	// Size filenames can be custom or derived from a -scaled original. Verify exact metadata.
	$file = wp_basename( $relative );
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wp_attachment_metadata' AND meta_value LIKE %s", '%"' . $wpdb->esc_like( $file ) . '"%' ) );
	foreach ( $ids as $candidate ) {
		if ( 'attachment' !== get_post_type( $candidate ) ) { continue; }
		$meta = wp_get_attachment_metadata( (int) $candidate );
		if ( ! is_array( $meta ) || empty( $meta['file'] ) ) { continue; }
		$directory = dirname( $meta['file'] );
		$directory = '.' === $directory ? '' : $directory . '/';
		$files = array( $meta['file'] );
		if ( ! empty( $meta['original_image'] ) ) { $files[] = $directory . $meta['original_image']; }
		foreach ( $meta['sizes'] ?? array() as $size ) { if ( ! empty( $size['file'] ) ) { $files[] = $directory . $size['file']; } }
		if ( in_array( $relative, $files, true ) ) { return (int) $candidate; }
	}
	return 0;
}
