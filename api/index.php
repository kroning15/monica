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

$isVercelRuntime = (bool) (
    getenv('VERCEL')
    || getenv('VERCEL_ENV')
    || getenv('LAMBDA_TASK_ROOT')
    || is_dir('/var/task')
);

// Prevent PHP deprecation notices from being rendered to end users on Vercel.
if ($isVercelRuntime) {
    // Marker for config files that run after this bootstrap.
    setRuntimeEnv('MONICA_VERCEL_RUNTIME', '1');

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

    // Neon workaround for environments with older libpq/SNI support.
    $databaseUrl = getenv('DATABASE_URL');
    if (is_string($databaseUrl) && $databaseUrl !== '') {
        $urlParts = parse_url($databaseUrl);
        $host = is_array($urlParts) ? ($urlParts['host'] ?? null) : null;
        $password = is_array($urlParts) ? ($urlParts['pass'] ?? null) : null;

        if (is_string($host) && str_ends_with($host, '.neon.tech')) {
            $endpoint = explode('.', $host)[0] ?? '';

            if ($endpoint !== '') {
                $dbPassword = getenv('DB_PASSWORD');
                if (is_string($dbPassword) && $dbPassword !== '' && ! str_starts_with($dbPassword, 'endpoint=')) {
                    setRuntimeEnv('DB_PASSWORD', 'endpoint='.$endpoint.';'.$dbPassword);
                }

                if (is_string($password) && $password !== '' && ! str_starts_with($password, 'endpoint=')) {
                    $urlParts['pass'] = 'endpoint='.$endpoint.';'.$password;

                    $scheme = isset($urlParts['scheme']) ? $urlParts['scheme'].'://' : '';
                    $userInfo = '';
                    if (isset($urlParts['user'])) {
                        $userInfo = rawurlencode((string) $urlParts['user']);
                        $userInfo .= ':'.rawurlencode((string) $urlParts['pass']);
                        $userInfo .= '@';
                    }

                    $port = isset($urlParts['port']) ? ':'.$urlParts['port'] : '';
                    $path = $urlParts['path'] ?? '';
                    $query = isset($urlParts['query']) && $urlParts['query'] !== '' ? '?'.$urlParts['query'] : '';
                    $fragment = isset($urlParts['fragment']) ? '#'.$urlParts['fragment'] : '';

                    setRuntimeEnv(
                        'DATABASE_URL',
                        $scheme.$userInfo.$host.$port.$path.$query.$fragment
                    );
                }
            }
        }
    }

    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if (! is_array($error)) {
            return;
        }

        $fatalErrorTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR, E_USER_ERROR];
        if (! in_array($error['type'] ?? null, $fatalErrorTypes, true)) {
            return;
        }

        error_log(sprintf(
            '[ShutdownFatal] type=%s message=%s file=%s line=%s path=%s method=%s',
            (string) ($error['type'] ?? ''),
            (string) ($error['message'] ?? ''),
            (string) ($error['file'] ?? ''),
            (string) ($error['line'] ?? ''),
            $_SERVER['REQUEST_URI'] ?? '-',
            $_SERVER['REQUEST_METHOD'] ?? '-'
        ));
    });
}

require __DIR__.'/../public/index.php';
