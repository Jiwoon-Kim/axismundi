<?php
/** Transport-independent request execution. @package AxismundiJmap */

namespace Axismundi\Jmap;

defined( 'ABSPATH' ) || exit;

final class Engine {

	public function __construct( private readonly Registry $registry, private readonly int $max_size = 1000000, private readonly int $max_calls = 16 ) {
		if ( $max_size < 1 || $max_calls < 1 ) {
			throw new \InvalidArgumentException( 'Request limits must be positive.' );
		}
		$registry->freeze();
	}

	/** Authentication and I-JSON wire validation belong to the future transport boundary. */
	public function execute( string $json, int $user_id, string $session_state, ?callable $account_resolver = null ) : \stdClass {
		if ( $user_id <= 0 ) {
			throw new \InvalidArgumentException( 'An authenticated user is required.' );
		}
		if ( strlen( $json ) > $this->max_size ) {
			throw new RequestError( 'limit', 400, 'Request exceeds the size limit.', 'maxSizeRequest' );
		}
		try {
			$request = json_decode( $json, false, 128, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $error ) {
			throw new RequestError( 'notJSON', 400, 'Request is not valid JSON.' );
		}
		$this->validate( $request );
		$context = new Context( $user_id, $request->createdIds ?? new \stdClass(), $account_resolver );
		$responses = array();
		foreach ( $request->methodCalls as [ $name, $arguments, $call_id ] ) {
			try {
				$arguments = $this->resolve_arguments( $arguments, $responses );
				$method = $this->registry->method( $name );
				if ( null === $method || ! in_array( $method['capability'], $request->using, true ) ) {
					throw new MethodError( 'unknownMethod' );
				}
				$account = $method['account_bound']
					? $context->authorize( $arguments->accountId ?? null, $method['capability'], $method['writes'] ) : null;
				$result = ( $method['handler'] )( $arguments, $context, $account );
				if ( ! $result instanceof \stdClass ) {
					throw new \LogicException( 'Method handlers must return an arguments object.' );
				}
				$responses[] = array( $name, copy_json( $result ), $call_id );
			} catch ( MethodError $error ) {
				$responses[] = array( 'error', $error->arguments(), $call_id );
			}
		}
		$response = (object) array( 'methodResponses' => $responses, 'sessionState' => $session_state );
		if ( property_exists( $request, 'createdIds' ) ) {
			$response->createdIds = $context->created_ids();
		}
		return $response;
	}

	private function validate( mixed $request ) : void {
		if ( ! $request instanceof \stdClass || ! is_array( $request->using ?? null ) || ! is_array( $request->methodCalls ?? null ) ) {
			throw new RequestError( 'notRequest', 400, 'Expected a Request object.' );
		}
		foreach ( $request->using as $capability ) {
			if ( ! is_string( $capability ) ) {
				throw new RequestError( 'notRequest', 400, 'using must contain strings.' );
			}
		}
		if ( ! in_array( CORE, $request->using, true ) ) {
			throw new RequestError( 'notRequest', 400, 'The core capability is required.' );
		}
		foreach ( $request->methodCalls as $call ) {
			if ( ! is_array( $call ) || 3 !== count( $call ) || ! is_string( $call[0] ) || ! $call[1] instanceof \stdClass || ! is_string( $call[2] ) ) {
				throw new RequestError( 'notRequest', 400, 'Invalid invocation tuple.' );
			}
		}
		if ( property_exists( $request, 'createdIds' ) ) {
			if ( ! $request->createdIds instanceof \stdClass ) {
				throw new RequestError( 'notRequest', 400, 'createdIds must be an object.' );
			}
			foreach ( $request->createdIds as $key => $value ) {
				if ( ! valid_id( (string) $key ) || ! valid_id( $value ) ) {
					throw new RequestError( 'notRequest', 400, 'Invalid createdIds entry.' );
				}
			}
		}
		foreach ( $request->using as $capability ) {
			if ( ! $this->registry->supports( $capability ) ) {
				throw new RequestError( 'unknownCapability', 400, 'Unsupported capability.' );
			}
		}
		if ( count( $request->methodCalls ) > $this->max_calls ) {
			throw new RequestError( 'limit', 400, 'Too many method calls.', 'maxCallsInRequest' );
		}
	}

	private function resolve_arguments( \stdClass $arguments, array $responses ) : \stdClass {
		$resolved = copy_json( $arguments );
		foreach ( $arguments as $key => $reference ) {
			if ( ! str_starts_with( (string) $key, '#' ) ) {
				continue;
			}
			$name = substr( (string) $key, 1 );
			if ( property_exists( $arguments, $name ) ) {
				throw new MethodError( 'invalidArguments' );
			}
			if ( ! $reference instanceof \stdClass || ! is_string( $reference->resultOf ?? null )
				|| ! is_string( $reference->name ?? null ) || ! is_string( $reference->path ?? null ) ) {
				throw new MethodError( 'invalidResultReference' );
			}
			$found = false;
			foreach ( $responses as $response ) {
				if ( $reference->resultOf !== $response[2] ) {
					continue;
				}
				// The first matching call ID wins, even if its response name differs.
				if ( $reference->name !== $response[0] ) {
					throw new MethodError( 'invalidResultReference' );
				}
				$resolved->{$name} = copy_json( $this->pointer( $response[1], $reference->path ) );
				$found = true;
				break;
			}
			if ( ! $found ) {
				throw new MethodError( 'invalidResultReference' );
			}
			unset( $resolved->{$key} );
		}
		return $resolved;
	}

	private function pointer( mixed $value, string $path ) : mixed {
		if ( '' === $path ) {
			return $value;
		}
		if ( ! str_starts_with( $path, '/' ) || preg_match( '/~(?![01])/', $path ) ) {
			throw new MethodError( 'invalidResultReference' );
		}
		$tokens = array_map( static fn( string $token ) : string => str_replace( array( '~1', '~0' ), array( '/', '~' ), $token ), explode( '/', substr( $path, 1 ) ) );
		return $this->walk( $value, $tokens );
	}

	private function walk( mixed $value, array $tokens ) : mixed {
		if ( array() === $tokens ) {
			return $value;
		}
		$token = array_shift( $tokens );
		if ( is_array( $value ) && '*' === $token ) {
			$result = array();
			foreach ( $value as $item ) {
				$selected = $this->walk( $item, $tokens );
				if ( is_array( $selected ) ) {
					array_push( $result, ...$selected );
				} else {
					$result[] = $selected;
				}
			}
			return $result;
		}
		if ( $value instanceof \stdClass && property_exists( $value, $token ) ) {
			return $this->walk( $value->{$token}, $tokens );
		}
		if ( is_array( $value ) && preg_match( '/\A(?:0|[1-9][0-9]*)\z/D', $token ) && array_key_exists( $token, $value ) ) {
			return $this->walk( $value[ $token ], $tokens );
		}
		throw new MethodError( 'invalidResultReference' );
	}
}
