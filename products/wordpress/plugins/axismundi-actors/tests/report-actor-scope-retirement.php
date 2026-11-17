<?php
/** Read-only retirement inventory. Run with wp eval-file. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$system = axismundi_actors_get_system_actor();
$actors = axismundi_actors_actors_table();
$managers = axismundi_actors_managers_table();
$rows = $wpdb->get_results( $wpdb->prepare(
	"SELECT a.identity_id, a.actor_type, a.actor_scope, a.status, a.local_user_id, a.personal_owner_user_id,
	        (legacy.ID IS NOT NULL) AS legacy_user_exists, (personal.ID IS NOT NULL) AS personal_user_exists, m.role AS personal_role
	 FROM %i a LEFT JOIN %i legacy ON legacy.ID = a.local_user_id
	 LEFT JOIN %i personal ON personal.ID = a.personal_owner_user_id
	 LEFT JOIN %i m ON m.identity_id = a.identity_id AND m.principal_kind = 'wp_user' AND m.principal_id = a.personal_owner_user_id
	 WHERE a.origin = 'local' AND (a.actor_scope IS NULL OR a.actor_scope = 'site'
	   OR (a.actor_scope = 'user' AND (personal.ID IS NULL OR legacy.ID IS NULL))
	   OR (a.personal_owner_user_id IS NOT NULL AND (personal.ID IS NULL OR m.role IS NULL OR m.role <> 'owner')))
	 ORDER BY a.status, a.actor_scope, a.identity_id",
	$actors, $wpdb->users, $wpdb->users, $managers
), ARRAY_A );
if ( $wpdb->last_error ) { throw new RuntimeException( $wpdb->last_error ); }
$system_id = $system ? $system->get_identity_id() : 0;
$system_grants = $system_id ? $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM %i WHERE identity_id = %d OR (principal_kind = 'local_account' AND principal_id = %d)",
	$managers, $system_id, $system_id
) ) : null;
if ( $wpdb->last_error ) { throw new RuntimeException( $wpdb->last_error ); }
$groups = array();
foreach ( $rows as $row ) {
	$key = $row['status'] . '/' . ( $row['actor_scope'] ?? 'NULL' );
	$groups[ $key ][] = $row;
}
echo wp_json_encode( array(
	'system_identity_id' => $system_id,
	'system_grants' => null === $system_grants ? null : (int) $system_grants,
	'legacy_retirement_receipt' => get_option( 'ax_actors_legacy_site_retired', false ),
	'counts' => array_map( 'count', $groups ),
	'non_tombstone_candidates' => array_values( array_filter( $rows, static fn( array $row ) : bool => 'tombstone' !== $row['status'] ) ),
), JSON_PRETTY_PRINT ) . "\n";
