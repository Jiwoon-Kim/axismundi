<?php
/**
 * The entries of a maintained calendar (dev-only; dist-excluded).
 *
 * Four properties, each of which fails quietly rather than loudly:
 *
 *   entries are civil dates, so a holiday on the 15th is the 15th everywhere
 *   categories are a closed vocabulary, because a filter can only be built on stable keys
 *   an unreviewed year is invisible to readers, which is what makes review mean anything
 *   an import naming an entry it wrote before updates it rather than adding a second copy
 *
 * The last one is the difference between re-running an import and doubling a year of holidays.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_si_results   = array();
$ax_si_calendars = array();
$ax_si_users     = array();

/** @param bool[] $results Results. */
function ax_si_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** A user with a public Person Actor. */
function ax_si_user( array &$users ) : array {
	$login   = 'ax_si_' . strtolower( wp_generate_password( 8, false, false ) );
	$id      = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$users[] = $id;
	$uri     = '';
	if ( function_exists( 'axismundi_actors_ensure_for_user' ) ) {
		$actor = axismundi_actors_ensure_for_user( $id );
		if ( $actor instanceof Axismundi_Actor ) {
			axismundi_actors_register_handle( $actor->get_identity_id(), $login );
			axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
			$uri = (string) $actor->get_uri();
		}
	}
	return array( 'user_id' => $id, 'actor_uri' => $uri );
}

/** The titles in one result set. */
function ax_si_titles( array $rows ) : array {
	return array_map( static fn( array $row ) : string => (string) $row['title'], $rows );
}

