<?php
/**
 * URI-keyed thread graph: one direct-parent edge per reply, local or remote.
 *
 * This is a rebuildable index, not an authority: `wp_comments` is not replaced and
 * no envelope moves here. A child always stores only its direct parent URI --
 * root/depth are a best-effort convenience computed at write time, never load-bearing
 * for correctness, and never require a full ancestry walk. A child with no parent
 * (a root object) has no row at all; only replies are indexed. An edge survives its
 * child being deleted (tombstoned) or its parent being unknown: `resolution_state`
 * records only whether the parent could be resolved to real content right now, and
 * flips from `unresolved` to `resolved` automatically once that parent becomes known,
 * without ever discarding the edge in between.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** Thread edge table name. */
function axismundi_op_thread_edges_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_thread_edges';
}

/** Install and verify the rebuildable thread-edge index. */
function axismundi_op_install_thread_edges() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = axismundi_op_thread_edges_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			child_uri text NOT NULL,
			child_uri_hash char(64) NOT NULL,
			parent_uri text NOT NULL,
			parent_uri_hash char(64) NOT NULL,
			root_uri text DEFAULT NULL,
			root_uri_hash char(64) DEFAULT NULL,
			depth int(10) unsigned DEFAULT NULL,
			resolution_state varchar(16) NOT NULL DEFAULT 'unresolved',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY child_uri_hash (child_uri_hash),
			KEY parent_uri_hash (parent_uri_hash),
			KEY root_uri_hash (root_uri_hash)
		) ENGINE=InnoDB {$charset};"
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom schema verification.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom index verification.
	$identity = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'child_uri_hash'", ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom engine verification.
	$engine = (string) $wpdb->get_var( "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'" );

	return in_array( 'parent_uri_hash', $columns, true )
		&& in_array( 'resolution_state', $columns, true )
		&& in_array( 'root_uri_hash', $columns, true )
		&& ! empty( $identity ) && 0 === (int) $identity[0]['Non_unique']
		&& 'InnoDB' === $engine;
}

