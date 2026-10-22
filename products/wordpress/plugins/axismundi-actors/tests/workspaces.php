<?php
/** Standalone access-policy matrix: php tests/workspaces.php. */

define( 'ABSPATH', __DIR__ );

/** Minimal identity fixture; no WordPress or JMAP bootstrap is involved. */
final class Axismundi_Actor {
	public function __construct( private int $id, private string $scope, private ?int $user, private string $status = 'internal', private bool $local = true ) {}
	public function get_identity_id() : int { return $this->id; }
	public function get_scope() : string { return $this->scope; }
	public function get_local_user_id() : ?int { return $this->user; }
	public function get_status() : string { return $this->status; }
	public function is_local() : bool { return $this->local; }
	public function is_managed() : bool { return 'managed' === $this->scope; }
}

$actors = array(
	1 => new Axismundi_Actor( 1, 'user', 10 ),
	2 => new Axismundi_Actor( 2, 'managed', null ),
	3 => new Axismundi_Actor( 3, 'site', null ),
	4 => new Axismundi_Actor( 4, 'user', 10, 'disabled' ),
	5 => new Axismundi_Actor( 5, 'managed', null, 'tombstone' ),
	6 => new Axismundi_Actor( 6, 'managed', null, 'public', false ),
);
$managers = array( 2 => array( 10 => 'owner', 20 => 'editor' ), 5 => array( 10 => 'owner' ), 6 => array( 10 => 'owner' ) );
function axismundi_actors_get_by_identity( int $id ) : ?Axismundi_Actor {
	return $GLOBALS['actors'][ $id ] ?? null;
}
function axismundi_actors_get_for_user( int $user ) : ?Axismundi_Actor {
	return 10 === $user ? $GLOBALS['actors'][1] : null;
}
function axismundi_actors_managed_actor_can_manage( int $id, int $user ) : bool {
	return isset( $GLOBALS['managers'][ $id ][ $user ] );
}
function axismundi_actors_list_manageable_actors( int $user ) : array {
	$result = array();
	foreach ( $GLOBALS['managers'] as $id => $users ) {
		if ( isset( $users[ $user ] ) ) {
			$result[] = $GLOBALS['actors'][ $id ];
		}
	}
	return $result;
}

require dirname( __DIR__ ) . '/includes/workspaces.php';
$checks = 0;
function check_workspace( bool $condition, string $label ) : void {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		throw new RuntimeException( $label );
	}
}

check_workspace( axismundi_actors_can_access_workspace( $actors[1], 10 ), 'Own identity accessible without a handle dependency' );
check_workspace( ! axismundi_actors_can_access_workspace( $actors[1], 20 ), 'Personal isolation' );
check_workspace( axismundi_actors_can_access_workspace( $actors[2], 20 ), 'Editor relationship accepted' );
check_workspace( ! axismundi_actors_can_access_workspace( $actors[2], 99 ), 'No implicit administrator access' );
foreach ( array( 3, 4, 5, 6 ) as $id ) {
	check_workspace( ! axismundi_actors_can_access_workspace( $actors[ $id ], 10 ), 'Site, disabled, tombstone, and remote identities excluded' );
}
check_workspace( array( 1, 2 ) === array_keys( axismundi_actors_workspace_options( 10 ) ), 'Personal-first filtered identity keys' );
check_workspace( array( 2 ) === array_keys( axismundi_actors_workspace_options( 20 ) ), 'Managed-only access without personal identity' );
check_workspace( null === axismundi_actors_resolve_workspace( 999, 10 ), 'Unknown identity rejected' );
check_workspace( null === axismundi_actors_resolve_workspace( 1, 0 ) && array() === axismundi_actors_workspace_options( 0 ), 'Anonymous denied' );
unset( $managers[2][20] );
check_workspace( null === axismundi_actors_resolve_workspace( 2, 20 ) && array() === axismundi_actors_workspace_options( 20 ), 'Revocation rechecked' );
check_workspace( ! class_exists( 'Axismundi\\Jmap\\Engine' ), 'Actors works without JMAP' );
printf( "PASS: %d workspace checks\n", $checks );
