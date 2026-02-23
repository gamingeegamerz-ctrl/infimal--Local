<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SubscriberController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        // TEMPORARY: Check if table exists and has user_id column
        try {
            // First check if table exists
            $tableExists = DB::select("SHOW TABLES LIKE 'subscribers'");
            
            if (empty($tableExists)) {
                // Table doesn't exist - show demo data
                return $this->showDemoData();
            }
            
            // Check if user_id column exists
            $columns = DB::select("SHOW COLUMNS FROM subscribers LIKE 'user_id'");
            
            if (empty($columns)) {
                // Column doesn't exist - show demo data
                return $this->showDemoData();
            }
            
            // Table and column exist - proceed with real data
            $totalSubscribers = DB::table('subscribers')
                ->where('user_id', $user->id)
                ->count();
                
            $activeSubscribers = DB::table('subscribers')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->count();
                
            $subscribers = DB::table('subscribers')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate(20);
                
            return view('subscribers.index', compact('subscribers', 'totalSubscribers', 'activeSubscribers'));
            
        } catch (\Exception $e) {
            // Any error - show demo data
            return $this->showDemoData();
        }
    }
    
    private function showDemoData()
    {
        // Demo data for testing UI
        $subscribers = collect([
            (object)[
                'id' => 1,
                'email' => 'john@example.com',
                'name' => 'John Doe',
                'status' => 'active',
                'subscribed_at' => now(),
                'tags' => ['Customer', 'Premium'],
                'created_at' => now()
            ],
            (object)[
                'id' => 2,
                'email' => 'jane@example.com',
                'name' => 'Jane Smith',
                'status' => 'active',
                'subscribed_at' => now()->subDays(5),
                'tags' => ['Lead', 'Newsletter'],
                'created_at' => now()->subDays(5)
            ],
            // Add more demo data as needed
        ]);
        
        // Create paginator manually
        $perPage = 20;
        $currentPage = request()->get('page', 1);
        $paginatedData = $subscribers->forPage($currentPage, $perPage);
        $subscribers = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedData,
            $subscribers->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url()]
        );
        
        $totalSubscribers = 156;
        $activeSubscribers = 142;
        
        return view('subscribers.index', compact('subscribers', 'totalSubscribers', 'activeSubscribers'))
            ->with('demo_mode', true);
    }

    // Rest of your controller methods...
    public function create()
    {
        return view('subscribers.create');
    }

    public function store(Request $request)
    {
        // If in demo mode, just redirect with success message
        if (session('demo_mode')) {
            return redirect()->route('subscribers.index')
                ->with('success', 'Subscriber added successfully! (Demo Mode)');
        }
        
        $validated = $request->validate([
            'email' => 'required|email|unique:subscribers,email',
            'name' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,unsubscribed,bounced',
            'tags' => 'nullable|string'
        ]);

        $tags = $request->tags ? explode(',', $request->tags) : [];

        DB::table('subscribers')->insert([
            'user_id' => Auth::id(),
            'email' => $validated['email'],
            'name' => $validated['name'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'tags' => json_encode($tags),
            'subscribed_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return redirect()->route('subscribers.index')
            ->with('success', 'Subscriber added successfully!');
    }

    // Add other methods...
}
