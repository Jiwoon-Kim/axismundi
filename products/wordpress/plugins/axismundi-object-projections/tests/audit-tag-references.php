<?php
/** URI tag evidence and derived membership regression. */
defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$refs = axismundi_op_tag_references_table();
$usage = axismundi_op_term_usage_table();
$prefix = 'https://tag-index.example/' . wp_generate_uuid4();
$name = 'TagIndex' . wp_generate_password( 10, false, false );
$uris = array_map( static fn( $kind ) => $prefix . '/' . $kind, array( 'note', 'actor', 'question', 'create' ) );
$terms = array();
$posts = array();
$checks = array();
$check = static function ( string $label, bool $ok ) use ( &$checks ) {
	$checks[] = $ok;
	printf( "[%s] [project choice] %s\n", $ok ? 'PASS' : 'FAIL', $label );
};
$members = static function ( string $uri ) use ( $wpdb, $usage ) : array {
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$usage} WHERE source_uri_hash=%s AND source_uri=%s AND is_current=1", hash( 'sha256', $uri ), $uri ), ARRAY_A );
};
$source = array( 'provider' => 'c2s', 'role' => 'content', 'complete' => true, 'feed_sort_at' => '2026-01-01 00:00:00' );
$delete_gate = has_filter( 'pre_delete_post', 'axismundi_act_pre_delete_post_lifecycle' );
// This index fixture does not submit an Actor-authorized federation command.
if ( false !== $delete_gate ) {
	remove_filter( 'pre_delete_post', 'axismundi_act_pre_delete_post_lifecycle', $delete_gate );
}
try {
	$check( 'both index layers install', axismundi_op_install_tag_reference_schema() );
	$tags = array( array( 'name' => '#' . $name ), array( 'name' => '#' . strtoupper( $name ), 'href' => 'https://peer.example/tags/test' ), array( 'type' => 'Person', 'id' => 'https://peer.example/person' ), array( 'type' => 'Mention', 'href' => 'https://peer.example/person' ), array( 'x:unknown' => array( 'nested' => true ) ) );
	$check( 'targetless tags are accepted', true === axismundi_op_replace_tag_references( $uris[0], $tags, $source ) );
	$evidence = axismundi_op_tag_references( $uris[0] );
	$check( 'distinct descriptors of the same source and target survive', 5 === count( $evidence ) );
	$check( 'Person is preserved without coercion to Mention', $tags[2] === json_decode( $evidence[2]['descriptor_json'], true ) );
	$check( 'unknown descriptor evidence survives verbatim', $tags[4] === json_decode( $evidence[4]['descriptor_json'], true ) );
	$first = $members( $uris[0] );
	$check( 'case variants produce one member per source and term', 1 === count( $first ) );
	$term = axismundi_op_find_hashtag_term( $name );
	$terms[] = $term->term_id;
	$check( 'C2S usage does not overwrite Core taxonomy count', 0 === (int) get_term( $term->term_id )->count );
	$changed = $source;
	$changed['feed_sort_at'] = '2026-10-20 00:00:00';
	axismundi_op_replace_tag_references( $uris[0], $tags, $changed );
	$check( 'an Update cannot move the immutable membership cursor', $first[0]['id'] === $members( $uris[0] )[0]['id'] && $first[0]['feed_sort_at'] === $members( $uris[0] )[0]['feed_sort_at'] );
	$partial = $source;
	$partial['complete'] = false;
	axismundi_op_replace_tag_references( $uris[0], array(), $partial );
	$check( 'partial absence cannot retract current evidence', 5 === count( axismundi_op_tag_references( $uris[0] ) ) && 1 === count( $members( $uris[0] ) ) );
	axismundi_op_replace_tag_references( $uris[0], array(), $source );
	$check( 'complete absence retracts current membership but keeps history', array() === axismundi_op_tag_references( $uris[0] ) && 5 === count( axismundi_op_tag_references( $uris[0], false ) ) && array() === $members( $uris[0] ) );
	axismundi_op_replace_tag_references( $uris[0], $tags, $changed );
	$check( 'remove then re-add keeps the initial cursor', $first[0]['feed_sort_at'] === $members( $uris[0] )[0]['feed_sort_at'] );
	$actor = array( 'provider' => 'actor-profile', 'complete' => true );
	axismundi_op_replace_tag_references( $uris[1], array( $tags[0] ), $actor );
	$check( 'Actor profile reference is retained without archive membership', 1 === count( axismundi_op_tag_references( $uris[1] ) ) && array() === $members( $uris[1] ) );
	$check( 'an Actor provider cannot grant itself content membership', is_wp_error( axismundi_op_replace_tag_references( $uris[1], array( $tags[0] ), array_merge( $actor, array( 'role' => 'content' ) ) ) ) && array() === $members( $uris[1] ) );
	axismundi_op_replace_tag_references( $uris[2], array( $tags[0] ), array_merge( $source, array( 'provider' => 'activity-ledger' ) ) );
	$check( 'a standalone Question can explicitly participate as content', 1 === count( $members( $uris[2] ) ) );
	axismundi_op_replace_tag_references( $uris[3], array( $tags[0], array( 'object' => array( 'tag' => array( $tags[1] ) ) ) ), array( 'provider' => 'activity-ledger', 'complete' => true ) );
	$check( 'envelope tags and embedded object tags do not leak into content membership', 2 === count( axismundi_op_tag_references( $uris[3] ) ) && array() === $members( $uris[3] ) );
	axismundi_op_retract_tag_source( $uris[0], 'remote-cache' );
	$check( 'remote eviction cannot retract local authored evidence', 5 === count( axismundi_op_tag_references( $uris[0] ) ) && 1 === count( $members( $uris[0] ) ) );
	$check( 'another provider cannot replace local evidence', is_wp_error( axismundi_op_replace_tag_references( $uris[0], array(), array( 'provider' => 'remote-cache', 'complete' => true ) ) ) && 1 === count( $members( $uris[0] ) ) );
	$check( 'bad sort timestamps fail without changing the current snapshot', is_wp_error( axismundi_op_replace_tag_references( $uris[0], array(), array_merge( $source, array( 'feed_sort_at' => 'tomorrow' ) ) ) ) && 1 === count( $members( $uris[0] ) ) );
	$wpdb->query( 'START TRANSACTION' );
	try {
		$wpdb->update( $usage, array( 'feed_sort_at' => '2026-02-02 00:00:00' ), array( 'source_uri_hash' => hash( 'sha256', $uris[0] ) ) );
		$nested = axismundi_op_replace_tag_references( $uris[0], $tags, $source );
	} finally {
		$wpdb->query( 'ROLLBACK' );
	}
	$check( 'an enclosing transaction is refused without implicitly committing its writes', is_wp_error( $nested ) && 'ax_op_tag_transaction' === $nested->get_error_code() && $first[0]['feed_sort_at'] === $members( $uris[0] )[0]['feed_sort_at'] );
	$wpdb->query( 'SET autocommit=0' );
	try {
		$manual = axismundi_op_replace_tag_references( $uris[0], $tags, $source );
	} finally {
		$wpdb->query( 'ROLLBACK' );
		$wpdb->query( 'SET autocommit=1' );
	}
	$check( 'manual autocommit mode is refused before source writes', is_wp_error( $manual ) && 'ax_op_tag_transaction' === $manual->get_error_code() );

	$post_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Tag index fixture' ) );
	$posts[] = $post_id;
	wp_set_object_terms( $post_id, array( (int) $term->term_id ), AXISMUNDI_OP_HASHTAG_TAXONOMY );
	$profile = axismundi_op_local_object_profile( get_post( $post_id ) );
	$post_uri = $profile['uri'];
	$uris[] = $post_uri;
	$check( 'Core term assignment updates the URI index without making a visibility claim', 1 === count( $members( $post_uri ) ) );
	unset( $GLOBALS['axismundi_op_post_tag_fingerprints'][ $post_id ] );
	$starts = 0;
	$count_starts = static function ( $sql ) use ( &$starts ) {
		if ( 'START TRANSACTION' === $sql ) {
			$starts++;
		}
		return $sql;
	};
	add_filter( 'query', $count_starts );
	try {
		axismundi_op_tag_terms_changed( $post_id, array(), array(), AXISMUNDI_OP_HASHTAG_TAXONOMY );
		axismundi_op_tag_terms_removed( $post_id, array(), AXISMUNDI_OP_HASHTAG_TAXONOMY );
		axismundi_op_saved_post_tag_references( $post_id );
	} finally {
		remove_filter( 'query', $count_starts );
	}
	$check( 'identical term and save hooks perform only one projection transaction', 1 === $starts );
	wp_remove_object_terms( $post_id, $term->term_id, AXISMUNDI_OP_HASHTAG_TAXONOMY );
	$check( 'Core removal retracts membership', array() === $members( $post_uri ) );
	wp_set_object_terms( $post_id, array( (int) $term->term_id ), AXISMUNDI_OP_HASHTAG_TAXONOMY );
	$wpdb->delete( $usage, array( 'source_uri_hash' => hash( 'sha256', $post_uri ) ) );
	axismundi_op_sync_post_tag_references( $post_id );
	$check( 'a lost projection rebuilds from Core taxonomy authority', 1 === count( $members( $post_uri ) ) );
	$deleted_post = wp_delete_post( $post_id, true );
	$check( 'source deletion retracts Core membership', $deleted_post instanceof WP_Post && array() === $members( $post_uri ) );

	$remote_uri = $prefix . '/remote';
	$uris[] = $remote_uri;
	$payload = array( 'id' => $remote_uri, 'type' => 'Question', 'attributedTo' => 'https://tag-index.example/actor', 'content' => 'test', 'tag' => array( array( 'name' => '#' . $name ) ) );
	$remote = axismundi_op_store_remote_object( $payload );
	$check( 'remote accepted payload indexes untyped hashtag declarations', is_array( $remote ) && 1 === count( $members( $remote_uri ) ) );
	$wpdb->delete( $usage, array( 'source_uri_hash' => hash( 'sha256', $remote_uri ) ) );
	axismundi_op_sync_remote_tag_references( axismundi_op_get_remote_object( $remote_uri ) );
	$check( 'remote projection rebuild reads payload, not the legacy hashtag index', 1 === count( $members( $remote_uri ) ) );
	unset( $payload['tag'] );
	axismundi_op_store_remote_object( $payload );
	$check( 'an incomplete received observation does not retract previous tags', 1 === count( $members( $remote_uri ) ) );
	axismundi_op_store_remote_object( $payload, array( 'complete' => true ) );
	$check( 'a complete stored replacement with absent tags retracts usage', array() === $members( $remote_uri ) );
	$next_uri = $prefix . '/next-remote';
	$uris[] = $next_uri;
	$next = axismundi_op_store_remote_object( array_merge( $payload, array( 'id' => $next_uri ) ) );
	$remote_table = axismundi_op_remote_objects_table();
	$bad_id = (int) $remote['id'];
	$inject_bad_date = static function ( $sql ) use ( $wpdb, $remote_table, $bad_id ) {
		if ( str_starts_with( $sql, $wpdb->prepare( 'SELECT * FROM %i WHERE ID >', $remote_table ) ) ) {
			return str_replace( 'SELECT *', "SELECT *, IF(id={$bad_id},'invalid',published_at) AS published_at", $sql );
		}
		return $sql;
	};
	add_filter( 'query', $inject_bad_date );
	try {
		$failed_batch = axismundi_op_rebuild_tag_references( 'remote-cache', $bad_id - 1, 2 );
	} finally {
		remove_filter( 'query', $inject_bad_date );
	}
	$check( 'a bad rebuild row is reported and does not trap resume on the same id', is_array( $failed_batch ) && 2 === $failed_batch['processed'] && 1 === $failed_batch['failed'] && $bad_id === $failed_batch['errors'][0]['id'] && (int) $next['id'] === $failed_batch['after'] );
	$post_scan = '';
	$capture_scan = static function ( $sql ) use ( $wpdb, &$post_scan ) {
		if ( str_contains( $sql, $wpdb->prepare( 'FROM %i WHERE ID >', $wpdb->posts ) ) ) {
			$post_scan = $sql;
		}
		return $sql;
	};
	add_filter( 'query', $capture_scan );
	try {
		axismundi_op_rebuild_tag_references( 'wordpress-taxonomy', 0, 1 );
	} finally {
		remove_filter( 'query', $capture_scan );
	}
	$check( 'Core rebuild fetches only ids of taxonomy-enabled post types', str_starts_with( $post_scan, 'SELECT ID FROM ' ) && str_contains( $post_scan, 'post_type IN (' ) && ! str_contains( $post_scan, "'revision'" ) );
	axismundi_op_delete_remote_object( $remote_uri );
	$batch = axismundi_op_rebuild_tag_references( 'remote-cache', 0, 1 );
	$check( 'batch rebuild is bounded and resumable', is_array( $batch ) && $batch['processed'] <= 1 && isset( $batch['after'], $batch['done'] ) );
	axismundi_op_replace_tag_references( $uris[0], array( array( 'name' => '#' ) ), $partial );
	$check( 'a malformed hashtag remains evidence without preventing other tags', 6 === count( axismundi_op_tag_references( $uris[0] ) ) && 1 === count( $members( $uris[0] ) ) );
	wp_delete_term( $term->term_id, AXISMUNDI_OP_HASHTAG_TAXONOMY );
	$check( 'term deletion removes the mapping but preserves descriptor evidence', array() === $members( $uris[0] ) && 6 === count( axismundi_op_tag_references( $uris[0] ) ) );
} finally {
	foreach ( $posts as $id ) {
		wp_delete_post( $id, true );
	}
	if ( isset( $remote_uri ) ) {
		axismundi_op_delete_remote_object( $remote_uri );
	}
	if ( isset( $next_uri ) ) {
		axismundi_op_delete_remote_object( $next_uri );
	}
	foreach ( $uris as $uri ) {
		foreach ( array( $refs, $usage ) as $table ) {
			$wpdb->delete( $table, array( 'source_uri_hash' => hash( 'sha256', $uri ) ) );
		}
	}
	foreach ( $terms as $id ) {
		wp_delete_term( $id, AXISMUNDI_OP_HASHTAG_TAXONOMY );
	}
	if ( false !== $delete_gate ) {
		add_filter( 'pre_delete_post', 'axismundi_act_pre_delete_post_lifecycle', $delete_gate, 2 );
	}
}
$failed = count( array_filter( $checks, static fn( $ok ) => ! $ok ) );
printf( "\n== %d checks, %d failed ==\n", count( $checks ), $failed );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $failed ? 1 : 0 );
}
