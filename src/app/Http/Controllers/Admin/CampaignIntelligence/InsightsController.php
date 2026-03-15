<?php

namespace App\Http\Controllers\Admin\CampaignIntelligence;

use App\Http\Controllers\Controller;
use App\Models\CampaignInsight;
use App\Models\ContactEngagement;
use App\Models\UnifiedCampaign;
use App\Services\CampaignIntelligence\InsightsService;
use App\Services\CampaignIntelligence\SendTimeOptimizerService;
use App\Services\CampaignIntelligence\ContentOptimizerService;
use Illuminate\Http\Request;

class InsightsController extends Controller
{
    protected InsightsService $insightsService;
    protected SendTimeOptimizerService $sendTimeService;
    protected ContentOptimizerService $contentService;

    public function __construct(
        InsightsService $insightsService,
        SendTimeOptimizerService $sendTimeService,
        ContentOptimizerService $contentService
    ) {
        $this->insightsService = $insightsService;
        $this->sendTimeService = $sendTimeService;
        $this->contentService = $contentService;
    }

    /**
     * Display the insights dashboard
     */
    public function index(Request $request)
    {
        $title = translate('Campaign Intelligence');

        // Get recent campaigns with insights
        $campaigns = UnifiedCampaign::with('insight')
            ->whereIn('status', ['completed', 'running'])
            ->latest()
            ->limit(10)
            ->get();

        // Get overall statistics
        $totalCampaigns = UnifiedCampaign::count();
        $completedCampaigns = UnifiedCampaign::where('status', 'completed')->count();
        $totalContacts = ContactEngagement::distinct('contact_id')->count();
        $avgEngagement = ContactEngagement::avg('engagement_score') ?? 0;

        // Get best performing campaigns
        $topCampaigns = UnifiedCampaign::where('status', 'completed')
            ->orderByRaw('CASE WHEN total_contacts > 0 THEN processed_contacts / total_contacts ELSE 0 END DESC')
            ->limit(5)
            ->get();

        return view('admin.campaign_intelligence.insights.index', compact(
            'title',
            'campaigns',
            'totalCampaigns',
            'completedCampaigns',
            'totalContacts',
            'avgEngagement',
            'topCampaigns'
        ));
    }

    /**
     * Show insights for a specific campaign
     */
    public function show($campaignId)
    {
        $campaign = UnifiedCampaign::with(['messages', 'dispatches', 'insight'])
            ->findOrFail($campaignId);

        $title = translate('Campaign Insights') . ': ' . $campaign->name;

        // Generate fresh insights
        $insight = $this->insightsService->generateInsights($campaign);

        // Get real-time stats
        $realTimeStats = $this->insightsService->getRealTimeStats($campaign);

        return view('admin.campaign_intelligence.insights.show', compact(
            'title',
            'campaign',
            'insight',
            'realTimeStats'
        ));
    }

    /**
     * Compare multiple campaigns
     */
    public function compare(Request $request)
    {
        $title = translate('Compare Campaigns');

        $campaignIds = $request->input('campaigns', []);
        $comparison = [];

        if (!empty($campaignIds)) {
            $comparison = $this->insightsService->compareCampaigns($campaignIds);
        }

        $availableCampaigns = UnifiedCampaign::where('status', 'completed')
            ->latest()
            ->limit(50)
            ->get();

        return view('admin.campaign_intelligence.insights.compare', compact(
            'title',
            'comparison',
            'availableCampaigns',
            'campaignIds'
        ));
    }

