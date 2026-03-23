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
        return view('payments.success');
    }

    public function processPaddleCheckout(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Redirect user to configured payment gateway to pay $299.',
            'amount' => 299.00,
            'user_id' => $user->id,
        ]);
    }

    public function webhook(Request $request)
    {
        if (($request->alert_name ?? '') !== 'payment_succeeded') {
            return response('Ignored', 200);
        }

        $data = json_decode($request->passthrough ?? '[]', true);
        $user = User::find($data['user_id'] ?? null);

        if (!$user) {
            return response('User not found', 404);
        }

        $licenseKey = License::generateLicenseKey();

        $user->update([
            'payment_status' => 'paid',
            'is_paid' => true,
            'paid_at' => now(),
            'plan_name' => 'InfiMal Pro',
            'license_key' => $licenseKey,
        ]);

        License::updateOrCreate(
            ['user_id' => $user->id],
            [
                'license_key' => $licenseKey,
                'plan_type' => 'InfiMal Pro',
                'price' => 299.00,
                'duration_days' => 0,
                'is_active' => true,
                'is_lifetime' => true,
                'status' => 'active',
            ]
        );

        return response('OK', 200);
    }
}
