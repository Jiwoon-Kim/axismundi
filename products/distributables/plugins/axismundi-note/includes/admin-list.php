<?php
/**
 * The Notes admin list, shaped like Comments rather than like Posts.
 *
 * A Note has no title, so the Posts list gave it a column that could only ever say "(no title)" and
 * put the one thing worth reading — what the Note says — nowhere. Comments solved the same problem
 * long ago: the text is the row, the author sits beside it, and what it answers is a column of its
 * own. This mirrors that arrangement rather than inventing a third one, so the screen behaves the
 * way anyone moderating a WordPress site already expects.
 *
 * The author column names the Actor, not the WordPress user. They are usually the same person and
 * are not the same identity: a Note is attributed to an Actor URI, that is what federates, and a
 * list that showed the WP display name would be showing something no peer ever sees.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

/**
 * Replace the Posts columns with the Comments arrangement.
 *
 * `title` goes because the post type does not support one. `author` goes because it names the
 * WordPress user; the Actor column replaces it rather than sitting beside it, which would show two
 * author columns that disagree whenever an Actor is renamed.
 *
 * @param array<string,string> $columns Existing columns.
 * @return array<string,string>
 */
function axismundi_note_admin_columns( array $columns ) : array {
	$rebuilt = array();
	if ( isset( $columns['cb'] ) ) {
		$rebuilt['cb'] = $columns['cb'];
	}
	$rebuilt['ax_note_author']  = __( 'Author', 'axismundi-note' );
	$rebuilt['ax_note_type']    = __( 'Type', 'axismundi-note' );
	$rebuilt['ax_note_content'] = __( 'Note', 'axismundi-note' );
	$rebuilt['ax_note_context'] = __( 'In reply to', 'axismundi-note' );
	$rebuilt['date']            = $columns['date'] ?? __( 'Submitted on', 'axismundi-note' );
	return $rebuilt;
}
add_filter( 'manage_' . AXISMUNDI_NOTE_POST_TYPE . '_posts_columns', 'axismundi_note_admin_columns' );

/**
 * Hang the row actions off the Note text.
 *
 * Core attaches them to whichever column it considers primary, and its default is `title` — a
 * column this screen no longer has, which would leave every row without Edit or Trash.
 *
 * @param string $default Column Core would use.
 * @param string $context Screen id.
 * @return string
 */
function axismundi_note_admin_primary_column( string $default, string $context ) : string {
	return 'edit-' . AXISMUNDI_NOTE_POST_TYPE === $context ? 'ax_note_content' : $default;
}
add_filter( 'list_table_primary_column', 'axismundi_note_admin_primary_column', 10, 2 );

/**
 * The Actor a Note is attributed to, rendered as avatar, name and handle.
 *
 * @param array<string,mixed> $envelope Note envelope.
 * @return string
 */
function axismundi_note_admin_author_cell( array $envelope ) : string {
	$uri = (string) ( $envelope['actor_uri'] ?? '' );
	if ( '' === $uri || ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
		return '<span class="ax-note-row__muted">' . esc_html__( 'Unattributed', 'axismundi-note' ) . '</span>';
	}
	$actor = axismundi_actors_get_by_uri( $uri );
	if ( ! $actor instanceof Axismundi_Actor ) {
		// The URI is the attribution of record; showing it beats showing nothing when the Actor
		// row is gone, because it is still what every peer received.
		return '<span class="ax-note-row__muted">' . esc_html( $uri ) . '</span>';
	}
	$name   = $actor->get_display_name();
	$name   = '' !== $name ? $name : $actor->get_preferred_username();
	$handle = function_exists( 'axismundi_actors_mention_handle' ) ? (string) axismundi_actors_mention_handle( $actor ) : '';
	$avatar = function_exists( 'axismundi_actors_avatar_url' ) ? (string) axismundi_actors_avatar_url( $actor ) : '';
	$out    = '';
	if ( '' !== $avatar ) {
		$out .= '<img class="ax-note-row__avatar" src="' . esc_url( $avatar ) . '" alt="" width="32" height="32" loading="lazy" decoding="async" />';
	}
	$profile = $actor->get_profile_url();
	$label   = '' !== $profile
		? '<a href="' . esc_url( $profile ) . '">' . esc_html( $name ) . '</a>'
		: esc_html( $name );
	$out .= '<span class="ax-note-row__identity"><strong>' . $label . '</strong>';
	if ( '' !== $handle ) {
		$out .= '<br /><span class="ax-note-row__muted">' . esc_html( $handle ) . '</span>';
	}
	return $out . '</span>';
}

