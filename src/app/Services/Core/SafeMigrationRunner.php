<?php

namespace App\Services\Core;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Exception;
use Throwable;

/**
 * SafeMigrationRunner - Enterprise-level migration handler
 *
 * Handles database migrations safely with:
 * - Graceful error handling (skip existing tables/columns, continue on failure)
 * - Detailed logging for debugging
 * - Version-based migration from config.json
 * - Automatic recovery from common errors
 */
class SafeMigrationRunner
{
    /**
     * Migration results tracking
     */
    protected array $results = [
        'success' => [],
        'skipped' => [],
        'failed' => [],
        'warnings' => [],
    ];

    /**
     * Log channel name
     */
    protected string $logChannel = 'daily';

    /**
     * Base migrations path
     */
    protected string $migrationsPath;

    public function __construct()
    {
        $this->migrationsPath = database_path('migrations');
    }

    /**
     * Run migrations from a config array (used by SystemUpdateController)
     *
     * @param array $config Config array with migrations and version info
     * @return array Migration results
     */
    public function runFromConfig(array $config): array
    {
        $this->resetResults();

        $currentVersion = (float) site_settings('app_version', 1.1);
        $migrations = $config['migrations'] ?? [];

        Log::info("[SafeMigrationRunner] Starting migration run", [
            'current_version' => $currentVersion,
            'total_versions' => count($migrations)
        ]);

        foreach ($migrations as $version => $migrationFiles) {
            // Only run migrations for versions newer than current
            if (version_compare((string)$version, (string)$currentVersion, '>')) {
                Log::info("[SafeMigrationRunner] Processing version: {$version}");

                if (is_array($migrationFiles)) {
                    foreach ($migrationFiles as $migrationPath) {
                        $this->runSingleMigration($migrationPath);
                    }
                }
            }
        }

        // Run seeders if defined
        $seeders = $config['seeder'] ?? [];
        foreach ($seeders as $version => $seederList) {
            if (version_compare((string)$version, (string)$currentVersion, '>')) {
                if (is_array($seederList)) {
                    foreach ($seederList as $seeder) {
                        $this->runSeeder($seeder);
                    }
                }
            }
        }

        $this->logFinalResults();

        return $this->results;
    }

    /**
     * Run all pending migrations safely (fallback method)
     *
     * @return array Migration results
     */
    public function runAllPending(): array
    {
        $this->resetResults();

        Log::info("[SafeMigrationRunner] Running all pending migrations");

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();

            Log::info("[SafeMigrationRunner] Default migrate completed", ['output' => $output]);
            $this->results['success'][] = 'All pending migrations';

        } catch (Throwable $e) {
            // If bulk migration fails, try individual files
            Log::warning("[SafeMigrationRunner] Bulk migration failed, trying individual files", [
                'error' => $e->getMessage()
            ]);

            $this->runMigrationsIndividually();
        }

