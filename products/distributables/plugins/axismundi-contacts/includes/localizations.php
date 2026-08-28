<?php
/**
 * The same name written another way.
 *
 * `김지운`, `Jiwoon Kim`, `金知運` and `Trump` are four different things, and flattening any two of
 * them together loses which is which:
 *
 *   ko-Latn   how the Korean name is written in Latin script
 *   ko-Hani   how it is written in Han characters
 *   en        the name this person actually uses in English, which may be unrelated
 *
 * So each is its own localization, with its own components and its own reading order, and nothing
 * here derives one from another. In particular a localized `full` is never split into components:
 * deciding which half of `Jiwoon Kim` is the surname is a guess, and a guess written into a stored
 * field stops looking like one.
 *
 * Rich data is accepted and simple data is generated. An imported Card carrying `ko-Latn` keeps it
 * exactly; nothing on this side invents a script-specific tag for somebody who only said `ko-KR` and
 * `en-US`. Supporting a vocabulary and designing around it are different decisions.
 *
 * JSContact says localizations as patches: a map of language tag to a PatchObject whose keys are
 * paths into the Card. A key of `name` replaces the whole name, which is what this writes; a key of
 * `name/components/0/phonetic` replaces one value inside it, which is what an import may arrive
 * with. Both are read here, and paths belonging to anything other than the name are left untouched
 * -- they are somebody else's localization of a field this code does not model, and dropping them on
 * the next save would lose it.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a patch key addresses the name.
 *
 * @param string $path Patch key.
 * @return bool
 */
function axismundi_contacts_is_name_path( string $path ) : bool {
	return 'name' === $path || str_starts_with( $path, 'name/' );
}

/**
 * One language's name, read out of whatever shape the patch is in.
 *
 * @param array<string,mixed> $card Card document.
 * @param string              $tag  Language tag.
 * @return array<string,mixed> Name object, or an empty array when that language has no name.
 */
function axismundi_contacts_localized_name( array $card, string $tag ) : array {
	$patch = (array) ( $card['localizations'][ $tag ] ?? array() );
	if ( array() === $patch ) {
		return array();
	}
	// The whole-property form, which is what this writes.
	$name = is_array( $patch['name'] ?? null ) ? $patch['name'] : array();
	/*
	 * Then anything addressed more finely, applied over it. An import may localize a single component
	 * rather than the name -- `name/components/0/phonetic` is the shape RFC 9553's own example uses --
	 * and reading only the coarse form would show that Card as having no localized name at all.
	 */
	foreach ( $patch as $path => $value ) {
		$path = (string) $path;
		if ( 'name' === $path || ! axismundi_contacts_is_name_path( $path ) ) {
			continue;
		}
		$name = axismundi_contacts_apply_patch_path( $name, explode( '/', substr( $path, 5 ) ), $value );
	}
	return $name;
}

/**
 * Set one value inside a name, by the path segments that address it.
 *
 * @param array<string,mixed> $name     Name so far.
 * @param string[]            $segments Remaining path segments.
 * @param mixed               $value    Value to set.
 * @return array<string,mixed>
 */
function axismundi_contacts_apply_patch_path( array $name, array $segments, $value ) : array {
	$key = (string) array_shift( $segments );
	if ( '' === $key ) {
		return $name;
	}
	// A numeric segment addresses a position in a list, which is how components are reached.
	$index = ctype_digit( $key ) ? (int) $key : null;
	$slot  = null === $index ? $key : $index;
	if ( array() === $segments ) {
		$name[ $slot ] = $value;
		return $name;
	}
	$child         = is_array( $name[ $slot ] ?? null ) ? $name[ $slot ] : array();
	$name[ $slot ] = axismundi_contacts_apply_patch_path( $child, $segments, $value );
	return $name;
}

/**
 * Write one language's name, leaving that language's other localizations alone.
 *
 * @param array<string,mixed> $card Card document.
 * @param string              $tag  Language tag.
 * @param array<string,mixed> $name Name object, or an empty array to remove it.
 * @return array<string,mixed> Card document.
 */
function axismundi_contacts_set_localized_name( array $card, string $tag, array $name ) : array {
	$tag = trim( $tag );
	if ( '' === $tag ) {
		return $card;
	}
	$patch = (array) ( $card['localizations'][ $tag ] ?? array() );
	// Every path that was about the name goes, coarse or fine; everything else is somebody else's.
	foreach ( array_keys( $patch ) as $path ) {
		if ( axismundi_contacts_is_name_path( (string) $path ) ) {
			unset( $patch[ $path ] );
		}
	}
	if ( array() !== $name ) {
		$patch['name'] = array_merge( array( '@type' => 'Name' ), $name );
	}
	if ( array() === $patch ) {
		/*
		 * A tag with nothing under it is removed rather than left empty, because absence is what says
		 * `this Card has no name of its own in that language` -- and that is the question the renderer
		 * asks before letting another domain contribute one. An empty patch would answer it wrongly.
		 */
		unset( $card['localizations'][ $tag ] );
		if ( array() === (array) ( $card['localizations'] ?? array() ) ) {
			unset( $card['localizations'] );
		}
		return $card;
	}
	$card['localizations'][ $tag ] = $patch;
	return $card;
}

/**
 * The language tags this Card has a name of its own in.
 *
 * @param array<string,mixed> $card Card document.
 * @return string[]
 */
