<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

// =================== ADMIN ROUTES (CLEAN VERSION) ===================
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    
    // Simple admin check
    $adminCheck = function() {
        $user = auth()->user();
        $adminEmails = [
            'admin@infimal.site',
            'contact@infimal.site',
            'sainikhilsaini143@gmail.com',
            'khileshrathod1729@gmail.com',
            'admin@infimal.com'
        ];
        
        if (!in_array($user->email, $adminEmails)) {
            abort(403, 'Admin access required.');
        }
    };
    
    // Dashboard
    Route::get('/dashboard', function() use ($adminCheck) {
        $adminCheck();
        
        try {
            $totalUsers = DB::table('users')->count();
            $activeLicenses = DB::table('licenses')->where('is_active', true)->count();
            $totalRevenue = DB::table('licenses')->sum('price') ?? 0;
            $revenueToday = DB::table('licenses')
                ->whereDate('created_at', today())
                ->sum('price') ?? 0;
        } catch (\Exception $e) {
            $totalUsers = $activeLicenses = $totalRevenue = $revenueToday = 0;
        }
        
        return view('admin.dashboard', compact(
            'totalUsers', 'activeLicenses', 'totalRevenue', 'revenueToday'
        ));
    })->name('dashboard');
    
    // Users
    Route::get('/users', function() use ($adminCheck) {
        $adminCheck();
        
        try {
            $users = DB::table('users')
                ->select('id', 'name', 'email', 'is_admin', 'created_at')
                ->orderByDesc('id')
                ->paginate(20);
            $totalUsers = DB::table('users')->count();
        } catch (\Exception $e) {
            $users = collect()->paginate(20);
            $totalUsers = 0;
        }
        
        return view('admin.users', compact('users', 'totalUsers'));
    })->name('users.index');
    
    // Licenses
    Route::get('/licenses', function() use ($adminCheck) {
        $adminCheck();
        
        try {
            $licenses = DB::table('licenses')
                ->leftJoin('users', 'licenses.user_id', '=', 'users.id')
                ->select('licenses.*', 'users.name as user_name', 'users.email as user_email')
                ->orderByDesc('licenses.created_at')
                ->paginate(20);
            
            $totalLicenses = DB::table('licenses')->count();
            $activeLicenses = DB::table('licenses')->where('is_active', true)->count();
            $totalRevenue = DB::table('licenses')->sum('price') ?? 0;
        } catch (\Exception $e) {
            $licenses = collect()->paginate(20);
            $totalLicenses = $activeLicenses = $totalRevenue = 0;
        }
        
        return view('admin.licenses', compact(
            'licenses', 'totalLicenses', 'activeLicenses', 'totalRevenue'
        ));
    })->name('licenses.index');
    
    // Other pages
    Route::get('/trust', function() use ($adminCheck) {
        $adminCheck();
        return view('admin.trust');
    })->name('trust.index');
    
    Route::get('/emails', function() use ($adminCheck) {
        $adminCheck();
        return view('admin.emails');
    })->name('emails.index');
    
    Route::get('/smtp', function() use ($adminCheck) {
        $adminCheck();
        return view('admin.smtp');
    })->name('smtp.index');
    
    Route::get('/revenue', function() use ($adminCheck) {
        $adminCheck();
        return view('admin.revenue');
    })->name('revenue.index');
    
    // License actions
    Route::post('/licenses/generate', function() use ($adminCheck) {
        $adminCheck();
        
        $licenseKey = 'INFIMAL-' . strtoupper(bin2hex(random_bytes(4))) . '-' . 
                     strtoupper(bin2hex(random_bytes(4))) . '-' . 
                     strtoupper(bin2hex(random_bytes(4))) . '-' . 
                     strtoupper(bin2hex(random_bytes(4)));
        
        DB::table('licenses')->insert([
            'license_key' => $licenseKey,
            'plan_type' => 'Premium',
            'duration_days' => 30,
            'is_active' => true,
            'price' => 299.00,
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        return response()->json(['success' => true, 'license_key' => $licenseKey]);
    })->name('licenses.generate');
    
    Route::post('/licenses/bulk-generate', function() use ($adminCheck) {
        $adminCheck();
        
        $count = request('count', 10);
        $days = request('days', 30);
        
        for ($i = 0; $i < $count; $i++) {
            $licenseKey = 'INFIMAL-' . strtoupper(bin2hex(random_bytes(4))) . '-' . 
                         strtoupper(bin2hex(random_bytes(4))) . '-' . 
                         strtoupper(bin2hex(random_bytes(4))) . '-' . 
                         strtoupper(bin2hex(random_bytes(4)));
            
            DB::table('licenses')->insert([
                'license_key' => $licenseKey,
                'plan_type' => 'Premium',
                'duration_days' => $days,
                'is_active' => true,
                'price' => 299.00,
                'expires_at' => now()->addDays($days),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        return response()->json(['success' => true, 'message' => "$count licenses generated"]);
    })->name('licenses.bulk-generate');
    
    Route::get('/licenses/export', function() use ($adminCheck) {
        $adminCheck();
        
        try {
            $licenses = DB::table('licenses')->get();
            $filename = 'licenses_export_' . date('Y-m-d') . '.csv';
            
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];
            
            $callback = function() use ($licenses) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['License Key', 'Plan', 'Price', 'Status', 'Expires', 'Created']);
                
                foreach ($licenses as $license) {
                    fputcsv($file, [
                        $license->license_key,
                        $license->plan_type,
                        '$' . $license->price,
                        $license->is_active ? 'Active' : 'Inactive',
                        $license->expires_at,
                        $license->created_at
                    ]);
                }
                
                fclose($file);
            };
            
            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return back()->with('error', 'Export failed');
        }
    })->name('licenses.export');
});
