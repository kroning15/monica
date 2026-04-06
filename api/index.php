<?php

declare(strict_types=1);

// Prevent PHP deprecation notices from being rendered to end users on Vercel.
if (getenv('VERCEL_ENV')) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}

require __DIR__.'/../public/index.php';
