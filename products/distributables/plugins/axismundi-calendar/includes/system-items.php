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
 * An entry is one of two shapes in time, and `temporal_kind` is which. An `all_day` entry is a civil
 * date: a holiday on the 15th is the 15th in Seoul and in New York, and converting it would move it a
 * day for somebody, which is exactly the bug the all-day handling elsewhere in this plugin exists to
 * prevent. An `instant` is a moment in UTC and has no civil date at all -- a full moon at 00:30Z is
 * the 28th in Seoul and the 27th in Los Angeles, so the date is the viewer's to produce.
 *
 * "Civil" here is only ever the time model. The `CIVIC` category below is unrelated: it classifies
 * what a date is about, not how it sits in time.
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
	'NEW-MOON',
	'FIRST-QUARTER',
	'FULL-MOON',
	'LAST-QUARTER',
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

/** The two shapes a dataset entry can have. */
const AXISMUNDI_CAL_TEMPORAL_ALL_DAY = 'all_day';
const AXISMUNDI_CAL_TEMPORAL_INSTANT = 'instant';
const AXISMUNDI_CAL_TEMPORAL_KINDS   = array( AXISMUNDI_CAL_TEMPORAL_ALL_DAY, AXISMUNDI_CAL_TEMPORAL_INSTANT );

/**
 * Category keys that describe one dimension and therefore cannot coexist.
 *
 * Categories normally compose: a Buddhist public holiday is both `BUDDHIST` and
 * `PUBLIC-HOLIDAY`. A moon phase is different. It has exactly one position in its cycle, so
 * storing both `NEW-MOON` and `FULL-MOON` would make the row name itself differently at different
 * read surfaces. Keep the rule next to the vocabulary instead of teaching each renderer which key
 * happens to win.
 */
const AXISMUNDI_CAL_CATEGORY_EXCLUSIVE_GROUPS = array(
	'moon_phase' => array(
		'members'  => array( 'NEW-MOON', 'FIRST-QUARTER', 'FULL-MOON', 'LAST-QUARTER' ),
		'requires' => 'MOON-PHASE',
	),
);

/**
 * A UTC moment, or null when the string is not one.
 *
 * UTC and nothing else. A stored moment carrying an offset would be two facts where one is wanted,
 * and the second goes stale the next time a zone changes its rules.
 *
 * @param string $value `YYYY-MM-DD HH:MM:SS`, or ISO 8601 with `T` and an optional trailing `Z`.
 * @return string|null
 */
function axismundi_cal_utc_instant( string $value ) : ?string {
	$value = trim( str_replace( array( 'T', 'Z' ), array( ' ', '' ), $value ) );
	if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})(?::(\d{2}))?$/', $value, $parts ) ) {
		return null;
	}
	if ( ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
		return null;
	}
	if ( (int) $parts[4] > 23 || (int) $parts[5] > 59 || (int) ( $parts[6] ?? 0 ) > 59 ) {
		return null;
	}
	return sprintf( '%s-%s-%s %s:%s:%02d', $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], (int) ( $parts[6] ?? 0 ) );
}

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
 * Reject category combinations that would make one entry say two incompatible things.
 *
 * @param mixed $categories List, or a comma-separated string.
 * @return true|WP_Error
 */
function axismundi_cal_validate_categories( $categories ) {
	$normalized = axismundi_cal_normalize_categories( $categories );
	foreach ( AXISMUNDI_CAL_CATEGORY_EXCLUSIVE_GROUPS as $group => $definition ) {
		$members  = $definition['members'];
		$requires = (string) ( $definition['requires'] ?? '' );
		$chosen   = array_values( array_intersect( $normalized, $members ) );
		if ( count( $chosen ) > 1 ) {
			return new WP_Error( 'ax_cal_category_conflict', __( 'An entry can have only one value in each exclusive category group.', 'axismundi-calendar' ), array( 'group' => $group, 'categories' => $chosen, 'status' => 400 ) );
		}
		if ( '' !== $requires ) {
			if ( in_array( $requires, $normalized, true ) && 1 !== count( $chosen ) ) {
				return new WP_Error( 'ax_cal_category_required', __( 'A moon phase needs one phase.', 'axismundi-calendar' ), array( 'group' => $group, 'status' => 400 ) );
			}
			if ( array() !== $chosen && ! in_array( $requires, $normalized, true ) ) {
				return new WP_Error( 'ax_cal_category_parent', __( 'A phase belongs to the moon-phase category.', 'axismundi-calendar' ), array( 'group' => $group, 'status' => 400 ) );
			}
		}
	}
	return true;
}

