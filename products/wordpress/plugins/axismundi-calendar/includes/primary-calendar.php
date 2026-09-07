<?php
/**
 * Each local Actor's own Calendar.
 *
 * An Event has to go somewhere, and until now "somewhere" was a single site-wide `unfiled-events`
 * Calendar belonging to nobody. That was the wrong answer twice over: a Calendar with no authority
 * cannot be federated, shared or administered, and one shared Calendar for every author means the
 * first person to publish decides what everyone else's Events are attributed to.
 *
 * Made with the Actor rather than with its first Event. A calendar people subscribe to is an
 * address, and an address that appears the moment somebody happens to write something is one whose
 * identity was settled by an unrelated act -- worse, by a request that had every reason to fail
 * halfway. Every Actor that has locked a handle has this Calendar, from the moment it has a handle
 * to name it.
 *
 * The address is the handle: `/calendar/@jiwoon`, `/calendar/@jiwoon.ics`. Prefixing it puts an
 * Actor's own Calendar in a namespace nothing else can enter -- `sanitize_title()` strips `@` from
 * anything a person types -- so it needs no collision suffix, and needing no suffix is what lets it
 * be promised as permanent. A suffix would mean the second Actor called `jiwoon` quietly published
 * under a different address than the first, decided by whoever wrote an Event first.
 *
 * What is NOT fixed is what it is called. The name is a projection of the Actor's display name,
 * stored as NULL and resolved when read, so renaming yourself renames your calendar and no copy
 * drifts. An administrator may still type a name over it, and then that is what the site calls it.
 *
 * `primary` is a property of the Calendar rather than a pointer on the Actor, because Actors is a
 * different plugin and knowing which calendar is somebody's default is not part of being an
 * identity. One primary per authority is the invariant; nothing here creates a second.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The reserved address of an Actor's own Calendar.
 *
 * @param string $handle Locked Actor handle.
 * @return string Slug, or '' for a handle that is not one.
 */
function axismundi_cal_actor_calendar_slug( string $handle ) : string {
	$handle = strtolower( trim( $handle ) );
	// The Actors handle grammar, restated rather than borrowed: this decides a URL, so a change
	// there must be a decision here too and not a silent widening of what may address a calendar.
	return '' !== $handle && 1 === preg_match( '/^[a-z0-9](?:[a-z0-9_]{0,28}[a-z0-9])?$/', $handle ) ? '@' . $handle : '';
}

/**
 * The handle an Actor-Calendar slug names, or '' if the slug is an ordinary one.
 *
 * @param string $slug Calendar slug.
 * @return string
 */
function axismundi_cal_calendar_slug_handle( string $slug ) : string {
	$slug = trim( $slug );
	return str_starts_with( $slug, '@' ) ? substr( $slug, 1 ) : '';
}

/**
 * Sanitize a calendar slug, keeping the Actor prefix that `sanitize_title()` would eat.
 *
 * @param string $raw Submitted slug.
 * @return string
 */
function axismundi_cal_sanitize_calendar_slug( string $raw ) : string {
	$raw = trim( $raw );
	return str_starts_with( $raw, '@' )
		? '@' . sanitize_title( substr( $raw, 1 ) )
		: sanitize_title( $raw );
}

/**
 * Whether one slug is the reserved address of the Actor that would hold this Calendar.
 *
 * The check every caller of the reserved namespace passes through, so "only an Actor's own calendar
 * may live at `@handle`" is one rule rather than a convention each writer re-implements.
 *
 * @param string $slug          Calendar slug.
 * @param string $authority_uri Actor URI the Calendar belongs to.
 * @return bool
 */
function axismundi_cal_slug_belongs_to_actor( string $slug, string $authority_uri ) : bool {
	$handle = axismundi_cal_calendar_slug_handle( $slug );
	if ( '' === $handle || '' === trim( $authority_uri ) || ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
		return false;
	}
	$actor = axismundi_actors_get_by_uri( trim( $authority_uri ) );
	return $actor instanceof Axismundi_Actor
		&& $actor->is_local()
		&& $actor->is_handle_locked()
		&& axismundi_cal_actor_calendar_slug( (string) $actor->get_preferred_username() ) === $slug;
}

/**
 * Whether this row is an Actor's own Calendar -- the one that may not be renamed away, demoted or
 * deleted while the Actor exists.
 *
 * Asked of the address rather than of `is_primary`, because `is_primary` is a choice somebody may
 * have made about an ordinary Calendar and this is a different claim: that the Calendar *is* the
 * Actor's, at the address its handle reserves.
 *
 * @param array<string,mixed>|null $calendar Calendar row.
 * @return bool
 */
function axismundi_cal_is_actor_calendar( ?array $calendar ) : bool {
	return is_array( $calendar )
		&& 'local' === (string) ( $calendar['kind'] ?? '' )
		&& axismundi_cal_slug_belongs_to_actor( (string) ( $calendar['slug'] ?? '' ), (string) ( $calendar['authority_actor_uri'] ?? '' ) );
}

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
 * The Actor's own Calendar, creating it if it is somehow missing.
 *
 * Ordinarily this finds one, because activation made it. It still creates, because an Actor that
 * locked its handle before this plugin was installed never had that moment.
 *
 * @param string $actor_uri Actor URI.
 * @return int|WP_Error Calendar id.
 */
