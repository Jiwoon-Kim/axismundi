<?php
/**
 * The holiday behind the rows (dev-only; dist-excluded).
 *
 * Two calendars hold 설날 and Lunar New Year. Nothing in either row relates them: the feeds carry
 * their publisher's identities and share none, and 설날 falls on a different date every year. What
 * relates them is a third thing neither contains, and this file is about recording it without ever
 * inferring it.
 *
 * The properties under test:
 *
 *   three days of one holiday are one concept with three occurrences, not three concepts
 *   a substitute derives its principal day from the holiday and year, rather than asking a person
 *   classification lives on the concept, so a holiday is a public holiday once
 *   a link is recorded, never derived -- candidates are proposed and a person decides
 *   re-importing a feed cannot disturb a link somebody made while reviewing
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_hc_results   = array();
$ax_hc_calendars = array();
$ax_hc_concepts  = array();
$ax_hc_catalogs  = array();

/** @param bool[] $results Results. */
function ax_hc_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$ax_hc_suffix = strtolower( wp_generate_password( 6, false, false ) );

	/*
	 * Two datasets for one country in two languages, which is the arrangement Google forces: its `ko`
	 * and `en` holiday feeds share no UIDs, so they cannot be one calendar.
	 */
	$ax_hc_ko = (int) axismundi_cal_calendar_save(
		array( 'kind' => 'system', 'system_provider' => 'holiday', 'provider_config' => array( 'region' => 'KR', 'source_locale' => 'ko-KR' ), 'name' => 'KR holidays ko', 'slug' => 'ax-hc-ko-' . $ax_hc_suffix, 'timezone' => 'Asia/Seoul' )
	);
	$ax_hc_en = (int) axismundi_cal_calendar_save(
		array( 'kind' => 'system', 'system_provider' => 'holiday', 'provider_config' => array( 'region' => 'KR', 'source_locale' => 'en-US' ), 'name' => 'KR holidays en', 'slug' => 'ax-hc-en-' . $ax_hc_suffix, 'timezone' => 'Asia/Seoul' )
	);
	$ax_hc_calendars = array( $ax_hc_ko, $ax_hc_en );
	ax_hc_assert( $ax_hc_results, 'one country in two languages is two datasets', $ax_hc_ko !== $ax_hc_en && $ax_hc_ko > 0 && $ax_hc_en > 0 );

	// -- The dataset the two of them are ---------------------------------------------------------------

	/*
	 * Wikidata's arrangement one level up. The catalog is what "Korean public holidays" means; the two
	 * calendars are sitelinks onto it, so neither is the original and neither is a translation.
	 */
	$ax_hc_catalog = (int) axismundi_cal_holiday_catalog_save(
		array( 'provider' => 'holiday', 'jurisdiction' => 'KR', 'scope' => 'public-holidays-and-observances', 'label' => 'KR holidays' )
	);
	$ax_hc_catalogs[] = $ax_hc_catalog;
	ax_hc_assert( $ax_hc_results, 'the dataset exists apart from any language', $ax_hc_catalog > 0 );
	ax_hc_assert( $ax_hc_results, 'identified opaquely rather than by what it covers', '' !== (string) axismundi_cal_holiday_catalog_get( $ax_hc_catalog )['uuid'] );
	ax_hc_assert( $ax_hc_results, 'and saying what it covers, so two datasets about one country stay two', 'public-holidays-and-observances' === (string) axismundi_cal_holiday_catalog_get( $ax_hc_catalog )['scope'] );
	ax_hc_assert( $ax_hc_results, 'a scope it cannot cover is refused', is_wp_error( axismundi_cal_holiday_catalog_save( array( 'jurisdiction' => 'KR', 'scope' => 'everything' ) ) ) );
	ax_hc_assert( $ax_hc_results, 'and a catalog covering nowhere', is_wp_error( axismundi_cal_holiday_catalog_save( array( 'jurisdiction' => 'Korea' ) ) ) );

	/*
	 * Offered, never applied. `holiday + KR` describes a great many datasets somebody might mean, and
	 * inferring identity from a description is how two unrelated collections silently become one.
	 */
	$ax_hc_offered = axismundi_cal_holiday_catalog_candidates( 'holiday', 'KR' );
	ax_hc_assert( $ax_hc_results, 'a calendar being made for the same country is offered it', 1 <= count( $ax_hc_offered ) );
	ax_hc_assert( $ax_hc_results, 'while matching settings join nothing on their own', 0 === (int) axismundi_cal_calendar_get( $ax_hc_en )['holiday_catalog_id'] );

	ax_hc_assert( $ax_hc_results, 'joining is something somebody does', true === axismundi_cal_join_holiday_catalog( $ax_hc_ko, $ax_hc_catalog ) );
	axismundi_cal_join_holiday_catalog( $ax_hc_en, $ax_hc_catalog );
	ax_hc_assert( $ax_hc_results, 'and both languages are then the same dataset', 2 === count( axismundi_cal_catalog_calendars( $ax_hc_catalog ) ) );
	ax_hc_assert( $ax_hc_results, 'a catalog that does not exist cannot be joined', is_wp_error( axismundi_cal_join_holiday_catalog( $ax_hc_ko, 999999 ) ) );

	// -- One holiday, three days ---------------------------------------------------------------------

	/*
	 * The three days of 설날 are one concept with three occurrences. Three concepts would make "when
	 * is 설날" unanswerable and leave the substitute relation pointing at whichever fragment happened
	 * to hold the name.
	 */
	$ax_hc_seollal = (int) axismundi_cal_holiday_concept_save(
		array( 'catalog_id' => $ax_hc_catalog, 'label' => '설날', 'categories' => array( 'HOLIDAY', 'PUBLIC-HOLIDAY' ) )
	);
	$ax_hc_concepts[] = $ax_hc_seollal;
	ax_hc_assert( $ax_hc_results, 'a holiday exists apart from any year or language', $ax_hc_seollal > 0 );
	ax_hc_assert( $ax_hc_results, 'identified opaquely rather than by a name in one language', '' !== (string) axismundi_cal_holiday_concept_get( $ax_hc_seollal )['uuid'] );
	ax_hc_assert( $ax_hc_results, 'and belonging to a country', 'KR' === (string) axismundi_cal_holiday_concept_get( $ax_hc_seollal )['jurisdiction'] );
	ax_hc_assert( $ax_hc_results, 'a holiday belonging to no catalog is refused', is_wp_error( axismundi_cal_holiday_concept_save( array( 'label' => 'Nowhere' ) ) ) );
	ax_hc_assert( $ax_hc_results, 'and one with no name to recognise it by', is_wp_error( axismundi_cal_holiday_concept_save( array( 'catalog_id' => $ax_hc_catalog ) ) ) );
	ax_hc_assert( $ax_hc_results, 'where it applies is the catalog answer rather than its own', 'KR' === (string) axismundi_cal_holiday_concept_get( $ax_hc_seollal )['jurisdiction'] );

	// -- What it is, elsewhere -------------------------------------------------------------------------

	/*
	 * Wikidata names the same subject, and saying so is worth recording: it lets somebody state that a
	 * concept is 설날 before any English row exists, and lets a later import propose links from
	 * catalog and date rather than from how alike two titles look.
	 */
	axismundi_cal_holiday_concept_save( array( 'wikidata_qid' => 'Q8249787' ), $ax_hc_seollal );
	ax_hc_assert( $ax_hc_results, 'a holiday can say which Wikidata item is the same subject', 'Q8249787' === (string) axismundi_cal_holiday_concept_get( $ax_hc_seollal )['wikidata_qid'] );
	ax_hc_assert( $ax_hc_results, 'and be found by it within its catalog', $ax_hc_seollal === (int) axismundi_cal_concept_by_qid( $ax_hc_catalog, 'Q8249787' )['id'] );
	ax_hc_assert( $ax_hc_results, 'with somewhere to go and read about it', 'https://www.wikidata.org/wiki/Q8249787' === axismundi_cal_concept_wikidata_url( (array) axismundi_cal_holiday_concept_get( $ax_hc_seollal ) ) );
	/*
	 * An identifier, never the identity. Wikidata is authority for what 설날 is; it is not authority
	 * for which days Korea took off in 2026 or what this site decided to publish.
	 */
	ax_hc_assert( $ax_hc_results, 'while the identity stays this site own', '' !== (string) axismundi_cal_holiday_concept_get( $ax_hc_seollal )['uuid'] );
	// A concept of its own, because the ones below this point do not exist yet.
	$ax_hc_plain = (int) axismundi_cal_holiday_concept_save( array( 'catalog_id' => $ax_hc_catalog, 'label' => 'Unclaimed', 'categories' => array( 'HOLIDAY' ) ) );
	$ax_hc_concepts[] = $ax_hc_plain;
	ax_hc_assert( $ax_hc_results, 'a holiday that names no external item is still a holiday', '' === (string) axismundi_cal_holiday_concept_get( $ax_hc_plain )['wikidata_qid'] );
	ax_hc_assert( $ax_hc_results, 'and nothing is found by an identifier nobody claimed', null === axismundi_cal_concept_by_qid( $ax_hc_catalog, 'Q196627' ) );
	ax_hc_assert( $ax_hc_results, 'an identifier that is not one is refused rather than stored', is_wp_error( axismundi_cal_holiday_concept_save( array( 'wikidata_qid' => 'Seollal' ), $ax_hc_seollal ) ) );

	$ax_hc_eve   = (int) axismundi_cal_holiday_occurrence_save( $ax_hc_seollal, array( 'start_date' => '2026-02-16', 'role' => 'holiday-period', 'status' => 'published' ) );
	$ax_hc_day   = (int) axismundi_cal_holiday_occurrence_save( $ax_hc_seollal, array( 'start_date' => '2026-02-17', 'role' => 'principal', 'status' => 'published' ) );
	$ax_hc_after = (int) axismundi_cal_holiday_occurrence_save( $ax_hc_seollal, array( 'start_date' => '2026-02-18', 'role' => 'holiday-period', 'status' => 'published' ) );
	ax_hc_assert( $ax_hc_results, 'its three days in one year are three occurrences of it', 3 === count( axismundi_cal_holiday_occurrences( $ax_hc_seollal, 2026 ) ) );
	ax_hc_assert( $ax_hc_results, 'one of which is the day the holiday is', 'principal' === (string) axismundi_cal_holiday_occurrence_get( $ax_hc_day )['role'] );
	ax_hc_assert( $ax_hc_results, 'and the others days off around it', 'holiday-period' === (string) axismundi_cal_holiday_occurrence_get( $ax_hc_eve )['role'] );
	ax_hc_assert( $ax_hc_results, 'a day that is none of those things is refused', is_wp_error( axismundi_cal_holiday_occurrence_save( $ax_hc_seollal, array( 'start_date' => '2026-02-19', 'role' => 'maybe' ) ) ) );

	/*
	 * The next year is the same holiday on other dates. That is the whole reason the concept exists
	 * apart from the year: nothing about 2026-02-17 identifies 설날.
	 */
	axismundi_cal_holiday_occurrence_save( $ax_hc_seollal, array( 'start_date' => '2027-02-06', 'role' => 'principal' ) );
	ax_hc_assert( $ax_hc_results, 'next year is the same holiday on another date', 1 === count( axismundi_cal_holiday_occurrences( $ax_hc_seollal, 2027 ) ) );
	ax_hc_assert( $ax_hc_results, 'and both years belong to it', 4 === count( axismundi_cal_holiday_occurrences( $ax_hc_seollal ) ) );

	// -- A substitute derives its principal day ----------------------------------------------------------

	$ax_hc_march = (int) axismundi_cal_holiday_concept_save(
		array( 'catalog_id' => $ax_hc_catalog, 'label' => '삼일절', 'categories' => array( 'HOLIDAY', 'PUBLIC-HOLIDAY' ) )
	);
	$ax_hc_concepts[] = $ax_hc_march;
	$ax_hc_first  = (int) axismundi_cal_holiday_occurrence_save( $ax_hc_march, array( 'start_date' => '2026-03-01', 'role' => 'principal', 'status' => 'published' ) );
	$ax_hc_second = (int) axismundi_cal_holiday_occurrence_save( $ax_hc_march, array( 'start_date' => '2026-03-02', 'role' => 'substitute', 'status' => 'published' ) );
	ax_hc_assert( $ax_hc_results, 'a substitute day belongs to the same holiday as its principal', $ax_hc_march === (int) axismundi_cal_holiday_occurrence_get( $ax_hc_second )['concept_id'] );
	/*
	 * The relation remains useful to readers, but it is derived from the role rather than supplied
	 * by the editor.
	 */
	ax_hc_assert( $ax_hc_results, 'and records that principal automatically', $ax_hc_first === (int) axismundi_cal_holiday_occurrence_get( $ax_hc_second )['substitute_for'] );
	ax_hc_assert( $ax_hc_results, 'while an ordinary day has no derived principal', 0 === (int) axismundi_cal_holiday_occurrence_get( $ax_hc_first )['substitute_for'] );
	ax_hc_assert(
		$ax_hc_results,
		'a substitute without one principal in its holiday year is refused',
		is_wp_error( axismundi_cal_holiday_occurrence_save( $ax_hc_march, array( 'start_date' => '2027-03-02', 'role' => 'substitute' ) ) )
	);

	// -- The rows in each language hang off the day -------------------------------------------------------

	$ax_hc_ko_item = (int) axismundi_cal_system_item_save( $ax_hc_ko, array( 'title' => '설날', 'start_date' => '2026-02-17', 'status' => 'published' ) );
	$ax_hc_en_item = (int) axismundi_cal_system_item_save( $ax_hc_en, array( 'title' => 'Lunar New Year', 'start_date' => '2026-02-17', 'status' => 'published' ) );
	ax_hc_assert( $ax_hc_results, 'an imported row starts attached to nothing', 0 === (int) axismundi_cal_system_item_get( $ax_hc_ko_item )['holiday_occurrence_id'] );

	/*
	 * Proposed, never merged. Same country and same date is usually one answer and sometimes several,
	 * which is exactly the case a person has to settle -- and the reason nothing here decides it.
	 */
	$ax_hc_candidates = axismundi_cal_occurrence_candidates( (array) axismundi_cal_system_item_get( $ax_hc_en_item ), $ax_hc_catalog );
	ax_hc_assert( $ax_hc_results, 'the day it might be about is proposed by date', 1 === count( $ax_hc_candidates ) && $ax_hc_day === (int) $ax_hc_candidates[0]['id'] );
	ax_hc_assert( $ax_hc_results, 'with the holiday named, so somebody can tell whether it is right', '설날' === (string) $ax_hc_candidates[0]['label'] );
	ax_hc_assert(
		$ax_hc_results,
		'and a date belonging to nothing proposes nothing rather than the nearest thing',
		array() === axismundi_cal_occurrence_candidates( array( 'start_date' => '2026-07-04' ), $ax_hc_catalog )
	);

	ax_hc_assert( $ax_hc_results, 'a row can be said to be about one day of a holiday', true === axismundi_cal_link_item_to_occurrence( $ax_hc_ko_item, $ax_hc_day ) );
	axismundi_cal_link_item_to_occurrence( $ax_hc_en_item, $ax_hc_day );
	ax_hc_assert( $ax_hc_results, 'and both languages then hang off the same day', 2 === count( axismundi_cal_occurrence_items( $ax_hc_day ) ) );
	$ax_hc_attached = (int) axismundi_cal_system_item_save( $ax_hc_ko, array( 'title' => '설날 연휴', 'start_date' => '2026-02-16', 'categories' => array( 'HOLIDAY', 'PUBLIC-HOLIDAY' ), 'status' => 'published' ) );
	$ax_hc_attached_day = axismundi_cal_attach_item_to_holiday_concept( $ax_hc_attached, $ax_hc_seollal, 'holiday-period' );
	ax_hc_assert( $ax_hc_results, 'an adjacent entry can join an existing holiday rather than only a same-date candidate', is_int( $ax_hc_attached_day ) && 'holiday-period' === (string) axismundi_cal_holiday_occurrence_get( $ax_hc_attached_day )['role'] );
	$ax_hc_next_period = (int) axismundi_cal_system_item_save( $ax_hc_ko, array( 'title' => '설날 연휴', 'start_date' => '2027-02-05', 'categories' => array( 'HOLIDAY', 'PUBLIC-HOLIDAY' ), 'status' => 'published' ) );
	$ax_hc_next_principal = (int) axismundi_cal_system_item_save( $ax_hc_ko, array( 'title' => '설날', 'start_date' => '2027-02-07', 'categories' => array( 'HOLIDAY', 'PUBLIC-HOLIDAY' ), 'status' => 'published' ) );
	ax_hc_assert( $ax_hc_results, 'a prior local title cannot manufacture this year\'s neutral days', 0 === axismundi_cal_apply_prior_holiday_links( $ax_hc_ko, 2027 ) && 0 === (int) axismundi_cal_system_item_get( $ax_hc_next_period )['holiday_occurrence_id'] && 0 === (int) axismundi_cal_system_item_get( $ax_hc_next_principal )['holiday_occurrence_id'] );
	ax_hc_assert( $ax_hc_results, 'a maintainer can establish those days explicitly', is_int( axismundi_cal_attach_item_to_holiday_concept( $ax_hc_next_period, $ax_hc_seollal, 'holiday-period' ) ) && is_int( axismundi_cal_attach_item_to_holiday_concept( $ax_hc_next_principal, $ax_hc_seollal, 'principal' ) ) );
	$ax_hc_sibling_2027 = (int) axismundi_cal_system_item_save( $ax_hc_en, array( 'title' => 'Seollal', 'start_date' => '2027-02-07', 'status' => 'draft' ) );
	ax_hc_assert(
		$ax_hc_results,
		'a sibling language inherits an already reviewed current-year day without a second review',
		1 === axismundi_cal_apply_prior_holiday_links( $ax_hc_en, 2027 )
		&& $ax_hc_seollal === (int) axismundi_cal_holiday_occurrence_get( (int) axismundi_cal_system_item_get( $ax_hc_sibling_2027 )['holiday_occurrence_id'] )['concept_id']
		&& 'principal' === (string) axismundi_cal_holiday_occurrence_get( (int) axismundi_cal_system_item_get( $ax_hc_sibling_2027 )['holiday_occurrence_id'] )['role']
		&& 'published' === (string) axismundi_cal_system_item_get( $ax_hc_sibling_2027 )['status']
		&& array( 'HOLIDAY', 'PUBLIC-HOLIDAY' ) === axismundi_cal_item_effective_categories( (array) axismundi_cal_system_item_get( $ax_hc_sibling_2027 ) )
	);
	$ax_hc_principal_item = (int) axismundi_cal_system_item_save( $ax_hc_ko, array( 'title' => 'A day of its own', 'start_date' => '2026-08-01', 'categories' => array( 'HOLIDAY', 'OBSERVANCE' ), 'status' => 'published' ) );
	$ax_hc_principal_concept = axismundi_cal_create_principal_holiday_from_item( $ax_hc_principal_item );
	ax_hc_assert( $ax_hc_results, 'an unlinked reviewed entry can become its own principal holiday in one operation', is_int( $ax_hc_principal_concept ) && 'principal' === (string) axismundi_cal_holiday_occurrence_get( (int) axismundi_cal_system_item_get( $ax_hc_principal_item )['holiday_occurrence_id'] )['role'] );
	$ax_hc_unprincipled = (int) axismundi_cal_system_item_save( $ax_hc_ko, array( 'title' => 'Unprincipled substitute', 'start_date' => '2026-09-01', 'categories' => array( 'HOLIDAY', 'PUBLIC-HOLIDAY' ), 'status' => 'draft' ) );
	ax_hc_assert( $ax_hc_results, 'a substitute cannot be the first day recorded for a holiday', is_wp_error( axismundi_cal_attach_item_to_holiday_concept( $ax_hc_unprincipled, $ax_hc_plain, 'substitute' ) ) );
	ax_hc_assert( $ax_hc_results, 'without either becoming the other', '설날' === (string) axismundi_cal_system_item_get( $ax_hc_ko_item )['title'] && 'Lunar New Year' === (string) axismundi_cal_system_item_get( $ax_hc_en_item )['title'] );
	ax_hc_assert( $ax_hc_results, 'a link to a day that does not exist is refused', is_wp_error( axismundi_cal_link_item_to_occurrence( $ax_hc_ko_item, 999999 ) ) );
	axismundi_cal_link_item_to_occurrence( $ax_hc_en_item, 0 );
	ax_hc_assert(
	$ax_hc_results,
	'removing a localized label keeps a reviewed standalone entry',
	'published' === (string) axismundi_cal_system_item_get( $ax_hc_en_item )['status'] && array() === axismundi_cal_normalize_categories( (string) axismundi_cal_system_item_get( $ax_hc_en_item )['categories'] )
);
	axismundi_cal_link_item_to_occurrence( $ax_hc_en_item, $ax_hc_day );

	// -- Classified once ------------------------------------------------------------------------------------

	/*
	 * The payoff. 설날 is a public holiday once rather than once per language and again every year --
	 * which is what the reviewer was otherwise doing by hand, twice.
	 */
	ax_hc_assert(
		$ax_hc_results,
		'a row with no categories of its own takes its holiday&rsquo;s',
		array( 'HOLIDAY', 'PUBLIC-HOLIDAY' ) === axismundi_cal_item_effective_categories( (array) axismundi_cal_system_item_get( $ax_hc_en_item ) )
	);
	ax_hc_assert(
		$ax_hc_results,
		'and an unlinked row still has none, rather than inheriting from nowhere',
		array() === axismundi_cal_item_effective_categories( array( 'categories' => '', 'holiday_occurrence_id' => 0 ) )
	);
	/*
	 * A localized label cannot overrule the holiday it represents. Any exceptional classification needs
	 * an occurrence-level rule, not an English row disagreeing with its Korean sibling.
	 */
	axismundi_cal_system_item_save( $ax_hc_en, array( 'categories' => array( 'HOLIDAY', 'OBSERVANCE' ) ), $ax_hc_en_item );
	ax_hc_assert(
		$ax_hc_results,
		'a localized label cannot overrule its holiday classification',
		array( 'HOLIDAY', 'PUBLIC-HOLIDAY' ) === axismundi_cal_item_effective_categories( (array) axismundi_cal_system_item_get( $ax_hc_en_item ) )
	);
	ax_hc_assert(
	$ax_hc_results,
	'and its stored category survives linking, so removing the label later restores what was reviewed',
	// The row's own half only: `HOLIDAY` is the Calendar's now, and is added back by the effective read.
	array( 'OBSERVANCE' ) === axismundi_cal_normalize_categories( (string) axismundi_cal_system_item_get( $ax_hc_en_item )['categories'] )
);
	axismundi_cal_holiday_occurrence_save( $ax_hc_seollal, array( 'status' => 'draft' ), $ax_hc_day );
	ax_hc_assert( $ax_hc_results, 'changing an occurrence status does not erase localized review categories', array( 'OBSERVANCE' ) === axismundi_cal_normalize_categories( (string) axismundi_cal_system_item_get( $ax_hc_en_item )['categories'] ) );
	axismundi_cal_holiday_occurrence_save( $ax_hc_seollal, array( 'status' => 'published' ), $ax_hc_day );
	axismundi_cal_link_item_to_occurrence( $ax_hc_en_item, 0 );
	// Read effectively, which is what a reader sees: the row's own preserved review, plus the key its
	// Calendar supplies. The row itself keeps only `OBSERVANCE`, which is the half somebody reviewed.
	ax_hc_assert( $ax_hc_results, 'and unlinking returns that preserved category rather than the concept\'s', array( 'HOLIDAY', 'OBSERVANCE' ) === axismundi_cal_item_effective_categories( (array) axismundi_cal_system_item_get( $ax_hc_en_item ) ) );
	axismundi_cal_link_item_to_occurrence( $ax_hc_en_item, $ax_hc_day );

	// -- A re-import leaves the judgement alone ----------------------------------------------------------------

	/*
	 * The link is something somebody decided while reading both rows. A second read of the feed has
	 * nothing to say about it, and losing it would mean redoing the review after every import.
	 */
	axismundi_cal_system_item_save( $ax_hc_ko, array( 'title' => '설날 (updated)', 'start_date' => '2026-02-17', 'source_uid' => 'ko-2026-seollal@example.org' ), $ax_hc_ko_item );
	ax_hc_assert(
		$ax_hc_results,
		're-reading the feed leaves the link somebody made',
		$ax_hc_day === (int) axismundi_cal_system_item_get( $ax_hc_ko_item )['holiday_occurrence_id']
	);
	ax_hc_assert( $ax_hc_results, 'while taking the newer title', '설날 (updated)' === (string) axismundi_cal_system_item_get( $ax_hc_ko_item )['title'] );

	// -- An imported sibling label attaches itself only when the reviewed day is unambiguous -----------

	$ax_hc_march_ko = (int) axismundi_cal_system_item_save( $ax_hc_ko, array( 'title' => '삼일절', 'start_date' => '2026-03-01', 'status' => 'published' ) );
	axismundi_cal_link_item_to_occurrence( $ax_hc_march_ko, $ax_hc_first );
	$ax_hc_imported = array(
		array(
			'ical_uid'    => 'en-2026-march-first@example.org',
			'summary'     => 'Independence Movement Day',
			'start_local' => '2026-03-01 00:00:00',
			'end_local'   => '2026-03-02 00:00:00',
		),
	);
	ax_hc_assert( $ax_hc_results, 'an imported locale label is written', 1 === axismundi_cal_import_write( $ax_hc_en, $ax_hc_imported, 'https://example.org/en-holidays.ics' ) );
	$ax_hc_march_en = axismundi_cal_system_item_by_uid( $ax_hc_en, 'en-2026-march-first@example.org' );
	ax_hc_assert(
		$ax_hc_results,
		'a uniquely matching imported date attaches to the existing occurrence',
		is_array( $ax_hc_march_en ) && $ax_hc_first === (int) $ax_hc_march_en['holiday_occurrence_id']
	);
	ax_hc_assert(
		$ax_hc_results,
		'and inherits its approved state and classification without reading the foreign label',
		is_array( $ax_hc_march_en ) && 'published' === (string) $ax_hc_march_en['status'] && '' === (string) $ax_hc_march_en['categories'] && array( 'HOLIDAY', 'PUBLIC-HOLIDAY' ) === axismundi_cal_item_effective_categories( $ax_hc_march_en )
	);
	axismundi_cal_holiday_occurrence_save( $ax_hc_march, array( 'status' => 'draft' ), $ax_hc_first );
	ax_hc_assert(
	$ax_hc_results,
	'changing the occurrence review state updates every localized label',
	'draft' === (string) axismundi_cal_system_item_get( (int) $ax_hc_march_en['id'] )['status'] && 'draft' === (string) axismundi_cal_system_item_get( $ax_hc_march_ko )['status']
);
	axismundi_cal_holiday_occurrence_save( $ax_hc_march, array( 'status' => 'published' ), $ax_hc_first );

	// -- Different holidays stay different -----------------------------------------------------------------------

	/*
	 * New Year's Day shares the catalog with 설날 and nothing else. Both are Korean public holidays in
	 * the same dataset, and a model that grouped them by that would group everything.
	 */
	$ax_hc_newyear = (int) axismundi_cal_holiday_concept_save( array( 'catalog_id' => $ax_hc_catalog, 'label' => '새해 첫날', 'categories' => array( 'HOLIDAY', 'PUBLIC-HOLIDAY' ) ) );
	$ax_hc_concepts[] = $ax_hc_newyear;
	axismundi_cal_holiday_occurrence_save( $ax_hc_newyear, array( 'start_date' => '2026-01-01', 'role' => 'principal' ) );
	ax_hc_assert( $ax_hc_results, 'a different holiday is a different concept', $ax_hc_newyear !== $ax_hc_seollal );
	ax_hc_assert( $ax_hc_results, 'with its own days', 1 === count( axismundi_cal_holiday_occurrences( $ax_hc_newyear ) ) );
	ax_hc_assert( $ax_hc_results, 'and both are found under the dataset they belong to', in_array( $ax_hc_seollal, array_map( static fn( array $concept ) : int => (int) $concept['id'], axismundi_cal_holiday_concepts( $ax_hc_catalog ) ), true ) && in_array( $ax_hc_newyear, array_map( static fn( array $concept ) : int => (int) $concept['id'], axismundi_cal_holiday_concepts( $ax_hc_catalog ) ), true ) );
	ax_hc_assert( $ax_hc_results, 'while another dataset has none of them', array() === axismundi_cal_holiday_concepts( 999999 ) );

	// -- Only the languages that exist -----------------------------------------------------------------------

	/*
	 * Wikipedia's language menu lists the editions that exist, not the ones that could. This is the
	 * state both of your calendars are in: an English calendar joined to the catalog, holding nothing
	 * -- which must look empty rather than translated.
	 */
	$ax_hc_langs = axismundi_cal_occurrence_languages( $ax_hc_day );
	ax_hc_assert( $ax_hc_results, 'a day offers the languages it actually has', array( 'ko-KR', 'en-US' ) === array_keys( $ax_hc_langs ) );
	ax_hc_assert( $ax_hc_results, 'each being that language own row', '설날 (updated)' === (string) $ax_hc_langs['ko-KR']['title'] );

	$ax_hc_alone = (int) axismundi_cal_holiday_occurrence_save( $ax_hc_newyear, array( 'start_date' => '2026-01-01', 'role' => 'principal' ), 0 );
	$ax_hc_ko_only = (int) axismundi_cal_system_item_save( $ax_hc_ko, array( 'title' => '새해 첫날', 'start_date' => '2026-01-01', 'status' => 'published' ) );
	axismundi_cal_link_item_to_occurrence( $ax_hc_ko_only, $ax_hc_alone );
	ax_hc_assert(
		$ax_hc_results,
		'a day with one language offers one, though the catalog has two calendars',
		array( 'ko-KR' ) === array_keys( axismundi_cal_occurrence_languages( $ax_hc_alone ) )
	);
	ax_hc_assert(
		$ax_hc_results,
		'so joining an English calendar changes nothing a reader sees until something is linked',
		2 === count( axismundi_cal_catalog_calendars( $ax_hc_catalog ) )
	);

	$ax_hc_substitute_item = (int) axismundi_cal_system_item_save( $ax_hc_ko, array( 'title' => '쉬는 날 삼일절', 'start_date' => '2026-03-02', 'status' => 'published' ) );
	axismundi_cal_link_item_to_occurrence( $ax_hc_substitute_item, $ax_hc_second );
	$ax_hc_substitute_range = axismundi_cal_system_items_in_range( $ax_hc_ko, '2026-03-02', '2026-03-03', array(), true );
	ax_hc_assert(
	$ax_hc_results,
	'a linked substitute exposes its derived category to the calendar reader',
	1 === count( $ax_hc_substitute_range ) && array( 'HOLIDAY', 'PUBLIC-HOLIDAY', 'SUBSTITUTE-HOLIDAY' ) === axismundi_cal_normalize_categories( (string) $ax_hc_substitute_range[0]['categories'] )
);
	// -- What an automatic link will and will not do ------------------------------------------------

	/*
	 * An occurrence nobody has confirmed in any language is not evidence. It is often one this very
	 * import created a moment ago through the prior-year path, and following it would be the machine
	 * agreeing with itself -- what makes a date safe to follow is that another language committed to
	 * it first.
	 */
	$ax_hc_lonely_concept = (int) axismundi_cal_holiday_concept_save( array( 'catalog_id' => $ax_hc_catalog, 'label' => 'Unconfirmed day', 'categories' => array( 'HOLIDAY' ) ) );
	$ax_hc_concepts[]     = $ax_hc_lonely_concept;
	$ax_hc_lonely         = (int) axismundi_cal_holiday_occurrence_save( $ax_hc_lonely_concept, array( 'start_date' => '2026-06-06', 'role' => 'principal' ) );
	$ax_hc_lonely_item    = (int) axismundi_cal_system_item_save( $ax_hc_en, array( 'title' => 'Memorial Day', 'start_date' => '2026-06-06' ) );
	ax_hc_assert( $ax_hc_results, 'an occurrence no language has confirmed is not offered automatically', array() === axismundi_cal_auto_link_candidates( (array) axismundi_cal_system_item_get( $ax_hc_lonely_item ), $ax_hc_catalog ) );
	ax_hc_assert( $ax_hc_results, 'so nothing attaches itself to it', false === axismundi_cal_auto_link_imported_holiday_item( $ax_hc_lonely_item ) );
	ax_hc_assert( $ax_hc_results, 'while a person is still offered it, since they can judge what a count cannot', 1 === count( axismundi_cal_occurrence_candidates( (array) axismundi_cal_system_item_get( $ax_hc_lonely_item ), $ax_hc_catalog ) ) );

	/*
	 * Once another language commits to the day it becomes evidence, and the same row links.
	 */
	$ax_hc_lonely_ko = (int) axismundi_cal_system_item_save( $ax_hc_ko, array( 'title' => '현충일', 'start_date' => '2026-06-06' ) );
	axismundi_cal_link_item_to_occurrence( $ax_hc_lonely_ko, $ax_hc_lonely );
	ax_hc_assert( $ax_hc_results, 'a day another language has confirmed is followed', true === axismundi_cal_auto_link_imported_holiday_item( $ax_hc_lonely_item ) );

	/*
	 * And a locale never lands twice on one day. Two entries on one date with a single occurrence
	 * would both pass a count-of-one test, after which `occurrence_languages()` keys by locale and one
	 * of the two labels simply disappears.
	 */
	$ax_hc_second_en = (int) axismundi_cal_system_item_save( $ax_hc_en, array( 'title' => 'Something else that day', 'start_date' => '2026-06-06' ) );
	ax_hc_assert( $ax_hc_results, 'a second entry from the same language is not offered the day it already holds', array() === axismundi_cal_auto_link_candidates( (array) axismundi_cal_system_item_get( $ax_hc_second_en ), $ax_hc_catalog ) );
	ax_hc_assert( $ax_hc_results, 'and does not attach itself over the first', false === axismundi_cal_auto_link_imported_holiday_item( $ax_hc_second_en ) );
	ax_hc_assert( $ax_hc_results, 'nor can a direct write put two labels from one calendar on that day', is_wp_error( axismundi_cal_link_item_to_occurrence( $ax_hc_second_en, $ax_hc_lonely ) ) );
	ax_hc_assert( $ax_hc_results, 'so the day keeps one label per language', 2 === count( axismundi_cal_occurrence_languages( $ax_hc_lonely ) ) );

	/*
	 * Unlinking is a decision. Nothing records that it was deliberate, so an import that reattached
	 * every existing row would turn it into something the reviewer has to keep deciding -- the honest
	 * reading of a row that was already here is that its link is whatever somebody wanted.
	 */
	axismundi_cal_link_item_to_occurrence( $ax_hc_lonely_item, 0 );
	$ax_hc_reimport = array(
		array(
			'ical_uid'    => 'en-2026-memorial@example.org',
			'summary'     => 'Memorial Day',
			'start_local' => '2026-06-06 00:00:00',
			'end_local'   => '2026-06-07 00:00:00',
		),
	);
	axismundi_cal_import_write( $ax_hc_en, $ax_hc_reimport, 'https://example.org/en.ics' );
	$ax_hc_fresh = axismundi_cal_system_item_by_uid( $ax_hc_en, 'en-2026-memorial@example.org' );
	ax_hc_assert( $ax_hc_results, 'a row an import creates is linked where the day is established', is_array( $ax_hc_fresh ) && 0 < (int) $ax_hc_fresh['holiday_occurrence_id'] );
	axismundi_cal_link_item_to_occurrence( (int) $ax_hc_fresh['id'], 0 );
	axismundi_cal_import_write( $ax_hc_en, $ax_hc_reimport, 'https://example.org/en.ics' );
	ax_hc_assert(
		$ax_hc_results,
		'and reading the feed again leaves a row somebody unlinked alone',
		0 === (int) axismundi_cal_system_item_by_uid( $ax_hc_en, 'en-2026-memorial@example.org' )['holiday_occurrence_id']
	);

	/* A localized row never promotes the occurrence it joins; review flows from the neutral day down. */
	$ax_hc_draft_concept = (int) axismundi_cal_holiday_concept_save( array( 'catalog_id' => $ax_hc_catalog, 'label' => 'Draft-only holiday', 'categories' => array( 'HOLIDAY', 'OBSERVANCE' ) ) );
	$ax_hc_concepts[]    = $ax_hc_draft_concept;
	$ax_hc_draft_day     = (int) axismundi_cal_holiday_occurrence_save( $ax_hc_draft_concept, array( 'start_date' => '2026-06-07', 'role' => 'principal', 'status' => 'draft' ) );
	$ax_hc_published_label = (int) axismundi_cal_system_item_save( $ax_hc_en, array( 'title' => 'A reviewed label', 'start_date' => '2026-06-07', 'status' => 'published' ) );
	axismundi_cal_link_item_to_occurrence( $ax_hc_published_label, $ax_hc_draft_day );
	ax_hc_assert( $ax_hc_results, 'a published localized row cannot publish the draft occurrence it joins', 'draft' === (string) axismundi_cal_holiday_occurrence_get( $ax_hc_draft_day )['status'] && 'draft' === (string) axismundi_cal_system_item_get( $ax_hc_published_label )['status'] );

	// -- Which label a day is shown with ---------------------------------------------------------------

	/*
	 * A day is shown once whatever languages it has, and the fallback is what makes a catalog nobody
	 * has translated readable rather than absent.
	 */
	/*
	 * The site reads in English here, and that beats what the client asked for: a browser carrying a
	 * Korean pack is not a request for Korean names from an account and a site set to English.
	 */
	$ax_hc_label = axismundi_cal_resolve_occurrence_label( $ax_hc_day, array( 'ko-KR' ) );
	ax_hc_assert( $ax_hc_results, 'a day is shown in the language this site reads in, over the one asked for', 'en-US' === $ax_hc_label['locale'] );
	ax_hc_assert( $ax_hc_results, 'and says which language that turned out to be', isset( $ax_hc_label['locale'], $ax_hc_label['item_id'] ) );

	$ax_hc_english = axismundi_cal_resolve_occurrence_label( $ax_hc_day, array( 'en-GB' ) );
	ax_hc_assert( $ax_hc_results, 'a region nobody has is answered by the same language', 'en-US' === $ax_hc_english['locale'] );

	/*
	 * The Japanese case: a catalog with one language only. A Korean reader sees it in English rather
	 * than not at all, which is the ordinary state of a catalog nobody has translated.
	 */
	ax_hc_assert( $ax_hc_results, 'a day with no label in the language asked for is still shown', null !== axismundi_cal_resolve_occurrence_label( $ax_hc_alone, array( 'ja-JP' ) ) );
	ax_hc_assert( $ax_hc_results, 'in whichever language it does have', 'ko-KR' === axismundi_cal_resolve_occurrence_label( $ax_hc_alone, array( 'ja-JP' ) )['locale'] );
	/*
	 * Ordered rather than whatever the query returned first: an unordered tail is a coin toss, not a
	 * fallback, and the same day would read differently between two requests.
	 */
	ax_hc_assert(
		$ax_hc_results,
		'and the same way every time it is asked',
		axismundi_cal_resolve_occurrence_label( $ax_hc_day, array( 'ja-JP' ) ) === axismundi_cal_resolve_occurrence_label( $ax_hc_day, array( 'ja-JP' ) )
	);
	ax_hc_assert( $ax_hc_results, 'while a day with no labels at all is nothing to show', null === axismundi_cal_resolve_occurrence_label( $ax_hc_lonely_2027 ?? 999999, array( 'ko-KR' ) ) );

	/*
	 * Script matters where region does not. `ko-KR` and `ko` are one language written one way; Hans
	 * and Hant are not, and a language-only rule would hand Traditional to a Simplified reader while
	 * reporting a match.
	 */
	ax_hc_assert( $ax_hc_results, 'a region is not part of what makes two tags the same language', axismundi_cal_language_key( 'ko-KR' ) === axismundi_cal_language_key( 'ko' ) );
	ax_hc_assert( $ax_hc_results, 'while a script is', axismundi_cal_language_key( 'zh-Hans' ) !== axismundi_cal_language_key( 'zh-Hant' ) );
	ax_hc_assert( $ax_hc_results, 'and a script survives a region beside it', axismundi_cal_language_key( 'zh-Hant-TW' ) === axismundi_cal_language_key( 'zh-Hant' ) );

	/*
	 * What somebody chose beats what their browser carries: a Korean language pack is not a request
	 * for Korean names from an account set to English.
	 */
	$ax_hc_order = axismundi_cal_preferred_locales( array( 'ja-JP' ) );
	ax_hc_assert( $ax_hc_results, 'the language somebody chose is asked for before the one their browser carries', array_search( 'ja-JP', $ax_hc_order, true ) === count( $ax_hc_order ) - 1 );

	// -- The screens that record it -----------------------------------------------------------------------

	$ax_hc_admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	if ( ! empty( $ax_hc_admin ) ) {
		wp_set_current_user( (int) $ax_hc_admin[0] );

		ob_start();
		axismundi_cal_render_catalog_join( (array) axismundi_cal_calendar_get( $ax_hc_ko ) );
		$ax_hc_join_html = (string) ob_get_clean();
		/*
		 * Already joined, so it says which dataset and who its siblings are rather than offering to
		 * join again -- and names both languages, since neither is the original.
		 */
		ax_hc_assert( $ax_hc_results, 'a joined calendar says which dataset it is', str_contains( $ax_hc_join_html, 'KR holidays' ) );
		ax_hc_assert( $ax_hc_results, 'and lists the languages it exists in', str_contains( $ax_hc_join_html, 'ko-KR' ) && str_contains( $ax_hc_join_html, 'en-US' ) );

		/*
		 * An unjoined one offers the match and the alternative together. Being about the same country
		 * is a reason to ask, never a reason to join.
		 */
		$ax_hc_loose = (int) axismundi_cal_calendar_save(
			array( 'kind' => 'system', 'system_provider' => 'holiday', 'provider_config' => array( 'region' => 'KR', 'source_locale' => 'ja-JP' ), 'name' => 'KR holidays ja', 'slug' => 'ax-hc-ja-' . $ax_hc_suffix, 'timezone' => 'Asia/Seoul' )
		);
		$ax_hc_calendars[] = $ax_hc_loose;
		ob_start();
		axismundi_cal_render_catalog_join( (array) axismundi_cal_calendar_get( $ax_hc_loose ) );
		$ax_hc_offer_html = (string) ob_get_clean();
		ax_hc_assert( $ax_hc_results, 'an unjoined calendar is offered the dataset about the same country', str_contains( $ax_hc_offer_html, 'name="catalog_id"' ) && str_contains( $ax_hc_offer_html, 'KR holidays' ) );
		ax_hc_assert( $ax_hc_results, 'and the alternative of staying separate', str_contains( $ax_hc_offer_html, 'Start a separate dataset' ) );
		ax_hc_assert( $ax_hc_results, 'while rendering the offer joins nothing', 0 === (int) axismundi_cal_calendar_get( $ax_hc_loose )['holiday_catalog_id'] );

		/*
		 * A row already linked shows what it is. One with a candidate is offered it. One with neither
		 * is a holiday nobody has recorded, and the reviewer looking at it is who knows.
		 */
		ob_start();
		axismundi_cal_render_item_links(
			(array) axismundi_cal_calendar_get( $ax_hc_ko ),
			array( (array) axismundi_cal_system_item_get( $ax_hc_ko_item ) ),
			2026
		);
		$ax_hc_linked_html = (string) ob_get_clean();
		ax_hc_assert( $ax_hc_results, 'a linked entry shows the holiday it is a day of', str_contains( $ax_hc_linked_html, '설날' ) );
		ax_hc_assert( $ax_hc_results, 'and can be removed from the holiday without deleting it', str_contains( $ax_hc_linked_html, 'Remove from holiday' ) );
		ax_hc_assert( $ax_hc_results, 'and lets a maintainer edit its holiday name and day role', str_contains( $ax_hc_linked_html, 'name="concept_label"' ) && str_contains( $ax_hc_linked_html, 'type="radio" name="role"' ) && str_contains( $ax_hc_linked_html, 'Holiday period' ) && str_contains( $ax_hc_linked_html, 'Save role' ) );
		ax_hc_assert( $ax_hc_results, 'without asking which principal a substitute stands in for', ! str_contains( $ax_hc_linked_html, 'Stands in for' ) && ! str_contains( $ax_hc_linked_html, 'substitute_for' ) );
		$_GET['year'] = 2026;
		ob_start();
		axismundi_cal_render_system_item_editor( (array) axismundi_cal_calendar_get( $ax_hc_ko ), 'https://example.test/admin' );
		$ax_hc_review_html = (string) ob_get_clean();
		unset( $_GET['year'] );
		ax_hc_assert( $ax_hc_results, 'the review table shows a substitute role as checked but does not offer it as an item edit', str_contains( $ax_hc_review_html, '<input type="checkbox" checked disabled> Substitute day' ) && ! str_contains( $ax_hc_review_html, '[substitute]' ) );

		$ax_hc_orphan = (int) axismundi_cal_system_item_save( $ax_hc_ko, array( 'title' => '제헌절', 'start_date' => '2026-07-17', 'categories' => array( 'HOLIDAY', 'OBSERVANCE' ), 'status' => 'published' ) );
		ob_start();
		axismundi_cal_render_item_links( (array) axismundi_cal_calendar_get( $ax_hc_ko ), array( (array) axismundi_cal_system_item_get( $ax_hc_orphan ) ), 2026 );
		$ax_hc_orphan_html = (string) ob_get_clean();
		ax_hc_assert( $ax_hc_results, 'an entry about nothing yet shows its classification separately from its missing link', str_contains( $ax_hc_orphan_html, 'Observance' ) && str_contains( $ax_hc_orphan_html, 'Not linked yet' ) );
		ax_hc_assert( $ax_hc_results, 'and offers principal-day bulk saving where the holiday links are edited', str_contains( $ax_hc_orphan_html, 'Save selected as principal days' ) && str_contains( $ax_hc_orphan_html, 'name="item_ids[]"' ) && str_contains( $ax_hc_orphan_html, 'togglePrincipals' ) );
		ax_hc_assert( $ax_hc_results, 'and can apply established links there', str_contains( $ax_hc_orphan_html, 'Apply established holiday links' ) && str_contains( $ax_hc_orphan_html, 'ax_cal_apply_prior_holiday_links' ) );
		ax_hc_assert( $ax_hc_results, 'and can name a new holiday', str_contains( $ax_hc_orphan_html, 'New holiday name' ) && str_contains( $ax_hc_orphan_html, 'Save holiday link' ) );
		ax_hc_assert( $ax_hc_results, 'or join an existing holiday with the roles a day can have', str_contains( $ax_hc_orphan_html, 'name="concept_id"' ) && str_contains( $ax_hc_orphan_html, 'holiday-period' ) && str_contains( $ax_hc_orphan_html, 'substitute' ) );

		/*
		 * Promotion carries the classification up, which is what stops it being done once per language
		 * and again every year.
		 */
		$ax_hc_promoted = (int) axismundi_cal_holiday_concept_save(
			array( 'catalog_id' => $ax_hc_catalog, 'label' => '제헌절', 'categories' => implode( ',', axismundi_cal_item_effective_categories( (array) axismundi_cal_system_item_get( $ax_hc_orphan ) ) ) )
		);
		$ax_hc_concepts[] = $ax_hc_promoted;
		ax_hc_assert(
			$ax_hc_results,
			'a holiday made from an entry keeps how that entry was classified',
			'HOLIDAY,OBSERVANCE' === (string) axismundi_cal_holiday_concept_get( $ax_hc_promoted )['categories']
		);

		wp_set_current_user( 0 );
	}
} finally {
	foreach ( $ax_hc_calendars as $ax_hc_calendar ) {
		axismundi_cal_system_items_forget_calendar( (int) $ax_hc_calendar );
		axismundi_cal_list_forget_calendar( (int) $ax_hc_calendar );
		axismundi_cal_acl_forget_calendar( (int) $ax_hc_calendar );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => (int) $ax_hc_calendar ) );
	}
	foreach ( $ax_hc_catalogs as $ax_hc_catalog_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_cal_holiday_catalogs_table(), array( 'id' => (int) $ax_hc_catalog_id ) );
	}
	foreach ( $ax_hc_concepts as $ax_hc_concept ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_cal_holiday_occurrences_table(), array( 'concept_id' => (int) $ax_hc_concept ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_cal_holiday_concepts_table(), array( 'id' => (int) $ax_hc_concept ) );
	}
}

$ax_hc_failures = count( array_filter( $ax_hc_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_hc_results ), $ax_hc_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_hc_failures > 0 ? 1 : 0 );
}
exit( $ax_hc_failures > 0 ? 1 : 0 );
