<?php
/** Shared application runtime; no public HTTP routes or implicit Account selection. */
namespace Axismundi\Jmap;
defined( 'ABSPATH' ) || exit;

function runtime_registry() : Registry {
	static $registry = null;
	if ( null === $registry ) {
		if ( ! did_action( 'plugins_loaded' ) ) { throw new \LogicException( 'JMAP registration must follow plugin loading.' ); }
		$candidate = core_registry();
		do_action( 'axismundi_jmap_register', $candidate );
		$candidate->freeze();
		$registry = $candidate;
	}
	return $registry;
}

final class Server {
	private SessionProjector $projector;
	private Engine $engine;
	public function __construct( Registry $registry, private readonly CoreConfiguration $configuration ) {
		$this->projector = new SessionProjector( $registry );
		$this->engine = $configuration->engine( $registry );
	}
	public function accounts() : \stdClass { return $this->projector->accounts( get_current_user_id() ); }
	/** Authenticated runtime execution without claiming a public Session transport. */
	public function execute_json( string $json ) : \stdClass {
		$state = fn() => hash_hmac( 'sha256', wp_json_encode( $this->accounts() ), wp_salt( 'auth' ) );
		$response = $this->engine->execute( $json, get_current_user_id(), $state(), fn( $id, $user, $capability, $writes ) => $this->projector->authorize( $id, $user, $capability, $writes ) );
		$response->sessionState = $state();
		return $response;
	}
	public function session( \stdClass $transport ) : \stdClass {
		return $this->projector->build( get_current_user_id(), $transport, $this->configuration->properties() );
	}
	public function authorize( string $account_id, string $capability, bool $writes = false ) : \stdClass {
		return $this->projector->authorize( $account_id, get_current_user_id(), $capability, $writes );
	}
	public function execute( string $json, \stdClass $transport ) : \stdClass {
		$session = $this->session( $transport );
		$response = $this->engine->execute( $json, get_current_user_id(), $session->state, fn( $id, $user, $capability, $writes ) => $this->projector->authorize( $id, $user, $capability, $writes ) );
		$response->sessionState = $this->session( $transport )->state;
		return $response;
	}
}

function execute_json( string $json ) : \stdClass {
	return server()->execute_json( $json );
}

function server() : Server {
	static $server = null;
	if ( null === $server ) {
		$config = apply_filters( 'axismundi_jmap_core_configuration', new \stdClass() );
		if ( ! $config instanceof \stdClass ) { throw new \LogicException( 'Core configuration must be an object.' ); }
		$server = new Server( runtime_registry(), new CoreConfiguration( array() === get_object_vars( $config ) ? null : $config ) );
	}
	return $server;
}

/** Backends call while holding their resource-write transaction/lock. */
function check_account_limit( string $account_id, string $capability, string $name, int $used, int $increase = 1 ) {
	try { server()->authorize( $account_id, $capability, true ); }
	catch ( MethodError $error ) { return new \WP_Error( 'ax_jmap_limit_forbidden', 'Account write authority is unavailable.' ); }
	$actor = resolve_account_actor( $account_id, get_current_user_id() );
	$policy = $actor ? account_policy( $actor->get_identity_id(), $capability ) : null;
	if ( ! $policy || ! $policy->enabled || $used < 0 || $increase < 0 || ! property_exists( $policy->limits, $name ) ) { return new \WP_Error( 'ax_jmap_limit_invalid', 'A declared Account limit is required.' ); }
	$limit = $policy->limits->{$name};
	if ( null === $limit ) { return true; }
	if ( ! is_int( $limit ) || $limit < 0 ) { return new \WP_Error( 'ax_jmap_limit_invalid', 'Invalid Account limit.' ); }
	return $used <= $limit && $increase <= $limit - $used ? true : new \WP_Error( 'ax_jmap_limit', 'Account resource limit exceeded.' );
}
