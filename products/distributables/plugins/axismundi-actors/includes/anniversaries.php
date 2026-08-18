<?php
/**
 * Actor anniversaries.
 *
 * `anniversaries` is a JSContact collection. Birth is one kind of entry, not a set of special
 * columns on a Person profile. An Actor owns the Gregorian date fact; Calendar later decides how
 * to repeat or display it in any secondary calendar.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACTORS_ANNIVERSARY_KINDS = array( 'birth', 'death', 'wedding', 'other' );
const AXISMUNDI_ACTORS_ANNIVERSARY_VISIBILITIES = array( 'none', 'month-day', 'full' );

/** @return array<string,string> */
function axismundi_actors_anniversary_kind_labels() : array {
	return array(
		'birth'   => __( 'Birth', 'axismundi-actors' ),
		'death'   => __( 'Death', 'axismundi-actors' ),
		'wedding' => __( 'Wedding', 'axismundi-actors' ),
		'other'   => __( 'Other', 'axismundi-actors' ),
	);
}

/** @return array<int,array<string,mixed>> */
function axismundi_actors_get_anniversaries( int $identity_id ) : array {
	global $wpdb;
	if ( $identity_id <= 0 ) {
		return array();
	}
	$table = axismundi_actors_anniversaries_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, anniversary_kind, year, month, day, visibility FROM {$table} WHERE identity_id = %d ORDER BY id ASC", $identity_id ), ARRAY_A );
	return array_map(
		static fn( array $row ) : array => array(
			'id'         => (int) $row['id'],
			'kind'       => (string) $row['anniversary_kind'],
			'year'       => (int) $row['year'],
			'month'      => (int) $row['month'],
			'day'        => (int) $row['day'],
			'visibility' => (string) $row['visibility'],
		),
		$rows
	);
}

/**
 * @param array<string,mixed> $item User input.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_actors_normalize_anniversary( array $item ) {
	$kind       = sanitize_key( (string) ( $item['kind'] ?? '' ) );
	$year       = absint( $item['year'] ?? 0 );
	$month      = absint( $item['month'] ?? 0 );
	$day        = absint( $item['day'] ?? 0 );
	$visibility = sanitize_key( (string) ( $item['visibility'] ?? 'none' ) );
	if ( ! in_array( $kind, AXISMUNDI_ACTORS_ANNIVERSARY_KINDS, true ) ) {
		return new WP_Error( 'ax_actors_anniversary_kind', __( 'Choose an anniversary kind.', 'axismundi-actors' ) );
	}
	if ( $month < 1 || $month > 12 || $day < 1 || ! checkdate( $month, $day, 2000 ) || ( 0 !== $year && $year > (int) gmdate( 'Y' ) ) ) {
		return new WP_Error( 'ax_actors_anniversary_date', __( 'Enter a valid anniversary date.', 'axismundi-actors' ) );
	}
	if ( ! in_array( $visibility, AXISMUNDI_ACTORS_ANNIVERSARY_VISIBILITIES, true ) ) {
		return new WP_Error( 'ax_actors_anniversary_visibility', __( 'Choose how much of this anniversary is public.', 'axismundi-actors' ) );
	}
	return array(
		'kind'       => $kind,
		'year'       => $year,
		'month'      => $month,
		'day'        => $day,
		'visibility' => $visibility,
	);
}

/**
 * @param int                           $identity_id Actor identity.
 * @param array<int,array<string,mixed>> $items Submitted rows.
 * @return true|WP_Error
 */
function axismundi_actors_replace_anniversaries( int $identity_id, array $items ) {
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() ) {
		return new WP_Error( 'ax_actors_anniversary_actor', __( 'Only a local Actor can own anniversaries.', 'axismundi-actors' ) );
	}
	$normalized = array();
	foreach ( $items as $item ) {
		if ( ! is_array( $item ) || ( empty( $item['kind'] ) && empty( $item['month'] ) && empty( $item['day'] ) ) ) {
			continue;
		}
		$row = axismundi_actors_normalize_anniversary( $item );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$normalized[] = $row;
	}
	global $wpdb;
	$table = axismundi_actors_anniversaries_table();
	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic replacement in plugin-owned table.
	$deleted = $wpdb->delete( $table, array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic replacement.
	if ( false === $deleted ) {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- transaction cleanup.
		return new WP_Error( 'ax_actors_anniversary_save', __( 'Could not save anniversaries.', 'axismundi-actors' ) );
	}
	$now = current_time( 'mysql', true );
	foreach ( $normalized as $row ) {
		$inserted = $wpdb->insert( $table, array( 'identity_id' => $identity_id, 'anniversary_kind' => $row['kind'], 'year' => $row['year'], 'month' => $row['month'], 'day' => $row['day'], 'visibility' => $row['visibility'], 'created_at' => $now, 'updated_at' => $now ), array( '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
		if ( false === $inserted ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- transaction cleanup.
			return new WP_Error( 'ax_actors_anniversary_save', __( 'Could not save anniversaries.', 'axismundi-actors' ) );
		}
	}
	$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic replacement complete.
	return true;
}

/** @return array<string,array<string,mixed>> Public JSContact values keyed by local ids. */
function axismundi_actors_public_anniversaries( int $identity_id ) : array {
	$out = array();
	foreach ( axismundi_actors_get_anniversaries( $identity_id ) as $anniversary ) {
		if ( 'none' === $anniversary['visibility'] ) {
			continue;
		}
		$date = array( 'month' => $anniversary['month'], 'day' => $anniversary['day'] );
		if ( 'full' === $anniversary['visibility'] && $anniversary['year'] > 0 ) {
			$date['year'] = $anniversary['year'];
		}
		$out[ 'a' . $anniversary['id'] ] = array( 'kind' => 'other' === $anniversary['kind'] ? 'x-axismundi-other' : $anniversary['kind'], 'date' => $date );
	}
	return $out;
}
