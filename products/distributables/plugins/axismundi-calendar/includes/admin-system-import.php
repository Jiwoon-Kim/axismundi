<?php
/**
 * Reading a published iCalendar into a dataset this site then maintains.
 *
 * Import, not subscription, and the difference is the whole point. A subscription keeps answering to
 * its publisher: it is read-only, it changes when they change it, and an entry that disappears from
 * the feed disappears here. An import happens once. From then on these are this site's dates, to be
 * corrected, classified, translated and published on its own judgement -- which is what a holiday
 * calendar needs, because the classification a feed carries is not one an importer can read.
 *
 * Nothing arrives published. Holiday dates move -- substitute days, temporary holidays, election
 * days -- and a feed is one publisher's answer about them, not the law. Everything lands as a draft
 * for the year it belongs to, and somebody says it is right before anyone else sees it.
 *
 * Two steps, because the first question is which years. A feed like Google's carries a decade at
 * once, and importing all of it means committing to reviewing years nobody has looked at yet.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** How long a fetched document waits between the preview and the confirmation. */
const AXISMUNDI_CAL_IMPORT_TTL = 15 * MINUTE_IN_SECONDS;

/**
 * Group parsed entries by the year they fall in.
 *
 * @param array<int,array<string,mixed>> $entries Parsed entries.
 * @return array<int,int> Year => count, in order.
 */
function axismundi_cal_import_years( array $entries ) : array {
	$years = array();
	foreach ( $entries as $entry ) {
		$year = (int) substr( (string) $entry['start_local'], 0, 4 );
		if ( $year > 0 ) {
			$years[ $year ] = ( $years[ $year ] ?? 0 ) + 1;
		}
	}
	ksort( $years );
	return $years;
}

/**
 * Which entries this store can hold, and which it cannot.
 *
 * A dataset entry is a whole day. An entry with a time is something else -- a service, a broadcast --
 * and one carrying a recurrence rule is a claim about every future year that a curated dataset should
 * not accept sight unseen. Both are counted and reported rather than silently dropped, because a
 * silently shorter import is one nobody notices until a date is missing.
 *
 * @param array<int,array<string,mixed>> $entries Parsed entries.
 * @param int[]                          $years   Years to keep.
 * @return array{keep:array<int,array<string,mixed>>,timed:int,recurring:int}
 */
function axismundi_cal_import_partition( array $entries, array $years ) : array {
	$keep      = array();
	$timed     = 0;
	$recurring = 0;
	foreach ( $entries as $entry ) {
		$year = (int) substr( (string) $entry['start_local'], 0, 4 );
		if ( array() !== $years && ! in_array( $year, $years, true ) ) {
			continue;
		}
		if ( '' !== trim( (string) $entry['rrule'] ) ) {
			++$recurring;
			continue;
		}
		if ( empty( $entry['all_day'] ) ) {
			++$timed;
			continue;
		}
		$keep[] = $entry;
	}
	return array( 'keep' => $keep, 'timed' => $timed, 'recurring' => $recurring );
}

/**
 * Fetch a document and hold it for the confirmation step.
 *
 * Held rather than fetched twice, so what is imported is what was previewed. A second fetch could
 * return something else -- the publisher updates, a CDN answers differently -- and the years somebody
 * ticked would then be counts they never saw.
 *
 * @param string $url Source address.
 * @return array{body:string,hash:string}|WP_Error
 */
function axismundi_cal_import_fetch( string $url ) {
	/*
	 * The guard answers whether this address may be fetched; it does not hand back an address to
	 * fetch. Passing its `true` to the request was asking for the URL `1`, which fails as "a valid URL
	 * was not provided" -- reported here as an address that could not be read, which is exactly the
	 * wrong thing to tell somebody whose address was fine.
	 */
	$valid = axismundi_cal_validate_source_url( $url );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'     => 20,
			'redirection' => 3,
			'headers'     => array( 'Accept' => 'text/calendar, text/plain;q=0.5' ),
		)
	);
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'ax_cal_import_fetch', __( 'That address could not be read.', 'axismundi-calendar' ) );
	}
	if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'ax_cal_import_fetch', __( 'That address did not return a calendar.', 'axismundi-calendar' ) );
	}
	$body = (string) wp_remote_retrieve_body( $response );
	if ( ! str_contains( $body, 'BEGIN:VCALENDAR' ) ) {
		return new WP_Error( 'ax_cal_import_parse', __( 'That address returned something that is not an iCalendar document.', 'axismundi-calendar' ) );
	}
	return array( 'body' => $body, 'hash' => hash( 'sha256', $body ) );
}

