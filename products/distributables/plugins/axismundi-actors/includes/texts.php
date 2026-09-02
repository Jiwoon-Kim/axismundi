<?php
/**
 * Phase 4d — multilingual Actor profile text storage and resolution.
 *
 * WP_User and site names/descriptions remain live fallback sources. Rows exist
 * only for translations explicitly authored for an Actor.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalize a WordPress locale or BCP-47-ish language tag to stable BCP-47 case.
 *
 * @param string $language Language tag.
 * @return string Empty when invalid.
 */
function axismundi_actors_normalize_language_tag( string $language ) : string {
	$language = trim( str_replace( '_', '-', $language ) );
	if ( '' === $language || 1 !== preg_match( '/^(?:[A-Za-z]{2,8})(?:-[A-Za-z0-9]{1,8})*$|^und$/i', $language ) ) {
		return '';
	}
	$parts = explode( '-', $language );
	foreach ( $parts as $index => $part ) {
		if ( 0 === $index ) {
			$parts[ $index ] = strtolower( $part );
		} elseif ( 4 === strlen( $part ) && ctype_alpha( $part ) ) {
			$parts[ $index ] = ucfirst( strtolower( $part ) );
		} elseif ( ( 2 === strlen( $part ) && ctype_alpha( $part ) ) || ( 3 === strlen( $part ) && ctype_digit( $part ) ) ) {
			$parts[ $index ] = strtoupper( $part );
		} else {
			$parts[ $index ] = strtolower( $part );
		}
	}
	return implode( '-', $parts );
}

/** @return string Normalized site language, with `und` as the final fallback. */
function axismundi_actors_site_language() : string {
	$language = axismundi_actors_normalize_language_tag( get_locale() );
	return '' !== $language ? $language : 'und';
}

/**
 * Profile-language choices for Actor authoring surfaces.
 *
 * The post editor's list is BCP 47, not WordPress's installed-locale list. Reusing it keeps the
 * two authoring surfaces familiar while the input remains open to a profile language WordPress has
 * no translation pack for. Existing profile languages are always retained as candidates.
 *
 * @param string[] $included Tags that must be selectable even when outside the shared list.
 * @return array<string,string> BCP 47 tag => display label.
 */
function axismundi_actors_profile_language_options( array $included = array() ) : array {
	$options = array();
	if ( function_exists( 'axismundi_op_language_options' ) ) {
		foreach ( axismundi_op_language_options() as $option ) {
			$tag = axismundi_actors_normalize_language_tag( (string) ( $option['value'] ?? '' ) );
			if ( '' !== $tag ) {
				$options[ $tag ] = (string) ( $option['label'] ?? $tag );
			}
		}
	}
	foreach ( array_merge( get_available_languages(), array( get_locale() ), $included ) as $locale ) {
		$tag = axismundi_actors_normalize_language_tag( (string) $locale );
		if ( '' !== $tag && ! isset( $options[ $tag ] ) ) {
			$options[ $tag ] = $tag;
		}
	}
	return $options;
}

/**
 * Preferred language for the human-facing HTML profile. A local Person uses their
 * WordPress profile language when an authored translation exists; this does not
 * change the Actor's serialization default_language.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return string
 */
function axismundi_actors_profile_language( Axismundi_Actor $actor ) : string {
	$user_id  = $actor->get_local_user_id();
	$language = $user_id
		? axismundi_actors_normalize_language_tag( get_user_locale( $user_id ) )
		: axismundi_actors_site_language();
	/**
	 * Filter the language preferred for a human-facing Actor profile.
	 *
	 * @param string          $language Preferred language.
	 * @param Axismundi_Actor $actor    Actor being viewed.
	 */
	$filtered = axismundi_actors_normalize_language_tag( (string) apply_filters( 'axismundi_actors_profile_language', $language, $actor ) );
	return '' !== $filtered ? $filtered : ( $actor->get_default_language() ?: axismundi_actors_site_language() );
}

/** Resolve the language used for scalar fields in an outbound Actor document. */
function axismundi_actors_serialization_language( Axismundi_Actor $actor ) : string {
	$language = axismundi_actors_normalize_language_tag( $actor->get_default_language() );
	return '' !== $language ? $language : axismundi_actors_site_language();
}

/**
 * Set the language used for scalar Actor fields during serialization.
 *
 * @param int    $identity_id Actor identity id.
 * @param string $language    BCP-47 language tag.
 * @return true|WP_Error
 */
