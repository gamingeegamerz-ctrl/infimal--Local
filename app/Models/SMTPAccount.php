<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SMTPAccount extends Model
{
    /**
     * Explicit table name
     */
    protected $table = 'smtps';

    /**
     * Mass assignable fields
     */
    protected $fillable = [
        'user_id',

        // SMTP credentials
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',

        // Reputation & control
        'reputation_score',
        'is_active',
        'priority',
        'daily_limit',
        'is_temporarily_disabled',
        'disabled_until',

        // Daily usage
        'sent_today',
        'last_used_at',

        // Warmup / hourly usage
        'hourly_sent',
        'last_hour_at',
    ];

    /**
     * Default attribute values
     */
    protected $attributes = [
        'reputation_score' => 100,
        'is_active'        => true,
        'priority'         => 1,
        'daily_limit'      => 1000,
        'is_temporarily_disabled' => false,
        'sent_today'       => 0,
        'hourly_sent'      => 0,
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'is_active'    => 'boolean',
        'is_temporarily_disabled' => 'boolean',
        'priority'     => 'integer',
        'daily_limit'  => 'integer',
        'last_used_at' => 'datetime',
        'last_hour_at' => 'datetime',
        'disabled_until' => 'datetime',
    ];

    /* -----------------------------------------------------------------
     |  QUERY SCOPES
     |------------------------------------------------------------------
     */

    /**
     * Only active SMTPs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Healthy SMTPs (minimum reputation)
     */
    public function scopeHealthy($query)
    {
        return $query
            ->where('is_active', true)
            ->where('reputation_score', '>=', 40);
    }

    /* -----------------------------------------------------------------
     |  CORE ROTATION LOGIC
     |------------------------------------------------------------------
     */

    /**
     * Pick best SMTP for sending based on priority, reputation, and daily limits
     */
    public static function pickForSending($userId = null): ?self
    {
        $query = self::query()
            ->where('is_active', true)
            ->where('is_temporarily_disabled', false)
            ->where(function($q) {
                $q->whereNull('disabled_until')
                  ->orWhere('disabled_until', '<=', now());
            })
            ->whereColumn('sent_today', '<', 'daily_limit');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->orderByDesc('priority')
            ->orderByDesc('reputation_score')
            ->orderBy('sent_today')
            ->orderBy('last_used_at')
            ->first();
    }

    /**
     * Pick fallback SMTP when primary fails
     */
    public static function pickFallback($excludeSmtpId, $userId = null): ?self
    {
        $query = self::query()
            ->where('id', '!=', $excludeSmtpId)
            ->where('is_active', true)
            ->where('is_temporarily_disabled', false)
            ->where(function($q) {
                $q->whereNull('disabled_until')
                  ->orWhere('disabled_until', '<=', now());
            })
            ->whereColumn('sent_today', '<', 'daily_limit');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->orderByDesc('priority')
            ->orderByDesc('reputation_score')
            ->orderBy('sent_today')
            ->first();
    }

    /**
     * Mark SMTP usage when selected
     */
    public function markUsed(): void
    {
        $this->increment('sent_today');
        $this->last_used_at = Carbon::now();
        $this->save();
    }

    /* -----------------------------------------------------------------
     |  WARMUP / HOURLY CONTROL
     |------------------------------------------------------------------
     */

    /**
     * Hourly send limit based on reputation
     */
    public function hourlyLimit(): int
    {
        return match (true) {
            $this->reputation_score >= 90 => 1000,
            $this->reputation_score >= 70 => 500,
            $this->reputation_score >= 50 => 200,
            $this->reputation_score >= 40 => 50,
            default => 0,
        };
    }

    /**
     * Check if SMTP can send right now (warmup safe + daily limits)
     */
    public function canSendNow(): bool
    {
        if (!$this->is_active || $this->is_temporarily_disabled) {
            return false;
        }

        // Check if temporarily disabled
        if ($this->disabled_until && $this->disabled_until->isFuture()) {
            return false;
        }

        // Check daily limit
        if ($this->sent_today >= $this->daily_limit) {
            return false;
        }

        // Reset hourly counter if 1 hour passed
        if ($this->last_hour_at && now()->diffInMinutes($this->last_hour_at) >= 60) {
            $this->hourly_sent = 0;
            $this->last_hour_at = now();
            $this->save();
        }

        // First ever send
        if (!$this->last_hour_at) {
            $this->last_hour_at = now();
            $this->save();
        }

        return $this->hourly_sent < $this->hourlyLimit();
    }

    /**
     * Temporarily disable SMTP
     */
    public function temporarilyDisable($minutes = 60)
    {
        $this->update([
            'is_temporarily_disabled' => true,
            'disabled_until' => now()->addMinutes($minutes)
        ]);
    }

    /**
     * Re-enable SMTP
     */
    public function enable()
    {
        $this->update([
            'is_temporarily_disabled' => false,
            'disabled_until' => null
        ]);
    }

    /**
     * Mark one hourly send
     */
    public function markHourlySend(): void
    {
        $this->increment('hourly_sent');

        if (!$this->last_hour_at) {
            $this->last_hour_at = now();
            $this->save();
        }
    }

    /* -----------------------------------------------------------------
     |  REPUTATION MANAGEMENT
     |------------------------------------------------------------------
     */

    /**
     * Reduce reputation and auto-disable if needed
     */
    public function reduceReputation(int $points): void
    {
        $this->reputation_score = max(0, $this->reputation_score - $points);

        if ($this->reputation_score < 40) {
            $this->is_active = false;
        }

        $this->save();
    }

    /**
     * Increase reputation slowly (clean sending)
     */
    public function increaseReputation(int $points = 1): void
    {
        $this->reputation_score = min(100, $this->reputation_score + $points);
        $this->save();
    }

    /* -----------------------------------------------------------------
     |  DAILY MAINTENANCE
     |------------------------------------------------------------------
     */

    /**
     * Reset daily counters (run via scheduler)
     */
    public static function resetDailyCounters(): void
    {
        self::query()->update([
            'sent_today'  => 0,
            'hourly_sent' => 0,
            'last_hour_at'=> null,
        ]);
    }
}
