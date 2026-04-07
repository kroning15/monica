<?php

declare(strict_types=1);

/**
 * Ensure runtime overrides are visible to PHP getenv() and Laravel's env()
 * repository (which reads $_ENV / $_SERVER).
 */
function setRuntimeEnv(string $key, string $value): void
{
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

// Prevent PHP deprecation notices from being rendered to end users on Vercel.
if (getenv('VERCEL_ENV')) {
    // Nightwatch instrumentation expects a long-running agent and can fail on serverless.
    setRuntimeEnv('NIGHTWATCH_ENABLED', 'false');

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
    setRuntimeEnv('APP_PACKAGES_CACHE', $packagesCachePath);
    setRuntimeEnv('APP_SERVICES_CACHE', $servicesCachePath);
    setRuntimeEnv('APP_CONFIG_CACHE', $configCachePath);
    setRuntimeEnv('APP_ROUTES_CACHE', $routesCachePath);
    setRuntimeEnv('APP_EVENTS_CACHE', $eventsCachePath);
    setRuntimeEnv('VIEW_COMPILED_PATH', $viewCachePath);

    // Prefer HTTPS URL generation when deployed behind Vercel's proxy.
    $requestHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? getenv('VERCEL_URL');
    if ($requestHost) {
        setRuntimeEnv('APP_URL', 'https://'.$requestHost);
    }
    setRuntimeEnv('APP_FORCE_URL', 'true');

    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}

require __DIR__.'/../public/index.php';
