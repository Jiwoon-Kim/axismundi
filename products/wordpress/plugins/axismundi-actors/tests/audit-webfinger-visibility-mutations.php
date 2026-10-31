<?php
/** In-memory public-profile gate mutants, using the same visibility assertions. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
( static function () : void {
	global $wpdb;
	$probe = require __DIR__ . '/webfinger-visibility-fixtures.php';
	$user = wp_insert_user( array( 'user_login' => 'axwf' . wp_generate_password( 12, false, false ), 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	if ( is_wp_error( $user ) ) { throw new RuntimeException( 'Cannot create visibility mutation user' ); }
	$id = 0;
	$source = static function ( string $function ) : string {
		$r = new ReflectionFunction( $function );
		return implode( '', array_slice( file( $r->getFileName() ), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1 ) );
	};
	try {
		$actor = axismundi_actors_ensure_for_user( (int) $user );
		if ( ! $actor instanceof Axismundi_Actor ) { throw new RuntimeException( 'Cannot create visibility mutation Actor' ); }
		$id = $actor->get_identity_id();
		if ( is_wp_error( axismundi_actors_register_handle( $id, 'wf' . strtolower( wp_generate_password( 12, false, false ) ) ) ) ) { throw new RuntimeException( 'Cannot register visibility mutation handle' ); }
		foreach ( $probe( $id, 'axismundi_actors_webfinger_descriptor' ) as $label => $ok ) {
			if ( ! $ok ) { throw new RuntimeException( 'Baseline: ' . $label ); }
		}
		foreach ( array(
			'status' => array( "return 'public' === \$actor->get_status()", 'return true', 2, 'a disabled actor is concealed' ),
			'lock' => array( '&& $actor->is_handle_locked()', '', 1, 'an actor with an unlocked handle is concealed' ),
		) as $mode => [ $anchor, $replacement, $expected_count, $expected ] ) {
			$predicate = str_replace( $anchor, $replacement, $source( 'axismundi_actors_is_public_profile' ), $count );
			if ( $expected_count !== $count ) { throw new RuntimeException( 'MISSED ' . $mode . ': anchor changed' ); }
			$namespace = 'AxWebfingerMutation' . $mode;
			eval( 'namespace ' . $namespace . '; use \\Axismundi_Actor; use \\WP_Error; ' . $predicate . $source( 'axismundi_actors_webfinger_descriptor' ) );
			$results = $probe( $id, $namespace . '\\axismundi_actors_webfinger_descriptor' );
			if ( false !== ( $results[ $expected ] ?? null ) ) { throw new RuntimeException( 'MISSED ' . $mode ); }
			foreach ( $results as $label => $ok ) { if ( $label !== $expected && ! $ok ) { throw new RuntimeException( 'Unexpected failure: ' . $label ); } }
			echo 'CAUGHT ' . $mode . ': ' . $expected . "\n";
		}
	} finally {
		if ( $id ) {
			$wpdb->delete( axismundi_actors_addresses_table(), array( 'identity_id' => $id ) );
			$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $id ) );
			$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
		}
		wp_delete_user( (int) $user );
	}
	echo "PASS: 2 mutation checks\n";
} )();
