<?php
/**
 * Note federation readiness, source routing, and projection regression.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_nf_results   = array();
$ax_nf_post_ids  = array();
$ax_nf_user_ids  = array();
$ax_nf_actor_ids = array();
$ax_nf_attachment_ids = array();
$ax_nf_hashtag_term_id = 0;
$ax_nf_hashtag_name = 'NoteAudit' . strtolower( wp_generate_password( 8, false, false ) );
$ax_nf_get       = $_GET;
$ax_nf_uri       = $_SERVER['REQUEST_URI'] ?? null;

/** @param bool[] $results Results. */
function ax_nf_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Create one public local Actor with a locked handle. */
function ax_nf_actor( array &$users, array &$actors ) : ?Axismundi_Actor {
	$login = 'ax_nf_' . strtolower( wp_generate_password( 8, false, false ) );
	$uid   = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	if ( $uid <= 0 ) {
		return null;
	}
	$users[] = $uid;
	$actor   = axismundi_actors_ensure_for_user( $uid );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return null;
	}
	$actors[] = $actor->get_identity_id();
	axismundi_actors_register_handle( $actor->get_identity_id(), $login );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	axismundi_actors_set_default_language( $actor->get_identity_id(), 'en' );
	return axismundi_actors_get_by_uri( $actor->get_uri() );
}

