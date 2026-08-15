<?php
/**
 * The calendars this plugin maintains itself.
 *
 * Moon phases are not a dataset somebody curates. Nobody reviews them, nobody translates them, and
 * there is no version of the answer that belongs to this site rather than to arithmetic -- so asking
 * an administrator to create a calendar, name it, and choose what it holds is asking them to make
 * four decisions that have one correct answer each. These exist from installation.
 *
 * They are not, however, compulsory. A switch on the system calendar screen decides whether this site
 * produces each one, and that switch is the only record of the answer: an earlier version inferred it
 * from "has this ever been provisioned", which had to be asked because there was nowhere to say it
 * outright. Two mechanisms answering one question is how they come to disagree, and the disagreement
 * reads as a calendar that will not go away.
 *
 * Switching one off removes it rather than hiding it. These rows are a materialized view of
 * arithmetic, so nothing is lost that cannot be restated, and a calendar left behind with its
 * maintenance stopped is the worst available state -- still subscribable, quietly ceasing to be true
 * at its far edge, and looking no different from a working one.
 *
 * The name is not stored, for the reason a moon phase's name is not stored: it is a translation.
 * `Moon phases` in the catalog is read as 달의 위상 by a Korean reader and stays correct when the
 * site changes language, where a string written into the row at installation would be frozen in
 * whatever language happened to be active that day. An administrator who types a name over it is
 * making a different statement -- this is what we call it here -- and that one is stored and wins.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** The scheduled job that keeps each managed calendar's window current. */
const AXISMUNDI_CAL_MAINTENANCE_HOOK = 'ax_cal_maintain_managed';

/**
 * The calendars this plugin knows how to maintain.
 *
 * Keyed by a stable identifier that never reaches a reader: it names the row for the code, the way
 * a category key names an entry, and the label is produced from it at read time.
 *
 * Everything here is computed from the site's own arithmetic. That is what makes them provisionable
 * without asking anybody anything -- there is no region to choose, no source language to declare and
 * no feed to reach, so there is no question whose answer could be wrong.
 */
const AXISMUNDI_CAL_MANAGED_CALENDARS = array(
	'moon-phases'          => array(
		'provider'   => 'astronomy',
		'slug'       => 'moon-phases',
		'categories' => array( 'ASTRONOMY' ),
		'available'  => true,
		'default_on' => true,
	),
	/*
	 * Declared before they can be produced, and listed on the settings screen as unavailable. A dataset
	 * that is simply absent reads as one this plugin has no opinion about, which would leave somebody
	 * looking for equinoxes with nothing to conclude. `available` is what a generator existing means;
	 * turning one of these on is refused until it does.
	 */
	'equinox-solstice'     => array(
		'provider'   => 'astronomy',
		'slug'       => 'equinoxes-solstices',
		'categories' => array( 'ASTRONOMY' ),
		'available'  => true,
		'default_on' => true,
	),
	'lunar-eclipses'       => array(
		'provider'   => 'astronomy',
		'slug'       => 'lunar-eclipses',
		'categories' => array( 'ASTRONOMY' ),
		'available'  => false,
		'default_on' => false,
	),
);

/** Which managed calendars an administrator has switched on or off. */
const AXISMUNDI_CAL_MANAGED_ENABLED_OPTION = 'ax_cal_managed_enabled';

/**
 * What one managed calendar is called, in the language it is being read in.
 *
 * Translated here rather than stored, which is the same rule the phase names follow one level down.
 *
 * @param string $key Managed calendar key.
 * @return string
 */
function axismundi_cal_managed_calendar_name( string $key ) : string {
	$names = array(
		'moon-phases'      => __( 'Moon phases', 'axismundi-calendar' ),
		'equinox-solstice' => __( 'Equinoxes and solstices', 'axismundi-calendar' ),
		'lunar-eclipses'   => __( 'Lunar eclipses', 'axismundi-calendar' ),
	);
	return $names[ $key ] ?? '';
}

/**
 * A one-line account of what a managed calendar holds.
 *
 * @param string $key Managed calendar key.
 * @return string
 */