/**
 * What the Note says, plus the facts a moderator needs before reading it.
 *
 * A content warning is printed as a warning rather than used to hide the text. This screen exists to
 * moderate, and moderation that cannot see what it is moderating is not moderation — but a moderator
 * who is about to read warned material should be told so first, which is what the author asked for.
 *
 * @param array<string,mixed> $envelope Note envelope.
 * @param WP_Post             $post     Listed post.
 * @return string
 */
function axismundi_note_admin_content_cell( array $envelope, WP_Post $post ) : string {
	$out     = '';
	$warning = trim( (string) ( $envelope['content_warning'] ?? '' ) );
	if ( ! empty( $envelope['is_sensitive'] ) ) {
		$label = '' !== $warning ? $warning : __( 'Sensitive', 'axismundi-note' );
		$out  .= '<span class="ax-note-row__warning">' . esc_html( $label ) . '</span> ';
	}
	$excerpt = trim( wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 25 ) );
	if ( '' === $excerpt ) {
		$excerpt = __( '(no text)', 'axismundi-note' );
	}
	$out .= '<span class="ax-note-row__text">' . esc_html( $excerpt ) . '</span>';

	$flags = array();
	$visibility = (string) ( $envelope['visibility'] ?? '' );
	if ( '' !== $visibility && 'public' !== $visibility ) {
		$flags[] = esc_html( $visibility );
	}
	if ( 'active' !== (string) ( $envelope['object_status'] ?? 'active' ) ) {
		$flags[] = esc_html( (string) $envelope['object_status'] );
	}
	if ( ! empty( $flags ) ) {
		$out .= ' <span class="ax-note-row__muted">— ' . implode( ', ', $flags ) . '</span>';
	}
	return $out . axismundi_note_admin_inline_data( $envelope, $post );
}

/**
 * The Object form this Note takes: Note, Question or Quote.
 *
 * One value, from the shared classifier, because these three are alternatives rather than flags. A
 * quote of a Question is a Quote — the form belongs to this Object, and being a Question belongs to
 * the thing it points at, so there is no compound label to print.
 *
 * Reply and quote *targets* are not shown here. They are relations to other Objects, and stating
 * them in a Type column was the confusion this replaced; they belong in the context column beside
 * what they point at.
 *
 * @param array<string,mixed> $envelope Note envelope (unused; the form is read by post).
 * @param WP_Post             $post     Listed post.
 * @return string
 */
function axismundi_note_admin_type_cell( array $envelope, WP_Post $post ) : string {
	unset( $envelope );
	$form = function_exists( 'axismundi_note_object_form' ) ? axismundi_note_object_form( $post->ID ) : 'note';
	return '<strong>' . esc_html( axismundi_note_object_form_label( $form ) ) . '</strong>';
}

/**
 * What this Note answers, as Comments states the post a comment is on.
 *
 * The parent is resolved through the shared thread accessor when it is available, so a reply to a
 * cached remote Object names its author rather than printing a bare URI. An unresolved parent still
 * shows its URI: the reply genuinely points there, and hiding that would make an unresolved thread
 * look like a root Note.
 *
 * @param array<string,mixed> $envelope Note envelope.
 * @return string
 */
function axismundi_note_admin_context_cell( array $envelope ) : string {
	$parent_uri = trim( (string) ( $envelope['in_reply_to_uri'] ?? '' ) );
	if ( '' === $parent_uri ) {
		return '<span class="ax-note-row__muted">' . esc_html__( '&mdash;', 'axismundi-note' ) . '</span>';
	}
	$child_uri = function_exists( 'axismundi_note_object_uri' ) ? axismundi_note_object_uri( (string) ( $envelope['local_uuid'] ?? '' ) ) : '';
	$out       = '';

	/*
	 * The conversation first, the direct parent second — which is the order Comments states them.
	 *
	 * The thread index already records each reply's root, so this is a lookup rather than a walk up
	 * the chain. A root that is an Article or a Topic has a title and that is what a moderator
	 * recognises; a Note or Question root has none, so it is named by its author instead of being
	 * given an invented one.
	 */
	$root_uri = '';
	if ( '' !== $child_uri && function_exists( 'axismundi_op_get_thread_edge' ) ) {
		$edge     = axismundi_op_get_thread_edge( $child_uri );
		$root_uri = is_array( $edge ) ? trim( (string) ( $edge['root_uri'] ?? '' ) ) : '';
	}
	if ( '' !== $root_uri ) {
		$label = axismundi_note_admin_object_label( $root_uri );
		$out  .= '<a href="' . esc_url( $root_uri ) . '"><strong>' . esc_html( $label ) . '</strong></a>';
	}

	/*
	 * Only when it differs from the root. On a two-deep thread the parent is the root, and printing
	 * the same line twice would say the conversation has a shape it does not have.
	 */
	if ( $parent_uri !== $root_uri ) {
		$parent_label = axismundi_note_admin_object_label( $parent_uri );
		$out         .= ( '' !== $out ? '<br />' : '' )
			/* translators: %s: the Object being replied to. */
			. '<span class="ax-note-row__muted">' . esc_html( sprintf( __( 'in reply to %s', 'axismundi-note' ), $parent_label ) ) . '</span>';
	}
	if ( '' === $out ) {
		$out = '<a href="' . esc_url( $parent_uri ) . '">' . esc_html__( 'Reply', 'axismundi-note' ) . '</a>';
	}
	return $out . axismundi_note_admin_quote_line( $envelope );
}

