<?php
/**
 * Block-editor authoring assets for the Event envelope.
 *
 * No build step: plain `wp.element.createElement` against runtime globals, as the Note panel does.
 * Versioned by `filemtime` so an edited file is actually re-fetched -- a fixed version string
 * becomes the `ver=` query and serves stale JS from cache after every change.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The IANA zones offered to the author, grouped by region.
 *
 * The full list, because an Event happens somewhere specific and a shortened list would silently
 * exclude those places. Grouping is what makes ~420 entries usable; it carries no meaning.
 *
 * @return array<int,array<string,string>>
 */
function axismundi_cal_timezone_options() : array {
	$options = array();
	foreach ( timezone_identifiers_list() as $identifier ) {
		$parts    = explode( '/', $identifier, 2 );
		$group    = isset( $parts[1] ) ? $parts[0] : __( 'Other', 'axismundi-calendar' );
		$label    = isset( $parts[1] ) ? str_replace( '_', ' ', $parts[1] ) : $identifier;
		$options[] = array(
			'value' => $identifier,
			'label' => $label,
			'group' => $group,
		);
	}
	return $options;
}

/**
 * Local Calendars the current author may choose as an Event home.
 *
 * Remote calendars are caches we cannot write into. Keeping that distinction in the REST-backed
 * panel prevents an author from turning a subscribed public holiday feed into local content.
 *
 * @return array<int,array{id:int,name:string,timezone:string}>
 */
function axismundi_cal_editor_calendars() : array {
	global $wpdb;
	$calendars = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- editor choices from this plugin's own table.
	$rows = (array) $wpdb->get_results( "SELECT * FROM {$calendars} WHERE kind = 'local' ORDER BY name ASC", ARRAY_A );
	$out  = array();
	foreach ( $rows as $calendar ) {
		if ( axismundi_cal_can_manage_calendar( $calendar ) ) {
			$out[] = array(
				'id'       => (int) $calendar['id'],
				'name'     => (string) $calendar['name'],
				'timezone' => axismundi_cal_calendar_timezone( $calendar ),
			);
		}
	}
	return $out;
}

/**
 * Enqueue the Event document panel on the Event editor only.
 *
 * @return void
 */
function axismundi_cal_enqueue_editor_assets() : void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen instanceof WP_Screen || AXISMUNDI_CAL_EVENT_POST_TYPE !== $screen->post_type ) {
		return;
	}
	$plugin = dirname( __DIR__ ) . '/axismundi-calendar.php';
	$asset  = dirname( __DIR__ ) . '/assets/editor/event-panel.js';
	if ( ! file_exists( $asset ) ) {
		return;
	}

	// PluginDocumentSettingPanel moved from wp-edit-post to wp-editor; declaring a handle that is
	// not registered drops the whole script silently, so depend on whichever this build has.
	$deps = array( 'wp-element', 'wp-plugins', 'wp-data', 'wp-components', 'wp-i18n' );
	if ( wp_script_is( 'wp-editor', 'registered' ) ) {
		$deps[] = 'wp-editor';
	} elseif ( wp_script_is( 'wp-edit-post', 'registered' ) ) {
		$deps[] = 'wp-edit-post';
	}

	wp_enqueue_script(
		'axismundi-calendar-panel',
		plugins_url( 'assets/editor/event-panel.js', $plugin ),
		$deps,
		AXISMUNDI_CAL_VERSION . '-' . (string) filemtime( $asset ),
		true
	);
	wp_set_script_translations( 'axismundi-calendar-panel', 'axismundi-calendar' );
	wp_localize_script(
		'axismundi-calendar-panel',
		'axismundiCalendarEditor',
		array(
			'calendars' => axismundi_cal_editor_calendars(),
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'axismundi_cal_enqueue_editor_assets' );
