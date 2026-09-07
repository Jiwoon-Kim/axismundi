<?php
/**
 * When the sun crosses the four points that divide the year.
 *
 * All four are one fact stated four times: the apparent geocentric longitude of the sun reaching
 * 0°, 90°, 180° and 270°. That definition would be implemented as a solar longitude engine and a
 * root search, which is the right shape for a plugin that also wants perihelion, aphelion or the
 * twenty-four 절기 -- each of those is the same search against a different target.
 *
 * This is Meeus chapter 27 instead: a polynomial for the mean instant of each point, corrected by a
 * periodic series. It answers only these four questions and answers them directly, without iterating
 * a solar position it would first have to build. The moment a fifth question arrives -- and 절기 is
 * the likely one -- the engine is worth building and this file becomes its four special cases.
 *
 * Named by what the sun does, not by the season it starts. The March equinox begins spring north of
 * the equator and autumn south of it, so a key naming the season is false for half of any feed's
 * readers; the idiomatic name is settled per language instead, where that flavour belongs.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The four points, in the order the sun reaches them.
 *
 * Keyed by the quarter turn of solar longitude each corresponds to, which is what makes the index
 * below an integer and therefore a usable identity.
 */
const AXISMUNDI_CAL_SEASON_POINTS = array(
	0 => 'NORTHWARD-EQUINOX',
	1 => 'NORTHERN-SOLSTICE',
	2 => 'SOUTHWARD-EQUINOX',
	3 => 'SOUTHERN-SOLSTICE',
);

/** Which parent key each point filters under. */
const AXISMUNDI_CAL_SEASON_PARENTS = array(
	'NORTHWARD-EQUINOX' => 'EQUINOX',
	'NORTHERN-SOLSTICE' => 'SOLSTICE',
	'SOUTHWARD-EQUINOX' => 'EQUINOX',
	'SOUTHERN-SOLSTICE' => 'SOLSTICE',
);

/**
 * The Julian Ephemeris Day of one seasonal point, in Dynamical Time.
 *
 * Two polynomials, because Meeus fits the mean instant separately either side of the year 1000 --
 * the same curve extended would drift by days at the far end. Only the later one can be reached from
 * anything this plugin materializes, and the earlier is carried so that computing a distant year
 * returns an answer rather than a confidently wrong one.
 *
 * @param int $year  Calendar year.
 * @param int $point Index into `AXISMUNDI_CAL_SEASON_POINTS`.
 * @return float
 */
