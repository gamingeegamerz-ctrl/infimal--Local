<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    private const PRICE = '299.00';
    private const PRODUCT = 'InfiMal Pro';

    public function startCheckout(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->hasPaid() && $user->hasActiveLicense(), Response::HTTP_FORBIDDEN, 'Your account is already active.');

        $order = $this->createPaypalOrder($user);
        $approvalUrl = collect($order['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        abort_unless($approvalUrl, Response::HTTP_UNPROCESSABLE_ENTITY, 'Unable to start PayPal approval flow.');

        Payment::updateOrCreate(
            ['payment_id' => $order['id']],
            [
                'user_id' => $user->id,
                'plan' => self::PRODUCT,
                'amount' => (float) self::PRICE,
                'currency' => 'USD',
                'status' => 'pending',
                'payment_method' => 'paypal',
                'metadata' => $order,
            ]
        );

        return redirect()->away($approvalUrl);
    }

    public function success(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $user = $request->user();
        $orderId = (string) $request->string('token');
        $order = $this->showOrder($orderId);

        $approvedUserId = (int) (data_get($order, 'purchase_units.0.custom_id') ?? 0);
        abort_unless($approvedUserId === $user->id, Response::HTTP_FORBIDDEN, 'Payment does not belong to the authenticated user.');

        $capture = $this->capturePaypalOrder($orderId);
        $this->finalizeSuccessfulPayment($user, $orderId, $capture);

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Payment verified successfully. Welcome to InfiMal Pro.');
    }

    public function cancel(): RedirectResponse
    {
        return redirect()->route('billing')->with('error', 'Payment was cancelled. Your account is still unpaid.');
    }

    public function webhook(Request $request): Response
    {
        abort_unless($this->verifyWebhookSignature($request), Response::HTTP_UNAUTHORIZED, 'Invalid PayPal webhook signature.');

        $eventType = (string) $request->input('event_type');
        $resource = $request->input('resource', []);

        if ($eventType === 'CHECKOUT.ORDER.APPROVED') {
            $orderId = (string) ($resource['id'] ?? '');
            if ($orderId !== '') {
                $capture = $this->capturePaypalOrder($orderId);
                $order = $this->showOrder($orderId);
                $userId = (int) (data_get($order, 'purchase_units.0.custom_id') ?? 0);
                if ($user = User::find($userId)) {
                    $this->finalizeSuccessfulPayment($user, $orderId, $capture);
                }
            }
        }

        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $orderId = (string) (data_get($resource, 'supplementary_data.related_ids.order_id') ?? '');
            $userId = (int) (data_get($resource, 'custom_id') ?? 0);

            if ($orderId !== '' && $userId > 0 && ($user = User::find($userId))) {
                $this->finalizeSuccessfulPayment($user, $orderId, $request->all());
            }
        }

        return response('ok', 200);
    }

    private function finalizeSuccessfulPayment(User $user, string $orderId, array $payload): void
    {
        DB::transaction(function () use ($user, $orderId, $payload): void {
            Payment::updateOrCreate(
                ['payment_id' => $orderId],
                [
                    'user_id' => $user->id,
                    'plan' => self::PRODUCT,
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
                    'price' => (float) self::PRICE,
                    'duration_days' => 0,
                    'status' => 'active',
                    'is_active' => true,
                    'is_lifetime' => true,
                    'features' => [
                        'unlimited_smtp_sending',
                        'campaign_management',
                        'analytics',
                        'smtp_integration',
                        'lifetime_access',
                    ],
                    'notes' => 'Verified PayPal payment '.Str::limit($orderId, 36, ''),
                ]
            );

            $user->forceFill([
                'is_paid' => true,
                'payment_status' => 'paid',
                'plan_name' => self::PRODUCT,
                'paid_at' => now(),
                'payment_date' => now(),
                'payment_amount' => (float) self::PRICE,
                'transaction_id' => $orderId,
                'license_key' => $license->license_key,
                'license_status' => 'active',
            ])->save();
        });
    }

    private function createPaypalOrder(User $user): array
    {
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

        abort_unless($response->successful(), Response::HTTP_UNPROCESSABLE_ENTITY, $response->json('message', 'Unable to create PayPal order.'));

        return $response->json();
    }

    private function showOrder(string $orderId): array
    {
        $response = Http::withToken($this->paypalToken())
            ->acceptJson()
            ->get($this->paypalBaseUrl().'/v2/checkout/orders/'.$orderId);

        abort_unless($response->successful(), Response::HTTP_UNPROCESSABLE_ENTITY, 'Unable to verify PayPal order.');

        return $response->json();
    }

    private function capturePaypalOrder(string $orderId): array
    {
        $response = Http::withToken($this->paypalToken())
            ->acceptJson()
            ->post($this->paypalBaseUrl().'/v2/checkout/orders/'.$orderId.'/capture');

        abort_unless($response->successful(), Response::HTTP_UNPROCESSABLE_ENTITY, $response->json('message', 'Unable to capture PayPal order.'));

        return $response->json();
    }

    private function verifyWebhookSignature(Request $request): bool
    {
        $webhookId = (string) config('services.paypal.webhook_id');
        if ($webhookId === '') {
            return false;
        }

        $headers = [
            'PAYPAL-AUTH-ALGO',
            'PAYPAL-CERT-URL',
            'PAYPAL-TRANSMISSION-ID',
            'PAYPAL-TRANSMISSION-SIG',
            'PAYPAL-TRANSMISSION-TIME',
        ];

        foreach ($headers as $header) {
            if (! $request->headers->has($header)) {
                return false;
            }
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
        $clientId = (string) config('services.paypal.client_id');
        $secret = (string) config('services.paypal.secret');

        if ($clientId === '' || $secret === '') {
            throw ValidationException::withMessages([
                'paypal' => 'PayPal credentials are missing. Set PAYPAL_CLIENT_ID and PAYPAL_SECRET in your environment.',
            ]);
        }

        $response = Http::asForm()
            ->withBasicAuth($clientId, $secret)
            ->post($this->paypalBaseUrl().'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

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
