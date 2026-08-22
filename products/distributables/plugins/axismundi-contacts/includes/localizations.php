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
