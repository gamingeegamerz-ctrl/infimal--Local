<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        // Double check license (extra security)
        if (!$user->canAccessDashboard()) {
            Log::error('Unauthorized dashboard access', [
                'user_id' => $user->id,
                'license_status' => $user->license_status
            ]);
            
            return redirect()->route('payment.page')
                ->with('error', 'License verification required to access dashboard.');
        }

        Log::info('Dashboard accessed', ['user_id' => $user->id]);

        // Get user-specific data
        $data = [
            'totalSubscribers' => 0, // Will be real data later
            'activeSubscribers' => 0,
            'campaignsSent' => 0,
            'totalOpens' => 0,
            'totalClicks' => 0,
            'subscriberGrowth' => 0,
            'emailsToday' => 0,
            'opensToday' => 0,
            'clicksToday' => 0,
            'activeCampaigns' => 0,
            'bounceRate' => 0,
            'growthData' => [0, 0, 0, 0, 0, 0, 0],
            'growthLabels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'recentActivities' => [
                'Welcome to InfiMal Dashboard',
                'Your license is active until: ' . $user->license_expires_at->format('Y-m-d'),
                'Start by creating your first email list',
                'Connect your SMTP to send emails'
            ],
            'user' => $user,
            'license_expires' => $user->license_expires_at->format('M d, Y')
        ];

        return view('dashboard', $data);
    }

    public function getStats()
    {
        $user = Auth::user();
        
        if (!$user->canAccessDashboard()) {
            return response()->json(['error' => 'License required'], 403);
        }

        // Return real-time stats
        return response()->json([
            'emailsToday' => 0,
            'opensToday' => 0,
            'clicksToday' => 0,
            'activeCampaigns' => 0,
            'updated_at' => now()->toDateTimeString()
        ]);
    }
}