/** Fetch one edge row by its exact child URI. */
function axismundi_op_get_thread_edge( string $child_uri ) : ?array {
	global $wpdb;
	$child = axismundi_op_relation_uri( $child_uri );
	if ( '' === $child ) {
		return null;
	}
	$table = axismundi_op_thread_edges_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed exact-URI lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE child_uri_hash = %s AND child_uri = %s", hash( 'sha256', $child ), $child ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/** The direct parent URI of one child, or '' when it has none on record. */
function axismundi_op_get_thread_parent_uri( string $child_uri ) : string {
	$edge = axismundi_op_get_thread_edge( $child_uri );
	return is_array( $edge ) ? (string) $edge['parent_uri'] : '';
}

/** Direct reply (child) URIs for one parent, oldest first. */
function axismundi_op_get_thread_reply_uris( string $parent_uri, int $limit = 50 ) : array {
	global $wpdb;
	$parent = axismundi_op_relation_uri( $parent_uri );
	if ( '' === $parent ) {
		return array();
	}
	$table = axismundi_op_thread_edges_table();
	$limit = max( 1, min( 200, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed exact-URI lookup.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT child_uri, parent_uri FROM {$table} WHERE parent_uri_hash = %s AND parent_uri = %s ORDER BY id ASC LIMIT %d", hash( 'sha256', $parent ), $parent, $limit ), ARRAY_A );
	$uris  = array();
	foreach ( $rows as $row ) {
		if ( hash_equals( $parent, (string) $row['parent_uri'] ) ) {
			$uris[] = (string) $row['child_uri'];
		}
	}
	return $uris;
}

/**
 * Flip any edge waiting on one now-known parent from unresolved to resolved.
 *
 * This is the only place `resolution_state` changes after it is first written: a
 * child's edge is never dropped while its parent is unknown, so once that parent
 * becomes resolvable (locally authored, or freshly cached from a remote payload)
 * every edge already pointing at it catches up without re-deriving anything else.
 */
function axismundi_op_reconcile_unresolved_thread_children( string $newly_known_uri ) : void {
	global $wpdb;
	$parent = axismundi_op_relation_uri( $newly_known_uri );
	if ( '' === $parent ) {
		return;
	}
	$table       = axismundi_op_thread_edges_table();
	$parent_edge = axismundi_op_get_thread_edge( $parent );
	$root        = is_array( $parent_edge ) && ! empty( $parent_edge['root_uri'] ) ? (string) $parent_edge['root_uri'] : $parent;
	$depth       = is_array( $parent_edge ) && null !== $parent_edge['depth'] ? (int) $parent_edge['depth'] + 1 : 1;
	$now         = current_time( 'mysql', true );
	$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed exact-URI reconciliation.
		$wpdb->prepare(
			"UPDATE {$table} SET root_uri = %s, root_uri_hash = %s, depth = %d, resolution_state = 'resolved', updated_at = %s WHERE parent_uri_hash = %s AND parent_uri = %s AND resolution_state = 'unresolved'",
			$root,
			hash( 'sha256', $root ),
			$depth,
			$now,
			hash( 'sha256', $parent ),
			$parent
		)
	);
}

/**
 * Record, update, or clear one child's direct-parent edge.
 *
 * An empty parent removes any standing edge (the object is no longer, or never
 * was, a reply). A non-empty parent upserts one row: root/depth inherit from the
 * parent's own edge when it has one, otherwise default to treating the parent
 * itself as the root at depth 1 -- a best-effort convenience, not a full ancestry
 * walk, so it can go stale if the parent later turns out to have its own parent
 * discovered afterward. `resolution_state` reflects only whether the parent can be
 * resolved to real content right now via `axismundi_op_resolve_source_by_uri()`.
 */
function axismundi_op_record_thread_edge( string $child_uri, string $parent_uri ) : bool {
	global $wpdb;
	$child  = axismundi_op_relation_uri( $child_uri );
	$parent = axismundi_op_relation_uri( $parent_uri );
	if ( '' === $child ) {
		return false;
	}
	$table = axismundi_op_thread_edges_table();
	if ( '' === $parent ) {
		$ok = false !== $wpdb->delete( $table, array( 'child_uri_hash' => hash( 'sha256', $child ), 'child_uri' => $child ), array( '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- rebuildable index cleanup.
		if ( $ok ) {
			// This object itself becoming known (even parentless, i.e. a root) can be
			// exactly what an existing unresolved child was waiting on.
			axismundi_op_reconcile_unresolved_thread_children( $child );
		}
		return $ok;
	}
	if ( hash_equals( $child, $parent ) ) {
		return false;
	}

	$resolvable  = null !== axismundi_op_resolve_source_by_uri( $parent );
	$parent_edge = axismundi_op_get_thread_edge( $parent );
	$root        = $resolvable
		? ( is_array( $parent_edge ) && ! empty( $parent_edge['root_uri'] ) ? (string) $parent_edge['root_uri'] : $parent )
		: '';
	$depth       = $resolvable
		? ( is_array( $parent_edge ) && null !== $parent_edge['depth'] ? (int) $parent_edge['depth'] + 1 : 1 )
		: null;
	$now = current_time( 'mysql', true );
	$row = array(
		'child_uri'         => $child,
		'child_uri_hash'    => hash( 'sha256', $child ),
		'parent_uri'        => $parent,
		'parent_uri_hash'   => hash( 'sha256', $parent ),
		'root_uri'          => '' !== $root ? $root : null,
		'root_uri_hash'     => '' !== $root ? hash( 'sha256', $root ) : null,
		'depth'             => $depth,
		'resolution_state'  => $resolvable ? 'resolved' : 'unresolved',
		'updated_at'        => $now,
	);
	$existing = axismundi_op_get_thread_edge( $child );
	$ok       = is_array( $existing )
		? false !== $wpdb->update( $table, $row, array( 'id' => (int) $existing['id'] ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- rebuildable index replacement.
		: false !== $wpdb->insert( $table, array_merge( $row, array( 'created_at' => $now ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- rebuildable index insertion.
	if ( $ok ) {
		axismundi_op_reconcile_unresolved_thread_children( $child );
	}
	return $ok;
}

/** Index the direct-parent edge for one finalized committed local Object. */
function axismundi_op_index_thread_edge_from_activity( Axismundi_Activity $activity ) : void {
	if ( ! in_array( $activity->get_type(), array( 'Create', 'Update' ), true ) || 'outbound' !== $activity->get_direction() ) {
		return;
	}
	$object = $activity->get_payload()['object'] ?? null;
	if ( ! is_array( $object ) || array_is_list( $object ) ) {
		return;
	}
	$child  = axismundi_op_relation_uri( $object['id'] ?? '' );
	$parent = axismundi_op_relation_uri( $object['inReplyTo'] ?? '' );
	if ( '' !== $child ) {
		axismundi_op_record_thread_edge( $child, $parent );
	}
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_op_index_thread_edge_from_activity', 20 );

/**
 * Index the direct-parent edge for one freshly stored remote Object observation.
 *
 * A Tombstone payload carries no `inReplyTo` of its own, so re-storing an object as
 * a Tombstone must never be read as "this object turned out not to be a reply" --
 * that would erase a standing edge on exactly the event (deletion) this whole index
 * is built to survive. Only an active payload's own `inReplyTo` is authoritative.
 */
function axismundi_op_index_thread_edge_from_remote_object( array $row ) : void {
	if ( 'tombstone' === (string) ( $row['object_status'] ?? '' ) ) {
		return;
	}
	$child  = axismundi_op_relation_uri( $row['object_uri'] ?? '' );
	$parent = axismundi_op_relation_uri( $row['in_reply_to_uri'] ?? '' );
	if ( '' !== $child ) {
		axismundi_op_record_thread_edge( $child, $parent );
	}
}

/** Opaque view-model source wrapping one cached remote-object observation row. */
final class Axismundi_Op_Remote_Source {
	/** @var array<string,mixed> */
	private array $row;

	/** @param array<string,mixed> $row Remote-object cache row (with decoded `payload`). */
	public function __construct( array $row ) {
		$this->row = $row;
	}

	/** @return array<string,mixed> */
	public function get_row() : array {
		return $this->row;
	}
}

/**
 * Resolve one URI to whatever source (local or remote-cached) represents it, or null.
 *
 * A product registers through the `axismundi_op_resolve_source_by_uri` filter,
 * mirroring `axismundi_op_current_source`'s fallback-only contract but keyed on an
 * explicit URI instead of the current request, so this works for an arbitrary
 * thread-edge parent or child. OP's own remote-object cache is the fallback no
 * product needs to declare, wrapped so the view-model registry can recognize it.
 *
 * @return mixed|null A source `axismundi_op_object_view_model()` accepts, or null.
 */
function axismundi_op_resolve_source_by_uri( string $uri ) {
	$uri = axismundi_op_relation_uri( $uri );
	if ( '' === $uri ) {
		return null;
	}
	/**
	 * Filter one URI into its owning product's source, fallback-only.
	 *
	 * @since 0.0.36
	 * @param mixed|null $source Existing resolution; a product returns its own source
	 *                           only when it recognizes this exact URI, otherwise it
	 *                           must return `$source` unchanged.
	 * @param string     $uri    Canonical object URI.
	 */
	$source = apply_filters( 'axismundi_op_resolve_source_by_uri', null, $uri );
	if ( null !== $source ) {
		return $source;
	}
	$cached = function_exists( 'axismundi_op_remote_object_get' ) ? axismundi_op_remote_object_get( $uri ) : null;
	return is_array( $cached ) ? new Axismundi_Op_Remote_Source( $cached ) : null;
}

/** Normalize one cached remote-object row into the neutral OP view model. */
function axismundi_op_remote_source_view_model( $source ) : ?array {
	if ( ! $source instanceof Axismundi_Op_Remote_Source ) {
		return null;
	}
	$row = $source->get_row();
	$id  = (string) ( $row['object_uri'] ?? '' );
	if ( '' === $id ) {
		return null;
	}
	if ( 'tombstone' === (string) ( $row['object_status'] ?? '' ) ) {
		return array( 'id' => $id, 'type' => 'Tombstone', 'status' => 'tombstone' );
	}
	$author_uri = (string) ( $row['attributed_to_uri'] ?? '' );
	$actor      = '' !== $author_uri && function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( $author_uri ) : null;
	$name       = $actor instanceof Axismundi_Actor ? $actor->get_display_name() : '';
	$handle     = $actor instanceof Axismundi_Actor && function_exists( 'axismundi_actors_federated_mention_name' ) ? axismundi_actors_federated_mention_name( $actor ) : '';
	return array(
		'id'              => $id,
		'type'            => (string) ( $row['object_type'] ?? 'Object' ),
		'status'          => 'active',
		'object_uri'      => $id,
		'language'        => (string) ( $row['content_language'] ?? '' ),
		'title'           => (string) ( $row['name'] ?? '' ),
		'author'          => array(
			'name'   => '' !== $name ? $name : ( '' !== $handle ? ltrim( $handle, '@' ) : '' ),
			'handle' => $handle,
			'url'    => $author_uri,
		),
		'content_html'    => wp_kses_post( (string) ( $row['content'] ?? '' ) ),
		'published'       => '' !== (string) ( $row['published_at'] ?? '' ) ? gmdate( 'c', strtotime( (string) $row['published_at'] . ' UTC' ) ) : '',
		'updated'         => '' !== (string) ( $row['remote_updated_at'] ?? '' ) ? gmdate( 'c', strtotime( (string) $row['remote_updated_at'] . ' UTC' ) ) : '',
		'sensitive'       => ! empty( $row['is_sensitive'] ),
		'content_warning' => (string) ( $row['summary'] ?? '' ),
		'attachments'     => array(),
	);
}

/** Register OP's own remote-cache view-model adapter, the fallback no product owns. */
function axismundi_op_register_remote_source_view_model() : void {
	if ( function_exists( 'axismundi_op_register_object_view_model' ) ) {
		axismundi_op_register_object_view_model(
			'remote-cache',
			array(
				'supports'  => static fn( $source ) : bool => $source instanceof Axismundi_Op_Remote_Source,
				'transform' => 'axismundi_op_remote_source_view_model',
				'priority'  => 90,
			)
		);
	}
}
add_action( 'init', 'axismundi_op_register_remote_source_view_model', 5 );

/**
 * Whether one resolved source may be disclosed to an anonymous thread viewer.
 *
 * A cached remote observation is already this site's own legitimately received
 * disclosure and is not re-gated here. A local source must clear its own product's
 * registered object-transformer `visible` predicate -- the same public/unlisted-only
 * gate the anonymous JSON-LD and HTML routes already enforce for that object -- so a
 * followers-only or mentioned-only local reply or parent can never leak through
 * another object's thread display. A local-looking source no registered transformer
 * recognizes fails closed.
 */
function axismundi_op_source_publicly_visible( $source ) : bool {
	if ( $source instanceof Axismundi_Op_Remote_Source ) {
		return true;
	}
	$transformer = axismundi_op_resolve_object_transformer( $source );
	if ( null === $transformer || null === ( $transformer['visible'] ?? null ) ) {
		return false;
	}
	try {
		return true === call_user_func( $transformer['visible'], $source );
	} catch ( \Throwable $error ) {
		return false;
	}
}

/**
 * Reply view models for one parent URI, local or remote, tombstone-aware.
 *
 * @return array<int,array<string,mixed>>
 */
function axismundi_op_get_reply_view_models( string $parent_uri, int $limit = 50 ) : array {
	$models = array();
	foreach ( axismundi_op_get_thread_reply_uris( $parent_uri, $limit ) as $child_uri ) {
		$source = axismundi_op_resolve_source_by_uri( $child_uri );
		if ( null === $source || ! axismundi_op_source_publicly_visible( $source ) ) {
			continue;
		}
		$model = axismundi_op_object_view_model( $source );
		if ( is_array( $model ) ) {
			$models[] = $model;
		}
	}
	return $models;
}

/** The parent's view model for one child URI, or null when it has no recorded parent, it cannot be resolved, or it is not publicly visible. */
function axismundi_op_get_parent_view_model( string $child_uri ) : ?array {
	$parent_uri = axismundi_op_get_thread_parent_uri( $child_uri );
	if ( '' === $parent_uri ) {
		return null;
	}
	$source = axismundi_op_resolve_source_by_uri( $parent_uri );
	if ( null === $source || ! axismundi_op_source_publicly_visible( $source ) ) {
		return null;
	}
	return axismundi_op_object_view_model( $source );
}

/** Render one compact reply-list item, tombstone-aware. */
function axismundi_op_render_thread_item( array $model, string $children_html = '' ) : string {
	$children = '' !== $children_html ? '<ol class="axismundi-thread__list axismundi-thread__list--nested">' . $children_html . '</ol>' : '';
	if ( 'tombstone' === (string) ( $model['status'] ?? '' ) ) {
		return '<li class="axismundi-thread__item axismundi-thread__item--tombstone">' . esc_html__( 'This reply has been deleted.', 'axismundi-object-projections' ) . $children . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Nested items are escaped by this renderer.
	}
	$uri     = (string) ( $model['object_uri'] ?? '' );
	$author  = axismundi_op_object_view_author( $model );
	$excerpt = wp_trim_words( wp_strip_all_tags( (string) ( $model['content_html'] ?? '' ) ), 30 );
	$body    = '<div class="axismundi-thread__excerpt">' . esc_html( $excerpt ) . '</div>';
	if ( '' !== $uri ) {
		$body = '<a class="axismundi-thread__link" href="' . esc_url( $uri ) . '">' . $body . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $body escaped above.
	}
	return '<li class="axismundi-thread__item">' . $author . $body . $children . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parts escaped above and nested output is recursively rendered here.
}

/**
 * Render a bounded reply tree from one parent URI.
 *
 * The graph stores exactly one direct parent per child, but defensive cycle
 * detection remains necessary for malformed remote observations. The same
 * shared `remaining` counter bounds an entire tree rather than each level.
 *
 * @param string[] $ancestors Canonical URIs already on this branch.
 * @return array{html:string,count:int,truncated:bool}
 */
function axismundi_op_render_reply_tree( string $parent_uri, int &$remaining, array $ancestors = array() ) : array {
	if ( $remaining <= 0 ) {
		return array( 'html' => '', 'count' => 0, 'truncated' => false );
	}
	$uris      = axismundi_op_get_thread_reply_uris( $parent_uri, min( 200, $remaining + 1 ) );
	$truncated = count( $uris ) > $remaining;
	$items     = array();
	$count     = 0;
	foreach ( $uris as $child_uri ) {
		if ( $remaining <= 0 ) {
			$truncated = true;
			break;
		}
		if ( in_array( $child_uri, $ancestors, true ) ) {
			continue;
		}
		$source = axismundi_op_resolve_source_by_uri( $child_uri );
		if ( null === $source || ! axismundi_op_source_publicly_visible( $source ) ) {
			continue;
		}
		$model = axismundi_op_object_view_model( $source );
		if ( ! is_array( $model ) ) {
			continue;
		}
		--$remaining;
		if ( $remaining > 0 ) {
			$branch = axismundi_op_render_reply_tree( $child_uri, $remaining, array_merge( $ancestors, array( $child_uri ) ) );
		} else {
			$branch = array(
				'html'      => '',
				'count'     => 0,
				'truncated' => ! empty( axismundi_op_get_thread_reply_uris( $child_uri, 1 ) ),
			);
		}
		$items[] = axismundi_op_render_thread_item( $model, $branch['html'] );
		++$count;
		$count += $branch['count'];
		$truncated = $truncated || $branch['truncated'];
	}
	return array( 'html' => implode( '', $items ), 'count' => $count, 'truncated' => $truncated );
}

/** Render the "in reply to" context line for the request's current object view model. */
function axismundi_op_render_reply_context_block() : string {
	$model = axismundi_op_current_object_view_model();
	$uri   = is_array( $model ) ? (string) ( $model['object_uri'] ?? '' ) : '';
	if ( '' === $uri ) {
		return '';
	}
	$parent = axismundi_op_get_parent_view_model( $uri );
	if ( null === $parent ) {
		return '';
	}
	if ( 'tombstone' === (string) ( $parent['status'] ?? '' ) ) {
		return '<p class="axismundi-thread__context axismundi-thread__context--tombstone">' . esc_html__( 'In reply to a deleted post.', 'axismundi-object-projections' ) . '</p>';
	}
	$parent_uri = (string) ( $parent['object_uri'] ?? '' );
	$author     = trim( (string) ( $parent['author']['name'] ?? '' ) );
	$label      = '' !== $author
		/* translators: %s: the original author's display name. */
		? sprintf( __( 'In reply to %s', 'axismundi-object-projections' ), $author )
		: __( 'In reply to', 'axismundi-object-projections' );
	$link = '' !== $parent_uri ? '<a href="' . esc_url( $parent_uri ) . '">' . esc_html( $label ) . '</a>' : esc_html( $label );
	return '<p class="axismundi-thread__context">' . $link . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $link escaped above.
}

/** Render the direct-replies list for the request's current object view model. */
function axismundi_op_render_replies_block() : string {
	$model = axismundi_op_current_object_view_model();
	$uri   = is_array( $model ) ? (string) ( $model['object_uri'] ?? '' ) : '';
	if ( '' === $uri || 'tombstone' === (string) ( $model['status'] ?? '' ) ) {
		return '';
	}
	$remaining = 50;
	$tree      = axismundi_op_render_reply_tree( $uri, $remaining, array( $uri ) );
	if ( 0 === $tree['count'] ) {
		return '';
	}
	$notice = $tree['truncated']
		? '<p class="axismundi-thread__notice">' . esc_html__( 'Some replies are not shown in this preview.', 'axismundi-object-projections' ) . '</p>'
		: '';
	return '<div class="axismundi-thread axismundi-thread--replies">'
		. '<h2 class="axismundi-thread__heading">' . esc_html( sprintf(
			/* translators: %d: number of replies. */
			_n( '%d Reply', '%d Replies', $tree['count'], 'axismundi-object-projections' ),
			$tree['count']
		) ) . '</h2>'
		. '<ol class="axismundi-thread__list">' . $tree['html'] . '</ol>'
		. $notice
		. '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Items escaped by axismundi_op_render_thread_item().
}

/** Register the server-rendered thread context and replies blocks (no editor script). */
function axismundi_op_register_thread_blocks() : void {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	register_block_type(
		'axismundi/reply-context',
		array(
			'api_version'     => 3,
			'title'           => __( 'Axismundi Reply Context', 'axismundi-object-projections' ),
			'category'        => 'theme',
			'render_callback' => 'axismundi_op_render_reply_context_block',
			'supports'        => array( 'html' => false, 'inserter' => false ),
		)
	);
	register_block_type(
		'axismundi/replies',
		array(
			'api_version'     => 3,
			'title'           => __( 'Axismundi Replies', 'axismundi-object-projections' ),
			'category'        => 'theme',
			'render_callback' => 'axismundi_op_render_replies_block',
			'supports'        => array( 'html' => false, 'inserter' => false ),
		)
	);
}
add_action( 'init', 'axismundi_op_register_thread_blocks' );
