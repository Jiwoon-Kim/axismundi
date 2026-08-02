<?php
/**
 * Remote-object API rename compatibility (dev-only).
 *
 * The repository's four functions were renamed to the canonical
 * `axismundi_{product}_{verb}_{domain}` form and the old names left as forwarding aliases while
 * five products migrate their call sites. Two things have to hold for the whole migration, and
 * nothing else asserts either of them:
 *
 * 1. The canonical names perform the same contract, rather than being new functions that merely
 *    exist. Every other audit exercises whichever name it happens to call, so a canonical
 *    function that silently diverged would be found by nobody until a product moved onto it.
 * 2. The aliases stay alive. This is deliberately the last audit that calls the old names — the
 *    other suites move to canonical names with their product's call sites, so once this file is
 *    the only caller left, the aliases have no consumer inside the codebase and can go.
 *
 * A forwarding alias fails in one specific way: it drops an argument. So the optional second
 * parameter of each function is asserted through the alias, not just the required first.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/object-relations.php';
require_once dirname( __DIR__ ) . '/includes/remote-objects.php';

$ax_compat_results = array();
$ax_compat_uris    = array(
	'https://remote.example/objects/api-compat-a',
	'https://remote.example/objects/api-compat-b',
);

/** @param array<bool> $results Results. @param string $label Label. @param bool $condition Condition. */
function ax_compat_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** @return array<string,mixed> A minimal valid Note observation. */
function ax_compat_payload( string $uri ) : array {
	return array(
		'id'           => $uri,
		'type'         => 'Note',
		'attributedTo' => 'https://remote.example/users/alice',
		'content'      => '<p>Compatibility fixture.</p>',
		'published'    => '2026-08-02T09:00:00Z',
	);
}

