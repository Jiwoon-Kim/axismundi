<?php
/**
 * Phase 1 — renderer regression (dev-only; dist-excluded).
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/registry.php';
require_once dirname( __DIR__ ) . '/includes/sanitize.php';
require_once dirname( __DIR__ ) . '/includes/renderer.php';

$ax_rnd_results = array();

/**
 * @param array  $results Accumulator.
 * @param string $label Contract.
 * @param bool   $cond Holds.
 * @return void
 */
function ax_rnd_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/**
 * Register one object transformer against a fresh registry.
 *
 * @param array<string,mixed> $args Transformer args.
 * @return void
 */
function ax_rnd_register( array $args ) : void {
	$GLOBALS['axismundi_op_object_transformers'] = array();
	$GLOBALS['axismundi_op_sequence']            = 0;
	$GLOBALS['axismundi_op_loaded']              = true;
	axismundi_op_register_object_transformer( 'test', $args );
}

$uri = 'https://example.com/?p=123';
$uri_cb = static fn( $s ) : string => $uri;

// Happy path: required members + renderer-owned context.
ax_rnd_register(
	array(
		'supports'   => '__return_true',
		'object_uri' => $uri_cb,
		'transform'  => static fn( $s ) => array(
			'id'           => $uri,
			'type'         => 'Article',
			'attributedTo' => 'https://example.com/actors/uuid',
			'url'          => 'https://example.com/hello/',
			'name'         => 'Hello <b>World</b>',
			'content'      => '<p>Hi <script>alert(1)</script></p>',
			'@context'     => 'https://evil.example/context',
		),
	)
);
$object = axismundi_op_transform_object( 'x' );
ax_rnd_assert(
	$ax_rnd_results,
	'a valid object gets the canonical @context, plain-text name, and sanitized content',
	is_array( $object )
		&& 'https://www.w3.org/ns/activitystreams' === $object['@context']
		&& 'Hello World' === $object['name']
		&& false === strpos( (string) $object['content'], '<script' )
		&& array_key_first( $object ) === '@context'
);

// Every contentMap entry uses the same federation HTML allowlist as content.
ax_rnd_register(
	array(
		'supports'   => '__return_true',
		'object_uri' => $uri_cb,
		'transform'  => static fn( $s ) => array(
			'id'           => $uri,
			'type'         => 'Article',
			'attributedTo' => 'https://example.com/actors/uuid',
			'url'          => 'https://example.com/hello/',
			'content'      => '<p>Safe</p>',
			'nameMap'      => array( 'en' => '<strong>Safe title</strong>' ),
			'summaryMap'   => array( 'en' => '<p>Safe summary</p><iframe src="https://evil.example/"></iframe>' ),
			'contentMap'   => array( 'en' => '<p>Safe</p><iframe src="https://evil.example/"></iframe>' ),
		),
	)
);
$mapped = axismundi_op_transform_object( 'x' );
ax_rnd_assert( $ax_rnd_results, 'contentMap scalar values use the federation HTML allowlist', is_array( $mapped ) && '<p>Safe</p>' === $mapped['contentMap']['en'] );
ax_rnd_assert( $ax_rnd_results, 'nameMap scalar values use plain-text normalization', is_array( $mapped ) && 'Safe title' === $mapped['nameMap']['en'] );
ax_rnd_assert( $ax_rnd_results, 'summaryMap scalar values use the federation HTML allowlist', is_array( $mapped ) && '<p>Safe summary</p>' === $mapped['summaryMap']['en'] );

// Quote declaration and approval evidence map only the FEP terms actually emitted.
$quote_context = axismundi_op_jsonld_context( array( 'quote' => 'https://remote.example/notes/1' ) );
$approved_quote_context = axismundi_op_jsonld_context(
	array(
		'quote'              => 'https://remote.example/notes/1',
		'quoteAuthorization' => 'https://remote.example/authorizations/1',
	)
);
$quote_serialized          = serialize( $quote_context );
$approved_quote_serialized = serialize( $approved_quote_context );
ax_rnd_assert(
	$ax_rnd_results,
	'quote extension terms are conditional and URI-valued in the renderer-owned context',
	false !== strpos( $quote_serialized, 'https://w3id.org/fep/044f#quote' )
		&& false === strpos( $quote_serialized, 'https://w3id.org/fep/044f#quoteAuthorization' )
		&& false !== strpos( $approved_quote_serialized, 'https://w3id.org/fep/044f#quoteAuthorization' )
		&& false !== strpos( $approved_quote_serialized, '@id' )
);

// Tombstones retain only their stable identity and non-sensitive lifecycle fields.
ax_rnd_register(
	array(
		'supports'   => '__return_true',
		'object_uri' => $uri_cb,
		'transform'  => static fn( $s ) => array(
			'id'           => $uri,
			'type'         => 'Tombstone',
			'formerType'   => 'Note',
			'deleted'      => '2026-07-19T00:00:00Z',
			'attributedTo' => 'https://example.com/actors/uuid',
			'url'          => 'https://example.com/private/',
			'content'      => '<p>must not survive</p>',
		),
	)
);
$tombstone = axismundi_op_transform_object( 'x' );
ax_rnd_assert(
	$ax_rnd_results,
	'a Tombstone is privacy-minimal and maps to generic HTTP 410 semantics',
	is_array( $tombstone )
		&& array( '@context', 'id', 'type', 'formerType', 'deleted' ) === array_keys( $tombstone )
		&& 410 === axismundi_op_object_http_status( $tombstone )
);

// Global WordPress KSES extensions cannot widen the federation allowlist.
add_filter(
	'wp_kses_allowed_html',
	static function ( array $allowed ) : array {
		$allowed['iframe'] = array( 'src' => true );
		return $allowed;
	}
);
$cleaned = axismundi_op_clean_html( '<p>Keep</p><iframe src="https://evil.example/">leak</iframe><button>noise</button>' );
remove_all_filters( 'wp_kses_allowed_html' );
ax_rnd_assert( $ax_rnd_results, 'the FEP allowlist does not inherit global KSES widening and strips interactive contents', '<p>Keep</p>' === $cleaned );

