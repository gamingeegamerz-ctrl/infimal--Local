<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignAnalytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'subscriber_id',
        'event_type',
        'ip_address',
        'user_agent',
        'link_url',
        'bounce_reason',
        'event_time'
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // =============================================
    // RELATIONSHIPS
    // =============================================
    
    /**
     * Analytics belongs to Campaign
     */
    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Analytics belongs to Subscriber
     */
    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }

    // =============================================
    // SCOPES
    // =============================================
    
    /**
     * Scope: Get only 'sent' events
     */
    public function scopeSent($query)
    {
        return $query->where('event_type', 'sent');
    }

    /**
     * Scope: Get only 'opened' events
     */
    public function scopeOpened($query)
    {
        return $query->where('event_type', 'opened');
    }

    /**
     * Scope: Get only 'clicked' events
     */
    public function scopeClicked($query)
    {
        return $query->where('event_type', 'clicked');
    }

    /**
     * Scope: Get only 'bounced' events
     */
    public function scopeBounced($query)
    {
        return $query->where('event_type', 'bounced');
    }

    /**
     * Scope: Get only 'unsubscribed' events
     */
    public function scopeUnsubscribed($query)
    {
        return $query->where('event_type', 'unsubscribed');
    }

    /**
     * Scope: Filter by campaign
     */
    public function scopeByCampaign($query, $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    /**
     * Scope: Filter by subscriber
     */
    public function scopeBySubscriber($query, $subscriberId)
    {
        return $query->where('subscriber_id', $subscriberId);
    }

    /**
     * Scope: Events in date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('event_time', [$startDate, $endDate]);
    }

    // =============================================
    // HELPER METHODS
    // =============================================
    
    /**
     * Log an analytics event
     */
    public static function logEvent($campaignId, $subscriberId, $eventType, $data = [])
    {
        return self::create([
            'campaign_id' => $campaignId,
            'subscriber_id' => $subscriberId,
            'event_type' => $eventType,
            'ip_address' => $data['ip_address'] ?? request()->ip(),
            'user_agent' => $data['user_agent'] ?? request()->userAgent(),
            'link_url' => $data['link_url'] ?? null,
            'bounce_reason' => $data['bounce_reason'] ?? null,
            'event_time' => now()
        ]);
    }

    /**
     * Get event statistics for a campaign
     */
    public static function getCampaignStats($campaignId)
    {
        $stats = self::where('campaign_id', $campaignId)
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type')
            ->toArray();

        return [
            'sent' => $stats['sent'] ?? 0,
            'delivered' => $stats['delivered'] ?? 0,
            'opened' => $stats['opened'] ?? 0,
            'clicked' => $stats['clicked'] ?? 0,
            'bounced' => $stats['bounced'] ?? 0,
            'unsubscribed' => $stats['unsubscribed'] ?? 0,
            'spam_complaint' => $stats['spam_complaint'] ?? 0,
        ];
    }

    /**
     * Get unique opens for a campaign
     */
    public static function getUniqueOpens($campaignId)
    {
        return self::where('campaign_id', $campaignId)
            ->where('event_type', 'opened')
            ->distinct('subscriber_id')
            ->count('subscriber_id');
    }

    /**
     * Get unique clicks for a campaign
     */
    public static function getUniqueClicks($campaignId)
    {
        return self::where('campaign_id', $campaignId)
            ->where('event_type', 'clicked')
            ->distinct('subscriber_id')
            ->count('subscriber_id');
    }

    /**
     * Check if subscriber has opened email
     */
    public static function hasOpened($campaignId, $subscriberId)
    {
        return self::where('campaign_id', $campaignId)
            ->where('subscriber_id', $subscriberId)
            ->where('event_type', 'opened')
            ->exists();
    }

    /**
     * Check if subscriber has clicked
     */
    public static function hasClicked($campaignId, $subscriberId)
    {
        return self::where('campaign_id', $campaignId)
            ->where('subscriber_id', $subscriberId)
            ->where('event_type', 'clicked')
            ->exists();
    }
}
