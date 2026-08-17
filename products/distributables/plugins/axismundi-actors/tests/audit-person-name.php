<?php
/**
 * The structured person name, and what it will not store (dev-only; dist-excluded).
 *
 * This slice is the storage the three representations will share, so what it pins is mostly what
 * must not happen to it: a pronunciation nobody can read, a saved name quietly dropping the
 * pronunciation next to it, and a former name being one careless line away from a public document.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_pn_results = array();
$ax_pn_users   = array();
$ax_pn_groups  = array();

/** @param bool[] $results Results. */
function ax_pn_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with a Person Actor. */
function ax_pn_actor( array &$users ) : Axismundi_Actor {
	$login = 'axpn' . strtolower( wp_generate_password( 8, false, false ) );
	$id    = (int) wp_insert_user(
		array( 'user_login' => $login, 'user_email' => $login . '@example.test', 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), $login );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return axismundi_actors_get_for_user( $id );
}

try {
	global $wpdb;
	$ax_pn_actor = ax_pn_actor( $ax_pn_users );
	$ax_pn_id    = (int) $ax_pn_actor->get_identity_id();

	// -- one name, two writings -------------------------------------------------------------------------

	axismundi_actors_set_person_name( $ax_pn_id, 'ko-KR', array( 'family_name' => '김', 'given_name' => '지운', 'display_order' => 'family-given' ) );
	axismundi_actors_set_person_name( $ax_pn_id, 'en', array( 'family_name' => 'Kim', 'given_name' => 'Jiwoon', 'display_order' => 'given-family' ) );
	$ax_pn_rows = axismundi_actors_person_names( $ax_pn_id );
	ax_pn_assert(
		$ax_pn_results,
		'a name written in two scripts is two rows of one person, each assembled in its own order',
		'김지운' === axismundi_actors_assemble_person_name( $ax_pn_rows['ko-KR'] )
			&& 'Jiwoon Kim' === axismundi_actors_assemble_person_name( $ax_pn_rows['en'] )
	);

	// -- a pronunciation says how it is written ----------------------------------------------------------

	/*
	 * The check that keeps the contact document honest. `jee-WOON` is not IPA, and a phonetic value
	 * with no notation beside it is read by whatever the consumer assumes -- which is how somebody's
	 * name gets said wrongly on the authority of a file we published.
	 */
	$ax_pn_unreadable = axismundi_actors_set_person_name( $ax_pn_id, 'en', array( 'phonetic_given' => 'jee-WOON' ) );
	ax_pn_assert(
		$ax_pn_results,
		'a pronunciation with no notation or script is refused rather than stored',
		is_wp_error( $ax_pn_unreadable ) && 'ax_actors_name_phonetic_unreadable' === $ax_pn_unreadable->get_error_code()
	);
	$ax_pn_invented = axismundi_actors_set_person_name( $ax_pn_id, 'en', array( 'phonetic_given' => 'jee-WOON', 'phonetic_system' => 'shouty-caps' ) );
	ax_pn_assert(
		$ax_pn_results,
		'and a notation this site cannot name is refused too, rather than invented into the document',
		is_wp_error( $ax_pn_invented ) && 'ax_actors_name_phonetic_system' === $ax_pn_invented->get_error_code()
	);
	$ax_pn_stored = axismundi_actors_set_person_name(
		$ax_pn_id,
		'en',
		array( 'phonetic_family' => 'KIM', 'phonetic_given' => 'ˈdʒiːwuːn', 'phonetic_system' => 'ipa' )
	);
	ax_pn_assert(
		$ax_pn_results,
		'while one that says which notation it is in is kept, per part',
		true === $ax_pn_stored
			&& 'ipa' === (string) axismundi_actors_person_names( $ax_pn_id )['en']['phonetic_system']
			&& 'KIM' === (string) axismundi_actors_person_names( $ax_pn_id )['en']['phonetic_family']
	);
	// A script with no registered notation is still an answer: this is written in Hiragana.
	ax_pn_assert(
		$ax_pn_results,
		'a script alone is enough to say how a value is written',
		true === axismundi_actors_set_person_name( $ax_pn_id, 'ja', array( 'given_name' => 'ジウン', 'phonetic_given' => 'じうん', 'phonetic_script' => 'Hira' ) )
	);

	// -- saving a name does not erase what sits beside it -------------------------------------------------

	/*
	 * The row is written with REPLACE, so a caller that mentions only the parts is saying "leave the
	 * rest", not "empty it". Without this, editing a name on one screen drops the pronunciation
	 * entered on another, and nobody finds out until a card goes out without it.
	 */
	axismundi_actors_set_person_name( $ax_pn_id, 'en', array( 'family_name' => 'Kim', 'given_name' => 'Jiwoon', 'display_order' => 'given-family' ) );
	$ax_pn_after = axismundi_actors_person_names( $ax_pn_id )['en'];
	ax_pn_assert(
		$ax_pn_results,
		'saving the name again keeps the pronunciation stored beside it',
		'KIM' === (string) $ax_pn_after['phonetic_family'] && 'ipa' === (string) $ax_pn_after['phonetic_system']
	);

	/*
	 * And the state between keeping and changing: an empty value clears. Without it a merge becomes a
	 * store nobody can erase anything from -- an editor sending the fields it owns would preserve a
	 * pronunciation forever, and removing one would need a verb of its own.
	 */
	axismundi_actors_set_person_name( $ax_pn_id, 'en', array( 'phonetic_given' => '' ) );
	$ax_pn_cleared = axismundi_actors_person_names( $ax_pn_id )['en'];
	ax_pn_assert(
		$ax_pn_results,
		'sending one pronunciation empty clears that one and leaves the others alone',
		'' === (string) $ax_pn_cleared['phonetic_given']
			&& 'KIM' === (string) $ax_pn_cleared['phonetic_family']
			&& 'ipa' === (string) $ax_pn_cleared['phonetic_system']
	);
	// The order is a stored fact too, so a save that says nothing about it must not reset it.
	axismundi_actors_set_person_name( $ax_pn_id, 'ko-KR', array( 'given_name' => '지운' ) );
	ax_pn_assert(
		$ax_pn_results,
		'and editing a name without mentioning its order keeps the order it was written in',
		'family-given' === (string) axismundi_actors_person_names( $ax_pn_id )['ko-KR']['display_order']
			&& '김지운' === axismundi_actors_assemble_person_name( axismundi_actors_person_names( $ax_pn_id )['ko-KR'] )
	);

	/*
	 * And the pronunciation is judged on the row that will exist, not on what the caller sent. A
	 * partial update is the normal case -- one screen sends the components, another the notation -- so
	 * removing the last value must take the notation with it rather than leaving a setting that
	 * describes nothing and waits to be attached to whatever somebody types next.
	 */
	axismundi_actors_set_person_name( $ax_pn_id, 'en', array( 'phonetic_family' => '' ) );
	$ax_pn_orphan = axismundi_actors_person_names( $ax_pn_id )['en'];
	ax_pn_assert(
		$ax_pn_results,
		'removing the last pronunciation takes its notation with it, leaving no setting describing nothing',
		'' === (string) $ax_pn_orphan['phonetic_family']
			&& '' === (string) $ax_pn_orphan['phonetic_system']
			&& '' === (string) $ax_pn_orphan['phonetic_script']
	);
	// A notation with nothing to pronounce is the same emptiness arriving from the other direction.
	axismundi_actors_set_person_name( $ax_pn_id, 'en', array( 'phonetic_system' => 'ipa' ) );
	ax_pn_assert(
		$ax_pn_results,
		'and a notation offered with no pronunciation to go with it is not stored either',
		'' === (string) axismundi_actors_person_names( $ax_pn_id )['en']['phonetic_system']
	);
	/*
	 * The refusal is judged the same way. The values are already stored; clearing only the notation
	 * would leave a pronunciation nobody can read, so it is refused rather than normalized away --
	 * normalizing here would silently delete what somebody wrote.
	 */
	axismundi_actors_set_person_name( $ax_pn_id, 'en', array( 'phonetic_given' => 'ˈdʒiːwuːn', 'phonetic_system' => 'ipa' ) );
	$ax_pn_stranded = axismundi_actors_set_person_name( $ax_pn_id, 'en', array( 'phonetic_system' => '', 'phonetic_script' => '' ) );
	ax_pn_assert(
		$ax_pn_results,
		'while clearing the notation out from under a stored pronunciation is refused, not quietly obeyed',
		is_wp_error( $ax_pn_stranded )
			&& 'ax_actors_name_phonetic_unreadable' === $ax_pn_stranded->get_error_code()
			&& 'ipa' === (string) axismundi_actors_person_names( $ax_pn_id )['en']['phonetic_system']
	);

	// -- a script is a subtag, not a word ---------------------------------------------------------------------

	ax_pn_assert(
		$ax_pn_results,
		'a script that is not a four-letter subtag is refused, since no consumer could resolve it',
		is_wp_error( axismundi_actors_set_person_name( $ax_pn_id, 'ja', array( 'phonetic_given' => 'じうん', 'phonetic_script' => 'Hiragana' ) ) )
	);
	axismundi_actors_set_person_name( $ax_pn_id, 'ja', array( 'phonetic_given' => 'じうん', 'phonetic_script' => 'hira' ) );
	ax_pn_assert(
		$ax_pn_results,
		'while one written in any case is stored as the subtag it is',
		'Hira' === (string) axismundi_actors_person_names( $ax_pn_id )['ja']['phonetic_script']
	);

	// -- other names are not localizations ------------------------------------------------------------------

	ax_pn_assert(
		$ax_pn_results,
		'the other-name kinds name different names, and localizations are not among them',
		in_array( 'nickname', AXISMUNDI_ACTORS_ALTERNATE_NAME_KINDS, true )
			&& in_array( 'former', AXISMUNDI_ACTORS_ALTERNATE_NAME_KINDS, true )
			&& ! in_array( 'localization', AXISMUNDI_ACTORS_ALTERNATE_NAME_KINDS, true )
	);
	/*
	 * The one that matters. A previous name of a real person turning up in a public file is not a
	 * mistake anybody gets to take back, so the publishable set is a named subset rather than a
	 * decision made again at each serializer.
	 */
	ax_pn_assert(
		$ax_pn_results,
		'only a nickname may ever leave this site, and every publishable kind is a real kind',
		array( 'nickname' ) === AXISMUNDI_ACTORS_PUBLISHED_ALTERNATE_NAME_KINDS
			&& array() === array_diff( AXISMUNDI_ACTORS_PUBLISHED_ALTERNATE_NAME_KINDS, AXISMUNDI_ACTORS_ALTERNATE_NAME_KINDS )
	);

	// -- the storage is there and the version says so ---------------------------------------------------------

	$ax_pn_columns = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . axismundi_actors_person_names_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema check.
	$ax_pn_alt     = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . axismundi_actors_alternate_names_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema check.
	ax_pn_assert(
		$ax_pn_results,
		'both tables carry what this slice added, and the recorded version says the migration ran',
		array() === array_diff( array_keys( AXISMUNDI_ACTORS_NAME_PHONETIC_PARTS ), $ax_pn_columns )
			&& in_array( 'phonetic_system', $ax_pn_columns, true )
			&& in_array( 'name_kind', $ax_pn_alt, true )
			&& AXISMUNDI_ACTORS_DB_VERSION === (string) get_option( 'ax_actors_db_version', '' )
	);
	// And re-running it changes nothing, which is what an upgrade on a live site does.
	axismundi_actors_install();
	ax_pn_assert(
		$ax_pn_results,
		'running the migration again leaves the stored name exactly as it was',
		'ˈdʒiːwuːn' === (string) ( axismundi_actors_person_names( $ax_pn_id )['en']['phonetic_given'] ?? '' )
			&& 'ipa' === (string) ( axismundi_actors_person_names( $ax_pn_id )['en']['phonetic_system'] ?? '' )
			&& 'Jiwoon Kim' === axismundi_actors_assemble_person_name( axismundi_actors_person_names( $ax_pn_id )['en'] )
			&& AXISMUNDI_ACTORS_DB_VERSION === (string) get_option( 'ax_actors_db_version', '' )
	);

	// -- the account name is a starting point, offered once ------------------------------------------------------

	/*
	 * Eligibility is a history, not an emptiness. Reading "the table has no rows" would let the
	 * WordPress account name walk back in after somebody deliberately removed their structured name --
	 * once, quietly, looking exactly like the software remembering something helpful.
	 */
	$ax_pn_fresh    = ax_pn_actor( $ax_pn_users );
	$ax_pn_fresh_id = (int) $ax_pn_fresh->get_identity_id();
	wp_update_user( array( 'ID' => (int) $ax_pn_fresh->get_local_user_id(), 'first_name' => 'Ji-woon', 'last_name' => 'Kim' ) );
	ax_pn_assert(
		$ax_pn_results,
		'an Actor whose name nobody has decided can start from the account name',
		! axismundi_actors_person_name_was_edited( $ax_pn_fresh_id )
			&& true === axismundi_actors_seed_person_name_from_user( $ax_pn_fresh_id )
			&& 'Ji-woon Kim' === axismundi_actors_assemble_person_name( axismundi_actors_person_names( $ax_pn_fresh_id )[ axismundi_actors_normalize_language_tag( (string) $ax_pn_fresh->get_default_language() ) ] )
	);
	ax_pn_assert(
		$ax_pn_results,
		'and once it has one, copying the account name again is refused rather than overwriting it',
		axismundi_actors_person_name_was_edited( $ax_pn_fresh_id )
			&& is_wp_error( axismundi_actors_seed_person_name_from_user( $ax_pn_fresh_id ) )
	);
	/*
	 * The case the flag exists for. Somebody removes their structured name; the table is empty again,
	 * exactly as it was before they ever touched it, and the account name must still stay out.
	 */
	$ax_pn_emptied    = ax_pn_actor( $ax_pn_users );
	$ax_pn_emptied_id = (int) $ax_pn_emptied->get_identity_id();
	wp_update_user( array( 'ID' => (int) $ax_pn_emptied->get_local_user_id(), 'first_name' => 'Ji-woon', 'last_name' => 'Kim' ) );
	axismundi_actors_set_person_name( $ax_pn_emptied_id, 'en', array( 'given_name' => 'Someone' ) );
	axismundi_actors_delete_person_name( $ax_pn_emptied_id, 'en' );
	$ax_pn_reseed = axismundi_actors_seed_person_name_from_user( $ax_pn_emptied_id );
	ax_pn_assert(
		$ax_pn_results,
		'a name somebody deliberately emptied stays empty; the account name does not come back into it',
		array() === axismundi_actors_person_names( $ax_pn_emptied_id )
			&& is_wp_error( $ax_pn_reseed )
			&& 'ax_actors_name_seed_decided' === $ax_pn_reseed->get_error_code()
	);

	// -- the other names, per kind -------------------------------------------------------------------------------

	axismundi_actors_set_alternate_names( $ax_pn_id, 'nickname', array( array( 'value' => 'Jay' ), array( 'value' => '지운이', 'language_tag' => 'ko-KR' ) ) );
	axismundi_actors_set_alternate_names( $ax_pn_id, 'former', array( array( 'value' => 'A Previous Name' ) ) );
	$ax_pn_nicknames = axismundi_actors_alternate_names( $ax_pn_id, 'nickname' );
	ax_pn_assert(
		$ax_pn_results,
		'other names keep the order they were given in, and a name of no particular language says so',
		2 === count( $ax_pn_nicknames )
			&& 'Jay' === (string) $ax_pn_nicknames[0]['value']
			&& '' === (string) $ax_pn_nicknames[0]['language_tag']
			&& 'ko-KR' === (string) $ax_pn_nicknames[1]['language_tag']
	);
	/*
	 * Each kind is answered for separately. Clearing the nicknames must not be a way to lose a former
	 * name -- a whole-table replace would make one list's edit into another list's deletion.
	 */
	axismundi_actors_set_alternate_names( $ax_pn_id, 'nickname', array() );
	ax_pn_assert(
		$ax_pn_results,
		'clearing one kind leaves the others exactly where they were',
		array() === axismundi_actors_alternate_names( $ax_pn_id, 'nickname' )
			&& 1 === count( axismundi_actors_alternate_names( $ax_pn_id, 'former' ) )
	);
	// The reader a serializer uses, so nothing has to remember which kinds are publishable.
	axismundi_actors_set_alternate_names( $ax_pn_id, 'nickname', array( array( 'value' => 'Jay' ) ) );
	$ax_pn_publishable = axismundi_actors_published_alternate_names( $ax_pn_id );
	ax_pn_assert(
		$ax_pn_results,
		'and the publishable reader hands over the nickname while the former name never leaves the store',
		1 === count( $ax_pn_publishable )
			&& 'Jay' === (string) $ax_pn_publishable[0]['value']
			&& 'nickname' === (string) $ax_pn_publishable[0]['name_kind']
			&& 1 === count( axismundi_actors_alternate_names( $ax_pn_id, 'former' ) )
	);
	ax_pn_assert(
		$ax_pn_results,
		'a kind this site does not know is refused rather than stored as a word nobody reads',
		is_wp_error( axismundi_actors_set_alternate_names( $ax_pn_id, 'stage_name', array( array( 'value' => 'Nope' ) ) ) )
	);

	// -- a name belongs to a person ----------------------------------------------------------------------------

	$ax_pn_group = axismundi_actors_create_managed_actor(
		array(
			'owner_user_id'      => (int) $ax_pn_users[0],
			'actor_type'         => 'Group',
			'preferred_username' => 'axpn' . strtolower( wp_generate_password( 6, false, false ) ),
		)
	);
	ax_pn_assert(
		$ax_pn_results,
		'a Group has a name and not a given name, so the structured form refuses it',
		$ax_pn_group instanceof Axismundi_Actor
			&& is_wp_error( axismundi_actors_set_person_name( (int) $ax_pn_group->get_identity_id(), 'en', array( 'given_name' => 'Nope' ) ) )
	);
	if ( $ax_pn_group instanceof Axismundi_Actor ) {
		$ax_pn_groups[] = (int) $ax_pn_group->get_identity_id();
	}
} finally {
	global $wpdb;
	foreach ( array_unique( $ax_pn_groups ) as $ax_pn_identity_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $ax_pn_identity_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $ax_pn_identity_id ), array( '%d' ) );
	}
	foreach ( array_unique( $ax_pn_users ) as $ax_pn_user_id ) {
		$ax_pn_gone = axismundi_actors_get_for_user( (int) $ax_pn_user_id );
		if ( $ax_pn_gone instanceof Axismundi_Actor ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
			$wpdb->delete( axismundi_actors_person_names_table(), array( 'identity_id' => (int) $ax_pn_gone->get_identity_id() ), array( '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
			$wpdb->delete( axismundi_actors_alternate_names_table(), array( 'identity_id' => (int) $ax_pn_gone->get_identity_id() ), array( '%d' ) );
		}
		wp_delete_user( (int) $ax_pn_user_id );
	}
}

$ax_pn_failures = count( array_filter( $ax_pn_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_pn_results ), $ax_pn_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_pn_failures > 0 ? 1 : 0 );
}
exit( $ax_pn_failures > 0 ? 1 : 0 );
