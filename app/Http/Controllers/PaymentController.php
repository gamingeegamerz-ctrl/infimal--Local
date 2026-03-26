<?php

namespace App\Http\Controllers;

use App\Mail\PaymentWelcomeOtpMail;
use App\Models\License;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function checkout()
    {
        return view('payments.checkout', [
            'productName' => 'InfiMal Pro',
            'amount' => 299,
            'currency' => 'USD',
        ]);
    }

    public function success()
    {
        return redirect()->route('otp.verify.form')->with('success', 'Payment received. Please verify OTP sent to your email.');
    }

    public function processPaddleCheckout()
    {
        abort(501, 'Checkout creation should be handled by configured payment gateway integration.');
    }

    public function webhook(Request $request)
    {
        $eventType = (string) ($request->input('event_type') ?? $request->input('alert_name') ?? '');
        if (!in_array($eventType, ['payment_succeeded', 'PAYMENT.CAPTURE.COMPLETED'], true)) {
            return response('Ignored', 200);
        }

        $transactionId = (string) ($request->input('resource.id') ?? $request->input('order_id') ?? $request->input('txn_id') ?? $request->input('sale_id') ?? Str::uuid());
        $userId = (int) ($request->input('custom_id') ?? data_get(json_decode((string) $request->input('passthrough', '{}'), true), 'user_id'));

        if (!$userId) {
            Log::warning('Payment webhook received without user id', ['payload' => $request->all()]);
            return response('Missing user', 422);
        }

        $user = User::find($userId);
        if (!$user) {
            return response('User not found', 404);
        }

        DB::transaction(function () use ($user, $transactionId, $request): void {
            Payment::updateOrCreate(
                ['payment_id' => $transactionId],
                [
                    'user_id' => $user->id,
                    'plan' => 'InfiMal Pro',
                    'amount' => 299,
                    'currency' => 'USD',
                    'status' => 'completed',
                    'payment_method' => 'paypal',
                    'metadata' => ['payload' => $request->all()],
                ]
            );

            $license = License::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'license_key' => License::generateLicenseKey(),
                    'status' => 'active',
                    'is_active' => true,
                    'plan_type' => 'lifetime',
                    'duration_days' => null,
                ]
            );

            $otp = (string) random_int(100000, 999999);
            $user->forceFill([
                'is_paid' => true,
                'payment_status' => 'paid',
                'paid_at' => now(),
                'payment_date' => now(),
                'transaction_id' => $transactionId,
                'license_key' => $license->license_key,
                'otp_code' => Hash::make($otp),
                'otp_expires_at' => now()->addMinutes(10),
                'otp_verified_at' => null,
            ])->save();

            Mail::to($user->email)->queue(new PaymentWelcomeOtpMail($user, $license->license_key, $otp));
        });

        return response('OK', 200);
    }
}
