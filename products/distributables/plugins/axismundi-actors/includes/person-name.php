<?php
/**
 * A person's name in parts, per language.
 *
 * The display name stays what it always was -- a single string somebody wrote -- and this sits under
 * it: the components a contact card needs, and a stated order to assemble them in. Two reasons it is
 * not derived from the display name, and the display name is not derived from it:
 *
 * Order is not a property of a language, it is a property of a person. `김지운` puts the family name
 * first and `Jiwoon Kim` puts it last, and the same person may want `Kim Jiwoon` in a directory --
 * so the order is stored beside the parts rather than guessed from the language tag.
 *
 * And plenty of names have no parts at all. A mononym, a stage name, an organisation: writing a
 * splitter that turns those into a surname and a given name is how software ends up addressing
 * somebody by half of their name. The parts are optional, and what is displayed falls back to the
 * name that was already there.
 *
 * Per language, because the components are: `김`/`지운` and `Kim`/`Jiwoon` are the same person's name
 * written in two scripts, not a translation of one string. This is the same shape the text store uses
 * for `name` and `summary`, and it feeds the same `nameMap`.
 *
 * Person only. An Organization or a Group has a name, not a given name, and a table row inviting
 * somebody to give a Group a surname is a table row somebody eventually fills in.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/** @return string Structured person-name table. */
function axismundi_actors_person_names_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_actor_person_names';
}

/** The orders a name can be assembled in. `custom` means the stored display value is the answer. */
const AXISMUNDI_ACTORS_NAME_ORDERS = array( 'family-given', 'given-family', 'custom' );

/** The parts, in the order vCard's `N` lists them. */
const AXISMUNDI_ACTORS_NAME_PARTS = array( 'family_name', 'given_name', 'additional_name', 'honorific_prefix', 'honorific_suffix' );

/**
 * Store one language's parts of a Person's name.
 *
 * @param int                 $identity_id Actor identity.
 * @param string              $language    BCP-47 tag.
 * @param array<string,mixed> $parts       Any of the name parts, plus `display_order` and `display_name`.
 * @return true|WP_Error
 */
function axismundi_actors_set_person_name( int $identity_id, string $language, array $parts ) {
	global $wpdb;
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || 'Person' !== $actor->get_type() ) {
		return new WP_Error( 'ax_actors_name_kind', __( 'Only a person has a given name.', 'axismundi-actors' ), array( 'status' => 400 ) );
	}
	$language = axismundi_actors_normalize_language_tag( $language );
	if ( '' === $language ) {
		return new WP_Error( 'ax_actors_name_language', __( 'A name needs the language it is written in.', 'axismundi-actors' ), array( 'status' => 400 ) );
	}
	$order = (string) ( $parts['display_order'] ?? 'given-family' );
	if ( ! in_array( $order, AXISMUNDI_ACTORS_NAME_ORDERS, true ) ) {
		return new WP_Error( 'ax_actors_name_order', __( 'That is not a way of ordering a name.', 'axismundi-actors' ), array( 'status' => 400 ) );
	}

	$row = array(
		'identity_id'   => $identity_id,
		'language_tag'  => $language,
		'display_order' => $order,
		'display_name'  => sanitize_text_field( (string) ( $parts['display_name'] ?? '' ) ),
		'updated_at'    => current_time( 'mysql', true ),
	);
	foreach ( AXISMUNDI_ACTORS_NAME_PARTS as $part ) {
		$row[ $part ] = sanitize_text_field( (string) ( $parts[ $part ] ?? '' ) );
	}
	// Nothing written is not a name in one language; it is the absence of one, and an empty row would
	// otherwise sit in the way of the fallback.
	if ( '' === implode( '', array_map( static fn( string $part ) : string => (string) $row[ $part ], AXISMUNDI_ACTORS_NAME_PARTS ) ) && '' === $row['display_name'] ) {
		return axismundi_actors_delete_person_name( $identity_id, $language );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	return false === $wpdb->replace( axismundi_actors_person_names_table(), $row )
		? new WP_Error( 'ax_actors_name_write', __( 'The name could not be saved.', 'axismundi-actors' ) )
		: true;
}

