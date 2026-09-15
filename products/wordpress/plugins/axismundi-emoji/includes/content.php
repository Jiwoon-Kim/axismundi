<?php
/**
 * Custom emoji in this site's own posts and comments.
 *
 * Until now a `:shortcode:` became an image only inside Object Projections' Object body and
 * Actors' profile fields. A plain WordPress post or comment never passed through either, so
 * `:wordpress:` typed into a post — or inserted by this plugin's own editor picker — stayed a
 * word on the front end. Emoji ships on its own, without ActivityPub or Object Projections, so
 * the site's ordinary content has to render its own emoji.
 *
 * The declaration here is local, not federated. A remote Object says which emoji it means in
 * its `tag[]`; text written on this site means this site's emoji, and nothing else. So a
 * shortcode is replaced only when it names a local, approved, renderable emoji. Withheld
 * from publication (`outbound_allowed = 0`) still renders: that decides what travels, not
 * what the author sees at home, exactly as the architecture document states. Remote emoji
 * are never matched by name here — that would show a stranger's picture under a word the
 * author typed.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit;

/**
 * Declaration map for text written on this site: each used shortcode that is a renderable
 * local emoji.
 *
 * @param string $html Rendered content.
 * @return array<string,array<string,array<string,mixed>>> Same shape as axismundi_emoji_declaration_map().
 */
function axismundi_emoji_local_text_map( string $html ) : array {
	$map = array();
	foreach ( axismundi_emoji_tokenize( $html ) as $key ) {
		$row = axismundi_emoji_local_get( $key );
		if ( is_array( $row ) && axismundi_emoji_is_renderable( $row ) ) {
			$map[ $key ][ (string) $row['emoji_authority'] ] = $row;
		}
	}
	return $map;
}

/**
 * Decorate a post body or comment.
 *
 * Feeds keep the shortcode: a feed is a copy read elsewhere, and the architecture keeps
 * plain-text and syndicated surfaces as text rather than guessing how a reader renders them.
 *
 * Running on content an Object Projections view later decorates again is harmless: the
 * renderer walks text nodes only, and a replaced shortcode is an `<img>` by then.
 *
 * @param mixed $content Rendered HTML.
 * @return string
 */
function axismundi_emoji_decorate_site_content( $content ) : string {
	$content = (string) $content;
	if ( false === strpos( $content, ':' ) || is_feed() || ! axismundi_emoji_ready() ) {
		return $content;
	}
	$map = axismundi_emoji_local_text_map( $content );
	return array() === $map ? $content : axismundi_emoji_decorate( $content, $map );
}

/*
 * Priority 12: after blocks (9), texturize (10) and shortcodes (11) have produced the final
 * markup, and before `convert_smilies()` at 20, so a local emoji wins over a Core smiley of
 * the same spelling while every undeclared `:word:` still reaches Core untouched.
 */
add_filter( 'the_content', 'axismundi_emoji_decorate_site_content', 12 );
add_filter( 'comment_text', 'axismundi_emoji_decorate_site_content', 12 );
