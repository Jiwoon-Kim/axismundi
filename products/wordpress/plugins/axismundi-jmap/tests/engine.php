<?php
/** Standalone protocol regression tests: php tests/engine.php. */

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ );
require_once dirname( __DIR__ ) . '/includes/bootstrap.php';

use Axismundi\Jmap\Context;
use Axismundi\Jmap\Engine;
use Axismundi\Jmap\MethodError;
use Axismundi\Jmap\RequestError;
use function Axismundi\Jmap\core_registry;
use const Axismundi\Jmap\CORE;

$GLOBALS['ax_jmap_engine_checks'] = 0;
function check( bool $condition, string $label ) : void {
	++$GLOBALS['ax_jmap_engine_checks'];
	if ( ! $condition ) {
		throw new RuntimeException( $label );
	}
}
function wire( array $calls, array $extra = array() ) : string {
	return json_encode( array_merge( array( 'using' => array( CORE ), 'methodCalls' => $calls ), $extra ), JSON_THROW_ON_ERROR );
}
function echo_call( stdClass $arguments, string $id = 'c' ) : array {
	return array( 'Core/echo', $arguments, $id );
}
function rejects( Engine $engine, string $wire, string $type, ?string $limit = null ) : void {
	try {
		$engine->execute( $wire, 1, 's' );
		check( false, 'Expected rejection: ' . $type );
	} catch ( RequestError $error ) {
		check( $type === $error->type && $limit === $error->limit, 'Request error: ' . $type );
		check( 400 === $error->problem()->status, 'Problem status' );
	}
}

$engine = new Engine( core_registry() );
$echo = $engine->execute( wire( array( echo_call( (object) array( 'empty' => new stdClass(), 'list' => array(), 'text' => '#literal' ) ) ) ), 1, 'session' );
check( '{}' === json_encode( $echo->methodResponses[0][1]->empty ), 'Empty object preserved' );
check( array() === $echo->methodResponses[0][1]->list, 'Empty array preserved' );
check( '#literal' === $echo->methodResponses[0][1]->text, 'Text is not creation-ID substituted' );
check( 'session' === $echo->sessionState && ! property_exists( $echo, 'createdIds' ), 'Response envelope' );
check( array() === $engine->execute( wire( array() ), 1, 's' )->methodResponses, 'Empty batch' );

foreach ( array( 'null', '[]', '{}', '{"using":[],"methodCalls":[]}', '{"using":[3],"methodCalls":[]}', '{"using":["urn:ietf:params:jmap:core"],"methodCalls":[["Core/echo",[],"x"]]}' ) as $invalid ) {
	rejects( $engine, $invalid, 'notRequest' );
}
rejects( $engine, '{', 'notJSON' );
rejects( $engine, wire( array(), array( 'using' => array( CORE, 'unknown' ) ) ), 'unknownCapability' );
rejects( $engine, wire( array(), array( 'createdIds' => null ) ), 'notRequest' );
rejects( $engine, wire( array(), array( 'createdIds' => (object) array( 'bad key' => 'id' ) ) ), 'notRequest' );
rejects( new Engine( core_registry(), 10 ), wire( array() ), 'limit', 'maxSizeRequest' );
rejects( new Engine( core_registry(), 1000000, 1 ), wire( array( echo_call( new stdClass() ), echo_call( new stdClass() ) ) ), 'limit', 'maxCallsInRequest' );

$source = (object) array( 'list' => array( (object) array( 'ids' => array( 'one', 'two' ) ), (object) array( 'ids' => array( 'three' ) ) ), 'a/b' => (object) array( '~' => null ), '*' => 'literal' );
foreach ( array( '/list/*/ids' => array( 'one', 'two', 'three' ), '/a~1b/~0' => null, '/*' => 'literal', '/list/0/ids/1' => 'two' ) as $path => $expected ) {
	$ref = (object) array( '#value' => (object) array( 'resultOf' => 'first', 'name' => 'Core/echo', 'path' => $path ) );
	$result = $engine->execute( wire( array( echo_call( $source, 'first' ), echo_call( $ref, 'next' ) ) ), 1, 's' );
	check( $expected === $result->methodResponses[1][1]->value, 'Pointer: ' . $path );
}
foreach ( array( '/missing', '/list/01', '/list/-', '/bad~2', 'not/pointer', '/list/*/missing' ) as $path ) {
	$ref = (object) array( '#v' => (object) array( 'resultOf' => 'first', 'name' => 'Core/echo', 'path' => $path ) );
	$result = $engine->execute( wire( array( echo_call( $source, 'first' ), echo_call( $ref ) ) ), 1, 's' );
	check( 'invalidResultReference' === $result->methodResponses[1][1]->type, 'Invalid pointer: ' . $path );
}
$ref = (object) array( '#v' => (object) array( 'resultOf' => 'first', 'name' => 'Core/echo', 'path' => '' ) );
$result = $engine->execute( wire( array( array( 'Missing/method', new stdClass(), 'first' ), echo_call( $ref ), echo_call( new stdClass(), 'last' ) ) ), 1, 's' );
check( 'unknownMethod' === $result->methodResponses[0][1]->type && 'invalidResultReference' === $result->methodResponses[1][1]->type && 'Core/echo' === $result->methodResponses[2][0], 'Errors continue the batch' );
$ref->v = null;
check( 'invalidArguments' === $engine->execute( wire( array( echo_call( $ref ) ) ), 1, 's' )->methodResponses[0][1]->type, 'Null argument conflicts with reference' );

