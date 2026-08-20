<?php
/**
 * Removing the structured-name columns, and refusing to (dev-only; dist-excluded).
 *
 * The upgrade this pins is the last step of moving a person's name onto their contact card. What it
 * checks is mostly what the migration will not do: it will not drop a column while somebody still
 * has an unanswered question about which of two names is theirs, and it will not drop one on a site
 * where the plugin that would have taken them over is not installed.
 *
 * The columns are added back and removed again several times here. That is the only way to check a
 * one-way migration more than once, and every fixture puts the table back the way it found it.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_lc_results = array();
$ax_lc_users   = array();
$ax_lc_cards   = array();

/** @param bool[] $results Results. */
function ax_lc_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** The profile table's columns, asked fresh. */
function ax_lc_columns() : array {
	global $wpdb;
	$table = axismundi_actors_profile_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture schema check.
	return (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
}

/** Put the structured-name columns back, as a site mid-upgrade still has them. */
function ax_lc_restore_columns() : void {
	global $wpdb;
	$table   = axismundi_actors_profile_table();
	$columns = ax_lc_columns();
	foreach ( array( 'given', 'given2', 'surname', 'surname2' ) as $column ) {
		if ( ! in_array( $column, $columns, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture schema setup.
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN {$column} varchar(191) NOT NULL default ''" );
		}
	}
	if ( ! in_array( 'display_order', $columns, true ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture schema setup.
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN display_order varchar(32) NOT NULL default 'given-family'" );
	}
}

/**
 * Write the legacy parts of one Actor's name straight into the columns.
 *
 * Replaced rather than updated. A profile that holds nothing is deleted as it is written, so an
 * Actor that has only ever had components has no row to update -- which is exactly the state a site
 * mid-upgrade is in.
 */
function ax_lc_write_legacy( int $identity_id, array $parts ) : void {
	global $wpdb;
	$row = array_merge(
		axismundi_actors_person_profile( $identity_id ),
		$parts,
		array( 'identity_id' => $identity_id, 'updated_at' => current_time( 'mysql', true ) )
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture write into this plugin's own table.
	$wpdb->replace( axismundi_actors_profile_table(), $row );
}

/** An account with a published Person Actor and a contact card. */
function ax_lc_actor( array &$users, array &$cards ) : array {
	$login = 'axlc' . strtolower( wp_generate_password( 8, false, false ) );
	$id    = (int) wp_insert_user(
		array( 'user_login' => $login, 'user_email' => $login . '@example.test', 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), $login );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	$identity_id = (int) axismundi_actors_get_for_user( $id )->get_identity_id();
	$card        = axismundi_contacts_create_profile_card( $identity_id );
	$card_id     = is_wp_error( $card ) ? 0 : (int) $card;
	$cards[] = $card_id;
	return array( $identity_id, $card_id );
}

try {
	global $wpdb;

	// -- what is left of the columns in the code ---------------------------------------------------------

	/*
	 * The list a caller is offered. A name that is still on it is a way back in: somebody passes
	 * `given`, finds it stored, and a second copy of the components exists again.
	 */
	ax_lc_assert(
		$ax_lc_results,
		'the profile no longer names a component or a reading order among the things it holds',
		array() === array_intersect( AXISMUNDI_ACTORS_LEGACY_NAME_COLUMNS, AXISMUNDI_ACTORS_PROFILE_COLUMNS )
			&& in_array( 'display_name', AXISMUNDI_ACTORS_PROFILE_COLUMNS, true )
			&& in_array( 'phonetic_system', AXISMUNDI_ACTORS_PROFILE_COLUMNS, true )
	);

	list( $ax_lc_id, $ax_lc_card ) = ax_lc_actor( $ax_lc_users, $ax_lc_cards );
	axismundi_actors_write_person_profile( $ax_lc_id, array( 'given' => 'Alice', 'surname' => 'Smith', 'display_name' => 'Kept' ) );
	ax_lc_assert(
		$ax_lc_results,
		'a caller offering components is not refused and not obeyed: they are simply not stored',
		'Kept' === (string) ( axismundi_actors_person_profile( $ax_lc_id )['display_name'] ?? '' )
			&& ! array_key_exists( 'given', axismundi_actors_person_profile( $ax_lc_id ) )
	);

	// -- a fresh install never has them ------------------------------------------------------------------

	/*
	 * A migration that drops a column while `CREATE TABLE` still declares it leaves every new site
	 * carrying the thing that was removed. So the table is made from scratch here and asked.
	 */
	$ax_lc_table = axismundi_actors_profile_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture keeps the rows and puts them back below.
	$wpdb->query( "CREATE TABLE {$ax_lc_table}_axlc AS SELECT * FROM {$ax_lc_table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	$wpdb->query( "DROP TABLE {$ax_lc_table}" );
	axismundi_actors_install();
	$ax_lc_fresh = ax_lc_columns();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture restore.
	$wpdb->query( "REPLACE INTO {$ax_lc_table} SELECT * FROM {$ax_lc_table}_axlc" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( "DROP TABLE {$ax_lc_table}_axlc" );
	ax_lc_assert(
		$ax_lc_results,
		'a table built from the schema has no structured-name columns to begin with',
		array() === axismundi_actors_legacy_name_columns_in( $ax_lc_fresh )
			&& in_array( 'display_name', $ax_lc_fresh, true )
			&& in_array( 'phonetic_script', $ax_lc_fresh, true )
	);

	// -- a disagreement stops the upgrade ----------------------------------------------------------------

	/*
	 * The whole point of the gate. Two names, no way to tell which was written last, and a migration
	 * that dropped the columns now would be answering on somebody's behalf a question deliberately
	 * left to them.
	 */
	ax_lc_restore_columns();
	list( $ax_lc_cf_id, $ax_lc_cf_card ) = ax_lc_actor( $ax_lc_users, $ax_lc_cards );
	ax_lc_write_legacy( $ax_lc_cf_id, array( 'structured_name_language' => 'en-US', 'given' => 'Jiwoon2', 'surname' => 'Kim', 'display_order' => 'given-family' ) );
	$ax_lc_cf_doc = axismundi_contacts_card_document( $ax_lc_cf_card );
	$ax_lc_cf_doc['name'] = array(
		'@type'      => 'Name',
		'isOrdered'  => true,
		'components' => array(
			array( '@type' => 'NameComponent', 'kind' => 'given', 'value' => 'Jiwoon' ),
			array( '@type' => 'NameComponent', 'kind' => 'surname', 'value' => 'Kim' ),
		),
	);
	axismundi_contacts_save_card_for_owner( $ax_lc_cf_id, $ax_lc_cf_doc, $ax_lc_cf_card );

	axismundi_actors_install();
	$ax_lc_blocked = get_option( 'ax_actors_legacy_name_blockers', array() );
	ax_lc_assert(
		$ax_lc_results,
		'an unsettled disagreement stops the columns being dropped, and the site records who is waiting',
		array() !== axismundi_actors_legacy_name_columns_in( ax_lc_columns() )
			&& 'conflict' === (string) ( $ax_lc_blocked['reason'] ?? '' )
			&& in_array( $ax_lc_cf_id, (array) ( $ax_lc_blocked['actors'] ?? array() ), true )
	);
	ax_lc_assert(
		$ax_lc_results,
		'and the version is not stamped, so the upgrade tries again rather than reporting itself done',
		AXISMUNDI_ACTORS_DB_VERSION !== (string) get_option( 'ax_actors_db_version', '' )
	);
	// Neither side was touched while it waited.
	ax_lc_assert(
		$ax_lc_results,
		'both names stand exactly as they were while the question is open',
		'Jiwoon2' === (string) ( axismundi_actors_person_profile( $ax_lc_cf_id )['given'] ?? '' )
			&& 'Jiwoon' === (string) ( axismundi_contacts_card_document( $ax_lc_cf_card )['name']['components'][0]['value'] ?? '' )
	);

	// -- and answering it lets the upgrade finish --------------------------------------------------------

	axismundi_contacts_resolve_legacy_name( $ax_lc_cf_id, 'card' );
	axismundi_actors_install();
	ax_lc_assert(
		$ax_lc_results,
		'once it is answered the columns go, and nothing is recorded as waiting',
		array() === axismundi_actors_legacy_name_columns_in( ax_lc_columns() )
			&& array() === (array) get_option( 'ax_actors_legacy_name_blockers', array() )
			&& AXISMUNDI_ACTORS_DB_VERSION === (string) get_option( 'ax_actors_db_version', '' )
	);
	ax_lc_assert(
		$ax_lc_results,
		'the card keeps the name it was given, in full and in parts',
		'Jiwoon' === (string) ( axismundi_contacts_card_document( $ax_lc_cf_card )['name']['components'][0]['value'] ?? '' )
			&& 'Jiwoon Kim' === (string) ( axismundi_contacts_card_document( $ax_lc_cf_card )['name']['full'] ?? '' )
	);

	// -- a name the card is missing is carried over rather than blocking ---------------------------------

	/*
	 * The other half of the gate: an Actor still holding components that the card never received is not
	 * a disagreement, it is an errand. It is run, and then the columns go.
	 */
	ax_lc_restore_columns();
	list( $ax_lc_ad_id, $ax_lc_ad_card ) = ax_lc_actor( $ax_lc_users, $ax_lc_cards );
	$ax_lc_ad_doc = axismundi_contacts_card_document( $ax_lc_ad_card );
	$ax_lc_ad_doc['name'] = array( '@type' => 'Name', 'full' => '김지운' );
	axismundi_contacts_save_card_for_owner( $ax_lc_ad_id, $ax_lc_ad_doc, $ax_lc_ad_card );
	ax_lc_write_legacy( $ax_lc_ad_id, array( 'structured_name_language' => 'ko-KR', 'given' => '지운', 'surname' => '김', 'display_order' => 'family-given-compact' ) );
	axismundi_actors_set_text( $ax_lc_ad_id, 'name', 'en-US', 'Jiwoon Kim' );

	axismundi_actors_install();
	$ax_lc_ad_name = axismundi_contacts_card_document( $ax_lc_ad_card )['name'] ?? array();
	ax_lc_assert(
		$ax_lc_results,
		'a name only the Actor was holding reaches the card first, and then the columns go',
		array() === axismundi_actors_legacy_name_columns_in( ax_lc_columns() )
			&& array( 'surname' => '김', 'given' => '지운' ) === axismundi_contacts_name_actor_parts( $ax_lc_ad_name )
			&& '김지운' === (string) ( $ax_lc_ad_name['full'] ?? '' )
			&& '' === (string) ( $ax_lc_ad_name['defaultSeparator'] ?? 'unset' )
	);
	$ax_lc_ad_actor = axismundi_actors_get_by_identity( $ax_lc_ad_id );
	ax_lc_assert(
		$ax_lc_results,
		'and nothing the Actor answers with changed on the way through',
		$ax_lc_ad_actor instanceof Axismundi_Actor
			&& 'Jiwoon Kim' === (string) ( axismundi_actors_get_text_map( $ax_lc_ad_id )['en-US']['name'] ?? '' )
	);

	// -- what the Actor keeps ----------------------------------------------------------------------------

	axismundi_actors_write_person_profile(
		$ax_lc_id,
		array( 'display_name' => 'Shown as this', 'phonetic_surname' => 'KIM', 'phonetic_system' => 'ipa' )
	);
	axismundi_actors_install();
	$ax_lc_kept = axismundi_actors_person_profile( $ax_lc_id );
	ax_lc_assert(
		$ax_lc_results,
		'the label and the pronunciation are Actors’ own and survive the columns going',
		'Shown as this' === (string) ( $ax_lc_kept['display_name'] ?? '' )
			&& 'KIM' === (string) ( $ax_lc_kept['phonetic_surname'] ?? '' )
			&& 'ipa' === (string) ( $ax_lc_kept['phonetic_system'] ?? '' )
	);

	// -- the reconciliation has nothing left to do -------------------------------------------------------

	/*
	 * It is still shipped, because an install that has not upgraded yet needs it. What it must do on a
	 * site that has is nothing at all -- which is what makes deleting it later a removal rather than a
	 * change.
	 */
	ax_lc_assert(
		$ax_lc_results,
		'with the columns gone the reconciliation answers that there is nothing to reconcile',
		! axismundi_contacts_legacy_name_columns_present( true )
			&& 0 === axismundi_contacts_reconcile_legacy_names()
			&& 'none' === axismundi_contacts_legacy_name_state( $ax_lc_id )
	);
} finally {
	global $wpdb;
	foreach ( array_unique( array_filter( $ax_lc_cards ) ) as $ax_lc_card_id ) {
		axismundi_contacts_delete_card( (int) $ax_lc_card_id );
	}
	foreach ( array_unique( $ax_lc_users ) as $ax_lc_user_id ) {
		wp_delete_user( (int) $ax_lc_user_id );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup of its own scratch table.
	$wpdb->query( 'DROP TABLE IF EXISTS ' . axismundi_actors_profile_table() . '_axlc' );
	// Whatever the checks above did to the schema, the site is left on the version it should be.
	axismundi_actors_install();
}

$ax_lc_failures = count( array_filter( $ax_lc_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_lc_results ), $ax_lc_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_lc_failures > 0 ? 1 : 0 );
}
exit( $ax_lc_failures > 0 ? 1 : 0 );
