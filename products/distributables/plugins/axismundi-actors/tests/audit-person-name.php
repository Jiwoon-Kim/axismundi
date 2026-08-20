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

// WP-CLI does not load the administrator screen helpers this fixture renders.
require_once dirname( __DIR__ ) . '/includes/admin.php';

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

	// -- the parts of a name are not kept here ------------------------------------------------------------

	/*
	 * What this file used to open with was a name stored in components and assembled in the order its
	 * owner chose. The components are the contact card's now, and nothing here assembles anything: an
	 * Actor holds one written-out string per language, which is what `name` and `nameMap` publish.
	 */
	ax_pn_assert(
		$ax_pn_results,
		'nothing here builds a name out of parts any more',
		! function_exists( 'axismundi_actors_assemble_person_name' )
			&& ! function_exists( 'axismundi_actors_publish_structured_name' )
			&& ! function_exists( 'axismundi_actors_jscontact_name' )
			&& ! function_exists( 'axismundi_actors_set_person_name' )
	);

	axismundi_actors_set_default_language( $ax_pn_id, 'ko-KR' );
	axismundi_actors_set_text( $ax_pn_id, 'name', 'ko-KR', '김지운' );
	axismundi_actors_set_text( $ax_pn_id, 'name', 'en', 'Jiwoon Kim' );
	ax_pn_assert(
		$ax_pn_results,
		'each language holds a written-out name, with no components invented for it',
		'김지운' === axismundi_actors_person_display_name( axismundi_actors_get_by_identity( $ax_pn_id ) )
			&& 'Jiwoon Kim' === axismundi_actors_person_display_name( axismundi_actors_get_by_identity( $ax_pn_id ), 'en' )
	);

	// -- a pronunciation says how it is written ----------------------------------------------------------

	/*
	 * The check that keeps the contact document honest. `jee-WOON` is not IPA, and a phonetic value
	 * with no notation beside it is read by whatever the consumer assumes -- which is how somebody's
	 * name gets said wrongly on the authority of a file we published.
	 */
	$ax_pn_unreadable = axismundi_actors_write_person_profile( $ax_pn_id, array( 'phonetic_given' => 'jee-WOON' ) );
	ax_pn_assert(
		$ax_pn_results,
		'a pronunciation with no notation or script is refused rather than stored',
		is_wp_error( $ax_pn_unreadable ) && 'ax_actors_name_phonetic_unreadable' === $ax_pn_unreadable->get_error_code()
	);
	$ax_pn_invented = axismundi_actors_write_person_profile( $ax_pn_id, array( 'phonetic_given' => 'jee-WOON', 'phonetic_system' => 'shouty-caps' ) );
	ax_pn_assert(
		$ax_pn_results,
		'and a notation this site cannot name is refused too, rather than invented into the document',
		is_wp_error( $ax_pn_invented ) && 'ax_actors_name_phonetic_system' === $ax_pn_invented->get_error_code()
	);
	$ax_pn_stored = axismundi_actors_write_person_profile(
		$ax_pn_id,
		array( 'phonetic_surname' => 'KIM', 'phonetic_given' => 'ˈdʒiːwuːn', 'phonetic_system' => 'ipa' )
	);
	ax_pn_assert(
		$ax_pn_results,
		'while one that says which notation it is in is kept, per part',
		true === $ax_pn_stored
			&& 'ipa' === (string) axismundi_actors_person_profile( $ax_pn_id )['phonetic_system']
			&& 'KIM' === (string) axismundi_actors_person_profile( $ax_pn_id )['phonetic_surname']
	);
	// A script with no registered notation is still an answer: this is written in Hiragana.
	ax_pn_assert(
		$ax_pn_results,
		'a script alone is enough to say how a value is written',
		true === axismundi_actors_write_person_profile( $ax_pn_id, array( 'given' => 'ジウン', 'phonetic_given' => 'じうん', 'phonetic_script' => 'Hira' ) )
	);

	// -- saving a name does not erase what sits beside it -------------------------------------------------

	/*
	 * The row is written with REPLACE, so a caller that mentions only the parts is saying "leave the
	 * rest", not "empty it". Without this, editing a name on one screen drops the pronunciation
	 * entered on another, and nobody finds out until a card goes out without it.
	 */
	axismundi_actors_write_person_profile( $ax_pn_id, array( 'structured_name_language' => 'ko-KR', 'surname' => 'Kim', 'given' => 'Jiwoon', 'display_order' => 'given-family' ) );
	$ax_pn_after = axismundi_actors_person_profile( $ax_pn_id );
	ax_pn_assert(
		$ax_pn_results,
		'saving the name again keeps the pronunciation stored beside it',
		'KIM' === (string) $ax_pn_after['phonetic_surname'] && 'ipa' === (string) $ax_pn_after['phonetic_system']
	);

	/*
	 * And the state between keeping and changing: an empty value clears. Without it a merge becomes a
	 * store nobody can erase anything from -- an editor sending the fields it owns would preserve a
	 * pronunciation forever, and removing one would need a verb of its own.
	 */
	axismundi_actors_write_person_profile( $ax_pn_id, array( 'phonetic_given' => '' ) );
	$ax_pn_cleared = axismundi_actors_person_profile( $ax_pn_id );
	ax_pn_assert(
		$ax_pn_results,
		'sending one pronunciation empty clears that one and leaves the others alone',
		'' === (string) $ax_pn_cleared['phonetic_given']
			&& 'KIM' === (string) $ax_pn_cleared['phonetic_surname']
			&& 'ipa' === (string) $ax_pn_cleared['phonetic_system']
	);
	/*
	 * A field the form did not send is left alone, which is what lets one screen write the label and
	 * another the pronunciation without either erasing the other's work.
	 */
	axismundi_actors_write_person_profile( $ax_pn_id, array( 'structured_name_language' => 'ko-KR', 'display_name' => 'Shown as this' ) );
	axismundi_actors_write_person_profile( $ax_pn_id, array( 'phonetic_surname' => 'KIM' ) );
	ax_pn_assert(
		$ax_pn_results,
		'a save that says nothing about a stored value keeps it',
		'Shown as this' === (string) axismundi_actors_person_profile( $ax_pn_id )['display_name']
			&& 'ko-KR' === (string) axismundi_actors_person_profile( $ax_pn_id )['structured_name_language']
			&& 'KIM' === (string) axismundi_actors_person_profile( $ax_pn_id )['phonetic_surname']
	);

	/*
	 * And the pronunciation is judged on the row that will exist, not on what the caller sent. A
	 * partial update is the normal case -- one screen sends the components, another the notation -- so
	 * removing the last value must take the notation with it rather than leaving a setting that
	 * describes nothing and waits to be attached to whatever somebody types next.
	 */
	axismundi_actors_write_person_profile( $ax_pn_id, array( 'phonetic_surname' => '' ) );
	$ax_pn_orphan = axismundi_actors_person_profile( $ax_pn_id );
	ax_pn_assert(
		$ax_pn_results,
		'removing the last pronunciation takes its notation with it, leaving no setting describing nothing',
		'' === (string) $ax_pn_orphan['phonetic_surname']
			&& '' === (string) $ax_pn_orphan['phonetic_system']
			&& '' === (string) $ax_pn_orphan['phonetic_script']
	);
	// A notation with nothing to pronounce is the same emptiness arriving from the other direction.
	axismundi_actors_write_person_profile( $ax_pn_id, array( 'phonetic_system' => 'ipa' ) );
	ax_pn_assert(
		$ax_pn_results,
		'and a notation offered with no pronunciation to go with it is not stored either',
		'' === (string) axismundi_actors_person_profile( $ax_pn_id )['phonetic_system']
	);
	/*
	 * The refusal is judged the same way. The values are already stored; clearing only the notation
	 * would leave a pronunciation nobody can read, so it is refused rather than normalized away --
	 * normalizing here would silently delete what somebody wrote.
	 */
	axismundi_actors_write_person_profile( $ax_pn_id, array( 'phonetic_given' => 'ˈdʒiːwuːn', 'phonetic_system' => 'ipa' ) );
	$ax_pn_stranded = axismundi_actors_write_person_profile( $ax_pn_id, array( 'phonetic_system' => '', 'phonetic_script' => '' ) );
	ax_pn_assert(
		$ax_pn_results,
		'while clearing the notation out from under a stored pronunciation is refused, not quietly obeyed',
		is_wp_error( $ax_pn_stranded )
			&& 'ax_actors_name_phonetic_unreadable' === $ax_pn_stranded->get_error_code()
			&& 'ipa' === (string) axismundi_actors_person_profile( $ax_pn_id )['phonetic_system']
	);

	// -- a script is a subtag, not a word ---------------------------------------------------------------------

	ax_pn_assert(
		$ax_pn_results,
		'a script that is not a four-letter subtag is refused, since no consumer could resolve it',
		is_wp_error( axismundi_actors_write_person_profile( $ax_pn_id, array( 'phonetic_given' => 'じうん', 'phonetic_script' => 'Hiragana' ) ) )
	);
	axismundi_actors_write_person_profile( $ax_pn_id, array( 'phonetic_given' => 'じうん', 'phonetic_script' => 'hira' ) );
	ax_pn_assert(
		$ax_pn_results,
		'while one written in any case is stored as the subtag it is',
		'Hira' === (string) axismundi_actors_person_profile( $ax_pn_id )['phonetic_script']
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

	$ax_pn_columns = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . axismundi_actors_profile_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema check.
	$ax_pn_alt     = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . axismundi_actors_alternate_names_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema check.
	ax_pn_assert(
		$ax_pn_results,
		'the profile store is there and the recorded version says the migration ran',
		array() === array_diff( AXISMUNDI_ACTORS_PROFILE_COLUMNS, $ax_pn_columns )
			&& in_array( 'name_kind', $ax_pn_alt, true )
			&& AXISMUNDI_ACTORS_DB_VERSION === (string) get_option( 'ax_actors_db_version', '' )
	);
	/*
	 * And re-running it changes nothing, which is what an upgrade on a live site does. Stated from a
	 * known profile, because there is one of them now: earlier checks in this file write over each
	 * other where they used to sit in separate language rows.
	 */
	axismundi_actors_write_person_profile(
		$ax_pn_id,
		array( 'given' => 'Jiwoon', 'surname' => 'Kim', 'display_order' => 'given-family', 'phonetic_given' => 'ˈdʒiːwuːn', 'phonetic_system' => 'ipa', 'phonetic_script' => '' )
	);
	axismundi_actors_install();
	ax_pn_assert(
		$ax_pn_results,
		'running the migration again leaves the stored name exactly as it was',
		'ˈdʒiːwuːn' === (string) ( axismundi_actors_person_profile( $ax_pn_id )['phonetic_given'] ?? '' )
			&& 'ipa' === (string) ( axismundi_actors_person_profile( $ax_pn_id )['phonetic_system'] ?? '' )
			&& AXISMUNDI_ACTORS_DB_VERSION === (string) get_option( 'ax_actors_db_version', '' )
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
		'a profile is authored here for a local Actor, and never for one another server sent',
		$ax_pn_group instanceof Axismundi_Actor
			&& is_wp_error( axismundi_actors_write_person_profile( 0, array( 'display_name' => 'Nope' ) ) )
	);

	/*
	 * The existing Profile languages surface owns the Person name inputs; it is not a second section.
	 * Rendered in the language the parts belong to, because that is the only one that offers them --
	 * every other language of the same profile is a written-out name.
	 */
	axismundi_actors_set_default_language( $ax_pn_id, 'ko-KR' );
	ob_start();
	axismundi_actors_text_form( axismundi_actors_get_by_identity( $ax_pn_id ) );
	$ax_pn_profile_language_form = (string) ob_get_clean();
	ax_pn_assert(
		$ax_pn_results,
		'the primary profile offers a name, a label and a pronunciation, and asks for no parts at all',
		str_contains( $ax_pn_profile_language_form, 'name="name"' )
			&& str_contains( $ax_pn_profile_language_form, 'name="display_name"' )
			&& str_contains( $ax_pn_profile_language_form, 'name="phonetic_given"' )
			&& ! str_contains( $ax_pn_profile_language_form, 'name="given"' )
			&& ! str_contains( $ax_pn_profile_language_form, 'name="given2"' )
			&& ! str_contains( $ax_pn_profile_language_form, 'name="surname"' )
			&& ! str_contains( $ax_pn_profile_language_form, 'name="surname2"' )
			&& ! str_contains( $ax_pn_profile_language_form, 'name="display_order"' )
	);
	ax_pn_assert(
		$ax_pn_results,
		'Profile languages fixes the primary profile at the left, lets every profile change its own language, and keeps translation selection behind an add action',
		false !== strpos( $ax_pn_profile_language_form, 'primary &middot; ko-KR' )
			&& str_contains( $ax_pn_profile_language_form, 'id="ax-actor-primary-language" name="profile_language"' )
			&& str_contains( $ax_pn_profile_language_form, 'Add translated profile' )
			&& ! str_contains( $ax_pn_profile_language_form, 'id="ax-actor-add-language"' )
			&& ! str_contains( $ax_pn_profile_language_form, 'name="make_default"' )
	);
	$_GET['ax_actor_add_language'] = '1';
	ob_start();
	axismundi_actors_text_form( axismundi_actors_get_by_identity( $ax_pn_id ) );
	$ax_pn_add_translation_form = (string) ob_get_clean();
	unset( $_GET['ax_actor_add_language'] );
	ax_pn_assert(
		$ax_pn_results,
		'the add action opens the BCP 47 profile-language selector only when it is needed',
		str_contains( $ax_pn_add_translation_form, 'id="ax-actor-add-language" name="ax_actor_lang"' )
			&& str_contains( $ax_pn_add_translation_form, 'Open translated profile' )
	);
	ax_pn_assert(
		$ax_pn_results,
		'the name box is a name box, editable rather than assembled from something else on the page',
		str_contains( $ax_pn_profile_language_form, 'id="ax-actor-name" name="name"' )
			&& ! str_contains( $ax_pn_profile_language_form, 'readonly' )
	);
	/*
	 * A secondary language is the same profile written out again. Offering it name parts would ask
	 * somebody to say which half of `Jiwoon Kim` is a surname for a second time, and let two languages
	 * disagree about a fact the person has only once.
	 */
	$_GET['ax_actor_lang'] = 'en-US';
	ob_start();
	axismundi_actors_text_form( axismundi_actors_get_by_identity( $ax_pn_id ) );
	$ax_pn_secondary_form = (string) ob_get_clean();
	unset( $_GET['ax_actor_lang'] );
	ax_pn_assert(
		$ax_pn_results,
		'a secondary language offers a written-out name and a summary, can change its profile language, and never repeats name details',
		str_contains( $ax_pn_secondary_form, 'name="name"' )
			&& str_contains( $ax_pn_secondary_form, 'name="summary"' )
			&& str_contains( $ax_pn_secondary_form, 'id="ax-actor-primary-language" name="profile_language"' )
			&& str_contains( $ax_pn_secondary_form, 'name="make_primary" value="1"' )
			&& ! str_contains( $ax_pn_secondary_form, 'name="given"' )
			&& ! str_contains( $ax_pn_secondary_form, 'name="display_order"' )
			&& ! str_contains( $ax_pn_secondary_form, 'name="phonetic_given"' )
	);
	axismundi_actors_write_person_profile( $ax_pn_id, array( 'display_name' => 'Korean custom name' ) );
	$ax_pn_before_promotion     = axismundi_actors_get_by_identity( $ax_pn_id );
	$ax_pn_map_before_promotion = axismundi_actors_get_text_map( $ax_pn_id );
	ax_pn_assert(
		$ax_pn_results,
		'a custom primary display name does not replace that language’s nameMap spelling',
		$ax_pn_before_promotion instanceof Axismundi_Actor
			&& 'Korean custom name' === $ax_pn_before_promotion->get_display_name()
			&& '김지운' === (string) ( $ax_pn_map_before_promotion['ko-KR']['name'] ?? '' )
	);
	/*
	 * Promotion says which language a reader that asked for none in particular gets, and does nothing
	 * else. It used to shuffle name components between languages as well; there are none to shuffle,
	 * so every language keeps exactly the name written for it.
	 */
	$ax_pn_promoted = axismundi_actors_make_profile_primary( $ax_pn_id, 'en' );
	$ax_pn_map      = axismundi_actors_get_text_map( $ax_pn_id );
	$ax_pn_actor    = axismundi_actors_get_by_identity( $ax_pn_id );
	ax_pn_assert(
		$ax_pn_results,
		'making another profile primary changes which name is answered with and rewrites none of them',
		true === $ax_pn_promoted
			&& $ax_pn_actor instanceof Axismundi_Actor
			&& 'en' === $ax_pn_actor->get_default_language()
			&& 'Jiwoon Kim' === (string) ( $ax_pn_map['en']['name'] ?? '' )
			&& '김지운' === (string) ( $ax_pn_map['ko-KR']['name'] ?? '' )
			&& 'Jiwoon Kim' === $ax_pn_actor->get_display_name()
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
