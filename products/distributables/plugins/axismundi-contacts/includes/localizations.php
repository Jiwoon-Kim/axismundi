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
