<?php
/**
 * What this plugin needs, and why both are required rather than one.
 *
 * Actors, because a notification is addressed to an Actor and to nothing else. The recipient of a
 * calendar invitation may be an Organization; the person who reads it is whoever manages that
 * Organization today, which is a question only Actors can answer.
 *
 * Activities, because every act worth telling somebody about is already an Activity -- `Invite`,
 * `Accept`, `Undo`, `Remove`, `Update`. A notification minted without one would be a fact with no
 * history, no provenance and no federated counterpart, and there would be no way to tell a second
 * delivery of the same act from a second act.
 *
 * Neither is optional and neither is imitated. With either absent nothing here runs, which is the
 * honest failure: the acts still happen, the ledger still records them, and the screens that show
 * them are unchanged. What is missing is only the list.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the identity service is present.
 *
 * Detected by the function actually called rather than by the version constant alone: a constant
 * proves a file loaded, the function proves the seam exists.
 *
 * @return bool
 */
function axismundi_ntf_has_actors() : bool {
	return defined( 'AXISMUNDI_ACTORS_VERSION' ) && function_exists( 'axismundi_actors_get_by_uri' );
}

/**
 * Whether the Activity ledger is present.
 *
 * @return bool
 */
function axismundi_ntf_has_activities() : bool {
	return defined( 'AXISMUNDI_ACTIVITIES_VERSION' ) && function_exists( 'axismundi_act_get' );
}

/**
 * Whether this plugin can do anything at all.
 *
 * @return bool
 */
function axismundi_ntf_ready() : bool {
	return axismundi_ntf_has_actors()
		&& axismundi_ntf_has_activities()
		&& AXISMUNDI_NTF_DB_VERSION === (string) get_option( AXISMUNDI_NTF_DB_VERSION_OPTION, '' );
}
