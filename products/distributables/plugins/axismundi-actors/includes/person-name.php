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

/**
 * How the components of a name are put together.
 *
 * No `nickname` here. Being known by one is a real thing, but the public name of an Actor is the
 * `name` string of its primary language and nothing else, and letting a nickname win instead would
 * make `name` and `nameMap` disagree for reasons a reader could not see. A nickname is kept as an
 * alternate name; whether it may become somebody's shown name is a policy to decide on its own,
 * with `name` and `nameMap` in view, rather than a fourth option in this list.
 */
const AXISMUNDI_ACTORS_NAME_ORDERS = array( 'family-given', 'family-given-compact', 'given-family', 'given-family-compact', 'custom' );

/** The parts, in the order vCard's `N` lists them. */
const AXISMUNDI_ACTORS_NAME_PARTS = array( 'surname', 'surname2', 'given', 'given2' );

/** The parts a pronunciation can be given for, keyed by the part they are said for. */
const AXISMUNDI_ACTORS_NAME_PHONETIC_PARTS = array(
	'phonetic_surname'  => 'surname',
	'phonetic_surname2' => 'surname2',
	'phonetic_given'    => 'given',
	'phonetic_given2'   => 'given2',
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
 *
 * No "name before marriage". It is a category one social network offers rather than one either
 * standard has -- JSContact models names as components and nicknames, with no field for it, and
 * vCard never had `MAIDENNAME` -- and it says nothing `birth` and `former` do not already say.
 * Offering a field that can never be projected anywhere invites somebody to record something
 * sensitive for no reason at all.
 */
const AXISMUNDI_ACTORS_ALTERNATE_NAME_KINDS = array( 'nickname', 'former', 'birth', 'alternate_spelling', 'other' );

/**
 * The kinds that may leave this site in a contact document.
 *
 * Only a nickname, because only a nickname has an unambiguous home in one -- and because a former
 * or birth name of a real person appearing in a public file is not a mistake that can be taken back
 * afterwards. The rest are stored because people want them recorded, and stop here.
 */
const AXISMUNDI_ACTORS_PUBLISHED_ALTERNATE_NAME_KINDS = array( 'nickname' );

/**
 * Store a local Person's name in parts.
 *
 * No language argument. The parts of a name are a fact about the person rather than about a
 * translation of them, so they are stored once; the other languages a profile is written in keep a
 * plain name in the text store, which is all somebody has to say in them.
 *
 * @param int                 $identity_id Actor identity.
 * @param array<string,mixed> $parts       Any of AXISMUNDI_ACTORS_PROFILE_COLUMNS.
 * @return true|WP_Error
 */
function axismundi_actors_set_person_name( int $identity_id, array $parts ) {
	$actor = axismundi_actors_get_by_identity( $identity_id );
	/*
	 * Local Persons only. A remote Actor's name is a string somebody else's server sent, and deciding
	 * which half of it is a surname would be us inventing a fact about them and then re-inventing it
	 * on the next fetch. What they sent stays what they sent.
	 */
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || 'Person' !== $actor->get_type() ) {
		return new WP_Error( 'ax_actors_name_kind', __( 'Only a local person has a given name here.', 'axismundi-actors' ), array( 'status' => 400 ) );
	}
	return axismundi_actors_write_person_profile( $identity_id, $parts );
}

/**
 * Remove a local Person's structured name, keeping the decision that it was removed.
 *
 * @param int $identity_id Actor identity.
 * @return true|WP_Error
 */
function axismundi_actors_clear_person_name( int $identity_id ) {
	return axismundi_actors_write_person_profile( $identity_id, array_fill_keys( AXISMUNDI_ACTORS_PROFILE_COLUMNS, '' ) );
}

