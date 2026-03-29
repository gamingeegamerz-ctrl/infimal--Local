<?php

namespace App\Http\Controllers;

use App\Mail\PaidWelcomeOtpMail;
use App\Models\License;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    private const PRICE = '299.00';
    private const PRODUCT = 'InfiMal Pro';

    public function createOrder(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->hasPaid() && $user->hasActiveLicense(), Response::HTTP_CONFLICT, 'Your account is already active.');

        $response = Http::withToken($this->paypalToken())
            ->acceptJson()
            ->post($this->paypalBaseUrl().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => 'user-'.$user->id,
                    'custom_id' => (string) $user->id,
                    'description' => self::PRODUCT,
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => self::PRICE,
                    ],
                ]],
                'application_context' => [
                    'brand_name' => config('app.name', 'InfiMal'),
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                    'return_url' => route('payment.success'),
                    'cancel_url' => route('billing'),
                ],
            ]);

        abort_unless($response->successful(), Response::HTTP_UNPROCESSABLE_ENTITY, $response->json('message', 'Unable to create PayPal order.'));

        Payment::updateOrCreate(
            ['payment_id' => $response->json('id')],
            [
                'user_id' => $user->id,
                'plan' => self::PRODUCT,
                'amount' => 299.00,
                'currency' => 'USD',
                'status' => 'pending',
                'payment_method' => 'paypal',
                'metadata' => $response->json(),
            ]
        );

        return response()->json($response->json());
    }

    public function captureOrder(Request $request, string $orderId): JsonResponse
    {
        $user = $request->user();
        $order = $this->fetchOrder($orderId);

        abort_unless(
            data_get($order, 'status') === 'APPROVED' && data_get($order, 'purchase_units.0.custom_id') === (string) $user->id,
            Response::HTTP_FORBIDDEN,
            'Order approval verification failed.'
        );

        $response = Http::withToken($this->paypalToken())
            ->acceptJson()
            ->post($this->paypalBaseUrl()."/v2/checkout/orders/{$orderId}/capture");

        abort_unless($response->successful(), Response::HTTP_UNPROCESSABLE_ENTITY, $response->json('message', 'Unable to capture PayPal order.'));

        $captureData = $response->json();
        $captureId = data_get($captureData, 'purchase_units.0.payments.captures.0.id', $orderId);

        $this->finalizeSuccessfulPayment($user, $orderId, $captureId, $captureData);

        return response()->json([
            'success' => true,
            'redirect' => route('dashboard'),
        ]);
    }

    public function webhook(Request $request): Response
    {
        if (! $this->verifyWebhookSignature($request)) {
            return response('invalid signature', 422);
        }

        $eventType = (string) $request->input('event_type');
        if (! in_array($eventType, ['CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED'], true)) {
            return response('ignored', 200);
        }

        $resource = (array) $request->input('resource', []);
        $orderId = data_get($resource, 'supplementary_data.related_ids.order_id') ?? Arr::get($resource, 'id');
        $captureId = data_get($resource, 'id', $orderId);
        $customId = data_get($resource, 'purchase_units.0.custom_id') ?? data_get($resource, 'custom_id');
        $user = User::find($customId);

        if ($user && $orderId) {
            $this->finalizeSuccessfulPayment($user, (string) $orderId, (string) $captureId, $request->all());
    // ✅ COMBINED (codex + main)
    public function startCheckout(Request $request): RedirectResponse
    {
        return $this->createOrder($request);
    }

    public function createOrder(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        abort_if($user->hasPaid() && $user->hasActiveLicense(), 403, 'Already active');

        $response = Http::withToken($this->paypalToken())
            ->acceptJson()
            ->post($this->paypalBaseUrl().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => 'user-'.$user->id,
                    'custom_id' => (string) $user->id,
                    'description' => self::PRODUCT,
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => self::PRICE,
                    ],
                ]],
                'application_context' => [
                    'brand_name' => config('app.name', 'InfiMal'),
                    'user_action' => 'PAY_NOW',
                    'return_url' => route('payment.success'),
                    'cancel_url' => route('payment.cancel'),
                ],
            ]);

        abort_unless($response->successful(), 422, 'PayPal order failed');

        $payload = $response->json();
        $approvalUrl = collect($payload['links'] ?? [])
            ->firstWhere('rel', 'approve')['href'] ?? null;

        abort_unless($approvalUrl, 422, 'Approval URL missing');

        Payment::updateOrCreate(
            ['payment_id' => $payload['id']],
            [
                'user_id' => $user->id,
                'plan' => self::PRODUCT,
                'amount' => (float) self::PRICE,
                'currency' => 'USD',
                'status' => 'pending',
                'payment_method' => 'paypal',
                'metadata' => $payload,
            ]
        );

        return $request->expectsJson()
            ? response()->json(['approval_url' => $approvalUrl])
            : redirect()->away($approvalUrl);
    }

    public function success(Request $request): RedirectResponse
    {
        $user = $request->user();
        $orderId = (string) ($request->query('token') ?: '');

        abort_if($orderId === '', 422, 'Missing order');

        $capture = $this->capturePaypalOrder($orderId);

        $this->finalizeSuccessfulPayment($user, $orderId, $capture);

        return redirect()->route('otp.verify.form')
            ->with('success', 'Payment successful. Verify OTP.');
    }

    public function cancel(): RedirectResponse
    {
        return redirect()->route('billing')
            ->with('error', 'Payment cancelled.');
    }

    // ✅ WEBHOOK (MERGED BOTH SYSTEMS)
    public function webhook(Request $request): Response
    {
        if (! $this->verifyWebhookSignature($request)) {
            return response('invalid', 422);
        }

        $eventType = $request->input('event_type');
        $resource = $request->input('resource', []);

        $orderId = data_get($resource, 'id')
            ?? data_get($resource, 'supplementary_data.related_ids.order_id');

        $userId = data_get($resource, 'custom_id')
            ?? data_get($resource, 'purchase_units.0.custom_id');

        if ($orderId && $userId && ($user = User::find($userId))) {
            $this->finalizeSuccessfulPayment($user, $orderId, $request->all());
        }

        return response('ok', 200);
    }

    public function success(): RedirectResponse
    {
        return redirect()->route(Auth::user()?->hasPaid() && Auth::user()?->hasActiveLicense() ? 'dashboard' : 'billing');
    }

    private function finalizeSuccessfulPayment(User $user, string $orderId, string $captureId, array $payload): void
    {
        DB::transaction(function () use ($user, $orderId, $captureId, $payload): void {
    // ✅ CORE PAYMENT FINALIZER (UNCHANGED CORE LOGIC)
    private function finalizeSuccessfulPayment(User $user, string $orderId, array $payload): void
    {
        DB::transaction(function () use ($user, $orderId, $payload) {

            Payment::updateOrCreate(
                ['payment_id' => $orderId],
                [
                    'user_id' => $user->id,
                    'plan' => self::PRODUCT,
                    'amount' => 299.00,
                    'amount' => (float) self::PRICE,
                    'currency' => 'USD',
                    'status' => 'completed',
                    'payment_method' => 'paypal',
                    'metadata' => $payload,
                ]
            );

            $license = License::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'license_key' => $user->license_key ?: License::generateLicenseKey(),
                    'plan_type' => self::PRODUCT,
                    'price' => 299.00,
                    'duration_days' => 0,
                    'status' => 'active',
                    'is_active' => true,
                    'is_lifetime' => true,
                    'expires_at' => null,
                    'features' => ['campaigns', 'subscribers', 'smtp', 'analytics', 'messages'],
                ]
            );

            $user->forceFill([
                'is_paid' => true,
                'payment_status' => 'paid',
                'plan_name' => self::PRODUCT,
                'paid_at' => now(),
                'payment_date' => now(),
                'payment_amount' => 299.00,
                'transaction_id' => $captureId,
                'license_key' => $license->license_key,
                'license_status' => 'active',
            ])->save();
        });
    }

    private function fetchOrder(string $orderId): array
    {
        $response = Http::withToken($this->paypalToken())
            ->acceptJson()
            ->get($this->paypalBaseUrl()."/v2/checkout/orders/{$orderId}");

        abort_unless($response->successful(), Response::HTTP_UNPROCESSABLE_ENTITY, 'Unable to verify the PayPal order.');

        return $response->json();
    }

    private function verifyWebhookSignature(Request $request): bool
    {
        $webhookId = config('services.paypal.webhook_id');
        if (! $webhookId) {
            return false;
        }

        $response = Http::withToken($this->paypalToken())
            ->acceptJson()
            ->post($this->paypalBaseUrl().'/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url' => $request->header('PAYPAL-CERT-URL'),
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                'webhook_id' => $webhookId,
                'webhook_event' => $request->all(),
            ]);

        return $response->successful() && $response->json('verification_status') === 'SUCCESS';
    }

    private function paypalToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.secret'))
            ->post($this->paypalBaseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        abort_unless($response->successful(), Response::HTTP_UNPROCESSABLE_ENTITY, 'PayPal authentication failed.');

        return (string) $response->json('access_token');
    }

    private function paypalBaseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }
}

            $license = License::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'license_key' => $user->license_key ?: License::generateLicenseKey(),
                    'plan_type' => self::PRODUCT,
                    'price' => (float) self::PRICE,
                    'status' => 'active',
                    'is_active' => true,
                    'is_lifetime' => true,
                ]
            );

            $user->forceFill([
                'is_paid' => true,
                'payment_status' => 'paid',
                'plan_name' => self::PRODUCT,
                'paid_at' => now(),
                'transaction_id' => $orderId,
                'license_key' => $license->license_key,
                'license_status' => 'active',
            ])->save();

            $this->issueOtp($user);
        });
    }

    private function paypalToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth(
                config('services.paypal.client_id'),
                config('services.paypal.secret')
            )
            ->post($this->paypalBaseUrl().'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        abort_unless($response->successful(), 422, 'PayPal auth failed');

        return $response->json('access_token');
    }

    private function paypalBaseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function verifyWebhookSignature(Request $request): bool
    {
        $webhookId = config('services.paypal.webhook_id');

        if (!$webhookId) return false;

        $response = Http::withToken($this->paypalToken())
            ->post($this->paypalBaseUrl().'/v1/notifications/verify-webhook-signature', [
                'webhook_id' => $webhookId,
                'webhook_event' => $request->all(),
            ]);

        return $response->successful();
    }

    // ✅ OTP SYSTEM (MAIN FEATURE KEPT)
    private function issueOtp(User $user): void
    {
        $otp = random_int(100000, 999999);

        $user->update([
            'otp_code' => bcrypt($otp),
            'otp_expires_at' => now()->addMinutes(15),
            'otp_verified_at' => null,
        ]);

        Mail::to($user->email)->queue(new PaidWelcomeOtpMail($user, $otp));
    }

    public function showOtpForm(): View
    {
        return view('auth.verify-otp');
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate(['otp' => 'required|digits:6']);

        $user = $request->user();

        if (!password_verify($request->otp, $user->otp_code)) {
            return back()->withErrors(['otp' => 'Invalid OTP']);
        }

        $user->update(['otp_verified_at' => now()]);

        return redirect()->route('dashboard');
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $this->issueOtp($request->user());

        return back()->with('success', 'OTP resent');
    }
}
