<?php
/**
 * Phase 2b media feeds — Atom for Home / Author / Folder scopes.
 *
 * One shared query service, one renderer per format. Atom ships here; Media RSS is
 * a sibling renderer over the same query (follow-up). All three scopes emit only
 * public + listed + ungated media; unlisted/private/password-gated items and folders
 * never appear. Entry identity is the stable /?attachment_id={id} across scopes so a
 * reader never treats the same media as three different objects (SPEC.md §3).
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_MEDIA_FEED_ATOM = 'ax-media-atom';
const AXISMUNDI_MEDIA_FEED_MRSS = 'ax-media-mrss';
const AXISMUNDI_MEDIA_FOLDER_FEED_META = '_ax_media_folder_feed_enabled';

/**
 * May a folder expose a public feed? Folder feeds are ON by default (like a category
 * feed); only these turn it off — always via the RESOLVER's effective values so an
 * inherited private/password ancestor counts:
 *   - effective tier is not public (unlisted / private), or
 *   - effective gate is on (a password anywhere in the chain), or
 *   - the owner explicitly opted out (`_ax_media_folder_feed_enabled` === '0').
 * There is no owner/editor bypass — a public feed endpoint must not vary by auth
 * state (that would risk a cache leak); an authenticated private feed is a separate,
 * token-based design for later.
 *
 * @param int $folder Folder term ID.
 * @param int $owner  Expected owner user ID.
 * @return bool
 */
function axismundi_media_folder_feed_allowed( int $folder, int $owner ) : bool {
	$term = get_term( $folder, AXISMUNDI_MEDIA_FOLDER_TAX );
	if ( ! $term instanceof WP_Term
		|| axismundi_media_is_root_term( $folder )
		|| axismundi_media_folder_owner( $folder ) !== $owner
		|| axismundi_media_folder_effective_tier_rank( $folder ) > 0
		|| 1 === (int) get_term_meta( $folder, AXISMUNDI_MEDIA_FOLDER_EFFECTIVE_GATE_META, true )
		|| '0' === (string) get_term_meta( $folder, AXISMUNDI_MEDIA_FOLDER_FEED_META, true )
	) {
		return false;
	}
	return true;
}

/**
 * Register the custom feed type + scope query var. Independent mode only.
 *
 * @return void
 */
function axismundi_media_register_feeds() : void {
	if ( ! axismundi_media_is_independent() ) {
		return;
	}
	add_feed( AXISMUNDI_MEDIA_FEED_ATOM, 'axismundi_media_render_atom_feed' );
	add_feed( AXISMUNDI_MEDIA_FEED_MRSS, 'axismundi_media_render_mrss_feed' );
}
add_action( 'init', 'axismundi_media_register_feeds', 11 );

/**
 * Expose the feed-scope query var.
 *
 * @param string[] $vars Query vars.
 * @return string[]
 */
function axismundi_media_feed_query_vars( array $vars ) : array {
	$vars[] = 'ax_media_feed';
	return $vars;
}
add_filter( 'query_vars', 'axismundi_media_feed_query_vars' );

/**
 * Pretty feed rewrites (aliases over the plain ?ax_media_feed=…&feed=… base). These
 * must precede the folder archive rule so `/folder/{path}/feed/atom/` is not
 * swallowed by the greedy folder-path capture. Untestable without pretty permalinks;
 * the plain endpoints are the canonical base.
 *
 * @param array<string,string> $rules Existing rules.
 * @return array<string,string>
 */
function axismundi_media_feed_rewrite_rules( array $rules ) : array {
	$mine = array();
	foreach ( array( 'atom' => AXISMUNDI_MEDIA_FEED_ATOM, 'media-rss' => AXISMUNDI_MEDIA_FEED_MRSS ) as $slug => $type ) {
		$mine[ '^media/author/([^/]+)/folder/(.+)/feed/' . $slug . '/?$' ] = 'index.php?ax_media_feed=folder&ax_media_owner=$matches[1]&ax_media_folder_path=$matches[2]&feed=' . $type;
		$mine[ '^media/author/([^/]+)/feed/' . $slug . '/?$' ]             = 'index.php?ax_media_feed=author&ax_media_owner=$matches[1]&feed=' . $type;
		$mine[ '^media/feed/' . $slug . '/?$' ]                            = 'index.php?ax_media_feed=home&feed=' . $type;
	}
	return $mine + $rules;
}
add_filter( 'rewrite_rules_array', 'axismundi_media_feed_rewrite_rules' );

