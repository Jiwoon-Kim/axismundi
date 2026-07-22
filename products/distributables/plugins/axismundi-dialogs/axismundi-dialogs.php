<?php
/**
 * Plugin Name:       Axismundi Dialogs
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-dialogs
 * Description:       Accessible Material Design 3 side / bottom sheet and dialog blocks for Axismundi. The blocks own native dialog behavior; theme template parts own default content and layout.
 * Version:           0.2.2
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-dialogs
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/interaction-dialog.php';

/**
 * Register the Sheet collection, host, and close blocks.
 *
 * The host block renders a trigger button plus a native <dialog>; the close block
 * is a small affordance a Sheet template part places wherever it wants a dismiss
 * control (mirrors core's navigation-overlay-close for the Navigation overlay).
 *
 * @return void
 */
function axismundi_dialogs_register_blocks() : void {
	foreach ( array( 'dialogs', 'sheet', 'dialog', 'dialog-close', 'dialog-title', 'dialog-icon', 'post-quick-view-trigger', 'post-quick-view' ) as $axismundi_dialogs_block ) {
		$axismundi_dialogs_dir = __DIR__ . '/blocks/' . $axismundi_dialogs_block;
		if ( file_exists( $axismundi_dialogs_dir . '/block.json' ) ) {
			register_block_type( $axismundi_dialogs_dir );
		}
	}

	// Shared runtime surface (open button, <dialog> box, template-part contract,
	// scrim, scroll lock) enqueued for both the Sheet and the Dialog host blocks.
	$axismundi_dialogs_shared = array(
		'handle' => 'axismundi-dialogs-shared',
		'src'    => plugins_url( 'assets/shared.css', __FILE__ ),
		'path'   => __DIR__ . '/assets/shared.css',
		'ver'    => (string) filemtime( __DIR__ . '/assets/shared.css' ),
	);
	wp_enqueue_block_style( 'axismundi/sheet', $axismundi_dialogs_shared );
	wp_enqueue_block_style( 'axismundi/dialog', $axismundi_dialogs_shared );
	wp_enqueue_block_style( 'axismundi/post-quick-view', $axismundi_dialogs_shared );
}
add_action( 'init', 'axismundi_dialogs_register_blocks' );

/**
 * Keep part-only Dialogs blocks out of the post/page inserter.
 *
 * The close, title, and icon blocks only have a meaningful role inside the
 * referenced template part. Keep those building blocks available in the Site
 * Editor, but do not offer them in ordinary post content.
 *
 * @param bool|array<int,string>        $allowed Allowed block names, or true for all.
 * @param WP_Block_Editor_Context|mixed $context Current editor context.
 * @return bool|array<int,string>
 */
function axismundi_dialogs_restrict_close_block( $allowed, $context ) {
	if ( ! ( isset( $context->post ) && $context->post instanceof WP_Post ) ) {
		return $allowed;
	}
	if ( 'wp_template_part' === $context->post->post_type ) {
		return $allowed;
	}

	if ( true === $allowed ) {
		$allowed = array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() );
	}

	return array_values( array_diff( (array) $allowed, array( 'axismundi/dialog-close', 'axismundi/dialog-title', 'axismundi/dialog-icon' ) ) );
}
add_filter( 'allowed_block_types_all', 'axismundi_dialogs_restrict_close_block', 10, 2 );

/**
 * Register the post quick-view REST route.
 *
 * Feeds carry one lightweight trigger per row (axismundi/post-quick-view-trigger)
 * and a single hub dialog (axismundi/post-quick-view). On click the hub fetches
 * this endpoint for the selected post and injects the returned HTML fragment, so
 * the page never renders N hidden post/comment sections. Read-only and public —
 * it exposes only what the front end already shows — but the callback still
 * refuses anything that is not published, publicly-viewable content.
 *
 * @return void
 */
