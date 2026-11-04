<?php
/** Administrator-only storage observations, never a repair or migration trigger. */
defined( 'ABSPATH' ) || exit;

function axismundi_actors_storage_debug( array $information ) : array {
	if ( ! current_user_can( 'manage_options' ) ) { return $information; }
	global $wpdb;
	$fields = array();
	$put = static function ( string $key, string $value ) use ( &$fields ) {
		$fields[$key] = array( 'label' => $key, 'value' => $value, 'private' => true );
	};
	$put( 'Plugin version', AXISMUNDI_ACTORS_VERSION );
	$put( 'Expected database version', AXISMUNDI_ACTORS_DB_VERSION );
	foreach ( array( 'ax_actors_db_version', 'ax_actors_body_version', 'ax_actors_account_access_version', 'ax_actors_principal_schema_version', 'ax_actors_account_selection_enabled', 'ax_object_projections_db_version', 'ax_op_object_identifiers_migrated' ) as $key ) {
		$value = get_option( $key, null );
		$put( $key, null === $value ? '(missing)' : (string) wp_json_encode( $value ) );
	}
	$put( 'System storage gate', '1' === get_option( 'ax_actors_body_version' ) && AXISMUNDI_ACTORS_DB_VERSION === get_option( 'ax_actors_db_version' ) ? 'ready' : 'not ready' );
	$put( 'Account selection effective', axismundi_actors_account_selection_enabled() ? 'enabled' : 'disabled' );
	$put( 'JMAP inventory available', function_exists( '\Axismundi\Jmap\admin_account_inventory' ) ? 'yes' : 'no' );
	$columns = array();
	$previous = $wpdb->suppress_errors( true );
	try {
		foreach ( array( 'ax_actors', 'ax_identities', 'ax_actors_next', 'ax_actors_legacy', 'ax_actormeta', 'ax_objects' ) as $suffix ) {
			$table = $wpdb->prefix . $suffix;
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( $wpdb->last_error ) { $put( $suffix, 'query failed (existence unknown)' ); continue; }
			if ( null === $exists ) { $put( $suffix, 'missing' ); continue; }
			$names = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
			if ( $wpdb->last_error ) { $put( $suffix, 'present; column query failed' ); continue; }
			$columns[$suffix] = $names;
			$put( $suffix, 'present; columns: ' . implode( ', ', $names ) );
		}
		$count = static function ( string $label, string $sql ) use ( $wpdb, $put ) {
			$value = $wpdb->get_var( $sql );
			$put( $label, $wpdb->last_error || null === $value ? 'query failed (count unknown)' : (string) $value );
		};
		$body = $columns['ax_actors'] ?? array();
		$legacy = $columns['ax_identities'] ?? array();
		if ( ! array_diff( array( 'id', 'identity_id', 'canonical_uri', 'canonical_uri_hash' ), $body ) ) {
			$count( 'Body invariant violations', $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE identity_id IS NULL OR identity_id<>id OR canonical_uri_hash<>SHA2(canonical_uri,256)', $wpdb->prefix . 'ax_actors' ) );
		} else { $put( 'Body invariant violations', 'not applicable (body columns unavailable)' ); }
		if ( in_array( 'object_kind', $legacy, true ) ) {
			$count( 'Legacy non-Actor identities', $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE object_kind<>'actor'", $wpdb->prefix . 'ax_identities' ) );
			$kinds = $wpdb->get_results( $wpdb->prepare( 'SELECT object_kind,COUNT(*) AS total FROM %i GROUP BY object_kind ORDER BY object_kind', $wpdb->prefix . 'ax_identities' ), ARRAY_A );
			$put( 'Legacy identity kinds', $wpdb->last_error ? 'query failed (kinds unknown)' : (string) wp_json_encode( $kinds ) );
		} else { $put( 'Legacy non-Actor identities', 'not applicable (legacy columns unavailable)' ); }
		if ( in_array( 'id', $legacy, true ) && in_array( 'identity_id', $body, true ) ) {
			$count( 'Legacy identities without Actor row', $wpdb->prepare( 'SELECT COUNT(*) FROM %i i LEFT JOIN %i a ON a.identity_id=i.id WHERE a.identity_id IS NULL', $wpdb->prefix . 'ax_identities', $wpdb->prefix . 'ax_actors' ) );
			$count( 'Actor rows without legacy identity', $wpdb->prepare( 'SELECT COUNT(*) FROM %i a LEFT JOIN %i i ON i.id=a.identity_id WHERE i.id IS NULL', $wpdb->prefix . 'ax_actors', $wpdb->prefix . 'ax_identities' ) );
		} else {
			$put( 'Legacy identities without Actor row', 'not applicable (legacy columns unavailable)' );
			$put( 'Actor rows without legacy identity', 'not applicable (legacy columns unavailable)' );
		}
	} finally { $wpdb->suppress_errors( $previous ); }
	$information['axismundi-actors-storage'] = array( 'label' => 'Axismundi Actors storage', 'description' => 'Read-only observations; unavailable counts are not zero. This section does not run or repair migrations.', 'fields' => $fields );
	return $information;
}
add_filter( 'debug_information', 'axismundi_actors_storage_debug' );