/**
 * @param int    $identity_id Actor identity.
 * @param string $language    BCP-47 tag.
 * @return true
 */
function axismundi_actors_delete_person_name( int $identity_id, string $language ) : bool {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete(
		axismundi_actors_person_names_table(),
		array( 'identity_id' => $identity_id, 'language_tag' => axismundi_actors_normalize_language_tag( $language ) ),
		array( '%d', '%s' )
	);
	return true;
}

/**
 * Every language a Person's name is written in, keyed by tag.
 *
 * @param int $identity_id Actor identity.
 * @return array<string,array<string,mixed>>
 */
function axismundi_actors_person_names( int $identity_id ) : array {
	global $wpdb;
	if ( $identity_id <= 0 ) {
		return array();
	}
	$table = axismundi_actors_person_names_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE identity_id = %d ORDER BY language_tag ASC", $identity_id ), ARRAY_A );
	$out  = array();
	foreach ( $rows as $row ) {
		$out[ (string) $row['language_tag'] ] = $row;
	}
	return $out;
}

/**
 * Assemble one stored row into the name to show.
 *
 * A stored display value wins outright -- somebody wrote it on purpose, and `custom` exists to say
 * exactly that. Otherwise the parts are joined in the stated order, and a row with no parts produces
 * nothing rather than an empty string pretending to be a name.
 *
 * @param array<string,mixed> $row Stored row.
 * @return string
 */
function axismundi_actors_assemble_person_name( array $row ) : string {
	$written = trim( (string) ( $row['display_name'] ?? '' ) );
	if ( '' !== $written || 'custom' === (string) ( $row['display_order'] ?? '' ) ) {
		return $written;
	}
	$family = trim( (string) ( $row['family_name'] ?? '' ) );
	$given  = trim( (string) ( $row['given_name'] ?? '' ) );
	$middle = trim( (string) ( $row['additional_name'] ?? '' ) );
	$parts  = 'family-given' === (string) ( $row['display_order'] ?? '' )
		? array( $family, $given, $middle )
		: array( $given, $middle, $family );
	$parts  = array_values( array_filter( $parts, static fn( string $part ) : bool => '' !== $part ) );
	if ( array() === $parts ) {
		return '';
	}
	/*
	 * Joined without a space where the script does not use one. A Korean name written 김 지운 is one
	 * word to a reader of it, and the separator is a property of the writing system rather than of the
	 * order the parts are in.
	 */
	$separator = 'family-given' === (string) ( $row['display_order'] ?? '' ) && ! preg_match( '/[A-Za-z]/', $family . $given ) ? '' : ' ';
	return implode( $separator, $parts );
}

/**
 * The display name for one Actor in one language, from the parts if they are there.
 *
 * Falls through to the text store and then to whatever the Actor already answers with, so an Actor
 * that never filled any of this in is unaffected.
 *
 * @param Axismundi_Actor $actor    Actor.
 * @param string          $language Requested language, or '' for the Actor's default.
 * @return string
 */
function axismundi_actors_person_display_name( Axismundi_Actor $actor, string $language = '' ) : string {
	if ( 'Person' !== $actor->get_type() ) {
		return $actor->get_display_name();
	}
	$names = axismundi_actors_person_names( $actor->get_identity_id() );
	if ( array() !== $names ) {
		$wanted = axismundi_actors_normalize_language_tag( '' !== $language ? $language : (string) $actor->get_default_language() );
		foreach ( array( $wanted, strtok( $wanted, '-' ) ) as $tag ) {
			if ( '' !== (string) $tag && isset( $names[ $tag ] ) ) {
				$assembled = axismundi_actors_assemble_person_name( $names[ $tag ] );
				if ( '' !== $assembled ) {
					return $assembled;
				}
			}
		}
	}
	return $actor->get_display_name();
}