// A transformer-supplied @context never survives.
ax_rnd_assert( $ax_rnd_results, 'a transformer-supplied @context is dropped in favor of the canonical one', is_array( $object ) && 'https://evil.example/context' !== $object['@context'] );

// Missing required member.
ax_rnd_register( array( 'supports' => '__return_true', 'object_uri' => $uri_cb, 'transform' => static fn( $s ) => array( 'id' => $uri, 'type' => 'Article', 'url' => 'https://example.com/x/' ) ) );
$missing = axismundi_op_transform_object( 'x' );
ax_rnd_assert( $ax_rnd_results, 'a missing required member yields ax_op_invalid_object', is_wp_error( $missing ) && 'ax_op_invalid_object' === $missing->get_error_code() );

// id must equal the declared object URI.
ax_rnd_register( array( 'supports' => '__return_true', 'object_uri' => $uri_cb, 'transform' => static fn( $s ) => array( 'id' => 'https://example.com/?p=999', 'type' => 'Article', 'attributedTo' => 'https://example.com/actors/u', 'url' => 'https://example.com/x/' ) ) );
$mismatch = axismundi_op_transform_object( 'x' );
ax_rnd_assert( $ax_rnd_results, 'an id that differs from the declared object URI is rejected', is_wp_error( $mismatch ) && 'ax_op_id_mismatch' === $mismatch->get_error_code() );

// Visibility gate is distinct from an error and from "no transformer".
ax_rnd_register( array( 'supports' => '__return_true', 'object_uri' => $uri_cb, 'transform' => static fn( $s ) => array( 'id' => $uri, 'type' => 'Article', 'attributedTo' => 'https://example.com/actors/u', 'url' => 'https://example.com/x/' ), 'visible' => '__return_false' ) );
$hidden = axismundi_op_transform_object( 'x' );
$GLOBALS['axismundi_op_object_transformers'] = array();
$no_tx  = axismundi_op_transform_object( 'x' );
ax_rnd_assert(
	$ax_rnd_results,
	'not-public, no-transformer, and transformer error are three distinct outcomes',
	is_wp_error( $hidden ) && 'ax_op_not_public' === $hidden->get_error_code() && is_wp_error( $no_tx ) && 'ax_op_no_transformer' === $no_tx->get_error_code()
);

// A transformer WP_Error passes through; a thrown exception is contained.
ax_rnd_register( array( 'supports' => '__return_true', 'object_uri' => $uri_cb, 'transform' => static fn( $s ) => new WP_Error( 'my_domain_error', 'nope' ) ) );
$domain_err = axismundi_op_transform_object( 'x' );
ax_rnd_register( array( 'supports' => '__return_true', 'object_uri' => $uri_cb, 'transform' => static function ( $s ) { throw new \RuntimeException( 'boom' ); } ) );
$threw = axismundi_op_transform_object( 'x' );
ax_rnd_assert( $ax_rnd_results, "a transformer's own WP_Error is preserved and a thrown exception becomes ax_op_transform_threw", is_wp_error( $domain_err ) && 'my_domain_error' === $domain_err->get_error_code() && is_wp_error( $threw ) && 'ax_op_transform_threw' === $threw->get_error_code() );

// A context-extension filter is honored (renderer still owns assembly).
add_filter( 'axismundi_op_jsonld_context', static fn( array $c ) : array => array_merge( $c, array( array( 'toot' => 'http://joinmastodon.org/ns#' ) ) ) );
ax_rnd_register( array( 'supports' => '__return_true', 'object_uri' => $uri_cb, 'transform' => static fn( $s ) => array( 'id' => $uri, 'type' => 'Article', 'attributedTo' => 'https://example.com/actors/u', 'url' => 'https://example.com/x/' ) ) );
$extended = axismundi_op_transform_object( 'x' );
remove_all_filters( 'axismundi_op_jsonld_context' );
ax_rnd_assert( $ax_rnd_results, 'the @context filter can add an extension entry while the renderer owns assembly', is_array( $extended ) && is_array( $extended['@context'] ) && 'https://www.w3.org/ns/activitystreams' === $extended['@context'][0] );

// Activity payloads use the same renderer-owned context without object-only requirements.
$activity_uri = 'https://example.com/activities/uuid/';
$activity = axismundi_op_finalize_activity(
	array(
		'id'      => $activity_uri,
		'type'    => 'Follow',
		'actor'   => 'https://example.com/actors/alice',
		'object'  => 'https://remote.example/actors/bob',
		'@context' => 'https://evil.example/context',
	),
	$activity_uri
);
ax_rnd_assert( $ax_rnd_results, 'an Activity gets the canonical renderer-owned context without object-only url/attributedTo members', is_array( $activity ) && 'https://www.w3.org/ns/activitystreams' === $activity['@context'] && array_key_first( $activity ) === '@context' );
$quote_accept = axismundi_op_finalize_activity(
	array(
		'id'     => $activity_uri,
		'type'   => 'Accept',
		'actor'  => 'https://example.com/actors/alice',
		'object' => array( 'id' => 'https://remote.example/quote-requests/1', 'type' => 'QuoteRequest', 'actor' => 'https://remote.example/actors/bob', 'object' => 'https://example.com/posts/1', 'instrument' => 'https://remote.example/posts/2' ),
	),
	$activity_uri
);
ax_rnd_assert( $ax_rnd_results, 'an Accept embedding a QuoteRequest declares the FEP-044f activity type in the renderer-owned context', is_array( $quote_accept ) && is_array( $quote_accept['@context'] ) && in_array( array( 'QuoteRequest' => 'https://w3id.org/fep/044f#QuoteRequest' ), $quote_accept['@context'], true ) );
$invalid_activity = axismundi_op_finalize_activity( array( 'id' => $activity_uri, 'type' => 'Follow' ), $activity_uri );
$activity_mismatch = axismundi_op_finalize_activity( array( 'id' => $activity_uri, 'type' => 'Follow', 'actor' => 'https://example.com/actors/alice' ), $activity_uri . 'different' );
ax_rnd_assert( $ax_rnd_results, 'Activity finalization rejects a missing actor and a ledger id mismatch', is_wp_error( $invalid_activity ) && 'ax_op_invalid_activity' === $invalid_activity->get_error_code() && is_wp_error( $activity_mismatch ) && 'ax_op_id_mismatch' === $activity_mismatch->get_error_code() );

