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
const AXISMUNDI_OP_POST_LANGUAGE_META     = '_ax_op_language';

/** Return the searchable BCP-47 language choices shared by local-object editors. */
function axismundi_op_language_options() : array {
	$choices = array(
		'ar'    => __( 'Arabic', 'axismundi-object-projections' ),
		'bn'    => __( 'Bengali', 'axismundi-object-projections' ),
		'zh'    => __( 'Chinese', 'axismundi-object-projections' ),
		'zh-CN' => __( 'Chinese (Simplified)', 'axismundi-object-projections' ),
		'zh-TW' => __( 'Chinese (Traditional)', 'axismundi-object-projections' ),
		'cs'    => __( 'Czech', 'axismundi-object-projections' ),
		'da'    => __( 'Danish', 'axismundi-object-projections' ),
		'nl'    => __( 'Dutch', 'axismundi-object-projections' ),
		'en'    => __( 'English', 'axismundi-object-projections' ),
		'en-GB' => __( 'English (United Kingdom)', 'axismundi-object-projections' ),
		'en-US' => __( 'English (United States)', 'axismundi-object-projections' ),
		'fi'    => __( 'Finnish', 'axismundi-object-projections' ),
		'fr'    => __( 'French', 'axismundi-object-projections' ),
		'de'    => __( 'German', 'axismundi-object-projections' ),
		'el'    => __( 'Greek', 'axismundi-object-projections' ),
		'hi'    => __( 'Hindi', 'axismundi-object-projections' ),
		'id'    => __( 'Indonesian', 'axismundi-object-projections' ),
		'it'    => __( 'Italian', 'axismundi-object-projections' ),
		'ja'    => __( 'Japanese', 'axismundi-object-projections' ),
		'ko'    => __( 'Korean', 'axismundi-object-projections' ),
		'ko-KR' => __( 'Korean (Korea)', 'axismundi-object-projections' ),
		'nb'    => __( 'Norwegian Bokmal', 'axismundi-object-projections' ),
		'pl'    => __( 'Polish', 'axismundi-object-projections' ),
		'pt'    => __( 'Portuguese', 'axismundi-object-projections' ),
		'pt-BR' => __( 'Portuguese (Brazil)', 'axismundi-object-projections' ),
		'ro'    => __( 'Romanian', 'axismundi-object-projections' ),
		'ru'    => __( 'Russian', 'axismundi-object-projections' ),
		'es'    => __( 'Spanish', 'axismundi-object-projections' ),
		'sv'    => __( 'Swedish', 'axismundi-object-projections' ),
		'th'    => __( 'Thai', 'axismundi-object-projections' ),
		'tr'    => __( 'Turkish', 'axismundi-object-projections' ),
		'uk'    => __( 'Ukrainian', 'axismundi-object-projections' ),
		'vi'    => __( 'Vietnamese', 'axismundi-object-projections' ),
		'und'   => __( 'Undetermined', 'axismundi-object-projections' ),
	);
	$options = array();
	foreach ( $choices as $value => $label ) {
		$options[] = array( 'value' => $value, 'label' => $label . ' (' . $value . ')' );
	}
	return $options;
}

/** Normalize one BCP-47 tag, or return an empty string for invalid input. */
function axismundi_op_normalize_language( string $language ) : string {
	if ( function_exists( 'axismundi_actors_normalize_language_tag' ) ) {
		return axismundi_actors_normalize_language_tag( $language );
	}
	$language = trim( str_replace( '_', '-', $language ) );
	if ( '' === $language || 1 !== preg_match( '/^(?:[A-Za-z]{2,8})(?:-[A-Za-z0-9]{1,8})*$|^und$/i', $language ) ) {
		return '';
	}
	$parts    = explode( '-', $language );
	$parts[0] = strtolower( $parts[0] );
	foreach ( $parts as $index => $part ) {
		if ( 0 === $index ) {
			continue;
		}
		$parts[ $index ] = 2 === strlen( $part ) && ctype_alpha( $part ) ? strtoupper( $part ) : $part;
	}
	return implode( '-', $parts );
}

