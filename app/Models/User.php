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
        'payment_status',
        'plan_name',
        'paid_at',
        'is_paid',
        'license_key',
        'google_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'paid_at' => 'datetime',
        'is_paid' => 'boolean',
    ];

    public function campaigns()
    {
        return $this->hasMany(Campaign::class, 'user_id');
    }

    public function lists()
    {
        return $this->hasMany(MailingList::class, 'user_id');
    }

    public function mailingLists()
    {
        return $this->lists();
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
        return (bool) $this->is_paid || $this->payment_status === 'paid';
    }
}
