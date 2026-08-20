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
 * Provenance is not here. It records where a value came from, which is a different question from
 * what the document says, and mixing it into the draft would have the editor round-tripping a fact
 * it did not author and cannot be responsible for.
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
	 * The uid is the Card's identity and is not the editor's to change. A draft that came back with a
	 * different one would be published as a different contact to everybody who had saved the first.
	 */
	if ( '' !== (string) ( $row['uid'] ?? '' ) ) {
		$card['uid'] = (string) $row['uid'];
	}

	$saved = axismundi_contacts_save_card_for_owner( $owner, $card, $card_id, (int) $request['revision'] );
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}
	if ( null !== $pointers ) {
		$written = axismundi_contacts_set_published_pointers( $owner, $pointers );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
	}
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
	 * `version` is deliberately not required. It says which revision of JSContact a document is
	 * serialised as, which is a statement about a copy leaving here rather than a fact about the
	 * contact -- so the public route states it on the way out and the ledger does not have to carry
	 * one. Requiring it would mean the editor could not save a Card this site made itself. A Card
	 * that arrived with one keeps it, like every other property.
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
		foreach ( array_keys( $patch ) as $path ) {
			/*
			 * A localization patches the Card it is on. Patching the localizations themselves is
			 * refused by RFC 9553, and a path that started there would be a document describing its own
			 * translations recursively.
			 */
			if ( 'localizations' === (string) $path || 0 === strpos( (string) $path, 'localizations/' ) ) {
				return new WP_Error(
					'ax_contacts_draft_patch_recursive',
					__( 'A localization cannot patch the localizations.', 'axismundi-contacts' ),
					array( 'status' => 400 )
				);
			}
		}
	}
	return axismundi_contacts_validate_card( $card );
}