/**
 * The name a row's own categories give it, or '' when they give it none.
 *
 * A moon phase is not named, it is identified. `FULL-MOON` already says everything the name would,
 * and storing "보름" next to it would be storing a translation: the row would then read as Korean to
 * an English reader, and a site that changed its language would keep the old word forever. The key
 * is the fact and the name is produced from it at read time, which is the same rule the secondary
 * calendars follow.
 *
 * Only the exclusive groups can do this, and that is not a coincidence -- a name has to be one name,
 * so only a dimension that already permits exactly one value can supply it.
 *
 * @param mixed $categories List, or a comma-separated string.
 * @return string
 */
function axismundi_cal_item_generated_name( $categories ) : string {
	$names = array(
		'NEW-MOON'      => __( 'New moon', 'axismundi-calendar' ),
		'FIRST-QUARTER' => __( 'First quarter', 'axismundi-calendar' ),
		'FULL-MOON'     => __( 'Full moon', 'axismundi-calendar' ),
		'LAST-QUARTER'  => __( 'Last quarter', 'axismundi-calendar' ),
	);
	foreach ( axismundi_cal_normalize_categories( $categories ) as $key ) {
		if ( isset( $names[ $key ] ) ) {
			return $names[ $key ];
		}
	}
	return '';
}

/**
 * What one entry is called.
 *
 * The single answer to "what does this row say its name is", so a surface cannot accidentally
 * disagree with another one. A stored title wins because somebody wrote it on purpose -- a
 * translated holiday label is not something a category could reproduce.
 *
 * Never '' for a row this plugin wrote: `system_item_save()` refuses a row that neither carries a
 * title nor can generate one, so the two halves are one rule seen from either end. It is still
 * written defensively, because a row that predates that rule would otherwise reach a subscriber's
 * client as a VEVENT with no SUMMARY, and an .ics is kept by the client rather than re-fetched.
 *
 * @param array<string,mixed> $item Entry row.
 * @return string
 */
