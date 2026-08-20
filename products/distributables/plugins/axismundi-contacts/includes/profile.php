<?php
/**
 * The Card an Actor publishes about itself, and how widely.
 *
 * This is not a property of an address book. An Actor may keep several books -- `개인`, `업무`, a
 * temporary one -- and none of them is what gets shared; what gets shared is the one Card that says
 * who this Actor is. Keeping the binding here rather than as a column on the default book is what
 * stops `share my profile` and `share my address book` from being one setting by accident.
 *
 * The unit is the Actor rather than the account. One person switches between acting as themselves,
 * as a Group and as an Organization, and each of those publishes a different card: a Person has a
 * birthday and relatives, an Organization has a department and a main number. So exactly one profile
 * Card per Actor, and the acting Actor decides which one is in front of somebody.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Card an Actor publishes about itself.
 *
 * @param int $actor_id Actor identity.
 * @return int Card id, or 0.
 */
function axismundi_contacts_profile_card( int $actor_id ) : int {
	global $wpdb;
	if ( $actor_id <= 0 ) {
		return 0;
	}
	$table = axismundi_contacts_profiles_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT card_id FROM {$table} WHERE actor_id = %d", $actor_id ) );
}

/**
 * How widely that Card may be read.
 *
 * @param int $actor_id Actor identity.
 * @return string One of AXISMUNDI_CONTACTS_SHARING.
 */
function axismundi_contacts_profile_sharing( int $actor_id ) : string {
	if ( ! axismundi_contacts_profile_sharing_enabled( $actor_id ) ) {
		return 'off';
	}
	return axismundi_contacts_profile_audience( $actor_id );
}

/**
 * Whether this Actor is sharing the Card it publishes about itself at all.
 *
 * @param int $actor_id Actor identity.
 * @return bool
 */
function axismundi_contacts_profile_sharing_enabled( int $actor_id ) : bool {
	global $wpdb;
	if ( $actor_id <= 0 ) {
		return false;
	}
	$table = axismundi_contacts_profiles_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_var( $wpdb->prepare( "SELECT sharing_enabled FROM {$table} WHERE actor_id = %d", $actor_id ) );
	// Nothing recorded is not permission. An Actor with no binding shares nothing.
	return null !== $row && 1 === (int) $row;
}

/**
 * Who it is shared with, whether or not it is being shared right now.
 *
 * Answered even while sharing is off, which is the point of keeping the two apart: turning sharing
 * back on restores the audience somebody chose rather than asking them to choose again.
 *
 * @param int $actor_id Actor identity.
 * @return string One of AXISMUNDI_CONTACTS_AUDIENCES.
 */
function axismundi_contacts_profile_audience( int $actor_id ) : string {
	global $wpdb;
	if ( $actor_id <= 0 ) {
		return 'contacts';
	}
	$table = axismundi_contacts_profiles_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$audience = (string) $wpdb->get_var( $wpdb->prepare( "SELECT audience FROM {$table} WHERE actor_id = %d", $actor_id ) );
	// The narrower of the two when nothing has been chosen.
	return in_array( $audience, AXISMUNDI_CONTACTS_AUDIENCES, true ) ? $audience : 'contacts';
}

/**
 * The link to this Actor's Card, when there is one anybody may fetch.
 *
 * The whole question another domain has to ask. Actors puts this on the Actor document and needs to
 * know nothing about profile bindings, audiences, or what `public` is called in this plugin's
 * tables -- it asks whether there is a public contact card and gets a link or nothing.
 *
 * @param int $actor_id Actor identity.
 * @return array<string,string>|null AS2 Link properties, or null.
 */
function axismundi_contacts_public_profile_link( int $actor_id ) {
	if ( ! function_exists( 'axismundi_actors_get_by_identity' ) ) {
		return null;
	}
	$actor = axismundi_actors_get_by_identity( $actor_id );
	if ( ! $actor instanceof Axismundi_Actor || ! axismundi_contacts_jscontact_is_public( $actor ) ) {
		return null;
	}
	$handle = trim( $actor->get_preferred_username() );
	if ( '' === $handle ) {
		return null;
	}
	/*
	 * Where the Card is, and not what it is. The Card's own `uid` says which Card this is, and
	 * repeating it here would make two places authoritative about one identity -- so an address that
	 * later moves does not take the identity with it.
	 */
	return array(
		'type'      => 'Link',
		'href'      => home_url( '/@' . rawurlencode( $handle ) . '.jscontact' ),
		'mediaType' => AXISMUNDI_CONTACTS_JSCONTACT_MEDIA_TYPE,
		'name'      => 'JSContact',
	);
}