// Every server-rendered Object block also needs a client-side registration or
// the Site Editor reports it as unsupported. The editor list is derived from the
// registry, so this guards the PHP and JavaScript sides from desynchronizing the
// way a hardcoded copy did.
axismundi_op_register_object_block_assets();
axismundi_op_enqueue_object_block_editor_data();
$ax_rnd_inline  = wp_scripts()->get_data( 'axismundi-op-object-blocks', 'before' );
$ax_rnd_inline  = is_array( $ax_rnd_inline ) ? $ax_rnd_inline : array( (string) $ax_rnd_inline );
$ax_rnd_payload = '';
foreach ( $ax_rnd_inline as $ax_rnd_line ) {
	if ( is_string( $ax_rnd_line ) && false !== strpos( $ax_rnd_line, 'axismundiOpObjectBlocks' ) ) {
		$ax_rnd_payload = $ax_rnd_line;
	}
}
$ax_rnd_decoded  = json_decode( trim( str_replace( array( 'window.axismundiOpObjectBlocks =', ';' ), '', $ax_rnd_payload ) ), true );
$ax_rnd_actual   = is_array( $ax_rnd_decoded ) ? array_keys( $ax_rnd_decoded ) : array();
$ax_rnd_expected = array();
foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $ax_rnd_name => $ax_rnd_type ) {
	if ( in_array( 'axismundi-op-object-blocks', (array) ( $ax_rnd_type->editor_script_handles ?? array() ), true ) ) {
		$ax_rnd_expected[] = $ax_rnd_name;
	}
}
sort( $ax_rnd_expected );
sort( $ax_rnd_actual );
$ax_rnd_editor_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/object-blocks.js' );

// The invariant survives the block.json migration: a block reaches the editor
// either through its own metadata-declared script or through the shared list,
// and the shared list is always derived from the registry. A block registered
// on the server with no editor script at all is what surfaces to an author as
// "your site doesn't include support for this block".
$ax_rnd_owned = array(
	'axismundi/object-status',
	'axismundi/object-meta',
	'axismundi/object-date',
	'axismundi/object-type',
	'axismundi/object-title',
	'axismundi/object-content',
	'axismundi/object-summary',
	'axismundi/object-read-more',
	'axismundi/object-card-body',
	'axismundi/object-hashtags',
	'axismundi/object-attachments',
	'axismundi/interactions',
	'axismundi/quote-context',
	'axismundi/reply-context',
	'axismundi/replies',
	'axismundi/question',
	'axismundi/hashtag-archive',
);
$ax_rnd_object_blocks = array();
foreach ( $ax_rnd_owned as $ax_rnd_name ) {
	$ax_rnd_type = WP_Block_Type_Registry::get_instance()->get_registered( $ax_rnd_name );
	if ( null === $ax_rnd_type ) {
		continue;
	}
	$ax_rnd_object_blocks[ $ax_rnd_name ] = count( (array) ( $ax_rnd_type->editor_script_handles ?? array() ) );
}
$ax_rnd_without_editor = array_keys( array_filter( $ax_rnd_object_blocks, static fn( int $count ) : bool => 0 === $count ) );
ax_rnd_assert(
	$ax_rnd_results,
	'every server-rendered Object block reaches the editor, and the shared list stays derived from the registry',
	array() !== $ax_rnd_object_blocks
		&& array() === $ax_rnd_without_editor
		&& array() !== $ax_rnd_expected
		&& $ax_rnd_expected === $ax_rnd_actual
		&& false !== strpos( $ax_rnd_editor_js, 'axismundiOpObjectBlocks' )
);

/*
 * `object-content` may stand down in a stream when a summary already speaks there.
 *
 * One Object Card pattern assembles the single page, the Actor timeline, and the
 * hashtag archive, so this is a per-surface decision the block has to ask about rather
 * than a second template. The Note case is the one that matters: a Note has no summary
 * and its body IS the post, so the toggle must not empty its card.
 */
function ax_rnd_content_on_surface( array $model, array $attributes, string $surface ) : string {
	// `get_block_wrapper_attributes()` reads the block being rendered; calling the
	// renderer directly leaves that unset and Core emits a null-offset notice.
	WP_Block_Supports::$block_to_render = array( 'blockName' => 'axismundi/object-content', 'attrs' => array() );
	$previous = $GLOBALS['axismundi_op_object_template_options'] ?? array();
	$GLOBALS['axismundi_op_object_template_options'] = array( 'surface' => $surface );
	axismundi_op_set_current_object_view_model( $model );
	try {
		return axismundi_op_render_object_content_block( $attributes );
	} finally {
		axismundi_op_set_current_object_view_model( null );
		$GLOBALS['axismundi_op_object_template_options'] = $previous;
	}
}

$ax_rnd_article                 = axismundi_op_object_view_model_defaults();
$ax_rnd_article['status']       = 'active';
$ax_rnd_article['type']         = 'Article';
$ax_rnd_article['content_html'] = '<p>Full article body.</p>';
$ax_rnd_article['summary']      = '<p>The teaser.</p>';

$ax_rnd_note                 = axismundi_op_object_view_model_defaults();
$ax_rnd_note['status']       = 'active';
$ax_rnd_note['type']         = 'Note';
$ax_rnd_note['content_html'] = '<p>A note body.</p>';
$ax_rnd_note['summary']      = '';

