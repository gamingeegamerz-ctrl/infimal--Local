<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function success()
    {
        return redirect()->route('billing')->with('success', 'Payment verification pending or completed.');
    }

    public function processPaddleCheckout(Request $request)
    {
        return app(BillingController::class)->processPayment($request);
    }

    public function webhook(Request $request)
    {
        if (($request->alert_name ?? '') !== 'payment_succeeded') {
            return response('Ignored', 200);
        }

        $data = json_decode($request->passthrough ?? '{}', true);

        $user = User::find($data['user_id'] ?? null);
        if (!$user) {
            return response('User not found', 404);
        }

        $licenseKey = 'INFIMAL-' . strtoupper(Str::random(20));

        $user->update([
            'payment_status' => 'paid',
            'is_paid' => true,
            'paid_at' => now(),
            'payment_date' => now(),
            'plan_name' => 'InfiMal Pro',
            'license_key' => $licenseKey,
        ]);

        License::updateOrCreate(
            ['user_id' => $user->id],
            [
                'license_key' => $licenseKey,
                'plan_type' => 'InfiMal Pro',
                'duration_days' => 0,
                'price' => 299.00,
                'is_active' => true,
                'is_lifetime' => true,
                'expires_at' => null,
                'features' => [
                    'Unlimited email sending (via SMTP)',
                    'Campaign management',
                    'Analytics (open, click, bounce)',
                    'SMTP integration',
                    'Lifetime access',
                ],
            ]
        );

        return response('OK', 200);
    }
}