/**
 * The Object a Quote points at, on its own line.
 *
 * Separate from the thread because quoting is independent of replying: a Quote may sit in no thread
 * at all, and a reply may quote something from a different conversation entirely. Folding the two
 * into one line would state a relationship between them that the data does not have.
 *
 * @param array<string,mixed> $envelope Note envelope.
 * @return string
 */
function axismundi_note_admin_quote_line( array $envelope ) : string {
	$target = trim( (string) ( $envelope['quote_target_uri'] ?? '' ) );
	if ( '' === $target ) {
		return '';
	}
	/* translators: %s: the quoted Object. */
	$label = sprintf( __( 'Quotes: %s', 'axismundi-note' ), axismundi_note_admin_object_label( $target ) );
	return '<br /><a href="' . esc_url( $target ) . '"><span class="ax-note-row__muted">' . esc_html( $label ) . '</span></a>';
}

/**
 * A short human label for one Object URI: its title, or its author, or the URI itself.
 *
 * Resolution goes through the public renderability gate, so a private ancestor is named by neither
 * its title nor its author here. That is deliberate — this list must not become a way to read what
 * an Object's audience excludes — and the URI still shows, because the reply genuinely points there.
 *
 * @param string $uri Object URI.
 * @return string
 */
function axismundi_note_admin_object_label( string $uri ) : string {
	if ( ! function_exists( 'axismundi_op_resolve_source_by_uri' ) || ! function_exists( 'axismundi_op_object_card_publicly_renderable' ) ) {
		return $uri;
	}
	$source = axismundi_op_resolve_source_by_uri( $uri );
	if ( null === $source ) {
		return $uri;
	}
	/*
	 * Public renderability, or the capability to moderate other people's posts.
	 *
	 * The public gate alone was too tight for this screen: most threads here begin with a Note whose
	 * audience is not Public, so every row named its ancestor with a raw URI — the one thing the
	 * column exists to replace. Relaxing it to `edit_others_posts` keeps the boundary meaningful,
	 * because a user without it sees the URI exactly as before, and does not invent a new one: it is
	 * the capability WordPress already uses to decide who may read and moderate others' content.
	 */
	if ( ! axismundi_op_object_card_publicly_renderable( $source ) && ! current_user_can( 'edit_others_posts' ) ) {
		return $uri;
	}
	$model = axismundi_op_object_view_model( $source );
	if ( ! is_array( $model ) ) {
		return $uri;
	}
	$title = trim( (string) ( $model['title'] ?? '' ) );
	if ( '' !== $title ) {
		return $title;
	}
	$author = trim( (string) ( $model['author']['name'] ?? '' ) );
	$type   = 'Question' === (string) ( $model['type'] ?? '' ) ? __( 'Question', 'axismundi-note' ) : __( 'Note', 'axismundi-note' );
	if ( '' === $author ) {
		return $uri;
	}
	/* translators: 1: object type, 2: author display name. */
	return sprintf( __( '%1$s by %2$s', 'axismundi-note' ), $type, $author );
}

/**
 * Render one custom column.
 *
 * @param string $column  Column key.
 * @param int    $post_id Listed post ID.
 * @return void
 */