/**
 * Resolve the current feed request into a scope descriptor, or null when invalid.
 *
 * @return array{scope:string,owner:int,folder:int}|null
 */
function axismundi_media_feed_context() : ?array {
	$scope = (string) get_query_var( 'ax_media_feed' );
	if ( ! in_array( $scope, array( 'home', 'author', 'folder' ), true ) ) {
		return null;
	}
	$owner  = 0;
	$folder = 0;
	if ( 'home' !== $scope ) {
		$raw  = (string) get_query_var( 'ax_media_owner' );
		$user = ctype_digit( $raw ) ? get_user_by( 'id', (int) $raw ) : get_user_by( 'slug', $raw );
		if ( ! $user ) {
			return null;
		}
		$owner = (int) $user->ID;
	}
	if ( 'folder' === $scope ) {
		$raw_folder = (string) get_query_var( 'ax_media_folder' );
		$folder     = ctype_digit( $raw_folder )
			? (int) $raw_folder
			: axismundi_media_folder_from_path( $owner, (string) get_query_var( 'ax_media_folder_path' ) );
		if ( ! axismundi_media_folder_feed_allowed( $folder, $owner ) ) {
			return null;
		}
	}
	return array(
		'scope'  => $scope,
		'owner'  => $owner,
		'folder' => $folder,
	);
}

/**
 * Opt a folder out of / back into its public feed (default on, like a category).
 * Enabled deletes the meta (default), disabled stores '0'.
 *
 * @param int      $term_id Folder term ID.
 * @param bool     $enabled Whether the feed is enabled.
 * @param int|null $user_id Acting user.
 * @return true|WP_Error
 */
function axismundi_media_set_folder_feed_enabled( int $term_id, bool $enabled, ?int $user_id = null ) {
	$user_id = $user_id ?? get_current_user_id();
	if ( axismundi_media_is_root_term( $term_id ) || ! axismundi_media_can_manage_folder( $term_id, $user_id ) ) {
		return new WP_Error( 'ax_media_forbidden', __( 'Not allowed.', 'axismundi-media-library' ), array( 'status' => 403 ) );
	}
	if ( $enabled ) {
		delete_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_FEED_META );
	} else {
		update_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_FEED_META, '0' );
	}
	return true;
}

/**
 * The shared feed query. Strict public: public|legacy-public visibility, listed,
 * ungated, in a public folder. No current-user bypass (a feed is anonymous).
 *
 * @param array{scope:string,owner:int,folder:int} $ctx Scope descriptor.
 * @return WP_Query
 */
function axismundi_media_feed_query( array $ctx ) : WP_Query {
	$args = array(
		'post_type'           => 'attachment',
		'post_status'         => 'inherit',
		'posts_per_page'      => 20,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'ax_media_feed_filter' => true,
	);
	if ( 'author' === $ctx['scope'] ) {
		$args['author'] = $ctx['owner'];
	}
	if ( 'folder' === $ctx['scope'] ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => AXISMUNDI_MEDIA_FOLDER_TAX,
				'field'    => 'term_id',
				'terms'    => array( $ctx['folder'] ),
			),
		);
		// Every folder-assigned item has _ax_media_folder_added_at (set on move), so
		// ordering by it is safe; the folder is the collection event's timeline.
		$args['meta_key'] = '_ax_media_folder_added_at'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$args['orderby']  = 'meta_value';
		$args['order']    = 'DESC';
	} else {
		// Home/Author order by publish date (a _ax_media_first_published_at refinement
		// is reserved but not stamped yet).
		$args['orderby'] = 'date';
		$args['order']   = 'DESC';
	}
	return new WP_Query( $args );
}

/**
 * Strict-public WHERE for feed queries (flagged by ax_media_feed_filter). No user
 * bypass and no "mine" clause — feeds are anonymous. Mirrors the archive exclusions
 * but always at max_rank 0.
 *
 * @param string   $where WHERE clause.
 * @param WP_Query $query Query.
 * @return string
 */
