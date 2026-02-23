<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\License;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('admin');
    }
    
    /**
     * Show licenses management page
     */
    public function licenses(Request $request)
    {
        try {
            // Check if user is admin
            $user = Auth::user();
            if (!$user->is_admin) {
                abort(403, 'Unauthorized access');
            }
            
            // Get search parameter
            $search = $request->input('search', '');
            $status = $request->input('status', 'all');
            
            // Start query
            $query = User::query();
            
            // Apply search filter
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('license_key', 'like', "%{$search}%");
                });
            }
            
            // Apply license status filter
            if ($status !== 'all') {
                switch ($status) {
                    case 'active':
                        $query->where('license_status', 'active')
                              ->where(function($q) {
                                  $q->where('license_expires_at', '>', now())
                                    ->orWhereNull('license_expires_at');
                              });
                        break;
                    case 'expired':
                        $query->where(function($q) {
                            $q->where('license_status', 'expired')
                              ->orWhere('license_expires_at', '<', now());
                        });
                        break;
                    case 'expiring':
                        $query->where('license_status', 'active')
                              ->where('license_expires_at', '>', now())
                              ->where('license_expires_at', '<=', now()->addDays(7));
                        break;
                    case 'none':
                        $query->where(function($q) {
                            $q->whereNull('license_status')
                              ->orWhere('license_status', 'none');
                        });
                        break;
                }
            }
            
            // Paginate results
            $users = $query->orderBy('created_at', 'desc')->paginate(20);
            
            // Calculate statistics
            $totalUsers = User::count();
            $licensedUsers = User::where('license_status', 'active')
                ->where(function($q) {
                    $q->where('license_expires_at', '>', now())
                      ->orWhereNull('license_expires_at');
                })->count();
                
            $expiredLicenses = User::where(function($q) {
                $q->where('license_status', 'expired')
                  ->orWhere('license_expires_at', '<', now());
            })->count();
            
            $expiringSoon = User::where('license_status', 'active')
                ->where('license_expires_at', '>', now())
                ->where('license_expires_at', '<=', now()->addDays(7))
                ->count();
            
            $licensesToday = User::whereDate('created_at', today())
                ->where('license_status', 'active')->count();
            
            // Calculate active percentage
            $activePercentage = $totalUsers > 0 ? round(($licensedUsers / $totalUsers) * 100, 2) : 0;
            
            // Calculate revenue (adjust based on your pricing)
            $totalRevenue = $licensedUsers * 29.99; // Example: $29.99 per license
            
            return view('admin.licenses.index', compact(
                'users',
                'search',
                'status',
                'totalUsers',
                'licensedUsers',
                'expiredLicenses',
                'expiringSoon',
                'licensesToday',
                'activePercentage',
                'totalRevenue'
            ));
            
        } catch (\Exception $e) {
            // Log error and return with message
            \Log::error('Licenses page error: ' . $e->getMessage());
            
            return view('admin.licenses.index', [
                'users' => collect([]),
                'search' => '',
                'status' => 'all',
                'totalUsers' => 0,
                'licensedUsers' => 0,
                'expiredLicenses' => 0,
                'expiringSoon' => 0,
                'licensesToday' => 0,
                'activePercentage' => 0,
                'totalRevenue' => 0
            ])->with('error', 'An error occurred while loading the page.');
        }
    }
    
    /**
     * Generate license for a user
     */
    public function generateLicense(Request $request, User $user)
    {
        try {
            // Validate request
            $validated = $request->validate([
                'days' => 'required|integer|min:1|max:365',
                'plan_type' => 'nullable|string|in:basic,pro,enterprise'
            ]);
            
            // Generate unique license key
            $licenseKey = $this->generateUniqueLicenseKey();
            
            // Calculate expiry date
            $expiresAt = now()->addDays($validated['days']);
            
            // Update user with license
            $user->update([
                'license_status' => 'active',
                'license_key' => $licenseKey,
                'license_expires_at' => $expiresAt,
                'license_plan' => $validated['plan_type'] ?? 'basic'
            ]);
            
            // Log the action
            activity()
                ->causedBy(Auth::user())
                ->performedOn($user)
                ->log('Generated license for user ' . $user->email);
            
            return response()->json([
                'success' => true,
                'license_key' => $licenseKey,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'message' => 'License generated successfully!'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Generate license error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate license. Please try again.'
            ], 500);
        }
    }
    
    /**
     * Revoke license from a user
     */
    public function revokeLicense(User $user)
    {
        try {
            // Update user license status
            $user->update([
                'license_status' => 'revoked',
                'license_expires_at' => now()
            ]);
            
            // Log the action
            activity()
                ->causedBy(Auth::user())
                ->performedOn($user)
                ->log('Revoked license from user ' . $user->email);
            
            return response()->json([
                'success' => true,
                'message' => 'License revoked successfully!'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Revoke license error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke license.'
            ], 500);
        }
    }
    
    /**
     * Generate unique license key
     */
    private function generateUniqueLicenseKey()
    {
        do {
            $key = 'INF-' . strtoupper(bin2hex(random_bytes(8))) . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(8)));
        } while (User::where('license_key', $key)->exists());
        
        return $key;
    }
    
    /**
     * Export licenses to CSV
     */
    public function exportLicenses()
    {
        try {
            $users = User::whereNotNull('license_key')
                ->select('name', 'email', 'license_key', 'license_status', 'license_expires_at', 'created_at')
                ->get();
            
            $filename = 'licenses_export_' . date('Y-m-d_H-i-s') . '.csv';
            
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];
            
            $callback = function() use ($users) {
                $file = fopen('php://output', 'w');
                
                // Add BOM for UTF-8
                fwrite($file, "\xEF\xBB\xBF");
                
                // Header row
                fputcsv($file, ['Name', 'Email', 'License Key', 'Status', 'Expires At', 'Created At']);
                
                // Data rows
                foreach ($users as $user) {
                    fputcsv($file, [
                        $user->name ?? '',
                        $user->email,
                        $user->license_key ?? '',
                        $user->license_status ?? '',
                        $user->license_expires_at ? $user->license_expires_at->format('Y-m-d H:i:s') : '',
                        $user->created_at->format('Y-m-d H:i:s')
                    ]);
                }
                
                fclose($file);
            };
            
            return response()->stream($callback, 200, $headers);
            
        } catch (\Exception $e) {
            \Log::error('Export licenses error: ' . $e->getMessage());
            
            return back()->with('error', 'Failed to export licenses.');
        }
    }
}
