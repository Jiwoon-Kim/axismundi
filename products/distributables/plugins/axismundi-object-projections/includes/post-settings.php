<?php
/**
 * Core Post federation authoring settings.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_OP_POST_SENSITIVE_META    = '_ax_op_sensitive';
const AXISMUNDI_OP_POST_WARNING_META      = '_ax_op_content_warning';
const AXISMUNDI_OP_POST_QUOTE_POLICY_META = '_ax_op_quote_policy';
const AXISMUNDI_OP_POST_VISIBILITY_META   = '_ax_op_visibility';
const AXISMUNDI_OP_POST_MENTIONS_META     = '_ax_op_mentions';

/** Sanitize a REST or form boolean. */
function axismundi_op_sanitize_post_sensitive( $value ) : bool {
	return rest_sanitize_boolean( $value );
}

/** Sanitize a public content-warning label. */
function axismundi_op_sanitize_content_warning( $value ) : string {
	$value = sanitize_text_field( (string) $value );
	return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 500 ) : substr( $value, 0, 500 );
}

/** Sanitize an explicitly authored FEP-044f Quote policy. */
function axismundi_op_sanitize_quote_policy( $value ) : string {
	$value = sanitize_key( (string) $value );
	return in_array( $value, array( 'anyone', 'followers', 'me' ), true ) ? $value : '';
}

/** Sanitize one authored federation visibility. */
function axismundi_op_sanitize_post_visibility( $value ) : string {
	$value = function_exists( 'axismundi_act_canonical_visibility' )
		? axismundi_act_canonical_visibility( (string) $value )
		: sanitize_key( (string) $value );
	return in_array( $value, array( 'public', 'unlisted', 'followers', 'mentioned' ), true ) ? $value : 'public';
}

/** Sanitize an ordered set of explicitly mentioned Actor URIs. */
function axismundi_op_sanitize_post_mentions( $value ) : array {
	$value = is_array( $value ) ? $value : preg_split( '/[\r\n,]+/', (string) $value );
	$uris  = array();
	foreach ( (array) $value as $member ) {
		$uri   = trim( (string) $member );
		$parts = wp_parse_url( $uri );
		$uri   = is_array( $parts )
			&& in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true )
			&& ! empty( $parts['host'] )
			&& ! isset( $parts['user'], $parts['pass'] )
			? $uri
			: '';
		if ( '' !== $uri ) {
			$uris[] = $uri;
		}
	}
	return array_values( array_unique( $uris ) );
}

/** Authorize edits to federation post metadata. */
function axismundi_op_auth_post_setting( $allowed, string $meta_key, int $post_id, int $user_id ) : bool {
	return user_can( $user_id, 'edit_post', $post_id );
}

/** Register the shared REST/editor and Quick Edit metadata contract. */
function axismundi_op_register_post_settings_meta() : void {
	register_post_meta(
		'post',
		AXISMUNDI_OP_POST_SENSITIVE_META,
		array(
			'type'              => 'boolean',
			'single'            => true,
			'default'           => false,
			'show_in_rest'      => true,
			'sanitize_callback' => 'axismundi_op_sanitize_post_sensitive',
			'auth_callback'     => 'axismundi_op_auth_post_setting',
		)
	);
	register_post_meta(
		'post',
		AXISMUNDI_OP_POST_WARNING_META,
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => array(
				'schema' => array(
					'type'      => 'string',
					'maxLength' => 500,
				),
			),
			'sanitize_callback' => 'axismundi_op_sanitize_content_warning',
			'auth_callback'     => 'axismundi_op_auth_post_setting',
		)
	);
	register_post_meta(
		'post',
		AXISMUNDI_OP_POST_QUOTE_POLICY_META,
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => array(
				'schema' => array(
					'type' => 'string',
					'enum' => array( '', 'anyone', 'followers', 'me' ),
				),
			),
			'sanitize_callback' => 'axismundi_op_sanitize_quote_policy',
			'auth_callback'     => 'axismundi_op_auth_post_setting',
		)
	);
	register_post_meta(
		'post',
		AXISMUNDI_OP_POST_VISIBILITY_META,
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => 'public',
			'show_in_rest'      => array( 'schema' => array( 'type' => 'string', 'enum' => array( 'public', 'unlisted', 'followers', 'mentioned' ) ) ),
			'sanitize_callback' => 'axismundi_op_sanitize_post_visibility',
			'auth_callback'     => 'axismundi_op_auth_post_setting',
		)
	);
	register_post_meta(
		'post',
		AXISMUNDI_OP_POST_MENTIONS_META,
		array(
			'type'              => 'array',
			'single'            => true,
			'default'           => array(),
			'show_in_rest'      => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'format' => 'uri' ) ) ),
			'sanitize_callback' => 'axismundi_op_sanitize_post_mentions',
			'auth_callback'     => 'axismundi_op_auth_post_setting',
		)
	);
}
add_action( 'init', 'axismundi_op_register_post_settings_meta' );