$registry = core_registry();
$capability = 'https://example.test/jmap/testing';
$registry->register_capability( $capability );
$registry->register_method( 'Test/create', $capability, static function ( stdClass $args, Context $context ) : stdClass {
	$context->record_created_id( 'new', 'record' );
	return (object) array( 'id' => $context->resolve_id( '#new' ) );
}, true, true );
$engine = new Engine( $registry );
$access = true;
$readonly = false;
$account = static function ( string $id, int $user ) use ( &$access, &$readonly, $capability ) : ?stdClass {
	return $access && 'a1' === $id && 1 === $user ? (object) array( 'isReadOnly' => $readonly, 'accountCapabilities' => (object) array( $capability => new stdClass() ) ) : null;
};
$calls = array( array( 'Test/create', (object) array( 'accountId' => 'a1' ), 'create' ) );
$extra = array( 'using' => array( CORE, $capability ), 'createdIds' => (object) array( 'old' => 'existing' ) );
$result = $engine->execute( wire( $calls, $extra ), 1, 's', $account );
check( 'record' === $result->createdIds->new && 'existing' === $result->createdIds->old, 'Creation-ID request map' );
$next = $engine->execute( wire( array(), array( 'createdIds' => new stdClass() ) ), 1, 's', $account );
check( '{}' === json_encode( $next->createdIds ), 'Creation IDs do not leak across requests' );
check( 'unknownMethod' === $engine->execute( wire( $calls ), 1, 's', $account )->methodResponses[0][1]->type, 'Capability must be requested' );
$access = false;
check( 'accountNotFound' === $engine->execute( wire( $calls, $extra ), 1, 's', $account )->methodResponses[0][1]->type, 'Revocation rechecked' );
$access = true;
$readonly = true;
check( 'accountReadOnly' === $engine->execute( wire( $calls, $extra ), 1, 's', $account )->methodResponses[0][1]->type, 'Read-only write rejected' );
check( 'accountNotFound' === $engine->execute( wire( $calls, $extra ), 2, 's', $account )->methodResponses[0][1]->type, 'User isolation' );
check( 'accountNotFound' === $engine->execute( wire( $calls, $extra ), 1, 's' )->methodResponses[0][1]->type, 'Missing resolver fails closed' );
try {
	$registry->register_capability( 'late' );
	check( false, 'Registry frozen' );
} catch ( LogicException $error ) {
	check( true, 'Registry frozen' );
}
try {
	$engine->execute( wire( array() ), 0, 's' );
	check( false, 'Anonymous execution rejected' );
} catch ( InvalidArgumentException $error ) {
	check( true, 'Anonymous execution rejected' );
}

