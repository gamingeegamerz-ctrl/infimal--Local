<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaddleService
{
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('PADDLE_API_KEY');
        $this->baseUrl = 'https://api.paddle.com';
    }

    public function createCheckout($data)
    {
        try {
            $payload = [
                'items' => [
                    [
                        'price_id' => $data['price_id'],
                        'quantity' => 1
                    ]
                ],
                'customer' => [
                    'email' => $data['email']
                ],
                'settings' => [
                    'success_url' => 'https://infimal.site/payment/success',
                    'cancel_url'  => 'https://infimal.site/payment/cancel'
                ]
            ];

            Log::info("Paddle Checkout Payload", $payload);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json'
            ])->post($this->baseUrl . '/checkout/sessions', $payload);

            $json = $response->json();

            Log::info("Paddle Checkout Response", $json ?? []);

            if ($response->successful() && isset($json['data']['url'])) {
                return [
                    'success' => true,
                    'checkout_url' => $json['data']['url']
                ];
            }

            return [
                'success' => false,
                'error' => $json['error']['detail'] ?? 'Unknown Paddle error',
                'response' => $json
            ];

        } catch (\Exception $e) {
            Log::error("Paddle Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function verifyWebhook($request)
    {
        $secret = env('PADDLE_WEBHOOK_SECRET');

        $signature = $request->header('Paddle-Signature');
        $body = $request->getContent();

        $computed = base64_encode(hash_hmac('sha256', $body, $secret, true));

        return hash_equals($signature, $computed);
    }
}
