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
 * Per-folder default license (term meta). A folder with no default walks up its
 * parent chain; see axismundi_media_folder_default_license().
 */
const AXISMUNDI_MEDIA_FOLDER_DEFAULT_LICENSE_META = '_ax_media_folder_default_license';

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
 * Escape human text for a Markdown link label.
 *
 * @param string $text Label text.
 * @return string
 */
function axismundi_media_markdown_label( string $text ) : string {
	return str_replace( array( '\\', '[', ']' ), array( '\\\\', '\\[', '\\]' ), $text );
}

/**
 * Build plain, Markdown, and HTML attribution from one normalized record.
 * Authored attribution is treated as authoritative plain text; generated formats
 * follow the Openverse credit pattern and link the work, creator, and license when
 * their URLs are known. These strings are display/copy output and are never persisted.
 *
 * @param int $attachment_id Attachment ID.
 * @return array{plain:string,markdown:string,html:string}
 */
function axismundi_media_attribution_formats( int $attachment_id ) : array {
	$authored = (string) get_post_meta( $attachment_id, '_ax_media_attribution', true );
	if ( '' !== $authored ) {
		return array(
			'plain'    => $authored,
			'markdown' => $authored,
			'html'     => '<p class="attribution">' . esc_html( $authored ) . '</p>',
		);
	}

	$record      = axismundi_media_license_record( $attachment_id );
	$creator     = trim( (string) get_post_meta( $attachment_id, '_ax_media_creator_name', true ) );
	$creator_url = (string) get_post_meta( $attachment_id, '_ax_media_creator_url', true );
	$source_url  = (string) get_post_meta( $attachment_id, '_ax_media_source_url', true );
	$title       = trim( (string) get_the_title( $attachment_id ) );
	$title       = '' !== $title ? $title : __( 'This work', 'axismundi-media-library' );

	$markdown_link = static function ( string $label, string $url ) : string {
		if ( '' === $url ) {
			return axismundi_media_markdown_label( $label );
		}
		$url = str_replace( array( '(', ')' ), array( '%28', '%29' ), esc_url_raw( $url ) );
		return '[' . axismundi_media_markdown_label( $label ) . '](' . $url . ')';
	};
	$html_link = static function ( string $label, string $url ) : string {
		return '' === $url
			? esc_html( $label )
			: '<a rel="noopener noreferrer" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	};

	$plain_work    = '"' . $title . '"';
	$markdown_work = '"' . $markdown_link( $title, $source_url ) . '"';
	$html_work     = '&quot;' . $html_link( $title, $source_url ) . '&quot;';
	if ( '' !== $creator ) {
		/* translators: %s: creator name, optionally linked. */
		$by_pattern     = __( 'by %s', 'axismundi-media-library' );
		$plain_work    .= ' ' . sprintf( $by_pattern, $creator );
		$markdown_work .= ' ' . sprintf( $by_pattern, $markdown_link( $creator, $creator_url ) );
		$html_work     .= ' ' . sprintf( $by_pattern, $html_link( $creator, $creator_url ) );
	}
	$plain_license    = $record['name'];
	$markdown_license = $markdown_link( $record['name'], $record['url'] );
	$html_license     = $html_link( $record['name'], $record['url'] );

	if ( in_array( $record['code'], array( 'pdm', 'cc0' ), true ) ) {
		/* translators: 1: work and creator, 2: rights statement. */
		$pattern = __( '%1$s is marked with %2$s.', 'axismundi-media-library' );
	} elseif ( $record['conditions']['known'] ) {
		/* translators: 1: work and creator, 2: license. */
		$pattern = __( '%1$s is licensed under %2$s.', 'axismundi-media-library' );
	} elseif ( 'all-rights-reserved' === $record['code'] ) {
		/* translators: 1: work and creator, 2: rights statement. */
		$pattern = __( '%1$s. %2$s.', 'axismundi-media-library' );
		$plain_license = $markdown_license = $html_license = __( 'All rights reserved', 'axismundi-media-library' );
	} else {
		/* translators: 1: work and creator, 2: license status. */
		$pattern = __( '%1$s. %2$s.', 'axismundi-media-library' );
		$plain_license = $markdown_license = $html_license = __( 'License unknown', 'axismundi-media-library' );
	}

	return array(
		'plain'    => sprintf( $pattern, $plain_work, $plain_license ),
		'markdown' => sprintf( $pattern, $markdown_work, $markdown_license ),
		'html'     => '<p class="attribution">' . sprintf( $pattern, $html_work, $html_license ) . '</p>',
	);
}

/**
 * Plain-text attribution convenience wrapper.
 *
 * @param int $attachment_id Attachment ID.
 * @return string
 */
function axismundi_media_attribution_text( int $attachment_id ) : string {
	$formats = axismundi_media_attribution_formats( $attachment_id );
	return $formats['plain'];
}

/**
 * The nearest authored default license walking up a folder's parent chain, or ''
 * when no folder in the chain declares one (the caller then falls back to
 * all-rights-reserved at read time via axismundi_media_license_code()). An invalid
 * stored value is skipped, not treated as a default.
 *
 * @param int $term_id Folder term ID.
 * @return string
 */
function axismundi_media_folder_default_license( int $term_id ) : string {
	if ( $term_id <= 0 || ! defined( 'AXISMUNDI_MEDIA_FOLDER_TAX' ) ) {
		return '';
	}
	$options = function_exists( 'axismundi_media_license_options' ) ? axismundi_media_license_options() : array();
	$visited = array();
	while ( $term_id > 0 && ! isset( $visited[ $term_id ] ) ) {
		$visited[ $term_id ] = true;
		$code = (string) get_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_DEFAULT_LICENSE_META, true );
		if ( '' !== $code && isset( $options[ $code ] ) ) {
			return $code;
		}
		$term    = get_term( $term_id, AXISMUNDI_MEDIA_FOLDER_TAX );
		$term_id = $term instanceof WP_Term ? (int) $term->parent : 0;
	}
	return '';
}

