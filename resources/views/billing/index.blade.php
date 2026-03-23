@extends('layouts.saas')
@section('title', 'Billing · InfiMal')
@section('content')
<div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
    <section class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-blue-600">InfiMal Pro</p>
        <h1 class="mt-3 text-4xl font-bold">$299 one-time payment</h1>
        <p class="mt-4 max-w-2xl text-slate-600 dark:text-slate-300">Unlock lifetime access to the full private SaaS workspace. Your account stays blocked until backend payment verification completes and an active license is linked to your user record.</p>

        <div class="mt-8 grid gap-4 md:grid-cols-2">
            @foreach($features as $feature)
                <div class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $feature }}</div>
            @endforeach
        </div>

        <div class="mt-8 rounded-2xl border border-slate-200 p-5 dark:border-slate-800">
            <h2 class="text-lg font-semibold">Security notes</h2>
            <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                <li>• The frontend never activates accounts by itself.</li>
                <li>• PayPal approval returns to the backend, which verifies the order and captures payment server-side.</li>
                <li>• Webhooks can additionally mark orders complete when a valid PayPal signature is present.</li>
            </ul>
        </div>
    </section>

    <aside class="space-y-6">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-semibold">Account billing</h2>
            <div class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between"><span>Status</span><strong>{{ $user->hasPaid() ? 'Paid' : 'Unpaid' }}</strong></div>
                <div class="flex justify-between"><span>Plan</span><strong>{{ $planName }}</strong></div>
                <div class="flex justify-between"><span>License key</span><strong class="max-w-[160px] truncate">{{ $license?->license_key ?? 'Pending' }}</strong></div>
                <div class="flex justify-between"><span>Paid at</span><strong>{{ $user->paid_at ?? 'Not paid' }}</strong></div>
            </div>

            @if(! $user->hasPaid())
                <form method="POST" action="{{ route('billing.checkout') }}" class="mt-6">
                    @csrf
                    <button class="w-full rounded-2xl bg-blue-600 px-4 py-3 font-semibold text-white transition hover:bg-blue-700">Pay $299 with PayPal</button>
                </form>
                <p class="mt-3 text-xs text-slate-500">You will be redirected to PayPal, then back to InfiMal for verified activation.</p>
            @else
                <a href="{{ route('dashboard') }}" class="mt-6 inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3 font-semibold text-white">Go to dashboard</a>
            @endif
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-semibold">Payment history</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="py-2">Payment ID</th>
                            <th>Status</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                            <tr class="border-t border-slate-200 dark:border-slate-800">
                                <td class="py-3">{{ $payment->payment_id }}</td>
                                <td>{{ ucfirst($payment->status) }}</td>
                                <td>${{ number_format($payment->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-4 text-slate-500">No transactions recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </aside>
</div>
@endsection
