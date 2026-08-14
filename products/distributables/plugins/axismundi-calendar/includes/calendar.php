<?php
/**
 * The Calendar collection.
 *
 * A Calendar is the one home of an Event and a resource in its own right: it has a name, a
 * timezone, a subscription URL and a revision. It is not a taxonomy term and not a post type. A
 * term classifies what an Event is about; a Calendar establishes where it is scheduled.
 *
 * Membership is by series, not by occurrence. A weekly meeting is one member however many times it
 * meets, and an annual birthday is one member forever. Storing occurrences as members would make a
 * Calendar grow without bound for a rule that never ends, which is the same reason iCalendar export
 * writes rules rather than expansions.
 *
 * `revision` moves whenever the Calendar's own fields or its Event assignment changes. It is not a
 * substitute for the feed's entity tag -- that is a hash of the document, which also moves when an
 * Event inside changes or the rolling window turns over -- but it gives the collection something
 * cheap to compare without serializing it.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * What a Calendar is, which is settled when it is made.
 *
 *   local   an Actor's own, shared by that Actor's decision
 *   remote  a read-only mirror of a feed published elsewhere
 *   system  a dataset this instance publishes and maintains, belonging to nobody
 */
const AXISMUNDI_CAL_KINDS = array( 'local', 'remote', 'system' );

/**
 * A Calendar by slug.
 *
 * @param string $slug Calendar slug.
 * @return array<string,mixed>|null
 */
