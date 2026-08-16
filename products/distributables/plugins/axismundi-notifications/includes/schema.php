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

const AXISMUNDI_NTF_DB_VERSION        = '1';
const AXISMUNDI_NTF_DB_VERSION_OPTION = 'ax_ntf_db_version';

/** @return string Events table name. */
function axismundi_ntf_events_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_ntf_events';
}

/**
 * Install or update the schema.
 *
 * @return bool
 */
function axismundi_ntf_install_schema() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$events  = axismundi_ntf_events_table();

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
	 */
	dbDelta(
		"CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL auto_increment,
			kind varchar(96) NOT NULL default '',
			category varchar(32) NOT NULL default '',
			recipient_actor_id bigint(20) unsigned NOT NULL default 0,
			recipient_actor_uri text NOT NULL,
			actor_uri text NOT NULL,
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

	update_option( AXISMUNDI_NTF_DB_VERSION_OPTION, AXISMUNDI_NTF_DB_VERSION, false );
	return true;
}
