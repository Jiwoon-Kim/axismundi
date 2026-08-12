<?php
/**
 * The holiday behind the rows: one subject, its years, and its names.
 *
 * Two calendars hold 설날 and Lunar New Year. They are not translations of each other in any sense a
 * program can act on -- the feeds they came from share no identity, and neither is authoritative for
 * the other. What relates them is a third thing that neither contains: the holiday itself.
 *
 * Wikipedia's model, for its actual reason. A sitelink does not claim two pages say the same thing;
 * it claims they are about the same subject, and leaves both editable by the people who maintain
 * them. Here that means an English correction never overwrites a Korean one, and re-importing either
 * feed cannot disturb the other.
 *
 * Three layers, because they answer different questions:
 *
 *   concept     which holiday this is            설날, across every year and language
 *   occurrence  which day of it, in which year   16, 17, 18 February 2026
 *   item        what it is called, in one feed   설날 연휴 / Lunar New Year Holiday
 *
 * The three days of 설날 are one concept with three occurrences rather than three concepts. They are
 * one holiday that lasts three days, and splitting them would make "when is 설날" unanswerable and
 * leave the substitute-day relation pointing at whichever fragment happened to hold the name.
 *
 * Classification lives on the concept. That is what the linking is for: 설날 is a public holiday
 * once, not once per language and again every year.
 *
 * Nothing here merges anything automatically. The feeds have no shared identity, dates move between
 * years, and several holidays can share a date -- so a link is a judgement somebody makes while
 * reviewing, and this file only records it.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * What one day of a holiday is to the holiday.
 *
 *   principal       the day the holiday is
 *   holiday-period  a day off around it, as the 16th and 18th are to 설날 on the 17th
 *   substitute      a day observed instead, because the holiday fell on a weekend
 */
const AXISMUNDI_CAL_OCCURRENCE_ROLES = array( 'principal', 'holiday-period', 'substitute' );

/**
 * Create or update a holiday concept.
 *
 * The key is opaque and generated. A readable one is a name, and a name in one language is the thing
 * this layer exists to stop being the identity -- `kr.seollal` would be an English-alphabet spelling
 * of a Korean word standing in for a subject that has names in neither.
 *
 * @param array<string,mixed> $fields     jurisdiction, label, categories.
 * @param int                 $concept_id Existing concept, or 0.
 * @return int|WP_Error
 */
