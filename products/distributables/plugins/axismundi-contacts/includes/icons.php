<?php
/**
 * The icons this plugin draws, registered once and named everywhere else.
 *
 * WordPress 7.1 keeps a registry of icons under a collection each plugin owns. Registering there
 * rather than inlining SVG means a screen names an icon and gets whatever the registry holds: one
 * asset, one style, and a second screen that needs `delete` cannot end up with a different bin.
 *
 * The set is deliberately small -- what this editor actually draws. A registry full of icons nobody
 * uses is one nobody trusts to say which is the right one, and the ones for Groups, anniversaries
 * and places belong to the slices that draw them.
 *
 * Everything here is Material Symbols under the Apache Licence 2.0; see `assets/icons/LICENSE.md`
 * for the two changes made to the files and why.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/** The collection this plugin's icons live under. */
const AXISMUNDI_CONTACTS_ICON_COLLECTION = 'axismundi-contacts';

/**
 * Every icon, by the name a screen asks for and the label a person would read.
 *
 * Named for what it is rather than where it is used. `delete` is a bin whether it removes a contact
 * or a row of a name, and an icon called `remove-name-part` would be one nobody reuses.
 *
 * @return array<string,string> File stem => label.
 */
function axismundi_contacts_icons() : array {
	return array(
		'add'                    => __( 'Add', 'axismundi-contacts' ),
		'delete'                 => __( 'Delete', 'axismundi-contacts' ),
		'drag-indicator'         => __( 'Drag to reorder', 'axismundi-contacts' ),
		'close'                  => __( 'Close', 'axismundi-contacts' ),
		'contacts'               => __( 'Contacts', 'axismundi-contacts' ),
		'person'                 => __( 'Person', 'axismundi-contacts' ),
		'domain'                 => __( 'Organisation', 'axismundi-contacts' ),
		'group'                  => __( 'Group', 'axismundi-contacts' ),
		'location-on'            => __( 'Place', 'axismundi-contacts' ),
		'apps'                   => __( 'Application', 'axismundi-contacts' ),
		'devices'                => __( 'Device', 'axismundi-contacts' ),
		'mail'                   => __( 'Email', 'axismundi-contacts' ),
		'call'                   => __( 'Phone', 'axismundi-contacts' ),
		'alternate-email'        => __( 'Online account', 'axismundi-contacts' ),
		'link'                   => __( 'Link', 'axismundi-contacts' ),
		'image'                  => __( 'Media', 'axismundi-contacts' ),
		'language'               => __( 'Language', 'axismundi-contacts' ),
		'language-international' => __( 'Other languages', 'axismundi-contacts' ),
		'notes'                  => __( 'Notes', 'axismundi-contacts' ),
		'keyboard-arrow-down'    => __( 'More', 'axismundi-contacts' ),
		'visibility'             => __( 'Published', 'axismundi-contacts' ),
	);
}

/**
 * Put them in the registry.
 *
 * Registered from the file rather than read into memory here: the registry decides when it needs the
 * markup, and a plugin that inlined thirteen SVGs on every request would be paying for all of them
 * to draw one.
 *
 * @return void
 */
function axismundi_contacts_register_icons() : void {
	if ( ! function_exists( 'wp_register_icon_collection' ) || ! function_exists( 'wp_register_icon' ) ) {
		// An older WordPress has no registry. The screens fall back to naming no icon, which is a
		// button with a word on it rather than a broken one.
		return;
	}
	wp_register_icon_collection(
		AXISMUNDI_CONTACTS_ICON_COLLECTION,
		array(
			'label'       => __( 'Axismundi Contacts', 'axismundi-contacts' ),
			'description' => __( 'Material Symbols used by the contacts screens.', 'axismundi-contacts' ),
		)
	);
	$directory = dirname( __DIR__ ) . '/assets/icons/';
	foreach ( axismundi_contacts_icons() as $name => $label ) {
		$file = $directory . $name . '.svg';
		if ( ! file_exists( $file ) ) {
			continue;
		}
		wp_register_icon(
			AXISMUNDI_CONTACTS_ICON_COLLECTION . '/' . $name,
			array( 'label' => $label, 'file_path' => $file )
		);
	}
}
add_action( 'init', 'axismundi_contacts_register_icons' );

/**
 * One icon's markup, or nothing.
 *
 * Nothing rather than a placeholder. A screen that asked for an icon this plugin does not have
 * should show the word it labelled the control with, not a box suggesting the picture failed to
 * load.
 *
 * @param string $name Icon name without the collection.
 * @return string
 */
function axismundi_contacts_icon( string $name ) : string {
	if ( ! function_exists( 'wp_get_icon' ) ) {
		return '';
	}
	return (string) wp_get_icon( AXISMUNDI_CONTACTS_ICON_COLLECTION . '/' . $name );
}
