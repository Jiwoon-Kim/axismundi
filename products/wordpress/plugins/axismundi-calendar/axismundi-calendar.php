<?php
/**
 * Plugin Name:       Axismundi Calendar
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi
 * Description:       Calendar and event infrastructure: schedules, occurrences, iCalendar subscriptions, and FEP-8a8e federation.
 * Version:           0.1.0-beta.1
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            Ji-woon Kim
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-calendar
 *
 * The time layer, in four parts that stay separate because collapsing any two of them is what makes
 * recurring events intractable later:
 *
 *   Calendar     a collection of events, and a subscribable resource
 *   Schedule     when something happens: timezone, DTSTART/DTEND, RRULE
 *   Occurrence   one actual instance, and the exceptions that apply to it alone
 *   Event        what happens: body, place, organizer, participation policy
 *
 * Today there is one consumer of that layer -- an Event with a single non-recurring Schedule, which
 * is what the `ax_event` post type and the `ax_events` table already hold. Naming the layers before
 * they are all built is deliberate: an Event that grew `event_start`/`event_end` and then had
 * recurrence bolted on is the shape this is avoiding.
 *
 * The engine owns time, iCalendar and collections. ActivityPub is an optional adapter above it: the
 * calendar must work with Object Projections absent, and must never learn about inboxes, delivery or
 * HTTP signatures. Federation of an Event happens through Object Projections when it is installed,
 * so the thread graph, interactions and the canonical document route come for free.
 *
 * Deliberately not a bridge. Reading another event plugin's storage would make that plugin's model
 * this one's permanent contract -- GatherPress keeps RSVPs in `wp_comments`, which is a different
 * ledger than the Activity ledger participation belongs in. `event-bridge-for-activitypub` already
 * adapts eight event plugins for people who want that; this owns its own Objects instead, and meets
 * the same wire format so both talk to the same peers.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_CAL_VERSION = '0.1.0-beta.1';

/*
 * The Event post type keeps its `ax_event` key and its `/event/{slug}` permalink through the move to
 * Calendar. The key is stored in every row that references an Event and the permalink is public, so
 * renaming either would be a data and URL migration bought for a tidier string. The canonical Object
 * URI is the stable `?p=<id>` form and is unaffected by both.
 */
const AXISMUNDI_CAL_EVENT_POST_TYPE = 'ax_event';

require_once __DIR__ . '/includes/dependencies.php';
require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/cpt.php';
require_once __DIR__ . '/includes/rrule.php';
require_once __DIR__ . '/includes/duration.php';
require_once __DIR__ . '/includes/recurrence.php';
require_once __DIR__ . '/includes/occurrence.php';
require_once __DIR__ . '/includes/schedule.php';
require_once __DIR__ . '/includes/calendar.php';
require_once __DIR__ . '/includes/calendar-list.php';
require_once __DIR__ . '/includes/calendar-acl.php';
require_once __DIR__ . '/includes/primary-calendar.php';
require_once __DIR__ . '/includes/capabilities.php';
require_once __DIR__ . '/includes/calendar-systems.php';
require_once __DIR__ . '/includes/icu-calendars.php';
require_once __DIR__ . '/includes/secondary-display.php';
require_once __DIR__ . '/includes/system-providers.php';
require_once __DIR__ . '/includes/system-items.php';
require_once __DIR__ . '/includes/holiday-concepts.php';
require_once __DIR__ . '/includes/admin-calendars.php';
require_once __DIR__ . '/includes/admin-workspace.php';
require_once __DIR__ . '/includes/admin-participation.php';
require_once __DIR__ . '/includes/admin-sharing.php';
require_once __DIR__ . '/includes/admin-system-items.php';
require_once __DIR__ . '/includes/admin-system-import.php';
require_once __DIR__ . '/includes/admin-holiday-links.php';
require_once __DIR__ . '/includes/admin-secondary-calendars.php';
require_once __DIR__ . '/includes/admin-sources.php';
require_once __DIR__ . '/includes/query.php';
require_once __DIR__ . '/includes/ics.php';
require_once __DIR__ . '/includes/ics-parse.php';
require_once __DIR__ . '/includes/subscription.php';
require_once __DIR__ . '/includes/ics-feed.php';
require_once __DIR__ . '/includes/ics-dataset.php';
require_once __DIR__ . '/includes/astronomy.php';
require_once __DIR__ . '/includes/moon-phases.php';
require_once __DIR__ . '/includes/seasons.php';
require_once __DIR__ . '/includes/managed-calendars.php';
require_once __DIR__ . '/includes/calendar-page.php';
require_once __DIR__ . '/includes/blocks.php';
require_once __DIR__ . '/includes/event-locations.php';
require_once __DIR__ . '/includes/event-participation.php';
require_once __DIR__ . '/includes/participant-visibility.php';
require_once __DIR__ . '/includes/event-invite.php';
require_once __DIR__ . '/includes/event-removal.php';
require_once __DIR__ . '/includes/event-cancellation.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/event-actor.php';
require_once __DIR__ . '/includes/event-placement.php';
require_once __DIR__ . '/includes/share-invitation.php';
require_once __DIR__ . '/includes/collection-projection.php';
require_once __DIR__ . '/includes/jscalendar.php';
require_once __DIR__ . '/includes/jscalendar-group.php';
require_once __DIR__ . '/includes/envelope.php';
require_once __DIR__ . '/includes/rest.php';
require_once __DIR__ . '/includes/rest-read.php';
require_once __DIR__ . '/includes/rest-write.php';
require_once __DIR__ . '/includes/rest-participation.php';
require_once __DIR__ . '/includes/publish-guard.php';
require_once __DIR__ . '/includes/editor.php';
require_once __DIR__ . '/includes/projection.php';

