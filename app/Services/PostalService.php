<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PostalService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = 'http://127.0.0.1:5000/api/v1';
        $this->apiKey  = config('services.postal.api_key');
    }

    protected function headers()
    {
        return [
            'X-Server-API-Key' => $this->apiKey,
            'Accept' => 'application/json',
        ];
    }

    /**
     * Create SMTP credentials for Infimal user
     */
    public function createSmtpCredential(string $username)
    {
        $response = Http::withHeaders($this->headers())
            ->post($this->baseUrl.'/credentials', [
                'name' => $username,
                'type' => 'SMTP',
            ]);

        if (!$response->successful()) {
            Log::error('Postal SMTP create failed', $response->json());
            throw new \Exception('Postal SMTP credential creation failed');
        }

        return $response->json();
    }
}
