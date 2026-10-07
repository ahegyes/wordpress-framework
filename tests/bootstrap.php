<?php declare( strict_types=1 );

// Framework files outside the shared package exit when WordPress has not defined ABSPATH, and the autoloader includes core's functions.php at once.
define( 'ABSPATH', __DIR__ . '/' );

require_once __DIR__ . '/../vendor/autoload.php';
