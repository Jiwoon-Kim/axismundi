<?php
/**
 * Human-readable Calendar pages.
 *
 * The .ics document is for calendar software. This route is the corresponding page people can
 * open, share and navigate; keeping those representations beside the same Calendar slug prevents
 * the admin screen from handing out a file URL as though it were a web page.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render one public Calendar using the same dynamic block as an authored page.
 *
 * @return void
 */
function axismundi_cal_serve_calendar_page() : void {
	if ( '1' !== (string) get_query_var( 'ax_cal_page' ) ) {
		return;
	}
	$calendar = axismundi_cal_calendar_by_slug( (string) get_query_var( 'ax_cal_slug' ) );
	// A private Calendar answers exactly as a missing one does. Distinguishing them would confirm
	// that a particular slug exists, which is the one thing an anonymous request must not learn.
	if ( ! is_array( $calendar ) || ! axismundi_cal_is_publicly_readable( (int) $calendar['id'] ) ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		get_template_part( '404' );
		exit;
	}

	status_header( 200 );
	get_header();
	?>
	<main id="primary" class="site-main ax-cal-page">
		<header class="ax-cal-page__header">
			<h1><?php echo esc_html( (string) $calendar['name'] ); ?></h1>
			<?php if ( '' !== (string) $calendar['description'] ) : ?>
				<p><?php echo esc_html( (string) $calendar['description'] ); ?></p>
			<?php endif; ?>
		</header>
		<?php echo do_blocks( '<!-- wp:axismundi-calendar/calendar {"calendar":"' . esc_attr( (string) $calendar['slug'] ) . '"} /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block renderer returns its own escaped markup. ?>
	</main>
	<?php
	get_footer();
	exit;
}
add_action( 'template_redirect', 'axismundi_cal_serve_calendar_page', 9 );

/**
 * Withhold the readable page of an Event whose Calendar is not public.
 *
 * Without this the gate is defeated by guessing one URL. The Object route, the grid and the feeds
 * would all withhold a private Calendar's Event while its own permalink served the title, the time
 * and the description to anyone -- and a published post is exactly what an Event on a private
 * Calendar is, so post status cannot be what decides this.
 *
 * People who may read the Calendar still get the page, so an author is not locked out of their own
 * draft-in-public. `404` rather than `403` for everyone else, for the reason the Calendar page uses
 * it: a refusal confirms there is something there to refuse.
 *
 * @return void
 */
function axismundi_cal_guard_event_page() : void {
	if ( ! is_singular( AXISMUNDI_CAL_EVENT_POST_TYPE ) ) {
		return;
	}
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return;
	}
	$schedule = axismundi_cal_schedule_for_event( (int) $post->ID );
	// An Event with no schedule yet is not a Calendar's to withhold; the envelope adapter owns that.
	if ( ! is_array( $schedule ) ) {
		return;
	}
	$calendar_id = (int) $schedule['calendar_id'];
	if ( axismundi_cal_is_publicly_readable( $calendar_id )
		|| axismundi_cal_can_read( $calendar_id, axismundi_cal_current_actor_uri(), get_current_user_id() ) ) {
		return;
	}

	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	get_template_part( '404' );
	exit;
}
add_action( 'template_redirect', 'axismundi_cal_guard_event_page', 9 );
