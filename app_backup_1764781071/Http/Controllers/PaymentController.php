<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Payment;

class PaymentController extends Controller
{
    /**
     * Show payment page for guests
     */
    public function showCheckout()
    {
        // Agar user login nahi hai toh guest payment page dikhao
        if (!Auth::check()) {
            return view('payment.guest');
        }
        
        $user = Auth::user();
        return view('payment.checkout', compact('user'));
    }

    /**
     * Show payment success page
     */
    public function success(Request $request)
    {
        $paymentId = $request->input('payment_id');
        $plan = $request->input('plan', 'premium');
        
        if (Auth::check()) {
            $user = Auth::user();
            return view('payment.success', compact('user', 'paymentId', 'plan'));
        }
        
        return view('payment.success-guest', compact('paymentId', 'plan'));
    }

    /**
     * Show payment cancel page
     */
    public function cancel()
    {
        return view('payment.cancel');
    }

    /**
     * Process payment (Main logic)
     */
    public function processPaddleCheckout(Request $request)
    {
        try {
            $validated = $request->validate([
                'plan' => 'required|in:free_trial,premium,lifetime',
                'payment_method' => 'required|in:paddle,stripe,manual'
            ]);

            $plan = $validated['plan'];
            $paymentMethod = $validated['payment_method'];
            
            // Agar user login nahi hai
            if (!Auth::check()) {
                // Session mein payment data store karo
                session([
                    'pending_payment' => [
                        'plan' => $plan,
                        'payment_method' => $paymentMethod,
                        'redirect_url' => '/payment/success'
                    ]
                ]);
                
                return redirect('/login')->with('info', 'Please login to complete your payment.');
            }

            $user = Auth::user();
            $paymentId = 'pdl_' . strtoupper(uniqid());
            
            // Plan pricing set karo
            $pricing = [
                'free_trial' => ['amount' => 0, 'currency' => 'USD', 'duration' => '7 days'],
                'premium' => ['amount' => 299, 'currency' => 'USD', 'duration' => '1 year'],
                'lifetime' => ['amount' => 999, 'currency' => 'USD', 'duration' => 'lifetime']
            ];

            $planData = $pricing[$plan];

            // Payment record create karo
            $payment = Payment::create([
                'user_id' => $user->id,
                'payment_id' => $paymentId,
                'amount' => $planData['amount'],
                'currency' => $planData['currency'],
                'plan' => $plan,
                'payment_method' => $paymentMethod,
                'status' => 'completed'
            ]);

            // User subscription update karo
            if ($plan === 'lifetime') {
                $user->update([
                    'subscription' => 'lifetime',
                    'subscription_expires_at' => null,
                    'plan_type' => 'lifetime'
                ]);
            } 
            elseif ($plan === 'premium') {
                $user->update([
                    'subscription' => 'premium',
                    'subscription_expires_at' => Carbon::now()->addYear(),
                    'plan_type' => 'premium'
                ]);
            }
            else {
                // Free trial
                $user->update([
                    'subscription' => 'premium',
                    'subscription_expires_at' => Carbon::now()->addDays(7),
                    'plan_type' => 'trial'
                ]);
            }

            \Log::info('Payment processed successfully', [
                'user_id' => $user->id,
                'payment_id' => $paymentId,
                'plan' => $plan,
                'amount' => $planData['amount']
            ]);

            // Success page par redirect karo
            return redirect()->route('payment.success', [
                'payment_id' => $paymentId,
                'plan' => $plan
            ])->with('success', 'Payment completed successfully!');

        } catch (\Exception $e) {
            \Log::error('Payment processing error: ' . $e->getMessage());
            
            return redirect()->back()->with('error', 'Payment failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle Paddle webhook
     */
    public function handleWebhook(Request $request)
    {
        \Log::info('Paddle webhook received', $request->all());

        try {
            $payload = $request->all();
            
            // Webhook signature verify karo (production mein important)
            if (config('app.env') === 'production') {
                // Paddle webhook signature verification logic yahan add karo
            }

            $eventType = $payload['alert_name'] ?? 'unknown';
            
            switch ($eventType) {
                case 'subscription_created':
                    $this->handleSubscriptionCreated($payload);
                    break;
                    
                case 'subscription_updated':
                    $this->handleSubscriptionUpdated($payload);
                    break;
                    
                case 'subscription_cancelled':
                    $this->handleSubscriptionCancelled($payload);
                    break;
                    
                case 'payment_succeeded':
                    $this->handlePaymentSucceeded($payload);
                    break;
                    
                default:
                    \Log::info('Unhandled webhook event: ' . $eventType);
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            \Log::error('Webhook processing error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle subscription created webhook
     */
    private function handleSubscriptionCreated($payload)
    {
        $email = $payload['email'] ?? null;
        $subscriptionId = $payload['subscription_id'] ?? null;
        
        if ($email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->update([
                    'subscription' => 'premium',
                    'subscription_id' => $subscriptionId,
                    'subscription_expires_at' => Carbon::now()->addMonth()
                ]);
                
                Payment::create([
                    'user_id' => $user->id,
                    'payment_id' => $subscriptionId,
                    'amount' => $payload['unit_price'] ?? 0,
                    'currency' => $payload['currency'] ?? 'USD',
                    'plan' => 'premium',
                    'payment_method' => 'paddle',
                    'status' => 'completed'
                ]);
                
                \Log::info('Subscription created via webhook', ['user_id' => $user->id]);
            }
        }
    }

    /**
     * Handle subscription updated webhook
     */
    private function handleSubscriptionUpdated($payload)
    {
        // Subscription update logic
        \Log::info('Subscription updated via webhook', $payload);
    }

    /**
     * Handle subscription cancelled webhook
     */
    private function handleSubscriptionCancelled($payload)
    {
        $email = $payload['email'] ?? null;
        if ($email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->update([
                    'subscription' => 'free',
                    'subscription_expires_at' => null
                ]);
                \Log::info('Subscription cancelled via webhook', ['user_id' => $user->id]);
            }
        }
    }

    /**
     * Handle payment succeeded webhook
     */
    private function handlePaymentSucceeded($payload)
    {
        \Log::info('Payment succeeded via webhook', $payload);
        
        // Additional payment success logic yahan add karo
    }

    /**
     * Show user's payment history
     */
    public function paymentHistory()
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();
        $payments = Payment::where('user_id', $user->id)
                          ->orderBy('created_at', 'desc')
                          ->get();

        return view('payment.history', compact('user', 'payments'));
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription()
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();
        $user->update([
            'subscription' => 'free',
            'subscription_expires_at' => null
        ]);

        \Log::info('Subscription cancelled by user', ['user_id' => $user->id]);

        return redirect('/profile')->with('success', 'Your subscription has been cancelled.');
    }
}
