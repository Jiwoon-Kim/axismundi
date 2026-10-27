<?php
/**
 * FEP-9967 Question state projected from the immutable Activity ledger.
 *
 * A Question is published in Create and its results arrive in Update. Votes are
 * content-less Create(Note) activities addressed to the Question's author. The
 * activity rows remain the evidence; these tables hold only the current Poll
 * state and its queryable vote projection, never a feed.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** @return string Question state table. */
function axismundi_act_questions_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_activity_questions';
}

/** @return string Vote projection table. */
function axismundi_act_question_votes_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_activity_question_votes';
}

/** Install the Activities-owned durable Question state. */
function axismundi_act_install_questions_schema() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$questions = axismundi_act_questions_table();
	$votes     = axismundi_act_question_votes_table();
	$charset   = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$questions} (
			identity_id bigint(20) unsigned NOT NULL,
			author_actor_uri text NOT NULL,
			author_actor_uri_hash char(64) NOT NULL,
			author_identity_id bigint(20) unsigned DEFAULT NULL,
			authority_origin varchar(8) NOT NULL,
			mode varchar(8) NOT NULL,
			options_json longtext NOT NULL,
			option_counts_json longtext NOT NULL,
			votes_count bigint(20) unsigned NOT NULL DEFAULT 0,
			voters_count bigint(20) unsigned NOT NULL DEFAULT 0,
			end_time datetime DEFAULT NULL,
			closed_at datetime DEFAULT NULL,
			question_updated_at datetime DEFAULT NULL,
			lock_version bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (identity_id),
			KEY author_actor_uri_hash (author_actor_uri_hash),
			KEY authority_origin (authority_origin)
		) ENGINE=InnoDB {$charset};\n\n"
		. "CREATE TABLE {$votes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			question_identity_id bigint(20) unsigned NOT NULL,
			voter_actor_uri text NOT NULL,
			voter_actor_uri_hash char(64) NOT NULL,
			voter_identity_id bigint(20) unsigned DEFAULT NULL,
			choice_position smallint(5) unsigned DEFAULT NULL,
			vote_activity_uri text NOT NULL,
			vote_activity_uri_hash char(64) NOT NULL,
			vote_object_uri text DEFAULT NULL,
			vote_object_uri_hash char(64) DEFAULT NULL,
			vote_status varchar(16) NOT NULL,
			ignore_reason varchar(64) DEFAULT NULL,
			origin varchar(8) NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY vote_activity_uri_hash (vote_activity_uri_hash),
			KEY question_status (question_identity_id, vote_status),
			KEY question_voter_status (question_identity_id, voter_actor_uri_hash, vote_status),
			KEY question_choice_status (question_identity_id, choice_position, vote_status),
			KEY vote_object_uri_hash (vote_object_uri_hash)
		) ENGINE=InnoDB {$charset};"
	);
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom schema verification.
	$question_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$questions}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom schema verification.
	$vote_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$votes}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom schema verification.
	$vote_index = (array) $wpdb->get_results( "SHOW INDEX FROM {$votes} WHERE Key_name = 'vote_activity_uri_hash'", ARRAY_A );
	return in_array( 'options_json', $question_columns, true )
		&& in_array( 'option_counts_json', $question_columns, true )
		&& in_array( 'lock_version', $question_columns, true )
		&& in_array( 'voter_actor_uri', $vote_columns, true )
		&& in_array( 'choice_position', $vote_columns, true )
		&& in_array( 'ignore_reason', $vote_columns, true )
		&& ! empty( $vote_index )
		&& 0 === (int) $vote_index[0]['Non_unique'];
}

/** @return array<string,mixed>|null */
function axismundi_act_get_question( int $identity_id ) : ?array {
	if ( $identity_id <= 0 ) {
		return null;
	}
	global $wpdb;
	$table = axismundi_act_questions_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact durable Question lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE identity_id = %d", $identity_id ), ARRAY_A );
	if ( ! is_array( $row ) ) {
		return null;
	}
	$row['options']       = json_decode( (string) $row['options_json'], true );
	$row['option_counts'] = json_decode( (string) $row['option_counts_json'], true );
	$row['options']       = is_array( $row['options'] ) ? array_values( $row['options'] ) : array();
	$row['option_counts'] = is_array( $row['option_counts'] ) ? array_values( array_map( 'intval', $row['option_counts'] ) ) : array();
	return $row;
}

