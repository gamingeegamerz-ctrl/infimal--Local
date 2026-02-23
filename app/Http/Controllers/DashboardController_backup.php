<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index()
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please login first.');
        }

        $user = Auth::user();
        
        Log::info('Dashboard access attempt', [
            'user_id' => $user->id,
            'email' => $user->email,
            'license_status' => $user->license_status,
            'license_key' => $user->license_key ? 'Yes' : 'No'
        ]);
        
        if (!$user->license_key || $user->license_status !== 'active') {
            Log::warning('License check failed', [
                'user_id' => $user->id,
                'has_key' => $user->license_key ? 'Yes' : 'No',
                'status' => $user->license_status
            ]);
            
            if ($user->license_key && $user->license_status === 'pending') {
                return redirect()->route('verify.license')
                    ->with('warning', 'Please verify your license key.');
            }
            
            return redirect()->route('payment.page')
                ->with('warning', 'Please complete payment to access dashboard.');
        }

        Log::info('Dashboard accessed successfully', ['user_id' => $user->id]);

        $data = [
            'totalSubscribers' => 0,
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
            'growthData' => [65, 59, 80, 81, 56, 55, 40],
            'growthLabels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'recentActivities' => [
                'Welcome to InfiMal Dashboard!',
                'Your license is active until: ' . ($user->license_expires_at ? $user->license_expires_at->format('Y-m-d') : 'Lifetime'),
                'Start by creating your first email list',
                'Connect your SMTP to send emails',
                'Create your first campaign'
            ],
            'user' => $user,
            'license_expires' => $user->license_expires_at ? $user->license_expires_at->format('M d, Y') : 'Lifetime'
        ];

        return view('dashboard', $data);
    }

    public function getStats()
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $user = Auth::user();
        
        if (!$user->license_key || $user->license_status !== 'active') {
            return response()->json(['error' => 'License required'], 403);
        }

        return response()->json([
            'emailsToday' => rand(50, 200),
            'opensToday' => rand(20, 100),
            'clicksToday' => rand(5, 50),
            'activeCampaigns' => rand(1, 5),
            'totalSubscribers' => rand(100, 1000),
            'activeSubscribers' => rand(80, 900),
            'openRate' => rand(20, 40) . '%',
            'clickRate' => rand(2, 10) . '%',
            'updated_at' => now()->toDateTimeString()
        ]);
    }
}
