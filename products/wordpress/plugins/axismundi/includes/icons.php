<?php
/**
 * Register Axismundi's curated Icon Registry collection from its asset manifest.
 *
 * The manifest is the catalogue source of truth: it records both the stable public identifier a
 * consumer asks for and provenance that WordPress's runtime registry does not model. The registry
 * receives only the data it needs to render an icon lazily from its SVG file.
 *
 * @package Axismundi
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ICON_LIBRARY_COLLECTION = 'axismundi';

/**
 * Read the local icon-library manifest.
 *
 * @return array<string,mixed>|null Valid manifest, or null when it cannot safely register.
 */
function axismundi_icon_library_manifest(): ?array {
	$path = __DIR__ . '/images/icon-library/manifest.json';
	if ( ! is_readable( $path ) ) {
		return null;
	}

	$manifest = json_decode( (string) file_get_contents( $path ), true );
	if (
		! is_array( $manifest ) ||
		! isset( $manifest['collection']['slug'], $manifest['icons'] ) ||
		AXISMUNDI_ICON_LIBRARY_COLLECTION !== $manifest['collection']['slug'] ||
		! is_array( $manifest['icons'] )
	) {
		return null;
	}

	return $manifest;
}

/**
 * Register only this plugin's small, curated collection.
 *
 * @return void
 */
function axismundi_register_icon_library(): void {
	if ( ! function_exists( 'wp_register_icon_collection' ) || ! function_exists( 'wp_register_icon' ) ) {
		return;
	}

	$manifest = axismundi_icon_library_manifest();
	if ( null === $manifest ) {
		return;
	}

	$collections = WP_Icon_Collections_Registry::get_instance();
	if ( $collections->is_registered( AXISMUNDI_ICON_LIBRARY_COLLECTION ) ) {
		// Do not add icons to a collection another plugin registered under this name.
		return;
	}

	$collection = $manifest['collection'];
	wp_register_icon_collection(
		AXISMUNDI_ICON_LIBRARY_COLLECTION,
		array(
			'label'       => (string) ( $collection['label'] ?? 'Axismundi' ),
			'description' => (string) ( $collection['description'] ?? '' ),
		)
	);

	$directory = __DIR__ . '/images/icon-library/';
	foreach ( $manifest['icons'] as $name => $icon ) {
		if (
			! is_string( $name ) ||
			! preg_match( '/^[a-z0-9](?:[a-z0-9_-]*[a-z0-9])?$/', $name ) ||
			! is_array( $icon ) ||
			empty( $icon['label'] ) ||
			empty( $icon['file'] )
		) {
			continue;
		}

		$file = (string) $icon['file'];
		if ( basename( $file ) !== $file || ! str_ends_with( $file, '.svg' ) || ! is_readable( $directory . $file ) ) {
			continue;
		}

		wp_register_icon(
			AXISMUNDI_ICON_LIBRARY_COLLECTION . '/' . $name,
			array(
				'label'     => (string) $icon['label'],
				'file_path' => $directory . $file,
			)
		);
	}
}
add_action( 'init', 'axismundi_register_icon_library' );
