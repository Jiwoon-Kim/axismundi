<?php
/**
 * What kind of dataset a system calendar holds, and what that kind needs to know.
 *
 * The top-level classification turned out to be the wrong shape as a set of checkboxes. It is not a
 * label somebody applies for browsing -- it decides which writer fills the calendar, which settings
 * the screen asks for, and what its entries even mean in time. A calendar cannot be a bit of a
 * holiday feed and a bit of an astronomical one, so it is one choice, and the choice dispatches.
 *
 * The four differ far more than a category would suggest:
 *
 *   holiday    whole days, curated per region and per source language, reviewed a year at a time
 *   astronomy  computed from an instant in UTC, named by a translation rather than by stored text
 *   religious  whole days whose reckoning comes from a tradition rather than from the Gregorian year
 *   civic      dates a site records itself: elections, commemorations
 *   academic   periods rather than days, set per term by an institution
 *
 * `civic` and `CIVIC` have nothing to do with a civil date. A civil date is the time model -- the
 * 15th is the 15th in Seoul and in New York, which is what `all_day` means and what an instant is
 * not. `CIVIC` classifies what a date is *about*: public and civic occasions. The words collide in
 * English and nowhere else, so they are kept apart here rather than left to be read in context.
 *
 * Only `holiday` has a contract today and `astronomy` is the one being built. The rest are
 * declarable so a calendar can say what it is before there is a writer for it, which is what stops
 * the first of them from arriving as a special case bolted onto this one. Declaring one is not a
 * commitment to a writer, a screen or an endpoint for it.
 *
 * Nothing about a provider changes who may read a system calendar or who maintains it. That is the
 * part all of them share, and it is why they are one kind rather than five.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The kinds of dataset a system calendar can hold.
 *
 * One per calendar, chosen when it is made and fixed afterwards: changing it would change what its
 * existing entries mean, and there is no reading under which a moon phase was ever a public holiday.
 */
const AXISMUNDI_CAL_SYSTEM_PROVIDERS = array( 'holiday', 'astronomy', 'religious', 'civic', 'academic' );

/**
 * How each kind reads on screen.
 *
 * @param string $provider Provider key.
 * @return array{label:string,description:string}
 */
function axismundi_cal_system_provider_labels( string $provider ) : array {
	$labels = array(
		'holiday'   => array(
			'label'       => __( 'Public holidays', 'axismundi-calendar' ),
			'description' => __( 'Holidays and observances for one country or region, in one language. Reviewed a year at a time, because dates move.', 'axismundi-calendar' ),
		),
		'astronomy' => array(
			'label'       => __( 'Astronomy', 'axismundi-calendar' ),
			'description' => __( 'Moon phases, equinoxes and solstices. Computed rather than entered, and named in the language each reader uses.', 'axismundi-calendar' ),
		),
		'religious' => array(
			'label'       => __( 'Religious observances', 'axismundi-calendar' ),
			'description' => __( 'Dates from one tradition, which may follow its own reckoning rather than the civil calendar.', 'axismundi-calendar' ),
		),
		'civic'     => array(
			'label'       => __( 'Civic dates', 'axismundi-calendar' ),
			'description' => __( 'Elections, commemorations and other dates this site records itself.', 'axismundi-calendar' ),
		),
		'academic'  => array(
			'label'       => __( 'Academic calendar', 'axismundi-calendar' ),
			'description' => __( 'Terms, vacations and examination periods. Periods rather than single days.', 'axismundi-calendar' ),
		),
	);
	return $labels[ $provider ] ?? array( 'label' => $provider, 'description' => '' );
}

/**
 * Why a provider cannot be chosen on the creation form, or '' when it can.
 *
 * Two different reasons, and they are worth telling apart on screen rather than greying four rows
 * out identically. `astronomy` is refused because this plugin already maintains those calendars:
 * choosing it would produce a second, empty Moon phases beside the real one. The rest are refused
 * because nothing fills them yet, and a calendar that can be created but never populated is a
 * feature that looks finished and is not.
 *
 * Solar eclipses are the case that will reopen `astronomy` to a person. "There is an eclipse" is a
 * global instant like any other, but what somebody actually wants to know is whether it is visible
 * where they are -- so it needs the geodata plugin, and until that exists an astronomy calendar
 * remains something the site maintains rather than something anybody configures.
 *
 * @param string $provider Provider key.
 * @return string
 */
function axismundi_cal_system_provider_unavailable_reason( string $provider ) : string {
	if ( 'astronomy' === $provider ) {
		return __( 'Maintained by this plugin, and added for you. Creating one by hand would leave a second, empty copy beside it.', 'axismundi-calendar' );
	}
	if ( 'holiday' === $provider ) {
		return '';
	}
	return __( 'Not yet. There is no writer that fills this kind, so a calendar made now would stay empty.', 'axismundi-calendar' );
}

/**
 * The item categories a provider's entries are expected to carry.
 *
 * Offered rather than enforced: a holiday calendar mostly holds `PUBLIC-HOLIDAY` and `OBSERVANCE`,
 * and occasionally an election that is also a holiday. The editor uses this to put the likely keys
 * first without hiding the rest, since a taxonomy that refuses the unusual case is one somebody
 * works around by picking the wrong key.
 *
 * @param string $provider Provider key.
 * @return string[]
 */
