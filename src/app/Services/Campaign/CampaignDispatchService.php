<?php

namespace App\Services\Campaign;

use App\Enums\Campaign\CampaignChannel;
use App\Enums\Campaign\DispatchStatus;
use App\Enums\Campaign\UnifiedCampaignStatus;
use App\Http\Utility\SendMail;
use App\Http\Utility\SendSMS;
use App\Http\Utility\SendWhatsapp;
use App\Models\CampaignDispatch;
use App\Models\CampaignMessage;
use App\Models\Contact;
use App\Models\Gateway;
use App\Models\Message;
use App\Models\UnifiedCampaign;
use Illuminate\Support\Facades\Log;

class CampaignDispatchService
{
    /**
     * Process a single dispatch
     */
    public function processDispatch(CampaignDispatch $dispatch): bool
    {
        try {
            // Atomic lock update: ensure no parallel process or duplicate worker claims this dispatch
            $affected = CampaignDispatch::where('id', $dispatch->id)
                ->where('status', DispatchStatus::PENDING)
                ->update(['status' => DispatchStatus::PROCESSING]);

            if (!$affected) {
                // Dispatch was already claimed or processed by another worker
                return false;
            }

            $dispatch->refresh();

            $message = $dispatch->campaignMessage;
            $contact = $dispatch->contact;
            $gateway = $dispatch->gateway;

            if (!$message || !$contact || !$gateway) {
                $err = 'Missing required data: ' . (!$message ? 'message' : (!$contact ? 'contact' : 'gateway'));
                $dispatch->markAsFailed($err);
                $this->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'failed');
                return false;
            }

            // Get personalized content
            $content = $message->getPersonalizedContent($contact);

            // Send based on channel
            $result = match ($dispatch->channel) {
                CampaignChannel::SMS      => $this->sendSms($dispatch, $gateway, $contact, $content),
                CampaignChannel::EMAIL    => $this->sendEmail($dispatch, $gateway, $contact, $content, $message),
                CampaignChannel::WHATSAPP => $this->sendWhatsApp($dispatch, $gateway, $contact, $content, $message),
                default                   => false,
            };

            if ($result) {
                // For WhatsApp groups: treat SENT as terminal success (no delivery receipt expected)
                if ($dispatch->channel === CampaignChannel::WHATSAPP) {
                    // If not already marked as sent by sendWhatsApp(), mark it now
                    if ($dispatch->fresh()->status === DispatchStatus::PROCESSING) {
                        $dispatch->markAsSent();
                    }
                }
                $this->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'sent');
                return true;
            }

