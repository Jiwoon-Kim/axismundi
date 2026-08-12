<?php
/**
 * The entries of a maintained calendar.
 *
 * Holidays, observances, moon phases. They look like Events on a screen and are nothing like them
 * underneath: nobody authored them, nobody is their organizer, they federate as nothing, and they
 * are replaced wholesale when next year's dates are reviewed. Giving each one an `ax_event` post
 * would put a country's public holidays into the same list a person writes their own Events in, and
 * would hand every one of them an author, a permalink and an ActivityPub identity that mean nothing.
 *
 * Everything here is a civil date. A holiday on the 15th is the 15th in Seoul and in New York, and
 * the moment one of these becomes an instant it starts moving a day for somebody -- which is exactly
 * the bug the all-day handling elsewhere in this plugin exists to prevent.
 *
 * Categories are the stable half of the vocabulary. `SUMMARY` is a translation and changes with the
 * locale; `PUBLIC-HOLIDAY` is what a filter can be built on. Google's own holiday feed puts its
 * classification in a localized `DESCRIPTION`, which is precisely why an importer cannot read it
 * back: the string changes with the language and with whatever Google decides to say next.
 *
 * Nothing here decides what a year is ready to be seen. `status` records the answer and the review
 * workflow that sets it comes later; what this file guarantees is that a draft year is invisible to
 * everyone but the people maintaining it.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The category keys an entry may carry.
 *
 * A closed vocabulary, so a filter can be built on it. `CATEGORIES` in RFC 5545 is free text with no
 * reserved values -- the specification defines the property and leaves the words to the application
 * -- so these are ours to define and ours to keep stable. Adding one is a code change on purpose: a
 * key invented by a typo during an import is a category nobody can ever filter by, and it would look
 * exactly like a real one.
 *
 * Multiple keys per entry is the point rather than an allowance. Buddha's Birthday in Korea is a
 * public holiday and a religious observance at once, and a taxonomy that forces a choice between
 * those records something false.
 */
const AXISMUNDI_CAL_ITEM_CATEGORIES = array(
	'HOLIDAY',
	'PUBLIC-HOLIDAY',
	'OBSERVANCE',
	'SUBSTITUTE-HOLIDAY',
	'RELIGIOUS',
	'BUDDHIST',
	'CHRISTIAN',
	'ISLAMIC',
	'JEWISH',
	'CIVIC',
	'ELECTION',
	'COMMEMORATION',
	'ASTRONOMY',
	'MOON-PHASE',
	'EQUINOX',
	'SOLSTICE',
	'ACADEMIC',
	'TERM',
	'VACATION',
	'EXAM-PERIOD',
);

/**
 * The top-level categories a system Calendar declares for itself.
 *
 * These describe the dataset a person is adding from the catalog. Item categories describe the
 * individual dates inside it, so `MOON-PHASE` belongs on an item while `ASTRONOMY` belongs on the
 * Moon phases Calendar that contains it.
 */
const AXISMUNDI_CAL_SYSTEM_CALENDAR_CATEGORIES = array(
	'HOLIDAY',
	'ASTRONOMY',
	'RELIGIOUS',
	'CIVIC',
	'ACADEMIC',
);

/** Entries not yet reviewed are visible only to the people maintaining them. */
const AXISMUNDI_CAL_ITEM_STATUSES = array( 'draft', 'published' );

/**
 * Normalize a list of category keys.
 *
 * Unknown keys are dropped rather than stored. A category that no filter offers is one that hides an
 * entry from everybody who uses the filter and shows it to everybody who does not -- worse than
 * having no category at all, and invisible until somebody wonders where an entry went.
 *
 * @param mixed $categories List, or a comma-separated string.
 * @return string[] Known keys, deduplicated, in vocabulary order.
 */