/** @return array<string,mixed>|null */
function axismundi_act_get_question_by_uri( string $uri ) : ?array {
	if ( ! function_exists( 'axismundi_actors_get_identity_by_uri' ) ) {
		return null;
	}
	$identity = axismundi_actors_get_identity_by_uri( $uri );
	return is_array( $identity ) ? axismundi_act_get_question( (int) $identity['identity_id'] ) : null;
}

/** @return string[]|WP_Error */
function axismundi_act_question_options( $value ) {
	if ( ! is_array( $value ) || ! array_is_list( $value ) || count( $value ) < 2 ) {
		return new WP_Error( 'ax_act_question_options', __( 'A Question needs at least two ordered options.', 'axismundi-activities' ) );
	}
	$options = array();
	foreach ( $value as $option ) {
		$name = is_array( $option ) ? ( $option['name'] ?? null ) : $option;
		if ( ! is_string( $name ) || '' === trim( $name ) || 200 < strlen( $name ) ) {
			return new WP_Error( 'ax_act_question_option', __( 'Each Question option needs a short non-empty name.', 'axismundi-activities' ) );
		}
		$options[] = $name;
	}
	if ( count( $options ) !== count( array_unique( $options, SORT_STRING ) ) ) {
		return new WP_Error( 'ax_act_question_option_unique', __( 'Question option names must be unique within the poll.', 'axismundi-activities' ) );
	}
	return $options;
}

/** @return array<string,mixed>|WP_Error */
function axismundi_act_question_object( array $object ) {
	if ( 'Question' !== (string) ( $object['type'] ?? '' ) ) {
		return new WP_Error( 'ax_act_question_type', __( 'The embedded object is not a Question.', 'axismundi-activities' ) );
	}
	$uri = axismundi_act_member_uri( $object['id'] ?? '' );
	if ( '' === $uri ) {
		return new WP_Error( 'ax_act_question_id', __( 'A Question needs an object id.', 'axismundi-activities' ) );
	}
	$has_one = array_key_exists( 'oneOf', $object );
	$has_any = array_key_exists( 'anyOf', $object );
	if ( $has_one === $has_any ) {
		return new WP_Error( 'ax_act_question_mode', __( 'A Question needs exactly one of oneOf or anyOf.', 'axismundi-activities' ) );
	}
	$options = axismundi_act_question_options( $object[ $has_one ? 'oneOf' : 'anyOf' ] );
	if ( is_wp_error( $options ) ) {
		return $options;
	}
	$counts = array();
	foreach ( (array) $object[ $has_one ? 'oneOf' : 'anyOf' ] as $option ) {
		$count = is_array( $option ) ? (int) ( $option['replies']['totalItems'] ?? 0 ) : 0;
		if ( $count < 0 ) {
			return new WP_Error( 'ax_act_question_tally', __( 'Question tallies cannot be negative.', 'axismundi-activities' ) );
		}
		$counts[] = $count;
	}
	$voters = isset( $object['votersCount'] ) ? (int) $object['votersCount'] : 0;
	if ( $voters < 0 ) {
		return new WP_Error( 'ax_act_question_voters', __( 'Question voter count cannot be negative.', 'axismundi-activities' ) );
	}
	$author = axismundi_act_member_uri( $object['attributedTo'] ?? '' );
	if ( '' === $author ) {
		return new WP_Error( 'ax_act_question_author', __( 'A Question needs attributedTo.', 'axismundi-activities' ) );
	}
	return array(
		'uri'          => $uri,
		'author_uri'   => $author,
		'mode'         => $has_one ? 'oneOf' : 'anyOf',
		'options'      => $options,
		'option_counts'=> $counts,
		'votes_count'  => array_sum( $counts ),
		'voters_count' => $voters,
		'end_time'     => axismundi_act_datetime( $object['endTime'] ?? '' ),
		'closed_at'    => axismundi_act_datetime( $object['closed'] ?? '' ),
		'updated_at'   => axismundi_act_datetime( $object['updated'] ?? '' ),
	);
}

