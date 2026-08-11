<?php
/**
 * Who may do what with a Calendar.
 *
 * Three relations, kept apart because they answer different questions and every calendar product
 * that got this right keeps them apart too:
 *
 *   authority   the one Actor a Calendar belongs to; what a transfer moves, and what an Event on it
 *               is attributed to
 *   ACL         what some principal may do -- a role several Actors can hold at once
 *   list entry  how one Actor displays it: selected, hidden, an alias, a colour
 *
 * Collapsing authority into the ACL would make "hand this calendar to the group" unexpressible,
 * since there would be no single thing to hand over. Collapsing the list entry into the ACL would
 * mean hiding a shared calendar in your own sidebar hid it for everyone in it.
 *
 * A Group Actor may be the authority. That is usable today rather than theoretical: Actors already
 * models managed Groups with owner/manager/editor, so "the Group's managers may manage its calendar"
 * resolves through an existing seam. What is deliberately *not* done is expanding a Group into its
 * members to grant them access -- that is a membership policy, and a Forum moderator is not a Group
 * manager. Anyone who should read a Group's calendar gets an explicit ACL entry.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The roles a principal can hold, weakest first.
 *
 * `freeBusyReader` exists so a calendar can say when its owner is busy without saying what they are
 * doing, which is the whole point of sharing a working calendar with people who should not read it.
 */
const AXISMUNDI_CAL_ACL_ROLES = array( 'freeBusyReader' => 1, 'reader' => 2, 'writer' => 3, 'owner' => 4 );

/**
 * The rank of one role, or 0 when it is not a role.
 *
 * @param string $role Role.
 * @return int
 */
function axismundi_cal_acl_rank( string $role ) : int {
	return (int) ( AXISMUNDI_CAL_ACL_ROLES[ $role ] ?? 0 );
}

/**
 * Grant a principal a role on a Calendar.
 *
 * Idempotent per principal: granting twice changes the role rather than adding a second rule, so a
 * principal can never hold two roles and leave "which wins?" to whichever row is read first.
 *
 * @param int    $calendar_id    Calendar id.
 * @param string $principal_uri  Actor URI, or '' with `principal_type` of `public`.
 * @param string $role           One of freeBusyReader|reader|writer|owner.
 * @param string $principal_type actor|public.
 * @return int|WP_Error Rule id.
 */