/** Resolve the inherited language and its source for an authored local user. */
function axismundi_op_default_language_for_user( int $user_id ) : array {
	if ( $user_id > 0 && function_exists( 'axismundi_actors_get_for_user' ) ) {
		$actor = axismundi_actors_get_for_user( $user_id );
		if ( $actor instanceof Axismundi_Actor ) {
			$language = axismundi_op_normalize_language( (string) $actor->get_default_language() );
			if ( '' !== $language ) {
				return array( 'language' => $language, 'source' => 'actor' );
			}
		}
	}
	if ( $user_id > 0 ) {
		$language = axismundi_op_normalize_language( get_user_locale( $user_id ) );
		if ( '' !== $language ) {
			return array( 'language' => $language, 'source' => 'user' );
		}
	}
	$site_language = function_exists( 'axismundi_actors_site_language' ) ? axismundi_actors_site_language() : get_locale();
	$site_language = axismundi_op_normalize_language( $site_language );
	return array( 'language' => '' !== $site_language ? $site_language : 'und', 'source' => 'site' );
}

/** Resolve the existing editor post author, or the current user for a new post. */
function axismundi_op_editor_language_user_id( string $post_type ) : int {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor route hint; no state changes occur here.
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	$post    = $post_id > 0 ? get_post( $post_id ) : null;
	return $post instanceof WP_Post && $post_type === $post->post_type
		? (int) $post->post_author
		: get_current_user_id();
}

/** Sanitize a stored Article language override. */
function axismundi_op_sanitize_post_language( $value ) : string {
	return is_scalar( $value ) ? axismundi_op_normalize_language( (string) $value ) : '';
}

/** Resolve the effective Article language without materializing an inherited override. */
function axismundi_op_post_effective_language( WP_Post $post ) : string {
	$stored = axismundi_op_sanitize_post_language( get_post_meta( $post->ID, AXISMUNDI_OP_POST_LANGUAGE_META, true ) );
	if ( '' !== $stored ) {
		return $stored;
	}
	$resolved = axismundi_op_default_language_for_user( (int) $post->post_author );
	return $resolved['language'];
}

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
	register_post_meta(
		'post',
		AXISMUNDI_OP_POST_LANGUAGE_META,
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => array( 'schema' => array( 'type' => 'string', 'maxLength' => 35 ) ),
			'sanitize_callback' => 'axismundi_op_sanitize_post_language',
			'auth_callback'     => 'axismundi_op_auth_post_setting',
		)
	);
}
add_action( 'init', 'axismundi_op_register_post_settings_meta' );

/** Persist product defaults only for newly created Posts, never legacy content. */
function axismundi_op_initialize_post_settings( int $post_id, WP_Post $post, bool $update ) : void {
	if ( $update || 'post' !== $post->post_type || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! metadata_exists( 'post', $post_id, AXISMUNDI_OP_POST_VISIBILITY_META ) ) {
		update_post_meta( $post_id, AXISMUNDI_OP_POST_VISIBILITY_META, 'public' );
	}
	if ( ! metadata_exists( 'post', $post_id, AXISMUNDI_OP_POST_QUOTE_POLICY_META ) ) {
		update_post_meta( $post_id, AXISMUNDI_OP_POST_QUOTE_POLICY_META, 'anyone' );
	}
}
add_action( 'wp_after_insert_post', 'axismundi_op_initialize_post_settings', 10, 3 );

/** Whether a post is marked sensitive. */
function axismundi_op_post_is_sensitive( WP_Post $post ) : bool {
	return rest_sanitize_boolean( get_post_meta( $post->ID, AXISMUNDI_OP_POST_SENSITIVE_META, true ) );
}

/** Return the post's public content-warning label. */
function axismundi_op_post_content_warning( WP_Post $post ) : string {
	return axismundi_op_sanitize_content_warning( get_post_meta( $post->ID, AXISMUNDI_OP_POST_WARNING_META, true ) );
}

/** Return only an explicitly stored Quote policy; legacy unset content denies. */
function axismundi_op_post_quote_policy( WP_Post $post ) : string {
	return axismundi_op_sanitize_quote_policy( get_post_meta( $post->ID, AXISMUNDI_OP_POST_QUOTE_POLICY_META, true ) );
}

/** Return the post's canonical authored federation visibility. */
function axismundi_op_post_visibility( WP_Post $post ) : string {
	return axismundi_op_sanitize_post_visibility( get_post_meta( $post->ID, AXISMUNDI_OP_POST_VISIBILITY_META, true ) );
}

