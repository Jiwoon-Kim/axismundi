<?php
/** Local signing secrets and rotation history, never part of Actor payloads. @package AxismundiActors */
defined( 'ABSPATH' ) || exit;

function axismundi_actors_signing_keys_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_actor_signing_keys';
}

function axismundi_actors_install_signing_keys() : bool {
	global $wpdb;
	$table = axismundi_actors_signing_keys_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		identity_id bigint(20) unsigned NOT NULL,
		public_key_pem text NOT NULL,
		secret_box longtext DEFAULT NULL,
		fingerprint char(64) NOT NULL,
		previous_fingerprint char(64) DEFAULT NULL,
		previous_provider varchar(24) DEFAULT NULL,
		initiated_by bigint(20) unsigned DEFAULT NULL,
		created_at datetime NOT NULL,
		retired_at datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		KEY identity_history (identity_id, id)
	) ENGINE=InnoDB {$charset};" );
	$columns = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
	$primary = (array) $wpdb->get_col( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'PRIMARY'", $table ), 4 );
	$history = (array) $wpdb->get_col( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'identity_history'", $table ), 4 );
	$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
	return 'InnoDB' === $engine && array( 'id' ) === $primary && array( 'identity_id', 'id' ) === $history
		&& ! array_diff( array( 'id', 'identity_id', 'public_key_pem', 'secret_box', 'fingerprint', 'previous_fingerprint', 'previous_provider', 'initiated_by', 'created_at', 'retired_at' ), $columns );
}

/** A deployment override should be a persistent, independently backed-up base64 32-byte key. */
function axismundi_actors_signing_encryption_key() : string {
	if ( defined( 'AXISMUNDI_ACTORS_KEY_ENCRYPTION_KEY' ) ) {
		$key = base64_decode( (string) AXISMUNDI_ACTORS_KEY_ENCRYPTION_KEY, true );
		return is_string( $key ) && 32 === strlen( $key ) ? $key : '';
	}
	return hash( 'sha256', 'axismundi-actor-signing-v1:' . wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );
}

/** Generate once before acquiring database locks; errors never include key material. */
function axismundi_actors_generate_signing_key() {
	if ( ! function_exists( 'openssl_pkey_new' ) || ! function_exists( 'openssl_encrypt' ) ) {
		return new WP_Error( 'ax_actor_crypto_unavailable', 'OpenSSL is required for Actor keys.' );
	}
	$key = openssl_pkey_new( array( 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA ) );
	$private = '';
	if ( false === $key || ! openssl_pkey_export( $key, $private ) ) {
		return new WP_Error( 'ax_actor_key_generation', 'Actor key generation failed.' );
	}
	$details = openssl_pkey_get_details( $key );
	$encryption_key = axismundi_actors_signing_encryption_key();
	if ( ! is_array( $details ) || '' === $encryption_key ) {
		return new WP_Error( 'ax_actor_key_encryption', 'Actor key encryption is unavailable.' );
	}
	$public = trim( $details['key'] );
	$fingerprint = hash( 'sha256', $public );
	$iv = random_bytes( 12 );
	$tag = '';
	$ciphertext = openssl_encrypt( $private, 'aes-256-gcm', $encryption_key, OPENSSL_RAW_DATA, $iv, $tag, $fingerprint, 16 );
	if ( false === $ciphertext || 16 !== strlen( $tag ) ) {
		return new WP_Error( 'ax_actor_key_encryption', 'Actor key encryption failed.' );
	}
	return array( 'public_key_pem' => $public, 'fingerprint' => $fingerprint, 'secret_box' => 'v1:' . base64_encode( $iv . $tag . $ciphertext ) );
}

/** Caller owns the transaction. The active pointer and event commit together. */
function axismundi_actors_store_signing_key( int $id, array $pair, ?string $previous = null, ?string $provider = null, ?int $user = null ) : bool {
	global $wpdb;
	$old_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT local_signing_key_id FROM %i WHERE identity_id = %d', axismundi_actors_actors_table(), $id ) );
	$now = current_time( 'mysql', true );
	if ( false === $wpdb->insert( axismundi_actors_signing_keys_table(), array_merge( $pair, array( 'identity_id' => $id, 'previous_fingerprint' => $previous, 'previous_provider' => $provider, 'initiated_by' => $user, 'created_at' => $now ) ) ) ) {
		return false;
	}
	$new_id = (int) $wpdb->insert_id;
	if ( 1 !== $wpdb->update( axismundi_actors_actors_table(), array( 'transport_kind' => 'actor', 'local_signing_key_id' => $new_id ), array( 'identity_id' => $id ) ) ) {
		return false;
	}
	return ! $old_id || 1 === $wpdb->update( axismundi_actors_signing_keys_table(), array( 'secret_box' => null, 'retired_at' => $now ), array( 'id' => $old_id, 'identity_id' => $id ) );
}

/** Internal request-only snapshot. Never pass this array to serialization, hooks, or a job spool. */
function axismundi_actors_signing_key_snapshot( Axismundi_Actor $actor ) : ?array {
	$binding = axismundi_actors_transport_binding( $actor );
	if ( ! $binding || 'actor' !== $binding['kind'] || ! function_exists( 'openssl_decrypt' ) || ! axismundi_actors_is_public_profile( $actor ) ) { return null; }
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT k.* FROM %i k INNER JOIN %i a ON a.local_signing_key_id = k.id AND a.identity_id = k.identity_id
		INNER JOIN %i i ON i.id = a.identity_id WHERE a.identity_id = %d AND k.retired_at IS NULL
		AND i.origin = 'local' AND i.status = 'public'", axismundi_actors_signing_keys_table(), axismundi_actors_actors_table(), axismundi_actors_identities_table(), $actor->get_identity_id() ), ARRAY_A );
	if ( ! is_array( $row ) || ! is_string( $row['secret_box'] ) || ! str_starts_with( $row['secret_box'], 'v1:' )
		|| ! hash_equals( $row['fingerprint'], hash( 'sha256', trim( $row['public_key_pem'] ) ) ) ) { return null; }
	$box = base64_decode( substr( $row['secret_box'], 3 ), true );
	$encryption_key = axismundi_actors_signing_encryption_key();
	if ( false === $box || strlen( $box ) <= 28 || '' === $encryption_key ) { return null; }
	$private = openssl_decrypt( substr( $box, 28 ), 'aes-256-gcm', $encryption_key, OPENSSL_RAW_DATA, substr( $box, 0, 12 ), substr( $box, 12, 16 ), $row['fingerprint'] );
	$key = is_string( $private ) ? openssl_pkey_get_private( $private ) : false;
	$details = false !== $key ? openssl_pkey_get_details( $key ) : false;
	if ( ! is_array( $details ) || ! hash_equals( $row['fingerprint'], hash( 'sha256', trim( $details['key'] ) ) ) ) { return null; }
	return array( 'public_key_pem' => $row['public_key_pem'], 'private_key' => $private, 'fingerprint' => $row['fingerprint'] );
}

/** Explicit owner operation with compare-and-swap. No HTTP endpoint is registered here. */
function axismundi_actors_rotate_signing_key( int $id, string $expected_fingerprint ) {
	if ( ! preg_match( '/\A[a-f0-9]{64}\z/D', $expected_fingerprint ) ) { return new WP_Error( 'ax_actor_key_expected', 'A current public key fingerprint is required.' ); }
	$actor = axismundi_actors_get_by_identity( $id );
	$user = get_current_user_id();
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || 'public' !== $actor->get_status()
		|| ! axismundi_actors_can_act_as( $actor, $user ) || 'owner' !== axismundi_actors_account_role( $id, $user ) ) {
		return new WP_Error( 'ax_actor_key_forbidden', 'Only an authorized Account owner can rotate its key.' );
	}
	$pair = axismundi_actors_generate_signing_key();
	if ( is_wp_error( $pair ) ) { return $pair; }
	global $wpdb;
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return new WP_Error( 'ax_actor_key_transaction', 'Key rotation could not start.' ); }
	$committed = false;
	try {
		$locked = $wpdb->get_var( $wpdb->prepare( 'SELECT identity_id FROM %i WHERE identity_id = %d FOR UPDATE', axismundi_actors_actors_table(), $id ) );
		$status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE id = %d FOR UPDATE', axismundi_actors_identities_table(), $id ) );
		$role = $wpdb->get_var( $wpdb->prepare( "SELECT role FROM %i WHERE identity_id = %d AND principal_kind = 'wp_user' AND principal_id = %d FOR UPDATE", axismundi_actors_managers_table(), $id, $user ) );
		$public = axismundi_actors_local_public_key( $actor );
		$binding = axismundi_actors_transport_binding( $actor );
		if ( ! $locked || 'public' !== $status || 'owner' !== $role || ! $binding || ! $public
			|| ! hash_equals( hash( 'sha256', trim( $public['publicKeyPem'] ) ), $expected_fingerprint ) ) {
			return new WP_Error( 'ax_actor_key_conflict', 'The signing key or permission changed; refresh before retrying.' );
		}
		if ( ! axismundi_actors_store_signing_key( $id, $pair, $expected_fingerprint, $binding['kind'], $user ) || false === $wpdb->query( 'COMMIT' ) ) {
			return new WP_Error( 'ax_actor_key_write', 'Key rotation could not commit.' );
		}
		$committed = true;
	} finally {
		if ( ! $committed ) { $wpdb->query( 'ROLLBACK' ); }
	}
	do_action( 'axismundi_actors_signing_key_rotated', $id, $pair['fingerprint'], $expected_fingerprint, $user );
	do_action( 'axismundi_actors_local_actor_profile_updated', axismundi_actors_get_by_identity( $id ) );
	return $pair['fingerprint'];
}
