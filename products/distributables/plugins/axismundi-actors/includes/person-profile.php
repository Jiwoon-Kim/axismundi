<?php
/**
 * A local Actor's base profile: the parts of its name, and how they are shown.
 *
 * One row per Actor, with no language axis. The parts of a name are a fact about the person rather
 * than about a translation of them, and the arrangement this replaces asked somebody to re-enter
 * `Jiwoon` and `Kim` as components for every language their profile was written in -- when what they
 * have in English is one string and nothing more.
 *
 *   ax_actor_texts    name and summary per language, as plain strings. This is what `nameMap`
 *                     publishes.
 *   base profile      a chosen label, and how the name is pronounced
 *   ax_actors         display_name -- the primary language's name, kept for lists and search
 *
 * So a Korean profile stores `김` and `지운` and assembles them into the Korean name; the English
 * profile is `Jiwoon Kim` and nothing more. Only one language carries components, because a name has
 * parts once -- writing them again for every translation is work with nothing at the end of it.
 *
 * Local Actors only. A remote Actor's name is a string another server sent; deciding which half of
 * it is a surname would be inventing a fact about somebody and then inventing it again on the next
 * fetch. Remote names are cached whole and never taken apart.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/** @return string The base profile table. */
function axismundi_actors_profile_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_actor_profile';
}

/**
 * Everything the base profile holds, so no caller has to list them again.
 *
 * No components and no reading order. Those were here while Actors assembled names; the contact
 * card holds them now, and a list that still named them would be a way back in -- a caller passing
 * `given` and finding it stored is how a second copy starts existing again.
 */
const AXISMUNDI_ACTORS_PROFILE_COLUMNS = array(
	'structured_name_language',
	'phonetic_given',
	'phonetic_given2',
	'phonetic_surname',
	'phonetic_surname2',
	'phonetic_system',
	'phonetic_script',
	'display_name',
);

/**
 * One Actor's base profile, or an empty array when it has none.
 *
 * @param int $identity_id Actor identity.
 * @return array<string,mixed>
 */
function axismundi_actors_person_profile( int $identity_id ) : array {
	global $wpdb;
	if ( $identity_id <= 0 ) {
		return array();
	}
	$table = axismundi_actors_profile_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE identity_id = %d", $identity_id ), ARRAY_A );
	return is_array( $row ) ? $row : array();
}

/**
 * Whether a profile says anything at all.
 *
 * @param array<string,mixed> $row Profile row.
 * @return bool
 */
function axismundi_actors_person_profile_is_empty( array $row ) : bool {
	foreach ( AXISMUNDI_ACTORS_PROFILE_COLUMNS as $column ) {
		// Not content: it only says where other values belong.
		if ( 'structured_name_language' === $column ) {
			continue;
		}
		$value = trim( (string) ( $row[ $column ] ?? '' ) );
		// A numeric column at zero is unset rather than written, so it does not keep a row alive.
		if ( '' !== $value && '0' !== $value ) {
			return false;
		}
	}
	return true;
}

/**
 * Write a local Actor's base profile, merging with what is stored.
 *
 *   key absent          leave what is there
 *   key present, empty  clear that value, deliberately
 *   key present, value  change it
 *
 * @param int                 $identity_id Actor identity.
 * @param array<string,mixed> $changes     Any of AXISMUNDI_ACTORS_PROFILE_COLUMNS.
 * @return true|WP_Error
 */
function axismundi_actors_write_person_profile( int $identity_id, array $changes ) {
	global $wpdb;
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() ) {
		return new WP_Error(
			'ax_actors_profile_remote',
			__( 'Only a local Actor has a profile authored here.', 'axismundi-actors' ),
			array( 'status' => 400 )
		);
	}
	$existing = axismundi_actors_person_profile( $identity_id );
	$row      = array( 'identity_id' => $identity_id, 'updated_at' => current_time( 'mysql', true ) );
	foreach ( AXISMUNDI_ACTORS_PROFILE_COLUMNS as $column ) {
		$row[ $column ] = (string) ( $changes[ $column ] ?? $existing[ $column ] ?? '' );
	}
	$row['phonetic_system'] = strtolower( $row['phonetic_system'] );
	// Titlecased on the way in, so `hira` and `Hira` are the same subtag rather than two.
	$row['phonetic_script'] = ucfirst( strtolower( $row['phonetic_script'] ) );
	$row = axismundi_actors_normalize_name_phonetics( $row );
	if ( is_wp_error( $row ) ) {
		return $row;
	}
	if ( axismundi_actors_person_profile_is_empty( $row ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->delete( axismundi_actors_profile_table(), array( 'identity_id' => $identity_id ), array( '%d' ) );
	} else {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		if ( false === $wpdb->replace( axismundi_actors_profile_table(), $row ) ) {
			return new WP_Error( 'ax_actors_profile_save', __( 'The profile could not be saved.', 'axismundi-actors' ) );
		}
	}
	axismundi_actors_mark_person_name_edited( $identity_id );
	axismundi_actors_refresh_display_name( $identity_id );

	/**
	 * Fires after one Actor's stored name is written.
	 *
	 * Announced rather than pushed anywhere: Actors owns these components and does not know who else
	 * keeps a copy. Contacts does, on the Card an Actor publishes about itself, and listens here so
	 * that a name edited on the Actor screen does not leave a contact card saying the old one.
	 *
	 * @param int $identity_id Actor identity whose name was written.
	 */
	do_action( 'axismundi_actors_person_name_written', $identity_id );
	return true;
}