function axismundi_media_feed_where( string $where, WP_Query $query ) : string {
	if ( ! $query->get( 'ax_media_feed_filter' ) ) {
		return $where;
	}
	global $wpdb;
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Only static literals; no external input.
	$where .= " AND {$wpdb->posts}.ID NOT IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ax_media_visibility' AND meta_value IN ('unlisted','private') )
		AND {$wpdb->posts}.ID NOT IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ax_media_listed' AND meta_value = '0' )
		AND {$wpdb->posts}.ID NOT IN (
			SELECT tr.object_id FROM {$wpdb->term_relationships} AS tr
			INNER JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'ax_media_folder'
			INNER JOIN {$wpdb->termmeta} AS tm ON tm.term_id = tt.term_id AND tm.meta_key = '_ax_media_folder_effective_tier_rank'
			WHERE CAST(tm.meta_value AS UNSIGNED) > 0
		)
		AND {$wpdb->posts}.ID NOT IN (
			SELECT tr.object_id FROM {$wpdb->term_relationships} AS tr
			INNER JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'ax_media_folder'
			INNER JOIN {$wpdb->termmeta} AS tm ON tm.term_id = tt.term_id AND tm.meta_key = '_ax_media_folder_effective_gated'
			WHERE tm.meta_value = '1'
		)";
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	return $where;
}
add_filter( 'posts_where', 'axismundi_media_feed_where', 10, 2 );

/**
 * The feed's own stable Atom id (plain identity, not the pretty URL).
 *
 * @param array{scope:string,owner:int,folder:int} $ctx Scope.
 * @return string
 */
function axismundi_media_feed_self_id( array $ctx ) : string {
	$url = home_url( '/?ax_media_feed=' . $ctx['scope'] );
	if ( 'home' !== $ctx['scope'] ) {
		$url = add_query_arg( 'ax_media_owner', $ctx['owner'], $url );
	}
	if ( 'folder' === $ctx['scope'] ) {
		$url = add_query_arg( 'ax_media_folder', $ctx['folder'], $url );
	}
	return $url;
}

/**
 * The plain Atom feed URL for a scope (used for discovery + self link).
 *
 * @param array{scope:string,owner:int,folder:int} $ctx  Scope.
 * @param string                                    $type Feed type (atom/mrss).
 * @return string
 */
function axismundi_media_feed_url( array $ctx, string $type = AXISMUNDI_MEDIA_FEED_ATOM ) : string {
	return add_query_arg( 'feed', $type, axismundi_media_feed_self_id( $ctx ) );
}

/**
 * Feed title for a scope.
 *
 * @param array{scope:string,owner:int,folder:int} $ctx Scope.
 * @return string
 */
function axismundi_media_feed_title( array $ctx ) : string {
	if ( 'author' === $ctx['scope'] ) {
		/* translators: %s: author display name. */
		return sprintf( __( '%s — Media', 'axismundi-media-library' ), get_the_author_meta( 'display_name', $ctx['owner'] ) );
	}
	if ( 'folder' === $ctx['scope'] ) {
		$term = get_term( $ctx['folder'], AXISMUNDI_MEDIA_FOLDER_TAX );
		return $term instanceof WP_Term ? $term->name : __( 'Media', 'axismundi-media-library' );
	}
	/* translators: %s: site name. */
	return sprintf( __( '%s — Media', 'axismundi-media-library' ), get_bloginfo( 'name' ) );
}

/**
 * Category scheme URIs (opaque identifiers; they need not resolve).
 *
 * @return string
 */
function axismundi_media_feed_scheme( string $kind ) : string {
	return home_url( '/ns/media/' . $kind );
}

/**
 * Pick an intermediate image rendition — never the original by default (feeds serve
 * derivatives to stay light). Returns the first
 * available preferred size, or null for non-images / no derivative.
 *
 * @param int      $id     Attachment ID.
 * @param string[] $prefer Ordered size candidates.
 * @return array{url:string,width:int,height:int,filesize:int,mime:string}|null
 */