function axismundi_cal_item_display_name( array $item ) : string {
	$title = trim( (string) ( $item['title'] ?? '' ) );
	if ( '' !== $title ) {
		return $title;
	}
	$generated = axismundi_cal_item_generated_name( $item['categories'] ?? array() );
	return '' !== $generated ? $generated : __( 'Untitled entry', 'axismundi-calendar' );
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

	/*
	 * Before the title, because whether a title is required is a question the categories answer.
	 */
	$categories = $fields['categories'] ?? ( $existing['categories'] ?? array() );
	$category_validation = axismundi_cal_validate_categories( $categories );
	if ( is_wp_error( $category_validation ) ) {
		return $category_validation;
	}

	/*
	 * A row must be able to say what it is called, by one of two routes. A holiday is named because
	 * somebody wrote the name: `설날` cannot be derived from `PUBLIC-HOLIDAY`, and no fallback is going
	 * to invent it. A moon phase is named because `FULL-MOON` already says it, in whatever language
	 * the reader is using -- a generator producing tens of thousands of these should not be made to
	 * write a word it would have to translate.
	 *
	 * The condition is nameability rather than which provider or which screen is writing. A caller
	 * cannot be trusted to declare that it is the generator, and the invariant that actually matters
	 * downstream is that no row reaches a reader without a name. Stated this way the two ends agree by
	 * construction: NULL is allowed here exactly when `item_display_name()` has an answer.
	 */
	$title = trim( (string) ( $fields['title'] ?? ( $existing['title'] ?? '' ) ) );
	if ( '' === $title && '' === axismundi_cal_item_generated_name( $categories ) ) {
		return new WP_Error( 'ax_cal_item_title', __( 'An entry needs a name, or a category that supplies one.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	/*
	 * Which shape this row is. Defaulted to `all_day`, which every row was before instants existed
	 * and which a caller that says nothing means.
	 */
	$kind = (string) ( $fields['temporal_kind'] ?? ( $existing['temporal_kind'] ?? AXISMUNDI_CAL_TEMPORAL_ALL_DAY ) );
	if ( ! in_array( $kind, AXISMUNDI_CAL_TEMPORAL_KINDS, true ) ) {
		return new WP_Error( 'ax_cal_item_kind', __( 'An entry is either a whole day or a moment.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$start     = null;
	$end       = null;
	$start_utc = null;
	$end_utc   = null;

	if ( AXISMUNDI_CAL_TEMPORAL_INSTANT === $kind ) {
		/*
		 * A moment, and only a moment. The civil columns stay NULL rather than being filled with the
		 * UTC date, because that date is true for only half the world: a full moon at 00:30Z is the
		 * 28th in Seoul and the 27th in Los Angeles. The date is something a viewer's timezone
		 * produces, not something this row can hold.
		 */
		$start_utc = axismundi_cal_utc_instant( (string) ( $fields['start_utc'] ?? ( $existing['start_utc'] ?? '' ) ) );
		if ( null === $start_utc ) {
			return new WP_Error( 'ax_cal_item_instant', __( 'A moment needs a UTC time, as YYYY-MM-DD HH:MM:SS.', 'axismundi-calendar' ), array( 'status' => 400 ) );
		}
		$end_utc = axismundi_cal_utc_instant( (string) ( $fields['end_utc'] ?? ( $existing['end_utc'] ?? '' ) ) );
		if ( null !== $end_utc && $end_utc < $start_utc ) {
			return new WP_Error( 'ax_cal_item_range', __( 'A moment cannot end before it begins.', 'axismundi-calendar' ), array( 'status' => 400 ) );
		}
	} else {
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
		$batch_year = (int) substr( (string) ( $start ?? $start_utc ), 0, 4 );
	}

	$source_uid = trim( (string) ( $fields['source_uid'] ?? ( $existing['source_uid'] ?? '' ) ) );
	$occurrence_id = (int) ( $fields['holiday_occurrence_id'] ?? ( $existing['holiday_occurrence_id'] ?? 0 ) );
	$occurrence    = axismundi_cal_holiday_occurrence_get( $occurrence_id );
	if ( is_array( $occurrence ) ) {
		// The occurrence owns effective review state; preserve the label's prior review for an unlink.
		$status = (string) $occurrence['status'];
	}
	$now        = current_time( 'mysql', true );
	$data       = array(
		'calendar_id'  => $calendar_id,
		'temporal_kind' => $kind,
		'start_utc'    => $start_utc,
		'end_utc'      => $end_utc,
		'start_date'   => $start,
		'end_date'     => $end,
		/*
		 * NULL rather than '' for a row that names itself, because the two are different facts and only
		 * one of them is recoverable. NULL says the categories are the name; '' would say somebody set
		 * the name to nothing, and a later reviewer looking at a blank field could not tell which had
		 * happened. It also keeps "which rows did the generator write" answerable in SQL.
		 */
		'title'        => '' !== $title ? $title : null,
		'description'  => (string) ( $fields['description'] ?? ( $existing['description'] ?? '' ) ),
		'categories'   => implode( ',', axismundi_cal_normalize_categories( $categories ) ),
		'transparency' => $transparency,
		'batch_year'   => $batch_year,
		'status'       => $status,
		/*
		 * NULL rather than '' when there is none, because the uniqueness that stops an import writing
		 * an entry twice is on (calendar, uid) -- and in SQL every NULL is distinct while every '' is
		 * the same value. Stored as '' it would mean one hand-typed entry per Calendar, ever.
		 */
		'source_uid'   => '' !== $source_uid ? $source_uid : null,
		/*
		 * Preserved across a re-import. The link is a judgement somebody made while reviewing, and a
		 * second read of the feed has nothing to say about it.
		 */
		'holiday_occurrence_id' => $occurrence_id,
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
		$effective_categories = axismundi_cal_item_effective_categories( $item );
		if ( isset( $publish[ $item_id ] ) && '' === $classification && ! in_array( 'PUBLIC-HOLIDAY', $effective_categories, true ) && ! in_array( 'OBSERVANCE', $effective_categories, true ) ) {
			return new WP_Error( 'ax_cal_holiday_category', __( 'Classify every entry before publishing it.', 'axismundi-calendar' ), array( 'status' => 400 ) );
		}
		$prepared[ $item_id ] = array( 'item' => $item, 'classification' => $classification, 'substitute' => ! empty( $review['substitute'] ) );
	}
	foreach ( array_keys( $publish ) as $item_id ) {
		if ( ! isset( $prepared[ $item_id ] ) ) {
			return new WP_Error( 'ax_cal_item_missing', __( 'That entry is not on this calendar.', 'axismundi-calendar' ), array( 'status' => 400 ) );
		}
	}

	$saved = 0;
	foreach ( $prepared as $item_id => $review ) {
		$occurrence = axismundi_cal_holiday_occurrence_get( (int) $review['item']['holiday_occurrence_id'] );
		if ( is_array( $occurrence ) ) {
			/*
			 * Once an item names an occurrence, its review belongs there. A Korean reviewer publishing
			 * March First must make the English label public too; otherwise the two language editions
			 * disagree about whether the same day exists.
			 */
			$concept = axismundi_cal_holiday_concept_get( (int) $occurrence['concept_id'] );
			if ( ! is_array( $concept ) ) {
				return new WP_Error( 'ax_cal_concept_missing', __( 'That holiday does not exist.', 'axismundi-calendar' ), array( 'status' => 404 ) );
			}
			if ( '' !== $review['classification'] ) {
				$categories = axismundi_cal_normalize_categories( (string) $concept['categories'] );
				$categories = array_values( array_diff( $categories, array( 'HOLIDAY', 'PUBLIC-HOLIDAY', 'OBSERVANCE', 'SUBSTITUTE-HOLIDAY' ) ) );
				$categories[] = 'HOLIDAY';
				$categories[] = $review['classification'];
				$concept_save = axismundi_cal_holiday_concept_save( array( 'categories' => $categories ), (int) $concept['id'] );
				if ( is_wp_error( $concept_save ) ) {
					return $concept_save;
				}
			}
			if ( isset( $publish[ $item_id ] ) ) {
				$occurrence_save = axismundi_cal_holiday_occurrence_save( (int) $occurrence['concept_id'], array( 'status' => 'published' ), (int) $occurrence['id'] );
				if ( is_wp_error( $occurrence_save ) ) {
					return $occurrence_save;
				}
			}
			++$saved;
			continue;
		}
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
 * Apply one holiday classification to selected entries.
 *
 * @param int    $calendar_id Calendar id.
 * @param int[]  $item_ids    Selected item ids.
 * @param string $category    PUBLIC-HOLIDAY or OBSERVANCE.
 * @param bool   $publish     Whether the selected entries are ready to publish.
 * @return int|WP_Error Number of rows saved.
 */
function axismundi_cal_bulk_classify_holiday_items( int $calendar_id, array $item_ids, string $category, bool $publish = false ) {
	$item_ids = array_values( array_unique( array_filter( array_map( 'intval', $item_ids ) ) ) );
	if ( array() === $item_ids ) {
		return new WP_Error( 'ax_cal_holiday_selection', __( 'Choose at least one entry first.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$reviews = array();
	foreach ( $item_ids as $item_id ) {
		$item = axismundi_cal_system_item_get( $item_id );
		if ( ! is_array( $item ) || (int) $item['calendar_id'] !== $calendar_id ) {
			return new WP_Error( 'ax_cal_item_missing', __( 'That entry is not on this calendar.', 'axismundi-calendar' ), array( 'status' => 400 ) );
		}
		$categories = axismundi_cal_normalize_categories( (string) $item['categories'] );
		$reviews[ $item_id ] = array(
			'classification' => $category,
			'substitute'     => in_array( 'SUBSTITUTE-HOLIDAY', $categories, true ),
		);
	}
	return axismundi_cal_review_holiday_items( $calendar_id, $reviews, $publish ? $item_ids : array() );
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
	$table       = axismundi_cal_system_items_table();
	$occurrences = axismundi_cal_holiday_occurrences_table();
	$concepts    = axismundi_cal_holiday_concepts_table();
	/*
	 * Overlap, not containment: an entry spanning the whole window belongs in it.
	 *
	 * Instants are matched on their own column and with a day of slack at each end. The caller asks in
	 * civil dates, but a moment's civil date is the viewer's to decide -- 2026-08-28T00:30Z is the
	 * 28th in Seoul and the 27th in Los Angeles -- so the window is widened to cover both readings and
	 * the projection is left to whoever knows the timezone. Narrowing here would drop the first or
	 * last day of every month for somebody.
	 */
	$sql    = "SELECT i.* FROM {$table} i LEFT JOIN {$occurrences} o ON o.id = i.holiday_occurrence_id LEFT JOIN {$concepts} c ON c.id = o.concept_id"
		. " WHERE i.calendar_id = %d AND ( ( i.temporal_kind = 'all_day' AND i.end_date > %s AND i.start_date < %s )"
		. " OR ( i.temporal_kind = 'instant' AND i.start_utc >= %s AND i.start_utc < %s ) )";
	$params = array(
		$calendar_id,
		$from,
		$to,
		gmdate( 'Y-m-d 00:00:00', (int) strtotime( $from . ' -1 day' ) ),
		gmdate( 'Y-m-d 00:00:00', (int) strtotime( $to . ' +1 day' ) ),
	);
	if ( ! $include_drafts ) {
		$sql .= " AND (CASE WHEN i.holiday_occurrence_id > 0 THEN o.status ELSE i.status END) = 'published'";
	}

	$wanted = axismundi_cal_normalize_categories( $categories );
	if ( array() !== $wanted ) {
		$sql .= ' AND (' . implode( ' OR ', array_fill( 0, count( $wanted ), 'FIND_IN_SET(%s, CASE WHEN i.holiday_occurrence_id > 0 THEN c.categories ELSE i.categories END)' ) ) . ')';
		$params = array_merge( $params, $wanted );
	}
	// One order for both shapes: whichever column a row uses, the other is NULL.
	$sql .= ' ORDER BY COALESCE(i.start_date, DATE(i.start_utc)) ASC, i.id ASC';

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- range query over this plugin's own table.
	$items = (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	foreach ( $items as &$item ) {
		if ( (int) $item['holiday_occurrence_id'] > 0 ) {
			$occurrence = axismundi_cal_holiday_occurrence_get( (int) $item['holiday_occurrence_id'] );
			$item['status']     = is_array( $occurrence ) ? (string) $occurrence['status'] : (string) $item['status'];
			$categories = axismundi_cal_item_effective_categories( $item );
			if ( is_array( $occurrence ) && 'substitute' === (string) $occurrence['role'] ) {
				$categories[] = 'SUBSTITUTE-HOLIDAY';
			}
			$item['categories'] = implode( ',', axismundi_cal_normalize_categories( $categories ) );
		}
	}
	unset( $item );
	return $items;
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
	$table       = axismundi_cal_system_items_table();
	$occurrences = axismundi_cal_holiday_occurrences_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- summary over this plugin's own table.
	$rows = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT i.batch_year, COUNT(*) AS total,
			 SUM((CASE WHEN i.holiday_occurrence_id > 0 THEN o.status ELSE i.status END) = 'published') AS published
			 FROM {$table} i LEFT JOIN {$occurrences} o ON o.id = i.holiday_occurrence_id
			 WHERE i.calendar_id = %d GROUP BY i.batch_year ORDER BY i.batch_year ASC",
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
