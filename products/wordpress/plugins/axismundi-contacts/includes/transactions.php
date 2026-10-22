<?php
/** Owned writes, nested savepoints and post-commit notifications. @package AxismundiContacts */
defined( 'ABSPATH' ) || exit;

final class Axismundi_Contacts_Write {
	private static array $frames = array();
	private static int $sequence = 0;
	private static bool $unusable = false;

	public static function active() : bool {
		return array() !== self::$frames && ! self::$unusable;
	}

	/** Raw enclosing transactions are refused; callers composing writes must use this runner. */
	public static function run( callable $operation ) {
		global $wpdb;
		if ( self::$unusable ) {
			return self::error();
		}
		$root = array() === self::$frames;
		$name = 'ax_contacts_' . ++self::$sequence;
		if ( $root ) {
			if ( '1' !== (string) $wpdb->get_var( 'SELECT @@session.autocommit' ) ) {
				return self::error();
			}
			// Existing repository pattern: this fails inside a transaction without committing it.
			$previous = $wpdb->suppress_errors( true );
			$ready = $wpdb->query( 'SET TRANSACTION READ WRITE' );
			$wpdb->suppress_errors( $previous );
			if ( false === $ready ) {
				return self::error();
			}
		}
		if ( false === $wpdb->query( $root ? 'START TRANSACTION' : 'SAVEPOINT ' . $name ) ) {
			return self::error();
		}
		self::$frames[] = array();
		try {
			$result = $operation();
			if ( self::$unusable || is_wp_error( $result ) || false === $result ) {
				self::rollback( $root, $name );
				array_pop( self::$frames );
				return is_wp_error( $result ) ? $result : self::error();
			}
			if ( false === $wpdb->query( $root ? 'COMMIT' : 'RELEASE SAVEPOINT ' . $name ) ) {
				self::rollback( $root, $name );
				array_pop( self::$frames );
				return self::error();
			}
		} catch ( Throwable $error ) {
			self::rollback( $root, $name );
			array_pop( self::$frames );
			throw $error;
		}
		$notifications = array_pop( self::$frames );
		if ( $root ) {
			foreach ( $notifications as $notification ) {
				$notification();
			}
		} else {
			$parent = count( self::$frames ) - 1;
			self::$frames[$parent] = array_merge( self::$frames[$parent], $notifications );
		}
		return $result;
	}

	public static function after_commit( callable $notification ) : void {
		if ( array() === self::$frames ) {
			throw new LogicException( 'A Contacts write must own its notification.' );
		}
		self::$frames[count( self::$frames ) - 1][] = $notification;
	}

	private static function rollback( bool $root, string $name ) : void {
		global $wpdb;
		if ( false === $wpdb->query( $root ? 'ROLLBACK' : 'ROLLBACK TO SAVEPOINT ' . $name ) ) {
			self::$unusable = true;
		}
		if ( ! $root ) {
			if ( false === $wpdb->query( 'RELEASE SAVEPOINT ' . $name ) ) {
				self::$unusable = true;
			}
		}
	}

	private static function error() : WP_Error {
		return new WP_Error( 'ax_contacts_transaction', __( 'The contact changes could not be committed.', 'axismundi-contacts' ), array( 'status' => 500 ) );
	}
}
