<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\UserTrust;

class TrustController extends Controller
{
    /**
     * Display trust system overview
     */
    public function index()
    {
        $totalUsers = DB::table('users')->count();
        $trustStats = DB::table('user_trust')
            ->select('stage', DB::raw('count(*) as total'))
            ->groupBy('stage')
            ->orderBy('stage')
            ->get();
        
        $avgTrustScore = DB::table('user_trust')->avg('trust_score') ?? 0;
        $frozenUsers = DB::table('user_trust')->where('is_frozen', true)->count();
        
        // Recent trust changes
        $recentChanges = DB::table('trust_logs')
            ->join('users', 'trust_logs.user_id', '=', 'users.id')
            ->select(
                'trust_logs.*',
                'users.name',
                'users.email'
            )
            ->orderByDesc('trust_logs.created_at')
            ->limit(20)
            ->get();
        
        // Stage distribution
        $stageDistribution = [];
        foreach ($trustStats as $stat) {
            $stageDistribution[] = [
                'stage' => $stat->stage,
                'count' => $stat->total,
                'percentage' => $totalUsers > 0 ? round(($stat->total / $totalUsers) * 100, 1) : 0
            ];
        }
        
        return view('admin.trust.index', compact(
            'totalUsers',
            'trustStats',
            'avgTrustScore',
            'frozenUsers',
            'recentChanges',
            'stageDistribution'
        ));
    }
    
    /**
     * Manage individual user trust
     */
    public function manage($id)
    {
        $user = User::with('trust')->findOrFail($id);
        
        $trustLogs = DB::table('trust_logs')
            ->where('user_id', $id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
        
        $emailStats = DB::table('email_logs')
            ->where('user_id', $id)
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) as opened'),
                DB::raw('SUM(CASE WHEN clicked = 1 THEN 1 ELSE 0 END) as clicked')
            )
            ->first();
        
