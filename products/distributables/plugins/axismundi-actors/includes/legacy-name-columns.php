<?php
/**
 * Getting rid of the structured-name columns, and refusing to until it is safe.
 *
 * Actors held the parts of a person's name until the contact card took them over. Nothing reads
 * these columns any more and nothing writes them, but "nobody reads it" is not the same as "nobody
 * needs it": between the migration that moved them and the removal of the screen that edited them,
 * both copies were editable, and where the two ended up saying different things it was left for a
 * person to settle rather than for code to guess.
 *
 * Dropping the columns while one of those questions is still open would answer it -- badly, and
 * without telling anybody. So the drop is gated:
 *
 *   Contacts is not here          nothing is dropped; there is nowhere for the names to have gone
 *   a disagreement is unsettled   nothing is dropped, and the site says which Actors are waiting
 *   everything is settled         the columns go
 *
 * The gate runs the reconciliation first, so a name the card side is simply missing is carried over
 * rather than counted as an obstacle. What blocks is only a real disagreement.
 *
 * One thing is discarded rather than carried: components on an Actor that publishes no contact card
 * at all. There is nowhere for them to go, and minting a card to hold them would publish a document
 * nobody asked for. What that Actor is called is unaffected -- the name string lives in the text
 * store and is what it answers with -- and only the split into given and family is lost.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/**
 * The columns the structured name used to live in, newest vocabulary first.
 *
 * The old development names are here too. A site that never ran the rename still has them, and
 * leaving them behind would keep exactly the baggage this removes.
 */
const AXISMUNDI_ACTORS_LEGACY_NAME_COLUMNS = array(
	'given',
	'given2',
	'surname',
	'surname2',
	'display_order',
	'title',
	'credential',
	'first_name',
	'middle_name',
	'last_name',
	'second_surname',
	'honorific_prefix',
	'honorific_suffix',
);

/**
 * Which of them a given site still has.
 *
 * @param string[] $columns Columns the profile table currently has.
 * @return string[]
 */
function axismundi_actors_legacy_name_columns_in( array $columns ) : array {
	return array_values( array_intersect( AXISMUNDI_ACTORS_LEGACY_NAME_COLUMNS, $columns ) );
}

/**
 * The Actors whose structured name still disagrees with the card that now owns it.
 *
 * Asked of Contacts, because Contacts is what holds the other half of the comparison and what
 * offers somebody the choice. Without it there is no answer, and no answer is not the same as none:
 * the caller treats it as a reason to keep the columns.
 *
 * @return int[]|null Identity ids, or null when nothing here can say.
 */
function axismundi_actors_unsettled_legacy_names() : ?array {
	global $wpdb;
	if ( ! function_exists( 'axismundi_contacts_legacy_name_state' ) || ! function_exists( 'axismundi_contacts_reconcile_legacy_names' ) ) {
		return null;
	}
	// Carried over first, so that a card merely missing a name is not counted as a disagreement. That
	// call rechecks the schema itself, which matters here: this runs in the request that changes it.
	axismundi_contacts_reconcile_legacy_names();
	$table = axismundi_actors_profile_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration gate over this plugin's own table.
	$rows      = (array) $wpdb->get_col( "SELECT identity_id FROM {$table}" );
	$unsettled = array();
	foreach ( $rows as $identity_id ) {
		if ( 'conflict' === axismundi_contacts_legacy_name_state( (int) $identity_id ) ) {
			$unsettled[] = (int) $identity_id;
		}
	}
	return $unsettled;
}

/**
 * Say the schema is not finished, so the upgrade asks again next request.
 *
 * Refusing to drop is a state the site stays in until somebody settles a question, and a site that
 * still reported itself current would never come back to ask. Not writing the version is enough on
 * the run that first reaches this; a site that got here another way needs the stamp taken off.
 *
 * @return void
 */
function axismundi_actors_unstamp_schema_version() : void {
	delete_option( 'ax_actors_db_version' );
}

/** The option recording who is still waiting, so the notice does not re-ask the database. */
const AXISMUNDI_ACTORS_LEGACY_NAME_BLOCKERS = 'ax_actors_legacy_name_blockers';

/**
 * Remove the structured-name columns, once nothing is waiting on them.
 *
 * @param string[] $columns Columns the profile table currently has.
 * @return bool Whether the table is now free of them.
 */
function axismundi_actors_drop_legacy_name_columns( array $columns ) : bool {
	global $wpdb;
	$present = axismundi_actors_legacy_name_columns_in( $columns );
	if ( array() === $present ) {
		delete_option( AXISMUNDI_ACTORS_LEGACY_NAME_BLOCKERS );
		return true;
	}
	$unsettled = axismundi_actors_unsettled_legacy_names();
	if ( null === $unsettled ) {
		/*
		 * No Contacts, so no card has taken these over and no comparison is possible. Keeping them is
		 * the only answer that does not throw away somebody's name on the grounds that the plugin that
		 * would have wanted it is not installed.
		 */
		axismundi_actors_unstamp_schema_version();
		update_option( AXISMUNDI_ACTORS_LEGACY_NAME_BLOCKERS, array( 'reason' => 'contacts' ), false );
		return false;
	}
	if ( array() !== $unsettled ) {
		axismundi_actors_unstamp_schema_version();
		update_option( AXISMUNDI_ACTORS_LEGACY_NAME_BLOCKERS, array( 'reason' => 'conflict', 'actors' => $unsettled ), false );
		return false;
	}
	foreach ( $present as $column ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed internal identifiers from the list above.
		$wpdb->query( "ALTER TABLE " . axismundi_actors_profile_table() . " DROP COLUMN {$column}" );
	}
	delete_option( AXISMUNDI_ACTORS_LEGACY_NAME_BLOCKERS );
	return true;
}

/**
 * Say so, on the screens where somebody can do something about it.
 *
 * @return void
 */
function axismundi_actors_legacy_name_notice() : void {
	$blocked = get_option( AXISMUNDI_ACTORS_LEGACY_NAME_BLOCKERS, array() );
	if ( ! is_array( $blocked ) || array() === $blocked || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$message = 'contacts' === (string) ( $blocked['reason'] ?? '' )
		? __( 'Some Actors still hold a name in parts from before contact cards kept them. Axismundi Contacts is where those belong, and this site keeps them until it is available.', 'axismundi-actors' )
		: sprintf(
			/* translators: %d: how many Actors are waiting. */
			_n(
				'%d Actor has a name from its profile that differs from the one on its contact card. Open My profile in Contacts to say which is meant.',
				'%d Actors have a name from their profile that differs from the one on their contact card. Open My profile in Contacts to say which is meant.',
				count( (array) ( $blocked['actors'] ?? array() ) ),
				'axismundi-actors'
			),
			count( (array) ( $blocked['actors'] ?? array() ) )
		);
	printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $message ) );
}
add_action( 'admin_notices', 'axismundi_actors_legacy_name_notice' );
