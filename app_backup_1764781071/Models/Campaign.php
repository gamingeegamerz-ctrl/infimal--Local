<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'list_id', 
        'name',
        'subject',
        'content',
        'status',
        'scheduled_at',
        'sent_at',
        'total_recipients',
        'delivered',
        'opens',
        'clicks',
        'bounces',
        'unsubscribes',
        'open_rate',
        'click_rate'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function list()
    {
        return $this->belongsTo(EmailList::class, 'list_id');
    }
}