function axismundi_cal_managed_calendar_description( string $key ) : string {
	$descriptions = array(
		'moon-phases'      => __( 'New moon, first quarter, full moon and last quarter, computed rather than fetched.', 'axismundi-calendar' ),
		'equinox-solstice' => __( 'The two equinoxes and the two solstices each year, as instants rather than dates.', 'axismundi-calendar' ),
		'lunar-eclipses'   => __( 'Penumbral, partial and total lunar eclipses, with the moment of greatest eclipse.', 'axismundi-calendar' ),
	);
	return $descriptions[ $key ] ?? '';
}

/**
 * What one Calendar is called.
 *
 * The single answer, so no two surfaces can disagree. A stored name wins because somebody wrote it
 * on purpose: an administrator renaming the moon phases calendar to 음력 참고 is saying what this
 * site calls it, and no generated label should overrule that.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return string
 */
function axismundi_cal_calendar_display_name( array $calendar ) : string {
	$name = trim( (string) ( $calendar['name'] ?? '' ) );
	if ( '' !== $name ) {
		return $name;
	}
	$generated = axismundi_cal_managed_calendar_name( (string) ( $calendar['managed_key'] ?? '' ) );
	if ( '' !== $generated ) {
		return $generated;
	}
	/*
	 * An Actor's own calendar is called what the Actor is called, resolved here rather than copied at
	 * creation: somebody who renames themselves has renamed their calendar, and a stored copy would
	 * only be the name they used to have. The handle is the fallback because it is the one name the
	 * calendar's own address already promises.
	 */
	$handle = axismundi_cal_calendar_slug_handle( (string) ( $calendar['slug'] ?? '' ) );
	if ( '' !== $handle && function_exists( 'axismundi_actors_get_by_uri' ) ) {
		$actor = axismundi_actors_get_by_uri( (string) ( $calendar['authority_actor_uri'] ?? '' ) );
		if ( $actor instanceof Axismundi_Actor ) {
			$display = trim( (string) $actor->get_display_name() );
			return '' !== $display ? $display : '@' . $handle;
		}
		return '@' . $handle;
	}
	return __( 'Untitled calendar', 'axismundi-calendar' );
}

/**
 * Bring one managed calendar's contents into line with its window.
 *
 * The dispatch, in one place. Each generator owns its own rows -- scoped by `source_uid` -- so two of
 * them can share a calendar without pruning each other, and adding a third is a case here rather than
 * an edit to the scheduler, the provisioner and the job.
 *
 * @param string              $key      Managed calendar key.
 * @param array<string,mixed> $calendar Calendar row.
 * @return void
 */
function axismundi_cal_maintain_managed_calendar( string $key, array $calendar ) : void {
	switch ( $key ) {
		case 'moon-phases':
			axismundi_cal_maintain_moon_phases( (int) $calendar['id'] );
			break;
		case 'equinox-solstice':
			axismundi_cal_maintain_seasons( (int) $calendar['id'] );
			break;
	}
}

/**
 * When one managed calendar next needs attention.
 *
 * @param string              $key      Managed calendar key.
 * @param array<string,mixed> $calendar Calendar row.
 * @return int Timestamp, or 0 for immediately.
 */
function axismundi_cal_managed_calendar_next_maintenance( string $key, array $calendar ) : int {
	switch ( $key ) {
		case 'moon-phases':
			return axismundi_cal_moon_phase_next_maintenance( (int) $calendar['id'] );
		case 'equinox-solstice':
			return axismundi_cal_season_next_maintenance( (int) $calendar['id'] );
	}
	return 0;
}

/**
 * Where one managed calendar's rendered subscription document is kept.
 *
 * @param string $key Managed calendar key.
 * @return string Option name.
 */
function axismundi_cal_managed_ics_option( string $key ) : string {
	return 'ax_cal_managed_ics_' . $key;
}