/** Register the shared identity behind one Question URI. */
function axismundi_act_question_identity( string $uri, string $origin ) {
	if ( ! function_exists( 'axismundi_actors_get_identity_by_uri' ) || ! function_exists( 'axismundi_actors_register_identity' ) ) {
		return new WP_Error( 'ax_act_question_identity_registry', __( 'The shared identity registry is unavailable.', 'axismundi-activities' ) );
	}
	$identity = axismundi_actors_get_identity_by_uri( $uri );
	if ( null === $identity ) {
		$identity = axismundi_actors_register_identity(
			array(
				'object_kind'   => 'object',
				'origin'        => $origin,
				'status'        => 'public',
				'canonical_uri' => $uri,
			)
		);
	}
	if ( is_wp_error( $identity ) ) {
		return $identity;
	}
	if ( ! is_array( $identity ) || 'object' !== (string) $identity['object_kind'] ) {
		return new WP_Error( 'ax_act_question_identity_kind', __( 'The Question URI is already owned by a non-Object identity.', 'axismundi-activities' ) );
	}
	return $identity;
}

/** @return int|null */
function axismundi_act_question_actor_identity( string $uri ) : ?int {
	if ( ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
		return null;
	}
	$actor = axismundi_actors_get_by_uri( $uri );
	return $actor instanceof Axismundi_Actor ? $actor->get_identity_id() : null;
}

/** @return string|WP_Error */
function axismundi_act_question_local_uri() {
	$base = trailingslashit( home_url( '/questions/' ) );
	$uri  = $base . wp_generate_uuid4() . '/';
	return '' !== axismundi_act_uri( $uri ) ? $uri : new WP_Error( 'ax_act_question_uri', __( 'Could not allocate a local Question URI.', 'axismundi-activities' ) );
}

/** @return array<int,array<string,mixed>> */
function axismundi_act_question_option_members( array $options, array $counts = array() ) : array {
	$members = array();
	foreach ( $options as $position => $name ) {
		$members[] = array(
			'type'    => 'Note',
			'name'    => $name,
			'replies' => array( 'type' => 'Collection', 'totalItems' => max( 0, (int) ( $counts[ $position ] ?? 0 ) ) ),
		);
	}
	return $members;
}

/**
 * Author a local Question as Create(Question), without a post or draft object.
 *
 * The frontend C2S adapter authenticates the WordPress user before choosing the
 * local acting Actor. This service owns only the ActivityStreams event.
 *
 * @return Axismundi_Activity|WP_Error
 */
function axismundi_act_create_question( Axismundi_Actor $actor, array $input ) {
	if ( ! $actor->is_local() ) {
		return new WP_Error( 'ax_act_question_actor', __( 'Only a local Actor can author a local Question.', 'axismundi-activities' ) );
	}
	$mode = (string) ( $input['mode'] ?? '' );
	if ( ! in_array( $mode, array( 'oneOf', 'anyOf' ), true ) ) {
		return new WP_Error( 'ax_act_question_mode', __( 'A Question must choose oneOf or anyOf.', 'axismundi-activities' ) );
	}
	$options = axismundi_act_question_options( $input['options'] ?? null );
	if ( is_wp_error( $options ) ) {
		return $options;
	}
	$uri = axismundi_act_question_local_uri();
	if ( is_wp_error( $uri ) ) {
		return $uri;
	}
	$object = array(
		'id'           => $uri,
		'type'         => 'Question',
		'attributedTo' => $actor->get_uri(),
		$mode          => axismundi_act_question_option_members( $options ),
		'votersCount'  => 0,
	);
	foreach ( array( 'content', 'summary', 'endTime', 'to', 'cc', 'tag', 'attachment' ) as $property ) {
		if ( array_key_exists( $property, $input ) ) {
			$object[ $property ] = $input[ $property ];
		}
	}
	$payload = array(
		'type'   => 'Create',
		'actor'  => $actor->get_uri(),
		'object' => $object,
	);
	foreach ( array( 'to', 'cc', 'bto', 'bcc', 'audience' ) as $property ) {
		if ( array_key_exists( $property, $input ) ) {
			$payload[ $property ] = $input[ $property ];
		}
	}
	return axismundi_act_record_activity( $payload, 'outbound' );
}

/**
 * Author one local FEP-9967 Vote Create(Note) directly from the ledger.
 *
 * @return Axismundi_Activity|WP_Error
 */
