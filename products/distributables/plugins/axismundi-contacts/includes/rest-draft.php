<?php
/**
 * The one door the Card editor writes through.
 *
 * A draft is the whole ledger plus the revision it was read at. The visual form, the localizations
 * screen and the Advanced JSON box are three views of the same document, so they read and write one
 * thing: three endpoints, or three shapes of payload, would be three chances for one of them to
 * carry a Card that had lost whatever the others do not display.
 *
 * Three rules the editor cannot bend.
 *
 * Permission is the Card's owner, never the address book in the URL. A Card belongs to as many
 * groups as somebody files it into, and which sidebar it happened to be opened from says nothing
 * about who may change it.
 *
 * `publishedPointers` belongs to the one Card an Actor publishes about itself, and is absent
 * everywhere else -- not empty, absent. An ordinary contact has no public policy to send, and a
 * field that arrived anyway is a caller confused about which Card this is rather than a caller
 * asking to publish nothing.
 *
 * And a save is one decision. A stale revision, a pointer that names nothing, a document that is not
 * a Card: any of them refuses the whole request. A partly-applied save leaves a ledger nobody
 * authored, and the editor's own copy would still be the version that was rejected.
 *
 * Provenance is in neither direction of the payload. It records where a value came from, which is a
 * different question from what the document says, and mixing it in would have the editor
 * round-tripping a fact it did not author. It is still written on save: whatever somebody changed by
 * hand becomes theirs, so that refreshing the Actor a value came from does not take their edit away.
 *
 * A Card's `uid` is not authority over it. Two people may keep a Card for the same person, with the
 * same `uid`, and each edits their own -- what may be changed is decided by who owns the record, and
 * `uid` only says that the two are about the same somebody.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/** @return string The REST namespace this plugin answers on. */
function axismundi_contacts_rest_namespace() : string {
	return 'axismundi-contacts/v1';
}

/**
 * Register the draft routes.
 *
 * @return void
 */
