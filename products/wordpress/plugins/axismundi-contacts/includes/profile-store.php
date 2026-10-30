<?php
/** Independent Actor profile documents. These are not JMAP ContactCard records. */
defined( 'ABSPATH' ) || exit;

function axismundi_contacts_profile_documents_table() : string { global $wpdb; return $wpdb->prefix . 'ax_contact_profile_cards'; }

function axismundi_contacts_get_profile_document_row( int $id ) : array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', axismundi_contacts_profile_documents_table(), $id ), ARRAY_A );
	if ( ! $row ) { return array(); }
	$row['storage_kind'] = 'profile';
	return $row;
}

function axismundi_contacts_profile_document( int $id ) : array {
	$row = axismundi_contacts_get_profile_document_row( $id );
	return $row ? (array) json_decode( $row['card_json'], true ) : array();
}

function axismundi_contacts_save_profile_document( int $actor_id, array $document, int $id = 0, ?int $revision = null ) {
	return Axismundi_Contacts_Write::run( static function () use ( $actor_id, $document, $id, $revision ) {
		global $wpdb;
		$actor = axismundi_actors_get_by_identity( $actor_id );
		if ( ! $actor || ! $actor->is_local() ) { return new WP_Error( 'ax_contacts_profile_owner', 'A profile belongs to a local Actor.' ); }
		$valid = axismundi_contacts_validate_card( $document );
		if ( is_wp_error( $valid ) ) { return $valid; }
		$existing = $id ? axismundi_contacts_get_profile_document_row( $id ) : array();
		if ( $id && ( ! $existing || (int) $existing['owner_actor_id'] !== $actor_id ) ) { return new WP_Error( 'ax_contacts_profile_missing', 'Profile not found.', array( 'status' => 404 ) ); }
		if ( $existing && null !== $revision && (int) $existing['revision'] !== $revision ) { return new WP_Error( 'ax_contacts_profile_conflict', 'Profile changed.', array( 'status' => 409 ) ); }
		if ( $existing && isset( $document['uid'] ) && $existing['uid'] !== $document['uid'] ) { return new WP_Error( 'ax_contacts_profile_uid', 'Profile UID is immutable.', array( 'status' => 409 ) ); }
		$document['uid'] = $existing['uid'] ?? ( $document['uid'] ?? 'urn:uuid:' . wp_generate_uuid4() );
		$now = current_time( 'mysql', true );
		$data = array( 'owner_actor_id' => $actor_id, 'uid' => $document['uid'], 'card_json' => wp_json_encode( axismundi_contacts_canonical_card( $document ) ), 'revision' => $existing ? (int) $existing['revision'] + 1 : 1, 'updated_at' => $now );
		if ( $id ) {
			$ok = $wpdb->update( axismundi_contacts_profile_documents_table(), $data, array( 'id' => $id, 'revision' => (int) $existing['revision'] ) );
			if ( 1 !== $ok ) { return new WP_Error( 'ax_contacts_profile_conflict', 'Profile could not be updated.', array( 'status' => 409 ) ); }
		} else {
			$data['created_at'] = $now;
			if ( false === $wpdb->insert( axismundi_contacts_profile_documents_table(), $data ) ) { return new WP_Error( 'ax_contacts_profile_save', 'Profile could not be created.' ); }
			$id = (int) $wpdb->insert_id;
			if ( false === $wpdb->insert( axismundi_contacts_profiles_table(), array( 'actor_id' => $actor_id, 'card_id' => $id, 'sharing_enabled' => 0, 'audience' => 'public', 'created_at' => $now, 'updated_at' => $now ) ) ) { return new WP_Error( 'ax_contacts_profile_bind', 'Profile already exists.' ); }
		}
		return $id;
	} );
}

/** The anchor is an actual membership; the relation table supplies additional memberships. */
function axismundi_contacts_effective_memberships_sql() : string {
	$cards = axismundi_contacts_cards_table(); $extra = axismundi_contacts_memberships_table();
	return "(SELECT primary_address_book_id AS address_book_id,id AS card_id FROM {$cards} UNION SELECT address_book_id,card_id FROM {$extra})";
}

