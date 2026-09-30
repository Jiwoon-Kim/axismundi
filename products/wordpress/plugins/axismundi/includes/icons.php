<?php
/**
 * Register Axismundi's curated Icon Registry collection from its asset manifest.
 *
 * The runtime registration follows Core's `images/icon-library` plus `assets/icon-library-manifest`
 * pattern. The manifest preserves Core's `label` and `filePath` contract, and carries Axismundi
 * catalogue enrichment that registration deliberately ignores.
 *
 * @package Axismundi
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ICON_LIBRARY_COLLECTION = 'axismundi';

/**
 * Read the local runtime icon-library manifest.
 *
 * @return array<string,mixed>|null Valid manifest, or null when it cannot safely register.
 */
function axismundi_icon_library_manifest(): ?array {
	$path = __DIR__ . '/assets/icon-library-manifest.php';
	if ( ! is_readable( $path ) ) {
		wp_trigger_error( __FUNCTION__, __( 'Axismundi icon collection manifest is missing or unreadable.', 'axismundi' ) );
		return null;
	}

	$manifest = include $path;
	if ( empty( $manifest ) || ! is_array( $manifest ) ) {
		wp_trigger_error( __FUNCTION__, __( 'Axismundi icon collection manifest is empty or invalid.', 'axismundi' ) );
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

	wp_register_icon_collection(
		AXISMUNDI_ICON_LIBRARY_COLLECTION,
		array(
			'label'       => __( 'Axismundi', 'axismundi' ),
			'description' => __( 'Curated visual resources used by Axismundi applications.', 'axismundi' ),
		)
	);

	$directory = __DIR__ . '/images/icon-library/';
	foreach ( $manifest as $name => $icon ) {
		if (
			! is_string( $name ) ||
			! preg_match( '/^[a-z0-9](?:[a-z0-9_-]*[a-z0-9])?$/', $name ) ||
			! is_array( $icon ) ||
			empty( $icon['label'] ) ||
			empty( $icon['filePath'] ) ||
			! is_string( $icon['filePath'] )
		) {
			_doing_it_wrong(
				__FUNCTION__,
				__( 'Axismundi icon collection manifest must provide a valid label and "filePath" for each icon.', 'axismundi' ),
				AXISMUNDI_CAPSTONE_VERSION
			);
			return;
		}

		$file = $icon['filePath'];
		if ( basename( $file ) !== $file || ! str_ends_with( $file, '.svg' ) || ! is_readable( $directory . $file ) ) {
			_doing_it_wrong(
				__FUNCTION__,
				__( 'Axismundi icon collection manifest must reference a readable SVG inside its icon-library directory.', 'axismundi' ),
				AXISMUNDI_CAPSTONE_VERSION
			);
			return;
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
