<?php

namespace App\Console;

use App\Http\Controllers\CronController;
use App\Services\System\AutomationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * Enterprise-grade scheduling that works on any server:
     * - VPS/Dedicated: Use supervisor for continuous queue workers
     * - Shared Hosting: Use these scheduled commands via cron job
     *
     * IMPORTANT: The scheduler now respects the automation_mode setting:
     * - 'cron_url' mode: Scheduler skips queue processing (handled by /automation/run URL)
     * - 'scheduler' mode: Scheduler handles both campaigns and queues
     * - 'supervisor' mode: Scheduler handles campaigns only (queues handled by supervisor)
     * - 'auto' mode: Auto-detects based on environment
     *
     * For cPanel: Add this cron job (every minute):
     * php /path/to/artisan schedule:run >> /dev/null 2>&1
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // Main automation task - runs every minute
        // Processes campaigns, scheduled messages, subscriptions
        // Only runs if mode allows scheduler to handle campaigns
        $schedule->call(function () {
            if (AutomationService::shouldProcessCampaigns('scheduler')) {
                $cron = new CronController();
                $cron->run();
            }
        })->everyMinute()->name('automation:run')->withoutOverlapping(5);

        // Queue workers for each channel (every minute)
        // These process the actual sending jobs
        // ONLY run if mode allows scheduler to handle queues
        // Skip if supervisor workers are running or mode is cron_url
        $schedule->call(function () {
            if (!AutomationService::shouldProcessQueues('scheduler')) {
                return; // Skip - queues handled by supervisor or cron_url
            }

            // Process queues via Artisan
            \Illuminate\Support\Facades\Artisan::call('queue:work', [
                'connection' => 'database',
                '--queue' => 'worker-trigger',
                '--once' => true,
                '--stop-when-empty' => true,
            ]);
        })->everyMinute()->name('queue:worker-trigger')->withoutOverlapping();

        $schedule->call(function () {
            if (!AutomationService::shouldProcessQueues('scheduler')) {
                return;
            }

            \Illuminate\Support\Facades\Artisan::call('queue:work', [
                'connection' => 'database',
                '--queue' => 'regular-sms,campaign-sms',
                '--once' => true,
                '--stop-when-empty' => true,
            ]);
        })->everyMinute()->name('queue:sms')->withoutOverlapping();

        $schedule->call(function () {
            if (!AutomationService::shouldProcessQueues('scheduler')) {
                return;
            }

            \Illuminate\Support\Facades\Artisan::call('queue:work', [
                'connection' => 'database',
                '--queue' => 'regular-email,campaign-email',
                '--once' => true,
                '--stop-when-empty' => true,
            ]);
        })->everyMinute()->name('queue:email')->withoutOverlapping();

        $schedule->call(function () {
            if (!AutomationService::shouldProcessQueues('scheduler')) {
                return;
            }

            \Illuminate\Support\Facades\Artisan::call('queue:work', [
                'connection' => 'database',
                '--queue' => 'regular-whatsapp,campaign-whatsapp,chat-whatsapp',
                '--once' => true,
                '--stop-when-empty' => true,
            ]);
        })->everyMinute()->name('queue:whatsapp')->withoutOverlapping();

        $schedule->call(function () {
            if (!AutomationService::shouldProcessQueues('scheduler')) {
                return;
            }

            \Illuminate\Support\Facades\Artisan::call('queue:work', [
                'connection' => 'database',
                '--queue' => 'default,dispatch-logs,import-contacts,verify-email',
                '--once' => true,
                '--stop-when-empty' => true,
            ]);
        })->everyMinute()->name('queue:utility')->withoutOverlapping();

        // Lead generation scraping queue
        $schedule->call(function () {
            if (!AutomationService::shouldProcessQueues('scheduler')) {
                return;
            }

            \Illuminate\Support\Facades\Artisan::call('queue:work', [
                'connection' => 'database',
                '--queue' => 'lead-scraping',
                '--once' => true,
                '--stop-when-empty' => true,
            ]);
        })->everyMinute()->name('queue:lead-scraping')->withoutOverlapping();

        // Automation workflow queue
        $schedule->call(function () {
            if (!AutomationService::shouldProcessQueues('scheduler')) {
                return;
            }

            \Illuminate\Support\Facades\Artisan::call('queue:work', [
                'connection' => 'database',
                '--queue' => 'automation',
                '--once' => true,
                '--stop-when-empty' => true,
            ]);
        })->everyMinute()->name('queue:automation')->withoutOverlapping();

        // Check workflow triggers and resume waiting executions
        $schedule->call(function () {
            try {
                \App\Jobs\CheckWorkflowTriggersJob::dispatch();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Workflow triggers check failed: ' . $e->getMessage());
            }
        })->everyMinute()->name('automation:check-triggers')->withoutOverlapping();

        // Cleanup old failed jobs (daily)
        $schedule->command('queue:prune-failed --hours=168')
            ->daily()
            ->name('queue:prune-failed');

        // Clear old cache (daily)
        $schedule->command('cache:prune-stale-tags')
            ->daily()
            ->name('cache:prune');

        // Sync WhatsApp Node service config every 6 hours
        // This ensures license data stays fresh and prevents "Invalid license" errors
        $schedule->call(function () {
            try {
                $nodeService = app(\App\Services\System\Communication\NodeService::class);
                $nodeService->forceSyncConfig();
                \Illuminate\Support\Facades\Log::debug('WhatsApp Node config synced via scheduler');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('WhatsApp Node config sync failed: ' . $e->getMessage());
            }
        })->everySixHours()->name('whatsapp:sync-config')->withoutOverlapping();

        // Cleanup stale processing jobs (every 30 minutes)
        // This marks stuck jobs as failed
        $schedule->call(function () {
            AutomationService::cleanupStaleJobs(1);
        })->everyThirtyMinutes()->name('automation:cleanup-stale')->withoutOverlapping();

        // Cleanup old log files daily to prevent storage issues
        // Keeps only last 7 days of logs, aggressive cleanup if over 50MB
        $schedule->command('logs:cleanup --days=7 --max-size=50')
            ->dailyAt('03:00')
            ->name('logs:cleanup')
            ->withoutOverlapping();

        // ===================================================
        // WhatsApp Meta 2025 Enterprise Features
        // ===================================================

        // Refresh System User tokens (daily)
        // Extends tokens before they expire to maintain API access
        $schedule->job(new \App\Jobs\RefreshSystemUserTokenJob())
            ->dailyAt('02:00')
            ->name('meta:refresh-tokens')
            ->withoutOverlapping();

        // Run gateway health checks (every 30 minutes)
        // Monitors Cloud API and Node-based gateway connectivity
        $schedule->job(new \App\Jobs\RunGatewayHealthChecksJob())
            ->everyThirtyMinutes()
            ->name('whatsapp:health-checks')
            ->withoutOverlapping();

        // Cleanup stale onboarding sessions (every 6 hours)
        // Marks expired OAuth sessions as failed and cleans old records
        $schedule->job(new \App\Jobs\CleanupStaleOnboardingsJob())
            ->everySixHours()
            ->name('whatsapp:cleanup-onboardings')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     * @return void
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
