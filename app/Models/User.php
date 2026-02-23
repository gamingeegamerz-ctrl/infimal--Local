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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Relationship: User has many MailingLists
    public function mailingLists()
    {
        return $this->hasMany(MailingList::class);
    }

    // Relationship: User has many Subscribers
    public function subscribers()
    {
        return $this->hasMany(Subscriber::class);
    }

    // Relationship: User has many Campaigns
    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }
}