/**
 * Settle the pronunciation of a row that is about to be written.
 *
 * Judged on the merged row rather than on what the caller sent, because a partial update is the
 * normal case: one screen sends the components, another sends the notation, and neither can see
 * what the other left behind. Asking the question of the row that will actually exist is the only
 * way the answer stays true.
 *
 * Three outcomes, and only one of them is a refusal:
 *
 *   values, with a notation or script      kept
 *   values, with neither                   refused -- unreadable
 *   no values, notation or script left     cleared, not refused
 *
 * The last is why this normalizes rather than only validating. Somebody removing the final phonetic
 * component has removed the pronunciation; leaving `phoneticSystem: ipa` behind would be a setting
 * describing nothing, and the kind that survives long enough to be attached to the next value
 * somebody types.
 *
 * A phonetic value is a claim about sound, and `jee-WOON` is not IPA. Without a notation it cannot
 * be said correctly by anybody who did not write it, and serving it beside an absent
 * `phoneticSystem` is worse than not serving it -- a reader assumes a default and pronounces
 * somebody's name wrongly on the strength of it. A script alone is accepted: saying the value is
 * written in Hiragana is a real answer even when the notation has no registered name.
 *
 * @param array<string,string> $row Row about to be written.
 * @return array<string,string>|WP_Error The row, with the pronunciation made coherent.
 */
function axismundi_actors_normalize_name_phonetics( array $row ) {
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
	if ( '' === $written ) {
		// Nothing left to pronounce, so nothing left to say how it is pronounced.
		$row['phonetic_system'] = '';
		$row['phonetic_script'] = '';
	}
	return $row;
}

/**
 * Record that somebody has decided this Actor's name.
 *
 * Kept as a fact of its own rather than inferred from the rows, because the interesting state is
 * "nobody has ever said" and an empty table is two different things: an Actor whose name has never
 * been touched, and one whose name was deliberately emptied. Reading emptiness would let the
 * WordPress account name walk back in after somebody removed it -- once, quietly, and looking for
 * all the world like the software had simply remembered.
 *
 * Set by every write and every deletion, and never cleared.
 *
 * @param int $identity_id Actor identity.
 * @return void
 */
function axismundi_actors_mark_person_name_edited( int $identity_id ) : void {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update(
		axismundi_actors_actors_table(),
		array( 'person_name_edited_at' => current_time( 'mysql', true ) ),
		array( 'identity_id' => $identity_id ),
		array( '%s' ),
		array( '%d' )
	);
}

/**
 * Whether this Actor's name has ever been decided by a person.
 *
 * @param int $identity_id Actor identity.
 * @return bool
 */
function axismundi_actors_person_name_was_edited( int $identity_id ) : bool {
	global $wpdb;
	$table = axismundi_actors_actors_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$edited = $wpdb->get_var( $wpdb->prepare( "SELECT person_name_edited_at FROM {$table} WHERE identity_id = %d", $identity_id ) );
	return null !== $edited && '' !== (string) $edited;
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
	$order   = (string) ( $row['display_order'] ?? '' );
	$written = trim( (string) ( $row['display_name'] ?? '' ) );
	if ( '' !== $written || 'custom' === $order ) {
		return $written;
	}
	$family = trim( (string) ( $row['surname'] ?? '' ) );
	$second_family = trim( (string) ( $row['surname2'] ?? '' ) );
	$given  = trim( (string) ( $row['given'] ?? '' ) );
	$middle = trim( (string) ( $row['given2'] ?? '' ) );
	$family_first = in_array( $order, array( 'family-given', 'family-given-compact' ), true );
	$compact      = in_array( $order, array( 'family-given-compact', 'given-family-compact' ), true );
	$parts        = $family_first
		? array( $family, $second_family, $given, $middle )
		: array( $given, $middle, $family, $second_family );
	$parts  = array_values( array_filter( $parts, static fn( string $part ) : bool => '' !== $part ) );
	if ( array() === $parts ) {
		return '';
	}
	/*
	 * Joined without a space where the script does not use one. A Korean name written 김 지운 is one
	 * word to a reader of it, and the separator is a property of the writing system rather than of the
	 * order the parts are in.
	 */
	$separator = $compact || ( $family_first && ! preg_match( '/[A-Za-z]/', $family . $given ) ) ? '' : ' ';
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
	// One answer for every language, from the map, which the components feed as they are saved.
	$resolved = axismundi_actors_resolve_text( $actor, 'name', $language );
	return '' !== trim( $resolved ) ? $resolved : $actor->get_display_name();
}