function axismundi_dialogs_register_rest() : void {
	register_rest_route(
		'axismundi-dialogs/v1',
		'/post-quick-view/(?P<id>\d+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_dialogs_rest_quick_view',
			'permission_callback' => '__return_true',
			'args'                => array(
				'id' => array(
					'sanitize_callback' => 'absint',
					'validate_callback' => static function ( $param ) : bool {
						return is_numeric( $param ) && (int) $param > 0;
					},
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_dialogs_register_rest' );

/**
 * Return a post quick-view fragment for the hub dialog.
 *
 * @param WP_REST_Request $request REST request; `id` is the post ID.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_dialogs_rest_quick_view( WP_REST_Request $request ) {
	$axismundi_dialogs_post = get_post( (int) $request['id'] );

	if ( ! $axismundi_dialogs_post instanceof WP_Post ) {
		return new WP_Error( 'axismundi_dialogs_not_found', __( 'Post not found.', 'axismundi-dialogs' ), array( 'status' => 404 ) );
	}

	// Never expose drafts, private, future, trashed, or non-public post types to a
	// public reader — only what the front end already renders for everyone.
	$axismundi_dialogs_type = get_post_type_object( $axismundi_dialogs_post->post_type );
	if ( 'publish' !== get_post_status( $axismundi_dialogs_post ) || ! $axismundi_dialogs_type || empty( $axismundi_dialogs_type->public ) ) {
		return new WP_Error( 'axismundi_dialogs_unavailable', __( 'This content is not available.', 'axismundi-dialogs' ), array( 'status' => 404 ) );
	}

	return new WP_REST_Response(
		array(
			'id'        => $axismundi_dialogs_post->ID,
			'permalink' => get_permalink( $axismundi_dialogs_post ),
			'html'      => axismundi_dialogs_render_quick_view( $axismundi_dialogs_post ),
		),
		200
	);
}

/**
 * Render the quick-view HTML fragment for a post (title, meta, content, comments
 * summary link). v0.2 scope: no comment list or form — the footer links out to
 * the post's #comments so the endpoint stays clear of the global comment query.
 *
 * @param WP_Post $quick_view_post Published post to render.
 * @return string
 */
function axismundi_dialogs_render_quick_view( WP_Post $quick_view_post ) : string {
	global $post;
	$axismundi_dialogs_prev_post = $post;
	$post                        = $quick_view_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	setup_postdata( $post );

	$axismundi_dialogs_pw      = post_password_required( $post );
	$axismundi_dialogs_count   = (int) get_comments_number( $post );
	$axismundi_dialogs_clink   = get_comments_link( $post );
	$axismundi_dialogs_respond = get_permalink( $post ) . '#respond';
	$axismundi_dialogs_author  = get_the_author_meta( 'display_name', (int) $post->post_author );

	// Read-only discussion (Phase 3a): the comment tree is rendered by
	// axismundi_dialogs_render_comments() below — no comments_template() (which
	// couples to the global main query and the theme's comments.php). Replies fold
	// Reddit-style client-side; writing stays a link out to #respond.
	$axismundi_dialogs_comments_html = $axismundi_dialogs_pw ? '' : axismundi_dialogs_render_comments( $post->ID );

	ob_start();
	?>
	<article class="ax-post-quick-view__article">
		<header class="ax-post-quick-view__head">
			<h2 class="ax-post-quick-view__title"><?php echo esc_html( get_the_title( $post ) ); ?></h2>
			<p class="ax-post-quick-view__meta">
				<?php
				printf(
					/* translators: 1: author display name, 2: post date. */
					esc_html__( '%1$s · %2$s', 'axismundi-dialogs' ),
					esc_html( $axismundi_dialogs_author ),
					esc_html( get_the_date( '', $post ) )
				);
				?>
			</p>
		</header>
		<?php if ( ! $axismundi_dialogs_pw && has_post_thumbnail( $post ) ) : ?>
			<figure class="ax-post-quick-view__media">
				<?php echo get_the_post_thumbnail( $post, 'medium_large', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core thumbnail markup. ?>
			</figure>
		<?php endif; ?>
		<div class="ax-post-quick-view__content">
			<?php
			if ( $axismundi_dialogs_pw ) {
				echo '<p>' . esc_html__( 'This post is password protected. Open the full post to read it.', 'axismundi-dialogs' ) . '</p>';
			} else {
				echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Applies (not invents) the core the_content filter to render the post body exactly as the front end does.
			}
			?>
		</div>
		<?php if ( ! $axismundi_dialogs_pw ) : ?>
		<section class="ax-post-quick-view__comments" aria-label="<?php esc_attr_e( 'Comments', 'axismundi-dialogs' ); ?>">
			<div class="ax-post-quick-view__comments-head">
				<span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">comment</span>
				<h3 class="ax-post-quick-view__comments-count">
					<?php
					if ( $axismundi_dialogs_count > 0 ) {
						printf(
							/* translators: %s: number of comments. */
							esc_html( _n( '%s comment', '%s comments', $axismundi_dialogs_count, 'axismundi-dialogs' ) ),
							esc_html( number_format_i18n( $axismundi_dialogs_count ) )
						);
					} else {
						esc_html_e( 'No comments yet', 'axismundi-dialogs' );
					}
					?>
				</h3>
			</div>
			<?php echo $axismundi_dialogs_comments_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in axismundi_dialogs_render_comments(). ?>
			<p class="ax-post-quick-view__discussion">
				<?php if ( comments_open( $post ) ) : ?>
					<a class="ax-post-quick-view__discussion-reply" href="<?php echo esc_url( $axismundi_dialogs_respond ); ?>"><?php esc_html_e( 'Reply on the full post', 'axismundi-dialogs' ); ?></a>
				<?php endif; ?>
				<a class="ax-post-quick-view__discussion-all" href="<?php echo esc_url( $axismundi_dialogs_clink ); ?>"><?php esc_html_e( 'View full discussion', 'axismundi-dialogs' ); ?></a>
			</p>
		</section>
		<?php endif; ?>
	</article>
	<?php
	$axismundi_dialogs_html = (string) ob_get_clean();

	wp_reset_postdata();
	$post = $axismundi_dialogs_prev_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

	return $axismundi_dialogs_html;
}

/**
 * Render the quick-view comment tree (Phase 3a, read-only).
 *
 * One query for every approved comment on the post, grouped by parent and
 * rendered as a Reddit-style thread: top-level comments (capped) with their
 * replies foldable client-side. Past the depth cap a "Continue thread" link goes
 * to the full post. No form, no comments_template().
 *
 * @param int $post_id Post ID.
 * @return string HTML, or '' when there are no comments.
 */
function axismundi_dialogs_render_comments( int $post_id ) : string {
	$axismundi_dialogs_all = get_comments(
		array(
			'post_id' => $post_id,
			'status'  => 'approve',
			'type'    => 'comment',
			'orderby' => 'comment_date_gmt',
			'order'   => 'ASC',
		)
	);
	if ( empty( $axismundi_dialogs_all ) ) {
		return '';
	}

	$axismundi_dialogs_by_parent = array();
	foreach ( $axismundi_dialogs_all as $axismundi_dialogs_c ) {
		$axismundi_dialogs_by_parent[ (int) $axismundi_dialogs_c->comment_parent ][] = $axismundi_dialogs_c;
	}

	$axismundi_dialogs_top       = $axismundi_dialogs_by_parent[0] ?? array();
	$axismundi_dialogs_top_limit = 10;
	$axismundi_dialogs_max_depth = 3;
	$axismundi_dialogs_has_more  = count( $axismundi_dialogs_top ) > $axismundi_dialogs_top_limit;
	$axismundi_dialogs_top       = array_slice( $axismundi_dialogs_top, 0, $axismundi_dialogs_top_limit );

	$axismundi_dialogs_render_one = static function ( $comment, $depth ) use ( &$axismundi_dialogs_render_one, $axismundi_dialogs_by_parent, $axismundi_dialogs_max_depth ) : string {
		$id           = (int) $comment->comment_ID;
		$author       = get_comment_author( $comment );
		$children     = $axismundi_dialogs_by_parent[ $id ] ?? array();
		$show_replies = ! empty( $children ) && $depth < $axismundi_dialogs_max_depth;
		$deeper_only  = ! empty( $children ) && $depth >= $axismundi_dialogs_max_depth;

		ob_start();
		?>
		<li class="ax-comment<?php echo $show_replies ? ' has-replies is-collapsed' : ''; ?>" data-ax-comment data-comment-id="<?php echo esc_attr( (string) $id ); ?>">
			<div class="ax-comment__body">
				<div class="ax-comment__head">
					<?php echo get_avatar( $comment, 32, '', '', array( 'class' => 'ax-comment__avatar' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core avatar markup. ?>
					<span class="ax-comment__author"><?php echo esc_html( $author ); ?></span>
					<span class="ax-comment__date"><?php echo esc_html( get_comment_date( '', $comment ) ); ?></span>
				</div>
				<div class="ax-comment__content"><?php echo wp_kses_post( get_comment_text( $comment ) ); ?></div>
				<div class="ax-comment__actions">
					<button type="button" class="ax-comment__reply" data-comment-id="<?php echo esc_attr( (string) $id ); ?>" data-comment-author="<?php echo esc_attr( $author ); ?>"><?php esc_html_e( 'Reply', 'axismundi-dialogs' ); ?></button>
					<?php if ( $show_replies ) : ?>
						<button type="button" class="ax-comment__toggle" aria-expanded="false">
							<span class="ax-comment__toggle-sign" aria-hidden="true"></span>
							<span><?php
								printf(
									/* translators: %s: number of replies. */
									esc_html( _n( '%s reply', '%s replies', count( $children ), 'axismundi-dialogs' ) ),
									esc_html( number_format_i18n( count( $children ) ) )
								);
							?></span>
						</button>
					<?php elseif ( $deeper_only ) : ?>
						<a class="ax-comment__more" href="<?php echo esc_url( get_comment_link( $comment ) ); ?>"><?php esc_html_e( 'Continue thread', 'axismundi-dialogs' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( $show_replies ) : ?>
				<ol class="ax-comment__children">
					<?php
					foreach ( $children as $axismundi_dialogs_child ) {
						echo $axismundi_dialogs_render_one( $axismundi_dialogs_child, $depth + 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Recursively escaped.
					}
					?>
				</ol>
			<?php endif; ?>
		</li>
		<?php
		return (string) ob_get_clean();
	};

	ob_start();
	echo '<ol class="ax-post-quick-view__comments-list">';
	foreach ( $axismundi_dialogs_top as $axismundi_dialogs_c ) {
		echo $axismundi_dialogs_render_one( $axismundi_dialogs_c, 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in closure.
	}
	echo '</ol>';
	if ( $axismundi_dialogs_has_more ) {
		printf(
			'<a class="ax-post-quick-view__more-comments" href="%1$s">%2$s</a>',
			esc_url( get_comments_link( $post_id ) ),
			esc_html__( 'View more comments', 'axismundi-dialogs' )
		);
	}
	return (string) ob_get_clean();
}