function axismundi_note_admin_render_column( string $column, int $post_id ) : void {
	if ( ! in_array( $column, array( 'ax_note_author', 'ax_note_type', 'ax_note_content', 'ax_note_context' ), true ) ) {
		return;
	}
	$post     = get_post( $post_id );
	$envelope = function_exists( 'axismundi_note_get' ) ? axismundi_note_get( $post_id ) : null;
	if ( ! $post instanceof WP_Post || ! is_array( $envelope ) ) {
		return;
	}
	$html = '';
	if ( 'ax_note_author' === $column ) {
		$html = axismundi_note_admin_author_cell( $envelope );
	} elseif ( 'ax_note_type' === $column ) {
		$html = axismundi_note_admin_type_cell( $envelope, $post );
	} elseif ( 'ax_note_content' === $column ) {
		$html = axismundi_note_admin_content_cell( $envelope, $post );
	} else {
		$html = axismundi_note_admin_context_cell( $envelope );
	}
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each cell escapes its own parts above.
	echo $html;
}
add_action( 'manage_' . AXISMUNDI_NOTE_POST_TYPE . '_posts_custom_column', 'axismundi_note_admin_render_column', 10, 2 );

/**
 * The little styling the arrangement needs, inline and only on this screen.
 *
 * A separate stylesheet for four rules would be a request per admin page load for something only
 * this screen ever uses.
 *
 * @return void
 */
function axismundi_note_admin_list_styles() : void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen instanceof WP_Screen || 'edit-' . AXISMUNDI_NOTE_POST_TYPE !== $screen->id ) {
		return;
	}
	$css = '.column-ax_note_author{width:18%}.column-ax_note_context{width:22%}'
		. '.ax-note-row__avatar{float:left;margin-right:8px;border-radius:50%}'
		. '.ax-note-row__identity{display:block;overflow:hidden}'
		. '.ax-note-row__muted{color:#646970}'
		. '.ax-note-row__warning{display:inline-block;padding:0 6px;border-radius:9px;background:#f0b849;color:#1d2327;font-size:11px;font-weight:600}';
	wp_add_inline_style( 'list-tables', $css );

	$base = dirname( __DIR__ ) . '/axismundi-note.php';
	$js   = dirname( __DIR__ ) . '/assets/admin-list.js';
	wp_enqueue_script(
		'axismundi-note-admin-list',
		plugins_url( 'assets/admin-list.js', $base ),
		array( 'inline-edit-post' ),
		file_exists( $js ) ? (string) filemtime( $js ) : AXISMUNDI_NOTE_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'axismundi_note_admin_list_styles' );

/**
 * A Reply action beside Edit, as Comments offers one.
 *
 * It opens the Note editor with this Object as the parent rather than an inline form. Composing a
 * reply here is composing a Note — the same visibility, warning, attachment and federation
 * decisions as any other — and a two-field box in a table row cannot ask for those without either
 * hiding them or growing into the editor it is standing in for. The compose URL comes from the same
 * filter the front-end Reply control uses, so both routes agree on what replying means.
 *
 * @param array<string,string> $actions Existing row actions.
 * @param WP_Post              $post    Listed post.
 * @return array<string,string>
 */
function axismundi_note_admin_reply_row_action( array $actions, WP_Post $post ) : array {
	if ( AXISMUNDI_NOTE_POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
		return $actions;
	}
	if ( ! current_user_can( 'edit_posts' ) || ! function_exists( 'axismundi_note_reply_compose_url' ) ) {
		return $actions;
	}
	$envelope = axismundi_note_get( $post->ID );
	$uuid     = is_array( $envelope ) ? (string) ( $envelope['local_uuid'] ?? '' ) : '';
	if ( '' === $uuid ) {
		return $actions;
	}
	$url = axismundi_note_reply_compose_url( '', axismundi_note_object_uri( $uuid ) );
	if ( '' === $url ) {
		return $actions;
	}
	$reply = array( 'ax_reply' => '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Reply', 'axismundi-note' ) . '</a>' );
	// Before Quick Edit, matching the order Comments uses.
	$offset = array_search( 'inline hide-if-no-js', array_keys( $actions ), true );
	if ( false === $offset ) {
		return $actions + $reply;
	}
	return array_slice( $actions, 0, (int) $offset, true ) + $reply + array_slice( $actions, (int) $offset, null, true );
}
add_filter( 'post_row_actions', 'axismundi_note_admin_reply_row_action', 9, 2 );

/**
 * The row's current envelope values, for Quick Edit to read.
 *
 * Core's inline editor copies a row's values out of the DOM; it knows nothing about fields a plugin
 * adds, so without this the Note fields would open blank and saving would write those blanks over
 * real values. Emitted hidden in a column rather than as a separate request, which is how Core
 * carries its own inline data.
 *
 * @param array<string,mixed> $envelope Note envelope.
 * @param WP_Post             $post     Listed post.
 * @return string
 */
