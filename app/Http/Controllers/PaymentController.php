<?php

namespace App\Http\Controllers;

use App\Mail\PaymentOtpMail;
use App\Models\License;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentController extends Controller
{
    public function checkout()
    {
        return view('payments.checkout', [
            'product' => 'InfiMal Pro',
            'price' => 299,
            'currency' => 'USD',
        ]);
    }

    public function success()
    {
        return redirect()->route('otp.notice')->with('success', 'Payment recorded. Enter OTP sent to your email.');
    }

    public function webhook(Request $request)
    {
        $payload = $request->all();

        if (!$this->verifyGatewayWebhook($request)) {
            return response()->json(['message' => 'Invalid webhook signature'], 422);
        }

        if (($payload['event_type'] ?? '') !== 'PAYMENT.CAPTURE.COMPLETED') {
            return response()->json(['message' => 'Ignored'], 200);
        }

        $customId = data_get($payload, 'resource.custom_id');
        $userId = (int) preg_replace('/[^0-9]/', '', (string) $customId);
        $transactionId = (string) data_get($payload, 'resource.id');

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $license = $user->license ?: new License(['user_id' => $user->id]);
        $license->license_key = $license->license_key ?: License::generateLicenseKey();
        $license->plan_type = 'InfiMal Pro';
        $license->duration_days = 3650;
        $license->is_active = true;
        $license->expires_at = null;
        $license->save();

        $otp = (string) random_int(100000, 999999);

        $user->update([
            'is_paid' => true,
            'payment_status' => 'paid',
            'paid_at' => now(),
            'transaction_id' => $transactionId,
            'license_key' => $license->license_key,
            'otp_code' => bcrypt($otp),
            'otp_expires_at' => now()->addMinutes(15),
            'otp_verified_at' => null,
        ]);

        Mail::to($user->email)->queue(new PaymentOtpMail($otp));

        return response()->json(['message' => 'Payment confirmed'], 200);
    }

    private function verifyGatewayWebhook(Request $request): bool
    {
        if (app()->environment('local', 'testing')) {
            return true;
        }

        try {
            $response = Http::withBasicAuth(
                config('services.paypal.sandbox_client_id'),
                config('services.paypal.sandbox_secret')
            )->post('https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $request->header('paypal-auth-algo'),
                'cert_url' => $request->header('paypal-cert-url'),
                'transmission_id' => $request->header('paypal-transmission-id'),
                'transmission_sig' => $request->header('paypal-transmission-sig'),
                'transmission_time' => $request->header('paypal-transmission-time'),
                'webhook_id' => config('services.paypal.webhook_id'),
                'webhook_event' => $request->all(),
            ]);

            return data_get($response->json(), 'verification_status') === 'SUCCESS';
        } catch (\Throwable $e) {
            Log::warning('Payment webhook verification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
