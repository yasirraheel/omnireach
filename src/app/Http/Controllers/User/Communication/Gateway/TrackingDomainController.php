<?php

namespace App\Http\Controllers\User\Communication\Gateway;

use Exception;
use App\Models\TrackingDomain;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;

class TrackingDomainController extends Controller
{
    /**
     * Display tracking domains list.
     */
    public function index(): View
    {
        Session::put("menu_active", false);
        $title = translate("Tracking Domains");
        $user = auth()->user();

        $plan = $user->runningSubscription()?->currentPlan();
        $maxDomains = (int) ($plan?->email?->max_tracking_domains ?? site_settings('max_tracking_domains_per_user', 2));
        $currentCount = TrackingDomain::forUser($user->id)->count();

        $domains = TrackingDomain::forUser($user->id)
            ->search(['domain'])
            ->filter(['status'])
            ->date()
            ->latest()
            ->paginate(paginateNumber(site_settings("paginate_number")));

        return view('user.gateway.tracking_domain.index', compact('title', 'domains', 'maxDomains', 'currentCount'));
    }

    /**
     * Store a new tracking domain.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $plan = $user->runningSubscription()?->currentPlan();
        $maxDomains = (int) ($plan?->email?->max_tracking_domains ?? site_settings('max_tracking_domains_per_user', 2));
        $currentCount = TrackingDomain::forUser($user->id)->count();

        if ($currentCount >= $maxDomains) {
            $notify[] = ['error', translate('You have reached the maximum number of tracking domains')];
            return back()->withNotify($notify);
        }

        $request->validate([
            'domain' => 'required|string|max:255|regex:/^[a-zA-Z0-9][a-zA-Z0-9.-]*\.[a-zA-Z]{2,}$/',
        ]);

        // Check uniqueness for this user
        $exists = TrackingDomain::where('domain', strtolower($request->input('domain')))
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            $notify[] = ['error', translate('This tracking domain already exists')];
            return back()->withNotify($notify);
        }

        try {
            $appDomain = parse_url(config('app.url'), PHP_URL_HOST);

            TrackingDomain::create([
                'domain' => strtolower($request->input('domain')),
                'user_id' => $user->id,
                'status' => 'pending',
            ]);

            $notify[] = ['success', translate('Tracking domain added. Please add a CNAME record pointing to') . " {$appDomain}"];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * Verify CNAME record for a tracking domain.
     */
    public function verify(string $uid): JsonResponse
    {
        try {
            $user = auth()->user();
            $domain = TrackingDomain::where('uid', $uid)->where('user_id', $user->id)->firstOrFail();
            $verified = $domain->verifyCname();

            if ($verified) {
                $domain->update([
                    'status' => 'active',
                    'verified_at' => now(),
                ]);
            }

            return response()->json([
                'status' => $verified,
                'message' => $verified
                    ? translate('CNAME verified! Tracking domain is now active.')
                    : translate('CNAME not found. Please ensure the DNS record is configured correctly.'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ]);
        }
    }

    /**
     * Delete a tracking domain.
     */
    public function destroy(string $uid): RedirectResponse
    {
        try {
            $user = auth()->user();
            $domain = TrackingDomain::where('uid', $uid)->where('user_id', $user->id)->firstOrFail();
            $domain->delete();
            $notify[] = ['success', translate('Tracking domain deleted')];
        } catch (Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e->getMessage())];
        }

        return back()->withNotify($notify);
    }
}