/**
 * Read a document, or confirm a previewed one.
 *
 * @return void
 */
function axismundi_cal_handle_system_import() : void {
	$calendar_id = isset( $_POST['calendar_id'] ) ? absint( wp_unslash( $_POST['calendar_id'] ) ) : 0;
	check_admin_referer( 'ax_cal_import_' . $calendar_id );
	$calendar = $calendar_id > 0 ? axismundi_cal_calendar_get( $calendar_id ) : null;
	if ( ! axismundi_cal_calendar_can( $calendar, 'manage_items' ) ) {
		wp_die( esc_html__( 'You are not allowed to maintain that calendar.', 'axismundi-calendar' ), 403 );
	}
	$base   = add_query_arg( 'calendar', $calendar_id, admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendar-system' ) );
	$action = isset( $_POST['ax_cal_import_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['ax_cal_import_action'] ) ) : 'preview';

	if ( 'confirm' === $action ) {
		axismundi_cal_confirm_system_import( $calendar_id, $base );
		return;
	}

	$url   = isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['source_url'] ) ) : '';
	$fetch = axismundi_cal_import_fetch( $url );
	if ( is_wp_error( $fetch ) ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', rawurlencode( $fetch->get_error_code() ), $base ) );
		exit;
	}
	$token = wp_generate_uuid4();
	set_transient(
		'ax_cal_import_' . $token,
		array( 'calendar_id' => $calendar_id, 'url' => $url, 'body' => $fetch['body'], 'hash' => $fetch['hash'] ),
		AXISMUNDI_CAL_IMPORT_TTL
	);
	wp_safe_redirect( add_query_arg( 'ax_cal_import', $token, $base ) );
	exit;
}
add_action( 'admin_post_ax_cal_import_system', 'axismundi_cal_handle_system_import' );

/**
 * Write the previewed years as drafts.
 *
 * @param int    $calendar_id Calendar id.
 * @param string $base        Screen URL.
 * @return void
 */
function axismundi_cal_confirm_system_import( int $calendar_id, string $base ) : void {
	$token = isset( $_POST['import_token'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['import_token'] ) ) : '';
	$held  = get_transient( 'ax_cal_import_' . $token );
	if ( ! is_array( $held ) || (int) $held['calendar_id'] !== $calendar_id ) {
		// The document waited longer than the confirmation took. Re-read rather than import something
		// nobody previewed.
		wp_safe_redirect( add_query_arg( 'ax_cal_error', 'ax_cal_import_expired', $base ) );
		exit;
	}
	$years = isset( $_POST['years'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['years'] ) ) : array();
	if ( array() === $years ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', 'ax_cal_import_no_years', $base ) );
		exit;
	}

	$partition = axismundi_cal_import_partition( axismundi_cal_ics_parse( (string) $held['body'] ), $years );
	$written   = axismundi_cal_import_write( $calendar_id, $partition['keep'], (string) $held['url'] );
	delete_transient( 'ax_cal_import_' . $token );

	wp_safe_redirect(
		add_query_arg(
			array(
				'ax_cal_notice'    => 'imported',
				'imported'         => $written,
				'skipped_timed'    => $partition['timed'],
				'skipped_repeating' => $partition['recurring'],
				'year'             => min( $years ),
			),
			$base
		)
	);
	exit;
}

/**
 * Write parsed entries as drafts.
 *
 * Its own function rather than a loop inside the handler, so what the import decides can be asserted
 * without going through a request that exits. A test that reproduced this loop would be checking its
 * own copy of the rules.
 *
 * @param int                            $calendar_id Calendar id.
 * @param array<int,array<string,mixed>> $entries     Entries to write.
 * @param string                         $source_url  Where they were read from.
 * @return int Number written.
 */
function axismundi_cal_import_write( int $calendar_id, array $entries, string $source_url ) : int {
	$now     = current_time( 'mysql', true );
	$written = 0;
	foreach ( $entries as $entry ) {
		$source_uid = (string) $entry['ical_uid'];
		$existing   = '' !== $source_uid ? axismundi_cal_system_item_by_uid( $calendar_id, $source_uid ) : null;
		$fields     = array(
			'title'       => (string) $entry['summary'],
			'start_date'  => substr( (string) $entry['start_local'], 0, 10 ),
			'end_date'    => substr( (string) $entry['end_local'], 0, 10 ),
			'source_uid'  => $source_uid,
			'source_url'  => $source_url,
			'imported_at' => $now,
		);
		if ( ! is_array( $existing ) ) {
			/*
			 * A first import deliberately makes no classification or publication claim. On later reads,
			 * omit both fields so `system_item_save()` preserves the site's review rather than restoring
			 * the foreign feed as its authority.
			 */
			$fields['categories'] = array();
			$fields['status']     = 'draft';
		}
		$saved = axismundi_cal_system_item_save(
			$calendar_id,
			$fields,
			(int) ( $existing['id'] ?? 0 )
		);
		if ( ! is_wp_error( $saved ) ) {
			++$written;
		}
	}
	return $written;
}

/**
 * The import form, and the preview once a document has been read.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return void
 */
function axismundi_cal_render_system_import( array $calendar ) : void {
	$calendar_id = (int) $calendar['id'];
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen selection.
	$token = isset( $_GET['ax_cal_import'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['ax_cal_import'] ) ) : '';
	$held  = '' !== $token ? get_transient( 'ax_cal_import_' . $token ) : false;
	?>
	<h3><?php esc_html_e( 'Import from an iCalendar address', 'axismundi-calendar' ); ?></h3>

	<?php if ( is_array( $held ) && (int) $held['calendar_id'] === $calendar_id ) : ?>
		<?php
		$entries = axismundi_cal_ics_parse( (string) $held['body'] );
		$years   = axismundi_cal_import_years( $entries );
		$this_year = (int) gmdate( 'Y' );
		?>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: number of entries, 2: source address. */
					__( 'Read %1$d entries from %2$s. Choose the years to bring in; everything arrives as a draft for review.', 'axismundi-calendar' ),
					count( $entries ),
					(string) $held['url']
				)
			);
			?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ax_cal_import_system">
			<input type="hidden" name="calendar_id" value="<?php echo esc_attr( (string) $calendar_id ); ?>">
			<input type="hidden" name="import_token" value="<?php echo esc_attr( $token ); ?>">
			<?php wp_nonce_field( 'ax_cal_import_' . $calendar_id ); ?>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'Years to import', 'axismundi-calendar' ); ?></legend>
				<?php foreach ( $years as $year => $count ) : ?>
					<label style="display:inline-block;min-width:12em;">
						<input type="checkbox" name="years[]" value="<?php echo esc_attr( (string) $year ); ?>"
							<?php checked( $year >= $this_year && $year <= $this_year + 1 ); ?>>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: year, 2: number of entries. */
								_n( '%1$d (%2$d entry)', '%1$d (%2$d entries)', $count, 'axismundi-calendar' ),
								$year,
								$count
							)
						);
						?>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<p class="description">
				<?php esc_html_e( 'This year and next are ticked to begin with. A feed often carries a decade, and importing all of it commits somebody to reviewing years nobody has looked at.', 'axismundi-calendar' ); ?>
			</p>
			<p class="submit">
				<button type="submit" class="button button-primary" name="ax_cal_import_action" value="confirm">
					<?php esc_html_e( 'Import as drafts', 'axismundi-calendar' ); ?>
				</button>
				<a class="button button-secondary" href="<?php echo esc_url( add_query_arg( 'calendar', $calendar_id, admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendar-system' ) ) ); ?>">
					<?php esc_html_e( 'Cancel', 'axismundi-calendar' ); ?>
				</a>
			</p>
		</form>
		<?php return; ?>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ax_cal_import_system">
		<input type="hidden" name="calendar_id" value="<?php echo esc_attr( (string) $calendar_id ); ?>">
		<?php wp_nonce_field( 'ax_cal_import_' . $calendar_id ); ?>
		<p>
			<label class="screen-reader-text" for="ax-cal-import-url"><?php esc_html_e( 'iCalendar address', 'axismundi-calendar' ); ?></label>
			<input name="source_url" id="ax-cal-import-url" type="url" class="large-text" required placeholder="https://example.com/holidays.ics">
		</p>
		<p class="description">
			<?php esc_html_e( 'Read once, not followed. These become this site&rsquo;s dates, to correct and classify; the publisher is recorded but stops being the authority. Nothing is published until somebody reviews it.', 'axismundi-calendar' ); ?>
		</p>
		<p class="submit">
			<button type="submit" class="button button-secondary" name="ax_cal_import_action" value="preview">
				<?php esc_html_e( 'Read it', 'axismundi-calendar' ); ?>
			</button>
		</p>
	</form>
	<?php
}
