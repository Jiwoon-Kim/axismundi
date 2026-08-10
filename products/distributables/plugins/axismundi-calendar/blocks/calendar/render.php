<?php
/**
 * The month and week grid.
 *
 * Server-rendered and navigable without JavaScript: the previous and next links are ordinary links
 * carrying the period in the query string. A calendar that only works once a script has run is a
 * calendar that is blank in feed readers, in search results and for anyone whose script failed.
 *
 * The grid is laid out in the site's timezone while each Event keeps its own. That is not a
 * simplification -- it is what makes the grid agree with the clock of the person reading it, and it
 * is why an event at 08:00 in Seoul can sit on the previous row of a European site's calendar.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

$ax_cal_view = isset( $attributes['view'] ) && 'week' === $attributes['view'] ? 'week' : 'month';

// Read-only navigation, so no nonce: this selects what to display and changes nothing.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$ax_cal_requested = isset( $_GET['ax_cal'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['ax_cal'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$ax_cal_view = isset( $_GET['ax_cal_view'] ) && in_array( $_GET['ax_cal_view'], array( 'month', 'week' ), true )
	? sanitize_key( wp_unslash( (string) $_GET['ax_cal_view'] ) )
	: $ax_cal_view;

$ax_cal_zone = wp_timezone();
$ax_cal_utc  = new DateTimeZone( 'UTC' );

try {
	$ax_cal_anchor = '' !== $ax_cal_requested
		? new DateTimeImmutable( $ax_cal_requested, $ax_cal_zone )
		: new DateTimeImmutable( 'now', $ax_cal_zone );
} catch ( Exception $ax_cal_error ) {
	// An unparseable period falls back to now rather than erroring: the query string is public and
	// anyone can put anything in it.
	$ax_cal_anchor = new DateTimeImmutable( 'now', $ax_cal_zone );
}

if ( 'week' === $ax_cal_view ) {
	$ax_cal_start = $ax_cal_anchor->modify( 'monday this week' )->setTime( 0, 0 );
	$ax_cal_end   = $ax_cal_start->modify( '+7 days' );
	$ax_cal_first = $ax_cal_start;
	$ax_cal_last  = $ax_cal_end;
	$ax_cal_label = sprintf(
		/* translators: 1: first day of the week, 2: last day. */
		__( '%1$s – %2$s', 'axismundi-calendar' ),
		wp_date( (string) get_option( 'date_format' ), $ax_cal_start->getTimestamp() ),
		wp_date( (string) get_option( 'date_format' ), $ax_cal_end->modify( '-1 day' )->getTimestamp() )
	);
	$ax_cal_prev = $ax_cal_start->modify( '-7 days' )->format( 'Y-m-d' );
	$ax_cal_next = $ax_cal_start->modify( '+7 days' )->format( 'Y-m-d' );
} else {
	$ax_cal_start = $ax_cal_anchor->modify( 'first day of this month' )->setTime( 0, 0 );
	$ax_cal_end   = $ax_cal_start->modify( '+1 month' );
	// Padded to whole weeks so the grid is rectangular; the padding days are marked so they can be
	// styled as belonging to another month rather than silently looking like this one.
	$ax_cal_first = $ax_cal_start->modify( 'monday this week' );
	$ax_cal_last  = $ax_cal_end->modify( '-1 day' )->modify( 'monday this week' )->modify( '+7 days' );
	$ax_cal_label = wp_date( 'F Y', $ax_cal_start->getTimestamp() );
	$ax_cal_prev  = $ax_cal_start->modify( '-1 month' )->format( 'Y-m-d' );
	$ax_cal_next  = $ax_cal_start->modify( '+1 month' )->format( 'Y-m-d' );
}

$ax_cal_occurrences = axismundi_cal_occurrences_in_range(
	$ax_cal_first->setTimezone( $ax_cal_utc )->format( 'Y-m-d H:i:s' ),
	$ax_cal_last->setTimezone( $ax_cal_utc )->format( 'Y-m-d H:i:s' )
);
$ax_cal_days = axismundi_cal_group_by_day( $ax_cal_occurrences, $ax_cal_zone );

/** Build a navigation URL that keeps the reader where they are. */
$ax_cal_url = static function ( string $period, string $view ) : string {
	return esc_url( add_query_arg( array( 'ax_cal' => $period, 'ax_cal_view' => $view ) ) );
};

$ax_cal_today   = ( new DateTimeImmutable( 'now', $ax_cal_zone ) )->format( 'Y-m-d' );
$ax_cal_weekday = array();
$ax_cal_probe   = $ax_cal_first;
for ( $ax_cal_i = 0; $ax_cal_i < 7; $ax_cal_i++ ) {
	$ax_cal_weekday[] = wp_date( 'D', $ax_cal_probe->getTimestamp() );
	$ax_cal_probe     = $ax_cal_probe->modify( '+1 day' );
}