function axismundi_media_feed_rendition( int $id, array $prefer ) : ?array {
	$meta = wp_get_attachment_metadata( $id );
	if ( empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
		return null;
	}
	foreach ( $prefer as $size ) {
		if ( empty( $meta['sizes'][ $size ] ) ) {
			continue;
		}
		$src = wp_get_attachment_image_src( $id, $size );
		if ( ! $src || empty( $src[0] ) ) {
			continue;
		}
		$info     = $meta['sizes'][ $size ];
		$filesize = isset( $info['filesize'] ) ? (int) $info['filesize'] : 0;
		if ( 0 === $filesize && ! empty( $info['file'] ) ) {
			$path     = dirname( (string) get_attached_file( $id ) ) . '/' . $info['file'];
			$filesize = file_exists( $path ) ? (int) filesize( $path ) : 0;
		}
		return array(
			'url'      => (string) $src[0],
			'width'    => (int) $src[1],
			'height'   => (int) $src[2],
			'filesize' => $filesize,
			'mime'     => isset( $info['mime-type'] ) ? (string) $info['mime-type'] : (string) get_post_mime_type( $id ),
		);
	}
	return null;
}

/**
 * Normalize one attachment into the shared feed-item shape used by both renderers.
 *
 * @param WP_Post                                   $post Attachment.
 * @param array{scope:string,owner:int,folder:int} $ctx  Scope.
 * @return array<string,mixed>
 */
function axismundi_media_feed_item( WP_Post $post, array $ctx ) : array {
	$id        = $post->ID;
	$sensitive = '1' === (string) get_post_meta( $id, '_ax_media_sensitive', true );
	$warning   = (string) get_post_meta( $id, '_ax_media_content_warning', true );
	if ( '' === $warning ) {
		$warning = (string) get_post_meta( $id, '_ax_media_sensitivity_reason', true );
	}
	$published = get_post_time( 'c', true, $post );
	if ( 'folder' === $ctx['scope'] ) {
		$added = (string) get_post_meta( $id, '_ax_media_folder_added_at', true );
		if ( '' !== $added ) {
			$published = mysql2date( 'c', $added, false );
		}
	}
	$folder_id = axismundi_media_attachment_folder( $id );
	$folder    = $folder_id > 0 ? get_term( $folder_id, AXISMUNDI_MEDIA_FOLDER_TAX ) : null;
	$summary   = '' !== $post->post_excerpt ? $post->post_excerpt : $post->post_content;

	return array(
		'id'        => $id,
		'entry_id'  => home_url( '/?attachment_id=' . $id ),
		'page'      => home_url( '/?attachment_id=' . $id ),
		'title'     => '' !== $post->post_title ? $post->post_title : __( '(untitled)', 'axismundi-media-library' ),
		'published' => $published,
		'updated'   => get_post_modified_time( 'c', true, $post ),
		'pubdate'   => get_post_time( 'r', true, $post ),
		'author'    => get_the_author_meta( 'display_name', (int) $post->post_author ),
		'summary'   => $summary,
		'sensitive' => $sensitive,
		'warning'   => $warning,
		'folder_id' => $folder_id,
		'folder'    => ( $folder instanceof WP_Term ) ? $folder->name : '',
		'content'   => axismundi_media_feed_rendition( $id, array( 'medium_large', 'large', 'medium' ) ),
		'thumb'     => axismundi_media_feed_rendition( $id, array( 'medium', 'thumbnail' ) ),
		'file'      => (string) wp_get_attachment_url( $id ),
		'mime'      => (string) get_post_mime_type( $id ),
	);
}

/**
 * The summary with a leading content-warning line when sensitive.
 *
 * @param array<string,mixed> $item Feed item.
 * @return string
 */
function axismundi_media_feed_summary( array $item ) : string {
	if ( ! empty( $item['sensitive'] ) ) {
		$cw = '' !== $item['warning'] ? $item['warning'] : __( 'Sensitive content.', 'axismundi-media-library' );
		/* translators: %s: content warning text. */
		$prefix = sprintf( __( 'Content warning: %s', 'axismundi-media-library' ), $cw );
		return '' !== $item['summary'] ? $prefix . "\n\n" . $item['summary'] : $prefix;
	}
	return (string) $item['summary'];
}

/**
 * Render the Atom feed for the current request. 404 on an invalid/non-public scope.
 *
 * @return void
 */
