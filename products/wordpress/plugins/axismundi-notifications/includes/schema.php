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

const AXISMUNDI_NTF_DB_VERSION        = '6';
const AXISMUNDI_NTF_DB_VERSION_OPTION = 'ax_ntf_db_version';

/** @return string Events table name. */
function axismundi_ntf_events_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_ntf_events';
}

/** @return string Acceptance policy table name. */
function axismundi_ntf_policies_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_ntf_policies';
}

/** @return string Notification-only mutes table name. */
function axismundi_ntf_mutes_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_ntf_mutes';
}

/** @return string Per-person preference table name. */
function axismundi_ntf_preferences_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_ntf_preferences';
}

/** @return string Transport attempt table name. */
function axismundi_ntf_attempts_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_ntf_transport_attempts';
}

/** @return string Consented mailbox table name. */
function axismundi_ntf_mailboxes_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_ntf_mailboxes';
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
	$policies    = axismundi_ntf_policies_table();
	$mutes       = axismundi_ntf_mutes_table();
	$preferences = axismundi_ntf_preferences_table();
	$attempts    = axismundi_ntf_attempts_table();
	$mailboxes   = axismundi_ntf_mailboxes_table();

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

	/*
	 * What one Actor wants held back for review rather than delivered. Conditions about the sender,
	 * not about the subject: whether the settings screen offers them per category is a preference
	 * question and lives in the next slice.
	 *
	 * Absent means the defaults, and the defaults are off. Quarantining somebody's first message from
	 * a stranger by default would make an empty inbox and a full requests list, which is a worse
	 * failure than the one it prevents on a site nobody is harassing.
	 */
	dbDelta(
		"CREATE TABLE {$policies} (
			recipient_actor_id bigint(20) unsigned NOT NULL,
			filter_not_following tinyint(1) unsigned NOT NULL default 0,
			filter_new_actors tinyint(1) unsigned NOT NULL default 0,
			filter_automated tinyint(1) unsigned NOT NULL default 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (recipient_actor_id)
		) ENGINE=InnoDB {$charset};"
	);

	/*
	 * Notification-only mutes, and named that way on purpose. This says "do not notify me about that
	 * Actor" and nothing else: their posts still appear in a timeline, their replies still exist, a
	 * search still finds them. A mute that reached those surfaces would be a different relation
	 * altogether -- a private one between two Actors -- and it would belong to a model that owns it
	 * rather than to whichever product happened to need it first.
	 */
	dbDelta(
		"CREATE TABLE {$mutes} (
			id bigint(20) unsigned NOT NULL auto_increment,
			recipient_actor_id bigint(20) unsigned NOT NULL,
			muted_actor_uri text NOT NULL,
			muted_actor_uri_hash char(64) NOT NULL default '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY one_each (recipient_actor_id,muted_actor_uri_hash)
		) ENGINE=InnoDB {$charset};"
	);

	/*
	 * What one person wants, which is a different question from what an Actor accepts. Acceptance is
	 * about the sender and belongs to the Actor that was written to; this is about attention and
	 * belongs to the people who read for that Actor -- two managers of one Group may want entirely
	 * different things from the same inbox.
	 *
	 * `actor_id` of 0 means "all the Actors I read for", which is what most people will ever set.
	 * `scope_type` says how specific the row is, and the resolver reads the most specific first.
	 * `transport` is in_app here; email and push are the same rows with a different value in it.
	 */
	dbDelta(
		"CREATE TABLE {$preferences} (
			id bigint(20) unsigned NOT NULL auto_increment,
			local_user_id bigint(20) unsigned NOT NULL,
			actor_id bigint(20) unsigned NOT NULL default 0,
			scope_type varchar(16) NOT NULL default 'all',
			scope_key varchar(96) NOT NULL default '',
			transport varchar(16) NOT NULL default 'in_app',
			enabled tinyint(1) unsigned NOT NULL default 1,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY one_each (local_user_id,actor_id,scope_type,scope_key,transport),
			KEY reader (local_user_id,transport)
		) ENGINE=InnoDB {$charset};"
	);

	/*
	 * Sending is not delivering, and the two must not share a row. A delivery is the fact that this
	 * notification is one of yours; an attempt is one try at carrying it somewhere -- and a try can be
	 * queued, sent, refused, worth repeating, or overtaken by somebody reading it in the app first.
	 * Folding them together would make "you have this" and "the mail server accepted it" one column
	 * that cannot be both.
	 *
	 * One row per delivery per transport, so email and push are the same shape and push arrives later
	 * as a value rather than a schema.
	 */
	dbDelta(
		"CREATE TABLE {$attempts} (
			id bigint(20) unsigned NOT NULL auto_increment,
			delivery_id bigint(20) unsigned NOT NULL,
			transport varchar(16) NOT NULL default 'email',
			state varchar(16) NOT NULL default 'queued',
			attempts smallint(5) unsigned NOT NULL default 0,
			last_error text NOT NULL,
			scheduled_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY one_each (delivery_id,transport),
			KEY due (state,scheduled_at)
		) ENGINE=InnoDB {$charset};"
	);

	/*
	 * An address somebody typed in here and confirmed, and nothing else. Not the Actor's public
	 * contact, which is a fact about an identity rather than a consent to be written to; and never
	 * the signup address promoted quietly, because agreeing to have an account is not agreeing to be
	 * mailed about it. `confirmed_at` is the whole of the permission: unconfirmed rows are requests.
	 */
	dbDelta(
		"CREATE TABLE {$mailboxes} (
			local_user_id bigint(20) unsigned NOT NULL,
			address varchar(191) NOT NULL default '',
			token_hash char(64) NOT NULL default '',
			requested_at datetime NOT NULL,
			confirmed_at datetime NULL,
			PRIMARY KEY  (local_user_id)
		) ENGINE=InnoDB {$charset};"
	);

	update_option( AXISMUNDI_NTF_DB_VERSION_OPTION, AXISMUNDI_NTF_DB_VERSION, false );
	return true;
}
