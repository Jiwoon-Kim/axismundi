<?php
/** Contacts-owned JMAP data types and explicit Account-bound application services. */
defined( 'ABSPATH' ) || exit;

const AXISMUNDI_CONTACTS_JMAP_CAPABILITY = 'urn:ietf:params:jmap:contacts';

function axismundi_contacts_register_jmap( \Axismundi\Jmap\Registry $registry ) : void {
	$registry->register_capability( AXISMUNDI_CONTACTS_JMAP_CAPABILITY );
	$registry->register_type( 'AddressBook', AXISMUNDI_CONTACTS_JMAP_CAPABILITY, 'https://www.rfc-editor.org/rfc/rfc9610.html#section-7.2' );
}
add_action( 'axismundi_jmap_register', 'axismundi_contacts_register_jmap' );

/** Resolve the explicit Account, never the current Actor cookie or a WP User's primary Actor. */
function axismundi_contacts_resolve_jmap_account( string $account_id, bool $writes = false ) {
	if ( ! axismundi_contacts_ready() ) { return new WP_Error( 'ax_contacts_jmap_unavailable', 'Contacts Account storage is unavailable.', array( 'status' => 503 ) ); }
	$actor = \Axismundi\Jmap\resolve_account_actor( $account_id, get_current_user_id() );
	if ( ! $actor || ! ( $writes
		? axismundi_contacts_can_write_account( $actor->get_identity_id(), get_current_user_id() )
		: axismundi_contacts_can_read_account( $actor->get_identity_id(), get_current_user_id() ) ) ) {
		return new WP_Error( 'ax_contacts_account_forbidden', 'Account access is unavailable.', array( 'status' => 403 ) );
	}
	return $actor;
}

/** Explicit write. Default creation is opt-in; ordinary Account reads create nothing. */
function axismundi_contacts_create_book_for_account( string $account_id, string $name, bool $default = false ) {
	$actor = axismundi_contacts_resolve_jmap_account( $account_id, true );
	if ( is_wp_error( $actor ) ) { return $actor; }
	if ( ! $default && ( '' === trim( $name ) || mb_strlen( $name ) > 191 ) ) {
		return new WP_Error( 'ax_contacts_book_name', 'An address book needs a name of at most 191 characters.', array( 'status' => 400 ) );
	}
	return Axismundi_Contacts_Write::run( static function () use ( $actor, $name, $default ) {
		if ( $default ) {
			$book = axismundi_contacts_book_for_actor( $actor->get_identity_id() );
			return is_wp_error( $book ) ? $book : (int) $book['id'];
		}
		return axismundi_contacts_create_book( $actor->get_identity_id(), $name );
	} );
}

function axismundi_contacts_books_for_account( string $account_id ) {
	$actor = axismundi_contacts_resolve_jmap_account( $account_id );
	return is_wp_error( $actor ) ? $actor : axismundi_contacts_books_for_actor( $actor->get_identity_id() );
}