function axismundi_note_admin_inline_data( array $envelope, WP_Post $post ) : string {
	return '<div class="hidden ax-note-inline" id="ax-note-inline-' . (int) $post->ID . '"'
		. ' data-visibility="' . esc_attr( (string) ( $envelope['visibility'] ?? 'public' ) ) . '"'
		. ' data-sensitive="' . ( empty( $envelope['is_sensitive'] ) ? '0' : '1' ) . '"'
		. ' data-warning="' . esc_attr( (string) ( $envelope['content_warning'] ?? '' ) ) . '"></div>';
}

/**
 * Note fields inside Core's Quick Edit form.
 *
 * Visibility and the content warning are the two things a moderator changes without wanting the
 * editor, and both are envelope fields Core's own Quick Edit cannot see.
 *
 * @param string $column    Column the box is rendered for.
 * @param string $post_type Post type.
 * @return void
 */
function axismundi_note_admin_quick_edit_box( string $column, string $post_type ) : void {
	if ( AXISMUNDI_NOTE_POST_TYPE !== $post_type || 'ax_note_content' !== $column ) {
		return;
	}
	wp_nonce_field( 'axismundi_note_quick_edit', 'axismundi_note_quick_edit_nonce' );
	?>
	<fieldset class="inline-edit-col-right ax-note-quick-edit">
		<div class="inline-edit-col">
			<label class="inline-edit-group">
				<span class="title"><?php esc_html_e( 'Visibility', 'axismundi-note' ); ?></span>
				<select name="ax_note_visibility">
					<?php
					$tiers = array(
						'public'    => __( 'Public', 'axismundi-note' ),
						'unlisted'  => __( 'Unlisted', 'axismundi-note' ),
						'followers' => __( 'Followers', 'axismundi-note' ),
						'mentioned' => __( 'Mentioned only', 'axismundi-note' ),
					);
					foreach ( $tiers as $value => $label ) {
						echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
					}
					?>
				</select>
			</label>
			<label class="inline-edit-group">
				<input type="checkbox" name="ax_note_sensitive" value="1" />
				<span class="checkbox-title"><?php esc_html_e( 'Sensitive', 'axismundi-note' ); ?></span>
			</label>
			<label class="inline-edit-group">
				<span class="title"><?php esc_html_e( 'Warning', 'axismundi-note' ); ?></span>
				<input type="text" name="ax_note_warning" value="" maxlength="500" />
			</label>
		</div>
	</fieldset>
	<?php
}
add_action( 'quick_edit_custom_box', 'axismundi_note_admin_quick_edit_box', 10, 2 );

/**
 * Persist the Quick Edit envelope fields.
 *
 * Gated on the nonce this form prints rather than on the post type alone: every other save path —
 * the editor, REST, the importer — writes these fields through its own validated route, and a
 * handler that fired for all of them would overwrite an editor save with whatever a stale inline
 * form happened to hold. Absent fields are left alone for the same reason.
 *
 * @param int $post_id Saved post ID.
 * @return void
 */
function axismundi_note_admin_quick_edit_save( int $post_id ) : void {
	if ( ! isset( $_POST['axismundi_note_quick_edit_nonce'] ) ) {
		return;
	}
	$nonce = sanitize_text_field( wp_unslash( (string) $_POST['axismundi_note_quick_edit_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'axismundi_note_quick_edit' ) ) {
		return;
	}
	if ( AXISMUNDI_NOTE_POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ! function_exists( 'axismundi_note_save' ) || ! is_array( axismundi_note_get( $post_id ) ) ) {
		return;
	}
	$fields = array();
	if ( isset( $_POST['ax_note_visibility'] ) ) {
		$visibility = sanitize_key( wp_unslash( (string) $_POST['ax_note_visibility'] ) );
		if ( in_array( $visibility, array( 'public', 'unlisted', 'followers', 'mentioned' ), true ) ) {
			$fields['visibility'] = $visibility;
		}
	}
	// An unchecked checkbox posts nothing, so its absence is the value rather than a missing field.
	$fields['is_sensitive'] = empty( $_POST['ax_note_sensitive'] ) ? 0 : 1;
	if ( isset( $_POST['ax_note_warning'] ) ) {
		$fields['content_warning'] = sanitize_text_field( wp_unslash( (string) $_POST['ax_note_warning'] ) );
	}
	if ( ! empty( $fields ) ) {
		axismundi_note_save( $post_id, $fields );
	}
}
add_action( 'save_post_' . AXISMUNDI_NOTE_POST_TYPE, 'axismundi_note_admin_quick_edit_save', 20, 1 );