function axismundi_cal_season_jde( int $year, int $point ) : float {
	$point = ( ( $point % 4 ) + 4 ) % 4;

	if ( $year >= 1000 ) {
		$y    = ( $year - 2000 ) / 1000;
		$mean = array(
			array( 2451623.80984, 365242.37404, 0.05169, -0.00411, -0.00057 ),
			array( 2451716.56767, 365241.62603, 0.00325, 0.00888, -0.00030 ),
			array( 2451810.21715, 365242.01767, -0.11575, 0.00337, 0.00078 ),
			array( 2451900.05952, 365242.74049, -0.06223, -0.00823, 0.00032 ),
		);
	} else {
		$y    = $year / 1000;
		$mean = array(
			array( 1721139.29189, 365242.13740, 0.06134, 0.00111, -0.00071 ),
			array( 1721233.25401, 365241.72562, -0.05323, 0.00907, 0.00025 ),
			array( 1721325.70455, 365242.49558, -0.11677, -0.00297, 0.00074 ),
			array( 1721414.39987, 365242.88257, -0.00769, -0.00933, -0.00006 ),
		);
	}

	$c    = $mean[ $point ];
	$jde0 = $c[0] + ( $c[1] * $y ) + ( $c[2] * $y * $y ) + ( $c[3] * $y * $y * $y ) + ( $c[4] * $y * $y * $y * $y );

	$t = ( $jde0 - 2451545.0 ) / 36525;

	/*
	 * Twenty-four periodic terms, which between them are worth about twenty minutes. The mean instant
	 * alone is a plausible-looking date that is a third of a day out, and nothing downstream could tell.
	 */
	$terms = array(
		array( 485, 324.96, 1934.136 ),
		array( 203, 337.23, 32964.467 ),
		array( 199, 342.08, 20.186 ),
		array( 182, 27.85, 445267.112 ),
		array( 156, 73.14, 45036.886 ),
		array( 136, 171.52, 22518.443 ),
		array( 77, 222.54, 65928.934 ),
		array( 74, 296.72, 3034.906 ),
		array( 70, 243.58, 9037.513 ),
		array( 58, 119.81, 33718.147 ),
		array( 52, 297.17, 150.678 ),
		array( 50, 21.02, 2281.226 ),
		array( 45, 247.54, 29929.562 ),
		array( 44, 325.15, 31555.956 ),
		array( 29, 60.93, 4443.417 ),
		array( 18, 155.12, 67555.328 ),
		array( 17, 288.79, 4562.452 ),
		array( 16, 198.04, 62894.029 ),
		array( 14, 199.76, 31436.921 ),
		array( 12, 95.39, 14577.848 ),
		array( 12, 287.11, 31931.756 ),
		array( 12, 320.81, 34777.259 ),
		array( 9, 227.73, 1222.114 ),
		array( 8, 15.45, 16859.074 ),
	);
	$s = 0.0;
	foreach ( $terms as $term ) {
		$s += $term[0] * axismundi_cal_cos_deg( $term[1] + ( $term[2] * $t ) );
	}

	/*
	 * The correction is divided by the sun's apparent speed at the moment in question rather than
	 * applied flat: the same displacement in longitude takes longer to cover near aphelion than near
	 * perihelion, which is worth a few minutes across the year.
	 */
	$w      = ( 35999.373 * $t ) - 2.47;
	$lambda = 1 + ( 0.0334 * axismundi_cal_cos_deg( $w ) ) + ( 0.0007 * axismundi_cal_cos_deg( 2 * $w ) );

	return $jde0 + ( ( 0.00001 * $s ) / $lambda );
}

/**
 * The four seasonal points of one year, in UTC.
 *
 * Exactly four, always, and each one lands in its own quarter of the year -- which is what makes
 * this simpler than the lunations, where a year holds twelve or thirteen depending on where its
 * boundaries fall.
 *
 * @param int $year Calendar year, UTC.
 * @return array<int,array{phase:string,parent:string,start_utc:string,index:int}>
 */
function axismundi_cal_seasons_in_year( int $year ) : array {
	$out = array();
	foreach ( AXISMUNDI_CAL_SEASON_POINTS as $point => $key ) {
		$out[] = array(
			'phase'     => $key,
			'parent'    => AXISMUNDI_CAL_SEASON_PARENTS[ $key ],
			'start_utc' => axismundi_cal_jde_to_utc( axismundi_cal_season_jde( $year, (int) $point ) ),
			/*
			 * Year and quarter, which is unique across all of them and stable against the answer being
			 * corrected. A revised ΔT moves the instant by seconds; the March equinox of 2027 is still the
			 * March equinox of 2027, and a subscriber should get an update rather than a second entry.
			 */
			'index'     => ( $year * 4 ) + (int) $point,
		);
	}
	return $out;
}

/**
 * The window these rows are kept for.
 *
 * Whole calendar years, unlike the phases. Two reasons, and neither is storage -- a century of these
 * is four hundred rows.
 *
 * They are asked for by year. Somebody looks up the equinox of 2027, the way nobody looks up the
 * full moons of 2027, so a window that began in May would leave the maintenance screen showing a
 * partial year and no way to see the rest of it.
 *
 * And there are four a year, so a rolling window would spend most of its length holding nothing. The
 * phases arrive weekly and their window is sized to how far the feed reaches; these arrive quarterly
 * and their window is sized to how people address them.
 *
 * Last year is kept because the feed reaches three months back, which in January is last year, and
 * because comparing one year against the last is the ordinary thing to do with them. The far end
 * covers the feed exactly: on the last day of a year, `+2` still reaches the two years the
 * subscription window asks for.
 *
 * @return array{0:string,1:string} First and last civil date, as `Y-m-d`.
 */
