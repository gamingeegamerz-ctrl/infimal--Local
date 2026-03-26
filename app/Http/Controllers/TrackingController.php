<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TrackingController extends Controller
{
    public function openById(int $id): Response
    {
        $log = EmailLog::find($id);

        if ($log && !$log->opened) {
            $log->update([
                'opened' => true,
                'opened_at' => now(),
                'opens_count' => ($log->opens_count ?? 0) + 1,
            ]);
        }

        return $this->pixel();
    }

    public function clickById(Request $request, int $id): RedirectResponse
    {
        $log = EmailLog::find($id);

        if ($log && !$log->clicked) {
            $log->update([
                'clicked' => true,
                'clicked_at' => now(),
                'clicks_count' => ($log->clicks_count ?? 0) + 1,
            ]);
        }

        $url = (string) $request->query('url', '/');
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $url = url('/');
        }

        return redirect()->away($url);
    }

    public function trackOpen(Request $request): Response
    {
        return $request->filled('id')
            ? $this->openById((int) $request->query('id'))
            : $this->pixel();
    }

    public function trackClick(Request $request): RedirectResponse
    {
        return $request->filled('id')
            ? $this->clickById($request, (int) $request->query('id'))
            : redirect('/');
    }

    public function trackBounce(Request $request)
    {
        $data = $request->validate(['message_id' => 'required|string|max:255']);

        EmailLog::where('message_id', $data['message_id'])
            ->whereNull('bounced_at')
            ->update(['status' => 'bounced', 'bounced_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function unsubscribe(Request $request)
    {
        return response('You have been unsubscribed.', 200);
    }

    private function pixel(): Response
    {
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Zr90AAAAASUVORK5CYII=');

        return response($pixel, 200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    public static function processEmailContent(string $htmlContent, int $logId): string
    {
        $pixel = '<img src="' . url('/track/open/' . $logId . '.png') . '" width="1" height="1" style="display:none;" />';

        if (stripos($htmlContent, '</body>') !== false) {
            $htmlContent = str_ireplace('</body>', $pixel . '</body>', $htmlContent);
        } else {
            $htmlContent .= $pixel;
        }

        return preg_replace_callback('/<a\s+([^>]*href=["\']([^"\']+)["\'][^>]*)>/i', function ($matches) use ($logId) {
            $originalUrl = $matches[2];
            $trackingUrl = url('/track/click/' . $logId . '?url=' . urlencode($originalUrl));
            return str_replace($originalUrl, $trackingUrl, $matches[0]);
        }, $htmlContent) ?? $htmlContent;
    }
}
