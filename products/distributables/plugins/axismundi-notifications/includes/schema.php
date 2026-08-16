<?php
/**
 * Where the facts go.
 *
 * One table in this slice: the events themselves, addressed to an Actor. The per-user deliveries
 * that carry read state are the next slice and deliberately not here -- an inbox with read state
 * hanging off the event row would already be wrong for the case this plugin exists to handle, where
 * two people manage a Group and one of them reading must not clear the other's badge.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_NTF_DB_VERSION        = '3';
const AXISMUNDI_NTF_DB_VERSION_OPTION = 'ax_ntf_db_version';

/** @return string Events table name. */
function axismundi_ntf_events_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_ntf_events';
}

/** @return string Deliveries table name. */
function axismundi_ntf_deliveries_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_ntf_deliveries';
}

/**
 * Install or update the schema.
 *
 * @return bool
 */
function axismundi_ntf_install_schema() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset    = $wpdb->get_charset_collate();
	$events     = axismundi_ntf_events_table();
	$deliveries = axismundi_ntf_deliveries_table();

	/*
	 * The dedupe rule is a constraint and not a convention, because everything that produces one of
	 * these can produce it twice: a redelivered inbox POST, a retried request, a double-clicked
	 * button. `source_activity_uri` plus the recipient plus the kind is what one act means to one
	 * Actor, so the second attempt has nowhere to go.
	 *
	 * Hashed, because a URI is longer than an index key may be -- the same reason every Actor
	 * reference in this family is stored hashed beside its text.
	 *
	 * `snapshot` holds what the resolver saw. A notification outlives what it is about: the Event may
	 * since have been renamed, moved or deleted, and the entry still has to read sensibly. It is also
	 * what closes the door on recomputing a past audience -- there is no path here that re-runs a
	 * resolver over history, because the answer would be today's and the notice was yesterday's.
	 *
	 * `initiating_local_user_id` is which local person performed the act, when one did, and it is
	 * what "not your own act" actually means. It cannot be derived from the Actor URIs: an act by one
	 * manager of an Organization is addressed to that Organization, so comparing Actor to recipient
	 * would suppress the entry for every manager -- including the ones who most need it. Null for
	 * anything with no local author (a remote Activity, cron, a system act) and null suppresses
	 * nothing. Passed as provenance by whoever ran the command and never read from the session, which
	 * by flush time is only a guess about who caused an act that may have come from another server.
	 *
	 * No block comments inside the CREATE TABLE below. dbDelta parses that string itself, and a
	 * comment in it truncates the ALTER it generates -- the column silently never arrives while the
	 * version option still advances and every readiness check goes on saying yes.
	 */
	dbDelta(
		"CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL auto_increment,
			kind varchar(96) NOT NULL default '',
			category varchar(32) NOT NULL default '',
			recipient_actor_id bigint(20) unsigned NOT NULL default 0,
			recipient_actor_uri text NOT NULL,
			actor_uri text NOT NULL,
			initiating_local_user_id bigint(20) unsigned NULL,
			object_uri text NOT NULL,
			source_activity_uri text NOT NULL,
			source_activity_hash char(64) NOT NULL default '',
			dedupe_hash char(64) NOT NULL default '',
			grouping_key varchar(191) NOT NULL default '',
			snapshot longtext NOT NULL,
			state varchar(16) NOT NULL default 'accepted',
			occurred_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY dedupe (dedupe_hash),
			KEY recipient (recipient_actor_id,occurred_at),
			KEY source_activity_hash (source_activity_hash),
			KEY grouping_key (grouping_key)
		) ENGINE=InnoDB {$charset};"
	);

	/*
	 * One row per person per notification, which is the whole reason read state is not on the event.
	 * Two people manage a Group; one of them reading must not clear the other's badge.
	 *
	 * Written when the event is, for the managers as they stood at that moment. Somebody made a
	 * manager afterwards gets no rows for what came before, which is deliberate: they can read the
	 * Actor's history, and they do not inherit a hundred unread notices about months they were not
	 * there for.
	 *
	 * Never the authority on access. A manager who has since been removed may still have rows here,
	 * and every read re-asks whether they may still see that Actor's inbox -- the rows say what was
	 * delivered, not what may now be read.
	 */
	dbDelta(
		"CREATE TABLE {$deliveries} (
			id bigint(20) unsigned NOT NULL auto_increment,
			notification_id bigint(20) unsigned NOT NULL,
			local_user_id bigint(20) unsigned NOT NULL,
			delivered_at datetime NOT NULL,
			read_at datetime NULL,
			dismissed_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY one_each (notification_id,local_user_id),
			KEY unread (local_user_id,read_at)
		) ENGINE=InnoDB {$charset};"
	);

	update_option( AXISMUNDI_NTF_DB_VERSION_OPTION, AXISMUNDI_NTF_DB_VERSION, false );
	return true;
}
