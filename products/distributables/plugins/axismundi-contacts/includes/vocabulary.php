<?php
/**
 * What the labels on a contact row actually mean.
 *
 * `휴대전화`, `직장팩스`, `집` are not values. They are the reading of two standard axes plus,
 * sometimes, a word somebody chose:
 *
 *   contexts   where this belongs      private · work
 *   features   what it can do          voice · mobile · fax · pager · text · video
 *   label      what a person called it free text
 *
 * Storing the Korean word instead would make an export unreadable to anybody else and an import
 * from Google or a phone unmatchable -- `직장팩스` and `Work fax` are the same fact, and only the
 * axes say so. So the stored value is always the standard one and the word on screen is rendered
 * from it.
 *
 * The exception is deliberate: when somebody types their own label, that text is the fact. Nothing
 * guesses a context or a feature from it, because a person writing their own word is telling you
 * the standard vocabulary did not have what they meant.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/** Where an entry belongs. Addresses add delivery and billing; a phone has no use for those. */
const AXISMUNDI_CONTACTS_CONTEXTS = array( 'private', 'work' );

/**
 * What a number can do.
 *
 * Kept as a list this code recognises rather than a closed world: an imported Card may carry
 * features from a newer revision than this, and dropping them on the next save would lose data
 * somebody's other client understands.
 */
const AXISMUNDI_CONTACTS_PHONE_FEATURES = array( 'voice', 'mobile', 'fax', 'pager', 'text', 'video', 'textphone', 'main-number' );

/**
 * The labels a phone row offers, and the standard values behind each.
 *
 * The list is the one people expect from a phone's address book, because that is what they are
 * copying from. What is stored is the right-hand side.
 *
 * @return array<string,array{label:string,contexts:string[],features:string[]}>
 */
function axismundi_contacts_phone_presets() : array {
	return array(
		'mobile'     => array( 'label' => __( 'Mobile', 'axismundi-contacts' ), 'contexts' => array(), 'features' => array( 'mobile' ) ),
		'home'       => array( 'label' => __( 'Home', 'axismundi-contacts' ), 'contexts' => array( 'private' ), 'features' => array( 'voice' ) ),
		'work'       => array( 'label' => __( 'Work', 'axismundi-contacts' ), 'contexts' => array( 'work' ), 'features' => array( 'voice' ) ),
		'home-fax'   => array( 'label' => __( 'Home fax', 'axismundi-contacts' ), 'contexts' => array( 'private' ), 'features' => array( 'fax' ) ),
		'work-fax'   => array( 'label' => __( 'Work fax', 'axismundi-contacts' ), 'contexts' => array( 'work' ), 'features' => array( 'fax' ) ),
		'main'       => array( 'label' => __( 'Main', 'axismundi-contacts' ), 'contexts' => array( 'work' ), 'features' => array( 'main-number' ) ),
		'pager'      => array( 'label' => __( 'Pager', 'axismundi-contacts' ), 'contexts' => array(), 'features' => array( 'pager' ) ),
		'other'      => array( 'label' => __( 'Other', 'axismundi-contacts' ), 'contexts' => array(), 'features' => array() ),
	);
}

/**
 * The labels an email row offers.
 *
 * Fewer, because an email address has no second axis: there is no such thing as a fax email. Google
 * offers only a free label here for the same reason, and that is the honest shape.
 *
 * @return array<string,array{label:string,contexts:string[],features:string[]}>
 */
function axismundi_contacts_email_presets() : array {
	return array(
		'private' => array( 'label' => __( 'Personal', 'axismundi-contacts' ), 'contexts' => array( 'private' ), 'features' => array() ),
		'work'    => array( 'label' => __( 'Work', 'axismundi-contacts' ), 'contexts' => array( 'work' ), 'features' => array() ),
		'other'   => array( 'label' => __( 'Other', 'axismundi-contacts' ), 'contexts' => array(), 'features' => array() ),
	);
}

/**
 * The labels an address row offers.
 *
 * @return array<string,array{label:string,contexts:string[],features:string[]}>
 */
