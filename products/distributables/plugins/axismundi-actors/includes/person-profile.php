<?php
/**
 * A local Actor's base profile: the parts of its name, and how they are shown.
 *
 * One row per Actor, with no language axis. The parts of a name are a fact about the person rather
 * than about a translation of them, and the arrangement this replaces asked somebody to re-enter
 * `Jiwoon` and `Kim` as components for every language their profile was written in -- when what they
 * have in English is one string and nothing more.
 *
 *   ax_actor_texts    name and summary per language, as plain strings -- every language, including
 *                     the one the parts belong to. This is what `nameMap` publishes.
 *   base profile      the components of the name in one language, said by
 *                     `structured_name_language`, plus how they are ordered and pronounced
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

/** Everything the base profile holds, so no caller has to list them again. */
const AXISMUNDI_ACTORS_PROFILE_COLUMNS = array(
	'structured_name_language',
	'given',
	'given2',
	'surname',
	'surname2',
	'title',
	'credential',
	'phonetic_given',
	'phonetic_given2',
	'phonetic_surname',
	'phonetic_surname2',
	'phonetic_system',
	'phonetic_script',
	'display_order',
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
		// None of these is content: they always hold a value, or only say where other values belong.
		if ( in_array( $column, array( 'display_order', 'structured_name_language' ), true ) ) {
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
	if ( '' === $row['display_order'] ) {
		$row['display_order'] = 'given-family';
	}
	$row['phonetic_system'] = strtolower( $row['phonetic_system'] );
	// Titlecased on the way in, so `hira` and `Hira` are the same subtag rather than two.
	$row['phonetic_script'] = ucfirst( strtolower( $row['phonetic_script'] ) );
	$row = axismundi_actors_normalize_name_phonetics( $row );
	if ( is_wp_error( $row ) ) {
		return $row;
	}
	if ( ! in_array( (string) $row['display_order'], AXISMUNDI_ACTORS_NAME_ORDERS, true ) ) {
		return new WP_Error( 'ax_actors_name_order', __( 'That is not a way of showing a name.', 'axismundi-actors' ), array( 'status' => 400 ) );
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
	axismundi_actors_publish_structured_name( $identity_id );
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
 * Write what the components assemble to into that language's name.
 *
 * The map is the published truth: `name` and `nameMap` are plain strings for every language, and a
 * consumer never has to know that one of them happens to have parts behind it. So the components do
 * not compete with the map -- they feed it, in the one language they belong to, and every other
 * language keeps whatever was written out for it.
 *
 * @param int $identity_id Actor identity.
 * @return void
 */
function axismundi_actors_publish_structured_name( int $identity_id ) : void {
	$profile  = axismundi_actors_person_profile( $identity_id );
	$language = axismundi_actors_normalize_language_tag( (string) ( $profile['structured_name_language'] ?? '' ) );
	if ( array() === $profile || '' === $language ) {
		return;
	}
	// A custom display name is the scalar public label, not a replacement for this language's nameMap
	// spelling. Otherwise demoting Korean after choosing an English primary would overwrite ko-KR.
	$assembled = axismundi_actors_assemble_person_name( array_merge( $profile, array( 'display_name' => '' ) ) );
	if ( '' !== $assembled ) {
		axismundi_actors_set_text( $identity_id, 'name', $language, $assembled );
	}
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
 * A Person has one structured-name set. When it moves to a different written profile, the existing
 * full name becomes the given-name fallback rather than being split or replaced by components from
 * the old language. The old language remains intact as a plain nameMap entry.
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
	if ( 'Person' === $actor->get_type() ) {
		$profile    = axismundi_actors_person_profile( $identity_id );
		$structured = axismundi_actors_normalize_language_tag( (string) ( $profile['structured_name_language'] ?? '' ) );
		if ( $structured !== $language ) {
			$old_name = axismundi_actors_assemble_person_name( array_merge( $profile, array( 'display_name' => '' ) ) );
			if ( '' !== $structured && '' !== $old_name ) {
				axismundi_actors_set_text( $identity_id, 'name', $structured, $old_name );
			}
			$result = axismundi_actors_set_person_name(
				$identity_id,
				array(
					'structured_name_language' => $language,
					'given'                    => $name,
					'given2'                   => '',
					'surname'                  => '',
					'surname2'                 => '',
					'title'                    => '',
					'credential'               => '',
					'display_order'            => 'given-family',
					'display_name'             => '',
					'phonetic_given'           => '',
					'phonetic_given2'          => '',
					'phonetic_surname'         => '',
					'phonetic_surname2'        => '',
					'phonetic_system'          => '',
					'phonetic_script'          => '',
				)
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
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

/**
 * Fold the per-language name rows into one base profile per Actor.
 *
 * The Actor's default language is the one that becomes the base profile, because that is the
 * language its scalar `name` is already published in. Every other language keeps the name it
 * assembled to, as a plain string in the text store -- which is what those languages needed all
 * along and is exactly what the new model asks for.
 *
 * Remote Actors are skipped outright. The old table should not have held any, and if it does, their
 * components are a local invention that must not be promoted into a base profile.
 *
 * @return bool Whether the tables were readable.
 */
function axismundi_actors_migrate_person_profile() : bool {
	global $wpdb;
	$names      = axismundi_actors_person_names_table();
	$profile    = axismundi_actors_profile_table();
	$actors     = axismundi_actors_actors_table();
	$identities = axismundi_actors_identities_table();
	foreach ( array( $names, $profile ) as $table ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema check.
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return false;
		}
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration.
	$rows = (array) $wpdb->get_results(
		"SELECT n.*, a.default_language FROM {$names} n
			INNER JOIN {$actors} a ON a.identity_id = n.identity_id
			INNER JOIN {$identities} i ON i.id = n.identity_id
			WHERE i.origin = 'local'
			ORDER BY n.identity_id ASC, n.language_tag ASC",
		ARRAY_A
	);
	$by_actor = array();
	foreach ( $rows as $row ) {
		$by_actor[ (int) $row['identity_id'] ][ (string) $row['language_tag'] ] = $row;
	}
	foreach ( $by_actor as $identity_id => $languages ) {
		if ( array() !== axismundi_actors_person_profile( $identity_id ) ) {
			// Already migrated, or edited since. Either way the base profile is the newer truth.
			continue;
		}
		$default = axismundi_actors_normalize_language_tag( (string) ( reset( $languages )['default_language'] ?? '' ) );
		$base    = $languages[ $default ] ?? reset( $languages );
		$changes = array();
		$legacy_columns = array(
			'given'           => 'first_name',
			'given2'          => 'middle_name',
			'surname'         => 'last_name',
			'surname2'        => 'second_surname',
			'title'           => 'honorific_prefix',
			'credential'      => 'honorific_suffix',
			'phonetic_given'  => 'phonetic_first',
			'phonetic_given2' => 'phonetic_middle',
			'phonetic_surname' => 'phonetic_last',
			'phonetic_surname2' => 'phonetic_second_surname',
		);
		foreach ( AXISMUNDI_ACTORS_PROFILE_COLUMNS as $column ) {
			$source = $legacy_columns[ $column ] ?? $column;
			$changes[ $column ] = (string) ( $base[ $source ] ?? '' );
		}
		// The language those components were written in is now recorded rather than implied.
		$changes['structured_name_language'] = (string) ( $base['language_tag'] ?? $default );
		axismundi_actors_write_person_profile( $identity_id, $changes );
		// Everything else becomes the plain string that language always amounted to.
		foreach ( $languages as $language => $row ) {
			if ( $row === $base ) {
				continue;
			}
			$assembled = axismundi_actors_assemble_person_name( $row );
			if ( '' !== $assembled && '' === (string) ( axismundi_actors_get_text_map( $identity_id )[ $language ]['name'] ?? '' ) ) {
				axismundi_actors_set_text( $identity_id, 'name', (string) $language, $assembled );
			}
		}
	}
	return true;
}
