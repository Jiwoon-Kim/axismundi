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

/*
 * The release this plugin was built against. Both plugins being installed is not the same as both
 * being from the same deployment, and a site that updates one and not the other is the case worth
 * refusing early: `function_exists` proves the seams named below and nothing else, so a notification
 * resolved against an older ledger would fail somewhere further in, as a missing function rather than
 * as an answer to what is actually wrong.
 */
const AXISMUNDI_NTF_ACTORS_MINIMUM     = '0.1.0';
const AXISMUNDI_NTF_ACTIVITIES_MINIMUM = '0.1.0';

/**
 * Whether the identity service is present and new enough.
 *
 * Detected by the function actually called as well as by the version: a constant proves a file
 * loaded, the function proves the seam exists, and the version proves the two came from one release.
 *
 * @return bool
 */
function axismundi_ntf_has_actors() : bool {
	return defined( 'AXISMUNDI_ACTORS_VERSION' )
		&& version_compare( (string) AXISMUNDI_ACTORS_VERSION, AXISMUNDI_NTF_ACTORS_MINIMUM, '>=' )
		&& function_exists( 'axismundi_actors_get_by_uri' );
}

/**
 * Whether the Activity ledger is present and new enough.
 *
 * @return bool
 */
function axismundi_ntf_has_activities() : bool {
	return defined( 'AXISMUNDI_ACTIVITIES_VERSION' )
		&& version_compare( (string) AXISMUNDI_ACTIVITIES_VERSION, AXISMUNDI_NTF_ACTIVITIES_MINIMUM, '>=' )
		&& function_exists( 'axismundi_act_get' );
}

/**
 * What is missing, by the name the person reading it will recognise.
 *
 * A plugin that is installed but behind is named as a version rather than as an absence. Being told
 * to install something that is sitting on the plugins screen already active reads as a broken notice
 * instead of as a half-updated site, which is the one thing this message exists to say.
 *
 * @return string[]
 */
function axismundi_ntf_unmet_dependencies() : array {
	$unmet = array();
	if ( ! axismundi_ntf_has_actors() ) {
		$unmet[] = defined( 'AXISMUNDI_ACTORS_VERSION' )
			/* translators: %s: version number. */
			? sprintf( __( 'Axismundi Actors %s or newer', 'axismundi-notifications' ), AXISMUNDI_NTF_ACTORS_MINIMUM )
			: __( 'Axismundi Actors', 'axismundi-notifications' );
	}
	if ( ! axismundi_ntf_has_activities() ) {
		$unmet[] = defined( 'AXISMUNDI_ACTIVITIES_VERSION' )
			/* translators: %s: version number. */
			? sprintf( __( 'Axismundi Activities %s or newer', 'axismundi-notifications' ), AXISMUNDI_NTF_ACTIVITIES_MINIMUM )
			: __( 'Axismundi Activities', 'axismundi-notifications' );
	}
	return $unmet;
}

/**
 * Say so on the plugins screen, where somebody is looking at the very thing to fix.
 *
 * Nowhere else. Going dormant is the correct behaviour and a notice on every admin page for it would
 * be noise, but going dormant silently on a half-updated site looks like notifications simply
 * stopping.
 *
 * @return void
 */
function axismundi_ntf_dependency_notice() : void {
	$unmet = axismundi_ntf_unmet_dependencies();
	if ( array() === $unmet || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen instanceof WP_Screen || 'plugins' !== $screen->id ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: %s: comma-separated plugin names. */
				__( 'Axismundi Notifications is not running: it needs %s. Nothing else is affected -- the acts still happen and the ledger still records them; what is missing is the list.', 'axismundi-notifications' ),
				implode( ', ', $unmet )
			)
		)
	);
}
add_action( 'admin_notices', 'axismundi_ntf_dependency_notice' );

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
