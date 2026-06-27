<?php
/**
 * Place-type vocabulary for the ax_geo_place_type term meta.
 *
 * ax_geo_place_type is a single slug string on a geo_area / geotag term, NOT a
 * taxonomy — "what kind of place is this". This file holds the controlled
 * vocabulary that backs the term editor's <select> (grouped by category, no free
 * text — keeps the values clean for icons / filters) and a slug -> label lookup
 * for display. Slugs follow Google Places
 * naming style where an equivalent exists, with Korea-specific types added; an
 * exact Google Places type mapping belongs in the future Places adapter.
 *
 * geo_area types are administrative divisions; geotag types are facility / venue
 * kinds, grouped for maintenance and editor readability.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * Suggested place types for a taxonomy, grouped by category.
 *
 * @param string $taxonomy 'geo_area' or 'geotag'.
 * @return array<string,array<string,string>> group label => ( slug => label )
 */
function axismundi_geodata_place_types( string $taxonomy ) : array {
	if ( 'geo_area' === $taxonomy ) {
		// Abstract, slimmed international roles grouped by tier — deliberately wide so
		// many countries map on (광역시/都/直辖市 → province, etc.). The exact national
		// class is carried by the term name + ax_geo_country_code / ax_geo_iso_3166_2,
		// NOT by a slug. Tier comes from the geo_area parent hierarchy.
		// See docs/geodata-standards.md. Perceptual / statistical / transport areas
		// and road-name addresses are deliberately out of this vocabulary.
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

	return array(
		__( 'Automotive', 'axismundi-geodata' ) => array(
			'car_rental'                        => __( 'Car rental', 'axismundi-geodata' ),
			'car_repair'                        => __( 'Car repair', 'axismundi-geodata' ),
			'car_wash'                          => __( 'Car wash', 'axismundi-geodata' ),
			'electric_vehicle_charging_station' => __( 'EV charging station', 'axismundi-geodata' ),
			'gas_station'                       => __( 'Gas station', 'axismundi-geodata' ),
			'rest_stop'                         => __( 'Rest stop', 'axismundi-geodata' ),
		),
		__( 'Business', 'axismundi-geodata' ) => array(
			'business_center'   => __( 'Business center', 'axismundi-geodata' ),
			'corporate_office'  => __( 'Corporate office', 'axismundi-geodata' ),
			'coworking_space'   => __( 'Coworking space', 'axismundi-geodata' ),
			'farm'              => __( 'Farm', 'axismundi-geodata' ),
			'television_studio' => __( 'Television studio', 'axismundi-geodata' ),
		),
		__( 'Culture & attraction', 'axismundi-geodata' ) => array(
			'museum'                 => __( 'Museum', 'axismundi-geodata' ),
			'history_museum'         => __( 'History museum', 'axismundi-geodata' ),
			'art_museum'             => __( 'Art museum', 'axismundi-geodata' ),
			'science_museum'         => __( 'Science museum', 'axismundi-geodata' ),
			'natural_history_museum' => __( 'Natural history museum', 'axismundi-geodata' ),
			'military_museum'        => __( 'Military museum', 'axismundi-geodata' ),
			'technology_museum'      => __( 'Technology museum', 'axismundi-geodata' ),
			'music_museum'           => __( 'Music museum', 'axismundi-geodata' ),
			'virtual_museum'         => __( 'Virtual museum', 'axismundi-geodata' ),
			'art_gallery'            => __( 'Art gallery', 'axismundi-geodata' ),
			'historical_landmark'    => __( 'Historical landmark', 'axismundi-geodata' ),
			'temple'                 => __( 'Temple', 'axismundi-geodata' ),
			'amusement_park'         => __( 'Amusement park', 'axismundi-geodata' ),
			'cultural_complex'       => __( 'Cultural complex', 'axismundi-geodata' ),
			'experience_village'     => __( 'Experience village', 'axismundi-geodata' ),
			'specialty_street'       => __( 'Specialty street', 'axismundi-geodata' ),
		),
		__( 'Education', 'axismundi-geodata' ) => array(
			'library'                 => __( 'Library', 'axismundi-geodata' ),
			'educational_institution' => __( 'Educational institution', 'axismundi-geodata' ),
			'research_institute'      => __( 'Research institute', 'axismundi-geodata' ),
			'school'                  => __( 'School', 'axismundi-geodata' ),
			'university'              => __( 'University', 'axismundi-geodata' ),
		),
		__( 'Garden, park & nature', 'axismundi-geodata' ) => array(
			'garden'                => __( 'Garden', 'axismundi-geodata' ),
			'botanical_garden'      => __( 'Botanical garden', 'axismundi-geodata' ),
			'japanese_garden'       => __( 'Japanese garden', 'axismundi-geodata' ),
			'herb_garden'           => __( 'Herb garden', 'axismundi-geodata' ),
			'arboretum'             => __( 'Arboretum', 'axismundi-geodata' ),
			'zoo'                   => __( 'Zoo', 'axismundi-geodata' ),
			'aquarium'              => __( 'Aquarium', 'axismundi-geodata' ),
			'city_park'             => __( 'City park', 'axismundi-geodata' ),
			'historic_park'         => __( 'Historic park', 'axismundi-geodata' ),
			'cultural_park'         => __( 'Cultural park', 'axismundi-geodata' ),
			'waterfront_park'       => __( 'Waterfront park', 'axismundi-geodata' ),
			'sports_park'           => __( 'Sports park', 'axismundi-geodata' ),
			'cemetery_park'         => __( 'Cemetery park', 'axismundi-geodata' ),
			'urban_agriculture_park' => __( 'Urban agriculture park', 'axismundi-geodata' ),
			'ecological_park'       => __( 'Ecological park', 'axismundi-geodata' ),
			'natural_park'          => __( 'Natural park', 'axismundi-geodata' ),
			'national_park'         => __( 'National park', 'axismundi-geodata' ),
			'municipal_park'        => __( 'Municipal park', 'axismundi-geodata' ),
			'geopark'               => __( 'Geopark', 'axismundi-geodata' ),
			'small_park'            => __( 'Small park', 'axismundi-geodata' ),
			'children_park'         => __( 'Children’s park', 'axismundi-geodata' ),
			'neighborhood_park'     => __( 'Neighborhood park', 'axismundi-geodata' ),
		),
		__( 'Natural features', 'axismundi-geodata' ) => array(
			'island'          => __( 'Island', 'axismundi-geodata' ),
			'lake'            => __( 'Lake', 'axismundi-geodata' ),
			'mountain_peak'   => __( 'Mountain peak', 'axismundi-geodata' ),
			'natural_feature' => __( 'Natural feature', 'axismundi-geodata' ),
			'river'           => __( 'River', 'axismundi-geodata' ),
		),
		__( 'Accommodation', 'axismundi-geodata' ) => array(
			'resort_hotel'       => __( 'Resort hotel', 'axismundi-geodata' ),
			'hotel'              => __( 'Hotel', 'axismundi-geodata' ),
			'condominium'        => __( 'Condominium', 'axismundi-geodata' ),
			'serviced_residence' => __( 'Serviced residence', 'axismundi-geodata' ),
			'motel'              => __( 'Motel', 'axismundi-geodata' ),
			'inn'                => __( 'Inn', 'axismundi-geodata' ),
			'hostel'             => __( 'Hostel', 'axismundi-geodata' ),
			'guest_house'        => __( 'Guest house', 'axismundi-geodata' ),
			'homestay'           => __( 'Homestay', 'axismundi-geodata' ),
			'minbak'             => __( 'Minbak', 'axismundi-geodata' ),
			'pension'            => __( 'Pension', 'axismundi-geodata' ),
			'cabin'              => __( 'Cabin', 'axismundi-geodata' ),
			'bungalow'           => __( 'Bungalow', 'axismundi-geodata' ),
			'campground'         => __( 'Campground', 'axismundi-geodata' ),
			'share_house'        => __( 'Share house', 'axismundi-geodata' ),
			'japanese_inn'       => __( 'Japanese inn', 'axismundi-geodata' ),
		),
		__( 'Food & drink', 'axismundi-geodata' ) => array(
			'restaurant'               => __( 'Restaurant', 'axismundi-geodata' ),
			'korean_restaurant'        => __( 'Korean restaurant', 'axismundi-geodata' ),
			'international_restaurant'  => __( 'International restaurant', 'axismundi-geodata' ),
			'bakery'                   => __( 'Bakery', 'axismundi-geodata' ),
			'cafe'                     => __( 'Cafe', 'axismundi-geodata' ),
			'tea_house'                => __( 'Tea house', 'axismundi-geodata' ),
			'chicken_restaurant'       => __( 'Chicken restaurant', 'axismundi-geodata' ),
			'pizza_restaurant'         => __( 'Pizza restaurant', 'axismundi-geodata' ),
			'hamburger_restaurant'     => __( 'Hamburger restaurant', 'axismundi-geodata' ),
			'sandwich_shop'            => __( 'Sandwich shop', 'axismundi-geodata' ),
			'snack_bar'                => __( 'Snack bar', 'axismundi-geodata' ),
			'ice_cream_shop'           => __( 'Ice cream shop', 'axismundi-geodata' ),
			'makgeolli_bar'            => __( 'Makgeolli bar', 'axismundi-geodata' ),
			'local_pub'                => __( 'Local pub', 'axismundi-geodata' ),
		),
		__( 'Government', 'axismundi-geodata' ) => array(
			'city_hall'       => __( 'City hall', 'axismundi-geodata' ),
			'courthouse'      => __( 'Courthouse', 'axismundi-geodata' ),
			'embassy'         => __( 'Embassy', 'axismundi-geodata' ),
			'fire_station'    => __( 'Fire station', 'axismundi-geodata' ),
			'police'          => __( 'Police', 'axismundi-geodata' ),
			'post_office'     => __( 'Post office', 'axismundi-geodata' ),
			'public_office'   => __( 'Public office', 'axismundi-geodata' ),
			'visitor_center'  => __( 'Visitor center', 'axismundi-geodata' ),
		),
		__( 'Places of worship', 'axismundi-geodata' ) => array(
			'place_of_worship' => __( 'Place of worship', 'axismundi-geodata' ),
			'buddhist_temple'  => __( 'Buddhist temple', 'axismundi-geodata' ),
			'church'           => __( 'Church', 'axismundi-geodata' ),
			'hindu_temple'     => __( 'Hindu temple', 'axismundi-geodata' ),
			'mosque'           => __( 'Mosque', 'axismundi-geodata' ),
			'synagogue'        => __( 'Synagogue', 'axismundi-geodata' ),
		),
		__( 'Retail', 'axismundi-geodata' ) => array(
			'department_store'      => __( 'Department store', 'axismundi-geodata' ),
			'supermarket'           => __( 'Supermarket', 'axismundi-geodata' ),
			'outlet'                => __( 'Outlet', 'axismundi-geodata' ),
			'duty_free_shop'        => __( 'Duty-free shop', 'axismundi-geodata' ),
			'convenience_store'     => __( 'Convenience store', 'axismundi-geodata' ),
			'drugstore'             => __( 'Drugstore', 'axismundi-geodata' ),
			'general_store'         => __( 'General store', 'axismundi-geodata' ),
			'stationery_store'      => __( 'Stationery store', 'axismundi-geodata' ),
			'household_goods_store' => __( 'Household goods store', 'axismundi-geodata' ),
			'home_decor_store'      => __( 'Home decor store', 'axismundi-geodata' ),
			'party_supply_store'    => __( 'Party supply store', 'axismundi-geodata' ),
			'goods_shop'            => __( 'Goods shop', 'axismundi-geodata' ),
			'flagship_store'        => __( 'Flagship store', 'axismundi-geodata' ),
			'traditional_market'    => __( 'Traditional market', 'axismundi-geodata' ),
		),
		__( 'Entertainment', 'axismundi-geodata' ) => array(
			'entertainment_venue' => __( 'Entertainment venue', 'axismundi-geodata' ),
			'pc_bang'             => __( 'PC bang', 'axismundi-geodata' ),
			'karaoke'             => __( 'Karaoke', 'axismundi-geodata' ),
			'video_arcade'        => __( 'Video arcade', 'axismundi-geodata' ),
			'performance_venue'   => __( 'Performance venue', 'axismundi-geodata' ),
			'movie_theater'       => __( 'Movie theater', 'axismundi-geodata' ),
			'shooting_range'      => __( 'Shooting range', 'axismundi-geodata' ),
		),
		__( 'Marine leisure', 'axismundi-geodata' ) => array(
			'beach'        => __( 'Beach', 'axismundi-geodata' ),
			'fishing_spot' => __( 'Fishing spot', 'axismundi-geodata' ),
			'marina'       => __( 'Marina', 'axismundi-geodata' ),
			'yacht'        => __( 'Yacht', 'axismundi-geodata' ),
			'scuba_diving' => __( 'Scuba diving', 'axismundi-geodata' ),
		),
		__( 'Sports', 'axismundi-geodata' ) => array(
			'golf_course'        => __( 'Golf course', 'axismundi-geodata' ),
			'ski_resort'         => __( 'Ski resort', 'axismundi-geodata' ),
			'swimming_pool'      => __( 'Swimming pool', 'axismundi-geodata' ),
			'billiard_hall'      => __( 'Billiard hall', 'axismundi-geodata' ),
			'bowling_alley'      => __( 'Bowling alley', 'axismundi-geodata' ),
			'golf_driving_range' => __( 'Golf driving range', 'axismundi-geodata' ),
		),
		__( 'Beauty & wellness', 'axismundi-geodata' ) => array(
			'hair_salon' => __( 'Hair salon', 'axismundi-geodata' ),
			'hot_spring' => __( 'Hot spring', 'axismundi-geodata' ),
			'sauna'      => __( 'Sauna', 'axismundi-geodata' ),
			'spa'        => __( 'Spa', 'axismundi-geodata' ),
			'jjimjilbang' => __( 'Jjimjilbang', 'axismundi-geodata' ),
			'massage'    => __( 'Massage', 'axismundi-geodata' ),
			'nail_salon' => __( 'Nail salon', 'axismundi-geodata' ),
		),
		__( 'Services & public', 'axismundi-geodata' ) => array(
			'photo_studio'         => __( 'Photo studio', 'axismundi-geodata' ),
			'bank'                 => __( 'Bank', 'axismundi-geodata' ),
			'atm'                  => __( 'ATM', 'axismundi-geodata' ),
			'hospital'             => __( 'Hospital', 'axismundi-geodata' ),
			'clinic'               => __( 'Clinic', 'axismundi-geodata' ),
			'pharmacy'             => __( 'Pharmacy', 'axismundi-geodata' ),
			'emergency_room'       => __( 'Emergency room', 'axismundi-geodata' ),
			'neighborhood_facility' => __( 'Neighborhood facility', 'axismundi-geodata' ),
		),
		__( 'Transportation', 'axismundi-geodata' ) => array(
			'station'         => __( 'Station', 'axismundi-geodata' ),
			'bus_station'     => __( 'Bus station', 'axismundi-geodata' ),
			'ferry_terminal'  => __( 'Ferry terminal', 'axismundi-geodata' ),
			'airport'         => __( 'Airport', 'axismundi-geodata' ),
			'parking'         => __( 'Parking', 'axismundi-geodata' ),
			'bicycle_rental'  => __( 'Bicycle rental', 'axismundi-geodata' ),
		),
	);
}

/**
 * A grouped <select> of the place types for a taxonomy.
 *
 * Categories render as <optgroup>s. A leading blank option allows "no type", and
 * a stored value outside the vocabulary (legacy / imported) is kept as its own
 * selected option so editing a term never silently drops it.
 *
 * @param string $taxonomy Taxonomy being edited.
 * @param string $name     Field name / id.
 * @param string $value    Currently stored slug.
 * @return string Escaped <select> markup.
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
			$options .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), $is_selected, esc_html( $label ) );
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
	foreach ( array( 'geo_area', 'geotag' ) as $taxonomy ) {
		foreach ( axismundi_geodata_place_types( $taxonomy ) as $types ) {
			if ( isset( $types[ $slug ] ) ) {
				return $types[ $slug ];
			}
		}
	}

	return $slug;
}
