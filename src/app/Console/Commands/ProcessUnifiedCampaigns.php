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
            $repeatTime   = (int) ($config['repeat_time'] ?? 1);
            $repeatFormat = $config['repeat_format'] ?? 'daily';

            $scheduleAt = \Carbon\Carbon::parse($campaign->schedule_at ?? now());
            match ($repeatFormat) {
                'hourly'  => $scheduleAt->addHours($repeatTime),
                'daily'   => $scheduleAt->addDays($repeatTime),
                'weekly'  => $scheduleAt->addWeeks($repeatTime),
                'monthly' => $scheduleAt->addMonths($repeatTime),
                'yearly'  => $scheduleAt->addYears($repeatTime),
                default   => $scheduleAt->addDays($repeatTime),
            };

            // Reset all dispatches for next run
            $campaign->dispatches()->update([
                'status'        => DispatchStatus::PENDING,
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
     * Process a single campaign
     */
    protected function processCampaign(UnifiedCampaign $campaign, int $batchSize): void
    {
        $this->line("Processing campaign: {$campaign->name} (ID: {$campaign->id})");

        // Count pending (not currently being processed)
        $pendingCount = $campaign->dispatches()
            ->whereIn('status', ['pending', 'queued'])
            ->count();

        $processingCount = $campaign->dispatches()
            ->where('status', 'processing')
            ->count();

        // If nothing left to do, trigger completion
        if ($pendingCount === 0 && $processingCount === 0) {
            $this->triggerCampaignCompletion($campaign);
            return;
        }

        // If there are pending dispatches, fire the processing job
        if ($pendingCount > 0) {
            ProcessUnifiedCampaignJob::dispatch($campaign->id, $batchSize);
            $this->line("Dispatched processing job for campaign {$campaign->id} ({$pendingCount} pending)");
        } else {
            $this->line("Campaign {$campaign->id} has {$processingCount} dispatches still processing — will check next run");
        }
    }
}
