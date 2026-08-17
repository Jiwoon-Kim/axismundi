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

/** @return string The other names a person goes by. */
function axismundi_actors_alternate_names_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_actor_alternate_names';
}

/** The orders a name can be assembled in. `custom` means the stored display value is the answer. */
const AXISMUNDI_ACTORS_NAME_ORDERS = array( 'family-given', 'given-family', 'custom' );

/** The parts, in the order vCard's `N` lists them. */
const AXISMUNDI_ACTORS_NAME_PARTS = array( 'family_name', 'given_name', 'additional_name', 'honorific_prefix', 'honorific_suffix' );

/** The parts a pronunciation can be given for, keyed by the part they are said for. */
const AXISMUNDI_ACTORS_NAME_PHONETIC_PARTS = array(
	'phonetic_family'     => 'family_name',
	'phonetic_given'      => 'given_name',
	'phonetic_additional' => 'additional_name',
);

/**
 * The notations a pronunciation may be written in.
 *
 * The three JSContact registers, and nothing invented. A value with no notation cannot be read by
 * anybody who did not write it, which is why one of these is required the moment a phonetic value
 * exists rather than being an optional refinement.
 */
const AXISMUNDI_ACTORS_PHONETIC_SYSTEMS = array( 'ipa', 'jyut', 'piny' );

/**
 * The kinds of other name, and what each one means.
 *
 * Not localizations. `김지운` and `Jiwoon Kim` are one name written twice and belong in the table
 * above; these are different names for the same person.
 */
const AXISMUNDI_ACTORS_ALTERNATE_NAME_KINDS = array( 'nickname', 'former', 'birth', 'maiden', 'alternate_spelling', 'other' );

/**
 * The kinds that may leave this site in a contact document.
 *
 * Only a nickname, because only a nickname has an unambiguous home in one -- and because a former
 * or birth name of a real person appearing in a public file is not a mistake that can be taken back
 * afterwards. The rest are stored because people want them recorded, and stop here.
 */
const AXISMUNDI_ACTORS_PUBLISHED_ALTERNATE_NAME_KINDS = array( 'nickname' );

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
	/*
	 * What is already stored, because this writes with REPLACE: the row is deleted and written again.
	 * Three states, and the middle one is the reason this is not simply a merge:
	 *
	 *   key absent          leave what is there
	 *   key present, empty  clear that value, deliberately
	 *   key present, value  change it
	 *
	 * A merge without the middle state is a store nobody can erase anything from: an editor that sends
	 * the fields it owns would preserve a pronunciation forever, and clearing one would need a second
	 * verb. `??` gives exactly this, since it falls through on absence and not on emptiness -- which is
	 * worth saying out loud, because the obvious `?:` here would silently make empty mean absent.
	 */
	$existing = axismundi_actors_person_names( $identity_id )[ $language ] ?? array();
	$order    = (string) ( $parts['display_order'] ?? $existing['display_order'] ?? 'given-family' );
	if ( ! in_array( $order, AXISMUNDI_ACTORS_NAME_ORDERS, true ) ) {
		return new WP_Error( 'ax_actors_name_order', __( 'That is not a way of ordering a name.', 'axismundi-actors' ), array( 'status' => 400 ) );
	}
	$row = array(
		'identity_id'   => $identity_id,
		'language_tag'  => $language,
		'display_order' => $order,
		'display_name'  => sanitize_text_field( (string) ( $parts['display_name'] ?? $existing['display_name'] ?? '' ) ),
		'updated_at'    => current_time( 'mysql', true ),
	);
	foreach ( AXISMUNDI_ACTORS_NAME_PARTS as $part ) {
		$row[ $part ] = sanitize_text_field( (string) ( $parts[ $part ] ?? $existing[ $part ] ?? '' ) );
	}
	foreach ( array_keys( AXISMUNDI_ACTORS_NAME_PHONETIC_PARTS ) as $phonetic ) {
		$row[ $phonetic ] = sanitize_text_field( (string) ( $parts[ $phonetic ] ?? $existing[ $phonetic ] ?? '' ) );
	}
	$row['phonetic_system'] = strtolower( sanitize_text_field( (string) ( $parts['phonetic_system'] ?? $existing['phonetic_system'] ?? '' ) ) );
	// Titlecased on the way in, so `hira` and `Hira` are the same subtag rather than two.
	$row['phonetic_script'] = ucfirst( strtolower( sanitize_text_field( (string) ( $parts['phonetic_script'] ?? $existing['phonetic_script'] ?? '' ) ) ) );
	$phonetics = axismundi_actors_validate_name_phonetics( $row );
	if ( is_wp_error( $phonetics ) ) {
		return $phonetics;
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
 * Refuse a pronunciation nobody can read.
 *
 * A phonetic value is a claim about sound, and `jee-WOON` is not IPA. Without a notation it cannot
 * be said correctly by anybody who did not write it, and serving it in a contact document beside a
 * field named `phoneticSystem` that is absent is worse than not serving it at all -- a reader
 * assumes a default and pronounces somebody's name wrongly on the strength of it.
 *
 * A script alone is accepted: saying the value is written in Hiragana is a real answer even when
 * the notation has no registered name. What is refused is neither.
 *
 * @param array<string,string> $row Row about to be written.
 * @return true|WP_Error
 */
function axismundi_actors_validate_name_phonetics( array $row ) {
	$written = '';
	foreach ( array_keys( AXISMUNDI_ACTORS_NAME_PHONETIC_PARTS ) as $phonetic ) {
		$written .= trim( (string) ( $row[ $phonetic ] ?? '' ) );
	}
	$system = trim( (string) ( $row['phonetic_system'] ?? '' ) );
	$script = trim( (string) ( $row['phonetic_script'] ?? '' ) );
	if ( '' !== $system && ! in_array( $system, AXISMUNDI_ACTORS_PHONETIC_SYSTEMS, true ) ) {
		return new WP_Error(
			'ax_actors_name_phonetic_system',
			__( 'That is not a phonetic notation this site can name.', 'axismundi-actors' ),
			array( 'status' => 400 )
		);
	}
	/*
	 * A script subtag is four letters, ISO 15924, and shape is all this checks. Holding a copy of the
	 * registry would go stale, and refusing a script because our list predates it would be worse than
	 * accepting one nobody uses -- but `Hiragana` and `hira-ish` are not subtags at all, and letting
	 * them through would put a value in `phoneticScript` that no consumer can resolve.
	 */
	if ( '' !== $script && 1 !== preg_match( '/^[A-Z][a-z]{3}$/', $script ) ) {
		return new WP_Error(
			'ax_actors_name_phonetic_script',
			__( 'A script is a four-letter ISO 15924 subtag, like Hira or Latn.', 'axismundi-actors' ),
			array( 'status' => 400 )
		);
	}
	if ( '' !== $written && '' === $system && '' === $script ) {
		return new WP_Error(
			'ax_actors_name_phonetic_unreadable',
			__( 'A pronunciation needs to say which notation or script it is written in.', 'axismundi-actors' ),
			array( 'status' => 400 )
		);
	}
	return true;
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
