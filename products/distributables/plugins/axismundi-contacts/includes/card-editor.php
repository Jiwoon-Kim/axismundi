<?php
/**
 * Where the Card editor is mounted, and what it is handed to start from.
 *
 * The screen is a heading and an empty element. Everything else is the editor, which reads and writes
 * the draft route: this file does not save anything, and adding a form here would be a second way to
 * write a Card whose rules were maintained separately from the first.
 *
 * The draft is handed over in the page rather than fetched, so the editor draws the Card on its first
 * paint instead of an empty form that fills in a moment later. It is the same payload the route
 * returns, from the same function, so there is one shape rather than two that must be kept in step.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * The same localized IANA timezone choices WordPress gives its own Settings screen.
 *
 * Core also appends manual UTC offsets. They are useful as a site display preference, but an
 * Address's `timeZone` is required to name a place in the IANA Time Zone Database, so do not offer
 * an answer this Card cannot say. The option markup remains Core's: its labels and regional groups
 * follow the current WordPress locale without this plugin maintaining another timezone list.
 *
 * @return string Safe Core-generated option and optgroup markup.
 */
function axismundi_contacts_timezone_choice() : string {
	require_once ABSPATH . 'wp-admin/includes/template.php';
	// Core returns this markup rather than printing it, as its own callers `echo` it.
	$choices = (string) wp_timezone_choice( '', get_user_locale() );
	$names   = array_fill_keys( timezone_identifiers_list(), true );

	$choices = (string) preg_replace_callback(
		'/<option\\b[^>]*\\bvalue=(?:"([^"]*)"|\'([^\']*)\')[^>]*>.*?<\\/option>/is',
		static function ( array $match ) use ( $names ) : string {
			$value = html_entity_decode( (string) ( $match[1] ?? $match[2] ?? '' ), ENT_QUOTES, 'UTF-8' );
			// Core's own empty choice reads as an instruction rather than an answer, and this field
			// has its own way of saying nothing. Two blanks in one menu is one too many.
			return isset( $names[ $value ] ) ? $match[0] : '';
		},
		$choices
	);

	/*
	 * A group whose every answer was an offset is now a heading over nothing. Core writes the offsets
	 * as one group, so this drops the emptied group rather than matching a label that is translated.
	 */
	$choices = (string) preg_replace( '/<optgroup\b[^>]*>\s*<\/optgroup>/i', '', $choices );

	/*
	 * And an answer for not having one. Core's picker is used where a site must be on some clock, so
	 * it offers no empty choice; an address may simply not say which zone it is in. Without this the
	 * first zone in the list is shown for an address that names none -- the screen saying Antarctica
	 * while the Card says nothing -- and choosing it again is how somebody takes the answer back off.
	 */
	return '<option value="">' . esc_html__( 'Not said', 'axismundi-contacts' ) . '</option>' . $choices;
}

/**
 * Load the editor on the screen that edits a Card.
 *
 * @param string $hook Current admin page.
 * @return void
 */
