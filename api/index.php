<?php

declare(strict_types=1);

// Prevent PHP deprecation notices from being rendered to end users on Vercel.
if (getenv('VERCEL_ENV')) {
    // Laravel caches normally default to bootstrap/cache, which is read-only on Vercel.
    putenv('APP_PACKAGES_CACHE=/tmp/packages.php');
    putenv('APP_SERVICES_CACHE=/tmp/services.php');
    putenv('APP_CONFIG_CACHE=/tmp/config.php');
    putenv('APP_ROUTES_CACHE=/tmp/routes.php');
    putenv('APP_EVENTS_CACHE=/tmp/events.php');
    putenv('VIEW_COMPILED_PATH=/tmp/storage/framework/views');

    @mkdir('/tmp/storage/framework/views', 0777, true);

    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}

require __DIR__.'/../public/index.php';