function axismundi_actors_set_default_language( int $identity_id, string $language ) {
	global $wpdb;
	$language = axismundi_actors_normalize_language_tag( $language );
	if ( $identity_id <= 0 || '' === $language ) {
		return new WP_Error( 'ax_actors_language', __( 'Enter a valid language tag.', 'axismundi-actors' ) );
	}
	$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- actor repository custom table.
		axismundi_actors_actors_table(),
		array( 'default_language' => $language, 'updated_at' => current_time( 'mysql', true ) ),
		array( 'identity_id' => $identity_id ),
		array( '%s', '%s' ),
		array( '%d' )
	);
	return false === $updated ? new WP_Error( 'ax_actors_language_save', __( 'Could not save the default language.', 'axismundi-actors' ) ) : true;
}

/**
 * Store one explicitly authored translation; an empty value removes the row.
 *
 * @param int    $identity_id Actor identity id.
 * @param string $field       name | summary | content.
 * @param string $language    BCP-47 language tag.
 * @param string $value       Authored value.
 * @return true|WP_Error
 */
function axismundi_actors_set_text( int $identity_id, string $field, string $language, string $value ) {
	global $wpdb;
	$language = axismundi_actors_normalize_language_tag( $language );
	if ( $identity_id <= 0 || ! in_array( $field, array( 'name', 'summary', 'content' ), true ) || '' === $language ) {
		return new WP_Error( 'ax_actors_text_key', __( 'Invalid profile text field or language.', 'axismundi-actors' ) );
	}
	$value = 'name' === $field ? sanitize_text_field( $value ) : wp_kses_post( $value );
	$table = axismundi_actors_texts_table();
	if ( '' === trim( $value ) ) {
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- actor text custom table.
			$table,
			array( 'identity_id' => $identity_id, 'field_name' => $field, 'language_tag' => $language ),
			array( '%d', '%s', '%s' )
		);
		return true;
	}
	$result = $wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- unique translation upsert in custom table.
		$table,
		array(
			'identity_id'  => $identity_id,
			'field_name'   => $field,
			'language_tag' => $language,
			'value'        => $value,
			'media_type'   => 'name' === $field ? null : 'text/html',
			/*
			 * Typed here, so it follows nothing. Somebody who wrote this value chose it, and a later
			 * change to whatever it once resembled is not a reason to overwrite what they wrote.
			 * `axismundi_actors_bind_text()` is how a value that does follow something is written.
			 */
			'source'       => 'custom',
			'source_tag'   => '',
			'updated_at'   => current_time( 'mysql', true ),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
	return false === $result ? new WP_Error( 'ax_actors_text_save', __( 'Could not save the profile translation.', 'axismundi-actors' ) ) : true;
}

/**
 * Write a text and record what it follows.
 *
 * The difference between this and `axismundi_actors_set_text()` is the whole point of the binding:
 * one records a value somebody typed, the other records a value taken from somewhere that may change
 * again. Keeping both in one function would mean guessing which had happened.
 *
 * What is published stays a plain string -- ActivityStreams receives `nameMap` and nothing about
 * where each entry came from. The binding is local editing metadata.
 *
 * @param int    $identity_id Actor identity.
 * @param string $field       name | summary | content.
 * @param string $language    BCP-47 language tag this is shown for.
 * @param string $value       Resolved value.
 * @param string $source      What kind of thing it follows.
 * @param string $source_tag  Which one, in that source's own vocabulary.
 * @return true|WP_Error
 */
function axismundi_actors_bind_text( int $identity_id, string $field, string $language, string $value, string $source, string $source_tag = '' ) {
	global $wpdb;
	$written = axismundi_actors_set_text( $identity_id, $field, $language, $value );
	if ( is_wp_error( $written ) ) {
		return $written;
	}
	$language = axismundi_actors_normalize_language_tag( $language );
	if ( '' === trim( $value ) ) {
		// The row was deleted rather than written, so there is nothing left to explain.
		return true;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- actor text custom table.
	$wpdb->update(
		axismundi_actors_texts_table(),
		array( 'source' => sanitize_key( $source ), 'source_tag' => sanitize_text_field( $source_tag ) ),
		array( 'identity_id' => $identity_id, 'field_name' => $field, 'language_tag' => $language ),
		array( '%s', '%s' ),
		array( '%d', '%s', '%s' )
	);
	return true;
}

/**
 * What one text follows, if anything.
 *
 * @param int    $identity_id Actor identity.
 * @param string $field       name | summary | content.
 * @param string $language    BCP-47 language tag.
 * @return array{source:string,source_tag:string}
 */
function axismundi_actors_text_binding( int $identity_id, string $field, string $language ) : array {
	global $wpdb;
	$language = axismundi_actors_normalize_language_tag( $language );
	$table    = axismundi_actors_texts_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT source, source_tag FROM {$table} WHERE identity_id = %d AND field_name = %s AND language_tag = %s", $identity_id, $field, $language ),
		ARRAY_A
	);
	return array(
		'source'     => (string) ( $row['source'] ?? '' ),
		'source_tag' => (string) ( $row['source_tag'] ?? '' ),
	);
}

