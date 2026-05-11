<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
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
        $requiredColumns = [
            'contact_tasks' => ['deleted_at'],
        ];

        try {
            $missingTables = [];
            foreach ($requiredTables as $requiredTable) {
                if (! Schema::hasTable($requiredTable)) {
                    $missingTables[] = $requiredTable;
                }
            }

            $missingColumns = [];
            foreach ($requiredColumns as $table => $columns) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        $missingColumns[] = $table.'.'.$column;
                    }
                }
            }

            if ($missingTables === [] && $missingColumns === []) {
                return;
            }

            if ($missingTables === [] && $this->containsOnlyDeletedAtColumns($missingColumns)) {
                $this->addMissingDeletedAtColumns($missingColumns);

                $stillMissing = array_filter($missingColumns, function (string $column): bool {
                    [$table, $field] = explode('.', $column, 2);

                    return ! Schema::hasTable($table) || ! Schema::hasColumn($table, $field);
                });

                if ($stillMissing === []) {
                    return;
                }
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

            $missingColumns = [];
            foreach ($requiredColumns as $table => $columns) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        $missingColumns[] = $table.'.'.$column;
                    }
                }
            }

            if ($missingTables !== [] || $missingColumns !== []) {
                error_log(sprintf(
                    '[AutoMigrate] migrate completed but schema is still missing. tables=[%s] columns=[%s] (path=%s method=%s)',
                    implode(', ', $missingTables),
                    implode(', ', $missingColumns),
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

    private function containsOnlyDeletedAtColumns(array $missingColumns): bool
    {
        return $missingColumns !== []
            && array_reduce($missingColumns, function (bool $carry, string $column): bool {
                return $carry && str_ends_with($column, '.deleted_at');
            }, true);
    }

    private function addMissingDeletedAtColumns(array $missingColumns): void
    {
        $tables = [];
        foreach ($missingColumns as $column) {
            [$table, $field] = explode('.', $column, 2);
            if ($field === 'deleted_at') {
                $tables[] = $table;
            }
        }

        foreach (array_unique($tables) as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'deleted_at')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->timestamp('deleted_at')->nullable()->index();
            });
        }
    }
}