        return view('admin.trust.manage', compact(
            'user',
            'trustLogs',
            'emailStats'
        ));
    }
    
    /**
     * Update user trust
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'stage' => 'required|integer|min:1|max:5',
            'trust_score' => 'required|integer|min:0|max:1000',
            'is_frozen' => 'boolean',
            'notes' => 'nullable|string',
        ]);
        
        $trust = UserTrust::where('user_id', $id)->firstOrFail();
        
        // Log old values
        $oldStage = $trust->stage;
        $oldScore = $trust->trust_score;
        
        // Update trust
        $trust->update([
            'stage' => $request->stage,
            'trust_score' => $request->trust_score,
            'is_frozen' => $request->is_frozen ?? false,
        ]);
        
        // Log the change
        DB::table('trust_logs')->insert([
            'user_id' => $id,
            'action' => 'admin_manual_update',
            'old_stage' => $oldStage,
            'new_stage' => $request->stage,
            'old_score' => $oldScore,
            'new_score' => $request->trust_score,
            'reason' => $request->notes,
            'admin_id' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Trust settings updated successfully.'
        ]);
    }
    
    /**
     * Reset user trust
     */
    public function reset($id)
    {
        $trust = UserTrust::where('user_id', $id)->firstOrFail();
        
        // Log old values
        $oldStage = $trust->stage;
        $oldScore = $trust->trust_score;
        
        // Reset to defaults
        $trust->update([
            'stage' => 1,
            'trust_score' => 100,
            'is_frozen' => false,
        ]);
        
        // Log the reset
        DB::table('trust_logs')->insert([
            'user_id' => $id,
            'action' => 'admin_reset',
            'old_stage' => $oldStage,
            'new_stage' => 1,
            'old_score' => $oldScore,
            'new_score' => 100,
            'reason' => 'Reset by administrator',
            'admin_id' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Trust reset to defaults.'
        ]);
    }
    
    /**
     * View trust logs
     */
    public function logs(Request $request)
    {
        $query = DB::table('trust_logs')
            ->join('users', 'trust_logs.user_id', '=', 'users.id')
            ->leftJoin('users as admins', 'trust_logs.admin_id', '=', 'admins.id')
            ->select(
                'trust_logs.*',
                'users.name as user_name',
                'users.email as user_email',
                'admins.name as admin_name'
            );
        
        // Filter by user
        if ($request->has('user_id')) {
            $query->where('trust_logs.user_id', $request->user_id);
        }
        
        // Filter by action
        if ($request->has('action')) {
            $query->where('trust_logs.action', $request->action);
        }
        
        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('trust_logs.created_at', '>=', $request->date_from);
        }
        
        if ($request->has('date_to')) {
            $query->whereDate('trust_logs.created_at', '<=', $request->date_to);
        }
        
        $logs = $query->orderByDesc('trust_logs.created_at')->paginate(50);
        
        return view('admin.trust.logs', compact('logs'));
    }
    
    /**
     * Bulk update trust
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'action' => 'required|in:freeze,unfreeze,reset,increase_stage,decrease_stage',
            'notes' => 'nullable|string',
        ]);
        
        $userIds = $request->user_ids;
        $action = $request->action;
        $notes = $request->notes;
        
        foreach ($userIds as $userId) {
            $trust = UserTrust::where('user_id', $userId)->first();
            
            if (!$trust) continue;
            
            $oldStage = $trust->stage;
            $oldScore = $trust->trust_score;
            
            switch ($action) {
                case 'freeze':
                    $trust->update(['is_frozen' => true]);
                    $newStage = $oldStage;
                    $newScore = $oldScore;
                    break;
                    
                case 'unfreeze':
                    $trust->update(['is_frozen' => false]);
                    $newStage = $oldStage;
                    $newScore = $oldScore;
                    break;
                    
                case 'reset':
                    $trust->update([
                        'stage' => 1,
                        'trust_score' => 100,
                        'is_frozen' => false
                    ]);
                    $newStage = 1;
                    $newScore = 100;
                    break;
                    
                case 'increase_stage':
                    $newStage = min($oldStage + 1, 5);
                    $trust->update(['stage' => $newStage]);
                    $newScore = $oldScore;
                    break;
                    
                case 'decrease_stage':
                    $newStage = max($oldStage - 1, 1);
                    $trust->update(['stage' => $newStage]);
                    $newScore = $oldScore;
                    break;
            }
            
            // Log the bulk action
            DB::table('trust_logs')->insert([
                'user_id' => $userId,
                'action' => 'admin_bulk_' . $action,
                'old_stage' => $oldStage,
                'new_stage' => $newStage,
                'old_score' => $oldScore,
                'new_score' => $newScore,
                'reason' => $notes,
                'admin_id' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Bulk action completed for ' . count($userIds) . ' users.'
        ]);
    }
    
    /**
     * Get trust analytics
     */
    public function analytics()
    {
        // Daily trust changes
        $dailyChanges = DB::table('trust_logs')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as changes'),
                DB::raw('SUM(CASE WHEN new_stage > old_stage THEN 1 ELSE 0 END) as upgrades'),
                DB::raw('SUM(CASE WHEN new_stage < old_stage THEN 1 ELSE 0 END) as downgrades')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();
        
        // Action distribution
        $actionDistribution = DB::table('trust_logs')
            ->select('action', DB::raw('COUNT(*) as count'))
            ->groupBy('action')
            ->orderByDesc('count')
            ->get();
        
        // Stage transition matrix
        $transitions = DB::table('trust_logs')
            ->select(
                'old_stage',
                'new_stage',
                DB::raw('COUNT(*) as count')
            )
            ->whereNotNull('old_stage')
            ->whereNotNull('new_stage')
            ->groupBy('old_stage', 'new_stage')
            ->orderBy('old_stage')
            ->orderBy('new_stage')
            ->get();
        
        return view('admin.trust.analytics', compact(
            'dailyChanges',
            'actionDistribution',
            'transitions'
        ));
    }
    
    /**
     * Export trust logs
     */
    public function export(Request $request)
    {
        $logs = DB::table('trust_logs')
            ->join('users', 'trust_logs.user_id', '=', 'users.id')
            ->leftJoin('users as admins', 'trust_logs.admin_id', '=', 'admins.id')
            ->select(
                'trust_logs.id',
                'users.name as user_name',
                'users.email as user_email',
                'trust_logs.action',
                'trust_logs.old_stage',
                'trust_logs.new_stage',
                'trust_logs.old_score',
                'trust_logs.new_score',
                'trust_logs.reason',
                'admins.name as admin_name',
                'trust_logs.created_at'
            )
            ->orderByDesc('trust_logs.created_at')
            ->get();
        
        $filename = 'trust_logs_export_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');
            
            // Headers
            fputcsv($file, [
                'ID', 'User Name', 'User Email', 'Action',
                'Old Stage', 'New Stage', 'Old Score', 'New Score',
                'Reason', 'Admin Name', 'Date'
            ]);
            
            // Data
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->user_name,
                    $log->user_email,
                    $log->action,
                    $log->old_stage,
                    $log->new_stage,
                    $log->old_score,
                    $log->new_score,
                    $log->reason,
                    $log->admin_name,
                    $log->created_at
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
}
