<?php

namespace App\Http\Controllers;

use App\Models\License;
use Illuminate\Support\Facades\Http;

class PayPalController extends Controller
{
    private function baseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function token(): string
    {
        $response = Http::asForm()
            ->withBasicAuth(
                config('services.paypal.client_id'),
                config('services.paypal.secret')
            )
            ->post($this->baseUrl() . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        return (string) $response['access_token'];
    }

    public function createOrder()
    {
        $token = $this->token();

        return Http::withToken($token)
            ->post($this->baseUrl() . '/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => '299.00',
                    ],
                ]],
            ])
            ->json();
    }

    public function captureOrder($id)
    {
        $token = $this->token();

        Http::withToken($token)
            ->post($this->baseUrl() . "/v2/checkout/orders/{$id}/capture")
            ->throw();

        $user = auth()->user();
        $userId = $user->id;
        $licenseKey = License::generateLicenseKey();

        $user->update([
            'is_paid' => true,
            'payment_status' => 'paid',
            'plan_name' => 'InfiMal Pro',
            'paid_at' => now(),
            'license_key' => $licenseKey,
        ]);

        License::updateOrCreate(
            ['user_id' => $userId],
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

        request()->session()->invalidate();
        request()->session()->regenerateToken();
        auth()->loginUsingId($userId);

        return response()->json(['success' => true]);
    }
}
