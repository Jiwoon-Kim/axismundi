<?php
/** Public application queries over the rebuildable term usage index. */
defined( 'ABSPATH' ) || exit;

/** Bind opaque traversal state to the term and explicit narrowing filters. */
function axismundi_op_term_member_cursor( array $position, array $scope ) : string {
	$data = rtrim( strtr( base64_encode( wp_json_encode( array( $scope, $position ) ) ), '+/', '-_' ), '=' );
	return $data . '.' . hash_hmac( 'sha256', $data, wp_salt( 'auth' ) );
}

/** Invalid or cross-query cursors fail closed rather than silently restarting. */
function axismundi_op_parse_term_member_cursor( string $cursor, array $scope ) {
	if ( '' === $cursor ) {
		return null;
	}
	$parts = explode( '.', $cursor );
	if ( 2 !== count( $parts ) || strlen( $cursor ) > 2048 || ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'auth' ) ), $parts[1] ) ) {
		return new WP_Error( 'ax_op_term_cursor', 'Invalid member cursor.', array( 'status' => 400 ) );
	}
	$data = json_decode( (string) base64_decode( strtr( $parts[0], '-_', '+/' ), true ), true );
	$position = $data[1] ?? null;
	if ( ( $data[0] ?? null ) !== $scope || ! is_array( $position ) || ! is_string( $position['at'] ?? null ) || ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $position['at'] ) || ! is_int( $position['id'] ?? null ) || $position['id'] < 1 ) {
		return new WP_Error( 'ax_op_term_cursor', 'The member cursor belongs to another query.', array( 'status' => 400 ) );
	}
	return $position;
}

/**
 * Public members only, for PHP and REST consumers alike. Filters never grant
 * visibility. A scan budget bounds work, not traversal: even an empty page can
 * have a continuation after a long run of private or unavailable sources.
 *
 * @return array|WP_Error Application result, not a published AS2 CollectionPage.
 */
function axismundi_op_query_term_members( WP_Term $term, array $args = array() ) {
	global $wpdb;
	if ( AXISMUNDI_OP_HASHTAG_TAXONOMY !== $term->taxonomy ) {
		return new WP_Error( 'ax_op_term_not_found', 'Hashtag not found.', array( 'status' => 404 ) );
	}
	$type = (string) ( $args['type'] ?? 'all' );
	$actor = (string) ( $args['actor'] ?? '' );
	if ( ! in_array( $type, array( 'all', 'post', 'note', 'media', 'remote' ), true ) || ( '' !== $actor && ! preg_match( '~^https?://[^\s/]+(?:/[^\s]*)?$~D', $actor ) ) ) {
		return new WP_Error( 'ax_op_term_filter', 'Supply a supported type and an absolute Actor URI.', array( 'status' => 400 ) );
	}
	$limit = max( 1, min( 100, (int) ( $args['limit'] ?? 50 ) ) );
	$scope = array( (int) $term->term_taxonomy_id, $type, $actor );
	$position = axismundi_op_parse_term_member_cursor( (string) ( $args['cursor'] ?? '' ), $scope );
	if ( is_wp_error( $position ) ) {
		return $position;
	}
	$table = axismundi_op_term_usage_table();
	$join = '';
	$where = $wpdb->prepare( 'u.term_taxonomy_id=%d AND u.is_current=1', $term->term_taxonomy_id );
	if ( '' !== $actor ) {
		$identities = axismundi_actors_identities_table();
		$objects = axismundi_op_objects_table();
		$join = " INNER JOIN {$identities} i ON i.canonical_uri_hash=u.source_uri_hash AND i.canonical_uri=u.source_uri INNER JOIN {$objects} o ON o.identity_id=i.id";
		$where .= $wpdb->prepare( ' AND o.attributed_to_uri_hash=%s AND o.attributed_to_uri=%s', hash( 'sha256', $actor ), $actor );
	}
	$items = array();
	$scanned = 0;
	$more = true;
	while ( count( $items ) < $limit && $scanned < 500 && $more ) {
		$after = null === $position ? '' : $wpdb->prepare( ' AND (u.feed_sort_at < %s OR (u.feed_sort_at=%s AND u.id < %d))', $position['at'], $position['at'], $position['id'] );
		$rows = $wpdb->get_results( "SELECT u.* FROM {$table} u{$join} WHERE {$where}{$after} ORDER BY u.feed_sort_at DESC,u.id DESC LIMIT 100", ARRAY_A );
		if ( $wpdb->last_error ) {
			return new WP_Error( 'ax_op_term_read', 'The member index could not be read.', array( 'status' => 500 ) );
		}
		$more = count( $rows ) === 100;
		foreach ( $rows as $offset => $row ) {
			$position = array( 'at' => $row['feed_sort_at'], 'id' => (int) $row['id'] );
			$scanned++;
			$source = axismundi_op_resolve_source_by_uri( $row['source_uri'] );
			// This is disclosure, independent of every optional filter and WP login.
			$visible = null !== $source && axismundi_op_object_card_publicly_renderable( $source );
			if ( $source instanceof Axismundi_Op_Remote_Source ) {
				$visible = $visible && axismundi_op_remote_object_is_publicly_listable( $source->get_row() );
			}
			if ( $source instanceof WP_Post && 'attachment' === $source->post_type && function_exists( 'axismundi_media_effective_visibility' ) ) {
				$visible = $visible && 'public' === axismundi_media_effective_visibility( $source->ID );
			}
			if ( $visible ) {
				$matches = ( 'all' === $type && ! $source instanceof WP_Post ) || ( 'remote' === $type && $source instanceof Axismundi_Op_Remote_Source )
					|| ( $source instanceof WP_Post && in_array( $source->post_type, axismundi_op_hashtag_archive_local_types( $type ), true ) );
				if ( $matches ) {
					$model = axismundi_op_object_view_model( $source );
					if ( is_array( $model ) && 'tombstone' !== ( $model['status'] ?? '' ) && $row['source_uri'] === ( $model['id'] ?? $model['object_uri'] ?? '' ) ) {
						$items[] = array( 'object_uri' => $row['source_uri'], 'published' => (string) ( $model['published'] ?? '' ), 'origin' => $source instanceof WP_Post ? 'local' : 'remote', 'type' => (string) $model['type'] );
					}
				}
			}
			if ( count( $items ) >= $limit ) {
				$more = $more || $offset < count( $rows ) - 1;
				break;
			}
		}
	}
	return array(
		'collection_uri' => axismundi_op_hashtag_archive_uri( $term ),
		'items' => $items,
		'next_cursor' => $more && null !== $position ? axismundi_op_term_member_cursor( $position, $scope ) : null,
	);
}

