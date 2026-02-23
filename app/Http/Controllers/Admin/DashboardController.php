<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\License;
use App\Models\Campaign;
use App\Models\Smtp;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // User Statistics
        $totalUsers = User::count();
        $todayUsers = User::whereDate('created_at', today())->count();
        $activeUsers = User::where('is_active', true)->count();
        $paidUsers = User::where('is_paid', true)->count();
        
        // License Statistics
        $totalLicenses = License::count();
        $activeLicenses = License::where('is_active', true)
            ->where(function($query) {
                $query->where('is_lifetime', true)
                      ->orWhere('expires_at', '>', now());
            })->count();
        $expiredLicenses = License::where('is_active', true)
            ->where('is_lifetime', false)
            ->where('expires_at', '<=', now())
            ->count();
        
        // Revenue Statistics ($299 per license)
        $totalRevenue = License::sum('price');
        $todayRevenue = License::whereDate('created_at', today())->sum('price');
        $monthRevenue = License::whereMonth('created_at', now()->month)->sum('price');
        
        // Campaign Statistics
        $totalCampaigns = Campaign::count();
        $activeCampaigns = Campaign::where('status', 'active')->count();
        
        // SMTP Statistics
        $totalSmtp = Smtp::count();
        $activeSmtp = Smtp::where('is_active', true)->count();
        
        // Recent Users
        $recentUsers = User::orderBy('created_at', 'desc')->limit(10)->get();
        
        // Recent Licenses
        $recentLicenses = License::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        // Daily Revenue Chart Data (Last 30 days)
        $dailyRevenue = License::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as license_count'),
                DB::raw('SUM(price) as revenue')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        return view('admin.dashboard', compact(
            'totalUsers',
            'todayUsers',
            'activeUsers',
            'paidUsers',
            'totalLicenses',
            'activeLicenses',
            'expiredLicenses',
            'totalRevenue',
            'todayRevenue',
            'monthRevenue',
            'totalCampaigns',
            'activeCampaigns',
            'totalSmtp',
            'activeSmtp',
            'recentUsers',
            'recentLicenses',
            'dailyRevenue'
        ));
    }
}