function axismundi_act_vote_question( Axismundi_Actor $actor, string $question_uri, int $choice_position ) {
	if ( ! $actor->is_local() ) {
		return new WP_Error( 'ax_act_question_actor', __( 'Only a local Actor can submit a local vote.', 'axismundi-activities' ) );
	}
	$question = axismundi_act_get_question_by_uri( $question_uri );
	if ( ! is_array( $question ) || ! isset( $question['options'][ $choice_position ] ) ) {
		return new WP_Error( 'ax_act_question_choice', __( 'That Question choice does not exist.', 'axismundi-activities' ) );
	}
	if ( ! empty( $question['closed_at'] ) || ( ! empty( $question['end_time'] ) && strtotime( (string) $question['end_time'] . ' UTC' ) <= time() ) ) {
		return new WP_Error( 'ax_act_question_closed', __( 'That Question is closed.', 'axismundi-activities' ) );
	}
	$object = array(
		'id'           => trailingslashit( home_url( '/votes/' ) ) . wp_generate_uuid4() . '/',
		'type'         => 'Note',
		'attributedTo' => $actor->get_uri(),
		'inReplyTo'    => $question_uri,
		'name'         => (string) $question['options'][ $choice_position ],
	);
	return axismundi_act_record_activity(
		array(
			'type'   => 'Create',
			'actor'  => $actor->get_uri(),
			'object' => $object,
			'to'     => array( (string) $question['author_actor_uri'] ),
		),
		'outbound'
	);
}

/** Return true when an Update changes the immutable Poll definition. */
function axismundi_act_question_definition_changed( array $question, array $next ) : bool {
	return (string) $question['mode'] !== (string) $next['mode']
		|| (array) $question['options'] !== (array) $next['options'];
}

