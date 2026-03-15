<?php

namespace App\Http\Controllers;

use App\Models\DispatchLog;
use App\Models\EmailTrackingEvent;
use App\Models\ContactEngagement;
use App\Models\TrackingDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TrackingController extends Controller
{
    /**
     * 1x1 transparent GIF pixel for open tracking.
     */
    private const TRANSPARENT_GIF = "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00\x21\xf9\x04\x01\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3b";

    /**
     * Track email open via pixel.
     *
     * GET /t/o/{token}
     */
    public function trackOpen(Request $request, string $token)
    {
        try {
            $data = $this->decodeToken($token);
            if (!$data) {
                return $this->pixelResponse();
            }

            $dispatchLog = DispatchLog::find($data['dispatch_log_id']);
            if (!$dispatchLog) {
                return $this->pixelResponse();
            }

            EmailTrackingEvent::create([
                'dispatch_log_id' => $dispatchLog->id,
                'contact_id' => $dispatchLog->contact_id,
                'campaign_id' => $dispatchLog->campaign_id,
                'user_id' => $dispatchLog->user_id,
                'event_type' => 'open',
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500),
                'created_at' => now(),
            ]);

            $this->updateEngagement($dispatchLog, 'opened');

        } catch (\Exception $e) {
            Log::debug("Tracking open error: " . $e->getMessage());
        }

        return $this->pixelResponse();
    }

    /**
     * Track email click and redirect to original URL.
     *
     * GET /t/c/{token}
     */
    public function trackClick(Request $request, string $token)
    {
        try {
            $data = $this->decodeToken($token);
            if (!$data || empty($data['url'])) {
                return redirect('/');
            }

            $url = $data['url'];

            $dispatchLog = DispatchLog::find($data['dispatch_log_id']);
            if ($dispatchLog) {
                EmailTrackingEvent::create([
                    'dispatch_log_id' => $dispatchLog->id,
                    'contact_id' => $dispatchLog->contact_id,
                    'campaign_id' => $dispatchLog->campaign_id,
                    'user_id' => $dispatchLog->user_id,
                    'event_type' => 'click',
                    'url' => substr($url, 0, 2048),
                    'ip_address' => $request->ip(),
                    'user_agent' => substr($request->userAgent() ?? '', 0, 500),
                    'created_at' => now(),
                ]);

                $this->updateEngagement($dispatchLog, 'clicked');
            }

            return redirect()->away($url);

        } catch (\Exception $e) {
            Log::debug("Tracking click error: " . $e->getMessage());
            return redirect('/');
        }
    }

    /**
     * Decode a tracking token.
     * Token format: base64(json({dispatch_log_id, url?}))
     */
    private function decodeToken(string $token): ?array
    {
        try {
            $decoded = base64_decode(strtr($token, '-_', '+/'), true);
            if (!$decoded) return null;

            $data = json_decode($decoded, true);
            if (!is_array($data) || empty($data['dispatch_log_id'])) return null;

            return $data;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Encode a tracking token.
     */
    public static function encodeToken(array $data): string
    {
        return strtr(base64_encode(json_encode($data)), '+/', '-_');
    }

    /**
     * Generate open tracking pixel URL.
     */
    public static function openPixelUrl(int $dispatchLogId, ?int $userId = null): string
    {
        $token = self::encodeToken(['dispatch_log_id' => $dispatchLogId]);
        $baseUrl = TrackingDomain::getTrackingBaseUrl($userId);
        return "{$baseUrl}/t/o/{$token}";
    }

    /**
     * Generate click tracking URL.
     */
    public static function clickTrackingUrl(int $dispatchLogId, string $originalUrl, ?int $userId = null): string
    {
        $token = self::encodeToken([
            'dispatch_log_id' => $dispatchLogId,
            'url' => $originalUrl,
        ]);
        $baseUrl = TrackingDomain::getTrackingBaseUrl($userId);
        return "{$baseUrl}/t/c/{$token}";
    }

    /**
     * Inject tracking pixel and rewrite links in email HTML.
     * Skips injection when the tracking base URL is not publicly reachable.
     */
    public static function injectTracking(string $html, int $dispatchLogId, ?int $userId = null): string
    {
        $baseUrl = TrackingDomain::getTrackingBaseUrl($userId);
        $host = parse_url($baseUrl, PHP_URL_HOST);

        // Skip tracking for local/non-public domains that won't resolve from the internet
        if ($host && (
            preg_match('/\.(test|local|localhost|example|invalid|internal)$/i', $host) ||
            in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0']) ||
            str_ends_with($host, '.local')
        )) {
            return $html;
        }

        // Inject open tracking pixel before </body> or at end
        $pixelUrl = self::openPixelUrl($dispatchLogId, $userId);
        $pixel = '<img src="' . htmlspecialchars($pixelUrl) . '" width="1" height="1" alt="" style="display:none;border:0;" />';

        if (stripos($html, '</body>') !== false) {
            $html = preg_replace('/<\/body>/i', $pixel . '</body>', $html, 1);
        } else {
            $html .= $pixel;
        }

        // Rewrite <a href="..."> links for click tracking
        // Skip mailto:, tel:, #, unsubscribe links, and tracking URLs
        $html = preg_replace_callback(
            '/<a\s([^>]*?)href=["\']([^"\']+)["\']/i',
            function ($matches) use ($dispatchLogId, $userId) {
                $attrs = $matches[1];
                $url = $matches[2];

                // Skip special URLs
                if (preg_match('/^(mailto:|tel:|#|javascript:)/i', $url)) {
                    return $matches[0];
                }
                // Skip unsubscribe links
                if (str_contains($url, 'unsubscribe')) {
                    return $matches[0];
                }
                // Skip tracking URLs (avoid double-wrapping)
                if (str_contains($url, '/t/c/') || str_contains($url, '/t/o/')) {
                    return $matches[0];
                }

                $trackingUrl = self::clickTrackingUrl($dispatchLogId, $url, $userId);
                return '<a ' . $attrs . 'href="' . htmlspecialchars($trackingUrl) . '"';
            },
            $html
        );

        return $html;
    }

    /**
     * Return a 1x1 transparent GIF response.
     */
    private function pixelResponse(): Response
    {
        return response(self::TRANSPARENT_GIF, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => strlen(self::TRANSPARENT_GIF),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
        ]);
    }

    /**
     * Update contact engagement counters.
     */
    private function updateEngagement(DispatchLog $dispatchLog, string $type): void
    {
        if (!$dispatchLog->contact_id || !$dispatchLog->user_id) {
            return;
        }

        $engagement = ContactEngagement::firstOrCreate(
            [
                'contact_id' => $dispatchLog->contact_id,
                'channel' => 'email',
            ],
            [
                'user_id' => $dispatchLog->user_id,
            ]
        );

        $engagement->recordEngagement($type);

        // Update optimal time patterns
        $now = now();
        $engagement->updateOptimalPatterns($now->hour, $now->dayOfWeek);
    }
}