/**
 * Say which Card describes an Actor.
 *
 * The Card is an ordinary Card that happens to be pointed at, rather than a special kind of row: it
 * is edited, filed and exported like any other, and this binding is the only thing that makes it a
 * profile.
 *
 * @param int $actor_id Actor identity.
 * @param int $card_id  Card id, or 0 to clear.
 * @return true|WP_Error
 */
function axismundi_contacts_set_profile_card( int $actor_id, int $card_id ) {
	global $wpdb;
	if ( $actor_id <= 0 ) {
		return new WP_Error( 'ax_contacts_profile_actor', __( 'A profile card belongs to an Actor.', 'axismundi-contacts' ), array( 'status' => 400 ) );
	}
	$table = axismundi_contacts_profiles_table();
	if ( $card_id <= 0 ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->delete( $table, array( 'actor_id' => $actor_id ), array( '%d' ) );
		return true;
	}
	$card = axismundi_contacts_get_card( $card_id );
	// Asked of ownership rather than of filing: a Card moved between books is still the same Actor's.
	if ( array() === $card || (int) $card['owner_actor_id'] !== $actor_id ) {
		return new WP_Error(
			'ax_contacts_profile_card',
			__( 'A profile card must be a card that Actor keeps.', 'axismundi-contacts' ),
			array( 'status' => 400 )
		);
	}
	$now      = current_time( 'mysql', true );
	$existing = axismundi_contacts_profile_card( $actor_id );
	if ( $existing > 0 ) {
		// The sharing already chosen stays with the Actor, not with whichever Card was pointed at.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( $table, array( 'card_id' => $card_id, 'updated_at' => $now ), array( 'actor_id' => $actor_id ), array( '%d', '%s' ), array( '%d' ) );
		return true;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$created = $wpdb->insert(
		$table,
		array( 'actor_id' => $actor_id, 'card_id' => $card_id, 'sharing_enabled' => 0, 'audience' => 'contacts', 'created_at' => $now, 'updated_at' => $now ),
		array( '%d', '%d', '%d', '%s', '%s', '%s' )
	);
	if ( false === $created ) {
		// The unique key on card_id: one Card cannot be two Actors' profile.
		return new WP_Error( 'ax_contacts_profile_taken', __( 'That card is already another Actor\'s profile.', 'axismundi-contacts' ), array( 'status' => 409 ) );
	}
	return true;
}

/**
 * Choose how widely an Actor's profile Card may be read.
 *
 * `contacts` is answered from this Actor's own address books -- whether *they* saved the person
 * asking -- and that question has no answer anywhere else. So it is not a federation audience that
 * happens to fail off-site; it is not evaluated off-site at all. `followers` and `mutuals` are
 * refused for the same reason they may later be accepted: an audience is offered once a request can
 * actually be measured against it.
 *
 * @param int    $actor_id Actor identity.
 * @param string $sharing  One of AXISMUNDI_CONTACTS_SHARING.
 * @return true|WP_Error
 */
function axismundi_contacts_set_profile_sharing( int $actor_id, string $sharing ) {
	if ( ! in_array( $sharing, AXISMUNDI_CONTACTS_SHARING, true ) ) {
		return new WP_Error( 'ax_contacts_sharing', __( 'That is not an audience this site can decide.', 'axismundi-contacts' ), array( 'status' => 400 ) );
	}
	/*
	 * The old three-way setting, kept for callers that think in it. Turning it off leaves the audience
	 * where it was, so this no longer destroys the choice it used to overwrite.
	 */
	if ( 'off' === $sharing ) {
		return axismundi_contacts_set_profile_sharing_enabled( $actor_id, false );
	}
	$enabled = axismundi_contacts_set_profile_audience( $actor_id, $sharing );
	return is_wp_error( $enabled ) ? $enabled : axismundi_contacts_set_profile_sharing_enabled( $actor_id, true );
}

/**
 * Start or stop sharing, without touching who it is shared with.
 *
 * @param int  $actor_id Actor identity.
 * @param bool $enabled  Whether to share.
 * @return true|WP_Error
 */
function axismundi_contacts_set_profile_sharing_enabled( int $actor_id, bool $enabled ) {
	global $wpdb;
	if ( axismundi_contacts_profile_card( $actor_id ) <= 0 ) {
		// Publishing nothing is not a policy. There has to be a Card before there is one.
		return new WP_Error( 'ax_contacts_profile_missing', __( 'That Actor has no profile card yet.', 'axismundi-contacts' ), array( 'status' => 404 ) );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update(
		axismundi_contacts_profiles_table(),
		array( 'sharing_enabled' => $enabled ? 1 : 0, 'updated_at' => current_time( 'mysql', true ) ),
		array( 'actor_id' => $actor_id ),
		array( '%d', '%s' ),
		array( '%d' )
	);
	return true;
}

/**
 * Choose who it is shared with, whether or not it is being shared right now.
 *
 * @param int    $actor_id Actor identity.
 * @param string $audience One of AXISMUNDI_CONTACTS_AUDIENCES.
 * @return true|WP_Error
 */
function axismundi_contacts_set_profile_audience( int $actor_id, string $audience ) {
	global $wpdb;
	if ( ! in_array( $audience, AXISMUNDI_CONTACTS_AUDIENCES, true ) ) {
		return new WP_Error( 'ax_contacts_sharing', __( 'That is not an audience this site can decide.', 'axismundi-contacts' ), array( 'status' => 400 ) );
	}
	if ( axismundi_contacts_profile_card( $actor_id ) <= 0 ) {
		return new WP_Error( 'ax_contacts_profile_missing', __( 'That Actor has no profile card yet.', 'axismundi-contacts' ), array( 'status' => 404 ) );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update(
		axismundi_contacts_profiles_table(),
		array( 'audience' => $audience, 'updated_at' => current_time( 'mysql', true ) ),
		array( 'actor_id' => $actor_id ),
		array( '%s', '%s' ),
		array( '%d' )
	);
	return true;
}

/**
 * Whether an Actor of this type keeps address books at all.
 *
 * A Person is a contact keeper: the address book is most of why they are here. An Organization can
 * be one too -- employees, clients, partners, press are ordinary organisational lists. A Group is a
 * set of relationships rather than somebody who files contacts, and an Application or a Service is
 * administered through the Actor screens, so giving either an address book would be a second place
 * to look for something that lives somewhere else.
 *
 * @param string $actor_type ActivityStreams actor type.
 * @return bool
 */
function axismundi_contacts_type_keeps_books( string $actor_type ) : bool {
	return in_array( $actor_type, array( 'Person', 'Organization' ), true );
}

/**
 * When an Actor of this type gets a profile Card.
 *
 * `auto` on creation, `optional` when somebody asks for one, `none` at all. A Card is not a
 * by-product of an Actor existing: it is made when there is something to publish or somewhere to
 * file it. Minting one for every Service Actor would fill an address book with rows nobody wrote
 * and nobody reads.
 *
 * @param string $actor_type ActivityStreams actor type.
 * @return string One of auto|optional|none.
 */
function axismundi_contacts_profile_policy( string $actor_type ) : string {
	switch ( $actor_type ) {
		case 'Person':
			return 'auto';
		case 'Organization':
		case 'Group':
			return 'optional';
		default:
			return 'none';
	}
}

/**
 * The kind a new profile Card starts as, for an Actor of this type.
 *
 * A default and never a constraint. `Card.kind` and an ActivityStreams actor type are two vocabularies
 * about different questions -- what sort of contact this is, and what sort of agent that is -- and
 * they do not line up: `Service` has no JSContact kind at all, and a Service Actor might honestly be
 * `application` or `org` depending on what it is. So the type suggests a starting value, the Card
 * keeps whatever it is given, and changing an Actor's type later never rewrites a Card that already
 * exists.
 *
 * @param string $actor_type ActivityStreams actor type.
 * @return string JSContact kind, or '' when there is no honest default.
 */
function axismundi_contacts_default_kind( string $actor_type ) : string {
	// The Actors plugin already answers this for its own JSContact output; asking it keeps one answer.
	return function_exists( 'axismundi_actors_jscontact_kind' ) ? axismundi_actors_jscontact_kind( $actor_type ) : '';
}

/**
 * Make the Card an Actor publishes about itself, for a type that does not get one automatically.
 *
 * Owned by the Actor and filed into no book, because a Group keeps none and a card that cannot exist
 * without a container would be the container deciding what may exist.
 *
 * @param int $actor_id Actor identity.
 * @return int|WP_Error Card id.
 */
function axismundi_contacts_create_profile_card( int $actor_id ) {
	$existing = axismundi_contacts_profile_card( $actor_id );
	if ( $existing > 0 ) {
		return $existing;
	}
	if ( ! function_exists( 'axismundi_actors_get_by_identity' ) ) {
		return new WP_Error( 'ax_contacts_actors', __( 'Profile cards need Axismundi Actors.', 'axismundi-contacts' ) );
	}
	$actor = axismundi_actors_get_by_identity( $actor_id );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() ) {
		return new WP_Error( 'ax_contacts_profile_actor', __( 'A profile card belongs to a local Actor.', 'axismundi-contacts' ), array( 'status' => 400 ) );
	}
	if ( 'none' === axismundi_contacts_profile_policy( $actor->get_type() ) ) {
		return new WP_Error(
			'ax_contacts_profile_policy',
			__( 'Actors of that kind are managed from the Actor screens rather than from an address book.', 'axismundi-contacts' ),
			array( 'status' => 400 )
		);
	}
	$name = trim( $actor->get_display_name() );
	$kind = axismundi_contacts_default_kind( $actor->get_type() );
	/*
	 * A profile card is minted with a uid, because it is the one Card here that other people will
	 * hold copies of. It is what lets somebody who saved this Actor before recognise the same Card
	 * later, when a wider audience or a first sync brings them more of it, instead of ending up with
	 * two contacts for one person.
	 *
	 * A uid is not permission to read anything. Knowing it says which Card this is, never who may open
	 * it, and nothing downstream may treat holding one as authorisation.
	 *
	 * Cards about other people are not given one. A uid somebody else can quote should come from the
	 * entity it describes; minting identities on their behalf for every contact in a private address
	 * book would put this site's invented identifiers into other people's exports.
	 */
	$document = array( '@type' => 'Card', 'uid' => 'urn:uuid:' . wp_generate_uuid4() );
	if ( '' !== $kind ) {
		$document['kind'] = $kind;
	}
	$document['name'] = array( '@type' => 'Name', 'full' => '' !== $name ? $name : __( 'Me', 'axismundi-contacts' ) );
	$card             = axismundi_contacts_save_card_for_owner( $actor_id, $document );
	if ( is_wp_error( $card ) ) {
		return $card;
	}
	axismundi_contacts_set_provenance( (int) $card, 'name', AXISMUNDI_CONTACTS_SOURCE_ACTOR, $actor->get_uri() );
	/*
	 * This site's own account, pinned to the top of the card. It is the one this Actor is here, so it
	 * leads -- and unlike the accounts added underneath it, this row is seeded and refreshed from the
	 * Actor rather than typed.
	 */
	axismundi_contacts_link_actor( (int) $card, $actor->get_uri(), 'Axismundi', AXISMUNDI_CONTACTS_HOME_SERVICE_KEY );
	$seeded = axismundi_contacts_card_document( (int) $card );
	if ( isset( $seeded['onlineServices'][ AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ] ) ) {
		$seeded['onlineServices'][ AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ]['pref'] = 1;
		axismundi_contacts_save_card_for_owner( $actor_id, $seeded, (int) $card );
	}
	$bound = axismundi_contacts_set_profile_card( $actor_id, (int) $card );
	if ( is_wp_error( $bound ) ) {
		return $bound;
	}
	return (int) $card;
}
