<?php
/** Two independent visibility gates on an already handled, disposable Actor. */
defined( 'ABSPATH' ) || exit( 1 );
return static function ( int $id, callable $descriptor ) : array {
	global $wpdb;
	$actor = axismundi_actors_get_by_identity( $id );
	$handle = $actor->get_preferred_username();
	$status = $actor->get_status();
	$lock = $wpdb->get_var( $wpdb->prepare( 'SELECT handle_locked_at FROM %i WHERE identity_id=%d', axismundi_actors_actors_table(), $id ) );
	if ( ! $actor->is_local() || ! $lock || '' === $handle ) { throw new RuntimeException( 'Visibility fixture requires a locked local handle' ); }
	$resource = 'acct:' . $handle . '@' . axismundi_actors_webfinger_authority();
	$concealed = static fn( $result ) => is_wp_error( $result ) && 'ax_actors_webfinger_not_found' === $result->get_error_code();
	try {
		if ( ! axismundi_actors_set_status( $id, 'disabled' ) ) { throw new RuntimeException( 'Cannot disable visibility fixture' ); }
		$results = array( 'a disabled actor is concealed' => $concealed( $descriptor( $resource ) ) );
		if ( ! axismundi_actors_set_status( $id, 'public' ) || false === $wpdb->update( axismundi_actors_actors_table(), array( 'handle_locked_at' => null ), array( 'identity_id' => $id ) ) ) { throw new RuntimeException( 'Cannot unlock visibility fixture' ); }
		$results['an actor with an unlocked handle is concealed'] = $concealed( $descriptor( $resource ) );
		return $results;
	} finally {
		$wpdb->update( axismundi_actors_actors_table(), array( 'handle_locked_at' => $lock ), array( 'identity_id' => $id ) );
		axismundi_actors_set_status( $id, $status );
	}
};
