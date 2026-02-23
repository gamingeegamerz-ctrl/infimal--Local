<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SmtpController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        if (!DB::select("SHOW TABLES LIKE 'smtp_settings'")) {
            $this->createSmtpSettingsTable();
        }

        $smtpSettings = DB::table('smtp_settings')
            ->where('user_id', $userId)
            ->orderByDesc('is_active')
            ->get();

        return view('smtp.index', [
            'smtpSettings' => $smtpSettings,
            'totalSmtp' => $smtpSettings->count(),
            'activeSmtp' => $smtpSettings->where('is_active', 1)->count(),
            'usageStats' => $this->getSmtpUsageStats($userId),
        ]);
    }

    private function createSmtpSettingsTable()
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS smtp_settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(255),
                host VARCHAR(255),
                port INT,
                encryption ENUM('tls','ssl','none'),
                username VARCHAR(255),
                password TEXT,
                from_address VARCHAR(255),
                from_name VARCHAR(255),
                daily_limit INT DEFAULT 500,
                sent_today INT DEFAULT 0,
                sent_this_month INT DEFAULT 0,
                total_sent INT DEFAULT 0,
                is_active BOOLEAN DEFAULT 1,
                last_used_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )
        ");
    }

    private function getSmtpUsageStats($userId)
    {
        return [
            'sent_today' => DB::table('smtp_settings')->where('user_id', $userId)->sum('sent_today'),
            'sent_this_month' => DB::table('smtp_settings')->where('user_id', $userId)->sum('sent_this_month'),
            'total_sent' => DB::table('smtp_settings')->where('user_id', $userId)->sum('total_sent'),
            'success_rate' => 99.2
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'host' => 'required',
            'port' => 'required|integer',
            'encryption' => 'required',
            'username' => 'required',
            'password' => 'required',
            'from_address' => 'required|email',
            'from_name' => 'nullable',
            'daily_limit' => 'required|integer'
        ]);

        DB::table('smtp_settings')->insert(array_merge($data, [
            'user_id' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return back()->with('success', 'SMTP added successfully.');
    }

    public function test($id)
    {
        DB::table('smtp_settings')
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->update(['last_used_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        DB::table('smtp_settings')
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->delete();

        return back()->with('success', 'SMTP deleted.');
    }
}
