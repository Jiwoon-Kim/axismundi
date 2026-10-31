<?php
/** Discovery response header contract, including the WebFinger 404 path. */
defined( 'ABSPATH' ) || exit( 1 );
$probe = require __DIR__ . '/discovery-header-fixtures.php';
$checks = $probe();
echo "PASS: {$checks} discovery header checks\n";