function axismundi_contacts_localized_name_tags( array $card ) : array {
	$tags = array();
	foreach ( (array) ( $card['localizations'] ?? array() ) as $tag => $patch ) {
		foreach ( array_keys( (array) $patch ) as $path ) {
			if ( axismundi_contacts_is_name_path( (string) $path ) ) {
				$tags[] = (string) $tag;
				break;
			}
		}
	}
	return $tags;
}

/**
 * Whether one language's patch is a PatchObject that can be applied.
 *
 * A localization is a set of paths into the Card and the values to put there. What makes one valid
 * is structural and has nothing to do with where the document came from: the same patch is valid or
 * invalid whether somebody typed it or an import brought it, because otherwise a Card that arrived
 * could not be read out and written back unchanged.
 *
 * The rules RFC 9553 states for a PatchObject, and no more:
 *
 *   every path but the last token must already exist, because a patch replaces a value inside
 *   something rather than conjuring the thing that holds it
 *
 *   `-` is never an array index here; it means "append" in JSON Patch and a PatchObject has no
 *   such thing
 *
 *   no path may be a prefix of another, because two patches to the same place have no defined order
 *
 *   nothing may patch `localizations`, which would be a document describing its own translations
 *
 * The last token is deliberately not required to exist. A patch may set a property the base Card
 * does not have -- the standard says a localization SHOULD NOT do that, which is advice to whoever
 * writes one rather than a reason to refuse a document that does. The screens follow the advice by
 * offering only paths that are already there; this refuses only what cannot be applied at all.
 *
 * @param array<string,mixed> $card  The Card the patch applies to.
 * @param string              $tag   Language tag, for the message.
 * @param array<string,mixed> $patch Paths and the values to put there.
 * @return true|WP_Error
 */
function axismundi_contacts_validate_patch( array $card, string $tag, array $patch ) {
	$paths = array_map( 'strval', array_keys( $patch ) );
	foreach ( $paths as $path ) {
		$segments = explode( '/', $path );
		if ( '' === $path || in_array( '', $segments, true ) ) {
			return axismundi_contacts_patch_error( $tag, $path, __( 'A path in a localization names a value, one step at a time.', 'axismundi-contacts' ) );
		}
		if ( in_array( '-', $segments, true ) ) {
			// `-` is JSON Patch's "after the last item". A PatchObject replaces values that are there.
			return axismundi_contacts_patch_error( $tag, $path, __( 'A localization patches values that exist; it cannot append to a list.', 'axismundi-contacts' ) );
		}
		if ( 'localizations' === $segments[0] ) {
			return axismundi_contacts_patch_error( $tag, $path, __( 'A localization cannot patch the localizations.', 'axismundi-contacts' ) );
		}
		/*
		 * Two patches to the same place have no order between them, so a document that carried both
		 * would mean different things depending on which was read second.
		 */
		foreach ( $paths as $other ) {
			if ( $other !== $path && 0 === strpos( $other, $path . '/' ) ) {
				return axismundi_contacts_patch_error(
					$tag,
					$path,
					/* translators: %s: the other path. */
					sprintf( __( 'This patches the same value as %s, and there is no order between them.', 'axismundi-contacts' ), $other )
				);
			}
		}
		$walked = axismundi_contacts_walk_patch_path( $card, array_slice( $segments, 0, -1 ) );
		if ( is_wp_error( $walked ) ) {
			return axismundi_contacts_patch_error( $tag, $path, $walked->get_error_message() );
		}
	}
	/*
	 * And what it leaves behind is asked of the document rather than of the change. Whether a patch is
	 * allowed to remove something depends on what would be left -- a `label` may go and a `kind` may
	 * not -- and no amount of reading the path alone answers that.
	 */
	$result = axismundi_contacts_validate_card_values( axismundi_contacts_apply_patch( $card, $patch ) );
	if ( is_wp_error( $result ) ) {
		return axismundi_contacts_patch_error( $tag, implode( ', ', $paths ), $result->get_error_message() );
	}
	return true;
}

/**
 * Follow the part of a path that has to be there already.
 *
 * @param mixed    $value    The Card, then whatever is inside it.
 * @param string[] $segments Path without its last token.
 * @return true|WP_Error
 */
function axismundi_contacts_walk_patch_path( $value, array $segments ) {
	foreach ( $segments as $segment ) {
		if ( ! is_array( $value ) ) {
			return new WP_Error( 'ax_contacts_patch_step', __( 'That path goes inside a value that has nothing inside it.', 'axismundi-contacts' ) );
		}
		if ( axismundi_contacts_is_list( $value ) ) {
			// A list is addressed by position, and a position outside it is not a place.
			if ( 1 !== preg_match( '/^(0|[1-9][0-9]*)$/', $segment ) || (int) $segment >= count( $value ) ) {
				return new WP_Error( 'ax_contacts_patch_index', __( 'That path names a position that is not in the list.', 'axismundi-contacts' ) );
			}
			$value = $value[ (int) $segment ];
			continue;
		}
		if ( ! array_key_exists( $segment, $value ) ) {
			return new WP_Error( 'ax_contacts_patch_missing', __( 'That path goes through something the card does not have.', 'axismundi-contacts' ) );
		}
		$value = $value[ $segment ];
	}
	return true;
}

/**
 * One refusal, saying which language and which path.
 *
 * @param string $tag     Language tag.
 * @param string $path    Path.
 * @param string $message What is wrong with it.
 * @return WP_Error
 */
