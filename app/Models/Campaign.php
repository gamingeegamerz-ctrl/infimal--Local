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
        'from_name',
        'from_email',
        'reply_to',
        'preview_text',
        'content',
        'html_content',
        'plain_text',
        'status',
        'schedule_date',
        'started_at',
        'sent_at',
        'total_recipients',
        'total_sent',
        'total_opened',
        'total_clicked',
        'total_bounced',
        'total_unsubscribed'
    ];

    protected $casts = [
        'schedule_date' => 'datetime',
        'started_at' => 'datetime',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'total_recipients' => 'integer',
        'total_sent' => 'integer',
        'total_opened' => 'integer',
        'total_clicked' => 'integer',
        'total_bounced' => 'integer',
        'total_unsubscribed' => 'integer'
    ];

    // =============================================
    // RELATIONSHIPS
    // =============================================
    
    /**
     * Campaign belongs to User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Campaign belongs to MailingList
     */
    public function mailingList()
    {
        return $this->belongsTo(MailingList::class, 'list_id');
    }

    /**
     * Alternative name for consistency
     */
    public function list()
    {
        return $this->belongsTo(MailingList::class, 'list_id');
    }

    /**
     * Campaign has many Analytics events
     */
    public function analytics()
    {
        return $this->hasMany(CampaignAnalytics::class);
    }

    /**
     * Get sent events
     */
    public function sentEvents()
    {
        return $this->hasMany(CampaignAnalytics::class)->where('event_type', 'sent');
    }

    /**
     * Get opened events
     */
    public function openedEvents()
    {
        return $this->hasMany(CampaignAnalytics::class)->where('event_type', 'opened');
    }

    /**
     * Get clicked events
     */
    public function clickedEvents()
    {
        return $this->hasMany(CampaignAnalytics::class)->where('event_type', 'clicked');
    }

    // =============================================
    // STATUS HELPERS
    // =============================================
    
    /**
     * Check if draft
     */
    public function isDraft()
    {
        return $this->status === 'draft';
    }

    /**
     * Check if sent
     */
    public function isSent()
    {
        return $this->status === 'sent';
    }

    /**
     * Check if scheduled
     */
    public function isScheduled()
    {
        return $this->status === 'scheduled';
    }

    /**
     * Check if sending
     */
    public function isSending()
    {
        return $this->status === 'sending';
    }

    // =============================================
    // STATISTICS HELPERS
    // =============================================
    
    /**
     * Get open rate percentage
     */
    public function getOpenRateAttribute()
    {
        if ($this->total_sent > 0) {
            return round(($this->total_opened / $this->total_sent) * 100, 2);
        }
        return 0;
    }

    /**
     * Get click rate percentage
     */
    public function getClickRateAttribute()
    {
        if ($this->total_sent > 0) {
            return round(($this->total_clicked / $this->total_sent) * 100, 2);
        }
        return 0;
    }

    /**
     * Get bounce rate percentage
     */
    public function getBounceRateAttribute()
    {
        if ($this->total_sent > 0) {
            return round(($this->total_bounced / $this->total_sent) * 100, 2);
        }
        return 0;
    }

    /**
     * Get unsubscribe rate percentage
     */
    public function getUnsubscribeRateAttribute()
    {
        if ($this->total_sent > 0) {
            return round(($this->total_unsubscribed / $this->total_sent) * 100, 2);
        }
        return 0;
    }

    /**
     * Get click-to-open rate (CTOR)
     */
    public function getClickToOpenRateAttribute()
    {
        if ($this->total_opened > 0) {
            return round(($this->total_clicked / $this->total_opened) * 100, 2);
        }
        return 0;
    }

    // =============================================
    // ANALYTICS METHODS
    // =============================================
    
    /**
     * Update campaign statistics from analytics
     */
    public function updateStatistics()
    {
        $stats = CampaignAnalytics::getCampaignStats($this->id);
        
        $this->update([
            'total_sent' => $stats['sent'],
            'total_opened' => $stats['opened'],
            'total_clicked' => $stats['clicked'],
            'total_bounced' => $stats['bounced'],
            'total_unsubscribed' => $stats['unsubscribed']
        ]);
    }

    /**
     * Get unique opens count
     */
    public function getUniqueOpensCount()
    {
        return CampaignAnalytics::getUniqueOpens($this->id);
    }

    /**
     * Get unique clicks count
     */
    public function getUniqueClicksCount()
    {
        return CampaignAnalytics::getUniqueClicks($this->id);
    }

    /**
     * Get detailed analytics
     */
    public function getDetailedStats()
    {
        return [
            'sent' => $this->total_sent,
            'opened' => $this->total_opened,
            'clicked' => $this->total_clicked,
            'bounced' => $this->total_bounced,
            'unsubscribed' => $this->total_unsubscribed,
            'unique_opens' => $this->getUniqueOpensCount(),
            'unique_clicks' => $this->getUniqueClicksCount(),
            'open_rate' => $this->open_rate,
            'click_rate' => $this->click_rate,
            'bounce_rate' => $this->bounce_rate,
            'unsubscribe_rate' => $this->unsubscribe_rate,
            'click_to_open_rate' => $this->click_to_open_rate
        ];
    }

    // =============================================
    // SCOPES
    // =============================================
    
    /**
     * Scope: Only drafts
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope: Only sent
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope: Only scheduled
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope: By list
     */
    public function scopeByList($query, $listId)
    {
        return $query->where('list_id', $listId);
    }

    /**
     * Scope: Recent campaigns
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}