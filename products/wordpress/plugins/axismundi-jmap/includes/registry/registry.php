<?php
/** Explicit in-process method registration. @package AxismundiJmap */

namespace Axismundi\Jmap;

defined( 'ABSPATH' ) || exit;

final class Registry {

	private array $capabilities = array();
	private array $methods = array();
	private array $types = array();
	private array $account_providers = array();
	private bool $frozen = false;

	public function register_capability( string $uri ) : void {
		if ( $this->frozen || strlen( $uri ) > 255 || ! preg_match( '/\A[A-Za-z][A-Za-z0-9+.-]*:[\x21-\x7e]+\z/D', $uri ) || isset( $this->capabilities[ $uri ] ) ) {
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

	/** Type ownership is independent of implemented methods and Account capability grants. */
	public function register_type( string $name, string $capability, string $reference ) : void {
		if ( $this->frozen || ! $this->supports( $capability ) || isset( $this->types[ $name ] )
			|| 1 !== preg_match( '/\A[A-Za-z][A-Za-z0-9]*\z/D', $name )
			|| ! filter_var( $reference, FILTER_VALIDATE_URL ) || 'https' !== parse_url( $reference, PHP_URL_SCHEME ) ) {
			throw new \LogicException( 'Invalid, duplicate, or frozen data type registration.' );
		}
		$this->types[ $name ] = array( 'capability' => $capability, 'reference' => $reference );
	}

	public function type( string $name ) : ?array { return $this->types[ $name ] ?? null; }

	/** Explicit server declaration plus a live, User-relative Account grant provider. */
	public function register_account_provider( string $capability, \stdClass $server, callable $provider, string $purpose, ?callable $validate_policy = null ) : void {
		if ( $this->frozen || ! $this->supports( $capability ) || CORE === $capability
			|| isset( $this->account_providers[ $capability ] )
			|| 1 !== preg_match( '/\A[a-z][a-z0-9_-]{0,63}\z/D', $purpose ) ) {
			throw new \LogicException( 'Invalid, duplicate, or frozen Account provider.' );
		}
		$has_method = false;
		foreach ( $this->methods as $method ) {
			$has_method = $has_method || ( $method['capability'] === $capability && $method['account_bound'] );
		}
		if ( ! $has_method ) {
			throw new \LogicException( 'An Account provider requires a registered Account-bound method.' );
		}
		$this->account_providers[ $capability ] = array(
			'server' => copy_json( $server ), 'provider' => \Closure::fromCallable( $provider ), 'purpose' => $purpose,
			'validate_policy' => null === $validate_policy ? static fn( \stdClass $limits ) : bool => array() === get_object_vars( $limits ) : \Closure::fromCallable( $validate_policy ),
		);
	}

	public function validate_policy( string $capability, \stdClass $limits ) : bool {
		$entry = $this->account_providers[ $capability ] ?? null;
		return null !== $entry && true === ( $entry['validate_policy'] )( copy_json( $limits ) );
	}

	public function account_providers() : array {
		$result = $this->account_providers;
		foreach ( $result as &$entry ) {
			$entry['server'] = copy_json( $entry['server'] );
		}
		return $result;
	}

	public function supports( string $uri ) : bool {
		return isset( $this->capabilities[ $uri ] );
	}

	public function method( string $name ) : ?array {
		return $this->methods[ $name ] ?? null;
	}
}