function axismundi_cal_ensure_primary_calendar( string $actor_uri ) {
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
	if ( ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
		return new WP_Error( 'ax_cal_no_actors', __( 'Actor identities are unavailable.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	$actor = axismundi_actors_get_by_uri( $actor_uri );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_cal_no_actor', __( 'That Actor does not exist here.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	return axismundi_cal_create_actor_calendar( $actor );
}

/**
 * Make one Actor's own Calendar.
 *
 * Refuses an Actor with no locked handle rather than inventing an address for it: the handle is
 * what the address is, and a Calendar created before there is one could only be given a name that
 * would have to change later -- which is the promise this contract exists to keep.
 *
 * @param Axismundi_Actor $actor Local Actor.
 * @return int|WP_Error Calendar id.
 */
function axismundi_cal_create_actor_calendar( Axismundi_Actor $actor ) {
	if ( ! axismundi_cal_ready() ) {
		return new WP_Error( 'ax_cal_store', __( 'The calendar store is unavailable.', 'axismundi-calendar' ) );
	}
	if ( ! $actor->is_local() || ! $actor->is_handle_locked() ) {
		return new WP_Error( 'ax_cal_actor_handle', __( 'An Actor needs a registered handle before it has a calendar.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	$actor_uri = (string) $actor->get_uri();
	$existing  = axismundi_cal_primary_calendar( $actor_uri );
	if ( is_array( $existing ) ) {
		return (int) $existing['id'];
	}
	$slug = axismundi_cal_actor_calendar_slug( (string) $actor->get_preferred_username() );
	if ( '' === $slug ) {
		return new WP_Error( 'ax_cal_actor_handle', __( 'That Actor handle cannot address a calendar.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	$created = axismundi_cal_calendar_save(
		array(
			// No name. The Actor's display name is read through `calendar_display_name()`, so a rename
			// carries and no second copy of it exists to disagree.
			'slug'            => $slug,
			'timezone'        => axismundi_cal_default_calendar_timezone(),
			'owner_actor_uri' => $actor_uri,
		)
	);
	if ( is_wp_error( $created ) ) {
		return $created;
	}
	axismundi_cal_set_primary( (int) $created, true );
	return (int) $created;
}

/**
 * Give an Actor its Calendar the moment it has a handle to address one by.
 *
 * @param int    $identity_id Actor identity.
 * @param string $handle      Registered handle.
 * @return void
 */
function axismundi_cal_actor_handle_registered( int $identity_id, string $handle ) : void {
	if ( ! axismundi_cal_ready() || ! function_exists( 'axismundi_actors_get_by_identity' ) ) {
		return;
	}
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( $actor instanceof Axismundi_Actor && $actor->is_local() ) {
		// Best effort: an Actor is not left unactivated because its calendar could not be written.
		axismundi_cal_create_actor_calendar( $actor );
	}
}
add_action( 'axismundi_actors_handle_registered', 'axismundi_cal_actor_handle_registered', 10, 2 );

/**
 * Give the Actors that locked a handle before this contract existed the Calendar they would have
 * been given, and move a lazily-made one to the address it should always have had.
 *
 * A slug is a subscription URL, so an existing one is moved only when the reserved address is free;
 * a collision is left alone and reported rather than resolved by inventing a third address.
 *
 * @return array{created:int,moved:int,skipped:int}
 */
function axismundi_cal_backfill_actor_calendars() : array {
	$result = array( 'created' => 0, 'moved' => 0, 'skipped' => 0 );
	if ( ! axismundi_cal_ready() || ! function_exists( 'axismundi_actors_get_by_identity' ) ) {
		return $result;
	}
	global $wpdb;
	$identities = axismundi_actors_identities_table();
	$actors     = axismundi_actors_actors_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time inventory across the identity registry.
	$ids = (array) $wpdb->get_col(
		"SELECT a.identity_id FROM {$actors} a
		 INNER JOIN {$identities} i ON i.id = a.identity_id
		 WHERE i.origin = 'local' AND a.actor_scope IN ('user','managed') AND a.handle_locked_at IS NOT NULL
		 ORDER BY a.identity_id ASC"
	);
	foreach ( $ids as $identity_id ) {
		$actor = axismundi_actors_get_by_identity( (int) $identity_id );
		if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_handle_locked() ) {
			continue;
		}
		$wanted   = axismundi_cal_actor_calendar_slug( (string) $actor->get_preferred_username() );
		$existing = axismundi_cal_primary_calendar( (string) $actor->get_uri() );
		if ( ! is_array( $existing ) ) {
			$result[ is_wp_error( axismundi_cal_create_actor_calendar( $actor ) ) ? 'skipped' : 'created' ]++;
			continue;
		}
		if ( '' === $wanted || $wanted === (string) $existing['slug'] ) {
			continue;
		}
		if ( null !== axismundi_cal_calendar_by_slug( $wanted ) ) {
			++$result['skipped'];
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( axismundi_cal_calendars_table(), array( 'slug' => $wanted ), array( 'id' => (int) $existing['id'] ), array( '%s' ), array( '%d' ) );
		++$result['moved'];
	}
	return $result;
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
