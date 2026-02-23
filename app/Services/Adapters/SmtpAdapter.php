<?php

namespace App\Services\Adapters;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SmtpAdapter
{
    /**
     * Send email via configured SMTP
     * Returns true on success, false on failure
     */
    public function send(array $data): bool
    {
        try {
            $to      = $data['to'] ?? null;
            $subject = $data['subject'] ?? '';
            $body    = $data['body'] ?? '';

            if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return false;
            }

            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)
                        ->subject($subject);
            });

            return true;
        } catch (Throwable $e) {
            // ❌ UI ko kuch nahi dikhega
            // ✅ Log backend me safe rahega
            Log::warning('SMTP SEND FAILED', [
                'error' => $e->getMessage(),
                'to'    => $data['to'] ?? null,
            ]);

            return false;
        }
    }
}
