<?php
namespace App\Http\Utility;

use App\Enums\CommunicationStatusEnum;
use App\Enums\ServiceType;
use App\Enums\System\CommunicationStatusEnum as SystemCommunicationStatusEnum;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;
use App\Models\CampaignContact;
use App\Models\CommunicationLog;
use App\Models\DispatchLog;
use App\Models\Gateway;
use App\Models\Message;
use App\Models\Template;
use App\Models\User;
use App\Models\WhatsappCreditLog;
use App\Models\WhatsappDevice;
use App\Service\Admin\Core\CustomerService;
use App\Services\System\PhoneNumberService;
use App\Traits\Dispatchable;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsapp
{
    use Dispatchable;

    /**
     * Track message count per gateway for delay calculation
     * @var array
     */
    private static array $messageCounters = [];

    /**
     * send
     *
     * @param Gateway $gateway
     * @param array|string $to
     * @param array|Collection|DispatchLog $dispatchLog
     * @param Message $message
     * @param string $body
     *
     * @return bool
     */
    public function send(Gateway $gateway, array|string $to, array|Collection|DispatchLog $dispatchLog, Message $message, string $body): bool
    {
        $body = textSpinner($body);
        return $this->sendWithHandler($gateway, $to,$dispatchLog, $message, $body);
    }

    /**
     * Calculate delay for anti-ban protection based on gateway settings
     * Uses gateway's delay configuration to prevent WhatsApp number suspension
     *
     * @param Gateway $gateway
     * @return int Delay in milliseconds
     */
    private function calculateAntiBanDelay(Gateway $gateway): int
    {
        $gatewayId = $gateway->id;

        // Initialize counter for this gateway
        if (!isset(self::$messageCounters[$gatewayId])) {
            self::$messageCounters[$gatewayId] = [
                'count' => 0,
                'last_reset' => time(),
            ];
        }

        $counter = &self::$messageCounters[$gatewayId];
        $counter['count']++;

        // Get gateway delay settings (with defaults)
        $minDelay = (float) ($gateway->per_message_min_delay ?? 1); // seconds
        $maxDelay = (float) ($gateway->per_message_max_delay ?? 3); // seconds
        $delayAfterCount = (int) ($gateway->delay_after_count ?? 50);
        $delayAfterDuration = (float) ($gateway->delay_after_duration ?? 30); // seconds
        $resetAfterCount = (int) ($gateway->reset_after_count ?? 200);

        // Calculate base random delay (between min and max)
        $baseDelayMs = rand((int)($minDelay * 1000), (int)($maxDelay * 1000));

        // Check if we need to apply extended delay after count
        if ($delayAfterCount > 0 && $counter['count'] % $delayAfterCount === 0) {
            // Add extended delay to prevent banning
            $extendedDelayMs = (int)($delayAfterDuration * 1000);
            $baseDelayMs += $extendedDelayMs;

            Log::info("WhatsApp anti-ban: Extended delay applied", [
                'gateway_id' => $gatewayId,
                'message_count' => $counter['count'],
                'delay_ms' => $baseDelayMs,
            ]);
        }

        // Reset counter if reached reset threshold
        if ($resetAfterCount > 0 && $counter['count'] >= $resetAfterCount) {
            $counter['count'] = 0;
            $counter['last_reset'] = time();

            Log::info("WhatsApp anti-ban: Counter reset", [
                'gateway_id' => $gatewayId,
                'reset_after_count' => $resetAfterCount,
            ]);
        }

        return $baseDelayMs;
    }

    /**
     * sendWithHandler
     *
     * @param Gateway $gateway
     * @param array|string $to
     * @param array|Collection|DispatchLog $dispatchLog
     * @param Message $message
     * @param string $body
     * 
     * @return bool
     */
    public function sendWithHandler(Gateway $gateway, array|string $to, array|Collection|DispatchLog $dispatchLog, Message $message, string $body): bool
    {
        try {
            $success = false;
            if($gateway->type == WhatsAppGatewayTypeEnum::NODE->value) {
                
                $success = $this->sendNodeMessages($dispatchLog, $gateway, $message, $body, $to);
            } elseif ($gateway->type == WhatsAppGatewayTypeEnum::CLOUD->value) {
    
                $success = $this->sendCloudApiMessages($dispatchLog, $gateway, $message, $body, $to);
            }
            if ($success && $dispatchLog) {
                $this->markAsDelivered($dispatchLog);
            }
            return $success;
        } catch (Exception $e) {
            $this->fail($dispatchLog, $e->getMessage());
            return false;
        }
    }

    /**
     * Replace variables in message with contact data
     * Falls back to WhatsApp profile name if contact name is not in database
     *
     * @param string $messageData
     * @param DispatchLog $log
     * @param Gateway $gateway
     * @param string $receiver
     *
     * @return string
     */
    private function replaceMessageVariables(string $messageData, DispatchLog $log, Gateway $gateway, string $receiver): string {

        // Check if message has variables
        if (strpos($messageData, '{{') === false && strpos($messageData, '{') === false) {
            return $messageData;
        }

        // Get contact from dispatch log
        $contact = $log->contact;

        // Build replacement map with contact data
        $variables = [
            'phone' => $receiver,
            'name' => $receiver, // Default to phone number
            'email' => ''
        ];

        if ($contact) {
            if (!empty($contact->name)) {
                $variables['name'] = $contact->name;
            } elseif (!empty($contact->first_name) || !empty($contact->last_name)) {
                $variables['name'] = trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? ''));
            }

            $variables['phone'] = $contact->phone_number ?? $receiver;
            $variables['email'] = $contact->email ?? '';
        }

        // Replace both {{variable}} and {variable} patterns
        $message = $messageData;
        foreach ($variables as $key => $value) {
            $message = str_replace('{{' . $key . '}}', $value, $message);
            $message = str_replace('{' . $key . '}', $value, $message);
        }

        return $message;
    }

    /**
     * Get WhatsApp profile name from Node service
     *
     * @param Gateway $gateway
     * @param string $receiver
     *
     * @return string|null
     */
    private function getWhatsAppProfileName(Gateway $gateway, string $receiver): ?string {

        try {
            $apiURL = env('WP_SERVER_URL') . '/messages/get-contact';

            $postInput = [
                'sessionId' => $gateway->name,
                'number'    => $receiver
            ];

            $headers = [
                'Content-Type' => 'application/json',
                'X-API-Key'    => env('WP_API_KEY', ''),
            ];

            $response = Http::timeout(5)
                           ->withoutVerifying()
                           ->withHeaders($headers)
                           ->post($apiURL, $postInput);

            if ($response && $response->status() === 200) {
                $res = json_decode($response->getBody(), true);

                if (Arr::get($res, 'success') === true) {
                    // Try to get pushname (WhatsApp display name) or notify (contact name)
                    $contactData = Arr::get($res, 'data', []);
                    return Arr::get($contactData, 'pushname')
                        ?? Arr::get($contactData, 'notify')
                        ?? Arr::get($contactData, 'name')
                        ?? null;
                }
            }

            return null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Validate WhatsApp session before sending
     * Updates gateway status if session is invalid
     *
     * @param Gateway $gateway
     * @return array ['valid' => bool, 'error' => string|null]
     */
    private function validateSession(Gateway $gateway): array
    {
        try {
            $apiURL = env('WP_SERVER_URL') . '/sessions/status/' . $gateway->name;
            $headers = [
                'Content-Type' => 'application/json',
                'X-API-Key' => env('WP_API_KEY', ''),
            ];

            $response = Http::timeout(10)
                ->withoutVerifying()
                ->withHeaders($headers)
                ->get($apiURL);

            if ($response->status() === 200) {
                $res = json_decode($response->body(), true);

                // Check if session is authenticated
                if (Arr::get($res, 'data.isSession') === true) {
                    return ['valid' => true, 'error' => null];
                }
            }

            // Session not found or not connected - update gateway status
            $gateway->status = \App\Enums\Common\Status::INACTIVE;
            $gateway->save();

            // Clear any cached session status
            \Illuminate\Support\Facades\Cache::forget("whatsapp_session_{$gateway->id}");

            Log::warning("WhatsApp session invalid for gateway: {$gateway->name}", [
                'gateway_id' => $gateway->id,
                'response_status' => $response->status(),
            ]);

            return [
                'valid' => false,
                'error' => 'WhatsApp session disconnected. Please scan QR code to reconnect.'
            ];

        } catch (\Exception $e) {
            Log::error("Session validation failed: {$e->getMessage()}", [
                'gateway_id' => $gateway->id,
            ]);

            return [
                'valid' => false,
                'error' => 'Cannot connect to WhatsApp service. Please check if service is running.'
            ];
        }
    }

    /**
     * sendNodeMessages
     *
     * @param DispatchLog $log
     * @param Gateway $gateway
     * @param Message $message
     * @param string|array $to
     *
     * @return bool
     */
    public function sendNodeMessages(DispatchLog $log, Gateway $gateway, Message $message, string $messageData, string|array $to): bool {

        // Validate session before sending
        $sessionCheck = $this->validateSession($gateway);
        if (!$sessionCheck['valid']) {
            throw new Exception($sessionCheck['error']);
        }

        // Use PhoneNumberService for professional phone number handling
        // This handles all formats: +880xxx, 880xxx, 0xxx, with spaces, dashes, etc.
        $phoneResult = PhoneNumberService::prepareForSending($to, $gateway, false);

        if (!$phoneResult['success']) {
            throw new Exception($phoneResult['error'] ?? 'Invalid phone number format');
        }

        $receiver = $phoneResult['formatted'];

        // Replace variables in message
        $messageData = $this->replaceMessageVariables($messageData, $log, $gateway, $receiver);

        $body = [];
        if(!is_null($message->file_info)) {
            
            $url  = Arr::get($message->file_info, 'url_file', null);
            $type = Arr::get($message->file_info, 'type', null);
            $name = Arr::get($message->file_info, 'name', null);

            if(!filter_var($url, FILTER_VALIDATE_URL)) {

                $url = url($url);
            }
            
            if($type == "image" ) {

                $body = [
                    'image'    => [
                        'url'=>$url
                    ],
                    'mimetype' => 'image/jpeg',
                    'caption'  => $messageData,
                ];
            }

            elseif($type == "audio" ) {

                $body = [
                    'audio' => [
                        'url' => $url
                    ],
                    'caption' => $messageData,
                ];
            }

            elseif($type == "video" ) {

                $body = [
                    'video' => [

                        'url' => $url
                    ],
                    'caption' => $messageData,
                ];
            }

            elseif($type == "document" ) {

                $body = [
                    'document' => [
                        'url' => $url
                    ],
                    'mimetype' => 'application/pdf',
                    'fileName' => $name,
                    'caption'  => $messageData,
                ];
            }
        } else {

            $body['text'] = $messageData;
        }
        
        // Calculate anti-ban delay based on gateway settings
        $delayMs = $this->calculateAntiBanDelay($gateway);

        // Send API to new Node service with delay settings
        $response = null;
        $apiURL = env('WP_SERVER_URL') . '/messages/send';

        $postInput = [
            'sessionId' => $gateway->name,
            'receiver'  => $receiver,
            'message'   => $body,
            'delay'     => $delayMs, // Pass calculated delay to Node service
            // Pass gateway settings for enterprise anti-ban protection
            'antiBanConfig' => [
                'minDelay' => (float) ($gateway->per_message_min_delay ?? 1),
                'maxDelay' => (float) ($gateway->per_message_max_delay ?? 3),
                'delayAfterCount' => (int) ($gateway->delay_after_count ?? 50),
                'delayAfterDuration' => (float) ($gateway->delay_after_duration ?? 30),
                'resetAfterCount' => (int) ($gateway->reset_after_count ?? 200),
            ],
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'X-API-Key'    => env('WP_API_KEY', ''),
        ];

        // Apply delay before sending to spread out messages
        if ($delayMs > 0) {
            usleep($delayMs * 1000); // Convert to microseconds
        }

        $response = Http::timeout(30)->withoutVerifying()->withHeaders($headers)->post($apiURL, $postInput);

        if ($response && $response->status() === 200) {

            $res = json_decode($response->getBody(), true);
            if (!Arr::has($res, "success") || $res['success'] !== true) {
                $errorMessage = Arr::get($res, 'message', 'Unknown error');
                throw new Exception("Node service error: " . $errorMessage);
            }
        } else {
            $errorBody = json_decode($response->body(), true);
            $errorMessage = Arr::get($errorBody, 'message', 'Failed To Connect Gateway');
            throw new Exception($errorMessage);
        }
        return true;
    }

    /**
     * sendCloudApiMessages
     *
     * @param DispatchLog $log
     * @param Gateway $gateway
     * @param Message $message
     * @param string $body
     * @param string|array $to
     *
     * @return bool
     */
    public static function sendCloudApiMessages(DispatchLog $log, Gateway $gateway, Message $message, string $body, string|array $to): bool {

        $message                = $message->load(['template']);
        $template               = $message->template;

        if (!$template) {
            throw new Exception("Template not found for Cloud API message");
        }

        $default_credentials    = (object) config("setting.whatsapp_business_credentials.default");
        $gateway_credentials    = (object) $gateway->meta_data;
        $messageBody            = $message->message ? json_decode($message->message, true) : [];
        $apiVersion             = $gateway->getApiVersion();
        $url                    = "https://graph.facebook.com/{$apiVersion}/{$gateway_credentials->phone_number_id}/messages";

        $headers = [
            'Content-Type'  => 'application/json',
            'Authorization' => "Bearer {$gateway_credentials->user_access_token}",
        ];

        // Build template data
        $templateData = [
            "name" => $template->name,
            "language" => [
                "code" => Arr::get($template->template_data, 'language', 'en')
            ]
        ];

        // Only include components if there are variable substitutions
        if (!empty($messageBody) && is_array($messageBody)) {
            $templateData["components"] = $messageBody;
        }

        $data = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => $templateData
        ];

        Log::info("WhatsApp Cloud API Request", [
            'url' => $url,
            'to' => $to,
            'template' => $template->name,
            'has_components' => !empty($messageBody)
        ]);

        $response           = Http::timeout(30)->withHeaders($headers)->post($url, $data);
        $responseBody       = $response->body();
        $responseData       = json_decode($responseBody, true);

        if (!$response->successful()) {
            $errorMessage = Arr::get($responseData, 'error.message', 'Failed to dispatch via Cloud API');
            Log::error("WhatsApp Cloud API Error", [
                'error' => $errorMessage,
                'response' => $responseData
            ]);
            throw new Exception($errorMessage);
        }

        // Check for errors in successful response (some API errors return 200)
        if (Arr::has($responseData, 'error.message')) {
            $errorMessage = Arr::get($responseData, 'error.message');
            Log::error("WhatsApp Cloud API Error in Response", [
                'error' => $errorMessage,
                'response' => $responseData
            ]);
            throw new Exception($errorMessage);
        }

        // Success - update log and return true
        $log->response_message = $responseData;
        $log->status           = SystemCommunicationStatusEnum::PROCESSING->value;
        $log->update();

        Log::info("WhatsApp Cloud API Success", [
            'message_id' => Arr::get($responseData, 'messages.0.id'),
            'dispatch_log_id' => $log->id
        ]);

        return true;
    }

    /**
     * @param CommunicationLog $log
     * @param $status
     * @param $errorMessage
     * @return void
     */
    public static function addedCreditLog(CommunicationLog $log, $status, $errorMessage = null): void {
        
        $log->status           = $status;
        $log->response_message = !is_null($errorMessage) ? $errorMessage : null;
        $log->save();
        $user = User::find($log->user_id);

        if ($user && $status == CommunicationStatusEnum::FAIL->value) {

            if($log->whatsapp_template_id) {

                CustomerService::addedCreditLog($user, 1, ServiceType::WHATSAPP->value);
            } else {

                $messages    = str_split($log->message["message_body"], site_settings('whatsapp_word_count'));
                $totalcredit = count($messages);
                CustomerService::addedCreditLog($user, $totalcredit, ServiceType::WHATSAPP->value);
            }
        }
    }

    private static function processFailed($whatsapp_log, $message = "Failed To Connect Gateway") {

        $status = (string) CommunicationStatusEnum::FAIL->value;
        if($whatsapp_log->user_id) {

            SendWhatsapp::addedCreditLog($whatsapp_log, $status, $message);
        } else {

            $whatsapp_log->response_message = $message;
            $whatsapp_log->status = $status;
            $whatsapp_log->save();
        }
    }
}
