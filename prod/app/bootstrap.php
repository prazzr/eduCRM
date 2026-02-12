<?php
declare(strict_types=1);

/**
 * EduCRM Bootstrap
 * PSR-4 Autoloading and Helper Initialization
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Load core configuration
// REQUIRE CONFIG AFTER AUTOLOADER SO ENV CAN BE LOADED
// require_once APP_ROOT . '/config/config.php'; -- Moved down

// Load Vendor Autoload (Composer)
if (file_exists(APP_ROOT . '/vendor/autoload.php')) {
    require_once APP_ROOT . '/vendor/autoload.php';
}

// PSR-4 Autoloader (Custom App)
spl_autoload_register(function ($class) {
    $prefix = 'EduCRM\\';
    $base_dir = APP_ROOT . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Load Environment Variables
\EduCRM\Helpers\Env::load(APP_ROOT . '/.env');

// Load Config (Now it can use $_ENV)
require_once APP_ROOT . '/config/config.php';

// Load Global Helper Functions (Centralized)
// This file contains users(), lookups(), crud(), csrf_*, and authentication helpers
require_once APP_ROOT . '/app/helpers.php';
