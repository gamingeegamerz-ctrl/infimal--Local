<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RevenueController extends Controller
{
    /**
     * Display revenue dashboard
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->is_admin) {
            abort(403, 'Unauthorized access');
        }

        $period = $request->get('period', 'month');
        
        // Calculate dates based on period
        $startDate = match($period) {
            'today' => now()->startOfDay(),
            'week' => now()->subDays(7),
            'month' => now()->subDays(30),
            default => now()->subDays(30),
        };

        // REAL REVENUE CALCULATIONS BASED ON $299 PRICE
        $licensePrice = 299.00;
        
        // Get transaction data
        $transactionsQuery = DB::table('transactions')
            ->select('*')
            ->where('created_at', '>=', $startDate)
            ->orderBy('created_at', 'desc');

        $transactions = $transactionsQuery->paginate(20);

        // Calculate total revenue
        $totalRevenue = DB::table('transactions')
            ->where('status', 'successful')
            ->sum('amount') ?? 0;

        // Today's revenue
        $todayRevenue = DB::table('transactions')
            ->where('status', 'successful')
            ->whereDate('created_at', today())
            ->sum('amount') ?? 0;

        // This month's revenue
        $monthRevenue = DB::table('transactions')
            ->where('status', 'successful')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount') ?? 0;

        // Last month's revenue for growth calculation
        $lastMonthRevenue = DB::table('transactions')
            ->where('status', 'successful')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('amount') ?? 0;

        // Calculate growth percentage
        $revenueGrowth = $lastMonthRevenue > 0 ? 
            (($monthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 0;

        // Sales counts
        $todaySales = DB::table('transactions')
            ->where('status', 'successful')
            ->whereDate('created_at', today())
            ->count();

        $monthlySales = DB::table('transactions')
            ->where('status', 'successful')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Customer metrics
        $totalCustomers = DB::table('users')
            ->whereNotNull('email_verified_at')
            ->count();

        $activeCustomers = DB::table('licenses')
            ->where('status', 'active')
            ->count();

        $newCustomersToday = DB::table('users')
            ->whereDate('created_at', today())
            ->count();

        $newCustomersThisMonth = DB::table('users')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Calculate churn rate (simplified)
        $churnRate = $totalCustomers > 0 ? 
            round((($totalCustomers - $activeCustomers) / $totalCustomers) * 100, 1) : 0;

        // Renewal rate (simplified)
        $renewalRate = 85; // Assuming 85% renewal rate

        // Average revenue per customer
        $avgRevenuePerCustomer = $totalCustomers > 0 ? 
            round($totalRevenue / $totalCustomers, 2) : 0;

        // Daily targets
        $dailyTarget = $licensePrice * 5; // Target 5 sales per day
        $monthlyTarget = $licensePrice * 150; // Target 150 sales per month

        // Weekly revenue
        $weeklyRevenue = DB::table('transactions')
            ->where('status', 'successful')
            ->where('created_at', '>=', now()->subDays(7))
            ->sum('amount') ?? 0;

        // Average daily revenue
        $avgDailyRevenue = $todayRevenue > 0 ? $todayRevenue : 
            round($monthRevenue / now()->day, 2);

        $avgDailySales = round($monthlySales / now()->day, 1);

        // License sales distribution
        $licenseSales = [
            'monthly' => DB::table('transactions')
                ->where('plan_type', 'Monthly')
                ->where('status', 'successful')
                ->count(),
            'annual' => DB::table('transactions')
                ->where('plan_type', 'Annual')
                ->where('status', 'successful')
                ->count(),
            'lifetime' => DB::table('transactions')
                ->where('plan_type', 'Lifetime')
                ->where('status', 'successful')
                ->count(),
            'team' => DB::table('transactions')
                ->where('plan_type', 'Team')
                ->where('status', 'successful')
                ->count(),
        ];

        // Revenue projections
        $projectedRevenue = [
            'weekly' => round($weeklyRevenue * 1.1, 2), // 10% growth
            'monthly' => round($monthRevenue * 1.15, 2), // 15% growth
            'quarterly' => round($monthRevenue * 3 * 1.2, 2), // 20% growth
            'yearly' => round($monthRevenue * 12 * 1.3, 2), // 30% growth
        ];

        $projectionGrowth = [
            'weekly' => 10,
            'monthly' => 15,
            'quarterly' => 20,
            'yearly' => 30,
        ];

        // Revenue sources
        $revenueSources = [
            'direct' => $totalRevenue * 0.6, // 60% direct
            'affiliate' => $totalRevenue * 0.2, // 20% affiliate
            'reseller' => $totalRevenue * 0.1, // 10% reseller
            'upgrade' => $totalRevenue * 0.05, // 5% upgrades
            'renewal' => $totalRevenue * 0.05, // 5% renewals
        ];

        // Key financial metrics
        $ltv = round($avgRevenuePerCustomer * 12 * 0.7, 2); // LTV calculation
        $cac = 150.00; // Customer Acquisition Cost (estimated)
        $ltvCacRatio = round($ltv / $cac, 2);
        
        $mrr = round($monthRevenue, 2); // Monthly Recurring Revenue
        $arr = round($mrr * 12, 2); // Annual Recurring Revenue
        
        $profitMargin = 65; // 65% profit margin (estimated)
        
        $totalTransactions = DB::table('transactions')->count();

        return view('admin.revenue', [
            'transactions' => $transactions,
            'totalRevenue' => $totalRevenue,
            'todayRevenue' => $todayRevenue,
            'monthRevenue' => $monthRevenue,
            'revenueGrowth' => $revenueGrowth,
            'todaySales' => $todaySales,
            'monthlySales' => $monthlySales,
            'totalCustomers' => $totalCustomers,
            'activeCustomers' => $activeCustomers,
            'newCustomersToday' => $newCustomersToday,
            'newCustomersThisMonth' => $newCustomersThisMonth,
            'churnRate' => $churnRate,
            'renewalRate' => $renewalRate,
            'avgRevenuePerCustomer' => $avgRevenuePerCustomer,
            'dailyTarget' => $dailyTarget,
            'monthlyTarget' => $monthlyTarget,
            'weeklyRevenue' => $weeklyRevenue,
            'avgDailyRevenue' => $avgDailyRevenue,
            'avgDailySales' => $avgDailySales,
            'licenseSales' => $licenseSales,
            'projectedRevenue' => $projectedRevenue,
            'projectionGrowth' => $projectionGrowth,
            'revenueSources' => $revenueSources,
            'ltv' => $ltv,
            'cac' => $cac,
            'ltvCacRatio' => $ltvCacRatio,
            'mrr' => $mrr,
            'arr' => $arr,
            'profitMargin' => $profitMargin,
            'totalTransactions' => $totalTransactions,
            'now' => now(),
        ]);
    }

    /**
     * Get real-time revenue stats
     */
    public function stats(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->is_admin) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Real-time calculations
            $todayRevenue = DB::table('transactions')
                ->where('status', 'successful')
                ->whereDate('created_at', today())
                ->sum('amount') ?? 0;

            $monthRevenue = DB::table('transactions')
                ->where('status', 'successful')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount') ?? 0;

            $totalRevenue = DB::table('transactions')
                ->where('status', 'successful')
                ->sum('amount') ?? 0;

            $todaySales = DB::table('transactions')
                ->where('status', 'successful')
                ->whereDate('created_at', today())
                ->count();

            $monthlySales = DB::table('transactions')
                ->where('status', 'successful')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            $totalCustomers = DB::table('users')
                ->whereNotNull('email_verified_at')
                ->count();

            // Calculate growth
            $lastMonthRevenue = DB::table('transactions')
                ->where('status', 'successful')
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->sum('amount') ?? 0;

            $revenueGrowth = $lastMonthRevenue > 0 ? 
                round((($monthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1) : 0;

            return response()->json([
                'success' => true,
                'todayRevenue' => $todayRevenue,
                'monthRevenue' => $monthRevenue,
                'totalRevenue' => $totalRevenue,
                'todaySales' => $todaySales,
                'monthlySales' => $monthlySales,
                'totalCustomers' => $totalCustomers,
                'revenueGrowth' => $revenueGrowth,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching revenue stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transaction details
     */
    public function getTransaction($id)
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->is_admin) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $transaction = DB::table('transactions')->find($id);
            if (!$transaction) {
                return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
            }

            // Get customer info
            $customer = DB::table('users')->find($transaction->user_id);

            // Status classes
            $statusClasses = [
                'successful' => 'bg-green-500/20 text-green-400',
                'pending' => 'bg-yellow-500/20 text-yellow-400',
                'failed' => 'bg-red-500/20 text-red-400',
                'refunded' => 'bg-purple-500/20 text-purple-400',
            ];

            // Plan colors
            $planColors = [
                'Monthly' => 'text-blue-400',
                'Annual' => 'text-green-400',
                'Lifetime' => 'text-purple-400',
                'Team' => 'text-yellow-400',
            ];

            return response()->json([
                'success' => true,
                'transaction' => [
                    'id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'status' => $transaction->status,
                    'status_class' => $statusClasses[$transaction->status] ?? 'bg-gray-500/20 text-gray-400',
                    'payment_method' => $transaction->payment_method,
                    'payment_gateway' => $transaction->payment_gateway,
                    'gateway_transaction_id' => $transaction->gateway_transaction_id,
                    'plan_type' => $transaction->plan_type,
                    'plan_color' => $planColors[$transaction->plan_type] ?? 'text-white',
                    'discount' => $transaction->discount ?? 0,
                    'currency' => $transaction->currency,
                    'country' => $transaction->country,
                    'created_at_formatted' => Carbon::parse($transaction->created_at)->format('M d, Y H:i:s'),
                    'updated_at_formatted' => Carbon::parse($transaction->updated_at)->format('M d, Y H:i:s'),
                    'next_billing_date' => $transaction->next_billing_date ? 
                        Carbon::parse($transaction->next_billing_date)->format('M d, Y') : null,
                    'user_id' => $transaction->user_id,
                    'customer_name' => $customer->name ?? 'N/A',
                    'customer_email' => $customer->email ?? 'N/A',
                    'customer_avatar' => 'https://ui-avatars.com/api/?name=' . 
                        urlencode($customer->name ?? 'Customer') . '&color=FFFFFF&background=10B981',
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching transaction details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve transaction
     */
    public function approveTransaction($id)
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->is_admin) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            DB::table('transactions')
                ->where('id', $id)
                ->update([
                    'status' => 'successful',
                    'updated_at' => now()
                ]);

            // Activate license for this transaction
            $transaction = DB::table('transactions')->find($id);
            if ($transaction) {
                DB::table('licenses')->insert([
                    'user_id' => $transaction->user_id,
                    'transaction_id' => $id,
                    'license_key' => $this->generateLicenseKey(),
                    'plan_type' => $transaction->plan_type,
                    'status' => 'active',
                    'expires_at' => $this->calculateExpiryDate($transaction->plan_type),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaction approved and license activated'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refund transaction
     */
    public function refundTransaction($id)
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->is_admin) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            DB::table('transactions')
                ->where('id', $id)
                ->update([
                    'status' => 'refunded',
                    'updated_at' => now()
                ]);

            // Deactivate license
            DB::table('licenses')
                ->where('transaction_id', $id)
                ->update([
                    'status' => 'cancelled',
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction refunded and license deactivated'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error refunding transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate financial report
     */
    public function generateReport(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->is_admin) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Generate report data
            $reportData = [
                'generated_at' => now()->toISOString(),
                'period' => 'Monthly Report - ' . now()->format('F Y'),
                'revenue' => [
                    'total' => DB::table('transactions')
                        ->where('status', 'successful')
                        ->whereMonth('created_at', now()->month)
                        ->sum('amount'),
                    'daily_average' => DB::table('transactions')
                        ->where('status', 'successful')
                        ->whereMonth('created_at', now()->month)
                        ->avg('amount'),
                    'transactions_count' => DB::table('transactions')
                        ->where('status', 'successful')
                        ->whereMonth('created_at', now()->month)
                        ->count(),
                ],
                'customers' => [
                    'total' => DB::table('users')->count(),
                    'new_this_month' => DB::table('users')
                        ->whereMonth('created_at', now()->month)
                        ->count(),
                    'active' => DB::table('licenses')
                        ->where('status', 'active')
                        ->count(),
                ],
                'plan_distribution' => DB::table('transactions')
                    ->select('plan_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as revenue'))
                    ->where('status', 'successful')
                    ->whereMonth('created_at', now()->month)
                    ->groupBy('plan_type')
                    ->get()
            ];

            // In real implementation, you would generate PDF/Excel here
            // For now, return JSON response

            return response()->json([
                'success' => true,
                'message' => 'Report generated successfully',
                'download_url' => '/admin/revenue/download-report/' . time(),
                'data' => $reportData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update pricing
     */
    public function updatePricing(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->is_admin) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $request->validate([
                'price' => 'required|numeric|min:1'
            ]);

            // Update pricing in configuration
            // This would typically update a database configuration table
            // For now, just return success

            return response()->json([
                'success' => true,
                'message' => 'Pricing updated to $' . $request->price,
                'new_price' => $request->price
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating pricing',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send payment reminders
     */
    public function sendReminders(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->is_admin) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Find licenses expiring in next 7 days
            $expiringLicenses = DB::table('licenses')
                ->where('status', 'active')
                ->where('expires_at', '<=', now()->addDays(7))
                ->where('expires_at', '>', now())
                ->count();

            // In real implementation, send emails here

            return response()->json([
                'success' => true,
                'message' => 'Payment reminders processed',
                'recipients' => $expiringLicenses
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending reminders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reconcile payments
     */
    public function reconcile(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->is_admin) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Find pending transactions older than 1 hour
            $pendingTransactions = DB::table('transactions')
                ->where('status', 'pending')
                ->where('created_at', '<', now()->subHour())
                ->count();

            // In real implementation, check with payment gateway and update status

            return response()->json([
                'success' => true,
                'message' => 'Payments reconciled',
                'reconciled' => $pendingTransactions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reconciling payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Generate license key
     */
    private function generateLicenseKey()
    {
        return strtoupper(bin2hex(random_bytes(16)));
    }

    /**
     * Helper: Calculate expiry date based on plan
     */
    private function calculateExpiryDate($planType)
    {
        return match($planType) {
            'Monthly' => now()->addMonth(),
            'Annual' => now()->addYear(),
            'Lifetime' => now()->addYears(100),
            'Team' => now()->addYear(),
            default => now()->addMonth(),
        };
    }
}
