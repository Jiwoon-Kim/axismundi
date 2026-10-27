<?php
/** Transport-independent protocol classes; no WordPress hooks. */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/engine/protocol.php';
require_once __DIR__ . '/registry/registry.php';
require_once __DIR__ . '/engine/context.php';
require_once __DIR__ . '/engine/engine.php';
require_once dirname( __DIR__ ) . '/modules/rfc8620/core.php';
require_once dirname( __DIR__ ) . '/modules/rfc8620/session.php';