try {
	global $wpdb;
	axismundi_op_install();
	$table = axismundi_op_remote_objects_table();
	foreach ( $ax_compat_uris as $ax_compat_uri ) {
		axismundi_op_delete_remote_object( $ax_compat_uri );
	}

	ax_compat_assert(
		$ax_compat_results,
		'both names exist and are distinct functions, so this is a rename with an alias and not one name pointing at itself',
		function_exists( 'axismundi_op_get_remote_object' ) && function_exists( 'axismundi_op_remote_object_get' )
			&& function_exists( 'axismundi_op_store_remote_object' ) && function_exists( 'axismundi_op_remote_object_store' )
			&& function_exists( 'axismundi_op_delete_remote_object' ) && function_exists( 'axismundi_op_remote_object_delete' )
			&& function_exists( 'axismundi_op_get_remote_object_by_hash' ) && function_exists( 'axismundi_op_remote_object_get_by_hash' )
	);

	// store: written through the canonical name, found through both.
	$stored = axismundi_op_store_remote_object( ax_compat_payload( $ax_compat_uris[0] ), array( 'etag' => '"compat"' ) );
	ax_compat_assert(
		$ax_compat_results,
		'the canonical store writes a row the canonical get returns, with the response metadata it was given',
		is_array( $stored ) && '"compat"' === (string) $stored['etag']
			&& is_array( axismundi_op_get_remote_object( $ax_compat_uris[0] ) )
	);

	/*
	 * The central claim: one row, two names, one answer. Compared whole rather than field by
	 * field — a wrapper that returned a subset, or dropped the decoded `payload` the callers
	 * actually read, would pass any narrower check.
	 */
	ax_compat_assert(
		$ax_compat_results,
		'get_remote_object(uri) and remote_object_get(uri) return the identical row, payload included',
		axismundi_op_get_remote_object( $ax_compat_uris[0] ) === axismundi_op_remote_object_get( $ax_compat_uris[0] )
			&& is_array( axismundi_op_get_remote_object( $ax_compat_uris[0] )['payload'] ?? null )
	);

	$hash = hash( 'sha256', $ax_compat_uris[0] );
	ax_compat_assert(
		$ax_compat_results,
		'the by-hash pair agrees the same way, and agrees with the URI lookup',
		axismundi_op_get_remote_object_by_hash( $hash ) === axismundi_op_remote_object_get_by_hash( $hash )
			&& axismundi_op_get_remote_object_by_hash( $hash ) === axismundi_op_get_remote_object( $ax_compat_uris[0] )
	);

	// store through the alias, read through the canonical name: the write half of the same claim.
	$aliased = axismundi_op_remote_object_store(
		array_merge( ax_compat_payload( $ax_compat_uris[1] ), array( 'content' => '<p>Written through the old name.</p>' ) ),
		array( 'etag' => '"compat-alias"' )
	);
	$read_back = axismundi_op_get_remote_object( $ax_compat_uris[1] );
	ax_compat_assert(
		$ax_compat_results,
		'store_remote_object through the alias lands where get_remote_object reads, keeping its second argument',
		is_array( $aliased ) && is_array( $read_back )
			&& (string) $aliased['payload_hash'] === (string) $read_back['payload_hash']
			&& '"compat-alias"' === (string) $read_back['etag']
			&& false !== strpos( (string) $read_back['content'], 'old name' )
	);

	/*
	 * The optional argument, asserted where it can actually be seen.
	 *
	 * `$touch` refreshes the access time, and the timestamp has one-second resolution — so the row
	 * is aged first rather than trusting two calls in the same second to differ. An alias that
	 * dropped this parameter would silently stop extending the retention of everything its callers
	 * read, and every reader would still get its row, so nothing else would notice.
	 *
	 * Both names are aged and read, because "the alias forwards it" and "the canonical one still
	 * does it" are two claims and only one of them is about the alias.
	 */
	$age_and_read = static function ( callable $reader ) use ( $wpdb, $table, $hash, $ax_compat_uris ) : array {
		$wpdb->update( $table, array( 'last_accessed_at' => '2020-01-01 00:00:00' ), array( 'object_uri_hash' => $hash ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture ages its own row.
		$before = axismundi_op_get_remote_object( $ax_compat_uris[0] );
		$after  = $reader( $ax_compat_uris[0], true );
		return array( (string) ( $before['last_accessed_at'] ?? '' ), (string) ( $after['last_accessed_at'] ?? '' ) );
	};
	list( $alias_before, $alias_after )         = $age_and_read( 'axismundi_op_remote_object_get' );
	list( $canonical_before, $canonical_after ) = $age_and_read( 'axismundi_op_get_remote_object' );
	ax_compat_assert(
		$ax_compat_results,
		'$touch survives the alias, so a read through either name still extends retention',
		'2020-01-01 00:00:00' === $alias_before && '2020-01-01 00:00:00' !== $alias_after
			&& '2020-01-01 00:00:00' === $canonical_before && '2020-01-01 00:00:00' !== $canonical_after
	);

	// delete: gone through both readers, and only the addressed row.
	$deleted = axismundi_op_delete_remote_object( $ax_compat_uris[0] );
	ax_compat_assert(
		$ax_compat_results,
		'delete_remote_object(uri) leaves both names reading null, and leaves the other row alone',
		$deleted
			&& null === axismundi_op_get_remote_object( $ax_compat_uris[0] )
			&& null === axismundi_op_remote_object_get( $ax_compat_uris[0] )
			&& null === axismundi_op_get_remote_object_by_hash( $hash )
			&& is_array( axismundi_op_get_remote_object( $ax_compat_uris[1] ) )
	);

	/*
	 * The alias deletes, and both names answer a missing row identically.
	 *
	 * Identically, not `false`: this repository reports whether the delete statement ran, not
	 * whether it matched anything, so removing an absent URI is a success. That is worth pinning
	 * either way — the point here is that the two names cannot disagree about it.
	 */
	$alias_deleted     = axismundi_op_remote_object_delete( $ax_compat_uris[1] );
	$missing_canonical = axismundi_op_delete_remote_object( $ax_compat_uris[1] );
	$missing_alias     = axismundi_op_remote_object_delete( $ax_compat_uris[1] );
	ax_compat_assert(
		$ax_compat_results,
		'the delete alias deletes, and both names report a missing row the same way',
		$alias_deleted && $missing_canonical === $missing_alias
			&& null === axismundi_op_get_remote_object( $ax_compat_uris[1] )
	);
} finally {
	foreach ( $ax_compat_uris as $ax_compat_uri ) {
		axismundi_op_delete_remote_object( $ax_compat_uri );
	}
}

/*
 * How much of the migration is left, printed rather than asserted.
 *
 * A count is not a pass/fail condition — the old names are supposed to be in use right now. It is
 * the signal for when this audit has done its job: at zero, this file is the only caller left and
 * the aliases can be removed along with it.
 */
$ax_compat_old   = array( 'axismundi_op_remote_object_get', 'axismundi_op_remote_object_store', 'axismundi_op_remote_object_delete' );
$ax_compat_files = array();
$ax_compat_root  = dirname( dirname( __DIR__ ) );
$ax_compat_dir   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $ax_compat_root ) );
foreach ( $ax_compat_dir as $ax_compat_file ) {
	if ( ! $ax_compat_file->isFile() || 'php' !== strtolower( $ax_compat_file->getExtension() ) ) {
		continue;
	}
	$ax_compat_path = str_replace( '\\', '/', (string) $ax_compat_file->getPathname() );
	if ( false !== strpos( $ax_compat_path, '/tests/audit-remote-object-api-compat.php' )
		|| false !== strpos( $ax_compat_path, '/includes/remote-objects.php' )
	) {
		continue;
	}
	$ax_compat_body = (string) file_get_contents( $ax_compat_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- dev-only migration counter.
	foreach ( $ax_compat_old as $ax_compat_name ) {
		if ( 1 === preg_match( '/\b' . preg_quote( $ax_compat_name, '/' ) . '\b(?!_)/', $ax_compat_body ) ) {
			$ax_compat_files[] = $ax_compat_path;
			break;
		}
	}
}
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n-- %d file(s) in this plugin still call the deprecated names --\n", count( $ax_compat_files ) );

$ax_compat_failures = count( array_filter( $ax_compat_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_compat_results ), $ax_compat_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_compat_failures > 0 ? 1 : 0 );
}
exit( $ax_compat_failures > 0 ? 1 : 0 );
