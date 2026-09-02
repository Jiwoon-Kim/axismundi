<?php
/**
 * What this plugin needs, and the narrow reasons it needs it.
 *
 * Actors, for two things and no more:
 *
 *   1. an address book belongs to an Actor, so that a person and an Organization can each keep one
 *      and the existing acting-Actor switch decides which is open;
 *   2. a Card may link to an Actor, which is what makes an avatar and a public profile available.
 *
 * Neither of those makes a Card into an Actor. A Card with a name and a telephone number is a
 * complete contact, and most of any real address book looks like that.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * The release this plugin was built against.
 *
 * Both plugins installed is not the same as both from one deployment: `function_exists` proves the
 * seams named here and nothing else, so a site that updates one and not the other is refused at the
 * boundary rather than somewhere in the middle of a save.
 */
const AXISMUNDI_CONTACTS_ACTORS_MINIMUM = '0.1.0';

/**
 * Whether the identity service is present and new enough.
 *
 * @return bool
 */
function axismundi_contacts_has_actors() : bool {
	return defined( 'AXISMUNDI_ACTORS_VERSION' )
		&& version_compare( (string) AXISMUNDI_ACTORS_VERSION, AXISMUNDI_CONTACTS_ACTORS_MINIMUM, '>=' )
		&& function_exists( 'axismundi_actors_get_by_identity' );
}

/**
 * Whether this plugin can do anything at all.
 *
 * @return bool
 */
function axismundi_contacts_ready() : bool {
	return axismundi_contacts_has_actors()
		&& AXISMUNDI_CONTACTS_DB_VERSION === (string) get_option( AXISMUNDI_CONTACTS_DB_VERSION_OPTION, '' );
}

/**
 * What is missing, named the way somebody reading it will recognise.
 *
 * @return string[]
 */
function axismundi_contacts_unmet_dependencies() : array {
	if ( axismundi_contacts_has_actors() ) {
		return array();
	}
	return array(
		defined( 'AXISMUNDI_ACTORS_VERSION' )
			/* translators: %s: version number. */
			? sprintf( __( 'Axismundi Actors %s or newer', 'axismundi-contacts' ), AXISMUNDI_CONTACTS_ACTORS_MINIMUM )
			: __( 'Axismundi Actors', 'axismundi-contacts' ),
	);
}

/**
 * Say so on the plugins screen, where the thing to fix is already on screen.
 *
 * @return void
 */
function axismundi_contacts_dependency_notice() : void {
	$unmet = axismundi_contacts_unmet_dependencies();
	if ( array() === $unmet || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen instanceof WP_Screen || 'plugins' !== $screen->id ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: %s: comma-separated plugin names. */
				__( 'Axismundi Contacts is not running: it needs %s. Address books already saved are untouched and reappear once it is available.', 'axismundi-contacts' ),
				implode( ', ', $unmet )
			)
		)
	);
}
add_action( 'admin_notices', 'axismundi_contacts_dependency_notice' );
