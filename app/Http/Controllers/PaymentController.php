<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function showCheckout()
    {
        return view('payments.checkout', [
            'product' => 'InfiMal Pro',
            'price' => 299,
            'currency' => 'USD',
        ]);
    }

    public function success(Request $request)
    {
        if (!$request->user()->hasPaid()) {
            return redirect()->route('payment')->with('error', 'Payment is not verified yet.');
        }

        return redirect()->route('otp.notice')->with('success', 'Payment verified. Please complete OTP verification.');
    }


    public function processPaddleCheckout(Request $request)
    {
        return redirect()->route('payment')->with('error', 'Checkout must be completed via secure gateway callback.');
    }

    public function webhook(Request $request)
    {
        $eventType = (string) $request->input('event_type', $request->input('alert_name', ''));

        if (!in_array($eventType, ['PAYMENT.CAPTURE.COMPLETED', 'payment_succeeded'], true)) {
            return response('Ignored', 200);
        }

        $userId = $request->input('custom_id')
            ?? $request->input('resource.custom_id')
            ?? data_get(json_decode((string) $request->input('passthrough', '{}'), true), 'user_id');

        /** @var User|null $user */
        $user = User::find($userId);
        if (!$user) {
            return response('User not found', 404);
        }

        $amount = (float) ($request->input('resource.amount.value') ?? 299);
        $txId = (string) ($request->input('resource.id') ?? $request->input('txn_id') ?? Str::uuid());

        $license = License::firstOrCreate(
            ['user_id' => $user->id],
            [
                'license_key' => License::generateLicenseKey(),
                'plan_type' => 'InfiMal Pro',
                'duration_days' => 36500,
                'is_active' => true,
                'is_lifetime' => true,
                'expires_at' => null,
            ]
        );

        $otp = (string) random_int(100000, 999999);

        $user->update([
            'payment_status' => 'paid',
            'is_paid' => true,
            'payment_amount' => $amount,
            'transaction_id' => $txId,
            'paid_at' => now(),
            'plan_name' => 'InfiMal Pro',
            'license_key' => $license->license_key,
            'license_status' => 'active',
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(15),
            'otp_verified_at' => null,
        ]);

        if (class_exists(Payment::class)) {
            Payment::updateOrCreate(
                ['payment_id' => $txId],
                [
                    'user_id' => $user->id,
                    'plan' => 'InfiMal Pro',
                    'amount' => $amount,
                    'currency' => 'USD',
                    'status' => 'completed',
                    'payment_method' => 'paypal',
                    'metadata' => ['event' => $eventType],
                ]
            );
        }

        try {
            Mail::raw("Welcome to InfiMal Pro. Login: {$user->email}. OTP: {$otp}", function ($message) use ($user) {
                $message->to($user->email)->subject('Welcome to InfiMal Pro - Verify OTP');
            });
        } catch (\Throwable) {
            // no crash on mail delivery errors
        }

        return response('OK', 200);
    }
}