/**
 * Derive validated Actor mention URIs from `a.mention[href]` anchors in one HTML body.
 *
 * The neutral parser is shared by every local object type (Core Post Article and
 * the Note CPT) so both derive mentions with one `WP_HTML_Tag_Processor` contract
 * and cannot drift. Only URL-shaped hrefs survive; Actor identity is verified at
 * the publish boundary, not here.
 */
function axismundi_op_content_mention_uris( string $html ) : array {
	if ( '' === trim( $html ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return array();
	}
	$processor = new WP_HTML_Tag_Processor( $html );
	$uris      = array();
	while ( $processor->next_tag( 'A' ) ) {
		$classes = preg_split( '/\s+/', trim( (string) $processor->get_attribute( 'class' ) ) );
		if ( ! in_array( 'mention', (array) $classes, true ) ) {
			continue;
		}
		$valid = axismundi_op_sanitize_post_mentions( array( (string) $processor->get_attribute( 'href' ) ) );
		if ( isset( $valid[0] ) ) {
			$uris[] = $valid[0];
		}
	}
	return array_values( array_unique( $uris ) );
}

/** Derive Actor URIs from explicit mention anchors in saved block HTML. */
function axismundi_op_post_content_mentions( WP_Post $post ) : array {
	return axismundi_op_content_mention_uris( $post->post_content );
}

/** Return the ordered union of authored and content-derived Actor mentions. */
function axismundi_op_post_mentions( WP_Post $post ) : array {
	$explicit = axismundi_op_sanitize_post_mentions( get_post_meta( $post->ID, AXISMUNDI_OP_POST_MENTIONS_META, true ) );
	return array_values( array_unique( array_merge( $explicit, axismundi_op_post_content_mentions( $post ) ) ) );
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
					<option value=""><?php esc_html_e( 'Not specified (deny)', 'axismundi-object-projections' ); ?></option>
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

/** Register the shared resolved-Actor token control for dependent editors. */
function axismundi_op_register_mention_token_field() : void {
	wp_register_script(
		'axismundi-op-mention-token-field',
		plugins_url( 'assets/mention-token-field.js', dirname( __DIR__ ) . '/axismundi-object-projections.php' ),
		array( 'wp-api-fetch', 'wp-components', 'wp-element', 'wp-url' ),
		AXISMUNDI_OP_VERSION,
		true
	);
}
add_action( 'init', 'axismundi_op_register_mention_token_field' );

/** Load the document-settings panel in the Core Post block editor. */
function axismundi_op_enqueue_post_editor_settings() : void {
	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->post_type || ! $screen->is_block_editor() ) {
		return;
	}
	wp_enqueue_script( 'axismundi-op-mention-token-field' );
	wp_enqueue_script(
		'axismundi-op-post-settings',
		plugins_url( 'assets/post-settings.js', dirname( __DIR__ ) . '/axismundi-object-projections.php' ),
		array( 'axismundi-op-mention-token-field', 'wp-components', 'wp-data', 'wp-edit-post', 'wp-element', 'wp-i18n', 'wp-plugins' ),
		AXISMUNDI_OP_VERSION . '-' . (string) filemtime( dirname( __DIR__ ) . '/assets/post-settings.js' ),
		true
	);
	$default_language = axismundi_op_default_language_for_user( axismundi_op_editor_language_user_id( 'post' ) );
	wp_localize_script(
		'axismundi-op-post-settings',
		'axismundiPostLanguage',
		array(
			'options' => axismundi_op_language_options(),
			'default' => $default_language,
		)
	);
	wp_enqueue_script(
		'axismundi-op-mention-autocomplete',
		plugins_url( 'assets/mention-autocomplete.js', dirname( __DIR__ ) . '/axismundi-object-projections.php' ),
		array( 'wp-api-fetch', 'wp-element', 'wp-hooks', 'wp-i18n', 'wp-url' ),
		AXISMUNDI_OP_VERSION . '-mentions',
		true
	);
	wp_set_script_translations( 'axismundi-op-post-settings', 'axismundi-object-projections' );
	wp_set_script_translations( 'axismundi-op-mention-autocomplete', 'axismundi-object-projections' );
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
