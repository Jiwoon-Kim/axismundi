<?php
/** RFC 8620 core registration; not a full server capability declaration. @package AxismundiJmap */

namespace Axismundi\Jmap;

defined( 'ABSPATH' ) || exit;

const CORE = 'urn:ietf:params:jmap:core';

function core_registry() : Registry {
	$registry = new Registry();
	$registry->register_capability( CORE );
	$registry->register_method( 'Core/echo', CORE, static fn( \stdClass $arguments ) : \stdClass => $arguments );
	return $registry;
}

/** One source for limits advertised by Session and enforced by the executor. */
final class CoreConfiguration {
	private \stdClass $properties;
	public function __construct( ?\stdClass $properties = null ) {
		$this->properties = copy_json( $properties ?? (object) array(
			'maxSizeUpload' => 0, 'maxConcurrentUpload' => 0,
			'maxSizeRequest' => 1000000, 'maxConcurrentRequests' => 1,
			'maxCallsInRequest' => 16, 'maxObjectsInGet' => 256, 'maxObjectsInSet' => 256,
			'collationAlgorithms' => array(),
		) );
		foreach ( array( 'maxSizeUpload', 'maxConcurrentUpload', 'maxSizeRequest', 'maxConcurrentRequests', 'maxCallsInRequest', 'maxObjectsInGet', 'maxObjectsInSet' ) as $key ) {
			$value = $this->properties->{$key} ?? null;
			if ( ! is_int( $value ) || $value < 0 || $value > 9007199254740991 ) { throw new \InvalidArgumentException( 'Invalid Core limit: ' . $key ); }
		}
		if ( $this->properties->maxSizeRequest < 1 || $this->properties->maxCallsInRequest < 1 || $this->properties->maxConcurrentRequests < 1 ) { throw new \InvalidArgumentException( 'Execution limits must be positive.' ); }
		if ( ! is_array( $this->properties->collationAlgorithms ?? null ) || ! array_is_list( $this->properties->collationAlgorithms ) ) { throw new \InvalidArgumentException( 'Invalid collation list.' ); }
		foreach ( $this->properties->collationAlgorithms as $value ) { if ( ! is_string( $value ) ) { throw new \InvalidArgumentException( 'Invalid collation.' ); } }
	}
	public function properties() : \stdClass { return copy_json( $this->properties ); }
	public function engine( Registry $registry ) : Engine {
		return new Engine( $registry, $this->properties->maxSizeRequest, $this->properties->maxCallsInRequest, $this->properties->maxObjectsInGet, $this->properties->maxObjectsInSet );
	}
}
