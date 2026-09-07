<?php
/**
 * How an Event repeats, as a structure rather than a sentence.
 *
 * `RRULE:FREQ=WEEKLY;BYDAY=MO,WE` is a string that has to be parsed before anything can ask it a
 * question, and every reader that parsed it was a reader that could disagree about what it meant --
 * which already happened twice: a comma list read as one value, and an unsupported rule published as
 * though this site could expand it.
 *
 * So the stored fact is the JSCalendar recurrence rule, and the iCalendar sentence is generated from
 * it. That direction matters: a rule this site cannot expand can no longer be stored as canonical at
 * all, because the structure only holds what the expander understands.
 *
 * The raw `rrule` column stays written for now, and nothing reads it. It is provenance for imported
 * feeds -- where a rule we cannot work out is kept verbatim and marked unexpandable -- and it goes
 * when that path has its own home.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The structured rules for one schedule.
 *
 * Falls back to reading the sentence for rows written before the column existed, which is the
 * transition and is expected to go.
 *
 * @param array<string,mixed> $schedule Schedule row.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_schedule_recurrence( array $schedule ) : array {
	$stored = trim( (string) ( $schedule['recurrence_json'] ?? '' ) );
	if ( '' !== $stored ) {
		$decoded = json_decode( $stored, true );
		if ( is_array( $decoded ) && array() !== $decoded ) {
			return $decoded;
		}
	}
	return axismundi_cal_recurrence_from_rrule( (string) ( $schedule['rrule'] ?? '' ) );
}

/**
 * One iCalendar rule as the structure this site keeps.
 *
 * Empty for a rule that is absent, unreadable, or one the expander refuses -- which is deliberate:
 * a structure that held a rule nothing can expand would be a promise the calendar cannot keep.
 *
 * @param string $rrule Stored RRULE text.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_recurrence_from_rrule( string $rrule ) : array {
	$rule = axismundi_cal_jscalendar_recurrence_rule( $rrule );
	return null === $rule ? array() : array( $rule );
}

/**
 * The structured rules as the parts the expander works in.
 *
 * The expander's vocabulary is iCalendar's, so this is a translation rather than a second model --
 * and it is the only place the two vocabularies meet.
 *
 * @param array<int,array<string,mixed>> $rules Structured rules.
 * @return array<string,mixed> Rule parts, empty when there is no rule.
 */
function axismundi_cal_recurrence_to_parts( array $rules ) : array {
	$rule = $rules[0] ?? null;
	if ( ! is_array( $rule ) || empty( $rule['frequency'] ) ) {
		return array();
	}
	$parts = array( 'FREQ' => strtoupper( (string) $rule['frequency'] ) );
	if ( isset( $rule['interval'] ) && (int) $rule['interval'] > 1 ) {
		$parts['INTERVAL'] = (string) (int) $rule['interval'];
	}
	if ( isset( $rule['count'] ) ) {
		$parts['COUNT'] = (string) (int) $rule['count'];
	}
	if ( ! empty( $rule['until'] ) ) {
		$parts['UNTIL'] = str_replace( array( '-', ':' ), '', (string) $rule['until'] );
	}
	if ( ! empty( $rule['byDay'] ) ) {
		$parts['BYDAY'] = implode(
			',',
			array_map(
				static function ( array $day ) : string {
					$nth = isset( $day['nthOfPeriod'] ) ? (int) $day['nthOfPeriod'] : 0;
					return ( 0 !== $nth ? (string) $nth : '' ) . strtoupper( (string) ( $day['day'] ?? '' ) );
				},
				(array) $rule['byDay']
			)
		);
	}
	foreach ( array( 'byMonthDay' => 'BYMONTHDAY', 'byMonth' => 'BYMONTH' ) as $from => $to ) {
		if ( ! empty( $rule[ $from ] ) ) {
			$parts[ $to ] = implode( ',', array_map( 'strval', (array) $rule[ $from ] ) );
		}
	}
	if ( ! empty( $rule['firstDayOfWeek'] ) ) {
		$parts['WKST'] = strtoupper( (string) $rule['firstDayOfWeek'] );
	}
	return $parts;
}

/**
 * The structured rules written as an iCalendar sentence.
 *
 * Generated, not stored twice. A document and the occurrences a calendar shows cannot disagree if
 * one of them is derived from the other.
 *
 * @param array<int,array<string,mixed>> $rules Structured rules.
 * @return string
 */
function axismundi_cal_recurrence_to_rrule( array $rules ) : string {
	$parts = axismundi_cal_recurrence_to_parts( $rules );
	if ( array() === $parts ) {
		return '';
	}
	// The order iCalendar readers expect to see, and the order the stored text used, so a feed does
	// not appear to change merely because the rule moved into a structure.
	$order  = array( 'FREQ', 'INTERVAL', 'BYDAY', 'BYMONTHDAY', 'BYMONTH', 'WKST', 'COUNT', 'UNTIL' );
	$pieces = array();
	foreach ( $order as $key ) {
		if ( isset( $parts[ $key ] ) && '' !== (string) $parts[ $key ] ) {
			$pieces[] = $key . '=' . $parts[ $key ];
		}
	}
	return implode( ';', $pieces );
}

/**
 * Write down the structure of every rule that has none.
 *
 * @return int Rows filled.
 */
function axismundi_cal_backfill_recurrence() : int {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return 0;
	}
	$table = axismundi_cal_schedules_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration over this plugin's own table.
	$rows = (array) $wpdb->get_results( "SELECT id, rrule FROM {$table} WHERE recurrence_json = '' AND rrule <> ''", ARRAY_A );
	$filled = 0;
	foreach ( $rows as $row ) {
		$rules = axismundi_cal_recurrence_from_rrule( (string) $row['rrule'] );
		if ( array() === $rules ) {
			// A rule this site cannot expand keeps its text and gains no structure, which is the same
			// answer the importer gives: visible, and never expanded from a rule nothing understands.
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( $table, array( 'recurrence_json' => (string) wp_json_encode( $rules ) ), array( 'id' => (int) $row['id'] ), array( '%s' ), array( '%d' ) );
		++$filled;
	}
	return $filled;
}
