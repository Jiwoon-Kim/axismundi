<?php
/**
 * What a Card says to strangers, chosen rather than inherited.
 *
 * A Card is one document holding everything somebody wrote down about a person: a mobile number, a
 * home address, a private email, and notes that are frequently about somebody else. Publishing it
 * because its owner turned sharing on would hand all of that to anybody who asked, and would do it
 * on the strength of a setting that reads like "let people see my profile".
 *
 * So sharing says *that* a Card is published, and this says *what*. Nothing is published unless it
 * was named, one entry at a time.
 *
 * `contexts.private` is not the boundary and is not used as one. RFC 9553 defines contexts as where
 * a value is meant to be used -- somebody's work phone is `contexts.work`, and it is not thereby
 * public. A field carrying no context at all is not public either; it is a field nobody classified.
 * Reading either as permission would be inferring consent from a filing decision.
 *
 * The default is the narrowest thing that is still a contact card: the name, what kind of entity it
 * is, and the accounts and links that are already public elsewhere. Everything else starts
 * unpublished, including on Cards that existed before this did -- a Card published yesterday under
 * a rule of "all of it" does not get to keep publishing all of it because it was there first.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Properties that may be published whole, because every part of them is the same fact.
 *
 * A name is a name; there is no half of `kind` to withhold. Splitting these per entry would offer a
 * choice nobody has.
 */
const AXISMUNDI_CONTACTS_PUBLISHABLE_SINGULAR = array(
	'name',
	'kind',
	'language',
	'speakToAs',
	'preferredLanguages',
);

/**
 * Properties whose entries are chosen one at a time.
 *
 * Every one of these is a map of separately-authored things: four email addresses of which one is
 * for strangers, three notes of which two are about other people. A property-level switch here
 * would mean publishing the private ones to publish the public one.
 */
const AXISMUNDI_CONTACTS_PUBLISHABLE_ENTRIES = array(
	'emails',
	'phones',
	'addresses',
	'onlineServices',
	'links',
	'media',
	'organizations',
	'titles',
	'calendars',
	'schedulingAddresses',
	'keywords',
	'personalInfo',
	'anniversaries',
	'notes',
);

/**
 * What a Card publishes when nobody has said.
 *
 * The name, and what kind of thing this is. Not an email address, not a link, not a photo: those are
 * all reasonable things to publish and all things somebody should have said yes to.
 *
 * @return string[] Pointers.
 */
function axismundi_contacts_default_published() : array {
	return array( 'name', 'kind', 'language' );
}

/**
 * Whether a pointer names something that may be published at all.
 *
 * `name`, or `emails/e1`. Anything else -- a property this does not know, a path reaching inside an
 * entry -- is refused rather than stored, because a pointer that is not understood here is a
 * pointer that would be silently ignored later, and a person would have said yes to something that
 * never happened.
 *
 * @param string $pointer Pointer.
 * @return bool
 */
function axismundi_contacts_is_publishable_pointer( string $pointer ) : bool {
	if ( in_array( $pointer, AXISMUNDI_CONTACTS_PUBLISHABLE_SINGULAR, true ) ) {
		return true;
	}
	$parts = explode( '/', $pointer );
	if ( 2 !== count( $parts ) ) {
		return false;
	}
	return in_array( $parts[0], AXISMUNDI_CONTACTS_PUBLISHABLE_ENTRIES, true ) && '' !== $parts[1];
}

/** @return string The table holding what each Actor publishes. */
function axismundi_contacts_published_column() : string {
	return 'published_json';
}

/**
 * What this Actor has said may be published.
 *
 * @param int $actor_id Actor identity.
 * @return string[] Pointers.
 */
function axismundi_contacts_published_pointers( int $actor_id ) : array {
	global $wpdb;
	if ( $actor_id <= 0 ) {
		return array();
	}
	$table = axismundi_contacts_profiles_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$stored = $wpdb->get_var( $wpdb->prepare( "SELECT published_json FROM {$table} WHERE actor_id = %d", $actor_id ) );
	if ( null === $stored || '' === (string) $stored ) {
		return axismundi_contacts_default_published();
	}
	$decoded = json_decode( (string) $stored, true );
	if ( ! is_array( $decoded ) ) {
		return axismundi_contacts_default_published();
	}
	return array_values( array_filter( array_map( 'strval', $decoded ), 'axismundi_contacts_is_publishable_pointer' ) );
}