/** Public read surface: caller-supplied requester/owner flags cannot grant access. */
function axismundi_op_rest_term_members( WP_REST_Request $request ) {
	$term = get_term( (int) $request['term_id'], AXISMUNDI_OP_HASHTAG_TAXONOMY );
	if ( ! $term instanceof WP_Term ) {
		return new WP_Error( 'ax_op_term_not_found', 'Hashtag not found.', array( 'status' => 404 ) );
	}
	$result = axismundi_op_query_term_members( $term, $request->get_params() );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	$response = rest_ensure_response( $result );
	$response->header( 'Cache-Control', 'no-store' );
	return $response;
}

function axismundi_op_register_term_member_route() : void {
	register_rest_route( 'axismundi/v1', '/hashtags/(?P<term_id>[1-9][0-9]*)/members', array(
		'methods' => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback' => 'axismundi_op_rest_term_members',
		'args' => array(
			'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ),
			'type' => array( 'type' => 'string', 'enum' => array( 'all', 'post', 'note', 'media', 'remote' ), 'default' => 'all' ),
			'actor' => array( 'type' => 'string' ),
			'cursor' => array( 'type' => 'string' ),
		),
	) );
}
add_action( 'rest_api_init', 'axismundi_op_register_term_member_route' );

/** One bounded migration batch, resumable from accepted source authority. */
function axismundi_op_backfill_term_members() : array {
	$state = get_option( 'ax_op_term_members_backfill', array( 'stage' => 0, 'after' => 0, 'failed' => 0 ) );
	if ( $state['stage'] >= 2 ) {
		return $state;
	}
	$result = axismundi_op_rebuild_tag_references( array( 'wordpress-taxonomy', 'remote-cache' )[ $state['stage'] ], $state['after'], 100 );
	if ( is_wp_error( $result ) ) {
		$state['error'] = $result->get_error_code();
	} else {
		unset( $state['error'] );
		$state['failed'] += $result['failed'];
		$state['after'] = $result['after'];
		if ( $result['failed'] ) {
			$state['last_errors'] = $result['errors'];
		}
		if ( $result['done'] ) {
			$state['stage']++;
			$state['after'] = 0;
		}
	}
	update_option( 'ax_op_term_members_backfill', $state, false );
	axismundi_op_schedule_term_member_backfill();
	return $state;
}

/** Never fall back to the legacy union while a projection rebuild is pending. */
function axismundi_op_schedule_term_member_backfill() : void {
	$state = get_option( 'ax_op_term_members_backfill', array( 'stage' => 0 ) );
	if ( $state['stage'] < 2 && ! wp_next_scheduled( 'ax_op_term_member_backfill' ) ) {
		wp_schedule_single_event( time() + 30, 'ax_op_term_member_backfill' );
	}
}
add_action( 'init', 'axismundi_op_schedule_term_member_backfill', 24 );
add_action( 'ax_op_term_member_backfill', 'axismundi_op_backfill_term_members' );
