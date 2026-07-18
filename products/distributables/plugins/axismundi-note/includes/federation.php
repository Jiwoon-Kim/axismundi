<?php
/**
 * Note federation readiness, request source, and JSON-LD transformer.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

/** Opaque Note-owned source passed through Object Projections. */
final class Axismundi_Note_Source {
	/** @var array<string,mixed> */
	private array $envelope;
	private ?WP_Post $post;

	/** @param array<string,mixed> $envelope Envelope row. */
	public function __construct( array $envelope, ?WP_Post $post ) {
		$this->envelope = $envelope;
		$this->post     = $post;
	}

	/** @return array<string,mixed> */
	public function get_envelope() : array {
		return $this->envelope;
	}

	public function get_post() : ?WP_Post {
		return $this->post;
	}

	public function get_uri() : string {
		return axismundi_note_object_uri( (string) ( $this->envelope['local_uuid'] ?? '' ) );
	}

	public function is_tombstone() : bool {
		return 'tombstone' === (string) ( $this->envelope['object_status'] ?? '' );
	}
}

/** Resolve and validate the local public Actor frozen into one envelope. */
function axismundi_note_envelope_actor( array $envelope ) : ?Axismundi_Actor {
	$uri   = (string) ( $envelope['actor_uri'] ?? '' );
	$actor = '' !== $uri && function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( $uri ) : null;
	return $actor instanceof Axismundi_Actor
		&& $actor->is_local()
		&& 'public' === $actor->get_status()
		&& $actor->is_handle_locked()
		? $actor
		: null;
}

/** Resolve Mention descriptors, failing closed only at an authoring boundary. */
function axismundi_note_mention_tags( WP_Post $post, bool $strict = true ) {
	$tags = array();
	foreach ( axismundi_note_mentions( $post ) as $uri ) {
		$actor = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( $uri ) : null;
		$name  = $actor instanceof Axismundi_Actor && function_exists( 'axismundi_actors_federated_mention_name' )
			? axismundi_actors_federated_mention_name( $actor )
			: '';
		if ( ! $actor instanceof Axismundi_Actor || 'public' !== $actor->get_status() || '' === $name ) {
			if ( $strict ) {
				return new WP_Error( 'ax_note_mention_actor', __( 'A mentioned Actor could not be resolved safely.', 'axismundi-note' ) );
			}
			continue;
		}
		$tags[] = array( 'type' => 'Mention', 'name' => $name, 'href' => $uri );
	}
	return $tags;
}

/**
 * Freeze language and attribution before one published Note becomes readable.
 *
 * Attribution lock is written last and acts as the readiness marker. The method
 * is idempotent: later saves do not rewrite either frozen field.
 *
 * @return true|WP_Error
 */
function axismundi_note_prepare_for_federation( WP_Post $post ) {
	if ( AXISMUNDI_NOTE_POST_TYPE !== $post->post_type || 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
		return new WP_Error( 'ax_note_not_publishable', __( 'Only an unlocked published Note can be prepared for federation.', 'axismundi-note' ) );
	}
	$envelope = axismundi_note_get( $post->ID );
	if ( ! is_array( $envelope ) || 'active' !== (string) $envelope['object_status'] ) {
		return new WP_Error( 'ax_note_envelope', __( 'The active Note envelope is unavailable.', 'axismundi-note' ) );
	}
	if ( ! empty( $envelope['attribution_locked_at'] ) ) {
		$language = axismundi_note_normalize_language( (string) $envelope['language_tag'] );
		return '' !== $language && 'und' !== $language && axismundi_note_envelope_actor( $envelope ) instanceof Axismundi_Actor
			? true
			: new WP_Error( 'ax_note_readiness', __( 'The frozen Note identity is incomplete.', 'axismundi-note' ) );
	}

	// Refresh the unlocked Actor snapshot from the current post author.
	$saved = axismundi_note_save( $post->ID, array() );
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}
	if ( ! axismundi_note_envelope_actor( $saved ) instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_note_actor', __( 'The Note requires a public local Actor with a locked handle.', 'axismundi-note' ) );
	}

	$language = axismundi_note_effective_language( $post );
	if ( '' === $language || 'und' === $language ) {
		return new WP_Error( 'ax_note_language', __( 'The Note language could not be resolved.', 'axismundi-note' ) );
	}
	$saved = axismundi_note_save( $post->ID, array( 'language_tag' => $language ) );
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}
	$mentions = axismundi_note_mention_tags( $post, true );
	if ( is_wp_error( $mentions ) ) {
		return $mentions;
	}
	return axismundi_note_lock_attribution( $post->ID );
}

