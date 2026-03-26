<?php

namespace App\Http\Controllers;

use App\Models\SMTPAccount;
use App\Services\SmtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SmtpController extends Controller
{
    public function __construct(private readonly SmtpService $smtpService)
    {
    }

    public function index()
    {
        $userId = Auth::id();
        $smtpSettings = SMTPAccount::ownedBy($userId)->latest()->get();

        $activeSmtp = $smtpSettings->where('is_active', true)->count();
        $failedSmtp = $smtpSettings->where('last_test_status', 'failed')->count();
        $notConnectedSmtp = $smtpSettings->whereNull('last_test_status')->count();

        return view('smtp.index', [
            'smtpSettings' => $smtpSettings,
            'totalSmtp' => $smtpSettings->count(),
            'activeSmtp' => $activeSmtp,
            'failedSmtp' => $failedSmtp,
            'notConnectedSmtp' => $notConnectedSmtp,
            'usageStats' => [
                'sent_today' => DB::table('email_logs')->where('user_id', $userId)->whereDate('created_at', today())->count(),
                'sent_this_month' => DB::table('email_logs')->where('user_id', $userId)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
                'total_sent' => DB::table('email_logs')->where('user_id', $userId)->where('status', 'sent')->count(),
                'success_rate' => $this->successRate($userId),
            ],
        ]);
    }

    private function successRate(int $userId): float
    {
        $total = DB::table('email_logs')->where('user_id', $userId)->count();
        if ($total === 0) {
            return 0;
        }

        $sent = DB::table('email_logs')->where('user_id', $userId)->where('status', 'sent')->count();

        return round(($sent / $total) * 100, 2);
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

        $smtp = $this->smtpService->saveForUser(Auth::id(), $data);
        $smtp->update(['last_test_status' => null, 'last_tested_at' => null]);

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
            'password' => 'nullable|string|max:1000',
            'encryption' => 'required|in:tls,ssl,none',
            'from_address' => 'nullable|email|max:255',
            'from_name' => 'nullable|string|max:255',
            'daily_limit' => 'nullable|integer|min:1|max:100000',
            'per_minute_limit' => 'nullable|integer|min:1|max:10000',
            'warmup_enabled' => 'nullable|boolean',
        ]);

        $this->smtpService->saveForUser(Auth::id(), $data, $smtp);

        return back()->with('success', 'SMTP updated successfully.');
    }

    public function test(Request $request, string $smtp)
    {
        $smtpModel = SMTPAccount::ownedBy(Auth::id())->findOrFail($smtp);
        $data = $request->validate(['email' => 'nullable|email']);
        $target = $data['email'] ?? Auth::user()->email;

        $result = $this->smtpService->testConnection($smtpModel, $target);

        $smtpModel->update([
            'last_test_status' => $result['success'] ? 'active' : 'failed',
            'last_tested_at' => now(),
            'is_active' => $result['success'],
        ]);

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
        return $this->test(request(), $smtp);
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

    public function destroy(string $id)
    {
        SMTPAccount::ownedBy(Auth::id())->findOrFail($id)->delete();

        return back()->with('success', 'SMTP deleted.');
    }
}
