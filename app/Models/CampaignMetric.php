<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'subscriber_id',
        'email',
        'sent',
        'opened',
        'clicked',
        'bounced',
        'unsubscribed',
        'complained',
        'sent_at',
        'opened_at',
        'clicked_at',
        'click_data',
        'ip_address',
        'user_agent',
        'device_type',
        'os',
        'browser',
    ];

    protected $casts = [
        'sent' => 'boolean',
        'opened' => 'boolean',
        'clicked' => 'boolean',
        'bounced' => 'boolean',
        'unsubscribed' => 'boolean',
        'complained' => 'boolean',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'click_data' => 'array',
    ];

    // Relationships
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }
}