/**
 * Offer the WordPress account's name as a starting point, once.
 *
 * A convenience and not a link. Somebody creating their first Actor has usually already typed their
 * name into the account screen, and asking again is a small rudeness; but the two are different
 * facts about different things -- one is how WordPress bylines a post author, the other is how a
 * person is known on the network -- so this copies and then lets go.
 *
 * Only when nobody has ever decided the Actor's name. Not "when the table is empty": somebody who
 * removed their structured name meant to remove it, and letting the account name reappear
 * afterwards would undo a decision without being asked, in the one direction nobody would think to
 * check.
 *
 * @param int $identity_id Actor identity.
 * @return true|WP_Error
 */
function axismundi_actors_seed_person_name_from_user( int $identity_id ) {
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || 'Person' !== $actor->get_type() ) {
		return new WP_Error( 'ax_actors_name_seed_kind', __( 'Only a local person has an account name to copy.', 'axismundi-actors' ), array( 'status' => 400 ) );
	}
	if ( axismundi_actors_person_name_was_edited( $identity_id ) ) {
		return new WP_Error(
			'ax_actors_name_seed_decided',
			__( 'This Actor already has a name somebody decided. Copying the account name now would overwrite it.', 'axismundi-actors' ),
			array( 'status' => 409 )
		);
	}
	$user = get_userdata( (int) $actor->get_local_user_id() );
	if ( ! $user instanceof WP_User ) {
		return new WP_Error( 'ax_actors_name_seed_user', __( 'There is no account to copy a name from.', 'axismundi-actors' ), array( 'status' => 404 ) );
	}
	$given  = trim( (string) $user->first_name );
	$family = trim( (string) $user->last_name );
	if ( '' === $given && '' === $family ) {
		return new WP_Error( 'ax_actors_name_seed_empty', __( 'The account has no first or last name to copy.', 'axismundi-actors' ), array( 'status' => 404 ) );
	}
	/*
	 * Given-family, because that is the order the two WordPress fields are named in and the only order
	 * they can honestly be read in. Someone whose name reads the other way changes it once, and that
	 * edit is what stops this ever running again.
	 */
	return axismundi_actors_set_person_name(
		$identity_id,
		array( 'given' => $given, 'surname' => $family, 'display_order' => 'given-family' )
	);
}

/**
 * The other names one Actor goes by, in the order they were put in.
 *
 * @param int    $identity_id Actor identity.
 * @param string $kind        Restrict to one kind, or '' for all.
 * @return array<int,array<string,mixed>>
 */
function axismundi_actors_alternate_names( int $identity_id, string $kind = '' ) : array {
	global $wpdb;
	if ( $identity_id <= 0 ) {
		return array();
	}
	$table = axismundi_actors_alternate_names_table();
	$sql   = "SELECT * FROM {$table} WHERE identity_id = %d";
	$args  = array( $identity_id );
	if ( '' !== $kind ) {
		$sql   .= ' AND name_kind = %s';
		$args[] = $kind;
	}
	$sql .= ' ORDER BY name_kind ASC, position ASC, id ASC';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
}

/**
 * Replace every other name of one kind.
 *
 * Whole-kind rather than row-by-row, because the order within a kind is part of what is stored and
 * a list is the thing a person edits. Replacing one kind never touches another: clearing the
 * nicknames must not be a way to lose a former name, and each kind is answered for separately.
 *
 * Scoped to one language when the caller names one, because a screen edits the language it is
 * showing: replacing every language from a form that could only see Korean would delete the English
 * nicknames somebody added on another visit, and they would not find out until a card went out
 * without them.
 *
 * @param int                            $identity_id Actor identity.
 * @param string                         $kind        One of AXISMUNDI_ACTORS_ALTERNATE_NAME_KINDS.
 * @param array<int,array<string,mixed>> $names       Rows of `value` and optional `language_tag`.
 * @param string|null                    $language    Replace only this language's rows, or null for all.
 * @return true|WP_Error
 */
