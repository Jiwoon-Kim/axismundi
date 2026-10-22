<?php
/** In-process Session projection; no discovery or transport registration. @package AxismundiJmap */
namespace Axismundi\Jmap;

defined( 'ABSPATH' ) || exit;

/** Providers must check Actor feature assignment, backend availability and User rights. */
final class AccountCapability {
	public function __construct( public readonly \stdClass $properties, public readonly bool $writable ) {}
}

final class SessionProjector {
	public function __construct( private readonly Registry $registry ) {
		if ( ! $registry->supports( CORE ) || null === $registry->method( 'Core/echo' ) ) {
			throw new \LogicException( 'Session projection requires the Core registry.' );
		}
		$registry->freeze();
	}

	/** Callers cannot use a request parameter to inspect another User's Session. */
	private function user( int $user_id ) : \WP_User {
		$user = get_userdata( $user_id );
		if ( $user_id <= 0 || $user_id !== get_current_user_id() || ! $user ) {
			throw new \InvalidArgumentException( 'The authenticated current User is required.' );
		}
		foreach ( array( 'axismundi_actors_account_is_personal', 'axismundi_actors_default_account', 'axismundi_actors_account_access_ready', 'axismundi_actors_resolve_workspace', 'axismundi_actors_workspace_options' ) as $function ) {
			if ( ! function_exists( $function ) ) {
				throw new \LogicException( 'Actors Account services are unavailable.' );
			}
		}
		if ( ! axismundi_actors_account_access_ready() ) {
			throw new \LogicException( 'Actors Account migration is incomplete.' );
		}
		return $user;
	}

	/** No acting-Actor dependency, implicit grants, or first-Account default. */
	public function accounts( int $user_id ) : \stdClass {
		$this->user( $user_id );
		$accounts = new \stdClass();
		$primary = new \stdClass();
		$providers = $this->registry->account_providers();
		foreach ( account_actors( $user_id ) as $id => $actor ) {
			$accounts->{$id} = $this->project_account( $actor, $user_id );
		}
		foreach ( $providers as $uri => $entry ) {
			$default = axismundi_actors_default_account( $user_id, $entry['purpose'] );
			$id = null === $default ? '' : 'a' . $default->get_identity_id();
			if ( isset( $accounts->{$id}->accountCapabilities->{$uri} ) ) {
				$primary->{$uri} = $id;
			}
		}
		return (object) array( 'accounts' => $accounts, 'primaryAccounts' => $primary );
	}

	/** Engine authorization must re-resolve current access, never reuse a Session snapshot. */
	public function account( string $account_id, int $user_id ) : ?\stdClass {
		$this->user( $user_id );
		$actor = resolve_account_actor( $account_id, $user_id );
		return null === $actor ? null : $this->project_account( $actor, $user_id );
	}

	private function project_account( \Axismundi_Actor $actor, int $user_id ) : \stdClass {
		$caps = new \stdClass();
		$writable = false;
		foreach ( $this->registry->account_providers() as $uri => $entry ) {
			$grant = ( $entry['provider'] )( $actor, $user_id );
			if ( null === $grant ) {
				continue;
			}
			if ( ! $grant instanceof AccountCapability ) {
				throw new \LogicException( 'Account providers must return AccountCapability or null.' );
			}
			$caps->{$uri} = copy_json( $grant->properties );
			$writable = $writable || $grant->writable;
		}
		return (object) array(
			'name' => $actor->get_display_name(),
			'isPersonal' => axismundi_actors_account_is_personal( $actor, $user_id ),
			'isReadOnly' => ! $writable,
			'accountCapabilities' => $caps,
		);
	}

