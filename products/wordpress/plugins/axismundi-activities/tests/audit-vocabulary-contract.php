<?php
/** Dev-only ledger admission, Question routing and Undo contract audit. */
defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks;
};
$snapshot = axismundi_op_vocabulary_snapshot();
$custom = $GLOBALS['axismundi_op_vocabulary_types'];
$mutation = getenv( 'AX_ACTIVITY_VOCAB_MUTATION' ) ?: '';
$ids = array();
$question_uri = '';
$question_id = 0;
$principal_before = $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id, principal_kind, principal_id', ARRAY_A );
$ledger_before = $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_activities_table() . ' ORDER BY id', ARRAY_A );
$question_before = $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_questions_table() . ' ORDER BY identity_id', ARRAY_A );
$record = static function ( array $payload, string $direction = 'outbound' ) use ( &$ids ) {
	$result = axismundi_act_record_activity( $payload, $direction );
	if ( $result instanceof Axismundi_Activity ) { $ids[] = $result->get_id(); }
	return $result;
};
try {
	axismundi_op_load_vocabulary();
	if ( 'exclusion' === $mutation ) {
		$GLOBALS['axismundi_op_vocabulary_snapshot']['types']['IntransitiveActivity']['excludes'] = array();
	} elseif ( 'type' === $mutation ) {
		unset( $GLOBALS['axismundi_op_vocabulary_snapshot']['types']['Arrive'] );
	} elseif ( 'question' === $mutation ) {
		$GLOBALS['axismundi_op_vocabulary_snapshot']['types']['Question']['parents'] = array( 'Activity' );
	}
	$types = axismundi_act_types();
	$assert( in_array( 'Arrive', $types, true ) && in_array( 'Question', $types, true ) && in_array( 'Travel', $types, true ), 'Standard intransitive types come from OP' );
	$assert( ! in_array( 'Person', $types, true ) && ! in_array( 'Note', $types, true ), 'Non-Activity vocabulary types are not ledger types' );
	foreach ( array( 'EmojiReact', 'QuoteRequest' ) as $term ) {
		$definition = axismundi_op_vocabulary_type( $term );
		$declaration = axismundi_op_vocabulary_term( $term );
		$assert( in_array( $term, $types, true ) && 'custom' === $definition['tier'] && 'axismundi-activities' === $declaration['contributor'], $term . ' is owned and registered by Activities' );
	}
	$assert( true === axismundi_op_register_vocabulary_type( 'FixtureAction', array( 'iri' => 'https://example.test/ns#FixtureAction', 'tier' => 'custom', 'category' => 'activity', 'parents' => array( 'Activity' ), 'declares' => array(), 'excludes' => array(), 'provenance' => 'product:fixture' ) ) && in_array( 'FixtureAction', axismundi_act_types(), true ), 'New registered Activity subtypes need no second allow-list' );
	$actor = axismundi_actors_get_site_actor();
	$assert( $actor instanceof Axismundi_Actor, 'Existing local Actor is available without a fixture principal' );
	$base = array( 'actor' => $actor->get_uri() );
	foreach ( array( 'Arrive', 'Travel', 'Question' ) as $term ) {
		$payload = array_merge( $base, array( 'type' => $term, 'name' => 'Vocabulary fixture', 'oneOf' => array( array( 'type' => 'Note', 'name' => 'Yes' ), array( 'type' => 'Note', 'name' => 'No' ) ) ) );
		$activity = $record( $payload );
		$assert( $activity instanceof Axismundi_Activity && null === $activity->get_object_uri(), $term . ' records without object' );
		$assert( $activity->get_payload()['oneOf'] === $payload['oneOf'], $term . ' preserves embedded values' );
		$invalid = axismundi_act_normalize( array_merge( $payload, array( 'object' => 'https://example.test/object' ) ), 'outbound' );
		$assert( is_wp_error( $invalid ) && 'ax_act_object_excluded' === $invalid->get_error_code(), $term . ' rejects an excluded object' );
		if ( 'Question' === $term ) {
			$assert( null === axismundi_act_get_question_by_uri( $activity->get_uri() ), 'Standalone Question is not projected as a FEP poll' );
			$undo = $record( array_merge( $base, array( 'type' => 'Undo', 'object' => $activity->get_uri() ) ) );
			$assert( $undo instanceof Axismundi_Activity && ! axismundi_act_get( $activity->get_uri() )->is_effective(), 'Same-Actor Undo neutralizes standalone Question' );
			$assert( axismundi_act_get( $activity->get_uri() )->get_payload() === $activity->get_payload(), 'Undo preserves the Question evidence' );
			$remote_uri = $wpdb->get_var( 'SELECT canonical_uri FROM ' . axismundi_actors_identities_table() . " WHERE origin = 'remote' LIMIT 1" );
			$assert( is_string( $remote_uri ) && '' !== $remote_uri, 'Existing remote Actor supplies the unauthorized Undo probe' );
			$wrong = $record( array( 'id' => 'https://example.test/undo/' . wp_generate_uuid4(), 'type' => 'Undo', 'actor' => $remote_uri, 'object' => $activity->get_uri() ), 'inbound' );
			$assert( is_wp_error( $wrong ) && 'ax_act_undo_actor' === $wrong->get_error_code(), 'Different Actor cannot Undo Question' );
		}
	}
	$missing = axismundi_act_normalize( array_merge( $base, array( 'type' => 'Like' ) ), 'outbound' );
	$assert( is_wp_error( $missing ) && 'ax_act_object' === $missing->get_error_code(), 'Transitive admission still requires a resolvable object' );
	$created = axismundi_act_create_question( $actor, array( 'mode' => 'oneOf', 'options' => array( 'Yes', 'No' ) ) );
	if ( $created instanceof Axismundi_Activity ) {
		$ids[] = $created->get_id();
		$question_uri = axismundi_act_member_uri( $created->get_payload()['object'] );
		$question = axismundi_act_get_question_by_uri( $question_uri );
		$question_id = (int) ( $question['identity_id'] ?? 0 );
	}
	$assert( $created instanceof Axismundi_Activity && 'Create' === $created->get_type() && $question_id > 0, 'Create(Question) records Create and projects its embedded Question' );
	$assert( null === axismundi_act_get( $question_uri ), 'Embedded Question is not a second standalone ledger Activity' );
} finally {
	foreach ( array_unique( $ids ) as $id ) {
		$wpdb->delete( axismundi_act_inbox_table(), array( 'activity_id' => $id ) );
		$wpdb->delete( axismundi_act_activities_table(), array( 'id' => $id ) );
	}
	if ( $question_id > 0 ) {
		$wpdb->delete( axismundi_act_question_votes_table(), array( 'question_identity_id' => $question_id ) );
		$wpdb->delete( axismundi_act_questions_table(), array( 'identity_id' => $question_id ) );
		$wpdb->delete( axismundi_op_objects_table(), array( 'source_owner' => 'axismundi-activities', 'source_kind' => 'question-ledger', 'source_ref' => (string) $question_id ) );
	}
	$GLOBALS['axismundi_op_vocabulary_snapshot'] = $snapshot;
	$GLOBALS['axismundi_op_vocabulary_types'] = $custom;
}
$assert( $ledger_before === $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_activities_table() . ' ORDER BY id', ARRAY_A ), 'Pre-existing ledger rows are unchanged' );
$assert( $question_before === $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_questions_table() . ' ORDER BY identity_id', ARRAY_A ), 'Pre-existing Question state is unchanged' );
$assert( $principal_before === $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id, principal_kind, principal_id', ARRAY_A ), 'Principal rows are unchanged' );
printf( "PASS: %d Activity vocabulary contract checks (%d pre-existing ledger rows preserved)\n", $checks, count( $ledger_before ) );