function axismundi_contacts_patch_error( string $tag, string $path, string $message ) : WP_Error {
	return new WP_Error(
		'ax_contacts_draft_patch_path',
		sprintf(
			/* translators: 1: language tag, 2: path inside the card, 3: what is wrong. */
			__( 'The localization for %1$s cannot patch %2$s: %3$s', 'axismundi-contacts' ),
			$tag,
			$path,
			$message
		),
		array( 'status' => 400 )
	);
}

/**
 * One language tag, written the way this site writes them.
 *
 * Not this plugin's rule. An Actor's `nameMap`, a Note's language and a Card all name languages, and
 * three plugins each normalising tags their own way is three spellings of Korean-in-Latin-letters in
 * one database -- `ko-latn` here, `ko-LATN` there -- which nothing downstream can match up.
 *
 * Left exactly as it arrived when the registry is not there to ask. A second copy of the rule would
 * be the thing this avoids, and a tag written as somebody typed it is at least honest about where it
 * came from.
 *
 * @param string $tag Language tag.
 * @return string Normalized tag, or the tag as given when there is nothing to normalize it with.
 */
function axismundi_contacts_language_tag( string $tag ) : string {
	if ( ! function_exists( 'axismundi_actors_normalize_language_tag' ) ) {
		return trim( $tag );
	}
	$normalized = axismundi_actors_normalize_language_tag( $tag );
	// An unparseable tag is returned as it was: refusing a Card because of one is not this to decide.
	return '' !== $normalized ? $normalized : trim( $tag );
}

/**
 * What a Card calls the organization it names first.
 *
 * The first one with a name, in the order the document holds them. Not a fixed key: an entry id is
 * an address a screen chose, and a lookup for one particular word was a lookup that worked for
 * exactly the cards one importer happened to write -- everything else appeared in the list as
 * nothing at all.
 *
 * @param array<string,mixed> $card Card.
 * @return string
 */
function axismundi_contacts_first_organization_name( array $card ) : string {
	foreach ( (array) ( $card['organizations'] ?? array() ) as $entry ) {
		$name = is_array( $entry ) ? trim( (string) ( $entry['name'] ?? '' ) ) : '';
		if ( '' !== $name ) {
			return $name;
		}
	}
	return '';
}

/**
 * Every language a Card names, so a picker can offer them back.
 *
 * What it is written in, what its subject would rather receive, and which languages it carries
 * translations for. A tag nobody listed is still the tag this Card uses, and a picker that dropped
 * it would offer somebody every language except theirs.
 *
 * @param array<string,mixed> $card Card.
 * @return string[]
 */
function axismundi_contacts_card_languages( array $card ) : array {
	$tags = array();
	if ( isset( $card['language'] ) && is_string( $card['language'] ) ) {
		$tags[] = $card['language'];
	}
	foreach ( (array) ( $card['preferredLanguages'] ?? array() ) as $entry ) {
		if ( is_array( $entry ) && isset( $entry['language'] ) && is_string( $entry['language'] ) ) {
			$tags[] = $entry['language'];
		}
	}
	foreach ( array_keys( (array) ( $card['localizations'] ?? array() ) ) as $tag ) {
		$tags[] = (string) $tag;
	}
	return array_values( array_unique( array_filter( $tags ) ) );
}

/**
 * A Card with its language tags written the way this site writes them.
 *
 * Every place a Card names a language: what it is written in, what its subject would rather receive,
 * and which language each set of patches is for. Two tags that normalize to the same thing are left
 * alone -- that Card already says one language twice, and merging the two would be this code
 * deciding which of them somebody meant.
 *
 * @param array<string,mixed> $card Card.
 * @return array<string,mixed>
 */
function axismundi_contacts_normalize_languages( array $card ) : array {
	if ( isset( $card['language'] ) && is_string( $card['language'] ) ) {
		$card['language'] = axismundi_contacts_language_tag( $card['language'] );
	}
	foreach ( (array) ( $card['preferredLanguages'] ?? array() ) as $id => $entry ) {
		if ( is_array( $entry ) && isset( $entry['language'] ) && is_string( $entry['language'] ) ) {
			$card['preferredLanguages'][ $id ]['language'] = axismundi_contacts_language_tag( $entry['language'] );
		}
	}
	$localizations = (array) ( $card['localizations'] ?? array() );
	if ( array() !== $localizations ) {
		$written = array();
		foreach ( $localizations as $tag => $patch ) {
			$tag  = (string) $tag;
			$next = axismundi_contacts_language_tag( $tag );
			$written[ isset( $localizations[ $next ] ) && $next !== $tag ? $tag : $next ] = $patch;
		}
		$card['localizations'] = $written;
	}
	return $card;
}

/**
 * The paths a screen may offer, which is narrower than what is valid.
 *
 * Only values the Card already has. A localization that introduced a property the base Card does not
 * carry is something RFC 9553 says a writer should not do -- so the picker does not offer it, while
 * the validator still accepts a document that arrived with one.
 *
 * @param array<string,mixed> $value  The Card, or something inside it.
 * @param string              $prefix Path so far.
 * @return string[]
 */