/**
 * What the stored document would have to have been built from to still be right.
 *
 * A computed feed changes when the window advances, which happens at a moment maintenance already
 * knows about -- but the document is not only its dates. It carries a name, and that name is
 * translated at build time, so a site that changes its language has a correct set of instants
 * rendered under the wrong heading. It also carries whatever an administrator typed if they renamed
 * the calendar.
 *
 * Neither of those goes through maintenance, and hooking every path that could touch them is a list
 * that gets one entry short. Recording what the document was built from and comparing on the way out
 * is the version that cannot be incomplete.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return string
 */
function axismundi_cal_managed_ics_fingerprint( array $calendar ) : string {
	return implode(
		'|',
		array(
			(string) get_locale(),
			(string) ( $calendar['updated_at'] ?? '' ),
			axismundi_cal_calendar_display_name( $calendar ),
		)
	);
}

/**
 * Render one managed calendar's document and keep it.
 *
 * Called from maintenance, which is the only thing that changes what the document says. A public
 * subscription feed is polled by every subscriber on their own schedule and forever, so serializing
 * it per request means the whole site boots, queries and re-renders a document that is identical to
 * the one it rendered a second earlier -- the conditional GET saves the bytes and none of the work.
 *
 * Only for calendars this plugin computes. A holiday dataset is edited by people at unpredictable
 * moments, and a cached document would need invalidating from every one of those paths.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return array{body:string,modified:int,etag:string}
 */
function axismundi_cal_store_managed_ics( array $calendar ) : array {
	$feed   = axismundi_cal_dataset_feed( $calendar );
	$stored = array(
		'body'        => (string) $feed['body'],
		'modified'    => (int) $feed['modified'],
		// Hashed once here rather than per request, which is the other half of the work being avoided.
		'etag'        => '"' . md5( (string) $feed['body'] ) . '"',
		'fingerprint' => axismundi_cal_managed_ics_fingerprint( $calendar ),
	);
	update_option( axismundi_cal_managed_ics_option( (string) $calendar['managed_key'] ), $stored, false );
	return $stored;
}

/**
 * The stored document for one managed calendar, rendering it if there is none or it has gone stale.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return array{body:string,modified:int,etag:string}
 */
function axismundi_cal_managed_ics( array $calendar ) : array {
	$stored = get_option( axismundi_cal_managed_ics_option( (string) $calendar['managed_key'] ), array() );
	if ( ! is_array( $stored ) || ! isset( $stored['body'], $stored['etag'], $stored['fingerprint'] )
		|| (string) $stored['fingerprint'] !== axismundi_cal_managed_ics_fingerprint( $calendar ) ) {
		return axismundi_cal_store_managed_ics( $calendar );
	}
	return $stored;
}

/**
 * Forget a managed calendar's stored document.
 *
 * @param string $key Managed calendar key.
 * @return void
 */
function axismundi_cal_forget_managed_ics( string $key ) : void {
	delete_option( axismundi_cal_managed_ics_option( $key ) );
}

/**
 * The managed dataset one slug belongs to, whether or not it currently exists.
 *
 * The registry holds fixed slugs, so this answers from the definition rather than from the database
 * -- which is the point: it is asked about addresses that have nothing behind them.
 *
 * @param string $slug Calendar slug.
 * @return string Managed key, or ''.
 */
function axismundi_cal_managed_key_for_slug( string $slug ) : string {
	foreach ( AXISMUNDI_CAL_MANAGED_CALENDARS as $key => $definition ) {
		if ( (string) $definition['slug'] === $slug ) {
			return (string) $key;
		}
	}
	return '';
}

/**
 * Whether a generator exists for this dataset.
 *
 * Distinct from whether it is switched on. `equinox-solstice` is declared here before anything can
 * compute it, so that the settings screen can say what is coming rather than staying silent about
 * it -- and so that turning it on can be refused for the honest reason instead of producing an empty
 * calendar that looks like a broken one.
 *
 * @param string $key Managed calendar key.
 * @return bool
 */
function axismundi_cal_managed_calendar_available( string $key ) : bool {
	return ! empty( AXISMUNDI_CAL_MANAGED_CALENDARS[ $key ]['available'] );
}

