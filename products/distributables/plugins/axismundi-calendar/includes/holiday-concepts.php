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
 * What a catalog can claim to cover.
 *
 * A site publishing public holidays and a site publishing every commemoration are describing
 * different datasets. Without this they would join each other, and a reader who asked for the first
 * would be given the second.
 */
const AXISMUNDI_CAL_CATALOG_SCOPES = array( 'public-holidays', 'observances', 'public-holidays-and-observances' );

/**
 * Create or find the catalog a dataset belongs to.
 *
 * Identified opaquely and described by what it covers. Two calendars are siblings because they were
 * joined to one catalog, never because their settings happen to match -- `holiday + KR` describes a
 * great many datasets somebody might mean, and inferring identity from a description is how two
 * unrelated collections silently become one.
 *
 * @param array<string,mixed> $fields provider, jurisdiction, scope, label.
 * @return int|WP_Error
 */
function axismundi_cal_holiday_catalog_save( array $fields, int $catalog_id = 0 ) {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return new WP_Error( 'ax_cal_store', __( 'The calendar store is unavailable.', 'axismundi-calendar' ) );
	}
	$existing     = $catalog_id > 0 ? axismundi_cal_holiday_catalog_get( $catalog_id ) : null;
	$jurisdiction = strtoupper( trim( (string) ( $fields['jurisdiction'] ?? ( $existing['jurisdiction'] ?? '' ) ) ) );
	if ( 1 !== preg_match( '/^[A-Z]{2}$/', $jurisdiction ) ) {
		return new WP_Error( 'ax_cal_catalog_jurisdiction', __( 'A holiday catalog covers a country or region.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$scope = (string) ( $fields['scope'] ?? ( $existing['scope'] ?? 'public-holidays-and-observances' ) );
	if ( ! in_array( $scope, AXISMUNDI_CAL_CATALOG_SCOPES, true ) ) {
		return new WP_Error( 'ax_cal_catalog_scope', __( 'That is not something a catalog can cover.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$now  = current_time( 'mysql', true );
	$data = array(
		'provider'     => (string) ( $fields['provider'] ?? ( $existing['provider'] ?? 'holiday' ) ),
		'jurisdiction' => $jurisdiction,
		'scope'        => $scope,
		// A label for the people maintaining it, in whatever language they read. Not a name in any
		// particular one, and not what any calendar is called.
		'label'        => trim( (string) ( $fields['label'] ?? ( $existing['label'] ?? '' ) ) ),
		'updated_at'   => $now,
	);
	$table = axismundi_cal_holiday_catalogs_table();
	if ( is_array( $existing ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ) );
		return (int) $existing['id'];
	}
	$data['uuid']       = wp_generate_uuid4();
	$data['created_at'] = $now;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	if ( false === $wpdb->insert( $table, $data ) ) {
		return new WP_Error( 'ax_cal_catalog_write', __( 'The catalog could not be saved.', 'axismundi-calendar' ) );
	}
	return (int) $wpdb->insert_id;
}

/**
 * Keep cached label rows legible in admin tables. Read-side authorization still takes the occurrence
 * status, so this mirror is convenience rather than a second source of truth.
 *
 * @param int    $occurrence_id Occurrence id.
 * @param string $status        Canonical review state.
 * @return void
 */
function axismundi_cal_sync_occurrence_items( int $occurrence_id, string $status ) : void {
	global $wpdb;
	if ( $occurrence_id <= 0 || ! in_array( $status, AXISMUNDI_CAL_ITEM_STATUSES, true ) ) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- mirror of a canonical occurrence state in this plugin's own table.
	$wpdb->update( axismundi_cal_system_items_table(), array( 'status' => $status, 'categories' => '' ), array( 'holiday_occurrence_id' => $occurrence_id ) );
}

/**
 * One catalog.
 *
 * @param int $catalog_id Catalog id.
 * @return array<string,mixed>|null
 */
function axismundi_cal_holiday_catalog_get( int $catalog_id ) : ?array {
	global $wpdb;
	if ( $catalog_id <= 0 || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_holiday_catalogs_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $catalog_id ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * Catalogs that describe the same thing a calendar is being made for.
 *
 * Offered to a person, never applied. A match here means "somebody may have meant this one", which is
 * a question, and the answer is theirs -- the alternative is two collections of Korean holidays
 * merging because they were both about Korea.
 *
 * @param string $provider     Provider key.
 * @param string $jurisdiction Two-letter code.
 * @param string $scope        Scope, or '' for any.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_holiday_catalog_candidates( string $provider, string $jurisdiction, string $scope = '' ) : array {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_holiday_catalogs_table();
	if ( '' !== $scope ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE provider = %s AND jurisdiction = %s AND scope = %s ORDER BY id ASC", $provider, strtoupper( $jurisdiction ), $scope ), ARRAY_A );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE provider = %s AND jurisdiction = %s ORDER BY id ASC", $provider, strtoupper( $jurisdiction ) ), ARRAY_A );
}

/**
 * Say that one calendar is this catalog in one language.
 *
 * The catalog-level sitelink, and it is independent of the item-level ones. A calendar can be joined
 * and hold nothing -- which is the normal state of a language edition somebody has created and not
 * yet written, and must stay visibly empty rather than appearing translated.
 *
 * @param int $calendar_id Calendar id.
 * @param int $catalog_id  Catalog id, or 0 to detach.
 * @return bool|WP_Error
 */
function axismundi_cal_join_holiday_catalog( int $calendar_id, int $catalog_id ) {
	global $wpdb;
	$calendar = axismundi_cal_calendar_get( $calendar_id );
	if ( ! is_array( $calendar ) || 'holiday' !== axismundi_cal_system_provider( $calendar ) ) {
		return new WP_Error( 'ax_cal_not_holiday', __( 'Only a holiday calendar belongs to a holiday catalog.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( $catalog_id > 0 && null === axismundi_cal_holiday_catalog_get( $catalog_id ) ) {
		return new WP_Error( 'ax_cal_catalog_missing', __( 'That catalog does not exist.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update( axismundi_cal_calendars_table(), array( 'holiday_catalog_id' => $catalog_id ), array( 'id' => $calendar_id ) );
	return true;
}

/**
 * The calendars that are one catalog, in each language it has.
 *
 * @param int $catalog_id Catalog id.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_catalog_calendars( int $catalog_id ) : array {
	global $wpdb;
	if ( $catalog_id <= 0 || ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE holiday_catalog_id = %d ORDER BY id ASC", $catalog_id ), ARRAY_A );
}

/**
 * The languages one day of a holiday actually has.
 *
 * Wikipedia's language menu, and its rule: only editions that exist are listed. A catalog with three
 * calendars and one linked item offers one language, because the other two have nothing to show and
 * saying otherwise would present a Korean name as an English one.
 *
 * @param int $occurrence_id Occurrence id.
 * @return array<string,array<string,mixed>> Language tag => item row.
 */
function axismundi_cal_occurrence_languages( int $occurrence_id ) : array {
	$languages = array();
	foreach ( axismundi_cal_occurrence_items( $occurrence_id ) as $item ) {
		$calendar = axismundi_cal_calendar_get( (int) $item['calendar_id'] );
		$config   = axismundi_cal_provider_config( $calendar );
		$locale   = (string) ( $config['source_locale'] ?? '' );
		if ( '' !== $locale ) {
			$languages[ $locale ] = $item;
		}
	}
	return $languages;
}

/**
 * Group holiday calendars that predate catalogs.
 *
 * One catalog per provider and jurisdiction, which is what those calendars already were: a site with
 * Korean holidays in two languages had one dataset and two representations, and the only thing
 * missing was somewhere to say so. Concepts follow their jurisdiction into it.
 *
 * @return int Number of calendars joined.
 */
function axismundi_cal_backfill_holiday_catalogs() : int {
	global $wpdb;
	$calendars = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration over this plugin's own table.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$calendars}" );
	if ( ! in_array( 'holiday_catalog_id', $columns, true ) ) {
		return 0;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
	$rows = (array) $wpdb->get_results( "SELECT id, provider_config FROM {$calendars} WHERE kind = 'system' AND system_provider = 'holiday' AND holiday_catalog_id = 0", ARRAY_A );

	$joined = 0;
	foreach ( $rows as $row ) {
		$config       = axismundi_cal_provider_config( $row );
		$jurisdiction = strtoupper( (string) ( $config['region'] ?? '' ) );
		if ( 1 !== preg_match( '/^[A-Z]{2}$/', $jurisdiction ) ) {
			continue;
		}
		$existing = axismundi_cal_holiday_catalog_candidates( 'holiday', $jurisdiction );
		$catalog  = array() !== $existing
			? (int) $existing[0]['id']
			: axismundi_cal_holiday_catalog_save( array( 'provider' => 'holiday', 'jurisdiction' => $jurisdiction, 'label' => $jurisdiction . ' holidays' ) );
		if ( is_wp_error( $catalog ) ) {
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( $calendars, array( 'holiday_catalog_id' => (int) $catalog ), array( 'id' => (int) $row['id'] ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- concepts follow their jurisdiction into it.
		$wpdb->update( axismundi_cal_holiday_concepts_table(), array( 'catalog_id' => (int) $catalog ), array( 'jurisdiction' => $jurisdiction, 'catalog_id' => 0 ) );
		++$joined;
	}
	return $joined;
}

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

	/*
	 * The catalog answers where this holiday applies. Asked once there rather than repeated on every
	 * concept, which would be two places to disagree about the same fact.
	 */
	$catalog_id = (int) ( $fields['catalog_id'] ?? ( $existing['catalog_id'] ?? 0 ) );
	$catalog    = axismundi_cal_holiday_catalog_get( $catalog_id );
	if ( ! is_array( $catalog ) ) {
		return new WP_Error( 'ax_cal_concept_catalog', __( 'A holiday belongs to a catalog.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$jurisdiction = (string) $catalog['jurisdiction'];
	/*
	 * A label for the people maintaining this, not an identity. It is whatever the reviewer finds
	 * legible -- often the name in the site's own language -- and changing it moves nothing.
	 */
	$label = trim( (string) ( $fields['label'] ?? ( $existing['label'] ?? '' ) ) );
	if ( '' === $label ) {
		return new WP_Error( 'ax_cal_concept_label', __( 'A holiday needs a name to be recognised by.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$categories = axismundi_cal_normalize_categories( $fields['categories'] ?? ( $existing['categories'] ?? array() ) );

	/*
	 * An external identifier, and only that. `Q8249787` names the same subject in Wikidata and is
	 * worth recording -- it lets somebody say "this concept is 설날" before any English row exists, and
	 * lets a later import propose links from catalog and date rather than from how alike two titles
	 * look.
	 *
	 * It does not replace the internal uuid and is never the identity here. Wikidata is authority for
	 * what 설날 is; it is not authority for which days Korea took off in 2026, whether a substitute
	 * was granted, or what this site decided to publish -- and a record that borrowed its identity
	 * would inherit its answers to questions it is not answering.
	 */
	$qid = strtoupper( trim( (string) ( $fields['wikidata_qid'] ?? ( $existing['wikidata_qid'] ?? '' ) ) ) );
	if ( '' !== $qid && 1 !== preg_match( '/^Q[1-9][0-9]*$/', $qid ) ) {
		return new WP_Error( 'ax_cal_concept_qid', __( 'A Wikidata identifier looks like Q8249787.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$now  = current_time( 'mysql', true );
	$data = array(
		'catalog_id'   => $catalog_id,
		'wikidata_qid' => $qid,
		// Kept alongside so a concept can be read without its catalog, and written only from it.
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
 * The concept that names the same subject as one Wikidata item.
 *
 * Looked up rather than joined on: two catalogs can both point at `Q8249787`, because Korean and
 * Japanese observances of the lunar new year are the same subject and different datasets.
 *
 * @param int    $catalog_id Catalog id.
 * @param string $qid        Wikidata item id.
 * @return array<string,mixed>|null
 */
function axismundi_cal_concept_by_qid( int $catalog_id, string $qid ) : ?array {
	global $wpdb;
	$qid = strtoupper( trim( $qid ) );
	if ( $catalog_id <= 0 || '' === $qid || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_holiday_concepts_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE catalog_id = %d AND wikidata_qid = %s", $catalog_id, $qid ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * Where one concept can be read about, when it says.
 *
 * @param array<string,mixed> $concept Concept row.
 * @return string
 */
function axismundi_cal_concept_wikidata_url( array $concept ) : string {
	$qid = (string) ( $concept['wikidata_qid'] ?? '' );
	return '' !== $qid ? 'https://www.wikidata.org/wiki/' . $qid : '';
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
 * @param int $catalog_id Catalog id.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_holiday_concepts( int $catalog_id ) : array {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_holiday_concepts_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE catalog_id = %d ORDER BY label ASC", $catalog_id ), ARRAY_A );
}

/**
 * Record one day of a holiday in one year.
 *
 * @param int                 $concept_id    Concept id.
 * @param array<string,mixed> $fields        start_date, end_date, batch_year, role, substitute_for, status.
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
	} else {
		$principal = axismundi_cal_holiday_occurrence_get( $substitute_for );
		if ( ! is_array( $principal ) || (int) $principal['concept_id'] !== $concept_id || $substitute_for === $occurrence_id ) {
			return new WP_Error( 'ax_cal_substitute_for', __( 'A substitute day must name another day of the same holiday.', 'axismundi-calendar' ), array( 'status' => 400 ) );
		}
	}
	$batch_year = (int) ( $fields['batch_year'] ?? ( $existing['batch_year'] ?? 0 ) );
	if ( $batch_year <= 0 ) {
		$batch_year = (int) substr( $start, 0, 4 );
	}
	$status = (string) ( $fields['status'] ?? ( $existing['status'] ?? 'draft' ) );
	if ( ! in_array( $status, AXISMUNDI_CAL_ITEM_STATUSES, true ) ) {
		return new WP_Error( 'ax_cal_occurrence_status', __( 'A holiday day is either a draft or published.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$now  = current_time( 'mysql', true );
	$data = array(
		'concept_id'     => $concept_id,
		'start_date'     => $start,
		'end_date'       => $end,
		'batch_year'     => $batch_year,
		'role'           => $role,
		'substitute_for' => $substitute_for,
		'status'         => $status,
		'updated_at'     => $now,
	);
	$table = axismundi_cal_holiday_occurrences_table();
	if ( is_array( $existing ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ) );
		axismundi_cal_sync_occurrence_items( (int) $existing['id'], $status );
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
	$occurrence = $occurrence_id > 0 ? axismundi_cal_holiday_occurrence_get( $occurrence_id ) : null;
	if ( $occurrence_id > 0 && ! is_array( $occurrence ) ) {
		return new WP_Error( 'ax_cal_occurrence_missing', __( 'That day of a holiday does not exist.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	if ( is_array( $occurrence ) && 'published' === (string) $item['status'] && 'published' !== (string) $occurrence['status'] ) {
		$updated = axismundi_cal_holiday_occurrence_save( (int) $occurrence['concept_id'], array( 'status' => 'published' ), $occurrence_id );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}
		$occurrence = axismundi_cal_holiday_occurrence_get( $occurrence_id );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update(
		axismundi_cal_system_items_table(),
		array(
			'holiday_occurrence_id' => $occurrence_id,
			// Classification and publication belong to the occurrence and concept once it is linked.
			'categories'             => '',
			// Detaching also returns this label to review. Otherwise it would remain published while no
			// longer having a holiday to classify or an occurrence to publish.
			'status'                 => is_array( $occurrence ) ? (string) $occurrence['status'] : 'draft',
		),
		array( 'id' => $item_id )
	);
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
 * Its holiday's when it is linked, or its own while it is awaiting review. This is what the linking
 * is for: 설날 is a public holiday once, rather than once per language and again every year.
 *
 * @param array<string,mixed> $item System item row.
 * @return string[]
 */
function axismundi_cal_item_effective_categories( array $item ) : array {
	$occurrence = axismundi_cal_holiday_occurrence_get( (int) ( $item['holiday_occurrence_id'] ?? 0 ) );
	if ( is_array( $occurrence ) ) {
		$concept = axismundi_cal_holiday_concept_get( (int) $occurrence['concept_id'] );
		return is_array( $concept ) ? axismundi_cal_normalize_categories( (string) $concept['categories'] ) : array();
	}
	return axismundi_cal_normalize_categories( (string) ( $item['categories'] ?? '' ) );
}

/**
 * Attach an imported locale label when the catalog has exactly one day at that date.
 *
 * A single candidate is no longer a judgement about a foreign title: it is an already-reviewed
 * occurrence in the same dataset. None or several candidates stay for the reviewer.
 *
 * @param int $item_id Imported system item id.
 * @return bool True when linked automatically.
 */
function axismundi_cal_auto_link_imported_holiday_item( int $item_id ) : bool {
	$item     = axismundi_cal_system_item_get( $item_id );
	$calendar = is_array( $item ) ? axismundi_cal_calendar_get( (int) $item['calendar_id'] ) : null;
	if ( ! is_array( $item ) || ! is_array( $calendar ) || 'holiday' !== axismundi_cal_system_provider( $calendar ) || (int) $item['holiday_occurrence_id'] > 0 ) {
		return false;
	}
	$candidates = axismundi_cal_occurrence_candidates( $item, (int) $calendar['holiday_catalog_id'] );
	if ( 1 !== count( $candidates ) ) {
		return false;
	}
	return ! is_wp_error( axismundi_cal_link_item_to_occurrence( $item_id, (int) $candidates[0]['id'] ) );
}

/**
 * Occurrences that could be what an unlinked entry is about.
 *
 * A proposal, never a merge. Same jurisdiction, same date -- which is usually one answer and
 * sometimes several, and is exactly the case a person has to settle.
 *
 * @param array<string,mixed> $item         System item row.
 * @param int                 $catalog_id   Catalog the calendar belongs to.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_occurrence_candidates( array $item, int $catalog_id ) : array {
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
			 WHERE c.catalog_id = %d AND o.start_date = %s ORDER BY o.id ASC",
			$catalog_id,
			(string) $item['start_date']
		),
		ARRAY_A
	);
}