/**
 * Record what may be published.
 *
 * Pointers this does not understand are dropped rather than stored. A stored pointer that nothing
 * acts on is a person believing they published something they did not.
 *
 * @param int      $actor_id Actor identity.
 * @param string[] $pointers Pointers.
 * @return true|WP_Error
 */
function axismundi_contacts_set_published_pointers( int $actor_id, array $pointers ) {
	global $wpdb;
	if ( $actor_id <= 0 || axismundi_contacts_profile_card( $actor_id ) <= 0 ) {
		return new WP_Error( 'ax_contacts_published_none', __( 'That Actor publishes no contact card.', 'axismundi-contacts' ), array( 'status' => 404 ) );
	}
	$clean = array_values( array_unique( array_filter( array_map( 'strval', $pointers ), 'axismundi_contacts_is_publishable_pointer' ) ) );
	$table = axismundi_contacts_profiles_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update(
		$table,
		array( 'published_json' => (string) wp_json_encode( $clean ), 'updated_at' => current_time( 'mysql', true ) ),
		array( 'actor_id' => $actor_id ),
		array( '%s', '%s' ),
		array( '%d' )
	);
	return true;
}

/**
 * Cut one Card down to what was published.
 *
 * Built by taking, never by removing. A projection written as "the Card, minus these" publishes
 * every property somebody adds later and every property a future revision of the standard invents,
 * because neither was on the list of things to take out. Taking only what was asked for fails the
 * other way, which is the way to fail.
 *
 * `uid` and `@type` come along because they are what makes this a Card rather than a fragment, and
 * `version` because a reader needs to know what it is reading. None of the three says anything
 * about the person.
 *
 * @param array<string,mixed> $card      Stored Card.
 * @param string[]            $published Pointers.
 * @return array<string,mixed>
 */
function axismundi_contacts_public_projection( array $card, array $published ) : array {
	$out = array( '@type' => 'Card', 'version' => '2.0' );
	if ( isset( $card['uid'] ) ) {
		$out['uid'] = $card['uid'];
	}
	foreach ( $published as $pointer ) {
		if ( in_array( $pointer, AXISMUNDI_CONTACTS_PUBLISHABLE_SINGULAR, true ) ) {
			if ( isset( $card[ $pointer ] ) ) {
				$out[ $pointer ] = $card[ $pointer ];
			}
			continue;
		}
		$parts = explode( '/', $pointer );
		if ( 2 !== count( $parts ) ) {
			continue;
		}
		list( $property, $key ) = $parts;
		if ( ! in_array( $property, AXISMUNDI_CONTACTS_PUBLISHABLE_ENTRIES, true ) ) {
			continue;
		}
		if ( ! isset( $card[ $property ] ) || ! is_array( $card[ $property ] ) || ! array_key_exists( $key, $card[ $property ] ) ) {
			continue;
		}
		if ( ! isset( $out[ $property ] ) ) {
			$out[ $property ] = array();
		}
		$out[ $property ][ $key ] = $card[ $property ][ $key ];
	}
	$out = axismundi_contacts_project_localizations( $card, $out );
	return $out;
}

/**
 * Carry over only the localizations of what survived.
 *
 * A localization is a translation of a value, so it is exactly as public as the value it translates
 * and no more. A Card that withheld a home address and published its English rendering would have
 * withheld nothing -- and the patch form makes that easy to miss, because `addresses/home/components`
 * does not look like an address until it is applied to one.
 *
 * @param array<string,mixed> $card Stored Card.
 * @param array<string,mixed> $out  Projection so far.
 * @return array<string,mixed>
 */
function axismundi_contacts_project_localizations( array $card, array $out ) : array {
	$localizations = (array) ( $card['localizations'] ?? array() );
	$kept          = array();
	foreach ( $localizations as $tag => $patch ) {
		if ( ! is_array( $patch ) ) {
			continue;
		}
		$surviving = array();
		foreach ( $patch as $path => $value ) {
			$path    = (string) $path;
			$segments = explode( '/', $path );
			$property = (string) ( $segments[0] ?? '' );
			if ( in_array( $property, AXISMUNDI_CONTACTS_PUBLISHABLE_SINGULAR, true ) ) {
				// A whole-property localization is as public as that property.
				if ( isset( $out[ $property ] ) ) {
					$surviving[ $path ] = $value;
				}
				continue;
			}
			$key = (string) ( $segments[1] ?? '' );
			if ( '' === $key || ! isset( $out[ $property ] ) || ! is_array( $out[ $property ] ) ) {
				continue;
			}
			if ( array_key_exists( $key, $out[ $property ] ) ) {
				$surviving[ $path ] = $value;
			}
		}
		if ( array() !== $surviving ) {
			$kept[ (string) $tag ] = $surviving;
		}
	}
	if ( array() !== $kept ) {
		$out['localizations'] = $kept;
	}
	return $out;
}

