<?php

namespace App\Services;

use App\Models\UserTrust;
use App\Models\EmailUsage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TrustManager
{
    protected int $userId;

    // Backend-only trust stages (silent)
    protected array $stageLimits = [
        1 => 10000,    // 10k/day
        2 => 50000,    // 50k/day
        3 => 100000,   // 1 lakh/day
    ];

    public function __construct(int $userId)
    {
        $this->userId = $userId;

        UserTrust::firstOrCreate(
            ['user_id' => $this->userId],
            [
                'stage'       => 1,
                'is_frozen'   => false,
                'trust_score' => 100,
            ]
        );
    }

    /**
     * Current trust stage
     */
    public function getStage(): int
    {
        return (int) UserTrust::where('user_id', $this->userId)->value('stage');
    }

    /**
     * Daily sending limit
     */
    public function getDailyLimit(): int
    {
        $stage = $this->getStage();
        return $this->stageLimits[$stage] ?? $this->stageLimits[1];
    }

    /**
     * Emails sent today
     */
    public function getTodayUsage(): int
    {
        return (int) EmailUsage::where('user_id', $this->userId)
            ->where('date', Carbon::today()->toDateString())
            ->value('sent_count');
    }

    /**
     * Increment usage after successful send
     */
    public function incrementTodayUsage(): void
    {
        $today = Carbon::today()->toDateString();

        EmailUsage::updateOrCreate(
            [
                'user_id' => $this->userId,
                'date'    => $today,
            ],
            [
                'sent_count' => DB::raw('sent_count + 1'),
            ]
        );
    }

    /**
     * Can user send right now?
     */
    public function canSend(): bool
    {
        $trust = UserTrust::where('user_id', $this->userId)->first();

        if (!$trust || $trust->is_frozen) {
            return false;
        }

        if ($this->getTodayUsage() >= $this->getDailyLimit()) {
            return false;
        }

        return true;
    }

    /**
     * Penalize user trust
     */
    public function penalize(int $points = 5): void
    {
        $trust = UserTrust::where('user_id', $this->userId)->first();

        if (!$trust) {
            return;
        }

        $trust->trust_score = max(0, $trust->trust_score - $points);

        if ($trust->trust_score < 30) {
            $trust->is_frozen = true;
            $trust->frozen_at = now();
        }

        $trust->save();
    }

    /**
     * Auto-upgrade trust stage (cron or batch based)
     */
    public function autoUpgradeIfEligible(
        float $bounceRate,
        int $complaints,
        int $daysConsistent
    ): void {
        $trust = UserTrust::where('user_id', $this->userId)->first();

        if (!$trust || $trust->stage >= 3) {
            return;
        }

        if (
            $bounceRate < 3.0 &&
            $complaints === 0 &&
            $daysConsistent >= 7
        ) {
            $trust->stage++;
            $trust->save();
        }
    }
}

