<?php
/** One request's identity and creation-ID context. @package AxismundiJmap */

namespace Axismundi\Jmap;

defined( 'ABSPATH' ) || exit;

final class Context {

	private \stdClass $created_ids;
	private ?\Closure $account_resolver;

	public function __construct( public readonly int $user_id, \stdClass $created_ids, ?callable $account_resolver ) {
		$this->created_ids = clone $created_ids;
		$this->account_resolver = null === $account_resolver ? null : \Closure::fromCallable( $account_resolver );
	}

	/** Providers call only after their transaction has successfully committed. */
	public function record_created_id( string $creation_id, string $id ) : void {
		if ( ! valid_id( $creation_id ) || ! valid_id( $id ) ) {
			throw new \LogicException( 'Invalid creation ID mapping.' );
		}
		$this->created_ids->{$creation_id} = $id;
	}

	/** Resolve only schema-declared ID fields, never arbitrary text containing '#'. */
	public function resolve_id( string $id ) : ?string {
		if ( ! str_starts_with( $id, '#' ) ) {
			return valid_id( $id ) ? $id : null;
		}
		return $this->created_ids->{substr( $id, 1 )} ?? null;
	}

	public function created_ids() : \stdClass {
		return clone $this->created_ids;
	}

	public function authorize( mixed $id, string $capability, bool $writes ) : \stdClass {
		if ( ! valid_id( $id ) ) {
			throw new MethodError( 'invalidArguments', 'accountId must be an Id.' );
		}
		$account = null === $this->account_resolver ? null : ( $this->account_resolver )( $id, $this->user_id, $capability, $writes );
		if ( ! $account instanceof \stdClass ) {
			throw new MethodError( 'accountNotFound' );
		}
		if ( ! isset( $account->accountCapabilities->{$capability} ) ) {
			throw new MethodError( 'accountNotSupportedByMethod' );
		}
		if ( $writes && ( $account->isReadOnly ?? true ) ) {
			throw new MethodError( 'accountReadOnly' );
		}
		return $account;
	}
}