$ax_cal_wrapper = get_block_wrapper_attributes( array( 'class' => 'ax-cal ax-cal--' . $ax_cal_view ) );
?>
<div <?php echo wp_kses_data( $ax_cal_wrapper ); ?>>
	<nav class="ax-cal__nav" aria-label="<?php esc_attr_e( 'Calendar navigation', 'axismundi-calendar' ); ?>">
		<a class="ax-cal__step" href="<?php echo $ax_cal_url( $ax_cal_prev, $ax_cal_view ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?>" rel="prev">
			<?php echo esc_html__( 'Previous', 'axismundi-calendar' ); ?>
		</a>
		<h2 class="ax-cal__label"><?php echo esc_html( $ax_cal_label ); ?></h2>
		<a class="ax-cal__step" href="<?php echo $ax_cal_url( $ax_cal_next, $ax_cal_view ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?>" rel="next">
			<?php echo esc_html__( 'Next', 'axismundi-calendar' ); ?>
		</a>
	</nav>

	<table class="ax-cal__grid">
		<caption class="screen-reader-text"><?php echo esc_html( $ax_cal_label ); ?></caption>
		<thead>
			<tr>
				<?php foreach ( $ax_cal_weekday as $ax_cal_name ) : ?>
					<th scope="col"><?php echo esc_html( $ax_cal_name ); ?></th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<?php
			$ax_cal_cursor = $ax_cal_first;
			while ( $ax_cal_cursor < $ax_cal_last ) :
				echo '<tr>';
				for ( $ax_cal_i = 0; $ax_cal_i < 7; $ax_cal_i++ ) :
					$ax_cal_key     = $ax_cal_cursor->format( 'Y-m-d' );
					$ax_cal_outside = 'month' === $ax_cal_view && $ax_cal_cursor->format( 'Y-m' ) !== $ax_cal_start->format( 'Y-m' );
					$ax_cal_classes = 'ax-cal__day'
						. ( $ax_cal_outside ? ' ax-cal__day--outside' : '' )
						. ( $ax_cal_key === $ax_cal_today ? ' ax-cal__day--today' : '' );
					?>
					<td class="<?php echo esc_attr( $ax_cal_classes ); ?>"<?php echo $ax_cal_key === $ax_cal_today ? ' aria-current="date"' : ''; ?>>
						<span class="ax-cal__date"><?php echo esc_html( wp_date( 'j', $ax_cal_cursor->getTimestamp() ) ); ?></span>
						<?php if ( ! empty( $ax_cal_days[ $ax_cal_key ] ) ) : ?>
							<ul class="ax-cal__events">
								<?php foreach ( $ax_cal_days[ $ax_cal_key ] as $ax_cal_event ) : ?>
									<?php
									$ax_cal_cancelled = 'cancelled' === (string) $ax_cal_event['status'];
									$ax_cal_time      = '';
									if ( empty( $ax_cal_event['all_day'] ) ) {
										$ax_cal_time = wp_date(
											(string) get_option( 'time_format' ),
											( new DateTimeImmutable( (string) $ax_cal_event['start_utc'], $ax_cal_utc ) )->getTimestamp()
										);
									}
									?>
									<li class="ax-cal__event<?php echo $ax_cal_cancelled ? ' ax-cal__event--cancelled' : ''; ?>">
										<a href="<?php echo esc_url( (string) $ax_cal_event['permalink'] ); ?>">
											<?php if ( '' !== $ax_cal_time ) : ?>
												<time class="ax-cal__time" datetime="<?php echo esc_attr( gmdate( 'c', strtotime( (string) $ax_cal_event['start_utc'] . ' UTC' ) ) ); ?>"><?php echo esc_html( $ax_cal_time ); ?></time>
											<?php endif; ?>
											<span class="ax-cal__title"><?php echo esc_html( (string) $ax_cal_event['title'] ); ?></span>
										</a>
										<?php if ( $ax_cal_cancelled ) : ?>
											<span class="ax-cal__badge"><?php esc_html_e( 'Cancelled', 'axismundi-calendar' ); ?></span>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</td>
					<?php
					$ax_cal_cursor = $ax_cal_cursor->modify( '+1 day' );
				endfor;
				echo '</tr>';
			endwhile;
			?>
		</tbody>
	</table>

	<?php if ( empty( $ax_cal_occurrences ) ) : ?>
		<p class="ax-cal__empty"><?php esc_html_e( 'Nothing scheduled in this period.', 'axismundi-calendar' ); ?></p>
	<?php endif; ?>
</div>