	/** Only an explicit transport contract can supply the required Session URLs and limits. */
	public function build( int $user_id, \stdClass $transport, \stdClass $core ) : \stdClass {
		$user = $this->user( $user_id );
		$this->validate_transport( $transport, $core );
		$projection = $this->accounts( $user_id );
		$capabilities = (object) array( CORE => copy_json( $core ) );
		foreach ( $this->registry->account_providers() as $uri => $entry ) {
			$capabilities->{$uri} = $entry['server'];
		}
		$session = (object) array(
			'capabilities' => $capabilities,
			'accounts' => $projection->accounts,
			'primaryAccounts' => $projection->primaryAccounts,
			'username' => $user->user_login,
			'apiUrl' => $transport->apiUrl,
			'downloadUrl' => $transport->downloadUrl,
			'uploadUrl' => $transport->uploadUrl,
			'eventSourceUrl' => $transport->eventSourceUrl,
		);
		$session->state = hash( 'sha256', $user_id . ':' . json_encode( $this->canonical( $session ), JSON_THROW_ON_ERROR ) );
		return $session;
	}

	private function validate_transport( \stdClass $transport, \stdClass $core ) : void {
		$variables = array( 'apiUrl' => array(), 'downloadUrl' => array( 'accountId', 'blobId', 'type', 'name' ), 'uploadUrl' => array( 'accountId' ), 'eventSourceUrl' => array( 'types', 'closeafter', 'ping' ) );
		foreach ( $variables as $key => $required ) {
			$url = $transport->{$key} ?? null;
			if ( ! is_string( $url ) || preg_match( '/[\x00-\x20\x7f]/', $url ) ) {
				throw new \InvalidArgumentException( 'Missing or invalid Session URL.' );
			}
			$literal = preg_replace( '/\{[A-Za-z][A-Za-z0-9]*\}/', 'value', $url );
			$parts = parse_url( $literal );
			if ( str_contains( $literal, '{' ) || str_contains( $literal, '}' ) || ! is_array( $parts )
				|| 'https' !== ( $parts['scheme'] ?? '' ) || empty( $parts['host'] )
				|| isset( $parts['user'] ) || isset( $parts['fragment'] ) || ! filter_var( $literal, FILTER_VALIDATE_URL ) ) {
				throw new \InvalidArgumentException( 'Session URLs require HTTPS and level-1 templates without credentials or fragments.' );
			}
			preg_match_all( '/\{([A-Za-z][A-Za-z0-9]*)\}/', $url, $matches );
			$found = array_unique( $matches[1] );
			if ( array_diff( $required, $found ) || array_diff( $found, $required ) ) {
				throw new \InvalidArgumentException( 'Invalid Session URL template variables.' );
			}
		}
		foreach ( array( 'maxSizeUpload', 'maxConcurrentUpload', 'maxSizeRequest', 'maxConcurrentRequests', 'maxCallsInRequest', 'maxObjectsInGet', 'maxObjectsInSet' ) as $key ) {
			$value = $core->{$key} ?? null;
			if ( ! is_int( $value ) || $value < 0 || $value > 9007199254740991 ) {
				throw new \InvalidArgumentException( 'Missing or invalid Core unsigned limit.' );
			}
		}
		if ( ! is_array( $core->collationAlgorithms ?? null ) || ! array_is_list( $core->collationAlgorithms ) ) {
			throw new \InvalidArgumentException( 'Core collationAlgorithms must be a string list.' );
		}
		foreach ( $core->collationAlgorithms as $algorithm ) {
			if ( ! is_string( $algorithm ) ) {
				throw new \InvalidArgumentException( 'Invalid collation algorithm.' );
			}
		}
	}

	/** Object key order is not a Session change; array order remains significant. */
	private function canonical( mixed $value ) : mixed {
		if ( $value instanceof \stdClass ) {
			$members = get_object_vars( $value );
			ksort( $members, SORT_STRING );
			$value = (object) array_map( fn( $member ) => $this->canonical( $member ), $members );
		} elseif ( is_array( $value ) ) {
			$value = array_map( fn( $member ) => $this->canonical( $member ), $value );
		}
		return $value;
	}
}
