<?php
/**
 * One-off: import the English holiday feed and link it to the Korean rows.
 *
 * Not a test. A recorded run of the operations the screens perform, so the result is reproducible
 * and every judgement it makes is written down rather than clicked away.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;

$ko = axismundi_cal_calendar_get( 2258 );
$en = axismundi_cal_calendar_get( 2481 );
if ( ! is_array( $ko ) || ! is_array( $en ) ) {
	echo "calendars missing\n";
	return;
}
$catalog = (int) $ko['holiday_catalog_id'];
printf( "catalog: %d\n", $catalog );

// 1. The English calendar is the same dataset in another language.
if ( (int) $en['holiday_catalog_id'] !== $catalog ) {
	axismundi_cal_join_holiday_catalog( 2481, $catalog );
	printf( "joined en calendar to catalog %d\n", $catalog );
}

// 2. Read the English feed.
$fetch = axismundi_cal_import_fetch( 'https://calendar.google.com/calendar/ical/en.south_korea%23holiday%40group.v.calendar.google.com/public/basic.ics' );
if ( is_wp_error( $fetch ) ) {
	printf( "fetch failed: %s\n", $fetch->get_error_code() );
	return;
}
$parsed = axismundi_cal_ics_parse( $fetch['body'] );
$years  = array_keys( axismundi_cal_system_items_in_range( 2258, '2000-01-01', '2100-01-01', array(), true ) ? array( 2026 => 1, 2027 => 1 ) : array() );
$part   = axismundi_cal_import_partition( $parsed, $years );
$written = axismundi_cal_import_write( 2481, $part['keep'], 'https://calendar.google.com/calendar/ical/en.south_korea%23holiday%40group.v.calendar.google.com/public/basic.ics' );
printf( "imported %d english drafts for %s (set aside: %d timed, %d repeating)\n", $written, implode( ',', $years ), $part['timed'], $part['recurring'] );

/*
 * 3. Promote the Korean rows into holidays. Rows sharing a title in one language and one catalog are
 * one holiday -- 설날 연휴 on the 16th and the 18th is the same holiday twice, which is exactly the
 * grouping the concept layer exists to hold. Across languages nothing is matched by title, and this
 * is not that: it is one feed, one language, one publisher's own naming.
 *
 * Every substitute stays a `principal` here. Which day a substitute stands in for is a judgement the
 * feed does not state, and guessing it would put a false relation somewhere nobody would look again.
 */
$concepts = array();
$promoted = 0;
$linked   = 0;
foreach ( $years as $year ) {
	foreach ( axismundi_cal_system_items_in_range( 2258, $year . '-01-01', ( $year + 1 ) . '-01-01', array(), true ) as $item ) {
		if ( (int) $item['holiday_occurrence_id'] > 0 ) {
			continue;
		}
		$title = (string) $item['title'];
		if ( ! isset( $concepts[ $title ] ) ) {
			$existing = null;
			foreach ( axismundi_cal_holiday_concepts( $catalog ) as $candidate ) {
				if ( (string) $candidate['label'] === $title ) {
					$existing = (int) $candidate['id'];
				}
			}
			$concepts[ $title ] = null !== $existing
				? $existing
				: (int) axismundi_cal_holiday_concept_save( array( 'catalog_id' => $catalog, 'label' => $title, 'categories' => (string) $item['categories'] ) );
			++$promoted;
		}
		$occurrence = axismundi_cal_holiday_occurrence_save(
			$concepts[ $title ],
			array( 'start_date' => (string) $item['start_date'], 'end_date' => (string) $item['end_date'], 'batch_year' => (int) $item['batch_year'], 'role' => 'principal' )
		);
		if ( ! is_wp_error( $occurrence ) ) {
			axismundi_cal_link_item_to_occurrence( (int) $item['id'], (int) $occurrence );
			++$linked;
		}
	}
}
printf( "promoted %d holidays, linked %d korean rows\n", $promoted, $linked );

/*
 * 4. Link the English rows by date. Same catalog, same day, and exactly one candidate -- anything
 * ambiguous is left for a person, which is the case the screen exists for.
 */
$matched   = 0;
$ambiguous = 0;
foreach ( $years as $year ) {
	foreach ( axismundi_cal_system_items_in_range( 2481, $year . '-01-01', ( $year + 1 ) . '-01-01', array(), true ) as $item ) {
		if ( (int) $item['holiday_occurrence_id'] > 0 ) {
			continue;
		}
		$candidates = axismundi_cal_occurrence_candidates( $item, $catalog );
		if ( 1 === count( $candidates ) ) {
			axismundi_cal_link_item_to_occurrence( (int) $item['id'], (int) $candidates[0]['id'] );
			++$matched;
		} elseif ( array() !== $candidates ) {
			++$ambiguous;
		}
	}
}
printf( "linked %d english rows, %d left ambiguous for review\n", $matched, $ambiguous );

$sample = axismundi_cal_system_items_in_range( 2258, '2026-02-15', '2026-03-05', array(), true );
foreach ( $sample as $item ) {
	$languages = axismundi_cal_occurrence_languages( (int) $item['holiday_occurrence_id'] );
	$names     = array();
	foreach ( $languages as $locale => $row ) {
		$names[] = $locale . ': ' . $row['title'];
	}
	printf( "  %s  %s\n", $item['start_date'], implode( ' | ', $names ) );
}