function axismundi_cal_normalize_categories( $categories ) : array {
	$list = is_array( $categories ) ? $categories : explode( ',', (string) $categories );
	$seen = array();
	foreach ( $list as $category ) {
		$seen[ strtoupper( trim( (string) $category ) ) ] = true;
	}
	/*
	 * Built by walking the vocabulary rather than the input, which does three things at once: unknown
	 * keys cannot appear, duplicates collapse, and the stored value is in one order regardless of how
	 * whoever wrote it happened to type the list.
	 */
	return array_values( array_filter( AXISMUNDI_CAL_ITEM_CATEGORIES, static fn( string $key ) : bool => isset( $seen[ $key ] ) ) );
}

/**
 * Normalize the top-level classification of a system Calendar.
 *
 * @param mixed $categories List, or a comma-separated string.
 * @return string[] Known category keys, deduplicated, in vocabulary order.
 */
function axismundi_cal_normalize_system_calendar_categories( $categories ) : array {
	$list = is_array( $categories ) ? $categories : explode( ',', (string) $categories );
	$seen = array();
	foreach ( $list as $category ) {
		$seen[ strtoupper( trim( (string) $category ) ) ] = true;
	}
	return array_values( array_filter( AXISMUNDI_CAL_SYSTEM_CALENDAR_CATEGORIES, static fn( string $key ) : bool => isset( $seen[ $key ] ) ) );
}

/**
 * A translated label for one top-level system Calendar category.
 *
 * @param string $category Stable category key.
 * @return string
 */
function axismundi_cal_system_calendar_category_label( string $category ) : string {
	$labels = array(
		'HOLIDAY'   => __( 'Holidays', 'axismundi-calendar' ),
		'ASTRONOMY' => __( 'Astronomy', 'axismundi-calendar' ),
		'RELIGIOUS' => __( 'Religious observances', 'axismundi-calendar' ),
		'CIVIC'     => __( 'Civic dates', 'axismundi-calendar' ),
		'ACADEMIC'  => __( 'Academic calendar', 'axismundi-calendar' ),
	);
	return $labels[ $category ] ?? $category;
}

/**
 * Write one entry.
 *
 * `source_uid` is what makes an import re-runnable: an entry already carrying the same uid on this
 * Calendar is updated rather than added again. Entries typed in by hand have none, and are therefore
 * always new -- which is right, since nothing outside identifies them.
 *
 * @param int                 $calendar_id Calendar id.
 * @param array<string,mixed> $fields      Entry fields.
 * @param int                 $item_id     Existing entry to update, or 0.
 * @return int|WP_Error Entry id.
 */
