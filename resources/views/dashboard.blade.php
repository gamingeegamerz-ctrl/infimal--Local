@extends('layouts.saas')
@section('title', 'Dashboard · InfiMal')
@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    @foreach ([
        'Total campaigns' => $stats['total_campaigns'],
        'Total subscribers' => $stats['total_subscribers'],
        'Emails sent' => $stats['emails_sent'],
        'SMTP accounts' => $stats['smtp_accounts'],
        'Open rate' => $stats['open_rate'].'%',
        'Click rate' => $stats['click_rate'].'%',
        'Bounce rate' => $stats['bounce_rate'].'%',
        'Unread messages' => $stats['unread_messages'],
    ] as $label => $value)
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $label }}</p>
            <p class="mt-2 text-3xl font-bold">{{ $value }}</p>
        </div>
    @endforeach
</div>
<div class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Performance snapshot</h2><span class="text-sm text-slate-500">Open / click / bounce</span></div>
        <div class="mt-6 h-80"><canvas id="overviewChart"></canvas></div>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-lg font-semibold">Delivery health</h2>
        <div class="mt-6 h-80"><canvas id="rateChart"></canvas></div>
    </section>
</div>
<div class="grid gap-6 lg:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-lg font-semibold">Recent campaigns</h2>
        <div class="mt-4 space-y-3">@forelse($recentCampaigns as $campaign)<div class="flex items-center justify-between rounded-xl bg-slate-50 p-3 dark:bg-slate-800"><div><p class="font-medium">{{ $campaign->name }}</p><p class="text-sm text-slate-500">{{ $campaign->subject }}</p></div><span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-300">{{ $campaign->status }}</span></div>@empty <p class="text-sm text-slate-500">No campaigns yet.</p>@endforelse</div>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-lg font-semibold">Recent subscribers</h2>
        <div class="mt-4 space-y-3">@forelse($recentSubscribers as $subscriber)<div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800"><p class="font-medium">{{ $subscriber->email }}</p><p class="text-sm text-slate-500">{{ trim(($subscriber->first_name ?? '').' '.($subscriber->last_name ?? '')) ?: 'No name' }}</p></div>@empty <p class="text-sm text-slate-500">No subscribers yet.</p>@endforelse</div>
    </section>
</div>
<script>
new Chart(document.getElementById('overviewChart'), {
    type: 'bar',
    data: {
        labels: ['Campaigns', 'Subscribers', 'Emails sent'],
        datasets: [{
            label: 'Account volume',
            data: [{{ $stats['total_campaigns'] }}, {{ $stats['total_subscribers'] }}, {{ $stats['emails_sent'] }}],
            backgroundColor: ['#2563eb', '#60a5fa', '#93c5fd'],
            borderRadius: 12,
        }]
    },
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}}
});
new Chart(document.getElementById('rateChart'), {
    type: 'doughnut',
    data: {
        labels: ['Open rate', 'Click rate', 'Bounce rate'],
        datasets: [{
            data: [{{ $stats['open_rate'] }}, {{ $stats['click_rate'] }}, {{ $stats['bounce_rate'] }}],
            backgroundColor: ['#2563eb', '#0ea5e9', '#ef4444']
        }]
    },
    options: {responsive: true, maintainAspectRatio: false}
});
</script>
@endsection
