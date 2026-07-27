<?php
/**
 * `reply_context` projection regression.
 *
 * Locks the shallow conversational-parent contract: exactly one ancestor, never a
 * thread; a non-reply produces nothing; an unshowable parent still yields a link-only
 * reference rather than vanishing; a sensitive parent never leaks its body past its own
 * content warning; and an Object is never its own ancestor.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_rc_results = array();
$ax_rc_uris    = array();

/** @param bool[] $results Results. */
function ax_rc_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Cache one public remote Note fixture and remember it for cleanup. */
function ax_rc_store( array &$uris, string $uri, array $extra = array() ) : bool {
	$payload = array_merge(
		array(
			'@context'     => 'https://www.w3.org/ns/activitystreams',
			'id'           => $uri,
			'type'         => 'Note',
			'attributedTo' => 'https://remote.test/users/parent',
			'content'      => '<p>The parent post being replied to.</p>',
			'published'    => '2026-01-01T00:00:00Z',
			'to'           => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		),
		$extra
	);
	$stored = axismundi_op_remote_object_store( $payload );
	if ( ! is_wp_error( $stored ) ) {
		$uris[] = $uri;
		return true;
	}
	return false;
}

try {
	// A non-reply has no conversational parent at all.
	ax_rc_assert(
		$ax_rc_results,
		'an Object that is not a reply produces no reply context',
		null === axismundi_op_build_reply_context( array( 'object_uri' => 'https://a.test/1', 'in_reply_to' => '' ) )
	);

	// An Object may not be its own ancestor.
	ax_rc_assert(
		$ax_rc_results,
		'a self-referencing inReplyTo produces no reply context',
		null === axismundi_op_build_reply_context(
			array( 'object_uri' => 'https://a.test/1', 'in_reply_to' => 'https://a.test/1' )
		)
	);

	// An unresolvable parent still reports the relation and a usable destination.
	$ax_rc_missing = axismundi_op_build_reply_context(
		array( 'object_uri' => 'https://a.test/2', 'in_reply_to' => 'https://remote.test/notes/never-cached' )
	);
	ax_rc_assert(
		$ax_rc_results,
		'an unresolvable parent yields a link-only reference instead of disappearing',
		is_array( $ax_rc_missing )
			&& false === $ax_rc_missing['available']
			&& 'https://remote.test/notes/never-cached' === $ax_rc_missing['uri']
			&& 'https://remote.test/notes/never-cached' === $ax_rc_missing['url']
	);

	// A non-dereferenceable identifier gets no link.
	$ax_rc_tag = axismundi_op_build_reply_context(
		array( 'object_uri' => 'https://a.test/3', 'in_reply_to' => 'tag:example.test,2026:note/9' )
	);
	ax_rc_assert(
		$ax_rc_results,
		'a non-HTTP parent identifier produces no link',
		is_array( $ax_rc_tag ) && false === $ax_rc_tag['available'] && '' === $ax_rc_tag['url']
	);

	// A cached, public parent resolves to identity plus a short excerpt.
	$ax_rc_parent = 'https://remote.test/notes/parent-visible';
	if ( ax_rc_store( $ax_rc_uris, $ax_rc_parent ) ) {
		$ax_rc_ok = axismundi_op_build_reply_context(
			array( 'object_uri' => 'https://a.test/4', 'in_reply_to' => $ax_rc_parent )
		);
		ax_rc_assert(
			$ax_rc_results,
			'a cached public parent resolves to identity, excerpt, and a canonical link',
			is_array( $ax_rc_ok )
				&& true === $ax_rc_ok['available']
				&& $ax_rc_parent === $ax_rc_ok['uri']
				&& false !== strpos( (string) $ax_rc_ok['excerpt'], 'parent post' )
				&& array_key_exists( 'author', $ax_rc_ok )
		);
		// The projection is the compact one: no attachment gallery, no thread.
		ax_rc_assert(
			$ax_rc_results,
			'the parent projection stays compact and carries no thread or attachment payload',
			is_array( $ax_rc_ok )
				&& ! array_key_exists( 'attachments', $ax_rc_ok )
				&& ! array_key_exists( 'reply_context', $ax_rc_ok )
				&& ! array_key_exists( 'media', $ax_rc_ok )
		);
	} else {
		ax_rc_assert( $ax_rc_results, 'a cached public parent resolves (fixture store failed)', false );
		ax_rc_assert( $ax_rc_results, 'the parent projection stays compact (fixture store failed)', false );
	}

	// A sensitive parent contributes no excerpt: its own warning still governs.
	$ax_rc_cw = 'https://remote.test/notes/parent-sensitive';
	if ( ax_rc_store( $ax_rc_uris, $ax_rc_cw, array( 'sensitive' => true, 'summary' => 'cw here' ) ) ) {
		$ax_rc_gated = axismundi_op_build_reply_context(
			array( 'object_uri' => 'https://a.test/5', 'in_reply_to' => $ax_rc_cw )
		);
		ax_rc_assert(
			$ax_rc_results,
			'a sensitive parent contributes its warning but never its body',
			is_array( $ax_rc_gated )
				&& true === $ax_rc_gated['available']
				&& '' === (string) $ax_rc_gated['excerpt']
				&& true === $ax_rc_gated['sensitive']
		);
	} else {
		ax_rc_assert( $ax_rc_results, 'a sensitive parent contributes no body (fixture store failed)', false );
	}

	// The enrichment boundary attaches it, so consumers read one model field.
	$ax_rc_enriched = axismundi_op_enrich_object_view_model(
		array( 'object_uri' => 'https://a.test/6', 'status' => 'active', 'in_reply_to' => $ax_rc_parent )
	);
	ax_rc_assert(
		$ax_rc_results,
		'the enriched view model exposes reply_context as a first-class field',
		is_array( $ax_rc_enriched['reply_context'] ?? null )
			&& $ax_rc_parent === $ax_rc_enriched['reply_context']['uri']
	);
} finally {
	foreach ( array_unique( $ax_rc_uris ) as $ax_rc_uri ) {
		axismundi_op_remote_object_delete( $ax_rc_uri );
	}
}

$ax_rc_failed = count( array_filter( $ax_rc_results, static fn( $r ) => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n%d/%d passed\n", count( $ax_rc_results ) - $ax_rc_failed, count( $ax_rc_results ) );
exit( $ax_rc_failed > 0 ? 1 : 0 );
