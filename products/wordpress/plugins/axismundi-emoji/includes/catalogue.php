<?php
/**
 * The local emoji catalogue and its search endpoint (docs §9).
 *
 * Two decisions shape everything here.
 *
 * **Local only.** Observed remote emoji are evidence for faithfully rendering messages
 * we received, not assets to reuse in new ones: their provenance, licensing, and
 * continued availability are somebody else's. Offering `hoto.moe`'s `:misskey:` in a
 * picker would also reintroduce the namespace collision the renderer exists to avoid —
 * two servers ship that name and a flat list cannot say which was meant. Copying a
 * remote emoji into the local registry is the correct way to want one, and it is a
 * separate, explicit act.
 *
 * **Searched on the server.** The catalogue is never bulk-localized into the page. A
 * site with a few hundred emoji would ship a large payload into every editor load for a
 * list the user filters down to three entries.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit;

/** @return int Hard ceiling on one page of catalogue results. */
const AXISMUNDI_EMOJI_CATALOGUE_MAX_PER_PAGE = 100;

/**
 * Search this site's own emoji.
 *
 * @param array<string,mixed> $args search, category, federated, per_page, page.
 * @return array{items:array<int,array<string,mixed>>,total:int}
 */
function axismundi_emoji_search_local( array $args = array() ) : array {
	global $wpdb;
	$empty = array( 'items' => array(), 'total' => 0 );
	if ( ! axismundi_emoji_ready() ) {
		return $empty;
	}
	$search   = trim( (string) ( $args['search'] ?? '' ) );
	$category = trim( (string) ( $args['category'] ?? '' ) );
	$per_page = min( AXISMUNDI_EMOJI_CATALOGUE_MAX_PER_PAGE, max( 1, (int) ( $args['per_page'] ?? 40 ) ) );
	$page     = max( 1, (int) ( $args['page'] ?? 1 ) );

	/*
	 * `cached_path` is part of the predicate, not an afterthought. A row whose bytes are
	 * missing would offer the user an emoji that renders as a broken image the moment
	 * they pick it, which is worse than not offering it.
	 */
	$where  = array( "scope = 'local'", 'picker_visible = 1', "COALESCE(cached_path, '') <> ''" );
	$params = array();

	/*
	 * Federated by default, because almost everything here is.
	 *
	 * The earlier reading — that a `localOnly` emoji is merely not attached to `tag[]`, so
	 * offering it anywhere is harmless — does not survive contact with what publishing
	 * means: the `:shortcode:` text still leaves the site. A Note or Article composer is a
	 * federating surface, so a picker there offering a local-only emoji produces a message
	 * that reads correctly at home and as a bare word everywhere else.
	 *
	 * Misskey is the precedent and it agrees. `localOnly` is a first-class field in its
	 * emoji API, and a `localOnly` emoji in a note federates with `"tag": []` — the
	 * shortcode travels, the image reference does not.
	 *
	 * So `federated` is the default and `false` is the deliberate exception, reserved for a
	 * composer whose output genuinely never leaves. Defaulting the other way would make
	 * every future consumer leak by omission.
	 */
	if ( false !== ( $args['federated'] ?? true ) ) {
		$where[] = 'outbound_allowed = 1';
	}
	if ( '' !== $category ) {
		$where[]  = 'category = %s';
		$params[] = $category;
	}
	if ( '' !== $search ) {
		// Aliases are searched as well as the name, which is the whole point of having
		// them: somebody who knows an emoji as "thumbsup" should find `:+1:`.
		$like     = '%' . $wpdb->esc_like( strtolower( $search ) ) . '%';
		$where[]  = "( shortcode_key LIKE %s OR LOWER(COALESCE(aliases, '')) LIKE %s )";
		$params[] = $like;
		$params[] = $like;
	}

	$table     = axismundi_emoji_table();
	$condition = implode( ' AND ', $where );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; values prepared.
	$total = (int) $wpdb->get_var(
		array() === $params
			? "SELECT COUNT(*) FROM {$table} WHERE {$condition}"
			: $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$condition}", $params )
	);
	if ( 0 === $total ) {
		return $empty;
	}

	$paged = array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; values prepared.
	$rows = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE {$condition} ORDER BY category IS NULL, category ASC, shortcode_key ASC LIMIT %d OFFSET %d",
			$paged
		),
		ARRAY_A
	);

	return array(
		'items' => array_map( 'axismundi_emoji_catalogue_item', $rows ),
		'total' => $total,
	);
}

/**
 * One catalogue entry, as a picker needs it.
 *
 * `shortcode` is what a picker inserts — plain text, never image markup. The image URLs
 * are for the tile only: substitution happens at render time from the declarations the
 * document carries, so a picker that pasted an `<img>` would produce content that
 * cannot federate and cannot be re-rendered when the emoji changes.
 *
 * @param array<string,mixed> $row Registry row.
 * @return array<string,mixed>
 */