/**
 * Every language this Actor's texts are bound for, with what each follows.
 *
 * @param int    $identity_id Actor identity.
 * @param string $source      Only bindings of this kind.
 * @param string $field       name | summary | content.
 * @return array<string,string> Language tag => source tag.
 */
function axismundi_actors_bound_texts( int $identity_id, string $source, string $field = 'name' ) : array {
	global $wpdb;
	$table = axismundi_actors_texts_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$rows = (array) $wpdb->get_results(
		$wpdb->prepare( "SELECT language_tag, source_tag FROM {$table} WHERE identity_id = %d AND field_name = %s AND source = %s", $identity_id, $field, $source ),
		ARRAY_A
	);
	$bound = array();
	foreach ( $rows as $row ) {
		$bound[ (string) $row['language_tag'] ] = (string) $row['source_tag'];
	}
	return $bound;
}

/**
 * Move every authored field in one localized profile to another BCP 47 tag.
 *
 * A profile is a name/summary/content bundle. Moving fields independently would leave a name in one
 * language and its summary in another, so a target profile must be empty before the bundle moves.
 *
 * @param int    $identity_id Actor identity id.
 * @param string $from        Existing BCP 47 language tag.
 * @param string $to          Replacement BCP 47 language tag.
 * @return true|WP_Error
 */