try {
	$ax_si_keeper = ax_si_user( $ax_si_users );
	$ax_si_reader = ax_si_user( $ax_si_users );
	$ax_si_suffix = strtolower( wp_generate_password( 6, false, false ) );

	$ax_si_holidays = axismundi_cal_calendar_save(
		array( 'name' => 'Holidays fixture', 'slug' => 'ax-si-holidays-' . $ax_si_suffix, 'timezone' => 'Asia/Seoul', 'source' => 'manual', 'owner_actor_uri' => $ax_si_keeper['actor_uri'] )
	);
	ax_si_assert( $ax_si_results, 'a maintained calendar can be created', is_int( $ax_si_holidays ) );
	$ax_si_holidays = (int) $ax_si_holidays;
	$ax_si_calendars[] = $ax_si_holidays;

	$ax_si_ordinary = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Ordinary fixture', 'slug' => 'ax-si-ordinary-' . $ax_si_suffix, 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_si_keeper['actor_uri'] )
	);
	$ax_si_calendars[] = $ax_si_ordinary;

	// -- Only a maintained calendar holds these ------------------------------------------------------

	/*
	 * An entry on an ordinary Calendar would appear on the grid belonging to nobody: no author, no
	 * permalink, no federated identity. Refused at the writer rather than left to the screen.
	 */
	$ax_si_wrong = axismundi_cal_system_item_save( $ax_si_ordinary, array( 'title' => 'Nope', 'start_date' => '2027-01-01' ) );
	ax_si_assert( $ax_si_results, 'an ordinary calendar refuses a dataset entry', is_wp_error( $ax_si_wrong ) && 'ax_cal_not_dataset' === $ax_si_wrong->get_error_code() );

	// -- Civil dates -----------------------------------------------------------------------------------

	$ax_si_newyear = axismundi_cal_system_item_save(
		$ax_si_holidays,
		array( 'title' => 'New Year', 'start_date' => '2027-01-01', 'categories' => array( 'HOLIDAY', 'PUBLIC-HOLIDAY' ), 'status' => 'published' )
	);
	ax_si_assert( $ax_si_results, 'an entry saves', is_int( $ax_si_newyear ) );
	$ax_si_row = (array) axismundi_cal_system_item_get( (int) $ax_si_newyear );
	ax_si_assert( $ax_si_results, 'keeping the date it was given, with no timezone applied to it', '2027-01-01' === (string) $ax_si_row['start_date'] );
	/*
	 * The end is exclusive, as `DTEND` is for an all-day VEVENT. Defaulted, because almost every one
	 * of these is a single day and asking for both invites the off-by-one the convention settles.
	 */
	ax_si_assert( $ax_si_results, 'and ending the following day, which is what a one-day all-day entry means', '2027-01-02' === (string) $ax_si_row['end_date'] );
	ax_si_assert( $ax_si_results, 'and not occupying anyone as busy, since a holiday is not an appointment', 'TRANSPARENT' === (string) $ax_si_row['transparency'] );

	ax_si_assert( $ax_si_results, 'a date that is not a date is refused', is_wp_error( axismundi_cal_system_item_save( $ax_si_holidays, array( 'title' => 'Bad', 'start_date' => 'next tuesday' ) ) ) );
	ax_si_assert( $ax_si_results, 'and neither is a day that does not exist', is_wp_error( axismundi_cal_system_item_save( $ax_si_holidays, array( 'title' => 'Bad', 'start_date' => '2027-02-30' ) ) ) );
	ax_si_assert( $ax_si_results, 'nor an entry ending before it starts', is_wp_error( axismundi_cal_system_item_save( $ax_si_holidays, array( 'title' => 'Bad', 'start_date' => '2027-05-05', 'end_date' => '2027-05-01' ) ) ) );
	ax_si_assert( $ax_si_results, 'nor one with no name', is_wp_error( axismundi_cal_system_item_save( $ax_si_holidays, array( 'start_date' => '2027-05-05' ) ) ) );

	// -- Categories are a closed vocabulary -------------------------------------------------------------

	/*
	 * Several at once is the point rather than an allowance: Buddha's Birthday in Korea is a public
	 * holiday and a religious observance at the same time, and a taxonomy forcing a choice records
	 * something false.
	 */
	$ax_si_buddha = (int) axismundi_cal_system_item_save(
		$ax_si_holidays,
		array( 'title' => "Buddha's Birthday", 'start_date' => '2027-05-13', 'categories' => array( 'PUBLIC-HOLIDAY', 'RELIGIOUS', 'BUDDHIST', 'HOLIDAY' ), 'status' => 'published' )
	);
	ax_si_assert(
		$ax_si_results,
		'an entry can be a public holiday and a religious observance at once',
		'HOLIDAY,PUBLIC-HOLIDAY,RELIGIOUS,BUDDHIST' === (string) axismundi_cal_system_item_get( $ax_si_buddha )['categories']
	);
	ax_si_assert(
		$ax_si_results,
		'stored in vocabulary order, so the value does not depend on how somebody typed the list',
		axismundi_cal_normalize_categories( array( 'BUDDHIST', 'HOLIDAY' ) ) === axismundi_cal_normalize_categories( array( 'HOLIDAY', 'BUDDHIST' ) )
	);

	/*
	 * An invented key is dropped rather than stored. It would hide the entry from everyone using the
	 * filter and show it to everyone who is not -- and look exactly like a real category until
	 * somebody wondered where an entry had gone.
	 */
	ax_si_assert( $ax_si_results, 'a key outside the vocabulary is dropped rather than stored', array( 'HOLIDAY' ) === axismundi_cal_normalize_categories( array( 'HOLIDAY', 'BANK-HOLIDAY-KR' ) ) );
	ax_si_assert( $ax_si_results, 'a category is matched by key rather than by the name a reader sees', array( 'PUBLIC-HOLIDAY' ) === axismundi_cal_normalize_categories( 'public-holiday' ) );
	ax_si_assert( $ax_si_results, 'and the same key twice is one category', array( 'HOLIDAY' ) === axismundi_cal_normalize_categories( array( 'HOLIDAY', 'HOLIDAY' ) ) );

	$ax_si_parents = (int) axismundi_cal_system_item_save(
		$ax_si_holidays,
		array( 'title' => 'Parents Day', 'start_date' => '2027-05-08', 'categories' => array( 'HOLIDAY', 'OBSERVANCE' ), 'status' => 'published' )
	);

	// -- Filtering --------------------------------------------------------------------------------------

	$ax_si_all = axismundi_cal_system_items_in_range( $ax_si_holidays, '2027-01-01', '2028-01-01' );
	ax_si_assert( $ax_si_results, 'a year returns its published entries in date order', array( 'New Year', 'Parents Day', "Buddha's Birthday" ) === ax_si_titles( $ax_si_all ) );

	$ax_si_public = axismundi_cal_system_items_in_range( $ax_si_holidays, '2027-01-01', '2028-01-01', array( 'PUBLIC-HOLIDAY' ) );
	ax_si_assert( $ax_si_results, 'filtering by category keeps the public holidays', array( 'New Year', "Buddha's Birthday" ) === ax_si_titles( $ax_si_public ) );
	ax_si_assert(
		$ax_si_results,
		'and leaves out the observance, which is what the Google feed can only express in prose',
		! in_array( 'Parents Day', ax_si_titles( $ax_si_public ), true )
	);
	// Several keys read as "either", the way a set of checkboxes does.
	ax_si_assert(
		$ax_si_results,
		'asking for two categories returns entries carrying either',
		3 === count( axismundi_cal_system_items_in_range( $ax_si_holidays, '2027-01-01', '2028-01-01', array( 'PUBLIC-HOLIDAY', 'OBSERVANCE' ) ) )
	);
	ax_si_assert(
		$ax_si_results,
		'a category matched by prefix is not a match, so BUDDHIST does not answer for HOLIDAY',
		array( "Buddha's Birthday" ) === ax_si_titles( axismundi_cal_system_items_in_range( $ax_si_holidays, '2027-01-01', '2028-01-01', array( 'BUDDHIST' ) ) )
	);

	$ax_si_january = axismundi_cal_system_items_in_range( $ax_si_holidays, '2027-01-01', '2027-02-01' );
	ax_si_assert( $ax_si_results, 'a range returns only what overlaps it', array( 'New Year' ) === ax_si_titles( $ax_si_january ) );

	// -- An unreviewed year is invisible -------------------------------------------------------------------

	/*
	 * The point of a draft year. Dates move: substitute holidays, temporary holidays, election days.
	 * Showing an unreviewed year to readers would make reviewing it pointless.
	 */
	$ax_si_draft = (int) axismundi_cal_system_item_save(
		$ax_si_holidays,
		array( 'title' => 'Unreviewed 2028', 'start_date' => '2028-01-01', 'categories' => array( 'HOLIDAY' ) )
	);
	ax_si_assert( $ax_si_results, 'an entry is a draft until somebody says otherwise', 'draft' === (string) axismundi_cal_system_item_get( $ax_si_draft )['status'] );
	ax_si_assert( $ax_si_results, 'and a draft is not returned to readers', array() === axismundi_cal_system_items_in_range( $ax_si_holidays, '2028-01-01', '2029-01-01' ) );
	ax_si_assert(
		$ax_si_results,
		'while whoever is reviewing it can see it',
		array( 'Unreviewed 2028' ) === ax_si_titles( axismundi_cal_system_items_in_range( $ax_si_holidays, '2028-01-01', '2029-01-01', array(), true ) )
	);
	axismundi_cal_system_item_save( $ax_si_holidays, array( 'status' => 'published' ), $ax_si_draft );
	ax_si_assert( $ax_si_results, 'publishing it makes it visible', 1 === count( axismundi_cal_system_items_in_range( $ax_si_holidays, '2028-01-01', '2029-01-01' ) ) );
	ax_si_assert( $ax_si_results, 'an invented status is refused rather than stored', is_wp_error( axismundi_cal_system_item_save( $ax_si_holidays, array( 'status' => 'maybe' ), $ax_si_draft ) ) );

	// -- Which year an entry belongs to ----------------------------------------------------------------------

	ax_si_assert( $ax_si_results, 'an entry belongs to the year of its date by default', 2027 === (int) axismundi_cal_system_item_get( $ax_si_newyear )['batch_year'] );
	/*
	 * Except when it does not. A substitute holiday for the 31st of December falls in January and
	 * belongs to the year being reviewed, not to the one it lands in.
	 */
	$ax_si_substitute = (int) axismundi_cal_system_item_save(
		$ax_si_holidays,
		array( 'title' => 'Substitute for 31 December', 'start_date' => '2028-01-03', 'batch_year' => 2027, 'categories' => array( 'HOLIDAY', 'SUBSTITUTE-HOLIDAY' ), 'status' => 'published' )
	);
	ax_si_assert( $ax_si_results, 'and can be told which year it belongs to when the date says otherwise', 2027 === (int) axismundi_cal_system_item_get( $ax_si_substitute )['batch_year'] );

	$ax_si_years = axismundi_cal_system_item_years( $ax_si_holidays );
	ax_si_assert( $ax_si_results, 'the years a calendar covers are reported', array( 2027, 2028 ) === array_column( $ax_si_years, 'year' ) );
	ax_si_assert( $ax_si_results, 'with how much of each has been reviewed', 4 === $ax_si_years[0]['total'] && 4 === $ax_si_years[0]['published'] );

	// -- Re-running an import ---------------------------------------------------------------------------------

	/*
	 * The difference between running an import twice and having two of every holiday. An entry
	 * carrying a uid its source gave it is updated rather than added again.
	 */
	$ax_si_imported = (int) axismundi_cal_system_item_save(
		$ax_si_holidays,
		array( 'title' => 'Liberation Day', 'start_date' => '2027-08-15', 'source_uid' => '20270815_abc@example.org', 'categories' => array( 'HOLIDAY', 'PUBLIC-HOLIDAY' ), 'status' => 'published' )
	);
	$ax_si_again = axismundi_cal_system_item_save(
		$ax_si_holidays,
		array( 'title' => 'Liberation Day (corrected)', 'start_date' => '2027-08-15', 'source_uid' => '20270815_abc@example.org', 'status' => 'published' )
	);
	ax_si_assert( $ax_si_results, 'importing the same entry again updates it rather than adding a second', $ax_si_imported === (int) $ax_si_again );
	ax_si_assert( $ax_si_results, 'with the newer title', 'Liberation Day (corrected)' === (string) axismundi_cal_system_item_get( $ax_si_imported )['title'] );
	/*
	 * Entries typed in by hand carry no uid and are always new, which is right: nothing outside
	 * identifies them, so there is nothing to recognise them by.
	 */
	$ax_si_hand_a = (int) axismundi_cal_system_item_save( $ax_si_holidays, array( 'title' => 'Local election', 'start_date' => '2027-06-02', 'categories' => array( 'CIVIC', 'ELECTION' ), 'status' => 'published' ) );
	$ax_si_hand_b = (int) axismundi_cal_system_item_save( $ax_si_holidays, array( 'title' => 'Local election', 'start_date' => '2027-06-02', 'categories' => array( 'CIVIC', 'ELECTION' ), 'status' => 'published' ) );
	ax_si_assert( $ax_si_results, 'while two entries typed by hand stay two entries', $ax_si_hand_a !== $ax_si_hand_b );

	// -- Who may maintain them ----------------------------------------------------------------------------------

	wp_set_current_user( $ax_si_keeper['user_id'] );
	$ax_si_dataset_row = (array) axismundi_cal_calendar_get( $ax_si_holidays );
	ax_si_assert( $ax_si_results, 'whoever may write to a maintained calendar may maintain its entries', true === axismundi_cal_calendar_can( $ax_si_dataset_row, 'manage_items' ) );
	ax_si_assert( $ax_si_results, 'and still may not file an Event there', false === axismundi_cal_calendar_can( $ax_si_dataset_row, 'write_events' ) );
	ax_si_assert(
		$ax_si_results,
		'while an ordinary calendar is the other way round, so neither screen offers the wrong thing',
		false === axismundi_cal_calendar_can( (array) axismundi_cal_calendar_get( $ax_si_ordinary ), 'manage_items' )
			&& true === axismundi_cal_calendar_can( (array) axismundi_cal_calendar_get( $ax_si_ordinary ), 'write_events' )
	);

	axismundi_cal_acl_grant( $ax_si_holidays, $ax_si_reader['actor_uri'], 'reader' );
	wp_set_current_user( $ax_si_reader['user_id'] );
	ax_si_assert( $ax_si_results, 'somebody who may only read it may not maintain it', false === axismundi_cal_calendar_can( $ax_si_dataset_row, 'manage_items' ) );

	// -- A calendar the site publishes, belonging to nobody ---------------------------------------------

	/*
	 * The kind an ordinary Calendar cannot be. Nobody owns a public holiday: the site maintains it,
	 * whoever does that answers to a capability rather than to ownership, and the ACL screen it would
	 * otherwise get offers grants that mean nothing.
	 */
	$ax_si_system = axismundi_cal_calendar_save(
		array(
			'kind'            => 'system',
			'system_provider' => 'holiday',
			'provider_config' => array( 'region' => 'KR', 'source_locale' => 'ko-KR' ),
			'name'       => 'Site holidays',
			'slug'       => 'ax-si-system-' . $ax_si_suffix,
			'system_categories' => array( 'HOLIDAY' ),
			'timezone'   => 'Asia/Seoul',
		)
	);
	ax_si_assert( $ax_si_results, 'a maintained calendar needs no Actor to exist', is_int( $ax_si_system ) );
	$ax_si_system = (int) $ax_si_system;
	$ax_si_calendars[] = $ax_si_system;
	$ax_si_system_row = (array) axismundi_cal_calendar_get( $ax_si_system );

	ax_si_assert( $ax_si_results, 'and has none, which is what it is rather than a state it is waiting to leave', '' === (string) $ax_si_system_row['authority_actor_uri'] );
	ax_si_assert( $ax_si_results, 'it declares the top-level category of its dataset', 'HOLIDAY' === (string) $ax_si_system_row['system_categories'] );
	ax_si_assert( $ax_si_results, 'calendar categories keep only the top-level vocabulary', array( 'ASTRONOMY' ) === axismundi_cal_normalize_system_calendar_categories( array( 'ASTRONOMY', 'MOON-PHASE' ) ) );
	ax_si_assert( $ax_si_results, 'a new system calendar cannot omit its catalog category', is_wp_error( axismundi_cal_calendar_save( array( 'kind' => 'system', 'name' => 'Unclassified', 'slug' => 'ax-si-unclassified-' . $ax_si_suffix, 'timezone' => 'UTC' ) ) ) );
	ax_si_assert( $ax_si_results, 'while an ordinary local calendar still cannot be made without one', is_wp_error( axismundi_cal_calendar_save( array( 'name' => 'No actor', 'slug' => 'ax-si-noactor-' . $ax_si_suffix, 'timezone' => 'UTC' ) ) ) );
	ax_si_assert(
		$ax_si_results,
		'and giving one an Actor is refused rather than quietly accepted',
		is_wp_error( axismundi_cal_record_owner( $ax_si_system, $ax_si_keeper['actor_uri'], 'system' ) )
	);

	/*
	 * Public because of what it is, not because a rule says so. Reading it from the ACL would make it
	 * depend on a row nothing writes, which anybody could remove -- silently unpublishing every
	 * subscription to it.
	 */
	ax_si_assert( $ax_si_results, 'it is readable by everyone as a matter of policy', true === axismundi_cal_is_publicly_readable( $ax_si_system ) );
	ax_si_assert( $ax_si_results, 'without a public rule having been written for it', null === axismundi_cal_acl_rule( $ax_si_system, '', 'public' ) );

	ax_si_assert( $ax_si_results, 'its entries are a dataset', true === axismundi_cal_calendar_is_dataset( $ax_si_system_row ) );
	/* The Calendar UUID already is its stable identity; a second random system-only key adds nothing. */
	axismundi_cal_calendar_save( array( 'name' => 'Renamed holidays' ), $ax_si_system );
	$ax_si_system_row = (array) axismundi_cal_calendar_get( $ax_si_system );
	ax_si_assert( $ax_si_results, 'it uses the Calendar UUID as the one stable identity it needs', '' !== (string) $ax_si_system_row['uuid'] && empty( $ax_si_system_row['system_key'] ) );
	ax_si_assert( $ax_si_results, 'though the name itself changes freely, since it is a translation', 'Renamed holidays' === (string) $ax_si_system_row['name'] );

	ax_si_assert(
		$ax_si_results,
		'and what kind of calendar it is cannot be changed afterwards',
		is_wp_error( axismundi_cal_calendar_save( array( 'kind' => 'local' ), $ax_si_system ) )
	);

	// -- What kind of dataset it holds ------------------------------------------------------------------

	/*
	 * One choice rather than a set of labels, because it decides which writer fills the calendar and
	 * what its entries mean in time. A holiday is a civil date; a moon phase is an instant in UTC that
	 * falls on different days for different readers. Those cannot be the same calendar.
	 */
	ax_si_assert( $ax_si_results, 'a system calendar says what kind of dataset it holds', 'holiday' === axismundi_cal_system_provider( $ax_si_system_row ) );
	ax_si_assert(
		$ax_si_results,
		'and cannot be made without saying so',
		is_wp_error( axismundi_cal_calendar_save( array( 'kind' => 'system', 'name' => 'Unclassified', 'slug' => 'ax-si-unclassified-' . $ax_si_suffix, 'timezone' => 'UTC' ) ) )
	);
	ax_si_assert(
		$ax_si_results,
		'nor with a kind that does not exist',
		is_wp_error( axismundi_cal_calendar_save( array( 'kind' => 'system', 'system_provider' => 'sports', 'name' => 'Sports', 'slug' => 'ax-si-sports-' . $ax_si_suffix, 'timezone' => 'UTC' ) ) )
	);

	/*
	 * Fixed afterwards. Changing it would change what every entry already on the calendar means, and
	 * there is no reading under which a moon phase was ever a public holiday.
	 */
	axismundi_cal_calendar_save( array( 'system_provider' => 'astronomy' ), $ax_si_system );
	ax_si_assert( $ax_si_results, 'and what it holds does not change once entries exist', 'holiday' === axismundi_cal_system_provider( (array) axismundi_cal_calendar_get( $ax_si_system ) ) );

	// -- What that kind needs to know ---------------------------------------------------------------------

	/*
	 * Region and language are separate answers. `KR` says whose dates these are; `ko-KR` says what
	 * language they are written in -- and Japanese holidays read in Korean is a real combination.
	 */
	$ax_si_config = axismundi_cal_provider_config( (array) axismundi_cal_calendar_get( $ax_si_system ) );
	ax_si_assert( $ax_si_results, 'a holiday calendar records whose dates it holds', 'KR' === ( $ax_si_config['region'] ?? '' ) );
	ax_si_assert( $ax_si_results, 'and separately what language they are written in', 'ko-KR' === ( $ax_si_config['source_locale'] ?? '' ) );

	ax_si_assert(
		$ax_si_results,
		'a holiday calendar for nowhere is refused',
		is_wp_error(
			axismundi_cal_calendar_save(
				array( 'kind' => 'system', 'system_provider' => 'holiday', 'provider_config' => array( 'source_locale' => 'ko-KR' ), 'name' => 'Nowhere', 'slug' => 'ax-si-nowhere-' . $ax_si_suffix, 'timezone' => 'UTC' )
			)
		)
	);
	ax_si_assert(
		$ax_si_results,
		'and one in no language is refused too',
		is_wp_error(
			axismundi_cal_calendar_save(
				array( 'kind' => 'system', 'system_provider' => 'holiday', 'provider_config' => array( 'region' => 'KR' ), 'name' => 'No language', 'slug' => 'ax-si-nolang-' . $ax_si_suffix, 'timezone' => 'UTC' )
			)
		)
	);
	ax_si_assert(
		$ax_si_results,
		'a region that is not a region code is refused rather than stored',
		is_wp_error(
			axismundi_cal_calendar_save(
				array( 'kind' => 'system', 'system_provider' => 'holiday', 'provider_config' => array( 'region' => 'South Korea', 'source_locale' => 'ko' ), 'name' => 'Bad region', 'slug' => 'ax-si-badregion-' . $ax_si_suffix, 'timezone' => 'UTC' )
			)
		)
	);

	/*
	 * A WordPress locale arrives with an underscore and a language tag uses a hyphen. Accepted and
	 * normalized rather than refused, since both forms name the same language and a later translation
	 * link is keyed on one of them.
	 */
	$ax_si_underscore = axismundi_cal_calendar_save(
		array( 'kind' => 'system', 'system_provider' => 'holiday', 'provider_config' => array( 'region' => 'jp', 'source_locale' => 'ja_JP' ), 'name' => 'Japan holidays', 'slug' => 'ax-si-jp-' . $ax_si_suffix, 'timezone' => 'Asia/Tokyo' )
	);
	// Asserted rather than guarded on: wrapped in a bare `if`, a refusal here would skip the three
	// checks below and report a shorter, greener run instead of a failure.
	ax_si_assert( $ax_si_results, 'a region and locale given in other forms are accepted', is_int( $ax_si_underscore ) );

	/*
	 * The one choice on core's language list that submits nothing. `wp_dropdown_languages()` uses ''
	 * for English (United States), because core has no translation file for the language it is written
	 * in -- so choosing English was the only option that could not be saved.
	 */
	$_POST['provider_config'] = array( 'region' => 'US', 'source_locale' => '' );
	$ax_si_english = axismundi_cal_read_provider_config_post();
	unset( $_POST['provider_config'] );
	ax_si_assert( $ax_si_results, 'choosing English on the language list means English, not nothing', 'en_US' === ( $ax_si_english['source_locale'] ?? '' ) );
	ax_si_assert(
		$ax_si_results,
		'and a calendar in it can actually be created',
		is_int( axismundi_cal_calendar_save( array( 'kind' => 'system', 'system_provider' => 'holiday', 'provider_config' => $ax_si_english, 'name' => 'Holidays in English', 'slug' => 'ax-si-english-' . $ax_si_suffix, 'timezone' => 'UTC' ) ) )
	);
	/*
	 * While a caller that genuinely sends no language is still refused. The empty string means English
	 * on one control, and nothing at all everywhere else.
	 */
	ax_si_assert(
		$ax_si_results,
		'though a request with no language at all is still refused',
		is_wp_error( axismundi_cal_calendar_save( array( 'kind' => 'system', 'system_provider' => 'holiday', 'provider_config' => array( 'region' => 'US', 'source_locale' => '' ), 'name' => 'No language at all', 'slug' => 'ax-si-nolang2-' . $ax_si_suffix, 'timezone' => 'UTC' ) ) )
	);
	if ( is_int( $ax_si_underscore ) ) {
		$ax_si_calendars[] = $ax_si_underscore;
		$ax_si_jp = axismundi_cal_provider_config( (array) axismundi_cal_calendar_get( $ax_si_underscore ) );
		ax_si_assert( $ax_si_results, 'a region given in lower case is stored as a code', 'JP' === ( $ax_si_jp['region'] ?? '' ) );
		ax_si_assert( $ax_si_results, 'and a WordPress locale is stored as a language tag', 'ja-JP' === ( $ax_si_jp['source_locale'] ?? '' ) );
		/*
		 * Japanese holidays in Japanese and the same dates in Korean are two calendars, not one with
		 * two names: the feeds they come from share no identity, so nothing could merge them.
		 */
		ax_si_assert( $ax_si_results, 'and it is a different calendar from the Korean one', $ax_si_underscore !== $ax_si_system );
	}

	/*
	 * The browsing classification follows from the kind rather than being asked for twice. Two answers
	 * to one question is how a calendar ends up listed under something its own writer is not.
	 */
	ax_si_assert(
		$ax_si_results,
		'the catalog classification follows from what the calendar holds',
		'HOLIDAY' === (string) axismundi_cal_calendar_get( $ax_si_system )['system_categories']
	);
	ax_si_assert(
		$ax_si_results,
		'and the entry categories a holiday calendar expects are offered first',
		array( 'HOLIDAY', 'PUBLIC-HOLIDAY', 'OBSERVANCE', 'SUBSTITUTE-HOLIDAY' ) === axismundi_cal_system_provider_categories( 'holiday' )
	);
	ax_si_assert(
		$ax_si_results,
		'without the vocabulary being narrowed to them, since an election can also be a holiday',
		in_array( 'ELECTION', AXISMUNDI_CAL_ITEM_CATEGORIES, true )
	);
	ax_si_assert(
		$ax_si_results,
		'a kind with no writer yet asks for no settings, rather than settings guessed ahead of it',
		array() === axismundi_cal_system_provider_config_fields( 'astronomy' )
	);

	// -- What can be done with it ----------------------------------------------------------------------

	/*
	 * Sharing and publishing are not refusals of a role somebody lacks -- they are operations with no
	 * meaning on a Calendar that belongs to nobody and is already public.
	 */
	$ax_si_admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	if ( ! empty( $ax_si_admin ) ) {
		wp_set_current_user( (int) $ax_si_admin[0] );
		$ax_si_caps = axismundi_cal_calendar_capabilities( $ax_si_system_row );
		ax_si_assert( $ax_si_results, 'whoever maintains the site maintains its entries', true === $ax_si_caps['manage_items'] );
		ax_si_assert( $ax_si_results, 'and may correct its details', true === $ax_si_caps['edit_details'] );
		ax_si_assert( $ax_si_results, 'while sharing it is not an operation that exists here', false === $ax_si_caps['share'] && false === $ax_si_caps['publish'] );
		ax_si_assert( $ax_si_results, 'nor is writing an Event onto it', false === $ax_si_caps['write_events'] );
		ax_si_assert( $ax_si_results, 'and it appears on the screen that maintains datasets', in_array( $ax_si_system, array_map( static fn( array $c ) : int => (int) $c['id'], axismundi_cal_manageable_datasets() ), true ) );
	}

	wp_set_current_user( $ax_si_keeper['user_id'] );
	$ax_si_caps = axismundi_cal_calendar_capabilities( $ax_si_system_row );
	ax_si_assert( $ax_si_results, 'somebody who is not maintaining the site cannot maintain it', false === $ax_si_caps['manage_items'] && false === $ax_si_caps['edit_details'] );
	ax_si_assert( $ax_si_results, 'but cannot yet be offered an ICS export that has not been implemented', false === $ax_si_caps['export'] );

	// -- Its entries behave like any other dataset -------------------------------------------------------

	$ax_si_site_item = axismundi_cal_system_item_save(
		$ax_si_system,
		array( 'title' => 'Site holiday', 'start_date' => '2027-10-03', 'categories' => array( 'HOLIDAY', 'PUBLIC-HOLIDAY' ), 'status' => 'published' )
	);
	ax_si_assert( $ax_si_results, 'a maintained calendar holds entries like any other dataset', is_int( $ax_si_site_item ) );

	// -- The screen that maintains them --------------------------------------------------------------

	wp_set_current_user( $ax_si_keeper['user_id'] );
	ax_si_assert(
		$ax_si_results,
		'a maintained calendar appears on the screen that maintains it',
		in_array( $ax_si_holidays, array_map( static fn( array $c ) : int => (int) $c['id'], axismundi_cal_manageable_datasets() ), true )
	);
	ax_si_assert(
		$ax_si_results,
		'while an ordinary calendar does not, because it holds Events rather than entries',
		! in_array( $ax_si_ordinary, array_map( static fn( array $c ) : int => (int) $c['id'], axismundi_cal_manageable_datasets() ), true )
	);

	// An entry still waiting to be checked, since everything written above has since been published.
	axismundi_cal_system_item_save( $ax_si_holidays, array( 'title' => 'Still to check', 'start_date' => '2028-03-01', 'categories' => array( 'HOLIDAY' ) ) );

	ob_start();
	axismundi_cal_render_system_item_editor( (array) axismundi_cal_calendar_get( $ax_si_holidays ), 'https://example.test/admin' );
	$ax_si_html = (string) ob_get_clean();
	/*
	 * The year defaults to the latest one with entries rather than to now, so somebody opening this in
	 * December is looking at next year's draft, which is the year that needs reviewing.
	 */
	ax_si_assert( $ax_si_results, 'the editor opens on the most recent year it holds', str_contains( $ax_si_html, 'Unreviewed 2028' ) );
	ax_si_assert(
		$ax_si_results,
		'and shows drafts, since a review screen that hides what needs reviewing is useless',
		str_contains( $ax_si_html, 'Draft' )
	);
	ax_si_assert( $ax_si_results, 'reporting how much of each year has been checked', str_contains( $ax_si_html, 'reviewed' ) );
	ax_si_assert( $ax_si_results, 'and offering the vocabulary as checkboxes rather than a free-text field', str_contains( $ax_si_html, 'name="categories[]"' ) && str_contains( $ax_si_html, 'SUBSTITUTE-HOLIDAY' ) );
	ax_si_assert( $ax_si_results, 'with a nonce bound to this calendar', str_contains( $ax_si_html, 'ax_cal_system_item_' ) || str_contains( $ax_si_html, '_wpnonce' ) );
	/*
	 * Where an entry came from is shown, because a hand-corrected entry and an imported one look
	 * identical and behave differently the next time the import runs.
	 */
	ax_si_assert( $ax_si_results, 'and saying which entries came from an import', str_contains( $ax_si_html, 'Entered here' ) );

	ob_start();
	axismundi_cal_render_system_calendar_form();
	$ax_si_create_html = (string) ob_get_clean();
	/*
	 * One choice rather than several, because it dispatches. Checkboxes invited a calendar to be a bit
	 * of a holiday feed and a bit of an astronomical one, which no writer could then fill.
	 */
	ax_si_assert( $ax_si_results, 'the creation form asks what kind of dataset this is, as one choice', str_contains( $ax_si_create_html, 'type="radio" name="system_provider"' ) );
	ax_si_assert( $ax_si_results, 'offering every kind that can be declared', str_contains( $ax_si_create_html, 'value="astronomy"' ) && str_contains( $ax_si_create_html, 'value="holiday"' ) );
	ax_si_assert( $ax_si_results, 'and no longer as a set of labels to tick', ! str_contains( $ax_si_create_html, 'name="system_categories[]"' ) );
	ax_si_assert(
		$ax_si_results,
		'with the settings a holiday dataset needs asked for on the same form',
		str_contains( $ax_si_create_html, 'provider_config[region]' ) && str_contains( $ax_si_create_html, 'provider_config[source_locale]' )
	);
	ax_si_assert( $ax_si_results, 'and offers the core timezone selector rather than a free-text IANA field', str_contains( $ax_si_create_html, '<select name="timezone"' ) && ! str_contains( $ax_si_create_html, 'name="system_key"' ) );

	wp_set_current_user( $ax_si_reader['user_id'] );
	ax_si_assert( $ax_si_results, 'somebody who may only read it is offered nothing to maintain', array() === axismundi_cal_manageable_datasets() );
	wp_set_current_user( $ax_si_keeper['user_id'] );

	// -- Reading a published feed into a dataset -----------------------------------------------------

	/*
	 * A holiday feed, as Google publishes one: whole days, one VEVENT per date rather than a yearly
	 * rule, and a classification written in prose that no importer can read back. Parsed from a
	 * literal document, because this environment has no network and the property under test is what
	 * the importer does with what it read.
	 */
	$ax_si_feed = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//EN\r\n"
		. "BEGIN:VEVENT\r\nUID:20270101_a@example.org\r\nDTSTART;VALUE=DATE:20270101\r\nDTEND;VALUE=DATE:20270102\r\nSUMMARY:New Year\r\nDESCRIPTION:Public holiday\r\nEND:VEVENT\r\n"
		. "BEGIN:VEVENT\r\nUID:20270301_b@example.org\r\nDTSTART;VALUE=DATE:20270301\r\nDTEND;VALUE=DATE:20270302\r\nSUMMARY:March First\r\nEND:VEVENT\r\n"
		. "BEGIN:VEVENT\r\nUID:20280101_c@example.org\r\nDTSTART;VALUE=DATE:20280101\r\nDTEND;VALUE=DATE:20280102\r\nSUMMARY:New Year 2028\r\nEND:VEVENT\r\n"
		. "BEGIN:VEVENT\r\nUID:20270501_d@example.org\r\nDTSTART:20270501T090000Z\r\nDTEND:20270501T100000Z\r\nSUMMARY:A timed thing\r\nEND:VEVENT\r\n"
		. "BEGIN:VEVENT\r\nUID:yearly_e@example.org\r\nDTSTART;VALUE=DATE:20270601\r\nDTEND;VALUE=DATE:20270602\r\nRRULE:FREQ=YEARLY\r\nSUMMARY:Every year\r\nEND:VEVENT\r\n"
		. "END:VCALENDAR\r\n";
	$ax_si_parsed = axismundi_cal_ics_parse( $ax_si_feed );
	ax_si_assert( $ax_si_results, 'a published feed parses', 5 === count( $ax_si_parsed ) );

	$ax_si_years = axismundi_cal_import_years( $ax_si_parsed );
	ax_si_assert( $ax_si_results, 'and reports how much of each year it carries, so nobody imports a decade unseen', array( 2027 => 4, 2028 => 1 ) === $ax_si_years );

	/*
	 * A dataset entry is a whole day. An entry with a time is something else, and one carrying a
	 * recurrence rule is a claim about every future year that a curated dataset should not accept
	 * unseen -- so both are counted and reported rather than silently dropped.
	 */
	$ax_si_part = axismundi_cal_import_partition( $ax_si_parsed, array( 2027 ) );
	ax_si_assert( $ax_si_results, 'only the chosen year is taken', 2 === count( $ax_si_part['keep'] ) );
	ax_si_assert( $ax_si_results, 'an entry with a time is set aside and counted', 1 === $ax_si_part['timed'] );
	ax_si_assert( $ax_si_results, 'and so is one that claims to repeat forever', 1 === $ax_si_part['recurring'] );

	$ax_si_import_cal = axismundi_cal_calendar_save(
		array(
			'kind'            => 'system',
			'system_provider' => 'holiday',
			'provider_config' => array( 'region' => 'KR', 'source_locale' => 'ko-KR' ),
			'name'            => 'Imported holidays',
			'slug'            => 'ax-si-imported-' . $ax_si_suffix,
			'timezone'        => 'Asia/Seoul',
		)
	);
	ax_si_assert( $ax_si_results, 'a calendar to import into can be made', is_int( $ax_si_import_cal ) );
	$ax_si_import_cal = (int) $ax_si_import_cal;
	$ax_si_calendars[] = $ax_si_import_cal;

	// Through the importer's own writer, not a copy of it: a fixture reproducing the loop would be
	// asserting against its own idea of the rules rather than against the ones that run.
	ax_si_assert(
		$ax_si_results,
		'the import writes what it kept',
		2 === axismundi_cal_import_write( $ax_si_import_cal, $ax_si_part['keep'], 'https://example.org/holidays.ics' )
	);

	/*
	 * Nothing arrives published. Holiday dates move, and a feed is one publisher answer about them
	 * rather than the law -- so a year is somebody judgement before it is anybody information.
	 */
	ax_si_assert( $ax_si_results, 'nothing imported is visible to readers yet', array() === axismundi_cal_system_items_in_range( $ax_si_import_cal, '2027-01-01', '2028-01-01' ) );
	$ax_si_drafts = axismundi_cal_system_items_in_range( $ax_si_import_cal, '2027-01-01', '2028-01-01', array(), true );
	ax_si_assert( $ax_si_results, 'while whoever reviews it sees both entries', array( 'New Year', 'March First' ) === ax_si_titles( $ax_si_drafts ) );
	ax_si_assert( $ax_si_results, 'as whole days ending the following day', '2027-01-01' === (string) $ax_si_drafts[0]['start_date'] && '2027-01-02' === (string) $ax_si_drafts[0]['end_date'] );

	/*
	 * No categories are guessed. The feed classification is prose in whatever language it was
	 * published in -- Google writes it into `DESCRIPTION` -- and reading it back breaks on the first
	 * translation or rewording. Classifying is the review this import exists to feed.
	 */
	ax_si_assert( $ax_si_results, 'and with no categories invented from the publisher prose', '' === (string) $ax_si_drafts[0]['categories'] );
	ax_si_assert( $ax_si_results, 'while the publisher is recorded', 'https://example.org/holidays.ics' === (string) $ax_si_drafts[0]['source_url'] );

	$ax_si_import_ids = array_map( static fn( array $item ) : int => (int) $item['id'], $ax_si_drafts );
	$ax_si_reviewed = axismundi_cal_review_holiday_items(
		$ax_si_import_cal,
		array(
			$ax_si_import_ids[0] => array( 'classification' => 'PUBLIC-HOLIDAY' ),
			$ax_si_import_ids[1] => array( 'classification' => 'OBSERVANCE' ),
		),
		array( $ax_si_import_ids[0] )
	);
	ax_si_assert( $ax_si_results, 'a holiday review classifies each imported entry without guessing from source prose', 2 === $ax_si_reviewed && 'HOLIDAY,PUBLIC-HOLIDAY' === (string) axismundi_cal_system_item_get( $ax_si_import_ids[0] )['categories'] && 'HOLIDAY,OBSERVANCE' === (string) axismundi_cal_system_item_get( $ax_si_import_ids[1] )['categories'] );
	ax_si_assert( $ax_si_results, 'and publishes only the checked, classified entry', 'published' === (string) axismundi_cal_system_item_get( $ax_si_import_ids[0] )['status'] && 'draft' === (string) axismundi_cal_system_item_get( $ax_si_import_ids[1] )['status'] );
	ax_si_assert( $ax_si_results, 'a holiday cannot be published before it has a classification', is_wp_error( axismundi_cal_review_holiday_items( $ax_si_import_cal, array( $ax_si_import_ids[1] => array( 'classification' => '' ) ), array( $ax_si_import_ids[1] ) ) ) );
	$ax_si_bulk = axismundi_cal_bulk_classify_holiday_items( $ax_si_import_cal, array( $ax_si_import_ids[1] ), 'PUBLIC-HOLIDAY' );
	ax_si_assert( $ax_si_results, 'a selected group can be classified together while remaining draft', 1 === $ax_si_bulk && 'HOLIDAY,PUBLIC-HOLIDAY' === (string) axismundi_cal_system_item_get( $ax_si_import_ids[1] )['categories'] && 'draft' === (string) axismundi_cal_system_item_get( $ax_si_import_ids[1] )['status'] );
	$ax_si_bulk_publish = axismundi_cal_bulk_classify_holiday_items( $ax_si_import_cal, array( $ax_si_import_ids[1] ), 'OBSERVANCE', true );
	ax_si_assert( $ax_si_results, 'a bulk classification can publish the same selected entries in one action', 1 === $ax_si_bulk_publish && 'HOLIDAY,OBSERVANCE' === (string) axismundi_cal_system_item_get( $ax_si_import_ids[1] )['categories'] && 'published' === (string) axismundi_cal_system_item_get( $ax_si_import_ids[1] )['status'] );
	ax_si_assert( $ax_si_results, 'a bulk classification needs a real selection', is_wp_error( axismundi_cal_bulk_classify_holiday_items( $ax_si_import_cal, array(), 'OBSERVANCE' ) ) );
	ax_si_assert( $ax_si_results, 'a publish selection cannot name an entry without its review data', is_wp_error( axismundi_cal_review_holiday_items( $ax_si_import_cal, array(), array( $ax_si_import_ids[1] ) ) ) );
	ob_start();
	axismundi_cal_render_system_item_editor( (array) axismundi_cal_calendar_get( $ax_si_import_cal ), 'https://example.test/admin' );