function axismundi_cal_season_window() : array {
	$year = (int) gmdate( 'Y' );
	return array( sprintf( '%04d-01-01', $year - 1 ), sprintf( '%04d-12-31', $year + 2 ) );
}

/**
 * Bring one calendar's seasonal points into line with the window.
 *
 * The same shape as the phases, and deliberately the same window: both are materialized views for
 * the current grid and the current subscription rather than archives, and two datasets on one
 * calendar reaching different distances would be a calendar that is complete for one of them.
 *
 * @param int $calendar_id Calendar id.
 * @return array{created:int,deleted:int}|WP_Error
 */
function axismundi_cal_maintain_seasons( int $calendar_id ) {
	global $wpdb;
	$calendar = axismundi_cal_calendar_get( $calendar_id );
	if ( ! is_array( $calendar ) || 'astronomy' !== axismundi_cal_system_provider( $calendar ) ) {
		return new WP_Error( 'ax_cal_season_provider', __( 'Equinoxes and solstices belong to an astronomy calendar.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	list( $from, $to ) = axismundi_cal_season_window();

	$created = 0;
	for ( $year = (int) substr( $from, 0, 4 ); $year <= (int) substr( $to, 0, 4 ); $year++ ) {
		foreach ( axismundi_cal_seasons_in_year( $year ) as $season ) {
			$date = substr( (string) $season['start_utc'], 0, 10 );
			if ( $date < $from || $date > $to ) {
				continue;
			}
			$saved = axismundi_cal_system_item_save(
				$calendar_id,
				array(
					'temporal_kind' => AXISMUNDI_CAL_TEMPORAL_INSTANT,
					'start_utc'     => $season['start_utc'],
					// No title: the point key names it, in whatever language it is read in.
					'categories'    => array( $season['parent'], $season['phase'] ),
					'batch_year'    => $year,
					'source_uid'    => 'season-' . $season['index'],
					'status'        => 'published',
				)
			);
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			++$created;
		}
	}

	// Scoped by `source_uid`, so the phases sharing this calendar are not pruned by the seasons' window.
	$table = axismundi_cal_system_items_table();
	$like  = $wpdb->esc_like( 'season-' ) . '%';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- window maintenance over this plugin's own table.
	$deleted = (int) $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$table}
			 WHERE calendar_id = %d AND source_uid LIKE %s
			   AND ( start_utc < %s OR start_utc >= %s )",
			$calendar_id,
			$like,
			$from . ' 00:00:00',
			gmdate( 'Y-m-d 00:00:00', (int) strtotime( $to . ' +1 day' ) )
		)
	);

	return array( 'created' => $created, 'deleted' => $deleted );
}

/**
 * When this calendar's seasonal points next need attention.
 *
 * The first instant of the next year, and nothing else. A window made of whole calendar years moves
 * on one day a year, so unlike the phases -- whose edges are wherever the next lunation happens to
 * fall -- there is nothing here to compute from the data. Waking any more often than that would be
 * recomputing sixteen rows to find them unchanged.
 *
 * Returns 0 when nothing is stored, which means do it now: an empty calendar is to be filled rather
 * than waited on.
 *
 * @param int $calendar_id Calendar id.
 * @return int Timestamp, or 0 for immediately.
 */
function axismundi_cal_season_next_maintenance( int $calendar_id ) : int {
	global $wpdb;
	$table = axismundi_cal_system_items_table();
	$like  = $wpdb->esc_like( 'season-' ) . '%';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- scheduling lookup over this plugin's own table.
	$stored = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE calendar_id = %d AND source_uid LIKE %s", $calendar_id, $like )
	);
	if ( 0 === $stored ) {
		return 0;
	}
	return (int) strtotime( sprintf( '%04d-01-01 00:00:00 UTC', (int) gmdate( 'Y' ) + 1 ) );
}