function axismundi_contacts_address_presets() : array {
	return array(
		'private' => array( 'label' => __( 'Home', 'axismundi-contacts' ), 'contexts' => array( 'private' ), 'features' => array() ),
		'work'    => array( 'label' => __( 'Work', 'axismundi-contacts' ), 'contexts' => array( 'work' ), 'features' => array() ),
		'other'   => array( 'label' => __( 'Other', 'axismundi-contacts' ), 'contexts' => array(), 'features' => array() ),
	);
}

/**
 * The presets for one field.
 *
 * @param string $field JSContact field name.
 * @return array<string,array{label:string,contexts:string[],features:string[]}>
 */
function axismundi_contacts_presets_for( string $field ) : array {
	switch ( $field ) {
		case 'phones':
			return axismundi_contacts_phone_presets();
		case 'emails':
			return axismundi_contacts_email_presets();
		case 'addresses':
			return axismundi_contacts_address_presets();
		default:
			return array();
	}
}

/**
 * Which preset an entry reads as, or `custom` when it does not read as any.
 *
 * Derived rather than stored. Storing the chosen preset alongside the values it set would be a
 * second record of the same fact, and an entry arriving from another client would have no preset to
 * store -- so the question is asked of the values every time it is displayed.
 *
 * @param string              $field JSContact field name.
 * @param array<string,mixed> $entry Entry as stored.
 * @return string Preset key, or 'custom'.
 */
function axismundi_contacts_entry_preset( string $field, array $entry ) : string {
	// A label somebody typed is the fact, and outranks whatever the axes happen to say.
	if ( '' !== trim( (string) ( $entry['label'] ?? '' ) ) ) {
		return 'custom';
	}
	$contexts = array_keys( array_filter( (array) ( $entry['contexts'] ?? array() ) ) );
	$features = array_keys( array_filter( (array) ( $entry['features'] ?? array() ) ) );
	sort( $contexts );
	sort( $features );
	foreach ( axismundi_contacts_presets_for( $field ) as $key => $preset ) {
		$preset_contexts = $preset['contexts'];
		$preset_features = $preset['features'];
		sort( $preset_contexts );
		sort( $preset_features );
		if ( $contexts === $preset_contexts && $features === $preset_features ) {
			return (string) $key;
		}
	}
	return 'custom';
}

/**
 * Apply one preset to an entry, or record the label somebody typed.
 *
 * The two are exclusive. Choosing `custom` clears the axes rather than leaving whatever was there,
 * because an entry that says both `work` and `내 별장` is claiming two different answers to the same
 * question and a reader has to pick one.
 *
 * @param string              $field  JSContact field name.
 * @param array<string,mixed> $entry  Entry to change.
 * @param string              $preset Preset key, or 'custom'.
 * @param string              $label  Free label, used when the preset is 'custom'.
 * @return array<string,mixed>
 */
function axismundi_contacts_apply_preset( string $field, array $entry, string $preset, string $label = '' ) : array {
	unset( $entry['contexts'], $entry['features'], $entry['label'] );
	if ( 'custom' === $preset ) {
		$label = trim( $label );
		if ( '' !== $label ) {
			$entry['label'] = $label;
		}
		return $entry;
	}
	$presets = axismundi_contacts_presets_for( $field );
	if ( ! isset( $presets[ $preset ] ) ) {
		return $entry;
	}
	// JSContact says these as sets: a map of value => true, not a list.
	foreach ( array( 'contexts', 'features' ) as $axis ) {
		$values = $presets[ $preset ][ $axis ];
		if ( array() !== $values ) {
			$entry[ $axis ] = array_fill_keys( $values, true );
		}
	}
	return $entry;
}

/**
 * What to call one entry on screen.
 *
 * @param string              $field JSContact field name.
 * @param array<string,mixed> $entry Entry as stored.
 * @return string
 */
function axismundi_contacts_entry_label( string $field, array $entry ) : string {
	$preset = axismundi_contacts_entry_preset( $field, $entry );
	if ( 'custom' === $preset ) {
		return trim( (string) ( $entry['label'] ?? '' ) );
	}
	return (string) ( axismundi_contacts_presets_for( $field )[ $preset ]['label'] ?? '' );
}
