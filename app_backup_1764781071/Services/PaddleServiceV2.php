<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaddleServiceV2
{
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = 'apikey_01k9qby046yrdy6cf9vxqh7n82';
        $this->baseUrl = 'https://api.paddle.com';
    }

    /**
     * Generate Paddle checkout - SIMPLE VERSION
     */
    public function createCheckout($data)
    {
        try {
            // Simple payload
            $payload = [
                'items' => [
                    [
                        'price_id' => 'pri_01k9qbbhq0cpyav5gt6zh9je94',
                        'quantity' => 1
                    ]
                ],
                'customer_email' => $data['email'],
                'return_url' => 'https://infimal.site/payment/success'
            ];

            Log::info('Paddle V2 Payload:', $payload);

            // Try different auth approach
            $response = Http::withToken($this->apiKey)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->timeout(30)
                ->post($this->baseUrl . '/checkout', $payload);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['data']['url'])) {
                return [
                    'success' => true,
                    'checkout_url' => $responseData['data']['url']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $responseData['error']['detail'] ?? 'API Error',
                    'status' => $response->status()
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
