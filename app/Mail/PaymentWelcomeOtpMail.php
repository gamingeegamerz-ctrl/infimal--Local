<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentWelcomeOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $licenseKey,
        public string $otp
    ) {
    }

    public function build(): self
    {
        return $this->subject('Welcome to InfiMal Pro - Verify your OTP')
            ->view('emails.payment-welcome-otp');
    }
}
