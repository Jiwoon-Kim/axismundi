<?php
/**
 * One-off: attach the Korean substitute days to the holidays they stand in for.
 *
 * The import left each `쉬는 날 X` as a holiday of its own, because the feed does not say what it
 * replaces and guessing would have recorded a false relation. It is legible from the naming, which is
 * a judgement a person makes -- this file records it rather than leaving it clicked away.
 *
 * Moving the occurrence moves every language with it: the English row hangs off the same day, so
 * `Day off for Independence Movement Day` follows without being touched.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;

$ko      = axismundi_cal_calendar_get( 2258 );
$catalog = (int) $ko['holiday_catalog_id'];
$byLabel = array();
foreach ( axismundi_cal_holiday_concepts( $catalog ) as $concept ) {
	$byLabel[ (string) $concept['label'] ] = (int) $concept['id'];
}

$moved  = 0;
$orphan = array();
foreach ( $byLabel as $label => $concept_id ) {
	if ( ! str_starts_with( $label, '쉬는 날 ' ) ) {
		continue;
	}
	$target_label = trim( substr( $label, strlen( '쉬는 날 ' ) ) );
	if ( ! isset( $byLabel[ $target_label ] ) ) {
		printf( "no holiday named %s to stand in for\n", $target_label );
		continue;
	}
	$target = $byLabel[ $target_label ];

	foreach ( axismundi_cal_holiday_occurrences( $concept_id ) as $occurrence ) {
		/*
		 * The day it stands in for: the principal day of the same holiday in the same year. Recorded
		 * as a relation rather than a flag, so a screen can say why the 2nd is a holiday.
		 */
		$stands_for = 0;
		foreach ( axismundi_cal_holiday_occurrences( $target, (int) $occurrence['batch_year'] ) as $candidate ) {
			if ( 'principal' === (string) $candidate['role'] ) {
				$stands_for = (int) $candidate['id'];
			}
		}
		$result = axismundi_cal_holiday_occurrence_save(
			$target,
			array( 'role' => 'substitute', 'substitute_for' => $stands_for ),
			(int) $occurrence['id']
		);
		if ( ! is_wp_error( $result ) ) {
			++$moved;
			printf( "  %s → %s, standing in for %s\n", $occurrence['start_date'], $target_label, $stands_for > 0 ? axismundi_cal_holiday_occurrence_get( $stands_for )['start_date'] : 'nothing recorded' );
		}
	}
	$orphan[] = $concept_id;
}

// The holidays that only existed because a substitute had nowhere to belong.
foreach ( $orphan as $concept_id ) {
	if ( array() === axismundi_cal_holiday_occurrences( $concept_id ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->delete( axismundi_cal_holiday_concepts_table(), array( 'id' => $concept_id ) );
	}
}
printf( "moved %d substitute days, removed %d holidays that held only them\n", $moved, count( $orphan ) );

foreach ( axismundi_cal_system_items_in_range( 2258, '2026-03-01', '2026-03-05', array(), true ) as $item ) {
	$occurrence = axismundi_cal_holiday_occurrence_get( (int) $item['holiday_occurrence_id'] );
	$concept    = is_array( $occurrence ) ? axismundi_cal_holiday_concept_get( (int) $occurrence['concept_id'] ) : null;
	$languages  = array();
	foreach ( axismundi_cal_occurrence_languages( (int) $item['holiday_occurrence_id'] ) as $locale => $row ) {
		$languages[] = $locale . ': ' . $row['title'];
	}
	printf(
		"  %s  %-10s %-14s %s\n",
		$item['start_date'],
		is_array( $concept ) ? $concept['label'] : '?',
		is_array( $occurrence ) ? $occurrence['role'] : '?',
		implode( ' | ', $languages )
	);
}
