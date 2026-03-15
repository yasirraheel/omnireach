<?php

namespace App\Providers;

use App\Enums\Common\Status;
use App\Enums\CommunicationStatusEnum;
use App\Enums\ServiceType;
use App\Enums\StatusEnum;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\TemplateApprovalStatusEnum;
use App\Enums\WithdrawLogEnum;
use App\Models\CommunicationLog;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Laravel\Passport\Passport;
use App\Models\PaymentLog;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\Language;
use App\Models\Template;
use App\Models\User;
use App\Models\WithdrawLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     * @return void
     */
    public function register()
    {

    }

    /**
     * Bootstrap any application services.
     * OPTIMIZED: Reduced DB queries, added caching
     *
     * @return void
     */
    public function boot()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        // Force HTTPS for secure connections (handles proxy/tunnel scenarios)
        $this->configureHttps();

        // Add missing env variables for older installations (runs once, then cached)
        $this->ensureEnvVariables();

        try {
            // Only register routes and paginator - no DB queries here
            Passport::routes();
            Paginator::useBootstrap();

            // Cache languages for 1 hour instead of querying on every request
            $view['top_bar_languages'] = \Illuminate\Support\Facades\Cache::remember(
                'top_bar_languages',
                now()->addHour(),
                fn() => Language::where("status", StatusEnum::TRUE->status())->get()
            );

            // Remove users query from every request - not needed in global view
            $view['users'] = collect();

            // Set default language only once per session
            if (!Session::has('lang')) {
                $default_language = \Illuminate\Support\Facades\Cache::remember(
                    'default_language_model',
                    now()->addDay(),
                    fn() => Language::where('is_default', StatusEnum::TRUE->status())->first()
                );

                $fallbackLocale = config('app.locale', 'en');
                if ($default_language && is_object($default_language)) {
                    session()->put('lang', $default_language->code ?? $fallbackLocale);
                } else {
                    session()->put('lang', $fallbackLocale);
                }
            }

            view()->share($view);

            // Admin sidebar - cache counts for 5 minutes to reduce DB load
            view()->composer('admin.partials.sidebar', function ($view) {
                $counts = \Illuminate\Support\Facades\Cache::remember(
                    'admin_sidebar_counts',
                    now()->addMinutes(5),
                    function () {
                        return [
                            'pending_sms_count'             => 0,
                            'pending_whatsapp_count'        => 0,
                            'pending_email_count'           => 0,
                            'running_support_ticket_count'  => SupportTicket::where('status', 1)->count(),
                            'answered_support_ticket_count' => SupportTicket::where('status', 2)->count(),
                            'replied_support_ticket_count'  => SupportTicket::where('status', 3)->count(),
                            'closed_support_ticket_count'   => SupportTicket::where('status', 4)->count(),
                            'pending_manual_payment_count'  => PaymentLog::where('status', (string) StatusEnum::TRUE->status())->count(),
                            'pending_withdraw_payment_count' => WithdrawLog::where('status', WithdrawLogEnum::PENDING->value)->count(),
                            'sms_template_request'          => Template::whereNotNull('user_id')
                                                                        ->where([
                                                                            'channel'         => ChannelTypeEnum::SMS,
                                                                            'approval_status' => TemplateApprovalStatusEnum::PENDING
                                                                        ])->count(),
                            'email_template_request'        => Template::whereNotNull('user_id')
                                                                        ->where([
                                                                            'channel'         => ChannelTypeEnum::EMAIL,
                                                                            'approval_status' => TemplateApprovalStatusEnum::PENDING
                                                                        ])->count(),
                        ];
                    }
                );
                $view->with($counts);
            });

            // User sidebar - cache counts for 5 minutes
            view()->composer('user.partials.sidebar', function ($view) {
                $counts = \Illuminate\Support\Facades\Cache::remember(
                    'user_sidebar_counts',
                    now()->addMinutes(5),
                    fn() => [
                        'replied_support_ticket_count'  => SupportTicket::where('status', 3)->count(),
                        'answered_support_ticket_count' => SupportTicket::where('status', 2)->count(),
                    ]
                );
                $view->with($counts);
            });

            Validator::extend('username_format', function ($attribute, $value, $parameters, $validator) {
                return preg_match('/^[a-z]+(?:_[a-z]+)*$/', $value);
            });

            Validator::replacer('username_format', function ($message, $attribute, $rule, $parameters) {
                return str_replace(':attribute', $attribute, 'The :attribute must be in lowercase with underscores.');
            });

        } catch (Exception $ex) {
            // Log error but don't break the application
            \Illuminate\Support\Facades\Log::error('AppServiceProvider boot error: ' . $ex->getMessage());
        }
    }

    /**
     * Configure HTTPS for secure connections
     * Handles proxy/tunnel/load balancer scenarios where the app
     * receives HTTP requests but the original request was HTTPS
     */
    private function configureHttps(): void
    {
        try {
            // Check various indicators that the request should be HTTPS
            $isSecure = false;

            // 1. Check X-Forwarded-Proto header (common for proxies/load balancers)
            if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                $isSecure = true;
            }

            // 2. Check X-Forwarded-Ssl header
            if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
                $isSecure = true;
            }

            // 3. Check if HTTPS is directly set
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                $isSecure = true;
            }

            // 4. Check standard port 443
            if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
                $isSecure = true;
            }

            // 5. Check if APP_URL starts with https
            $appUrl = config('app.url', '');
            if (str_starts_with($appUrl, 'https://')) {
                $isSecure = true;
            }

            // 6. Check if request host is different from local (.test, localhost, 127.0.0.1)
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $isLocalHost = str_ends_with($host, '.test')
                || str_ends_with($host, '.local')
                || $host === 'localhost'
                || str_starts_with($host, '127.0.0.1')
                || str_starts_with($host, '192.168.');

            // If not local and environment is production, assume HTTPS
            if (!$isLocalHost && config('app.env') === 'production') {
                $isSecure = true;
            }

            // Force HTTPS if any secure indicator is detected
            if ($isSecure) {
                URL::forceScheme('https');
            }

        } catch (Exception $e) {
            // Silently fail - don't break the application
        }
    }

    /**
     * Ensure required env variables exist (for older installations)
     * Only runs once per deployment using file cache marker
     */
    private function ensureEnvVariables(): void
    {
        try {
            // Skip if already checked (cache marker)
            $markerFile = storage_path('framework/cache/.env_checked');
            if (File::exists($markerFile)) {
                return;
            }

            $envFile = base_path('.env');
            if (!File::exists($envFile)) {
                return;
            }

            $envContent = file_get_contents($envFile);
            $updated = false;

            // Variables to ensure exist (for backward compatibility)
            $requiredVariables = [
                'WP_SERVER_URL' => 'http://127.0.0.1:3001',
                'WP_SERVER_HOST' => '127.0.0.1',
                'WP_SERVER_PORT' => '3001',
                'WP_API_KEY' => '',
                'MAX_RETRIES' => '5',
                'RECONNECT_INTERVAL' => '5000',
                'NODE_TLS_REJECT_UNAUTHORIZED' => '1',
                'QUEUE_CONNECTION' => 'database',
            ];

            // Also fix old QUEUE_DRIVER to QUEUE_CONNECTION
            if (Str::contains($envContent, 'QUEUE_DRIVER=') && !Str::contains($envContent, 'QUEUE_CONNECTION=')) {
                $envContent = preg_replace('/QUEUE_DRIVER=sync/', 'QUEUE_CONNECTION=database', $envContent);
                $updated = true;
            }

            foreach ($requiredVariables as $variable => $value) {
                if (!Str::contains($envContent, $variable . '=')) {
                    $envContent .= PHP_EOL . $variable . '=' . $value;
                    $updated = true;
                }
            }

            if ($updated) {
                file_put_contents($envFile, $envContent);
            }

            // Sync purchase credentials from .env to database (for older installations)
            $this->syncPurchaseCredentials();

            // Create marker file to prevent running again
            File::put($markerFile, now()->toDateTimeString());

            // Push config to WhatsApp Node service after env is ready
            $this->pushConfigToNodeService();

        } catch (Exception $e) {
            // Silently fail - don't break the application
        }
    }

    /**
     * Push configuration to WhatsApp Node service
     * This ensures Node service has latest config after Laravel boot
     */
    private function pushConfigToNodeService(): void
    {
        try {
            // Only push if not already cached (prevents excessive calls)
            if (\Illuminate\Support\Facades\Cache::has('wp_node_configured')) {
                return;
            }

            // Check if Node service is configured
            $serverUrl = env('WP_SERVER_URL');
            if (empty($serverUrl)) {
                return;
            }

            // Use a queue job to avoid blocking boot
            dispatch(function () {
                try {
                    $nodeService = new \App\Services\System\Communication\NodeService();
                    $nodeService->pushConfigToNode();
                } catch (Exception $e) {
                    \Illuminate\Support\Facades\Log::debug('Node config push deferred: ' . $e->getMessage());
                }
            })->afterResponse();

        } catch (Exception $e) {
            // Silently fail
        }
    }

    /**
     * Sync purchase credentials from .env to database
     * This ensures WhatsApp Node service can access them
     */
    private function syncPurchaseCredentials(): void
    {
        try {
            // Check if purchase_key exists in database
            $existingKey = Setting::where('key', 'purchase_key')->first();
            if ($existingKey && !empty($existingKey->value)) {
                return; // Already synced
            }

            // Get from env
            $purchaseKey = env('PURCHASE_KEY', '');
            $envatoUsername = env('ENVATO_USERNAME', '');

            if (!empty($purchaseKey)) {
                Setting::updateOrInsert(
                    ['key' => 'purchase_key'],
                    ['value' => $purchaseKey]
                );
            }

            if (!empty($envatoUsername)) {
                Setting::updateOrInsert(
                    ['key' => 'envato_username'],
                    ['value' => $envatoUsername]
                );
            }

        } catch (Exception $e) {
            // Silently fail
        }
    }
}
