<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    private const PRICE = '299.00';
    private const PRODUCT = 'InfiMal Pro';

    public function createOrder(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
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
                    'return_url' => route('payment.success'),
                    'cancel_url' => route('payment.cancel'),
                    'user_action' => 'PAY_NOW',
                ],
            ]);

        abort_unless($response->successful(), Response::HTTP_UNPROCESSABLE_ENTITY, $response->json('message', 'Unable to create PayPal order.'));

        $payload = $response->json();
        $approvalUrl = collect($payload['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        abort_if(! $approvalUrl, Response::HTTP_UNPROCESSABLE_ENTITY, 'Missing PayPal approval link.');

        Payment::updateOrCreate(
            ['payment_id' => $payload['id']],
            [
                'user_id' => $user->id,
                'plan' => self::PRODUCT,
                'amount' => 299.00,
                'currency' => 'USD',
                'status' => 'pending',
                'payment_method' => 'paypal',
                'metadata' => $payload,
            ]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'order_id' => $payload['id'],
                'approval_url' => $approvalUrl,
            ]);
        }

        return redirect()->away($approvalUrl);
    }

    public function success(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'PayerID' => ['nullable', 'string'],
        ]);

        $orderId = (string) $request->string('token');
        $accessToken = $this->paypalToken();

        $orderResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->get($this->paypalBaseUrl()."/v2/checkout/orders/{$orderId}");

        abort_unless($orderResponse->successful(), Response::HTTP_UNPROCESSABLE_ENTITY, 'Unable to verify PayPal order.');

        $order = $orderResponse->json();
        $customId = (string) data_get($order, 'purchase_units.0.custom_id');
        $user = $request->user();

        abort_unless($customId !== '' && $customId === (string) $user->id, Response::HTTP_FORBIDDEN, 'This payment does not belong to the current account.');
        abort_unless(data_get($order, 'status') === 'APPROVED', Response::HTTP_UNPROCESSABLE_ENTITY, 'PayPal order is not approved.');

        $captureResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->post($this->paypalBaseUrl()."/v2/checkout/orders/{$orderId}/capture");

        abort_unless($captureResponse->successful(), Response::HTTP_UNPROCESSABLE_ENTITY, 'Unable to capture approved PayPal payment.');

        $this->finalizeSuccessfulPayment($user, $orderId, $captureResponse->json());

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Payment verified successfully. Your lifetime license is active.');
    }

    public function cancel(): RedirectResponse
    {
        return redirect()->route('billing')->with('error', 'PayPal checkout was cancelled. Your account remains unpaid.');
    }

    public function webhook(Request $request): Response
    {
        if (! $this->verifyWebhookSignature($request)) {
            return response('invalid signature', Response::HTTP_UNAUTHORIZED);
        }

        $eventType = (string) $request->input('event_type');
        if (! in_array($eventType, ['CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED'], true)) {
            return response('ignored', Response::HTTP_OK);
        }

        $resource = (array) $request->input('resource', []);
        $orderId = (string) ($resource['supplementary_data']['related_ids']['order_id'] ?? $resource['id'] ?? '');
        $customId = (string) (data_get($resource, 'custom_id') ?: data_get($resource, 'purchase_units.0.custom_id'));
        $user = User::find($customId);

        if ($user && $orderId !== '') {
            $this->finalizeSuccessfulPayment($user, $orderId, $request->all());
        }

        return response('ok', Response::HTTP_OK);
    }

    private function finalizeSuccessfulPayment(User $user, string $orderId, array $payload): void
    {
        DB::transaction(function () use ($user, $orderId, $payload): void {
            Payment::updateOrCreate(
                ['payment_id' => $orderId],
                [
                    'user_id' => $user->id,
                    'plan' => self::PRODUCT,
                    'amount' => 299.00,
                    'currency' => 'USD',
                    'status' => 'completed',
                    'payment_method' => 'paypal',
                    'metadata' => $payload,
                ]
            );

            $license = License::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'license_key' => License::generateLicenseKey(),
                    'plan_type' => self::PRODUCT,
                    'price' => 299.00,
                    'duration_days' => 0,
                    'status' => 'active',
                    'is_active' => true,
                    'is_lifetime' => true,
                    'features' => ['campaigns', 'subscribers', 'smtp', 'analytics', 'messages'],
                ]
            );

            $license->forceFill([
                'status' => 'active',
                'is_active' => true,
                'is_lifetime' => true,
            ])->save();

            $user->forceFill([
                'is_paid' => true,
                'payment_status' => 'paid',
                'plan_name' => self::PRODUCT,
                'paid_at' => now(),
                'payment_date' => now(),
                'payment_amount' => 299.00,
                'transaction_id' => $orderId,
                'license_key' => $license->license_key,
                'license_status' => 'active',
            ])->save();
        });
    }

    private function verifyWebhookSignature(Request $request): bool
    {
        $webhookId = config('services.paypal.webhook_id');
        if (! $webhookId) {
            return app()->environment('local');
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

        return $response->successful() && Arr::get($response->json(), 'verification_status') === 'SUCCESS';
    }

    private function paypalToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.secret'))
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