/**
 * Which parts of this Card a stranger may have.
 *
 * One tick per value, because that is the granularity the question actually has. Somebody with four
 * email addresses has one they hand out and three they do not, and a switch that said "publish my
 * email addresses" would make publishing the first mean publishing the other three.
 *
 * Not derived from anything on the values themselves. A `private` context says where a value is
 * meant to be used, not who may see it, and a value with no context is one nobody classified rather
 * than one anybody may have. Reading either as an answer would be this screen deciding on somebody's
 * behalf and then showing them a page that looked like they had decided.
 *
 * A tick stores the entry's own id -- `emails/e1`, `media/avatar` -- and never the text beside it or
 * the row it was on. Somebody correcting a typo in an address, reordering their links or translating
 * a label has not changed which value they published, and consent that travelled with a display
 * string would move to a different value the first time either changed.
 *
 * This form is the transitional one. The editor that replaces it reads and writes the same
 * `published_json` through the same two functions above: a second writer of the public policy would
 * be two answers to one question, which is the shape of the bug this whole boundary exists to
 * prevent.
 *
 * @param int                 $actor_id Acting Actor.
 * @param array<string,mixed> $card     Stored Card.
 * @return void
 */
function axismundi_contacts_publish_fields( int $actor_id, array $card ) : void {
	$published = axismundi_contacts_published_pointers( $actor_id );
	?>
	<h2><?php esc_html_e( 'Published', 'axismundi-contacts' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Sharing decides whether this card is published at all. This decides what of it. Nothing here is published because it is written above.', 'axismundi-contacts' ); ?>
	</p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Always asked', 'axismundi-contacts' ); ?></th>
			<td>
				<?php foreach ( AXISMUNDI_CONTACTS_PUBLISHABLE_SINGULAR as $ax_ct_property ) : ?>
					<p>
						<label>
							<input type="checkbox" name="published[]" value="<?php echo esc_attr( $ax_ct_property ); ?>" <?php checked( in_array( $ax_ct_property, $published, true ) ); ?>>
							<?php echo esc_html( axismundi_contacts_publish_label( $ax_ct_property ) ); ?>
						</label>
					</p>
				<?php endforeach; ?>
			</td>
		</tr>
		<?php foreach ( axismundi_contacts_detail_sections() as $ax_ct_property => $ax_ct_label ) : ?>
			<?php
			$ax_ct_entries = (array) ( $card[ $ax_ct_property ] ?? array() );
			if ( array() === $ax_ct_entries || ! in_array( $ax_ct_property, AXISMUNDI_CONTACTS_PUBLISHABLE_ENTRIES, true ) ) {
				continue;
			}
			?>
			<tr>
				<th scope="row"><?php echo esc_html( $ax_ct_label ); ?></th>
				<td>
					<?php foreach ( $ax_ct_entries as $ax_ct_key => $ax_ct_entry ) : ?>
						<?php
						if ( ! is_array( $ax_ct_entry ) ) {
							continue;
						}
						$ax_ct_pointer = $ax_ct_property . '/' . $ax_ct_key;
						$ax_ct_text    = axismundi_contacts_entry_text( $ax_ct_entry, $ax_ct_property );
						?>
						<p>
							<label>
								<input type="checkbox" name="published[]" value="<?php echo esc_attr( $ax_ct_pointer ); ?>" <?php checked( in_array( $ax_ct_pointer, $published, true ) ); ?>>
								<?php echo esc_html( '' !== trim( $ax_ct_text ) ? $ax_ct_text : $ax_ct_key ); ?>
							</label>
						</p>
					<?php endforeach; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
	<?php
}

/**
 * What a whole-property tick is called on screen.
 *
 * @param string $property Property.
 * @return string
 */
function axismundi_contacts_publish_label( string $property ) : string {
	switch ( $property ) {
		case 'name':
			return __( 'Name', 'axismundi-contacts' );
		case 'kind':
			return __( 'What kind of entity this is', 'axismundi-contacts' );
		case 'language':
			return __( 'Preferred language of this card', 'axismundi-contacts' );
		case 'speakToAs':
			return __( 'Pronouns and how to address me', 'axismundi-contacts' );
		case 'preferredLanguages':
			return __( 'Languages I prefer to be written to in', 'axismundi-contacts' );
		default:
			return $property;
	}
}
