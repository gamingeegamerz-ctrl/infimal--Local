<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MessageController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        if (!Schema::hasTable('messages')) {
            return view('messages.index', [
                'messages' => collect([]),
                'stats' => $this->emptyStats($userId),
                'totalMessages' => 0,
                'unreadMessages' => 0,
                'emailsSent' => DB::table('email_logs')->where('user_id', $userId)->count(),
                'queueStatus' => 'Not configured',
            ]);
        }

        $messages = DB::table('messages')->where('user_id', $userId)->latest()->paginate(15);
        $todayCount = DB::table('messages')->where('user_id', $userId)->whereDate('created_at', today())->count();

        $stats = [
            'totalMessages' => DB::table('messages')->where('user_id', $userId)->count(),
            'unreadCount' => DB::table('messages')->where('user_id', $userId)->where('is_read', false)->count(),
            'systemMessagesCount' => DB::table('messages')->where('user_id', $userId)->where('type', 'system')->count(),
            'todayMessagesCount' => $todayCount,
            'campaignAlertsCount' => DB::table('messages')->where('user_id', $userId)->where('type', 'alert')->count(),
            'highPriorityCount' => 0,
            'thisMonthCount' => DB::table('messages')->where('user_id', $userId)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'growthRate' => 0,
        ];

        return view('messages.index', [
            'messages' => $messages,
            'stats' => $stats,
            'totalMessages' => $stats['totalMessages'],
            'unreadMessages' => $stats['unreadCount'],
            'emailsSent' => DB::table('email_logs')->where('user_id', $userId)->count(),
            'queueStatus' => Schema::hasTable('jobs') ? 'Active' : 'Not configured',
        ]);
    }

    private function emptyStats(int $userId): array
    {
        return [
            'totalMessages' => 0,
            'unreadCount' => 0,
            'systemMessagesCount' => 0,
            'todayMessagesCount' => 0,
            'campaignAlertsCount' => 0,
            'highPriorityCount' => 0,
            'thisMonthCount' => 0,
            'growthRate' => 0,
        ];
    }

    public function create()
    {
        return view('messages.create');
    }
}