/** Save the current Question snapshot after its Create or Update has reached the ledger. */
function axismundi_act_question_apply_snapshot( Axismundi_Activity $activity, array $question ) : void {
	global $wpdb;
	$origin   = 'inbound' === $activity->get_direction() ? 'remote' : 'local';
	$identity = axismundi_act_question_identity( (string) $question['uri'], $origin );
	if ( is_wp_error( $identity ) || ! is_array( $identity ) ) {
		return;
	}
	$table    = axismundi_act_questions_table();
	$existing = axismundi_act_get_question( (int) $identity['identity_id'] );
	$now      = current_time( 'mysql', true );
	if ( is_array( $existing ) && ! hash_equals( (string) $existing['author_actor_uri'], (string) $question['author_uri'] ) ) {
		return;
	}
	$definition_changed = is_array( $existing ) && axismundi_act_question_definition_changed( $existing, $question );
	if ( $definition_changed && ( 0 !== (int) $question['votes_count'] || 0 !== (int) $question['voters_count'] ) ) {
		return;
	}
	/*
	 * A local poll owns its tally through question_votes. An incoming replay of
	 * its Update must agree with that projection, never replace it.
	 */
	if (
		is_array( $existing )
		&& 'local' === (string) $existing['authority_origin']
		&& ! $definition_changed
		&& (
			(array) $existing['option_counts'] !== (array) $question['option_counts']
			|| (int) $existing['votes_count'] !== (int) $question['votes_count']
			|| (int) $existing['voters_count'] !== (int) $question['voters_count']
		)
	) {
		return;
	}
	$author_identity = axismundi_act_question_actor_identity( (string) $question['author_uri'] );
	$data = array(
		'author_actor_uri'      => $question['author_uri'],
		'author_actor_uri_hash' => hash( 'sha256', (string) $question['author_uri'] ),
		'author_identity_id'    => $author_identity,
		'authority_origin'      => $origin,
		'mode'                  => $question['mode'],
		'options_json'          => wp_json_encode( $question['options'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
		'option_counts_json'    => wp_json_encode( $question['option_counts'] ),
		'votes_count'           => $question['votes_count'],
		'voters_count'          => $question['voters_count'],
		'end_time'               => $question['end_time'],
		'closed_at'             => $question['closed_at'],
		'question_updated_at'   => $question['updated_at'],
		'updated_at'            => $now,
	);
	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Question snapshot and vote reset are one state change.
	if ( $definition_changed ) {
		$wpdb->update( axismundi_act_question_votes_table(), array( 'vote_status' => 'reset', 'updated_at' => $now ), array( 'question_identity_id' => (int) $identity['identity_id'], 'vote_status' => 'active' ), array( '%s', '%s' ), array( '%d', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- FEP-9967 option edit retires prior projection rows.
	}
	$ok = is_array( $existing )
		? false !== $wpdb->update( $table, $data, array( 'identity_id' => (int) $identity['identity_id'] ) )
		: false !== $wpdb->insert( $table, array_merge( $data, array( 'identity_id' => (int) $identity['identity_id'], 'lock_version' => 0, 'created_at' => $now ) ) );
	if ( $ok ) {
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomically retain only complete snapshot changes.
	} else {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- discard an incomplete snapshot.
	}
	if ( $ok ) {
		/*
		 * Object Projections may register this durable source in its optional
		 * vertical slice. Activities keeps working when that plugin is absent.
		 */
		do_action( 'axismundi_act_question_projected', $question, $activity );
	}
}

/** Whether a value carries visible Vote Note content. */
function axismundi_act_question_has_content( $value ) : bool {
	if ( is_array( $value ) ) {
		foreach ( $value as $member ) {
			if ( axismundi_act_question_has_content( $member ) ) {
				return true;
			}
		}
		return false;
	}
	return is_scalar( $value ) && '' !== trim( wp_strip_all_tags( (string) $value ) );
}

/** @return array<string,mixed>|null A Vote candidate, including classification failure reason. */
function axismundi_act_question_vote_candidate( Axismundi_Activity $activity ) : ?array {
	if ( 'Create' !== $activity->get_type() || ! $activity->is_effective() ) {
		return null;
	}
	$payload = $activity->get_payload();
	$object  = $payload['object'] ?? null;
	if ( ! is_array( $object ) || 'Note' !== (string) ( $object['type'] ?? '' ) ) {
		return null;
	}
	$question_uri = axismundi_act_member_uri( $object['inReplyTo'] ?? '' );
	$question     = '' !== $question_uri ? axismundi_act_get_question_by_uri( $question_uri ) : null;
	if ( ! is_array( $question ) ) {
		return null;
	}
	$actor_uri   = $activity->get_actor_uri();
	$author_uri  = axismundi_act_member_uri( $object['attributedTo'] ?? '' );
	$object_uri  = axismundi_act_member_uri( $object['id'] ?? '' );
	$name        = $object['name'] ?? null;
	$reason      = null;
	$position    = null;
	if ( '' === $object_uri || ! is_string( $name ) || '' === $name || ! hash_equals( $actor_uri, $author_uri ) ) {
		$reason = 'malformed';
	} elseif ( axismundi_act_question_has_content( $object['content'] ?? '' ) || axismundi_act_question_has_content( $object['contentMap'] ?? '' ) ) {
		$reason = 'content';
	} elseif ( ! empty( $object['attachment'] ) ) {
		$reason = 'attachment';
	} elseif ( ! in_array( (string) $question['author_actor_uri'], (array) ( axismundi_act_audience( $payload )['to'] ?? array() ), true ) ) {
		$reason = 'not_addressed';
	} else {
		$found = array_search( $name, (array) $question['options'], true );
		if ( false === $found ) {
			$reason = 'unknown_option';
		} else {
			$position = (int) $found;
			$ended    = ! empty( $question['closed_at'] ) || ( ! empty( $question['end_time'] ) && strtotime( (string) $question['end_time'] . ' UTC' ) <= time() );
			if ( $ended ) {
				$reason = 'closed';
			}
		}
	}
	return array(
		'question'        => $question,
		'actor_uri'       => $actor_uri,
		'choice_position' => $position,
		'vote_activity_uri'=> $activity->get_uri(),
		'vote_object_uri' => $object_uri,
		'origin'          => 'inbound' === $activity->get_direction() ? 'inbound' : 'local',
		'ignore_reason'   => $reason,
	);
}

/** Rebuild local authoritative counters from active Vote rows while holding the Question lock. */
function axismundi_act_question_recount_locked( array $question ) : bool {
	if ( 'local' !== (string) $question['authority_origin'] ) {
		return true;
	}
	global $wpdb;
	$votes = axismundi_act_question_votes_table();
	$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT choice_position, COUNT(*) AS tally FROM {$votes} WHERE question_identity_id = %d AND vote_status = 'active' GROUP BY choice_position", (int) $question['identity_id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded grouped tally for one locked Question.
	$counts = array_fill( 0, count( (array) $question['options'] ), 0 );
	foreach ( $rows as $row ) {
		$position = (int) $row['choice_position'];
		if ( isset( $counts[ $position ] ) ) {
			$counts[ $position ] = (int) $row['tally'];
		}
	}
	$voters = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT voter_actor_uri_hash) FROM {$votes} WHERE question_identity_id = %d AND vote_status = 'active'", (int) $question['identity_id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact local-authority voter count.
	return false !== $wpdb->update(
		axismundi_act_questions_table(),
		array(
			'option_counts_json' => wp_json_encode( $counts ),
			'votes_count'        => array_sum( $counts ),
			'voters_count'       => $voters,
			'lock_version'       => (int) $question['lock_version'] + 1,
			'updated_at'         => current_time( 'mysql', true ),
		),
		array( 'identity_id' => (int) $question['identity_id'], 'lock_version' => (int) $question['lock_version'] ),
		array( '%s', '%d', '%d', '%d', '%s' ),
		array( '%d', '%d' )
	);
}

/** Persist one Vote classification and refresh local authoritative counters. */
function axismundi_act_question_record_vote( array $candidate ) : void {
	global $wpdb;
	$question_id = (int) ( $candidate['question']['identity_id'] ?? 0 );
	if ( $question_id <= 0 ) {
		return;
	}
	$questions = axismundi_act_questions_table();
	$votes     = axismundi_act_question_votes_table();
	$activity_uri = (string) $candidate['vote_activity_uri'];
	$actor_uri    = (string) $candidate['actor_uri'];
	$now          = current_time( 'mysql', true );
	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- serialize deduplication and local tally updates.
	$question = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$questions} WHERE identity_id = %d FOR UPDATE", $question_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one locked durable Question.
	if ( ! is_array( $question ) ) {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return;
	}
	$question['options'] = json_decode( (string) $question['options_json'], true );
	$question['options'] = is_array( $question['options'] ) ? array_values( $question['options'] ) : array();
	$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$votes} WHERE vote_activity_uri_hash = %s AND vote_activity_uri = %s", hash( 'sha256', $activity_uri ), $activity_uri ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact immutable activity idempotency.
	if ( null !== $existing ) {
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return;
	}
	$status = empty( $candidate['ignore_reason'] ) ? 'active' : 'ignored';
	if ( 'active' === $status ) {
		$one_of = 'oneOf' === (string) $question['mode'];
		$sql = $one_of
			? $wpdb->prepare( "SELECT id FROM {$votes} WHERE question_identity_id = %d AND voter_actor_uri_hash = %s AND voter_actor_uri = %s AND vote_status = 'active' LIMIT 1", $question_id, hash( 'sha256', $actor_uri ), $actor_uri )
			: $wpdb->prepare( "SELECT id FROM {$votes} WHERE question_identity_id = %d AND voter_actor_uri_hash = %s AND voter_actor_uri = %s AND choice_position = %d AND vote_status = 'active' LIMIT 1", $question_id, hash( 'sha256', $actor_uri ), $actor_uri, (int) $candidate['choice_position'] );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one of two fully prepared deduplication queries.
		if ( null !== $wpdb->get_var( $sql ) ) {
			$status = 'ignored';
			$candidate['ignore_reason'] = 'duplicate';
		}
	}
	$identity_id = axismundi_act_question_actor_identity( $actor_uri );
	$ok = false !== $wpdb->insert(
		$votes,
		array(
			'question_identity_id'  => $question_id,
			'voter_actor_uri'       => $actor_uri,
			'voter_actor_uri_hash'  => hash( 'sha256', $actor_uri ),
			'voter_identity_id'     => $identity_id,
			'choice_position'       => null === $candidate['choice_position'] ? null : (int) $candidate['choice_position'],
			'vote_activity_uri'     => $activity_uri,
			'vote_activity_uri_hash'=> hash( 'sha256', $activity_uri ),
			'vote_object_uri'       => '' !== (string) $candidate['vote_object_uri'] ? $candidate['vote_object_uri'] : null,
			'vote_object_uri_hash'  => '' !== (string) $candidate['vote_object_uri'] ? hash( 'sha256', (string) $candidate['vote_object_uri'] ) : null,
			'vote_status'           => $status,
			'ignore_reason'         => $candidate['ignore_reason'],
			'origin'                => $candidate['origin'],
			'created_at'            => $now,
			'updated_at'            => $now,
		),
		array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- durable vote projection from immutable evidence.
	$ok = $ok && axismundi_act_question_recount_locked( $question );
	if ( $ok ) {
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- commit the vote and its tally together.
	} else {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- discard an incomplete vote change.
	}
}

/** Mark a Vote projection inactive when its supporting Activity or Note is withdrawn. */
function axismundi_act_question_mark_votes( string $field, string $uri, string $actor_uri, string $status ) : void {
	if ( ! in_array( $field, array( 'vote_activity_uri', 'vote_object_uri' ), true ) || ! in_array( $status, array( 'undone', 'deleted' ), true ) || '' === $uri || '' === $actor_uri ) {
		return;
	}
	global $wpdb;
	$votes = axismundi_act_question_votes_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- allowlisted relation reference and exact URI comparison.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT question_identity_id FROM {$votes} WHERE {$field}_hash = %s AND {$field} = %s AND voter_actor_uri_hash = %s AND voter_actor_uri = %s AND vote_status = 'active'", hash( 'sha256', $uri ), $uri, hash( 'sha256', $actor_uri ), $actor_uri ), ARRAY_A );
	if ( empty( $rows ) ) {
		return;
	}
	$wpdb->update( $votes, array( 'vote_status' => $status, 'updated_at' => current_time( 'mysql', true ) ), array( $field . '_hash' => hash( 'sha256', $uri ), $field => $uri, 'voter_actor_uri_hash' => hash( 'sha256', $actor_uri ), 'voter_actor_uri' => $actor_uri, 'vote_status' => 'active' ), array( '%s', '%s' ), array( '%s', '%s', '%s', '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- supporting evidence no longer stands.
	foreach ( $rows as $row ) {
		$question = axismundi_act_get_question( (int) $row['question_identity_id'] );
		if ( is_array( $question ) && 'local' === (string) $question['authority_origin'] ) {
			$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$locked = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . axismundi_act_questions_table() . " WHERE identity_id = %d FOR UPDATE", (int) $row['question_identity_id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed table with an exact Question lock.
			if ( is_array( $locked ) ) {
				$locked['options'] = json_decode( (string) $locked['options_json'], true );
				$locked['options'] = is_array( $locked['options'] ) ? array_values( $locked['options'] ) : array();
				axismundi_act_question_recount_locked( $locked );
			}
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}
}

/** Exclude structurally valid Question votes from generic reply projections. */
function axismundi_act_exclude_question_vote_reply( bool $include, string $child_uri ) : bool {
	if ( ! $include || '' === $child_uri ) {
		return $include;
	}
	global $wpdb;
	$votes = axismundi_act_question_votes_table();
	$row   = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT vote_status, ignore_reason FROM {$votes} WHERE vote_object_uri_hash = %s AND vote_object_uri = %s LIMIT 1",
			hash( 'sha256', $child_uri ),
			$child_uri
		),
		ARRAY_A
	); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact vote-object lookup for a shared read-time projection filter.
	if ( ! is_array( $row ) ) {
		return true;
	}
	if ( in_array( (string) $row['vote_status'], array( 'active', 'undone', 'deleted', 'reset' ), true ) ) {
		return false;
	}
	return 'ignored' === (string) $row['vote_status'] && in_array( (string) $row['ignore_reason'], array( 'duplicate', 'closed' ), true ) ? false : true;
}
add_filter( 'axismundi_op_thread_include_reply', 'axismundi_act_exclude_question_vote_reply', 10, 2 );

/** Project FEP-9967 Question and Vote activities after immutable ledger commit. */
function axismundi_act_sync_question_activity( Axismundi_Activity $activity ) : void {
	$payload = $activity->get_payload();
	$object  = $payload['object'] ?? null;
	if ( in_array( $activity->get_type(), array( 'Create', 'Update' ), true ) && is_array( $object ) && 'Question' === (string) ( $object['type'] ?? '' ) ) {
		$question = axismundi_act_question_object( $object );
		if ( is_array( $question ) && hash_equals( $activity->get_actor_uri(), (string) $question['author_uri'] ) ) {
			axismundi_act_question_apply_snapshot( $activity, $question );
		}
		return;
	}
	if ( 'Create' === $activity->get_type() ) {
		$candidate = axismundi_act_question_vote_candidate( $activity );
		if ( is_array( $candidate ) ) {
			axismundi_act_question_record_vote( $candidate );
		}
		return;
	}
	if ( 'Undo' === $activity->get_type() ) {
		axismundi_act_question_mark_votes( 'vote_activity_uri', (string) $activity->get_object_uri(), $activity->get_actor_uri(), 'undone' );
	}
	if ( 'Delete' === $activity->get_type() ) {
		axismundi_act_question_mark_votes( 'vote_object_uri', (string) $activity->get_object_uri(), $activity->get_actor_uri(), 'deleted' );
	}
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_act_sync_question_activity', 20 );