function axismundi_contacts_patchable_paths( array $value, string $prefix = '' ) : array {
	$paths = array();
	foreach ( $value as $key => $item ) {
		$key  = (string) $key;
		$path = '' === $prefix ? $key : $prefix . '/' . $key;
		if ( '' === $prefix && in_array( $key, array( '@type', 'version', 'uid', 'created', 'updated', 'prodId', 'localizations' ), true ) ) {
			// What the document is, rather than what it says about somebody. None of it is translated.
			continue;
		}
		$paths[] = $path;
		if ( is_array( $item ) ) {
			$paths = array_merge( $paths, axismundi_contacts_patchable_paths( $item, $path ) );
		}
	}
	return $paths;
}

/**
 * What each known property has to be.
 *
 * Only the properties this code knows. Anything else -- a vendor's own, a revision newer than this
 * file -- passes untouched, because refusing what is merely unrecognised is how an editor becomes
 * the reason somebody's data cannot be stored.
 *
 * `required` is what may not be removed. RFC 9553 lets a patch set a value to `null` to take it
 * away, and that is fine for `label` and meaningless for `kind`: a Media entry with no kind is not
 * a Media entry, and a patch that produced one would have written a document no reader can use.
 *
 * @return array<string,array<string,mixed>> Property => entry shape.
 */
function axismundi_contacts_value_rules() : array {
	$entry = array( 'pref' => 'integer', 'label' => 'string', 'contexts' => 'object' );
	return array(
		'emails'              => array( 'required' => array( 'address' => 'string' ), 'optional' => $entry ),
		'phones'              => array( 'required' => array( 'number' => 'string' ), 'optional' => $entry + array( 'features' => 'object' ) ),
		'onlineServices'      => array( 'required' => array(), 'optional' => $entry + array( 'uri' => 'string', 'user' => 'string', 'service' => 'string' ) ),
		'links'               => array( 'required' => array( 'uri' => 'string' ), 'optional' => $entry + array( 'kind' => 'string' ) ),
		'media'               => array( 'required' => array( 'kind' => 'string', 'uri' => 'string' ), 'optional' => $entry + array( 'mediaType' => 'string' ) ),
		'calendars'           => array( 'required' => array( 'uri' => 'string' ), 'optional' => $entry + array( 'kind' => 'string' ) ),
		'schedulingAddresses' => array( 'required' => array( 'uri' => 'string' ), 'optional' => $entry ),
		'notes'               => array( 'required' => array( 'note' => 'string' ), 'optional' => array( 'created' => 'string', 'author' => 'object' ) ),
		'organizations'       => array( 'required' => array(), 'optional' => $entry + array( 'name' => 'string', 'units' => 'list', 'sortAs' => 'string' ) ),
		'titles'              => array( 'required' => array( 'name' => 'string' ), 'optional' => array( 'kind' => 'string', 'organizationId' => 'string' ) ),
		'anniversaries'       => array( 'required' => array( 'kind' => 'string' ), 'optional' => array( 'place' => 'object' ) ),
		'personalInfo'        => array( 'required' => array( 'value' => 'string' ), 'optional' => array( 'kind' => 'string', 'level' => 'string', 'listAs' => 'integer' ) ),
	);
}

/**
 * Whether one value is the kind of thing it is supposed to be.
 *
 * @param mixed  $value Value.
 * @param string $type  Expected shape.
 * @return bool
 */
function axismundi_contacts_value_is( $value, string $type ) : bool {
	switch ( $type ) {
		case 'string':
			return is_string( $value );
		case 'boolean':
			return is_bool( $value );
		case 'integer':
			return is_int( $value );
		case 'list':
			return is_array( $value ) && axismundi_contacts_is_list( $value );
		case 'object':
			/*
			 * An empty one counts. JSON's `{}` and `[]` both arrive here as an empty PHP array and
			 * nothing distinguishes them afterwards, so refusing it would refuse `"emails": {}` and
			 * every other property somebody opened and did not fill in -- a document that says the
			 * same thing as one that never mentioned the property, and that the store drops on the way
			 * in rather than rejecting at the door.
			 */
			return is_array( $value ) && ( array() === $value || ! axismundi_contacts_is_list( $value ) );
		default:
			return true;
	}
}

/**
 * Whether a Card says each thing it knows about in the shape that thing has.
 *
 * Checked on the document that would result rather than on the change that produces it, because
 * that is the question: a patch is valid when what it leaves behind is a Card, and reasoning about
 * the patch alone cannot tell whether removing a value took a required one away.
 *
 * @param array<string,mixed> $card Card.
 * @return true|WP_Error
 */