function axismundi_media_render_atom_feed() : void {
	$ctx = axismundi_media_feed_context();
	if ( null === $ctx ) {
		status_header( 404 );
		nocache_headers();
		return;
	}
	$query   = axismundi_media_feed_query( $ctx );
	$self    = axismundi_media_feed_url( $ctx );
	$updated = axismundi_media_atom_now( $query );

	header( 'Content-Type: application/atom+xml; charset=' . get_option( 'blog_charset' ), true );
	echo '<?xml version="1.0" encoding="' . esc_attr( get_option( 'blog_charset' ) ) . '"?>' . "\n";
	?>
<feed xmlns="http://www.w3.org/2005/Atom">
	<title><?php echo esc_html( axismundi_media_feed_title( $ctx ) ); ?></title>
	<id><?php echo esc_url( axismundi_media_feed_self_id( $ctx ) ); ?></id>
	<updated><?php echo esc_html( $updated ); ?></updated>
	<link rel="self" type="application/atom+xml" href="<?php echo esc_url( $self ); ?>" />
	<link rel="alternate" type="text/html" href="<?php echo esc_url( home_url( '/' ) ); ?>" />
	<generator uri="https://github.com/Jiwoon-Kim/axismundi">Axismundi Media Library</generator>
	<?php
	while ( $query->have_posts() ) {
		$query->the_post();
		axismundi_media_atom_entry( get_post(), $ctx );
	}
	wp_reset_postdata();
	?>
</feed>
	<?php
}

/**
 * The feed-level <updated>: the newest entry's modified time, or now.
 *
 * @param WP_Query $query Feed query.
 * @return string RFC3339 timestamp.
 */
function axismundi_media_atom_now( WP_Query $query ) : string {
	if ( ! empty( $query->posts ) ) {
		$newest = get_post_modified_time( 'c', true, $query->posts[0] );
		if ( $newest ) {
			return $newest;
		}
	}
	return gmdate( 'c' );
}

/**
 * Emit one Atom <entry>.
 *
 * @param WP_Post                                   $post Attachment.
 * @param array{scope:string,owner:int,folder:int} $ctx  Scope.
 * @return void
 */
function axismundi_media_atom_entry( WP_Post $post, array $ctx ) : void {
	$item = axismundi_media_feed_item( $post, $ctx );
	// Enclosure: an image serves its derivative, never the original; a non-image
	// serves its file. A sensitive item gets NO enclosure — readers should not
	// auto-render it inline; the alternate link + warning carry it instead.
	$enclosure = null;
	if ( empty( $item['sensitive'] ) ) {
		if ( is_array( $item['content'] ) ) {
			$enclosure = array( 'url' => $item['content']['url'], 'mime' => $item['content']['mime'], 'length' => $item['content']['filesize'] );
		} elseif ( '' !== $item['file'] ) {
			$path      = (string) get_attached_file( $item['id'] );
			$enclosure = array(
				'url'    => $item['file'],
				'mime'   => $item['mime'],
				'length' => ( '' !== $path && file_exists( $path ) ) ? (int) filesize( $path ) : 0,
			);
		}
	}
	$summary = axismundi_media_feed_summary( $item );
	?>
	<entry>
		<id><?php echo esc_url( $item['entry_id'] ); ?></id>
		<title><?php echo esc_html( $item['title'] ); ?></title>
		<link rel="alternate" type="text/html" href="<?php echo esc_url( $item['page'] ); ?>" />
		<?php if ( null !== $enclosure ) : ?>
		<link rel="enclosure" type="<?php echo esc_attr( $enclosure['mime'] ); ?>"<?php echo $enclosure['length'] > 0 ? ' length="' . esc_attr( (string) $enclosure['length'] ) . '"' : ''; ?> href="<?php echo esc_url( $enclosure['url'] ); ?>" />
		<?php endif; ?>
		<?php if ( $item['folder_id'] > 0 ) : ?>
		<category scheme="<?php echo esc_url( axismundi_media_feed_scheme( 'folder' ) ); ?>" term="<?php echo esc_attr( (string) $item['folder_id'] ); ?>" label="<?php echo esc_attr( $item['folder'] ); ?>" />
		<?php endif; ?>
		<?php if ( ! empty( $item['sensitive'] ) ) : ?>
		<category scheme="<?php echo esc_url( axismundi_media_feed_scheme( 'sensitivity' ) ); ?>" term="sensitive" label="<?php echo esc_attr__( 'Sensitive content', 'axismundi-media-library' ); ?>" />
		<?php endif; ?>
		<published><?php echo esc_html( $item['published'] ); ?></published>
		<updated><?php echo esc_html( $item['updated'] ); ?></updated>
		<author><name><?php echo esc_html( $item['author'] ); ?></name></author>
		<?php if ( '' !== $summary ) : ?>
		<summary type="text"><?php echo esc_html( $summary ); ?></summary>
		<?php endif; ?>
	</entry>
	<?php
}

