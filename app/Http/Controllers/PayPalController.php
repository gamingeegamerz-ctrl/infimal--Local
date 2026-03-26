<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PayPalController extends Controller
{
    private function token(): string
    {
        $res = Http::asForm()
            ->withBasicAuth(
                config('services.paypal.sandbox_client_id'),
                config('services.paypal.sandbox_secret')
            )
            ->post('https://api-m.sandbox.paypal.com/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        return (string) $res->json('access_token');
    }

    public function createOrder(Request $request)
    {
        $token = $this->token();

        $res = Http::withToken($token)->post('https://api-m.sandbox.paypal.com/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => ['currency_code' => 'USD', 'value' => '299.00'],
                'custom_id' => (string) $request->user()->id,
                'description' => 'InfiMal Pro (one-time)',
            ]],
        ]);

        return response()->json($res->json(), $res->status());
    }

    public function captureOrder(string $orderId)
    {
        $token = $this->token();
        $response = Http::withToken($token)->post("https://api-m.sandbox.paypal.com/v2/checkout/orders/{$orderId}/capture");

        // Frontend capture response is informational only.
        // Actual entitlement happens only in webhook verification.
        return response()->json($response->json(), $response->status());
    }
}