$called = false;
$registry = core_registry();
$registry->register_method( 'Test/observe', CORE, static function () use ( &$called ) : stdClass {
	$called = true;
	return new stdClass();
} );
$engine = new Engine( $registry );
rejects( $engine, wire( array( array( 'Test/observe', new stdClass(), 'x' ), array( 'Core/echo', array(), 'bad' ) ) ), 'notRequest' );
check( ! $called, 'Whole envelope is validated before any side effect' );
rejects( $engine, wire( array( array( 'Test/observe', new stdClass(), 'x' ) ), array( 'using' => array( CORE, 'unknown' ) ) ), 'unknownCapability' );
check( ! $called, 'Unknown capability rejects before execution' );
$future = (object) array( '#v' => (object) array( 'resultOf' => 'later', 'name' => 'Core/echo', 'path' => '' ) );
$result = $engine->execute( wire( array( echo_call( $future, 'early' ), echo_call( new stdClass(), 'later' ) ) ), 1, 's' );
check( 'invalidResultReference' === $result->methodResponses[0][1]->type, 'Forward result reference rejected' );
$result = $engine->execute( wire( array( echo_call( $source, 'first' ), echo_call( $future, 'later' ) ) ), 1, 's' );
check( 'invalidResultReference' === $result->methodResponses[1][1]->type, 'No self result reference' );
$result = $engine->execute( wire( array( echo_call( new stdClass(), 'id with spaces' ) ), array( 'futureExtension' => true ) ), 1, 's' );
check( 'id with spaces' === $result->methodResponses[0][2], 'Call ID is not restricted to Id grammar' );
check( 'Core/echo' === $result->methodResponses[0][0], 'Unknown top-level fields ignored' );
$duplicate = core_registry();
try {
	$duplicate->register_method( 'Core/echo', CORE, static fn() : stdClass => new stdClass() );
	check( false, 'Duplicate rejected' );
} catch ( LogicException $error ) {
	check( true, 'Duplicate rejected' );
}

$context = new Context( 1, (object) array( 'old' => 'record' ), null );
check( 'record' === $context->resolve_id( '#old' ) && null === $context->resolve_id( '#missing' ), 'Creation reference lookup' );
check( 'literal' === $context->resolve_id( 'literal' ) && null === $context->resolve_id( 'bad id' ), 'Literal Id validation' );

// Reference traversal must not let a later handler mutate an earlier response.
$registry = core_registry();
$registry->register_method( 'Test/mutate', CORE, static function ( stdClass $args ) : stdClass {
	$args->value->nested = 'changed';
	return $args;
} );
$engine = new Engine( $registry );
$result = $engine->execute( wire( array(
	echo_call( (object) array( 'nested' => 'original' ), 'first' ),
	array( 'Test/mutate', (object) array( '#value' => (object) array( 'resultOf' => 'first', 'name' => 'Core/echo', 'path' => '' ) ), 'second' ),
) ), 1, 's' );
check( 'original' === $result->methodResponses[0][1]->nested && 'changed' === $result->methodResponses[1][1]->value->nested, 'Earlier results are isolated' );

$config = new \Axismundi\Jmap\CoreConfiguration();
$limits = $config->properties();
$limits->maxCallsInRequest = 1;
$limits->maxObjectsInGet = 1;
$limits->maxObjectsInSet = 1;
$config = new \Axismundi\Jmap\CoreConfiguration( $limits );
$reg = core_registry();
$reg->register_method( 'Test/get', CORE, static fn( stdClass $args ) => $args );
$reg->register_method( 'Test/set', CORE, static fn( stdClass $args ) => $args );
$bounded = $config->engine( $reg );
rejects( $bounded, wire( array( echo_call( new stdClass() ), echo_call( new stdClass() ) ) ), 'limit', 'maxCallsInRequest' );
$response = $bounded->execute( wire( array( array( 'Test/get', (object) array( 'ids' => array( 'a', 'b' ) ), 'get' ) ) ), 1, 's' );
check( 'requestTooLarge' === $response->methodResponses[0][1]->type, 'Shared Core configuration enforces get count' );
$response = $bounded->execute( wire( array( array( 'Test/set', (object) array( 'create' => (object) array( 'a' => new stdClass(), 'b' => new stdClass() ) ), 'set' ) ) ), 1, 's' );
check( 'requestTooLarge' === $response->methodResponses[0][1]->type, 'Shared Core configuration enforces set count' );
$response = $bounded->execute( wire( array( array( 'Test/set', (object) array( 'create' => 'invalid' ), 'set' ) ) ), 1, 's' );
check( 'invalidArguments' === $response->methodResponses[0][1]->type, 'Malformed set fields fail without a PHP type error' );
$copy = $config->properties();
$copy->maxCallsInRequest = 100;
check( 1 === $config->properties()->maxCallsInRequest, 'Configuration is not mutable through its advertised copy' );
$reg = core_registry();
$reg->register_method( 'Test/crash', CORE, static function () { throw new RuntimeException( 'private data' ); } );
$response = ( new Engine( $reg ) )->execute( wire( array( array( 'Test/crash', new stdClass(), 'bad' ), echo_call( new stdClass(), 'next' ) ) ), 1, 's' );
check( 'serverFail' === $response->methodResponses[0][1]->type && ! str_contains( json_encode( $response ), 'private data' ) && 'Core/echo' === $response->methodResponses[1][0], 'Unexpected handler failures are sanitized and the batch continues' );
printf( "PASS: %d checks\n", $GLOBALS['ax_jmap_engine_checks'] );