function axismundi_contacts_validate_card_values( array $card ) {
	/*
	 * What the Card describes. The store states `individual` on save for anything that says nothing,
	 * so a stored Card always carries one -- and a patch that emptied it would leave a document whose
	 * readers each have to remember the default again.
	 */
	if ( array_key_exists( 'kind', $card ) && ( ! is_string( $card['kind'] ) || '' === trim( (string) $card['kind'] ) ) ) {
		return axismundi_contacts_value_error( 'kind', __( 'what a card describes is a word, such as individual', 'axismundi-contacts' ) );
	}
	$name = $card['name'] ?? null;
	if ( null !== $name ) {
		if ( ! axismundi_contacts_value_is( $name, 'object' ) ) {
			return axismundi_contacts_value_error( 'name', __( 'a name is an object', 'axismundi-contacts' ) );
		}
		foreach (
			array(
				'full'             => 'string',
				'defaultSeparator' => 'string',
				'isOrdered'        => 'boolean',
				'components'       => 'list',
				'phoneticSystem'   => 'string',
				'phoneticScript'   => 'string',
			) as $key => $type
		) {
			if ( array_key_exists( $key, $name ) && ! axismundi_contacts_value_is( $name[ $key ], $type ) ) {
				/* translators: %s: the shape the value should have. */
				return axismundi_contacts_value_error( 'name/' . $key, sprintf( __( 'it is %s', 'axismundi-contacts' ), $type ) );
			}
		}
		/*
		 * How a name files. The values are free -- RFC 9553's own example files a surname of
		 * `Shou Chang` under `Pau Shou Chang`, because a sort key is what a directory should compare
		 * and not a copy of what the name says.
		 *
		 * The keys are not free. Each one names a component kind, and a card sorting by a `given` it
		 * does not have is telling a directory to file it under something nobody wrote down.
		 */
		if ( array_key_exists( 'sortAs', $name ) ) {
			if ( ! array_key_exists( 'components', $name ) ) {
				// There is nothing to file by. The standard says as much, and so does the question:
				// a sort key names a part of the name, and this name has no parts.
				return axismundi_contacts_value_error( 'name/sortAs', __( 'a name is filed by its parts, and this one has none', 'axismundi-contacts' ) );
			}
			if ( ! axismundi_contacts_value_is( $name['sortAs'], 'object' ) ) {
				/* translators: %s: the shape the value should have. */
				return axismundi_contacts_value_error( 'name/sortAs', sprintf( __( 'it is %s', 'axismundi-contacts' ), 'object' ) );
			}
			$kinds = array();
			foreach ( (array) ( $name['components'] ?? array() ) as $component ) {
				if ( is_array( $component ) && isset( $component['kind'] ) && is_string( $component['kind'] ) ) {
					$kinds[] = $component['kind'];
				}
			}
			foreach ( (array) $name['sortAs'] as $kind => $sort_value ) {
				if ( ! is_string( $sort_value ) ) {
					return axismundi_contacts_value_error( 'name/sortAs/' . $kind, __( 'a sort key is text', 'axismundi-contacts' ) );
				}
				if ( ! in_array( (string) $kind, $kinds, true ) ) {
					return axismundi_contacts_value_error(
						'name/sortAs/' . $kind,
						__( 'a name can only be filed by a part it has', 'axismundi-contacts' )
					);
				}
			}
		}
		foreach ( (array) ( $name['components'] ?? array() ) as $index => $component ) {
			if ( ! axismundi_contacts_value_is( $component, 'object' ) ) {
				// A list is positions and values; a position holding nothing is a component removed by
				// emptying rather than by shortening the list, which leaves a hole nothing can read.
				return axismundi_contacts_value_error( 'name/components/' . $index, __( 'a part of a name is an object, and a position in a list cannot be emptied', 'axismundi-contacts' ) );
			}
			foreach ( array( 'kind', 'value' ) as $key ) {
				if ( ! isset( $component[ $key ] ) || ! is_string( $component[ $key ] ) ) {
					/* translators: %s: the key a name component must have. */
					return axismundi_contacts_value_error( 'name/components/' . $index . '/' . $key, __( 'a part of a name says what kind it is and what it says', 'axismundi-contacts' ) );
				}
			}
			if ( array_key_exists( 'phonetic', $component ) && ! is_string( $component['phonetic'] ) ) {
				return axismundi_contacts_value_error( 'name/components/' . $index . '/phonetic', __( 'how a part of a name is said is text', 'axismundi-contacts' ) );
			}
			/*
			 * And a pronunciation without saying how it is written is a pronunciation nobody can read.
			 * `/kim/` is IPA, `Jīn` is Pinyin and `キム` is kana, and the same letters mean different
			 * sounds in each -- so the standard requires the Name to state the system or the script,
			 * and a document that skipped it would be handing a reader sounds in an unknown alphabet.
			 */
			if ( '' !== trim( (string) ( $component['phonetic'] ?? '' ) )
				&& '' === trim( (string) ( $name['phoneticSystem'] ?? '' ) )
				&& '' === trim( (string) ( $name['phoneticScript'] ?? '' ) ) ) {
				return axismundi_contacts_value_error(
					'name/components/' . $index . '/phonetic',
					__( 'a pronunciation says nothing until the name says what system or script it is written in', 'axismundi-contacts' )
				);
			}
		}
	}
	/*
	 * A title says which organization it belongs to by naming an entry rather than repeating its
	 * name, so that renaming the company renames it once. The other side of that bargain is that the
	 * entry has to be there: a title pointing at an organization the card no longer holds is a title
	 * whose employer nothing can resolve, and every reader of it has to invent an answer.
	 */
	foreach ( (array) ( $card['titles'] ?? array() ) as $id => $title ) {
		$of = is_array( $title ) ? trim( (string) ( $title['organizationId'] ?? '' ) ) : '';
		if ( '' !== $of && ! isset( $card['organizations'][ $of ] ) ) {
			return axismundi_contacts_value_error(
				'titles/' . $id . '/organizationId',
				__( 'a title belongs to an organization this card holds', 'axismundi-contacts' )
			);
		}
	}
	/*
	 * An address made only of the things that go between its parts is an address with no parts. The
	 * standard says as much, and so does reading one: `, , ,` names nowhere. Everything else about an
	 * address is left as it arrived -- a separator on an unordered address is wrong rather than
	 * meaningless, and refusing it would refuse somebody's import over a rule for writers.
	 */
	foreach ( (array) ( $card['addresses'] ?? array() ) as $id => $address ) {
		if ( ! is_array( $address ) ) {
			continue;
		}
		if ( array_key_exists( 'timeZone', $address ) ) {
			$zone = $address['timeZone'];
			if ( ! is_string( $zone ) || ! in_array( $zone, timezone_identifiers_list(), true ) ) {
				return axismundi_contacts_value_error(
					'addresses/' . $id . '/timeZone',
					__( 'an address time zone is an IANA name, such as Asia/Seoul', 'axismundi-contacts' )
				);
			}
		}
		$components = is_array( $address ) ? (array) ( $address['components'] ?? array() ) : array();
		if ( array() === $components ) {
			continue;
		}
		$named = array_filter(
			$components,
			static function ( $component ) : bool {
				return is_array( $component ) && 'separator' !== ( $component['kind'] ?? '' );
			}
		);
		if ( array() === $named ) {
			return axismundi_contacts_value_error(
				'addresses/' . $id . '/components',
				__( 'an address made only of separators names nowhere', 'axismundi-contacts' )
			);
		}
	}
	foreach ( axismundi_contacts_value_rules() as $property => $rules ) {
		$entries = $card[ $property ] ?? null;
		if ( null === $entries ) {
			continue;
		}
		if ( ! axismundi_contacts_value_is( $entries, 'object' ) ) {
			return axismundi_contacts_value_error( $property, __( 'it is a map of entries', 'axismundi-contacts' ) );
		}
		foreach ( $entries as $id => $entry ) {
			$at = $property . '/' . $id;
			if ( ! axismundi_contacts_value_is( $entry, 'object' ) ) {
				return axismundi_contacts_value_error( $at, __( 'an entry is an object', 'axismundi-contacts' ) );
			}
			foreach ( $rules['required'] as $key => $type ) {
				if ( ! array_key_exists( $key, $entry ) || ! axismundi_contacts_value_is( $entry[ $key ], $type ) ) {
					/* translators: %s: the key an entry of this kind must have. */
					return axismundi_contacts_value_error( $at . '/' . $key, sprintf( __( 'an entry of this kind needs %s, and it is not there', 'axismundi-contacts' ), $key ) );
				}
			}
			foreach ( $rules['optional'] as $key => $type ) {
				if ( array_key_exists( $key, $entry ) && ! axismundi_contacts_value_is( $entry[ $key ], $type ) ) {
					/* translators: %s: the shape the value should have. */
					return axismundi_contacts_value_error( $at . '/' . $key, sprintf( __( 'it is %s', 'axismundi-contacts' ), $type ) );
				}
			}
			if ( 'anniversaries' === $property ) {
				if ( '' === trim( (string) $entry['kind'] ) ) {
					return axismundi_contacts_value_error( $at . '/kind', __( 'an anniversary says what kind of day it is, such as birth', 'axismundi-contacts' ) );
				}
				/*
				 * A date that is absent, empty, or not an object at all reaches the same refusal
				 * below, and one rule saying "this is not a date" is better than three saying it in
				 * different words about the same missing day.
				 */
				$wrong = axismundi_contacts_anniversary_date_error( $at . '/date', (array) ( $entry['date'] ?? array() ) );
				if ( $wrong instanceof WP_Error ) {
					return $wrong;
				}
				/*
				 * Where it happened, which is also the only standard place to say how its day should
				 * be read. A day is a day somewhere: an instant near midnight is one date in Seoul and
				 * the one before it in New York, and the same is true of the day a lunisolar month is
				 * counted from. The standard has no time zone on an anniversary and does have one on
				 * an Address, and an Address carrying nothing but a time zone is a valid Address.
				 */
				if ( array_key_exists( 'place', $entry ) ) {
					$wrong = axismundi_contacts_anniversary_place_error( $at . '/place', (array) $entry['place'] );
					if ( $wrong instanceof WP_Error ) {
						return $wrong;
					}
				}
			}
			if ( 'organizations' === $property ) {
				$name  = trim( (string) ( $entry['name'] ?? '' ) );
				$units = (array) ( $entry['units'] ?? array() );
				if ( '' === $name && array() === $units ) {
					return axismundi_contacts_value_error( $at, __( 'an organization needs a name or a department', 'axismundi-contacts' ) );
				}
				foreach ( $units as $index => $unit ) {
					if ( ! axismundi_contacts_value_is( $unit, 'object' )
						|| ! isset( $unit['name'] )
						|| ! is_string( $unit['name'] )
						|| '' === trim( $unit['name'] ) ) {
						return axismundi_contacts_value_error( $at . '/units/' . $index . '/name', __( 'a department needs a name', 'axismundi-contacts' ) );
					}
					if ( array_key_exists( 'sortAs', $unit ) && ! is_string( $unit['sortAs'] ) ) {
						return axismundi_contacts_value_error( $at . '/units/' . $index . '/sortAs', __( 'a department sort key is text', 'axismundi-contacts' ) );
					}
				}
			}
		}
	}
	return true;
}