/**
 * Render the Media RSS feed for the current request. 404 on an invalid/non-public
 * scope. Same query and item shape as Atom; RSS 2.0 + media namespace.
 *
 * @return void
 */
function axismundi_media_render_mrss_feed() : void {
	$ctx = axismundi_media_feed_context();
	if ( null === $ctx ) {
		status_header( 404 );
		nocache_headers();
		return;
	}
	$query = axismundi_media_feed_query( $ctx );
	$self  = axismundi_media_feed_url( $ctx, AXISMUNDI_MEDIA_FEED_MRSS );

	header( 'Content-Type: application/rss+xml; charset=' . get_option( 'blog_charset' ), true );
	echo '<?xml version="1.0" encoding="' . esc_attr( get_option( 'blog_charset' ) ) . '"?>' . "\n";
	?>
<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
	<title><?php echo esc_html( axismundi_media_feed_title( $ctx ) ); ?></title>
	<link><?php echo esc_url( home_url( '/' ) ); ?></link>
	<description><?php echo esc_html( axismundi_media_feed_title( $ctx ) ); ?></description>
	<atom:link rel="self" type="application/rss+xml" href="<?php echo esc_url( $self ); ?>" />
	<?php
	while ( $query->have_posts() ) {
		$query->the_post();
		axismundi_media_mrss_item( get_post(), $ctx );
	}
	wp_reset_postdata();
	?>
</channel>
</rss>
	<?php
}

/**
 * Emit one Media RSS <item>. Sensitive items get only a player link + rating +
 * warning description — NO enclosure, media:content, or media:thumbnail, so a reader
 * never auto-renders the media (not even a small thumbnail).
 *
 * @param WP_Post                                   $post Attachment.
 * @param array{scope:string,owner:int,folder:int} $ctx  Scope.
 * @return void
 */
