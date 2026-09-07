<?php
/**
 * The recurrence rule: parsed, validated against what this plugin can actually evaluate, and stored
 * normalized.
 *
 * Rules are refused at authoring time rather than accepted and half-understood. A rule we cannot
 * expand but store anyway produces a calendar that is confidently wrong -- the series renders, the
 * dates are invented, and nothing reports it. Refusing names the part that is unsupported, which is
 * something the author can act on.
 *
 * Stored normalized rather than verbatim so that change detection compares meaning. Two authors
 * writing `FREQ=WEEKLY;BYDAY=SA;INTERVAL=1` and `FREQ=WEEKLY;INTERVAL=1;BYDAY=SA` mean the same
 * recurrence; comparing the strings would call that a change, climb `SEQUENCE`, and make every
 * subscriber re-sync a calendar that never moved.
 *
 * Imported rules are the exception and are not routed through here: a subscribed feed is not ours to
 * refuse, and dropping its events would be a silent loss. Those keep their raw rule with
 * `expansion_supported = false` -- handled by the provider layer, not this file.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** The frequencies this plugin can expand. */
const AXISMUNDI_CAL_RRULE_FREQUENCIES = array( 'DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY' );

/** Weekday tokens, in RFC 5545 order from the week start. */
const AXISMUNDI_CAL_RRULE_WEEKDAYS = array( 'MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU' );

/**
 * Rule parts that are understood but deliberately not implemented.
 *
 * Listed rather than lumped into "unknown" so the refusal can say which one it was, and so adding
 * one later is a change in one place.
 */
const AXISMUNDI_CAL_RRULE_UNSUPPORTED = array( 'BYSETPOS', 'BYYEARDAY', 'BYWEEKNO', 'BYHOUR', 'BYMINUTE', 'BYSECOND' );

/** Rule parts this plugin evaluates. */
const AXISMUNDI_CAL_RRULE_SUPPORTED = array( 'FREQ', 'INTERVAL', 'COUNT', 'UNTIL', 'BYDAY', 'BYMONTHDAY', 'BYMONTH', 'WKST' );

/**
 * Parse and validate one RRULE, returning it normalized.
 *
 * @param string $rrule    Rule value, with or without the `RRULE:` prefix.
 * @param bool   $floating Whether DTSTART is a floating local time. When it carries a zone, RFC 5545
 *                         requires `UNTIL` in UTC, and a local `UNTIL` silently ends the series at
 *                         the wrong instant for every reader in another zone.
 * @return string|WP_Error Normalized rule, or the reason it was refused.
 */
function axismundi_cal_rrule_validate( string $rrule, bool $floating = false ) {
	$parts = axismundi_cal_rrule_parse( $rrule );
	if ( is_wp_error( $parts ) ) {
		return $parts;
	}
	$checked = axismundi_cal_rrule_check( $parts, $floating );
	if ( is_wp_error( $checked ) ) {
		return $checked;
	}
	return axismundi_cal_rrule_normalize( $checked );
}

/**
 * Split a rule into its parts without judging them.
 *
 * @param string $rrule Rule value.
 * @return array<string,string>|WP_Error
 */