$ax_si_holiday_editor = (string) ob_get_clean();
$ax_si_holiday_controls = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/system-items.js' );
	ax_si_assert( $ax_si_results, 'the holiday review presents public holiday and observance as a mutually exclusive choice', str_contains( $ax_si_holiday_editor, 'type="radio" name="review[' ) && str_contains( $ax_si_holiday_editor, 'Public holiday' ) && str_contains( $ax_si_holiday_editor, 'Observance' ) );
	ax_si_assert( $ax_si_results, 'and offers bulk classification with publication in the same selected-entry action', str_contains( $ax_si_holiday_editor, 'name="selected_items[]"' ) && str_contains( $ax_si_holiday_editor, 'Set and publish selected as observances' ) && str_contains( $ax_si_holiday_editor, 'Set and publish selected as public holidays' ) && str_contains( $ax_si_holiday_editor, 'Publish selected' ) );
ax_si_assert( $ax_si_results, 'with controls to select only drafts or invert the visible selection', str_contains( $ax_si_holiday_editor, 'Select drafts' ) && str_contains( $ax_si_holiday_editor, 'Invert selection' ) && str_contains( $ax_si_holiday_editor, 'data-draft=' ) && str_contains( $ax_si_holiday_controls, 'selectDrafts: function' ) && str_contains( $ax_si_holiday_controls, 'invert: function' ) );

	/*
	 * Re-reading the same feed updates what it wrote before. Without the uid this would double a year
	 * of holidays, which is the difference between a repeatable import and a destructive one.
	 */
	$ax_si_renamed = array_map(
		static function ( array $entry ) : array {
			$entry['summary'] .= ' (again)';
			return $entry;
		},
		$ax_si_part['keep']
	);
	axismundi_cal_import_write( $ax_si_import_cal, $ax_si_renamed, 'https://example.org/holidays.ics' );
	$ax_si_again = axismundi_cal_system_items_in_range( $ax_si_import_cal, '2027-01-01', '2028-01-01', array(), true );
	ax_si_assert( $ax_si_results, 'reading the same feed again updates rather than doubling it', 2 === count( $ax_si_again ) );
	ax_si_assert( $ax_si_results, 'with what it says now', 'New Year (again)' === (string) $ax_si_again[0]['title'] );
	ax_si_assert( $ax_si_results, 'without overwriting the site review that classified and published it', 'HOLIDAY,PUBLIC-HOLIDAY' === (string) $ax_si_again[0]['categories'] && 'published' === (string) $ax_si_again[0]['status'] );

	/*
	 * A correction made by hand survives the next read, because it carries no source identity for the
	 * import to recognise. That is what makes this an import rather than a subscription.
	 */
	axismundi_cal_system_item_save( $ax_si_import_cal, array( 'title' => 'Temporary holiday', 'start_date' => '2027-07-17', 'status' => 'published' ) );
	axismundi_cal_import_write( $ax_si_import_cal, $ax_si_part['keep'], 'https://example.org/holidays.ics' );
	ax_si_assert(
		$ax_si_results,
		'an entry added by hand is untouched by a later read of the feed',
		1 === count( axismundi_cal_system_items_in_range( $ax_si_import_cal, '2027-07-01', '2027-08-01' ) )
	);

	// -- The entries go with the calendar ----------------------------------------------------------------------------

	wp_set_current_user( $ax_si_keeper['user_id'] );
	$ax_si_doomed = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Doomed dataset', 'slug' => 'ax-si-doomed-' . $ax_si_suffix, 'timezone' => 'UTC', 'source' => 'manual', 'owner_actor_uri' => $ax_si_keeper['actor_uri'] )
	);
	axismundi_cal_system_item_save( $ax_si_doomed, array( 'title' => 'Goes with it', 'start_date' => '2027-03-01', 'status' => 'published' ) );
	axismundi_cal_calendar_delete( $ax_si_doomed );
	ax_si_assert( $ax_si_results, 'deleting a maintained calendar takes its entries with it', array() === axismundi_cal_system_items_in_range( $ax_si_doomed, '2027-01-01', '2028-01-01', array(), true ) );
} finally {
	wp_set_current_user( 0 );
	/*
	 * Everything this file named, not only what it expected to be created. Several fixtures exist to
	 * be refused, and each of them was a real row until the guard refusing it was written -- so the
	 * runs before that guard left calendars behind in somebody's admin screen. Sweeping by slug
	 * catches the ones a future guard has not been written for yet.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$ax_si_strays = (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . axismundi_cal_calendars_table() . " WHERE slug LIKE %s", 'ax-si-%' ) );
	foreach ( array_map( 'intval', $ax_si_strays ) as $ax_si_stray ) {
		$ax_si_calendars[] = $ax_si_stray;
	}
	foreach ( array_unique( $ax_si_calendars ) as $ax_si_calendar ) {
		axismundi_cal_set_primary( (int) $ax_si_calendar, false );
		if ( ! axismundi_cal_calendar_delete( (int) $ax_si_calendar ) ) {
			// A system calendar refuses the ordinary delete, since it belongs to the site.
			axismundi_cal_system_items_forget_calendar( (int) $ax_si_calendar );
			axismundi_cal_list_forget_calendar( (int) $ax_si_calendar );
			axismundi_cal_acl_forget_calendar( (int) $ax_si_calendar );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
			$wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => (int) $ax_si_calendar ) );
		}
	}
	foreach ( $ax_si_users as $ax_si_user_id ) {
		wp_delete_user( (int) $ax_si_user_id );
	}
}

$ax_si_failures = count( array_filter( $ax_si_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_si_results ), $ax_si_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_si_failures > 0 ? 1 : 0 );
}
exit( $ax_si_failures > 0 ? 1 : 0 );