try {
	$author    = ax_nf_actor( $ax_nf_user_ids, $ax_nf_actor_ids );
	$mentioned = ax_nf_actor( $ax_nf_user_ids, $ax_nf_actor_ids );
	$author_id = $author instanceof Axismundi_Actor ? (int) $author->get_local_user_id() : 0;
	$mention_uri = $mentioned instanceof Axismundi_Actor ? $mentioned->get_uri() : '';
	$attachment_id = (int) wp_insert_attachment(
		array(
			'post_title'     => 'Federated document',
			'post_excerpt'   => 'Attachment warning',
			'post_status'    => 'inherit',
			'post_author'    => $author_id,
			'post_mime_type' => 'application/pdf',
			'guid'           => home_url( '/wp-content/uploads/ax-note-fixture.pdf' ),
		)
	);
	$ax_nf_attachment_ids[] = $attachment_id;
	update_post_meta( $attachment_id, '_ax_media_visibility', 'public' );
	axismundi_media_set_sensitive_state( $attachment_id, 'self_marked', $author_id );

	$post_id = (int) wp_insert_post(
		array(
			'post_type'     => AXISMUNDI_NOTE_POST_TYPE,
			'post_status'   => 'draft',
			'post_author'   => $author_id,
			'post_title'    => 'Federated Note',
			'post_content'  => '<p>Hello <a class="mention" href="' . esc_url( $mention_uri ) . '">@friend</a>.</p><iframe src="https://evil.example/embed"></iframe>',
		)
	);
	$ax_nf_post_ids[] = $post_id;
	axismundi_note_save( $post_id, array( 'visibility' => 'public', 'quote_policy' => 'anyone', 'sensitive' => false, 'content_warning' => '' ) );
	axismundi_note_replace_attachments( $post_id, array( $attachment_id ) );
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$post     = get_post( $post_id );
	$envelope = axismundi_note_get( $post_id );
	$hashtag  = function_exists( 'axismundi_op_ensure_hashtag_term' ) ? axismundi_op_ensure_hashtag_term( $ax_nf_hashtag_name ) : null;
	if ( $hashtag instanceof WP_Term ) {
		$ax_nf_hashtag_term_id = (int) $hashtag->term_id;
		wp_set_object_terms( $post_id, array( (int) $hashtag->term_id ), AXISMUNDI_OP_HASHTAG_TAXONOMY );
	}
	ax_nf_assert(
		$ax_nf_results,
		'first publication snapshots language and locks a valid local Actor only after strict preparation',
		is_array( $envelope ) && 'en' === $envelope['language_tag'] && ! empty( $envelope['attribution_locked_at'] ) && $author instanceof Axismundi_Actor && $author->get_uri() === $envelope['actor_uri']
	);
	$uuid = is_array( $envelope ) ? (string) $envelope['local_uuid'] : '';
	$id   = axismundi_note_object_uri( $uuid );
	$mention_edges = function_exists( 'axismundi_op_object_mentions_for_actor' ) ? axismundi_op_object_mentions_for_actor( $mention_uri ) : array();
	ax_nf_assert(
		$ax_nf_results,
		'a committed Note indexes its inline Mention as a canonical Object-to-Actor edge',
		1 === count( $mention_edges ) && $id === (string) $mention_edges[0]['source_object_uri'] && 'inline' === (string) $mention_edges[0]['origin']
	);
	$_GET = array( 'ax_note' => $uuid );
	$_SERVER['REQUEST_URI'] = (string) wp_parse_url( $id, PHP_URL_PATH ) . '?' . (string) wp_parse_url( $id, PHP_URL_QUERY );
	$preexisting_source = (object) array( 'kind' => 'static-front-page' );
	$source             = apply_filters( 'axismundi_op_current_source', $preexisting_source );
	ax_nf_assert( $ax_nf_results, 'the exact canonical Note request overrides a pre-resolved WordPress source with an opaque Note-owned source', $source instanceof Axismundi_Note_Source && $id === $source->get_uri() );

	$_GET = array();
	$_SERVER['REQUEST_URI'] = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$preserved = apply_filters( 'axismundi_op_current_source', $preexisting_source );
	ax_nf_assert( $ax_nf_results, 'the Note source adapter preserves an existing source outside its owned namespace', $preexisting_source === $preserved );
	$_GET = array( 'ax_note' => $uuid );
	$_SERVER['REQUEST_URI'] = (string) wp_parse_url( $id, PHP_URL_PATH ) . '?' . (string) wp_parse_url( $id, PHP_URL_QUERY );

	add_filter( 'axismundi_op_use_the_content', '__return_false' );
	$object = $source instanceof Axismundi_Note_Source ? axismundi_op_transform_object( $source ) : null;
	remove_filter( 'axismundi_op_use_the_content', '__return_false' );
	ax_nf_assert(
		$ax_nf_results,
		'a public Note projects one sanitized scalar/contentMap snapshot with matching audience and Mention',
		is_array( $object )
		&& 'Note' === $object['type']
		&& $id === $object['id']
		&& 'Federated Note' === $object['name']
		&& 'Federated Note' === $object['nameMap']['en']
		&& 'en' === array_key_first( $object['contentMap'] )
		&& (string) $object['content'] === (string) $object['contentMap']['en']
		&& false === stripos( (string) $object['content'], '<iframe' )
		&& isset( $object['attachment'][0] )
		&& 'Document' === $object['attachment'][0]['type']
		&& true === $object['attachment'][0]['sensitive']
		&& in_array( axismundi_act_public_audience_uri(), $object['to'], true )
		&& isset( $object['tag'][0]['name'], $object['tag'][0]['href'] )
		&& $mention_uri === $object['tag'][0]['href']
		&& true === $object['sensitive']
		&& axismundi_act_public_audience_uri() === $object['interactionPolicy']['canQuote']['automaticApproval']
		&& ! isset( $object['dcterms:subject'] )
	);
	ax_nf_assert(
		$ax_nf_results,
		'an explicitly assigned shared hashtag serializes beside Mention tags without changing the Note audience',
		is_array( $object )
			&& $hashtag instanceof WP_Term
			&& in_array( array( 'type' => 'Hashtag', 'name' => '#' . $ax_nf_hashtag_name, 'href' => get_term_link( $hashtag ) ), (array) ( $object['tag'] ?? array() ), true )
	);
	ax_nf_assert(
		$ax_nf_results,
		'a sensitive attachment elevates the federated Note flag without mutating the authored envelope',
		is_array( $envelope ) && empty( $envelope['is_sensitive'] ) && true === ( $object['sensitive'] ?? false )
	);
	axismundi_note_save( $post_id, array( 'sensitive' => true, 'content_warning' => 'CW' ) );
	$warning_source = new Axismundi_Note_Source( axismundi_note_get( $post_id ), get_post( $post_id ) );
	$warning_object = axismundi_op_transform_object( $warning_source );
	ax_nf_assert( $ax_nf_results, 'an authored Note warning remains the top-level sensitive subject', is_array( $warning_object ) && true === $warning_object['sensitive'] && 'CW' === $warning_object['dcterms:subject'] );
	ax_nf_assert( $ax_nf_results, 'a public Note appears in anonymous Media Library reverse provenance through the custom-route visibility seam', 1 === count( axismundi_media_relations_used_in( $attachment_id, 0 ) ) );

	// An addressed-only audience remains a valid stored object but is not anonymously projectable.
	axismundi_note_save( $post_id, array( 'visibility' => 'followers' ) );
	$hidden_source = new Axismundi_Note_Source( axismundi_note_get( $post_id ), get_post( $post_id ) );
	$hidden = axismundi_op_transform_object( $hidden_source );
	ax_nf_assert( $ax_nf_results, 'followers-only Note JSON-LD fails closed as not public', is_wp_error( $hidden ) && 'ax_op_not_public' === $hidden->get_error_code() );
	axismundi_note_save( $post_id, array( 'visibility' => 'public' ) );

	// A claimed malformed or unknown namespace is an explicit 404 outcome, not null.
	$_SERVER['REQUEST_URI'] .= '&ax_note=' . $uuid;
	$duplicate = apply_filters( 'axismundi_op_current_source', null );
	$unknown_uuid = wp_generate_uuid4();
	$_GET = array( 'ax_note' => $unknown_uuid );
	$_SERVER['REQUEST_URI'] = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) . '?ax_note=' . $unknown_uuid;
	$unknown = apply_filters( 'axismundi_op_current_source', null );
	ax_nf_assert( $ax_nf_results, 'a duplicated or unknown claimed Note identity maps to ax_op_not_found', is_wp_error( $duplicate ) && 'ax_op_not_found' === $duplicate->get_error_code() && is_wp_error( $unknown ) && 'ax_op_not_found' === $unknown->get_error_code() );

	// Strict first publication rejects an unresolved recipient and never sets readiness.
	$bad_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $author_id, 'post_title' => 'Bad mention' ) );
	$ax_nf_post_ids[] = $bad_id;
	axismundi_note_save( $bad_id, array( 'mention_actor_uris' => array( 'https://unknown.example/actor' ) ) );
	wp_update_post( array( 'ID' => $bad_id, 'post_status' => 'publish' ) );
	$bad_envelope = axismundi_note_get( $bad_id );
	$bad_source   = new Axismundi_Note_Source( $bad_envelope, get_post( $bad_id ) );
	$bad_object   = axismundi_op_transform_object( $bad_source );
	ax_nf_assert( $ax_nf_results, 'failed strict preparation leaves the Note unlocked and anonymously unavailable', empty( $bad_envelope['attribution_locked_at'] ) && is_wp_error( $bad_object ) && 'ax_op_not_public' === $bad_object->get_error_code() );

	// Permanent deletion leaves an envelope-only source that projects privacy-minimal 410.
	wp_delete_post( $post_id, true );
	$tombstone_envelope = axismundi_note_get_by_uuid( $uuid );
	$tombstone_source   = new Axismundi_Note_Source( $tombstone_envelope, null );
	$tombstone          = axismundi_op_transform_object( $tombstone_source );
	ax_nf_assert(
		$ax_nf_results,
		'an envelope-only Tombstone is privacy-minimal and carries generic HTTP 410 semantics',
		is_array( $tombstone )
		&& 'Tombstone' === $tombstone['type']
		&& 'Note' === $tombstone['formerType']
		&& 410 === axismundi_op_object_http_status( $tombstone )
		&& ! isset( $tombstone['content'], $tombstone['contentMap'], $tombstone['attributedTo'], $tombstone['url'], $tombstone['to'], $tombstone['cc'] )
	);
} finally {
	$_GET = $ax_nf_get;
	if ( null === $ax_nf_uri ) {
		unset( $_SERVER['REQUEST_URI'] );
	} else {
		$_SERVER['REQUEST_URI'] = $ax_nf_uri;
	}
	foreach ( array_unique( $ax_nf_post_ids ) as $post_id ) {
		$wpdb->delete( axismundi_note_table(), array( 'post_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( get_post( (int) $post_id ) instanceof WP_Post ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_unique( $ax_nf_attachment_ids ) as $attachment_id ) {
		if ( get_post( (int) $attachment_id ) instanceof WP_Post ) {
			wp_delete_attachment( (int) $attachment_id, true );
		}
	}
	if ( $ax_nf_hashtag_term_id > 0 ) {
		wp_delete_term( $ax_nf_hashtag_term_id, AXISMUNDI_OP_HASHTAG_TAXONOMY );
	}
	foreach ( array_unique( $ax_nf_actor_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( ! empty( $ax_nf_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_nf_user_ids ) as $user_id ) {
			if ( get_userdata( (int) $user_id ) ) {
				wp_delete_user( (int) $user_id );
			}
		}
	}
}

$ax_nf_failures = count( array_filter( $ax_nf_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_nf_results ), $ax_nf_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_nf_failures > 0 ? 1 : 0 );
}
exit( $ax_nf_failures > 0 ? 1 : 0 );