/**
 * Whether a date on an anniversary is a date, and nothing if it is.
 *
 * Two shapes are allowed and they are not alike. A Timestamp is an instant -- a death recorded to
 * the minute -- and a PartialDate is a calendar date somebody may only partly know. The standard
 * makes PartialDate the default, which is what a birthday almost always is: `1990-03-24` is a date
 * on a calendar and not a moment in a time zone, and storing it as one would move somebody's
 * birthday across midnight for every reader far enough east.
 *
 * A PartialDate is partial in named ways rather than in any way at all. A month with neither a year
 * nor a day beside it, or a day with no month, says nothing that could be put on a calendar -- so
 * the standard requires the company, and so does this.
 *
 * @param string              $at   Where in the Card.
 * @param array<string,mixed> $date The date as given.
 * @return WP_Error|null Null when there is nothing wrong.
 */
function axismundi_contacts_anniversary_date_error( string $at, array $date ) : ?WP_Error {
	// A Timestamp announces itself, and an instant is the one thing it has to carry.
	if ( 'Timestamp' === ( $date['@type'] ?? '' ) || array_key_exists( 'utc', $date ) ) {
		if ( ! isset( $date['utc'] ) || ! is_string( $date['utc'] ) || ! axismundi_contacts_is_utc_datetime( $date['utc'] ) ) {
			return axismundi_contacts_value_error( $at . '/utc', __( 'a moment is written in UTC, ending in Z, such as 1996-11-20T03:15:00Z', 'axismundi-contacts' ) );
		}
		/*
		 * And a calendar system may not be attached to it. `calendarScale` belongs to a partial date,
		 * where it says which calendar the day was counted in; an instant is a point on the line and
		 * has no month to be the seventh of. A Hebrew anniversary recorded to the minute is two facts
		 * -- the instant, and the day it is kept on -- and this one object cannot carry both.
		 */
		return array_key_exists( 'calendarScale', $date )
			? axismundi_contacts_value_error( $at . '/calendarScale', __( 'a moment in time is not counted in any calendar; the day it falls on is', 'axismundi-contacts' ) )
			: null;
	}
	$parts = array();
	foreach ( array( 'year', 'month', 'day' ) as $part ) {
		if ( ! array_key_exists( $part, $date ) ) {
			continue;
		}
		// Whole and positive. `0` is not a month, and `"3"` is text that happens to read like one.
		if ( ! is_int( $date[ $part ] ) || $date[ $part ] < 1 ) {
			/* translators: %s: year, month or day. */
			return axismundi_contacts_value_error( $at . '/' . $part, sprintf( __( 'a %s is a whole number above zero', 'axismundi-contacts' ), $part ) );
		}
		$parts[ $part ] = (int) $date[ $part ];
	}
	if ( array() === $parts ) {
		return axismundi_contacts_value_error( $at, __( 'a date says at least one of a year, a month and a day', 'axismundi-contacts' ) );
	}
	if ( isset( $parts['month'] ) && $parts['month'] > 12 ) {
		return axismundi_contacts_value_error( $at . '/month', __( 'a year has twelve months', 'axismundi-contacts' ) );
	}
	if ( isset( $parts['day'] ) && ! isset( $parts['month'] ) ) {
		return axismundi_contacts_value_error( $at . '/day', __( 'a day belongs to a month, and this one names none', 'axismundi-contacts' ) );
	}
	if ( isset( $parts['month'] ) && ! isset( $parts['year'] ) && ! isset( $parts['day'] ) ) {
		return axismundi_contacts_value_error( $at . '/month', __( 'a month on its own says nothing; give it a year or a day', 'axismundi-contacts' ) );
	}
	if ( isset( $parts['day'], $parts['month'] ) ) {
		/*
		 * The 29th of February is a real birthday and an impossible date in most years, so the year
		 * decides only when there is one. A date that does not name its year is asked whether the day
		 * exists in any year -- which is what somebody born on a leap day has.
		 */
		$year = $parts['year'] ?? 2000;
		if ( ! checkdate( $parts['month'], $parts['day'], $year ) ) {
			return axismundi_contacts_value_error( $at . '/day', __( 'that month has no such day', 'axismundi-contacts' ) );
		}
	}
	/*
	 * Which calendar the day was counted in. The numbers above stay Gregorian whatever this says --
	 * the standard is explicit about that -- so this is the reading and not the value: a birthday
	 * counted on the Korean lunisolar calendar is stored as the Gregorian day it fell on, with this
	 * saying which calendar the family was counting in when they named it.
	 */
	if ( array_key_exists( 'calendarScale', $date ) ) {
		$scale = $date['calendarScale'];
		if ( ! is_string( $scale ) || '' === trim( $scale ) || strtolower( $scale ) !== $scale ) {
			return axismundi_contacts_value_error( $at . '/calendarScale', __( 'a calendar system is named the way CLDR names it, in lowercase', 'axismundi-contacts' ) );
		}
	}
	return null;
}

