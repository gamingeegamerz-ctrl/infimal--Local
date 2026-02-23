<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Subscriber;
use App\Models\CampaignAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ============================================
 * TRACKING CONTROLLER
 * ============================================
 * Handles email tracking: opens and clicks
 */
class TrackingController extends Controller
{
    /**
     * Track email open (tracking pixel)
     * GET /track/open?c={campaign_id}&s={subscriber_id}
     */
    public function trackOpen(Request $request)
    {
        try {
            $campaignId = $request->query('c');
            $subscriberId = $request->query('s');

            // Validate inputs
            if (!$campaignId || !$subscriberId) {
                return response('Invalid tracking parameters', 400)
                    ->header('Content-Type', 'text/plain');
            }

            // Verify campaign and subscriber exist
            $campaign = Campaign::find($campaignId);
            $subscriber = Subscriber::find($subscriberId);

            if (!$campaign || !$subscriber) {
                // Return 1x1 transparent pixel even if invalid (to avoid breaking emails)
                return $this->getTransparentPixel();
            }

            // Check if already tracked (avoid duplicate opens)
            $alreadyTracked = CampaignAnalytics::where('campaign_id', $campaignId)
                ->where('subscriber_id', $subscriberId)
                ->where('event_type', 'opened')
                ->exists();

            if (!$alreadyTracked) {
                // Log open event
                CampaignAnalytics::logEvent($campaignId, $subscriberId, 'opened', [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);

                // Update campaign stats
                $campaign->increment('total_opened');
                
                // Update campaign statistics from analytics
                $campaign->updateStatistics();

                Log::info("Email opened", [
                    'campaign_id' => $campaignId,
                    'subscriber_id' => $subscriberId
                ]);
            }

            // Return 1x1 transparent pixel
            return $this->getTransparentPixel();

        } catch (\Exception $e) {
            Log::error("Track open error: " . $e->getMessage());
            // Return pixel even on error to avoid breaking emails
            return $this->getTransparentPixel();
        }
    }

    /**
     * Track email click (redirect)
     * GET /track/click?c={campaign_id}&s={subscriber_id}&url={encoded_url}
     */
    public function trackClick(Request $request)
    {
        try {
            $campaignId = $request->query('c');
            $subscriberId = $request->query('s');
            $redirectUrl = urldecode($request->query('url'));

            // Validate inputs
            if (!$campaignId || !$subscriberId || !$redirectUrl) {
                return redirect('/')->with('error', 'Invalid tracking parameters');
            }

            // Verify campaign and subscriber exist
            $campaign = Campaign::find($campaignId);
            $subscriber = Subscriber::find($subscriberId);

            if (!$campaign || !$subscriber) {
                // Redirect anyway (don't break user experience)
                return redirect($redirectUrl);
            }

            // Check if already tracked for this specific URL
            $alreadyTracked = CampaignAnalytics::where('campaign_id', $campaignId)
                ->where('subscriber_id', $subscriberId)
                ->where('event_type', 'clicked')
                ->where('link_url', $redirectUrl)
                ->exists();

            if (!$alreadyTracked) {
                // Log click event
                CampaignAnalytics::logEvent($campaignId, $subscriberId, 'clicked', [
                    'link_url' => $redirectUrl,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);

                // Update campaign stats
                $campaign->increment('total_clicked');
                
                // Update campaign statistics from analytics
                $campaign->updateStatistics();

                Log::info("Email clicked", [
                    'campaign_id' => $campaignId,
                    'subscriber_id' => $subscriberId,
                    'url' => $redirectUrl
                ]);
            }

            // Redirect to original URL
            return redirect($redirectUrl);

        } catch (\Exception $e) {
            Log::error("Track click error: " . $e->getMessage());
            // Redirect anyway (don't break user experience)
            $redirectUrl = urldecode($request->query('url', '/'));
            return redirect($redirectUrl);
        }
    }

    /**
     * Handle bounce webhook
     * POST /track/bounce
     */
    public function trackBounce(Request $request)
    {
        try {
            $data = $request->validate([
                'campaign_id' => 'required|exists:campaigns,id',
                'subscriber_id' => 'required|exists:subscribers,id',
                'reason' => 'nullable|string',
                'type' => 'nullable|string|in:hard,soft'
            ]);

            $campaign = Campaign::find($data['campaign_id']);
            $subscriber = Subscriber::find($data['subscriber_id']);

            if (!$campaign || !$subscriber) {
                return response()->json(['error' => 'Invalid campaign or subscriber'], 400);
            }

            // Log bounce event
            CampaignAnalytics::logEvent($data['campaign_id'], $data['subscriber_id'], 'bounced', [
                'bounce_reason' => $data['reason'] ?? 'Unknown',
                'ip_address' => $request->ip()
            ]);

            // Mark subscriber as bounced if hard bounce
            if (($data['type'] ?? 'soft') === 'hard') {
                $subscriber->markAsBounced();
            }

            // Update campaign stats
            $campaign->increment('total_bounced');
            $campaign->updateStatistics();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error("Track bounce error: " . $e->getMessage());
            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    /**
     * Handle unsubscribe
     * GET /track/unsubscribe?c={campaign_id}&s={subscriber_id}
     */
    public function unsubscribe(Request $request)
    {
        try {
            $campaignId = $request->query('c');
            $subscriberId = $request->query('s');

            if (!$campaignId || !$subscriberId) {
                return view('tracking.unsubscribe-error');
            }

            $campaign = Campaign::find($campaignId);
            $subscriber = Subscriber::find($subscriberId);

            if (!$campaign || !$subscriber) {
                return response('Invalid unsubscribe link', 400);
            }

            // Mark as unsubscribed
            $subscriber->markAsUnsubscribed();

            // Log unsubscribe event
            CampaignAnalytics::logEvent($campaignId, $subscriberId, 'unsubscribed', [
                'ip_address' => $request->ip()
            ]);

            // Update campaign stats
            $campaign->increment('total_unsubscribed');
            $campaign->updateStatistics();

            // Return simple success message
            return response("You have been successfully unsubscribed from {$campaign->name}.", 200)
                ->header('Content-Type', 'text/plain');

        } catch (\Exception $e) {
            Log::error("Unsubscribe error: " . $e->getMessage());
            return response('An error occurred while processing your unsubscribe request.', 500);
        }
    }

    /**
     * Get 1x1 transparent pixel
     */
    private function getTransparentPixel()
    {
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        return response($pixel, 200)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Process email content and add tracking
     * Adds tracking pixel and wraps links
     */
    public static function processEmailContent($htmlContent, $campaignId, $subscriberId)
    {
        // Add tracking pixel at the end
        $trackingPixelUrl = route('track.open', ['c' => $campaignId, 's' => $subscriberId]);
        $trackingPixel = '<img src="' . $trackingPixelUrl . '" width="1" height="1" style="display:none;" />';
        
        // Add pixel before closing body tag, or at the end if no body tag
        if (stripos($htmlContent, '</body>') !== false) {
            $htmlContent = str_replace('</body>', $trackingPixel . '</body>', $htmlContent);
        } else {
            $htmlContent .= $trackingPixel;
        }

        // Wrap all links with click tracking
        $htmlContent = preg_replace_callback(
            '/<a\s+([^>]*href=["\']([^"\']*)["\'][^>]*)>/i',
            function($matches) use ($campaignId, $subscriberId) {
                $originalUrl = $matches[2];
                $trackingUrl = route('track.click', [
                    'c' => $campaignId,
                    's' => $subscriberId,
                    'url' => urlencode($originalUrl)
                ]);
                
                // Replace href with tracking URL
                $attributes = str_replace($originalUrl, $trackingUrl, $matches[0]);
                return $attributes;
            },
            $htmlContent
        );

        return $htmlContent;
    }
}
