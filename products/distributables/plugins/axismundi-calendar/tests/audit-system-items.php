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
			'kind'       => 'system',
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
	ax_si_assert( $ax_si_results, 'the creation form classifies the system calendar itself', str_contains( $ax_si_create_html, 'name="system_categories[]"' ) && str_contains( $ax_si_create_html, 'Astronomy' ) );
	ax_si_assert( $ax_si_results, 'and offers the core timezone selector rather than a free-text IANA field', str_contains( $ax_si_create_html, '<select name="timezone"' ) && ! str_contains( $ax_si_create_html, 'name="system_key"' ) );

	wp_set_current_user( $ax_si_reader['user_id'] );
	ax_si_assert( $ax_si_results, 'somebody who may only read it is offered nothing to maintain', array() === axismundi_cal_manageable_datasets() );
	wp_set_current_user( $ax_si_keeper['user_id'] );

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
	foreach ( array_unique( $ax_si_calendars ) as $ax_si_calendar ) {
		axismundi_cal_set_primary( (int) $ax_si_calendar, false );
		axismundi_cal_calendar_delete( (int) $ax_si_calendar );
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
