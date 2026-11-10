<?php
/** Dev-only: authorized, filtered pagination over the Activity ledger. */
defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) {
		throw new RuntimeException( $label );
	}
	++$checks;
	echo "PASS: {$label}\n";
};

$table = axismundi_act_activities_table();
$original_user = get_current_user_id();
$original_get  = $_GET;
$fixture = 'https://example.test/admin-outbox/' . wp_generate_uuid4();
$actor = null;
$user = 0;
$transaction = false;
$candidates = $wpdb->get_results( $wpdb->prepare(
	"SELECT m.identity_id, m.principal_id FROM %i m
	 LEFT JOIN %i i ON i.id = m.identity_id
	 LEFT JOIN (SELECT actor_uri_hash, COUNT(*) AS total FROM %i WHERE direction = 'outbound' GROUP BY actor_uri_hash) a ON a.actor_uri_hash = i.canonical_uri_hash
	 WHERE m.principal_kind = 'wp_user' ORDER BY a.total DESC",
	axismundi_actors_managers_table(), axismundi_actors_identities_table(), $table
), ARRAY_A );

add_filter( 'axismundi_actors_account_selection_enabled', '__return_false' );
try {
	foreach ( $candidates as $candidate ) {
		$user = (int) $candidate['principal_id'];
		$actor = axismundi_actors_get_by_identity( (int) $candidate['identity_id'] );
		if ( user_can( $user, 'edit_posts' ) && true === axismundi_act_outbox_authorize( $user, $actor ) ) {
			break;
		}
		$actor = null;
	}
	$assert( $actor instanceof Axismundi_Actor, 'Existing authorized workspace is available' );
	wp_set_current_user( $user );
	$identity = (string) $actor->get_identity_id();
	$selection = static function ( $value, $id, $key, $single ) use ( $identity, $user ) {
		return $user === (int) $id && AXISMUNDI_ACTORS_ACTING_META === $key ? array( $identity ) : $value;
	};
	add_filter( 'get_user_metadata', $selection, 10, 4 );

	$sql = array();
	$capture = static function ( string $query ) use ( $table, &$sql ) : string {
		if ( str_contains( $query, "`{$table}`" ) ) {
			$sql[] = $query;
		}
		return $query;
	};
	$measure = static function ( string $query ) use ( $wpdb ) : float {
		$times = array();
		for ( $i = 0; $i < 7; ++$i ) {
			$start = hrtime( true );
			$wpdb->query( $query );
			$times[] = ( hrtime( true ) - $start ) / 1e6;
		}
		sort( $times );
		return $times[3];
	};
	foreach ( array( '', 'Create' ) as $type ) {
		$first = axismundi_act_admin_outbox_rows( $actor, $type, '', 1 );
		$assert( is_array( $first ), 'Real data query succeeds: ' . ( $type ?: 'all' ) );
		$sql = array();
		add_filter( 'query', $capture );
		try {
			axismundi_act_admin_outbox_rows( $actor, $type, '', max( 1, $first['pages'] ) );
		} finally {
			remove_filter( 'query', $capture );
		}
		foreach ( $sql as $index => $query ) {
			$plan = $wpdb->get_row( 'EXPLAIN ' . $query, ARRAY_A );
			printf( "Timing: %s, %d matching, %s %.3f ms median (key=%s, estimated rows=%s)\n", $type ?: 'all', $first['total'], 0 === $index ? 'COUNT' : 'last page', $measure( $query ), $plan['key'] ?? 'none', $plan['rows'] ?? '?' );
		}
	}

	$transaction = false !== $wpdb->query( 'START TRANSACTION' );
	$assert( $transaction, 'Fixture transaction started' );
	$insert = static function ( string $suffix, string $type, string $date, string $direction = 'outbound', string $actor_uri = '', string $target = '' ) use ( $wpdb, $table, $actor, $fixture ) : int {
		$uri = $fixture . '/' . $suffix;
		$actor_uri = $actor_uri ?: $actor->get_uri();
		$target = $target ?: $fixture;
		if ( ! $wpdb->insert( $table, array(
			'activity_uri' => $uri, 'activity_uri_hash' => hash( 'sha256', $uri ), 'activity_type' => $type,
			'actor_uri' => $actor_uri, 'actor_uri_hash' => hash( 'sha256', $actor_uri ), 'direction' => $direction,
			'object_uri' => $target, 'object_uri_hash' => hash( 'sha256', $target ),
			'audience_json' => '{}', 'payload_json' => '{}', 'payload_hash' => hash( 'sha256', '{}' ),
			'feed_sort_at' => $date, 'created_at' => $date, 'updated_at' => $date,
		) ) ) {
			throw new RuntimeException( $wpdb->last_error );
		}
		return (int) $wpdb->insert_id;
	};
	$ids = array();
	for ( $i = 0; $i < 53; ++$i ) {
		$ids[] = $insert( (string) $i, 'Create', $i < 3 ? '2020-01-02 00:00:00' : '2020-01-01 12:00:00' );
	}
	$insert( 'wrong-type', 'Update', '2020-01-02 00:00:00' );
	$insert( 'wrong-date', 'Create', '2020-01-03 00:00:00' );
	$insert( 'wrong-actor', 'Create', '2020-01-02 00:00:00', 'outbound', $fixture . '/actor' );
	$insert( 'wrong-direction', 'Create', '2020-01-02 00:00:00', 'inbound' );
	$insert( 'wrong-object', 'Create', '2020-01-02 00:00:00', 'outbound', '', $fixture . '/other' );
	$expected = array_merge( array_reverse( array_slice( $ids, 0, 3 ) ), array_reverse( array_slice( $ids, 3 ) ) );
	$actual = array();
	foreach ( array( 25, 25, 3, 0 ) as $index => $size ) {
		$page = axismundi_act_admin_outbox_rows( $actor, 'Create', $fixture, $index + 1, '2020-01-01', '2020-01-02' );
		$assert( is_array( $page ) && 53 === $page['total'] && 3 === $page['pages'] && $size === count( $page['rows'] ), 'Filtered count and page boundary: ' . ( $index + 1 ) );
		array_push( $actual, ...array_map( 'intval', array_column( $page['rows'], 'id' ) ) );
	}
	$assert( $expected === $actual, 'COUNT equals page sum; order is time then ID, without repeats' );

	$render = static function ( array $input ) : string {
		$_GET = wp_slash( $input );
		ob_start();
		try {
			axismundi_act_render_admin_outbox();
			return ob_get_contents();
		} finally {
			ob_end_clean();
		}
	};
	$filters = array( 'actor' => $identity, 'activity_type' => 'Create', 'target' => $fixture, 'from' => '2020-01-01', 'through' => '2020-01-02' );
	$html = $render( $filters + array( 'paged' => '2' ) );
	$assert( str_contains( $html, 'aria-current="page">2' ) && str_contains( $html, '53 activities' ), 'History is default and displays the selected page and total' );
	$assert( 1 === preg_match( '/href="([^"]+)" aria-label="Last page"/', $html, $matches ), 'Last page is directly reachable' );
	wp_parse_str( (string) wp_parse_url( html_entity_decode( $matches[1] ), PHP_URL_QUERY ), $query );
	$assert( $query === array_replace( $query, $filters + array( 'paged' => '3', 'view' => 'history' ) ), 'Navigation keeps actor and every filter' );
	$assert( ! str_contains( $html, 'name="paged"' ) && str_contains( $render( $filters ), 'aria-current="page">1' ), 'Submitting filters starts from page one' );
	$assert( str_contains( $render( $filters + array( 'paged' => '4' ) ), 'No matching activities.' ), 'Out-of-range page is empty and not an error' );
	$collection = $render( array( 'view' => 'collection', 'actor' => $identity ) );
	$assert( str_contains( $collection, 'Open public Outbox JSON' ) && str_contains( $collection, 'OrderedCollection' ) && ! str_contains( $collection, 'Activity history pages' ), 'Collection still renders ActivityPub JSON' );

	wp_set_current_user( 0 );
	$sql = array();
	add_filter( 'query', $capture );
	try {
		$denied = axismundi_act_admin_outbox_rows( $actor, 'Create', $fixture, 1 );
	} finally {
		remove_filter( 'query', $capture );
	}
	$assert( is_wp_error( $denied ) && 403 === $denied->get_error_data()['status'] && ! $sql, 'Unauthorized caller cannot query either rows or count' );
	echo "{$checks} checks passed.\n";
} finally {
	if ( $transaction ) {
		$wpdb->query( 'ROLLBACK' );
	}
	wp_set_current_user( $original_user );
	$_GET = $original_get;
	remove_filter( 'axismundi_actors_account_selection_enabled', '__return_false' );
	if ( isset( $selection ) ) {
		remove_filter( 'get_user_metadata', $selection, 10 );
	}
}