    /**
     * Show send time optimization insights
     */
    public function sendTimeOptimization(Request $request)
    {
        $title = translate('Send Time Optimization');

        $channel = $request->input('channel', 'email');

        // Get engagement statistics (admin view - all users)
        $engagementStats = ContactEngagement::selectRaw('
                channel,
                COUNT(*) as total_contacts,
                AVG(engagement_score) as avg_score,
                SUM(total_sent) as total_sent,
                SUM(total_opened) as total_opened
            ')
            ->groupBy('channel')
            ->get()
            ->keyBy('channel');

        // Get aggregated optimal hours across all contacts
        $optimalHours = $this->getAggregatedOptimalHours($channel);
        $optimalDays = $this->getAggregatedOptimalDays($channel);

        return view('admin.campaign_intelligence.insights.send_time', compact(
            'title',
            'channel',
            'engagementStats',
            'optimalHours',
            'optimalDays'
        ));
    }

    /**
     * Analyze content for a campaign message
     */
    public function analyzeContent(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
            'channel' => 'required|in:sms,email,whatsapp',
            'subject' => 'nullable|string',
        ]);

        $analysis = $this->contentService->analyzeContent(
            $request->content,
            $request->channel,
            $request->subject
        );

        return response()->json($analysis);
    }

    /**
     * Refresh insights for a campaign
     */
    public function refresh($campaignId)
    {
        $campaign = UnifiedCampaign::findOrFail($campaignId);
        $insight = $this->insightsService->generateInsights($campaign);

        return response()->json([
            'success' => true,
            'insight' => $insight,
            'message' => translate('Insights refreshed successfully'),
        ]);
    }

    /**
     * Get real-time stats via AJAX
     */
    public function realTimeStats($campaignId)
    {
        $campaign = UnifiedCampaign::findOrFail($campaignId);
        $stats = $this->insightsService->getRealTimeStats($campaign);

        return response()->json($stats);
    }

    /**
     * Get aggregated optimal hours across all contacts
     */
    protected function getAggregatedOptimalHours(string $channel): array
    {
        $engagements = ContactEngagement::where('channel', $channel)
            ->whereNotNull('optimal_hours')
            ->get();

        $aggregated = array_fill(0, 24, 0);

        foreach ($engagements as $engagement) {
            $hours = $engagement->optimal_hours ?? [];
            foreach ($hours as $hour => $score) {
                $aggregated[$hour] += $score;
            }
        }

        // Normalize
        $total = array_sum($aggregated);
        if ($total > 0) {
            foreach ($aggregated as $hour => $score) {
                $aggregated[$hour] = round($score / $total * 100, 2);
            }
        }

        return $aggregated;
    }

    /**
     * Get aggregated optimal days across all contacts
     */
    protected function getAggregatedOptimalDays(string $channel): array
    {
        $engagements = ContactEngagement::where('channel', $channel)
            ->whereNotNull('optimal_days')
            ->get();

        $aggregated = array_fill(0, 7, 0);

        foreach ($engagements as $engagement) {
            $days = $engagement->optimal_days ?? [];
            foreach ($days as $day => $score) {
                $aggregated[$day] += $score;
            }
        }

        // Normalize
        $total = array_sum($aggregated);
        if ($total > 0) {
            foreach ($aggregated as $day => $score) {
                $aggregated[$day] = round($score / $total * 100, 2);
            }
        }

        return $aggregated;
    }

    /**
     * Export insights report
     */
    public function export($campaignId)
    {
        $campaign = UnifiedCampaign::with('insight')->findOrFail($campaignId);
        $insight = $campaign->insight ?? $this->insightsService->generateInsights($campaign);

        $data = [
            'campaign' => [
                'name' => $campaign->name,
                'status' => $campaign->status->value ?? $campaign->status,
                'total_contacts' => $campaign->total_contacts,
                'processed_contacts' => $campaign->processed_contacts,
                'channels' => $campaign->channels,
            ],
            'performance' => [
                'delivery_rate' => $insight->delivery_rate ?? 0,
                'open_rate' => $insight->open_rate ?? 0,
                'click_rate' => $insight->click_rate ?? 0,
                'reply_rate' => $insight->reply_rate ?? 0,
                'bounce_rate' => $insight->bounce_rate ?? 0,
            ],
            'recommendations' => $insight->ai_recommendations ?? [],
            'generated_at' => $insight->generated_at ?? now(),
        ];

        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="campaign-insights-' . $campaign->id . '.json"');
    }
}