function axismundi_emoji_catalogue_item( array $row ) : array {
	$aliases = json_decode( (string) ( $row['aliases'] ?? '' ), true );
	return array(
		'id'         => (int) $row['id'],
		'shortcode'  => (string) $row['shortcode'],
		'name'       => (string) $row['shortcode_key'],
		'aliases'    => is_array( $aliases ) ? array_values( array_map( 'strval', $aliases ) ) : array(),
		'category'   => (string) ( $row['category'] ?? '' ),
		'url'        => axismundi_emoji_file_url( $row ),
		'static_url' => axismundi_emoji_static_url( $row ),
		'animated'   => ! empty( $row['animated'] ),
		'width'      => (int) $row['width'],
		'height'     => (int) $row['height'],
		'outbound'   => ! empty( $row['outbound_allowed'] ),
		'local_only' => ! empty( $row['local_only'] ),
	);
}

/** @return string[] Every category in use, for grouping a picker. */
function axismundi_emoji_local_categories( bool $federated = true ) : array {
	global $wpdb;
	if ( ! axismundi_emoji_ready() ) {
		return array();
	}
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	$where = "scope = 'local' AND picker_visible = 1 AND COALESCE(cached_path, '') <> '' AND COALESCE(category, '') <> ''";
	if ( $federated ) {
		$where .= ' AND outbound_allowed = 1';
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed local catalogue predicate.
	$found = (array) $wpdb->get_col( "SELECT DISTINCT category FROM {$table} WHERE {$where} ORDER BY category ASC" );
	return array_values( array_map( 'strval', $found ) );
}

/**
 * Who may read the catalogue.
 *
 * These images are already public — they are published in federated content and served
 * from the uploads directory. The *catalogue* is the list of what a site has, which is
 * editorial information, so it follows the editor.
 *
 * A reacting Actor is admitted alongside the editor, and admitted to the whole list rather
 * than only the federating part of it. `local_only` withholds an emoji from *publication*,
 * not from existence: it already renders inline in local content, so a reader has seen it.
 * Hiding it from the picker would only stop them naming a thing they can point at. The
 * picker offers it and the surface that would federate it declines instead
 * ({@see axismundi_act_react_to_object()}), which is where the constraint actually lives.
 *
 * @return bool
 */
function axismundi_emoji_can_read_catalogue() : bool {
	/** Filter who may browse the local emoji catalogue. @param bool $allowed Default. */
	return (bool) apply_filters( 'axismundi_emoji_can_read_catalogue', current_user_can( 'edit_posts' ) || axismundi_emoji_reader_is_reacting_actor() );
}

/** Whether the reader is here to react rather than to edit. */
function axismundi_emoji_reader_is_reacting_actor() : bool {
	return function_exists( 'axismundi_act_current_reaction_actor' )
		&& axismundi_act_current_reaction_actor() instanceof Axismundi_Actor;
}

/**
 * REST callback for the picker.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function axismundi_emoji_rest_search_local( WP_REST_Request $request ) : WP_REST_Response {
	$per_page = (int) $request->get_param( 'per_page' );
	$page     = (int) $request->get_param( 'page' );
	$found    = axismundi_emoji_search_local(
		array(
			'search'    => (string) $request->get_param( 'search' ),
			'category'  => (string) $request->get_param( 'category' ),
			// A reader who is only an Actor never sees the withheld rows, whatever they ask for.
			'federated' => (bool) $request->get_param( 'federated' ),
			'per_page'  => $per_page,
			'page'      => $page,
		)
	);
	$response = rest_ensure_response( $found['items'] );
	$response->header( 'X-WP-Total', (string) $found['total'] );
	$response->header( 'X-WP-TotalPages', (string) ( $per_page > 0 ? (int) ceil( $found['total'] / $per_page ) : 0 ) );
	return $response;
}

/** @return WP_REST_Response Categories in use. */
function axismundi_emoji_rest_local_categories( WP_REST_Request $request ) : WP_REST_Response {
	return rest_ensure_response( axismundi_emoji_local_categories( (bool) $request->get_param( 'federated' ) ) );
}

/** Register the catalogue routes. @return void */
function axismundi_emoji_register_catalogue_routes() : void {
	register_rest_route(
		'axismundi/v1',
		'/emoji/local',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_emoji_rest_search_local',
			'permission_callback' => 'axismundi_emoji_can_read_catalogue',
			'args'                => array(
				'search'    => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
				'category'  => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
				// Defaults true: a consumer that forgets this parameter gets the safe answer.
				'federated' => array( 'type' => 'boolean', 'default' => true ),
				'per_page'  => array( 'type' => 'integer', 'default' => 40, 'minimum' => 1, 'maximum' => AXISMUNDI_EMOJI_CATALOGUE_MAX_PER_PAGE ),
				'page'      => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
			),
		)
	);
	register_rest_route(
		'axismundi/v1',
		'/emoji/local/categories',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_emoji_rest_local_categories',
			'permission_callback' => 'axismundi_emoji_can_read_catalogue',
			'args'                => array( 'federated' => array( 'type' => 'boolean', 'default' => true ) ),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_emoji_register_catalogue_routes' );