function axismundi_contacts_enqueue_card_editor( string $hook ) : void {
	if ( ! str_contains( $hook, 'axismundi-contacts' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing what to load, not writing.
	$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
	if ( 'edit' !== $action ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing what to load, not writing.
	$card_id = isset( $_GET['item'] ) ? absint( $_GET['item'] ) : 0;
	$row     = axismundi_contacts_get_card( $card_id );
	if ( array() === $row || ! axismundi_contacts_can_use_book( (int) $row['owner_actor_id'], get_current_user_id() ) ) {
		return;
	}

	$plugin = dirname( __DIR__ ) . '/axismundi-contacts.php';
	$script = dirname( __DIR__ ) . '/assets/admin/card-editor.js';
	$style  = dirname( __DIR__ ) . '/assets/admin/card-editor.css';
	if ( ! file_exists( $script ) ) {
		return;
	}
	$fields_css = dirname( __DIR__ ) . '/assets/admin/fields.css';
	$fields_js  = dirname( __DIR__ ) . '/assets/admin/fields.js';
	/*
	 * `wp-theme` supplies `--wpds-*`, so a field is the same neutral, focus blue and error red as the
	 * rest of wp-admin. Asked for by handle rather than by URL: WordPress registers it against its own
	 * stylesheet and Gutenberg replaces the source with its build, so a plugin naming either path
	 * breaks the moment the other one is in charge. The fields have M3 baselines behind every token,
	 * so a context that does not enqueue it still draws something usable.
	 */
	wp_enqueue_style(
		'axismundi-contacts-fields',
		plugins_url( 'assets/admin/fields.css', $plugin ),
		wp_style_is( 'wp-theme', 'registered' ) ? array( 'wp-theme' ) : array(),
		AXISMUNDI_CONTACTS_VERSION . '-' . (string) filemtime( $fields_css )
	);
	wp_enqueue_style(
		'axismundi-contacts-card-editor',
		plugins_url( 'assets/admin/card-editor.css', $plugin ),
		array( 'axismundi-contacts-fields' ),
		AXISMUNDI_CONTACTS_VERSION . '-' . (string) filemtime( $style )
	);
	/*
	 * Google's phone number rules, as a browser build. What a national number means -- which leading
	 * zero is a trunk prefix and which is part of the number, where Italy differs from Korea -- is a
	 * table per country that changes, and a plugin that wrote its own would be wrong about somewhere
	 * within a year. MIT, over metadata derived from Google's Apache-2.0 libphonenumber; the version
	 * is recorded beside the file and updated with a release rather than silently.
	 */
	$phone_js = dirname( __DIR__ ) . '/assets/vendor/libphonenumber-js/libphonenumber.min.js';
	wp_enqueue_script(
		'axismundi-contacts-libphonenumber',
		plugins_url( 'assets/vendor/libphonenumber-js/libphonenumber.min.js', $plugin ),
		array(),
		AXISMUNDI_CONTACTS_VERSION . '-' . (string) filemtime( $phone_js ),
		true
	);
	wp_enqueue_script(
		'axismundi-contacts-fields',
		plugins_url( 'assets/admin/fields.js', $plugin ),
		// It draws one string of its own, so it needs the thing that translates one.
		array( 'wp-element', 'wp-i18n' ),
		AXISMUNDI_CONTACTS_VERSION . '-' . (string) filemtime( $fields_js ),
		true
	);
	wp_enqueue_script(
		'axismundi-contacts-card-editor',
		plugins_url( 'assets/admin/card-editor.js', $plugin ),
		array( 'wp-element', 'wp-api-fetch', 'wp-i18n', 'axismundi-contacts-fields', 'axismundi-contacts-libphonenumber' ),
		// Versioned by mtime: a fixed string becomes the `ver=` query and serves yesterday's script
		// from cache after every edit.
		AXISMUNDI_CONTACTS_VERSION . '-' . (string) filemtime( $script ),
		true
	);
	wp_set_script_translations( 'axismundi-contacts-fields', 'axismundi-contacts' );
	wp_set_script_translations( 'axismundi-contacts-card-editor', 'axismundi-contacts' );

	/*
	 * The icons, as markup, once. A button asks for one by name; drawing SVG in the script would put a
	 * second copy of every icon somewhere nobody would think to update.
	 */
	$icons = array();
	foreach ( array_keys( axismundi_contacts_icons() ) as $icon ) {
		$icons[ $icon ] = axismundi_contacts_icon( (string) $icon );
	}
	wp_add_inline_script(
		'axismundi-contacts-fields',
		'window.axismundiContactsIcons = ' . wp_json_encode( $icons ) . ';',
		'before'
	);

	$draft = axismundi_contacts_draft_payload( $row );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing where Done returns to.
	$group = isset( $_GET['group'] ) ? absint( $_GET['group'] ) : 0;
	/*
	 * What a Card describes, and whether it is a question. On the Card an Actor publishes about itself
	 * it is not: that Actor is a Person, a Group or an Organization in a registry that federates, and a
	 * Card claiming otherwise would say one thing to a reader and the Actor document another.
	 */
	$locked = null;
	if ( axismundi_contacts_is_profile_card( $row ) && function_exists( 'axismundi_actors_jscontact_kind' ) ) {
		$actor  = axismundi_actors_get_by_identity( (int) $row['owner_actor_id'] );
		$locked = $actor instanceof Axismundi_Actor
			? ( axismundi_actors_jscontact_kind( (string) $actor->get_type() ) ?: null )
			: null;
	}
	/*
	 * The languages every authoring surface on this site offers, which is one list rather than one
	 * per plugin: a Note's language, an Actor's `nameMap` and a Card all name the same languages, and
	 * a second list invented here would be a second answer to a question already answered -- and the
	 * one somebody notices when the two disagree.
	 *
	 * Whatever the Card already says is added to it. A tag nobody listed is still the tag this Card
	 * is written in, and a picker that dropped it would offer somebody every language except theirs.
	 */
	$languages = function_exists( 'axismundi_actors_profile_language_options' )
		? axismundi_actors_profile_language_options( axismundi_contacts_card_languages( $draft['card'] ) )
		: array();
	$language_options = array();
	foreach ( $languages as $tag => $label ) {
		$language_options[] = array( 'value' => (string) $tag, 'label' => (string) $label );
	}
	$config = array(
		'draftPath'           => '/' . axismundi_contacts_rest_namespace() . '/cards/' . $card_id . '/draft',
		'languages'           => $language_options,
		/*
		 * Core owns the human-facing timezone names and regional groups. Its ordinary picker also
		 * has fixed UTC offsets, which are deliberately filtered by the helper above: an Address
		 * stores an IANA place name, never a snapshot offset.
		 */
		'timeZoneOptions'     => axismundi_contacts_timezone_choice(),
		/*
		 * The order a Card is written down in, handed to the screen rather than written there again.
		 * The draft is the document: a property assigned to a JavaScript object lands at the end of
		 * it, so `language` and `organizations` sat below `localizations` until a save moved them --
		 * and the JSON somebody is reading while they type is the one that looked wrong.
		 */
		'order'               => axismundi_contacts_canonical_order(),
		/*
		 * The labels a row offers and what each of them stores. Served rather than written again in
		 * the script: the same table decides what a stored entry reads back as, and two copies of it
		 * would eventually disagree about what `Main` means.
		 */
		'presets'             => array(
			'phones' => axismundi_contacts_preset_options( 'phones' ),
			'emails' => axismundi_contacts_preset_options( 'emails' ),
			'addresses' => axismundi_contacts_preset_options( 'addresses' ),
		),
		/*
		 * Where to read a phone number typed without a country. A hint and never a stored fact: the
		 * card keeps `tel:+82…`, which says the country itself, and a second field saying `KR` beside
		 * it would be the same answer twice with nothing keeping the two in step.
		 */
		'region'              => axismundi_contacts_default_region( $draft['card'] ),
		/*
		 * The countries an address may be in, from the plugin whose subject that is. It was being
		 * taken from the phone number rules, which is a list of places with a numbering plan -- so
		 * Antarctica, Pitcairn and the French Southern Territories were missing from a list of places
		 * somebody can live, and removing the phone library would have removed the address country
		 * picker with it. The phone row still asks the phone rules, because which countries have
		 * numbering plans is exactly what they know.
		 *
		 * Empty when GeoData is not installed. The field takes what is typed either way, so an
		 * address is still writable -- it just stops suggesting.
		 */
		'countries'           => function_exists( 'axismundi_geodata_country_options' )
			? axismundi_geodata_country_options()
			: array(),
		/*
		 * Every kind RFC 9553 registers, because each is something somebody keeps the address of. A
		 * card for a building, for the software that runs a service, for a machine that reports its
		 * own readings: an address book that could not say which would be asking people to file all
		 * three as people.
		 *
		 * The words are the standard's. `org` and `location` are what a Card stores whatever a screen
		 * calls them, and a reader elsewhere is matching on those rather than on `An organisation`.
		 */
		'kinds'               => array(
			array( 'value' => 'individual', 'label' => __( 'A person', 'axismundi-contacts' ) ),
			array( 'value' => 'org', 'label' => __( 'An organisation', 'axismundi-contacts' ) ),
			array( 'value' => 'group', 'label' => __( 'A group', 'axismundi-contacts' ) ),
			array( 'value' => 'location', 'label' => __( 'A place', 'axismundi-contacts' ) ),
			array( 'value' => 'application', 'label' => __( 'An application', 'axismundi-contacts' ) ),
			array( 'value' => 'device', 'label' => __( 'A device', 'axismundi-contacts' ) ),
		),
		'lockedKind'          => $locked,
		'card'                => (object) $draft['card'],
		'revision'            => (int) $draft['revision'],
		'isProfile'           => array_key_exists( 'publishedPointers', $draft ),
		'published'           => (array) ( $draft['publishedPointers'] ?? array() ),
		'publishableSingular' => AXISMUNDI_CONTACTS_PUBLISHABLE_SINGULAR,
		'publishableEntries'  => AXISMUNDI_CONTACTS_PUBLISHABLE_ENTRIES,
		'backUrl'             => axismundi_contacts_screen_url( $card_id, $group ),
	);
	/*
	 * `wp_add_inline_script()` rather than `wp_localize_script()`, which casts every value to a
	 * string. A revision that arrived as "4" would be compared against a number and a Card's booleans
	 * would all become "1", which is the shape of bug that is found weeks later in one field.
	 */
	wp_add_inline_script(
		'axismundi-contacts-card-editor',
		'window.axismundiContactsCardEditor = ' . wp_json_encode( $config ) . ';',
		'before'
	);
}
add_action( 'admin_enqueue_scripts', 'axismundi_contacts_enqueue_card_editor' );

/**
 * The screen the editor mounts into.
 *
 * Deliberately almost nothing. A heading, a link back to the record, and an element -- a card drawn
 * here in PHP as well would be a second rendering of the same document, and the two would disagree
 * the first time one of them was changed.
 *
 * @param int $card_id  Card being edited.
 * @param int $group_id Group being browsed, so Done returns to it.
 * @return void
 */
function axismundi_contacts_card_editor_screen( int $card_id, int $group_id ) : void {
	$row = axismundi_contacts_get_card( $card_id );
	if ( array() === $row ) {
		echo '<h1>' . esc_html__( 'Edit contact', 'axismundi-contacts' ) . '</h1>';
		echo '<p>' . esc_html__( 'That contact does not exist.', 'axismundi-contacts' ) . '</p>';
		return;
	}
	$card = axismundi_contacts_card_document( $card_id );
	$name = trim( axismundi_contacts_name_text( is_array( $card['name'] ?? null ) ? $card['name'] : array() ) );
	?>
	<p><a href="<?php echo esc_url( axismundi_contacts_screen_url( $card_id, $group_id ) ); ?>">&larr; <?php esc_html_e( 'Back to the contact', 'axismundi-contacts' ); ?></a></p>
	<h1><?php echo esc_html( '' !== $name ? $name : __( '(no name)', 'axismundi-contacts' ) ); ?></h1>
	<?php if ( axismundi_contacts_is_profile_card( $row ) ) : ?>
		<p class="description">
			<?php esc_html_e( 'This is the card this Actor publishes about itself. Who may read it is decided on My profile; what of it they receive is decided below.', 'axismundi-contacts' ); ?>
		</p>
	<?php endif; ?>
	<div id="ax-contacts-card-editor" class="ax-contacts-fields"></div>
	<noscript>
		<p><?php esc_html_e( 'The card editor needs JavaScript. The contact itself is readable without it.', 'axismundi-contacts' ); ?></p>
	</noscript>
	<?php
}
