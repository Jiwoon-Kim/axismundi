<?php
/**
 * The arithmetic every astronomical generator shares.
 *
 * Julian Day does not leave this file. It counts from noon, it is a float, and both of those are
 * ways for a date to move half a day at the far end of an unrelated function. `jde_to_utc()` is the
 * boundary: above it everything is Dynamical Time in days counted from a noon epoch, below it
 * everything is a UTC timestamp string, and nothing in between is exposed.
 *
 * Separated from the moon because none of it is about the moon. The phases, the equinoxes and the
 * solstices are different series over the same time scale, and a second copy of the ΔT estimate or
 * of the calendar conversion would be a second place for a date to come out half a day wrong.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sine of an angle given in degrees.
 *
 * @param float $degrees Angle.
 * @return float
 */
function axismundi_cal_sin_deg( float $degrees ) : float {
	return sin( deg2rad( $degrees ) );
}

/**
 * Cosine of an angle given in degrees.
 *
 * @param float $degrees Angle.
 * @return float
 */
function axismundi_cal_cos_deg( float $degrees ) : float {
	return cos( deg2rad( $degrees ) );
}

/**
 * The difference between Terrestrial Time and UT, in seconds.
 *
 * Meeus computes phases in Dynamical Time, which is uniform. Civil time is not: the Earth's rotation
 * is irregular, so the gap between them is measured rather than derived and can only be predicted
 * forward. This is the Espenak-Meeus polynomial for 2005-2050, and it is a prediction -- around 74
 * seconds for 2025 against an observed value nearer 69, because the Earth did not slow as the fit
 * expected.
 *
 * Kept anyway, and kept visible. Five seconds is far below anything a phase is displayed to, and the
 * alternative is a table that would need updating from IERS bulletins forever to buy nothing.
 *
 * @param float $year Decimal year.
 * @return float Seconds.
 */
function axismundi_cal_delta_t( float $year ) : float {
	$t = $year - 2000.0;
	return 62.92 + ( 0.32217 * $t ) + ( 0.005589 * $t * $t );
}

/**
 * A Julian Day as a UTC timestamp string.
 *
 * The boundary. Everything above counts days from noon in Dynamical Time; everything below is a
 * civil timestamp, and nothing between the two is exposed.
 *
 * @param float $jde Julian Ephemeris Day, Dynamical Time.
 * @return string `Y-m-d H:i:s` UTC.
 */
function axismundi_cal_jde_to_utc( float $jde ) : string {
	/*
	 * The year is needed before the conversion in order to know how far Dynamical Time has drifted
	 * from civil time, and it is only wanted to a fraction of a year, so it is taken from the Julian
	 * Day directly rather than by converting twice.
	 */
	$approx_year = 2000.0 + ( ( $jde - 2451545.0 ) / 365.25 );
	$jd          = $jde - ( axismundi_cal_delta_t( $approx_year ) / 86400.0 );

	// Meeus chapter 7, inverted. The half-day is the noon epoch leaving.
	$z = (int) floor( $jd + 0.5 );
	$f = ( $jd + 0.5 ) - $z;

	$a = $z;
	if ( $z >= 2299161 ) {
		$alpha = (int) floor( ( $z - 1867216.25 ) / 36524.25 );
		$a     = $z + 1 + $alpha - (int) floor( $alpha / 4 );
	}
	$b = $a + 1524;
	$c = (int) floor( ( $b - 122.1 ) / 365.25 );
	$d = (int) floor( 365.25 * $c );
	$e = (int) floor( ( $b - $d ) / 30.6001 );

	$day_fraction = $b - $d - (int) floor( 30.6001 * $e ) + $f;
	$day          = (int) floor( $day_fraction );
	$month        = $e < 14 ? $e - 1 : $e - 13;
	$year         = $month > 2 ? $c - 4716 : $c - 4715;

	$seconds = (int) round( ( $day_fraction - $day ) * 86400 );
	/*
	 * Rounding can land exactly on the next midnight, and a timestamp of 24:00:00 is not one. Handed
	 * to `gmmktime` as an overflow rather than clamped, so the date rolls with it.
	 */
	return gmdate( 'Y-m-d H:i:s', (int) gmmktime( 0, 0, $seconds, $month, $day, $year ) );
}
