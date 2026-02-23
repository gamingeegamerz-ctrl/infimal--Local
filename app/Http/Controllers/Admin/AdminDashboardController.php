<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    /* ---------- MAIN DASHBOARD ---------- */
    public function index()
    {
        /* --- Users --- */
        $totalUsers           = $this->safeCount('users');
        $usersToday           = $this->safeCount('users', fn($q) => $q->whereDate('created_at', today()));

        /* --- Licences --- */
        $activeLicenses       = $this->safeCount('licenses', fn($q) => $q->where('status', 'active'));
        $licensesToday        = $this->safeCount('licenses', fn($q) => $q->whereDate('created_at', today()));

        /* --- E-mails --- */
        $totalEmailsSent      = $this->safeCount('email_logs');
        $emailsToday          = $this->safeCount('email_logs', fn($q) => $q->whereDate('created_at', today()));

        /* --- Trust --- */
        $frozenUsers          = $this->safeCount('user_trust', fn($q) => $q->where('is_frozen', true));
        $trustStats           = $this->safeTrustStats();
        $avgTrustScore        = $this->safeAvg('user_trust', 'trust_score');

        /* --- Revenue --- */
        $totalRevenue         = $this->safeRevenue();
        $revenueToday         = $this->safeRevenue(today());

        /* --- Campaigns --- */
        $avgOpenRate          = $this->safeAvg('campaigns', 'open_rate');
        $avgClickRate         = $this->safeAvg('campaigns', 'click_rate');

        /* --- Recent users --- */
        $users                = $this->safeRecentUsers();

        /* --- Activity --- */
        $recentActivity       = $this->safeRecentActivity();

        /* --- Growth & health --- */
        $growthData           = $this->getGrowthData();
        $systemHealth         = $this->getSystemHealth();

        $activeLicensesPercentage = $totalUsers ? round(($activeLicenses / $totalUsers) * 100, 1) : 0;

        return view('admin.dashboard', compact(
            'totalUsers','activeLicenses','activeLicensesPercentage','totalEmailsSent',
            'emailsToday','frozenUsers','usersToday','licensesToday','trustStats',
            'avgTrustScore','totalRevenue','revenueToday','avgOpenRate','avgClickRate',
            'users','recentActivity','growthData','systemHealth'
        ));
    }

    /* ---------- API ---------- */
    public function stats(Request $request)
    {
        return response()->json([
            'totalUsers'        => $this->safeCount('users'),
            'activeLicenses'    => $this->safeCount('licenses', fn($q) => $q->where('status', 'active')),
            'emailsToday'       => $this->safeCount('email_logs', fn($q) => $q->whereDate('created_at', today())),
            'frozenUsers'       => $this->safeCount('user_trust', fn($q) => $q->where('is_frozen', true)),
            'usersToday'        => $this->safeCount('users', fn($q) => $q->whereDate('created_at', today())),
            'revenueToday'      => $this->safeRevenue(today()),
            'activeCampaigns'   => $this->safeCount('campaigns', fn($q) => $q->whereIn('status', ['sending','scheduled'])),
            'timestamp'         => now()->toDateTimeString(),
        ]);
    }

    /* ---------- EXPORT ---------- */
    public function export(Request $request)
    {
        $type = $request->get('type', 'csv');
        $data = $this->getExportData();

        if ($type === 'json') {
            return response()->json($data);
        }

        $filename = 'admin_dashboard_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($data) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, ['Metric', 'Value', 'Timestamp']);
            foreach ($data as $row) {
                fputcsv($fh, $row);
            }
            fclose($fh);
        }, 200, $headers);
    }

    /* ---------- INTERNAL HELPERS ---------- */

    private function safeCount(string $table, ?callable $filter = null): int
    {
        try {
            $q = DB::table($table);
            if ($filter) {
                $filter($q);
            }
            return (int) $q->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeAvg(string $table, string $column): float
    {
        try {
            return (float) DB::table($table)->avg($column);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function safeRevenue(?Carbon $day = null): float
    {
        try {
            $q = DB::table('payments')->where('status', 'completed');
            if ($day) {
                $q->whereDate('created_at', $day);
            }
            return ((float) $q->sum('amount')) / 100;
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function safeTrustStats(): array
    {
        try {
            return DB::table('user_trust')
                ->selectRaw('stage, count(*) as total')
                ->groupBy('stage')
                ->orderBy('stage')
                ->get()
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    private function safeRecentUsers(): array
    {
        try {
            return DB::table('users')
                ->leftJoin('user_trust', 'users.id', '=', 'user_trust.user_id')
                ->leftJoin('licenses', 'users.id', '=', 'licenses.user_id')
                ->select(
                    'users.id','users.name','users.email','users.created_at',
                    'user_trust.stage','user_trust.trust_score','user_trust.is_frozen',
                    'licenses.status as license_status','licenses.expires_at'
                )
                ->orderByDesc('users.id')
                ->limit(15)
                ->get()
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    private function safeRecentActivity(): array
    {
        try {
            return DB::table('activity_logs')
                ->select('description', 'created_at', 'user_id')
                ->latest()
                ->limit(10)
                ->get()
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    private function getGrowthData(): array
    {
        $data = [];
        for ($i = 30; $i >= 0; $i--) {
            $date   = Carbon::now()->subDays($i);
            $users  = $this->safeCount('users', fn($q) => $q->whereDate('created_at', $date));
            $emails = $this->safeCount('email_logs', fn($q) => $q->whereDate('created_at', $date));
            $rev    = $this->safeRevenue($date);

            $data[] = [
                'date'     => $date->format('M d'),
                'users'    => $users,
                'emails'   => $emails,
                'revenue'  => $rev,
            ];
        }
        return $data;
    }

    private function getSystemHealth(): array
    {
        // DB
        $dbStatus = true;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $dbStatus = false;
        }

        // Disk
        $storagePath  = storage_path();
        $totalSpace   = @disk_total_space($storagePath) ?: 0;
        $freeSpace    = @disk_free_space($storagePath) ?: 0;
        $usedPercent  = $totalSpace > 0 ? (($totalSpace - $freeSpace) / $totalSpace) * 100 : 0;

        // Queue
        $queueJobs = $this->safeCount('jobs');

        return [
            'database'      => $dbStatus,
            'storage'       => [
                'total'       => $this->formatBytes($totalSpace),
                'free'        => $this->formatBytes($freeSpace),
                'used_percent'=> round($usedPercent, 1),
            ],
            'queue'         => $queueJobs,
            'last_cron'     => Cache::get('last_cron_run', 'Never'),
            'uptime'        => @exec('uptime -p') ?: 'Unknown',
        ];
    }

    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max((int) $bytes, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = min($pow, count($units) - 1);
        return round($bytes / (1 << (10 * $pow)), $precision) . ' ' . $units[$pow];
    }

    private function getExportData(): array
    {
        return [
            ['Total Users',        $this->safeCount('users'),                          now()],
            ['Active Licenses',    $this->safeCount('licenses', fn($q)=>$q->where('status','active')), now()],
            ['Total Emails Sent',  $this->safeCount('email_logs'),                     now()],
            ['Emails Today',       $this->safeCount('email_logs', fn($q)=>$q->whereDate('created_at',today())), now()],
            ['Frozen Users',       $this->safeCount('user_trust', fn($q)=>$q->where('is_frozen',true)), now()],
            ['Total Revenue',      $this->safeRevenue(),                               now()],
            ['Avg Trust Score',    round($this->safeAvg('user_trust','trust_score'),1), now()],
            ['Avg Open Rate',      round($this->safeAvg('campaigns','open_rate'),1).'%', now()],
            ['Avg Click Rate',     round($this->safeAvg('campaigns','click_rate'),1).'%', now()],
        ];
    }
}