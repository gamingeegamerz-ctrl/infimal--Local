<?php

namespace App\Jobs;

use App\Models\EmailJob;
use App\Models\SMTPAccount;
use App\Models\Campaign;
use App\Models\Subscriber;
use App\Models\CampaignAnalytics;
use App\Services\TrustManager;
use App\Services\ThrottleService;
use App\Http\Controllers\TrackingController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * ============================================
 * SEND CAMPAIGN EMAIL JOB
 * ============================================
 * Handles individual email sending with:
 * - Throttle control
 * - SMTP selection and fallback
 * - Email tracking (pixel + click wrapping)
 * - Retry logic (max 3 attempts)
 * - Bounce handling
 */
class SendCampaignEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $emailJobId;

    public $tries = 3; // Max retries
    public $timeout = 120;

    public function __construct(int $emailJobId)
    {
        $this->emailJobId = $emailJobId;
    }

    /**
     * Handle the job
     */
    public function handle(): void
    {
        $emailJob = EmailJob::find($this->emailJobId);

        // Already processed or doesn't exist
        if (!$emailJob || $emailJob->status === 'sent') {
            return;
        }

        // Update status to processing
        if ($emailJob->status === 'queued') {
            $emailJob->update(['status' => 'processing']);
        }

        /*
        |------------------------------------------------------------------
        | 1. THROTTLE CHECK
        |------------------------------------------------------------------
        */
        $throttleService = new ThrottleService();
        $throttleCheck = $throttleService->canSendNow($emailJob->user_id);

        if (!$throttleCheck['can_send']) {
            // Delay job until throttle allows
            $this->release($throttleCheck['delay_seconds']);
            Log::info("Email job throttled", [
                'email_job_id' => $this->emailJobId,
                'reason' => $throttleCheck['reason'],
                'delay' => $throttleCheck['delay_seconds']
            ]);
            return;
        }

        /*
        |------------------------------------------------------------------
        | 2. TRUST CHECK (if TrustManager exists)
        |------------------------------------------------------------------
        */
        try {
            $trust = new TrustManager($emailJob->user_id);
            if (!$trust->canSend()) {
                $this->release(300);
                return;
            }
        } catch (\Exception $e) {
            // TrustManager might not exist, continue
            Log::debug("TrustManager not available, skipping trust check");
        }

        /*
        |------------------------------------------------------------------
        | 3. PICK SMTP (with priority and daily limits)
        |------------------------------------------------------------------
        */
        $smtp = SMTPAccount::pickForSending($emailJob->user_id);

        if (!$smtp || !$smtp->canSendNow()) {
            $this->release(120);
            return;
        }

        // Mark SMTP as used
        $smtp->markUsed();
        $emailJob->update(['smtp_id' => $smtp->id]);

        /*
        |------------------------------------------------------------------
        | 4. PREPARE EMAIL CONTENT WITH TRACKING
        |------------------------------------------------------------------
        */
        $htmlContent = $emailJob->html ?? $emailJob->body;
        
        // Add tracking pixel and wrap links if campaign exists
        if ($emailJob->campaign_id && $emailJob->subscriber_id) {
            $htmlContent = TrackingController::processEmailContent(
                $htmlContent,
                $emailJob->campaign_id,
                $emailJob->subscriber_id
            );
        }

        /*
        |------------------------------------------------------------------
        | 5. CONFIGURE SMTP
        |------------------------------------------------------------------
        */
        config([
            'mail.default'                 => 'smtp',
            'mail.mailers.smtp.host'       => $smtp->smtp_host,
            'mail.mailers.smtp.port'       => $smtp->smtp_port,
            'mail.mailers.smtp.encryption' => $smtp->smtp_port == 465 ? 'ssl' : 'tls',
            'mail.mailers.smtp.username'   => $smtp->smtp_username,
            'mail.mailers.smtp.password'   => $smtp->smtp_password,
            'mail.from.address'            => $emailJob->from_email,
            'mail.from.name'               => $emailJob->from_name,
        ]);

        /*
        |------------------------------------------------------------------
        | 6. SEND EMAIL
        |------------------------------------------------------------------
        */
        try {
            Mail::html($htmlContent, function ($message) use ($emailJob) {
                $message->to($emailJob->to_email, $emailJob->to_name)
                    ->subject($emailJob->subject);

                if ($emailJob->reply_to) {
                    $message->replyTo($emailJob->reply_to);
                }
            });

            /*
            |------------------------------------------------------------------
            | 7. SUCCESS - UPDATE RECORDS
            |------------------------------------------------------------------
            */
            $emailJob->update([
                'status' => 'sent',
                'sent_at' => now(),
                'retry_count' => $emailJob->retry_count
            ]);

            // Record throttle usage
            $throttleService->recordSent($emailJob->user_id);

            // Update SMTP stats
            $smtp->markHourlySend();

            // Update trust manager if available
            try {
                $trust->incrementTodayUsage();
            } catch (\Exception $e) {
                // TrustManager might not exist
            }

            // Log to email_logs
            DB::table('email_logs')->insert([
                'user_id'     => $emailJob->user_id,
                'campaign_id' => $emailJob->campaign_id,
                'smtp_id'     => $smtp->id,
                'to_email'    => $emailJob->to_email,
                'status'      => 'sent',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            // Log sent event in campaign analytics
            if ($emailJob->campaign_id && $emailJob->subscriber_id) {
                CampaignAnalytics::logEvent(
                    $emailJob->campaign_id,
                    $emailJob->subscriber_id,
                    'sent'
                );

                // Update campaign stats
                $campaign = Campaign::find($emailJob->campaign_id);
                if ($campaign) {
                    $campaign->increment('total_sent');
                    $campaign->updateStatistics();
                }
            }

            Log::info("Email sent successfully", [
                'email_job_id' => $this->emailJobId,
                'to' => $emailJob->to_email,
                'smtp_id' => $smtp->id
            ]);

        } catch (Throwable $e) {
            /*
            |------------------------------------------------------------------
            | 8. FAILURE - HANDLE RETRY OR BOUNCE
            |------------------------------------------------------------------
            */
            $this->handleFailure($emailJob, $smtp, $e);
        }
    }

    /**
     * Handle email sending failure
     */
    private function handleFailure(EmailJob $emailJob, ?SMTPAccount $smtp, Throwable $e): void
    {
        $emailJob->increment('retry_count');

        Log::error('SendCampaignEmailJob failed', [
            'email_job_id' => $this->emailJobId,
            'retry_count' => $emailJob->retry_count,
            'error' => $e->getMessage(),
            'smtp_id' => $smtp->id ?? null
        ]);

        // Check if we should retry with fallback SMTP
        if ($emailJob->retry_count < $this->tries) {
            // Try fallback SMTP
            if ($smtp) {
                $fallbackSmtp = SMTPAccount::pickFallback($smtp->id, $emailJob->user_id);
                
                if ($fallbackSmtp && $fallbackSmtp->canSendNow()) {
                    // Temporarily disable failed SMTP
                    $smtp->temporarilyDisable(60);
                    $smtp->reduceReputation(2);
                    
                    Log::info("Retrying with fallback SMTP", [
                        'email_job_id' => $this->emailJobId,
                        'failed_smtp' => $smtp->id,
                        'fallback_smtp' => $fallbackSmtp->id
                    ]);

                    // Release job to retry immediately with new SMTP
                    $emailJob->update(['smtp_id' => $fallbackSmtp->id]);
                    $this->release(10);
                    return;
                }
            }

            // No fallback available, retry later
            $emailJob->update([
                'status' => 'queued',
                'failed_at' => now(),
                'error_message' => substr($e->getMessage(), 0, 500)
            ]);

            if ($smtp) {
                $smtp->reduceReputation(2);
            }

            // Exponential backoff
            $delay = min(60 * ($emailJob->retry_count ** 2), 3600);
            $this->release($delay);

        } else {
            /*
            |------------------------------------------------------------------
            | PERMANENT FAILURE - MARK AS BOUNCED
            |------------------------------------------------------------------
            */
            $emailJob->update([
                'status' => 'bounced',
                'failed_at' => now(),
                'error_message' => substr($e->getMessage(), 0, 500)
            ]);

            // Mark subscriber as bounced
            if ($emailJob->subscriber_id) {
                $subscriber = Subscriber::find($emailJob->subscriber_id);
                if ($subscriber) {
                    $subscriber->markAsBounced();
                }

                // Log bounce event
                if ($emailJob->campaign_id) {
                    CampaignAnalytics::logEvent(
                        $emailJob->campaign_id,
                        $emailJob->subscriber_id,
                        'bounced',
                        ['bounce_reason' => $e->getMessage()]
                    );

                    // Update campaign stats
                    $campaign = Campaign::find($emailJob->campaign_id);
                    if ($campaign) {
                        $campaign->increment('total_bounced');
                        $campaign->updateStatistics();
                    }
                }
            }

            // Reduce SMTP reputation
            if ($smtp) {
                $smtp->reduceReputation(5);
                $smtp->temporarilyDisable(120);
            }

            // Log to email_logs
            DB::table('email_logs')->insert([
                'user_id'     => $emailJob->user_id,
                'campaign_id' => $emailJob->campaign_id,
                'smtp_id'     => $smtp->id ?? null,
                'to_email'    => $emailJob->to_email,
                'status'      => 'bounced',
                'error'       => substr($e->getMessage(), 0, 500),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            Log::warning("Email permanently failed after retries", [
                'email_job_id' => $this->emailJobId,
                'to' => $emailJob->to_email
            ]);
        }
    }
}
