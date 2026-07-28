<?php
/**
 * Axismundi Actors integration.
 *
 * Three seams, all optional. Emoji records what an Actor declares when Actors discovers
 * or refreshes it, and decorates the display name and the biography when a block renders
 * one. Neither plugin requires the other: every entry point is guarded, and with Actors
 * absent this file simply never runs.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit;

/**
 * Record the emoji a remote Actor declares.
 *
 * Hooked to the same events the Actors asset cache and instance cache already use, so
 * an Actor's emoji are observed exactly when the rest of its cached state is. This
 * writes metadata only — a new row is `pending`, nothing is downloaded, and the
 * shortcode keeps rendering as text until somebody approves it.
 *
 * @param mixed $actor Discovered or refreshed Actor.
 * @return void
 */
function axismundi_emoji_observe_actor( $actor ) : void {
	if ( ! class_exists( 'Axismundi_Actor' ) || ! ( $actor instanceof Axismundi_Actor ) || $actor->is_local() ) {
		return;
	}
	if ( ! function_exists( 'axismundi_actors_get_remote_payload' ) || ! axismundi_emoji_ready() ) {
		return;
	}
	$payload = axismundi_actors_get_remote_payload( $actor->get_identity_id() );
	if ( ! is_array( $payload ) || array() === $payload ) {
		return;
	}
	axismundi_emoji_observe_payload( $payload, $actor->get_uri(), 'actor' );
}
add_action( 'axismundi_actors_remote_actor_discovered', 'axismundi_emoji_observe_actor', 40 );
add_action( 'axismundi_actors_remote_actor_updated', 'axismundi_emoji_observe_actor', 40 );

/**
 * The declarations belonging to one resolved block subject.
 *
 * Built from the Actor's own cached payload, which is what makes the substitution
 * scoped: a name is resolved against what *this* Actor declared, never against the
 * registry at large. Two servers running Misskey both ship a `:misskey:`, and a global
 * lookup would show one of them under the other's name.
 *
 * @param array<string,mixed> $subject Resolved block subject.
 * @return array<string,array<string,array<string,mixed>>>
 */
function axismundi_emoji_subject_declarations( array $subject ) : array {
	return axismundi_emoji_actor_declarations( $subject['actor'] ?? null );
}

/**
 * The declarations belonging to one Actor.
 *
 * Both Actor seams answer the same question — what did *this* Actor declare — so they
 * ask it in one place. A local Actor returns nothing: it has no `tag[]` yet, and until
 * a local registry exists there is nothing a lookup could honestly resolve against.
 *
 * @param mixed $actor Actor, or anything else.
 * @return array<string,array<string,array<string,mixed>>>
 */
function axismundi_emoji_actor_declarations( $actor ) : array {
	if ( ! class_exists( 'Axismundi_Actor' ) || ! ( $actor instanceof Axismundi_Actor ) ) {
		return array();
	}
	if ( $actor->is_local() || ! function_exists( 'axismundi_actors_get_remote_payload' ) || ! axismundi_emoji_ready() ) {
		return array();
	}
	$payload = axismundi_actors_get_remote_payload( $actor->get_identity_id() );
	return is_array( $payload ) ? axismundi_emoji_declaration_map( $payload, $actor->get_uri() ) : array();
}

/**
 * Decorate an Actor display name.
 *
 * The incoming string is already escaped, which is the only order that is safe: emoji
 * markup is added last, so nothing it introduces can be re-escaped into visible tags
 * and nothing in the name can escape through the substitution.
 *
 * @param string              $name_html Escaped display name.
 * @param array<string,mixed> $subject   Resolved block subject.
 * @return string
 */
function axismundi_emoji_decorate_display_name( string $name_html, array $subject ) : string {
	if ( false === strpos( $name_html, ':' ) ) {
		return $name_html;
	}
	$map = axismundi_emoji_subject_declarations( $subject );
	return array() === $map ? $name_html : axismundi_emoji_decorate_escaped( $name_html, $map );
}
add_filter( 'axismundi_actors_display_name_html', 'axismundi_emoji_decorate_display_name', 10, 2 );

/**
 * Decorate an Actor biography.
 *
 * A name arrives as escaped text and a summary arrives as sanitized HTML, so the two
 * seams need different substitution passes even though they resolve against the same
 * declarations: this one walks text nodes and leaves attributes, `<code>`, and `<pre>`
 * alone, because a summary can legitimately contain markup and an example shortcode.
 *
 * @param string $summary_html Sanitized summary HTML.
 * @param mixed  $actor        Actor the summary belongs to.
 * @return string
 */
function axismundi_emoji_decorate_summary( string $summary_html, $actor ) : string {
	if ( false === strpos( $summary_html, ':' ) ) {
		return $summary_html;
	}
	$map = axismundi_emoji_actor_declarations( $actor );
	return array() === $map ? $summary_html : axismundi_emoji_decorate( $summary_html, $map );
}
add_filter( 'axismundi_actors_summary_html', 'axismundi_emoji_decorate_summary', 10, 2 );
