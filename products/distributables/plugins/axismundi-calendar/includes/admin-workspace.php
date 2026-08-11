<?php
/**
 * The calendar people actually look at.
 *
 * Everything else in this plugin is a settings screen: names, timezones, who may read what. This is
 * the one that answers "what is happening", which is a different question and wants a different
 * shape -- a grid of days with a sidebar of things to tick, not a list of rows with an edit link.
 *
 * Deliberately the entry point rather than another tab. The Calendars screen exists to configure
 * Calendars and the External calendars screen to watch subscriptions; neither is what somebody opens
 * to find out when the meeting is.
 *
 * No build step: `wp.element.createElement` against the admin's own React, as the Event panel does.
 * The alternative is a bundler and a lockfile in a repository that has neither, to render a month
 * grid.
 *
 * What the screen shows is decided entirely by the REST API. The sidebar is `calendarList`, ticking
 * a box is a `calendarList` write, and the grid is `calendarView` -- so the browser cannot see
 * anything the API would not have given it, and there is no second permission model in JavaScript to
 * disagree with the first.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the workspace as the first Calendar screen.
 *
 * @return void
 */
function axismundi_cal_workspace_menu() : void {
	add_submenu_page(
		'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE,
		__( 'Calendar', 'axismundi-calendar' ),
		__( 'Calendar', 'axismundi-calendar' ),
		'publish_posts',
		'ax-calendar-workspace',
		'axismundi_cal_render_workspace_page'
	);
}
// Ahead of the settings screens, so the menu reads calendar, then its configuration.
add_action( 'admin_menu', 'axismundi_cal_workspace_menu', 9 );

/**
 * The mount point. Everything else arrives over REST.
 *
 * @return void
 */
function axismundi_cal_render_workspace_page() : void {
	if ( ! axismundi_cal_can_manage_calendars() ) {
		wp_die( esc_html__( 'You are not allowed to manage calendars.', 'axismundi-calendar' ), 403 );
	}
	?>
	<div class="wrap">
		<div id="ax-cal-workspace" class="ax-cal-workspace">
			<p><?php esc_html_e( 'Loading your calendars…', 'axismundi-calendar' ); ?></p>
		</div>
		<noscript>
			<p><?php esc_html_e( 'The calendar needs JavaScript. The Calendars screen lists the same calendars without it.', 'axismundi-calendar' ); ?></p>
		</noscript>
	</div>
	<?php
}

/**
 * What the screen is told about this site before it asks anything.
 *
 * Its own function so the values can be asserted. Two of them are settings the workspace must not
 * decide for itself: which day the week starts on is General Settings' answer, and the locale that
 * formats month and weekday names is the admin's, not whichever language the reader's browser
 * happens to prefer.
 *
 * @return array<string,mixed>
 */
function axismundi_cal_workspace_config() : array {
	return array(
		'namespace'   => 'axismundi/v1',
		'newEvent'    => admin_url( 'post-new.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE ),
		'settings'    => admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendars' ),
		// JavaScript `Intl` expects BCP 47 (en-US); WordPress locale identifiers use underscores.
		'locale'      => str_replace( '_', '-', determine_locale() ),
		/*
		 * The viewer's zone, which is what the grid is drawn in. Not the Calendar's: a person in
		 * Seoul looking at a London calendar wants to know when it happens for them, and an all-day
		 * entry is a civil date that must not be shifted by either.
		 */
		'timezone'    => axismundi_cal_default_calendar_timezone(),
		// An integer, because the client does arithmetic with it.
		'startOfWeek' => (int) get_option( 'start_of_week', 0 ),
	);
}

/**
 * Load the workspace assets on its own screen only.
 *
 * @param string $hook Current admin page.
 * @return void
 */
function axismundi_cal_enqueue_workspace( string $hook ) : void {
	if ( ! str_contains( $hook, 'ax-calendar-workspace' ) ) {
		return;
	}
	$plugin = dirname( __DIR__ ) . '/axismundi-calendar.php';
	$script = dirname( __DIR__ ) . '/assets/admin/workspace.js';
	$style  = dirname( __DIR__ ) . '/assets/admin/workspace.css';
	if ( ! file_exists( $script ) ) {
		return;
	}

	wp_enqueue_style(
		'axismundi-calendar-workspace',
		plugins_url( 'assets/admin/workspace.css', $plugin ),
		array( 'wp-components' ),
		AXISMUNDI_CAL_VERSION . '-' . (string) filemtime( $style )
	);
	wp_enqueue_script(
		'axismundi-calendar-workspace',
		plugins_url( 'assets/admin/workspace.js', $plugin ),
		array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-url' ),
		// Versioned by mtime: a fixed string becomes the `ver=` query and serves yesterday's script
		// from cache after every edit.
		AXISMUNDI_CAL_VERSION . '-' . (string) filemtime( $script ),
		true
	);
	wp_set_script_translations( 'axismundi-calendar-workspace', 'axismundi-calendar' );
	/*
	 * `wp_add_inline_script()` rather than `wp_localize_script()`, which is documented to cast every
	 * value to a string. That is how `start_of_week` reached the browser as "1" and made a weekday
	 * header start on Wednesday: "1" + 0 concatenates where 1 + 0 adds. Encoding the payload as JSON
	 * keeps an integer an integer, so the bug class is gone at the source rather than corrected in
	 * every consumer that remembers to.
	 */
	wp_add_inline_script(
		'axismundi-calendar-workspace',
		'var axismundiCalendarWorkspace = ' . wp_json_encode( axismundi_cal_workspace_config() ) . ';',
		'before'
	);
}
add_action( 'admin_enqueue_scripts', 'axismundi_cal_enqueue_workspace' );