/**
 * Set (or clear, with '') a folder's default license. Gated like the other folder
 * setters: not the hidden root, and the actor must manage the folder.
 *
 * @param int      $term_id Folder term ID.
 * @param string   $code    License code, or '' to clear.
 * @param int|null $user_id Acting user.
 * @return true|WP_Error
 */
function axismundi_media_set_folder_default_license( int $term_id, string $code, ?int $user_id = null ) {
	$user_id = $user_id ?? get_current_user_id();
	if ( axismundi_media_is_root_term( $term_id ) || ! axismundi_media_can_manage_folder( $term_id, $user_id ) ) {
		return new WP_Error( 'ax_media_forbidden', __( 'Not allowed.', 'axismundi-media-library' ), array( 'status' => 403 ) );
	}
	if ( '' === $code ) {
		delete_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_DEFAULT_LICENSE_META );
		return true;
	}
	$options = function_exists( 'axismundi_media_license_options' ) ? axismundi_media_license_options() : array();
	if ( ! isset( $options[ $code ] ) ) {
		return new WP_Error( 'ax_media_folder_license', __( 'Invalid folder default license.', 'axismundi-media-library' ), array( 'status' => 400 ) );
	}
	update_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_DEFAULT_LICENSE_META, $code );
	return true;
}

/**
 * Stamp the folder default license onto a NEW upload — the single stamp service
 * every upload path shares (docs/PHASES.md Phase 4b). It is a snapshot at upload,
 * NOT dynamic inheritance: it runs only from add_attachment, never on a later move,
 * and never overwrites a license the attachment already carries (contract 6-7). A
 * folder chain with no default writes nothing (reads as all-rights-reserved).
 *
 * @param int $attachment_id Attachment ID.
 * @param int $folder_id     Folder the upload landed in.
 * @return void
 */
function axismundi_media_stamp_folder_default_license( int $attachment_id, int $folder_id ) : void {
	if ( $folder_id <= 0 ) {
		return;
	}
	if ( '' !== (string) get_post_meta( $attachment_id, '_ax_media_license', true ) ) {
		return;
	}
	$default = axismundi_media_folder_default_license( $folder_id );
	if ( '' !== $default ) {
		update_post_meta( $attachment_id, '_ax_media_license', $default );
	}
}

/**
 * Append the plugin-owned rights block to a single Attachment's main content.
 * A manually rendered rights block wins and suppresses this automatic placement.
 *
 * @param string $content Rendered post content.
 * @return string
 */
function axismundi_media_append_rights_to_attachment( string $content ) : string {
	global $axismundi_media_rights_rendered;
	if ( is_admin() || is_feed() || ! is_attachment() || ! in_the_loop() || ! is_main_query() || $axismundi_media_rights_rendered ) {
		return $content;
	}
	$attachment_id = (int) get_the_ID();
	if ( $attachment_id <= 0 || $attachment_id !== (int) get_queried_object_id() ) {
		return $content;
	}
	return $content . do_blocks( '<!-- wp:axismundi/media-rights /-->' );
}
add_filter( 'the_content', 'axismundi_media_append_rights_to_attachment', 30 );