$ax_rnd_results[] = ( static function () use ( $ax_rnd_article ) : bool {
	$hidden = ax_rnd_content_on_surface( $ax_rnd_article, array( 'hideInFeed' => true ), 'feed' );
	$shown  = ax_rnd_content_on_surface( $ax_rnd_article, array( 'hideInFeed' => true ), 'single' );
	$pass   = '' === trim( $hidden ) && false !== strpos( $shown, 'Full article body' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] a summarized Article hides its body in a stream and keeps it on its own page\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

$ax_rnd_results[] = ( static function () use ( $ax_rnd_note ) : bool {
	$pass = false !== strpos(
		ax_rnd_content_on_surface( $ax_rnd_note, array( 'hideInFeed' => true ), 'feed' ),
		'A note body'
	);
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] an Object with no summary keeps its body in a stream, so a card is never empty\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

$ax_rnd_results[] = ( static function () use ( $ax_rnd_note ) : bool {
	// A Note's summary is a preview or a warning, not a teaser for a separate page, so
	// the body must survive even once Notes can carry one. Without the type check this
	// starts silently emptying Note cards the day Note excerpts ship.
	$summarised            = $ax_rnd_note;
	$summarised['summary'] = 'A note preview.';
	$pass = false !== strpos(
		ax_rnd_content_on_surface( $summarised, array( 'hideInFeed' => true ), 'feed' ),
		'A note body'
	);
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] a summarised Note keeps its body in a stream; only an Article stands down\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

$ax_rnd_results[] = ( static function () use ( $ax_rnd_article ) : bool {
	$pass = false !== strpos(
		ax_rnd_content_on_surface( $ax_rnd_article, array(), 'feed' ),
		'Full article body'
	);
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] the body stays in a stream unless the block opts out\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

$ax_rnd_results[] = ( static function () : bool {
	// The single Object template emits the pattern markup without wrapping it, so an
	// unset option must read as `single` -- otherwise the body would vanish there.
	$previous = $GLOBALS['axismundi_op_object_template_options'] ?? array();
	$GLOBALS['axismundi_op_object_template_options'] = array();
	$pass = 'single' === axismundi_op_object_template_option( 'surface', 'single' );
	$GLOBALS['axismundi_op_object_template_options'] = $previous;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] an unwrapped render reads as the single surface\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

/*
 * Post-level content warning.
 *
 * `sensitive` and "fold the post" are different questions. Mastodon and Misskey fold a
 * post only when the author wrote warning text; `sensitive: true` on its own blurs media
 * and leaves the writing readable. When a warning does exist it covers the body, the
 * poll, the quote, and the attachments as one unit.
 */
function ax_rnd_cw_model( bool $sensitive, string $warning, string $body = '<p>The body.</p>' ) : array {
	$model                  = axismundi_op_object_view_model_defaults();
	$model['status']        = 'active';
	$model['type']          = 'Note';
	$model['sensitive']     = $sensitive;
	$model['content_warning'] = $warning;
	$model['content_html']  = $body;
	return $model;
}

/** Render a fragment against one model. */
function ax_rnd_cw_render( array $model, string $markup ) : string {
	axismundi_op_set_current_object_view_model( $model );
	try {
		return do_blocks( $markup );
	} finally {
		axismundi_op_set_current_object_view_model( null );
	}
}

$ax_rnd_wrapped = '<!-- wp:axismundi/object-content-warning --><!-- wp:axismundi/object-content /--><!-- /wp:axismundi/object-content-warning -->';

$ax_rnd_results[] = ( static function () use ( $ax_rnd_wrapped ) : bool {
	$html = ax_rnd_cw_render( ax_rnd_cw_model( true, 'nsfw' ), $ax_rnd_wrapped );
	// Exactly one gate: the wrapper's. A second would mean the body gated itself too and
	// the reader would face two "Show more" controls for one warning.
	$pass = 1 === substr_count( $html, '<details' )
		&& false !== strpos( $html, '<summary>nsfw</summary>' )
		&& false !== strpos( $html, 'The body.' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] an authored warning folds the post once, and the body does not gate itself again\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

$ax_rnd_results[] = ( static function () use ( $ax_rnd_wrapped ) : bool {
	// The behaviour change: sensitive media with no warning text must not hide writing.
	$html = ax_rnd_cw_render( ax_rnd_cw_model( true, '' ), $ax_rnd_wrapped );
	$pass = false === strpos( $html, '<details' ) && false !== strpos( $html, 'The body.' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] sensitive with no authored warning leaves the post unfolded\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

$ax_rnd_results[] = ( static function () use ( $ax_rnd_wrapped ) : bool {
	$html = ax_rnd_cw_render( ax_rnd_cw_model( false, '' ), $ax_rnd_wrapped );
	$pass = false === strpos( $html, '<details' ) && false !== strpos( $html, 'The body.' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] the wrapper is invisible when nothing is warned\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

$ax_rnd_results[] = ( static function () : bool {
	// Backward compatibility: a template that places the body without the wrapper must
	// still gate it, or an existing card would leak a warned post.
	$html = ax_rnd_cw_render( ax_rnd_cw_model( true, 'nsfw' ), '<!-- wp:axismundi/object-content /-->' );
	$pass = 1 === substr_count( $html, '<details' ) && false !== strpos( $html, '<summary>nsfw</summary>' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] the body still gates itself when no wrapper owns the disclosure\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

$ax_rnd_results[] = ( static function () : bool {
	// An inferred label (hashtag/title) exists so a gate always has something to show,
	// but it must never be what decides to fold.
	$model              = ax_rnd_cw_model( true, '' );
	$model['hashtags']  = array( array( 'name' => 'art' ) );
	$pass = ! axismundi_op_object_has_content_warning( $model )
		&& '#art' === axismundi_op_sensitive_content_label( $model );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] an inferred label can title a gate but never creates one\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

/*
 * A warned Article in a stream, per FEP-b2b8's in-stream guidance:
 *
 *   Content Warning: Citizen Kane   the authored `dcterms:subject`
 *   click to view                   the summary, obscured
 *   read more                       the route to the full representation
 *
 * An Article obscures its *summary* — its body is not in the stream at all — which is
 * the mirror image of a Note, where the body is the post. The read-more link must stay
 * outside the disclosure, or a warned Article offers no way to reach itself.
 */
function ax_rnd_article_summary( bool $sensitive, string $warning ) : string {
	$model                    = axismundi_op_object_view_model_defaults();
	$model['status']          = 'active';
	$model['type']            = 'Article';
	$model['summary']         = 'The film is a masterpiece of framing.';
	$model['sensitive']       = $sensitive;
	$model['content_warning'] = $warning;
	$model['human_url']       = 'https://example.test/kane';
	axismundi_op_set_current_object_view_model( $model );
	try {
		return do_blocks( '<!-- wp:axismundi/object-summary {"moreText":"Read more"} /-->' );
	} finally {
		axismundi_op_set_current_object_view_model( null );
	}
}

$ax_rnd_results[] = ( static function () : bool {
	$html    = ax_rnd_article_summary( true, 'CW: Citizen Kane' );
	$spoiler = strpos( $html, 'data-ax-spoiler' );
	$link    = strpos( $html, 'more-link' );
	$pass    = false !== $spoiler
		// Obscured in place, not collapsed: the text is present and covered.
		&& false === strpos( $html, '<details' )
		&& false !== strpos( $html, 'masterpiece' )
		&& false !== strpos( $html, 'is-obscured' )
		&& false !== strpos( $html, 'Content warning: CW: Citizen Kane' )
		// The route to the full Article must not be covered with the summary.
		&& false !== $link && $link > strpos( $html, 'spoiler-reveal' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] a sensitive Article obscures its summary in place and keeps read-more outside the cover\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

$ax_rnd_results[] = ( static function () : bool {
	// The Note rule does not transfer: FEP supplies a label fallback chain exactly so a
	// missing `dcterms:subject` never switches an Article's protection off.
	$html = ax_rnd_article_summary( true, '' );
	$pass = false !== strpos( $html, 'data-ax-spoiler' ) && false !== strpos( $html, 'is-obscured' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] a sensitive Article stays covered even with no authored subject\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

$ax_rnd_results[] = ( static function () : bool {
	$html = ax_rnd_article_summary( false, '' );
	$pass = false === strpos( $html, 'data-ax-spoiler' ) && false !== strpos( $html, 'masterpiece' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] a plain Article summary is not covered\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

$ax_rnd_results[] = ( static function () : bool {
	// The two shapes must not borrow each other's treatment.
	$article = ax_rnd_article_summary( true, 'CW: Citizen Kane' );
	$note    = ax_rnd_cw_render( ax_rnd_cw_model( true, 'nsfw' ), '<!-- wp:axismundi/object-content-warning --><!-- wp:axismundi/object-content /--><!-- /wp:axismundi/object-content-warning -->' );
	$pass    = false === strpos( $article, '<details' )
		&& false === strpos( $note, 'data-ax-spoiler' )
		&& false !== strpos( $note, '<details' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] an Article uses the spoiler and a Note uses the fold, never the reverse\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

$ax_rnd_results[] = ( static function () : bool {
	// The fold belongs to the Note/Question composition. An Article can never fold, so a
	// wrapper on an Article surface could only ever render transparently — scenery that
	// implies a disclosure the page does not perform. Guard the compositions rather than
	// the behaviour, because the behaviour of a dead wrapper is indistinguishable from
	// correct and would let it sit there unnoticed.
	$article_single = axismundi_op_single_object_template_content( 'single-object-article' );
	$article_card   = axismundi_op_object_card_pattern_content( 'object-card-article' );
	$default_card   = axismundi_op_object_card_pattern_content( 'object-card-default' );
	$pass = false === strpos( $article_single, '<!-- wp:axismundi/object-content-warning' )
		&& false === strpos( $article_card, '<!-- wp:axismundi/object-content-warning' )
		// The Article card carries no body at all, so there is nothing for it to hide.
		&& false === strpos( $article_card, '<!-- wp:axismundi/object-content ' )
		&& false === strpos( $article_card, '<!-- wp:axismundi/object-content /' )
		/*
		 * The default card still has both, one level down: the fold and the body it exists to
		 * cover moved into `object-card-body` together, which is the point — separating them
		 * would leave a disclosure with nothing behind it. That they arrive together is asserted
		 * against the rendered card further down; what can be seen from here is that this card
		 * asks for them rather than keeping its own copy.
		 */
		&& false !== strpos( $default_card, '<!-- wp:axismundi/object-card-body /-->' )
		&& false === strpos( $default_card, '<!-- wp:axismundi/object-content-warning' )
		&& false === strpos( $default_card, '<!-- wp:axismundi/object-content /' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] the fold lives only where a body can be folded, never on an Article surface\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

$ax_rnd_results[] = ( static function () : bool {
	// The Article card is the stream lead-in and the Article page is the piece, so the
	// summary belongs to one and the body to the other. Swapping either would either
	// leak an Article's body into a feed or repeat its teaser above its own text.
	/*
	 * The card half of this moved. An Article card's summary now comes from `object-card-body`
	 * rather than from the card template, so it is asserted against the rendered card further
	 * down; reading the template string for it here would only ever find nothing. What stays
	 * here is the half this file can still see: the Article's own page leads with its body and
	 * never repeats the teaser above it.
	 */
	$article_card   = axismundi_op_object_card_pattern_content( 'object-card-article' );
	$article_single = axismundi_op_single_object_template_content( 'single-object-article' );
	$pass = false !== strpos( $article_card, '<!-- wp:axismundi/object-card-body /-->' )
		&& false === strpos( $article_single, '<!-- wp:axismundi/object-summary' )
		&& false !== strpos( $article_single, '<!-- wp:axismundi/object-content /' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] an Article leads with its summary in a stream and with its body on its own page\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

$ax_rnd_results[] = ( static function () : bool {
	// Most federated Objects are never edited and carry no `updated` time, so a card
	// header asking for that field renders an empty string and the timestamp disappears
	// — visibly on remote Objects and not on local ones, which reads as the surfaces
	// disagreeing when it is really the data.
	$model              = axismundi_op_object_view_model_defaults();
	$model['status']    = 'active';
	$model['type']      = 'Note';
	$model['published'] = '2026-07-26T16:43:41+00:00';
	$model['updated']   = '';

	axismundi_op_set_current_object_view_model( $model );
	$published = axismundi_op_render_object_date_block( array() );
	$updated   = axismundi_op_render_object_date_block( array( 'field' => 'updated' ) );
	axismundi_op_set_current_object_view_model( null );

	$header = (string) file_get_contents( dirname( __DIR__ ) . '/templates/parts/object-card-header.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local bundled template read.
	$pass   = '' !== trim( $published )
		// An absent `updated` correctly renders nothing rather than a wrong date.
		&& '' === trim( $updated )
		// So the shared header must ask for the field every Object actually has.
		&& false === strpos( $header, '"field":"updated"' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] the card header dates every Object, including one that was never edited\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

/*
 * The Read More link is its own block so a card can place it.
 *
 * `object-summary` can still end with one and that stays — templates depend on it — but a link
 * welded to the end of a paragraph cannot be aligned or grouped on its own. What matters here is
 * that the words are editable while the destination is not. The FEP example supplies the initial
 * "Read more" label. An editor can replace it, while an omitted or empty value still retains a
 * visible route to the full Object.
 */
$ax_rnd_read_more_model              = axismundi_op_object_view_model_defaults();
$ax_rnd_read_more_model['status']    = 'active';
$ax_rnd_read_more_model['type']      = 'Article';
$ax_rnd_read_more_model['human_url'] = 'https://example.com/read-more-target/';
axismundi_op_set_current_object_view_model( $ax_rnd_read_more_model );
$ax_rnd_read_more_default = trim( do_blocks( '<!-- wp:axismundi/object-read-more /-->' ) );
$ax_rnd_read_more_text  = trim( do_blocks( '<!-- wp:axismundi/object-read-more {"text":"Read more"} /-->' ) );
$ax_rnd_read_more_cleared = trim( do_blocks( '<!-- wp:axismundi/object-read-more {"text":""} /-->' ) );
axismundi_op_set_current_object_view_model( null );
$ax_rnd_read_more_none  = trim( do_blocks( '<!-- wp:axismundi/object-read-more {"text":"Read more"} /-->' ) );

ax_rnd_assert(
	$ax_rnd_results,
	'the Read More link defaults to the FEP label when omitted or empty, stays editable, and is absent only without an Object',
	false !== strpos( $ax_rnd_read_more_default, '>Read more<' )
		&& false !== strpos( $ax_rnd_read_more_cleared, '>Read more<' )
		&& '' === $ax_rnd_read_more_none
		&& false !== strpos( $ax_rnd_read_more_text, 'axismundi-object__read-more-link' )
		&& 1 === preg_match( '#<a[^>]*href="[^"]+"#', $ax_rnd_read_more_text )
);

/*
 * One feed can mix Note and Article cards, so card-body must choose the Object-specific block
 * composition at render time while leaving every other card part to its shared template.
 */
$ax_rnd_body_article              = axismundi_op_object_view_model_defaults();
$ax_rnd_body_article['status']    = 'active';
$ax_rnd_body_article['type']      = 'Article';
$ax_rnd_body_article['title']     = 'Article lead';
$ax_rnd_body_article['summary']   = 'Article summary.';
$ax_rnd_body_article['human_url'] = 'https://example.com/article-lead/';
$ax_rnd_body_note                 = axismundi_op_object_view_model_defaults();
$ax_rnd_body_note['status']       = 'active';
$ax_rnd_body_note['type']         = 'Note';
$ax_rnd_body_note['content_html'] = '<p>Note body.</p>';
axismundi_op_set_current_object_view_model( $ax_rnd_body_article );
$ax_rnd_article_body_html = do_blocks( '<!-- wp:axismundi/object-card-body /-->' );
axismundi_op_set_current_object_view_model( $ax_rnd_body_note );
$ax_rnd_note_body_html = do_blocks( '<!-- wp:axismundi/object-card-body /-->' );
axismundi_op_set_current_object_view_model( null );
$ax_rnd_unbound_body_html = do_blocks( '<!-- wp:axismundi/object-card-body /-->' );
ax_rnd_assert(
	$ax_rnd_results,
	'card body chooses the Article lead or the warned Note body per rendered Object',
	false !== strpos( $ax_rnd_article_body_html, 'Article lead' )
		&& false !== strpos( $ax_rnd_article_body_html, 'Article summary.' )
		&& false !== strpos( $ax_rnd_article_body_html, 'axismundi-object__read-more-link' )
		&& false === strpos( $ax_rnd_article_body_html, 'wp-block-axismundi-object-content-warning' )
		&& false !== strpos( $ax_rnd_note_body_html, 'Note body.' )
		&& false !== strpos( $ax_rnd_note_body_html, 'wp-block-axismundi-object-content-warning' )
		&& '' === trim( $ax_rnd_unbound_body_html )
);



/*
 * The Article card's shell delegates its body, and delegating changed exactly one thing.
 *
 * Selection is only `'Article' === $model['type']`, so a synthetic model exercises the real
 * template without depending on what happens to be in the database. The Read More link moved out
 * of the summary and into its own block — that is the intended difference — and everything else
 * the card is made of has to survive it, including the lead image's fixed height and the URL the
 * link points at. A `href` that changed would mean the two blocks resolve destinations
 * differently, which is the failure this arrangement exists to prevent.
 */
$ax_rnd_card_model                   = axismundi_op_object_view_model_defaults();
$ax_rnd_card_model['status']         = 'active';
$ax_rnd_card_model['type']           = 'Article';
$ax_rnd_card_model['object_uri']     = 'https://example.com/a/1';
$ax_rnd_card_model['human_url']      = 'https://example.com/a/1';
$ax_rnd_card_model['title']          = 'Baseline Article';
$ax_rnd_card_model['summary']        = 'A summary that is long enough to trim.';
$ax_rnd_card_model['media']['image'] = array( 'url' => 'https://example.com/lead.jpg', 'alt' => 'Lead image', 'width' => 1200, 'height' => 800 );
$ax_rnd_card_model['author']         = array( 'id' => 'https://example.com/actors/u', 'name' => 'Author', 'handle' => '@a@example.com', 'url' => 'https://example.com/actors/u' );
axismundi_op_set_current_object_view_model( $ax_rnd_card_model );
$ax_rnd_card_html = axismundi_op_render_object_pattern( array( 'headingTag' => 'h3', 'interactions' => false ) );
axismundi_op_set_current_object_view_model( null );
$ax_rnd_card_href = preg_match( '#read-more-link" href="([^"]*)#', $ax_rnd_card_html, $ax_rnd_card_match ) ? $ax_rnd_card_match[1] : '';

ax_rnd_assert(
	$ax_rnd_results,
	'an Article card keeps its lead image, fixed height, title and summary once its body is delegated',
	false !== strpos( $ax_rnd_card_html, 'axismundi-object-card--article' )
		&& false !== strpos( $ax_rnd_card_html, 'lead.jpg' )
		&& false !== strpos( $ax_rnd_card_html, '200px' )
		&& false !== strpos( $ax_rnd_card_html, 'axismundi-object__title' )
		&& false !== strpos( $ax_rnd_card_html, 'post-excerpt__excerpt' )
);

ax_rnd_assert(
	$ax_rnd_results,
	'the Read More link left the summary for a block of its own and still points where it did',
	false === strpos( $ax_rnd_card_html, 'post-excerpt__more-link' )
		&& 1 === substr_count( $ax_rnd_card_html, 'axismundi-object__read-more-link' )
		&& 'https://example.com/a/1' === $ax_rnd_card_href
);


/*
 * The Note card kept everything, including the shape of its disclosure.
 *
 * Unlike the Article card this delegation was meant to change nothing at all, so the check is
 * equality rather than a list of survivors — the only permitted difference is whitespace between
 * blocks, which a template's newlines produce and a concatenated block template does not.
 *
 * The content warning is the part worth naming. One authored warning covers the body, the quote
 * preview, the poll and the attachments together, the way Mastodon and Misskey present a warned
 * post; a delegation that emitted four separate disclosures, or none, would look right in a
 * screenshot and be wrong about what the author asked for.
 */
$ax_rnd_note_model                   = axismundi_op_object_view_model_defaults();
$ax_rnd_note_model['status']         = 'active';
$ax_rnd_note_model['type']           = 'Note';
$ax_rnd_note_model['object_uri']     = 'https://example.com/n/1';
$ax_rnd_note_model['human_url']      = 'https://example.com/n/1';
$ax_rnd_note_model['title']          = 'Note title';
$ax_rnd_note_model['summary']        = 'Note summary text.';
$ax_rnd_note_model['content']        = '<p>Note body text.</p>';
$ax_rnd_note_model['media']['image'] = array( 'url' => 'https://example.com/lead.jpg', 'alt' => 'Lead', 'width' => 1200, 'height' => 800 );
$ax_rnd_note_model['author']         = array( 'id' => 'https://example.com/actors/u', 'name' => 'Author', 'handle' => '@a@example.com', 'url' => 'https://example.com/actors/u' );
axismundi_op_set_current_object_view_model( $ax_rnd_note_model );
$ax_rnd_note_card = axismundi_op_render_object_pattern( array( 'headingTag' => 'h3', 'interactions' => false ) );
$ax_rnd_note_body = do_blocks( '<!-- wp:axismundi/object-card-body /-->' );
axismundi_op_set_current_object_view_model( null );

ax_rnd_assert(
	$ax_rnd_results,
	'a Note card wraps its body, quote, poll and attachments in one disclosure, delegated or not',
	1 === substr_count( $ax_rnd_note_card, 'content-warning' )
		&& 1 === substr_count( $ax_rnd_note_body, 'content-warning' )
		&& false !== strpos( $ax_rnd_note_card, 'lead.jpg' )
		&& false !== strpos( $ax_rnd_note_card, 'axismundi-object__title' )
);

// The card template asks the body block for this region; finding the blocks themselves back in
// the template would mean the two had been allowed to drift into separate copies.
$ax_rnd_note_template = axismundi_op_object_card_pattern_content( 'object-card-default' );
ax_rnd_assert(
	$ax_rnd_results,
	'the Note card delegates its body rather than keeping a second copy of it',
	false !== strpos( $ax_rnd_note_template, '<!-- wp:axismundi/object-card-body /-->' )
		&& false === strpos( $ax_rnd_note_template, '<!-- wp:axismundi/object-content /' )
		&& false === strpos( $ax_rnd_note_template, '<!-- wp:axismundi/object-content-warning' )
);


/*
 * A warned Article must still be able to reach itself.
 *
 * FEP-b2b8 asks an Article's content to stay covered until the reader chooses, so the summary is
 * obscured behind a warning and a reveal. The route to the full piece is deliberately not covered
 * with it: a warned Article whose only link sat under its own cover would be unreachable without
 * first revealing the thing the warning exists to withhold.
 *
 * That link used to live inside the summary block, placed after the spoiler by hand. It is its own
 * block now, so this checks the property rather than the arrangement that used to produce it — the
 * spoiler's element closes before the link begins, whoever emitted them.
 *
 * The absent content-warning wrapper is part of the same rule: with no body in an Article card
 * there is nothing for a post-level fold to cover, and the summary's own cover is the mechanism.
 */
$ax_rnd_warned                   = axismundi_op_object_view_model_defaults();
$ax_rnd_warned['status']         = 'active';
$ax_rnd_warned['type']           = 'Article';
$ax_rnd_warned['sensitive']      = true;
$ax_rnd_warned['object_uri']     = 'https://example.com/a/1';
$ax_rnd_warned['human_url']      = 'https://example.com/a/1';
$ax_rnd_warned['title']          = 'Sensitive Article';
$ax_rnd_warned['summary']        = 'Summary that should be covered.';
$ax_rnd_warned['author']         = array( 'id' => 'https://example.com/actors/u', 'name' => 'A', 'handle' => '@a', 'url' => 'https://example.com/actors/u' );
axismundi_op_set_current_object_view_model( $ax_rnd_warned );
$ax_rnd_warned_card = axismundi_op_render_object_pattern( array( 'headingTag' => 'h3', 'interactions' => false ) );
axismundi_op_set_current_object_view_model( null );

$ax_rnd_gate_at     = strpos( $ax_rnd_warned_card, 'summary-gate' );
$ax_rnd_link_at     = strpos( $ax_rnd_warned_card, 'axismundi-object__read-more' );
$ax_rnd_between     = false !== $ax_rnd_gate_at && false !== $ax_rnd_link_at
	? substr( $ax_rnd_warned_card, $ax_rnd_gate_at, $ax_rnd_link_at - $ax_rnd_gate_at )
	: '';

ax_rnd_assert(
	$ax_rnd_results,
	'a warned Article covers its summary and leaves the route to itself outside the cover',
	false !== strpos( $ax_rnd_warned_card, 'is-obscured' )
		&& false !== strpos( $ax_rnd_warned_card, 'axismundi-object__spoiler-reveal' )
		&& false === strpos( $ax_rnd_warned_card, 'content-warning' )
		&& false !== $ax_rnd_link_at
		&& false !== $ax_rnd_gate_at
		// Every element opened inside the cover is closed before the link starts, so the link is
		// a sibling of the cover rather than something inside it.
		&& substr_count( $ax_rnd_between, '</div>' ) > substr_count( $ax_rnd_between, '<div' )
);

/*
 * The audience marker draws what the model says and nothing when the model does not say.
 *
 * Each audience must be visibly distinct, because the whole purpose of the marker is that a reader
 * can tell them apart at a glance — two audiences sharing a glyph would be worse than no marker,
 * since it would look like an answer. `limited` in particular must not borrow the lock: it means
 * the audience could not be established, and a lock would state followers-only as fact.
 *
 * An unresolved audience renders nothing at all. The tempting default is `public`, and it is the
 * one value that must never be assumed — it would describe a restricted Object as open on a card
 * its author is looking at.
 */
/*
 * Off for readers, and the audits below say what it does when it is switched on.
 *
 * The five audiences stay resolved because access control needs them, but nothing draws them: no
 * author can choose an audience yet and no delivery rule is closed behind one, so a marker would
 * describe a promise the product does not keep. The default is asserted first and separately,
 * because that — not the glyph table — is the shipped behaviour.
 */
axismundi_op_set_current_object_view_model( array( 'object_uri' => 'https://example.com/n/v', 'visibility' => array( 'level' => 'public' ) ) );
$ax_rnd_vis_default_off = do_blocks( '<!-- wp:axismundi/object-visibility /-->' );
axismundi_op_set_current_object_view_model( null );
ax_rnd_assert(
	$ax_rnd_results,
	'the audience marker draws nothing until a product can offer the choice it would be describing',
	'' === trim( $ax_rnd_vis_default_off )
		&& false === axismundi_op_object_visibility_block_enabled()
		// And it is not placed in the bundled card either, so switching it on is one decision
		// rather than one decision plus an edit to every saved template that already shipped.
		&& false === strpos( (string) @file_get_contents( dirname( __DIR__ ) . '/templates/parts/object-card-header.php' ), '<!-- wp:axismundi/object-visibility' )
);

add_filter( 'axismundi_op_object_visibility_marker_enabled', '__return_true' );
$ax_rnd_vis_glyphs = array();
foreach ( array_keys( axismundi_op_object_visibility_vocabulary() ) as $ax_rnd_level ) {
	axismundi_op_set_current_object_view_model( array( 'object_uri' => 'https://example.com/n/v', 'visibility' => array( 'level' => $ax_rnd_level ) ) );
	$ax_rnd_vis_html = do_blocks( '<!-- wp:axismundi/object-visibility /-->' );
	if ( 1 === preg_match( '#material-symbols-outlined"[^>]*>([a-z_]+)<#', $ax_rnd_vis_html, $ax_rnd_vis_m ) ) {
		$ax_rnd_vis_glyphs[ $ax_rnd_level ] = $ax_rnd_vis_m[1];
	}
}
axismundi_op_set_current_object_view_model( array( 'object_uri' => 'https://example.com/n/v', 'visibility' => array( 'level' => '' ) ) );
$ax_rnd_vis_unknown = do_blocks( '<!-- wp:axismundi/object-visibility /-->' );
axismundi_op_set_current_object_view_model( array( 'object_uri' => 'https://example.com/n/v', 'visibility' => array( 'level' => 'followers' ) ) );
$ax_rnd_vis_followers = do_blocks( '<!-- wp:axismundi/object-visibility /-->' );
axismundi_op_set_current_object_view_model( null );

ax_rnd_assert(
	$ax_rnd_results,
	'every audience the marker knows draws its own glyph, so none of them reads as another',
	5 === count( $ax_rnd_vis_glyphs )
		&& count( $ax_rnd_vis_glyphs ) === count( array_unique( $ax_rnd_vis_glyphs ) )
		&& 'public' === ( $ax_rnd_vis_glyphs['public'] ?? '' )
		&& 'lock' === ( $ax_rnd_vis_glyphs['followers'] ?? '' )
		&& 'lock' !== ( $ax_rnd_vis_glyphs['limited'] ?? 'lock' )
		&& 'lock' !== ( $ax_rnd_vis_glyphs['unlisted'] ?? 'lock' )
);
ax_rnd_assert(
	$ax_rnd_results,
	'an Object whose audience was never resolved shows no marker rather than being called public',
	'' === trim( $ax_rnd_vis_unknown )
);
ax_rnd_assert(
	$ax_rnd_results,
	'the audience is named for a screen reader even when only the glyph is drawn',
	false !== strpos( $ax_rnd_vis_followers, 'screen-reader-text' )
		&& false !== strpos( $ax_rnd_vis_followers, 'Followers only' )
		&& false !== strpos( $ax_rnd_vis_followers, 'aria-hidden="true"' )
		&& false !== strpos( $ax_rnd_vis_followers, 'data-visibility="followers"' )
);

remove_filter( 'axismundi_op_object_visibility_marker_enabled', '__return_true' );

/*
 * Counted here, after the last assertion, and not anywhere earlier.
 *
 * This tally used to sit two thirds of the way up the file. Everything below it was still asserted
 * and still printed its own FAIL line, while the summary counted the checks and reported none of
 * them failed — and the exit code stayed 0, so nothing gating on this audit could see them either.
 */
$ax_rnd_failures = count( array_filter( $ax_rnd_results, static fn( bool $r ) : bool => ! $r ) );

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_rnd_results ), $ax_rnd_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_rnd_failures > 0 ? 1 : 0 );
}
exit( $ax_rnd_failures > 0 ? 1 : 0 );
