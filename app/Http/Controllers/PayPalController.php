<?php

namespace App\Http\Controllers;

use App\Models\License;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
                config('services.paypal.client_id') ?? config('services.paypal.sandbox_client_id'),
                config('services.paypal.secret') ?? config('services.paypal.sandbox_secret')
            )
            ->post($this->baseUrl() . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        $response->throw();

        return $response->json('access_token');
    }

    public function createOrder()
    {
        $token = $this->token();

        $response = Http::withToken($token)->post($this->baseUrl() . '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => '299.00',
                ],
            ]],
        ]);

        return response()->json($response->json(), $response->status());
    }

    public function captureOrder($id)
    {
        $token = $this->token();
        $response = Http::withToken($token)->post($this->baseUrl() . "/v2/checkout/orders/{$id}/capture");
        $response->throw();

        $user = Auth::user();
        $licenseKey = 'INFIMAL-' . strtoupper(Str::random(16));

        DB::transaction(function () use ($user, $licenseKey) {
            $user->update([
                'is_paid' => true,
                'payment_status' => 'paid',
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

        $request = request();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['success' => true]);
    }
}
