<?php
/**
 * Controlled place-type vocabulary for ax_geo_place_type term meta.
 *
 * geo_area uses a compact international administrative vocabulary below.
 * geotag records are generated from data/geotag-place-types.tsv; edit that TSV
 * and run `powershell -File tools/generate-place-types.ps1` rather than editing
 * the generated PHP file.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * Generated geotag place-type records.
 *
 * @return array<int,array{division:string,group:string,slug:string,label:string,source:string,google_fallback:string}>
 */
function axismundi_geodata_geotag_place_type_records() : array {
	static $records = null;

	if ( null === $records ) {
		$loaded  = require __DIR__ . '/place-types.generated.php';
		$records = is_array( $loaded ) ? $loaded : array();
	}

	return $records;
}

/**
 * Look up one generated geotag place-type record.
 *
 * @param string $slug Place-type slug.
 * @return array<string,string>|null
 */
function axismundi_geodata_geotag_place_type_record( string $slug ) : ?array {
	static $by_slug = null;

	if ( null === $by_slug ) {
		$by_slug = array();
		foreach ( axismundi_geodata_geotag_place_type_records() as $record ) {
			$by_slug[ $record['slug'] ] = $record;
		}
	}

	return $by_slug[ $slug ] ?? null;
}

/**
 * Suggested place types for a taxonomy, grouped for a native select.
 *
 * @param string $taxonomy geo_area or geotag.
 * @return array<string,array<string,string>> group label => ( slug => label )
 */
function axismundi_geodata_place_types( string $taxonomy ) : array {
	if ( 'axismundi_geo_area' === $taxonomy ) {
		return array(
			__( 'National', 'axismundi-geodata' ) => array(
				'country' => __( 'Country', 'axismundi-geodata' ),
			),
			__( 'First-order area', 'axismundi-geodata' ) => array(
				'province' => __( 'Province / first-order area', 'axismundi-geodata' ),
				'state'    => __( 'State', 'axismundi-geodata' ),
			),
			__( 'Second-order area', 'axismundi-geodata' ) => array(
				'city'     => __( 'City', 'axismundi-geodata' ),
				'county'   => __( 'County', 'axismundi-geodata' ),
				'district' => __( 'District', 'axismundi-geodata' ),
			),
			__( 'Local area', 'axismundi-geodata' ) => array(
				'town'         => __( 'Town', 'axismundi-geodata' ),
				'township'     => __( 'Township', 'axismundi-geodata' ),
				'village'      => __( 'Village', 'axismundi-geodata' ),
				'sublocality'  => __( 'Sublocality', 'axismundi-geodata' ),
				'neighborhood' => __( 'Neighborhood', 'axismundi-geodata' ),
			),
		);
	}

	$groups = array();
	foreach ( axismundi_geodata_geotag_place_type_records() as $record ) {
		$groups[ $record['group'] ][ $record['slug'] ] = $record['label'];
	}

	return $groups;
}

/**
 * Return the vocabulary source for a geotag place type.
 *
 * @param string $slug Place-type slug.
 * @return string google, custom, or an empty string for an unknown type.
 */
function axismundi_geodata_place_type_source( string $slug ) : string {
	$record = axismundi_geodata_geotag_place_type_record( $slug );

	return $record['source'] ?? '';
}

/**
 * Return the Google Places fallback for a local geotag type.
 *
 * Google-aligned records return their own slug. Unknown values return unchanged
 * so imported provider values remain usable as best-effort hints.
 *
 * @param string $slug Place-type slug.
 * @return string
 */
function axismundi_geodata_google_fallback_type( string $slug ) : string {
	$record = axismundi_geodata_geotag_place_type_record( $slug );

	return $record['google_fallback'] ?? $slug;
}

/**
 * A grouped select of the place types for a taxonomy.
 *
 * A stored value outside the current vocabulary is retained as its own selected
 * option, so editing an imported or legacy term never silently drops its value.
 * Local extensions are visibly marked; unmarked geotag options use Google Places
 * tokens directly.
 *
 * @param string $taxonomy Taxonomy being edited.
 * @param string $name     Field name and id.
 * @param string $value    Currently stored slug.
 * @return string Escaped select markup.
 */
function axismundi_geodata_place_type_select( string $taxonomy, string $name, string $value ) : string {
	$matched = '' === $value;
	$groups  = '';

	foreach ( axismundi_geodata_place_types( $taxonomy ) as $group_label => $types ) {
		$options = '';
		foreach ( $types as $slug => $label ) {
			$is_selected = selected( $value, $slug, false );
			if ( '' !== $is_selected ) {
				$matched = true;
			}

			$source        = 'axismundi_geotag' === $taxonomy ? axismundi_geodata_place_type_source( $slug ) : '';
			$display_label = 'custom' === $source
				? $label . ' ' . _x( '(Custom)', 'place-type select option suffix', 'axismundi-geodata' )
				: $label;
			$options      .= sprintf(
				'<option value="%1$s"%2$s%3$s>%4$s</option>',
				esc_attr( $slug ),
				$is_selected,
				'' !== $source ? ' data-source="' . esc_attr( $source ) . '"' : '',
				esc_html( $display_label )
			);
		}
		$groups .= sprintf( '<optgroup label="%s">%s</optgroup>', esc_attr( $group_label ), $options );
	}

	$html  = sprintf( '<select name="%1$s" id="%1$s">', esc_attr( $name ) );
	$html .= sprintf( '<option value="">%s</option>', esc_html__( '— Select —', 'axismundi-geodata' ) );
	if ( ! $matched ) {
		$html .= sprintf( '<option value="%1$s" selected>%1$s</option>', esc_attr( $value ) );
	}

	return $html . $groups . '</select>';
}

/**
 * Display label for a place-type slug, falling back to the slug itself.
 *
 * @param string $slug Stored place-type slug.
 * @return string
 */
function axismundi_geodata_place_type_label( string $slug ) : string {
	if ( '' === $slug ) {
		return '';
	}

	$geotag_record = axismundi_geodata_geotag_place_type_record( $slug );
	if ( null !== $geotag_record ) {
		return $geotag_record['label'];
	}

	foreach ( axismundi_geodata_place_types( 'axismundi_geo_area' ) as $types ) {
		if ( isset( $types[ $slug ] ) ) {
			return $types[ $slug ];
		}
	}

	return $slug;
}