/** Prepare a Note after baseline and Classic envelope fields have been saved. */
function axismundi_note_prepare_saved_post( int $post_id, WP_Post $post ) : void {
	if ( 'publish' !== $post->post_status ) {
		return;
	}
	$result = axismundi_note_prepare_for_federation( $post );
	if ( is_wp_error( $result ) && is_admin() ) {
		set_transient( 'axismundi_note_error_' . get_current_user_id(), $result->get_error_message(), MINUTE_IN_SECONDS );
	}
}
add_action( 'save_post_' . AXISMUNDI_NOTE_POST_TYPE, 'axismundi_note_prepare_saved_post', 20, 2 );

/** Parse the current request's claimed Note identity or return null when unclaimed. */
function axismundi_note_request_uuid() {
	if ( ! array_key_exists( 'ax_note', $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public identity route.
		return null;
	}
	$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
	$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
	$home_path   = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$query       = (string) wp_parse_url( $request_uri, PHP_URL_QUERY );
	if ( untrailingslashit( $path ) !== untrailingslashit( $home_path ) || '' === $query ) {
		return new WP_Error( 'ax_op_not_found', __( 'That Note identity does not exist.', 'axismundi-note' ) );
	}

	$seen = array();
	foreach ( explode( '&', $query ) as $pair ) {
		$parts = explode( '=', $pair, 2 );
		$key   = (string) $parts[0];
		if ( ! in_array( $key, array( 'ax_note', 'activitypub' ), true ) || isset( $seen[ $key ] ) ) {
			return new WP_Error( 'ax_op_not_found', __( 'That Note identity does not exist.', 'axismundi-note' ) );
		}
		$seen[ $key ] = rawurldecode( (string) ( $parts[1] ?? '' ) );
	}
	$uuid = strtolower( trim( (string) ( $seen['ax_note'] ?? '' ) ) );
	if ( ! wp_is_uuid( $uuid ) || (string) ( $seen['ax_note'] ?? '' ) !== $uuid ) {
		return new WP_Error( 'ax_op_not_found', __( 'That Note identity does not exist.', 'axismundi-note' ) );
	}
	return $uuid;
}

/** Resolve an exact Note namespace claim while preserving every other OP source. */
function axismundi_note_resolve_current_source( $source ) {
	$uuid = axismundi_note_request_uuid();
	if ( null === $uuid ) {
		return $source;
	}
	if ( is_wp_error( $uuid ) ) {
		return $uuid;
	}
	$envelope = axismundi_note_get_by_uuid( $uuid );
	if ( ! is_array( $envelope ) ) {
		return new WP_Error( 'ax_op_not_found', __( 'That Note identity does not exist.', 'axismundi-note' ) );
	}
	$post = get_post( (int) $envelope['post_id'] );
	return new Axismundi_Note_Source( $envelope, $post instanceof WP_Post ? $post : null );
}
add_filter( 'axismundi_op_current_source', 'axismundi_note_resolve_current_source' );

/** Resolve the current shared audience for one active Note source. */
function axismundi_note_source_audience( Axismundi_Note_Source $source ) {
	$envelope = $source->get_envelope();
	$post     = $source->get_post();
	$actor    = axismundi_note_envelope_actor( $envelope );
	if ( $source->is_tombstone() ) {
		return array( 'public' => true, 'to' => array(), 'cc' => array() );
	}
	$language = axismundi_note_normalize_language( (string) ( $envelope['language_tag'] ?? '' ) );
	if ( ! $post instanceof WP_Post
		|| AXISMUNDI_NOTE_POST_TYPE !== $post->post_type
		|| 'publish' !== $post->post_status
		|| '' !== (string) $post->post_password
		|| empty( $envelope['attribution_locked_at'] )
		|| ! $actor instanceof Axismundi_Actor
		|| '' === $language || 'und' === $language
		|| ! function_exists( 'axismundi_act_resolve_audience' )
	) {
		return new WP_Error( 'ax_note_not_ready', __( 'The Note is not ready for public projection.', 'axismundi-note' ) );
	}
	return axismundi_act_resolve_audience( $actor, (string) $envelope['visibility'], axismundi_note_mentions( $post ) );
}

/** Whether anonymous negotiation may disclose this Note source. */
function axismundi_note_source_visible( Axismundi_Note_Source $source ) : bool {
	if ( $source->is_tombstone() ) {
		return true;
	}
	$audience = axismundi_note_source_audience( $source );
	return is_array( $audience ) && true === $audience['public'];
}

/** Transform one Note-owned source into Note or privacy-minimal Tombstone. */
function axismundi_note_transform_source( Axismundi_Note_Source $source ) {
	$envelope = $source->get_envelope();
	$id       = $source->get_uri();
	if ( $source->is_tombstone() ) {
		$object = array( 'id' => $id, 'type' => 'Tombstone', 'formerType' => 'Note' );
		if ( ! empty( $envelope['deleted_at'] ) ) {
			$timestamp = strtotime( (string) $envelope['deleted_at'] . ' UTC' );
			if ( false !== $timestamp ) {
				$object['deleted'] = gmdate( 'c', $timestamp );
			}
		}
		return $object;
	}

	$post     = $source->get_post();
	$audience = axismundi_note_source_audience( $source );
	if ( ! $post instanceof WP_Post || is_wp_error( $audience ) ) {
		return is_wp_error( $audience ) ? $audience : new WP_Error( 'ax_note_not_ready', __( 'The Note source is incomplete.', 'axismundi-note' ) );
	}
	$content  = function_exists( 'axismundi_op_render_post_content' ) ? axismundi_op_render_post_content( $post ) : (string) $post->post_content;
	$language = (string) $envelope['language_tag'];
	$object   = array(
		'id'           => $id,
		'type'         => 'Note',
		'attributedTo' => (string) $envelope['actor_uri'],
		'url'          => array( 'type' => 'Link', 'href' => $id, 'mediaType' => 'text/html' ),
		'content'      => $content,
		'contentMap'   => array( $language => $content ),
		'mediaType'    => 'text/html',
		'published'    => get_post_time( DATE_W3C, true, $post ),
		'updated'      => get_post_modified_time( DATE_W3C, true, $post ),
		'to'           => $audience['to'],
		'cc'           => $audience['cc'],
		'sensitive'    => ! empty( $envelope['is_sensitive'] ),
	);
	$tags = axismundi_note_mention_tags( $post, false );
	if ( ! empty( $tags ) ) {
		$object['tag'] = $tags;
	}
	foreach ( array( 'in_reply_to_uri' => 'inReplyTo', 'context_uri' => 'context' ) as $column => $member ) {
		if ( ! empty( $envelope[ $column ] ) ) {
			$object[ $member ] = (string) $envelope[ $column ];
		}
	}
	if ( $object['sensitive'] && '' !== trim( (string) $envelope['content_warning'] ) ) {
		$object['dcterms:subject'] = (string) $envelope['content_warning'];
	}
	return $object;
}

/** Register the Note transformer without adding a Note dependency to OP. */
function axismundi_note_register_transformer() : void {
	if ( ! function_exists( 'axismundi_op_register_object_transformer' ) ) {
		return;
	}
	axismundi_op_register_object_transformer(
		'local-note',
		array(
			'supports'   => static fn( $source ) : bool => $source instanceof Axismundi_Note_Source,
			'object_uri' => static fn( Axismundi_Note_Source $source ) : string => $source->get_uri(),
			'transform'  => 'axismundi_note_transform_source',
			'visible'    => 'axismundi_note_source_visible',
			'priority'   => 10,
		)
	);
}
add_action( 'axismundi_op_register_transformers', 'axismundi_note_register_transformer' );
