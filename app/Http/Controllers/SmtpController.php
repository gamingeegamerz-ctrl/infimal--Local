<?php

namespace App\Http\Controllers;

use App\Models\SMTPAccount;
use Illuminate\Support\Facades\DB;
use App\Services\SmtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SmtpController extends Controller
{
    public function __construct(private readonly SmtpService $smtpService)
    {
    }

    public function index()
    {
        $userId = Auth::id();
        $smtpSettings = SMTPAccount::ownedBy($userId)->latest()->get()->map(function ($smtp) {
            $failedCount = DB::table('email_logs')
                ->where('smtp_id', $smtp->id)
                ->where('status', 'failed')
                ->count();

            $smtp->smtp_status = !$smtp->is_active
                ? 'failed'
                : ($failedCount > 0 ? 'failed' : 'active');

            $smtp->total_sent = DB::table('email_logs')
                ->where('smtp_id', $smtp->id)
                ->whereIn('status', ['sent', 'delivered'])
                ->count();

            return $smtp;
        });

        $statusCounts = [
            'active' => $smtpSettings->where('smtp_status', 'active')->count(),
            'failed' => $smtpSettings->where('smtp_status', 'failed')->count(),
            'not_connected' => $smtpSettings->count() === 0 ? 1 : 0,
        ];

        return view('smtp.index', [
            'smtpSettings' => $smtpSettings,
            'totalSmtp' => $smtpSettings->count(),
            'activeSmtp' => $smtpSettings->where('is_active', true)->count(),
            'smtpStatusCounts' => $statusCounts,
            'usageStats' => [
                'sent_today' => SMTPAccount::ownedBy($userId)->sum('sent_today'),
                'sent_this_month' => DB::table('email_logs')
                    ->where('user_id', $userId)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'total_sent' => DB::table('email_logs')
                    ->where('user_id', $userId)
                    ->whereIn('status', ['sent', 'delivered'])
                    ->count(),
                'success_rate' => (function () use ($userId) {
                    $total = DB::table('email_logs')->where('user_id', $userId)->count();
                    $sent = DB::table('email_logs')->where('user_id', $userId)->whereIn('status', ['sent', 'delivered'])->count();
                    return $total > 0 ? round(($sent / $total) * 100, 2) : 0;
                })(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:1000',
            'encryption' => 'required|in:tls,ssl,none',
            'from_address' => 'nullable|email|max:255',
            'from_name' => 'nullable|string|max:255',
            'daily_limit' => 'nullable|integer|min:1|max:100000',
            'per_minute_limit' => 'nullable|integer|min:1|max:10000',
            'warmup_enabled' => 'nullable|boolean',
        ]);

        $this->smtpService->saveForUser(Auth::id(), $data);

        return back()->with('success', 'SMTP added successfully.');
    }

    public function show(string $id)
    {
        $smtp = SMTPAccount::ownedBy(Auth::id())->findOrFail($id);
        return response()->json($smtp);
    }

    public function edit(string $id)
    {
        return $this->show($id);
    }

    public function update(Request $request, string $id)
    {
        $smtp = SMTPAccount::ownedBy(Auth::id())->findOrFail($id);

        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:1000',  // ✅ Nullable for updates
            'encryption' => 'required|in:tls,ssl,none',
            'from_address' => 'nullable|email|max:255',
            'from_name' => 'nullable|string|max:255',
            'daily_limit' => 'nullable|integer|min:1|max:100000',
            'per_minute_limit' => 'nullable|integer|min:1|max:10000',
            'warmup_enabled' => 'nullable|boolean',
        ]);

        $this->smtpService->saveForUser(Auth::id(), $data, $smtp);  // ✅ Pass $smtp for update

        return back()->with('success', 'SMTP updated successfully.');
    }

    public function test(Request $request, string $smtp)
    {
        $smtpModel = SMTPAccount::ownedBy(Auth::id())->findOrFail($smtp);
        $data = $request->validate(['email' => 'nullable|email']);
        $target = $data['email'] ?? Auth::user()->email;

        $result = $this->smtpService->testConnection($smtpModel, $target);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function setDefault(string $smtp)
    {
        $smtpModel = SMTPAccount::ownedBy(Auth::id())->findOrFail($smtp);
        $this->smtpService->setDefault($smtpModel);

        return back()->with('success', 'Default SMTP updated.');
    }

    public function verify(string $smtp)
    {
        $smtpModel = SMTPAccount::ownedBy(Auth::id())->findOrFail($smtp);
        $result = $this->smtpService->testConnection($smtpModel, Auth::user()->email);

        return response()->json([
            'verified' => $result['success'],
            'message' => $result['message'],
        ], $result['success'] ? 200 : 422);
    }

    public function getCredentials()
    {
        $smtp = SMTPAccount::ownedBy(Auth::id())
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->latest('id')
            ->first();

        if (!$smtp) {
            return response()->json(['error' => 'SMTP not configured'], 404);
        }

        return response()->json([
            'host' => $smtp->host,
            'port' => $smtp->port,
            'username' => $smtp->username,
            'from_address' => $smtp->from_address,
            'from_name' => $smtp->from_name,
            'encryption' => $smtp->encryption,
        ]);
    }

    public function health()
    {
        $smtp = SMTPAccount::ownedBy(Auth::id())
            ->orderByDesc('is_default')
            ->latest('id')
            ->first();

        if (!$smtp) {
            return response()->json(['status' => 'not_connected']);
        }

        if (!$smtp->is_active) {
            return response()->json(['status' => 'failed']);
        }

        $hasRecentFailures = DB::table('email_logs')
            ->where('smtp_id', $smtp->id)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        return response()->json(['status' => $hasRecentFailures ? 'failed' : 'active']);
    }

    public function destroy(string $id)
    {
        SMTPAccount::ownedBy(Auth::id())->findOrFail($id)->delete();

        return back()->with('success', 'SMTP deleted.');
    }
}
