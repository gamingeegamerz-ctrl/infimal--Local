@extends('layouts.saas')
@section('title', 'Dashboard · InfiMal')
@section('content')
<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach ([
        'Total campaigns' => $stats['total_campaigns'],
        'Total subscribers' => $stats['total_subscribers'],
        'Emails sent' => $stats['emails_sent'],
        'Open rate' => $stats['open_rate'].'%',
        'Click rate' => $stats['click_rate'].'%',
        'Bounce rate' => $stats['bounce_rate'].'%',
    ] as $label => $value)
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $label }}</p>
            <p class="mt-2 text-3xl font-bold">{{ $value }}</p>
        </div>
    @endforeach
</div>

<div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-lg font-semibold">Performance overview</h2>
        <div class="mt-6 space-y-4">
            @foreach ([
                'Open rate' => $stats['open_rate'],
                'Click rate' => $stats['click_rate'],
                'Bounce rate' => $stats['bounce_rate'],
            ] as $label => $percent)
                <div>
                    <div class="mb-2 flex items-center justify-between text-sm"><span>{{ $label }}</span><span>{{ $percent }}%</span></div>
                    <div class="h-3 rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-3 rounded-full {{ $label === 'Bounce rate' ? 'bg-rose-500' : 'bg-blue-600' }}" style="width: {{ min(100, max(0, (float) $percent)) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-lg font-semibold">Sending setup</h2>
        <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
            <div class="flex justify-between"><span>SMTP accounts</span><strong>{{ $stats['smtp_accounts'] }}</strong></div>
            <div class="flex justify-between"><span>Unread messages</span><strong>{{ $stats['unread_messages'] }}</strong></div>
            <div class="flex justify-between"><span>Theme mode</span><strong>Global toggle enabled</strong></div>
        </div>
    </section>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-lg font-semibold">Recent campaigns</h2>
        <div class="mt-4 space-y-3">
            @forelse($recentCampaigns as $campaign)
                <div class="flex items-center justify-between rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                    <div>
                        <p class="font-medium">{{ $campaign->name }}</p>
                        <p class="text-sm text-slate-500">{{ $campaign->subject }}</p>
                    </div>
                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-300">{{ $campaign->status }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">No campaigns yet.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-lg font-semibold">Recent subscribers</h2>
        <div class="mt-4 space-y-3">
            @forelse($recentSubscribers as $subscriber)
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                    <p class="font-medium">{{ $subscriber->email }}</p>
                    <p class="text-sm text-slate-500">{{ trim(($subscriber->first_name ?? '').' '.($subscriber->last_name ?? '')) ?: 'No name' }}</p>
                </div>
            @empty
                <p class="text-sm text-slate-500">No subscribers yet.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
