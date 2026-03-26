<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'payment_status',
        'plan_name',
        'paid_at',
        'is_paid',
        'license_key',
        'otp_code',
        'otp_expires_at',
        'otp_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'paid_at' => 'datetime',
        'payment_date' => 'datetime',
        'plan_expiry_date' => 'datetime',
        'is_paid' => 'boolean',
        'otp_expires_at' => 'datetime',
        'otp_verified_at' => 'datetime',
    ];

    public function campaigns()
    {
        return $this->hasMany(Campaign::class, 'user_id');
    }

    public function subscriberLists()
    {
        return $this->hasMany(MailingList::class, 'user_id');
    }

    public function mailingLists()
    {
        return $this->subscriberLists();
    }

    public function subscribers()
    {
        return $this->hasMany(Subscriber::class, 'user_id');
    }

    public function license()
    {
        return $this->hasOne(License::class, 'user_id');
    }

    public function hasPaid(): bool
    {
        return $this->is_paid || in_array((string) $this->payment_status, ['paid'], true) || !is_null($this->paid_at);
    }

    public function hasPaidAccess(): bool
    {
        $license = $this->license;

        return $this->hasPaid()
            && !empty($this->license_key)
            && $license
            && (bool) $license->is_active
            && (is_null($license->expires_at) || $license->expires_at?->isFuture())
            && !is_null($this->otp_verified_at);
    }
}
