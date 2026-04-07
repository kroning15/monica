<?php

declare(strict_types=1);

// Prevent PHP deprecation notices from being rendered to end users on Vercel.
if (getenv('VERCEL_ENV')) {
    // Nightwatch instrumentation expects a long-running agent and can fail on serverless.
    putenv('NIGHTWATCH_ENABLED=false');

    $deploymentId = getenv('VERCEL_DEPLOYMENT_ID') ?: 'unknown';
    $runtimeCacheRoot = '/tmp/laravel-cache-'.$deploymentId;
    $viewCachePath = $runtimeCacheRoot.'/views';
    $packagesCachePath = $runtimeCacheRoot.'/packages.php';
    $servicesCachePath = $runtimeCacheRoot.'/services.php';
    $configCachePath = $runtimeCacheRoot.'/config.php';
    $routesCachePath = $runtimeCacheRoot.'/routes.php';
    $eventsCachePath = $runtimeCacheRoot.'/events.php';

    @mkdir($viewCachePath, 0777, true);

    // Keep framework cache artifacts on writable storage for serverless runtime.
    putenv('APP_PACKAGES_CACHE='.$packagesCachePath);
    putenv('APP_SERVICES_CACHE='.$servicesCachePath);
    putenv('APP_CONFIG_CACHE='.$configCachePath);
    putenv('APP_ROUTES_CACHE='.$routesCachePath);
    putenv('APP_EVENTS_CACHE='.$eventsCachePath);
    putenv('VIEW_COMPILED_PATH='.$viewCachePath);

    // Prefer HTTPS URL generation when deployed behind Vercel's proxy.
    $requestHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? getenv('VERCEL_URL');
    if ($requestHost) {
        putenv('APP_URL=https://'.$requestHost);
    }
    putenv('APP_FORCE_URL=true');

    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}

require __DIR__.'/../public/index.php';
