<?php
/**
 * Object vertical-slice regression (dev-only).
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__, 2 ) . '/axismundi-actors/includes/repository.php';
require_once dirname( __DIR__, 2 ) . '/axismundi-actors/includes/activitystreams-uri.php';
require_once dirname( __DIR__, 2 ) . '/axismundi-actors/includes/identity-registry.php';
require_once dirname( __DIR__ ) . '/includes/object-relations.php';
require_once dirname( __DIR__ ) . '/includes/object-registry.php';
require_once dirname( __DIR__ ) . '/includes/remote-objects.php';
require_once dirname( __DIR__ ) . '/includes/registry.php';
require_once dirname( __DIR__ ) . '/includes/renderer.php';
require_once dirname( __DIR__ ) . '/includes/post-article.php';

global $wpdb;
$ax_results      = array();
$ax_identity_ids = array();
$ax_attachment   = 0;
$ax_page         = 0;
$ax_actor_identity = 0;
$ax_remote_uri   = 'https://registry.remote.example/objects/audit-object';
$ax_remote_actor_uri = 'https://example.com/users/object-registry-fixture';
$ax_media_mode   = defined( 'AXISMUNDI_MEDIA_MODE_OPTION' ) ? get_option( AXISMUNDI_MEDIA_MODE_OPTION, '' ) : null;

function ax_object_registry_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

$ax_note_post = 0;
try {
	axismundi_actors_install();
	$installed = axismundi_op_install();
	$objects   = axismundi_op_objects_table();
	$remote    = axismundi_op_remote_objects_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture verifies the Object slice schema.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$objects}" );
	ax_object_registry_assert(
		$ax_results,
		'the Object vertical slice is keyed by the shared identity and carries source, type, and attribution shadows',
		$installed && in_array( 'source_owner', $columns, true ) && in_array( 'as2_type_observed_at', $columns, true ) && in_array( 'attributed_identity_id', $columns, true )
	);

	$uri   = home_url( '/?p=987654' );
	$first = axismundi_op_register_object(
		array(
			'canonical_uri' => $uri,
			'origin'        => 'local',
			'source_owner'  => 'wordpress-core',
			'source_kind'   => 'post',
			'source_ref'    => 987654,
			'as2_type'      => 'Note',
		)
	);
	if ( is_array( $first ) ) {
		$ax_identity_ids[] = (int) $first['identity_id'];
	}
	$second = axismundi_op_register_object(
		array(
			'canonical_uri' => $uri,
			'origin'        => 'local',
			'source_owner'  => 'axismundi-note',
			'source_kind'   => 'note-post',
			'source_ref'    => 987654,
			'as2_type'      => 'Question',
		)
	);
	$identity = is_array( $first ) ? axismundi_actors_get_identity( (int) $first['identity_id'] ) : null;
	ax_object_registry_assert(
		$ax_results,
		'a source can re-declare Note as Question without changing its shared identity',
		is_array( $first ) && is_array( $second ) && (int) $first['identity_id'] === (int) $second['identity_id'] && 'Question' === (string) $second['as2_type'] && is_array( $identity ) && $uri === (string) $identity['canonical_uri']
	);

	$menu = new WP_Post(
		(object) array(
			'ID' => 987655,
			'post_type' => 'nav_menu_item',
			'post_status' => 'publish',
		)
	);
	ax_object_registry_assert( $ax_results, 'an unlisted WordPress post type receives no Object registration profile', null === axismundi_op_local_object_profile( $menu ) );

	/*
	 * The contamination this registry was built to avoid, exercised through the
	 * resolver rather than through a hand-written row. `ax_note` stores both Notes
	 * and Questions and the type is decided by whether a poll row exists, so a
	 * resolver that answered from `post_type` would label every Question a Note --
	 * and the re-declaration check above would still pass, because it writes the
	 * registry row itself.
	 */
	$ax_note_post = 0;
	$ax_note_uuid = '';
	if ( defined( 'AXISMUNDI_NOTE_POST_TYPE' ) && function_exists( 'axismundi_note_questions_table' ) && function_exists( 'axismundi_note_table' ) ) {
		$ax_note_post = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'publish', 'post_title' => 'Object registry note fixture' ) );
		$ax_note_uuid = wp_generate_uuid4();
		$ax_actor_fixture_uri = 'https://registry.local.example/actors/note-fixture';
		$now = current_time( 'mysql', true );
		$note_fixture = array(
			'post_id' => $ax_note_post, 'local_uuid' => $ax_note_uuid,
			'actor_uri' => $ax_actor_fixture_uri, 'actor_uri_hash' => hash( 'sha256', $ax_actor_fixture_uri ),
			'visibility' => 'public', 'language_tag' => 'en', 'in_reply_to_uri' => '', 'in_reply_to_uri_hash' => '',
			'context_uri' => '', 'context_uri_hash' => '', 'mention_actor_uris_json' => '[]',
			'created_at' => $now, 'updated_at' => $now,
		);
		$existing_note = $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . axismundi_note_table() . ' WHERE post_id = %d', $ax_note_post ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned row check.
		if ( null === $existing_note ) {
			$wpdb->insert( axismundi_note_table(), $note_fixture ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- regression fixture row.
		} else {
			$wpdb->update( axismundi_note_table(), $note_fixture, array( 'post_id' => $ax_note_post ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- make the automatic writer's fixture row deterministic.
		}
		$ax_note_object = axismundi_op_local_object_profile( get_post( $ax_note_post ) );
		ax_object_registry_assert(
			$ax_results,
			'a note without a poll resolves as Note through the profile resolver',
			is_array( $ax_note_object ) && 'Note' === (string) ( $ax_note_object['type'] ?? '' )
		);
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- regression fixture row.
			axismundi_note_questions_table(),
			array( 'note_post_id' => $ax_note_post, 'mode' => 'oneOf', 'created_at' => $now, 'updated_at' => $now )
		);
		$ax_question_object = axismundi_op_local_object_profile( get_post( $ax_note_post ) );
		ax_object_registry_assert(
			$ax_results,
			'the same post resolves as Question once its poll row exists, because the source decides the type and the container does not',
			is_array( $ax_question_object ) && 'Question' === (string) ( $ax_question_object['type'] ?? '' )
		);
	}

	$ax_attachment = (int) wp_insert_post(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image/jpeg',
			'post_title'     => 'Object registry attachment fixture',
			'guid'           => home_url( '/wp-content/uploads/object-registry-attachment.jpg' ),
		)
	);
	update_post_meta( $ax_attachment, '_wp_attached_file', '2026/10/object-registry-attachment.jpg' );
	wp_update_attachment_metadata( $ax_attachment, array( 'width' => 640, 'height' => 480 ) );
	$attachment = $ax_attachment > 0 ? get_post( $ax_attachment ) : null;
	$media      = $attachment instanceof WP_Post ? axismundi_op_register_local_post_object( $attachment ) : null;
	if ( is_array( $media ) ) {
		$ax_identity_ids[] = (int) $media['identity_id'];
	}
	ax_object_registry_assert(
		$ax_results,
		'a core attachment registers as Image without the optional Media Library transformer',
		is_array( $media ) && 'Image' === (string) $media['as2_type'] && 'wordpress-core' === (string) $media['source_owner']
	);
	/*
	 * Uploading a file is not a publication, so an attachment with no recorded
	 * Actor is attributed to nobody. Deriving one from post_author reads as
	 * harmless and is wrong on any user with more than one Actor -- and wrong
	 * only after the object has been fetched by someone else. Measured: the
	 * derivation can be restored with one line and every other check here
	 * still passes, which is why this one exists.
	 */
	$ax_attachment_actor_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/post-article.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source to pin a default.
	ax_object_registry_assert(
		$ax_results,
		'an attachment with no recorded Actor is attributed to nobody rather than to its uploader',
		$attachment instanceof WP_Post
			&& '' === axismundi_op_attachment_actor_uri( $attachment )
			&& ( ! is_array( $media ) || '' === (string) ( $media['attributed_to_uri'] ?? '' ) )
			&& false !== strpos( $ax_attachment_actor_source, "apply_filters( 'axismundi_op_attachment_actor_uri', '', \$attachment" )
	);

	if ( defined( 'AXISMUNDI_MEDIA_MODE_OPTION' ) ) {
		update_option( AXISMUNDI_MEDIA_MODE_OPTION, 'core' );
	}
	$GLOBALS['axismundi_op_loaded']              = false;
	$GLOBALS['axismundi_op_object_transformers'] = array();
	$GLOBALS['axismundi_op_collection_transformers'] = array();
	$GLOBALS['axismundi_op_sequence']            = 0;
	$core_attachment = $attachment instanceof WP_Post ? axismundi_op_transform_object( $attachment ) : null;
	ax_object_registry_assert(
		$ax_results,
		'a core attachment projects without Media Library and does not infer attributedTo from its WordPress author',
		is_array( $core_attachment )
			&& 'Image' === (string) $core_attachment['type']
			&& ! isset( $core_attachment['attributedTo'] )
			&& (string) wp_get_attachment_url( $ax_attachment ) === (string) ( $core_attachment['url']['href'] ?? '' )
			&& 640 === (int) ( $core_attachment['width'] ?? 0 )
			&& 480 === (int) ( $core_attachment['height'] ?? 0 )
	);
	if ( defined( 'AXISMUNDI_MEDIA_MODE_OPTION' ) ) {
		update_option( AXISMUNDI_MEDIA_MODE_OPTION, $ax_media_mode );
	}
	$GLOBALS['axismundi_op_loaded']              = false;
	$GLOBALS['axismundi_op_object_transformers'] = array();
	$GLOBALS['axismundi_op_collection_transformers'] = array();
	$GLOBALS['axismundi_op_sequence']            = 0;

	$ax_page = (int) wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Object registry Page fixture',
			'post_content' => '<p>Core Page source.</p>',
		)
	);
	$page         = $ax_page > 0 ? get_post( $ax_page ) : null;
	$page_profile = $page instanceof WP_Post ? axismundi_op_local_object_profile( $page ) : null;
	$page_object  = $page instanceof WP_Post ? axismundi_op_transform_object( $page ) : null;
	if ( is_array( $page_profile ) ) {
		$page_registry = axismundi_op_register_local_post_object( $page );
		if ( is_array( $page_registry ) ) {
			$ax_identity_ids[] = (int) $page_registry['identity_id'];
		}
	}
	ax_object_registry_assert(
		$ax_results,
		'a core Page is explicitly registered and projects as Page without making every WordPress post type eligible',
		is_array( $page_profile ) && 'Page' === (string) $page_profile['type'] && is_array( $page_object ) && 'Page' === (string) $page_object['type']
	);

	$stored = axismundi_op_store_remote_object(
		array(
			'id'           => $ax_remote_uri,
			'type'         => 'Article',
			'attributedTo' => $ax_remote_actor_uri,
			'content'      => '<p>Remote body.</p>',
		)
	);
	$remote_object = is_array( $stored ) ? axismundi_op_get_object_by_identity( (int) $stored['identity_id'] ) : null;
	ax_object_registry_assert(
		$ax_results,
		'an Object fetched before its author is known stores its URI with a null attributed identity shadow',
		is_array( $stored ) && (int) $stored['identity_id'] > 0 && null === $stored['attributed_identity_id'] && is_array( $remote_object ) && null === $remote_object['attributed_identity_id']
	);
	$actor = axismundi_actors_upsert_remote(
		array(
			'uri'                => $ax_remote_actor_uri,
			'actor_type'         => 'Person',
			'preferred_username' => 'object_registry_fixture',
			'display_name'       => 'Object Registry Fixture',
			'profile_url'        => $ax_remote_actor_uri,
			'endpoints'          => array( 'inbox' => $ax_remote_actor_uri . '/inbox', 'outbox' => $ax_remote_actor_uri . '/outbox' ),
			'payload'            => array( 'id' => $ax_remote_actor_uri, 'type' => 'Person', 'preferredUsername' => 'object_registry_fixture' ),
		)
	);
	$ax_actor_identity = $actor instanceof Axismundi_Actor ? $actor->get_identity_id() : 0;
	$resolved_object   = is_array( $stored ) ? axismundi_op_get_object_by_identity( (int) $stored['identity_id'] ) : null;
	$resolved_id       = is_array( $resolved_object ) ? axismundi_op_resolve_object_attributed_identity( $resolved_object ) : null;
	$resolved_row      = is_array( $stored ) ? axismundi_op_get_object_by_identity( (int) $stored['identity_id'] ) : null;
	ax_object_registry_assert(
		$ax_results,
		'an Object resolves a later-known author into its shadow without rewriting the canonical attribution URI',
		$ax_actor_identity > 0 && $ax_actor_identity === $resolved_id && is_array( $resolved_row ) && $ax_remote_actor_uri === (string) $resolved_row['attributed_to_uri'] && $ax_actor_identity === (int) $resolved_row['attributed_identity_id']
	);
	$canonical_remote = axismundi_op_remote_object_uri( 'HTTPS://REGISTRY.REMOTE.EXAMPLE:443/objects/./canonical' );
	ax_object_registry_assert(
		$ax_results,
		'the remote cache and Object identity use the same canonical URI before hashing their shadows',
		'https://registry.remote.example/objects/canonical' === $canonical_remote
	);

	if ( is_array( $stored ) ) {
		$ax_identity_ids[] = (int) $stored['identity_id'];
		$wpdb->update( $remote, array( 'identity_id' => null, 'attributed_identity_id' => null ), array( 'id' => (int) $stored['id'] ), array( '%d', '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- simulate a pre-v15 cache row.
		update_option( AXISMUNDI_OP_DB_VERSION_OPTION, '14', false );
		$backfilled = axismundi_op_install();
		$row        = axismundi_op_get_remote_object( $ax_remote_uri );
		ax_object_registry_assert(
			$ax_results,
		'the v15 upgrade backfills remote Object and newly resolvable author identity shadows without publishing anything',
		$backfilled && is_array( $row ) && (int) $row['identity_id'] === (int) $stored['identity_id'] && $ax_actor_identity === (int) $row['attributed_identity_id']
		);
	}
} finally {
	if ( $ax_note_post > 0 ) {
		if ( function_exists( 'axismundi_note_questions_table' ) ) {
			$wpdb->delete( axismundi_note_questions_table(), array( 'note_post_id' => $ax_note_post ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		if ( function_exists( 'axismundi_note_table' ) ) {
			$wpdb->delete( axismundi_note_table(), array( 'post_id' => $ax_note_post ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		wp_delete_post( $ax_note_post, true );
	}
	if ( $ax_attachment > 0 ) {
		wp_delete_post( $ax_attachment, true );
	}
	if ( $ax_page > 0 ) {
		wp_delete_post( $ax_page, true );
	}
	if ( defined( 'AXISMUNDI_MEDIA_MODE_OPTION' ) ) {
		update_option( AXISMUNDI_MEDIA_MODE_OPTION, $ax_media_mode );
	}
	axismundi_op_delete_remote_object( $ax_remote_uri );
	foreach ( array_unique( array_filter( $ax_identity_ids ) ) as $identity_id ) {
		$wpdb->delete( axismundi_op_objects_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	if ( $ax_actor_identity > 0 ) {
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => $ax_actor_identity ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $ax_actor_identity ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $ax_actor_identity ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
}

$failed = count( array_filter( $ax_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_results ), $failed );
exit( $failed > 0 ? 1 : 0 );