function axismundi_cal_rrule_parse( string $rrule ) {
	$value = trim( $rrule );
	if ( 0 === stripos( $value, 'RRULE:' ) ) {
		$value = substr( $value, 6 );
	}
	$value = trim( $value );
	if ( '' === $value ) {
		return new WP_Error( 'ax_cal_rrule_empty', __( 'A recurrence rule is empty.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$parts = array();
	foreach ( explode( ';', $value ) as $segment ) {
		$segment = trim( $segment );
		if ( '' === $segment ) {
			continue;
		}
		if ( ! str_contains( $segment, '=' ) ) {
			return new WP_Error(
				'ax_cal_rrule_malformed',
				/* translators: %s: the malformed rule fragment. */
				sprintf( __( 'The recurrence rule could not be read: "%s" is not a NAME=VALUE part.', 'axismundi-calendar' ), $segment ),
				array( 'status' => 400 )
			);
		}
		list( $name, $part ) = explode( '=', $segment, 2 );
		$name                = strtoupper( trim( $name ) );
		if ( isset( $parts[ $name ] ) ) {
			return new WP_Error(
				'ax_cal_rrule_duplicate',
				/* translators: %s: rule part name. */
				sprintf( __( 'The recurrence rule repeats %s, which has no defined meaning.', 'axismundi-calendar' ), $name ),
				array( 'status' => 400 )
			);
		}
		$parts[ $name ] = strtoupper( trim( $part ) );
	}
	return $parts;
}

/**
 * Refuse anything this plugin cannot evaluate, and anything RFC 5545 forbids.
 *
 * @param array<string,string> $parts    Parsed parts.
 * @param bool                 $floating Whether DTSTART is a floating local time.
 * @return array<string,mixed>|WP_Error Typed parts.
 */
function axismundi_cal_rrule_check( array $parts, bool $floating ) {
	$unsupported = array_intersect( array_keys( $parts ), AXISMUNDI_CAL_RRULE_UNSUPPORTED );
	if ( ! empty( $unsupported ) ) {
		return new WP_Error(
			'ax_cal_rrule_unsupported',
			sprintf(
				/* translators: %s: comma-separated rule part names, e.g. "BYSETPOS". */
				__( 'This calendar cannot yet work out dates from %s, so the rule was not saved rather than shown with invented dates.', 'axismundi-calendar' ),
				implode( ', ', $unsupported )
			),
			array( 'status' => 400 )
		);
	}
	$unknown = array_diff( array_keys( $parts ), AXISMUNDI_CAL_RRULE_SUPPORTED );
	if ( ! empty( $unknown ) ) {
		return new WP_Error(
			'ax_cal_rrule_unknown',
			sprintf(
				/* translators: %s: comma-separated rule part names. */
				__( 'The recurrence rule contains parts this calendar does not recognise: %s.', 'axismundi-calendar' ),
				implode( ', ', $unknown )
			),
			array( 'status' => 400 )
		);
	}

	$freq = (string) ( $parts['FREQ'] ?? '' );
	if ( ! in_array( $freq, AXISMUNDI_CAL_RRULE_FREQUENCIES, true ) ) {
		return new WP_Error(
			'ax_cal_rrule_freq',
			__( 'A recurrence rule needs FREQ, one of DAILY, WEEKLY, MONTHLY or YEARLY.', 'axismundi-calendar' ),
			array( 'status' => 400 )
		);
	}

	// RFC 5545 allows at most one bound. Both together has no single reading, and the two readings
	// disagree about when the series ends.
	if ( isset( $parts['COUNT'], $parts['UNTIL'] ) ) {
		return new WP_Error(
			'ax_cal_rrule_bounds',
			__( 'A recurrence rule may end after a number of occurrences or on a date, but not both.', 'axismundi-calendar' ),
			array( 'status' => 400 )
		);
	}

	$checked = array( 'FREQ' => $freq );

	foreach ( array( 'INTERVAL', 'COUNT' ) as $numeric ) {
		if ( ! isset( $parts[ $numeric ] ) ) {
			continue;
		}
		if ( ! preg_match( '/^[0-9]+$/', $parts[ $numeric ] ) || (int) $parts[ $numeric ] < 1 ) {
			return new WP_Error(
				'ax_cal_rrule_positive',
				/* translators: %s: rule part name. */
				sprintf( __( '%s must be a whole number of at least 1.', 'axismundi-calendar' ), $numeric ),
				array( 'status' => 400 )
			);
		}
		$checked[ $numeric ] = (int) $parts[ $numeric ];
	}

	if ( isset( $parts['UNTIL'] ) ) {
		$until = axismundi_cal_rrule_check_until( $parts['UNTIL'], $floating );
		if ( is_wp_error( $until ) ) {
			return $until;
		}
		$checked['UNTIL'] = $until;
	}

	if ( isset( $parts['WKST'] ) ) {
		if ( ! in_array( $parts['WKST'], AXISMUNDI_CAL_RRULE_WEEKDAYS, true ) ) {
			return new WP_Error( 'ax_cal_rrule_wkst', __( 'WKST must be a weekday such as MO.', 'axismundi-calendar' ), array( 'status' => 400 ) );
		}
		$checked['WKST'] = $parts['WKST'];
	}

	if ( isset( $parts['BYMONTH'] ) ) {
		$months = axismundi_cal_rrule_check_integers( $parts['BYMONTH'], 1, 12, false, 'BYMONTH' );
		if ( is_wp_error( $months ) ) {
			return $months;
		}
		$checked['BYMONTH'] = $months;
	}

	if ( isset( $parts['BYMONTHDAY'] ) ) {
		$days = axismundi_cal_rrule_check_integers( $parts['BYMONTHDAY'], 1, 31, true, 'BYMONTHDAY' );
		if ( is_wp_error( $days ) ) {
			return $days;
		}
		$checked['BYMONTHDAY'] = $days;
	}

	if ( isset( $parts['BYDAY'] ) ) {
		$byday = axismundi_cal_rrule_check_byday( $parts['BYDAY'], $freq );
		if ( is_wp_error( $byday ) ) {
			return $byday;
		}
		$checked['BYDAY'] = $byday;
	}

	return $checked;
}

/**
 * `UNTIL` must be a date, or a UTC date-time when the start carries a zone.
 *
 * @param string $value    Raw value.
 * @param bool   $floating Whether DTSTART is a floating local time.
 * @return string|WP_Error
 */
function axismundi_cal_rrule_check_until( string $value, bool $floating ) {
	if ( preg_match( '/^[0-9]{8}$/', $value ) ) {
		return $value;
	}
	if ( ! preg_match( '/^([0-9]{8})T([0-9]{6})(Z?)$/', $value, $matches ) ) {
		return new WP_Error( 'ax_cal_rrule_until', __( 'UNTIL must be a date such as 20261231, or a date-time such as 20261231T235959Z.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( ! $floating && 'Z' !== $matches[3] ) {
		// A local UNTIL against a zoned start ends the series at a different instant for every
		// reader, so RFC 5545 requires UTC here.
		return new WP_Error( 'ax_cal_rrule_until_utc', __( 'UNTIL must be given in UTC (ending in Z) when the event has a timezone.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	return $value;
}

/**
 * Parse a comma-separated integer list within bounds.
 *
 * @param string $value    Raw value.
 * @param int    $min      Minimum magnitude.
 * @param int    $max      Maximum magnitude.
 * @param bool   $negative Whether negative values (counting from the end) are meaningful.
 * @param string $name     Rule part name, for the message.
 * @return int[]|WP_Error
 */
function axismundi_cal_rrule_check_integers( string $value, int $min, int $max, bool $negative, string $name ) {
	$out = array();
	foreach ( explode( ',', $value ) as $item ) {
		$item = trim( $item );
		if ( ! preg_match( '/^[+-]?[0-9]+$/', $item ) ) {
			return new WP_Error(
				'ax_cal_rrule_integer',
				/* translators: 1: rule part name, 2: the offending value. */
				sprintf( __( '%1$s expects whole numbers, and "%2$s" is not one.', 'axismundi-calendar' ), $name, $item ),
				array( 'status' => 400 )
			);
		}
		$number = (int) $item;
		if ( 0 === $number || abs( $number ) < $min || abs( $number ) > $max || ( ! $negative && $number < 0 ) ) {
			return new WP_Error(
				'ax_cal_rrule_range',
				/* translators: 1: rule part name, 2: the offending value. */
				sprintf( __( '%1$s is out of range at "%2$s".', 'axismundi-calendar' ), $name, $item ),
				array( 'status' => 400 )
			);
		}
		$out[] = $number;
	}
	return $out;
}

/**
 * Parse BYDAY, allowing an ordinal prefix only where it means something.
 *
 * `1SA` answers "the first Saturday of the month", which is only a question MONTHLY and YEARLY ask.
 * In a WEEKLY rule there is one Saturday to choose from, so RFC 5545 forbids the ordinal there --
 * accepting it would mean guessing what the author wanted.
 *
 * @param string $value Raw value.
 * @param string $freq  Frequency.
 * @return array<int,array{ordinal:int|null,day:string}>|WP_Error
 */
function axismundi_cal_rrule_check_byday( string $value, string $freq ) {
	$ordinal_allowed = in_array( $freq, array( 'MONTHLY', 'YEARLY' ), true );
	$out             = array();
	foreach ( explode( ',', $value ) as $item ) {
		$item = trim( $item );
		if ( ! preg_match( '/^([+-]?[0-9]{1,2})?(MO|TU|WE|TH|FR|SA|SU)$/', $item, $matches ) ) {
			return new WP_Error(
				'ax_cal_rrule_byday',
				/* translators: %s: the offending value. */
				sprintf( __( 'BYDAY expects weekdays such as SA or 1SA, and "%s" is not one.', 'axismundi-calendar' ), $item ),
				array( 'status' => 400 )
			);
		}
		$ordinal = ( '' === $matches[1] ) ? null : (int) $matches[1];
		if ( null !== $ordinal && ! $ordinal_allowed ) {
			return new WP_Error(
				'ax_cal_rrule_byday_ordinal',
				sprintf(
					/* translators: 1: the offending value, 2: the frequency. */
					__( '"%1$s" counts weeks within a month, which %2$s rules do not do. Use MONTHLY or YEARLY, or drop the number.', 'axismundi-calendar' ),
					$item,
					$freq
				),
				array( 'status' => 400 )
			);
		}
		if ( null !== $ordinal && ( 0 === $ordinal || abs( $ordinal ) > 53 ) ) {
			return new WP_Error(
				'ax_cal_rrule_byday_range',
				/* translators: %s: the offending value. */
				sprintf( __( 'BYDAY is out of range at "%s".', 'axismundi-calendar' ), $item ),
				array( 'status' => 400 )
			);
		}
		$out[] = array( 'ordinal' => $ordinal, 'day' => $matches[2] );
	}
	return $out;
}

/**
 * Render checked parts in one canonical order and spelling.
 *
 * Defaults are dropped rather than written out: `INTERVAL=1` and `WKST=MO` are what RFC 5545 means
 * by their absence, so keeping them would make two identical rules compare unequal.
 *
 * @param array<string,mixed> $checked Checked parts.
 * @return string
 */
function axismundi_cal_rrule_normalize( array $checked ) : string {
	$out = array( 'FREQ=' . $checked['FREQ'] );

	if ( isset( $checked['INTERVAL'] ) && 1 !== $checked['INTERVAL'] ) {
		$out[] = 'INTERVAL=' . $checked['INTERVAL'];
	}
	if ( isset( $checked['COUNT'] ) ) {
		$out[] = 'COUNT=' . $checked['COUNT'];
	}
	if ( isset( $checked['UNTIL'] ) ) {
		$out[] = 'UNTIL=' . $checked['UNTIL'];
	}
	if ( isset( $checked['BYMONTH'] ) ) {
		$months = $checked['BYMONTH'];
		sort( $months );
		$out[] = 'BYMONTH=' . implode( ',', array_unique( $months ) );
	}
	if ( isset( $checked['BYMONTHDAY'] ) ) {
		$days = array_unique( $checked['BYMONTHDAY'] );
		// Days from the start ascending, then days from the end, so -1 reads as "last".
		usort(
			$days,
			static function ( int $a, int $b ) : int {
				if ( ( $a > 0 ) !== ( $b > 0 ) ) {
					return $a > 0 ? -1 : 1;
				}
				return $a <=> $b;
			}
		);
		$out[] = 'BYMONTHDAY=' . implode( ',', $days );
	}
	if ( isset( $checked['BYDAY'] ) ) {
		$byday = $checked['BYDAY'];
		usort(
			$byday,
			static function ( array $a, array $b ) : int {
				$ordinal = ( $a['ordinal'] ?? 0 ) <=> ( $b['ordinal'] ?? 0 );
				if ( 0 !== $ordinal ) {
					return $ordinal;
				}
				return array_search( $a['day'], AXISMUNDI_CAL_RRULE_WEEKDAYS, true )
					<=> array_search( $b['day'], AXISMUNDI_CAL_RRULE_WEEKDAYS, true );
			}
		);
		$tokens = array();
		foreach ( $byday as $entry ) {
			$token = ( null === $entry['ordinal'] ? '' : (string) $entry['ordinal'] ) . $entry['day'];
			if ( ! in_array( $token, $tokens, true ) ) {
				$tokens[] = $token;
			}
		}
		$out[] = 'BYDAY=' . implode( ',', $tokens );
	}
	if ( isset( $checked['WKST'] ) && 'MO' !== $checked['WKST'] ) {
		$out[] = 'WKST=' . $checked['WKST'];
	}

	return implode( ';', $out );
}