function axismundi_cal_calendar_by_slug( string $slug ) : ?array {
	global $wpdb;
	$slug = sanitize_title( $slug );
	if ( '' === $slug || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * The human-readable address of a Calendar.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return string
 */
function axismundi_cal_calendar_url( array $calendar ) : string {
	return home_url( '/calendar/' . rawurlencode( (string) $calendar['slug'] ) . '/' );
}

/**
 * The local iCalendar subscription address of a Calendar.
 *
 * Remote Calendars intentionally have no re-export URL: this instance is a cache, not their
 * authority. Callers receive an empty string for that case.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return string
 */
function axismundi_cal_calendar_ics_url( array $calendar ) : string {
	if ( 'local' !== (string) $calendar['kind'] ) {
		return '';
	}
	return home_url( '/calendar/' . rawurlencode( (string) $calendar['slug'] ) . '.ics' );
}

/**
 * A Calendar by id.
 *
 * @param int $calendar_id Calendar id.
 * @return array<string,mixed>|null
 */
function axismundi_cal_calendar_get( int $calendar_id ) : ?array {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- primary-key lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $calendar_id ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * The timezone a calendar is displayed in.
 *
 * The reader's, not the calendar's. An Event happening at 09:00 in London is the same instant
 * wherever it is read, and somebody in Seoul wants to see 17:00 -- so a London calendar viewed from
 * Seoul shows Seoul times. Laying the grid out in the calendar's own zone would tell that reader an
 * event is at nine in the morning when it is not.
 *
 * The site's zone stands in for the reader's until people have their own. That is the seam this
 * function exists to be: a per-user preference replaces the body and nothing else changes.
 *
 * Two kinds of value are deliberately not converted, and the grouping handles them: an all-day date
 * is the same civil date everywhere, and a floating time means the same wall clock everywhere.
 *
 * @return DateTimeZone
 */
function axismundi_cal_viewer_timezone() : DateTimeZone {
	return wp_timezone();
}

/**
 * The timezone a Calendar names as its own.
 *
 * This is the default timezone for an Event authored on this Calendar and what a subscription feed
 * declares as its home zone. It never decides what a reader sees. An Event may retain an explicit
 * timezone override for a genuine cross-region event or for legacy data, but a new Event starts
 * from this named place rather than an arbitrary site offset.
 *
 * @param array<string,mixed>|null $calendar Calendar row, or null.
 * @return string IANA identifier, or ''.
 */
function axismundi_cal_calendar_timezone( ?array $calendar ) : string {
	$stored = is_array( $calendar ) ? trim( (string) ( $calendar['timezone'] ?? '' ) ) : '';
	return in_array( $stored, timezone_identifiers_list(), true ) ? $stored : '';
}

/**
 * A Calendar by its public identifier.
 *
 * @param string $uuid Calendar UUID.
 * @return array<string,mixed>|null
 */
function axismundi_cal_calendar_by_uuid( string $uuid ) : ?array {
	global $wpdb;
	$uuid = trim( $uuid );
	if ( '' === $uuid || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE uuid = %s", $uuid ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * Give a public identifier to Calendars created before there was one.
 *
 * @return int Number of Calendars given a UUID.
 */
function axismundi_cal_backfill_calendar_uuids() : int {
	global $wpdb;
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration over this plugin's own table.
	$ids = array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM {$table} WHERE uuid = ''" ) );
	foreach ( $ids as $id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
		$wpdb->update( $table, array( 'uuid' => wp_generate_uuid4() ), array( 'id' => $id ) );
	}
	return count( $ids );
}

/**
 * Create or update a Calendar.
 *
 * @param array<string,mixed> $fields     Calendar fields. `slug` identifies an existing Calendar.
 * @param int                 $calendar_id Existing Calendar id, or 0.
 * @return int|WP_Error Calendar id.
 */
function axismundi_cal_calendar_save( array $fields, int $calendar_id = 0 ) {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return new WP_Error( 'ax_cal_store', __( 'The calendar store is unavailable.', 'axismundi-calendar' ) );
	}
	$existing = $calendar_id > 0 ? axismundi_cal_calendar_get( $calendar_id ) : null;

	/*
	 * Which managed calendar this is, if it is one. Settled once: a key cannot be attached to an
	 * existing Calendar or taken off one, because it decides what names the row and what fills it,
	 * and moving it would leave entries behind under a Calendar that no longer claims them.
	 */
	$managed_key = is_array( $existing )
		? (string) ( $existing['managed_key'] ?? '' )
		: trim( (string) ( $fields['managed_key'] ?? '' ) );
	if ( '' !== $managed_key && ! isset( AXISMUNDI_CAL_MANAGED_CALENDARS[ $managed_key ] ) ) {
		return new WP_Error( 'ax_cal_managed_unknown', __( 'That is not a calendar this plugin maintains.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	/*
	 * A name is required of everything except a calendar this plugin maintains, which is named by its
	 * key in the language of whoever is reading it -- the same rule an entry follows when its category
	 * names it. An administrator may still type one, and then that is what the site calls it.
	 */
	$name = trim( (string) ( $fields['name'] ?? ( $existing['name'] ?? '' ) ) );
	if ( '' === $name && '' === $managed_key ) {
		return new WP_Error( 'ax_cal_name', __( 'A calendar needs a name.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$slug = sanitize_title( (string) ( $fields['slug'] ?? ( $existing['slug'] ?? ( '' !== $name ? $name : $managed_key ) ) ) );
	if ( '' === $slug ) {
		return new WP_Error( 'ax_cal_slug', __( 'A calendar needs a slug that can appear in a URL.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$clash = axismundi_cal_calendar_by_slug( $slug );
	if ( is_array( $clash ) && (int) $clash['id'] !== (int) ( $existing['id'] ?? 0 ) ) {
		// Refused rather than suffixed. The slug is the subscription URL people have already given
		// to their calendar app, so quietly minting a different one produces a feed nobody follows.
		return new WP_Error( 'ax_cal_slug_taken', __( 'Another calendar already uses that slug.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}

	/*
	 * Three kinds, and they are not three flavours of one thing:
	 *
	 *   local   belongs to an Actor, who decides who else may see it
	 *   remote  a read-only mirror of a feed somebody else publishes
	 *   system  a dataset this instance publishes -- holidays, observances, moon phases
	 *
	 * A system Calendar has no authority Actor on purpose. Nobody's holidays are these; the site
	 * maintains them, and the people who do that are answering to a capability rather than owning an
	 * identity. That is why it cannot be created through the ordinary path, which requires an Actor
	 * and offers sharing that would mean nothing here.
	 */
	$kind = (string) ( $fields['kind'] ?? ( $existing['kind'] ?? 'local' ) );
	if ( ! in_array( $kind, AXISMUNDI_CAL_KINDS, true ) ) {
		return new WP_Error( 'ax_cal_kind', __( 'A calendar is local, subscribed or maintained by this site.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( is_array( $existing ) && $kind !== (string) $existing['kind'] ) {
		// What a Calendar is was settled when it was made. Changing it would move the answer to who
		// owns it, who may share it and whether its contents are Events, all at once and silently.
		return new WP_Error( 'ax_cal_kind', __( 'A calendar cannot change what kind of calendar it is.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$timezone = trim( (string) ( $fields['timezone'] ?? ( $existing['timezone'] ?? '' ) ) );
	if ( '' === $timezone || ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
		return new WP_Error( 'ax_cal_timezone', __( 'A calendar needs an IANA timezone such as Asia/Seoul.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	/*
	 * Public only, for now. Private calendars need a subscription secret, a way to revoke it and a
	 * story for the caches in between; shipping the column without those would offer a privacy
	 * guarantee nothing enforces.
	 */
	$visibility = 'public';

	/*
	 * Origin is settled when the Calendar is made and never afterwards: an imported dataset does not
	 * become authored because somebody edited an entry, and a mirror does not become ours because we
	 * kept it a while. Existing rows keep what they were given.
	 */
	$source = (string) ( $fields['source'] ?? ( $existing['source'] ?? '' ) );
	if ( ! in_array( $source, AXISMUNDI_CAL_SOURCE_TYPES, true ) ) {
		$source = 'remote' === $kind ? 'subscription' : 'native';
	}
	if ( 'system' === $kind && ! in_array( $source, array( 'manual', 'import' ), true ) ) {
		// A dataset this site publishes was either typed in or read from a file. There is no third way
		// for one to exist, and `native` would claim its entries are authored Events.
		$source = 'manual';
	}
	if ( 'remote' === $kind && 'subscription' !== $source ) {
		// A remote Calendar is a mirror by definition. Any other origin describes something local.
		return new WP_Error( 'ax_cal_source', __( 'A subscribed calendar is a mirror of its source.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( 'remote' !== $kind && 'subscription' === $source ) {
		return new WP_Error( 'ax_cal_source', __( 'Only a subscribed calendar mirrors a source.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	/*
	 * What kind of dataset this is, which is a choice rather than a set of labels: it decides which
	 * writer fills the calendar and what its entries mean in time. Fixed once set, because changing
	 * it would change the meaning of every entry already on it, and no reading turns a moon phase
	 * into a public holiday.
	 */
	$system_provider = 'system' === $kind
		? (string) ( $existing['system_provider'] ?? '' )
		: '';
	if ( 'system' === $kind && '' === $system_provider ) {
		$system_provider = (string) ( $fields['system_provider'] ?? '' );
		if ( ! in_array( $system_provider, AXISMUNDI_CAL_SYSTEM_PROVIDERS, true ) ) {
			return new WP_Error( 'ax_cal_system_provider', __( 'A system calendar has to say what kind of dataset it holds.', 'axismundi-calendar' ), array( 'status' => 400 ) );
		}
	}

	$provider_config = array();
	if ( 'system' === $kind ) {
		$submitted = array_key_exists( 'provider_config', $fields )
			? (array) $fields['provider_config']
			: axismundi_cal_provider_config( $existing );
		$provider_config = axismundi_cal_normalize_provider_config( $system_provider, $submitted );
		if ( is_wp_error( $provider_config ) ) {
			return $provider_config;
		}
	}

	/*
	 * Asked of the source rather than of the kind, for the reason the workspace reader was: `kind` is
	 * ownership and visibility, `source` is whether the contents are maintained entries. A dataset
	 * somebody keeps themselves is `local` and `manual` at once, and gating on `system` left it with no
	 * classification at all -- which stopped mattering the moment entries began inheriting theirs from
	 * the Calendar, because there was then nowhere for `HOLIDAY` to come back from.
	 */
	$holds_dataset     = in_array( $source, array( 'manual', 'import' ), true );
	$system_categories = $holds_dataset
		? axismundi_cal_normalize_system_calendar_categories( $fields['system_categories'] ?? ( $existing['system_categories'] ?? '' ) )
		: array();
	if ( $holds_dataset && array() === $system_categories && '' !== $system_provider ) {
		/*
		 * The browsing classification follows from the provider rather than being asked for twice.
		 * They were two answers to one question, and a calendar whose label disagreed with its own
		 * writer would appear in the catalog under something it is not.
		 */
		$system_categories = axismundi_cal_normalize_system_calendar_categories( array( strtoupper( $system_provider ) ) );
	}

	$now  = current_time( 'mysql', true );
	$data = array(
		'slug'           => $slug,
		/*
		 * NULL rather than '' for a calendar named by its key, so the two states stay distinguishable:
		 * NULL says the key names it, '' would say somebody cleared the name.
		 */
		'name'           => '' !== $name ? $name : null,
		'managed_key'    => $managed_key,
		'description'    => (string) ( $fields['description'] ?? ( $existing['description'] ?? '' ) ),
		'timezone'       => $timezone,
		'kind'           => $kind,
		'source'         => $source,
		'system_categories' => implode( ',', $system_categories ),
		'system_provider'   => $system_provider,
		'provider_config'   => (string) wp_json_encode( $provider_config ),
		'visibility'     => $visibility,
		'updated_at'     => $now,
	);

	$table = axismundi_cal_calendars_table();
	if ( is_array( $existing ) ) {
		if ( array_key_exists( 'owner_actor_uri', $fields ) ) {
			$current_authority = axismundi_cal_calendar_authority( (int) $existing['id'] );
			$requested_authority = trim( (string) $fields['owner_actor_uri'] );
			if ( '' !== $current_authority && $requested_authority !== $current_authority ) {
				return new WP_Error( 'ax_cal_authority_locked', __( 'Calendar authority cannot be transferred yet.', 'axismundi-calendar' ), array( 'status' => 409 ) );
			}
		}
		$data['revision'] = (int) $existing['revision'] + 1;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		if ( false === $wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ) ) ) {
			return new WP_Error( 'ax_cal_write', __( 'The calendar could not be saved.', 'axismundi-calendar' ) );
		}
		if ( array_key_exists( 'owner_actor_uri', $fields ) ) {
			$recorded = axismundi_cal_record_owner( (int) $existing['id'], (string) $fields['owner_actor_uri'], $kind );
			if ( is_wp_error( $recorded ) ) {
				return $recorded;
			}
		}
		return (int) $existing['id'];
	}

	$authority = trim( (string) ( $fields['owner_actor_uri'] ?? '' ) );
	if ( 'local' === $kind && '' === $authority ) {
		return new WP_Error( 'ax_cal_authority', __( 'A local calendar needs an Actor authority.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( 'remote' === $kind && '' !== $authority ) {
		return new WP_Error( 'ax_cal_authority_remote', __( 'A subscribed calendar cannot claim a local authority.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$data['revision']   = 1;
	// A stable public identifier. The slug is editable and appears in subscription URLs, so it cannot
	// also be the key an API or a stored reference uses -- renaming a calendar would silently break
	// every one of them.
	$data['uuid']       = wp_generate_uuid4();
	$data['created_at'] = $now;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	if ( false === $wpdb->insert( $table, $data ) ) {
		return new WP_Error( 'ax_cal_write', __( 'The calendar could not be saved.', 'axismundi-calendar' ) );
	}
	$created_id = (int) $wpdb->insert_id;
	$recorded = axismundi_cal_record_owner( $created_id, $authority, $kind );
	if ( is_wp_error( $recorded ) ) {
		return $recorded;
	}
	return $created_id;
}

/**
 * Record who owns a Calendar, as a relation.
 *
 * Nothing is written for a remote Calendar. Its authority is the feed it projects and whoever
 * publishes that, so making the subscriber its owner would be this site claiming it can speak for
 * somebody else's calendar -- and the claim would outlive the reason it was made.
 *
 * @param int    $calendar_id Calendar id.
 * @param string $actor_uri   Owning Actor URI, or '' to leave ownership unset.
 * @param string $kind        local|remote.
 * @return true|WP_Error
 */
function axismundi_cal_record_owner( int $calendar_id, string $actor_uri, string $kind ) {
	global $wpdb;
	$actor_uri = trim( $actor_uri );
	if ( 'remote' === $kind ) {
		return '' === $actor_uri ? true : new WP_Error( 'ax_cal_authority_remote', __( 'A subscribed calendar cannot claim a local authority.', 'axismundi-calendar' ) );
	}
	if ( 'system' === $kind ) {
		/*
		 * Having no authority is what a system Calendar is, not a state it is waiting to leave.
		 * Nobody's holidays are these: the site maintains them, and giving one an Actor would make
		 * whoever happened to create it the person they are published by.
		 */
		return '' === $actor_uri ? true : new WP_Error( 'ax_cal_authority_system', __( 'A maintained calendar belongs to the site rather than to an Actor.', 'axismundi-calendar' ) );
	}
	if ( '' === $actor_uri ) {
		return new WP_Error( 'ax_cal_authority', __( 'A local calendar needs an Actor authority.', 'axismundi-calendar' ) );
	}
	$calendar = axismundi_cal_calendar_get( $calendar_id );
	if ( ! is_array( $calendar ) ) {
		return new WP_Error( 'ax_cal_missing', __( 'That calendar no longer exists.', 'axismundi-calendar' ) );
	}
	$current_authority = (string) $calendar['authority_actor_uri'];
	if ( '' !== $current_authority && $current_authority !== $actor_uri ) {
		return new WP_Error( 'ax_cal_authority_locked', __( 'Calendar authority cannot be transferred yet.', 'axismundi-calendar' ) );
	}
	/*
	 * Three writes, because they are three different facts. The authority is the Actor this Calendar
	 * belongs to and is what a transfer would move. The ACL rule is what that Actor may do, which
	 * others can also be granted. The list entry is only how it appears in their own sidebar.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update(
		axismundi_cal_calendars_table(),
		array( 'authority_actor_uri' => $actor_uri, 'authority_actor_uri_hash' => hash( 'sha256', $actor_uri ) ),
		array( 'id' => $calendar_id )
	);
	axismundi_cal_acl_grant( $calendar_id, $actor_uri, 'owner' );
	// Creating a Calendar also puts it in the creator's sidebar, but this row grants nothing and may
	// later be removed without affecting either the authority or the ACL.
	axismundi_cal_list_set( $calendar_id, $actor_uri );
	return true;
}

/**
 * Make a Calendar its authority's default, or stop it being one.
 *
 * One primary per authority, so promoting demotes whatever held it. Separate from creation because
 * this is also the only way to make a primary Calendar deletable: `calendar_delete()` refuses one
 * outright, and demoting is the caller stating that it means to remove somebody's default rather
 * than passing a flag that says so in passing.
 *
 * @param int  $calendar_id Calendar id.
 * @param bool $primary     Whether this Calendar is the default.
 * @return bool
 */
function axismundi_cal_set_primary( int $calendar_id, bool $primary ) : bool {
	global $wpdb;
	$calendar = axismundi_cal_calendar_get( $calendar_id );
	if ( ! is_array( $calendar ) || 'local' !== (string) $calendar['kind'] ) {
		return false;
	}
	$table = axismundi_cal_calendars_table();
	if ( $primary ) {
		$authority = (string) $calendar['authority_actor_uri'];
		if ( '' === $authority ) {
			// Nobody's default. A Calendar with no authority has no Actor to be the default for.
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( $table, array( 'is_primary' => 0 ), array( 'authority_actor_uri_hash' => hash( 'sha256', $authority ) ) );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	return false !== $wpdb->update( $table, array( 'is_primary' => $primary ? 1 : 0 ), array( 'id' => $calendar_id ) );
}

/**
 * Delete a local Calendar and the Events it owns.
 *
 * A Calendar is the home of its Events. Deleting it therefore deletes that local content rather
 * than leaving unfiled schedules that can appear on some later page by accident. A remote Calendar
 * is removed together with its source by `axismundi_cal_remove_source()`.
 *
 * @param int $calendar_id Calendar id.
 * @return bool
 */
function axismundi_cal_calendar_delete( int $calendar_id ) : bool {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return false;
	}
	$calendar = axismundi_cal_calendar_get( $calendar_id );
	// A remote Calendar is the representation of one source. Deleting it independently would leave
	// the source and its cache pointing at a Calendar that no longer exists.
	if ( ! is_array( $calendar ) || 'remote' === (string) $calendar['kind'] ) {
		return false;
	}
	/*
	 * An Actor's own Calendar is where their Events go when they name none, so deleting it leaves the
	 * next Event with nowhere to be filed -- and the writer would simply make another, which is not
	 * what anyone pressing delete meant. Refused here rather than in the screen that offers the
	 * button, so no other caller can route around it.
	 *
	 * `axismundi_cal_set_primary()` is how a caller says it really means to remove one: demote first,
	 * which is a deliberate second act rather than a flag passed to a delete.
	 */
	if ( ! empty( $calendar['is_primary'] ) ) {
		return false;
	}
	foreach ( axismundi_cal_calendar_event_ids( $calendar_id ) as $event_id ) {
		$post = get_post( $event_id );
		if ( $post instanceof WP_Post ) {
			if ( ! wp_delete_post( $event_id, true ) ) {
				return false;
			}
		} else {
			// A missing post is stale local projection data, not a reason to preserve an otherwise
			// undeletable Calendar.
			axismundi_cal_forget_deleted_event( $event_id );
		}
	}
	// The relations go with it. An entry naming a Calendar that no longer exists would show up in
	// somebody's list as a calendar they cannot open.
	axismundi_cal_list_forget_calendar( $calendar_id );
	axismundi_cal_acl_forget_calendar( $calendar_id );
	// A maintained Calendar's entries are part of it, and outlive nothing.
	axismundi_cal_system_items_forget_calendar( $calendar_id );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	return false !== $wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => $calendar_id ) );
}

/**
 * Note that a Calendar's contents changed.
 *
 * @param int $calendar_id Calendar id.
 * @return void
 */
function axismundi_cal_bump_revision( int $calendar_id ) : void {
	global $wpdb;
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET revision = revision + 1, updated_at = %s WHERE id = %d", current_time( 'mysql', true ), $calendar_id ) );
}

/**
 * The Event post ids a local Calendar owns.
 *
 * @param int $calendar_id Calendar id.
 * @return int[]
 */
function axismundi_cal_calendar_event_ids( int $calendar_id ) : array {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_schedules_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ownership lookup in this plugin's own table.
	return array_map(
		'intval',
		(array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT event_post_id FROM {$table} WHERE calendar_id = %d",
				$calendar_id
			)
		)
	);
}

/**
 * The Calendar one Event belongs to.
 *
 * @param int $post_id Event post id.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_calendar_for_event( int $post_id ) : ?array {
	$schedule = axismundi_cal_schedule_for_event( $post_id );
	return is_array( $schedule ) ? axismundi_cal_calendar_get( (int) $schedule['calendar_id'] ) : null;
}

/**
 * Drop the Schedule when its Event is deleted.
 *
 * @param int $post_id Post id.
 * @return void
 */
function axismundi_cal_forget_deleted_event( int $post_id ) : void {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return;
	}
	$schedule = axismundi_cal_schedule_for_event( $post_id );
	if ( ! is_array( $schedule ) ) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own tables.
	$wpdb->delete( axismundi_cal_occurrences_table(), array( 'schedule_id' => (int) $schedule['id'] ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_cal_schedules_table(), array( 'id' => (int) $schedule['id'] ) );
	axismundi_cal_bump_revision( (int) $schedule['calendar_id'] );
}
add_action( 'deleted_post', 'axismundi_cal_forget_deleted_event', 10, 1 );
