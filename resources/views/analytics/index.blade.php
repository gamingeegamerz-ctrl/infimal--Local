@extends('layouts.saas')
@section('title', 'Analytics · InfiMal')
@section('content')
<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach (['Campaigns' => $overview['total_campaigns'], 'Subscribers' => $overview['total_subscribers'], 'Emails sent' => $overview['emails_sent'], 'Open rate' => $overview['open_rate'].'%', 'Click rate' => $overview['click_rate'].'%', 'Bounce rate' => $overview['bounce_rate'].'%'] as $label => $value)
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-slate-500">{{ $label }}</p>
            <p class="mt-2 text-3xl font-bold">{{ $value }}</p>
        </div>
    @endforeach
</div>

<div class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-lg font-semibold">Rate comparison</h2>
        <div class="mt-5 space-y-4">
            @foreach (['Open rate' => $overview['open_rate'], 'Click rate' => $overview['click_rate'], 'Bounce rate' => $overview['bounce_rate']] as $label => $value)
                <div>
                    <div class="mb-2 flex items-center justify-between text-sm"><span>{{ $label }}</span><span>{{ $value }}%</span></div>
                    <div class="h-3 rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-3 rounded-full {{ $label === 'Bounce rate' ? 'bg-rose-500' : 'bg-blue-600' }}" style="width: {{ min(100, max(0, (float) $value)) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-lg font-semibold">Recent email activity</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500"><th class="py-2">Recipient</th><th>Status</th><th>Opened</th><th>Clicked</th></tr>
                </thead>
                <tbody>
                    @forelse($recent_activity as $item)
                        <tr class="border-t border-slate-200 dark:border-slate-800">
                            <td class="py-3">{{ $item->recipient_email ?? $item->to_email }}</td>
                            <td>{{ $item->status }}</td>
                            <td>{{ $item->opened ? 'Yes' : 'No' }}</td>
                            <td>{{ $item->clicked ? 'Yes' : 'No' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-slate-500">No analytics yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