        return $this->results;
    }

    /**
     * Run migrations one by one (for error recovery)
     */
    protected function runMigrationsIndividually(): void
    {
        $files = File::glob($this->migrationsPath . '/*.php');
        sort($files);

        foreach ($files as $file) {
            $fileName = basename($file);

            // Skip if already migrated
            if ($this->isMigrationRan($fileName)) {
                continue;
            }

            $relativePath = 'database/migrations/' . $fileName;
            $this->runSingleMigration($relativePath);
        }
    }

    /**
     * Run a single migration file safely
     *
     * @param string $migrationPath Relative path to migration file
     * @return bool Success status
     */
    public function runSingleMigration(string $migrationPath): bool
    {
        $fileName = basename($migrationPath);
        $fullPath = base_path($migrationPath);

        // Check if file exists
        if (!File::exists($fullPath)) {
            // Try alternate path (storage temp path during update)
            $altPath = storage_path('app/public/temp_update/' . $migrationPath);
            if (!File::exists($altPath)) {
                $this->results['warnings'][] = "Migration file not found: {$fileName}";
                Log::warning("[SafeMigrationRunner] Migration file not found", [
                    'path' => $migrationPath,
                    'checked' => [$fullPath, $altPath]
                ]);
                return false;
            }
        }

        // Check if already migrated
        if ($this->isMigrationRan($fileName)) {
            $this->results['skipped'][] = [
                'file' => $fileName,
                'reason' => 'Already migrated',
            ];
            Log::info("[SafeMigrationRunner] Skipped (already ran): {$fileName}");
            return true;
        }

        try {
            Artisan::call('migrate', [
                '--path' => $migrationPath,
                '--force' => true,
            ]);

            $output = Artisan::output();

            // Check for "Nothing to migrate" which means it was already done
            if (str_contains($output, 'Nothing to migrate')) {
                $this->markAsMigrated($fileName);
                $this->results['skipped'][] = [
                    'file' => $fileName,
                    'reason' => 'Nothing to migrate',
                ];
                return true;
            }

            $this->results['success'][] = $fileName;
            Log::info("[SafeMigrationRunner] Successfully migrated: {$fileName}");

            return true;

        } catch (Throwable $e) {
            return $this->handleMigrationError($e, $fileName, $migrationPath);
        }
    }

    /**
     * Handle migration errors intelligently
     */
    protected function handleMigrationError(Throwable $e, string $fileName, string $migrationPath): bool
    {
        $errorMessage = $e->getMessage();
        $errorCode = $this->extractSqlErrorCode($errorMessage);

        Log::warning("[SafeMigrationRunner] Migration error", [
            'file' => $fileName,
            'error' => $errorMessage,
            'code' => $errorCode
        ]);

        // Handle "table already exists" (MySQL: 1050, SQLSTATE: 42S01)
        if ($this->isTableExistsError($errorMessage)) {
            $this->markAsMigrated($fileName);
            $this->results['skipped'][] = [
                'file' => $fileName,
                'reason' => 'Table already exists - marked as migrated',
            ];
            Log::info("[SafeMigrationRunner] Table exists, marking as migrated: {$fileName}");
            return true;
        }

        // Handle "column already exists" (MySQL: 1060, SQLSTATE: 42S21)
        if ($this->isColumnExistsError($errorMessage)) {
            $this->markAsMigrated($fileName);
            $this->results['skipped'][] = [
                'file' => $fileName,
                'reason' => 'Column already exists - marked as migrated',
            ];
            Log::info("[SafeMigrationRunner] Column exists, marking as migrated: {$fileName}");
            return true;
        }

        // Handle "duplicate key" errors
        if ($this->isDuplicateKeyError($errorMessage)) {
            $this->markAsMigrated($fileName);
            $this->results['skipped'][] = [
                'file' => $fileName,
                'reason' => 'Duplicate key - marked as migrated',
            ];
            Log::info("[SafeMigrationRunner] Duplicate key, marking as migrated: {$fileName}");
            return true;
        }

        // Handle "table doesn't exist" for ALTER operations - skip gracefully
        if ($this->isTableNotFoundError($errorMessage)) {
            $this->results['warnings'][] = "Table not found for migration: {$fileName}";
            Log::warning("[SafeMigrationRunner] Table not found for ALTER, skipping: {$fileName}");
            // Don't mark as migrated - may need to run again after table is created
            return false;
        }

        // Actual failure - log and continue
        $this->results['failed'][] = [
            'file' => $fileName,
            'error' => $this->sanitizeErrorMessage($errorMessage),
        ];

        Log::error("[SafeMigrationRunner] Migration failed", [
            'file' => $fileName,
            'error' => $errorMessage
        ]);

        // Return true to continue with other migrations (don't break the update process)
        return false;
    }

    /**
     * Run a seeder safely
     */
    protected function runSeeder(string $seederClass): bool
    {
        try {
            Artisan::call('db:seed', [
                '--class' => $seederClass,
                '--force' => true,
            ]);

            $this->results['success'][] = "Seeder: {$seederClass}";
            Log::info("[SafeMigrationRunner] Seeder completed: {$seederClass}");

            return true;

        } catch (Throwable $e) {
            // Don't fail the whole update for seeder issues
            $this->results['warnings'][] = "Seeder warning ({$seederClass}): " . $e->getMessage();
            Log::warning("[SafeMigrationRunner] Seeder issue", [
                'seeder' => $seederClass,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Check if migration has already been ran
     */
    protected function isMigrationRan(string $fileName): bool
    {
        $migrationName = pathinfo($fileName, PATHINFO_FILENAME);

        try {
            return DB::table('migrations')
                ->where('migration', $migrationName)
                ->exists();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Mark a migration as ran in the migrations table
     */
    protected function markAsMigrated(string $fileName): void
    {
        $migrationName = pathinfo($fileName, PATHINFO_FILENAME);

        try {
            if (!$this->isMigrationRan($fileName)) {
                DB::table('migrations')->insert([
                    'migration' => $migrationName,
                    'batch' => $this->getNextBatchNumber(),
                ]);
                Log::info("[SafeMigrationRunner] Marked as migrated: {$migrationName}");
            }
        } catch (Throwable $e) {
            Log::warning("[SafeMigrationRunner] Could not mark as migrated: {$migrationName}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the next batch number for migrations
     */
    protected function getNextBatchNumber(): int
    {
        try {
            $maxBatch = DB::table('migrations')->max('batch');
            return ($maxBatch ?? 0) + 1;
        } catch (Throwable $e) {
            return 1;
        }
    }

    /**
     * Extract SQL error code from message
     */
    protected function extractSqlErrorCode(string $message): ?string
    {
        // Match patterns like "1050" or "42S01"
        if (preg_match('/\b(\d{4})\b/', $message, $matches)) {
            return $matches[1];
        }
        if (preg_match('/\b([0-9A-Z]{5})\b/', $message, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Check if error is "table already exists"
     */
    protected function isTableExistsError(string $message): bool
    {
        $message = strtolower($message);
        return str_contains($message, '1050')
            || str_contains($message, '42s01')
            || (str_contains($message, 'table') && str_contains($message, 'already exists'))
            || str_contains($message, 'base table or view already exists');
    }

    /**
     * Check if error is "column already exists"
     */
    protected function isColumnExistsError(string $message): bool
    {
        $message = strtolower($message);
        return str_contains($message, '1060')
            || str_contains($message, '42s21')
            || str_contains($message, 'duplicate column')
            || str_contains($message, 'column already exists');
    }

    /**
     * Check if error is "duplicate key"
     */
    protected function isDuplicateKeyError(string $message): bool
    {
        $message = strtolower($message);
        return str_contains($message, '1061')
            || str_contains($message, '1062')
            || str_contains($message, 'duplicate key')
            || str_contains($message, 'duplicate entry');
    }

    /**
     * Check if error is "table not found"
     */
    protected function isTableNotFoundError(string $message): bool
    {
        $message = strtolower($message);
        return str_contains($message, '1146')
            || str_contains($message, '42s02')
            || str_contains($message, "doesn't exist")
            || str_contains($message, 'table not found');
    }

    /**
     * Sanitize error message for display
     */
    protected function sanitizeErrorMessage(string $message): string
    {
        // Remove sensitive information like full paths
        $message = preg_replace('/[A-Z]:\\\\[^)]+/', '[path]', $message);
        $message = preg_replace('/\/[^)]+\.php/', '[file]', $message);

        // Truncate if too long
        if (strlen($message) > 200) {
            $message = substr($message, 0, 200) . '...';
        }

        return $message;
    }

    /**
     * Reset results array
     */
    protected function resetResults(): void
    {
        $this->results = [
            'success' => [],
            'skipped' => [],
            'failed' => [],
            'warnings' => [],
        ];
    }

    /**
     * Log final results
     */
    protected function logFinalResults(): void
    {
        $summary = sprintf(
            "[SafeMigrationRunner] Migration complete - Success: %d, Skipped: %d, Failed: %d, Warnings: %d",
            count($this->results['success']),
            count($this->results['skipped']),
            count($this->results['failed']),
            count($this->results['warnings'])
        );

        Log::info($summary);

        if (!empty($this->results['failed'])) {
            Log::warning("[SafeMigrationRunner] Failed migrations", $this->results['failed']);
        }
    }

    /**
     * Get the results
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Check if migration process was successful (no critical failures)
     */
    public function isSuccessful(): bool
    {
        return empty($this->results['failed']);
    }

    /**
     * Get summary message for display
     */
    public function getSummaryMessage(): string
    {
        $success = count($this->results['success']);
        $skipped = count($this->results['skipped']);
        $failed = count($this->results['failed']);
        $warnings = count($this->results['warnings']);

        if ($failed === 0 && $warnings === 0) {
            return "All migrations completed successfully ({$success} executed, {$skipped} skipped).";
        }

        $message = "Migrations completed with issues: {$success} successful, {$skipped} skipped";

        if ($failed > 0) {
            $message .= ", {$failed} failed";
        }
        if ($warnings > 0) {
            $message .= ", {$warnings} warnings";
        }

        return $message . ". Please check logs for details.";
    }
}
