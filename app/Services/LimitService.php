<?php

namespace App\Services;

use App\Models\EmailLimit;
use App\Models\EmailLog;
use App\Models\License;
use App\Models\SMTPAccount;
use Carbon\Carbon;

class LimitService
{
    /**
     * Check whether user can send emails
     */
    public function canSend(int $userId, int $sendCount): void
    {
        $limit = EmailLimit::where('user_id', $userId)->first();

        if (!$limit) {
            throw new \Exception('Email limit not initialized');
        }

        // Hard block check
        if ($limit->is_blocked) {
            throw new \Exception(
                'Sending paused for reputation safety.'
            );
        }

        // Sudden bulk click protection (10k / 50k)
        if ($sendCount >= config('infimal-smtp.spike_threshold')) {
            $this->registerViolation($userId);
            throw new \Exception(
                'Sudden bulk sending detected. Warm-up required.'
            );
        }

        // Daily limit check
        if (($limit->emails_sent_today + $sendCount) > $limit->daily_limit) {
            throw new \Exception(
                'Daily sending limit reached. Please send gradually.'
            );
        }

        // Short-time spike detection (last 5 minutes)
        $recent = EmailLog::where('user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subMinutes(5))
            ->sum('sent_count');

        if (($recent + $sendCount) > config('infimal-smtp.spike_threshold')) {
            $this->registerViolation($userId);
            throw new \Exception(
                'Rapid sending detected. Slow down to protect reputation.'
            );
        }
    }

    /**
     * Register sent emails
     */
    public function registerSend(int $userId, int $count): void
    {
        EmailLimit::where('user_id', $userId)
            ->increment('emails_sent_today', $count);

        EmailLog::create([
            'user_id' => $userId,
            'sent_count' => $count,
            'created_at' => now()
        ]);
    }

    /**
     * Register violation and auto-block if needed
     */
    public function registerViolation(int $userId): void
    {
        $limit = EmailLimit::where('user_id', $userId)->first();

        if (!$limit) {
            return;
        }

        $limit->violation_count += 1;
        $limit->reputation_score = max(
            0,
            $limit->reputation_score - 20
        );
        $limit->save();

        // Auto block after 3 violations
        if ($limit->violation_count >= 3) {
            $this->hardBlockUser($userId);
        }
    }

    /**
     * Hard block user + license + SMTP
     */
    private function hardBlockUser(int $userId): void
    {
        EmailLimit::where('user_id', $userId)
            ->update(['is_blocked' => true]);

        License::where('user_id', $userId)
            ->update(['status' => 'blocked']);

        SMTPAccount::where('user_id', $userId)
            ->update(['is_active' => false]);
    }

    /**
     * Daily warm-up increment (run via cron)
     */
    public function processWarmup(): void
    {
        $limits = EmailLimit::where('is_blocked', false)->get();

        foreach ($limits as $limit) {
            if (
                $limit->reputation_score >= 85 &&
                $limit->violation_count === 0
            ) {
                $limit->daily_limit = min(
                    (int) ($limit->daily_limit * 1.5),
                    config('infimal-smtp.max_daily_limit')
                );
                $limit->save();
            }
        }
    }

    /**
     * Reset daily counters (run daily)
     */
    public function resetDailyLimits(): void
    {
        EmailLimit::query()->update([
            'emails_sent_today' => 0
        ]);
    }
}
