<?php
/**
 * Each local Actor's own Calendar, made when it is first needed.
 *
 * An Event has to go somewhere, and until now "somewhere" was a single site-wide `unfiled-events`
 * Calendar belonging to nobody. That was the wrong answer twice over: a Calendar with no authority
 * cannot be federated, shared or administered, and one shared Calendar for every author means the
 * first person to publish decides what everyone else's Events are attributed to.
 *
 * Made lazily rather than with the Actor. A site with two hundred registered accounts would
 * otherwise carry two hundred empty Calendars, every one of them a public URL and a subscribable
 * feed for a person who has never written an Event. The Calendar appears when somebody first needs
 * one, which is the moment its existence starts to mean something.
 *
 * `primary` is a property of the Calendar rather than a pointer on the Actor, because Actors is a
 * different plugin and knowing which calendar is somebody's default is not part of being an
 * identity. One primary per authority is the invariant; nothing here creates a second.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Calendar an Actor's Events go to by default, or null if they have none yet.
 *
 * @param string $actor_uri Actor URI.
 * @return array<string,mixed>|null
 */
function axismundi_cal_primary_calendar( string $actor_uri ) : ?array {
	global $wpdb;
	$actor_uri = trim( $actor_uri );
	if ( '' === $actor_uri || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE authority_actor_uri_hash = %s AND kind = 'local' AND is_primary = 1 ORDER BY id ASC LIMIT 1",
			hash( 'sha256', $actor_uri )
		),
		ARRAY_A
	);
	return is_array( $row ) ? $row : null;
}

/**
 * A timezone a Calendar can actually be created with.
 *
 * The site's own zone when it is a named one. A site configured with a fixed UTC offset has no DST
 * rules to expand a recurrence against, and `calendar_save()` refuses one for that reason -- so
 * rather than failing to create anybody a Calendar on such a site, this falls back to UTC and lets
 * them change it. Guessing a named zone from an offset would be inventing a DST history.
 *
 * @return string
 */
function axismundi_cal_default_calendar_timezone() : string {
	$zone = function_exists( 'wp_timezone_string' ) ? (string) wp_timezone_string() : '';
	return in_array( $zone, timezone_identifiers_list(), true ) ? $zone : 'UTC';
}

/**
 * A slug that is free, derived from what the Actor is called.
 *
 * `calendar_save()` refuses a taken slug rather than suffixing one, because a slug is a subscription
 * URL somebody may already hold. That is the right rule for a slug a person typed; here nobody typed
 * anything, so a collision has to be resolved rather than reported.
 *
 * @param string $base Preferred slug.
 * @return string
 */
function axismundi_cal_free_calendar_slug( string $base ) : string {
	$base = sanitize_title( $base );
	if ( '' === $base ) {
		$base = 'calendar';
	}
	$slug = $base;
	for ( $suffix = 2; $suffix < 100; $suffix++ ) {
		if ( null === axismundi_cal_calendar_by_slug( $slug ) ) {
			return $slug;
		}
		$slug = $base . '-' . $suffix;
	}
	return $base . '-' . wp_generate_password( 6, false, false );
}

/**
 * The Actor's primary Calendar, creating it if this is the first time it is needed.
 *
 * @param string $actor_uri Actor URI.
 * @return int|WP_Error Calendar id.
 */
function axismundi_cal_ensure_primary_calendar( string $actor_uri ) {
	global $wpdb;
	$actor_uri = trim( $actor_uri );
	if ( '' === $actor_uri ) {
		return new WP_Error(
			'ax_cal_no_actor',
			__( 'An Event needs an Actor to belong to. Set up your Actor before creating Events.', 'axismundi-calendar' ),
			array( 'status' => 409 )
		);
	}
	$existing = axismundi_cal_primary_calendar( $actor_uri );
	if ( is_array( $existing ) ) {
		return (int) $existing['id'];
	}

	$name   = __( 'Calendar', 'axismundi-calendar' );
	$handle = '';
	if ( function_exists( 'axismundi_actors_get_by_uri' ) ) {
		$actor = axismundi_actors_get_by_uri( $actor_uri );
		if ( $actor instanceof Axismundi_Actor ) {
			$handle  = trim( (string) $actor->get_preferred_username() );
			$display = trim( (string) $actor->get_display_name() );
			if ( '' !== $display ) {
				/* translators: %s: Actor display name. */
				$name = sprintf( __( '%s calendar', 'axismundi-calendar' ), $display );
			}
		}
	}

	$created = axismundi_cal_calendar_save(
		array(
			'name'            => $name,
			'slug'            => axismundi_cal_free_calendar_slug( ( '' !== $handle ? $handle : 'calendar' ) . '-calendar' ),
			'timezone'        => axismundi_cal_default_calendar_timezone(),
			'owner_actor_uri' => $actor_uri,
		)
	);
	if ( is_wp_error( $created ) ) {
		return $created;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update( axismundi_cal_calendars_table(), array( 'is_primary' => 1 ), array( 'id' => (int) $created ) );
	return (int) $created;
}

/**
 * Local Calendars that belong to nobody.
 *
 * Only the ones left behind by an upgrade: the writer has refused to create an authority-less
 * Calendar since v13, so this list is finite, shrinks as they are dealt with, and should normally be
 * empty. It is surfaced rather than repaired automatically because choosing whose Calendar an old
 * pile of Events becomes is somebody's decision, not a migration's.
 *
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_orphan_calendars() : array {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- inventory query over this plugin's own table.
	return (array) $wpdb->get_results( "SELECT * FROM {$table} WHERE kind = 'local' AND authority_actor_uri = '' ORDER BY id ASC", ARRAY_A );
}
