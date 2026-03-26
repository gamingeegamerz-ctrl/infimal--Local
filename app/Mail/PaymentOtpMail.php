<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $otp)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome to InfiMal - Verify your account');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payment-otp');
    }
}