function axismundi_contacts_register_draft_routes() : void {
	register_rest_route(
		axismundi_contacts_rest_namespace(),
		'/cards/(?P<id>\d+)/draft',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'axismundi_contacts_rest_get_draft',
				'permission_callback' => 'axismundi_contacts_rest_may_edit_card',
				'args'                => array( 'id' => array( 'type' => 'integer', 'required' => true ) ),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => 'axismundi_contacts_rest_put_draft',
				'permission_callback' => 'axismundi_contacts_rest_may_edit_card',
				'args'                => array(
					'id'       => array( 'type' => 'integer', 'required' => true ),
					'revision' => array( 'type' => 'integer', 'required' => true ),
					'card'     => array( 'type' => 'object', 'required' => true ),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_contacts_register_draft_routes' );

/**
 * Whether the caller may open this Card for changes.
 *
 * Asked of the Card's owner. Not of a book: a Card filed into `Work` and `School` is one record with
 * one owner, and answering from whichever container the caller mentioned would let the answer depend
 * on how they asked.
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function axismundi_contacts_rest_may_edit_card( WP_REST_Request $request ) {
	$card = axismundi_contacts_get_card( (int) $request['id'] );
	if ( array() === $card ) {
		// The same answer as a Card somebody may not open, so this route cannot be used to ask which
		// Cards exist.
		return new WP_Error( 'ax_contacts_draft_missing', __( 'That contact does not exist.', 'axismundi-contacts' ), array( 'status' => 404 ) );
	}
	if ( ! axismundi_contacts_can_use_book( (int) $card['owner_actor_id'], get_current_user_id() ) ) {
		return new WP_Error( 'ax_contacts_draft_missing', __( 'That contact does not exist.', 'axismundi-contacts' ), array( 'status' => 404 ) );
	}
	return true;
}

/**
 * Whether this Card is the one its owner publishes about itself.
 *
 * @param array<string,mixed> $card Card row.
 * @return bool
 */
function axismundi_contacts_is_profile_card( array $card ) : bool {
	$owner = (int) ( $card['owner_actor_id'] ?? 0 );
	return $owner > 0 && (int) ( $card['id'] ?? 0 ) === axismundi_contacts_profile_card( $owner );
}

/**
 * One Card, as the editor holds it.
 *
 * @param array<string,mixed> $row Card row.
 * @return array<string,mixed>
 */
function axismundi_contacts_draft_payload( array $row ) : array {
	$payload = array(
		'card'     => axismundi_contacts_card_document( (int) $row['id'] ),
		'revision' => (int) ( $row['revision'] ?? 0 ),
	);
	if ( axismundi_contacts_is_profile_card( $row ) ) {
		$payload['publishedPointers'] = axismundi_contacts_published_pointers( (int) $row['owner_actor_id'] );
	}
	return $payload;
}

/**
 * Read one Card's draft.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_contacts_rest_get_draft( WP_REST_Request $request ) {
	$row = axismundi_contacts_get_card( (int) $request['id'] );
	if ( array() === $row ) {
		return new WP_Error( 'ax_contacts_draft_missing', __( 'That contact does not exist.', 'axismundi-contacts' ), array( 'status' => 404 ) );
	}
	return rest_ensure_response( axismundi_contacts_draft_payload( $row ) );
}

/**
 * Write one Card's draft, or refuse the whole of it.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_contacts_rest_put_draft( WP_REST_Request $request ) {
	$card_id = (int) $request['id'];
	$row     = axismundi_contacts_get_card( $card_id );
	if ( array() === $row ) {
		return new WP_Error( 'ax_contacts_draft_missing', __( 'That contact does not exist.', 'axismundi-contacts' ), array( 'status' => 404 ) );
	}
	$owner   = (int) $row['owner_actor_id'];
	$profile = axismundi_contacts_is_profile_card( $row );
	$card    = (array) $request['card'];

	/*
	 * Everything is checked before anything is written. A save that refused halfway would leave a
	 * ledger nobody authored, and the editor holding a draft it believes was rejected.
	 */
	$valid = axismundi_contacts_validate_draft( $card );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}

	$pointers = null;
	if ( $request->has_param( 'publishedPointers' ) ) {
		if ( ! $profile ) {
			return new WP_Error(
				'ax_contacts_draft_not_profile',
				__( 'Only the card an Actor publishes about itself has a public selection.', 'axismundi-contacts' ),
				array( 'status' => 400 )
			);
		}
		$pointers = array_map( 'strval', (array) $request['publishedPointers'] );
		foreach ( $pointers as $pointer ) {
			if ( ! axismundi_contacts_is_publishable_pointer( $pointer ) ) {
				return new WP_Error(
					'ax_contacts_draft_pointer',
					/* translators: %s: the pointer that was sent. */
					sprintf( __( '%s does not name anything that can be published.', 'axismundi-contacts' ), $pointer ),
					array( 'status' => 400 )
				);
			}
		}
	}

	/*
	 * The uid is the Card's identity and is not the editor's to change. A Card that came back with a
	 * different one would be published as a different contact to everybody who had saved the first --
	 * they would end up holding two records of one person and no way to tell which is current.
	 *
	 * Refused rather than quietly put back. Somebody who typed a uid into the JSON box meant it, and
	 * a save that discarded it while reporting success would leave them believing the Card had an
	 * identity it does not have. A request that omits it is not changing it, so it is restored.
	 */
	$uid = (string) ( $row['uid'] ?? '' );
	if ( '' !== $uid ) {
		if ( isset( $card['uid'] ) && (string) $card['uid'] !== $uid ) {
			return new WP_Error(
				'ax_contacts_draft_uid',
				__( 'A card keeps the identity it was published under. Anybody holding a copy of this one finds it by that.', 'axismundi-contacts' ),
				array( 'status' => 409 )
			);
		}
		$card['uid'] = $uid;
	}

	/*
	 * And what the Card an Actor publishes about itself describes is that Actor's answer, not a field
	 * on this screen. A Person's card claiming to be an organisation would say one thing to a reader
	 * and the Actor document another, and the two are served from the same site.
	 */
	if ( $profile && function_exists( 'axismundi_actors_jscontact_kind' ) && function_exists( 'axismundi_actors_get_by_identity' ) ) {
		$actor = axismundi_actors_get_by_identity( $owner );
		$kind  = $actor instanceof Axismundi_Actor ? axismundi_actors_jscontact_kind( (string) $actor->get_type() ) : '';
		if ( '' !== $kind && (string) ( $card['kind'] ?? $kind ) !== $kind ) {
			return new WP_Error(
				'ax_contacts_draft_kind',
				__( 'This is the card an Actor publishes about itself, so what it describes is what that Actor is.', 'axismundi-contacts' ),
				array( 'status' => 409 )
			);
		}
	}

	/*
	 * The account that says which Actor this Card is about. It is this site's answer to "whose
	 * profile is this", it is served to everybody as part of the Actor's public identity, and it is
	 * not an entry on the list of accounts somebody keeps -- so the editor may not retire it, rename
	 * what it says, or move it out of first place.
	 *
	 * Removing it is refused too, rather than accepted and put back. Accepting a save that did not
	 * happen tells a client the account is gone when it is not, and a second request arriving between
	 * the two writes would read a Card that says nothing about whose it is. Every existing Card is
	 * given one on upgrade, so nothing here has to make up for a Card that never had it.
	 *
	 * The refusal leaves the submitted document alone. Quietly writing the Actor's answer over
	 * somebody's edit would tell them it was saved.
	 */
	if ( $profile && function_exists( 'axismundi_actors_get_by_identity' ) ) {
		$identity_actor = axismundi_actors_get_by_identity( $owner );
		if ( $identity_actor instanceof Axismundi_Actor ) {
			$fixed    = axismundi_contacts_identity_service( $identity_actor );
			$existing = $card['onlineServices'][ AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ] ?? null;
			if ( ! is_array( $existing ) || ! axismundi_contacts_identity_service_matches( $existing, $fixed ) ) {
				return new WP_Error(
					'ax_contacts_draft_identity',
					__( 'This account says which Actor this card is about, so this site answers it rather than the editor.', 'axismundi-contacts' ),
					array( 'status' => 409 )
				);
			}
		}
	}

	/*
	 * The document and what it publishes are one save. They are two tables, and a request that wrote
	 * the first and failed at the second would leave a Card published under a selection nobody chose
	 * -- which on this route means values reaching strangers that the person had not agreed to.
	 */
	global $wpdb;
	$before = axismundi_contacts_card_document( $card_id );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one save across two tables.
	$wpdb->query( 'START TRANSACTION' );
	$saved = axismundi_contacts_save_card_for_owner( $owner, $card, $card_id, (int) $request['revision'] );
	if ( is_wp_error( $saved ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'ROLLBACK' );
		return $saved;
	}
	if ( null !== $pointers ) {
		$written = axismundi_contacts_set_published_pointers( $owner, $pointers );
		if ( is_wp_error( $written ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'ROLLBACK' );
			return $written;
		}
	}
	/*
	 * And whatever was changed by hand becomes this person's. A value that came from an Actor is
	 * refreshed when the Actor changes; the moment somebody edits one, that has to stop being true of
	 * it, or the next refresh puts the Actor's answer back over the top of theirs.
	 *
	 * The screen form has always done this. Doing it only there would have meant the editor this
	 * route exists for was the one place where editing a linked value did not make it yours.
	 */
	$promoted = axismundi_contacts_record_local_edits( $card_id, $before, axismundi_contacts_card_document( $card_id ) );
	if ( is_wp_error( $promoted ) ) {
		/*
		 * The one step that could not be left out. Committing here would store somebody's edit while
		 * provenance still said an Actor owned the value -- and the next refresh would put the Actor's
		 * answer back, which is the failure this whole promotion exists to prevent.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'ROLLBACK' );
		return $promoted;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( 'COMMIT' );
	// Read back rather than echoed: what the editor gets is what is stored, in the order it is stored.
	return rest_ensure_response( axismundi_contacts_draft_payload( axismundi_contacts_get_card( $card_id ) ) );
}

/**
 * Whether a document is a JSContact Card this will store.
 *
 * The store's own rules, and the ones a draft has to add because a hand-edited JSON box can send
 * anything. What is deliberately not here is a closed list of properties: a Card carrying something
 * from a newer revision of the standard, or a vendor's own extension, is a Card worth keeping, and
 * refusing it would make this editor the reason somebody's data could not be stored.
 *
 * @param array<string,mixed> $card Card.
 * @return true|WP_Error
 */
function axismundi_contacts_validate_draft( array $card ) {
	if ( 'Card' !== (string) ( $card['@type'] ?? '' ) ) {
		return new WP_Error( 'ax_contacts_draft_type', __( 'A card says what it is: "@type": "Card".', 'axismundi-contacts' ), array( 'status' => 400 ) );
	}
	/*
	 * `version` is not required of a caller, and not because the ledger does without one: the store
	 * states it on save, for everything, so that this site holds one revision rather than a mixture
	 * every reader and every export has to handle. Requiring it here would only mean the editor could
	 * not send back a Card it had been given before that was true. What is checked is that a caller
	 * who does send one sends a version rather than a number.
	 */
	if ( isset( $card['version'] ) && ( ! is_string( $card['version'] ) || '' === trim( $card['version'] ) ) ) {
		return new WP_Error( 'ax_contacts_draft_version', __( 'The version of a card is the revision it is written in.', 'axismundi-contacts' ), array( 'status' => 400 ) );
	}
	if ( isset( $card['name'] ) ) {
		$name = $card['name'];
		if ( ! is_array( $name ) ) {
			return new WP_Error( 'ax_contacts_draft_name', __( 'A name is an object.', 'axismundi-contacts' ), array( 'status' => 400 ) );
		}
		/*
		 * One or the other, and either alone is complete. A name written out and never taken apart is
		 * how most of the world's names are best recorded; a name in components with no written-out
		 * form is what an import brings, and working out how it reads belongs to whoever shows it.
		 */
		$has_full       = '' !== trim( (string) ( $name['full'] ?? '' ) );
		$has_components = isset( $name['components'] ) && is_array( $name['components'] ) && array() !== $name['components'];
		if ( ! $has_full && ! $has_components ) {
			return new WP_Error(
				'ax_contacts_draft_name_empty',
				__( 'A name is either written out or given in parts.', 'axismundi-contacts' ),
				array( 'status' => 400 )
			);
		}
		if ( isset( $name['components'] ) && ! axismundi_contacts_is_list( (array) $name['components'] ) ) {
			// The components are a reading order, so they are a list rather than a map.
			return new WP_Error( 'ax_contacts_draft_components', __( 'The parts of a name are a list, in the order they are read.', 'axismundi-contacts' ), array( 'status' => 400 ) );
		}
	}
	$localizations = $card['localizations'] ?? array();
	if ( ! is_array( $localizations ) ) {
		return new WP_Error( 'ax_contacts_draft_localizations', __( 'The localizations of a card are a map of language tags.', 'axismundi-contacts' ), array( 'status' => 400 ) );
	}
	foreach ( $localizations as $tag => $patch ) {
		if ( ! is_array( $patch ) ) {
			return new WP_Error(
				'ax_contacts_draft_patch',
				/* translators: %s: language tag. */
				sprintf( __( 'The localization for %s is a set of paths and the values to put there.', 'axismundi-contacts' ), (string) $tag ),
				array( 'status' => 400 )
			);
		}
		/*
		 * Checked the same way whatever wrote it. A patch that an import brought and a patch somebody
		 * typed are the same kind of thing, and validating them differently would mean a Card that
		 * arrived could not be read out and written back unchanged.
		 *
		 * The first one that cannot be applied refuses the whole request. A localization is a set of
		 * changes to one document, and applying the half that happened to come first would leave a
		 * Card in a state nobody asked for.
		 */
		$patched = axismundi_contacts_validate_patch( $card, (string) $tag, $patch );
		if ( is_wp_error( $patched ) ) {
			return $patched;
		}
	}
	/*
	 * And the Card says each thing it knows about in the shape that thing has. Held to the same rules
	 * as a localization's result, because a document the editor may not produce by patching is not one
	 * it should be able to produce by typing either.
	 */
	$values = axismundi_contacts_validate_card_values( $card );
	if ( is_wp_error( $values ) ) {
		return $values;
	}
	return axismundi_contacts_validate_card( $card );
}
