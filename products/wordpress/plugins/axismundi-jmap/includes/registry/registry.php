<?php
/** Explicit in-process method registration. @package AxismundiJmap */

namespace Axismundi\Jmap;

defined( 'ABSPATH' ) || exit;

final class Registry {

	private array $capabilities = array();
	private array $methods = array();
	private bool $frozen = false;

	public function register_capability( string $uri ) : void {
		if ( $this->frozen || '' === $uri || isset( $this->capabilities[ $uri ] ) ) {
			throw new \LogicException( 'Invalid, duplicate, or frozen capability registration.' );
		}
		$this->capabilities[ $uri ] = true;
	}

	/** Account-bound handlers require a live authorization resolver on every call. */
	public function register_method( string $name, string $capability, callable $handler, bool $account_bound = false, bool $writes = false ) : void {
		if ( $this->frozen || ! isset( $this->capabilities[ $capability ] ) || isset( $this->methods[ $name ] )
			|| 1 !== preg_match( '/\A[A-Za-z][A-Za-z0-9]*\/[A-Za-z][A-Za-z0-9]*\z/D', $name )
			|| ( $writes && ! $account_bound ) ) {
			throw new \LogicException( 'Invalid, duplicate, or frozen method registration.' );
		}
		$this->methods[ $name ] = array(
			'capability' => $capability,
			'handler' => $handler,
			'account_bound' => $account_bound,
			'writes' => $writes,
		);
	}

	public function freeze() : void {
		$this->frozen = true;
	}

	public function supports( string $uri ) : bool {
		return isset( $this->capabilities[ $uri ] );
	}

	public function method( string $name ) : ?array {
		return $this->methods[ $name ] ?? null;
	}
}
