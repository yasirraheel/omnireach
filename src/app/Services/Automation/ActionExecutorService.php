<?php

namespace App\Services\Automation;

use App\Models\User;
use App\Models\Contact;
use App\Models\Gateway;
use App\Models\ContactGroup;
use App\Models\Automation\WorkflowNode;
use App\Models\Automation\WorkflowExecution;
use App\Enums\Common\Status;
use App\Enums\System\ChannelTypeEnum;
use App\Managers\GatewayManager;
use App\Services\System\Communication\NodeService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class ActionExecutorService
{
    protected GatewayManager $gatewayManager;
    protected NodeService $nodeService;

    public function __construct()
    {
        $this->gatewayManager = new GatewayManager();
        $this->nodeService = new NodeService();
    }

    /**
     * Execute an action node
     */
    public function execute(WorkflowNode $node, Contact $contact, WorkflowExecution $execution): array
    {
        $actionType = $node->action_type;
        $config = $node->config ?? [];
        $workflow = $execution->workflow;
        $user = $workflow->user;

        Log::info("Executing action", [
            'action_type' => $actionType,
            'node_id' => $node->id,
            'contact_id' => $contact->id,
        ]);

        return match ($actionType) {
            'send_sms' => $this->executeSendSms($config, $contact, $user),
            'send_email' => $this->executeSendEmail($config, $contact, $user),
            'send_whatsapp' => $this->executeSendWhatsapp($config, $contact, $user),
            'add_to_group' => $this->executeAddToGroup($config, $contact, $user),
            'remove_from_group' => $this->executeRemoveFromGroup($config, $contact, $user),
            'update_contact' => $this->executeUpdateContact($config, $contact),
            'add_tag' => $this->executeAddTag($config, $contact),
            'notify_admin' => $this->executeNotifyAdmin($config, $contact, $user, $execution),
            'call_webhook' => $this->executeCallWebhook($config, $contact, $execution),
            'simulate_typing' => $this->executeSimulateTyping($config, $contact, $user),
            'mark_as_read' => $this->executeMarkAsRead($config, $contact, $user, $execution),
            'whatsapp_engagement' => $this->executeWhatsappEngagement($config, $contact, $user, $execution),
            default => [
                'success' => false,
                'error' => "Unknown action type: {$actionType}",
            ],
        };
    }

    /**
     * Send SMS action
     */
    protected function executeSendSms(array $config, Contact $contact, ?User $user): array
    {
        $gatewayId = $config['gateway_id'] ?? null;
        $message = $this->parseMessage($config['message'] ?? '', $contact);
        $senderId = $config['sender_id'] ?? null;

        if (!$contact->sms_contact) {
            return [
                'success' => false,
                'error' => 'Contact has no SMS number',
            ];
        }

        $gateway = Gateway::where('id', $gatewayId)
            ->where('channel', ChannelTypeEnum::SMS->value)
            ->where('status', Status::ACTIVE->value)
            ->first();

        if (!$gateway) {
            return [
                'success' => false,
                'error' => 'SMS gateway not found or inactive',
            ];
        }

        try {
            // Use the existing dispatch system
            $dispatchData = [
                'gateway_id' => $gateway->id,
                'to' => $contact->sms_contact,
                'message' => $message,
                'sender_id' => $senderId,
            ];

            // Queue the message through the existing system
            $messageModel = \App\Models\Message::create([
                'user_id' => $user?->id,
                'type' => \App\Enums\System\ChannelTypeEnum::SMS->value,
                'message' => $message,
                'is_campaign' => false,
            ]);

            $dispatchLog = \App\Models\DispatchLog::create([
                'user_id' => $user?->id,
                'message_id' => $messageModel->id,
                'contact_id' => $contact->id,
                'type' => \App\Enums\System\ChannelTypeEnum::SMS->value,
                'gatewayable_id' => $gateway->id,
                'gatewayable_type' => \App\Models\Gateway::class,
                'status' => \App\Enums\System\CommunicationStatusEnum::PENDING->value,
                'priority' => \App\Enums\System\PriorityEnum::HIGH->value,
                'meta_data' => ['sender_id' => $senderId, 'source' => 'automation'],
                'retry_count' => 0,
            ]);

            \App\Jobs\ProcessDispatchLogBatch::dispatchSync([$dispatchLog->id], \App\Enums\System\ChannelTypeEnum::SMS, 'regular', false);

            return [
                'success' => true,
                'data' => ['to' => $contact->sms_contact, 'gateway' => $gateway->name],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send Email action
     */
    protected function executeSendEmail(array $config, Contact $contact, ?User $user): array
    {
        $gatewayId = $config['gateway_id'] ?? null;
        $subject = $this->parseMessage($config['subject'] ?? '', $contact);
        $message = $this->parseMessage($config['message'] ?? '', $contact);

        if (!$contact->email_contact) {
            return [
                'success' => false,
                'error' => 'Contact has no email address',
            ];
        }

        $gateway = Gateway::where('id', $gatewayId)
            ->where('channel', ChannelTypeEnum::EMAIL->value)
            ->where('status', Status::ACTIVE->value)
            ->first();

        if (!$gateway) {
            return [
                'success' => false,
                'error' => 'Email gateway not found or inactive',
            ];
        }

        try {
            // Queue the email through the existing system
            $messageModel = \App\Models\Message::create([
                'user_id' => $user?->id,
                'type' => \App\Enums\System\ChannelTypeEnum::EMAIL->value,
                'subject' => $subject,
                'main_body' => buildDomDocument($message),
                'is_campaign' => false,
            ]);

            $dispatchLog = \App\Models\DispatchLog::create([
                'user_id' => $user?->id,
                'message_id' => $messageModel->id,
                'contact_id' => $contact->id,
                'type' => \App\Enums\System\ChannelTypeEnum::EMAIL->value,
                'gatewayable_id' => $gateway->id,
                'gatewayable_type' => \App\Models\Gateway::class,
                'status' => \App\Enums\System\CommunicationStatusEnum::PENDING->value,
                'priority' => \App\Enums\System\PriorityEnum::HIGH->value,
                'meta_data' => ['source' => 'automation'],
                'retry_count' => 0,
            ]);

            \App\Jobs\ProcessDispatchLogBatch::dispatchSync([$dispatchLog->id], \App\Enums\System\ChannelTypeEnum::EMAIL, 'regular', false);

            return [
                'success' => true,
                'data' => ['to' => $contact->email_contact, 'subject' => $subject],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send WhatsApp action
     */
    protected function executeSendWhatsapp(array $config, Contact $contact, ?User $user): array
    {
        $deviceId = $config['device_id'] ?? null;
        $message = $this->parseMessage($config['message'] ?? '', $contact);
        $mediaUrl = $config['media_url'] ?? null;

        if (!$contact->whatsapp_contact) {
            return [
                'success' => false,
                'error' => 'Contact has no WhatsApp number',
            ];
        }

        $gateway = Gateway::where('id', $deviceId)
            ->where('channel', ChannelTypeEnum::WHATSAPP->value)
            ->where('status', Status::ACTIVE->value)
            ->first();

        if (!$gateway) {
            return [
                'success' => false,
                'error' => 'WhatsApp device not found or inactive',
            ];
        }

        try {
            // Queue the message through the existing system
            $messageModel = \App\Models\Message::create([
                'user_id' => $user?->id,
                'type' => \App\Enums\System\ChannelTypeEnum::WHATSAPP->value,
                'message' => $message,
                'file_info' => $mediaUrl ? ['attachments' => [['file_name' => basename($mediaUrl), 'file_path' => $mediaUrl, 'size' => 0, 'type' => '']]] : null,
                'is_campaign' => false,
            ]);

            $dispatchLog = \App\Models\DispatchLog::create([
                'user_id' => $user?->id,
                'message_id' => $messageModel->id,
                'contact_id' => $contact->id,
                'type' => \App\Enums\System\ChannelTypeEnum::WHATSAPP->value,
                'gatewayable_id' => $gateway->id,
                'gatewayable_type' => \App\Models\Gateway::class,
                'status' => \App\Enums\System\CommunicationStatusEnum::PENDING->value,
                'priority' => \App\Enums\System\PriorityEnum::HIGH->value,
                'meta_data' => ['source' => 'automation'],
                'retry_count' => 0,
            ]);

            \App\Jobs\ProcessDispatchLogBatch::dispatchSync([$dispatchLog->id], \App\Enums\System\ChannelTypeEnum::WHATSAPP, 'regular', false);

            return [
                'success' => true,
                'data' => ['to' => $contact->whatsapp_contact, 'device' => $gateway->name],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Simulate Typing action
     */
    protected function executeSimulateTyping(array $config, Contact $contact, ?User $user): array
    {
        $deviceId = $config['device_id'] ?? null;
        
        if (!$contact->whatsapp_contact) {
            return [
                'success' => false,
                'error' => 'Contact has no WhatsApp number',
            ];
        }

        $gateway = Gateway::where('id', $deviceId)
            ->where('channel', ChannelTypeEnum::WHATSAPP->value)
            ->where('status', Status::ACTIVE->value)
            ->first();

        if (!$gateway) {
            return [
                'success' => false,
                'error' => 'WhatsApp device not found or inactive',
            ];
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'X-API-Key' => env('WP_API_KEY'),
                    'Content-Type' => 'application/json',
                ])
                ->post(env('WP_SERVER_URL') . '/messages/presence', [
                    'sessionId' => $gateway->name,
                    'receiver' => $contact->whatsapp_contact,
                    'presence' => 'composing'
                ]);
            
            return [
                'success' => $response->successful(),
                'data' => ['to' => $contact->whatsapp_contact, 'device' => $gateway->name],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mark as Read action
     */
    protected function executeMarkAsRead(array $config, Contact $contact, ?User $user, WorkflowExecution $execution): array
    {
        $deviceId = $config['device_id'] ?? null;
        $triggerData = $execution->getContextValue('trigger_data') ?? [];
        $messageId = $triggerData['reply_data']['message_id'] ?? null;
        
        if (!$contact->whatsapp_contact || !$messageId) {
            return [
                'success' => false,
                'error' => 'No WhatsApp number or message ID to mark as read',
            ];
        }

        $gateway = Gateway::where('id', $deviceId)
            ->where('channel', ChannelTypeEnum::WHATSAPP->value)
            ->where('status', Status::ACTIVE->value)
            ->first();

        if (!$gateway) {
            return [
                'success' => false,
                'error' => 'WhatsApp device not found or inactive',
            ];
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'X-API-Key' => env('WP_API_KEY'),
                    'Content-Type' => 'application/json',
                ])
                ->post(env('WP_SERVER_URL') . '/messages/read', [
                    'sessionId' => $gateway->name,
                    'receiver' => $contact->whatsapp_contact,
                    'messageId' => $messageId
                ]);
            
            return [
                'success' => $response->successful(),
                'data' => ['to' => $contact->whatsapp_contact, 'message_id' => $messageId],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Combined WhatsApp Engagement: Mark as Read + Simulate Typing + Send Reply (with configurable delays)
     * All steps run synchronously in one shot — no cron, no queue delay.
     */
    protected function executeWhatsappEngagement(array $config, Contact $contact, ?User $user, WorkflowExecution $execution): array
    {
        $deviceId           = $config['device_id'] ?? null;
        $markAsRead         = (bool)($config['mark_as_read'] ?? true);
        $markAsReadDelay    = max(0, (int)($config['mark_as_read_delay'] ?? 1));
        $simulateTyping     = (bool)($config['simulate_typing'] ?? true);
        $typingDelay        = max(0, (int)($config['typing_delay'] ?? 2));
        $replyMessage       = $this->parseMessage(trim($config['reply_message'] ?? ''), $contact);
        $mediaUrl           = trim($config['reply_media_url'] ?? '');

        if (!$contact->whatsapp_contact) {
            return ['success' => false, 'error' => 'Contact has no WhatsApp number'];
        }

        $gateway = Gateway::where('id', $deviceId)
            ->where('channel', ChannelTypeEnum::WHATSAPP->value)
            ->where('status', Status::ACTIVE->value)
            ->first();

        if (!$gateway) {
            return ['success' => false, 'error' => 'WhatsApp device not found or inactive'];
        }

        $results = [];

        try {
            // ─── Step 1: Mark as Read ───────────────────────────────────────────
            if ($markAsRead) {
                if ($markAsReadDelay > 0) {
                    sleep($markAsReadDelay);
                }

                $triggerData = $execution->getContextValue('trigger_data') ?? [];
                $messageId   = $triggerData['reply_data']['message_id'] ?? null;

                if ($messageId) {
                    $readResp = Http::timeout(5)
                        ->withHeaders([
                            'X-API-Key'    => env('WP_API_KEY'),
                            'Content-Type' => 'application/json',
                        ])
                        ->post(env('WP_SERVER_URL') . '/messages/read', [
                            'sessionId' => $gateway->name,
                            'receiver'  => $contact->whatsapp_contact,
                            'messageId' => $messageId,
                        ]);
                    $results['mark_as_read'] = $readResp->successful();
                } else {
                    $results['mark_as_read']      = false;
                    $results['mark_as_read_note'] = 'No message ID in trigger data';
                }
            }

            // ─── Step 2: Simulate Typing ────────────────────────────────────────
            if ($simulateTyping) {
                if ($typingDelay > 0) {
                    sleep($typingDelay);
                }

                $typingResp = Http::timeout(5)
                    ->withHeaders([
                        'X-API-Key'    => env('WP_API_KEY'),
                        'Content-Type' => 'application/json',
                    ])
                    ->post(env('WP_SERVER_URL') . '/messages/presence', [
                        'sessionId' => $gateway->name,
                        'receiver'  => $contact->whatsapp_contact,
                        'presence'  => 'composing',
                    ]);
                $results['simulate_typing'] = $typingResp->successful();
            }

            // ─── Step 3: Send Reply (direct, no queue) ─────────────────────────
            if (!empty($replyMessage)) {
                $messagePayload = ['text' => $replyMessage];

                // Build final payload
                $sendPayload = [
                    'sessionId' => $gateway->name,
                    'receiver'  => $contact->whatsapp_contact,
                    'message'   => $messagePayload,
                    'delay'     => 0,
                ];

                // Attach media if provided
                if (!empty($mediaUrl)) {
                    $ext = strtolower(pathinfo(parse_url($mediaUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
                    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $videoExts = ['mp4', 'mov', 'avi', 'mkv'];
                    $audioExts = ['mp3', 'ogg', 'wav', 'aac', 'm4a'];

                    if (in_array($ext, $imageExts)) {
                        $sendPayload['message'] = ['image' => ['url' => $mediaUrl], 'caption' => $replyMessage];
                    } elseif (in_array($ext, $videoExts)) {
                        $sendPayload['message'] = ['video' => ['url' => $mediaUrl], 'caption' => $replyMessage];
                    } elseif (in_array($ext, $audioExts)) {
                        $sendPayload['message'] = ['audio' => ['url' => $mediaUrl], 'caption' => $replyMessage];
                    } else {
                        // Treat as document
                        $sendPayload['message'] = [
                            'document' => ['url' => $mediaUrl],
                            'caption'  => $replyMessage,
                            'fileName' => basename($mediaUrl),
                        ];
                    }
                }

                $sendResp = Http::timeout(15)
                    ->withHeaders([
                        'X-API-Key'    => env('WP_API_KEY'),
                        'Content-Type' => 'application/json',
                    ])
                    ->post(env('WP_SERVER_URL') . '/messages/send', $sendPayload);

                $results['reply_sent']    = $sendResp->successful();
                $results['reply_message'] = $replyMessage;

                if (!$sendResp->successful()) {
                    $results['reply_error'] = $sendResp->body();
                }
            }

            return [
                'success' => true,
                'data'    => array_merge(
                    ['to' => $contact->whatsapp_contact, 'device' => $gateway->name],
                    $results
                ),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Add contact to group action
     */
    protected function executeAddToGroup(array $config, Contact $contact, ?User $user): array
    {
        $groupId = $config['group_id'] ?? null;

        if (!$groupId) {
            return [
                'success' => false,
                'error' => 'Group ID not specified',
            ];
        }

        $group = ContactGroup::where('id', $groupId)
            ->when($user, fn($q) => $q->where('user_id', $user->id))
            ->first();

        if (!$group) {
            return [
                'success' => false,
                'error' => 'Group not found',
            ];
        }

        try {
            // Check if contact already in group
            $existsInGroup = Contact::where('group_id', $group->id)
                ->where(function ($q) use ($contact) {
                    if ($contact->email_contact) {
                        $q->orWhere('email_contact', $contact->email_contact);
                    }
                    if ($contact->sms_contact) {
                        $q->orWhere('sms_contact', $contact->sms_contact);
                    }
                    if ($contact->whatsapp_contact) {
                        $q->orWhere('whatsapp_contact', $contact->whatsapp_contact);
                    }
                })
                ->exists();

            if ($existsInGroup) {
                return [
                    'success' => true,
                    'data' => ['group' => $group->name, 'note' => 'Contact already in group'],
                ];
            }

            // Clone contact to new group
            $newContact = $contact->replicate();
            $newContact->uid = str_unique();
            $newContact->group_id = $group->id;
            $newContact->save();

            return [
                'success' => true,
                'data' => ['group' => $group->name, 'new_contact_id' => $newContact->id],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove contact from group action
     */
    protected function executeRemoveFromGroup(array $config, Contact $contact, ?User $user): array
    {
        $groupId = $config['group_id'] ?? null;

        if (!$groupId) {
            return [
                'success' => false,
                'error' => 'Group ID not specified',
            ];
        }

        try {
            // Find and remove contact from the specified group
            $deleted = Contact::where('group_id', $groupId)
                ->when($user, fn($q) => $q->where('user_id', $user->id))
                ->where(function ($q) use ($contact) {
                    if ($contact->email_contact) {
                        $q->orWhere('email_contact', $contact->email_contact);
                    }
                    if ($contact->sms_contact) {
                        $q->orWhere('sms_contact', $contact->sms_contact);
                    }
                    if ($contact->whatsapp_contact) {
                        $q->orWhere('whatsapp_contact', $contact->whatsapp_contact);
                    }
                })
                ->delete();

            return [
                'success' => true,
                'data' => ['removed' => $deleted > 0],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update contact field action
     */
    protected function executeUpdateContact(array $config, Contact $contact): array
    {
        $field = $config['field'] ?? null;
        $value = $config['value'] ?? null;

        if (!$field) {
            return [
                'success' => false,
                'error' => 'Field not specified',
            ];
        }

        try {
            // Handle standard fields
            $standardFields = ['first_name', 'last_name', 'email_contact', 'sms_contact', 'whatsapp_contact', 'status'];

            if (in_array($field, $standardFields)) {
                $contact->$field = $value;
                $contact->save();
            } else {
                // Handle meta_data fields
                $metaData = $contact->meta_data ? json_decode($contact->meta_data, true) : [];
                $metaData[$field] = ['value' => $value, 'type' => 'text'];
                $contact->meta_data = json_encode($metaData);
                $contact->save();
            }

            return [
                'success' => true,
                'data' => ['field' => $field, 'value' => $value],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add tag to contact action
     */
    protected function executeAddTag(array $config, Contact $contact): array
    {
        $tag = $config['tag'] ?? null;

        if (!$tag) {
            return [
                'success' => false,
                'error' => 'Tag not specified',
            ];
        }

        try {
            $metaData = $contact->meta_data ? json_decode($contact->meta_data, true) : [];
            $tags = $metaData['tags'] ?? [];

            if (!in_array($tag, $tags)) {
                $tags[] = $tag;
                $metaData['tags'] = $tags;
                $contact->meta_data = json_encode($metaData);
                $contact->save();
            }

            return [
                'success' => true,
                'data' => ['tag' => $tag, 'all_tags' => $tags],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Notify admin action
     */
    protected function executeNotifyAdmin(array $config, Contact $contact, ?User $user, WorkflowExecution $execution): array
    {
        $method = $config['method'] ?? 'email';
        $recipient = $config['recipient'] ?? null;
        $message = $this->parseMessage($config['message'] ?? '', $contact);

        if (!$recipient) {
            // Default to workflow owner email
            $recipient = $user?->email ?? site_settings('mail_from_address');
        }

        try {
            if ($method === 'email') {
                // Send email notification
                Mail::raw($message, function ($mail) use ($recipient, $execution) {
                    $mail->to($recipient)
                        ->subject("Workflow Notification: {$execution->workflow->name}");
                });
            } elseif ($method === 'webhook') {
                // Call webhook for notification
                Http::post($recipient, [
                    'workflow' => $execution->workflow->name,
                    'contact' => [
                        'id' => $contact->id,
                        'name' => trim("{$contact->first_name} {$contact->last_name}"),
                        'email' => $contact->email_contact,
                        'phone' => $contact->sms_contact ?? $contact->whatsapp_contact,
                    ],
                    'message' => $message,
                    'timestamp' => now()->toISOString(),
                ]);
            }

            return [
                'success' => true,
                'data' => ['method' => $method, 'recipient' => $recipient],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Call external webhook action
     */
    protected function executeCallWebhook(array $config, Contact $contact, WorkflowExecution $execution): array
    {
        $url = $config['url'] ?? null;
        $method = strtoupper($config['method'] ?? 'POST');
        $headers = $config['headers'] ?? [];
        $body = $config['body'] ?? [];

        if (!$url) {
            return [
                'success' => false,
                'error' => 'Webhook URL not specified',
            ];
        }

        try {
            // Build webhook payload
            $payload = array_merge($body, [
                'contact' => [
                    'id' => $contact->id,
                    'uid' => $contact->uid,
                    'first_name' => $contact->first_name,
                    'last_name' => $contact->last_name,
                    'email' => $contact->email_contact,
                    'phone' => $contact->sms_contact,
                    'whatsapp' => $contact->whatsapp_contact,
                    'meta_data' => $contact->meta_data ? json_decode($contact->meta_data, true) : [],
                ],
                'workflow' => [
                    'id' => $execution->workflow_id,
                    'name' => $execution->workflow->name,
                ],
                'execution_id' => $execution->uid,
                'timestamp' => now()->toISOString(),
            ]);

            // Parse any variables in the payload
            $payload = $this->parsePayload($payload, $contact);

            $response = match ($method) {
                'GET' => Http::withHeaders($headers)->get($url, $payload),
                'PUT' => Http::withHeaders($headers)->put($url, $payload),
                'PATCH' => Http::withHeaders($headers)->patch($url, $payload),
                default => Http::withHeaders($headers)->post($url, $payload),
            };

            return [
                'success' => $response->successful(),
                'data' => [
                    'status_code' => $response->status(),
                    'response' => $response->json() ?? $response->body(),
                ],
                'error' => $response->failed() ? "HTTP {$response->status()}" : null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Parse message with contact variables
     */
    protected function parseMessage(string $message, Contact $contact): string
    {
        $replacements = [
            '{{first_name}}' => $contact->first_name ?? '',
            '{{last_name}}' => $contact->last_name ?? '',
            '{{full_name}}' => trim("{$contact->first_name} {$contact->last_name}"),
            '{{email}}' => $contact->email_contact ?? '',
            '{{phone}}' => $contact->sms_contact ?? '',
            '{{whatsapp}}' => $contact->whatsapp_contact ?? '',
        ];

        // Add meta_data variables
        if ($contact->meta_data) {
            $metaData = json_decode($contact->meta_data, true);
            foreach ($metaData as $key => $value) {
                $val = is_array($value) ? ($value['value'] ?? '') : $value;
                $replacements["{{$key}}"] = $val;
            }
        }

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    /**
     * Parse payload recursively with contact variables
     */
    protected function parsePayload(array $payload, Contact $contact): array
    {
        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                $payload[$key] = $this->parseMessage($value, $contact);
            } elseif (is_array($value)) {
                $payload[$key] = $this->parsePayload($value, $contact);
            }
        }
        return $payload;
    }
}
