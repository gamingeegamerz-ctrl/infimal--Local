<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LicenseKeyMail extends Mailable
{
    use Queueable, SerializesModels;

    public $licenseKey;
    public $userName;

    public function __construct($licenseKey, $userName)
    {
        $this->licenseKey = $licenseKey;
        $this->userName = $userName;
    }

    public function build()
    {
        return $this->subject('Your InfiMal License Key')
                    ->view('emails.license-key')
                    ->with([
                        'licenseKey' => $this->licenseKey,
                        'userName' => $this->userName
                    ]);
    }
}
