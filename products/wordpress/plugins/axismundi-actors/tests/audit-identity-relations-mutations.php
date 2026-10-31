<?php
/** Negative control: suppress only the isolated legacy claim backfill. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );

add_filter( 'query', static function ( string $sql ) : string {
	if ( str_starts_with( $sql, 'INSERT INTO ' ) && str_contains( $sql, 'ax_audit_install_' ) && str_contains( $sql, 'ax_identity_relations' ) ) {
		return 'SELECT 1';
	}
	return $sql;
} );
require __DIR__ . '/audit-identity-relations.php';