            // result is false — ensure dispatch is marked FAILED (sendWhatsApp/sendSms/sendEmail
            // may have already called markAsFailed, but guard in case they didn't)
            $fresh = $dispatch->fresh();
            if ($fresh && $fresh->status === DispatchStatus::PROCESSING) {
                $dispatch->markAsFailed('Send returned false');
            }
            $this->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'failed');
            return false;

        } catch (\Throwable $e) {
            Log::error('Dispatch error: ' . $e->getMessage(), [
                'dispatch_id' => $dispatch->id,
                'campaign_id' => $dispatch->campaign_id,
            ]);

            $dispatch->markAsFailed($e->getMessage());
            $this->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'failed');

            return false;
        }
    }

    /**
     * Send SMS using the existing SendSMS utility
     */
    protected function sendSms(
        CampaignDispatch $dispatch,
        Gateway $gateway,
        Contact $contact,
        string $content
    ): bool {
        $phone = $contact->sms_contact;

        if (empty($phone)) {
            $dispatch->markAsFailed('No SMS phone number for contact');
            return false;
        }

        try {
            $sendSMS  = new SendSMS();
            $provider = strtolower($gateway->type);
            $success  = $sendSMS->send($provider, $phone, $gateway, null, $content);
            return (bool) $success;
        } catch (\Throwable $e) {
            $dispatch->markAsFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Send Email using the existing SendMail utility
     */
    protected function sendEmail(
        CampaignDispatch $dispatch,
        Gateway $gateway,
        Contact $contact,
        string $content,
        CampaignMessage $message
    ): bool {
        $email = $contact->email_contact;

        if (empty($email)) {
            $dispatch->markAsFailed('No email address for contact');
            return false;
        }

        $subject = $message->getPersonalizedSubject($contact);

        try {
            $sendMail    = new SendMail();
            $attachments = $message->hasAttachments() ? $message->attachments : null;
            $success     = $sendMail->send($gateway, $email, $subject, $content, null, $attachments);
            return (bool) $success;
        } catch (\Throwable $e) {
            $dispatch->markAsFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Send WhatsApp message using the existing SendWhatsapp utility
     */
    protected function sendWhatsApp(
        CampaignDispatch $dispatch,
        Gateway $gateway,
        Contact $contact,
        string $content,
        CampaignMessage $message
    ): bool {
        $phone = $contact->whatsapp_contact;

        if (empty($phone)) {
            $dispatch->markAsFailed('No WhatsApp number for contact');
            return false;
        }

        try {
            $sendWhatsapp = new SendWhatsapp();

            // Calculate and apply exact anti-ban delay configured on the Gateway
            $delayMs = $sendWhatsapp->calculateAntiBanDelay($gateway);
            $sleepSec = (int) ceil($delayMs / 1000);
            if ($sleepSec > 0) {
                sleep($sleepSec);
            }

            $fakeMessage             = new Message();
            $fakeMessage->message   = $content;
            $fakeMessage->file_info = $message->hasAttachments() ? ['attachments' => $message->attachments] : null;
            $fakeMessage->subject   = $message->subject ?? null;

            // For WhatsApp groups there is no delivery receipt, so treat a successful send() as terminal success.
            // send() returns bool — true means the API accepted the message.
            $success = $sendWhatsapp->send($gateway, $phone, null, $fakeMessage, $content);

            if ($success) {
                // Immediately mark as SENT — we will never get a delivery webhook for groups
                $dispatch->markAsSent();
            } else {
                // Hard fail — no retries for WhatsApp groups
                $dispatch->markAsFailed('WhatsApp send returned false — message rejected by gateway');
            }

            return $success;
        } catch (\Throwable $e) {
            $dispatch->markAsFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Update campaign stats
     */
    protected function updateCampaignStats(UnifiedCampaign $campaign, string $channel, string $stat): void
    {
        $campaign->updateChannelStats($channel, [$stat => 1]);
        $campaign->incrementProcessed();

        // Check if campaign is complete
        $this->checkCampaignCompletion($campaign);
    }

    /**
     * Check if campaign is complete
     */
    protected function checkCampaignCompletion(UnifiedCampaign $campaign): void
    {
        // Force-fail any dispatches that have been stuck in PROCESSING for more than 5 minutes
        // This handles cases where a send() call hangs or returns without updating status
        $campaign->dispatches()
            ->where('status', DispatchStatus::PROCESSING)
            ->where('updated_at', '<', now()->subMinutes(5))
            ->update([
                'status'        => DispatchStatus::FAILED,
                'error_message' => 'Timed out in processing state',
            ]);

        $pending = $campaign->dispatches()
            ->whereIn('status', [DispatchStatus::PENDING, DispatchStatus::QUEUED, DispatchStatus::PROCESSING])
            ->count();

        if ($pending === 0 && $campaign->status === UnifiedCampaignStatus::RUNNING) {
            if ($campaign->type === \App\Enums\Campaign\CampaignType::RECURRING && !empty($campaign->recurring_config)) {
                $this->rescheduleCampaign($campaign);
            } else {
                $campaign->markAsCompleted();
            }
        }
    }

    /**
     * Record execution run history snapshot for a campaign (especially recurring)
     */
    public function recordRunHistory(UnifiedCampaign $campaign, ?\Carbon\Carbon $nextScheduleAt = null): void
    {
        try {
            $history = $campaign->dispatches()->with('contact')->get()->map(function ($d) {
                return [
                    'id' => $d->id,
                    'contact_name' => $d->contact?->name ?? 'Contact #' . $d->contact_id,
                    'contact_address' => $d->getContactAddress() ?? $d->contact?->whatsapp_contact ?? $d->contact?->sms_contact ?? $d->contact?->email_contact ?? '',
                    'channel' => is_object($d->channel) ? $d->channel->value : (string) $d->channel,
                    'status' => is_object($d->status) ? $d->status->value : (string) $d->status,
                    'error_message' => $d->error_message,
                    'sent_at' => $d->sent_at?->toDateTimeString(),
                    'delivered_at' => $d->delivered_at?->toDateTimeString(),
                    'updated_at' => $d->updated_at?->toDateTimeString(),
                ];
            })->toArray();

            $runNumber = \App\Models\UnifiedCampaignRun::where('campaign_id', $campaign->id)->count() + 1;
            $overallStats = $campaign->getOverallStats();

            \App\Models\UnifiedCampaignRun::create([
                'campaign_id' => $campaign->id,
                'run_number' => $runNumber,
                'status' => 'completed',
                'started_at' => $campaign->started_at ?? now(),
                'completed_at' => now(),
                'scheduled_at' => $campaign->schedule_at,
                'next_schedule_at' => $nextScheduleAt,
                'total_contacts' => $campaign->total_contacts,
                'processed_contacts' => $campaign->processed_contacts,
                'sent_count' => $overallStats['sent'] ?? 0,
                'failed_count' => $overallStats['failed'] ?? 0,
                'delivered_count' => $overallStats['delivered'] ?? 0,
                'dispatch_history' => $history,
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to record campaign run history for campaign {$campaign->id}: " . $e->getMessage());
        }
    }

    /**
     * Reschedule a recurring campaign
     */
    protected function rescheduleCampaign(UnifiedCampaign $campaign): void
    {
        $config     = $campaign->recurring_config;
        $repeatTime = (int) ($config['repeat_time'] ?? 1);
        $repeatFormat = $config['repeat_format'] ?? 'daily';

        $scheduleAt = \Carbon\Carbon::parse($campaign->schedule_at ?? now());

        // Add the correct interval based on the stored repeat_format value
        match ($repeatFormat) {
            'hourly'  => $scheduleAt->addHours($repeatTime),
            'daily'   => $scheduleAt->addDays($repeatTime),
            'weekly'  => $scheduleAt->addWeeks($repeatTime),
            'monthly' => $scheduleAt->addMonths($repeatTime),
            'yearly'  => $scheduleAt->addYears($repeatTime),
            default   => $scheduleAt->addDays($repeatTime),
        };

        // 1. Record execution run history snapshot BEFORE resetting dispatches and stats
        $this->recordRunHistory($campaign, $scheduleAt);

        // 2. Reset ALL dispatches back to PENDING for next run
        $campaign->dispatches()->update([
            'status'        => DispatchStatus::PENDING,
            'sent_at'       => null,
            'delivered_at'  => null,
            'error_message' => null,
            'retry_count'   => 0,
        ]);

        // 3. Reset campaign stats and set as scheduled for next run
        $campaign->update([
            'schedule_at'         => $scheduleAt,
            'status'              => UnifiedCampaignStatus::SCHEDULED,
            'processed_contacts'  => 0,
            'stats'               => [],
        ]);

        Log::info("Recurring campaign {$campaign->id} rescheduled to {$scheduleAt->toDateTimeString()}");
    }

    /**
     * Process batch of dispatches
     */
    public function processBatch(int $limit = 100): array
    {
        $dispatches = CampaignDispatch::readyToProcess()
            ->with(['campaign', 'campaignMessage', 'contact', 'gateway'])
            ->whereHas('campaign', function ($q) {
                $q->where('status', UnifiedCampaignStatus::RUNNING);
            })
            ->limit($limit)
            ->get();

        $processed = 0;
        $succeeded = 0;
        $failed    = 0;

        foreach ($dispatches as $dispatch) {
            $processed++;

            if ($this->processDispatch($dispatch)) {
                $succeeded++;
            } else {
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed'    => $failed,
        ];
    }

    /**
     * Retry failed dispatches
     */
    public function retryFailed(UnifiedCampaign $campaign, int $maxRetries = 3): int
    {
        $retryable = $campaign->dispatches()
            ->retryable($maxRetries)
            ->get();

        $count = 0;

        foreach ($retryable as $dispatch) {
            $dispatch->incrementRetry();
            $count++;
        }

        return $count;
    }

    /**
     * Handle delivery status callback
     */
    public function handleDeliveryStatus(string $messageId, string $status, ?string $error = null): bool
    {
        $dispatch = CampaignDispatch::where('meta_data->message_id', $messageId)->first();

        if (!$dispatch) {
            return false;
        }

        switch (strtolower($status)) {
            case 'delivered':
                $dispatch->markAsDelivered();
                $this->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'delivered');
                break;

            case 'read':
            case 'opened':
                $dispatch->markAsOpened();
                $this->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'opened');
                break;

            case 'clicked':
                $dispatch->markAsClicked();
                $this->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'clicked');
                break;

            case 'replied':
                $dispatch->markAsReplied();
                break;

            case 'failed':
            case 'undelivered':
                $dispatch->markAsFailed($error);
                $this->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'failed');
                break;

            case 'bounced':
                $dispatch->markAsBounced($error);
                $this->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'failed');
                break;
        }

        return true;
    }

    /**
     * Get dispatch rate statistics for a time period
     */
    public function getDispatchRateStats(UnifiedCampaign $campaign, string $period = 'hour'): array
    {
        $groupBy = match ($period) {
            'minute' => "DATE_FORMAT(sent_at, '%Y-%m-%d %H:%i')",
            'hour'   => "DATE_FORMAT(sent_at, '%Y-%m-%d %H:00')",
            'day'    => "DATE(sent_at)",
            default  => "DATE_FORMAT(sent_at, '%Y-%m-%d %H:00')",
        };

        return $campaign->dispatches()
            ->whereNotNull('sent_at')
            ->selectRaw("$groupBy as period, COUNT(*) as count")
            ->groupByRaw($groupBy)
            ->orderBy('period')
            ->pluck('count', 'period')
            ->toArray();
    }
}
