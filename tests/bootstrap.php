<?php
/**
 * Bootstrap for PHPUnit tests.
 * Sets up constants, autoloading, and database connection for testing.
 */

define('BASE_DIR', dirname(__DIR__));

// Load config constants
require_once BASE_DIR . '/config.php';

// Composer autoloader
require_once BASE_DIR . '/vendor/autoload.php';