/** Resumable upgrade: copy profiles, retain filed copies, and anchor every remaining ContactCard. */
function axismundi_contacts_install_profile_split() : bool {
	global $wpdb;
	$documents = axismundi_contacts_profile_documents_table(); $charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$documents} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		owner_actor_id bigint(20) unsigned NOT NULL,
		uid varchar(191) NOT NULL,
		card_json longtext NOT NULL,
		provenance_json longtext NULL,
		revision bigint(20) unsigned NOT NULL DEFAULT 1,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY owner_actor_id (owner_actor_id)
	) ENGINE=InnoDB {$charset};" );
	$cards = axismundi_contacts_cards_table(); $bindings = axismundi_contacts_profiles_table();
	$books = axismundi_contacts_books_table(); $members = axismundi_contacts_memberships_table();
	$values = axismundi_contacts_values_table(); $provenance = axismundi_contacts_provenance_table();
	// DDL is outside the data transaction; a failed deployment leaves readiness false for retry.
	$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$cards}" );
	if ( ! in_array( 'primary_address_book_id', $columns, true ) && false === $wpdb->query( "ALTER TABLE {$cards} ADD primary_address_book_id bigint(20) unsigned NULL" ) ) { return false; }
	$result = Axismundi_Contacts_Write::run( static function () use ( $wpdb, $documents, $cards, $bindings, $books, $members, $values, $provenance ) {
		$rows = $wpdb->get_results( "SELECT p.card_id,c.* FROM {$bindings} p INNER JOIN {$cards} c ON c.id=p.card_id LEFT JOIN {$documents} d ON d.id=p.card_id WHERE d.id IS NULL", ARRAY_A );
		foreach ( $rows as $row ) {
			$prov = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$provenance} WHERE card_id=%d", $row['id'] ), ARRAY_A );
			if ( false === $wpdb->insert( $documents, array_intersect_key( $row, array_flip( array( 'id', 'owner_actor_id', 'uid', 'card_json', 'revision', 'created_at', 'updated_at' ) ) ) + array( 'provenance_json' => wp_json_encode( $prov ) ) ) ) { return new WP_Error( 'profile_copy', $wpdb->last_error ); }
			// Only profile-only rows leave the ContactCard store; filed copies remain independent.
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT card_id FROM {$members} WHERE card_id=%d LIMIT 1", $row['id'] ) ) ) {
				foreach ( array( $values, $provenance ) as $table ) { if ( false === $wpdb->delete( $table, array( 'card_id' => $row['id'] ) ) ) { return new WP_Error( 'profile_cleanup', $wpdb->last_error ); } }
				if ( false === $wpdb->delete( $cards, array( 'id' => $row['id'] ) ) ) { return new WP_Error( 'profile_cleanup', $wpdb->last_error ); }
			}
		}
		if ( $wpdb->get_var( "SELECT p.id FROM {$bindings} p LEFT JOIN {$documents} d ON d.id=p.card_id WHERE d.id IS NULL LIMIT 1" ) ) { return new WP_Error( 'profile_missing', 'Profile migration has a missing source.' ); }
		// Existing unfiled ordinary Cards are retained in their owner's default book, not discarded.
		if ( false === $wpdb->query( "INSERT INTO {$books} (owner_actor_id,name,is_default,revision,created_at,updated_at) SELECT DISTINCT c.owner_actor_id,'',1,0,UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM {$cards} c LEFT JOIN {$books} b ON b.owner_actor_id=c.owner_actor_id AND b.is_default=1 WHERE b.id IS NULL" ) ) { return new WP_Error( 'book_recovery', $wpdb->last_error ); }
		if ( false === $wpdb->query( "UPDATE {$cards} c INNER JOIN {$books} b ON b.owner_actor_id=c.owner_actor_id AND b.is_default=1 SET c.primary_address_book_id=COALESCE((SELECT MIN(m.address_book_id) FROM {$members} m INNER JOIN {$books} mb ON mb.id=m.address_book_id AND mb.owner_actor_id=c.owner_actor_id WHERE m.card_id=c.id),b.id) WHERE c.primary_address_book_id IS NULL OR c.primary_address_book_id=0" ) ) { return new WP_Error( 'book_anchor', $wpdb->last_error ); }
		return true;
	} );
	if ( is_wp_error( $result ) ) { return false; }
	$indexes = $wpdb->get_col( "SHOW INDEX FROM {$books}", 2 );
	if ( ! in_array( 'book_owner', $indexes, true ) && false === $wpdb->query( "ALTER TABLE {$books} ADD UNIQUE KEY book_owner (id,owner_actor_id)" ) ) { return false; }
	$fk = $wpdb->get_var( $wpdb->prepare( 'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME=%s AND REFERENCED_TABLE_NAME=%s', $cards, 'primary_address_book_id', $books ) );
	if ( ! $fk && false === $wpdb->query( "ALTER TABLE {$cards} MODIFY primary_address_book_id bigint(20) unsigned NOT NULL, ADD FOREIGN KEY (primary_address_book_id,owner_actor_id) REFERENCES {$books}(id,owner_actor_id) ON DELETE RESTRICT ON UPDATE RESTRICT" ) ) { return false; }
	return true;
}
