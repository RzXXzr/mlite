<?php
/**
 * Bootstrap for PHPUnit tests.
 * Sets up constants, autoloading, and database connection for testing.
 */

define('BASE_DIR', dirname(__DIR__));

// Provide a fake SCRIPT_NAME for the Autoloader
$_SERVER['SCRIPT_NAME'] = '/index.php';

// Load config constants
require_once BASE_DIR . '/config.php';

// Composer autoloader
require_once BASE_DIR . '/vendor/autoload.php';

/**
 * PHPUnit-compatible autoloader that works from CLI.
 * The mLITE Autoloader uses relative paths based on SCRIPT_NAME,
 * which doesn't work from the tests/ directory in CLI mode.
 * This autoloader resolves namespaces to absolute paths from BASE_DIR.
 */
spl_autoload_register(function (string $className): void {
    // Convert namespace to file path (lowercase dirs, original case file)
    $parts = explode('\\', $className);
    $file  = array_pop($parts);
    $dir   = strtolower(implode('/', $parts));

    $path = BASE_DIR . '/' . $dir . '/' . $file . '.php';
    if (is_readable($path)) {
        require_once $path;
    }
});
