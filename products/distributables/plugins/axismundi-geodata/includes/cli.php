<?php
/**
 * WP-CLI demo seeder.
 *
 * Opt-in only: the plugin never auto-creates place data on activation (that would
 * push Korean demo terms onto every install). The demo hierarchy ships with the
 * plugin but is created only when an operator runs the command, and can be
 * removed again.
 *
 *   wp axismundi-geodata seed-demo
 *   wp axismundi-geodata seed-demo --remove
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	return;
}

/**
 * Axismundi Geodata demo commands.
 */
class Axismundi_Geodata_CLI {

	/**
	 * Seed (or remove) the demo place hierarchy:
	 * 대한민국 > 부산광역시 > 수영구 > 광안동 (geo_area) with 광안리해수욕장 (geotag).
	 *
	 * Idempotent — re-running refreshes the term meta without duplicating terms.
	 *
	 * ## OPTIONS
	 *
	 * [--remove]
	 * : Delete the demo terms instead of creating them.
	 *
	 * ## EXAMPLES
	 *
	 *     wp axismundi-geodata seed-demo
	 *     wp axismundi-geodata seed-demo --remove
	 *
	 * @subcommand seed-demo
	 *
	 * @param array $args       Positional args (unused).
	 * @param array $assoc_args Associative args.
	 * @return void
	 */
	public function seed_demo( $args, $assoc_args ) : void {
		$areas = array(
			array( 'name' => '대한민국', 'parent' => '', 'lat' => 36.0, 'lng' => 127.8, 'radius' => 300000, 'type' => 'country', 'address' => '대한민국' ),
			array( 'name' => '부산광역시', 'parent' => '대한민국', 'lat' => 35.1796, 'lng' => 129.0756, 'radius' => 20000, 'type' => 'city', 'address' => '대한민국 부산광역시' ),
			array( 'name' => '수영구', 'parent' => '부산광역시', 'lat' => 35.1455, 'lng' => 129.1132, 'radius' => 2500, 'type' => 'district', 'address' => '부산광역시 수영구' ),
			array( 'name' => '광안동', 'parent' => '수영구', 'lat' => 35.1530, 'lng' => 129.1130, 'radius' => 900, 'type' => 'neighborhood', 'address' => '부산광역시 수영구 광안동' ),
		);
		$geotag = array( 'name' => '광안리해수욕장', 'area' => '광안동', 'lat' => 35.1532, 'lng' => 129.1186, 'radius' => 300, 'type' => 'beach', 'address' => '부산광역시 수영구 광안해변로 219' );

		if ( ! empty( $assoc_args['remove'] ) ) {
			$gt = get_term_by( 'name', $geotag['name'], 'geotag' );
			if ( $gt ) {
				wp_delete_term( $gt->term_id, 'geotag' );
			}
			foreach ( array_reverse( $areas ) as $area ) {
				$term = get_term_by( 'name', $area['name'], 'geo_area' );
				if ( $term ) {
					wp_delete_term( $term->term_id, 'geo_area' );
				}
			}
			WP_CLI::success( 'Removed Axismundi Geodata demo terms.' );
			return;
		}

		$ids = array();
		foreach ( $areas as $area ) {
			$parent_id = '' === $area['parent'] ? 0 : ( $ids[ $area['parent'] ] ?? 0 );
			$term_id   = $this->upsert_term( $area['name'], 'geo_area', $parent_id );
			if ( ! $term_id ) {
				continue;
			}
			$ids[ $area['name'] ] = $term_id;
			$this->set_place_meta( $term_id, $area );
		}

		$gt_id = $this->upsert_term( $geotag['name'], 'geotag', 0 );
		if ( $gt_id ) {
			$this->set_place_meta( $gt_id, $geotag );
			if ( ! empty( $ids[ $geotag['area'] ] ) ) {
				update_term_meta( $gt_id, 'ax_geo_area', $ids[ $geotag['area'] ] );
			}
		}

		WP_CLI::success( 'Seeded Axismundi Geodata demo: 4 geo areas + 1 geotag (Gwangalli Beach).' );
	}

	/**
	 * Find a term by name (within parent for geo_area) or create it.
	 *
	 * @param string $name      Term name.
	 * @param string $taxonomy  Taxonomy.
	 * @param int    $parent_id Parent term id (geo_area only).
	 * @return int Term id, or 0 on failure.
	 */
	private function upsert_term( string $name, string $taxonomy, int $parent_id ) : int {
		$existing = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'name'       => $name,
				'parent'     => $parent_id,
				'hide_empty' => false,
			)
		);
		if ( ! is_wp_error( $existing ) && ! empty( $existing ) ) {
			return (int) $existing[0]->term_id;
		}

		$res = wp_insert_term( $name, $taxonomy, $parent_id ? array( 'parent' => $parent_id ) : array() );
		if ( is_wp_error( $res ) ) {
			WP_CLI::warning( $taxonomy . ': ' . $res->get_error_message() );
			return 0;
		}
		return (int) $res['term_id'];
	}

	/**
	 * Write the place-fact meta for a demo term.
	 *
	 * @param int   $term_id Term id.
	 * @param array $place   Place data row.
	 * @return void
	 */
	private function set_place_meta( int $term_id, array $place ) : void {
		update_term_meta( $term_id, 'ax_geo_latitude', $place['lat'] );
		update_term_meta( $term_id, 'ax_geo_longitude', $place['lng'] );
		update_term_meta( $term_id, 'ax_geo_radius', $place['radius'] );
		update_term_meta( $term_id, 'ax_geo_place_type', $place['type'] );
		update_term_meta( $term_id, 'ax_geo_address', $place['address'] );
		update_term_meta( $term_id, 'ax_geo_source', 'demo' );
	}
}

WP_CLI::add_command( 'axismundi-geodata', 'Axismundi_Geodata_CLI' );
