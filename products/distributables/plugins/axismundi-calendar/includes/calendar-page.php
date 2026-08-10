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
	if ( ! is_array( $calendar ) || 'public' !== (string) $calendar['visibility'] ) {
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