function axismundi_cal_system_item_save( int $calendar_id, array $fields, int $item_id = 0 ) {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return new WP_Error( 'ax_cal_store', __( 'The calendar store is unavailable.', 'axismundi-calendar' ) );
	}
	$calendar = axismundi_cal_calendar_get( $calendar_id );
	if ( ! is_array( $calendar ) || ! axismundi_cal_calendar_is_dataset( $calendar ) ) {
		/*
		 * Only a maintained Calendar holds these. An ordinary Calendar holds Events, which have an
		 * author and a federated identity, and putting an entry on one would create something that
		 * appears on the grid and belongs to nobody.
		 */
		return new WP_Error( 'ax_cal_not_dataset', __( 'Only a maintained calendar holds dataset entries.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$existing = $item_id > 0 ? axismundi_cal_system_item_get( $item_id ) : null;
	$title    = trim( (string) ( $fields['title'] ?? ( $existing['title'] ?? '' ) ) );
	if ( '' === $title ) {
		return new WP_Error( 'ax_cal_item_title', __( 'An entry needs a name.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$start = axismundi_cal_civil_date( (string) ( $fields['start_date'] ?? ( $existing['start_date'] ?? '' ) ) );
	if ( '' === $start ) {
		return new WP_Error( 'ax_cal_item_date', __( 'An entry needs a date, as YYYY-MM-DD.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	/*
	 * The end is exclusive, as `DTEND` is for an all-day VEVENT: a one-day entry ends the following
	 * day. Defaulted rather than required, because almost every one of these is a single day and
	 * asking for both dates would invite the off-by-one this convention exists to settle.
	 */
	$end = axismundi_cal_civil_date( (string) ( $fields['end_date'] ?? ( $existing['end_date'] ?? '' ) ) );
	if ( '' === $end ) {
		$end = gmdate( 'Y-m-d', (int) strtotime( $start . ' +1 day' ) );
	}
	if ( $end <= $start ) {
		return new WP_Error( 'ax_cal_item_range', __( 'An entry ends the day after it starts, at the earliest.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$status = (string) ( $fields['status'] ?? ( $existing['status'] ?? 'draft' ) );
	if ( ! in_array( $status, AXISMUNDI_CAL_ITEM_STATUSES, true ) ) {
		return new WP_Error( 'ax_cal_item_status', __( 'An entry is either a draft or published.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$transparency = 'OPAQUE' === strtoupper( (string) ( $fields['transparency'] ?? ( $existing['transparency'] ?? '' ) ) ) ? 'OPAQUE' : 'TRANSPARENT';

	/*
	 * Which year an entry belongs to is recorded rather than read off its date. A substitute holiday
	 * for the 31st of December falls in January and belongs to the year being reviewed, not to the
	 * one it lands in.
	 */
	$batch_year = (int) ( $fields['batch_year'] ?? ( $existing['batch_year'] ?? 0 ) );
	if ( $batch_year <= 0 ) {
		$batch_year = (int) substr( $start, 0, 4 );
	}

	$source_uid = trim( (string) ( $fields['source_uid'] ?? ( $existing['source_uid'] ?? '' ) ) );
	$now        = current_time( 'mysql', true );
	$data       = array(
		'calendar_id'  => $calendar_id,
		'start_date'   => $start,
		'end_date'     => $end,
		'title'        => $title,
		'description'  => (string) ( $fields['description'] ?? ( $existing['description'] ?? '' ) ),
		'categories'   => implode( ',', axismundi_cal_normalize_categories( $fields['categories'] ?? ( $existing['categories'] ?? array() ) ) ),
		'transparency' => $transparency,
		'batch_year'   => $batch_year,
		'status'       => $status,
		/*
		 * NULL rather than '' when there is none, because the uniqueness that stops an import writing
		 * an entry twice is on (calendar, uid) -- and in SQL every NULL is distinct while every '' is
		 * the same value. Stored as '' it would mean one hand-typed entry per Calendar, ever.
		 */
		'source_uid'   => '' !== $source_uid ? $source_uid : null,
		'source_url'   => (string) ( $fields['source_url'] ?? ( $existing['source_url'] ?? '' ) ),
		'updated_at'   => $now,
	);
	if ( array_key_exists( 'imported_at', $fields ) ) {
		$data['imported_at'] = (string) $fields['imported_at'];
	}

	// An import naming an entry it has written before updates it rather than adding a second copy.
	if ( null === $existing && '' !== $source_uid ) {
		$existing = axismundi_cal_system_item_by_uid( $calendar_id, $source_uid );
	}
	if ( is_array( $existing ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( axismundi_cal_system_items_table(), $data, array( 'id' => (int) $existing['id'] ) );
		return (int) $existing['id'];
	}
	$data['created_at'] = $now;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	if ( false === $wpdb->insert( axismundi_cal_system_items_table(), $data ) ) {
		return new WP_Error( 'ax_cal_item_write', __( 'The entry could not be saved.', 'axismundi-calendar' ) );
	}
	return (int) $wpdb->insert_id;
}

/**
 * A `YYYY-MM-DD` string, or '' when it is not one.
 *
 * Parsed strictly rather than through `strtotime()`, which accepts a great deal that is not a civil
 * date and turns some of it into today.
 *
 * @param string $value Candidate date.
 * @return string
 */
function axismundi_cal_civil_date( string $value ) : string {
	$value = trim( $value );
	if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match ) ) {
		return '';
	}
	return checkdate( (int) $match[2], (int) $match[3], (int) $match[1] ) ? $value : '';
}

/**
 * One entry.
 *
 * @param int $item_id Entry id.
 * @return array<string,mixed>|null
 */
function axismundi_cal_system_item_get( int $item_id ) : ?array {
	global $wpdb;
	if ( $item_id <= 0 || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_system_items_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $item_id ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * Apply a holiday review to a set of entries.
 *
 * Import deliberately leaves Google's localized prose uninterpreted. This is the explicit human
 * step that turns a source row into either a public holiday or an observance, and only a row marked
 * ready becomes visible to readers. Other item categories survive, so a reviewer can add RELIGIOUS
 * in the individual editor without a later yearly review erasing it.
 *
 * @param int                               $calendar_id Calendar id.
 * @param array<int,array<string,mixed>>    $reviews     Item id => submitted review values.
 * @param int[]                             $publish_ids Item ids to publish.
 * @return int|WP_Error Number of rows saved.
 */
function axismundi_cal_review_holiday_items( int $calendar_id, array $reviews, array $publish_ids ) {
	$calendar = axismundi_cal_calendar_get( $calendar_id );
	if ( 'holiday' !== axismundi_cal_system_provider( $calendar ) ) {
		return new WP_Error( 'ax_cal_review_provider', __( 'Only a holiday calendar has this review workflow.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$publish = array_fill_keys( array_map( 'intval', $publish_ids ), true );
	$prepared = array();
	foreach ( $reviews as $item_id => $review ) {
		$item_id = (int) $item_id;
		$item    = axismundi_cal_system_item_get( $item_id );
		if ( ! is_array( $item ) || (int) $item['calendar_id'] !== $calendar_id ) {
			return new WP_Error( 'ax_cal_item_missing', __( 'That entry is not on this calendar.', 'axismundi-calendar' ), array( 'status' => 400 ) );
		}
		$classification = strtoupper( sanitize_key( (string) ( $review['classification'] ?? '' ) ) );
		$classification = str_replace( '_', '-', $classification );
		if ( '' !== $classification && ! in_array( $classification, array( 'PUBLIC-HOLIDAY', 'OBSERVANCE' ), true ) ) {
			return new WP_Error( 'ax_cal_holiday_category', __( 'A holiday entry is either a public holiday or an observance.', 'axismundi-calendar' ), array( 'status' => 400 ) );
		}
		if ( isset( $publish[ $item_id ] ) && '' === $classification ) {
			return new WP_Error( 'ax_cal_holiday_category', __( 'Classify every entry before publishing it.', 'axismundi-calendar' ), array( 'status' => 400 ) );
		}
		$prepared[ $item_id ] = array( 'item' => $item, 'classification' => $classification, 'substitute' => ! empty( $review['substitute'] ) );
	}

	$saved = 0;
	foreach ( $prepared as $item_id => $review ) {
		$categories = axismundi_cal_normalize_categories( (string) $review['item']['categories'] );
		$categories = array_values( array_diff( $categories, array( 'HOLIDAY', 'PUBLIC-HOLIDAY', 'OBSERVANCE', 'SUBSTITUTE-HOLIDAY' ) ) );
		if ( '' !== $review['classification'] ) {
			$categories[] = 'HOLIDAY';
			$categories[] = $review['classification'];
			if ( $review['substitute'] ) {
				$categories[] = 'SUBSTITUTE-HOLIDAY';
			}
		}
		$status = isset( $publish[ $item_id ] ) ? 'published' : (string) $review['item']['status'];
		$result = axismundi_cal_system_item_save( $calendar_id, array( 'categories' => $categories, 'status' => $status ), $item_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		++$saved;
	}
	return $saved;
}

/**
 * One entry by the uid its source gave it.
 *
 * @param int    $calendar_id Calendar id.
 * @param string $source_uid  Source uid.
 * @return array<string,mixed>|null
 */
function axismundi_cal_system_item_by_uid( int $calendar_id, string $source_uid ) : ?array {
	global $wpdb;
	$source_uid = trim( $source_uid );
	if ( $calendar_id <= 0 || '' === $source_uid || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_system_items_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE calendar_id = %d AND source_uid = %s", $calendar_id, $source_uid ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * Remove one entry.
 *
 * @param int $item_id Entry id.
 * @return bool
 */
function axismundi_cal_system_item_delete( int $item_id ) : bool {
	global $wpdb;
	if ( $item_id <= 0 || ! axismundi_cal_ready() ) {
		return false;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	return false !== $wpdb->delete( axismundi_cal_system_items_table(), array( 'id' => $item_id ) );
}

/**
 * Entries of one Calendar overlapping a range of civil dates.
 *
 * Drafts are excluded unless the asker maintains this Calendar. A year being reviewed is not wrong
 * data, it is unreviewed data, and showing it to readers would make review pointless.
 *
 * Categories filter by key rather than by name, which is the whole reason the vocabulary is closed.
 * An entry matches when it carries any of the requested keys, so asking for `PUBLIC-HOLIDAY` and
 * `OBSERVANCE` is "either", the way a set of checkboxes reads.
 *
 * @param int      $calendar_id Calendar id.
 * @param string   $from        Civil date, inclusive.
 * @param string   $to          Civil date, exclusive.
 * @param string[] $categories  Restrict to entries carrying any of these keys, or empty for all.
 * @param bool     $include_drafts Include unreviewed entries.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_system_items_in_range( int $calendar_id, string $from, string $to, array $categories = array(), bool $include_drafts = false ) : array {
	global $wpdb;
	$from = axismundi_cal_civil_date( $from );
	$to   = axismundi_cal_civil_date( $to );
	if ( $calendar_id <= 0 || '' === $from || '' === $to || ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_system_items_table();
	// Overlap, not containment: an entry spanning the whole window belongs in it.
	$sql    = "SELECT * FROM {$table} WHERE calendar_id = %d AND end_date > %s AND start_date < %s";
	$params = array( $calendar_id, $from, $to );
	if ( ! $include_drafts ) {
		$sql .= " AND status = 'published'";
	}

	$wanted = axismundi_cal_normalize_categories( $categories );
	if ( array() !== $wanted ) {
		$sql .= ' AND (' . implode( ' OR ', array_fill( 0, count( $wanted ), 'FIND_IN_SET(%s, categories)' ) ) . ')';
		$params = array_merge( $params, $wanted );
	}
	$sql .= ' ORDER BY start_date ASC, id ASC';

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- range query over this plugin's own table.
	return (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
}

/**
 * The years a Calendar holds entries for, and whether each has been reviewed.
 *
 * @param int $calendar_id Calendar id.
 * @return array<int,array{year:int,total:int,published:int}>
 */
function axismundi_cal_system_item_years( int $calendar_id ) : array {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_system_items_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- summary over this plugin's own table.
	$rows = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT batch_year, COUNT(*) AS total, SUM(status = 'published') AS published
			 FROM {$table} WHERE calendar_id = %d GROUP BY batch_year ORDER BY batch_year ASC",
			$calendar_id
		),
		ARRAY_A
	);
	return array_map(
		static fn( array $row ) : array => array(
			'year'      => (int) $row['batch_year'],
			'total'     => (int) $row['total'],
			'published' => (int) $row['published'],
		),
		$rows
	);
}

/**
 * Drop a Calendar's entries with the Calendar.
 *
 * @param int $calendar_id Calendar id.
 * @return void
 */
function axismundi_cal_system_items_forget_calendar( int $calendar_id ) : void {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_cal_system_items_table(), array( 'calendar_id' => $calendar_id ) );
}
