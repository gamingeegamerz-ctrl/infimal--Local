<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function success(Request $request)
    {
        $user = Auth::user();

        if (!$user || !$user->hasPaid()) {
            return redirect()->route('billing')->with('error', 'Payment not verified yet.');
        }

        return redirect()->route('dashboard')->with('success', 'Payment verified successfully.');
    }

    public function processPaddleCheckout(Request $request)
    {
        $request->validate([
            'gateway' => 'nullable|string',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checkout initialized.',
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

        DB::transaction(function () use ($user) {
            $licenseKey = 'INFIMAL-' . strtoupper(Str::random(20));

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
                    'duration_days' => 36500,
                    'is_active' => true,
                    'is_lifetime' => true,
                    'expires_at' => now()->addYears(100),
                ]
            );
        });

        return response('OK', 200);
    }
}
