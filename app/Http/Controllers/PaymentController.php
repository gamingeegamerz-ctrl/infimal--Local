<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function success()
    {
        return view('payments.success', [
            'user' => Auth::user(),
        ]);
    }

    public function processPaddleCheckout(Request $request)
    {
        $request->validate([
            'payment_method' => 'nullable|string|max:50',
        ]);

        $user = Auth::user();
        $licenseKey = License::generateLicenseKey();

        $user->update([
            'payment_status' => 'paid',
            'is_paid' => true,
            'plan_name' => 'InfiMal Pro',
            'paid_at' => now(),
            'license_key' => $licenseKey,
        ]);

        License::firstOrCreate(
            ['user_id' => $user->id],
            [
                'license_key' => $licenseKey,
                'plan_type' => 'InfiMal Pro',
                'price' => 299.00,
                'duration_days' => 36500,
                'is_active' => true,
                'is_lifetime' => true,
                'features' => [
                    'Unlimited email sending (via SMTP)',
                    'Campaign management',
                    'Analytics (open, click, bounce)',
                    'SMTP integration',
                    'Lifetime access',
                ],
            ]
        );

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'redirect' => route('dashboard'),
        ]);
    }

    public function webhook(Request $request)
    {
        if (($request->alert_name ?? '') !== 'payment_succeeded') {
            return response('Ignored', 200);
        }

        $data = json_decode($request->passthrough, true);

        $user = User::find($data['user_id'] ?? null);
        if (!$user) {
            return response('User not found', 404);
        }

        $licenseKey = License::generateLicenseKey();

        $user->update([
            'payment_status' => 'paid',
            'is_paid' => true,
            'plan_name' => 'InfiMal Pro',
            'paid_at' => now(),
            'license_key' => $licenseKey,
        ]);

        License::updateOrCreate(
            ['user_id' => $user->id],
            [
                'license_key' => $licenseKey,
                'plan_type' => 'InfiMal Pro',
                'price' => 299.00,
                'duration_days' => 36500,
                'is_active' => true,
                'is_lifetime' => true,
            ]
        );

        return response('OK', 200);
    }
}
