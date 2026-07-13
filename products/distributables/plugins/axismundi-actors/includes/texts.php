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
			'updated_at'   => current_time( 'mysql', true ),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s' )
	);
	return false === $result ? new WP_Error( 'ax_actors_text_save', __( 'Could not save the profile translation.', 'axismundi-actors' ) ) : true;
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

/** Remove child text rows when an Actor identity is explicitly deleted. */
function axismundi_actors_delete_texts( int $identity_id ) : void {
	global $wpdb;
	if ( $identity_id > 0 ) {
		$wpdb->delete( axismundi_actors_texts_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- logical child cleanup.
	}
}

