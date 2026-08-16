<?php
/**
 * Where a device is remembered.
 *
 * One row per browser per person, which is what a push subscription is: not a fact about an Actor,
 * and not a fact about a notification. Somebody reading for three Actors on two laptops and a
 * phone has three devices and three subscriptions, and each of them can be revoked on its own by
 * the browser that owns it.
 *
 * @package AxismundiPwa
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_PWA_DB_VERSION        = '1';
const AXISMUNDI_PWA_DB_VERSION_OPTION = 'ax_pwa_db_version';

/** @return string Push subscription table name. */
function axismundi_pwa_subscriptions_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_pwa_push_subscriptions';
}

/** @return bool Whether the schema is installed and current. */
function axismundi_pwa_ready() : bool {
	return AXISMUNDI_PWA_DB_VERSION === (string) get_option( AXISMUNDI_PWA_DB_VERSION_OPTION, '' );
}

/**
 * Install or update the schema.
 *
 * @return bool
 */
function axismundi_pwa_install_schema() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset       = $wpdb->get_charset_collate();
	$subscriptions = axismundi_pwa_subscriptions_table();

	/*
	 * The endpoint is the credential. Anyone holding it can ask the push service to wake that
	 * browser, so it is stored like one: hashed for lookup, and never handed back out of the site.
	 *
	 * `revoked_at` rather than deletion, because a push service telling us a subscription is gone is
	 * a thing worth remembering for a moment -- it stops a sender retrying an endpoint that has
	 * already said it is finished, and it is the difference between "this device said no" and "this
	 * device was never here".
	 *
	 * No Actor column. A device belongs to a person, and which Actors that person reads for is a
	 * question the identity plugin answers at the moment of sending -- caching it here would let
	 * somebody removed as a manager keep receiving that Group's notices on their phone.
	 */
	dbDelta(
		"CREATE TABLE {$subscriptions} (
			id bigint(20) unsigned NOT NULL auto_increment,
			local_user_id bigint(20) unsigned NOT NULL,
			endpoint text NOT NULL,
			endpoint_hash char(64) NOT NULL default '',
			p256dh_key varchar(191) NOT NULL default '',
			auth_key varchar(191) NOT NULL default '',
			user_agent varchar(191) NOT NULL default '',
			created_at datetime NOT NULL,
			last_seen_at datetime NOT NULL,
			revoked_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY endpoint_hash (endpoint_hash),
			KEY owner (local_user_id,revoked_at)
		) ENGINE=InnoDB {$charset};"
	);

	update_option( AXISMUNDI_PWA_DB_VERSION_OPTION, AXISMUNDI_PWA_DB_VERSION, false );
	return true;
}
