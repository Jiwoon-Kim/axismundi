<?php
/**
 * Calendar systems ICU already knows.
 *
 * These need no key, no quota, no network and no store. PHP's `intl` extension carries the
 * astronomical rules, so a Hebrew or Chinese date is a function call rather than a fetch, and the
 * settings screen shows no section for them because there is nothing to configure.
 *
 * Which is the whole argument for having made the registry a registry: one provider needs a service
 * key and a materialised store, and these need nothing, and the screen and the grid should not have
 * to know which is which.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * What an ICU calendar calls a civil day.
 *
 * Resolved at noon on purpose. ICU works in instants and these calendars roll their day at a
 * boundary that is not UTC midnight, so asking at midnight puts every answer within hours of the
 * wrong side; noon is the furthest a civil day gets from either edge.
 *
 * @param string $icu_calendar ICU calendar keyword, e.g. `hebrew`.
 * @param int    $absolute_day Absolute day.
 * @return array{year:int,month:int,day:int,leapMonth:bool}|null
 */
function axismundi_cal_icu_date( string $icu_calendar, int $absolute_day ) : ?array {
	static $calendars = array();
	if ( ! class_exists( 'IntlCalendar' ) ) {
		return null;
	}
	if ( ! isset( $calendars[ $icu_calendar ] ) ) {
		$instance = IntlCalendar::createInstance( 'UTC', 'en_US@calendar=' . $icu_calendar );
		if ( ! $instance instanceof IntlCalendar || $instance->getType() !== $icu_calendar ) {
			// A build without this calendar answers with the Gregorian one rather than refusing, and a
			// Gregorian date returned as a Hebrew one is worse than no second date at all.
			$calendars[ $icu_calendar ] = false;
		} else {
			$calendars[ $icu_calendar ] = $instance;
		}
	}
	if ( false === $calendars[ $icu_calendar ] ) {
		return null;
	}

	$date = axismundi_cal_absolute_day_to_date( $absolute_day );
	$from = IntlCalendar::createInstance( 'UTC', 'en_US@calendar=gregorian' );
	$from->clear();
	$from->set( $date['year'], $date['month'] - 1, $date['day'], 12, 0, 0 );

	$target = $calendars[ $icu_calendar ];
	$target->setTime( $from->getTime() );
	return array(
		/*
		 * The extended year, not `FIELD_YEAR`. For the East Asian calendars ICU puts the 60-year cycle
		 * in the era and numbers the year *within* it, so `FIELD_YEAR` for 2026 is 43 -- a real number
		 * about a real cycle, and nonsense to show as a year. The extended year is the one that counts
		 * straight through, and for Hebrew and Islamic it is the same value `FIELD_YEAR` gives.
		 */
		'year'  => (int) $target->get( IntlCalendar::FIELD_EXTENDED_YEAR ),
		'month' => (int) $target->get( IntlCalendar::FIELD_MONTH ) + 1,
		'day'   => (int) $target->get( IntlCalendar::FIELD_DAY_OF_MONTH ),
		/*
		 * Only the East Asian calendars set this. Hebrew marks its intercalated year by having a
		 * thirteenth month rather than by repeating one, so Adar I and Adar II are months 6 and 7 of a
		 * leap year and neither is flagged -- reporting one of them as a leap month would be inventing
		 * a distinction that calendar does not draw.
		 */
		'leapMonth' => (bool) $target->get( IntlCalendar::FIELD_IS_LEAP_MONTH ),
	);
}

/**
 * Register the calendars ICU can answer for on its own.
 *
 * @return void
 */
function axismundi_cal_register_icu_calendars() : void {
	if ( ! class_exists( 'IntlCalendar' ) ) {
		return;
	}
	$systems = array(
		array(
			/*
			 * `dangi` is ICU's identifier; `korean-lunisolar` is what the calendar is. Kept as the id
			 * so the two never get confused, and so a Korean authority provider can be registered
			 * beside this one later without either of them owning the name.
			 */
			'id'    => 'korean-lunisolar',
			'icu'   => 'dangi',
			'label' => __( 'Korean lunar calendar', 'axismundi-calendar' ),
			'type'  => 'lunisolar',
		),
		array(
			'id'    => 'hebrew',
			'icu'   => 'hebrew',
			'label' => __( 'Hebrew calendar', 'axismundi-calendar' ),
			'type'  => 'lunisolar',
		),
		array(
			'id'    => 'chinese',
			'icu'   => 'chinese',
			'label' => __( 'Chinese calendar', 'axismundi-calendar' ),
			'type'  => 'lunisolar',
		),
		array(
			// Umm al-Qura rather than plain `islamic`: it is the calendar Saudi Arabia publishes and
			// the one civil dates are actually printed against, where `islamic` is an arithmetic
			// approximation that drifts a day from it.
			'id'    => 'islamic-umalqura',
			'icu'   => 'islamic-umalqura',
			'label' => __( 'Islamic calendar (Umm al-Qura)', 'axismundi-calendar' ),
			// Lunar, not lunisolar. No month is intercalated to hold the seasons in place, which is
			// why Ramadan moves through them.
			'type'  => 'lunar',
		),
	);

	foreach ( $systems as $system ) {
		$icu = (string) $system['icu'];
		// Asked, not assumed. A build compiled without a calendar quietly returns the Gregorian one,
		// and registering a system whose every answer is a Gregorian date wearing another name would
		// put a plausible wrong date in front of somebody.
		if ( null === axismundi_cal_icu_date( $icu, axismundi_cal_absolute_day( 2026, 1, 1 ) ) ) {
			continue;
		}
		axismundi_cal_register_calendar_system(
			(string) $system['id'],
			array(
				'label'        => (string) $system['label'],
				'type'         => (string) $system['type'],
				'authority'    => sprintf(
					/* translators: %s: ICU version. */
					__( 'Unicode ICU %s, computed on this server', 'axismundi-calendar' ),
					defined( 'INTL_ICU_VERSION' ) ? INTL_ICU_VERSION : '?'
				),
				'icu_calendar' => $icu,
				/*
				 * No coverage bounds. ICU answers for any date it is given, and inventing a range here
				 * would be claiming knowledge of where its rules stop being historically accurate --
				 * which is a real question for every one of these, and not one this plugin has measured.
				 */
				'resolve'      => static fn( int $absolute_day ) : ?array => axismundi_cal_icu_date( $icu, $absolute_day ),
			)
		);
	}
}
add_action( 'axismundi_cal_register_calendar_systems', 'axismundi_cal_register_icu_calendars' );
