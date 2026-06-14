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
        if ($user_uid === 'admin') {
            $user = (object)['id' => null, 'uid' => 'admin', 'name' => 'Admin'];
            $group = null;
            if ($group_uid) {
                $group = ContactGroup::where(function($q) use ($group_uid) {
                    $q->where('uid', $group_uid)->orWhere('id', $group_uid);
                })->whereNull('user_id')->first();
            }
        } else {
            $user = User::where('uid', $user_uid)->orWhere('id', $user_uid)->firstOrFail();
            $group = null;
            if ($group_uid) {
                $group = ContactGroup::where(function($q) use ($group_uid) {
                    $q->where('uid', $group_uid)->orWhere('id', $group_uid);
                })->where('user_id', $user->id)->first();
            }
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
            'user_uid' => 'required',
            'group_uid' => 'nullable',
            'first_name' => 'nullable|string|max:90',
            'last_name' => 'nullable|string|max:90',
        ]);

        $user_id = null;
        if ($request->user_uid === 'admin') {
            $group_id = null;
            if ($request->group_uid) {
                $group = ContactGroup::where(function($q) use ($request) {
                    $q->where('uid', $request->group_uid)->orWhere('id', $request->group_uid);
                })->whereNull('user_id')->first();
                $group_id = $group ? $group->id : null;
            }
        } else {
            $user = User::where('uid', $request->user_uid)->orWhere('id', $request->user_uid)->firstOrFail();
            $user_id = $user->id;
            $group_id = null;
            if ($request->group_uid) {
                $group = ContactGroup::where(function($q) use ($request) {
                    $q->where('uid', $request->group_uid)->orWhere('id', $request->group_uid);
                })->where('user_id', $user->id)->first();
                $group_id = $group ? $group->id : null;
            }
        }

        $email = $request->email;

        // Check if contact already exists for this user
        $contact = Contact::where('user_id', $user_id)->where('email_contact', $email)->first();

        $needsVerification = true;

        if (!$contact) {
            $contact = new Contact();
            $contact->user_id = $user_id;
            $contact->email_contact = $email;
            $contact->uid = generateUid();
        } else {
            if ($contact->email_verification === 'verified' && $contact->status === 'active') {
                $needsVerification = false;
            }
        }

        $contact->first_name = $request->first_name;
        $contact->last_name = $request->last_name;
        $contact->group_id = $group_id;

        if ($needsVerification) {
            $contact->status = 'inactive'; 
            $contact->email_verification = 'unverified';
            $contact->is_subscribed = 0;
            $contact->save();

            // Generate verification hash
            $hash = hash('sha256', $contact->uid . $contact->email_contact . env('APP_KEY'));

            $verificationUrl = route('subscribe.verify', ['uid' => $contact->uid, 'hash' => $hash]);

            try {
                $gateway = \App\Models\Gateway::where('is_default', 1)
                    ->where('channel', \App\Enums\System\ChannelTypeEnum::EMAIL->value);
                
                if ($user_id) {
                    $gateway = $gateway->where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)->orWhereNull('user_id');
                    })->orderBy('user_id', 'desc')->first();
                } else {
                    $gateway = $gateway->whereNull('user_id')->first();
                }

                if ($gateway) {
                    $sendMail = new \App\Http\Utility\SendMail();
                    $mailBody = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 40px 20px; text-align: center; background-color: #f9f9f9; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
                            <h2 style='color: #333; margin-bottom: 20px;'>Verify Your Subscription</h2>
                            <p style='color: #555; font-size: 16px; margin-bottom: 30px;'>Thank you for subscribing! Please click the button below to verify your email address and activate your subscription.</p>
                            <a href='{$verificationUrl}' style='display: inline-block; padding: 14px 30px; background-color: #667eea; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;'>Verify Email Now</a>
                            <p style='color: #999; font-size: 12px; margin-top: 40px;'>If you did not request this subscription, please ignore this email.</p>
                            <hr style='border: none; border-top: 1px solid #eaeaea; margin: 20px 0;'>
                            <p style='color: #777; font-size: 12px; word-break: break-all;'>Or copy and paste this link into your browser:<br>{$verificationUrl}</p>
                        </div>
                    ";
                    $sendMail->send($gateway, $email, 'Verify Your Subscription', $mailBody);
                } else {
                    \Illuminate\Support\Facades\Log::error('No default email gateway found to send verification email.');
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send verification email: ' . $e->getMessage());
            }

            return back()->with('success', translate('Please check your email to verify your subscription.'));
        } else {
            // Already verified, just update their subscription status and group
            $contact->is_subscribed = 1;
            $contact->save();

            // Remove from suppressions if they were previously unsubscribed
            DB::table('email_suppressions')
                ->where('user_id', $contact->user_id)
                ->where('email_address', $contact->email_contact)
                ->delete();

            return back()->with('success', translate('You have been successfully subscribed to the list.'));
        }
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
        if ($user_uid === 'admin') {
            $user_id = null;
            $expectedHash = hash('sha256', 'admin' . $email . env('APP_KEY'));
        } else {
            $user = User::where('uid', $user_uid)->firstOrFail();
            $user_id = $user->id;
            $expectedHash = hash('sha256', $user->uid . $email . env('APP_KEY'));
        }

        if (!hash_equals($expectedHash, $hash)) {
            abort(403, 'Invalid unsubscribe link.');
        }

        // Add to email_suppressions
        DB::table('email_suppressions')->updateOrInsert(
            [
                'email_address' => $email,
                'user_id' => $user_id,
            ],
            [
                'uid' => generateUid(),
                'reason' => 'unsubscribe',
                'source' => 'system',
                'created_at' => now(),
            ]
        );

        // Also set contact to inactive and unsubscribed
        Contact::where('user_id', $user_id)
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
