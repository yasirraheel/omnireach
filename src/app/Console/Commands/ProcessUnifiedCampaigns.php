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
                if ($campaign->type === \App\Enums\Campaign\CampaignType::RECURRING && !empty($campaign->recurring_config)) {
                    $this->rescheduleCampaign($campaign);
                } else {
                    $campaign->markAsCompleted();
                    $this->line("Campaign {$campaign->id} completed - no more dispatches");
                }
                return;
            }
        }

        // Dispatch the processing job
        ProcessUnifiedCampaignJob::dispatch($campaign->id, $batchSize);

        $this->line("Dispatched processing job for campaign {$campaign->id} ({$pendingCount} pending)");
    }

    /**
     * Reschedule a recurring campaign
     */
    protected function rescheduleCampaign(UnifiedCampaign $campaign): void
    {
        $config = $campaign->recurring_config;
        $repeatTime = (int) ($config['repeat_time'] ?? 1);
        $repeatFormat = $config['repeat_format'] ?? 'day';

        $scheduleAt = \Carbon\Carbon::parse($campaign->schedule_at ?? now());
        match ($repeatFormat) {
            \App\Enums\System\RepeatTimeEnum::HOURLY->value => $scheduleAt->addHours($repeatTime),
            \App\Enums\System\RepeatTimeEnum::DAILY->value => $scheduleAt->addDays($repeatTime),
            \App\Enums\System\RepeatTimeEnum::WEEKLY->value => $scheduleAt->addWeeks($repeatTime),
            \App\Enums\System\RepeatTimeEnum::MONTHLY->value => $scheduleAt->addMonths($repeatTime),
            \App\Enums\System\RepeatTimeEnum::YEARLY->value => $scheduleAt->addYears($repeatTime),
            default => $scheduleAt->addDays($repeatTime),
        };

        // Reset all dispatches to scheduled
        $campaign->dispatches()->update([
            'status' => \App\Enums\Campaign\DispatchStatus::PENDING,
            'sent_at' => null,
            'delivered_at' => null,
            'error_message' => null,
            'retry_count' => 0,
        ]);

        // Reset campaign stats and mark as scheduled
        $campaign->update([
            'schedule_at' => $scheduleAt,
            'status' => \App\Enums\Campaign\UnifiedCampaignStatus::SCHEDULED,
            'processed_contacts' => 0,
            'stats' => [],
        ]);

        $this->line("Campaign {$campaign->id} rescheduled to {$scheduleAt->toDateTimeString()}");
        \Illuminate\Support\Facades\Log::info("Recurring campaign {$campaign->id} rescheduled to {$scheduleAt->toDateTimeString()}");
    }
}
