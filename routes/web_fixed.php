<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\SmtpController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AnalyticsController;

// =================== PUBLIC ROUTES ===================

Route::get('/', function () {
    return view('public.index');
})->name('home');

Route::get('/pricing', function () {
    return view('pricing');
})->name('pricing');

Route::get('/features', function () {
    return view('features');
})->name('features');

Route::get('/contact', function () {
    return view('contact');
})->name('contact');

Route::get('/about', function () {
    return view('about');
})->name('about');

Route::get('/privacy', function () {
    return view('privacy');
})->name('privacy');

Route::get('/terms', function () {
    return view('terms');
})->name('terms');

Route::get('/security', function () {
    return view('security');
})->name('security');

Route::get('/refund', function () {
    return view('refund');
})->name('refund');

Route::get('/help-center', function () {
    return view('help-center');
})->name('help.center');

// =================== AUTH ROUTES ===================

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/forgot-password', function () {
        return view('auth.forgot-password');
    })->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');
    Route::get('/reset-password/{token}', function ($token) {
        return view('auth.reset-password', ['token' => $token]);
    })->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('google.login');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
});

Route::middleware('auth')->post('/logout', [AuthController::class, 'logout'])->name('logout');

// Tracking
Route::get('/track/open/{campaign_id}/{subscriber_id}', [CampaignController::class, 'trackOpen'])->name('track.open');
Route::get('/track/click/{campaign_id}/{subscriber_id}/{url}', [CampaignController::class, 'trackClick'])->name('track.click');
Route::get('/unsubscribe/{subscriber_id}', [SubscriberController::class, 'unsubscribe'])->name('unsubscribe');

// =================== PROTECTED ROUTES ===================

Route::middleware(['auth'])->group(function () {
    
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // WORKSPACES
    Route::prefix('workspaces')->name('workspaces.')->group(function () {
        Route::get('/', [WorkspaceController::class, 'index'])->name('index');
        Route::get('/create', [WorkspaceController::class, 'create'])->name('create');
        Route::post('/', [WorkspaceController::class, 'store'])->name('store');
        Route::get('/{workspace}', [WorkspaceController::class, 'show'])->name('show');
        Route::get('/{workspace}/edit', [WorkspaceController::class, 'edit'])->name('edit');
        Route::put('/{workspace}', [WorkspaceController::class, 'update'])->name('update');
        Route::delete('/{workspace}', [WorkspaceController::class, 'destroy'])->name('destroy');
    });
    
    // ... (COPY ALL YOUR EXISTING PROTECTED ROUTES HERE) ...
    
    // API ROUTES
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/smtp/credentials', [SmtpController::class, 'getCredentials'])->name('smtp.credentials');
        Route::get('/limits', [DashboardController::class, 'getLimits'])->name('limits');
        Route::get('/stats', [DashboardController::class, 'getStats'])->name('stats');
        Route::get('/recent-activity', [DashboardController::class, 'getRecentActivity'])->name('recent-activity');
    });
});

// =================== ADMIN ROUTES (SIMPLE FIXED VERSION) ===================

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
            if (isset($user->is_admin) && !$user->is_admin) {
                abort(403, 'Admin access required.');
            }
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

// =================== WEBHOOK ===================

Route::post('/webhook/paddle', [PaymentController::class, 'handleWebhook'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// =================== HEALTH CHECK ===================

Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now(),
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version(),
        'environment' => app()->environment(),
    ]);
});

// =================== FALLBACK (404) ===================

Route::fallback(function () {
    return response()->view('errors.404', [], 404);
});
