<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureDatabaseSchemaIsReady
{
    private static bool $hasCheckedSchema = false;

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::$hasCheckedSchema && $this->shouldAutoMigrate()) {
            self::$hasCheckedSchema = true;
            $this->runMigrationsIfNeeded($request);
        }

        return $next($request);
    }

    private function shouldAutoMigrate(): bool
    {
        $isVercelRuntime = (bool) (
            env('MONICA_VERCEL_RUNTIME')
            || env('VERCEL')
            || env('VERCEL_ENV')
            || env('LAMBDA_TASK_ROOT')
        );

        if (! $isVercelRuntime) {
            return false;
        }

        $autoMigrate = filter_var(
            (string) env('MONICA_AUTO_MIGRATE', 'true'),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        );

        return $autoMigrate !== false;
    }

    private function runMigrationsIfNeeded(Request $request): void
    {
        $requiredTables = ['users', 'currencies'];

        try {
            $missingTables = [];
            foreach ($requiredTables as $requiredTable) {
                if (! Schema::hasTable($requiredTable)) {
                    $missingTables[] = $requiredTable;
                }
            }

            if ($missingTables === []) {
                return;
            }
        } catch (Throwable $throwable) {
            error_log(sprintf(
                '[AutoMigrate] Unable to check schema: %s in %s:%d (path=%s method=%s)',
                $throwable->getMessage(),
                $throwable->getFile(),
                $throwable->getLine(),
                $request->path(),
                $request->method()
            ));
        }

        try {
            $exitCode = Artisan::call('migrate', [
                '--force' => true,
                '--isolated' => true,
            ]);

            if ($exitCode !== 0) {
                error_log(sprintf(
                    '[AutoMigrate] migrate failed with exit code %d (path=%s method=%s) output=%s',
                    $exitCode,
                    $request->path(),
                    $request->method(),
                    trim(Artisan::output())
                ));

                return;
            }

            $missingTables = [];
            foreach ($requiredTables as $requiredTable) {
                if (! Schema::hasTable($requiredTable)) {
                    $missingTables[] = $requiredTable;
                }
            }

            if ($missingTables !== []) {
                error_log(sprintf(
                    '[AutoMigrate] migrate completed but required tables are missing: %s (path=%s method=%s)',
                    implode(', ', $missingTables),
                    $request->path(),
                    $request->method()
                ));
            }
        } catch (Throwable $throwable) {
            error_log(sprintf(
                '[AutoMigrate] migrate threw %s: %s in %s:%d (path=%s method=%s)',
                $throwable::class,
                $throwable->getMessage(),
                $throwable->getFile(),
                $throwable->getLine(),
                $request->path(),
                $request->method()
            ));
        }
    }
}
