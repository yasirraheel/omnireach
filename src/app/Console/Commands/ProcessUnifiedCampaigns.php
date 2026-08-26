<?php

namespace App\Console\Commands;

use App\Enums\Campaign\CampaignType;
use App\Enums\Campaign\DispatchStatus;
use App\Enums\Campaign\UnifiedCampaignStatus;
use App\Jobs\ProcessUnifiedCampaignJob;
use App\Models\CampaignDispatch;
use App\Models\UnifiedCampaign;
use App\Services\Campaign\CampaignDispatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessUnifiedCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:process
                            {--batch=50 : Number of dispatches per batch}
                            {--limit=10 : Maximum campaigns to process per run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all unified campaigns (SMS, Email, WhatsApp) in a single cron job';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $lock = \Illuminate\Support\Facades\Cache::lock('campaigns_process_lock', 55);

        if (!$lock->get()) {
            $this->info('Another instance of campaigns:process is currently running. Skipping execution.');
            return Command::SUCCESS;
        }

        try {
            $batchSize = (int) $this->option('batch');
            $limit     = (int) $this->option('limit');

            $this->info('Processing unified campaigns...');

            // Step 0: Proactively clear any dispatches stuck in PROCESSING for > 2 minutes
            // This handles cases where a send() call hangs or the queue worker crashed mid-job
            $this->clearStuckProcessingDispatches();

            // Step 1: Check for scheduled campaigns that should start
            $this->startScheduledCampaigns();

            // Step 2: Process running campaigns
            $runningCampaigns = UnifiedCampaign::running()
                ->orderBy('started_at', 'asc')
                ->limit($limit)
                ->get();

            $this->line("Found {$runningCampaigns->count()} running campaigns");

            foreach ($runningCampaigns as $campaign) {
                $this->processCampaign($campaign, $batchSize);
            }

            $this->info('Campaign processing completed');

            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    /**
     * Force-fail any dispatch stuck in PROCESSING for more than 2 minutes.
     * This runs every cron tick (every minute), so no dispatch can stay
     * stuck forever regardless of what happened in the queue worker.
     */
    protected function clearStuckProcessingDispatches(): void
    {
        $stuckDispatches = CampaignDispatch::where('status', DispatchStatus::PROCESSING)
            ->where('updated_at', '<', now()->subMinutes(2))
            ->get();

        if ($stuckDispatches->isEmpty()) {
            return;
        }

        $this->line("Found {$stuckDispatches->count()} stuck PROCESSING dispatches — force-failing them");
        Log::warning("Clearing {$stuckDispatches->count()} dispatches stuck in PROCESSING state");

        $affectedCampaignIds = $stuckDispatches->pluck('campaign_id')->unique();

        // Bulk update to FAILED
        CampaignDispatch::whereIn('id', $stuckDispatches->pluck('id'))
            ->update([
                'status'        => DispatchStatus::FAILED,
                'error_message' => 'Auto-failed: stuck in processing state for >2 minutes',
            ]);

        // Now trigger completion check for each affected campaign
        $dispatchService = app(CampaignDispatchService::class);
        foreach ($affectedCampaignIds as $campaignId) {
            $campaign = UnifiedCampaign::find($campaignId);
            if ($campaign && $campaign->status === UnifiedCampaignStatus::RUNNING) {
                $this->line("Triggering completion check for campaign {$campaignId} after clearing stuck dispatches");
                // Increment processed_contacts for each stuck dispatch that was cleared
                $clearedCount = $stuckDispatches->where('campaign_id', $campaignId)->count();
                for ($i = 0; $i < $clearedCount; $i++) {
                    $campaign->incrementProcessed();
                    $campaign->updateChannelStats(
                        $stuckDispatches->where('campaign_id', $campaignId)->first()->channel->value,
                        ['failed' => 1]
                    );
                }
                // Re-fetch and check if complete
                $campaign->refresh();
                $remaining = $campaign->dispatches()
                    ->whereIn('status', ['pending', 'queued', 'processing'])
                    ->count();

                if ($remaining === 0) {
                    $this->triggerCampaignCompletion($campaign);
                }
            }
        }
    }

    /**
     * Trigger campaign completion or rescheduling
     */
    protected function triggerCampaignCompletion(UnifiedCampaign $campaign): void
    {
        $isRecurring = $campaign->type === CampaignType::RECURRING && !empty($campaign->recurring_config);

        if ($isRecurring) {
            $config       = $campaign->recurring_config;
            $repeatTime   = max(1, (int) ($config['repeat_time'] ?? 1));
            $repeatFormat = $config['repeat_format'] ?? 'daily';

            $baseTime = $campaign->schedule_at ? \Carbon\Carbon::parse($campaign->schedule_at) : now();

            // Advance schedule forward until it is strictly in the future
            do {
                match ($repeatFormat) {
                    'hourly'  => $baseTime->addHours($repeatTime),
                    'daily'   => $baseTime->addDays($repeatTime),
                    'weekly'  => $baseTime->addWeeks($repeatTime),
                    'monthly' => $baseTime->addMonths($repeatTime),
                    'yearly'  => $baseTime->addYears($repeatTime),
                    default   => $baseTime->addDays($repeatTime),
                };
            } while ($baseTime->lte(now()));

            $scheduleAt = $baseTime;

            $dispatchService = app(CampaignDispatchService::class);
            $dispatchService->recordRunHistory($campaign, $scheduleAt);

            // Reset all dispatches for next run and assign the new future scheduled_at
            $campaign->dispatches()->update([
                'status'        => DispatchStatus::PENDING,
                'scheduled_at'  => $scheduleAt,
                'sent_at'       => null,
                'delivered_at'  => null,
                'error_message' => null,
                'retry_count'   => 0,
            ]);

            $campaign->update([
                'schedule_at'        => $scheduleAt,
                'status'             => UnifiedCampaignStatus::SCHEDULED,
                'processed_contacts' => 0,
                'stats'              => [],
            ]);

            $this->line("Campaign {$campaign->id} rescheduled to {$scheduleAt->toDateTimeString()}");
            Log::info("Recurring campaign {$campaign->id} auto-rescheduled to {$scheduleAt->toDateTimeString()}");
        } else {
            $dispatchService = app(CampaignDispatchService::class);
            $dispatchService->recordRunHistory($campaign, null);

            $campaign->markAsCompleted();
            $this->line("Campaign {$campaign->id} marked as completed");
        }
    }

    /**
     * Start scheduled campaigns that are ready
     */
    protected function startScheduledCampaigns(): void
    {
        $readyToStart = UnifiedCampaign::readyToRun()->get();

        foreach ($readyToStart as $campaign) {
            $this->line("Starting scheduled campaign: {$campaign->name}");
            $campaign->markAsStarted();
            Log::info("Scheduled campaign {$campaign->id} started: {$campaign->name}");
        }

        if ($readyToStart->count() > 0) {
            $this->info("Started {$readyToStart->count()} scheduled campaigns");
        }
    }

    /**
     * Process a single campaign synchronously (no queue dependency)
     * This avoids the queue worker dying mid-job and leaving dispatches stuck.
     */
    protected function processCampaign(UnifiedCampaign $campaign, int $batchSize): void
    {
        $this->line("Processing campaign: {$campaign->name} (ID: {$campaign->id})");

        $pendingCount    = $campaign->dispatches()->whereIn('status', ['pending', 'queued'])->count();
        $processingCount = $campaign->dispatches()->where('status', 'processing')->count();

        // Nothing left — complete or reschedule
        if ($pendingCount === 0 && $processingCount === 0) {
            $this->triggerCampaignCompletion($campaign);
            return;
        }

        if ($pendingCount === 0) {
            $this->line("Campaign {$campaign->id} has {$processingCount} dispatches still processing — will re-check next cron tick");
            return;
        }

        // Process up to $batchSize dispatches synchronously with a per-dispatch timeout
        $dispatches = $campaign->dispatches()
            ->readyToProcess()
            ->limit($batchSize)
            ->get();

        $dispatchService = app(CampaignDispatchService::class);

        foreach ($dispatches as $dispatch) {
            // Set a 25-second alarm so a hanging send() is killed
            $timeout = 25;
            $timedOut = false;

            if (function_exists('pcntl_signal') && function_exists('pcntl_alarm')) {
                pcntl_signal(SIGALRM, function () use (&$timedOut, $dispatch) {
                    $timedOut = true;
                    // Can't safely throw here in all PHP builds, so just set the flag
                });
                pcntl_alarm($timeout);
            }

            try {
                $dispatchService->processDispatch($dispatch);
            } catch (\Throwable $e) {
                Log::error("Dispatch {$dispatch->id} error: " . $e->getMessage());
                $dispatch->markAsFailed('Error: ' . $e->getMessage());
                try {
                    $dispatchService->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'failed');
                } catch (\Throwable $ignored) {}
            } finally {
                if (function_exists('pcntl_alarm')) {
                    pcntl_alarm(0); // Cancel alarm
                }
            }

            if ($timedOut) {
                Log::warning("Dispatch {$dispatch->id} timed out after {$timeout}s — marking as failed");
                $dispatch->markAsFailed("Timed out after {$timeout}s — WhatsApp group may be restricted");
                try {
                    $dispatchService->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'failed');
                } catch (\Throwable $ignored) {}
            }
        }

        $this->line("Processed {$dispatches->count()} dispatches for campaign {$campaign->id}");
    }
}