function axismundi_media_mrss_item( WP_Post $post, array $ctx ) : void {
	$item      = axismundi_media_feed_item( $post, $ctx );
	$sensitive = ! empty( $item['sensitive'] );
	$thumb     = is_array( $item['thumb'] ) ? $item['thumb'] : null;
	$summary   = axismundi_media_feed_summary( $item );

	// Primary delivery (non-sensitive only): an image derivative, or the file itself
	// for audio/video/other. Never the original image.
	$primary = null;
	if ( ! $sensitive ) {
		if ( is_array( $item['content'] ) ) {
			$primary = $item['content'] + array( 'medium' => 'image' );
		} elseif ( '' !== $item['file'] ) {
			$path            = (string) get_attached_file( $item['id'] );
			$primary         = array(
				'url'      => $item['file'],
				'mime'     => $item['mime'],
				'width'    => 0,
				'height'   => 0,
				'filesize' => ( '' !== $path && file_exists( $path ) ) ? (int) filesize( $path ) : 0,
				'medium'   => axismundi_media_mrss_medium( $item['mime'] ),
			);
		}
	}
	?>
	<item>
		<guid isPermaLink="false"><?php echo esc_url( $item['entry_id'] ); ?></guid>
		<title><?php echo esc_html( $item['title'] ); ?></title>
		<link><?php echo esc_url( $item['page'] ); ?></link>
		<pubDate><?php echo esc_html( $item['pubdate'] ); ?></pubDate>
		<?php if ( $item['folder_id'] > 0 ) : ?>
		<media:category scheme="<?php echo esc_url( axismundi_media_feed_scheme( 'folder' ) ); ?>" label="<?php echo esc_attr( $item['folder'] ); ?>"><?php echo esc_html( (string) $item['folder_id'] ); ?></media:category>
		<?php endif; ?>
		<?php if ( null !== $primary ) : ?>
		<enclosure url="<?php echo esc_url( $primary['url'] ); ?>"<?php echo $primary['filesize'] > 0 ? ' length="' . esc_attr( (string) $primary['filesize'] ) . '"' : ''; ?> type="<?php echo esc_attr( $primary['mime'] ); ?>" />
		<media:content url="<?php echo esc_url( $primary['url'] ); ?>" type="<?php echo esc_attr( $primary['mime'] ); ?>" medium="<?php echo esc_attr( $primary['medium'] ); ?>"<?php echo $primary['width'] > 0 ? ' width="' . esc_attr( (string) $primary['width'] ) . '" height="' . esc_attr( (string) $primary['height'] ) . '"' : ''; ?><?php echo $primary['filesize'] > 0 ? ' fileSize="' . esc_attr( (string) $primary['filesize'] ) . '"' : ''; ?> isDefault="true" expression="full" />
		<?php elseif ( $sensitive ) : ?>
		<media:player url="<?php echo esc_url( $item['page'] ); ?>" />
		<?php endif; ?>
		<?php if ( ! $sensitive && null !== $thumb ) : ?>
		<media:thumbnail url="<?php echo esc_url( $thumb['url'] ); ?>" width="<?php echo esc_attr( (string) $thumb['width'] ); ?>" height="<?php echo esc_attr( (string) $thumb['height'] ); ?>" />
		<?php endif; ?>
		<?php if ( $sensitive ) : ?>
		<media:rating scheme="urn:simple">adult</media:rating>
		<?php endif; ?>
		<?php if ( '' !== $summary ) : ?>
		<media:description type="plain"><?php echo esc_html( $summary ); ?></media:description>
		<?php endif; ?>
	</item>
	<?php
}

/**
 * Map a MIME type to a Media RSS `medium`.
 *
 * @param string $mime MIME type.
 * @return string
 */
function axismundi_media_mrss_medium( string $mime ) : string {
	if ( 0 === strpos( $mime, 'image/' ) ) {
		return 'image';
	}
	if ( 0 === strpos( $mime, 'audio/' ) ) {
		return 'audio';
	}
	if ( 0 === strpos( $mime, 'video/' ) ) {
		return 'video';
	}
	return 'document';
}

/**
 * Advertise the scope's Atom + Media RSS feeds on its HTML archive.
 *
 * @return void
 */
function axismundi_media_feed_discovery() : void {
	if ( ! axismundi_media_is_independent() ) {
		return;
	}
	$route = (string) get_query_var( 'ax_media_archive' );
	if ( ! in_array( $route, array( 'landing', 'owner', 'folder' ), true ) ) {
		return;
	}
	$ctx = array(
		'scope'  => 'landing' === $route ? 'home' : $route,
		'owner'  => (int) get_query_var( 'ax_media_owner' ),
		'folder' => (int) get_query_var( 'ax_media_folder' ),
	);
	if ( 'owner' === $ctx['scope'] ) {
		$ctx['scope'] = 'author';
	}
	// Folder feeds are conditional (non-public / opted-out folders have none); Home
	// and Author feeds are always public.
	if ( 'folder' === $ctx['scope'] && ! axismundi_media_folder_feed_allowed( $ctx['folder'], $ctx['owner'] ) ) {
		return;
	}
	$title = axismundi_media_feed_title( $ctx );
	printf(
		'<link rel="alternate" type="application/atom+xml" title="%s" href="%s" />' . "\n",
		esc_attr( $title ),
		esc_url( axismundi_media_feed_url( $ctx, AXISMUNDI_MEDIA_FEED_ATOM ) )
	);
	printf(
		'<link rel="alternate" type="application/rss+xml" title="%s" href="%s" />' . "\n",
		esc_attr( $title ),
		esc_url( axismundi_media_feed_url( $ctx, AXISMUNDI_MEDIA_FEED_MRSS ) )
	);
}
add_action( 'wp_head', 'axismundi_media_feed_discovery' );
