<?php
/**
 * Phase 1 — renderer regression (dev-only; dist-excluded).
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/registry.php';
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

$ax_rnd_failures = count( array_filter( $ax_rnd_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_rnd_results ), $ax_rnd_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_rnd_failures > 0 ? 1 : 0 );
}
exit( $ax_rnd_failures > 0 ? 1 : 0 );
