<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Enums\System\ChannelTypeEnum;
use App\Managers\ThemeManager;

class SubscriptionController extends Controller
{
    /**
     * Show the public subscription form.
     */
    public function showSubscribeForm(Request $request, $user_uid, $group_uid = null, ThemeManager $themeManager)
    {
        $user = User::where('uid', $user_uid)->firstOrFail();
        $group = null;
        if ($group_uid) {
            $group = ContactGroup::where('uid', $group_uid)->where('user_id', $user->id)->first();
        }

        return view($themeManager->view('sections.subscribe'), [
            'user' => $user,
            'group' => $group,
            'title' => translate('Subscribe to our list')
        ]);
    }

    /**
     * Handle the subscription form submission (Double Opt-In).
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'user_uid' => 'required|exists:users,uid',
            'group_uid' => 'nullable|exists:contact_groups,uid',
            'first_name' => 'nullable|string|max:90',
            'last_name' => 'nullable|string|max:90',
        ]);

        $user = User::where('uid', $request->user_uid)->firstOrFail();
        $group_id = null;
        if ($request->group_uid) {
            $group = ContactGroup::where('uid', $request->group_uid)->where('user_id', $user->id)->first();
            $group_id = $group ? $group->id : null;
        }

        $email = $request->email;

        // Check if contact already exists for this user
        $contact = Contact::where('user_id', $user->id)->where('email_contact', $email)->first();

        if (!$contact) {
            $contact = new Contact();
            $contact->user_id = $user->id;
            $contact->email_contact = $email;
            $contact->uid = generateUid();
        }

        $contact->first_name = $request->first_name;
        $contact->last_name = $request->last_name;
        $contact->group_id = $group_id;
        $contact->status = 'inactive'; // Double opt-in requires them to be inactive initially
        $contact->email_verification = 'unverified';
        $contact->save();

        // Generate verification hash
        $hash = hash('sha256', $contact->uid . $contact->email_contact . env('APP_KEY'));

        // We would normally send an email here using the user's default gateway.
        // For now, we will dispatch an email with the verification link.
        $verificationUrl = route('subscribe.verify', ['uid' => $contact->uid, 'hash' => $hash]);

        try {
            // A basic standard mail to verify.
            \Illuminate\Support\Facades\Mail::raw(
                "Please click the following link to verify your subscription: \n\n" . $verificationUrl,
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('Verify your subscription');
                }
            );
        } catch (\Exception $e) {
            // Log or handle mail failure
            \Illuminate\Support\Facades\Log::error('Failed to send verification email: ' . $e->getMessage());
        }

        return back()->with('success', translate('Please check your email to verify your subscription.'));
    }

    /**
     * Verify the subscription.
     */
    public function verifySubscription(Request $request, $uid, $hash, ThemeManager $themeManager)
    {
        $contact = Contact::where('uid', $uid)->firstOrFail();
        $expectedHash = hash('sha256', $contact->uid . $contact->email_contact . env('APP_KEY'));

        if (!hash_equals($expectedHash, $hash)) {
            abort(403, 'Invalid verification link.');
        }

        $contact->status = 'active';
        $contact->email_verification = 'verified';
        $contact->is_subscribed = 1;
        $contact->save();

        // Remove from suppressions if it was there
        DB::table('email_suppressions')
            ->where('user_id', $contact->user_id)
            ->where('email_address', $contact->email_contact)
            ->delete();

        return view($themeManager->view('sections.subscribe-success'), [
            'title' => translate('Subscription Verified'),
            'message' => translate('Your subscription has been verified successfully.'),
            'logo' => asset('images/subscribe-logo.png')
        ]);
    }

    /**
     * Handle general unsubscribe.
     */
    public function unsubscribeGeneral(Request $request, $user_uid, $email, $hash, ThemeManager $themeManager)
    {
        $user = User::where('uid', $user_uid)->firstOrFail();
        $expectedHash = hash('sha256', $user->uid . $email . env('APP_KEY'));

        if (!hash_equals($expectedHash, $hash)) {
            abort(403, 'Invalid unsubscribe link.');
        }

        // Add to email_suppressions
        DB::table('email_suppressions')->updateOrInsert(
            [
                'email_address' => $email,
                'user_id' => $user->id,
            ],
            [
                'uid' => generateUid(),
                'reason' => 'unsubscribe',
                'source' => 'system',
                'created_at' => now(),
            ]
        );

        // Also set contact to inactive and unsubscribed
        Contact::where('user_id', $user->id)
            ->where('email_contact', $email)
            ->update([
                'status' => 'inactive',
                'is_subscribed' => 0
            ]);

        return view($themeManager->view('sections.subscribe-success'), [
            'title' => translate('Unsubscribed'),
            'message' => translate('You have been successfully unsubscribed from all future emails from this sender.'),
            'logo' => asset('images/unsubscribe-logo.png')
        ]);
    }

    /**
     * Handle contact unsubscribe via dynamic URL.
     */
    public function contactUnsubscribe(Request $request, $contact_uid, $hash, ThemeManager $themeManager)
    {
        $contact = Contact::where('uid', $contact_uid)->firstOrFail();
        $expectedHash = hash('sha256', $contact->uid . env('APP_KEY'));

        if (!hash_equals($expectedHash, $hash)) {
            abort(403, 'Invalid unsubscribe link.');
        }

        $contact->unsubscribe();

        return view($themeManager->view('sections.subscribe-success'), [
            'title' => translate('Unsubscribed'),
            'message' => translate('We\'re sad to see you go! You have been successfully unsubscribed from future messages.'),
            'logo' => asset('images/unsubscribe-logo.png') // Or a sad face logo if we have one
        ]);
    }
}
