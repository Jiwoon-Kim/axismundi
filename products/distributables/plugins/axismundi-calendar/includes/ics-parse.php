<?php
/**
 * Reading somebody else's iCalendar.
 *
 * Defensive throughout, because the input is a document from a server this site does not control
 * and cannot ask to fix anything. A component that cannot be understood is skipped rather than
 * failing the whole feed: one malformed entry in a national holiday calendar should cost that entry,
 * not the other two hundred.
 *
 * Rules this parser cannot expand are kept verbatim with `expansion_supported = false`. A subscribed
 * feed is not ours to refuse -- refusing `BYSETPOS` here would silently drop somebody's events from
 * a calendar they are watching, which is the failure the authoring validator exists to avoid in the
 * other direction.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Unfold a document into logical lines.
 *
 * RFC 5545 folds long lines by inserting CRLF and a single space or tab, so a naive split on
 * newlines cuts values in half -- and does it only for the long ones, which is why a feed can look
 * fine until somebody writes a long description.
 *
 * @param string $body Document body.
 * @return string[]
 */
function axismundi_cal_ics_unfold( string $body ) : array {
	$body  = str_replace( array( "\r\n", "\r" ), "\n", $body );
	$lines = array();
	foreach ( explode( "\n", $body ) as $line ) {
		if ( '' !== $line && ( ' ' === $line[0] || "\t" === $line[0] ) ) {
			if ( ! empty( $lines ) ) {
				$lines[ count( $lines ) - 1 ] .= substr( $line, 1 );
				continue;
			}
		}
		$lines[] = $line;
	}
	return $lines;
}

/**
 * Split one content line into name, parameters and value.
 *
 * @param string $line Logical line.
 * @return array{name:string,params:array<string,string>,value:string}|null
 */
function axismundi_cal_ics_split_line( string $line ) {
	$colon = false;
	$quoted = false;
	$length = strlen( $line );
	for ( $i = 0; $i < $length; $i++ ) {
		$char = $line[ $i ];
		if ( '"' === $char ) {
			$quoted = ! $quoted;
			continue;
		}
		// A colon inside a quoted parameter value is data, not the separator -- which is how a
		// parameter containing a URL breaks a parser that splits on the first colon it sees.
		if ( ':' === $char && ! $quoted ) {
			$colon = $i;
			break;
		}
	}
	if ( false === $colon ) {
		return null;
	}
	$head  = substr( $line, 0, $colon );
	$value = substr( $line, $colon + 1 );
	$parts = explode( ';', $head );
	$name  = strtoupper( trim( (string) array_shift( $parts ) ) );
	if ( '' === $name ) {
		return null;
	}
	$params = array();
	foreach ( $parts as $part ) {
		if ( ! str_contains( $part, '=' ) ) {
			continue;
		}
		list( $key, $val ) = explode( '=', $part, 2 );
		$params[ strtoupper( trim( $key ) ) ] = trim( $val, " \"" );
	}
	return array( 'name' => $name, 'params' => $params, 'value' => $value );
}

/**
 * Reverse the text escaping of a value.
 *
 * @param string $value Escaped value.
 * @return string
 */
function axismundi_cal_ics_unescape( string $value ) : string {
	$out    = '';
	$length = strlen( $value );
	for ( $i = 0; $i < $length; $i++ ) {
		if ( '\\' === $value[ $i ] && $i + 1 < $length ) {
			$next = $value[ ++$i ];
			if ( 'n' === $next || 'N' === $next ) {
				$out .= "\n";
			} else {
				$out .= $next;
			}
			continue;
		}
		$out .= $value[ $i ];
	}
	return $out;
}

/**
 * Interpret a date or date-time value.
 *
 * Three forms exist and they mean different things: a bare date is the same day everywhere, a
 * `Z`-suffixed time is an instant, and a local time with `TZID` is a wall time in that zone. A
 * parser that treats them alike puts all-day entries an offset away from where they belong.
 *
 * @param string                $value  Raw value.
 * @param array<string,string>  $params Property parameters.
 * @param string                $fallback_tz Zone to assume for a floating time.
 * @return array{utc:string,local:string,all_day:bool,timezone:string}|null
 */
function axismundi_cal_ics_read_datetime( string $value, array $params, string $fallback_tz ) {
	$value = trim( $value );
	$utc   = new DateTimeZone( 'UTC' );

	if ( isset( $params['VALUE'] ) && 'DATE' === strtoupper( $params['VALUE'] ) ) {
		if ( ! preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $value, $m ) ) {
			return null;
		}
		try {
			$date = new DateTimeImmutable( "{$m[1]}-{$m[2]}-{$m[3]} 00:00:00", $utc );
		} catch ( Exception $error ) {
			return null;
		}
		return array(
			'utc'      => $date->format( 'Y-m-d H:i:s' ),
			'local'    => $date->format( 'Y-m-d H:i:s' ),
			'all_day'  => true,
			'timezone' => '',
		);
	}

	if ( ! preg_match( '/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})(Z)?$/', $value, $m ) ) {
		return null;
	}
	$zone_name = 'Z' === ( $m[7] ?? '' ) ? 'UTC' : ( $params['TZID'] ?? $fallback_tz );
	try {
		$zone = new DateTimeZone( '' !== $zone_name ? $zone_name : 'UTC' );
	} catch ( Exception $error ) {
		// An unknown TZID is a zone this server does not have. Read as UTC and recorded as unzoned
		// rather than dropped, since the entry is still worth showing.
		$zone      = $utc;
		$zone_name = '';
	}
	try {
		$local = new DateTimeImmutable( "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$m[6]}", $zone );
	} catch ( Exception $error ) {
		return null;
	}
	return array(
		'utc'      => $local->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
		'local'    => $local->format( 'Y-m-d H:i:s' ),
		'all_day'  => false,
		'timezone' => 'UTC' === $zone_name ? 'UTC' : (string) $zone_name,
	);
}

