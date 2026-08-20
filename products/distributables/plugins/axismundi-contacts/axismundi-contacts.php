<?php
/**
 * Plugin Name:       Axismundi Contacts
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-contacts
 * Description:       Address books of JSContact Cards, owned by an Actor and authored by a person. Imports and federation are optional sources; the Card is the record.
 * Version:           0.1.0-beta.1
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            Ji-woon Kim
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-contacts
 *
 * An address book is not a view of the network. It is a thing somebody keeps: a name and a phone
 * number written down, a card imported from an old account, a business card typed in from a
 * receipt. Most of it will never be an Actor and never federate, and that is the normal case rather
 * than an incomplete one.
 *
 * So the JSContact Card is the record here, not a projection of one. That is the opposite of how
 * Actors uses the same standard -- there a Card is assembled from identity facts for publication --
 * and the difference is who is writing. An Actor publishes; a person keeps.
 *
 *   ax_contact_address_books   whose book this is
 *   ax_contact_cards           the Cards, as authored
 *   ax_contact_card_values     what a Card holds, so it can be searched
 *   ax_contact_card_provenance where each value came from
 *
 * Actors is required for exactly two things: an address book belongs to an Actor, and a Card may
 * link to one. Neither makes a Card into an Actor.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_CONTACTS_VERSION = '0.1.0-beta.1';

require_once __DIR__ . '/includes/dependencies.php';
require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/address-books.php';
require_once __DIR__ . '/includes/vocabulary.php';
require_once __DIR__ . '/includes/canonical.php';
require_once __DIR__ . '/includes/name-format.php';
require_once __DIR__ . '/includes/cards.php';
require_once __DIR__ . '/includes/localizations.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/lifecycle.php';
require_once __DIR__ . '/includes/provenance.php';
require_once __DIR__ . '/includes/actor-link.php';
require_once __DIR__ . '/includes/name-sync.php';
require_once __DIR__ . '/includes/public-projection.php';
require_once __DIR__ . '/includes/jscontact.php';
require_once __DIR__ . '/includes/rest-draft.php';
if ( is_admin() ) {
	require_once __DIR__ . '/includes/name-editor.php';
	require_once __DIR__ . '/includes/card-detail.php';
	require_once __DIR__ . '/includes/admin.php';
	require_once __DIR__ . '/includes/profile-screen.php';
	require_once __DIR__ . '/includes/name-bindings.php';
}

/** Install the address book store. */
function axismundi_contacts_activate() : void {
	axismundi_contacts_install_schema();
}
register_activation_hook( __FILE__, 'axismundi_contacts_activate' );

/** Run schema upgrades on an ordinary plugin update, not only on activation. */
function axismundi_contacts_maybe_upgrade() : void {
	if ( AXISMUNDI_CONTACTS_DB_VERSION !== (string) get_option( AXISMUNDI_CONTACTS_DB_VERSION_OPTION, '' ) ) {
		axismundi_contacts_install_schema();
	}
}
add_action( 'plugins_loaded', 'axismundi_contacts_maybe_upgrade', 20 );
