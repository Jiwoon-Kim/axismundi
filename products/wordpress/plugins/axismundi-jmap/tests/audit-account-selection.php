<?php
/** Compatibility entry point; the browser adapter is now an Actors feature. */
defined( 'ABSPATH' ) || exit( 1 );
require dirname( __DIR__, 2 ) . '/axismundi-actors/tests/audit-selection-adapter.php';