/**
 * Keep `ax_actors.display_name` equal to the primary language's name.
 *
 * That column is a duplicate on purpose: lists, search and mention pickers need one cheap string,
 * and joining a profile table for every row in an author list is how a directory gets slow. It stays
 * honest because it has exactly two writers -- this, for local Actors, and the remote fetch that
 * caches what another server sent. Nothing else writes it, so the two never disagree.
 *
 * An Actor with nothing written in its primary language keeps whatever is there: the live WordPress
 * name is still the fallback, and clearing this column because no translation was authored would
 * erase the name a site actually shows.
 *
 * @param int $identity_id Actor identity.
 * @return void
 */
function axismundi_actors_refresh_display_name( int $identity_id ) : void {
	global $wpdb;
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() ) {
		return;
	}
	$primary   = axismundi_actors_serialization_language( $actor );
	$profile   = 'Person' === $actor->get_type() ? axismundi_actors_person_profile( $identity_id ) : array();
	$structured = axismundi_actors_normalize_language_tag( (string) ( $profile['structured_name_language'] ?? '' ) );
	// A custom display label applies only while the name it customizes is the scalar primary name.
	$assembled = $structured === $primary ? trim( (string) ( $profile['display_name'] ?? '' ) ) : '';
	if ( '' === $assembled ) {
		$assembled = (string) ( axismundi_actors_get_text_map( $identity_id )[ $primary ]['name'] ?? '' );
	}
	if ( '' === trim( $assembled ) ) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update(
		axismundi_actors_actors_table(),
		array( 'display_name' => $assembled, 'updated_at' => current_time( 'mysql', true ) ),
		array( 'identity_id' => $identity_id ),
		array( '%s', '%s' ),
		array( '%d' )
	);
}

/**
 * Make one authored profile language the scalar Actor profile.
 *
 * Nothing moves. Every language keeps the name written for it, and this says which of them a reader
 * that asked for no language in particular gets. It used to have to shuffle name components between
 * languages as well; they live on the contact card now, which has no primary language to be
 * promoted into.
 *
 * @param int    $identity_id Actor identity.
 * @param string $language    Authored profile language to promote.
 * @return true|WP_Error
 */
function axismundi_actors_make_profile_primary( int $identity_id, string $language ) {
	$actor    = axismundi_actors_get_by_identity( $identity_id );
	$language = axismundi_actors_normalize_language_tag( $language );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || '' === $language ) {
		return new WP_Error( 'ax_actors_primary_language', __( 'Enter a valid local profile language.', 'axismundi-actors' ) );
	}
	$map  = axismundi_actors_get_text_map( $identity_id );
	$name = trim( (string) ( $map[ $language ]['name'] ?? '' ) );
	if ( '' === $name ) {
		return new WP_Error( 'ax_actors_primary_name', __( 'Write a name for this profile before making it primary.', 'axismundi-actors' ) );
	}
	$result = axismundi_actors_set_default_language( $identity_id, $language );
	if ( ! is_wp_error( $result ) ) {
		axismundi_actors_refresh_display_name( $identity_id );
	}
	return $result;
}

/** Remove a base profile when an Actor identity is explicitly deleted. */
function axismundi_actors_delete_person_profile( int $identity_id ) : void {
	global $wpdb;
	if ( $identity_id > 0 ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- logical child cleanup.
		$wpdb->delete( axismundi_actors_profile_table(), array( 'identity_id' => $identity_id ), array( '%d' ) );
	}
}
