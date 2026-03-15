<?php

namespace App\Console\Commands;

use App\Enums\Campaign\UnifiedCampaignStatus;
use App\Jobs\ProcessUnifiedCampaignJob;
use App\Models\UnifiedCampaign;
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
        $limit = (int) $this->option('limit');

        $this->info('Processing unified campaigns...');

        // 1. Check for scheduled campaigns that should start
        $this->startScheduledCampaigns();

        // 2. Process running campaigns
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

        // Check if there are pending dispatches
        $pendingCount = $campaign->dispatches()
            ->whereIn('status', ['pending', 'queued'])
            ->count();

        if ($pendingCount === 0) {
            // Check if campaign should be completed
            $processingCount = $campaign->dispatches()
                ->where('status', 'processing')
                ->count();

            if ($processingCount === 0) {
                $campaign->markAsCompleted();
                $this->line("Campaign {$campaign->id} completed - no more dispatches");
                return;
            }
        }

        // Dispatch the processing job
        ProcessUnifiedCampaignJob::dispatch($campaign->id, $batchSize);

        $this->line("Dispatched processing job for campaign {$campaign->id} ({$pendingCount} pending)");
    }
}
