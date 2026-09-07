<?php
/**
 * What this plugin needs from its neighbours, and what it does without them.
 *
 * The split is deliberate. Time is this plugin's own: schedules, recurrence, occurrences, the
 * iCalendar engine and subscriptions all work with nothing else installed, and a site using
 * Axismundi Calendar purely as a calendar should never be told to install a federation stack.
 *
 * Identity is not. An owner is an Actor, and an Event becomes an Object through the projection
 * registry -- so ownership, the federated Object route and every future REST and ActivityPub surface
 * are registered only when both plugins are present. Registering them anyway would produce the worst
 * outcome available: a permission check with no principal to check, or an Object route answering for
 * something that cannot be projected.
 *
 * `Requires Plugins:` in the header is not the mechanism. That field resolves slugs hosted on
 * WordPress.org and can say nothing about plugins distributed any other way, so the header would be
 * documentation while the actual gate has to be this runtime check.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/*
 * The release this plugin was built against. Two plugins can each be installed and still not be the
 * same deployment: a site that updates Calendar and not Actors gets a plugin calling seams that were
 * added after the Actors it has. `function_exists` catches the seams this file names and nothing
 * else, so the version is the guard against the half-updated site as a whole -- checked once, here,
 * rather than discovered later as a missing function somewhere in the middle of an Invite.
 */
const AXISMUNDI_CAL_ACTORS_MINIMUM     = '0.1.0';
const AXISMUNDI_CAL_OP_MINIMUM         = '0.1.0';
const AXISMUNDI_CAL_ACTIVITIES_MINIMUM = '0.1.0';

/**
 * Whether the Actors identity service is present and new enough.
 *
 * Detected by the function that is actually called as well as by the version: a constant proves a
 * file loaded, the function proves the seam this plugin uses exists, and the version proves the two
 * plugins came from the same release.
 *
 * @return bool
 */
function axismundi_cal_has_actors() : bool {
	return defined( 'AXISMUNDI_ACTORS_VERSION' )
		&& version_compare( (string) AXISMUNDI_ACTORS_VERSION, AXISMUNDI_CAL_ACTORS_MINIMUM, '>=' )
		&& function_exists( 'axismundi_actors_get_for_user' );
}

/**
 * Whether Object Projections is present and new enough.
 *
 * @return bool
 */
function axismundi_cal_has_object_projections() : bool {
	return defined( 'AXISMUNDI_OP_VERSION' )
		&& version_compare( (string) AXISMUNDI_OP_VERSION, AXISMUNDI_CAL_OP_MINIMUM, '>=' )
		&& function_exists( 'axismundi_op_register_object_transformer' )
		&& function_exists( 'axismundi_op_local_author_actor_uri' );
}

/**
 * Whether the Activity ledger is present.
 *
 * Participation needs it and time does not, which is why this is its own question rather than part
 * of `federation_ready()`. A `Join` is an Activity before it is a row: the row is a projection kept
 * so screens do not have to replay the ledger, and recording the state without the Activity would
 * make the projection the original -- leaving nothing to federate, nothing to `Undo`, and no answer
 * to who said what when.
 *
 * @return bool
 */
function axismundi_cal_has_activities() : bool {
	return defined( 'AXISMUNDI_ACTIVITIES_VERSION' )
		&& version_compare( (string) AXISMUNDI_ACTIVITIES_VERSION, AXISMUNDI_CAL_ACTIVITIES_MINIMUM, '>=' )
		&& function_exists( 'axismundi_act_record_source_activity' );
}

/**
 * Whether the Actor-dependent surfaces may be registered.
 *
 * One question with one answer, so the projection, the REST API and the ActivityPub collection
 * cannot each decide differently and leave a site half federated.
 *
 * @return bool
 */
function axismundi_cal_federation_ready() : bool {
	return axismundi_cal_has_actors() && axismundi_cal_has_object_projections();
}

/**
 * The plugins that are missing, by display name.
 *
 * @return string[]
 */
function axismundi_cal_missing_dependencies() : array {
	$missing = array();
	if ( ! axismundi_cal_has_actors() ) {
		$missing[] = axismundi_cal_dependency_label( __( 'Axismundi Actors', 'axismundi-calendar' ), 'AXISMUNDI_ACTORS_VERSION', AXISMUNDI_CAL_ACTORS_MINIMUM );
	}
	if ( ! axismundi_cal_has_object_projections() ) {
		$missing[] = axismundi_cal_dependency_label( __( 'Axismundi Object Projections', 'axismundi-calendar' ), 'AXISMUNDI_OP_VERSION', AXISMUNDI_CAL_OP_MINIMUM );
	}
	return $missing;
}

/**
 * Name one unmet dependency the way the person reading it needs to hear it.
 *
 * Being told to install a plugin that is sitting right there on the plugins screen, already active,
 * is worse than being told nothing: it reads as a bug in the notice rather than as a half-updated
 * site. So a plugin that is present but behind says so, and says which version would do.
 *
 * @param string $name     Display name.
 * @param string $constant Version constant the dependency defines.
 * @param string $minimum  Version this plugin needs.
 * @return string
 */
function axismundi_cal_dependency_label( string $name, string $constant, string $minimum ) : string {
	if ( ! defined( $constant ) ) {
		return $name;
	}
	return sprintf(
		/* translators: 1: plugin name, 2: required version. */
		__( '%1$s %2$s or newer', 'axismundi-calendar' ),
		$name,
		$minimum
	);
}

/**
 * Say what is missing and what stops working, on the screens where it matters.
 *
 * Shown on the plugins screen and the Calendars screen only. A notice on every admin page for a
 * degradation that is survivable would be noise, and noise is how the notices that matter get
 * ignored.
 *
 * @return void
 */
function axismundi_cal_dependency_notice() : void {
	$missing = axismundi_cal_missing_dependencies();
	if ( empty( $missing ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$here   = $screen instanceof WP_Screen ? $screen->id : '';
	if ( 'plugins' !== $here && ! str_contains( $here, 'ax-calendars' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where the markup is built.
	echo axismundi_cal_dependency_notice_html( $missing );
}
add_action( 'admin_notices', 'axismundi_cal_dependency_notice' );

/**
 * The notice itself, given what is missing.
 *
 * Separate from the hook so the absent case can be asserted. The hook reads global state that a test
 * cannot set without uninstalling a plugin, and the case worth checking is precisely the one this
 * site is never in.
 *
 * It says what is lost as well as what is needed: a notice naming a plugin without saying what stops
 * working invites someone to install it for a feature they were never going to use, or to ignore it
 * and later wonder why nothing federates.
 *
 * @param string[] $missing Missing plugin names.
 * @return string
 */
function axismundi_cal_dependency_notice_html( array $missing ) : string {
	if ( empty( $missing ) ) {
		return '';
	}
	return '<div class="notice notice-warning"><p>'
		. sprintf(
			/* translators: %s: comma-separated plugin names. */
			esc_html__( 'Axismundi Calendar needs %s for calendar ownership and for publishing Events to other servers.', 'axismundi-calendar' ),
			esc_html( implode( ', ', $missing ) )
		)
		. '</p><p>'
		. esc_html__( 'Calendars, schedules, recurring events and iCalendar subscriptions keep working without them. Events will not be attributed to an Actor and will not be published to other servers.', 'axismundi-calendar' )
		. '</p></div>';
}