/**
 * Install the envelope schema and claim the Event permalink.
 *
 * The post type is registered on `init`, which has not run yet during activation, so a flush here
 * would write rewrite rules that do not include it. Registering it first is what makes
 * `/event/{slug}` resolve from the moment the plugin is switched on rather than whenever something
 * unrelated next flushes -- until then the permalink is a 404 while the Event itself looks fine
 * everywhere else, which is the kind of half-working state nothing reports.
 *
 * Soft flush: rewrite rules for a post type live in the `rewrite_rules` option, and `.htaccess`
 * only needs the front-controller rule it already has. Writing it is an unnecessary risk here.
 *
 * @return void
 */
function axismundi_cal_activate() : void {
	axismundi_cal_install_schema();
	// Everything that contributes rewrite rules has to be registered before the flush. `init` has
	// already run by the time an activation hook fires, so a rule added there was not present when
	// the rules were last written -- which is how `/event/{slug}` shipped as a 404 once already.
	axismundi_cal_register_event_post_type();
	axismundi_cal_register_ics_routes();
	flush_rewrite_rules( false );
	// The calendars this plugin maintains, so moon phases are on the grid from the first page load
	// rather than after somebody discovers they have to create them.
	axismundi_cal_sync_managed_calendars();
}
register_activation_hook( __FILE__, 'axismundi_cal_activate' );

/**
 * Drop the Event rewrite rules with the plugin.
 *
 * Otherwise `/event/{slug}` keeps matching a route nothing answers.
 *
 * @return void
 */
function axismundi_cal_deactivate() : void {
	flush_rewrite_rules( false );
	// A scheduled event outlives the plugin that registered it, so an orphaned hook would go on firing
	// against nothing until somebody cleaned the cron table by hand.
	wp_clear_scheduled_hook( AXISMUNDI_CAL_MAINTENANCE_HOOK );
}
register_deactivation_hook( __FILE__, 'axismundi_cal_deactivate' );

/**
 * Keep the schema current for a plugin updated in place rather than reactivated.
 *
 * @return void
 */
function axismundi_cal_maybe_upgrade() : void {
	if ( AXISMUNDI_CAL_DB_VERSION !== (string) get_option( AXISMUNDI_CAL_DB_VERSION_OPTION, '' ) ) {
		axismundi_cal_install_schema();
	}
	/*
	 * Squares the site with what is switched on. A plugin updated in place never ran the activation
	 * hook, so without this a newly available dataset would arrive for new installations only.
	 */
	axismundi_cal_sync_managed_calendars();
}
add_action( 'init', 'axismundi_cal_maybe_upgrade', 5 );