function axismundi_actors_rename_text_language( int $identity_id, string $from, string $to ) {
	global $wpdb;
	$from = axismundi_actors_normalize_language_tag( $from );
	$to   = axismundi_actors_normalize_language_tag( $to );
	if ( $identity_id <= 0 || '' === $from || '' === $to ) {
		return new WP_Error( 'ax_actors_text_language', __( 'Enter a valid profile language.', 'axismundi-actors' ) );
	}
	if ( $from === $to ) {
		return true;
	}
	$table = axismundi_actors_texts_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- conflict check for this plugin's own table.
	$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE identity_id = %d AND language_tag = %s", $identity_id, $to ) );
	if ( $exists > 0 ) {
		return new WP_Error( 'ax_actors_text_language_exists', __( 'A profile already uses that language.', 'axismundi-actors' ) );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- moving one logical profile bundle in this plugin's own table.
	$updated = $wpdb->update(
		$table,
		array( 'language_tag' => $to, 'updated_at' => current_time( 'mysql', true ) ),
		array( 'identity_id' => $identity_id, 'language_tag' => $from ),
		array( '%s', '%s' ),
		array( '%d', '%s' )
	);
	return false === $updated ? new WP_Error( 'ax_actors_text_language_save', __( 'Could not change the profile language.', 'axismundi-actors' ) ) : true;
}

/**
 * Return explicitly authored translations, grouped by language.
 *
 * @param int $identity_id Actor identity id.
 * @return array<string,array<string,string>> language => field => value.
 */
function axismundi_actors_get_text_map( int $identity_id ) : array {
	global $wpdb;
	if ( $identity_id <= 0 ) {
		return array();
	}
	$table = axismundi_actors_texts_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- actor text custom table.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT field_name, language_tag, value FROM {$table} WHERE identity_id = %d ORDER BY language_tag, field_name", $identity_id ), ARRAY_A );
	$map  = array();
	foreach ( $rows as $row ) {
		$language = (string) $row['language_tag'];
		$field    = (string) $row['field_name'];
		$map[ $language ][ $field ] = (string) $row['value'];
	}
	return $map;
}

/**
 * Candidate languages in deterministic viewer-resolution order.
 *
 * @param Axismundi_Actor $actor     Actor.
 * @param string          $requested Requested language, if known.
 * @return string[]
 */
function axismundi_actors_language_fallbacks( Axismundi_Actor $actor, string $requested = '' ) : array {
	$candidates = array();
	$requested  = axismundi_actors_normalize_language_tag( $requested );
	if ( '' !== $requested ) {
		$candidates[] = $requested;
		$base = explode( '-', $requested )[0];
		if ( $base !== $requested ) {
			$candidates[] = $base;
		}
	}
	$candidates[] = $actor->get_default_language();
	$candidates[] = axismundi_actors_site_language();
	$user_id      = $actor->get_local_user_id();
	if ( $user_id ) {
		$candidates[] = axismundi_actors_normalize_language_tag( get_user_locale( $user_id ) );
	}
	$candidates[] = 'und';
	return array_values( array_unique( array_filter( $candidates ) ) );
}

/**
 * Live fallback for a field when no authored translation resolves.
 *
 * @param Axismundi_Actor $actor Actor.
 * @param string          $field name | summary | content.
 * @return string
 */
function axismundi_actors_live_text_fallback( Axismundi_Actor $actor, string $field ) : string {
	if ( 'site' === $actor->get_scope() ) {
		if ( 'name' === $field ) {
			return (string) get_bloginfo( 'name' );
		}
		return 'summary' === $field ? (string) get_bloginfo( 'description' ) : '';
	}
	$user_id = $actor->get_local_user_id();
	if ( ! $user_id ) {
		return 'name' === $field ? $actor->get_display_name() : '';
	}
	if ( 'name' === $field ) {
		return $actor->get_display_name();
	}
	return 'summary' === $field ? (string) get_the_author_meta( 'description', $user_id ) : '';
}

/**
 * Resolve an authored translation, then fall back to live WordPress data.
 *
 * @param Axismundi_Actor $actor     Actor.
 * @param string          $field     name | summary | content.
 * @param string          $requested Requested language.
 * @return string
 */
function axismundi_actors_resolve_text( Axismundi_Actor $actor, string $field, string $requested = '' ) : string {
	if ( ! in_array( $field, array( 'name', 'summary', 'content' ), true ) ) {
		return '';
	}
	$map = axismundi_actors_get_text_map( $actor->get_identity_id() );
	foreach ( axismundi_actors_language_fallbacks( $actor, $requested ) as $language ) {
		if ( isset( $map[ $language ][ $field ] ) && '' !== trim( $map[ $language ][ $field ] ) ) {
			return $map[ $language ][ $field ];
		}
	}
	foreach ( $map as $fields ) {
		if ( isset( $fields[ $field ] ) && '' !== trim( $fields[ $field ] ) ) {
			return $fields[ $field ];
		}
	}
	return axismundi_actors_live_text_fallback( $actor, $field );
}

/**
 * The Actor's texts per language, with a Person's structured name filled in over the top.
 *
 * The one place the two stores meet, so that everything downstream -- the scalar `name`, `nameMap`,
 * the JSContact Card, and whatever serializes next -- is reading the same answer. A structured name
 * wins for its language because it is the more specific statement: somebody who wrote their name in
 * parts and stated its order has said more than somebody who typed a string.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return array<string,array<string,string>>
 */
function axismundi_actors_name_map( Axismundi_Actor $actor ) : array {
	/*
	 * Nothing to overlay any more. A Person's components are written out into their own language's
	 * `name` as they are saved, so the map is already the whole answer -- and the scalar and the map
	 * cannot disagree, because there is only one place either could come from.
	 */
	return axismundi_actors_get_text_map( $actor->get_identity_id() );
}

/** Keep the local Actor's inexpensive list/search name aligned with its primary text. */
function axismundi_actors_refresh_display_name( int $identity_id ) : void {
	global $wpdb;
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() ) {
		return;
	}
	$primary = axismundi_actors_serialization_language( $actor );
	$name    = trim( (string) ( axismundi_actors_get_text_map( $identity_id )[ $primary ]['name'] ?? '' ) );
	if ( '' === $name ) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own cache column.
	$wpdb->update(
		axismundi_actors_actors_table(),
		array( 'display_name' => $name, 'updated_at' => current_time( 'mysql', true ) ),
		array( 'identity_id' => $identity_id ),
		array( '%s', '%s' ),
		array( '%d' )
	);
}

/** Make an authored Actor profile language the scalar ActivityStreams name. */
function axismundi_actors_make_profile_primary( int $identity_id, string $language ) {
	$actor    = axismundi_actors_get_by_identity( $identity_id );
	$language = axismundi_actors_normalize_language_tag( $language );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || '' === $language ) {
		return new WP_Error( 'ax_actors_primary_language', __( 'Enter a valid local profile language.', 'axismundi-actors' ) );
	}
	$name = trim( (string) ( axismundi_actors_get_text_map( $identity_id )[ $language ]['name'] ?? '' ) );
	if ( '' === $name ) {
		return new WP_Error( 'ax_actors_primary_name', __( 'Write a name for this profile before making it primary.', 'axismundi-actors' ) );
	}
	$result = axismundi_actors_set_default_language( $identity_id, $language );
	if ( ! is_wp_error( $result ) ) {
		axismundi_actors_refresh_display_name( $identity_id );
	}
	return $result;
}


/** Remove child text rows when an Actor identity is explicitly deleted. */
function axismundi_actors_delete_texts( int $identity_id ) : void {
	global $wpdb;
	if ( $identity_id > 0 ) {
		$wpdb->delete( axismundi_actors_texts_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- logical child cleanup.
	}
}
