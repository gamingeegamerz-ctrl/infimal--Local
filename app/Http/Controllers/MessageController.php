<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $messages = DB::table('email_jobs')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(15);

        $totalMessages = DB::table('email_logs')->where('user_id', $userId)->count();
        $unreadMessages = DB::table('email_jobs')
            ->where('user_id', $userId)
            ->whereIn('status', ['queued', 'processing'])
            ->count();

        return view('messages.index', compact('messages', 'totalMessages', 'unreadMessages'));
    }

    public function create()
    {
        return view('messages.create');
    }
}