function axismundi_cal_holiday_concept_save( array $fields, int $concept_id = 0 ) {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return new WP_Error( 'ax_cal_store', __( 'The calendar store is unavailable.', 'axismundi-calendar' ) );
	}
	$existing = $concept_id > 0 ? axismundi_cal_holiday_concept_get( $concept_id ) : null;

	$jurisdiction = strtoupper( trim( (string) ( $fields['jurisdiction'] ?? ( $existing['jurisdiction'] ?? '' ) ) ) );
	if ( 1 !== preg_match( '/^[A-Z]{2}$/', $jurisdiction ) ) {
		return new WP_Error( 'ax_cal_concept_jurisdiction', __( 'A holiday belongs to a country or region.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	/*
	 * A label for the people maintaining this, not an identity. It is whatever the reviewer finds
	 * legible -- often the name in the site's own language -- and changing it moves nothing.
	 */
	$label = trim( (string) ( $fields['label'] ?? ( $existing['label'] ?? '' ) ) );
	if ( '' === $label ) {
		return new WP_Error( 'ax_cal_concept_label', __( 'A holiday needs a name to be recognised by.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$categories = axismundi_cal_normalize_categories( $fields['categories'] ?? ( $existing['categories'] ?? array() ) );

	$now  = current_time( 'mysql', true );
	$data = array(
		'jurisdiction' => $jurisdiction,
		'label'        => $label,
		'categories'   => implode( ',', $categories ),
		'updated_at'   => $now,
	);
	$table = axismundi_cal_holiday_concepts_table();
	if ( is_array( $existing ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ) );
		return (int) $existing['id'];
	}
	$data['uuid']       = wp_generate_uuid4();
	$data['created_at'] = $now;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	if ( false === $wpdb->insert( $table, $data ) ) {
		return new WP_Error( 'ax_cal_concept_write', __( 'The holiday could not be saved.', 'axismundi-calendar' ) );
	}
	return (int) $wpdb->insert_id;
}

/**
 * One concept.
 *
 * @param int $concept_id Concept id.
 * @return array<string,mixed>|null
 */
function axismundi_cal_holiday_concept_get( int $concept_id ) : ?array {
	global $wpdb;
	if ( $concept_id <= 0 || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_holiday_concepts_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $concept_id ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * Every concept for one jurisdiction.
 *
 * @param string $jurisdiction Two-letter code.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_holiday_concepts( string $jurisdiction ) : array {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_holiday_concepts_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE jurisdiction = %s ORDER BY label ASC", strtoupper( $jurisdiction ) ), ARRAY_A );
}

/**
 * Record one day of a holiday in one year.
 *
 * @param int                 $concept_id    Concept id.
 * @param array<string,mixed> $fields        start_date, end_date, batch_year, role, substitute_for.
 * @param int                 $occurrence_id Existing occurrence, or 0.
 * @return int|WP_Error
 */
function axismundi_cal_holiday_occurrence_save( int $concept_id, array $fields, int $occurrence_id = 0 ) {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return new WP_Error( 'ax_cal_store', __( 'The calendar store is unavailable.', 'axismundi-calendar' ) );
	}
	if ( null === axismundi_cal_holiday_concept_get( $concept_id ) ) {
		return new WP_Error( 'ax_cal_concept_missing', __( 'That holiday does not exist.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	$existing = $occurrence_id > 0 ? axismundi_cal_holiday_occurrence_get( $occurrence_id ) : null;

	$start = axismundi_cal_civil_date( (string) ( $fields['start_date'] ?? ( $existing['start_date'] ?? '' ) ) );
	if ( '' === $start ) {
		return new WP_Error( 'ax_cal_occurrence_date', __( 'A day of a holiday needs a date.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$end = axismundi_cal_civil_date( (string) ( $fields['end_date'] ?? ( $existing['end_date'] ?? '' ) ) );
	if ( '' === $end ) {
		$end = gmdate( 'Y-m-d', (int) strtotime( $start . ' +1 day' ) );
	}
	$role = (string) ( $fields['role'] ?? ( $existing['role'] ?? 'principal' ) );
	if ( ! in_array( $role, AXISMUNDI_CAL_OCCURRENCE_ROLES, true ) ) {
		return new WP_Error( 'ax_cal_occurrence_role', __( 'That is not something a day of a holiday can be.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	/*
	 * A substitute stands in for a particular day, so it says which. Recorded as a relation between
	 * occurrences rather than as a flag, because "observed instead of the 1st" and "observed instead
	 * of something" are different amounts of information, and a screen showing the first needs it.
	 */
	$substitute_for = (int) ( $fields['substitute_for'] ?? ( $existing['substitute_for'] ?? 0 ) );
	if ( 'substitute' !== $role ) {
		$substitute_for = 0;
	}
	$batch_year = (int) ( $fields['batch_year'] ?? ( $existing['batch_year'] ?? 0 ) );
	if ( $batch_year <= 0 ) {
		$batch_year = (int) substr( $start, 0, 4 );
	}

	$now  = current_time( 'mysql', true );
	$data = array(
		'concept_id'     => $concept_id,
		'start_date'     => $start,
		'end_date'       => $end,
		'batch_year'     => $batch_year,
		'role'           => $role,
		'substitute_for' => $substitute_for,
		'updated_at'     => $now,
	);
	$table = axismundi_cal_holiday_occurrences_table();
	if ( is_array( $existing ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ) );
		return (int) $existing['id'];
	}
	$data['uuid']       = wp_generate_uuid4();
	$data['created_at'] = $now;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	if ( false === $wpdb->insert( $table, $data ) ) {
		return new WP_Error( 'ax_cal_occurrence_write', __( 'That day could not be saved.', 'axismundi-calendar' ) );
	}
	return (int) $wpdb->insert_id;
}

/**
 * One occurrence.
 *
 * @param int $occurrence_id Occurrence id.
 * @return array<string,mixed>|null
 */
function axismundi_cal_holiday_occurrence_get( int $occurrence_id ) : ?array {
	global $wpdb;
	if ( $occurrence_id <= 0 || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_holiday_occurrences_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $occurrence_id ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * Every day of one holiday, optionally in one year.
 *
 * @param int $concept_id Concept id.
 * @param int $year       Batch year, or 0 for all.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_holiday_occurrences( int $concept_id, int $year = 0 ) : array {
	global $wpdb;
	if ( $concept_id <= 0 || ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_holiday_occurrences_table();
	if ( $year > 0 ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE concept_id = %d AND batch_year = %d ORDER BY start_date ASC", $concept_id, $year ), ARRAY_A );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE concept_id = %d ORDER BY start_date ASC", $concept_id ), ARRAY_A );
}

/**
 * Say that one feed's row is about one day of one holiday.
 *
 * The sitelink. Recorded rather than inferred, because nothing in the two rows could establish it:
 * the feeds share no identity, and matching by date is a proposal rather than a fact -- 설날 occupies
 * three dates, and a year with two holidays on one day would pair the wrong two.
 *
 * @param int $item_id       System item id.
 * @param int $occurrence_id Occurrence id, or 0 to unlink.
 * @return bool|WP_Error
 */
function axismundi_cal_link_item_to_occurrence( int $item_id, int $occurrence_id ) {
	global $wpdb;
	$item = axismundi_cal_system_item_get( $item_id );
	if ( ! is_array( $item ) ) {
		return new WP_Error( 'ax_cal_item_missing', __( 'That entry does not exist.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	if ( $occurrence_id > 0 && null === axismundi_cal_holiday_occurrence_get( $occurrence_id ) ) {
		return new WP_Error( 'ax_cal_occurrence_missing', __( 'That day of a holiday does not exist.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update( axismundi_cal_system_items_table(), array( 'holiday_occurrence_id' => $occurrence_id ), array( 'id' => $item_id ) );
	return true;
}

/**
 * The rows in every language that are about one day of a holiday.
 *
 * @param int $occurrence_id Occurrence id.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_occurrence_items( int $occurrence_id ) : array {
	global $wpdb;
	if ( $occurrence_id <= 0 || ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_system_items_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE holiday_occurrence_id = %d ORDER BY id ASC", $occurrence_id ), ARRAY_A );
}

/**
 * The categories an entry actually has.
 *
 * Its own, or its holiday's when it has none. This is what the linking is for: 설날 is a public
 * holiday once, rather than once per language and again every year. An entry that carries its own
 * keys keeps them, so a single day of a holiday can be classified differently -- an election day
 * that is also a holiday, a period day that is not.
 *
 * @param array<string,mixed> $item System item row.
 * @return string[]
 */
function axismundi_cal_item_effective_categories( array $item ) : array {
	$own = axismundi_cal_normalize_categories( (string) ( $item['categories'] ?? '' ) );
	if ( array() !== $own ) {
		return $own;
	}
	$occurrence = axismundi_cal_holiday_occurrence_get( (int) ( $item['holiday_occurrence_id'] ?? 0 ) );
	if ( ! is_array( $occurrence ) ) {
		return array();
	}
	$concept = axismundi_cal_holiday_concept_get( (int) $occurrence['concept_id'] );
	return is_array( $concept ) ? axismundi_cal_normalize_categories( (string) $concept['categories'] ) : array();
}

/**
 * Occurrences that could be what an unlinked entry is about.
 *
 * A proposal, never a merge. Same jurisdiction, same date -- which is usually one answer and
 * sometimes several, and is exactly the case a person has to settle.
 *
 * @param array<string,mixed> $item         System item row.
 * @param string              $jurisdiction Two-letter code.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_occurrence_candidates( array $item, string $jurisdiction ) : array {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return array();
	}
	$occurrences = axismundi_cal_holiday_occurrences_table();
	$concepts    = axismundi_cal_holiday_concepts_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own tables.
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT o.*, c.label, c.categories FROM {$occurrences} o INNER JOIN {$concepts} c ON c.id = o.concept_id
			 WHERE c.jurisdiction = %s AND o.start_date = %s ORDER BY o.id ASC",
			strtoupper( $jurisdiction ),
			(string) $item['start_date']
		),
		ARRAY_A
	);
}