function axismundi_actors_set_alternate_names( int $identity_id, string $kind, array $names, ?string $language = null ) {
	global $wpdb;
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || 'Person' !== $actor->get_type() ) {
		return new WP_Error( 'ax_actors_alt_name_kind_actor', __( 'Only a person goes by another name.', 'axismundi-actors' ), array( 'status' => 400 ) );
	}
	if ( ! in_array( $kind, AXISMUNDI_ACTORS_ALTERNATE_NAME_KINDS, true ) ) {
		return new WP_Error( 'ax_actors_alt_name_kind', __( 'That is not a kind of name this site knows.', 'axismundi-actors' ), array( 'status' => 400 ) );
	}
	$rows = array();
	foreach ( array_values( $names ) as $position => $name ) {
		$value = sanitize_text_field( (string) ( is_array( $name ) ? ( $name['value'] ?? '' ) : $name ) );
		if ( '' === trim( $value ) ) {
			continue;
		}
		$row_language = is_array( $name ) && isset( $name['language_tag'] )
			? axismundi_actors_normalize_language_tag( (string) $name['language_tag'] )
			: (string) ( null !== $language ? axismundi_actors_normalize_language_tag( $language ) : '' );
		$rows[]       = array(
			'identity_id'  => $identity_id,
			// Empty is an answer: "Jay" belongs to no language in particular, and guessing one would
			// hide it from every reader who asked for a different tag.
			'language_tag' => $row_language,
			'name_kind'    => $kind,
			'value'        => $value,
			'position'     => $position,
			'created_at'   => current_time( 'mysql', true ),
			'updated_at'   => current_time( 'mysql', true ),
		);
	}
	$where        = array( 'identity_id' => $identity_id, 'name_kind' => $kind );
	$where_format = array( '%d', '%s' );
	if ( null !== $language ) {
		$where['language_tag'] = axismundi_actors_normalize_language_tag( $language );
		$where_format[]        = '%s';
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_actors_alternate_names_table(), $where, $where_format );
	foreach ( $rows as $row ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		if ( false === $wpdb->insert( axismundi_actors_alternate_names_table(), $row ) ) {
			return new WP_Error( 'ax_actors_alt_name_write', __( 'That name could not be saved.', 'axismundi-actors' ) );
		}
	}
	return true;
}

/**
 * The other names that may appear in a public document.
 *
 * The only reader a serializer should use. Asking for `nickname` directly would work today and be
 * the line somebody copies the day a second kind becomes publishable -- or the day one stops being.
 *
 * @param int $identity_id Actor identity.
 * @return array<int,array<string,mixed>>
 */
function axismundi_actors_published_alternate_names( int $identity_id ) : array {
	$out = array();
	foreach ( AXISMUNDI_ACTORS_PUBLISHED_ALTERNATE_NAME_KINDS as $kind ) {
		$out = array_merge( $out, axismundi_actors_alternate_names( $identity_id, $kind ) );
	}
	return $out;
}

/**
 * Move a Person's old free-text name into their given name, once.
 *
 * The name people typed years ago was a single string, and the base profile wants parts. Only the
 * given name is filled: splitting on a space would decide that somebody's surname is whatever came
 * last, which is wrong for most of the world and unrecoverable once written.
 *
 * Runs only where no base profile exists, so anybody who has since written their name in parts is
 * never overwritten by their own history. Remote Actors are excluded outright.
 *
 * @return void
 */
function axismundi_actors_migrate_person_name_texts() : void {
	global $wpdb;
	$texts      = axismundi_actors_texts_table();
	$actors     = axismundi_actors_actors_table();
	$identities = axismundi_actors_identities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration over this plugin's own tables.
	$rows = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT t.identity_id, t.value, t.language_tag, a.default_language FROM {$texts} t
				INNER JOIN {$actors} a ON a.identity_id = t.identity_id
				INNER JOIN {$identities} i ON i.id = t.identity_id
				WHERE i.origin = 'local' AND a.actor_type = %s AND t.field_name = %s AND t.value <> ''
				ORDER BY t.identity_id ASC, t.language_tag ASC",
			'Person',
			'name'
		),
		ARRAY_A
	);
	foreach ( $rows as $row ) {
		$identity_id = (int) $row['identity_id'];
		if ( array() !== axismundi_actors_person_profile( $identity_id ) ) {
			continue;
		}
		$result = axismundi_actors_set_person_name(
			$identity_id,
			array(
				// The parts belong to the language that string was written in, which is where they came from.
				'structured_name_language' => (string) $row['language_tag'],
				'given'                    => (string) $row['value'],
			)
		);
		if ( is_wp_error( $result ) ) {
			return;
		}
	}
	update_option( 'ax_actors_person_name_text_migrated', '1', false );
}