function axismundi_cal_system_provider_categories( string $provider ) : array {
	/*
	 * Item keys only. Each provider's own top-level key is what its Calendar carries -- `RELIGIOUS` is
	 * what a Religious observances calendar is, not something an entry on it declares again -- so
	 * listing it here would offer somebody a box whose answer was settled when the Calendar was made.
	 */
	$map = array(
		'holiday'   => array( 'PUBLIC-HOLIDAY', 'OBSERVANCE', 'SUBSTITUTE-HOLIDAY' ),
		'astronomy' => array( 'MOON-PHASE', 'EQUINOX', 'NORTHWARD-EQUINOX', 'SOUTHWARD-EQUINOX', 'SOLSTICE', 'NORTHERN-SOLSTICE', 'SOUTHERN-SOLSTICE' ),
		// The tradition is the Calendar's, so what is left for an entry is what kind of day it is.
		'religious' => array( 'OBSERVANCE', 'PUBLIC-HOLIDAY' ),
		'civic'     => array( 'ELECTION', 'COMMEMORATION' ),
		'academic'  => array( 'TERM', 'VACATION', 'EXAM-PERIOD' ),
	);
	return $map[ $provider ] ?? array();
}

/**
 * The settings one kind of dataset needs.
 *
 * Only `holiday` answers today. An empty contract is the honest answer for the rest: they are
 * declarable so a calendar can say what it is before there is a writer for it, and a config screen
 * invented ahead of the writer would be guessing at what that writer will need.
 *
 * @param string $provider Provider key.
 * @return array<string,array{label:string,description:string,required:bool}>
 */
function axismundi_cal_system_provider_config_fields( string $provider ) : array {
	if ( 'holiday' !== $provider ) {
		return array();
	}
	return array(
		'region'        => array(
			'label'       => __( 'Country or region', 'axismundi-calendar' ),
			'description' => __( 'Two letters, as in KR or JP. What the dates are for, which is not the same as what language they are written in.', 'axismundi-calendar' ),
			'required'    => true,
		),
		'source_locale' => array(
			'label'       => __( 'Language', 'axismundi-calendar' ),
			'description' => __( 'The language these names are written in, as in ko-KR. One calendar holds one language: the same holiday published in two languages is two datasets, and merging them would need an identity neither of them carries.', 'axismundi-calendar' ),
			'required'    => true,
		),
	);
}

/**
 * Validate and normalize a provider's settings.
 *
 * Refused rather than corrected when a required setting is missing. A holiday calendar with no
 * region is a list of dates for nowhere, and the screens that will group these by country cannot
 * report what they cannot ask.
 *
 * @param string              $provider Provider key.
 * @param array<string,mixed> $config   Submitted settings.
 * @return array<string,string>|WP_Error
 */
function axismundi_cal_normalize_provider_config( string $provider, array $config ) {
	if ( 'holiday' !== $provider ) {
		// Nothing to keep for a provider with no contract, rather than storing whatever arrived.
		return array();
	}

	$region = strtoupper( trim( (string) ( $config['region'] ?? '' ) ) );
	if ( 1 !== preg_match( '/^[A-Z]{2}$/', $region ) ) {
		return new WP_Error( 'ax_cal_provider_region', __( 'A holiday calendar needs a two-letter country or region code.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$locale = trim( (string) ( $config['source_locale'] ?? '' ) );
	// A language tag, not a WordPress locale: `ko-KR` rather than `ko_KR`, since this is what the
	// dataset declares itself in and what a later translation link will be keyed on.
	$locale = str_replace( '_', '-', $locale );
	if ( 1 !== preg_match( '/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $locale ) ) {
		return new WP_Error( 'ax_cal_provider_locale', __( 'A holiday calendar needs the language its names are written in, such as ko-KR.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	return array( 'region' => $region, 'source_locale' => $locale );
}

/**
 * The stored settings of one system calendar.
 *
 * @param array<string,mixed>|null $calendar Calendar row.
 * @return array<string,string>
 */
function axismundi_cal_provider_config( ?array $calendar ) : array {
	if ( ! is_array( $calendar ) ) {
		return array();
	}
	$decoded = json_decode( (string) ( $calendar['provider_config'] ?? '' ), true );
	return is_array( $decoded ) ? array_map( 'strval', $decoded ) : array();
}

/**
 * One system calendar's provider, or '' when it is not one.
 *
 * @param array<string,mixed>|null $calendar Calendar row.
 * @return string
 */
function axismundi_cal_system_provider( ?array $calendar ) : string {
	if ( ! is_array( $calendar ) || 'system' !== (string) ( $calendar['kind'] ?? '' ) ) {
		return '';
	}
	$provider = (string) ( $calendar['system_provider'] ?? '' );
	return in_array( $provider, AXISMUNDI_CAL_SYSTEM_PROVIDERS, true ) ? $provider : '';
}

/**
 * How one system calendar describes itself in a catalog.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return string
 */
function axismundi_cal_system_provider_summary( array $calendar ) : string {
	$provider = axismundi_cal_system_provider( $calendar );
	if ( '' === $provider ) {
		return '';
	}
	$labels = axismundi_cal_system_provider_labels( $provider );
	$config = axismundi_cal_provider_config( $calendar );
	if ( 'holiday' === $provider && isset( $config['region'], $config['source_locale'] ) ) {
		/* translators: 1: provider name, 2: region code, 3: language tag. */
		return sprintf( __( '%1$s — %2$s, %3$s', 'axismundi-calendar' ), $labels['label'], $config['region'], $config['source_locale'] );
	}
	return $labels['label'];
}
