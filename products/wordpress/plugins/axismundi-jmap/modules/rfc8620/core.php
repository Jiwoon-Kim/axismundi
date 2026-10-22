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