/**
 * Whether the place on an anniversary is a place, and nothing if it is.
 *
 * An Address says at least one of what it is made of, where it is, which country it is in, how it
 * is written out, or which time zone it keeps -- and an empty one is a property that survived an
 * edit rather than an answer. A time zone alone is a whole Address here on purpose: it is how this
 * ledger records that a day was counted in Seoul without asking anybody for a street.
 *
 * @param string              $at    Where in the Card.
 * @param array<string,mixed> $place The place as given.
 * @return WP_Error|null Null when there is nothing wrong.
 */
function axismundi_contacts_anniversary_place_error( string $at, array $place ) : ?WP_Error {
	$said = array_filter(
		array( 'components', 'coordinates', 'countryCode', 'full', 'timeZone' ),
		static function ( string $key ) use ( $place ) : bool {
			return array_key_exists( $key, $place ) && array() !== $place[ $key ] && '' !== $place[ $key ];
		}
	);
	if ( array() === $said ) {
		return axismundi_contacts_value_error( $at, __( 'a place says at least one of where it is, what it is called, or which time zone it keeps', 'axismundi-contacts' ) );
	}
	if ( array_key_exists( 'countryCode', $place ) ) {
		/*
		 * Two letters, upper case, the way ISO 3166-1 writes them. Which is a different fact from the
		 * time zone beside it and not a substitute for one: the United States, Russia and Australia
		 * each keep several clocks, so a country never says which day an instant fell on.
		 */
		$country = $place['countryCode'];
		if ( ! is_string( $country ) || 1 !== preg_match( '/^[A-Z]{2}$/', $country ) ) {
			return axismundi_contacts_value_error( $at . '/countryCode', __( 'a country is its two-letter code, such as KR', 'axismundi-contacts' ) );
		}
	}
	if ( array_key_exists( 'timeZone', $place ) ) {
		$zone = $place['timeZone'];
		/*
		 * A name from the time zone database, not an offset. `+09:00` is what Seoul reads today and
		 * says nothing about what it read in 1988, which is exactly the question a birthday recorded
		 * to the minute in 1988 asks.
		 */
		if ( ! is_string( $zone ) || ! in_array( $zone, timezone_identifiers_list( DateTimeZone::ALL_WITH_BC ), true ) ) {
			return axismundi_contacts_value_error( $at . '/timeZone', __( 'a time zone is named in the IANA database, such as Asia/Seoul', 'axismundi-contacts' ) );
		}
	}
	return null;
}