function axismundi_cal_acl_grant( int $calendar_id, string $principal_uri, string $role, string $principal_type = 'actor' ) {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return new WP_Error( 'ax_cal_store', __( 'The calendar store is unavailable.', 'axismundi-calendar' ) );
	}
	if ( 0 === axismundi_cal_acl_rank( $role ) ) {
		return new WP_Error( 'ax_cal_acl_role', __( 'That is not a calendar access role.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( ! in_array( $principal_type, array( 'actor', 'public' ), true ) ) {
		return new WP_Error( 'ax_cal_acl_principal', __( 'A rule applies to an Actor or to the public.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$principal_uri = 'public' === $principal_type ? '' : trim( $principal_uri );
	if ( 'actor' === $principal_type && '' === $principal_uri ) {
		return new WP_Error( 'ax_cal_acl_principal', __( 'An Actor rule needs an Actor.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( 'public' === $principal_type && 'reader' !== $role && 'freeBusyReader' !== $role ) {
		// The public may read or see busy time. Granting the world write or ownership is never a
		// thing somebody meant to do, so it is refused rather than stored.
		return new WP_Error( 'ax_cal_acl_public_role', __( 'A public rule can only read or see free/busy time.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( null === axismundi_cal_calendar_get( $calendar_id ) ) {
		return new WP_Error( 'ax_cal_missing', __( 'That calendar does not exist.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}

	$hash     = hash( 'sha256', $principal_uri );
	$now      = current_time( 'mysql', true );
	$existing = axismundi_cal_acl_rule( $calendar_id, $principal_uri, $principal_type );
	$table    = axismundi_cal_acl_table();
	$data     = array(
		'calendar_id'        => $calendar_id,
		'principal_type'     => $principal_type,
		'principal_uri'      => $principal_uri,
		'principal_uri_hash' => $hash,
		'role'               => $role,
		'updated_at'         => $now,
	);

	if ( is_array( $existing ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ) );
		axismundi_cal_acl_flush_cache();
		return (int) $existing['id'];
	}
	$data['created_at'] = $now;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	if ( false === $wpdb->insert( $table, $data ) ) {
		return new WP_Error( 'ax_cal_acl_write', __( 'The access rule could not be saved.', 'axismundi-calendar' ) );
	}
	axismundi_cal_acl_flush_cache();
	return (int) $wpdb->insert_id;
}

/**
 * One rule, or null.
 *
 * @param int    $calendar_id    Calendar id.
 * @param string $principal_uri  Actor URI, or '' for public.
 * @param string $principal_type actor|public.
 * @return array<string,mixed>|null
 */
function axismundi_cal_acl_rule( int $calendar_id, string $principal_uri, string $principal_type = 'actor' ) : ?array {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_acl_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE calendar_id = %d AND principal_type = %s AND principal_uri_hash = %s",
			$calendar_id,
			$principal_type,
			hash( 'sha256', 'public' === $principal_type ? '' : trim( $principal_uri ) )
		),
		ARRAY_A
	);
	return is_array( $row ) ? $row : null;
}

/**
 * Withdraw a principal's rule.
 *
 * @param int    $calendar_id    Calendar id.
 * @param string $principal_uri  Actor URI, or '' for public.
 * @param string $principal_type actor|public.
 * @return bool
 */
function axismundi_cal_acl_revoke( int $calendar_id, string $principal_uri, string $principal_type = 'actor' ) : bool {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return false;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$deleted = (bool) $wpdb->delete(
		axismundi_cal_acl_table(),
		array(
			'calendar_id'        => $calendar_id,
			'principal_type'     => $principal_type,
			'principal_uri_hash' => hash( 'sha256', 'public' === $principal_type ? '' : trim( $principal_uri ) ),
		)
	);
	axismundi_cal_acl_flush_cache();
	return $deleted;
}

/**
 * Every rule on a Calendar.
 *
 * @param int $calendar_id Calendar id.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_acl_rules( int $calendar_id ) : array {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_acl_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE calendar_id = %d ORDER BY id ASC", $calendar_id ), ARRAY_A );
}

/**
 * How many principals hold `owner` on a Calendar.
 *
 * Asked before a revoke, so the last one can be refused. Counted rather than inferred from the
 * authority column: authority is the Actor a Calendar belongs to, which is a different fact and can
 * be empty on a Calendar created on nobody's behalf.
 *
 * @param int $calendar_id Calendar id.
 * @return int
 */
function axismundi_cal_acl_owner_count( int $calendar_id ) : int {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return 0;
	}
	$table = axismundi_cal_acl_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE calendar_id = %d AND role = 'owner'", $calendar_id ) );
}

/**
 * Drop a Calendar's rules with the Calendar.
 *
 * @param int $calendar_id Calendar id.
 * @return void
 */
function axismundi_cal_acl_forget_calendar( int $calendar_id ) : void {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_cal_acl_table(), array( 'calendar_id' => $calendar_id ) );
	axismundi_cal_acl_flush_cache();
}

/**
 * The strongest role an Actor effectively holds on a Calendar.
 *
 * Effective rather than stored, which is why it is computed here and not kept on the list entry:
 * it is the maximum of the Actor's own rule, whatever the public may do, and -- when the Calendar
 * belongs to a managed Group -- what that Group's managers may do.
 *
 * The Group case is the one worth stating. A Group Actor can be a Calendar's authority, and the
 * people who manage that Group manage its calendar. That does not extend to the Group's members or
 * to a Forum's moderators: those are different relations in different plugins, and a moderator of a
 * community is not an administrator of the identity that community publishes under. Anyone else who
 * should have access gets an explicit rule.
 *
 * @param int    $calendar_id Calendar id.
 * @param string $actor_uri   Actor URI, or '' for an anonymous reader.
 * @param int    $user_id     WP user behind that Actor, for managed-Group resolution. 0 to skip.
 * @return string Role, or '' for no access.
 */
function axismundi_cal_effective_role( int $calendar_id, string $actor_uri, int $user_id = 0 ) : string {
	$calendar = axismundi_cal_calendar_get( $calendar_id );
	if ( ! is_array( $calendar ) ) {
		return '';
	}
	$best = '';

	$public = axismundi_cal_acl_rule( $calendar_id, '', 'public' );
	if ( is_array( $public ) ) {
		$best = (string) $public['role'];
	}

	$actor_uri = trim( $actor_uri );
	if ( '' !== $actor_uri ) {
		$own = axismundi_cal_acl_rule( $calendar_id, $actor_uri, 'actor' );
		if ( is_array( $own ) && axismundi_cal_acl_rank( (string) $own['role'] ) > axismundi_cal_acl_rank( $best ) ) {
			$best = (string) $own['role'];
		}
	}

	if ( $user_id > 0 && axismundi_cal_acl_rank( 'owner' ) > axismundi_cal_acl_rank( $best ) && axismundi_cal_manages_authority( $calendar, $user_id ) ) {
		$best = 'owner';
	}

	return $best;
}

/**
 * Whether a user manages the Actor a Calendar belongs to.
 *
 * Only for a managed Group: a Person Actor is managed by the person it is, which the Actor rule
 * already covers. Resolved through Actors' own manager kernel rather than a second copy of the role
 * ordering here, so "at least manager" means the same thing in both plugins.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @param int                 $user_id  WP user.
 * @return bool
 */
function axismundi_cal_manages_authority( array $calendar, int $user_id ) : bool {
	$authority = trim( (string) ( $calendar['authority_actor_uri'] ?? '' ) );
	if ( '' === $authority || $user_id <= 0 || ! axismundi_cal_has_actors() ) {
		return false;
	}
	if ( ! function_exists( 'axismundi_actors_get_by_uri' ) || ! function_exists( 'axismundi_actors_managed_actor_can_manage' ) ) {
		return false;
	}
	$actor = axismundi_actors_get_by_uri( $authority );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return false;
	}
	// `manager` rather than `editor`: changing who may read a calendar is administering the identity
	// it belongs to, not authoring content under it.
	return axismundi_actors_managed_actor_can_manage( (int) $actor->get_identity_id(), $user_id, 'manager' );
}

/**
 * Whether a principal may at least read a Calendar.
 *
 * @param int    $calendar_id Calendar id.
 * @param string $actor_uri   Actor URI, or ''.
 * @param int    $user_id     WP user, or 0.
 * @return bool
 */
function axismundi_cal_can_read( int $calendar_id, string $actor_uri, int $user_id = 0 ) : bool {
	return axismundi_cal_acl_rank( axismundi_cal_effective_role( $calendar_id, $actor_uri, $user_id ) ) >= axismundi_cal_acl_rank( 'reader' );
}

/**
 * Whether a principal may add or change Events on a Calendar.
 *
 * @param int    $calendar_id Calendar id.
 * @param string $actor_uri   Actor URI, or ''.
 * @param int    $user_id     WP user, or 0.
 * @return bool
 */
function axismundi_cal_can_write( int $calendar_id, string $actor_uri, int $user_id = 0 ) : bool {
	return axismundi_cal_acl_rank( axismundi_cal_effective_role( $calendar_id, $actor_uri, $user_id ) ) >= axismundi_cal_acl_rank( 'writer' );
}

/**
 * Whether anyone at all may read a Calendar in full.
 *
 * What decides whether its page, its feeds and its Object route answer an anonymous request. A
 * Calendar is private unless somebody said otherwise, so this asks for a rule rather than assuming
 * one.
 *
 * Deliberately not satisfied by a free/busy rule. That rule exists precisely to disclose occupied
 * time *without* disclosing what the time is for, so treating it as public readability would publish
 * every title, description and location to people the owner meant to show only a busy block.
 *
 * @param int $calendar_id Calendar id.
 * @return bool
 */
function axismundi_cal_is_publicly_readable( int $calendar_id ) : bool {
	$public = axismundi_cal_acl_rule( $calendar_id, '', 'public' );
	return is_array( $public ) && axismundi_cal_acl_rank( (string) $public['role'] ) >= axismundi_cal_acl_rank( 'reader' );
}

/**
 * Whether anyone at all may see when a Calendar is busy.
 *
 * True for a full public reader as well, since reading everything includes reading when it happens.
 * Nothing consumes this yet -- it is what a free/busy endpoint will ask, and it is defined here so
 * that endpoint cannot later be built on the readability check by mistake.
 *
 * @param int $calendar_id Calendar id.
 * @return bool
 */
function axismundi_cal_is_publicly_freebusy( int $calendar_id ) : bool {
	$public = axismundi_cal_acl_rule( $calendar_id, '', 'public' );
	return is_array( $public ) && axismundi_cal_acl_rank( (string) $public['role'] ) >= axismundi_cal_acl_rank( 'freeBusyReader' );
}

/**
 * Whether a Calendar may appear on this site's public surfaces, memoized per request.
 *
 * The grid asks this once per Calendar rather than once per Event, since a month of a busy calendar
 * is one answer repeated fifty times.
 *
 * @param int $calendar_id Calendar id.
 * @return bool
 */
function axismundi_cal_calendar_is_listable( int $calendar_id, bool $flush = false ) : bool {
	static $cache = array();
	if ( $flush ) {
		$cache = array();
		return false;
	}
	if ( ! isset( $cache[ $calendar_id ] ) ) {
		$cache[ $calendar_id ] = axismundi_cal_is_publicly_readable( $calendar_id );
	}
	return $cache[ $calendar_id ];
}

/**
 * Forget memoized public answers after a rule changes.
 *
 * Called by every write below rather than left to expire with the request, because a rule is most
 * often changed by a request that then renders the thing it governs -- somebody makes a Calendar
 * public and looks at it -- and answering that render from an answer computed before the change is
 * the one case the cache exists to make faster and gets wrong.
 *
 * @return void
 */
function axismundi_cal_acl_flush_cache() : void {
	axismundi_cal_calendar_is_listable( 0, true );
}

/**
 * Give Calendars that predate access control the public rule they were behaving as if they had.
 *
 * Every Calendar was public before this existed: its page, its feed and its Events were served to
 * anyone. Closing the gate without this would silently unpublish them all -- subscription URLs
 * already in people's calendar apps would start answering 404, which is a worse outcome than the
 * over-sharing being fixed, and one nobody asked for.
 *
 * Only on upgrade, and only once. A Calendar created after this point starts owner-only, because
 * from here on being public is something somebody chose.
 *
 * @param string $previous_version Stored schema version before this upgrade, or '' on a fresh install.
 * @return int Number of Calendars grandfathered.
 */
function axismundi_cal_grandfather_public_calendars( string $previous_version ) : int {
	global $wpdb;
	// A fresh install has nothing to preserve: there are no existing URLs and no existing
	// expectations, so its Calendars begin private like every one created from now on.
	if ( '' === $previous_version || (int) $previous_version >= 11 ) {
		return 0;
	}
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration over this plugin's own table.
	$ids = array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM {$table}" ) );

	$granted = 0;
	foreach ( $ids as $id ) {
		if ( ! is_wp_error( axismundi_cal_acl_grant( $id, '', 'reader', 'public' ) ) ) {
			++$granted;
		}
	}
	return $granted;
}

/**
 * Give every Calendar an authority, from the owner it already recorded.
 *
 * @return int Number of Calendars given one.
 */
function axismundi_cal_backfill_authority() : int {
	global $wpdb;
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration over this plugin's own table.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	if ( ! in_array( 'authority_actor_uri', $columns, true ) ) {
		return 0;
	}

	$written = 0;
	// The legacy Calendar column is authoritative for this one-time migration. CalendarList entries
	// are sidebar state and may already have been removed by their owner.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
	$rows = (array) $wpdb->get_results( "SELECT id, kind FROM {$table} WHERE authority_actor_uri = ''", ARRAY_A );
	foreach ( $rows as $row ) {
		if ( 'local' !== (string) $row['kind'] ) {
			// A subscribed Calendar has no local authority: the feed it projects does.
			continue;
		}
		$owner = '';
		if ( in_array( 'owner_actor_uri', $columns, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
			$owner = (string) $wpdb->get_var( $wpdb->prepare( "SELECT owner_actor_uri FROM {$table} WHERE id = %d", (int) $row['id'] ) );
		}
		if ( '' === $owner ) {
			// A very early transitional install may only have the legacy entry. This is migration-only;
			// normal reads never infer authority from a CalendarList row.
			$owner = axismundi_cal_legacy_entry_owner( (int) $row['id'] );
		}
		if ( '' === $owner ) {
			/*
			 * The default Calendar predates Actor ownership and was created on nobody's behalf. Once
			 * this site has an Actor, make that Actor its authority instead of emitting an Event that
			 * cannot satisfy Object Projections' required `attributedTo` member.
			 */
			$owner = axismundi_cal_default_local_authority();
		}
		if ( '' === $owner ) {
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
		$wpdb->update(
			$table,
			array( 'authority_actor_uri' => $owner, 'authority_actor_uri_hash' => hash( 'sha256', $owner ) ),
			array( 'id' => (int) $row['id'] )
		);
		++$written;
	}
	return $written;
}

/**
 * The site Actor used to recover local Calendars that were created before authority existed.
 *
 * @return string
 */
function axismundi_cal_default_local_authority() : string {
	if ( ! axismundi_cal_has_actors() || ! function_exists( 'axismundi_actors_get_site_actor' ) ) {
		return '';
	}
	$actor = axismundi_actors_get_site_actor();
	return $actor instanceof Axismundi_Actor ? (string) $actor->get_uri() : '';
}

/**
 * Give each Calendar's authority an owner rule.
 *
 * @return int Number of rules written.
 */
function axismundi_cal_seed_authority_acl() : int {
	global $wpdb;
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration over this plugin's own table.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	if ( ! in_array( 'authority_actor_uri', $columns, true ) ) {
		return 0;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
	$rows = (array) $wpdb->get_results( "SELECT id, authority_actor_uri FROM {$table} WHERE authority_actor_uri <> ''", ARRAY_A );

	$written = 0;
	foreach ( $rows as $row ) {
		if ( ! is_wp_error( axismundi_cal_acl_grant( (int) $row['id'], (string) $row['authority_actor_uri'], 'owner' ) ) ) {
			++$written;
		}
	}
	return $written;
}

/**
 * Whether authority, ACL and the legacy owner still agree.
 *
 * Run before the legacy column is dropped, because after that there is nothing to compare against.
 *
 * @return array<string,mixed>
 */
function axismundi_cal_verify_authority_migration() : array {
	global $wpdb;
	$calendars = axismundi_cal_calendars_table();
	$acl       = axismundi_cal_acl_table();

	/*
	 * A Calendar that never had an owner is not a migration failure. The default Calendar is created
	 * by the upgrade itself, on nobody's behalf, and an installation with no Actors at all produces
	 * more of them -- so "every Calendar has an authority" is a stricter claim than this migration
	 * makes. What would be a failure is a Calendar that recorded an owner and did not get an
	 * authority from it, which is the loss this check exists to catch.
	 */
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$calendars}" );
	$legacy_owner = in_array( 'owner_actor_uri', $columns, true );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- migration verification.
	$missing_authority = array_map(
		'intval',
		(array) $wpdb->get_col(
			$legacy_owner
				? "SELECT id FROM {$calendars} WHERE kind = 'local' AND owner_actor_uri <> '' AND authority_actor_uri = ''"
				: "SELECT id FROM {$calendars} WHERE 1 = 0"
		)
	);
	$mismatched_authority = array_map(
		'intval',
		(array) $wpdb->get_col(
			$legacy_owner
				? "SELECT id FROM {$calendars} WHERE kind = 'local' AND owner_actor_uri <> '' AND authority_actor_uri <> owner_actor_uri"
				: "SELECT id FROM {$calendars} WHERE 1 = 0"
		)
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- migration verification.
	$missing_rule = array_map(
		'intval',
		(array) $wpdb->get_col(
			"SELECT c.id FROM {$calendars} c LEFT JOIN {$acl} a
			 ON a.calendar_id = c.id AND a.role = 'owner' AND a.principal_uri_hash = c.authority_actor_uri_hash
			 WHERE c.authority_actor_uri <> '' AND a.id IS NULL"
		)
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- migration verification.
	$bad_hash = array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM {$calendars} WHERE authority_actor_uri <> '' AND authority_actor_uri_hash <> SHA2(authority_actor_uri, 256)" ) );

	// A subscribed Calendar must not acquire a local authority: its authority is the feed.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- migration verification.
	$remote_authority = array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM {$calendars} WHERE kind <> 'local' AND authority_actor_uri <> ''" ) );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- migration verification.
	$public_write = array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM {$acl} WHERE principal_type = 'public' AND role NOT IN ('reader','freeBusyReader')" ) );

	return array(
		'ok'                => empty( $missing_authority ) && empty( $mismatched_authority ) && empty( $missing_rule ) && empty( $bad_hash ) && empty( $remote_authority ) && empty( $public_write ),
		'missing_authority' => $missing_authority,
		'mismatched_authority' => $mismatched_authority,
		'missing_rule'      => $missing_rule,
		'bad_hash'          => $bad_hash,
		'remote_authority'  => $remote_authority,
		'public_write'      => $public_write,
	);
}
