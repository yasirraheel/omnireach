<?php

namespace App\Services\Campaign;

use App\Enums\Campaign\CampaignChannel;
use App\Enums\Campaign\DispatchStatus;
use App\Enums\Campaign\UnifiedCampaignStatus;
use App\Models\CampaignDispatch;
use App\Models\CampaignMessage;
use App\Models\Contact;
use App\Models\Gateway;
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
                $dispatch->markAsFailed('Missing required data');
                return false;
            }

            // Get personalized content
            $content = $message->getPersonalizedContent($contact);

            // Send based on channel
            $result = match ($dispatch->channel) {
                CampaignChannel::SMS => $this->sendSms($dispatch, $gateway, $contact, $content),
                CampaignChannel::EMAIL => $this->sendEmail($dispatch, $gateway, $contact, $content, $message),
                CampaignChannel::WHATSAPP => $this->sendWhatsApp($dispatch, $gateway, $contact, $content, $message),
                default => false,
            };

            if ($result) {
                $dispatch->markAsSent();
                $this->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'sent');
                return true;
            }

            return false;
        } catch (\Exception $e) {
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
     * Send SMS
     */
    protected function sendSms(
        CampaignDispatch $dispatch,
        Gateway $gateway,
        Contact $contact,
        string $content
    ): bool {
        $phone = $contact->sms_contact;

        if (empty($phone)) {
            $dispatch->markAsFailed('No phone number');
            return false;
        }

        // Use existing SMS sending infrastructure
        $smsService = app(\App\Service\SmsService::class);

        try {
            $result = $smsService->sendSMS(
                gateway: $gateway,
                phone: $phone,
                message: $content
            );

            if ($result['status'] ?? false) {
                $dispatch->addMetadata('provider_response', $result['response'] ?? null);
                return true;
            }

            $dispatch->markAsFailed($result['message'] ?? 'SMS sending failed');
            return false;
        } catch (\Exception $e) {
            $dispatch->markAsFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Send Email
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
            $dispatch->markAsFailed('No email address');
            return false;
        }

        $subject = $message->getPersonalizedSubject($contact);

        // Use existing email sending infrastructure
        $emailService = app(\App\Service\MailService::class);

        try {
            $result = $emailService->sendMail(
                gateway: $gateway,
                email: $email,
                subject: $subject,
                message: $content
            );

            if ($result['status'] ?? false) {
                $dispatch->addMetadata('provider_response', $result['response'] ?? null);
                return true;
            }

            $dispatch->markAsFailed($result['message'] ?? 'Email sending failed');
            return false;
        } catch (\Exception $e) {
            $dispatch->markAsFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Send WhatsApp message
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
            $dispatch->markAsFailed('No WhatsApp number');
            return false;
        }

        // Use existing WhatsApp sending infrastructure
        $whatsappService = app(\App\Service\WhatsAppService::class);

        try {
            $payload = [
                'phone' => $phone,
                'message' => $content,
            ];

            // Add attachments if any
            if ($message->hasAttachments()) {
                $payload['attachments'] = $message->attachments;
            }

            // Add template if using Cloud API
            if ($message->template_id) {
                $payload['template_id'] = $message->template_id;
            }

            $result = $whatsappService->sendMessage($gateway, $payload);

            if ($result['status'] ?? false) {
                $dispatch->addMetadata('provider_response', $result['response'] ?? null);
                $dispatch->addMetadata('message_id', $result['message_id'] ?? null);
                return true;
            }

            $dispatch->markAsFailed($result['message'] ?? 'WhatsApp sending failed');
            return false;
        } catch (\Exception $e) {
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
        $failed = 0;

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
            'failed' => $failed,
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
            'hour' => "DATE_FORMAT(sent_at, '%Y-%m-%d %H:00')",
            'day' => "DATE(sent_at)",
            default => "DATE_FORMAT(sent_at, '%Y-%m-%d %H:00')",
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