/**
 * Whether this is a UTCDateTime, which is narrower than a date and time.
 *
 * RFC 9553 requires one written form per instant: upper case throughout, the offset written as `Z`
 * rather than `+00:00`, and a fraction of a second only when it is not zero and with no trailing
 * zeros. `2010-10-10T10:10:10.000Z` is a valid RFC 3339 timestamp and not a valid one here, because
 * two documents saying the same instant differently is what canonical form exists to prevent.
 *
 * An offset of `+03:30` is refused rather than converted. Converting would be this code deciding
 * that an offset is a time zone, which it is not -- the same clock reading in Tehran is a different
 * offset half the year -- and the zone belongs on `place`, where it can be named.
 *
 * @param string $value Candidate.
 * @return bool
 */
function axismundi_contacts_is_utc_datetime( string $value ) : bool {
	if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z$/', $value ) ) {
		return false;
	}
	// A fraction that is there says something. `.0`, `.000` and a trailing zero do not.
	if ( 1 === preg_match( '/\.(\d+)Z$/', $value, $fraction ) && ( '' === rtrim( $fraction[1], '0' ) || str_ends_with( $fraction[1], '0' ) ) ) {
		return false;
	}
	$parts = array_map( 'intval', array( substr( $value, 0, 4 ), substr( $value, 5, 2 ), substr( $value, 8, 2 ) ) );
	if ( ! checkdate( $parts[1], $parts[2], $parts[0] ) ) {
		return false;
	}
	$time = array_map( 'intval', array( substr( $value, 11, 2 ), substr( $value, 14, 2 ), substr( $value, 17, 2 ) ) );
	// 24:00 is a real RFC 3339 reading and 25:00 is not; a leap second lands on 60.
	return $time[0] <= 24 && $time[1] <= 59 && $time[2] <= 60;
}

/**
 * One refusal about a value, saying where it is.
 *
 * @param string $at   Where in the Card.
 * @param string $what What is wrong.
 * @return WP_Error
 */
function axismundi_contacts_value_error( string $at, string $what ) : WP_Error {
	return new WP_Error(
		'ax_contacts_card_value',
		sprintf(
			/* translators: 1: path inside the card, 2: what is wrong with the value there. */
			__( '%1$s is not something a card can say: %2$s.', 'axismundi-contacts' ),
			$at,
			$what
		),
		array( 'status' => 400 )
	);
}

/**
 * The Card one language would receive, for asking whether the answer is still a Card.
 *
 * @param array<string,mixed> $card  Base Card.
 * @param array<string,mixed> $patch Paths and the values to put there.
 * @return array<string,mixed>
 */
function axismundi_contacts_apply_patch( array $card, array $patch ) : array {
	foreach ( $patch as $path => $value ) {
		$segments = explode( '/', (string) $path );
		if ( null === $value ) {
			$card = axismundi_contacts_remove_patch_path( $card, $segments );
			continue;
		}
		$card = axismundi_contacts_apply_patch_path( $card, $segments, $value );
	}
	return $card;
}

/**
 * Take one value out, the way a patch of `null` asks.
 *
 * @param array<string,mixed> $value    The Card, or something inside it.
 * @param string[]            $segments Path.
 * @return array<string,mixed>
 */
function axismundi_contacts_remove_patch_path( array $value, array $segments ) : array {
	$key = (string) array_shift( $segments );
	if ( '' === $key ) {
		return $value;
	}
	$slot = ctype_digit( $key ) ? (int) $key : $key;
	if ( array() === $segments ) {
		unset( $value[ $slot ] );
		return $value;
	}
	if ( is_array( $value[ $slot ] ?? null ) ) {
		$value[ $slot ] = axismundi_contacts_remove_patch_path( $value[ $slot ], $segments );
	}
	return $value;
}
