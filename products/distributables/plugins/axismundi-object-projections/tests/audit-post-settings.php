<?php
/**
 * Core Post federation settings regression (dev-only).
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/post-settings.php';

$ax_settings_results = array();
$ax_settings_post_id = 0;
$ax_settings_post    = $_POST;

/** Record one assertion. */
function ax_settings_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	axismundi_op_register_post_settings_meta();
	$registered = get_registered_meta_keys( 'post', 'post' );
	ax_settings_assert(
		$ax_settings_results,
		'sensitive, warning, Quote policy, visibility, and mention metadata are registered for REST-backed Core Post editing',
		isset( $registered[ AXISMUNDI_OP_POST_SENSITIVE_META ], $registered[ AXISMUNDI_OP_POST_WARNING_META ], $registered[ AXISMUNDI_OP_POST_QUOTE_POLICY_META ], $registered[ AXISMUNDI_OP_POST_VISIBILITY_META ], $registered[ AXISMUNDI_OP_POST_MENTIONS_META ] )
			&& 'boolean' === $registered[ AXISMUNDI_OP_POST_SENSITIVE_META ]['type']
			&& true === $registered[ AXISMUNDI_OP_POST_SENSITIVE_META ]['show_in_rest']
			&& 'string' === $registered[ AXISMUNDI_OP_POST_WARNING_META ]['type']
			&& is_array( $registered[ AXISMUNDI_OP_POST_WARNING_META ]['show_in_rest'] )
			&& array( '', 'anyone', 'followers', 'me' ) === $registered[ AXISMUNDI_OP_POST_QUOTE_POLICY_META ]['show_in_rest']['schema']['enum']
			&& array( 'public', 'unlisted', 'followers', 'mentioned' ) === $registered[ AXISMUNDI_OP_POST_VISIBILITY_META ]['show_in_rest']['schema']['enum']
			&& 'array' === $registered[ AXISMUNDI_OP_POST_MENTIONS_META ]['type']
	);

	$columns = axismundi_op_post_columns( array( 'title' => 'Title' ) );
	ob_start();
	axismundi_op_quick_edit_fields( 'axismundi_op_federation', 'post' );
	$quick_edit = (string) ob_get_clean();
	ax_settings_assert(
		$ax_settings_results,
		'Posts Quick Edit exposes the shared sensitive, warning, audience, mentions, and Quote-policy controls',
		isset( $columns['axismundi_op_federation'] )
			&& false !== strpos( $quick_edit, 'name="axismundi_op_sensitive"' )
			&& false !== strpos( $quick_edit, 'name="axismundi_op_content_warning"' )
			&& false !== strpos( $quick_edit, 'name="axismundi_op_quote_policy"' )
			&& false !== strpos( $quick_edit, 'name="axismundi_op_visibility"' )
			&& false !== strpos( $quick_edit, 'name="axismundi_op_mentions"' )
	);
	$editor_script = file_get_contents( dirname( __DIR__ ) . '/assets/post-settings.js' );
	ax_settings_assert( $ax_settings_results, 'the block-editor Federation panel exposes the same audience and Quote-policy controls', is_string( $editor_script ) && false !== strpos( $editor_script, 'SelectControl' ) && false !== strpos( $editor_script, "'_ax_op_quote_policy'" ) && false !== strpos( $editor_script, "'_ax_op_visibility'" ) && false !== strpos( $editor_script, "'_ax_op_mentions'" ) && false !== strpos( $editor_script, "value: 'mentioned'" ) );
	$mention_script = file_get_contents( dirname( __DIR__ ) . '/assets/mention-autocomplete.js' );
	ax_settings_assert(
		$ax_settings_results,
		'the editor replaces the Core user completer with canonical Actor mention anchors',
		is_string( $mention_script )
			&& false !== strpos( $mention_script, 'editor.Autocomplete.completers' )
			&& false !== strpos( $mention_script, "'users' !== completer.name" )
			&& false !== strpos( $mention_script, '/actors/mention-search' )
			&& false !== strpos( $mention_script, "className: 'mention'" )
			&& false !== strpos( $mention_script, 'href: actor.uri' )
	);

	$user_id = get_current_user_id();
	if ( $user_id <= 0 ) {
		$admins  = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
		$user_id = isset( $admins[0] ) ? (int) $admins[0] : 0;
		wp_set_current_user( $user_id );
	}
	$ax_settings_post_id = wp_insert_post(
		array(
			'post_type'   => 'post',
			'post_status' => 'draft',
			'post_author' => $user_id,
			'post_title'  => 'Federation settings fixture',
		)
	);
	$post = get_post( $ax_settings_post_id );
	$_POST = array(
		'axismundi_op_quick_edit_present' => '1',
		'axismundi_op_quick_edit_nonce'   => wp_create_nonce( 'axismundi_op_quick_edit' ),
		'axismundi_op_sensitive'          => '1',
		'axismundi_op_content_warning'    => '<b>Spoilers</b> and distressing imagery',
		'axismundi_op_quote_policy'       => 'followers',
		'axismundi_op_visibility'         => 'mentioned',
		'axismundi_op_mentions'           => "https://remote.example/users/alice\nhttps://remote.example/users/alice",
	);
	axismundi_op_save_quick_edit( $ax_settings_post_id, $post );
	$post = get_post( $ax_settings_post_id );
	ax_settings_assert(
		$ax_settings_results,
		'Quick Edit saves sanitized values through the shared metadata contract',
		$post instanceof WP_Post
			&& axismundi_op_post_is_sensitive( $post )
			&& 'Spoilers and distressing imagery' === axismundi_op_post_content_warning( $post )
			&& 'followers' === axismundi_op_post_quote_policy( $post )
			&& 'mentioned' === axismundi_op_post_visibility( $post )
			&& array( 'https://remote.example/users/alice' ) === axismundi_op_post_mentions( $post )
	);
	wp_update_post(
		array(
			'ID'           => $ax_settings_post_id,
			'post_content' => '<!-- wp:paragraph --><p>Hello <a class="mention" href="https://remote.example/users/bob">@bob@example</a> and <a href="https://remote.example/users/not-mentioned">a normal link</a>.</p><!-- /wp:paragraph -->',
		)
	);
	$post = get_post( $ax_settings_post_id );
	ax_settings_assert(
		$ax_settings_results,
		'saved mention anchors derive recipients while ordinary links do not and explicit recipients remain an ordered fallback',
		array( 'https://remote.example/users/alice', 'https://remote.example/users/bob' ) === axismundi_op_post_mentions( $post )
	);
	wp_update_post( array( 'ID' => $ax_settings_post_id, 'post_content' => '<p>The mention was removed.</p>' ) );
	$post = get_post( $ax_settings_post_id );
	ax_settings_assert( $ax_settings_results, 'removing a mention anchor removes its derived recipient without deleting explicit metadata', array( 'https://remote.example/users/alice' ) === axismundi_op_post_mentions( $post ) );
	$_POST = array(
		'axismundi_op_quick_edit_present' => '1',
		'axismundi_op_quick_edit_nonce'   => wp_create_nonce( 'axismundi_op_quick_edit' ),
		'axismundi_op_visibility'         => 'public',
		'axismundi_op_mentions'           => "https://remote.example/users/alice\nnot-an-actor-uri",
	);
	axismundi_op_save_quick_edit( $ax_settings_post_id, $post );
	$post = get_post( $ax_settings_post_id );
	ax_settings_assert( $ax_settings_results, 'Quick Edit rejects a mixed invalid recipient set instead of silently narrowing delivery', 'mentioned' === axismundi_op_post_visibility( $post ) && array( 'https://remote.example/users/alice' ) === axismundi_op_post_mentions( $post ) );

	$_POST = array(
		'axismundi_op_quick_edit_present' => '1',
		'axismundi_op_quick_edit_nonce'   => wp_create_nonce( 'axismundi_op_quick_edit' ),
		'axismundi_op_content_warning'    => 'Retained draft warning',
	);
	axismundi_op_save_quick_edit( $ax_settings_post_id, $post );
	$post = get_post( $ax_settings_post_id );
	ax_settings_assert(
		$ax_settings_results,
		'Quick Edit can disable sensitivity while retaining a warning for later reuse',
		$post instanceof WP_Post
			&& ! axismundi_op_post_is_sensitive( $post )
			&& 'Retained draft warning' === axismundi_op_post_content_warning( $post )
			&& '' === axismundi_op_post_quote_policy( $post )
			&& 'public' === axismundi_op_post_visibility( $post )
			&& array() === axismundi_op_post_mentions( $post )
	);
} finally {
	$_POST = $ax_settings_post;
	if ( $ax_settings_post_id > 0 ) {
		wp_delete_post( $ax_settings_post_id, true );
	}
}

$ax_settings_failures = count( array_filter( $ax_settings_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_settings_results ), $ax_settings_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_settings_failures > 0 ? 1 : 0 );
}
exit( $ax_settings_failures > 0 ? 1 : 0 );
