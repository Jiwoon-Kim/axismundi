<?php
/**
 * Phase 4b — license / rights resolver.
 *
 * License is the **single source of truth** for reuse conditions — there is no
 * separate reuse-policy value. Clean break: only the current Openverse-aligned
 * vocabulary exists (edit-fields `axismundi_media_license_options()`); no legacy
 * aliases, no `custom`, no migration. Unspecified / unknown / all-rights-reserved
 * grant nothing (`known = false`) — no reuse or redistribution is inferred.
 *
 * This resolver is display + future Import/Copy/redistribution logic; it does NOT gate
 * Phase 5 Save (Save is a visibility-gated bookmark, not a licensed reuse).
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

/**
 * The canonical CC / Public-Domain URL for a license code ('' for ARR/unknown).
 *
 * @param string $code License code.
 * @return string
 */
function axismundi_media_license_url_for( string $code ) : string {
	$urls = array(
		'pdm'         => 'https://creativecommons.org/publicdomain/mark/1.0/',
		'cc0'         => 'https://creativecommons.org/publicdomain/zero/1.0/',
		'cc-by'       => 'https://creativecommons.org/licenses/by/4.0/',
		'cc-by-sa'    => 'https://creativecommons.org/licenses/by-sa/4.0/',
		'cc-by-nd'    => 'https://creativecommons.org/licenses/by-nd/4.0/',
		'cc-by-nc'    => 'https://creativecommons.org/licenses/by-nc/4.0/',
		'cc-by-nc-sa' => 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
		'cc-by-nc-nd' => 'https://creativecommons.org/licenses/by-nc-nd/4.0/',
	);
	return $urls[ $code ] ?? '';
}

/**
 * Reuse conditions derived from a license code. `known = false` (ARR / unknown) means
 * nothing is granted — callers must not infer reuse.
 *
 * @param string $code License code.
 * @return array{attribution:bool,commercial:bool,derivatives:bool,share_alike:bool,known:bool}
 */
function axismundi_media_license_conditions( string $code ) : array {
	$grant = static function ( bool $attr, bool $comm, bool $deriv, bool $sa ) : array {
		return array( 'attribution' => $attr, 'commercial' => $comm, 'derivatives' => $deriv, 'share_alike' => $sa, 'known' => true );
	};
	switch ( $code ) {
		case 'pdm':
		case 'cc0':
			return $grant( false, true, true, false );
		case 'cc-by':
			return $grant( true, true, true, false );
		case 'cc-by-sa':
			return $grant( true, true, true, true );
		case 'cc-by-nd':
			return $grant( true, true, false, false );
		case 'cc-by-nc':
			return $grant( true, false, true, false );
		case 'cc-by-nc-sa':
			return $grant( true, false, true, true );
		case 'cc-by-nc-nd':
			return $grant( true, false, false, false );
		default: // all-rights-reserved, unknown, anything unexpected
			return array( 'attribution' => false, 'commercial' => false, 'derivatives' => false, 'share_alike' => false, 'known' => false );
	}
}

/**
 * An attachment's stored license code, defaulting to all-rights-reserved (an unknown
 * or dropped value never leaks through).
 *
 * @param int $attachment_id Attachment ID.
 * @return string
 */
function axismundi_media_license_code( int $attachment_id ) : string {
	$code    = (string) get_post_meta( $attachment_id, '_ax_media_license', true );
	$options = function_exists( 'axismundi_media_license_options' ) ? axismundi_media_license_options() : array();
	return isset( $options[ $code ] ) ? $code : 'all-rights-reserved';
}

/**
 * Full license record for display and serialization. The canonical URL wins for
 * standard codes so the code and URL can never disagree; a user-supplied URL is used
 * only for codes without a canonical one.
 *
 * @param int $attachment_id Attachment ID.
 * @return array{code:string,name:string,url:string,conditions:array<string,bool>}
 */
function axismundi_media_license_record( int $attachment_id ) : array {
	$code      = axismundi_media_license_code( $attachment_id );
	$options   = function_exists( 'axismundi_media_license_options' ) ? axismundi_media_license_options() : array();
	$canonical = axismundi_media_license_url_for( $code );
	return array(
		'code'       => $code,
		'name'       => (string) ( $options[ $code ] ?? $code ),
		'url'        => '' !== $canonical ? $canonical : (string) get_post_meta( $attachment_id, '_ax_media_license_url', true ),
		'conditions' => axismundi_media_license_conditions( $code ),
	);
}

/**
 * Display attribution: the authored `_ax_media_attribution` if present, else a
 * display-only fallback from title + creator + license. Never written to meta.
 *
 * @param int $attachment_id Attachment ID.
 * @return string
 */
function axismundi_media_attribution_text( int $attachment_id ) : string {
	$authored = (string) get_post_meta( $attachment_id, '_ax_media_attribution', true );
	if ( '' !== $authored ) {
		return $authored;
	}
	$record  = axismundi_media_license_record( $attachment_id );
	$creator = (string) get_post_meta( $attachment_id, '_ax_media_creator_name', true );
	$title   = (string) get_the_title( $attachment_id );

	$parts = array();
	if ( '' !== $title ) {
		$parts[] = $title;
	}
	if ( '' !== $creator ) {
		/* translators: %s: creator name. */
		$parts[] = sprintf( __( 'by %s', 'axismundi-media-library' ), $creator );
	}
	if ( $record['conditions']['known'] ) {
		$parts[] = '(' . $record['name'] . ')';
	}
	return trim( implode( ' ', $parts ) );
}