/** Whether a post is marked sensitive. */
function axismundi_op_post_is_sensitive( WP_Post $post ) : bool {
	return rest_sanitize_boolean( get_post_meta( $post->ID, AXISMUNDI_OP_POST_SENSITIVE_META, true ) );
}

/** Return the post's public content-warning label. */
function axismundi_op_post_content_warning( WP_Post $post ) : string {
	return axismundi_op_sanitize_content_warning( get_post_meta( $post->ID, AXISMUNDI_OP_POST_WARNING_META, true ) );
}

/** Return an explicit Quote policy, or an empty string when the author set none. */
function axismundi_op_post_quote_policy( WP_Post $post ) : string {
	return axismundi_op_sanitize_quote_policy( get_post_meta( $post->ID, AXISMUNDI_OP_POST_QUOTE_POLICY_META, true ) );
}

/** Return the post's canonical authored federation visibility. */
function axismundi_op_post_visibility( WP_Post $post ) : string {
	return axismundi_op_sanitize_post_visibility( get_post_meta( $post->ID, AXISMUNDI_OP_POST_VISIBILITY_META, true ) );
}

/** Return explicitly mentioned Actor URIs. */
function axismundi_op_post_mentions( WP_Post $post ) : array {
	return axismundi_op_sanitize_post_mentions( get_post_meta( $post->ID, AXISMUNDI_OP_POST_MENTIONS_META, true ) );
}

/** Add a compact federation state column used by Quick Edit. */
function axismundi_op_post_columns( array $columns ) : array {
	$columns['axismundi_op_federation'] = __( 'Federation', 'axismundi-object-projections' );
	return $columns;
}
add_filter( 'manage_post_posts_columns', 'axismundi_op_post_columns' );

/** Render the list-table value and machine-readable Quick Edit source. */
function axismundi_op_render_post_column( string $column, int $post_id ) : void {
	if ( 'axismundi_op_federation' !== $column ) {
		return;
	}
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post ) {
		return;
	}
	$sensitive    = axismundi_op_post_is_sensitive( $post );
	$warning      = axismundi_op_post_content_warning( $post );
	$quote_policy = axismundi_op_post_quote_policy( $post );
	$visibility   = axismundi_op_post_visibility( $post );
	$mentions     = axismundi_op_post_mentions( $post );
	printf(
		'<span class="axismundi-op-federation-state" data-sensitive="%1$d" data-warning="%2$s" data-quote-policy="%3$s" data-visibility="%4$s" data-mentions="%5$s">%6$s</span>',
		$sensitive ? 1 : 0,
		esc_attr( $warning ),
		esc_attr( $quote_policy ),
		esc_attr( $visibility ),
		esc_attr( implode( "\n", $mentions ) ),
		esc_html( $sensitive ? __( 'Sensitive', 'axismundi-object-projections' ) : __( 'Standard', 'axismundi-object-projections' ) )
	);
}
add_action( 'manage_post_posts_custom_column', 'axismundi_op_render_post_column', 10, 2 );

/** Add the shared fields to Core Post Quick Edit. */
function axismundi_op_quick_edit_fields( string $column, string $post_type ) : void {
	if ( 'axismundi_op_federation' !== $column || 'post' !== $post_type ) {
		return;
	}
	wp_nonce_field( 'axismundi_op_quick_edit', 'axismundi_op_quick_edit_nonce' );
	?>
	<fieldset class="inline-edit-col-right axismundi-op-quick-edit">
		<div class="inline-edit-col">
			<span class="title"><?php esc_html_e( 'Federation', 'axismundi-object-projections' ); ?></span>
			<input type="hidden" name="axismundi_op_quick_edit_present" value="1" />
			<label class="alignleft">
				<input type="checkbox" name="axismundi_op_sensitive" value="1" />
				<span class="checkbox-title"><?php esc_html_e( 'Sensitive content', 'axismundi-object-projections' ); ?></span>
			</label>
			<label class="alignleft">
				<span class="title"><?php esc_html_e( 'Content warning', 'axismundi-object-projections' ); ?></span>
				<span class="input-text-wrap"><input type="text" name="axismundi_op_content_warning" maxlength="500" /></span>
			</label>
			<label class="alignleft">
				<span class="title"><?php esc_html_e( 'Audience', 'axismundi-object-projections' ); ?></span>
				<select name="axismundi_op_visibility">
					<option value="public"><?php esc_html_e( 'Public', 'axismundi-object-projections' ); ?></option>
					<option value="unlisted"><?php esc_html_e( 'Quiet public', 'axismundi-object-projections' ); ?></option>
					<option value="followers"><?php esc_html_e( 'Followers', 'axismundi-object-projections' ); ?></option>
					<option value="mentioned"><?php esc_html_e( 'Mentioned only', 'axismundi-object-projections' ); ?></option>
				</select>
			</label>
			<label class="alignleft">
				<span class="title"><?php esc_html_e( 'Mentioned Actor URLs', 'axismundi-object-projections' ); ?></span>
				<textarea name="axismundi_op_mentions" rows="3"></textarea>
			</label>
			<label class="alignleft">
				<span class="title"><?php esc_html_e( 'Who can quote this post?', 'axismundi-object-projections' ); ?></span>
				<select name="axismundi_op_quote_policy">
					<option value=""><?php esc_html_e( 'Not specified', 'axismundi-object-projections' ); ?></option>
					<option value="anyone"><?php esc_html_e( 'Anyone', 'axismundi-object-projections' ); ?></option>
					<option value="followers"><?php esc_html_e( 'Followers only', 'axismundi-object-projections' ); ?></option>
					<option value="me"><?php esc_html_e( 'Just me', 'axismundi-object-projections' ); ?></option>
				</select>
			</label>
		</div>
	</fieldset>
	<?php
}
add_action( 'quick_edit_custom_box', 'axismundi_op_quick_edit_fields', 10, 2 );

