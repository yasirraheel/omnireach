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
            // Mark as processing
            $dispatch->markAsProcessing();

            $message = $dispatch->campaignMessage;
            $contact = $dispatch->contact;
            $gateway = $dispatch->gateway;

            if (!$message || !$contact || !$gateway) {
                $dispatch->markAsFailed('Missing required data: ' . (!$message ? 'message' : (!$contact ? 'contact' : 'gateway')));
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
                $dispatch->markAsSent();
                $this->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'sent');
                return true;
            }

            // Ensure stats are updated even if it failed gracefully without throwing
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

            // SendSMS::send returns bool; pass null for dispatchLog so it won't try to update a DispatchLog record
            $success = $sendSMS->send($provider, $phone, $gateway, null, $content);

            if (!$success) {
                $dispatch->markAsFailed('SMS sending failed via provider: ' . $provider);
            }

            return $success;
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
            $sendMail = new SendMail();
            $attachments = $message->hasAttachments() ? $message->attachments : null;

            // SendMail::send returns bool; pass null for dispatchLog
            $success = $sendMail->send($gateway, $email, $subject, $content, null, $attachments);

            if (!$success) {
                $dispatch->markAsFailed('Email sending failed');
            }

            return $success;
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

            // Build a minimal Message-like object for SendWhatsapp
            // SendWhatsapp::send expects: Gateway, string|array $to, DispatchLog|Collection $dispatchLog, Message $message, string $body
            // We create a fake Message model-like object from campaign message data
            $fakeMessage = new Message();
            $fakeMessage->message    = $content;
            $fakeMessage->file_info  = $message->hasAttachments() ? ['attachments' => $message->attachments] : null;
            $fakeMessage->subject    = $message->subject ?? null;

            // SendWhatsapp::send returns bool; pass null for dispatchLog so it won't try to update DispatchLog
            $success = $sendWhatsapp->send($gateway, $phone, null, $fakeMessage, $content);

            if (!$success) {
                $dispatch->markAsFailed('WhatsApp sending failed');
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
        $pending = $campaign->dispatches()
            ->whereIn('status', [DispatchStatus::PENDING, DispatchStatus::QUEUED, DispatchStatus::PROCESSING])
            ->count();

        if ($pending === 0 && $campaign->status === UnifiedCampaignStatus::RUNNING) {
            $campaign->markAsCompleted();
        }
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
