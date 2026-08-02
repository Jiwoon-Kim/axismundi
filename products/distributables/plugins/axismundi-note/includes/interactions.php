<?php
/**
 * Like and Announce integration for public local Notes.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

/** Resolve an active, anonymously visible local Note without network access. */
function axismundi_note_reaction_target( string $object_uri ) {
	$uuid = axismundi_note_local_uuid_from_uri( $object_uri );
	if ( null === $uuid ) {
		return null;
	}
	$envelope = axismundi_note_get_by_uuid( $uuid );
	if ( ! is_array( $envelope ) || 'active' !== (string) ( $envelope['object_status'] ?? '' ) ) {
		return new WP_Error( 'ax_note_reaction_target', __( 'The Note is not available for interaction.', 'axismundi-note' ), array( 'status' => 409 ) );
	}
	$post   = get_post( (int) $envelope['post_id'] );
	$source = new Axismundi_Note_Source( $envelope, $post instanceof WP_Post ? $post : null );
	$actor  = axismundi_note_envelope_actor( $envelope );
	if ( ! $actor instanceof Axismundi_Actor || ! axismundi_note_source_visible( $source ) ) {
		return new WP_Error( 'ax_note_reaction_visibility', __( 'The Note is not publicly interactable.', 'axismundi-note' ), array( 'status' => 404 ) );
	}
	return array(
		'object_uri'   => $source->get_uri(),
		'recipient_uri' => $actor->get_uri(),
		'source'        => $source,
	);
}

/** Supply a Note target to the Activities Like resolver. */
function axismundi_note_resolve_like_target( $target, string $object_uri ) {
	$resolved = axismundi_note_reaction_target( $object_uri );
	return null === $resolved ? $target : $resolved;
}
add_filter( 'axismundi_act_resolve_like_target', 'axismundi_note_resolve_like_target', 10, 2 );

/** Supply a Note target to the Activities Announce resolver. */
function axismundi_note_resolve_announce_target( $target, string $object_uri ) {
	$resolved = axismundi_note_reaction_target( $object_uri );
	return null === $resolved ? $target : $resolved;
}
add_filter( 'axismundi_act_resolve_announce_target', 'axismundi_note_resolve_announce_target', 20, 2 );

/** Confirm that one exact public local Note may be announced. */
function axismundi_note_can_announce_object( $allowed, Axismundi_Actor $actor, string $object_uri ) {
	unset( $actor );
	$resolved = axismundi_note_reaction_target( $object_uri );
	return is_array( $resolved ) ? true : ( null === $resolved ? $allowed : $resolved );
}
add_filter( 'axismundi_act_can_announce_object', 'axismundi_note_can_announce_object', 20, 3 );

/** Keep the legacy compact Object renderer interactive outside the block template. */
function axismundi_note_object_view_interactions( string $html, array $model ) : string {
	$uri = isset( $model['object_uri'] ) ? (string) $model['object_uri'] : '';
	if ( null === axismundi_note_local_uuid_from_uri( $uri ) || is_wp_error( axismundi_note_reaction_target( $uri ) ) ) {
		return $html;
	}
	if ( ! function_exists( 'render_block' ) ) {
		return $html;
	}
	// One block, one control each, named by type — the same three this rendered before, now
	// asking for them the way a template does.
	$controls = '';
	foreach ( array( 'reply', 'like', array( 'announce', array( 'announceMenu' => true ) ) ) as $type ) {
		$attributes = array_merge(
			array( 'objectUri' => $uri, 'type' => is_array( $type ) ? $type[0] : $type ),
			is_array( $type ) ? $type[1] : array()
		);
		$controls  .= render_block(
			array( 'blockName' => 'axismundi/interaction', 'attrs' => $attributes, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() )
		);
	}
	return $html . $controls;
}
add_filter( 'axismundi_op_object_view_interactions', 'axismundi_note_object_view_interactions', 10, 2 );

/** Send a front-end Reply command into the existing Note editor contract. */
function axismundi_note_reply_compose_url( string $url, string $object_uri ) : string {
	if ( '' === esc_url_raw( $object_uri ) ) {
		return $url;
	}
	return add_query_arg(
		array(
			'post_type'   => AXISMUNDI_NOTE_POST_TYPE,
			'ax_reply_to' => $object_uri,
		),
		admin_url( 'post-new.php' )
	);
}
add_filter( 'axismundi_act_reply_compose_url', 'axismundi_note_reply_compose_url', 10, 2 );

/** Send a front-end Quote command into the existing Note editor contract. */
function axismundi_note_quote_compose_url( string $url, string $object_uri ) : string {
	if ( null === axismundi_note_local_uuid_from_uri( $object_uri ) && ! function_exists( 'axismundi_op_get_remote_object' ) ) {
		return $url;
	}
	return add_query_arg(
		array(
			'post_type'       => AXISMUNDI_NOTE_POST_TYPE,
			'ax_quote_target' => $object_uri,
		),
		admin_url( 'post-new.php' )
	);
}
add_filter( 'axismundi_act_quote_compose_url', 'axismundi_note_quote_compose_url', 10, 2 );
