<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Models\Invoice;

class BillingController extends Controller
{
    public function index()
    {
        $subscription = Subscription::where('user_id', auth()->id())->first();
        $invoices = Invoice::where('user_id', auth()->id())->get();
        
        return view('billing', compact('subscription', 'invoices'));
    }

    public function subscribe(Request $request)
    {
        Subscription::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'plan_id' => $request->plan_id,
                'status' => 'active',
                'amount' => 29
            ]
        );

        return redirect()->route('billing.index')->with('success', 'Subscription updated successfully!');
    }

    public function cancel(Request $request)
    {
        $subscription = Subscription::where('user_id', auth()->id())->first();
        if ($subscription) {
            $subscription->update(['status' => 'cancelled']);
        }

        return redirect()->route('billing.index')->with('success', 'Subscription cancelled successfully!');
    }
}