/** Save Quick Edit federation settings. */
function axismundi_op_save_quick_edit( int $post_id, WP_Post $post ) : void {
	if ( 'post' !== $post->post_type || ! isset( $_POST['axismundi_op_quick_edit_present'] ) ) {
		return;
	}
	$nonce = isset( $_POST['axismundi_op_quick_edit_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['axismundi_op_quick_edit_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'axismundi_op_quick_edit' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$mention_input = isset( $_POST['axismundi_op_mentions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['axismundi_op_mentions'] ) ) : '';
	$mention_parts = array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', $mention_input ) ) ) );
	$mentions      = axismundi_op_sanitize_post_mentions( $mention_parts );
	if ( count( array_unique( $mention_parts ) ) !== count( $mentions ) ) {
		return;
	}
	update_post_meta( $post_id, AXISMUNDI_OP_POST_SENSITIVE_META, isset( $_POST['axismundi_op_sensitive'] ) ? '1' : '0' );
	$warning = isset( $_POST['axismundi_op_content_warning'] ) ? sanitize_text_field( wp_unslash( $_POST['axismundi_op_content_warning'] ) ) : '';
	update_post_meta( $post_id, AXISMUNDI_OP_POST_WARNING_META, axismundi_op_sanitize_content_warning( $warning ) );
	$quote_policy = isset( $_POST['axismundi_op_quote_policy'] ) ? sanitize_key( wp_unslash( $_POST['axismundi_op_quote_policy'] ) ) : '';
	$quote_policy = axismundi_op_sanitize_quote_policy( $quote_policy );
	if ( '' === $quote_policy ) {
		delete_post_meta( $post_id, AXISMUNDI_OP_POST_QUOTE_POLICY_META );
	} else {
		update_post_meta( $post_id, AXISMUNDI_OP_POST_QUOTE_POLICY_META, $quote_policy );
	}
	$visibility = isset( $_POST['axismundi_op_visibility'] ) ? sanitize_key( wp_unslash( $_POST['axismundi_op_visibility'] ) ) : 'public';
	update_post_meta( $post_id, AXISMUNDI_OP_POST_VISIBILITY_META, axismundi_op_sanitize_post_visibility( $visibility ) );
	update_post_meta( $post_id, AXISMUNDI_OP_POST_MENTIONS_META, $mentions );
}
add_action( 'save_post_post', 'axismundi_op_save_quick_edit', 10, 2 );

/** Load the document-settings panel in the Core Post block editor. */
function axismundi_op_enqueue_post_editor_settings() : void {
	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->post_type || ! $screen->is_block_editor() ) {
		return;
	}
	wp_enqueue_script(
		'axismundi-op-post-settings',
		plugins_url( 'assets/post-settings.js', dirname( __DIR__ ) . '/axismundi-object-projections.php' ),
		array( 'wp-components', 'wp-data', 'wp-edit-post', 'wp-element', 'wp-i18n', 'wp-plugins' ),
		AXISMUNDI_OP_VERSION,
		true
	);
	wp_set_script_translations( 'axismundi-op-post-settings', 'axismundi-object-projections' );
}
add_action( 'enqueue_block_editor_assets', 'axismundi_op_enqueue_post_editor_settings' );

/** Populate Quick Edit from the current list-table row. */
function axismundi_op_enqueue_quick_edit() : void {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-post' !== $screen->id ) {
		return;
	}
	wp_enqueue_script(
		'axismundi-op-quick-edit',
		plugins_url( 'assets/quick-edit.js', dirname( __DIR__ ) . '/axismundi-object-projections.php' ),
		array( 'inline-edit-post' ),
		AXISMUNDI_OP_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'axismundi_op_enqueue_quick_edit' );