/**
 * Whether this site produces this dataset.
 *
 * The single record of intent, replacing the "provisioned once" flag that came before it. That flag
 * answered "has this ever been offered", which had to be asked because there was no other way to tell
 * a deliberate deletion from a fresh install; a switch says the same thing directly, and says it in a
 * place an administrator can see and change. Two mechanisms answering one question is how they come
 * to disagree.
 *
 * Moon phases default on, because a site that installs a calendar plugin has no reason to be asked
 * whether the moon should be computed. The others default off, and could not be turned on anyway.
 *
 * @param string $key Managed calendar key.
 * @return bool
 */
function axismundi_cal_managed_calendar_enabled( string $key ) : bool {
	if ( ! isset( AXISMUNDI_CAL_MANAGED_CALENDARS[ $key ] ) || ! axismundi_cal_managed_calendar_available( $key ) ) {
		return false;
	}
	$stored = (array) get_option( AXISMUNDI_CAL_MANAGED_ENABLED_OPTION, array() );
	if ( array_key_exists( $key, $stored ) ) {
		return (bool) $stored[ $key ];
	}
	return ! empty( AXISMUNDI_CAL_MANAGED_CALENDARS[ $key ]['default_on'] );
}

/**
 * Switch one dataset on or off, and make the site match.
 *
 * Switching off removes the Calendar and its entries rather than hiding them. Nothing is lost that
 * cannot be recomputed -- these rows are a materialized view of arithmetic, not an archive -- and a
 * calendar left behind with its maintenance stopped is the worst of the available states: it goes on
 * being subscribable while quietly ceasing to be true at its far edge.
 *
 * @param string $key     Managed calendar key.
 * @param bool   $enabled Whether the site should produce it.
 * @return true|WP_Error
 */