/**
 * Parse a document into entry rows.
 *
 * @param string $body Document body.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_ics_parse( string $body ) : array {
	$lines    = axismundi_cal_ics_unfold( $body );
	$entries  = array();
	$current  = null;
	$calscale = '';

	foreach ( $lines as $line ) {
		$parsed = axismundi_cal_ics_split_line( $line );
		if ( null === $parsed ) {
			continue;
		}
		$name  = $parsed['name'];
		$value = $parsed['value'];

		if ( 'BEGIN' === $name && 'VEVENT' === strtoupper( trim( $value ) ) ) {
			$current = array( 'params' => array() );
			continue;
		}
		if ( 'END' === $name && 'VEVENT' === strtoupper( trim( $value ) ) ) {
			if ( is_array( $current ) ) {
				$entry = axismundi_cal_ics_build_entry( $current, $calscale );
				if ( null !== $entry ) {
					$entries[] = $entry;
				}
			}
			$current = null;
			continue;
		}
		if ( null === $current ) {
			if ( 'CALSCALE' === $name ) {
				$calscale = strtoupper( trim( $value ) );
			}
			continue;
		}
		$current[ $name ]             = $value;
		$current['params'][ $name ]   = $parsed['params'];
	}

	return $entries;
}

/**
 * Turn one collected component into an entry row.
 *
 * @param array<string,mixed> $component Collected properties.
 * @param string              $calscale  Document calendar scale.
 * @return array<string,mixed>|null
 */
function axismundi_cal_ics_build_entry( array $component, string $calscale ) {
	$uid = trim( (string) ( $component['UID'] ?? '' ) );
	if ( '' === $uid || ! isset( $component['DTSTART'] ) ) {
		// Without a UID an entry cannot be recognised again on the next fetch, so it would be
		// re-created and re-deleted forever. Skipped rather than given one we invented.
		return null;
	}
	$params = (array) ( $component['params'] ?? array() );
	$start  = axismundi_cal_ics_read_datetime( (string) $component['DTSTART'], (array) ( $params['DTSTART'] ?? array() ), '' );
	if ( null === $start ) {
		return null;
	}

	$end = isset( $component['DTEND'] )
		? axismundi_cal_ics_read_datetime( (string) $component['DTEND'], (array) ( $params['DTEND'] ?? array() ), (string) $start['timezone'] )
		: null;
	if ( null === $end ) {
		// No end, or one that could not be read. An all-day entry runs to the next day; a timed one
		// with no end is treated as instantaneous, which is what RFC 5545 says.
		$span     = $start['all_day'] ? '+1 day' : '+0 seconds';
		$end_utc  = gmdate( 'Y-m-d H:i:s', strtotime( $start['utc'] . ' UTC ' . $span ) );
		$end      = array(
			'utc'   => $end_utc,
			'local' => gmdate( 'Y-m-d H:i:s', strtotime( $start['local'] . ' ' . $span ) ),
		);
	}

	$rrule = trim( (string) ( $component['RRULE'] ?? '' ) );
	$supported = true;
	if ( '' !== $rrule ) {
		/*
		 * Validated only to record whether this site can work the dates out, never to reject. A
		 * subscribed feed is not ours to refuse, and dropping the entry would remove somebody's
		 * event from a calendar they chose to watch. A rule we cannot expand keeps its text and is
		 * marked, so the series can be shown as present without inventing dates for it.
		 */
		$checked   = axismundi_cal_rrule_validate( $rrule );
		$supported = ! is_wp_error( $checked );
	}
	// A non-Gregorian scale changes what the rule means, and this engine expands Gregorian rules.
	if ( '' !== $calscale && 'GREGORIAN' !== $calscale ) {
		$supported = false;
	}

	$recurrence_id = '';
	if ( isset( $component['RECURRENCE-ID'] ) ) {
		$recurrence = axismundi_cal_ics_read_datetime( (string) $component['RECURRENCE-ID'], (array) ( $params['RECURRENCE-ID'] ?? array() ), (string) $start['timezone'] );
		if ( null !== $recurrence ) {
			$recurrence_id = $recurrence['all_day']
				? gmdate( 'Ymd', strtotime( $recurrence['local'] ) )
				: gmdate( 'Ymd\THis', strtotime( $recurrence['local'] ) );
		}
	}

	$status = strtoupper( trim( (string) ( $component['STATUS'] ?? '' ) ) );

	return array(
		'ical_uid'            => $uid,
		'recurrence_id'       => $recurrence_id,
		'summary'             => axismundi_cal_ics_unescape( (string) ( $component['SUMMARY'] ?? '' ) ),
		'location'            => axismundi_cal_ics_unescape( (string) ( $component['LOCATION'] ?? '' ) ),
		'url'                 => trim( (string) ( $component['URL'] ?? '' ) ),
		'timezone'            => (string) $start['timezone'],
		'all_day'             => $start['all_day'] ? 1 : 0,
		'start_utc'           => (string) $start['utc'],
		'end_utc'             => (string) $end['utc'],
		'start_local'         => (string) $start['local'],
		'end_local'           => (string) $end['local'],
		'rrule'               => $rrule,
		'expansion_supported' => $supported ? 1 : 0,
		'status'              => 'CANCELLED' === $status ? 'cancelled' : 'confirmed',
	);
}
