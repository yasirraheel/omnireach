<?php

namespace App\Http\Controllers\Admin\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use App\Models\MetaConfiguration;
use App\Models\WhatsappClientOnboarding;
use App\Services\WhatsApp\HealthCheckService;
use App\Services\WhatsApp\MetaEmbeddedSignupService;
use App\Services\WhatsApp\SystemUserTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MetaConfigurationController extends Controller
{
    /**
     * Display list of Meta configurations
     */
    public function index(): View
    {
        $title = translate('WhatsApp Cloud API Configurations');
        $configurations = MetaConfiguration::withCount(['gateways', 'clientOnboardings'])
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(paginateNumber());

        $healthService = new HealthCheckService();
        $healthSummary = $healthService->getHealthSummary();

        // Count configurations missing config_id for compliance warning
        $missingConfigIdCount = MetaConfiguration::whereNull('config_id')->count();

        return view('admin.whatsapp.configuration.index', compact(
            'title',
            'configurations',
            'healthSummary',
            'missingConfigIdCount'
        ));
    }

    /**
     * Show create configuration form
     */
    public function create(): View
    {
        $title = translate('Add Meta Configuration');

        return view('admin.whatsapp.configuration.create', compact('title'));
    }

    /**
     * Store new configuration
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'app_id' => 'required|string|max:255',
            'app_secret' => 'required|string|max:500',
            'config_id' => 'nullable|string|max:255',
            'business_manager_id' => 'nullable|string|max:255',
            'tech_provider_id' => 'nullable|string|max:255',
            'solution_id' => 'nullable|string|max:255',
            'system_user_id' => 'nullable|string|max:255',
            'api_version' => 'required|string|max:20',
            'environment' => 'required|in:sandbox,production',
            'is_default' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            // Auto-set as default if this is the first configuration
            $isDefault = $request->boolean('is_default');
            if (!$isDefault && MetaConfiguration::count() === 0) {
                $isDefault = true;
            }

            $configuration = MetaConfiguration::create([
                'name' => $request->input('name'),
                'app_id' => $request->input('app_id'),
                'app_secret' => $request->input('app_secret'),
                'config_id' => $request->input('config_id'),
                'business_manager_id' => $request->input('business_manager_id'),
                'tech_provider_id' => $request->input('tech_provider_id'),
                'solution_id' => $request->input('solution_id'),
                'system_user_id' => $request->input('system_user_id'),
                'api_version' => $request->input('api_version', 'v24.0'),
                'environment' => $request->input('environment', 'production'),
                'is_default' => $isDefault,
                'webhook_callback_url' => route('webhook'),
                'permissions' => [
                    'whatsapp_business_messaging',
                    'whatsapp_business_management',
                    'business_management',
                ],
                'status' => 'active',
            ]);

            DB::commit();

            $notify[] = ['success', translate('Meta configuration created successfully')];
            return redirect()->route('admin.whatsapp.configuration.index')->withNotify($notify);
        } catch (\Exception $e) {
            DB::rollBack();
            $notify[] = ['error', translate('Failed to create configuration: ') . $e->getMessage()];
            return back()->withNotify($notify)->withInput();
        }
    }

    /**
     * Show edit configuration form
     */
    public function edit(string $uid): View
    {
        $title = translate('Edit Meta Configuration');
        $configuration = MetaConfiguration::where('uid', $uid)->firstOrFail();

        return view('admin.whatsapp.configuration.edit', compact('title', 'configuration'));
    }

    /**
     * Update configuration
     */
    public function update(Request $request, string $uid): RedirectResponse
    {
        $configuration = MetaConfiguration::where('uid', $uid)->firstOrFail();

        $request->validate([
            'name' => 'required|string|max:255',
            'app_id' => 'required|string|max:255',
            'app_secret' => 'nullable|string|max:500',
            'config_id' => 'nullable|string|max:255',
            'business_manager_id' => 'nullable|string|max:255',
            'tech_provider_id' => 'nullable|string|max:255',
            'solution_id' => 'nullable|string|max:255',
            'system_user_id' => 'nullable|string|max:255',
            'api_version' => 'required|string|max:20',
            'environment' => 'required|in:sandbox,production',
            'is_default' => 'nullable|boolean',
        ]);

        $updateData = [
            'name' => $request->input('name'),
            'app_id' => $request->input('app_id'),
            'config_id' => $request->input('config_id'),
            'business_manager_id' => $request->input('business_manager_id'),
            'tech_provider_id' => $request->input('tech_provider_id'),
            'solution_id' => $request->input('solution_id'),
            'system_user_id' => $request->input('system_user_id'),
            'api_version' => $request->input('api_version'),
            'environment' => $request->input('environment'),
            'is_default' => $request->boolean('is_default'),
        ];

        // Only update secret if provided
        if ($request->filled('app_secret')) {
            $updateData['app_secret'] = $request->input('app_secret');
        }

        $configuration->update($updateData);

        $notify[] = ['success', translate('Meta configuration updated successfully')];
        return redirect()->route('admin.whatsapp.configuration.index')->withNotify($notify);
    }

    /**
     * Delete configuration
     */
    public function destroy(string $uid): RedirectResponse
    {
        $configuration = MetaConfiguration::where('uid', $uid)->firstOrFail();

        // Check if any gateways are using this configuration
        if ($configuration->gateways()->count() > 0) {
            $notify[] = ['error', translate('Cannot delete configuration with active gateways')];
            return back()->withNotify($notify);
        }

        $configuration->delete();

        $notify[] = ['success', translate('Meta configuration deleted successfully')];
        return redirect()->route('admin.whatsapp.configuration.index')->withNotify($notify);
    }

    /**
     * Toggle configuration status
     */
    public function toggleStatus(Request $request): JsonResponse
    {
        try {
            $configuration = MetaConfiguration::findOrFail($request->input('id'));
            $configuration->status = $configuration->status === 'active' ? 'inactive' : 'active';
            $configuration->save();

            return response()->json([
                'status' => true,
                'message' => translate('Status updated successfully'),
                'reload' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => translate('Failed to update status'),
            ], 500);
        }
    }

    /**
     * Set as default configuration
     */
    public function setDefault(string $uid): RedirectResponse
    {
        $configuration = MetaConfiguration::where('uid', $uid)->firstOrFail();

        // Remove default from all others
        MetaConfiguration::where('id', '!=', $configuration->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $configuration->update(['is_default' => true]);

        $notify[] = ['success', translate('Default configuration updated')];
        return back()->withNotify($notify);
    }

    /**
     * Test configuration connectivity
     */
    public function testConnection(string $uid): JsonResponse
    {
        $configuration = MetaConfiguration::where('uid', $uid)->firstOrFail();
        $tokenService = new SystemUserTokenService($configuration);

        // If system user token exists, validate it
        if ($configuration->system_user_token) {
            $result = $tokenService->validateToken($configuration->system_user_token);
            return response()->json($result);
        }

        // Test app credentials by attempting debug token
        $result = $tokenService->debugToken($configuration->app_id . '|' . $configuration->app_secret);

        return response()->json([
            'success' => true,
            'message' => translate('App credentials are valid'),
            'data' => $result['data'] ?? null
        ]);
    }

    /**
     * Generate new webhook verify token
     */
    public function regenerateWebhookToken(string $uid): JsonResponse
    {
        $configuration = MetaConfiguration::where('uid', $uid)->firstOrFail();
        $newToken = bin2hex(random_bytes(16));

        $configuration->update(['webhook_verify_token' => $newToken]);

        return response()->json([
            'success' => true,
            'token' => $newToken,
            'message' => translate('Webhook token regenerated')
        ]);
    }

    /**
     * Show onboarding dashboard
     */
    public function onboardingDashboard(): View
    {
        $title = translate('Client Onboarding Dashboard');

        $onboardings = WhatsappClientOnboarding::with(['user', 'gateway', 'metaConfiguration'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $stats = [
            'total' => WhatsappClientOnboarding::count(),
            'completed' => WhatsappClientOnboarding::completed()->count(),
            'pending' => WhatsappClientOnboarding::pending()->count(),
            'failed' => WhatsappClientOnboarding::failed()->count(),
        ];

        return view('admin.whatsapp.onboarding.index', compact('title', 'onboardings', 'stats'));
    }

    /**
     * View onboarding details
     */
    public function onboardingDetails(string $uid): View
    {
        $title = translate('Onboarding Details');
        $onboarding = WhatsappClientOnboarding::with(['user', 'gateway', 'metaConfiguration'])
            ->where('uid', $uid)
            ->firstOrFail();

        return view('admin.whatsapp.onboarding.details', compact('title', 'onboarding'));
    }

    /**
     * Retry failed onboarding
     */
    public function retryOnboarding(string $uid): RedirectResponse
    {
        $onboarding = WhatsappClientOnboarding::where('uid', $uid)->firstOrFail();

        if (!$onboarding->canRetry()) {
            $notify[] = ['error', translate('This onboarding cannot be retried')];
            return back()->withNotify($notify);
        }

        // Reset status to initiated
        $onboarding->update([
            'onboarding_status' => WhatsappClientOnboarding::STATUS_INITIATED,
            'last_error_message' => null,
        ]);

        $notify[] = ['success', translate('Onboarding reset. User can try again.')];
        return back()->withNotify($notify);
    }

    /**
     * Health dashboard - Cloud API gateways only (admin owned)
     */
    public function healthDashboard(): View
    {
        $title = translate('WhatsApp Cloud API Health Monitor');
        $healthService = new HealthCheckService();

        $summary = $healthService->getHealthSummary();
        $needsAttention = $healthService->getGatewaysNeedingAttention();

        // Only show admin-owned Cloud API gateways
        $gateways = Gateway::cloudApi()
            ->whereNull('user_id')
            ->with('metaConfiguration')
            ->orderByRaw("CASE
                WHEN health_status = 'unhealthy' THEN 1
                WHEN health_status = 'degraded' THEN 2
                WHEN health_status IS NULL THEN 3
                WHEN health_status = 'unknown' THEN 4
                ELSE 5 END")
            ->paginate(20);

        return view('admin.whatsapp.health.index', compact(
            'title',
            'summary',
            'needsAttention',
            'gateways'
        ));
    }

    /**
     * Run health check for specific gateway
     */
    public function runHealthCheck(int $id): JsonResponse
    {
        $gateway = Gateway::findOrFail($id);
        $healthService = new HealthCheckService();

        $result = $healthService->checkGateway($gateway);

        return response()->json($result);
    }

    /**
     * Run health checks for all gateways
     */
    public function runAllHealthChecks(): JsonResponse
    {
        $healthService = new HealthCheckService();
        $results = $healthService->checkAllGateways();

        return response()->json([
            'success' => true,
            'message' => translate("Health check completed. {$results['healthy']} healthy, {$results['unhealthy']} unhealthy."),
            'data' => $results
        ]);
    }

    /**
     * Get setup wizard view
     */
    public function setupWizard(): View
    {
        $title = translate('WhatsApp Cloud API Setup Wizard');
        $hasConfiguration = MetaConfiguration::exists();
        $defaultConfig = MetaConfiguration::getDefault();

        return view('admin.whatsapp.configuration.wizard', compact(
            'title',
            'hasConfiguration',
            'defaultConfig'
        ));
    }

    /**
     * Clear onboarding logs based on type
     */
    public function clearOnboardingLogs(Request $request): RedirectResponse
    {
        $type = $request->input('clear_type', 'all');
        $query = WhatsappClientOnboarding::query();
        $deletedCount = 0;

        switch ($type) {
            case 'completed':
                $deletedCount = $query->where('onboarding_status', 'completed')->delete();
                $message = $deletedCount . ' ' . translate('completed onboarding records cleared');
                break;

            case 'failed':
                $deletedCount = $query->where('onboarding_status', 'failed')->delete();
                $message = $deletedCount . ' ' . translate('failed onboarding records cleared');
                break;

            case 'older_30':
                $deletedCount = $query->where('created_at', '<', now()->subDays(30))->delete();
                $message = $deletedCount . ' ' . translate('records older than 30 days cleared');
                break;

            case 'older_90':
                $deletedCount = $query->where('created_at', '<', now()->subDays(90))->delete();
                $message = $deletedCount . ' ' . translate('records older than 90 days cleared');
                break;

            case 'all':
            default:
                $deletedCount = $query->delete();
                $message = $deletedCount . ' ' . translate('onboarding records cleared');
                break;
        }

        $notify[] = ['success', $message];
        return back()->withNotify($notify);
    }

    /**
     * Delete single onboarding record
     */
    public function deleteOnboarding(string $uid): RedirectResponse
    {
        $onboarding = WhatsappClientOnboarding::where('uid', $uid)->firstOrFail();
        $onboarding->delete();

        $notify[] = ['success', translate('Onboarding record deleted successfully')];
        return back()->withNotify($notify);
    }
}