function axismundi_cal_set_managed_calendar_enabled( string $key, bool $enabled ) {
	if ( ! isset( AXISMUNDI_CAL_MANAGED_CALENDARS[ $key ] ) ) {
		return new WP_Error( 'ax_cal_managed_unknown', __( 'That is not a calendar this plugin maintains.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( $enabled && ! axismundi_cal_managed_calendar_available( $key ) ) {
		return new WP_Error( 'ax_cal_managed_unavailable', __( 'Nothing can compute that dataset yet, so there is nothing to switch on.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$stored         = (array) get_option( AXISMUNDI_CAL_MANAGED_ENABLED_OPTION, array() );
	$stored[ $key ] = $enabled;
	update_option( AXISMUNDI_CAL_MANAGED_ENABLED_OPTION, $stored, false );

	axismundi_cal_sync_managed_calendars();
	return true;
}

/**
 * Make the calendars on this site match what is switched on.
 *
 * Idempotent and safe to call from anywhere: it creates what should exist, removes what should not,
 * and leaves everything else alone. That is what lets one function answer for installation, for an
 * in-place update, for the settings screen and for the scheduled job, instead of four paths that have
 * to agree about the same thing.
 *
 * @return void
 */
function axismundi_cal_sync_managed_calendars() : void {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return;
	}
	foreach ( array_keys( AXISMUNDI_CAL_MANAGED_CALENDARS ) as $key ) {
		$key      = (string) $key;
		$calendar = axismundi_cal_managed_calendar_get( $key );
		$wanted   = axismundi_cal_managed_calendar_enabled( $key );

		if ( $wanted && null === $calendar ) {
			axismundi_cal_provision_managed_calendar( $key );
			continue;
		}
		if ( ! $wanted && is_array( $calendar ) ) {
			axismundi_cal_forget_managed_ics( $key );
			$calendar_id = (int) $calendar['id'];
			axismundi_cal_system_items_forget_calendar( $calendar_id );
			axismundi_cal_list_forget_calendar( $calendar_id );
			axismundi_cal_acl_forget_calendar( $calendar_id );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
			$wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => $calendar_id ) );
		}
	}
}

/**
 * The Calendar one key currently has, if it still exists.
 *
 * @param string $key Managed calendar key.
 * @return array<string,mixed>|null
 */
function axismundi_cal_managed_calendar_get( string $key ) : ?array {
	global $wpdb;
	if ( '' === $key || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE managed_key = %s LIMIT 1", $key ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * Create one managed Calendar and fill it.
 *
 * Called on installation for each key that has never been provisioned, and by hand afterwards from
 * the system calendar screen. Filling it here rather than leaving it to a later job is deliberate:
 * an empty Moon phases calendar looks like a broken one, and the computation is fast enough that
 * there is nothing to defer.
 *
 * @param string $key Managed calendar key.
 * @return int|WP_Error Calendar id.
 */
function axismundi_cal_provision_managed_calendar( string $key ) {
	if ( ! isset( AXISMUNDI_CAL_MANAGED_CALENDARS[ $key ] ) ) {
		return new WP_Error( 'ax_cal_managed_unknown', __( 'That is not a calendar this plugin maintains.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$existing = axismundi_cal_managed_calendar_get( $key );
	if ( is_array( $existing ) ) {
		return (int) $existing['id'];
	}
	$definition = AXISMUNDI_CAL_MANAGED_CALENDARS[ $key ];

	$created = axismundi_cal_calendar_save(
		array(
			'kind'              => 'system',
			'source'            => 'manual',
			'managed_key'       => $key,
			// No name. The key names it, in whatever language it is read in.
			'slug'              => (string) $definition['slug'],
			'system_provider'   => (string) $definition['provider'],
			'system_categories' => (array) $definition['categories'],
			'description'       => '',
			/*
			 * UTC, because everything in one of these is an instant rather than a local time. A zone here
			 * would be a fact about nothing: a full moon does not happen in Seoul.
			 */
			'timezone'          => 'UTC',
		)
	);
	if ( is_wp_error( $created ) ) {
		return $created;
	}

	// The same call the scheduled maintenance makes, so a calendar added today holds exactly what one
	// provisioned a year ago holds. Two expressions of "which entries" would drift.
	axismundi_cal_maintain_managed_calendar( $key, (array) axismundi_cal_calendar_get( (int) $created ) );
	axismundi_cal_store_managed_ics( (array) axismundi_cal_calendar_get( (int) $created ) );
	return (int) $created;
}

/**
 * Put the plugin-maintained calendars in one viewer's CalendarList on first visit.
 *
 * A managed Calendar exists independently of any one Actor, but it would otherwise be invisible in
 * the one screen people use to read it: CalendarList is the workspace's input. This is membership,
 * not a grant -- managed Calendars are public by policy already. It is deliberately called only by
 * the workspace, for the Actor who opened it: a person who has hidden it still has an entry and is
 * left alone, while a Calendar an administrator deleted is absent and is never recreated here.
 *
 * @param string $actor_uri Viewer Actor URI.
 * @return int Number of newly added list entries.
 */
function axismundi_cal_add_managed_calendars_to_list( string $actor_uri ) : int {
	$actor_uri = trim( $actor_uri );
	if ( '' === $actor_uri || ! axismundi_cal_ready() ) {
		return 0;
	}

	$added = 0;
	foreach ( array_keys( AXISMUNDI_CAL_MANAGED_CALENDARS ) as $key ) {
		if ( ! axismundi_cal_managed_calendar_enabled( (string) $key ) ) {
			continue;
		}
		$calendar = axismundi_cal_managed_calendar_get( (string) $key );
		if ( ! is_array( $calendar ) || is_array( axismundi_cal_list_entry( (int) $calendar['id'], $actor_uri ) ) ) {
			continue;
		}
		if ( ! is_wp_error( axismundi_cal_list_set( (int) $calendar['id'], $actor_uri, 'reader', array( 'selected' => true ) ) ) ) {
			++$added;
		}
	}
	return $added;
}

/**
 * Bring every managed calendar's contents back in line with its window, then book the next visit.
 *
 * Whole-window, not incremental, even though it is scheduled for the exact moment one row falls due.
 * WordPress cron fires on a request rather than on a clock, so this can run days late on a quiet
 * site, or twice, or after the site was switched off for a month -- and each of those leaves a
 * different number of rows on the wrong side of the edges. Recomputing the window is idempotent and
 * costs a few hundred upserts, which is cheaper than being right only when the job was punctual.
 *
 * @return void
 */
function axismundi_cal_maintain_managed_calendars() : void {
	if ( ! axismundi_cal_ready() ) {
		return;
	}
	// What is switched on may have changed since the last run, so the site is squared with the settings
	// before anything is recomputed. A dataset switched off between runs should not be maintained once
	// more on the way out.
	axismundi_cal_sync_managed_calendars();

	foreach ( array_keys( AXISMUNDI_CAL_MANAGED_CALENDARS ) as $key ) {
		$key      = (string) $key;
		$calendar = axismundi_cal_managed_calendar_enabled( $key ) ? axismundi_cal_managed_calendar_get( $key ) : null;
		if ( ! is_array( $calendar ) ) {
			continue;
		}
		axismundi_cal_maintain_managed_calendar( $key, $calendar );
		// Re-read, because the rows the document is built from have just changed underneath it.
		axismundi_cal_store_managed_ics( (array) axismundi_cal_managed_calendar_get( $key ) );
	}
	// Booked from here rather than by a recurring schedule, because the next due moment is a fact about
	// the data and moves with it: it is roughly a phase apart, not a fixed interval.
	axismundi_cal_schedule_maintenance();
}
add_action( AXISMUNDI_CAL_MAINTENANCE_HOOK, 'axismundi_cal_maintain_managed_calendars' );

/**
 * Book the next maintenance run.
 *
 * A single event rather than a recurring one. Nothing about a moon phase changes daily, and the two
 * things that can make the stored set wrong -- a phase arriving at the far edge, the oldest one
 * leaving the near edge -- happen at moments this can compute. A daily job would wake up six times
 * out of seven to find nothing to do.
 *
 * Floored a little into the future. A due time in the past is legitimate after downtime, but handing
 * it to cron unchanged means the event is already overdue when it is booked, and a run that fails
 * before rescheduling would be retried in a tight loop against every request.
 *
 * @return void
 */
function axismundi_cal_schedule_maintenance() : void {
	if ( ! axismundi_cal_ready() ) {
		return;
	}
	$existing = wp_get_scheduled_event( AXISMUNDI_CAL_MAINTENANCE_HOOK );
	if ( is_object( $existing ) && ! empty( $existing->schedule ) ) {
		// A recurring event from an earlier version of this plugin. Left in place it would go on firing
		// daily beside the single events, which is the job this replaced.
		wp_clear_scheduled_hook( AXISMUNDI_CAL_MAINTENANCE_HOOK );
		$existing = false;
	}
	if ( is_object( $existing ) ) {
		return;
	}

	/*
	 * The earliest moment any enabled dataset falls due, not any one of them. The seasons move a quarter
	 * of a year at a time and the phases about a week, so scheduling from either alone would let the
	 * other sit past its edge -- and the one that sat would be the one nobody noticed, because a feed
	 * that stopped growing looks exactly like a feed with nothing new in it.
	 */
	$due = 0;
	foreach ( array_keys( AXISMUNDI_CAL_MANAGED_CALENDARS ) as $key ) {
		$key      = (string) $key;
		$calendar = axismundi_cal_managed_calendar_enabled( $key ) ? axismundi_cal_managed_calendar_get( $key ) : null;
		if ( ! is_array( $calendar ) ) {
			continue;
		}
		$next = axismundi_cal_managed_calendar_next_maintenance( $key, $calendar );
		if ( 0 === $next ) {
			// Nothing stored to reason about, which means do it now rather than at some computed moment.
			$due = 0;
			break;
		}
		$due = 0 === $due ? $next : min( $due, $next );
	}
	wp_schedule_single_event( max( $due, time() + ( 15 * MINUTE_IN_SECONDS ) ), AXISMUNDI_CAL_MAINTENANCE_HOOK );
}
/*
 * Checked on `init` as well, because a plugin updated in place never runs the activation hook -- and
 * a single event that fired without rescheduling, or was never booked, is a window that silently
 * stops moving. That looks like nothing at all until a subscriber's feed runs dry at its far edge.
 */
add_action( 'init', 'axismundi_cal_schedule_maintenance', 20 );
